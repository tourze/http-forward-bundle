<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(BackendStatus::class)]
final class BackendStatusTest extends AbstractEnumTestCase
{
    public function testGetLabel(): void
    {
        self::assertSame('正常', BackendStatus::ACTIVE->getLabel());
        self::assertSame('已停用', BackendStatus::INACTIVE->getLabel());
        self::assertSame('不健康', BackendStatus::UNHEALTHY->getLabel());
    }

    public function testGetBadge(): void
    {
        self::assertSame(BadgeInterface::SUCCESS, BackendStatus::ACTIVE->getBadge());
        self::assertSame(BadgeInterface::SECONDARY, BackendStatus::INACTIVE->getBadge());
        self::assertSame(BadgeInterface::DANGER, BackendStatus::UNHEALTHY->getBadge());
    }

    public function testGetBadgeClass(): void
    {
        self::assertSame(BadgeInterface::SUCCESS, BackendStatus::ACTIVE->getBadgeClass());
        self::assertSame(BadgeInterface::SECONDARY, BackendStatus::INACTIVE->getBadgeClass());
        self::assertSame(BadgeInterface::DANGER, BackendStatus::UNHEALTHY->getBadgeClass());
    }

    public function testGetIcon(): void
    {
        self::assertSame('✅', BackendStatus::ACTIVE->getIcon());
        self::assertSame('⚫', BackendStatus::INACTIVE->getIcon());
        self::assertSame('❌', BackendStatus::UNHEALTHY->getIcon());
    }

    public function testIsHealthy(): void
    {
        self::assertTrue(BackendStatus::ACTIVE->isHealthy());
        self::assertFalse(BackendStatus::INACTIVE->isHealthy());
        self::assertFalse(BackendStatus::UNHEALTHY->isHealthy());
    }

    public function testToArray(): void
    {
        $activeArray = BackendStatus::ACTIVE->toArray();
        $expectedActiveArray = [
            'value' => 'active',
            'label' => '正常',
        ];
        self::assertSame($expectedActiveArray, $activeArray);

        $inactiveArray = BackendStatus::INACTIVE->toArray();
        $expectedInactiveArray = [
            'value' => 'inactive',
            'label' => '已停用',
        ];
        self::assertSame($expectedInactiveArray, $inactiveArray);

        $unhealthyArray = BackendStatus::UNHEALTHY->toArray();
        $expectedUnhealthyArray = [
            'value' => 'unhealthy',
            'label' => '不健康',
        ];
        self::assertSame($expectedUnhealthyArray, $unhealthyArray);
    }
}
