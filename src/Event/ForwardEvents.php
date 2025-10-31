<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Event;

final class ForwardEvents
{
    public const BEFORE_FORWARD = 'http_forward.before_forward';
    public const AFTER_FORWARD = 'http_forward.after_forward';
    public const RULE_MATCHED = 'http_forward.rule_matched';
    public const RETRY_ATTEMPT = 'http_forward.retry_attempt';
    public const FALLBACK_TRIGGERED = 'http_forward.fallback_triggered';
    public const RULE_UPDATED = 'http_forward.rule_updated';
    public const MIDDLEWARE_UPDATED = 'http_forward.middleware_updated';
}
