<?php

namespace Tourze\HttpForwardBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class HttpForwardExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
