<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

class FallbackTriggeredEvent extends Event
{
    public function __construct(
        private readonly ForwardRule $rule,
        private readonly \Exception $exception,
        private readonly ?string $fallbackType = null,
    ) {
    }

    public function getRule(): ForwardRule
    {
        return $this->rule;
    }

    public function getException(): \Exception
    {
        return $this->exception;
    }

    public function getFallbackType(): ?string
    {
        return $this->fallbackType;
    }
}
