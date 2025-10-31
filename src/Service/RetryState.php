<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

final class RetryState
{
    private int $currentAttempt = 0;

    private ?\Throwable $lastException = null;

    public function __construct(
        private readonly int $maxRetries,
    ) {
    }

    public function isExhausted(): bool
    {
        return $this->currentAttempt > $this->maxRetries;
    }

    public function isLastAttempt(): bool
    {
        return $this->currentAttempt >= $this->maxRetries;
    }

    public function shouldWait(): bool
    {
        return $this->currentAttempt > 0;
    }

    public function getCurrentAttempt(): int
    {
        return $this->currentAttempt;
    }

    public function getLastException(): ?\Throwable
    {
        return $this->lastException;
    }

    public function recordFailure(\Throwable $exception): void
    {
        $this->lastException = $exception;
        ++$this->currentAttempt;
    }
}
