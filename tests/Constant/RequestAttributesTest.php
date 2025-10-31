<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Constant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Constant\RequestAttributes;

/**
 * @internal
 */
#[CoversClass(RequestAttributes::class)]
class RequestAttributesTest extends TestCase
{
    public function testAccessKeyConstant(): void
    {
        $this->assertSame('http_forward.access_key', RequestAttributes::ACCESS_KEY);
    }

    public function testAuthResultConstant(): void
    {
        $this->assertSame('http_forward.authorization_result', RequestAttributes::AUTH_RESULT);
    }

    public function testClientIpConstant(): void
    {
        $this->assertSame('http_forward.client_ip', RequestAttributes::CLIENT_IP);
    }

    public function testConstantsAreNotEmpty(): void
    {
        $this->assertGreaterThan(0, strlen(RequestAttributes::ACCESS_KEY));
        $this->assertGreaterThan(0, strlen(RequestAttributes::AUTH_RESULT));
        $this->assertGreaterThan(0, strlen(RequestAttributes::CLIENT_IP));
    }

    public function testConstantsHaveCorrectPrefix(): void
    {
        $this->assertStringStartsWith('http_forward.', RequestAttributes::ACCESS_KEY);
        $this->assertStringStartsWith('http_forward.', RequestAttributes::AUTH_RESULT);
        $this->assertStringStartsWith('http_forward.', RequestAttributes::CLIENT_IP);
    }

    public function testConstantsAreUnique(): void
    {
        $constants = [
            RequestAttributes::ACCESS_KEY,
            RequestAttributes::AUTH_RESULT,
            RequestAttributes::CLIENT_IP,
        ];

        $uniqueConstants = array_unique($constants);

        $this->assertCount(count($constants), $uniqueConstants, 'All constants should be unique');
    }

    public function testClassIsFinal(): void
    {
        $reflection = new \ReflectionClass(RequestAttributes::class);

        $this->assertTrue($reflection->isFinal(), 'RequestAttributes class should be final');
    }

    public function testClassHasNoConstructor(): void
    {
        $reflection = new \ReflectionClass(RequestAttributes::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull($constructor, 'Constants class should not have a constructor');
    }
}
