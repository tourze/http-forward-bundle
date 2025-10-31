<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Exception\MiddlewareException;

abstract class AbstractMiddleware implements MiddlewareInterface
{
    protected int $priority = 0;

    protected bool $enabled = true;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        return $request;
    }

    public function processResponse(Response $response, array $config = []): Response
    {
        return $response;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public static function getServiceAlias(): string
    {
        $className = get_called_class();
        $parts = explode('\\', $className);
        $name = end($parts);

        // end() always returns string for non-empty array from explode('\\', class-string)
        $name = preg_replace('/Middleware$/', '', $name) ?? $name;
        $name = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?? $name;

        return strtolower($name);
    }
}
