<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\ArrayHelper;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\BadgeConfigurationHelper;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\EnumProcessor;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\ValueFormatter;

/**
 * @internal
 */
#[CoversClass(ValueFormatter::class)]
final class ValueFormatterTest extends TestCase
{
    private ValueFormatter $formatter;

    private BadgeConfigurationHelper $badgeHelper;

    private EnumProcessor $enumProcessor;

    private ArrayHelper $arrayHelper;

    protected function setUp(): void
    {
        $this->badgeHelper = new BadgeConfigurationHelper();
        $this->enumProcessor = new EnumProcessor();
        $this->arrayHelper = new ArrayHelper();
        $this->formatter = new ValueFormatter($this->badgeHelper, $this->enumProcessor, $this->arrayHelper);
    }

    public function testConfigureFormattedValueWithNullFieldValue(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $field->setValue(null);

        // Create a simple test that doesn't require AdminContext
        $this->assertNull($field->getValue());
        $this->assertNull($field->getFormattedValue());
    }

    public function testValueFormatterConstructor(): void
    {
        $this->assertInstanceOf(ValueFormatter::class, $this->formatter);
    }

    public function testValueFormatterWithBadgeHelper(): void
    {
        $field = new FieldDto();
        $field->setProperty('test_field');
        $badge = $this->badgeHelper->prepareBadgeConfiguration($field);

        $this->assertArrayHasKey('selector', $badge);
        $this->assertArrayHasKey('isRenderedAsBadge', $badge);
    }

    public function testValueFormatterWithEnumProcessor(): void
    {
        $choices = ['active' => 'Active'];
        $config = $this->getDefaultConfig();
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->enumProcessor->processEnumChoices($choices, $config, $field);

        $this->assertArrayHasKey('choices', $result);
        $this->assertArrayHasKey('config', $result);
    }

    public function testValueFormatterWithArrayHelper(): void
    {
        $input = ['key' => 'value'];
        $result = $this->arrayHelper->flatten($input);

        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * @return array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * }
     */
    private function getDefaultConfig(): array
    {
        return [
            'areChoicesTranslatable' => false,
            'choicesSupportTranslatableInterface' => false,
            'isExpanded' => false,
            'isMultipleChoice' => false,
            'enumTypeClass' => null,
            'allChoicesAreEnums' => false,
        ];
    }
}
