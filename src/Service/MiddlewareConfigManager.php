<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;

#[Autoconfigure(public: true)]
class MiddlewareConfigManager
{
    public function __construct(
        private readonly MiddlewareRegistry $middlewareRegistry,
        private readonly MiddlewareTemplateManager $templateManager,
        private readonly MiddlewareValidator $validator,
    ) {
    }

    /**
     * 获取所有注册的中间件
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableMiddlewares(): array
    {
        $allMiddlewares = $this->middlewareRegistry->all();
        $templates = $this->templateManager->getMiddlewareConfigTemplates();
        $result = [];

        foreach ($allMiddlewares as $alias => $middleware) {
            $template = $templates[$alias] ?? [
                'label' => ucfirst(str_replace('_', ' ', $alias)),
                'description' => sprintf('%s中间件', $alias),
                'priority' => $middleware->getPriority(),
                'fields' => [],
            ];

            $result[$alias] = array_merge($template, [
                'enabled' => $middleware->isEnabled(),
                'priority' => $middleware->getPriority(),
            ]);
        }

        return $result;
    }

    /**
     * 获取所有可用中间件的配置模板
     *
     * @return array<string, array<string, mixed>>
     */
    public function getMiddlewareConfigTemplates(): array
    {
        return $this->templateManager->getMiddlewareConfigTemplates();
    }

    /**
     * 验证中间件配置
     *
     * @param array<array<string, mixed>> $middlewares
     * @return array<string> 验证错误信息
     */
    public function validateMiddlewareConfig(array $middlewares): array
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        return $this->validator->validateMiddlewareConfig($middlewares, $templates);
    }

    /**
     * 获取默认的中间件配置
     *
     * @param string $middlewareName
     * @return array<string, mixed>
     */
    public function getDefaultConfig(string $middlewareName): array
    {
        return $this->templateManager->getDefaultConfig($middlewareName);
    }

    /**
     * 格式化中间件配置用于前端显示
     *
     * @param array<array<string, mixed>> $middlewares
     * @return array<int, array<string, mixed>>
     */
    public function formatForFrontend(array $middlewares): array
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();
        $formatted = [];

        foreach ($middlewares as $middleware) {
            $name = is_string($middleware['name'] ?? null) ? $middleware['name'] : '';
            $config = $middleware['config'] ?? [];

            $formatted[] = [
                'name' => $name,
                'config' => $config,
                'template' => $templates[$name] ?? null,
                'enabled' => $middleware['enabled'] ?? true,
            ];
        }

        return $formatted;
    }
}
