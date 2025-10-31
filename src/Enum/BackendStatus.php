<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum BackendStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNHEALTHY = 'unhealthy';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => '正常',
            self::INACTIVE => '已停用',
            self::UNHEALTHY => '不健康',
        };
    }

    public function getBadgeClass(): string
    {
        return $this->getBadge();
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::ACTIVE => BadgeInterface::SUCCESS,
            self::INACTIVE => BadgeInterface::SECONDARY,
            self::UNHEALTHY => BadgeInterface::DANGER,
        };
    }

    /**
     * 获取状态图标（与现有的 ForwardRuleCrudController 保持兼容）
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => '✅',
            self::INACTIVE => '⚫',
            self::UNHEALTHY => '❌',
        };
    }

    /**
     * 检查是否为健康状态
     */
    public function isHealthy(): bool
    {
        return self::ACTIVE === $this;
    }
}
