<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(ForwardRule::class)]
final class ForwardRuleTest extends AbstractEntityTestCase
{
    public function testDefaultValues(): void
    {
        $rule = new ForwardRule();
        $this->assertEquals(['GET'], $rule->getHttpMethods());
        $this->assertTrue($rule->isEnabled());
        $this->assertEquals(100, $rule->getPriority());
        $this->assertTrue($rule->isStripPrefix());
        $this->assertEquals(30, $rule->getTimeout());
        $this->assertEquals(0, $rule->getRetryCount());
        $this->assertEquals(1000, $rule->getRetryInterval());
        $this->assertFalse($rule->isStreamEnabled());
        $this->assertEquals(8192, $rule->getBufferSize());
        $this->assertEquals([], $rule->getMiddlewares());
        $this->assertEquals('round_robin', $rule->getLoadBalanceStrategy());
        $this->assertCount(0, $rule->getBackends());
        $this->assertFalse($rule->hasBackends());
    }

    public function testSettersAndGetters(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $this->assertEquals('Test Rule', $rule->getName());

        $rule->setSourcePath('/api/*');
        $this->assertEquals('/api/*', $rule->getSourcePath());

        // 测试Backend关联
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(50);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);

        $rule->addBackend($backend);
        $this->assertCount(1, $rule->getBackends());
        $this->assertTrue($rule->hasBackends());
        $firstBackend = $rule->getBackends()->first();
        $this->assertNotFalse($firstBackend, 'Should have first backend');
        $this->assertEquals('Test Backend', $firstBackend->getName());
        $this->assertEquals('https://api.example.com', $firstBackend->getUrl());

        $rule->setHttpMethods(['GET', 'POST']);
        $this->assertEquals(['GET', 'POST'], $rule->getHttpMethods());

        $rule->setEnabled(false);
        $this->assertFalse($rule->isEnabled());

        $rule->setPriority(50);
        $this->assertEquals(50, $rule->getPriority());

        $rule->setStripPrefix(true);
        $this->assertTrue($rule->isStripPrefix());

        $rule->setTimeout(60);
        $this->assertEquals(60, $rule->getTimeout());

        $rule->setRetryCount(3);
        $this->assertEquals(3, $rule->getRetryCount());

        $rule->setRetryInterval(5000);
        $this->assertEquals(5000, $rule->getRetryInterval());

        $rule->setStreamEnabled(true);
        $this->assertTrue($rule->isStreamEnabled());

        $rule->setBufferSize(4096);
        $this->assertEquals(4096, $rule->getBufferSize());
    }

    public function testFallbackConfiguration(): void
    {
        $rule = new ForwardRule();
        $this->assertNull($rule->getFallbackType());
        $this->assertNull($rule->getFallbackConfig());

        $rule->setFallbackType('STATIC');
        $this->assertEquals('STATIC', $rule->getFallbackType());

        $fallbackConfig = ['content' => 'Service unavailable', 'status' => 503];
        $rule->setFallbackConfig($fallbackConfig);
        $this->assertEquals($fallbackConfig, $rule->getFallbackConfig());

        $rule->setFallbackType('BACKUP');
        $this->assertEquals('BACKUP', $rule->getFallbackType());
    }

    public function testMiddlewareConfiguration(): void
    {
        $rule = new ForwardRule();
        $middlewares = [
            'auth_header' => ['token' => 'test'],
            'retry' => ['max_retries' => 3],
        ];

        $rule->setMiddlewares($middlewares);
        $this->assertEquals($middlewares, $rule->getMiddlewares());
    }

    public function testTimestamps(): void
    {
        $rule = new ForwardRule();
        $this->assertNull($rule->getCreateTime());
        $this->assertNull($rule->getUpdateTime());
    }

    public function testToString(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $rule->setSourcePath('/api/*');

        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);

        $string = (string) $rule;
        $this->assertStringContainsString('Test Rule', $string);
        $this->assertStringContainsString('/api/*', $string);
        $this->assertStringContainsString('https://api.example.com', $string);
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', 'Test Rule'];
        yield 'sourcePath' => ['sourcePath', '/api/*'];
        yield 'httpMethods' => ['httpMethods', ['GET', 'POST']];
        yield 'enabled' => ['enabled', true];
        yield 'priority' => ['priority', 50];
        yield 'middlewares' => ['middlewares', [['name' => 'test', 'config' => []]]];
        yield 'stripPrefix' => ['stripPrefix', true];
        yield 'timeout' => ['timeout', 60];
        yield 'retryCount' => ['retryCount', 3];
        yield 'retryInterval' => ['retryInterval', 5000];
        yield 'fallbackType' => ['fallbackType', 'STATIC'];
        yield 'fallbackConfig' => ['fallbackConfig', ['content' => 'Service unavailable', 'status' => 503]];
        yield 'streamEnabled' => ['streamEnabled', true];
        yield 'bufferSize' => ['bufferSize', 4096];
        yield 'loadBalanceStrategy' => ['loadBalanceStrategy', 'weighted_round_robin'];
    }

    protected function createEntity(): ForwardRule
    {
        return new ForwardRule();
    }
}
