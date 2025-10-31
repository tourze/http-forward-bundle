<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Field\MiddlewareCollectionField;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;
use Tourze\HttpForwardBundle\Service\MiddlewareConfigManager;
use Tourze\HttpForwardBundle\Service\MiddlewareTemplateManager;
use Tourze\HttpForwardBundle\Service\MiddlewareValidator;

/**
 * @internal
 */
#[CoversClass(MiddlewareCollectionField::class)]
class MiddlewareCollectionFieldTest extends TestCase
{
    public function testNew(): void
    {
        $field = MiddlewareCollectionField::new('middlewares', '中间件配置');

        $this->assertInstanceOf(CodeEditorField::class, $field);
    }

    public function testNewWithHelper(): void
    {
        $registry = $this->createMock(MiddlewareRegistry::class);
        $registry->method('all')->willReturn([]);

        $templateManager = $this->createMock(MiddlewareTemplateManager::class);
        $validator = $this->createMock(MiddlewareValidator::class);

        $configManager = new MiddlewareConfigManager($registry, $templateManager, $validator);

        $field = MiddlewareCollectionField::newWithHelper(
            'middlewares',
            '中间件配置',
            $configManager
        );

        $this->assertInstanceOf(CodeEditorField::class, $field);
    }

    public function testNewWithHelperWithoutManager(): void
    {
        $field = MiddlewareCollectionField::newWithHelper('middlewares', '中间件配置', null);

        $this->assertInstanceOf(CodeEditorField::class, $field);
    }

    public function testFormatValueWithEmptyArray(): void
    {
        $field = MiddlewareCollectionField::new('middlewares');

        // 通过反射获取formatValue回调
        $reflection = new \ReflectionClass($field);
        $formatValueProperty = $reflection->getProperty('dto');
        $dto = $formatValueProperty->getValue($field);
        $this->assertInstanceOf(FieldDto::class, $dto);

        $formatValueCallback = $dto->getFormatValueCallable();
        $this->assertNotNull($formatValueCallback);

        $this->assertSame('[]', $formatValueCallback([]));
        $this->assertSame('[]', $formatValueCallback(null));
    }

    public function testFormatValueWithValidJson(): void
    {
        $field = MiddlewareCollectionField::new('middlewares');

        // 通过反射获取formatValue回调
        $reflection = new \ReflectionClass($field);
        $formatValueProperty = $reflection->getProperty('dto');
        $dto = $formatValueProperty->getValue($field);
        $this->assertInstanceOf(FieldDto::class, $dto);

        $formatValueCallback = $dto->getFormatValueCallable();
        $this->assertNotNull($formatValueCallback);

        $jsonString = '{"test": "value"}';
        $result = $formatValueCallback($jsonString);

        $this->assertIsString($result);
        $this->assertJson($result);
    }

    public function testFormatValueWithArray(): void
    {
        $field = MiddlewareCollectionField::new('middlewares');

        // 通过反射获取formatValue回调
        $reflection = new \ReflectionClass($field);
        $formatValueProperty = $reflection->getProperty('dto');
        $dto = $formatValueProperty->getValue($field);
        $this->assertInstanceOf(FieldDto::class, $dto);

        $formatValueCallback = $dto->getFormatValueCallable();
        $this->assertNotNull($formatValueCallback);

        $array = ['name' => 'auth_header', 'config' => ['token' => 'test']];
        $result = $formatValueCallback($array);

        $this->assertIsString($result);
        $this->assertJson($result);
        $this->assertStringContainsString('auth_header', $result);
    }
}
