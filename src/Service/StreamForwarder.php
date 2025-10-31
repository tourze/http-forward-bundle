<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\ForwardLogStatus;
use Tourze\HttpForwardBundle\Repository\ForwardLogRepository;

#[WithMonologChannel(channel: 'http_forward')]
final class StreamForwarder
{
    private const SSE_CONTENT_TYPE = 'text/event-stream; charset=utf-8';
    private const DEFAULT_SSE_ERROR = 'data: {"error": "Stream forward failed"}' . "\n\n";

    private const SKIP_HEADERS = ['transfer-encoding', 'content-encoding', 'content-length'];
    private const STREAMING_HEADERS = [
        'Cache-Control' => 'no-cache, no-transform',
        'X-Accel-Buffering' => 'no',
        'Connection' => 'keep-alive',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ForwardLogRepository $logRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createStreamResponse(
        Request $request,
        string $targetUrl,
        ForwardRule $rule,
        ForwardLog $log,
        float $startTime,
    ): Response {
        try {
            $log->setStatus(ForwardLogStatus::SENDING);
            $log->setSendTime(new \DateTimeImmutable());
            $this->logRepository->save($log, true);

            $httpResponse = $this->createUpstreamRequest($request, $targetUrl, $rule, $log);

            return $this->buildSuccessResponse($httpResponse, $rule, $log, $startTime, $request);
        } catch (\Exception $e) {
            return $this->buildErrorResponse($e, $request, $log, $rule, $startTime);
        }
    }

    private function createUpstreamRequest(Request $request, string $targetUrl, ForwardRule $rule, ForwardLog $log): ResponseInterface
    {
        $options = $this->buildStreamingOptions($request, $rule, $log);

        return $this->httpClient->request($request->getMethod(), $targetUrl, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStreamingOptions(Request $request, ForwardRule $rule, ForwardLog $log): array
    {
        $headers = $this->extractForwardHeaders($request, $log);

        return [
            'headers' => $headers,
            'timeout' => $rule->getTimeout(),
            'max_duration' => $rule->getTimeout(),
            'buffer' => false,
            'body' => '' !== $request->getContent() ? $request->getContent() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractForwardHeaders(Request $request, ForwardLog $log): array
    {
        $headers = [];

        // 使用中间件处理后的headers而不是原始请求headers
        $processedHeaders = $log->getProcessedRequestHeaders() ?? [];
        foreach ($processedHeaders as $key => $value) {
            if (!in_array(strtolower($key), ['host', 'content-length'], true)) {
                if (is_array($value)) {
                    $headers[$key] = $value[0] ?? '';
                } else {
                    $headers[$key] = $value;
                }
            }
        }

        $headers['X-Forwarded-For'] = $request->getClientIp() ?? '';
        $headers['X-Forwarded-Proto'] = $request->getScheme();
        $headers['X-Forwarded-Host'] = $request->getHost();

        return $headers;
    }

    private function buildSuccessResponse(
        ResponseInterface $httpResponse,
        ForwardRule $rule,
        ForwardLog $log,
        float $startTime,
        Request $request,
    ): StreamedResponse {
        $response = new StreamedResponse();

        $this->setStreamingHeaders($response);
        $this->propagateUpstreamHeaders($response, $httpResponse);
        $this->setContentTypeIfNeeded($response, $request);

        $response->setCallback(
            $this->createStreamingCallback($httpResponse, $rule, $log, $startTime)
        );

        return $response;
    }

    private function setStreamingHeaders(StreamedResponse $response): void
    {
        foreach (self::STREAMING_HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }
    }

    private function propagateUpstreamHeaders(StreamedResponse $response, ResponseInterface $httpResponse): void
    {
        foreach ($httpResponse->getHeaders(false) as $name => $values) {
            if (in_array(strtolower($name), self::SKIP_HEADERS, true)) {
                continue;
            }
            foreach ($values as $value) {
                $response->headers->set($name, $value, false);
            }
        }
    }

    private function setContentTypeIfNeeded(StreamedResponse $response, Request $request): void
    {
        if ($this->isSSERequest($request) && !$response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', self::SSE_CONTENT_TYPE);
        }
    }

    private function createStreamingCallback(
        ResponseInterface $httpResponse,
        ForwardRule $rule,
        ForwardLog $log,
        float $startTime,
    ): \Closure {
        return function () use ($httpResponse, $rule, $log, $startTime): void {
            $this->disableBuffering();

            // 先保存 header 和状态码
            $log->setResponseStatus($httpResponse->getStatusCode());
            $log->setResponseHeaders($this->normalizeHeaders($httpResponse->getHeaders(false)));
            $this->logRepository->save($log, true);

            try {
                $capturedContent = $this->streamContentAndCapture($httpResponse, $rule, $log);
                $this->completeSuccessLog($log, $httpResponse, $startTime, $capturedContent);
            } catch (\Exception $e) {
                $this->handleStreamError($e, $log, $startTime);
            }
        };
    }

    private function disableBuffering(): void
    {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        while (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_end_flush();
        }

        @flush();
    }

    private function streamContentAndCapture(ResponseInterface $httpResponse, ForwardRule $rule, ForwardLog $log): string
    {
        $bufferSize = $rule->getBufferSize();
        $stream = $this->httpClient->stream($httpResponse);
        $capturedContent = '';
        $firstByte = true;

        foreach ($stream as $chunk) {
            if ($this->isConnectionAborted()) {
                break;
            }

            $content = $chunk->getContent();
            if ('' !== $content) {
                // 记录首字节时间
                if ($firstByte) {
                    $log->setStatus(ForwardLogStatus::RECEIVING);
                    $log->setFirstByteTime(new \DateTimeImmutable());
                    $firstByte = false;
                }
                $log->setResponseBody($log->getResponseBody() . $content);
                $this->logRepository->save($log, true);

                echo $content;

                // 捕获响应内容（限制大小避免内存问题）
                if (strlen($capturedContent) <= 10000) {
                    $capturedContent .= $content;
                }

                if ($bufferSize > 0) {
                    @flush();
                }
            }
        }

        return $capturedContent;
    }

    private function isConnectionAborted(): bool
    {
        return function_exists('connection_aborted') && 0 !== connection_aborted();
    }

    private function completeSuccessLog(ForwardLog $log, ResponseInterface $httpResponse, float $startTime, string $capturedContent = ''): void
    {
        $log->setStatus(ForwardLogStatus::COMPLETED);
        $log->setCompleteTime(new \DateTimeImmutable());

        // 保存流式传输过程中捕获的响应内容
        if ('' !== $capturedContent && strlen($capturedContent) <= 10000) {
            $log->setResponseBody($capturedContent);
        }

        $this->logRepository->save($log, true);
    }

    private function handleStreamError(\Exception $e, ForwardLog $log, float $startTime): void
    {
        $this->logger->error('Stream forward failed', [
            'rule' => $log->getRule()?->getId(),
            'error' => $e->getMessage(),
        ]);

        $log->setStatus(ForwardLogStatus::FAILED);
        $log->setCompleteTime(new \DateTimeImmutable());
        $log->setErrorMessage($e->getMessage());
        $log->setResponseStatus(502);
        $this->logRepository->save($log, true);

        echo self::DEFAULT_SSE_ERROR;
        @flush();
    }

    private function buildErrorResponse(
        \Exception $e,
        Request $request,
        ForwardLog $log,
        ForwardRule $rule,
        float $startTime,
    ): Response {
        $this->logger->error('Stream setup failed', [
            'rule' => $rule->getId(),
            'error' => $e->getMessage(),
        ]);

        $this->completeErrorLog($log, $e, $startTime);

        if ($this->isSSERequest($request)) {
            return $this->createSSEErrorResponse($e);
        }

        return new Response(
            'Forward failed: ' . $e->getMessage(),
            502,
            ['X-Forward-Error' => 'true']
        );
    }

    private function completeErrorLog(ForwardLog $log, \Exception $e, float $startTime): void
    {
        $log->setErrorMessage($e->getMessage());
        $log->setResponseStatus(502);
        $log->setDurationMs((int) ((microtime(true) - $startTime) * 1000));
        $this->logRepository->save($log, true);
    }

    private function createSSEErrorResponse(\Exception $e): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($e): void {
            echo 'data: {"error": "' . addslashes($e->getMessage()) . '"}\n\n';
        });

        $this->setStreamingHeaders($response);
        $response->headers->set('Content-Type', self::SSE_CONTENT_TYPE);

        return $response;
    }

    private function isSSERequest(Request $request): bool
    {
        $accept = $request->headers->get('Accept') ?? '';

        return false !== stripos($accept, 'text/event-stream');
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
}
