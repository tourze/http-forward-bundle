<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Auth;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class RemoveAuthHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 100;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $request->headers->remove('Authorization');

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'remove_auth_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '移除认证头',
            'description' => '移除HTTP Authorization头',
            'priority' => 100,
            'fields' => [],
        ];
    }
}
