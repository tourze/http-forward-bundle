<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\ForwardLogStatus;
use Tourze\HttpForwardBundle\Event\AfterForwardEvent;
use Tourze\HttpForwardBundle\Event\BeforeForwardEvent;
use Tourze\HttpForwardBundle\Event\FallbackTriggeredEvent;
use Tourze\HttpForwardBundle\Exception\NoHealthyBackendException;
use Tourze\HttpForwardBundle\Middleware\MiddlewareChain;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;
use Tourze\HttpForwardBundle\Repository\ForwardLogRepository;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'http_forward')]
readonly class ForwarderService
{
    private UrlBuilder $urlBuilder;

    private RequestExecutor $requestExecutor;

    private StreamForwarder $streamForwarder;

    private HeaderBuilder $headerBuilder;

    public function __construct(
        private HttpClientInterface $httpClient,
        private MiddlewareRegistry $middlewareRegistry,
        private EventDispatcherInterface $eventDispatcher,
        private ForwardLogRepository $logRepository,
        private LoggerInterface $logger,
        private LoadBalanceService $loadBalanceService,
    ) {
        $this->urlBuilder = new UrlBuilder($loadBalanceService);
        $this->headerBuilder = new HeaderBuilder();
        $this->requestExecutor = new RequestExecutor($httpClient, $eventDispatcher, $this->headerBuilder, $logRepository);
        $this->streamForwarder = new StreamForwarder($httpClient, $logRepository, $logger);
    }

    public function forward(Request $request, ForwardRule $rule): Response
    {
        if ($rule->isStreamEnabled() || $this->shouldUseStream($request)) {
            return $this->forwardStream($request, $rule);
        }

        $this->eventDispatcher->dispatch(
            new BeforeForwardEvent($request, $rule)
        );

        $middlewareChain = $this->createMiddlewareChain($rule);
        $log = new ForwardLog();
        $processedRequest = $this->processRequestThroughMiddlewares($request, $rule, $middlewareChain, $log);

        try {
            $backend = $this->loadBalanceService->selectBackend($rule, $processedRequest);

            $this->initializeLog($log, $processedRequest, $rule, $backend);
            $log->setStatus(ForwardLogStatus::PENDING);
            $this->logRepository->save($log, true);

            $log->setStatus(ForwardLogStatus::SENDING);
            $log->setSendTime(new \DateTimeImmutable());
            $this->logRepository->update($log, true);

            $targetUrl = $this->buildTargetUrl($processedRequest, $rule, $backend);
            $response = $this->executeForward($processedRequest, $targetUrl, $rule, $log, $backend);

            $processedResponse = $this->processResponseThroughMiddlewares($response, $rule, $middlewareChain);

            $this->completeLog($log, $processedResponse, $backend);

            $this->eventDispatcher->dispatch(
                new AfterForwardEvent($processedRequest, $processedResponse, $rule, $log)
            );

            return $processedResponse;
        } catch (NoHealthyBackendException $e) {
            $this->handleNoBackendError($log, $e, $rule);

            return $this->handleFallback($rule, $e);
        } catch (\Exception $e) {
            $this->handleForwardError($log, $e);

            return $this->handleFallback($rule, $e);
        }
    }

    private function initializeLog(ForwardLog $log, Request $request, ForwardRule $rule, Backend $backend): void
    {
        $log->setRule($rule);
        $log->setMethod($request->getMethod());
        $log->setPath($request->getPathInfo());
        $log->setClientIp($request->getClientIp() ?? '');
        $log->setUserAgent($request->headers->get('User-Agent') ?? '');

        $originalHeaders = $this->normalizeHeaders($request->headers->all());
        $log->setOriginalRequestHeaders($originalHeaders);
        $log->setProcessedRequestHeaders($this->normalizeHeaders($request->headers->all()));

        $targetUrl = $this->buildTargetUrl($request, $rule, $backend);
        $log->setTargetUrl($targetUrl);

        $log->setBackend($backend);
        $log->setBackendName($backend->getName());
        $log->setBackendUrl($backend->getUrl());
        $log->setLoadBalanceStrategy($rule->getLoadBalanceStrategy());

        $log->setRuleName($rule->getName());
        $log->setRuleSourcePath($rule->getSourcePath());
        $log->setRuleMiddlewares($rule->getMiddlewares());

        $availableBackends = [];
        foreach ($rule->getHealthyBackends() as $availableBackend) {
            $availableBackends[] = [
                'id' => $availableBackend->getId(),
                'name' => $availableBackend->getName(),
                'url' => $availableBackend->getUrl(),
                'weight' => $availableBackend->getWeight(),
                'status' => $availableBackend->getStatus(),
            ];
        }
        $log->setAvailableBackends($availableBackends);

        $content = $request->getContent();
        if ('' !== $content) {
            $log->setRequestBody($content);
        }
    }

    private function createMiddlewareChain(ForwardRule $rule): MiddlewareChain
    {
        $middlewareNames = array_filter(
            array_map(
                fn ($m) => is_string($m['name'] ?? null) ? $m['name'] : null,
                $rule->getMiddlewares()
            ),
            fn ($name) => null !== $name
        );

        $middlewares = $this->middlewareRegistry->getByNames($middlewareNames);
        $sortedMiddlewares = $this->middlewareRegistry->sortByPriority($middlewares);

        return new MiddlewareChain($sortedMiddlewares);
    }

    private function processRequestThroughMiddlewares(Request $request, ForwardRule $rule, MiddlewareChain $chain, ForwardLog $log): Request
    {
        $configs = $this->buildMiddlewareConfigs($rule->getMiddlewares());

        return $chain->processRequest($request, $log, $configs);
    }

    private function buildTargetUrl(Request $request, ForwardRule $rule, Backend $backend): string
    {
        return $this->urlBuilder->buildWithBackend($request, $rule, $backend);
    }

    private function executeForward(Request $request, string $targetUrl, ForwardRule $rule, ForwardLog $log, Backend $backend): Response
    {
        $originalTimeout = $rule->getTimeout();
        $rule->setTimeout($backend->getTimeout());

        try {
            return $this->requestExecutor->execute($request, $targetUrl, $rule, $log);
        } finally {
            $rule->setTimeout($originalTimeout);
        }
    }

    private function processResponseThroughMiddlewares(Response $response, ForwardRule $rule, MiddlewareChain $chain): Response
    {
        $configs = $this->buildMiddlewareConfigs($rule->getMiddlewares());

        return $chain->processResponse($response, $configs);
    }

    private function completeLog(ForwardLog $log, Response $response, Backend $backend): void
    {
        $log->setStatus(ForwardLogStatus::COMPLETED);
        $log->setCompleteTime(new \DateTimeImmutable());
        $log->setResponseStatus($response->getStatusCode());
        $log->setResponseHeaders($this->normalizeHeaders($response->headers->all()));

        $content = $response->getContent();
        if (false !== $content && '' !== $content && strlen($content) <= 10000) {
            $log->setResponseBody($content);
        }

        $sendTime = $log->getSendTime();
        $completeTime = $log->getCompleteTime();
        if (null !== $sendTime && null !== $completeTime) {
            $backendResponseTime = (int) (($completeTime->getTimestamp() * 1000 + (int) $completeTime->format('v'))
                - ($sendTime->getTimestamp() * 1000 + (int) $sendTime->format('v')));
            $log->setBackendResponseTime($backendResponseTime);
        }

        $this->logRepository->update($log, true);
    }

    private function handleNoBackendError(ForwardLog $log, NoHealthyBackendException $e, ForwardRule $rule): void
    {
        $this->logger->error('No healthy backends available', [
            'rule' => $rule->getId(),
            'rule_name' => $rule->getName(),
            'total_backends' => $rule->getBackends()->count(),
            'exception' => $e,
        ]);

        $log->setStatus(ForwardLogStatus::FAILED);
        $log->setCompleteTime(new \DateTimeImmutable());
        $log->setErrorMessage($e->getMessage());
        $log->setFallbackUsed(true);
        $log->setFallbackDetails([
            'triggered_at' => new \DateTimeImmutable(),
            'reason' => 'No healthy backends available',
            'total_backends' => $rule->getBackends()->count(),
            'healthy_backends' => count($rule->getHealthyBackends()),
        ]);
        $this->logRepository->update($log, true);
    }

    private function handleForwardError(ForwardLog $log, \Exception $e): void
    {
        $this->logger->error('Forward failed', [
            'rule' => $log->getRule()?->getId(),
            'error' => $e->getMessage(),
        ]);

        $log->setStatus(ForwardLogStatus::FAILED);
        $log->setCompleteTime(new \DateTimeImmutable());
        $log->setErrorMessage($e->getMessage());
        $this->logRepository->update($log, true);
    }

    private function handleFallback(ForwardRule $rule, \Exception $exception): Response
    {
        $fallbackType = $rule->getFallbackType();
        $fallbackConfig = $rule->getFallbackConfig() ?? [];

        $this->eventDispatcher->dispatch(
            new FallbackTriggeredEvent($rule, $exception, $fallbackType)
        );

        switch ($fallbackType) {
            case 'STATIC':
                $content = is_string($fallbackConfig['content'] ?? null) ? $fallbackConfig['content'] : 'Service temporarily unavailable';
                $status = is_int($fallbackConfig['status'] ?? null) ? $fallbackConfig['status'] : 503;

                return new Response($content, $status, ['X-Fallback' => 'true']);

            case 'BACKUP':
                if (isset($fallbackConfig['url'])) {
                    $url = is_string($fallbackConfig['url']) ? $fallbackConfig['url'] : '';
                    if ('' !== $url) {
                        try {
                            $backupResponse = $this->httpClient->request('GET', $url);

                            return new Response(
                                $backupResponse->getContent(),
                                $backupResponse->getStatusCode(),
                                $backupResponse->getHeaders(false)
                            );
                        } catch (\Exception $e) {
                            $this->logger->error('Backup fallback failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                break;
        }

        return new Response('Service unavailable', 503, ['X-Fallback' => 'true']);
    }

    private function forwardStream(Request $request, ForwardRule $rule): Response
    {
        $this->eventDispatcher->dispatch(
            new BeforeForwardEvent($request, $rule)
        );

        try {
            $backend = $this->loadBalanceService->selectBackend($rule, $request);
        } catch (NoHealthyBackendException $e) {
            return $this->handleFallback($rule, $e);
        }

        $middlewareChain = $this->createMiddlewareChain($rule);
        $log = new ForwardLog();
        $processedRequest = $this->processRequestThroughMiddlewares($request, $rule, $middlewareChain, $log);

        $this->initializeLog($log, $processedRequest, $rule, $backend);
        $log->setStatus(ForwardLogStatus::PENDING);
        $this->logRepository->save($log, true);

        $response = $this->streamForwarder->createStreamResponse(
            $processedRequest,
            $this->buildTargetUrl($processedRequest, $rule, $backend),
            $rule,
            $log,
            microtime(true)
        );

        $this->eventDispatcher->dispatch(
            new AfterForwardEvent($processedRequest, $response, $rule, $log)
        );

        return $response;
    }

    private function shouldUseStream(Request $request): bool
    {
        // Heuristics to auto-enable streaming for SSE-like requests (e.g., OpenAI)
        $accept = $request->headers->get('Accept') ?? '';
        if (false !== stripos($accept, 'text/event-stream')) {
            return true;
        }

        // Query parameter toggle
        $streamQuery = $request->query->get('stream');
        if (is_string($streamQuery) && in_array(strtolower($streamQuery), ['1', 'true', 'yes'], true)) {
            return true;
        }

        // Body hint: JSON with "stream": true
        $contentType = $request->headers->get('Content-Type') ?? '';
        if (false !== stripos($contentType, 'application/json')) {
            $body = $request->getContent();
            if ('' !== $body && 1 === preg_match('/\"stream\"\s*:\s*true/i', $body)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<string|null>> $headers
     * @return array<string, string|array<string>>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $filteredValues = array_filter($values, static fn ($value): bool => null !== $value);

            if ([] === $filteredValues) {
                continue;
            }

            if (1 === count($filteredValues)) {
                $normalized[$name] = reset($filteredValues);
            } else {
                $normalized[$name] = array_values($filteredValues);
            }
        }

        return $normalized;
    }

    /**
     * 构建中间件配置映射
     *
     * @param array<array<string, mixed>> $middlewares
     * @return array<string, array<string, mixed>>
     */
    private function buildMiddlewareConfigs(array $middlewares): array
    {
        $configs = [];

        foreach ($middlewares as $middleware) {
            if (!isset($middleware['name']) || !is_string($middleware['name'])) {
                continue;
            }

            $config = $middleware['config'] ?? [];
            if (!is_array($config)) {
                continue;
            }

            // 确保 config是字符串索引的数组
            $normalizedConfig = [];
            foreach ($config as $key => $value) {
                if (is_string($key)) {
                    $normalizedConfig[$key] = $value;
                }
            }

            $configs[$middleware['name']] = $normalizedConfig;
        }

        return $configs;
    }
}
