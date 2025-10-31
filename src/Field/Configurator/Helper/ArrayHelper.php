<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Field\Configurator\Helper;

/**
 * 数组处理辅助类
 */
final class ArrayHelper
{
    /**
     * 扁平化选择数组
     *
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    public function flatten(array $choices): array
    {
        $flattened = [];

        foreach ($choices as $label => $choice) {
            $flattened = $this->flattenSingleChoice($flattened, $label, $choice);
        }

        return $flattened;
    }

    /**
     * 处理单个选择项的扁平化
     *
     * @param array<mixed> $flattened
     * @return array<mixed>
     */
    private function flattenSingleChoice(array $flattened, mixed $label, mixed $choice): array
    {
        if (\is_array($choice)) {
            return $this->flattenGroupedChoices($flattened, $choice);
        }
        if ($choice instanceof \BackedEnum) {
            $flattened[$choice->name] = $choice->value;
        } elseif ($choice instanceof \UnitEnum) {
            $flattened[$choice->name] = $choice->name;
        } else {
            // 确保键类型安全
            if (is_string($label) || is_int($label)) {
                $flattened[$label] = $choice;
            }
        }

        return $flattened;
    }

    /**
     * 处理分组选择项的扁平化
     *
     * @param array<mixed> $flattened
     * @param array<mixed> $groupedChoices
     * @return array<mixed>
     */
    private function flattenGroupedChoices(array $flattened, array $groupedChoices): array
    {
        foreach ($groupedChoices as $subLabel => $subChoice) {
            $flattened[$subLabel] = $subChoice;
        }

        return $flattened;
    }

    /**
     * 安全的 array_flip，过滤掉无法翻转的值
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public function safeArrayFlip(array $array): array
    {
        $flippable = [];

        foreach ($array as $key => $value) {
            // 只保留可以作为数组键的值（字符串和整数）
            if (is_string($value) || is_int($value)) {
                $flippable[$key] = $value;
            }
        }

        return array_flip($flippable);
    }
}
