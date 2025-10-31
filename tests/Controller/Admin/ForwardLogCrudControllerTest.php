<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Controller\Admin\ForwardLogCrudController;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(ForwardLogCrudController::class)]
#[RunTestsInSeparateProcesses]
final class ForwardLogCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /** @return ForwardLogCrudController */
    protected function getControllerService(): ForwardLogCrudController
    {
        return new ForwardLogCrudController();
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '规则' => ['规则'];
        yield '后端服务器' => ['后端服务器'];
        yield '访问密钥' => ['访问密钥'];
        yield '任务状态' => ['任务状态'];
        yield '请求时间' => ['请求时间'];
        yield '方法' => ['方法'];
        yield '路径' => ['路径'];
        yield '后端名称' => ['后端名称'];
        yield '响应状态' => ['响应状态'];
        yield '总耗时' => ['总耗时'];
        yield '降级' => ['降级'];
    }

    public function testGetEntityFqcn(): void
    {
        $this->assertEquals(ForwardLog::class, ForwardLogCrudController::getEntityFqcn());
    }

    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(ForwardLogCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if ('EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud' === $attribute->getName()) {
                $hasAdminCrudAttribute = true;
                $args = $attribute->getArguments();
                $this->assertEquals('/http-forward/forward-log', $args['routePath']);
                $this->assertEquals('http_forward_forward_log', $args['routeName']);
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute);
    }

    public function testConfigureCrud(): void
    {
        $controller = new ForwardLogCrudController();
        $crud = Crud::new();

        $result = $controller->configureCrud($crud);

        $this->assertInstanceOf(Crud::class, $result);

        // EasyAdmin 的 configureCrud 可能返回同一个对象
        $this->assertInstanceOf(Crud::class, $result);
    }

    public function testConfigureActions(): void
    {
        $controller = new ForwardLogCrudController();
        $actions = Actions::new();

        $result = $controller->configureActions($actions);

        $this->assertInstanceOf(Actions::class, $result);

        // EasyAdmin 的 configureActions 可能返回同一个对象
        $this->assertInstanceOf(Actions::class, $result);
    }

    public function testConfigureFilters(): void
    {
        $controller = new ForwardLogCrudController();
        $filters = Filters::new();

        $result = $controller->configureFilters($filters);

        $this->assertInstanceOf(Filters::class, $result);

        // EasyAdmin 的 configureFilters 可能返回同一个对象
        $this->assertInstanceOf(Filters::class, $result);
    }

    public function testConfigureFields(): void
    {
        $controller = new ForwardLogCrudController();

        $indexFields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));
        $this->assertNotEmpty($indexFields);

        $detailFields = iterator_to_array($controller->configureFields(Crud::PAGE_DETAIL));
        $this->assertNotEmpty($detailFields);

        // 提取在INDEX页面显示的字段标签
        $indexFieldLabels = $this->extractFieldLabels($indexFields, [Crud::PAGE_INDEX]);

        // 获取所有字段标签
        $allLabels = [];
        foreach ($indexFields as $field) {
            if ($field instanceof FieldInterface) {
                $dto = $field->getAsDto();
                $allLabels[] = $dto->getLabel();
            }
        }

        // 验证基本字段存在
        $this->assertNotEmpty($allLabels, 'Fields should not be empty');

        // 验证预期的字段在INDEX页面显示
        $this->assertContains('规则', $indexFieldLabels, 'Should contain 规则 field on index page');
        $this->assertContains('请求时间', $indexFieldLabels, 'Should contain 请求时间 field on index page');
        $this->assertContains('方法', $indexFieldLabels, 'Should contain 方法 field on index page');
        $this->assertContains('路径', $indexFieldLabels, 'Should contain 路径 field on index page');
        $this->assertContains('任务状态', $indexFieldLabels, 'Should contain 任务状态 field on index page');
        $this->assertContains('总耗时', $indexFieldLabels, 'Should contain 总耗时 field on index page');
        $this->assertContains('降级', $indexFieldLabels, 'Should contain 降级 field on index page');
    }

    public function testResponseStatusFormatting(): void
    {
        $controller = new ForwardLogCrudController();

        $fields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        $statusField = null;
        foreach ($fields as $field) {
            if (!$field instanceof FieldInterface) {
                continue;
            }
            $dto = $field->getAsDto();
            if ('响应状态' === $dto->getLabel()) {
                $statusField = $field;
                break;
            }
        }

        $this->assertNotNull($statusField);
        $this->assertInstanceOf(FieldInterface::class, $statusField);

        $dto = $statusField->getAsDto();
        $formatCallable = $dto->getFormatValueCallable();

        $this->assertNotNull($formatCallable);
        $this->assertEquals('200 OK', $formatCallable(200));
        $this->assertEquals('404 Not Found', $formatCallable(404));
        $this->assertEquals('500 Server Error', $formatCallable(500));
        $this->assertEquals(999, $formatCallable(999));
    }

    public function testFieldsVisibility(): void
    {
        $controller = new ForwardLogCrudController();

        $indexFieldLabels = $this->extractFieldLabels($controller->configureFields(Crud::PAGE_INDEX), [Crud::PAGE_INDEX]);
        $detailOnlyFieldLabels = $this->extractDetailOnlyFieldLabels($controller->configureFields(Crud::PAGE_DETAIL));

        $this->assertNotContains('原始请求头', $indexFieldLabels);
        $this->assertNotContains('处理后请求头', $indexFieldLabels);
        $this->assertNotContains('请求体', $indexFieldLabels);
        $this->assertNotContains('响应头', $indexFieldLabels);
        $this->assertNotContains('响应体', $indexFieldLabels);

        $this->assertContains('原始请求头', $detailOnlyFieldLabels);
        $this->assertContains('处理后请求头', $detailOnlyFieldLabels);
        $this->assertContains('请求体', $detailOnlyFieldLabels);
        $this->assertContains('响应头', $detailOnlyFieldLabels);
        $this->assertContains('响应体', $detailOnlyFieldLabels);
    }

    /**
     * @param iterable<mixed> $fields
     * @param array<string> $requiredPages
     * @return array<string>
     */
    private function extractFieldLabels(iterable $fields, array $requiredPages): array
    {
        $labels = [];
        foreach ($fields as $field) {
            if (!$field instanceof FieldInterface) {
                continue;
            }
            $dto = $field->getAsDto();
            if (!$this->hasRequiredMethods($dto, ['getDisplayedOn', 'getLabel'])) {
                continue;
            }

            $displayedOn = $dto->getDisplayedOn();
            if ($this->isDisplayedOnAllPages($displayedOn, $requiredPages)) {
                $label = $dto->getLabel();
                if (is_string($label)) {
                    $labels[] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * @param iterable<mixed> $fields
     * @return array<string>
     */
    private function extractDetailOnlyFieldLabels(iterable $fields): array
    {
        $labels = [];
        foreach ($fields as $field) {
            if (!$field instanceof FieldInterface) {
                continue;
            }
            $dto = $field->getAsDto();
            if (!$this->hasRequiredMethods($dto, ['getDisplayedOn', 'getLabel'])) {
                continue;
            }

            $displayedOn = $dto->getDisplayedOn();
            if ($this->isDetailOnlyField($displayedOn)) {
                $label = $dto->getLabel();
                if (is_string($label)) {
                    $labels[] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * @param array<string> $methods
     */
    private function hasRequiredMethods(object $dto, array $methods): bool
    {
        foreach ($methods as $method) {
            if (!method_exists($dto, $method)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param KeyValueStore $displayedOn
     * @param array<string> $requiredPages
     */
    private function isDisplayedOnAllPages(KeyValueStore $displayedOn, array $requiredPages): bool
    {
        $allPages = $displayedOn->all();
        foreach ($requiredPages as $page) {
            if (!in_array($page, $allPages, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param KeyValueStore $displayedOn
     */
    private function isDetailOnlyField(KeyValueStore $displayedOn): bool
    {
        $allPages = $displayedOn->all();

        return in_array(Crud::PAGE_DETAIL, $allPages, true) && !in_array(Crud::PAGE_INDEX, $allPages, true);
    }

    public function testValidationErrors(): void
    {
        $controller = new ForwardLogCrudController();
        $fields = iterator_to_array($controller->configureFields(Crud::PAGE_NEW));

        $requiredFields = [];
        foreach ($fields as $field) {
            if (!$field instanceof FieldInterface) {
                continue;
            }
            $dto = $field->getAsDto();
            if (true === $dto->getFormTypeOption('required')) {
                $requiredFields[] = $dto->getLabel();
            }
        }

        // ForwardLog is a log entity, so it should not have required fields
        $this->assertCount(0, $requiredFields, 'ForwardLog should not have required fields as it is a log entity');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // Basic fields that would be used for creating ForwardLog entries
        yield 'rule' => ['rule'];
        yield 'accessKey' => ['accessKey'];
        yield 'method' => ['method'];
        yield 'path' => ['path'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // Basic fields that would be used for editing ForwardLog entries
        yield 'rule' => ['rule'];
        yield 'accessKey' => ['accessKey'];
        yield 'method' => ['method'];
        yield 'path' => ['path'];
    }
}
