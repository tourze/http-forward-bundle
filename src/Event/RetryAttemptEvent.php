<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

class RetryAttemptEvent extends Event
{
    public function __construct(
        private readonly ForwardRule $rule,
        private readonly int $attemptNumber,
        private readonly ?\Exception $lastException = null,
    ) {
    }

    public function getRule(): ForwardRule
    {
        return $this->rule;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function getLastException(): ?\Exception
    {
        return $this->lastException;
    }
}
