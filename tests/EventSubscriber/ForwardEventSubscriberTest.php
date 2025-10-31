<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tourze\HttpForwardBundle\Event\ForwardEvents;
use Tourze\HttpForwardBundle\EventSubscriber\ForwardEventSubscriber;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
// @phpstan-ignore symplify.forbiddenExtendOfNonAbstractClass
// @phpstan-ignore eventSubscriberTest.mustInheritAbstractIntegrationTestCase
#[CoversClass(ForwardEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class ForwardEventSubscriberTest extends AbstractIntegrationTestCase
{
    protected static function getEventSubscriberClass(): string
    {
        return ForwardEventSubscriber::class;
    }

    protected function onSetUp(): void
    {
        // No specific setup needed for this test
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ForwardEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(ForwardEvents::BEFORE_FORWARD, $events);
        $this->assertArrayHasKey(ForwardEvents::AFTER_FORWARD, $events);
        $this->assertArrayHasKey(ForwardEvents::RETRY_ATTEMPT, $events);
        $this->assertArrayHasKey(ForwardEvents::FALLBACK_TRIGGERED, $events);

        $this->assertEquals(['onKernelRequest', 100], $events[KernelEvents::REQUEST]);
        $this->assertEquals('onBeforeForward', $events[ForwardEvents::BEFORE_FORWARD]);
        $this->assertEquals('onAfterForward', $events[ForwardEvents::AFTER_FORWARD]);
        $this->assertEquals('onRetryAttempt', $events[ForwardEvents::RETRY_ATTEMPT]);
        $this->assertEquals('onFallbackTriggered', $events[ForwardEvents::FALLBACK_TRIGGERED]);
    }

    public function testEventSubscriberIsRegistered(): void
    {
        $subscriber = self::getService(ForwardEventSubscriber::class);
        $this->assertInstanceOf(ForwardEventSubscriber::class, $subscriber);
    }

    public function testEventSubscriberImplementsInterface(): void
    {
        $subscriber = self::getService(ForwardEventSubscriber::class);
        $this->assertInstanceOf(EventSubscriberInterface::class, $subscriber);
    }

    public function testOnKernelRequest(): void
    {
        $subscriber = self::getService(ForwardEventSubscriber::class);

        // 验证方法存在且可调用
        $reflection = new \ReflectionClass($subscriber);
        $this->assertTrue($reflection->hasMethod('onKernelRequest'));
        $method = $reflection->getMethod('onKernelRequest');
        $this->assertTrue($method->isPublic());
    }

    public function testOnBeforeForward(): void
    {
        $subscriber = self::getService(ForwardEventSubscriber::class);

        // 验证方法存在且可调用
        $reflection = new \ReflectionClass($subscriber);
        $this->assertTrue($reflection->hasMethod('onBeforeForward'));
        $method = $reflection->getMethod('onBeforeForward');
        $this->assertTrue($method->isPublic());
    }

    public function testOnAfterForward(): void
    {
        $subscriber = self::getService(ForwardEventSubscriber::class);

        // 验证方法存在且可调用
        $reflection = new \ReflectionClass($subscriber);
        $this->assertTrue($reflection->hasMethod('onAfterForward'));
        $method = $reflection->getMethod('onAfterForward');
        $this->assertTrue($method->isPublic());
    }

    public function testOnRetryAttempt(): void
    {
        $subscriber = self::getService(ForwardEventSubscriber::class);

        // 验证方法存在且可调用
        $reflection = new \ReflectionClass($subscriber);
        $this->assertTrue($reflection->hasMethod('onRetryAttempt'));
        $method = $reflection->getMethod('onRetryAttempt');
        $this->assertTrue($method->isPublic());
    }

    public function testOnFallbackTriggered(): void
    {
        $subscriber = self::getService(ForwardEventSubscriber::class);

        // 验证方法存在且可调用
        $reflection = new \ReflectionClass($subscriber);
        $this->assertTrue($reflection->hasMethod('onFallbackTriggered'));
        $method = $reflection->getMethod('onFallbackTriggered');
        $this->assertTrue($method->isPublic());
    }
}
