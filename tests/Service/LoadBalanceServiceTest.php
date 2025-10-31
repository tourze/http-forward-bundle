<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Exception\NoHealthyBackendException;
use Tourze\HttpForwardBundle\Service\LoadBalanceService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(LoadBalanceService::class)]
#[RunTestsInSeparateProcesses]
final class LoadBalanceServiceTest extends AbstractIntegrationTestCase
{
    private LoadBalanceService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(LoadBalanceService::class);
    }

    public function testSelectBackendWithNoHealthyBackends(): void
    {
        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getHealthyBackends')
            ->willReturn([])
        ;
        $rule->method('getName')
            ->willReturn('test-rule')
        ;

        $request = new Request();

        $this->expectException(NoHealthyBackendException::class);
        $this->expectExceptionMessage('No healthy backends available for rule "test-rule"');

        $this->service->selectBackend($rule, $request);
    }

    public function testSelectBackendWithSingleHealthyBackend(): void
    {
        $backend = $this->createBackend('backend1', 'https://example.com', 1);

        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getHealthyBackends')
            ->willReturn([$backend])
        ;

        $request = new Request();

        $selectedBackend = $this->service->selectBackend($rule, $request);

        $this->assertSame($backend, $selectedBackend);
    }

    #[DataProvider('loadBalanceStrategyDataProvider')]
    public function testSelectBackendWithMultipleHealthyBackends(string $strategy): void
    {
        $backend1 = $this->createBackend('backend1', 'https://example1.com', 1);
        $backend2 = $this->createBackend('backend2', 'https://example2.com', 2);
        $backends = [$backend1, $backend2];

        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getHealthyBackends')
            ->willReturn($backends)
        ;
        $rule->method('getLoadBalanceStrategy')
            ->willReturn($strategy)
        ;

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        $selectedBackend = $this->service->selectBackend($rule, $request);

        $this->assertInstanceOf(Backend::class, $selectedBackend);
        $this->assertContains($selectedBackend, $backends);
    }

    /**
     * @return array<string, array<string>>
     */
    public static function loadBalanceStrategyDataProvider(): array
    {
        return [
            'round_robin' => ['round_robin'],
            'random' => ['random'],
            'weighted_round_robin' => ['weighted_round_robin'],
            'least_connections' => ['least_connections'],
            'ip_hash' => ['ip_hash'],
            'unknown_strategy' => ['unknown_strategy'], // 应该回退到 round_robin
        ];
    }

    public function testGetAvailableStrategies(): void
    {
        $strategies = $this->service->getAvailableStrategies();

        $expectedStrategies = [
            'round_robin' => '轮询',
            'random' => '随机',
            'weighted_round_robin' => '加权轮询',
            'least_connections' => '最少连接',
            'ip_hash' => 'IP哈希',
        ];

        $this->assertEquals($expectedStrategies, $strategies);
    }

    public function testHasAvailableBackendsWithHealthyBackends(): void
    {
        $backend = $this->createBackend('backend1', 'https://example.com', 1);

        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getHealthyBackends')
            ->willReturn([$backend])
        ;

        $result = $this->service->hasAvailableBackends($rule);

        $this->assertTrue($result);
    }

    public function testHasAvailableBackendsWithNoHealthyBackends(): void
    {
        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getHealthyBackends')
            ->willReturn([])
        ;

        $result = $this->service->hasAvailableBackends($rule);

        $this->assertFalse($result);
    }

    public function testGetBackendStats(): void
    {
        $enabledHealthyBackend = $this->createBackend('backend1', 'https://example1.com', 1, true, true);
        $enabledUnhealthyBackend = $this->createBackend('backend2', 'https://example2.com', 1, true, false);
        $disabledHealthyBackend = $this->createBackend('backend3', 'https://example3.com', 1, false, true);
        $disabledUnhealthyBackend = $this->createBackend('backend4', 'https://example4.com', 1, false, false);

        $allBackends = [
            $enabledHealthyBackend,
            $enabledUnhealthyBackend,
            $disabledHealthyBackend,
            $disabledUnhealthyBackend,
        ];

        $healthyBackends = [
            $enabledHealthyBackend,
            $disabledHealthyBackend,
        ];

        $backendCollection = $this->createMock(Collection::class);
        $backendCollection->method('toArray')
            ->willReturn($allBackends)
        ;

        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getBackends')
            ->willReturn($backendCollection)
        ;
        $rule->method('getHealthyBackends')
            ->willReturn($healthyBackends)
        ;

        $stats = $this->service->getBackendStats($rule);

        $expectedStats = [
            'total' => 4,
            'healthy' => 2,
            'unhealthy' => 2,
            'enabled' => 2,
            'disabled' => 2,
            'health_rate' => 50.0,
        ];

        $this->assertEquals($expectedStats, $stats);
    }

    public function testGetBackendStatsWithNoBackends(): void
    {
        $backendCollection = $this->createMock(Collection::class);
        $backendCollection->method('toArray')
            ->willReturn([])
        ;

        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getBackends')
            ->willReturn($backendCollection)
        ;
        $rule->method('getHealthyBackends')
            ->willReturn([])
        ;

        $stats = $this->service->getBackendStats($rule);

        $expectedStats = [
            'total' => 0,
            'healthy' => 0,
            'unhealthy' => 0,
            'enabled' => 0,
            'disabled' => 0,
            'health_rate' => 0,
        ];

        $this->assertEquals($expectedStats, $stats);
    }

    public function testSelectBackendWithIpHashStrategy(): void
    {
        $backend1 = $this->createBackend('backend1', 'https://example1.com', 1);
        $backend2 = $this->createBackend('backend2', 'https://example2.com', 1);
        $backends = [$backend1, $backend2];

        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getHealthyBackends')
            ->willReturn($backends)
        ;
        $rule->method('getLoadBalanceStrategy')
            ->willReturn('ip_hash')
        ;

        // 测试相同IP应该返回相同的backend
        $request1 = new Request([], [], [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);
        $request2 = new Request([], [], [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        $selectedBackend1 = $this->service->selectBackend($rule, $request1);
        $selectedBackend2 = $this->service->selectBackend($rule, $request2);

        $this->assertSame($selectedBackend1, $selectedBackend2);
    }

    public function testSelectBackendWithNoClientIp(): void
    {
        $backend = $this->createBackend('backend1', 'https://example.com', 1);

        $rule = $this->createMock(ForwardRule::class);
        $rule->method('getHealthyBackends')
            ->willReturn([$backend])
        ;
        $rule->method('getLoadBalanceStrategy')
            ->willReturn('ip_hash')
        ;

        $request = new Request(); // 没有设置客户端IP

        $selectedBackend = $this->service->selectBackend($rule, $request);

        $this->assertSame($backend, $selectedBackend);
    }

    private static int $backendIdCounter = 0;

    private function createBackend(
        string $name,
        string $url,
        int $weight,
        bool $enabled = true,
        bool $healthy = true,
    ): Backend {
        $backend = $this->createMock(Backend::class);
        $backend->method('getId')
            ->willReturn(++self::$backendIdCounter)
        ;
        $backend->method('getName')
            ->willReturn($name)
        ;
        $backend->method('getUrl')
            ->willReturn($url)
        ;
        $backend->method('getWeight')
            ->willReturn($weight)
        ;
        $backend->method('isEnabled')
            ->willReturn($enabled)
        ;
        $backend->method('getStatus')
            ->willReturn($healthy ? BackendStatus::ACTIVE : BackendStatus::INACTIVE) // 健康状态通过status判断
        ;

        return $backend;
    }
}
