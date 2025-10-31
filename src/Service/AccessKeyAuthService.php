<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AccessKeyBundle\Service\ApiCallerService;
use Tourze\HttpForwardBundle\DTO\AuthorizationResult;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'http_forward')]
readonly class AccessKeyAuthService
{
    public function __construct(
        private ApiCallerService $apiCallerService,
        private LoggerInterface $logger,
    ) {
    }

    public function authorize(string $bearerToken, ?string $clientIp = null): AuthorizationResult
    {
        try {
            // 查找AccessKey
            $accessKey = $this->findAccessKeyByAppSecret($bearerToken);
            if (null === $accessKey) {
                $this->logger->info('Access key not found', ['bearerToken' => $bearerToken]);

                return AuthorizationResult::failure('INVALID_TOKEN', 'Invalid or inactive access key');
            }

            // 验证AccessKey是否有效
            $isValid = $accessKey->isValid();
            if (null === $isValid || false === $isValid) {
                $this->logger->info('Access key is inactive', ['bearerToken' => $bearerToken]);

                return AuthorizationResult::failure('INACTIVE_KEY', 'Access key is inactive');
            }

            // IP白名单验证
            if (null !== $clientIp && !$this->isIpAllowed($accessKey, $clientIp)) {
                $this->logger->warning('IP access denied', [
                    'bearerToken' => $bearerToken,
                    'clientIp' => $clientIp,
                    'allowedIps' => $accessKey->getAllowIps(),
                ]);

                return AuthorizationResult::failure('IP_DENIED', 'Client IP is not allowed');
            }

            $this->logger->info('Authorization successful', [
                'bearerToken' => $bearerToken,
                'clientIp' => $clientIp,
            ]);

            return AuthorizationResult::success($accessKey);
        } catch (\Exception $e) {
            $this->logger->error('Authorization failed with exception', [
                'bearerToken' => $bearerToken,
                'error' => $e->getMessage(),
            ]);

            return AuthorizationResult::failure('SYSTEM_ERROR', 'Authorization system error');
        }
    }

    public function findAccessKeyByAppSecret(string $appSecret): ?AccessKey
    {
        return $this->apiCallerService->findValidApiCallerByAppSecret($appSecret);
    }

    public function findAccessKeyByAppId(string $appId): ?AccessKey
    {
        return $this->apiCallerService->findValidApiCallerByAppId($appId);
    }

    /**
     * 验证客户端IP是否在AccessKey的IP白名单中
     */
    private function isIpAllowed(AccessKey $accessKey, string $clientIp): bool
    {
        $allowedIps = $accessKey->getAllowIps();

        // 如果没有配置IP白名单，则允许所有IP
        if (null === $allowedIps || [] === $allowedIps) {
            return true;
        }

        // 检查IP是否在白名单中
        foreach ($allowedIps as $allowedIp) {
            if ($this->matchIp($clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查IP是否匹配，支持CIDR格式
     */
    private function matchIp(string $clientIp, string $allowedIp): bool
    {
        // 精确匹配
        if ($clientIp === $allowedIp) {
            return true;
        }

        // CIDR格式匹配
        if (str_contains($allowedIp, '/')) {
            return $this->matchCidr($clientIp, $allowedIp);
        }

        return false;
    }

    /**
     * CIDR格式IP匹配（仅支持IPv4以降低复杂度）
     */
    private function matchCidr(string $ip, string $cidr): bool
    {
        [$network, $prefixLength] = explode('/', $cidr, 2);
        $prefixLength = (int) $prefixLength;

        // 验证输入
        $ipValid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $networkValid = filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

        if (false === $ipValid || false === $networkValid) {
            return false;
        }

        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);

        if (false === $ipLong || false === $networkLong) {
            return false;
        }

        $mask = -1 << (32 - $prefixLength);

        return ($ipLong & $mask) === ($networkLong & $mask);
    }
}
