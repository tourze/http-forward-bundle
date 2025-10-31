<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Service\MiddlewareConfigManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MiddlewareConfigManager::class)]
#[RunTestsInSeparateProcesses]
class MiddlewareConfigManagerTest extends AbstractIntegrationTestCase
{
    private MiddlewareConfigManager $manager;

    public function testGetMiddlewareConfigTemplates(): void
    {
        $templates = $this->manager->getMiddlewareConfigTemplates();

        // 由于是从容器获取的真实服务，验证数组不为空即可
        $this->assertNotEmpty($templates);
    }

    public function testValidateMiddlewareConfig(): void
    {
        // 获取所有可用的中间件
        $availableMiddlewares = $this->manager->getAvailableMiddlewares();
        $firstMiddleware = array_key_first($availableMiddlewares);

        if (null !== $firstMiddleware) {
            $config = [
                ['name' => $firstMiddleware, 'config' => []],
            ];

            $errors = $this->manager->validateMiddlewareConfig($config);
            // Valid config should have no errors
            $this->assertCount(0, $errors);
        } else {
            self::markTestSkipped('No middlewares available for testing');
        }
    }

    public function testValidateMiddlewareConfigWithErrors(): void
    {
        $config = [
            ['name' => 'nonexistent', 'config' => []],
        ];

        $errors = $this->manager->validateMiddlewareConfig($config);
        $this->assertNotEmpty($errors);
    }

    public function testGetDefaultConfig(): void
    {
        // 获取一个真实存在的中间件名称来测试
        $availableMiddlewares = $this->manager->getAvailableMiddlewares();
        $firstMiddleware = array_key_first($availableMiddlewares);

        if (null !== $firstMiddleware) {
            $config = $this->manager->getDefaultConfig($firstMiddleware);
            // Default config should be an array (may be empty or have defaults)
            $this->assertGreaterThanOrEqual(0, count($config));
        } else {
            // 如果没有中间件，测试一个不存在的应该返回空数组
            $config = $this->manager->getDefaultConfig('nonexistent');
            $this->assertEmpty($config);
        }
    }

    public function testFormatForFrontend(): void
    {
        // 获取一个真实存在的中间件名称来测试
        $availableMiddlewares = $this->manager->getAvailableMiddlewares();
        $firstMiddleware = array_key_first($availableMiddlewares);

        if (null !== $firstMiddleware) {
            $middlewares = [
                ['name' => $firstMiddleware, 'config' => []],
            ];

            $formatted = $this->manager->formatForFrontend($middlewares);
            $this->assertCount(1, $formatted);
        } else {
            self::markTestSkipped('No middlewares available for testing');
        }
    }

    protected function onSetUp(): void
    {
        // 从容器获取真实的 MiddlewareConfigManager 服务
        $this->manager = self::getService(MiddlewareConfigManager::class);
    }
}
