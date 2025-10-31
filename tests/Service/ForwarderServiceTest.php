<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Service\ForwarderService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ForwarderService::class)]
#[RunTestsInSeparateProcesses]
final class ForwarderServiceTest extends AbstractIntegrationTestCase
{
    private ForwarderService $forwarder;

    protected function onSetUp(): void
    {
        $this->forwarder = self::getService(ForwarderService::class);
    }

    public function testForwardSimpleRequest(): void
    {
        $rule = new ForwardRule();
        $rule->setSourcePath('/api/users');
        $rule->setHttpMethods(['GET']);
        $rule->setStripPrefix(false);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/users', 'GET');

        // 由于这是一个集成测试，我们使用真实的容器服务
        // 这里我们只测试基本的请求转发功能，具体的 HTTP 客户端行为在单元测试中验证

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testForwardWithMiddleware(): void
    {
        $rule = new ForwardRule();
        $rule->setSourcePath('/api/data');
        $rule->setHttpMethods(['GET']);
        $rule->setMiddlewares([['name' => 'auth_header', 'config' => []]]);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/data', 'GET');

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testForwardWithStripPrefix(): void
    {
        $rule = new ForwardRule();
        $rule->setSourcePath('/api');
        $rule->setHttpMethods(['GET']);
        $rule->setStripPrefix(true);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/users/123', 'GET');

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testForwardWithRetry(): void
    {
        $rule = new ForwardRule();
        $rule->setSourcePath('/api');
        $rule->setHttpMethods(['GET']);
        $rule->setRetryCount(2);
        $rule->setRetryInterval(100);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/test', 'GET');

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testForwardWithError(): void
    {
        $rule = new ForwardRule();
        $rule->setSourcePath('/api');
        $rule->setHttpMethods(['GET']);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/error', 'GET');

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testForwardWithFallback(): void
    {
        $rule = new ForwardRule();
        $rule->setSourcePath('/api');
        $rule->setHttpMethods(['GET']);
        $rule->setFallbackType('STATIC');
        $rule->setFallbackConfig([
            'content' => 'Service temporarily unavailable',
            'status' => 503,
        ]);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/test', 'GET');

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testBackendSelectionAndLogging(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Backend Test Rule');
        $rule->setSourcePath('/api/backend-test');
        $rule->setHttpMethods(['GET']);
        $rule->setLoadBalanceStrategy('round_robin');

        // 创建多个Backend来测试负载均衡
        $backend1 = new Backend();
        $backend1->setName('Backend 1');
        $backend1->setUrl('https://api1.example.com');
        $backend1->setWeight(50);
        $backend1->setEnabled(true);
        $backend1->setStatus(BackendStatus::ACTIVE);

        $backend2 = new Backend();
        $backend2->setName('Backend 2');
        $backend2->setUrl('https://api2.example.com');
        $backend2->setWeight(100);
        $backend2->setEnabled(true);
        $backend2->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend1);
        $rule->addBackend($backend2);

        // 持久化实体
        self::getEntityManager()->persist($backend1);
        self::getEntityManager()->persist($backend2);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/backend-test', 'GET');

        // 执行转发
        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);

        // 验证规则正确关联了backend
        $this->assertCount(2, $rule->getBackends());
        $this->assertTrue($rule->hasBackends());
        $this->assertTrue($rule->hasHealthyBackends());

        // 验证backend可以通过规则获取
        $healthyBackends = $rule->getHealthyBackends();
        $this->assertCount(2, $healthyBackends);

        // 验证负载均衡策略设置正确
        $this->assertEquals('round_robin', $rule->getLoadBalanceStrategy());
    }
}
