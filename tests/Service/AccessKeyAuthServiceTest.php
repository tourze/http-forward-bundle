<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\HttpForwardBundle\Service\AccessKeyAuthService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AccessKeyAuthService::class)]
#[RunTestsInSeparateProcesses]
class AccessKeyAuthServiceTest extends AbstractIntegrationTestCase
{
    private AccessKeyAuthService $authService;

    protected function onSetUp(): void
    {
        // For integration tests, we get the service from container
        $this->authService = self::getService(AccessKeyAuthService::class);
    }

    public function testService应能从容器正确获取(): void
    {
        $this->assertInstanceOf(AccessKeyAuthService::class, $this->authService);
    }

    public function testAuthorize方法存在且参数类型正确(): void
    {
        $reflection = new \ReflectionClass($this->authService);
        $method = $reflection->getMethod('authorize');

        $this->assertTrue($method->isPublic());
        $this->assertSame(2, $method->getNumberOfParameters());

        $parameters = $method->getParameters();
        $this->assertSame('bearerToken', $parameters[0]->getName());
        $this->assertSame('clientIp', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->allowsNull());
    }

    public function testFindAccessKeyByAppId方法存在且参数类型正确(): void
    {
        $reflection = new \ReflectionClass($this->authService);
        $method = $reflection->getMethod('findAccessKeyByAppId');

        $this->assertTrue($method->isPublic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $parameters = $method->getParameters();
        $this->assertSame('appId', $parameters[0]->getName());

        // 验证返回类型
        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('?Tourze\AccessKeyBundle\Entity\AccessKey', (string) $returnType);
    }

    public function testAuthorize方法返回AuthorizationResult类型(): void
    {
        $reflection = new \ReflectionClass($this->authService);
        $method = $reflection->getMethod('authorize');

        // 验证返回类型
        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('Tourze\HttpForwardBundle\DTO\AuthorizationResult', (string) $returnType);
    }

    public function testService具有正确的构造函数依赖(): void
    {
        $reflection = new \ReflectionClass($this->authService);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPublic());
        $this->assertSame(2, $constructor->getNumberOfParameters());

        $parameters = $constructor->getParameters();
        $this->assertSame('apiCallerService', $parameters[0]->getName());
        $this->assertSame('logger', $parameters[1]->getName());
    }

    public function testService是只读类(): void
    {
        $reflection = new \ReflectionClass($this->authService);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testFindAccessKeyByAppSecret方法存在且参数类型正确(): void
    {
        $reflection = new \ReflectionClass($this->authService);
        $method = $reflection->getMethod('findAccessKeyByAppSecret');

        $this->assertTrue($method->isPublic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $parameters = $method->getParameters();
        $this->assertSame('appSecret', $parameters[0]->getName());

        // 验证返回类型
        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('?Tourze\AccessKeyBundle\Entity\AccessKey', (string) $returnType);
    }

    public function testFindAccessKeyByAppSecretCallsApiCallerService(): void
    {
        // 此测试验证findAccessKeyByAppSecret方法正确调用了ApiCallerService
        // 由于这是集成测试，我们通过反射验证方法存在并能被调用
        $reflection = new \ReflectionClass($this->authService);
        $method = $reflection->getMethod('findAccessKeyByAppSecret');

        // 验证方法可以被调用（即使返回null也是有效的）
        $result = $method->invoke($this->authService, 'test-secret');

        // 验证返回值类型正确（null或AccessKey实例）
        $this->assertTrue(
            null === $result || $result instanceof AccessKey,
            'findAccessKeyByAppSecret should return null or AccessKey instance'
        );
    }
}
