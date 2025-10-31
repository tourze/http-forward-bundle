<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Event\RetryAttemptEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(RetryAttemptEvent::class)]
final class RetryAttemptEventTest extends AbstractEventTestCase
{
    public function testConstructorWithRequiredParameters(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $attemptNumber = 2;

        $event = new RetryAttemptEvent($rule, $attemptNumber);

        $this->assertSame($rule, $event->getRule());
        $this->assertEquals(2, $event->getAttemptNumber());
        $this->assertNull($event->getLastException());
    }

    public function testConstructorWithAllParameters(): void
    {
        $rule = new ForwardRule();
        $rule->setName('API Rule');
        $attemptNumber = 3;
        $exception = new \Exception('Connection timeout');

        $event = new RetryAttemptEvent($rule, $attemptNumber, $exception);

        $this->assertSame($rule, $event->getRule());
        $this->assertEquals(3, $event->getAttemptNumber());
        $this->assertSame($exception, $event->getLastException());
    }

    public function testFirstRetryAttempt(): void
    {
        $rule = new ForwardRule();
        $rule->setName('First Retry Rule');

        $event = new RetryAttemptEvent($rule, 1);

        $this->assertEquals(1, $event->getAttemptNumber());
        $this->assertNull($event->getLastException());
    }

    public function testMaxRetryAttempt(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Max Retry Rule');
        $exception = new \RuntimeException('Max retries reached');

        $event = new RetryAttemptEvent($rule, 5, $exception);

        $this->assertEquals(5, $event->getAttemptNumber());
        $this->assertInstanceOf(\RuntimeException::class, $event->getLastException());
        $this->assertEquals('Max retries reached', $event->getLastException()->getMessage());
    }

    public function testWithDifferentExceptionTypes(): void
    {
        $rule = new ForwardRule();

        $connectException = new \RuntimeException('Connection failed');
        $event1 = new RetryAttemptEvent($rule, 1, $connectException);

        $timeoutException = new \Exception('Request timeout', 408);
        $event2 = new RetryAttemptEvent($rule, 2, $timeoutException);

        $serviceException = new \LogicException('Service unavailable');
        $event3 = new RetryAttemptEvent($rule, 3, $serviceException);

        $exception1 = $event1->getLastException();
        $this->assertInstanceOf(\RuntimeException::class, $exception1);
        $this->assertEquals('Connection failed', $exception1->getMessage());

        $exception2Event = $event2->getLastException();
        $this->assertInstanceOf(\Exception::class, $exception2Event);
        $this->assertEquals(408, $exception2Event->getCode());

        $this->assertInstanceOf(\LogicException::class, $event3->getLastException());
    }

    public function testRetrySequence(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Sequence Test Rule');

        // First attempt - no exception yet
        $attempt1 = new RetryAttemptEvent($rule, 1);
        $this->assertEquals(1, $attempt1->getAttemptNumber());
        $this->assertNull($attempt1->getLastException());

        // Second attempt - with first failure
        $firstException = new \Exception('First failure');
        $attempt2 = new RetryAttemptEvent($rule, 2, $firstException);
        $this->assertEquals(2, $attempt2->getAttemptNumber());
        $exception2 = $attempt2->getLastException();
        $this->assertNotNull($exception2);
        $this->assertEquals('First failure', $exception2->getMessage());

        // Third attempt - with second failure
        $secondException = new \Exception('Second failure');
        $attempt3 = new RetryAttemptEvent($rule, 3, $secondException);
        $this->assertEquals(3, $attempt3->getAttemptNumber());
        $exception3 = $attempt3->getLastException();
        $this->assertNotNull($exception3);
        $this->assertEquals('Second failure', $exception3->getMessage());
    }

    public function testWithComplexForwardRule(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Complex Retry Rule');
        $rule->setSourcePath('/api/retry/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://unreliable-service.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['POST', 'PUT']);

        $networkException = new \RuntimeException('Network is unreachable', 500);
        $event = new RetryAttemptEvent($rule, 4, $networkException);

        $this->assertEquals('Complex Retry Rule', $event->getRule()->getName());
        $this->assertEquals('/api/retry/*', $event->getRule()->getSourcePath());
        $this->assertEquals(4, $event->getAttemptNumber());
        $lastException = $event->getLastException();
        $this->assertNotNull($lastException);
        $this->assertEquals('Network is unreachable', $lastException->getMessage());
        $this->assertEquals(500, $lastException->getCode());
    }

    public function testAttemptNumberConstraints(): void
    {
        $rule = new ForwardRule();

        // Test with zero (edge case)
        $zeroAttempt = new RetryAttemptEvent($rule, 0);
        $this->assertEquals(0, $zeroAttempt->getAttemptNumber());

        // Test with negative number (edge case)
        $negativeAttempt = new RetryAttemptEvent($rule, -1);
        $this->assertEquals(-1, $negativeAttempt->getAttemptNumber());

        // Test with large number
        $largeAttempt = new RetryAttemptEvent($rule, 100);
        $this->assertEquals(100, $largeAttempt->getAttemptNumber());
    }
}
