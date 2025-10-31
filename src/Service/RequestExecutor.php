<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\ForwardLogStatus;
use Tourze\HttpForwardBundle\Event\ForwardEvents;
use Tourze\HttpForwardBundle\Event\RetryAttemptEvent;
use Tourze\HttpForwardBundle\Repository\ForwardLogRepository;

final readonly class RequestExecutor
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EventDispatcherInterface $eventDispatcher,
        private HeaderBuilder $headerBuilder,
        private ForwardLogRepository $logRepository,
    ) {
    }

    public function execute(Request $request, string $targetUrl, ForwardRule $rule, ForwardLog $log): Response
    {
        $retryState = new RetryState($rule->getRetryCount());

        while (!$retryState->isExhausted()) {
            $response = $this->attemptRequest($request, $targetUrl, $rule, $retryState, $log);

            if (null !== $response) {
                $log->setRetryCountUsed($retryState->getCurrentAttempt());

                return $response;
            }
        }

        throw $retryState->getLastException() ?? new \RuntimeException('Forward failed after retries');
    }

    private function attemptRequest(
        Request $request,
        string $targetUrl,
        ForwardRule $rule,
        RetryState $retryState,
        ForwardLog $log,
    ): ?Response {
        if ($retryState->shouldWait()) {
            $this->dispatchRetryEvent($rule, $retryState);
            $this->waitForRetry($rule);
        }

        try {
            $response = $this->makeHttpRequest($request, $targetUrl, $rule, $log);

            if ($this->shouldRetry($response, $retryState)) {
                $retryState->recordFailure(
                    new \RuntimeException('Server error: ' . $response->getStatusCode())
                );

                return null;
            }

            return $response;
        } catch (TransportExceptionInterface $e) {
            $retryState->recordFailure($e);

            return null;
        }
    }

    private function makeHttpRequest(Request $request, string $targetUrl, ForwardRule $rule, ForwardLog $log): Response
    {
        $options = $this->buildHttpClientOptions($request, $rule, $log);
        $httpResponse = $this->httpClient->request($request->getMethod(), $targetUrl, $options);

        return new Response(
            $httpResponse->getContent(throw: false),
            $httpResponse->getStatusCode(),
            $httpResponse->getHeaders(false)
        );
    }

    private function shouldRetry(Response $response, RetryState $retryState): bool
    {
        return $response->getStatusCode() >= 500 && !$retryState->isLastAttempt();
    }

    private function dispatchRetryEvent(ForwardRule $rule, RetryState $retryState): void
    {
        $lastException = $retryState->getLastException();
        $exception = $lastException instanceof \Exception ? $lastException : null;

        $this->eventDispatcher->dispatch(
            new RetryAttemptEvent($rule, $retryState->getCurrentAttempt(), $exception)
        );
    }

    private function waitForRetry(ForwardRule $rule): void
    {
        usleep($rule->getRetryInterval() * 1000);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHttpClientOptions(Request $request, ForwardRule $rule, ForwardLog $log): array
    {
        // 使用中间件处理后的headers而不是原始请求headers
        $processedHeaders = $log->getProcessedRequestHeaders() ?? [];
        $headers = $this->headerBuilder->buildForwardHeadersFromArray($processedHeaders);

        $options = [
            'headers' => $headers,
            'timeout' => $rule->getTimeout(),
            'max_duration' => $rule->getTimeout(),
            'on_progress' => function ($dlNow, $dlSize, $info) use ($log): void {
                // 记录首字节时间
                if ($dlNow > 0 && null === $log->getFirstByteTime()) {
                    $log->setStatus(ForwardLogStatus::RECEIVING);
                    $log->setFirstByteTime(new \DateTimeImmutable());
                    $this->logRepository->update($log, true);
                }
            },
        ];

        $content = $request->getContent();
        if ('' !== $content) {
            $options['body'] = $content;
        }

        return $options;
    }
}
