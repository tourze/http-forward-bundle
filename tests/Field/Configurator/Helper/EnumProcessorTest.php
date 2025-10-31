<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Field\Configurator\Helper;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\EnumProcessor;

/**
 * @internal
 */
#[CoversClass(EnumProcessor::class)]
final class EnumProcessorTest extends TestCase
{
    private EnumProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new EnumProcessor();
    }

    public function testProcessEnumChoicesWithRegularChoices(): void
    {
        $choices = ['Active' => 'active', 'Inactive' => 'inactive'];
        $config = $this->getDefaultConfig();
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->processor->processEnumChoices($choices, $config, $field);

        $this->assertEquals($choices, $result['choices']);
        $this->assertFalse($result['config']['allChoicesAreEnums']);
    }

    public function testProcessEnumChoicesWithEnumChoices(): void
    {
        $choices = [ProcessorTestEnum::ACTIVE, ProcessorTestEnum::INACTIVE];
        $config = $this->getDefaultConfig();
        $field = new FieldDto();
        $field->setProperty('test_field');
        $field->setFormType(ChoiceType::class);

        $result = $this->processor->processEnumChoices($choices, $config, $field);

        $expected = [
            'ACTIVE' => ProcessorTestEnum::ACTIVE,
            'INACTIVE' => ProcessorTestEnum::INACTIVE,
        ];
        $this->assertEquals($expected, $result['choices']);
        $this->assertTrue($result['config']['allChoicesAreEnums']);
        $this->assertEquals(EnumType::class, $field->getFormType());
    }

    public function testProcessEnumChoicesWithEmptyChoicesAndEnumClass(): void
    {
        $choices = [];
        $config = $this->getDefaultConfig();
        $config['enumTypeClass'] = ProcessorTestEnum::class;
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->processor->processEnumChoices($choices, $config, $field);

        $expected = [
            'ACTIVE' => ProcessorTestEnum::ACTIVE,
            'INACTIVE' => ProcessorTestEnum::INACTIVE,
        ];
        $this->assertEquals($expected, $result['choices']);
        $this->assertTrue($result['config']['allChoicesAreEnums']);
    }

    public function testProcessEnumChoicesWithMixedChoices(): void
    {
        $choices = [ProcessorTestEnum::ACTIVE, 'regular_choice'];
        $config = $this->getDefaultConfig();
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->processor->processEnumChoices($choices, $config, $field);

        // Should not be converted since not all choices are enums
        $this->assertEquals($choices, $result['choices']);
        $this->assertFalse($result['config']['allChoicesAreEnums']);
    }

    public function testNormalizeSelectedValueWithBackedEnum(): void
    {
        $config = $this->getDefaultConfig();
        $config['allChoicesAreEnums'] = true;
        $config['choicesSupportTranslatableInterface'] = false;

        $result = $this->processor->normalizeSelectedValue(ProcessorTestEnum::ACTIVE, $config);

        $this->assertEquals('active', $result);
    }

    public function testNormalizeSelectedValueWithBackedEnumTranslatable(): void
    {
        $config = $this->getDefaultConfig();
        $config['allChoicesAreEnums'] = true;
        $config['choicesSupportTranslatableInterface'] = true;

        $result = $this->processor->normalizeSelectedValue(ProcessorTestEnum::ACTIVE, $config);

        $this->assertEquals('ACTIVE', $result);
    }

    public function testNormalizeSelectedValueWithUnitEnum(): void
    {
        $config = $this->getDefaultConfig();

        $result = $this->processor->normalizeSelectedValue(ProcessorTestUnitEnum::OPTION_A, $config);

        $this->assertEquals('OPTION_A', $result);
    }

    public function testNormalizeSelectedValueWithRegularValue(): void
    {
        $config = $this->getDefaultConfig();

        $result = $this->processor->normalizeSelectedValue('regular_value', $config);

        $this->assertEquals('regular_value', $result);
    }

    public function testProcessEnumChoicesWithNonListArray(): void
    {
        $choices = ['key1' => ProcessorTestEnum::ACTIVE, 'key2' => ProcessorTestEnum::INACTIVE];
        $config = $this->getDefaultConfig();
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->processor->processEnumChoices($choices, $config, $field);

        // Should not be converted since it's not a list array, but allChoicesAreEnums should be true
        $this->assertEquals($choices, $result['choices']);
        $this->assertTrue($result['config']['allChoicesAreEnums']); // All values are enums
    }

    public function testProcessEnumChoicesWithEmptyEnumArray(): void
    {
        $choices = [];
        $config = $this->getDefaultConfig();
        $config['enumTypeClass'] = ProcessorTestEnum::class;
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->processor->processEnumChoices($choices, $config, $field);

        $this->assertNotEmpty($result['choices']);
        $this->assertTrue($result['config']['allChoicesAreEnums']);
    }

    public function testProcessEnumChoicesWithNonExistentEnumClass(): void
    {
        $choices = [];
        $config = $this->getDefaultConfig();
        $config['enumTypeClass'] = 'NonExistentEnum';
        $field = new FieldDto();
        $field->setProperty('test_field');

        $result = $this->processor->processEnumChoices($choices, $config, $field);

        $this->assertEquals([], $result['choices']);
        // Empty array results in allChoicesAreEnums being true (no false values in empty array)
        $this->assertTrue($result['config']['allChoicesAreEnums']);
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
