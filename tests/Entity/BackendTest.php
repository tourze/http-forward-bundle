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
#[CoversClass(Backend::class)]
class BackendTest extends AbstractEntityTestCase
{
    private Backend $backend;

    protected function setUp(): void
    {
        $this->backend = new Backend();
    }

    protected function createEntity(): Backend
    {
        return new Backend();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->backend->getId());
        $this->assertSame('', $this->backend->getName());
        $this->assertSame('', $this->backend->getUrl());
        $this->assertSame(1, $this->backend->getWeight());
        $this->assertTrue($this->backend->isEnabled());
        $this->assertSame(BackendStatus::ACTIVE, $this->backend->getStatus());
        $this->assertSame(30, $this->backend->getTimeout());
        $this->assertSame(100, $this->backend->getMaxConnections());
        $this->assertNull($this->backend->getHealthCheckPath());
        $this->assertNull($this->backend->getLastHealthCheck());
        $this->assertNull($this->backend->getLastHealthStatus());
        $this->assertNull($this->backend->getAvgResponseTime());
        $this->assertSame([], $this->backend->getMetadata());
        $this->assertNull($this->backend->getDescription());
        $this->assertEmpty($this->backend->getForwardRules());
    }

    public function testSettersAndGetters(): void
    {
        $this->backend->setName('Test Backend');
        $this->assertSame('Test Backend', $this->backend->getName());

        $this->backend->setUrl('https://api.example.com');
        $this->assertSame('https://api.example.com', $this->backend->getUrl());

        $this->backend->setWeight(50);
        $this->assertSame(50, $this->backend->getWeight());

        $this->backend->setEnabled(false);
        $this->assertFalse($this->backend->isEnabled());

        $this->backend->setStatus(BackendStatus::INACTIVE);
        $this->assertSame(BackendStatus::INACTIVE, $this->backend->getStatus());

        $this->backend->setTimeout(60);
        $this->assertSame(60, $this->backend->getTimeout());

        $this->backend->setMaxConnections(200);
        $this->assertSame(200, $this->backend->getMaxConnections());

        $this->backend->setHealthCheckPath('/health');
        $this->assertSame('/health', $this->backend->getHealthCheckPath());

        $lastHealthCheck = new \DateTimeImmutable();
        $this->backend->setLastHealthCheck($lastHealthCheck);
        $this->assertSame($lastHealthCheck, $this->backend->getLastHealthCheck());

        $this->backend->setLastHealthStatus(true);
        $this->assertTrue($this->backend->getLastHealthStatus());

        $this->backend->setAvgResponseTime(150.5);
        $this->assertSame(150.5, $this->backend->getAvgResponseTime());

        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $this->backend->setMetadata($metadata);
        $this->assertSame($metadata, $this->backend->getMetadata());

        $this->backend->setDescription('Test description');
        $this->assertSame('Test description', $this->backend->getDescription());
    }

    public function testToStringWithName(): void
    {
        $this->backend->setName('API Server');
        $this->assertSame('API Server', (string) $this->backend);
    }

    public function testToStringWithoutName(): void
    {
        // Mock getId method using reflection since it's readonly
        $reflection = new \ReflectionClass($this->backend);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->backend, 123);

        $this->assertSame('Backend #123', (string) $this->backend);
    }

    public function testAddForwardRule(): void
    {
        $forwardRule = $this->createMock(ForwardRule::class);
        $forwardRule->expects($this->once())
            ->method('addBackend')
            ->with($this->backend)
        ;

        // Method now returns void due to static analysis priority principle
        $this->backend->addForwardRule($forwardRule);

        $this->assertTrue($this->backend->getForwardRules()->contains($forwardRule));
    }

    public function testAddForwardRuleAlreadyExists(): void
    {
        $forwardRule = $this->createMock(ForwardRule::class);
        $forwardRule->expects($this->once())
            ->method('addBackend')
            ->with($this->backend)
        ;

        // Add once
        $this->backend->addForwardRule($forwardRule);

        // Add again - should not call addBackend again
        $forwardRule->expects($this->never())
            ->method('addBackend')
        ;

        $this->backend->addForwardRule($forwardRule);
        $this->assertTrue($this->backend->getForwardRules()->contains($forwardRule));
        $this->assertCount(1, $this->backend->getForwardRules());
    }

    public function testRemoveForwardRule(): void
    {
        $forwardRule = $this->createMock(ForwardRule::class);

        // Add first
        $forwardRule->expects($this->once())
            ->method('addBackend')
            ->with($this->backend)
        ;
        $this->backend->addForwardRule($forwardRule);

        // Then remove
        $forwardRule->expects($this->once())
            ->method('removeBackend')
            ->with($this->backend)
        ;

        $this->backend->removeForwardRule($forwardRule);
        $this->assertFalse($this->backend->getForwardRules()->contains($forwardRule));
    }

    public function testRemoveForwardRuleNotExists(): void
    {
        $forwardRule = $this->createMock(ForwardRule::class);
        $forwardRule->expects($this->never())
            ->method('removeBackend')
        ;

        $this->backend->removeForwardRule($forwardRule);
    }

    public function testFluentInterface(): void
    {
        $forwardRule1 = $this->createMock(ForwardRule::class);
        $forwardRule2 = $this->createMock(ForwardRule::class);

        // addBackend now returns void due to static analysis priority principle
        $forwardRule1->method('addBackend');
        $forwardRule2->method('addBackend');

        // Fluent interface no longer supported - methods return void
        $this->backend->addForwardRule($forwardRule1);
        $this->backend->addForwardRule($forwardRule2);

        $this->assertCount(2, $this->backend->getForwardRules());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', 'Test Backend'];
        yield 'url' => ['url', 'https://api.example.com'];
        yield 'weight' => ['weight', 50];
        yield 'enabled' => ['enabled', false];
        yield 'status' => ['status', BackendStatus::INACTIVE];
        yield 'timeout' => ['timeout', 60];
        yield 'maxConnections' => ['maxConnections', 200];
        yield 'healthCheckPath' => ['healthCheckPath', '/health'];
        yield 'lastHealthStatus' => ['lastHealthStatus', true];
        yield 'avgResponseTime' => ['avgResponseTime', 150.5];
        yield 'metadata' => ['metadata', ['key1' => 'value1', 'key2' => 'value2']];
        yield 'description' => ['description', 'Test description'];
    }
}
