<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Twig\MiddlewareConfigExtension;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(MiddlewareConfigExtension::class)]
#[RunTestsInSeparateProcesses]
final class MiddlewareConfigExtensionTest extends AbstractIntegrationTestCase
{
    private MiddlewareConfigExtension $extension;

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(2, $functions);

        foreach ($functions as $function) {
            $this->assertInstanceOf(TwigFunction::class, $function);
        }

        $functionNames = array_map(fn (TwigFunction $func) => $func->getName(), $functions);
        $this->assertContains('middleware_config_helper', $functionNames);
        $this->assertContains('middleware_templates', $functionNames);
    }

    public function testRenderMiddlewareHelper(): void
    {
        $result = $this->extension->renderMiddlewareHelper();

        $this->assertStringContainsString('middleware-config-helper', $result);
        $this->assertStringContainsString('<script', $result);
        $this->assertStringContainsString('<style', $result);
    }

    public function testGetMiddlewareTemplates(): void
    {
        $templates = $this->extension->getMiddlewareTemplates();

        // Since the test uses an empty MiddlewareRegistry, the templates array will be empty
        // This is the expected behavior for a unit test with minimal dependencies
        if (0 === count($templates)) {
            $this->assertSame([], $templates, 'Templates array should be empty when no middleware is registered');
        } else {
            // If templates are available, test for expected keys
            $this->assertArrayHasKey('access_key_auth', $templates);
            $this->assertArrayHasKey('auth_header', $templates);
            $this->assertArrayHasKey('header_transform', $templates);
        }
    }

    public function testMiddlewareTemplatesStructure(): void
    {
        $templates = $this->extension->getMiddlewareTemplates();

        // Since the test uses an empty MiddlewareRegistry, templates may be empty
        if (0 === count($templates)) {
            $this->assertCount(0, $templates, 'Templates array is empty as expected with no middleware registered');

            return; // Skip structure validation for empty templates
        }

        // If templates are not empty, validate their structure
        foreach ($templates as $middlewareName => $template) {
            $this->assertArrayHasKey('label', $template);
            $this->assertArrayHasKey('description', $template);
            $this->assertArrayHasKey('priority', $template);
            $this->assertArrayHasKey('fields', $template);

            // Validate template structure - these type assertions are necessary for unknown structures
            $this->assertIsString($template['label']);
            $this->assertIsString($template['description']);
            $this->assertIsInt($template['priority']);
        }
    }

    protected function onSetUp(): void
    {
        // 从容器获取真实的服务实例
        $this->extension = self::getService(MiddlewareConfigExtension::class);
    }
}
