<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Service\HealthCheckService;

/**
 * @extends AbstractCrudController<Backend>
 */
#[AdminCrud(
    routePath: '/http-forward/backend',
    routeName: 'http_forward_backend'
)]
final class BackendCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HealthCheckService $healthCheckService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Backend::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('后端服务器')
            ->setEntityLabelInSingular('后端服务器')
            ->setPageTitle('index', '后端服务器管理')
            ->setPageTitle('new', '添加后端服务器')
            ->setPageTitle('edit', '编辑后端服务器')
            ->setPageTitle('detail', '后端服务器详情')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $healthCheckAction = Action::new('healthCheck', '检查健康状态')
            ->linkToCrudAction('healthCheck')
            ->setIcon('fa fa-heartbeat')
            ->setHtmlAttributes(['title' => '检查此后端服务器的健康状态'])
        ;

        $healthCheckAllAction = Action::new('healthCheckAll', '批量健康检查')
            ->linkToCrudAction('healthCheckAll')
            ->setIcon('fa fa-stethoscope')
            ->setHtmlAttributes(['title' => '检查所有后端服务器的健康状态'])
            ->createAsGlobalAction()
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $healthCheckAction)
            ->add(Crud::PAGE_DETAIL, $healthCheckAction)
            ->add(Crud::PAGE_INDEX, $healthCheckAllAction)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('id', 'ID')
                ->hideOnForm(),

            TextField::new('name', '后端名称')
                ->setRequired(true)
                ->setMaxLength(255)
                ->setHelp('用于标识后端服务器的名称'),

            UrlField::new('url', '后端URL')
                ->setRequired(true)
                ->setHelp('后端服务器的基础URL，如: https://api.example.com'),

            IntegerField::new('weight', '权重')
                ->setRequired(true)
                ->setHelp('权重值，范围1-100，数值越大被选中的概率越高')
                ->hideOnIndex(),

            BooleanField::new('enabled', '是否启用')
                ->setRequired(true),

            ChoiceField::new('status', '状态')
                ->setChoices([
                    '正常' => BackendStatus::ACTIVE,
                    '已停用' => BackendStatus::INACTIVE,
                    '不健康' => BackendStatus::UNHEALTHY,
                ])
                ->setRequired(true),

            IntegerField::new('timeout', '超时时间(秒)')
                ->setRequired(true)
                ->setHelp('请求超时时间，范围1-300秒')
                ->hideOnIndex(),

            IntegerField::new('maxConnections', '最大连接数')
                ->setRequired(true)
                ->setHelp('允许的最大并发连接数')
                ->hideOnIndex(),

            TextField::new('healthCheckPath', '健康检查路径')
                ->setRequired(false)
                ->setHelp('用于健康检查的URL路径，如: /health。如果路径以"/"开头，则会从后端域名开始拼接检查地址')
                ->hideOnIndex(),

            DateTimeField::new('lastHealthCheck', '最后健康检查时间')
                ->hideOnForm(),

            BooleanField::new('lastHealthStatus', '最后健康状态')
                ->hideOnForm(),

            NumberField::new('avgResponseTime', '平均响应时间(ms)')
                ->setNumDecimals(2)
                ->hideOnForm(),

            TextareaField::new('description', '描述')
                ->setRequired(false)
                ->setMaxLength(1000)
                ->hideOnIndex(),

            AssociationField::new('forwardRules', '关联的转发规则')
                ->hideOnForm()
                ->hideOnIndex()
                ->onlyOnDetail(),

            DateTimeField::new('createTime', '创建时间')
                ->hideOnForm(),

            DateTimeField::new('updateTime', '更新时间')
                ->hideOnForm(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', '后端名称'))
            ->add(TextFilter::new('url', '后端URL'))
            ->add(BooleanFilter::new('enabled', '是否启用'))
            ->add(ChoiceFilter::new('status', '状态')->setChoices([
                '正常' => BackendStatus::ACTIVE->value,
                '已停用' => BackendStatus::INACTIVE->value,
                '不健康' => BackendStatus::UNHEALTHY->value,
            ]))
            ->add(NumericFilter::new('weight', '权重'))
            ->add(NumericFilter::new('timeout', '超时时间'))
            ->add(BooleanFilter::new('lastHealthStatus', '健康状态'))
            ->add(DateTimeFilter::new('lastHealthCheck', '最后健康检查'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
        ;
    }

    /**
     * 单个后端健康检查Action
     */
    #[AdminAction(routePath: '{entityId}/health-check', routeName: 'backend_health_check')]
    public function healthCheck(string $entityId, AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        if ('' === $entityId || !is_numeric($entityId)) {
            $this->addFlash('danger', '后端服务器ID无效');

            return $this->redirectToIndex($adminUrlGenerator);
        }

        $result = $this->healthCheckService->checkBackendById((int) $entityId);

        if (!$result['success']) {
            $this->addFlash('danger', $result['message']);

            return $this->redirectToIndex($adminUrlGenerator);
        }

        $this->entityManager->flush();
        $flashType = ($result['healthy'] ?? false) ? 'success' : ('' !== $result['message'] ? 'warning' : 'danger');
        $this->addFlash($flashType, $result['message']);

        return $this->redirectToIndex($adminUrlGenerator);
    }

    /**
     * 批量健康检查Action
     */
    #[AdminAction(routePath: 'health-check-all', routeName: 'backend_health_check_all')]
    public function healthCheckAll(AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        $results = $this->healthCheckService->checkAllBackends();

        if (0 === $results['healthy'] + $results['unhealthy']) {
            $this->addFlash('warning', '没有找到需要健康检查的后端服务器');

            return $this->redirectToIndex($adminUrlGenerator);
        }

        $this->entityManager->flush();

        if (0 === $results['unhealthy']) {
            $this->addFlash('success', sprintf(
                '批量健康检查完成：%d个后端全部健康 ✅',
                $results['healthy']
            ));
        } else {
            $this->addFlash('warning', sprintf(
                '批量健康检查完成：%d个健康，%d个不健康',
                $results['healthy'],
                $results['unhealthy']
            ));
            if (count($results['errors']) <= 5) {
                $this->addFlash('info', '不健康的后端：' . implode(', ', array_map(
                    fn ($error) => str_replace(['后端 "', '" (', ') 健康检查失败'], ['', ' ❌ (', ')'], $error),
                    array_slice($results['errors'], 0, 5)
                )));
            }
        }

        return $this->redirectToIndex($adminUrlGenerator);
    }

    private function redirectToIndex(AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        $url = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;

        return $this->redirect($url);
    }
}
