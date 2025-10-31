<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\HttpForwardBundle\DependencyInjection\HttpForwardExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(HttpForwardExtension::class)]
final class HttpForwardExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private HttpForwardExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new HttpForwardExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testServicesAreRegistered(): void
    {
        $configs = [];

        $this->extension->load($configs, $this->container);

        // 验证核心服务已注册
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Service\ForwarderService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Service\RuleMatcher'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Repository\ForwardRuleRepository'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Repository\ForwardLogRepository'));
    }

    public function testMiddlewaresAreRegistered(): void
    {
        $configs = [];

        $this->extension->load($configs, $this->container);

        // 验证中间件服务已注册
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Middleware\Builtin\AuthHeaderMiddleware'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Middleware\Builtin\XmlToJsonMiddleware'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Middleware\Builtin\HeaderTransformMiddleware'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Middleware\Builtin\QueryParamMiddleware'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Middleware\Builtin\RetryMiddleware'));
        $this->assertTrue($this->container->hasDefinition('Tourze\HttpForwardBundle\Middleware\Builtin\FallbackMiddleware'));
    }
}
