<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Middleware\Auth\RemoveAuthHeaderMiddleware;
use Tourze\HttpForwardBundle\Middleware\Auth\SetAuthHeaderMiddleware;
use Tourze\HttpForwardBundle\Middleware\Header\AddHeaderMiddleware;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;
use Tourze\HttpForwardBundle\Middleware\Query\AddQueryParamMiddleware;
use Tourze\HttpForwardBundle\Service\MiddlewareConfigManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 单元测试MiddlewareConfigManager
 * @internal
 */
#[CoversClass(MiddlewareConfigManager::class)]
#[RunTestsInSeparateProcesses]
class MiddlewareConfigManagerUnitTest extends AbstractIntegrationTestCase
{
    private MiddlewareRegistry $registry;

    private MiddlewareConfigManager $configManager;

    public function testGetAvailableMiddlewaresReturnsRegisteredMiddlewares(): void
    {
        $availableMiddlewares = $this->configManager->getAvailableMiddlewares();

        echo "\n=== 手动注册的中间件测试 ===\n";
        foreach ($availableMiddlewares as $alias => $config) {
            $label = '未设置';
            if (isset($config['label']) && is_string($config['label'])) {
                $label = $config['label'];
            }
            $enabled = isset($config['enabled']) ? (bool) $config['enabled'] : false;
            echo sprintf(
                "别名: %s, 标签: %s, 启用: %s\n",
                $alias,
                $label,
                $enabled ? '是' : '否'
            );
        }

        // 验证所有期望的中间件都在列表中（现在从容器获取会包含所有已注册的中间件）
        $expectedAliases = ['set_auth_header', 'remove_auth_header', 'add_header', 'add_query_param'];
        foreach ($expectedAliases as $alias) {
            $this->assertArrayHasKey($alias, $availableMiddlewares, sprintf('中间件 "%s" 未在可用列表中找到', $alias));
        }

        // 由于现在从容器获取服务，会包含所有已注册的中间件，所以检查是否至少包含我们期望的中间件
        $this->assertGreaterThanOrEqual(count($expectedAliases), count($availableMiddlewares), '应该至少包含我们期望的中间件数量');
    }

    public function testGetMiddlewareConfigTemplatesReturnsCorrectTemplates(): void
    {
        $templates = $this->configManager->getMiddlewareConfigTemplates();

        $this->assertNotEmpty($templates, '模板不应该为空');

        $this->debugTemplates($templates);
        $this->validateSetAuthTemplate($templates);
        $this->validateAddQueryTemplate($templates);
    }

    /**
     * @param array<string, mixed> $templates
     */
    private function debugTemplates(array $templates): void
    {
        echo "\n=== 中间件配置模板测试 ===\n";
        foreach ($templates as $alias => $template) {
            $this->debugTemplate($alias, $template);
        }
    }

    private function debugTemplate(string $alias, mixed $template): void
    {
        $fields = $this->extractFields($template);
        echo sprintf("别名: %s, 字段数: %d\n", $alias, count($fields));
        $this->debugFields($template);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFields(mixed $template): array
    {
        if (!is_array($template)) {
            return [];
        }

        $fields = $template['fields'] ?? null;
        if (!is_array($fields)) {
            return [];
        }

        /** @var array<string, mixed> $fields */
        return $fields;
    }

    private function debugFields(mixed $template): void
    {
        if (!is_array($template)) {
            return;
        }

        $fields = $template['fields'] ?? null;
        if (!is_array($fields)) {
            return;
        }

        /** @var array<string, mixed> $fields */
        foreach ($fields as $fieldName => $fieldConfig) {
            $fieldType = $this->extractFieldType($fieldConfig);
            echo sprintf("  - 字段: %s, 类型: %s\n", $fieldName, $fieldType);
        }
    }

    private function extractFieldType(mixed $fieldConfig): string
    {
        if (is_array($fieldConfig) && isset($fieldConfig['type']) && is_string($fieldConfig['type'])) {
            return $fieldConfig['type'];
        }

        return '未设置';
    }

    /**
     * @param array<string, mixed> $templates
     */
    private function validateSetAuthTemplate(array $templates): void
    {
        $this->assertArrayHasKey('set_auth_header', $templates);
        $setAuthTemplate = $templates['set_auth_header'];

        if (!is_array($setAuthTemplate)) {
            self::fail('set_auth_header template must be an array');
        }

        $this->assertArrayHasKey('label', $setAuthTemplate);
        $this->assertArrayHasKey('fields', $setAuthTemplate);

        $fields = $setAuthTemplate['fields'];
        if (!is_array($fields)) {
            self::fail('set_auth_header fields must be an array');
        }

        $this->assertArrayHasKey('scheme', $fields);
        $this->assertArrayHasKey('token', $fields);
    }

    /**
     * @param array<string, mixed> $templates
     */
    private function validateAddQueryTemplate(array $templates): void
    {
        $this->assertArrayHasKey('add_query_param', $templates);
        $addQueryTemplate = $templates['add_query_param'];

        if (!is_array($addQueryTemplate)) {
            self::fail('add_query_param template must be an array');
        }

        $fields = $addQueryTemplate['fields'];
        if (!is_array($fields)) {
            self::fail('add_query_param fields must be an array');
        }

        $this->assertArrayHasKey('params', $fields);
    }

    public function testValidateMiddlewareConfigWorksCorrectly(): void
    {
        // 测试有效配置
        $validConfig = [
            [
                'name' => 'set_auth_header',
                'config' => [
                    'scheme' => 'Bearer',
                    'token' => 'test-token',
                ],
            ],
            [
                'name' => 'add_query_param',
                'config' => [
                    'params' => [
                        'test' => 'value',
                    ],
                ],
            ],
        ];

        $errors = $this->configManager->validateMiddlewareConfig($validConfig);
        $this->assertEmpty($errors, '有效配置不应该有错误');

        // 测试无效配置
        $invalidConfig = [
            [
                'name' => 'set_auth_header',
                'config' => [], // 缺少必需的token字段
            ],
            [
                'name' => 'nonexistent_middleware',
                'config' => [],
            ],
        ];

        $errors = $this->configManager->validateMiddlewareConfig($invalidConfig);
        $this->assertNotEmpty($errors, '无效配置应该有错误');

        echo "\n=== 配置验证错误 ===\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
    }

    public function testFormatForFrontend(): void
    {
        // 测试formatForFrontend方法
        $middlewareConfigs = [
            [
                'name' => 'set_auth_header',
                'config' => [
                    'scheme' => 'Bearer',
                    'token' => 'test-token',
                ],
            ],
        ];

        $formatted = $this->configManager->formatForFrontend($middlewareConfigs);

        $this->assertNotEmpty($formatted, 'formatForFrontend should not return empty array');

        // 验证格式化后的结构 - array items are verified by method signature
        $this->assertGreaterThan(0, count($formatted));
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务实例，而不是手动实例化
        $this->configManager = self::getService(MiddlewareConfigManager::class);
        $this->registry = self::getService(MiddlewareRegistry::class);

        // 为了维持原有测试预期，我们从容器获取中间件实例而不是直接实例化
        $this->registry->register('set_auth_header', self::getService(SetAuthHeaderMiddleware::class));
        $this->registry->register('remove_auth_header', self::getService(RemoveAuthHeaderMiddleware::class));
        $this->registry->register('add_header', self::getService(AddHeaderMiddleware::class));
        $this->registry->register('add_query_param', self::getService(AddQueryParamMiddleware::class));
    }
}
