<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Constant;

/**
 * Request 属性键名常量定义
 * 中间件在 Request 对象中设置的属性键名
 */
final class RequestAttributes
{
    /**
     * AccessKey 实体
     */
    public const ACCESS_KEY = 'http_forward.access_key';

    /**
     * AuthorizationResult 对象
     */
    public const AUTH_RESULT = 'http_forward.authorization_result';

    /**
     * 客户端真实 IP
     */
    public const CLIENT_IP = 'http_forward.client_ip';
}
