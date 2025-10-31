<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\BadgeConfigurationHelper;

/**
 * @internal
 */
#[CoversClass(BadgeConfigurationHelper::class)]
final class BadgeConfigurationHelperTest extends TestCase
{
    private BadgeConfigurationHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new BadgeConfigurationHelper();
    }

    public function testPrepareBadgeConfigurationWithArraySelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $badgeSelector = ['active' => 'badge-success', 'inactive' => 'badge-danger'];
        $field->setCustomOption(ChoiceField::OPTION_RENDER_AS_BADGES, $badgeSelector);

        $result = $this->helper->prepareBadgeConfiguration($field);

        $this->assertArrayHasKey('selector', $result);
        $this->assertArrayHasKey('isRenderedAsBadge', $result);
        $this->assertEquals($badgeSelector, $result['selector']);
        $this->assertTrue($result['isRenderedAsBadge']);
    }

    public function testPrepareBadgeConfigurationWithTrueSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $field->setCustomOption(ChoiceField::OPTION_RENDER_AS_BADGES, true);

        $result = $this->helper->prepareBadgeConfiguration($field);

        $this->assertTrue($result['selector']);
        $this->assertTrue($result['isRenderedAsBadge']);
    }

    public function testPrepareBadgeConfigurationWithFalseSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $field->setCustomOption(ChoiceField::OPTION_RENDER_AS_BADGES, false);

        $result = $this->helper->prepareBadgeConfiguration($field);

        $this->assertFalse($result['selector']);
        $this->assertFalse($result['isRenderedAsBadge']);
    }

    public function testPrepareBadgeConfigurationWithNullSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $field->setCustomOption(ChoiceField::OPTION_RENDER_AS_BADGES, null);

        $result = $this->helper->prepareBadgeConfiguration($field);

        $this->assertNull($result['selector']);
        $this->assertFalse($result['isRenderedAsBadge']);
    }

    public function testPrepareBadgeConfigurationWithCallableSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $callable = fn ($value, $field) => 'badge-primary';
        $field->setCustomOption(ChoiceField::OPTION_RENDER_AS_BADGES, $callable);

        $result = $this->helper->prepareBadgeConfiguration($field);

        $this->assertIsCallable($result['selector']);
        $this->assertTrue($result['isRenderedAsBadge']);
    }

    public function testPrepareBadgeConfigurationWithInvalidArraySelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $badgeSelector = ['valid' => 'badge-success', 123 => 'invalid_key', 'another' => 456];
        $field->setCustomOption(ChoiceField::OPTION_RENDER_AS_BADGES, $badgeSelector);

        $result = $this->helper->prepareBadgeConfiguration($field);

        // Should only keep string key-value pairs
        $expected = ['valid' => 'badge-success'];
        $this->assertEquals($expected, $result['selector']);
        $this->assertTrue($result['isRenderedAsBadge']);
    }

    public function testGetBadgeCssClassWithTrueSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->helper->getBadgeCssClass(true, 'any_value', $field);

        $this->assertEquals('badge badge-secondary', $result);
    }

    public function testGetBadgeCssClassWithArraySelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $badgeSelector = ['active' => 'success', 'inactive' => 'danger'];

        $result = $this->helper->getBadgeCssClass($badgeSelector, 'active', $field);

        $this->assertEquals('badge badge-success', $result);
    }

    public function testGetBadgeCssClassWithArraySelectorUnknownValue(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $badgeSelector = ['active' => 'success'];

        $result = $this->helper->getBadgeCssClass($badgeSelector, 'unknown', $field);

        $this->assertEquals('badge badge-secondary', $result);
    }

    public function testGetBadgeCssClassWithCallableSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $callable = fn ($value, $field) => 'primary'; // Return valid badge type without 'badge-' prefix

        $result = $this->helper->getBadgeCssClass($callable, 'test_value', $field);

        $this->assertEquals('badge badge-primary', $result);
    }

    public function testGetBadgeCssClassWithInvalidCallableReturn(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The value returned by the callable passed to the "renderAsBadges()" method must be one of the following valid badge types');

        $field = new FieldDto();
        $field->setProperty('test_field');
        $callable = fn ($value, $field) => 'invalid-badge-type';

        $this->helper->getBadgeCssClass($callable, 'test_value', $field);
    }

    public function testGetBadgeCssClassWithNullSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->helper->getBadgeCssClass(null, 'any_value', $field);

        $this->assertEquals('badge', $result);
    }

    public function testGetBadgeCssClassWithFalseSelector(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->helper->getBadgeCssClass(false, 'any_value', $field);

        $this->assertEquals('badge', $result);
    }
}
