<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

/**
 * 测试用的有自定义标签方法的枚举
 */
enum TestBadgeEnum: string
{
    case HIGH = 'high';
    case LOW = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::HIGH => 'High Priority',
            self::LOW => 'Low Priority',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::HIGH => 'red',
            self::LOW => 'green',
        };
    }
}
