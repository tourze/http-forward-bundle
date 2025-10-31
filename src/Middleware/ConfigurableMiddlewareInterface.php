<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware;

interface ConfigurableMiddlewareInterface extends MiddlewareInterface
{
    /**
     * 获取中间件的配置模板
     *
     * @return array<string, mixed>
     */
    public static function getConfigTemplate(): array;
}
