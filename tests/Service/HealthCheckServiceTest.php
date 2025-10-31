<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Repository\BackendRepository;
use Tourze\HttpForwardBundle\Service\HealthCheckService;

/**
 * @internal
 */
#[CoversClass(HealthCheckService::class)]
class HealthCheckServiceTest extends TestCase
{
    /**
     * @var MockObject&BackendRepository
     */
    private MockObject $backendRepository;

    /**
     * @var MockObject&EntityManagerInterface
     */
    private MockObject $entityManager;

    /**
     * @var MockObject&HttpClientInterface
     */
    private MockObject $httpClient;

    private HealthCheckService $healthCheckService;

    protected function setUp(): void
    {
        $this->backendRepository = $this->createMock(BackendRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->healthCheckService = new HealthCheckService(
            $this->backendRepository,
            $this->entityManager,
            $this->httpClient,
            3
        );
    }

    public function testCheckAllBackends(): void
    {
        $backend1 = $this->createBackend('Backend 1', 'https://api1.example.com', '/health');
        $backend2 = $this->createBackend('Backend 2', 'https://api2.example.com', '/status');

        $this->backendRepository
            ->expects(self::once())
            ->method('findBackendsForHealthCheck')
            ->willReturn([$backend1, $backend2])
        ;

        $response1 = $this->createMock(ResponseInterface::class);
        $response1->method('getStatusCode')->willReturn(200);

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->method('getStatusCode')->willReturn(503);

        $this->httpClient
            ->expects(self::exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($response1, $response2)
        ;

        $this->entityManager
            ->expects(self::exactly(2))
            ->method('persist')
        ;

        $result = $this->healthCheckService->checkAllBackends();

        self::assertSame(1, $result['healthy']);
        self::assertSame(1, $result['unhealthy']);
        self::assertCount(1, $result['errors']);
        self::assertStringContainsString('Backend 2', $result['errors'][0]);
    }

    public function testCheckBackendHealthWithValidPath(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '/health');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/health',
                self::callback(function (array $options): bool {
                    return 3 === $options['timeout']
                        && isset($options['headers'])
                        && is_array($options['headers'])
                        && isset($options['headers']['User-Agent'])
                        && 3 === $options['max_redirects'];
                })
            )
            ->willReturn($response)
        ;

        $result = $this->healthCheckService->checkBackendHealth($backend);

        self::assertTrue($result);
    }

    public function testCheckBackendHealthWithoutHealthCheckPath(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', null);

        $this->httpClient
            ->expects(self::never())
            ->method('request')
        ;

        $result = $this->healthCheckService->checkBackendHealth($backend);

        self::assertTrue($result);
    }

    public function testCheckBackendHealthWithEmptyPath(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '');

        $this->httpClient
            ->expects(self::never())
            ->method('request')
        ;

        $result = $this->healthCheckService->checkBackendHealth($backend);

        self::assertTrue($result);
    }

    public function testCheckBackendHealthWithNonAbsolutePath(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', 'health');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with('GET', 'https://api.example.com/health')
            ->willReturn($response)
        ;

        $result = $this->healthCheckService->checkBackendHealth($backend);

        self::assertTrue($result);
    }

    public function testCheckBackendHealthWithHttpException(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '/health');

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection timeout'))
        ;

        $result = $this->healthCheckService->checkBackendHealth($backend);

        self::assertFalse($result);
    }

    public function testUpdateBackendStatusHealthy(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '/health');
        $backend->setStatus(BackendStatus::UNHEALTHY);

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($backend)
        ;

        $this->healthCheckService->updateBackendStatus($backend, true);

        self::assertTrue($backend->getLastHealthStatus());
        self::assertSame(BackendStatus::ACTIVE, $backend->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $backend->getLastHealthCheck());
    }

    public function testUpdateBackendStatusUnhealthy(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '/health');
        $backend->setStatus(BackendStatus::ACTIVE);

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($backend)
        ;

        $this->healthCheckService->updateBackendStatus($backend, false);

        self::assertFalse($backend->getLastHealthStatus());
        self::assertSame(BackendStatus::UNHEALTHY, $backend->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $backend->getLastHealthCheck());
    }

    public function testCheckBackendById(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '/health');

        $this->backendRepository
            ->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($backend)
        ;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($response)
        ;

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
        ;

        $result = $this->healthCheckService->checkBackendById(1);

        self::assertTrue($result['success']);
        self::assertStringContainsString('健康检查通过', $result['message']);
        self::assertArrayHasKey('backend', $result);
        self::assertArrayHasKey('healthy', $result);
        // PHPStan无法追踪assertArrayHasKey的效果，所以使用局部变量
        $resultBackend = $result['backend'] ?? null;
        $resultHealthy = $result['healthy'] ?? false;
        self::assertSame($backend, $resultBackend);
        self::assertTrue($resultHealthy);
    }

    public function testCheckBackendByIdNotFound(): void
    {
        $this->backendRepository
            ->expects(self::once())
            ->method('find')
            ->with(999)
            ->willReturn(null)
        ;

        $this->httpClient
            ->expects(self::never())
            ->method('request')
        ;

        $result = $this->healthCheckService->checkBackendById(999);

        self::assertFalse($result['success']);
        self::assertSame('后端服务器不存在', $result['message']);
        self::assertArrayNotHasKey('backend', $result);
    }

    public function testGetDefaultTimeout(): void
    {
        $timeout = $this->healthCheckService->getDefaultTimeout();

        self::assertSame(3, $timeout);
    }

    public function testCheckAndUpdateBackend(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '/health');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with('GET', 'https://api.example.com/health')
            ->willReturn($response)
        ;

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($backend)
        ;

        $result = $this->healthCheckService->checkAndUpdateBackend($backend);

        self::assertTrue($result);
        self::assertTrue($backend->getLastHealthStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $backend->getLastHealthCheck());
    }

    public function testCheckAndUpdateBackendWithFailure(): void
    {
        $backend = $this->createBackend('Test Backend', 'https://api.example.com', '/health');
        $backend->setStatus(BackendStatus::ACTIVE);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection failed'))
        ;

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($backend)
        ;

        $result = $this->healthCheckService->checkAndUpdateBackend($backend);

        self::assertFalse($result);
        self::assertFalse($backend->getLastHealthStatus());
        self::assertSame(BackendStatus::UNHEALTHY, $backend->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $backend->getLastHealthCheck());
    }

    public function testCheckMultipleBackendsWithMixedResults(): void
    {
        $backend1 = $this->createBackend('Backend 1', 'https://api1.example.com', '/health');
        $backend2 = $this->createBackend('Backend 2', 'https://api2.example.com', '/status');
        $backend3 = $this->createBackend('Backend 3', 'https://api3.example.com', '/health');

        $response1 = $this->createMock(ResponseInterface::class);
        $response1->method('getStatusCode')->willReturn(200);

        $response3 = $this->createMock(ResponseInterface::class);
        $response3->method('getStatusCode')->willReturn(200);

        $this->httpClient
            ->expects(self::exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url): ResponseInterface {
                if (str_contains($url, 'api2.example.com')) {
                    throw new \RuntimeException('Connection refused');
                }
                if (str_contains($url, 'api1.example.com')) {
                    $response = $this->createMock(ResponseInterface::class);
                    $response->method('getStatusCode')->willReturn(200);

                    return $response;
                }
                $response = $this->createMock(ResponseInterface::class);
                $response->method('getStatusCode')->willReturn(200);

                return $response;
            })
        ;

        $this->entityManager
            ->expects(self::exactly(3))
            ->method('persist')
        ;

        $result = $this->healthCheckService->checkMultipleBackends([$backend1, $backend2, $backend3]);

        self::assertSame(2, $result['healthy']);
        self::assertSame(1, $result['unhealthy']);
        self::assertCount(1, $result['errors']);
        self::assertStringContainsString('Backend 2', $result['errors'][0]);
        self::assertStringContainsString('健康检查失败', $result['errors'][0]);
    }

    private function createBackend(string $name, string $url, ?string $healthCheckPath): Backend
    {
        $backend = new Backend();
        $backend->setName($name);
        $backend->setUrl($url);
        $backend->setHealthCheckPath($healthCheckPath);
        $backend->setStatus(BackendStatus::ACTIVE);
        $backend->setEnabled(true);

        return $backend;
    }
}
