<?php

namespace Tourze\HttpForwardBundle\Tests\Service;

use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Service\AdminMenu;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private LinkGeneratorInterface $linkGenerator;

    protected function onSetUp(): void
    {
        $this->linkGenerator = new class () implements LinkGeneratorInterface {
            public function getCurdListPage(string $entityClass): string
            {
                return match ($entityClass) {
                    ForwardRule::class => '/admin/forward-rule',
                    ForwardLog::class => '/admin/forward-log',
                    default => '/admin/unknown',
                };
            }

            public function extractEntityFqcn(string $url): ?string
            {
                return null; // Not used in tests
            }

            public function setDashboard(string $dashboardControllerFqcn): void
            {
                // Not used in tests - empty implementation
            }
        };
        self::getContainer()->set(LinkGeneratorInterface::class, $this->linkGenerator);
    }

    public function testInvokeCreatesSystemMenu(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);
        $adminMenu($rootMenu);

        $systemMenu = $rootMenu->getChild('流量转发');
        self::assertNotNull($systemMenu);

        $forwardRuleMenu = $systemMenu->getChild('转发规则');
        self::assertNotNull($forwardRuleMenu);
        self::assertSame('/admin/forward-rule', $forwardRuleMenu->getUri());
        self::assertSame('fas fa-route', $forwardRuleMenu->getAttribute('icon'));
        self::assertSame('管理HTTP请求转发规则', $forwardRuleMenu->getAttribute('description'));

        $forwardLogMenu = $systemMenu->getChild('转发日志');
        self::assertNotNull($forwardLogMenu);
        self::assertSame('/admin/forward-log', $forwardLogMenu->getUri());
        self::assertSame('fas fa-list-alt', $forwardLogMenu->getAttribute('icon'));
        self::assertSame('查看HTTP转发请求日志', $forwardLogMenu->getAttribute('description'));
    }

    public function testInvokeUsesExistingSystemMenu(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);
        $existingSystemMenu = $rootMenu->addChild('流量转发');

        $adminMenu($rootMenu);

        $systemMenu = $rootMenu->getChild('流量转发');
        self::assertSame($existingSystemMenu, $systemMenu);

        $forwardRuleMenu = $systemMenu->getChild('转发规则');
        self::assertNotNull($forwardRuleMenu);
        self::assertSame('/admin/forward-rule', $forwardRuleMenu->getUri());

        $forwardLogMenu = $systemMenu->getChild('转发日志');
        self::assertNotNull($forwardLogMenu);
        self::assertSame('/admin/forward-log', $forwardLogMenu->getUri());
    }

    public function testServiceIsCallable(): void
    {
        $service = self::getService(AdminMenu::class);

        // Verify the service implements __invoke method
        $reflection = new \ReflectionClass($service);
        $this->assertTrue($reflection->hasMethod('__invoke'));
        $this->assertTrue($reflection->getMethod('__invoke')->isPublic());

        // Verify the service is readonly
        $this->assertTrue($reflection->isReadOnly());

        // Verify constructor injection
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertCount(1, $constructor->getParameters());

        $linkGeneratorParam = $constructor->getParameters()[0];
        self::assertSame('linkGenerator', $linkGeneratorParam->getName());
        $type = $linkGeneratorParam->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(LinkGeneratorInterface::class, $type->getName());
    }

    public function testInvokeHandlesNullSystemMenu(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);

        // Simulate case where getChild returns null initially
        $initialSystemMenu = $rootMenu->getChild('流量转发');
        self::assertNull($initialSystemMenu);

        $adminMenu($rootMenu);

        $systemMenu = $rootMenu->getChild('流量转发');
        self::assertNotNull($systemMenu);

        // Verify child menus are added correctly
        self::assertNotNull($systemMenu->getChild('转发规则'));
        self::assertNotNull($systemMenu->getChild('转发日志'));
    }

    public function testMenuItemAttributes(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);
        $adminMenu($rootMenu);

        $systemMenu = $rootMenu->getChild('流量转发');
        self::assertNotNull($systemMenu);

        // Test forward rule menu attributes
        $forwardRuleMenu = $systemMenu->getChild('转发规则');
        self::assertNotNull($forwardRuleMenu);

        $attributes = $forwardRuleMenu->getAttributes();
        self::assertArrayHasKey('icon', $attributes);
        self::assertSame('fas fa-route', $attributes['icon']);
        self::assertArrayHasKey('description', $attributes);
        self::assertSame('管理HTTP请求转发规则', $attributes['description']);

        // Test forward log menu attributes
        $forwardLogMenu = $systemMenu->getChild('转发日志');
        self::assertNotNull($forwardLogMenu);

        $attributes = $forwardLogMenu->getAttributes();
        self::assertArrayHasKey('icon', $attributes);
        self::assertSame('fas fa-list-alt', $attributes['icon']);
        self::assertArrayHasKey('description', $attributes);
        self::assertSame('查看HTTP转发请求日志', $attributes['description']);
    }
}
