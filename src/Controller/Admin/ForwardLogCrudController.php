<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Enum\ForwardLogStatus;

/**
 * @extends AbstractCrudController<ForwardLog>
 */
#[AdminCrud(
    routePath: '/http-forward/forward-log',
    routeName: 'http_forward_forward_log'
)]
final class ForwardLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ForwardLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('转发日志')
            ->setEntityLabelInPlural('转发日志')
            ->setDefaultSort(['requestTime' => 'DESC'])
            ->setSearchFields(['path', 'targetUrl', 'method', 'clientIp'])
            ->setPaginatorPageSize(50)
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DELETE, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('rule'))
            ->add(EntityFilter::new('backend'))
            ->add(EntityFilter::new('accessKey'))
            ->add(
                ChoiceFilter::new('status')
                ->setChoices([
                    '准备中' => ForwardLogStatus::PENDING->value,
                    '发送中' => ForwardLogStatus::SENDING->value,
                    '接收中' => ForwardLogStatus::RECEIVING->value,
                    '已完成' => ForwardLogStatus::COMPLETED->value,
                    '失败' => ForwardLogStatus::FAILED->value,
                ])
            )
            ->add(NumericFilter::new('responseStatus'))
            ->add(BooleanFilter::new('fallbackUsed'))
            ->add(NumericFilter::new('retryCountUsed'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [];

        foreach ($this->getBasicFields() as $field) {
            $fields[] = $field;
        }

        foreach ($this->getAssociationFields() as $field) {
            $fields[] = $field;
        }

        $fields[] = $this->createStatusField();

        foreach ($this->getTimeAndRequestFields() as $field) {
            $fields[] = $field;
        }

        foreach ($this->getBackendFields() as $field) {
            $fields[] = $field;
        }

        foreach ($this->getResponseFields() as $field) {
            $fields[] = $field;
        }

        foreach ($this->getMetricsFields() as $field) {
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getBasicFields(): iterable
    {
        yield IdField::new('id')
            ->setLabel('ID')
            ->onlyOnIndex()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getAssociationFields(): iterable
    {
        yield AssociationField::new('rule')
            ->setLabel('规则')
            ->autocomplete()
        ;

        yield AssociationField::new('backend')
            ->setLabel('后端服务器')
            ->autocomplete()
            ->hideOnForm()
        ;

        yield AssociationField::new('accessKey')
            ->setLabel('访问密钥')
            ->autocomplete()
        ;
    }

    private function createStatusField(): FieldInterface
    {
        $statusField = EnumField::new('status')
            ->setLabel('任务状态')
        ;
        $statusField->setEnumCases(ForwardLogStatus::cases());
        $statusField->renderAsBadges(true);

        $statusField->formatValue(static function ($value, ForwardLog $entity) {
            if (!$value instanceof ForwardLogStatus) {
                return 'Unknown';
            }
            $class = $value->getBadgeClass();
            $label = $value->getLabel();

            return sprintf('<span class="badge badge-%s">%s</span>', $class, $label);
        });

        return $statusField;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getTimeAndRequestFields(): iterable
    {
        yield DateTimeField::new('requestTime')
            ->setLabel('请求时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield TextField::new('method')
            ->setLabel('方法')
        ;

        yield TextField::new('path')
            ->setLabel('路径')
        ;

        yield TextField::new('targetUrl')
            ->setLabel('目标URL')
            ->hideOnIndex()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getBackendFields(): iterable
    {
        yield TextField::new('backendName')
            ->setLabel('后端名称')
            ->hideOnForm()
        ;

        yield TextField::new('backendUrl')
            ->setLabel('后端URL')
            ->hideOnIndex()
            ->hideOnForm()
        ;

        yield TextField::new('loadBalanceStrategy')
            ->setLabel('负载均衡策略')
            ->formatValue(function ($value) {
                return $this->formatLoadBalanceStrategy($value);
            })
            ->hideOnIndex()
            ->hideOnForm()
        ;
    }

    private function formatLoadBalanceStrategy(mixed $value): string
    {
        $strategies = [
            'round_robin' => '轮询',
            'random' => '随机',
            'weighted_round_robin' => '加权轮询',
            'least_connections' => '最少连接',
            'ip_hash' => 'IP哈希',
        ];

        if (is_string($value) && array_key_exists($value, $strategies)) {
            return $strategies[$value];
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getResponseFields(): iterable
    {
        yield IntegerField::new('responseStatus')
            ->setLabel('响应状态')
            ->formatValue(static function ($value) {
                $statusMap = [
                    200 => '200 OK',
                    201 => '201 Created',
                    204 => '204 No Content',
                    301 => '301 Moved',
                    302 => '302 Found',
                    400 => '400 Bad Request',
                    401 => '401 Unauthorized',
                    403 => '403 Forbidden',
                    404 => '404 Not Found',
                    500 => '500 Server Error',
                    502 => '502 Bad Gateway',
                    503 => '503 Unavailable',
                ];

                if (is_int($value) && array_key_exists($value, $statusMap)) {
                    return $statusMap[$value];
                }

                return is_scalar($value) ? (string) $value : '';
            })
        ;

        yield IntegerField::new('latencyMs')
            ->setLabel('网络延迟')
            ->formatValue(static function ($value) {
                return null !== $value && is_scalar($value) ? (string) $value . ' ms' : '-';
            })
            ->hideOnIndex()
        ;

        yield IntegerField::new('downloadMs')
            ->setLabel('下载耗时')
            ->formatValue(static function ($value) {
                return null !== $value && is_scalar($value) ? (string) $value . ' ms' : '-';
            })
            ->hideOnIndex()
        ;

        yield IntegerField::new('durationMs')
            ->setLabel('总耗时')
            ->formatValue(static function ($value) {
                return is_scalar($value) ? (string) $value . ' ms' : '0 ms';
            })
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getMetricsFields(): iterable
    {
        yield IntegerField::new('retryCountUsed')
            ->setLabel('重试次数')
            ->hideOnIndex()
        ;

        yield BooleanField::new('fallbackUsed')
            ->setLabel('降级')
            ->renderAsSwitch(false)
        ;

        yield TextField::new('clientIp')
            ->setLabel('客户端IP')
            ->hideOnIndex()
        ;

        yield TextField::new('userAgent')
            ->setLabel('用户代理')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('sendTime')
            ->setLabel('发送时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss.SSS')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('firstByteTime')
            ->setLabel('首字节时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss.SSS')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('completeTime')
            ->setLabel('完成时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss.SSS')
            ->onlyOnDetail()
        ;

        yield TextareaField::new('errorMessage')
            ->setLabel('错误')
            ->hideOnIndex()
        ;

        yield CodeEditorField::new('originalRequestHeaders')
            ->setLabel('原始请求头')
            ->setLanguage('php')
            ->formatValue(function ($value) {
                return $this->formatJsonValue($value);
            })
            ->onlyOnDetail()
        ;

        yield CodeEditorField::new('processedRequestHeaders')
            ->setLabel('处理后请求头')
            ->setLanguage('php')
            ->formatValue(function ($value) {
                return $this->formatJsonValue($value);
            })
            ->onlyOnDetail()
        ;

        yield CodeEditorField::new('requestBody')
            ->setLabel('请求体')
            ->setLanguage('php')
            ->onlyOnDetail()
        ;

        yield CodeEditorField::new('responseHeaders')
            ->setLabel('响应头')
            ->setLanguage('php')
            ->formatValue(function ($value) {
                return $this->formatJsonValue($value);
            })
            ->onlyOnDetail()
        ;

        yield CodeEditorField::new('responseBody')
            ->setLabel('响应体')
            ->setLanguage('php')
            ->onlyOnDetail()
        ;
    }

    private function formatJsonValue(mixed $value): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return false === $json ? 'Invalid JSON' : $json;
    }
}
