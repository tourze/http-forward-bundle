<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Field\MiddlewareCollectionField;
use Tourze\HttpForwardBundle\Repository\ForwardRuleRepository;
use Tourze\HttpForwardBundle\Service\MiddlewareConfigManager;
use Twig\Environment;

/**
 * @extends AbstractCrudController<ForwardRule>
 */
#[AdminCrud(
    routePath: '/http-forward/forward-rule',
    routeName: 'http_forward_forward_rule'
)]
final class ForwardRuleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MiddlewareConfigManager $middlewareConfigManager,
        private readonly Environment $twig,
        private readonly ForwardRuleRepository $forwardRuleRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ForwardRule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('转发规则')
            ->setEntityLabelInPlural('转发规则')
            ->setDefaultSort(['priority' => 'DESC', 'id' => 'ASC'])
            ->setSearchFields(['name', 'sourcePath'])
            ->setPaginatorPageSize(30)
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $cloneAction = Action::new('clone', '复制')
            ->linkToCrudAction('cloneRule')
            ->setIcon('fa fa-copy')
            ->setHtmlAttributes(['title' => '复制这个转发规则'])
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $cloneAction)
            ->add(Crud::PAGE_DETAIL, $cloneAction)
        ;
    }

    public function configureAssets(Assets $assets): Assets
    {
        // 在编辑页面添加中间件配置助手
        $middlewareHelper = $this->twig->render('@HttpForward/middleware_config_helper.html.twig');

        return $assets
            ->addHtmlContentToBody($middlewareHelper)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('enabled'))
            ->add(BooleanFilter::new('streamEnabled'))
            ->add(NumericFilter::new('retryCount'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->setLabel('ID')
            ->onlyOnIndex()
        ;

        yield TextField::new('name')
            ->setLabel('名称')
            ->setHelp('该转发规则的描述性名称')
        ;

        yield TextField::new('sourcePath')
            ->setLabel('源路径')
            ->setHelp('要匹配的路径模式（支持正则表达式^, 通配符*, 和参数{id}）')
        ;

        yield TextField::new('backendsSummary', '后端服务器')
            ->setHelp('后端服务器及权重配置')
            ->formatValue(function ($value, ForwardRule $entity): string {
                if (!$entity->hasBackends()) {
                    return '❌ 未配置后端';
                }

                $summary = [];
                foreach ($entity->getBackends() as $backend) {
                    // 综合判断后端真实状态
                    $status = $this->getBackendStatusIcon($backend);
                    $summary[] = sprintf(
                        '%s %s (权重:%d)',
                        $status,
                        $backend->getName(),
                        $backend->getWeight()
                    );
                }

                return implode('<br>', $summary);
            })
            ->onlyOnIndex()
            ->renderAsHtml()
        ;

        yield AssociationField::new('backends', '后端服务器')
            ->setRequired(true)
            ->setHelp('选择用于负载均衡的后端服务器列表')
            ->hideOnIndex()
        ;

        yield ChoiceField::new('loadBalanceStrategy', '负载均衡策略')
            ->setChoices([
                '轮询' => 'round_robin',
                '随机' => 'random',
                '加权轮询' => 'weighted_round_robin',
                '最少连接' => 'least_connections',
                'IP哈希' => 'ip_hash',
            ])
            ->setRequired(true)
            ->setHelp('后端服务器的负载均衡策略')
        ;

        yield ChoiceField::new('httpMethods')
            ->setLabel('HTTP方法')
            ->setChoices([
                'GET' => 'GET',
                'POST' => 'POST',
                'PUT' => 'PUT',
                'DELETE' => 'DELETE',
                'PATCH' => 'PATCH',
                'HEAD' => 'HEAD',
                'OPTIONS' => 'OPTIONS',
            ])
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setHelp('此规则允许的HTTP方法')
        ;

        yield BooleanField::new('enabled')
            ->setLabel('启用')
            ->renderAsSwitch()
        ;

        yield IntegerField::new('priority')
            ->setLabel('优先级')
            ->setHelp('更高的值优先匹配')
        ;

        yield BooleanField::new('stripPrefix')
            ->setLabel('去除前缀')
            ->setHelp('转发前从路径中删除匹配的前缀（默认启用）')
            ->renderAsSwitch()
        ;

        yield IntegerField::new('timeout')
            ->setLabel('超时（秒）')
            ->setHelp('请求超时时间（秒）')
        ;

        yield BooleanField::new('streamEnabled')
            ->setLabel('启用流式传输')
            ->setHelp('为SSE/WebSocket类API启用流式响应')
            ->renderAsSwitch()
        ;

        yield IntegerField::new('bufferSize')
            ->setLabel('缓冲区大小')
            ->setHelp('流缓冲区大小（字节，默认：8192）')
            ->hideOnIndex()
        ;

        yield IntegerField::new('retryCount')
            ->setLabel('重试次数')
            ->setHelp('失败时的重试次数')
            ->hideOnIndex()
        ;

        yield IntegerField::new('retryInterval')
            ->setLabel('重试间隔（毫秒）')
            ->setHelp('重试之间的毫秒数')
            ->hideOnIndex()
        ;

        yield ChoiceField::new('fallbackType')
            ->setLabel('降级类型')
            ->setChoices([
                '无' => null,
                '静态响应' => 'STATIC',
                '备用URL' => 'BACKUP',
            ])
            ->hideOnIndex()
        ;

        yield CodeEditorField::new('fallbackConfig')
            ->setLabel('降级配置')
            ->setHelp('降级策略的JSON配置')
            ->setLanguage('js')
            ->setNumOfRows(10)
            ->formatValue(static function ($value) {
                if (null === $value || [] === $value) {
                    return '{}';
                }
                if (is_string($value)) {
                    return $value;
                }

                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            })
            ->hideOnIndex()
        ;

        yield MiddlewareCollectionField::newWithHelper(
            'middlewaresJson',
            '中间件',
            $this->middlewareConfigManager
        )
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createdAt')
            ->setLabel('创建时间')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('updatedAt')
            ->setLabel('更新时间')
            ->onlyOnDetail()
        ;
    }

    #[AdminAction(routePath: '{entityId}/clone', routeName: 'http_forward_forward_rule_clone')]
    public function cloneRule(Request $request, EntityManagerInterface $entityManager, AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        $originalId = $request->query->get('entityId');
        if (null === $originalId || '' === $originalId) {
            $this->addFlash('danger', '未找到要复制的规则');
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
            ;

            return $this->redirect($url);
        }

        $originalRule = $this->forwardRuleRepository->find($originalId);
        if (null === $originalRule) {
            $this->addFlash('danger', '转发规则不存在');
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
            ;

            return $this->redirect($url);
        }

        // 创建新的规则实体
        $newRule = new ForwardRule();

        // 复制所有配置字段
        $newRule->setName($originalRule->getName() . ' - 副本');
        $newRule->setSourcePath($originalRule->getSourcePath());

        // 复制后端服务器关联
        foreach ($originalRule->getBackends() as $backend) {
            $newRule->addBackend($backend);
        }
        $newRule->setLoadBalanceStrategy($originalRule->getLoadBalanceStrategy());

        $newRule->setHttpMethods($originalRule->getHttpMethods());
        $newRule->setEnabled($originalRule->isEnabled());
        $newRule->setPriority($originalRule->getPriority());
        $newRule->setMiddlewares($originalRule->getMiddlewares());
        $newRule->setStripPrefix($originalRule->isStripPrefix());
        $newRule->setTimeout($originalRule->getTimeout());
        $newRule->setRetryCount($originalRule->getRetryCount());
        $newRule->setRetryInterval($originalRule->getRetryInterval());
        $newRule->setFallbackType($originalRule->getFallbackType());
        $newRule->setFallbackConfig($originalRule->getFallbackConfig());
        $newRule->setStreamEnabled($originalRule->isStreamEnabled());
        $newRule->setBufferSize($originalRule->getBufferSize());

        // 保存新规则
        try {
            $entityManager->persist($newRule);
            $entityManager->flush();

            $this->addFlash('success', sprintf('已成功复制转发规则 "%s"', $originalRule->getName()));

            // 重定向到新规则的编辑页面
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($newRule->getId())
                ->generateUrl()
            ;

            return $this->redirect($url);
        } catch (\Exception $e) {
            $this->addFlash('danger', '复制规则时发生错误：' . $e->getMessage());
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
            ;

            return $this->redirect($url);
        }
    }

    /**
     * 根据后端的综合状态返回对应的状态图标
     */
    private function getBackendStatusIcon(Backend $backend): string
    {
        // 未启用的后端
        if (!$backend->isEnabled()) {
            return '⚫'; // 禁用状态
        }

        // 启用的后端，检查实际状态
        $status = $backend->getStatus();

        switch ($status) {
            case BackendStatus::UNHEALTHY:
                return '❌'; // 明确标记为不健康

            case BackendStatus::INACTIVE:
                return '⚫'; // 非活跃状态

            case BackendStatus::ACTIVE:
                return $this->getHealthStatusIcon($backend->getLastHealthStatus());

            default:
                return '❓'; // 未知状态
        }
    }

    /**
     * 根据健康检查状态返回对应的图标
     */
    private function getHealthStatusIcon(?bool $lastHealthStatus): string
    {
        return match ($lastHealthStatus) {
            true => '✅',   // 健康检查通过
            false => '❌',  // 健康检查失败
            null => '⚠️',   // 未进行健康检查
        };
    }
}
