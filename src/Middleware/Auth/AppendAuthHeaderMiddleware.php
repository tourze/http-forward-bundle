<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Auth;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class AppendAuthHeaderMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 100;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $scheme = is_string($config['scheme'] ?? null) ? $config['scheme'] : 'Bearer';
        $token = is_string($config['token'] ?? null) ? $config['token'] : '';

        if ('' === $token) {
            return $request;
        }

        $existing = $request->headers->get('Authorization', '');
        if ('' !== $existing) {
            $request->headers->set('Authorization', $existing . ', ' . $scheme . ' ' . $token);
        } else {
            $request->headers->set('Authorization', $scheme . ' ' . $token);
        }

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'append_auth_header';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '追加认证头',
            'description' => '向现有Authorization头追加内容',
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
