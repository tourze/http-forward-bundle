<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Builtin;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;

class AuthHeaderMiddleware extends AbstractMiddleware
{
    protected int $priority = 100;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $action = is_string($config['action'] ?? null) ? $config['action'] : 'add';
        $scheme = is_string($config['scheme'] ?? null) ? $config['scheme'] : 'Bearer';
        $token = is_string($config['token'] ?? null) ? $config['token'] : '';

        if ('' === $token) {
            return $request;
        }

        switch ($action) {
            case 'add':
            case 'replace':
                $request->headers->set('Authorization', $scheme . ' ' . $token);
                break;
            case 'remove':
                $request->headers->remove('Authorization');
                break;
            case 'append':
                $existing = $request->headers->get('Authorization', '');
                if ('' !== $existing) {
                    $request->headers->set('Authorization', $existing . ', ' . $scheme . ' ' . $token);
                } else {
                    $request->headers->set('Authorization', $scheme . ' ' . $token);
                }
                break;
        }

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'auth_header';
    }
}
