<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Auth;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class SetAuthHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 100;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $scheme = is_string($config['scheme'] ?? null) ? $config['scheme'] : 'Bearer';
        $token = is_string($config['token'] ?? null) ? $config['token'] : '';

        if ('' === $token) {
            return $request;
        }

        $request->headers->set('Authorization', $scheme . ' ' . $token);

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'set_auth_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '设置认证头',
            'description' => '设置或替换HTTP Authorization头',
            'priority' => 100,
            'fields' => [
                'scheme' => [
                    'type' => 'text',
                    'label' => '认证方案',
                    'default' => 'Bearer',
                    'required' => false,
                ],
                'token' => [
                    'type' => 'text',
                    'label' => '认证令牌',
                    'default' => '',
                    'required' => true,
                ],
            ],
        ];
    }
}
