<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\ArrayHelper;

/**
 * @internal
 */
#[CoversClass(ArrayHelper::class)]
final class ArrayHelperTest extends TestCase
{
    private ArrayHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new ArrayHelper();
    }

    public function testSafeArrayFlip(): void
    {
        $input = [
            'key1' => 'string_value',
            'key2' => 123,
            'key3' => null,        // Not flippable
            'key4' => ['array'],   // Not flippable
            'key5' => new \stdClass(), // Not flippable
        ];

        $result = $this->helper->safeArrayFlip($input);

        // Should only flip string and int values
        $expected = [
            'string_value' => 'key1',
            123 => 'key2',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFlatten(): void
    {
        // Test with grouped choices
        $input = [
            'Group1' => [
                'Sub1' => 'value1',
                'Sub2' => 'value2',
            ],
            'Direct' => 'direct_value',
        ];

        $result = $this->helper->flatten($input);

        $expected = [
            'Sub1' => 'value1',
            'Sub2' => 'value2',
            'Direct' => 'direct_value',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithMixedValues(): void
    {
        $input = [
            'string_key' => 'string_value',
            123 => 456,
            'nested' => ['sub' => 'value'],
        ];

        $result = $this->helper->flatten($input);

        $this->assertArrayHasKey('string_key', $result);
        $this->assertArrayHasKey(123, $result);
        $this->assertArrayHasKey('sub', $result);
        $this->assertEquals('string_value', $result['string_key']);
        $this->assertEquals(456, $result[123]);
        $this->assertEquals('value', $result['sub']);
    }

    public function testFlattenWithBackedEnum(): void
    {
        $input = [
            'active_choice' => ArrayHelperTestBackedEnum::ACTIVE,
            'inactive_choice' => ArrayHelperTestBackedEnum::INACTIVE,
        ];

        $result = $this->helper->flatten($input);

        $expected = [
            'ACTIVE' => 'active',
            'INACTIVE' => 'inactive',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithUnitEnum(): void
    {
        $input = [
            'option_a' => ArrayHelperTestUnitEnum::OPTION_A,
            'option_b' => ArrayHelperTestUnitEnum::OPTION_B,
        ];

        $result = $this->helper->flatten($input);

        $expected = [
            'OPTION_A' => 'OPTION_A',
            'OPTION_B' => 'OPTION_B',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithInvalidKeys(): void
    {
        // ArrayHelper doesn't filter keys, it only processes values
        // This test should verify that invalid key types still work
        $input = [
            'object_key' => new \stdClass(), // Objects are preserved as values
            'null_key' => null,             // Null values are preserved
            'valid_key' => 'value3',
        ];

        $result = $this->helper->flatten($input);

        // All values should be preserved, flatten doesn't filter by key validity
        $this->assertArrayHasKey('object_key', $result);
        $this->assertArrayHasKey('null_key', $result);
        $this->assertArrayHasKey('valid_key', $result);
        $this->assertEquals('value3', $result['valid_key']);
    }

    public function testFlattenWithEmptyArray(): void
    {
        $result = $this->helper->flatten([]);

        $this->assertEquals([], $result);
    }

    public function testSafeArrayFlipWithEmptyArray(): void
    {
        $result = $this->helper->safeArrayFlip([]);

        $this->assertEquals([], $result);
    }

    public function testSafeArrayFlipWithOnlyFlippableValues(): void
    {
        $input = [
            'key1' => 'value1',
            'key2' => 123,
        ];

        $result = $this->helper->safeArrayFlip($input);

        $expected = [
            'value1' => 'key1',
            123 => 'key2',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testSafeArrayFlipWithOnlyNonFlippableValues(): void
    {
        $input = [
            'key1' => null,
            'key2' => ['array'],
            'key3' => new \stdClass(),
        ];

        $result = $this->helper->safeArrayFlip($input);

        $this->assertEquals([], $result);
    }
}
