<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Field\Configurator\Helper;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

/**
 * 表单配置器
 */
final class FormConfigurator
{
    /**
     * 配置表单选项
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
     */
    public function configureFormOptions(FieldDto $field, array $choices, array $config): void
    {
        if ($config['areChoicesTranslatable'] && !$config['choicesSupportTranslatableInterface']) {
            $field->setFormTypeOptionIfNotSet('choices', array_keys($choices));
            $field->setFormTypeOptionIfNotSet('choice_label', function ($value) use ($choices) {
                return is_string($value) && array_key_exists($value, $choices) ? $choices[$value] : $value;
            });
        } else {
            $field->setFormTypeOptionIfNotSet('choices', $choices);
        }
        $field->setFormTypeOptionIfNotSet('multiple', $config['isMultipleChoice']);
        $field->setFormTypeOptionIfNotSet('expanded', $config['isExpanded']);
    }

    /**
     * 配置组件部件
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
    public function configureWidget(FieldDto $field, array $config): void
    {
        $this->validateWidgetConfiguration($field, $config);
        $this->setWidgetType($field, $config);
        $this->configureAutocompleteWidget($field, $config);
        $this->setWidgetAttributes($field);
    }

    /**
     * 验证组件配置
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
    private function validateWidgetConfiguration(FieldDto $field, array $config): void
    {
        if ($config['isExpanded'] && ChoiceField::WIDGET_AUTOCOMPLETE === $field->getCustomOption(ChoiceField::OPTION_WIDGET)) {
            throw new \InvalidArgumentException(sprintf('The "%s" choice field wants to be displayed as an autocomplete widget and as an expanded list of choices at the same time, which is not possible. Use the renderExpanded() and renderAsNativeWidget() methods to change one of those options.', $field->getProperty()));
        }
    }

    /**
     * 设置组件类型
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
    private function setWidgetType(FieldDto $field, array $config): void
    {
        if (null === $field->getCustomOption(ChoiceField::OPTION_WIDGET)) {
            $widgetType = $config['isExpanded'] ? ChoiceField::WIDGET_NATIVE : ChoiceField::WIDGET_AUTOCOMPLETE;
            $field->setCustomOption(ChoiceField::OPTION_WIDGET, $widgetType);
        }
    }

    /**
     * 配置自动完成组件
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
    private function configureAutocompleteWidget(FieldDto $field, array $config): void
    {
        if (ChoiceField::WIDGET_AUTOCOMPLETE === $field->getCustomOption(ChoiceField::OPTION_WIDGET)) {
            $field->setFormTypeOption('attr.data-ea-widget', 'ea-autocomplete');
            $columns = $config['isMultipleChoice'] ? 'col-md-8 col-xxl-6' : 'col-md-6 col-xxl-5';
            $field->setDefaultColumns($columns);
        }
    }

    /**
     * 设置组件属性
     */
    private function setWidgetAttributes(FieldDto $field): void
    {
        $field->setFormTypeOptionIfNotSet('placeholder', '');
        $escapeHtml = true === $field->getCustomOption(ChoiceField::OPTION_ESCAPE_HTML_CONTENTS) ? 'false' : 'true';
        $field->setFormTypeOption('attr.data-ea-autocomplete-render-items-as-html', $escapeHtml);
    }
}
