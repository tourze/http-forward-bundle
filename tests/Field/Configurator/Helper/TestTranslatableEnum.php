<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 测试用的实现 TranslatableInterface 的枚举
 */
enum TestTranslatableEnum: string implements TranslatableInterface
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, [], null, $locale);
    }
}
