<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Header;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class AddHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 90;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $headers = $config['headers'] ?? [];

        if (is_array($headers)) {
            $this->setRequestHeaders($request, $headers);
        }

        return $request;
    }

    /**
     * @param array<mixed, mixed> $headers
     */
    private function setRequestHeaders(Request $request, array $headers): void
    {
        foreach ($headers as $header => $value) {
            if ($this->isValidHeader($header, $value) && is_string($header)) {
                /** @var string|array<mixed>|null $headerValue */
                $headerValue = $value;
                $this->setHeaderValue($request, $header, $headerValue);
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
    private function setHeaderValue(Request $request, string $header, $value): void
    {
        if (is_array($value)) {
            /** @var array<string> $stringArray */
            $stringArray = array_map(static fn (mixed $item): string => is_scalar($item) || null === $item ? (string) $item : '', $value);
            $request->headers->set($header, $stringArray);
        } else {
            $request->headers->set($header, $value);
        }
    }

    public static function getServiceAlias(): string
    {
        return 'add_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '添加请求头',
            'description' => '添加或设置HTTP请求头',
            'priority' => 90,
            'fields' => [
                'headers' => [
                    'type' => 'collection',
                    'label' => '请求头',
                    'default' => [],
                    'required' => true,
                ],
            ],
        ];
    }
}
