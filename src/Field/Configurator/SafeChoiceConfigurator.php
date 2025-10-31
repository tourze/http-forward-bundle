<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Field\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\ArrayHelper;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\BadgeConfigurationHelper;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\EnumProcessor;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\FormConfigurator;
use Tourze\HttpForwardBundle\Field\Configurator\Helper\ValueFormatter;

/**
 * 安全的 Choice 配置器，修复 array_flip() 警告问题
 */
final class SafeChoiceConfigurator implements FieldConfiguratorInterface
{
    private readonly EnumProcessor $enumProcessor;

    private readonly FormConfigurator $formConfigurator;

    private readonly ValueFormatter $valueFormatter;

    public function __construct()
    {
        $arrayHelper = new ArrayHelper();
        $badgeHelper = new BadgeConfigurationHelper();
        $this->enumProcessor = new EnumProcessor();
        $this->formConfigurator = new FormConfigurator();
        $this->valueFormatter = new ValueFormatter($badgeHelper, $this->enumProcessor, $arrayHelper);
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return ChoiceField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $config = $this->extractFieldConfiguration($field);
        $preparedData = $this->prepareChoices($field, $entityDto, $config);
        $choices = $preparedData['choices'];
        $config = $preparedData['config'];

        $this->formConfigurator->configureFormOptions($field, $choices, $config);
        $this->formConfigurator->configureWidget($field, $config);
        $this->valueFormatter->configureFormattedValue($field, $choices, $config, $context);
    }

    /**
     * 提取字段配置
     *
     * @return array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * }
     */
    private function extractFieldConfiguration(FieldDto $field): array
    {
        $enumTypeClass = $field->getDoctrineMetadata()->get('enumType');

        return [
            'areChoicesTranslatable' => true === $field->getCustomOption(ChoiceField::OPTION_USE_TRANSLATABLE_CHOICES),
            'choicesSupportTranslatableInterface' => false,
            'isExpanded' => true === $field->getCustomOption(ChoiceField::OPTION_RENDER_EXPANDED),
            'isMultipleChoice' => true === $field->getCustomOption(ChoiceField::OPTION_ALLOW_MULTIPLE_CHOICES),
            'enumTypeClass' => is_string($enumTypeClass) ? $enumTypeClass : null,
            'allChoicesAreEnums' => false,
        ];
    }

    /**
     * 准备选择项
     *
     * @param array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * } $config
     * @return array{choices: array<mixed>, config: array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * }}
     */
    private function prepareChoices(FieldDto $field, EntityDto $entityDto, array $config): array
    {
        $choiceOption = $field->getCustomOption(ChoiceField::OPTION_CHOICES);
        $choices = $this->getChoices($choiceOption, $entityDto, $field);

        if (null === $choices) {
            $choices = [];
        }

        return $this->enumProcessor->processEnumChoices($choices, $config, $field);
    }

    /**
     * @param mixed $choiceGenerator
     *
     * @return array<mixed>|null
     */
    private function getChoices(mixed $choiceGenerator, EntityDto $entity, FieldDto $field): ?array
    {
        if (null === $choiceGenerator) {
            return null;
        }

        if (\is_array($choiceGenerator)) {
            return $choiceGenerator;
        }

        if (\is_callable($choiceGenerator)) {
            $result = $choiceGenerator($entity->getInstance(), $field);

            return is_array($result) ? $result : null;
        }

        return null;
    }
}
