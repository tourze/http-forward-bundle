<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Field\Configurator\Helper;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

use function Symfony\Component\String\u;

/**
 * 徽章配置辅助类
 */
final class BadgeConfigurationHelper
{
    /**
     * 准备徽章配置
     *
     * @return array{selector: array<string, string>|bool|callable|null, isRenderedAsBadge: bool}
     */
    public function prepareBadgeConfiguration(FieldDto $field): array
    {
        $badgeSelector = $field->getCustomOption(ChoiceField::OPTION_RENDER_AS_BADGES);
        $cleanSelector = $this->cleanBadgeSelector($badgeSelector);

        return [
            'selector' => $cleanSelector,
            'isRenderedAsBadge' => null !== $cleanSelector && false !== $cleanSelector,
        ];
    }

    /**
     * 清理徽章选择器，确保类型安全
     *
     * @return array<string, string>|bool|callable|null
     */
    private function cleanBadgeSelector(mixed $badgeSelector): array|bool|callable|null
    {
        if (is_array($badgeSelector)) {
            return $this->cleanArrayBadgeSelector($badgeSelector);
        }

        if (is_bool($badgeSelector) || is_callable($badgeSelector)) {
            return $badgeSelector;
        }

        return null;
    }

    /**
     * 清理数组类型的徽章选择器
     *
     * @param array<mixed> $badgeSelector
     * @return array<string, string>
     */
    private function cleanArrayBadgeSelector(array $badgeSelector): array
    {
        $cleanBadgeSelector = [];
        foreach ($badgeSelector as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $cleanBadgeSelector[$key] = $value;
            }
        }

        return $cleanBadgeSelector;
    }

    /**
     * 获取徽章CSS类
     *
     * @param array<string, string>|bool|callable|null $badgeSelector
     */
    public function getBadgeCssClass(array|bool|callable|null $badgeSelector, mixed $value, FieldDto $field): string
    {
        $badgeType = $this->determineBadgeType($badgeSelector, $value, $field);
        $badgeTypeCssClass = $this->formatBadgeTypeCssClass($badgeType);

        return 'badge' . ('' !== $badgeTypeCssClass ? ' ' . $badgeTypeCssClass : '');
    }

    /**
     * 确定徽章类型
     *
     * @param array<string, string>|bool|callable|null $badgeSelector
     */
    private function determineBadgeType(array|bool|callable|null $badgeSelector, mixed $value, FieldDto $field): string
    {
        if (true === $badgeSelector) {
            return 'badge-secondary';
        }

        if (is_array($badgeSelector)) {
            // 确保传入的数组符合期望的类型
            $cleanBadgeSelector = $this->cleanArrayBadgeSelector($badgeSelector);

            return $this->getArrayBadgeType($cleanBadgeSelector, $value);
        }

        if (is_callable($badgeSelector)) {
            return $this->validateCallableBadgeType($badgeSelector($value, $field));
        }

        return '';
    }

    /**
     * 从数组选择器获取徽章类型
     *
     * @param array<string, string> $badgeSelector
     */
    /**
     * 从数组选择器获取徽章类型
     *
     * @param array<string, string> $badgeSelector
     */
    private function getArrayBadgeType(array $badgeSelector, mixed $value): string
    {
        if (is_string($value) && isset($badgeSelector[$value])) {
            return $badgeSelector[$value];
        }

        return 'badge-secondary';
    }

    /**
     * 验证可调用徽章类型
     */
    private function validateCallableBadgeType(mixed $badgeType): string
    {
        if (!in_array($badgeType, ChoiceField::VALID_BADGE_TYPES, true)) {
            $badgeTypeStr = is_scalar($badgeType) ? (string) $badgeType : gettype($badgeType);
            throw new \RuntimeException(sprintf('The value returned by the callable passed to the "renderAsBadges()" method must be one of the following valid badge types: "%s" ("%s" given).', implode(', ', ChoiceField::VALID_BADGE_TYPES), $badgeTypeStr));
        }

        return $badgeType;
    }

    /**
     * 格式化徽章类型CSS类
     */
    private function formatBadgeTypeCssClass(string $badgeType): string
    {
        return '' === $badgeType ? '' : u($badgeType)->ensureStart('badge-')->toString();
    }
}
