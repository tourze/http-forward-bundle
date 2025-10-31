<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Event\FallbackTriggeredEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(FallbackTriggeredEvent::class)]
final class FallbackTriggeredEventTest extends AbstractEventTestCase
{
    public function testConstructorWithRequiredParameters(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $exception = new \Exception('Connection timeout');

        $event = new FallbackTriggeredEvent($rule, $exception);

        $this->assertSame($rule, $event->getRule());
        $this->assertSame($exception, $event->getException());
        $this->assertNull($event->getFallbackType());
    }

    public function testConstructorWithAllParameters(): void
    {
        $rule = new ForwardRule();
        $rule->setName('API Rule');
        $exception = new \RuntimeException('Service unavailable');
        $fallbackType = 'retry_exhausted';

        $event = new FallbackTriggeredEvent($rule, $exception, $fallbackType);

        $this->assertSame($rule, $event->getRule());
        $this->assertSame($exception, $event->getException());
        $this->assertEquals('retry_exhausted', $event->getFallbackType());
    }

    public function testWithDifferentExceptionTypes(): void
    {
        $rule = new ForwardRule();

        $runtimeException = new \RuntimeException('Runtime error');
        $event1 = new FallbackTriggeredEvent($rule, $runtimeException, 'runtime_error');

        $logicException = new \LogicException('Logic error');
        $event2 = new FallbackTriggeredEvent($rule, $logicException, 'logic_error');

        $invalidArgumentException = new \InvalidArgumentException('Invalid argument');
        $event3 = new FallbackTriggeredEvent($rule, $invalidArgumentException);

        $this->assertInstanceOf(\RuntimeException::class, $event1->getException());
        $this->assertEquals('runtime_error', $event1->getFallbackType());

        $this->assertInstanceOf(\LogicException::class, $event2->getException());
        $this->assertEquals('logic_error', $event2->getFallbackType());

        $this->assertInstanceOf(\InvalidArgumentException::class, $event3->getException());
        $this->assertNull($event3->getFallbackType());
    }

    public function testExceptionDetails(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Error Rule');
        $exception = new \Exception('Detailed error message', 500);

        $event = new FallbackTriggeredEvent($rule, $exception, 'server_error');

        $this->assertEquals('Detailed error message', $event->getException()->getMessage());
        $this->assertEquals(500, $event->getException()->getCode());
    }

    public function testWithFallbackTypes(): void
    {
        $rule = new ForwardRule();
        $exception = new \Exception('Test');

        $timeoutEvent = new FallbackTriggeredEvent($rule, $exception, 'timeout');
        $retryEvent = new FallbackTriggeredEvent($rule, $exception, 'max_retries');
        $circuitBreakerEvent = new FallbackTriggeredEvent($rule, $exception, 'circuit_breaker');
        $defaultEvent = new FallbackTriggeredEvent($rule, $exception, 'default');

        $this->assertEquals('timeout', $timeoutEvent->getFallbackType());
        $this->assertEquals('max_retries', $retryEvent->getFallbackType());
        $this->assertEquals('circuit_breaker', $circuitBreakerEvent->getFallbackType());
        $this->assertEquals('default', $defaultEvent->getFallbackType());
    }

    public function testWithComplexForwardRule(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Complex API Rule');
        $rule->setSourcePath('/api/v1/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://external-api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET', 'POST']);

        $exception = new \Exception('Network timeout after 30 seconds');

        $event = new FallbackTriggeredEvent($rule, $exception, 'network_timeout');

        $this->assertEquals('Complex API Rule', $event->getRule()->getName());
        $this->assertEquals('/api/v1/*', $event->getRule()->getSourcePath());
        $this->assertEquals('network_timeout', $event->getFallbackType());
        $this->assertEquals('Network timeout after 30 seconds', $event->getException()->getMessage());
    }
}
