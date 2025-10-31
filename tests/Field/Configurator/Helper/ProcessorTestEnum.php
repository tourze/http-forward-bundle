<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

/**
 * EnumProcessor 测试用枚举
 */
enum ProcessorTestEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
