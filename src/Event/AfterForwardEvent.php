<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

class AfterForwardEvent extends Event
{
    public function __construct(
        private readonly Request $request,
        private Response $response,
        private readonly ForwardRule $rule,
        private readonly ?ForwardLog $forwardLog = null,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function getRule(): ForwardRule
    {
        return $this->rule;
    }

    public function getForwardLog(): ?ForwardLog
    {
        return $this->forwardLog;
    }
}
