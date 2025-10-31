<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Builtin;

use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;

class FallbackMiddleware extends AbstractMiddleware
{
    protected int $priority = 5;

    public function processResponse(Response $response, array $config = []): Response
    {
        if (!$this->shouldUseFallback($response, $config)) {
            return $response;
        }

        return $this->createFallbackResponse($response, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createFallbackResponse(Response $originalResponse, array $config): Response
    {
        $fallbackContent = is_string($config['fallback_content'] ?? null) ? $config['fallback_content'] : 'Service temporarily unavailable';
        $fallbackStatus = is_int($config['fallback_status'] ?? null) ? $config['fallback_status'] : 503;
        $fallbackHeaders = $this->ensureStringArray($config['fallback_headers'] ?? []);

        $fallbackResponse = new Response(
            $fallbackContent,
            $fallbackStatus,
            array_merge(['X-Fallback' => 'true'], $fallbackHeaders)
        );

        if ((bool) ($config['preserve_original_headers'] ?? false)) {
            $this->preserveOriginalHeaders($originalResponse, $fallbackResponse);
        }

        return $fallbackResponse;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function shouldUseFallback(Response $response, array $config): bool
    {
        $fallbackStatusCodes = $this->ensureIntArray($config['fallback_status_codes'] ?? [500, 502, 503, 504]);

        return in_array($response->getStatusCode(), $fallbackStatusCodes, true);
    }

    private function preserveOriginalHeaders(Response $originalResponse, Response $fallbackResponse): void
    {
        foreach ($originalResponse->headers->all() as $header => $values) {
            if (!$fallbackResponse->headers->has($header)) {
                $fallbackResponse->headers->set($header, $values[0] ?? '');
            }
        }
    }

    /**
     * @param mixed $value
     * @return array<int>
     */
    private function ensureIntArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_filter(
            array_map(static fn ($v): int => is_numeric($v) ? (int) $v : 0, $value),
            static fn ($v): bool => $v > 0
        );
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function ensureStringArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $val) {
            $stringKey = is_string($key) ? $key : '';
            $stringVal = is_string($val) ? $val : '';
            if ('' !== $stringKey) {
                $result[$stringKey] = $stringVal;
            }
        }

        return $result;
    }

    public static function getServiceAlias(): string
    {
        return 'fallback';
    }
}
