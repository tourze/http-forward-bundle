<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Service\RuleMatcher;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RuleMatcher::class)]
#[RunTestsInSeparateProcesses]
final class RuleMatcherTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 清理数据库并创建 schema
        self::cleanDatabase();
    }

    public function testMatchWithNoRules(): void
    {
        // 在集成测试环境中，我们应该测试真实的服务行为
        // 如果数据库中没有规则，应该返回 null
        $ruleMatcher = self::getService(RuleMatcher::class);

        $request = Request::create('/api/users', 'GET');
        $matched = $ruleMatcher->match($request);

        // 在空数据库中，预期没有匹配的规则
        $this->assertNull($matched);
    }

    public function testExtractParameters(): void
    {
        // extractParameters 方法不依赖数据库，可以直接测试
        $ruleMatcher = self::getService(RuleMatcher::class);

        $params = $ruleMatcher->extractParameters(
            '/api/users/{id}/posts/{postId}',
            '/api/users/123/posts/456'
        );

        $this->assertEquals([
            'id' => '123',
            'postId' => '456',
        ], $params);
    }

    public function testMatchWithExactPath(): void
    {
        // 在集成测试中创建真实的规则并测试
        $ruleMatcher = self::getService(RuleMatcher::class);

        // 创建一个规则并保存到数据库
        $rule = new ForwardRule();
        $rule->setSourcePath('/api/users');
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);

        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/users', 'GET');
        $matched = $ruleMatcher->match($request);

        $this->assertSame($rule, $matched);
    }

    public function testMatchWithWrongMethod(): void
    {
        // 在集成测试中创建只接受 POST 的规则
        $ruleMatcher = self::getService(RuleMatcher::class);

        $rule = new ForwardRule();
        $rule->setSourcePath('/api/users');
        $rule->setHttpMethods(['POST']);
        $rule->setEnabled(true);

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);

        self::getEntityManager()->persist($backend);
        self::getEntityManager()->persist($rule);
        self::getEntityManager()->flush();

        $request = Request::create('/api/users', 'GET');
        $matched = $ruleMatcher->match($request);

        $this->assertNull($matched);
    }
}
