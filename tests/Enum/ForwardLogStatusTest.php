<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\HttpForwardBundle\Enum\ForwardLogStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ForwardLogStatus::class)]
class ForwardLogStatusTest extends AbstractEnumTestCase
{
    public function testAllCases(): void
    {
        $cases = ForwardLogStatus::cases();
        $this->assertCount(5, $cases);

        $expectedCases = [
            ForwardLogStatus::PENDING,
            ForwardLogStatus::SENDING,
            ForwardLogStatus::RECEIVING,
            ForwardLogStatus::COMPLETED,
            ForwardLogStatus::FAILED,
        ];

        $this->assertSame($expectedCases, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('pending', ForwardLogStatus::PENDING->value);
        $this->assertSame('sending', ForwardLogStatus::SENDING->value);
        $this->assertSame('receiving', ForwardLogStatus::RECEIVING->value);
        $this->assertSame('completed', ForwardLogStatus::COMPLETED->value);
        $this->assertSame('failed', ForwardLogStatus::FAILED->value);
    }

    #[DataProvider('labelProvider')]
    public function testGetLabel(ForwardLogStatus $status, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $status->getLabel());
    }

    /**
     * @return array<int, array{ForwardLogStatus, string}>
     */
    public static function labelProvider(): array
    {
        return [
            [ForwardLogStatus::PENDING, '准备中'],
            [ForwardLogStatus::SENDING, '发送中'],
            [ForwardLogStatus::RECEIVING, '接收中'],
            [ForwardLogStatus::COMPLETED, '已完成'],
            [ForwardLogStatus::FAILED, '失败'],
        ];
    }

    #[DataProvider('badgeProvider')]
    public function testGetBadge(ForwardLogStatus $status, string $expectedBadge): void
    {
        $this->assertSame($expectedBadge, $status->getBadge());
    }

    #[DataProvider('badgeProvider')]
    public function testGetBadgeClass(ForwardLogStatus $status, string $expectedBadge): void
    {
        // getBadgeClass() just calls getBadge()
        $this->assertSame($expectedBadge, $status->getBadgeClass());
    }

    /**
     * @return array<int, array{ForwardLogStatus, string}>
     */
    public static function badgeProvider(): array
    {
        return [
            [ForwardLogStatus::PENDING, BadgeInterface::INFO],
            [ForwardLogStatus::SENDING, BadgeInterface::WARNING],
            [ForwardLogStatus::RECEIVING, BadgeInterface::WARNING],
            [ForwardLogStatus::COMPLETED, BadgeInterface::SUCCESS],
            [ForwardLogStatus::FAILED, BadgeInterface::DANGER],
        ];
    }

    public function testTraitsAreUsed(): void
    {
        // Test that the enum uses the expected traits by checking interfaces
        $status = ForwardLogStatus::PENDING;
        $this->assertInstanceOf(Itemable::class, $status);
        $this->assertInstanceOf(Selectable::class, $status);
    }

    public function testInterfaceImplementations(): void
    {
        $status = ForwardLogStatus::PENDING;

        $this->assertInstanceOf(Labelable::class, $status);
        $this->assertInstanceOf(Itemable::class, $status);
        $this->assertInstanceOf(Selectable::class, $status);
        $this->assertInstanceOf(BadgeInterface::class, $status);
    }

    public function testEnumBasicFunctionality(): void
    {
        // Test basic enum functionality
        $this->assertTrue(enum_exists(ForwardLogStatus::class));
        $this->assertSame('string', new \ReflectionEnum(ForwardLogStatus::class)->getBackingType()?->getName());
    }

    public function testFromValue(): void
    {
        $this->assertSame(ForwardLogStatus::PENDING, ForwardLogStatus::from('pending'));
        $this->assertSame(ForwardLogStatus::SENDING, ForwardLogStatus::from('sending'));
        $this->assertSame(ForwardLogStatus::RECEIVING, ForwardLogStatus::from('receiving'));
        $this->assertSame(ForwardLogStatus::COMPLETED, ForwardLogStatus::from('completed'));
        $this->assertSame(ForwardLogStatus::FAILED, ForwardLogStatus::from('failed'));
    }

    public function testTryFromValue(): void
    {
        $this->assertSame(ForwardLogStatus::PENDING, ForwardLogStatus::tryFrom('pending'));
        $this->assertSame(ForwardLogStatus::FAILED, ForwardLogStatus::tryFrom('failed'));
        // 测试无效值：tryFrom返回null（使用动态值避免PHPStan冗余断言警告）
        $invalidValue = uniqid('invalid_status_', true);
        $this->assertNull(ForwardLogStatus::tryFrom($invalidValue));
    }

    public function testToArray(): void
    {
        // 使用任意实例调用toArray，因为这可能是trait提供的方法
        $instance = ForwardLogStatus::PENDING;
        $result = $instance->toArray();

        // PHPStan已通过类型系统验证返回array，直接验证结构
        $this->assertCount(2, $result);

        // toArray() 返回单个枚举值的数组表示
        $expectedArray = [
            'value' => 'pending',
            'label' => '准备中',
        ];

        $this->assertSame($expectedArray, $result);
    }
}
