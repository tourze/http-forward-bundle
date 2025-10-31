<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Exception\NoHealthyBackendException;
use Tourze\LoadBalance\Exception\NoAvailableNodeException;
use Tourze\LoadBalance\LoadBalancerFactory;
use Tourze\LoadBalance\LoadBalancerInterface;
use Tourze\LoadBalance\Node;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'http_forward')]
readonly class LoadBalanceService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 根据负载均衡策略选择后端服务器
     *
     * @throws NoHealthyBackendException|NoAvailableNodeException
     */
    public function selectBackend(ForwardRule $rule, Request $request): Backend
    {
        $healthyBackends = $rule->getHealthyBackends();

        if ([] === $healthyBackends) {
            throw new NoHealthyBackendException(sprintf('No healthy backends available for rule "%s"', $rule->getName()));
        }

        if (1 === count($healthyBackends)) {
            return $healthyBackends[0];
        }

        $nodes = $this->convertBackendsToNodes($healthyBackends);
        $strategy = $rule->getLoadBalanceStrategy();

        $balancer = $this->createLoadBalancer($strategy, $request);
        $selectedNode = $balancer->select($nodes);
        $this->logger->debug('Using load balance strategy: ' . $strategy, [
            'nodes' => $nodes,
            'selected' => $selectedNode,
        ]);

        if (!$selectedNode instanceof Backend) {
            throw new NoHealthyBackendException('Load balancer failed to select a backend');
        }

        return $selectedNode;
    }

    /**
     * 将后端列表转换为负载均衡器需要的Node节点
     *
     * @param Backend[] $backends
     * @return Node[]
     */
    private function convertBackendsToNodes(array $backends): array
    {
        $nodes = [];

        foreach ($backends as $backend) {
            $nodes[] = new Node(
                key: (string) $backend->getId(),
                value: $backend,
                weight: $backend->getWeight()
            );
        }

        return $nodes;
    }

    /**
     * 根据策略创建负载均衡器
     */
    private function createLoadBalancer(string $strategy, Request $request): LoadBalancerInterface
    {
        return match ($strategy) {
            'round_robin' => LoadBalancerFactory::createRoundRobin(),
            'random' => LoadBalancerFactory::createRandom(),
            'weighted_round_robin' => LoadBalancerFactory::createSmoothWeightedRoundRobin(),
            'least_connections' => LoadBalancerFactory::createLeastConnections(),
            'ip_hash' => $this->createIpHashBalancer($request),
            default => LoadBalancerFactory::createRoundRobin(),
        };
    }

    /**
     * 创建基于IP哈希的负载均衡器
     */
    private function createIpHashBalancer(Request $request): LoadBalancerInterface
    {
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        return LoadBalancerFactory::createIpHash($clientIp);
    }

    /**
     * 获取可用的负载均衡策略列表
     *
     * @return array<string, string>
     */
    public function getAvailableStrategies(): array
    {
        return [
            'round_robin' => '轮询',
            'random' => '随机',
            'weighted_round_robin' => '加权轮询',
            'least_connections' => '最少连接',
            'ip_hash' => 'IP哈希',
        ];
    }

    /**
     * 检查规则是否有可用的后端
     */
    public function hasAvailableBackends(ForwardRule $rule): bool
    {
        return [] !== $rule->getHealthyBackends();
    }

    /**
     * 获取规则的后端统计信息
     *
     * @return array<string, mixed>
     */
    public function getBackendStats(ForwardRule $rule): array
    {
        $backends = $rule->getBackends()->toArray();
        $healthyBackends = $rule->getHealthyBackends();

        $stats = [
            'total' => count($backends),
            'healthy' => count($healthyBackends),
            'unhealthy' => count($backends) - count($healthyBackends),
            'enabled' => count(array_filter($backends, fn (Backend $b) => $b->isEnabled())),
            'disabled' => count(array_filter($backends, fn (Backend $b) => !$b->isEnabled())),
        ];

        $stats['health_rate'] = $stats['total'] > 0
            ? round(($stats['healthy'] / $stats['total']) * 100, 2)
            : 0;

        return $stats;
    }
}
