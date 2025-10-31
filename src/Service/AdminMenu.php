<?php

namespace Tourze\HttpForwardBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

/**
 * HTTP转发菜单服务
 */
#[Autoconfigure(public: true)]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('流量转发')) {
            $item->addChild('流量转发');
        }

        $systemMenu = $item->getChild('流量转发');

        if (null !== $systemMenu) {
            // HTTP转发规则菜单
            $systemMenu->addChild('转发规则')
                ->setUri($this->linkGenerator->getCurdListPage(ForwardRule::class))
                ->setAttribute('icon', 'fas fa-route')
                ->setAttribute('description', '管理HTTP请求转发规则')
            ;

            $systemMenu->addChild('后端主机')
                ->setUri($this->linkGenerator->getCurdListPage(Backend::class))
                ->setAttribute('icon', 'fas fa-server')
            ;

            // HTTP转发日志菜单
            $systemMenu->addChild('转发日志')
                ->setUri($this->linkGenerator->getCurdListPage(ForwardLog::class))
                ->setAttribute('icon', 'fas fa-list-alt')
                ->setAttribute('description', '查看HTTP转发请求日志')
            ;
        }
    }
}
