<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Header;

use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class RenameResponseHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 30;

    public function processResponse(Response $response, array $config = []): Response
    {
        $mappings = $config['mappings'] ?? [];

        if (is_array($mappings)) {
            foreach ($mappings as $oldHeader => $newHeader) {
                if (is_string($oldHeader) && is_string($newHeader) && $response->headers->has($oldHeader)) {
                    $value = $response->headers->get($oldHeader);
                    $response->headers->set($newHeader, $value ?? '');
                    $response->headers->remove($oldHeader);
                }
            }
        }

        return $response;
    }

    public static function getServiceAlias(): string
    {
        return 'rename_response_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '重命名响应头',
            'description' => '将响应头从旧名称重命名为新名称',
            'priority' => 30,
            'fields' => [
                'mappings' => [
                    'type' => 'collection',
                    'label' => '映射关系',
                    'default' => [],
                    'required' => true,
                ],
            ],
        ];
    }
}
