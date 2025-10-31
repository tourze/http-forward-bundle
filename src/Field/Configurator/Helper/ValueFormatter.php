<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Field\Configurator\Helper;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Translation\TranslatableChoiceMessage;
use EasyCorp\Bundle\EasyAdminBundle\Translation\TranslatableChoiceMessageCollection;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * 值格式化处理器
 */
final class ValueFormatter
{
    public function __construct(
        private readonly BadgeConfigurationHelper $badgeHelper,
        private readonly EnumProcessor $enumProcessor,
        private readonly ArrayHelper $arrayHelper,
    ) {
    }

    /**
     * 配置格式化值
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
    public function configureFormattedValue(FieldDto $field, array $choices, array $config, AdminContext $context): void
    {
        $fieldValue = $this->prepareFieldValue($field, $context);
        if (null === $fieldValue) {
            return;
        }

        $badgeConfig = $this->badgeHelper->prepareBadgeConfiguration($field);
        $translationConfig = $this->prepareTranslationConfiguration($context);
        $flippedChoices = $config['areChoicesTranslatable'] ? $choices : $this->arrayHelper->safeArrayFlip($this->arrayHelper->flatten($choices));

        $choiceMessages = $this->buildChoiceMessages($fieldValue, $flippedChoices, $config, $badgeConfig, $translationConfig, $field);
        $field->setFormattedValue(new TranslatableChoiceMessageCollection($choiceMessages, $badgeConfig['isRenderedAsBadge']));
    }

    /**
     * 准备字段值
     *
     * @return array<mixed>|null
     */
    private function prepareFieldValue(FieldDto $field, AdminContext $context): ?array
    {
        $fieldValue = $field->getValue();
        $crud = $context->getCrud();
        if (null === $crud) {
            return null;
        }
        $isIndexOrDetail = \in_array($crud->getCurrentPage(), [Crud::PAGE_INDEX, Crud::PAGE_DETAIL], true);

        if (null === $fieldValue || !$isIndexOrDetail) {
            return null;
        }

        return $fieldValue instanceof \UnitEnum ? [$fieldValue] : (array) $fieldValue;
    }

    /**
     * 准备翻译配置
     *
     * @return array{parameters: array<string, mixed>, domain: string|null}
     */
    private function prepareTranslationConfiguration(AdminContext $context): array
    {
        return [
            'parameters' => $context->getI18n()->getTranslationParameters(),
            'domain' => $context->getI18n()->getTranslationDomain(),
        ];
    }

    /**
     * 构建选择消息
     *
     * @param array<mixed> $fieldValue
     * @param array<mixed> $flippedChoices
     * @param array{
     *     areChoicesTranslatable: bool,
     *     choicesSupportTranslatableInterface: bool,
     *     isExpanded: bool,
     *     isMultipleChoice: bool,
     *     enumTypeClass: string|null,
     *     allChoicesAreEnums: bool
     * } $config
     * @param array{selector: array<string, string>|bool|callable|null, isRenderedAsBadge: bool} $badgeConfig
     * @param array{parameters: array<string, mixed>, domain: string|null} $translationConfig
     * @return array<TranslatableChoiceMessage>
     */
    private function buildChoiceMessages(array $fieldValue, array $flippedChoices, array $config, array $badgeConfig, array $translationConfig, FieldDto $field): array
    {
        $choiceMessages = [];

        foreach ($fieldValue as $selectedValue) {
            $selectedValue = $this->enumProcessor->normalizeSelectedValue($selectedValue, $config);
            // 确保键类型安全
            if (!is_string($selectedValue) && !is_int($selectedValue)) {
                continue;
            }
            $selectedLabel = $flippedChoices[$selectedValue] ?? null;

            if (null === $selectedLabel) {
                continue;
            }

            if (!is_string($selectedLabel)) {
                $selectedLabel = is_scalar($selectedLabel) ? (string) $selectedLabel : '';
            }
            $choiceMessage = $this->createTranslatableMessage($selectedLabel, $translationConfig);
            $badgeCss = $badgeConfig['isRenderedAsBadge']
                ? $this->badgeHelper->getBadgeCssClass($badgeConfig['selector'], $selectedValue, $field)
                : null;

            $choiceMessages[] = new TranslatableChoiceMessage($choiceMessage, $badgeCss);
        }

        return $choiceMessages;
    }

    /**
     * 创建可翻译消息
     *
     * @param array{parameters: array<string, mixed>, domain: string|null} $translationConfig
     */
    private function createTranslatableMessage(mixed $selectedLabel, array $translationConfig): TranslatableMessage
    {
        if ($selectedLabel instanceof TranslatableMessage) {
            return $selectedLabel;
        }

        $labelStr = is_string($selectedLabel) ? $selectedLabel : (is_scalar($selectedLabel) ? (string) $selectedLabel : '');

        return t($labelStr, $translationConfig['parameters'], $translationConfig['domain']);
    }
}
