<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Header;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class RemoveHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 85;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $headers = $config['headers'] ?? [];

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (is_string($header)) {
                    $request->headers->remove($header);
                }
            }
        }

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'remove_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '移除请求头',
            'description' => '移除指定的HTTP请求头',
            'priority' => 85,
            'fields' => [
                'headers' => [
                    'type' => 'array',
                    'label' => '请求头名称',
                    'default' => [],
                    'required' => true,
                ],
            ],
        ];
    }
}
