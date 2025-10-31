<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Config;

/**
 * AccessKeyAuthMiddleware 支持的配置项
 */
readonly class AccessKeyAuthConfig
{
    public function __construct(
        public bool $enabled = true,        // 是否启用授权验证
        public bool $required = true,       // 是否必须提供 token (false 允许无 token 通过)
        public string $fallbackMode = 'strict', // 异常降级模式: strict|permissive
    ) {
    }

    /**
     * 从中间件配置数组创建配置对象
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            required: (bool) ($config['required'] ?? true),
            fallbackMode: is_string($config['fallback_mode'] ?? null) ? $config['fallback_mode'] : 'strict'
        );
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'required' => $this->required,
            'fallback_mode' => $this->fallbackMode,
        ];
    }

    /**
     * 验证配置是否有效
     */
    public function isValid(): bool
    {
        return in_array($this->fallbackMode, ['strict', 'permissive'], true);
    }
}
