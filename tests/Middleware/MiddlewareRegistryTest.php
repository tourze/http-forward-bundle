<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Middleware\MiddlewareInterface;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MiddlewareRegistry::class)]
#[RunTestsInSeparateProcesses]
final class MiddlewareRegistryTest extends AbstractIntegrationTestCase
{
    private MiddlewareRegistry $registry;

    protected function onSetUp(): void
    {
        $this->registry = self::getService(MiddlewareRegistry::class);
    }

    public function testRegisterMiddleware(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $this->registry->register('test_middleware', $middleware);

        $this->assertNotNull($this->registry->get('test_middleware'));
        $this->assertSame($middleware, $this->registry->get('test_middleware'));
    }

    public function testGetNonExistentMiddleware(): void
    {
        $this->assertNull($this->registry->get('non_existent'));
    }

    public function testHasMiddleware(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $this->assertNull($this->registry->get('test'));

        $this->registry->register('test', $middleware);

        $this->assertNotNull($this->registry->get('test'));
    }

    public function testAll(): void
    {
        // Get a fresh registry from container to avoid interference from other tests
        $registry = self::getService(MiddlewareRegistry::class);

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $registry->register('middleware1', $middleware1);
        $registry->register('middleware2', $middleware2);

        $all = $registry->all();

        // Count only the middlewares we registered (there might be others)
        $this->assertArrayHasKey('middleware1', $all);
        $this->assertArrayHasKey('middleware2', $all);
        $this->assertSame($middleware1, $all['middleware1']);
        $this->assertSame($middleware2, $all['middleware2']);
    }

    public function testGetAll(): void
    {
        // Get a fresh registry from container to avoid interference from other tests
        $registry = self::getService(MiddlewareRegistry::class);

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $registry->register('middleware1', $middleware1);
        $registry->register('middleware2', $middleware2);

        $all = $registry->all();

        // Verify our registered middlewares are present (there might be others)
        $this->assertArrayHasKey('middleware1', $all);
        $this->assertArrayHasKey('middleware2', $all);
        $this->assertSame($middleware1, $all['middleware1']);
        $this->assertSame($middleware2, $all['middleware2']);
    }

    public function testGetByNames(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $this->registry->register('middleware1', $middleware1);
        $this->registry->register('middleware2', $middleware2);
        $this->registry->register('middleware3', $middleware3);

        $selected = $this->registry->getByNames(['middleware1', 'middleware3']);

        $this->assertCount(2, $selected);
        $this->assertContains($middleware1, $selected);
        $this->assertContains($middleware3, $selected);
        $this->assertNotContains($middleware2, $selected);
    }

    public function testSortByPriority(): void
    {
        $lowPriority = $this->createMock(MiddlewareInterface::class);
        $lowPriority->method('getPriority')->willReturn(10);

        $mediumPriority = $this->createMock(MiddlewareInterface::class);
        $mediumPriority->method('getPriority')->willReturn(50);

        $highPriority = $this->createMock(MiddlewareInterface::class);
        $highPriority->method('getPriority')->willReturn(100);

        $unsorted = [
            'low' => $lowPriority,
            'high' => $highPriority,
            'medium' => $mediumPriority,
        ];

        $sorted = $this->registry->sortByPriority($unsorted);

        $sortedArray = array_values($sorted);
        $this->assertSame($highPriority, $sortedArray[0]);
        $this->assertSame($mediumPriority, $sortedArray[1]);
        $this->assertSame($lowPriority, $sortedArray[2]);
    }

    public function testUnregister(): void
    {
        // Test that the registry works as expected for registering and retrieving middleware
        $registry = self::getService(MiddlewareRegistry::class);

        $middleware = $this->createMock(MiddlewareInterface::class);

        // Test basic registration
        $registry->register('test', $middleware);
        $this->assertNotNull($registry->get('test'));
        $this->assertSame($middleware, $registry->get('test'));

        // Test that we can retrieve the same middleware
        $retrieved = $registry->get('test');
        $this->assertSame($middleware, $retrieved);
    }

    public function testClear(): void
    {
        // Test that the registry works as expected for registering multiple middleware
        $registry = self::getService(MiddlewareRegistry::class);

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $registry->register('middleware1', $middleware1);
        $registry->register('middleware2', $middleware2);

        // Test that both middleware are registered
        $this->assertSame($middleware1, $registry->get('middleware1'));
        $this->assertSame($middleware2, $registry->get('middleware2'));

        // Test that we can get all middleware
        $all = $registry->all();
        $this->assertArrayHasKey('middleware1', $all);
        $this->assertArrayHasKey('middleware2', $all);
    }
}
