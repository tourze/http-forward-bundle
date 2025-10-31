<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Header;

use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class RemoveResponseHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 35;

    public function processResponse(Response $response, array $config = []): Response
    {
        $headers = $config['headers'] ?? [];

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (is_string($header)) {
                    $response->headers->remove($header);
                }
            }
        }

        return $response;
    }

    public static function getServiceAlias(): string
    {
        return 'remove_response_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '移除响应头',
            'description' => '移除指定的HTTP响应头',
            'priority' => 35,
            'fields' => [
                'headers' => [
                    'type' => 'array',
                    'label' => '响应头名称',
                    'default' => [],
                    'required' => true,
                ],
            ],
        ];
    }
}
