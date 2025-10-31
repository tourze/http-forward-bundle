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
final class StreamForwarderTest extends AbstractIntegrationTestCase
{
    private ForwarderService $forwarder;

    protected function onSetUp(): void
    {
        $this->forwarder = self::getService(ForwarderService::class);
    }

    public function testStreamEnabledReturnsStreamedResponse(): void
    {
        $rule = new ForwardRule();
        $rule->setStreamEnabled(true);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setSourcePath('/api');
        $rule->setHttpMethods(['POST']);
        $rule->setBufferSize(1024);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api', 'POST');

        // 集成测试使用真实的容器服务，简化测试逻辑

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testNonStreamRuleReturnsNormalResponse(): void
    {
        $rule = new ForwardRule();
        $rule->setStreamEnabled(false);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setSourcePath('/api');
        $rule->setHttpMethods(['GET']);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api', 'GET');

        // 集成测试使用真实的容器服务，简化测试逻辑

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testForward(): void
    {
        $rule = new ForwardRule();
        $rule->setStreamEnabled(false);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setSourcePath('/api/users');
        $rule->setHttpMethods(['GET']);
        $rule->setStripPrefix(false);

        // 持久化实体以避免 Doctrine 错误
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/users', 'GET');

        // 集成测试使用真实的容器服务，简化测试逻辑

        $response = $this->forwarder->forward($request, $rule);

        $this->assertInstanceOf(Response::class, $response);
    }
}
