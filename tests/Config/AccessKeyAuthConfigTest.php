<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Config\AccessKeyAuthConfig;

/**
 * @internal
 */
#[CoversClass(AccessKeyAuthConfig::class)]
class AccessKeyAuthConfigTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $config = new AccessKeyAuthConfig();

        $this->assertTrue($config->enabled);
        $this->assertTrue($config->required);
        $this->assertSame('strict', $config->fallbackMode);
    }

    public function testConstructorWithCustomValues(): void
    {
        $config = new AccessKeyAuthConfig(
            enabled: false,
            required: false,
            fallbackMode: 'permissive'
        );

        $this->assertFalse($config->enabled);
        $this->assertFalse($config->required);
        $this->assertSame('permissive', $config->fallbackMode);
    }

    public function testFromArrayWithEmptyArray(): void
    {
        $config = AccessKeyAuthConfig::fromArray([]);

        $this->assertTrue($config->enabled);
        $this->assertTrue($config->required);
        $this->assertSame('strict', $config->fallbackMode);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'enabled' => false,
            'required' => false,
            'fallback_mode' => 'permissive',
        ];

        $config = AccessKeyAuthConfig::fromArray($data);

        $this->assertFalse($config->enabled);
        $this->assertFalse($config->required);
        $this->assertSame('permissive', $config->fallbackMode);
    }

    public function testFromArrayWithStringBooleans(): void
    {
        $data = [
            'enabled' => '1',
            'required' => '0',
            'fallback_mode' => 'strict',
        ];

        $config = AccessKeyAuthConfig::fromArray($data);

        $this->assertTrue($config->enabled);
        $this->assertFalse($config->required);
        $this->assertSame('strict', $config->fallbackMode);
    }

    public function testFromArrayWithInvalidFallbackMode(): void
    {
        $data = [
            'fallback_mode' => 123, // non-string value
        ];

        $config = AccessKeyAuthConfig::fromArray($data);

        $this->assertSame('strict', $config->fallbackMode); // fallback to default
    }

    public function testToArray(): void
    {
        $config = new AccessKeyAuthConfig(
            enabled: false,
            required: true,
            fallbackMode: 'permissive'
        );

        $array = $config->toArray();

        $expected = [
            'enabled' => false,
            'required' => true,
            'fallback_mode' => 'permissive',
        ];

        $this->assertSame($expected, $array);
    }

    public function testIsValidWithValidFallbackModes(): void
    {
        $strictConfig = new AccessKeyAuthConfig(fallbackMode: 'strict');
        $permissiveConfig = new AccessKeyAuthConfig(fallbackMode: 'permissive');

        $this->assertTrue($strictConfig->isValid());
        $this->assertTrue($permissiveConfig->isValid());
    }

    public function testIsValidWithInvalidFallbackMode(): void
    {
        $config = new AccessKeyAuthConfig(fallbackMode: 'invalid_mode');

        $this->assertFalse($config->isValid());
    }
}
