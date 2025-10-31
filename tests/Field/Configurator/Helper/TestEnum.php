<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

/**
 * 测试用的简单枚举
 */
enum TestEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
