<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Repository\BackendRepository;

/**
 * 统一的后端健康检查服务
 */
readonly class HealthCheckService
{
    public function __construct(
        private BackendRepository $backendRepository,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private int $defaultTimeout = 3,
    ) {
    }

    /**
     * 检查所有需要健康检查的后端
     *
     * @return array{healthy: int, unhealthy: int, errors: string[]}
     */
    public function checkAllBackends(?int $timeout = null): array
    {
        $backends = $this->backendRepository->findBackendsForHealthCheck();

        return $this->checkMultipleBackends($backends, $timeout);
    }

    /**
     * 批量健康检查
     *
     * @param Backend[] $backends
     * @return array{healthy: int, unhealthy: int, errors: string[]}
     */
    public function checkMultipleBackends(array $backends, ?int $timeout = null): array
    {
        $results = [
            'healthy' => 0,
            'unhealthy' => 0,
            'errors' => [],
        ];

        foreach ($backends as $backend) {
            try {
                $isHealthy = $this->checkAndUpdateBackend($backend, $timeout);

                if ($isHealthy) {
                    ++$results['healthy'];
                } else {
                    ++$results['unhealthy'];
                    $results['errors'][] = sprintf(
                        '后端 "%s" (%s) 健康检查失败',
                        $backend->getName(),
                        $backend->getUrl()
                    );
                }
            } catch (\Exception $e) {
                ++$results['unhealthy'];
                $this->updateBackendStatus($backend, false);
                $results['errors'][] = sprintf(
                    '后端 "%s" (%s) 检查异常: %s',
                    $backend->getName(),
                    $backend->getUrl(),
                    $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * 检查单个后端并更新状态
     */
    public function checkAndUpdateBackend(Backend $backend, ?int $timeout = null): bool
    {
        $isHealthy = $this->checkBackendHealth($backend, $timeout);
        $this->updateBackendStatus($backend, $isHealthy);

        return $isHealthy;
    }

    /**
     * 检查单个后端的健康状态
     */
    public function checkBackendHealth(Backend $backend, ?int $timeout = null): bool
    {
        $timeout ??= $this->defaultTimeout;
        $healthCheckPath = $backend->getHealthCheckPath();

        if (!$this->hasValidHealthCheckPath($healthCheckPath)) {
            return true;
        }

        $url = $this->buildHealthCheckUrl($backend, (string) $healthCheckPath);

        return null !== $url && $this->performHealthCheck($url, $timeout);
    }

    private function hasValidHealthCheckPath(?string $healthCheckPath): bool
    {
        return null !== $healthCheckPath && '' !== $healthCheckPath;
    }

    private function buildHealthCheckUrl(Backend $backend, string $healthCheckPath): ?string
    {
        if (str_starts_with($healthCheckPath, '/')) {
            return $this->buildAbsoluteUrl($backend, $healthCheckPath);
        }

        return rtrim($backend->getUrl(), '/') . '/' . ltrim($healthCheckPath, '/');
    }

    private function buildAbsoluteUrl(Backend $backend, string $healthCheckPath): ?string
    {
        $parts = parse_url($backend->getUrl());
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = $parts['port'] ?? null;

        if (is_int($port) && $this->shouldIncludePort($scheme, $port)) {
            return "{$scheme}://{$host}:{$port}{$healthCheckPath}";
        }

        return "{$scheme}://{$host}{$healthCheckPath}";
    }

    private function shouldIncludePort(string $scheme, int $port): bool
    {
        return !(('https' === $scheme && 443 === $port) || ('http' === $scheme && 80 === $port));
    }

    private function performHealthCheck(string $url, int $timeout): bool
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $timeout,
                'headers' => [
                    'User-Agent' => 'HttpForward-HealthChecker/1.0',
                    'Accept' => 'application/json,text/plain,*/*',
                ],
                'max_redirects' => 3,
            ]);

            $statusCode = $response->getStatusCode();

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 更新后端的健康状态
     */
    public function updateBackendStatus(Backend $backend, bool $isHealthy): void
    {
        $backend->setLastHealthCheck(new \DateTimeImmutable());
        $backend->setLastHealthStatus($isHealthy);

        if ($isHealthy) {
            if (BackendStatus::UNHEALTHY === $backend->getStatus()) {
                $backend->setStatus(BackendStatus::ACTIVE);
            }
        } else {
            $backend->setStatus(BackendStatus::UNHEALTHY);
        }

        $this->entityManager->persist($backend);
    }

    /**
     * 检查指定ID的后端
     *
     * @return array{success: bool, message: string, backend?: Backend, healthy?: bool}
     */
    public function checkBackendById(int $backendId, ?int $timeout = null): array
    {
        $backend = $this->backendRepository->find($backendId);

        if (null === $backend) {
            return [
                'success' => false,
                'message' => '后端服务器不存在',
            ];
        }

        try {
            $isHealthy = $this->checkAndUpdateBackend($backend, $timeout);

            return [
                'success' => true,
                'message' => $isHealthy
                    ? sprintf('后端服务器 "%s" 健康检查通过 ✅', $backend->getName())
                    : sprintf('后端服务器 "%s" 健康检查失败 ❌', $backend->getName()),
                'backend' => $backend,
                'healthy' => $isHealthy,
            ];
        } catch (\Exception $e) {
            $this->updateBackendStatus($backend, false);

            return [
                'success' => true,
                'message' => sprintf(
                    '后端服务器 "%s" 检查异常: %s',
                    $backend->getName(),
                    $e->getMessage()
                ),
                'backend' => $backend,
                'healthy' => false,
            ];
        }
    }

    /**
     * 获取默认超时时间
     */
    public function getDefaultTimeout(): int
    {
        return $this->defaultTimeout;
    }
}
