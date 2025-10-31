<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardLog;

interface MiddlewareInterface
{
    /**
     * 在转发前处理请求
     *
     * @param array<string, mixed> $config
     */
    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request;

    /**
     * 从目标接收响应后处理响应
     *
     * @param array<string, mixed> $config
     */
    public function processResponse(Response $response, array $config = []): Response;

    /**
     * 获取此中间件的优先级（数值越高越早执行）
     */
    public function getPriority(): int;

    /**
     * 检查此中间件是否启用
     */
    public function isEnabled(): bool;

    /**
     * 获取服务别名，用于标记迭代器索引
     */
    public static function getServiceAlias(): string;
}
