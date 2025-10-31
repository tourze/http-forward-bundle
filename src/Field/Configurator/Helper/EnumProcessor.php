<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Field\Configurator\Helper;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * 枚举处理器
 */
final class EnumProcessor
{
    /**
     * 处理枚举选择项
     *
     * @param array<mixed> $choices
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
    public function processEnumChoices(array $choices, array $config, FieldDto $field): array
    {
        $config = $this->detectEnumTypes($choices, $config);
        $result = $this->handleEmptyChoicesWithEnum($choices, $config);
        $choices = $result['choices'];
        $config = $result['config'];
        $config = $this->configureTranslatableEnums($config);
        $choices = $this->processEnumFormType($choices, $config, $field);

        return [
            'choices' => $choices,
            'config' => $config,
        ];
    }

    /**
     * 检测枚举类型
     *
     * @param array<mixed> $choices
     * @param array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * } $config
     * @return array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * }
     */
    private function detectEnumTypes(array $choices, array $config): array
    {
        $elementIsEnum = array_unique(array_map(static function ($element): bool {
            return \is_object($element) && enum_exists($element::class);
        }, $choices));
        $config['allChoicesAreEnums'] = false === \in_array(false, $elementIsEnum, true);

        return $config;
    }

    /**
     * 处理空选择项的枚举情况
     *
     * @param array<mixed> $choices
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
    private function handleEmptyChoicesWithEnum(array $choices, array $config): array
    {
        $enumTypeClass = $config['enumTypeClass'];
        if (0 === \count($choices) && null !== $enumTypeClass && enum_exists($enumTypeClass)) {
            $choices = $enumTypeClass::cases();
            $config['allChoicesAreEnums'] = true;
        }

        return [
            'choices' => $choices,
            'config' => $config,
        ];
    }

    /**
     * 配置可翻译枚举
     *
     * @param array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * } $config
     * @return array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * }
     */
    private function configureTranslatableEnums(array $config): array
    {
        if (null !== $config['enumTypeClass'] && is_subclass_of($config['enumTypeClass'], TranslatableInterface::class)) {
            $config['areChoicesTranslatable'] = $config['choicesSupportTranslatableInterface'] = true;
        }

        return $config;
    }

    /**
     * 处理枚举表单类型
     *
     * @param array<mixed> $choices
     * @param array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * } $config
     * @return array<mixed>
     */
    private function processEnumFormType(array $choices, array $config, FieldDto $field): array
    {
        if (!$config['allChoicesAreEnums'] || !array_is_list($choices) || 0 === \count($choices)) {
            return $choices;
        }

        $processedEnumChoices = [];
        foreach ($choices as $choice) {
            if (is_object($choice) && property_exists($choice, 'name') && is_string($choice->name)) {
                $processedEnumChoices[$choice->name] = $choice;
            }
        }
        $choices = $processedEnumChoices;

        if (ChoiceType::class === $field->getFormType()) {
            $field->setFormType(EnumType::class);
        }
        $field->setFormTypeOptionIfNotSet('class', $config['enumTypeClass']);

        return $choices;
    }

    /**
     * 标准化选择的值
     *
     * @param array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * } $config
     */
    public function normalizeSelectedValue(mixed $selectedValue, array $config): mixed
    {
        return match (true) {
            $selectedValue instanceof \BackedEnum => $config['allChoicesAreEnums'] && $config['choicesSupportTranslatableInterface'] ? $selectedValue->name : $selectedValue->value,
            $selectedValue instanceof \UnitEnum => $selectedValue->name,
            default => $selectedValue,
        };
    }
}
