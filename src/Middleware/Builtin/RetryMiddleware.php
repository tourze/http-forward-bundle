<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Builtin;

use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;

class RetryMiddleware extends AbstractMiddleware
{
    protected int $priority = 10;

    /**
     * @var array<int, int>
     */
    private array $retryableStatusCodes = [429, 500, 502, 503, 504];

    public function processResponse(Response $response, array $config = []): Response
    {
        $retryableStatusCodes = is_array($config['retryable_status_codes'] ?? null) ? $config['retryable_status_codes'] : $this->retryableStatusCodes;
        $maxRetries = is_int($config['max_retries'] ?? null) ? $config['max_retries'] : 3;
        $retryHeader = is_string($config['retry_header'] ?? null) ? $config['retry_header'] : 'X-Retry-Count';

        $statusCode = $response->getStatusCode();

        if (in_array($statusCode, $retryableStatusCodes, true)) {
            $currentRetries = (int) $response->headers->get($retryHeader, '0');

            if ($currentRetries < $maxRetries) {
                $response->headers->set($retryHeader, (string) ($currentRetries + 1));
                $response->headers->set('X-Should-Retry', 'true');
            } else {
                $response->headers->set('X-Max-Retries-Reached', 'true');
            }
        }

        return $response;
    }

    public static function getServiceAlias(): string
    {
        return 'retry';
    }
}
