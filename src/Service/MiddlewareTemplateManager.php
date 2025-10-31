<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;
use Tourze\HttpForwardBundle\Middleware\MiddlewareInterface;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;

/**
 * 中间件模板管理器
 */
readonly class MiddlewareTemplateManager
{
    public function __construct(
        private MiddlewareRegistry $middlewareRegistry,
    ) {
    }

    /**
     * 获取所有可用中间件的配置模板
     *
     * @return array<string, array<string, mixed>>
     */
    public function getMiddlewareConfigTemplates(): array
    {
        $templates = [];
        $allMiddlewares = $this->middlewareRegistry->all();

        foreach ($allMiddlewares as $alias => $middleware) {
            // 检查中间件是否实现了ConfigurableMiddlewareInterface
            if ($middleware instanceof ConfigurableMiddlewareInterface) {
                $template = $middleware::getConfigTemplate();

                // 统一格式化配置模板
                $templates[$alias] = [
                    'label' => $template['label'] ?? ucfirst(str_replace('_', ' ', $alias)),
                    'description' => $template['description'] ?? sprintf('%s中间件', $alias),
                    'priority' => $template['priority'] ?? $middleware->getPriority(),
                    'fields' => $this->normalizeFields($template),
                ];
            } else {
                // 对于不实现ConfigurableMiddlewareInterface的中间件，提供默认配置
                $templates[$alias] = $this->getBuiltinMiddlewareTemplate($alias, $middleware);
            }
        }

        return $templates;
    }

    /**
     * 规范化中间件字段配置
     *
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    private function normalizeFields(array $template): array
    {
        // 处理不同格式的字段定义
        if (isset($template['fields']) && is_array($template['fields'])) {
            return $this->ensureStringKeyedArray($template['fields']);
        }

        if (isset($template['configSchema']) && is_array($template['configSchema'])) {
            return $this->ensureStringKeyedArray($template['configSchema']);
        }

        return [];
    }

    /**
     * @param array<mixed, mixed> $array
     * @return array<string, mixed>
     */
    private function ensureStringKeyedArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 获取内置中间件的配置模板
     *
     * @param string $alias
     * @param MiddlewareInterface $middleware
     * @return array<string, mixed>
     */
    private function getBuiltinMiddlewareTemplate(string $alias, $middleware): array
    {
        $builtinTemplates = [
            'access_key_auth' => [
                'label' => '访问密钥认证',
                'description' => '基于AccessKey的身份验证中间件',
                'priority' => 200,
                'fields' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'label' => '启用验证',
                        'default' => true,
                        'required' => false,
                    ],
                    'required' => [
                        'type' => 'boolean',
                        'label' => '必须提供Token',
                        'default' => true,
                        'required' => false,
                    ],
                    'fallback_mode' => [
                        'type' => 'choice',
                        'label' => '降级模式',
                        'choices' => [
                            '严格模式' => 'strict',
                            '宽松模式' => 'permissive',
                        ],
                        'default' => 'strict',
                        'required' => false,
                    ],
                ],
            ],
            'xml_to_json' => [
                'label' => 'XML转JSON',
                'description' => '将XML响应转换为JSON格式',
                'priority' => 70,
                'fields' => [],
            ],
            'retry' => [
                'label' => '重试机制',
                'description' => '失败请求的重试处理',
                'priority' => 60,
                'fields' => [],
            ],
            'fallback' => [
                'label' => '降级处理',
                'description' => '请求失败时的降级策略',
                'priority' => 50,
                'fields' => [],
            ],
        ];

        return $builtinTemplates[$alias] ?? [
            'label' => ucfirst(str_replace('_', ' ', $alias)),
            'description' => sprintf('%s中间件', $alias),
            'priority' => $middleware->getPriority(),
            'fields' => [],
        ];
    }

    /**
     * 获取默认的中间件配置
     *
     * @param string $middlewareName
     * @return array<string, mixed>
     */
    public function getDefaultConfig(string $middlewareName): array
    {
        $templates = $this->getMiddlewareConfigTemplates();

        if (!isset($templates[$middlewareName])) {
            return [];
        }

        $config = [];
        $fields = $templates[$middlewareName]['fields'] ?? [];
        if (is_array($fields)) {
            foreach ($fields as $fieldName => $template) {
                if (is_array($template) && isset($template['default'])) {
                    $config[(string) $fieldName] = $template['default'];
                }
            }
        }

        return $config;
    }
}
