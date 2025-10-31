<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Middleware\Auth\SetAuthHeaderMiddleware;
use Tourze\HttpForwardBundle\Middleware\Header\AddHeaderMiddleware;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;
use Tourze\HttpForwardBundle\Middleware\Query\AddQueryParamMiddleware;
use Tourze\HttpForwardBundle\Service\MiddlewareTemplateManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MiddlewareTemplateManager::class)]
#[RunTestsInSeparateProcesses]
final class MiddlewareTemplateManagerTest extends AbstractIntegrationTestCase
{
    private MiddlewareTemplateManager $templateManager;

    private MiddlewareRegistry $registry;

    public function testGetMiddlewareConfigTemplatesReturnsArrayOfTemplates(): void
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        $this->assertNotEmpty($templates);

        // 验证每个模板的结构
        foreach ($templates as $alias => $template) {
            $this->assertArrayHasKey('label', $template);
            $this->assertArrayHasKey('description', $template);
            $this->assertArrayHasKey('priority', $template);
            $this->assertArrayHasKey('fields', $template);

            // 类型断言是必要的，因为这些字段可能来自未知的中间件
            $this->assertIsString($template['label']);
            $this->assertIsString($template['description']);
            $this->assertIsInt($template['priority']);
        }
    }

    public function testGetMiddlewareConfigTemplatesReturnsConfigurableMiddlewareTemplates(): void
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        // 验证SetAuthHeaderMiddleware的模板（实现了ConfigurableMiddlewareInterface）
        $this->assertArrayHasKey('set_auth_header', $templates);
        $setAuthTemplate = $templates['set_auth_header'];

        $this->assertArrayHasKey('fields', $setAuthTemplate);
        $fields = $setAuthTemplate['fields'];

        if (!is_array($fields)) {
            self::fail('set_auth_header template fields must be an array');
        }

        // 验证特定字段存在
        $this->assertArrayHasKey('scheme', $fields);
        $this->assertArrayHasKey('token', $fields);
    }

    public function testGetMiddlewareConfigTemplatesReturnsBuiltinMiddlewareTemplates(): void
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        // 验证内置中间件模板存在（如果已注册）
        if (isset($templates['access_key_auth'])) {
            $accessKeyTemplate = $templates['access_key_auth'];
            $this->assertArrayHasKey('label', $accessKeyTemplate);
            $this->assertArrayHasKey('fields', $accessKeyTemplate);
        }
    }

    public function testGetDefaultConfigReturnsEmptyArrayForNonExistentMiddleware(): void
    {
        $config = $this->templateManager->getDefaultConfig('nonexistent_middleware');

        $this->assertEmpty($config);
    }

    public function testGetDefaultConfigReturnsDefaultValuesForMiddleware(): void
    {
        $config = $this->templateManager->getDefaultConfig('set_auth_header');

        // 验证默认配置中包含预期的键和值
        $this->assertArrayHasKey('scheme', $config);
        $this->assertSame('Bearer', $config['scheme']);
        $this->assertArrayHasKey('token', $config);
        $this->assertSame('', $config['token']);
    }

    public function testGetDefaultConfigExtractsDefaultValuesFromTemplate(): void
    {
        // 首先获取模板以确认存在默认值
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        foreach ($templates as $alias => $template) {
            $defaultConfig = $this->templateManager->getDefaultConfig($alias);

            // 验证默认配置中的值都是从模板的default字段提取的
            $fields = $template['fields'] ?? [];
            if (!is_array($fields)) {
                continue;
            }

            foreach ($defaultConfig as $fieldName => $defaultValue) {
                if (isset($fields[$fieldName]) && is_array($fields[$fieldName])) {
                    $this->assertArrayHasKey('default', $fields[$fieldName]);
                    $this->assertSame($fields[$fieldName]['default'], $defaultValue);
                }
            }
        }
    }

    public function testTemplatesContainCorrectPriorityValues(): void
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        foreach ($templates as $alias => $template) {
            $this->assertArrayHasKey('priority', $template);
            $this->assertIsInt($template['priority']);
            $this->assertGreaterThanOrEqual(0, $template['priority']);
        }
    }

    public function testTemplateFieldsAreStringKeyed(): void
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        foreach ($templates as $template) {
            $fields = $template['fields'] ?? [];

            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $fieldName => $fieldConfig) {
                $this->assertIsString($fieldName, 'Field names must be strings');
            }
        }
    }

    public function testBuiltinTemplatesHaveCorrectStructure(): void
    {
        $templates = $this->templateManager->getMiddlewareConfigTemplates();

        // 测试内置模板的特定字段
        if (isset($templates['access_key_auth'])) {
            $accessKeyTemplate = $templates['access_key_auth'];
            $fields = $accessKeyTemplate['fields'];

            if (!is_array($fields)) {
                self::fail('access_key_auth template fields must be an array');
            }

            $this->assertArrayHasKey('enabled', $fields);
            $this->assertArrayHasKey('required', $fields);
            $this->assertArrayHasKey('fallback_mode', $fields);

            // 验证fallback_mode字段的结构
            $fallbackField = $fields['fallback_mode'];
            $this->assertIsArray($fallbackField);
            $this->assertArrayHasKey('type', $fallbackField);
            $this->assertSame('choice', $fallbackField['type']);
            $this->assertArrayHasKey('choices', $fallbackField);
        }
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务实例
        $this->registry = self::getService(MiddlewareRegistry::class);
        $this->templateManager = self::getService(MiddlewareTemplateManager::class);

        // 注册测试所需的中间件
        $this->registry->register('set_auth_header', self::getService(SetAuthHeaderMiddleware::class));
        $this->registry->register('add_header', self::getService(AddHeaderMiddleware::class));
        $this->registry->register('add_query_param', self::getService(AddQueryParamMiddleware::class));
    }
}
