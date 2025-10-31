<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum ForwardLogStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case SENDING = 'sending';
    case RECEIVING = 'receiving';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '准备中',
            self::SENDING => '发送中',
            self::RECEIVING => '接收中',
            self::COMPLETED => '已完成',
            self::FAILED => '失败',
        };
    }

    public function getBadgeClass(): string
    {
        return $this->getBadge();
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::COMPLETED => BadgeInterface::SUCCESS,
            self::FAILED => BadgeInterface::DANGER,
            self::SENDING, self::RECEIVING => BadgeInterface::WARNING,
            self::PENDING => BadgeInterface::INFO,
        };
    }
}
