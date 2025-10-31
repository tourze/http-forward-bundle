<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use Tourze\HttpForwardBundle\Service\MiddlewareConfigManager;

final class MiddlewareCollectionField
{
    /**
     * 创建带有中间件配置助手的字段
     */
    public static function newWithHelper(
        string $propertyName,
        ?string $label = null,
        ?MiddlewareConfigManager $configManager = null,
    ): CodeEditorField {
        $field = self::new($propertyName, $label);

        if (null !== $configManager) {
            $field->setCustomOption('middleware_config_manager', $configManager);
            $field->setCustomOption('available_middlewares', $configManager->getAvailableMiddlewares());
        }

        // 中间件字段将通过JavaScript自动隐藏JSON编辑器，保留可视化界面

        return $field;
    }

    /**
     * 创建增强的中间件配置字段
     */
    public static function new(string $propertyName, ?string $label = null): CodeEditorField
    {
        return CodeEditorField::new($propertyName, $label)
            ->setLanguage('javascript')
            ->setNumOfRows(15)
            ->setDefaultColumns('col-md-12')
            ->setHelp('配置此转发规则使用的中间件。格式：[{"name": "中间件名", "config": {...}}]')
            ->formatValue(static function ($value) {
                if (null === $value || [] === $value) {
                    return '[]';
                }
                if (is_string($value)) {
                    // 验证是否为有效JSON
                    $decoded = json_decode($value, true);
                    if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
                        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    return $value;
                }

                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            })
        ;
    }
}
