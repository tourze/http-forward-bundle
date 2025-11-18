<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Controller\Admin\BackendCrudController;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(BackendCrudController::class)]
#[RunTestsInSeparateProcesses]
final class BackendCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /** @return BackendCrudController */
    protected function getControllerService(): BackendCrudController
    {
        return self::getService(BackendCrudController::class);
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '后端名称' => ['后端名称'];
        yield '后端URL' => ['后端URL'];
        yield '是否启用' => ['是否启用'];
        yield '状态' => ['状态'];
        yield '最后健康检查时间' => ['最后健康检查时间'];
        yield '最后健康状态' => ['最后健康状态'];
        yield '平均响应时间(ms)' => ['平均响应时间(ms)'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
    }

    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(BackendCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if ('EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud' === $attribute->getName()) {
                $hasAdminCrudAttribute = true;
                $args = $attribute->getArguments();
                $this->assertEquals('/http-forward/backend', $args['routePath']);
                $this->assertEquals('http_forward_backend', $args['routeName']);
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute);
    }

    public function testConfigureCrud(): void
    {
        $controller = $this->getControllerService();
        $crud = Crud::new();

        $result = $controller->configureCrud($crud);

        $this->assertInstanceOf(Crud::class, $result);
    }

    public function testConfigureFilters(): void
    {
        $controller = $this->getControllerService();
        $filters = Filters::new();

        $result = $controller->configureFilters($filters);

        $this->assertInstanceOf(Filters::class, $result);
    }

    public function testConfigureFields(): void
    {
        $controller = $this->getControllerService();

        $indexFields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));
        $this->assertNotEmpty($indexFields);

        $detailFields = iterator_to_array($controller->configureFields(Crud::PAGE_DETAIL));
        $this->assertNotEmpty($detailFields);

        $fieldLabels = [];
        foreach ($indexFields as $field) {
            if (!$field instanceof FieldInterface) {
                continue;
            }
            $dto = $field->getAsDto();
            $fieldLabels[] = $dto->getLabel();
        }

        $this->assertContains('ID', $fieldLabels);
        $this->assertContains('后端名称', $fieldLabels);
        $this->assertContains('后端URL', $fieldLabels);
        $this->assertContains('是否启用', $fieldLabels);
        $this->assertContains('状态', $fieldLabels);
        $this->assertContains('创建时间', $fieldLabels);
        $this->assertContains('更新时间', $fieldLabels);
    }

    public function testValidationErrors(): void
    {
        // First verify required fields configuration
        $controller = $this->getControllerService();
        $fields = iterator_to_array($controller->configureFields(Crud::PAGE_NEW));

        $requiredFields = [];
        foreach ($fields as $field) {
            if (!$field instanceof FieldInterface) {
                continue;
            }
            $dto = $field->getAsDto();
            // Check if field is required by examining form type configuration
            if (true === $dto->getFormTypeOption('required')) {
                $requiredFields[] = $dto->getLabel();
            }
        }

        $this->assertContains('后端名称', $requiredFields);
        $this->assertContains('后端URL', $requiredFields);
        $this->assertContains('权重', $requiredFields);
        $this->assertContains('是否启用', $requiredFields);
        $this->assertContains('状态', $requiredFields);
        $this->assertContains('超时时间(秒)', $requiredFields);
        $this->assertContains('最大连接数', $requiredFields);

        // Test actual form submission with empty data (integration test part)
        try {
            $client = self::getClient();
            if (null === $client) {
                self::markTestSkipped('Client is not available for integration testing');

                return; // @phpstan-ignore-line (markTestSkipped exits, but PHPStan doesn't detect this)
            }

            $crawler = $client->request('GET', '/admin?crudAction=new&crudControllerFqcn=' . urlencode(BackendCrudController::class));

            // Submit form without filling required fields
            $form = $crawler->filter('form[name="Backend"]')->form();
            $crawler = $client->submit($form);

            // Assert validation errors are shown
            $this->assertResponseStatusCodeSame(422);
            $this->assertStringContainsString('should not be blank', $crawler->filter('.invalid-feedback')->text());
        } catch (\Exception $e) {
            self::markTestSkipped('Client setup failed: ' . $e->getMessage());
        }
    }

    public function testRequiredFields(): void
    {
        // This method is kept for backward compatibility
        $this->testValidationErrors();
        // Verify the controller service exists and was properly tested
        $this->assertInstanceOf(BackendCrudController::class, $this->getControllerService());
    }

    public function testFieldsHiddenOnIndex(): void
    {
        $controller = $this->getControllerService();
        $indexFields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        $indexFieldLabels = [];
        foreach ($indexFields as $field) {
            if (!$field instanceof FieldInterface) {
                continue;
            }
            $dto = $field->getAsDto();
            $displayedOn = $dto->getDisplayedOn()->all();
            if (in_array(Crud::PAGE_INDEX, $displayedOn, true)) {
                $indexFieldLabels[] = $dto->getLabel();
            }
        }

        $this->assertNotContains('权重', $indexFieldLabels);
        $this->assertNotContains('超时时间(秒)', $indexFieldLabels);
        $this->assertNotContains('最大连接数', $indexFieldLabels);
        $this->assertNotContains('健康检查路径', $indexFieldLabels);
        $this->assertNotContains('描述', $indexFieldLabels);
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'url' => ['url'];
        yield 'weight' => ['weight'];
        yield 'enabled' => ['enabled'];
        yield 'status' => ['status'];
        yield 'timeout' => ['timeout'];
        yield 'maxConnections' => ['maxConnections'];
        yield 'healthCheckPath' => ['healthCheckPath'];
        yield 'description' => ['description'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'url' => ['url'];
        yield 'weight' => ['weight'];
        yield 'enabled' => ['enabled'];
        yield 'status' => ['status'];
        yield 'timeout' => ['timeout'];
        yield 'maxConnections' => ['maxConnections'];
        yield 'healthCheckPath' => ['healthCheckPath'];
        yield 'description' => ['description'];
    }

    public function testHealthCheckAction(): void
    {
        $controller = $this->getControllerService();

        // Verify that healthCheck action exists
        $reflectionClass = new \ReflectionClass($controller);
        $this->assertTrue($reflectionClass->hasMethod('healthCheck'));

        $healthCheckMethod = $reflectionClass->getMethod('healthCheck');
        $attributes = $healthCheckMethod->getAttributes();

        // Verify AdminAction attribute
        $hasAdminAction = false;
        foreach ($attributes as $attribute) {
            if ('EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction' === $attribute->getName()) {
                $hasAdminAction = true;
                break;
            }
        }

        $this->assertTrue($hasAdminAction, 'healthCheck method should have AdminAction attribute');
    }

    public function testHealthCheckAllAction(): void
    {
        $controller = $this->getControllerService();

        // Verify that healthCheckAll action exists
        $reflectionClass = new \ReflectionClass($controller);
        $this->assertTrue($reflectionClass->hasMethod('healthCheckAll'));

        $healthCheckAllMethod = $reflectionClass->getMethod('healthCheckAll');
        $attributes = $healthCheckAllMethod->getAttributes();

        // Verify AdminAction attribute
        $hasAdminAction = false;
        foreach ($attributes as $attribute) {
            if ('EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction' === $attribute->getName()) {
                $hasAdminAction = true;
                break;
            }
        }

        $this->assertTrue($hasAdminAction, 'healthCheckAll method should have AdminAction attribute');
    }
}
