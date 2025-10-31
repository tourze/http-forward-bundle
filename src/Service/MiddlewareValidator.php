<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;

/**
 * 中间件配置验证器
 */
readonly class MiddlewareValidator
{
    public function __construct(
        private MiddlewareRegistry $middlewareRegistry,
    ) {
    }

    /**
     * 验证中间件配置
     *
     * @param array<array<string, mixed>> $middlewares
     * @param array<string, array<string, mixed>> $templates
     * @return array<string> 验证错误信息
     */
    public function validateMiddlewareConfig(array $middlewares, array $templates): array
    {
        $errors = [];
        $availableMiddlewares = array_keys($this->middlewareRegistry->all());

        foreach ($middlewares as $index => $middleware) {
            $middlewareErrors = $this->validateSingleMiddleware($middleware, $index, $availableMiddlewares, $templates);
            $errors = array_merge($errors, $middlewareErrors);
        }

        return $errors;
    }

    /**
     * 验证单个中间件
     *
     * @param array<string, mixed> $middleware
     * @param array<string> $availableMiddlewares
     * @param array<string, array<string, mixed>> $templates
     * @return array<string>
     */
    private function validateSingleMiddleware(array $middleware, mixed $index, array $availableMiddlewares, array $templates): array
    {
        $errors = [];
        $indexInt = is_int($index) ? $index : 0;

        if (!isset($middleware['name'])) {
            $errors[] = sprintf('中间件 #%d 缺少必需的 name 字段', $indexInt + 1);

            return $errors;
        }

        // PHPStan ensures $middleware['name'] exists and type validation follows
        $name = $middleware['name'];
        if (!is_string($name)) {
            $errors[] = sprintf('中间件 #%d 的 name 字段必须是字符串', $indexInt + 1);

            return $errors;
        }

        // $name is guaranteed string here
        if (!in_array($name, $availableMiddlewares, true)) {
            $errors[] = sprintf('中间件 "%s" 未注册或不可用', $name);

            return $errors;
        }

        $config = $middleware['config'] ?? [];
        if (!is_array($config)) {
            $errors[] = sprintf('中间件 "%s" 的配置必须是数组', $name);

            return $errors;
        }

        // 验证具体字段
        if (isset($templates[$name])) {
            $fields = $templates[$name]['fields'] ?? [];
            // 确保 $fields 是数组类型才进行后续处理
            if (is_array($fields) && [] !== $fields) {
                // 将config转换为字符串索引的数组
                $normalizedConfig = $this->normalizeArrayKeys($config);
                $normalizedFields = $this->normalizeArrayKeys($fields);

                $fieldErrors = $this->validateMiddlewareFields($name, $normalizedConfig, $normalizedFields);
                $errors = array_merge($errors, $fieldErrors);
            }
        }

        return $errors;
    }

    /**
     * 验证中间件的具体字段
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $fieldTemplates
     * @return array<string>
     */
    private function validateMiddlewareFields(string $middlewareName, array $config, array $fieldTemplates): array
    {
        $errors = [];

        foreach ($fieldTemplates as $fieldName => $template) {
            if (!is_array($template)) {
                continue;
            }

            // 将template规范化为字符串索引的数组
            $normalizedTemplate = $this->normalizeArrayKeys($template);
            $fieldErrors = $this->validateSingleField($middlewareName, $fieldName, $config, $normalizedTemplate);
            $errors = array_merge($errors, $fieldErrors);
        }

        return $errors;
    }

    /**
     * 验证单个字段
     *
     * @param string $middlewareName
     * @param string $fieldName
     * @param array<string, mixed> $config
     * @param array<string, mixed> $template
     * @return array<string>
     */
    private function validateSingleField(string $middlewareName, string $fieldName, array $config, array $template): array
    {
        $errors = [];

        if ($this->isRequiredFieldMissing($config, $fieldName, $template)) {
            $errors[] = sprintf('中间件 "%s" 缺少必需字段 "%s"', $middlewareName, $fieldName);

            return $errors;
        }

        if (!isset($config[$fieldName])) {
            return $errors;
        }

        // 类型验证
        $typeErrors = $this->validateFieldType($middlewareName, $fieldName, $config[$fieldName], $template);

        return array_merge($errors, $typeErrors);
    }

    /**
     * 检查必需字段是否缺失
     *
     * @param array<string, mixed> $config
     * @param string $fieldName
     * @param array<string, mixed> $template
     */
    private function isRequiredFieldMissing(array $config, string $fieldName, array $template): bool
    {
        $isRequired = isset($template['required']) && true === $template['required'];

        return $isRequired && !isset($config[$fieldName]);
    }

    /**
     * 验证字段类型
     *
     * @param array<string, mixed> $template
     * @return array<string>
     */
    private function validateFieldType(string $middlewareName, string $fieldName, mixed $value, array $template): array
    {
        $type = is_string($template['type'] ?? null) ? $template['type'] : 'text';

        return match ($type) {
            'boolean' => $this->validateBooleanField($middlewareName, $fieldName, $value),
            'text' => $this->validateTextField($middlewareName, $fieldName, $value),
            'choice' => $this->validateChoiceField($middlewareName, $fieldName, $value, $template),
            'array', 'collection' => $this->validateArrayField($middlewareName, $fieldName, $value, $type),
            default => [],
        };
    }

    /**
     * @return array<string>
     */
    private function validateBooleanField(string $middlewareName, string $fieldName, mixed $value): array
    {
        if (!is_bool($value)) {
            return [sprintf('中间件 "%s" 字段 "%s" 必须是布尔值', $middlewareName, $fieldName)];
        }

        return [];
    }

    /**
     * @return array<string>
     */
    private function validateTextField(string $middlewareName, string $fieldName, mixed $value): array
    {
        if (!is_string($value)) {
            return [sprintf('中间件 "%s" 字段 "%s" 必须是字符串', $middlewareName, $fieldName)];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string>
     */
    private function validateChoiceField(string $middlewareName, string $fieldName, mixed $value, array $template): array
    {
        if (!isset($template['choices']) || !is_array($template['choices'])) {
            return [];
        }

        $validValues = array_values($template['choices']);
        if (!in_array($value, $validValues, true)) {
            $validChoices = implode(', ', array_map(
                static fn (mixed $v): string => is_scalar($v) ? (string) $v : gettype($v),
                $validValues
            ));

            return [sprintf('中间件 "%s" 字段 "%s" 的值无效，有效选项：%s', $middlewareName, $fieldName, $validChoices)];
        }

        return [];
    }

    /**
     * @return array<string>
     */
    private function validateArrayField(string $middlewareName, string $fieldName, mixed $value, string $type): array
    {
        if (!is_array($value)) {
            $typeDescription = 'array' === $type ? '数组' : '数组（键值对）';

            return [sprintf('中间件 "%s" 字段 "%s" 必须是%s', $middlewareName, $fieldName, $typeDescription)];
        }

        return [];
    }

    /**
     * 将数组的键名规范化为字符串
     *
     * @param array<mixed, mixed> $array
     * @return array<string, mixed>
     */
    private function normalizeArrayKeys(array $array): array
    {
        $normalized = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            } elseif (is_int($key)) {
                $normalized[(string) $key] = $value;
            }
        }

        /** @var array<string, mixed> */
        return $normalized;
    }
}
