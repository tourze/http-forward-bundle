<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[Autoconfigure(public: true)]
class MiddlewareRegistry
{
    /**
     * @var array<string, MiddlewareInterface>
     */
    private array $middlewares = [];

    /**
     * @param iterable<MiddlewareInterface> $taggedMiddlewares
     */
    public function __construct(
        #[AutowireIterator(tag: 'http_forward.middleware')]
        iterable $taggedMiddlewares = [],
    ) {
        foreach ($taggedMiddlewares as $middleware) {
            $alias = $middleware::getServiceAlias();
            $this->register($alias, $middleware);
        }
    }

    public function register(string $name, MiddlewareInterface $middleware): void
    {
        $this->middlewares[$name] = $middleware;
    }

    public function get(string $name): ?MiddlewareInterface
    {
        return $this->middlewares[$name] ?? null;
    }

    /**
     * @return array<string, MiddlewareInterface>
     */
    public function all(): array
    {
        return $this->middlewares;
    }

    /**
     * @param array<string> $names
     * @return MiddlewareInterface[]
     */
    public function getByNames(array $names): array
    {
        $result = [];
        foreach ($names as $name) {
            if (isset($this->middlewares[$name])) {
                $result[] = $this->middlewares[$name];
            }
        }

        return $result;
    }

    /**
     * @return MiddlewareInterface[]
     */
    public function getEnabled(): array
    {
        return array_filter($this->middlewares, fn ($m) => $m->isEnabled());
    }

    /**
     * @param MiddlewareInterface[] $middlewares
     * @return MiddlewareInterface[]
     */
    public function sortByPriority(array $middlewares): array
    {
        usort($middlewares, function (MiddlewareInterface $a, MiddlewareInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        return $middlewares;
    }

    public function unregister(string $name): void
    {
        unset($this->middlewares[$name]);
    }

    public function clear(): void
    {
        $this->middlewares = [];
    }
}
