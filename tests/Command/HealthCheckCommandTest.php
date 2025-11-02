<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\HttpForwardBundle\Command\HealthCheckCommand;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Repository\BackendRepository;
use Tourze\HttpForwardBundle\Service\HealthCheckService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(HealthCheckCommand::class)]
#[RunTestsInSeparateProcesses]
class HealthCheckCommandTest extends AbstractCommandTestCase
{
    private HealthCheckCommand $command;

    /** @var BackendRepository&MockObject */
    private BackendRepository $backendRepository;

    /** @var HttpClientInterface&MockObject */
    private HttpClientInterface $httpClient;

    /** @var HealthCheckService&MockObject */
    private HealthCheckService $healthCheckService;

    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function testExecuteWithNoBackends(): void
    {
        $this->backendRepository
            ->method('findBy')
            ->willReturn([])
        ;

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('没有找到需要健康检查的后端服务器', $this->commandTester->getDisplay());
    }

    public function testExecuteWithHealthyBackend(): void
    {
        $backend = $this->createBackend('http://example.com/health', BackendStatus::ACTIVE);

        $this->backendRepository
            ->method('findBy')
            ->willReturn([$backend])
        ;

        $this->httpClient
            ->method('request')
            ->willReturn($this->createMockResponse(200, 'OK'))
        ;

        $this->healthCheckService
            ->method('checkAndUpdateBackend')
            ->with($backend, 30)
            ->willReturn(true)
        ;

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
    }

    public function testExecuteWithUnhealthyBackend(): void
    {
        $backend = $this->createBackend('http://example.com/health', BackendStatus::ACTIVE);

        $this->backendRepository
            ->method('findBy')
            ->willReturn([$backend])
        ;

        $this->httpClient
            ->method('request')
            ->willReturn($this->createMockResponse(500, 'Internal Server Error'))
        ;

        $this->healthCheckService
            ->method('checkAndUpdateBackend')
            ->with($backend, 30)
            ->willReturn(false)
        ;

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
    }

    public function testExecuteWithTimeout(): void
    {
        $backend = $this->createBackend('http://example.com/health', BackendStatus::ACTIVE);

        $this->backendRepository
            ->method('findBy')
            ->willReturn([$backend])
        ;

        $this->healthCheckService
            ->method('checkAndUpdateBackend')
            ->with($backend, 5)
            ->willReturn(true)
        ;

        $exitCode = $this->commandTester->execute(['--timeout' => '5']);

        $this->assertSame(0, $exitCode);
    }

    public function testOptionTimeout(): void
    {
        // This method is required by AbstractCommandTestCase to ensure timeout option is tested
        $this->testExecuteWithTimeout();
        // Verify the test actually executed by checking command tester exists
        $this->assertInstanceOf(CommandTester::class, $this->commandTester);
    }

    public function testOptionBackendId(): void
    {
        $backend = $this->createBackend('http://example.com/health', BackendStatus::ACTIVE);

        $this->backendRepository
            ->method('find')
            ->with(1)
            ->willReturn($backend)
        ;

        $this->httpClient
            ->method('request')
            ->willReturn($this->createMockResponse(200, 'OK'))
        ;

        $this->healthCheckService
            ->method('getDefaultTimeout')
            ->willReturn(3)
        ;

        $this->healthCheckService
            ->method('checkAndUpdateBackend')
            ->with($backend, 3)
            ->willReturn(true)
        ;

        $exitCode = $this->commandTester->execute(['--backend-id' => '1']);

        $this->assertSame(0, $exitCode);
    }

    public function testOptionDryRun(): void
    {
        $backend = $this->createBackend('http://example.com/health', BackendStatus::ACTIVE);

        $this->backendRepository
            ->method('findBy')
            ->willReturn([$backend])
        ;

        $exitCode = $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * @return Backend&MockObject
     */
    private function createBackend(string $url, BackendStatus $status): Backend
    {
        $backend = $this->createMock(Backend::class);
        $backend->method('getUrl')->willReturn($url);
        $backend->method('getStatus')->willReturn($status);
        $backend->method('getName')->willReturn('test-backend');
        $backend->method('getId')->willReturn(1);

        return $backend;
    }

    /**
     * @return ResponseInterface&MockObject
     */
    private function createMockResponse(int $statusCode, string $content): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->willReturn($content);

        return $response;
    }

    protected function onSetUp(): void
    {
        $this->backendRepository = $this->createMock(BackendRepository::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->healthCheckService = $this->createMock(HealthCheckService::class);

        // 直接手动构造 Command 实例，注入 Mock 依赖
        // 避免使用 container->set()，因为服务可能已经被初始化
        $this->command = new HealthCheckCommand(
            $this->backendRepository,
            self::getService(EntityManagerInterface::class),
            $this->healthCheckService
        );

        $this->commandTester = new CommandTester($this->command);
    }
}
