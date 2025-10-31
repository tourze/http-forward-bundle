<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Exception;

/**
 * 当没有健康的后端服务器可用时抛出的异常
 */
class NoHealthyBackendException extends \RuntimeException
{
}
