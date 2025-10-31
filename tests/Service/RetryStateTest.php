<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Service\RetryState;

/**
 * @internal
 */
#[CoversClass(RetryState::class)]
final class RetryStateTest extends TestCase
{
    public function testInitialState(): void
    {
        $retryState = new RetryState(3);

        $this->assertSame(0, $retryState->getCurrentAttempt());
        $this->assertNull($retryState->getLastException());
        $this->assertFalse($retryState->isExhausted());
        $this->assertFalse($retryState->isLastAttempt());
        $this->assertFalse($retryState->shouldWait());
    }

    public function testRecordFailure(): void
    {
        $retryState = new RetryState(3);
        $exception = new \RuntimeException('Test error');

        $retryState->recordFailure($exception);

        $this->assertSame(1, $retryState->getCurrentAttempt());
        $this->assertSame($exception, $retryState->getLastException());
        $this->assertTrue($retryState->shouldWait());
    }

    public function testIsExhausted(): void
    {
        $retryState = new RetryState(1);

        $this->assertFalse($retryState->isExhausted());

        $retryState->recordFailure(new \RuntimeException());
        $this->assertFalse($retryState->isExhausted());

        $retryState->recordFailure(new \RuntimeException());
        $this->assertTrue($retryState->isExhausted());
    }

    public function testIsLastAttempt(): void
    {
        $retryState = new RetryState(2);

        $this->assertFalse($retryState->isLastAttempt());

        $retryState->recordFailure(new \RuntimeException());
        $this->assertFalse($retryState->isLastAttempt());

        $retryState->recordFailure(new \RuntimeException());
        $this->assertTrue($retryState->isLastAttempt());
    }

    public function testShouldWaitMethod(): void
    {
        $retryState = new RetryState(3);

        $this->assertFalse($retryState->shouldWait());

        $retryState->recordFailure(new \RuntimeException());
        $this->assertTrue($retryState->shouldWait());
    }
}
