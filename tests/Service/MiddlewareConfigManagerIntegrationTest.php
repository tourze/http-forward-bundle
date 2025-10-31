<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Service\MiddlewareConfigManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 集成测试MiddlewareConfigManager能否正确获取所有中间件
 * @internal
 */
#[CoversClass(MiddlewareConfigManager::class)]
#[RunTestsInSeparateProcesses]
class MiddlewareConfigManagerIntegrationTest extends AbstractIntegrationTestCase
{
    private MiddlewareConfigManager $configManager;

    public function testGetAvailableMiddlewaresReturnsAllExpectedMiddlewares(): void
    {
        $availableMiddlewares = $this->configManager->getAvailableMiddlewares();

        $expectedAliases = $this->getExpectedMiddlewareAliases();

        $this->printAvailableMiddlewares($availableMiddlewares);

        $actualAliases = array_keys($availableMiddlewares);

        // 验证所有期望的中间件都存在
        foreach ($expectedAliases as $expectedAlias) {
            $this->assertArrayHasKey(
                $expectedAlias,
                $availableMiddlewares,
                sprintf('中间件 "%s" 在可用列表中未找到', $expectedAlias)
            );
        }

        // 验证数量匹配
        $this->assertCount(
            count($expectedAliases),
            array_intersect($expectedAliases, $actualAliases),
            '期望的中间件数量与实际获取的数量不匹配'
        );

        $this->printMiddlewareTemplates();
    }

    /**
     * @return array<string>
     */
    private function getExpectedMiddlewareAliases(): array
    {
        return [
            // 原有的Builtin中间件
            'auth_header',
            'xml_to_json',
            'header_transform',
            'query_param',
            'retry',
            'fallback',

            // AccessKey中间件
            'access_key_auth',

            // 新的Auth中间件
            'set_auth_header',
            'remove_auth_header',
            'append_auth_header',

            // 新的Header中间件
            'add_header',
            'remove_header',
            'rename_header',
            'add_response_header',
            'remove_response_header',
            'rename_response_header',

            // 新的Query中间件
            'add_query_param',
            'remove_query_param',
            'override_query_param',
        ];
    }

    /**
     * @param array<string, mixed> $availableMiddlewares
     */
    private function printAvailableMiddlewares(array $availableMiddlewares): void
    {
        echo "\n=== 可用的中间件列表 ===\n";
        foreach ($availableMiddlewares as $alias => $config) {
            $label = $this->extractLabel($config);
            echo sprintf("别名: %s, 标签: %s\n", $alias, $label);
        }
    }

    /**
     * @param mixed $config
     */
    private function extractLabel($config): string
    {
        if (!is_array($config) || !isset($config['label'])) {
            return '未设置';
        }

        if (!is_string($config['label'])) {
            return '未设置';
        }

        return $config['label'];
    }

    private function printMiddlewareTemplates(): void
    {
        echo "\n=== 所有中间件配置模板 ===\n";
        $templates = $this->configManager->getMiddlewareConfigTemplates();
        foreach ($templates as $alias => $template) {
            $fieldCount = $this->getFieldCount($template);
            echo sprintf("别名: %s, 字段数: %d\n", (string) $alias, $fieldCount);
        }
    }

    /**
     * @param mixed $template
     */
    private function getFieldCount($template): int
    {
        if (!is_array($template) || !isset($template['fields'])) {
            return 0;
        }

        if (!is_array($template['fields'])) {
            return 0;
        }

        return count($template['fields']);
    }

    public function testNewMiddlewaresHaveConfigTemplates(): void
    {
        $templates = $this->configManager->getMiddlewareConfigTemplates();

        // 测试几个新中间件的配置模板
        $newMiddlewaresToTest = [
            'set_auth_header' => ['scheme', 'token'],
            'remove_auth_header' => [],
            'add_header' => ['headers'],
            'add_query_param' => ['params'],
            'remove_query_param' => ['params'],
        ];

        foreach ($newMiddlewaresToTest as $alias => $expectedFields) {
            $this->assertArrayHasKey($alias, $templates, sprintf('中间件 "%s" 没有配置模板', $alias));

            $template = $templates[$alias];
            $this->assertArrayHasKey('label', $template, sprintf('中间件 "%s" 缺少标签', $alias));
            $this->assertArrayHasKey('fields', $template, sprintf('中间件 "%s" 缺少字段配置', $alias));

            $templateFields = isset($template['fields']) && is_array($template['fields']) ? $template['fields'] : [];
            $actualFields = array_keys($templateFields);
            foreach ($expectedFields as $expectedField) {
                $this->assertContains(
                    $expectedField,
                    $actualFields,
                    sprintf('中间件 "%s" 缺少期望的字段 "%s"', $alias, $expectedField)
                );
            }
        }
    }

    public function testValidateMiddlewareConfigWithValidConfig(): void
    {
        $middlewares = [
            [
                'name' => 'auth_header',
                'config' => ['enabled' => true],
                'enabled' => true,
            ],
            [
                'name' => 'xml_to_json',
                'config' => [],
                'enabled' => true,
            ],
        ];

        $errors = $this->configManager->validateMiddlewareConfig($middlewares);

        $this->assertEmpty($errors, '有效配置不应该产生错误');
    }

    public function testValidateMiddlewareConfigWithInvalidConfig(): void
    {
        $middlewares = [
            [
                'name' => 'non_existent_middleware',
                'config' => [],
            ],
            [
                'config' => [],
            ],
        ];

        $errors = $this->configManager->validateMiddlewareConfig($middlewares);

        $this->assertNotEmpty($errors, '无效配置应该产生错误');
        $this->assertContains('中间件 "non_existent_middleware" 未注册或不可用', $errors);
        $this->assertContains('中间件 #2 缺少必需的 name 字段', $errors);
    }

    public function testGetDefaultConfig(): void
    {
        $defaultConfig = $this->configManager->getDefaultConfig('access_key_auth');

        $this->assertArrayHasKey('enabled', $defaultConfig);
        $this->assertArrayHasKey('required', $defaultConfig);
        $this->assertTrue($defaultConfig['enabled']);
        $this->assertTrue($defaultConfig['required']);
    }

    public function testGetDefaultConfigForNonExistentMiddleware(): void
    {
        $defaultConfig = $this->configManager->getDefaultConfig('non_existent');

        $this->assertEmpty($defaultConfig);
    }

    public function testFormatForFrontend(): void
    {
        $middlewares = [
            [
                'name' => 'auth_header',
                'config' => ['enabled' => true],
                'enabled' => true,
            ],
            [
                'name' => 'xml_to_json',
                'config' => [],
                'enabled' => false,
            ],
        ];

        $formatted = $this->configManager->formatForFrontend($middlewares);

        $this->assertCount(2, $formatted);

        $authHeaderFormatted = $formatted[0];
        $this->assertSame('auth_header', $authHeaderFormatted['name']);
        $this->assertTrue($authHeaderFormatted['enabled']);
        $this->assertArrayHasKey('template', $authHeaderFormatted);

        $xmlToJsonFormatted = $formatted[1];
        $this->assertSame('xml_to_json', $xmlToJsonFormatted['name']);
        $this->assertFalse($xmlToJsonFormatted['enabled']);
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务实例
        $this->configManager = self::getService(MiddlewareConfigManager::class);
    }
}
