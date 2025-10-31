<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Header;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class RenameHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 80;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $mappings = $config['mappings'] ?? [];

        if (is_array($mappings)) {
            foreach ($mappings as $oldHeader => $newHeader) {
                if (is_string($oldHeader) && is_string($newHeader) && $request->headers->has($oldHeader)) {
                    $value = $request->headers->get($oldHeader);
                    $request->headers->set($newHeader, $value ?? '');
                    $request->headers->remove($oldHeader);
                }
            }
        }

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'rename_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '重命名请求头',
            'description' => '将请求头从旧名称重命名为新名称',
            'priority' => 80,
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
