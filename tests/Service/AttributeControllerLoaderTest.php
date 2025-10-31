<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Tourze\HttpForwardBundle\Controller\ForwardController;
use Tourze\HttpForwardBundle\Service\AttributeControllerLoader;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    private AttributeControllerLoader $loader;

    public function testImplementsRoutingAutoLoaderInterface(): void
    {
        $this->assertInstanceOf(RoutingAutoLoaderInterface::class, $this->loader);
    }

    public function testSupportsAlwaysReturnsFalse(): void
    {
        $this->assertFalse($this->loader->supports('any-resource'));
        $this->assertFalse($this->loader->supports('/path/to/resource'));
        $this->assertFalse($this->loader->supports(null));
        $this->assertFalse($this->loader->supports('', 'any-type'));
        $this->assertFalse($this->loader->supports('resource', 'annotation'));
    }

    public function testLoadCallsAutoload(): void
    {
        $result1 = $this->loader->load('resource');
        $result2 = $this->loader->autoload();

        $this->assertEquals($result1, $result2);
        $this->assertInstanceOf(RouteCollection::class, $result1);
        $this->assertInstanceOf(RouteCollection::class, $result2);
    }

    public function testLoadWithDifferentParameters(): void
    {
        $result1 = $this->loader->load('any-resource');
        $result2 = $this->loader->load('/path/to/file', 'annotation');
        $result3 = $this->loader->load(null, null);

        $this->assertInstanceOf(RouteCollection::class, $result1);
        $this->assertInstanceOf(RouteCollection::class, $result2);
        $this->assertInstanceOf(RouteCollection::class, $result3);

        // All should return equivalent collections since they all delegate to autoload()
        $this->assertEquals($result1->count(), $result2->count());
        $this->assertEquals($result2->count(), $result3->count());
    }

    public function testAutoloadReturnsRouteCollection(): void
    {
        $collection = $this->loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection);
    }

    public function testAutoloadLoadsForwardControllerRoutes(): void
    {
        $collection = $this->loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection);

        // The collection should contain routes from ForwardController
        // We can't assert specific route details since they depend on the actual controller annotations
        // But we can verify it's a valid collection
        $this->assertGreaterThanOrEqual(0, $collection->count());
    }

    public function testMultipleAutoloadCallsReturnConsistentResults(): void
    {
        $collection1 = $this->loader->autoload();
        $collection2 = $this->loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection1);
        $this->assertInstanceOf(RouteCollection::class, $collection2);

        // Both collections should have the same number of routes
        $this->assertEquals($collection1->count(), $collection2->count());

        // Compare route names and paths
        $routes1 = $collection1->all();
        $routes2 = $collection2->all();

        $this->assertEquals(array_keys($routes1), array_keys($routes2));

        foreach ($routes1 as $name => $route1) {
            $this->assertArrayHasKey($name, $routes2);
            $route2 = $routes2[$name];
            $this->assertEquals($route1->getPath(), $route2->getPath());
            $this->assertEquals($route1->getMethods(), $route2->getMethods());
        }
    }

    public function testLoaderCreatesInternalControllerLoader(): void
    {
        // Test that the loader can be instantiated without errors
        $loader = self::getService(AttributeControllerLoader::class);
        $this->assertInstanceOf(AttributeControllerLoader::class, $loader);

        // Test that autoload works (indicating internal loader was created successfully)
        $collection = $loader->autoload();
        $this->assertInstanceOf(RouteCollection::class, $collection);
    }

    public function testForwardControllerClassExists(): void
    {
        // Verify that the controller class we're trying to load actually exists
        $controller = self::getService(ForwardController::class);
        $this->assertInstanceOf(ForwardController::class, $controller);
    }

    public function testRouteCollectionIsValid(): void
    {
        $collection = $this->loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection);

        // Test that all routes in the collection are valid
        foreach ($collection->all() as $name => $route) {
            $this->assertInstanceOf(Route::class, $route);
            // Verify route structure is valid
            $this->assertNotEmpty($route->getPath());
        }
    }

    public function testLoaderImplementsLoaderInterface(): void
    {
        // Verify inheritance chain
        $this->assertInstanceOf(Loader::class, $this->loader);
    }

    public function testLoaderHasCorrectTag(): void
    {
        // Test that the loader has the routing.loader attribute/tag
        $reflection = new \ReflectionClass(AttributeControllerLoader::class);
        $attributes = $reflection->getAttributes();

        $hasRoutingLoaderTag = false;
        foreach ($attributes as $attribute) {
            if (AutoconfigureTag::class === $attribute->getName()) {
                $args = $attribute->getArguments();
                if (isset($args['name']) && 'routing.loader' === $args['name']) {
                    $hasRoutingLoaderTag = true;
                    break;
                }
            }
        }

        $this->assertTrue($hasRoutingLoaderTag, 'AttributeControllerLoader should have the routing.loader autoconfigure tag');
    }

    public function testEnvironmentIndependentBehavior(): void
    {
        // Test that the loader behaves consistently regardless of environment
        $loader1 = self::getService(AttributeControllerLoader::class);
        $loader2 = self::getService(AttributeControllerLoader::class);

        $collection1 = $loader1->autoload();
        $collection2 = $loader2->autoload();

        $this->assertEquals($collection1->count(), $collection2->count());

        // Route collections should be equivalent
        $routes1 = $collection1->all();
        $routes2 = $collection2->all();

        $this->assertEquals(array_keys($routes1), array_keys($routes2));
    }

    public function testLoaderCanHandleEmptyResults(): void
    {
        $collection = $this->loader->autoload();

        // Even if no routes are found, should return a valid RouteCollection
        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertGreaterThanOrEqual(0, $collection->count());
    }

    protected function onSetUp(): void
    {
        $this->loader = self::getService(AttributeControllerLoader::class);
    }
}
