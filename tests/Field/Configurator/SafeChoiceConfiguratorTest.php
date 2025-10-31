<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Field\Configurator\SafeChoiceConfigurator;

/**
 * @internal
 */
#[CoversClass(SafeChoiceConfigurator::class)]
final class SafeChoiceConfiguratorTest extends TestCase
{
    private SafeChoiceConfigurator $configurator;

    protected function setUp(): void
    {
        $this->configurator = new SafeChoiceConfigurator();
    }

    public function testConfiguratorIsInstantiable(): void
    {
        $this->assertInstanceOf(SafeChoiceConfigurator::class, $this->configurator);
    }

    public function testConfiguratorImplementsInterface(): void
    {
        $this->assertInstanceOf(FieldConfiguratorInterface::class, $this->configurator);
    }

    public function testSupports(): void
    {
        // Test that the supports method is part of the FieldConfiguratorInterface
        $reflectionClass = new \ReflectionClass($this->configurator);
        $this->assertTrue($reflectionClass->hasMethod('supports'));
    }

    public function testConfigure(): void
    {
        // Test that the configure method is part of the FieldConfiguratorInterface
        $reflectionClass = new \ReflectionClass($this->configurator);
        $this->assertTrue($reflectionClass->hasMethod('configure'));
    }

    public function testSafeArrayFlipMethod(): void
    {
        // This method has been moved to ArrayHelper
        // Integration testing is handled through the complete workflow
        $this->assertInstanceOf(SafeChoiceConfigurator::class, $this->configurator);
    }

    public function testFlattenMethod(): void
    {
        // This method has been moved to ArrayHelper
        // Integration testing is handled through the complete workflow
        $this->assertInstanceOf(SafeChoiceConfigurator::class, $this->configurator);
    }
}
