<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardLog;

#[Autoconfigure(public: true)]
class MiddlewareChain
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middlewares = [];

    /**
     * @param MiddlewareInterface[] $middlewares
     */
    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    /**
     * @param array<string, array<string, mixed>> $configs
     */
    public function processRequest(Request $request, ForwardLog $log, array $configs = []): Request
    {
        foreach ($this->middlewares as $middleware) {
            if (!$middleware->isEnabled()) {
                continue;
            }

            $middlewareName = $this->getMiddlewareName($middleware);
            $config = $configs[$middlewareName] ?? [];

            $request = $middleware->processRequest($request, $log, $config);
        }

        return $request;
    }

    private function getMiddlewareName(MiddlewareInterface $middleware): string
    {
        try {
            return $middleware::getServiceAlias();
        } catch (\Exception) {
            // Fallback for tests and other cases
            $className = get_class($middleware);
            $parts = explode('\\', $className);
            $name = end($parts);

            return str_replace('Middleware', '', $name);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $configs
     */
    public function processResponse(Response $response, array $configs = []): Response
    {
        $reversedMiddlewares = array_reverse($this->middlewares);

        foreach ($reversedMiddlewares as $middleware) {
            if (!$middleware->isEnabled()) {
                continue;
            }

            $middlewareName = $this->getMiddlewareName($middleware);
            $config = $configs[$middlewareName] ?? [];

            $response = $middleware->processResponse($response, $config);
        }

        return $response;
    }

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * @return MiddlewareInterface[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function clearMiddlewares(): void
    {
        $this->middlewares = [];
    }
}
