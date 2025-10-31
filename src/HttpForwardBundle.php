<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\AccessKeyBundle\AccessKeyBundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;
use Tourze\HttpForwardBundle\Middleware\MiddlewareInterface;

class HttpForwardBundle extends Bundle implements BundleDependencyInterface
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(MiddlewareInterface::class)
            ->addTag('http_forward.middleware')
        ;
    }

    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            TwigBundle::class => ['all' => true],
            EasyAdminBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
            AccessKeyBundle::class => ['all' => true],
        ];
    }
}
