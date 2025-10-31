<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Event\ForwardEvents;

/**
 * @internal
 */
#[CoversClass(ForwardEvents::class)]
final class ForwardEventsTest extends TestCase
{
    public function testEventConstantValues(): void
    {
        $this->assertEquals('http_forward.before_forward', ForwardEvents::BEFORE_FORWARD);
        $this->assertEquals('http_forward.after_forward', ForwardEvents::AFTER_FORWARD);
        $this->assertEquals('http_forward.rule_matched', ForwardEvents::RULE_MATCHED);
        $this->assertEquals('http_forward.retry_attempt', ForwardEvents::RETRY_ATTEMPT);
        $this->assertEquals('http_forward.fallback_triggered', ForwardEvents::FALLBACK_TRIGGERED);
        $this->assertEquals('http_forward.rule_updated', ForwardEvents::RULE_UPDATED);
        $this->assertEquals('http_forward.middleware_updated', ForwardEvents::MIDDLEWARE_UPDATED);
    }

    public function testAllEventConstantsHaveCommonPrefix(): void
    {
        $expectedPrefix = 'http_forward.';

        $this->assertStringStartsWith($expectedPrefix, ForwardEvents::BEFORE_FORWARD);
        $this->assertStringStartsWith($expectedPrefix, ForwardEvents::AFTER_FORWARD);
        $this->assertStringStartsWith($expectedPrefix, ForwardEvents::RULE_MATCHED);
        $this->assertStringStartsWith($expectedPrefix, ForwardEvents::RETRY_ATTEMPT);
        $this->assertStringStartsWith($expectedPrefix, ForwardEvents::FALLBACK_TRIGGERED);
        $this->assertStringStartsWith($expectedPrefix, ForwardEvents::RULE_UPDATED);
        $this->assertStringStartsWith($expectedPrefix, ForwardEvents::MIDDLEWARE_UPDATED);
    }

    public function testAllEventConstantsAreUnique(): void
    {
        $constants = [
            ForwardEvents::BEFORE_FORWARD,
            ForwardEvents::AFTER_FORWARD,
            ForwardEvents::RULE_MATCHED,
            ForwardEvents::RETRY_ATTEMPT,
            ForwardEvents::FALLBACK_TRIGGERED,
            ForwardEvents::RULE_UPDATED,
            ForwardEvents::MIDDLEWARE_UPDATED,
        ];

        $uniqueConstants = array_unique($constants);

        $this->assertCount(7, $uniqueConstants, 'All event constants should be unique');
        $this->assertCount(count($constants), $uniqueConstants, 'No duplicate event constants should exist');
    }

    public function testEventConstantNaming(): void
    {
        // Test that event names follow expected patterns
        $this->assertStringContainsString('before', ForwardEvents::BEFORE_FORWARD);
        $this->assertStringContainsString('after', ForwardEvents::AFTER_FORWARD);
        $this->assertStringContainsString('matched', ForwardEvents::RULE_MATCHED);
        $this->assertStringContainsString('retry', ForwardEvents::RETRY_ATTEMPT);
        $this->assertStringContainsString('fallback', ForwardEvents::FALLBACK_TRIGGERED);
        $this->assertStringContainsString('updated', ForwardEvents::RULE_UPDATED);
        $this->assertStringContainsString('updated', ForwardEvents::MIDDLEWARE_UPDATED);
    }

    public function testEventConstantsCanBeUsedAsArrayKeys(): void
    {
        $eventMapping = [
            ForwardEvents::BEFORE_FORWARD => 'BeforeForwardEvent',
            ForwardEvents::AFTER_FORWARD => 'AfterForwardEvent',
            ForwardEvents::RETRY_ATTEMPT => 'RetryAttemptEvent',
            ForwardEvents::FALLBACK_TRIGGERED => 'FallbackTriggeredEvent',
        ];

        $this->assertArrayHasKey(ForwardEvents::BEFORE_FORWARD, $eventMapping);
        $this->assertArrayHasKey(ForwardEvents::AFTER_FORWARD, $eventMapping);
        $this->assertArrayHasKey(ForwardEvents::RETRY_ATTEMPT, $eventMapping);
        $this->assertArrayHasKey(ForwardEvents::FALLBACK_TRIGGERED, $eventMapping);

        $this->assertEquals('BeforeForwardEvent', $eventMapping[ForwardEvents::BEFORE_FORWARD]);
        $this->assertEquals('AfterForwardEvent', $eventMapping[ForwardEvents::AFTER_FORWARD]);
    }

    public function testForwardEventsClassIsFinal(): void
    {
        $reflection = new \ReflectionClass(ForwardEvents::class);

        $this->assertTrue($reflection->isFinal(), 'ForwardEvents class should be final');
    }

    public function testForwardEventsHasOnlyConstants(): void
    {
        $reflection = new \ReflectionClass(ForwardEvents::class);

        $this->assertEmpty($reflection->getMethods(), 'ForwardEvents should not have any methods');
        $this->assertEmpty($reflection->getProperties(), 'ForwardEvents should not have any properties');
        $this->assertCount(7, $reflection->getConstants(), 'ForwardEvents should have exactly 7 constants');
    }
}
