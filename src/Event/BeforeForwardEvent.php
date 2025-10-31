<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

class BeforeForwardEvent extends Event
{
    public function __construct(
        private Request $request,
        private readonly ForwardRule $rule,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getRule(): ForwardRule
    {
        return $this->rule;
    }
}
