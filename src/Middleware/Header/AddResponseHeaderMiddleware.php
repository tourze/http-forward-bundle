<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Header;

use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class AddResponseHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 40;

    public function processResponse(Response $response, array $config = []): Response
    {
        $headers = $config['headers'] ?? [];

        if (is_array($headers)) {
            $this->setResponseHeaders($response, $headers);
        }

        return $response;
    }

    /**
     * @param array<mixed, mixed> $headers
     */
    private function setResponseHeaders(Response $response, array $headers): void
    {
        foreach ($headers as $header => $value) {
            if ($this->isValidHeader($header, $value) && is_string($header)) {
                /** @var string|array<mixed>|null $headerValue */
                $headerValue = $value;
                $this->setHeaderValue($response, $header, $headerValue);
            }
        }
    }

    private function isValidHeader(mixed $header, mixed $value): bool
    {
        return is_string($header) && (is_string($value) || is_array($value) || null === $value);
    }

    /**
     * @param string|array<mixed>|null $value
     */
    private function setHeaderValue(Response $response, string $header, $value): void
    {
        if (is_array($value)) {
            /** @var array<string> $stringArray */
            $stringArray = array_map(static fn (mixed $item): string => is_scalar($item) || null === $item ? (string) $item : '', $value);
            $response->headers->set($header, $stringArray);
        } else {
            $response->headers->set($header, $value);
        }
    }

    public static function getServiceAlias(): string
    {
        return 'add_response_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '添加响应头',
            'description' => '添加或设置HTTP响应头',
            'priority' => 40,
            'fields' => [
                'headers' => [
                    'type' => 'collection',
                    'label' => '响应头',
                    'default' => [],
                    'required' => true,
                ],
            ],
        ];
    }
}
