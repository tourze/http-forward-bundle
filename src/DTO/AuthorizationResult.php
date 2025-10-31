<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\DTO;

use Tourze\AccessKeyBundle\Entity\AccessKey;

/**
 * 授权结果数据传输对象
 */
readonly class AuthorizationResult
{
    public function __construct(
        public bool $success,           // 是否授权成功
        public ?AccessKey $accessKey,   // 关联的访问密钥
        public ?string $errorCode,      // 错误代码: INVALID_TOKEN, INACTIVE_KEY, IP_DENIED
        public ?string $errorMessage,    // 错误消息
    ) {
    }

    public static function success(AccessKey $accessKey): self
    {
        return new self(
            success: true,
            accessKey: $accessKey,
            errorCode: null,
            errorMessage: null
        );
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(
            success: false,
            accessKey: null,
            errorCode: $errorCode,
            errorMessage: $errorMessage
        );
    }
}
