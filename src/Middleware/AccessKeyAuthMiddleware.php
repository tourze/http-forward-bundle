<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Config\AccessKeyAuthConfig;
use Tourze\HttpForwardBundle\Constant\RequestAttributes;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Service\AccessKeyAuthService;

#[WithMonologChannel(channel: 'http_forward')]
class AccessKeyAuthMiddleware extends AbstractMiddleware
{
    protected int $priority = 200;

    public function __construct(
        private readonly AccessKeyAuthService $authService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getServiceAlias(): string
    {
        return 'access_key_auth';
    }

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $authConfig = AccessKeyAuthConfig::fromArray($config);

        // 如果未启用授权验证，直接返回
        if (!$authConfig->enabled) {
            return $request;
        }

        try {
            // 提取Bearer token
            $bearerToken = $this->extractBearerToken($request);

            // 如果没有token且配置为非必须，则允许通过
            if (null === $bearerToken) {
                if (!$authConfig->required) {
                    return $request;
                }

                $this->logger->info('Missing Authorization header', [
                    'path' => $request->getPathInfo(),
                    'method' => $request->getMethod(),
                ]);

                throw new \RuntimeException('Authorization header is required', 401);
            }

            // 获取客户端IP
            $clientIp = $this->getClientIp($request);

            // 执行授权验证
            $authResult = $this->authService->authorize($bearerToken, $clientIp);

            // 设置请求属性
            $request->attributes->set(RequestAttributes::AUTH_RESULT, $authResult);
            $request->attributes->set(RequestAttributes::CLIENT_IP, $clientIp);

            if ($authResult->success && null !== $authResult->accessKey) {
                $request->attributes->set(RequestAttributes::ACCESS_KEY, $authResult->accessKey);
                $log->setAccessKey($authResult->accessKey);
                $this->logger->info('Authorization successful', [
                    'appId' => $bearerToken,
                    'clientIp' => $clientIp,
                ]);
            } else {
                $this->logger->warning('Authorization failed', [
                    'appId' => $bearerToken,
                    'clientIp' => $clientIp,
                    'errorCode' => $authResult->errorCode,
                    'errorMessage' => $authResult->errorMessage,
                ]);

                throw new \RuntimeException($authResult->errorMessage ?? 'Authorization failed', $this->getHttpStatusCode($authResult->errorCode));
            }

            return $request;
        } catch (\Exception $e) {
            if ('permissive' === $authConfig->fallbackMode) {
                $this->logger->warning('Authorization failed but using permissive fallback', [
                    'error' => $e->getMessage(),
                    'path' => $request->getPathInfo(),
                ]);

                return $request;
            }

            $this->logger->error('Authorization failed in strict mode', [
                'error' => $e->getMessage(),
                'path' => $request->getPathInfo(),
            ]);

            // 在严格模式下抛出异常，让上层处理HTTP响应
            throw $e;
        }
    }

    /**
     * 从Authorization头中提取Bearer token
     */
    private function extractBearerToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');

        if (null === $authHeader) {
            return null;
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7); // 移除 "Bearer " 前缀

        return '' === $token ? null : $token;
    }

    /**
     * 获取客户端真实IP地址
     */
    private function getClientIp(Request $request): ?string
    {
        // 优先使用X-Forwarded-For
        $forwardedFor = $request->headers->get('X-Forwarded-For');
        if (null !== $forwardedFor) {
            $ips = explode(',', $forwardedFor);
            $firstIp = trim($ips[0]);
            if ('' !== $firstIp) {
                return $firstIp;
            }
        }

        // 其次使用X-Real-IP
        $realIp = $request->headers->get('X-Real-IP');
        if (null !== $realIp && '' !== $realIp) {
            return $realIp;
        }

        // 最后使用直连IP
        return $request->getClientIp();
    }

    /**
     * 根据错误代码获取HTTP状态码
     */
    private function getHttpStatusCode(?string $errorCode): int
    {
        return match ($errorCode) {
            'INVALID_TOKEN', 'INACTIVE_KEY' => 401,
            'IP_DENIED' => 403,
            default => 500,
        };
    }
}
