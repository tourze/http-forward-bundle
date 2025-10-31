<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

class ForwardLogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // IP查询服务的转发日志
        $ipRule = $this->getReference(ForwardRuleFixtures::RULE_API_GATEWAY, ForwardRule::class);
        $backends = [
            $this->getReference(BackendFixtures::BACKEND_CIP_CC, Backend::class),
            $this->getReference(BackendFixtures::BACKEND_IP_CC, Backend::class),
            $this->getReference(BackendFixtures::BACKEND_IP_SB, Backend::class),
            $this->getReference(BackendFixtures::BACKEND_IPAPI_CO, Backend::class),
        ];

        for ($i = 1; $i <= 20; ++$i) {
            $log = new ForwardLog();
            $selectedBackend = $backends[random_int(0, count($backends) - 1)];
            $methods = ['GET'];
            $paths = ['/ip/', '/ip/json', '/ip/text', '/ip/xml'];
            $selectedPath = $paths[random_int(0, count($paths) - 1)];
            $statuses = [200, 200, 200, 429, 503]; // 大部分成功，少量失败

            $log->setRule($ipRule);
            $log->setBackend($selectedBackend);
            $log->setBackendName($selectedBackend->getName());
            $log->setBackendUrl($selectedBackend->getUrl());
            $log->setLoadBalanceStrategy('weighted_round_robin');
            $log->setAvailableBackends(array_map(fn (Backend $b) => [
                'id' => $b->getId(),
                'name' => $b->getName(),
                'url' => $b->getUrl(),
                'weight' => $b->getWeight(),
                'status' => $b->getStatus(),
            ], $backends));
            $log->setRuleName($ipRule->getName());

            $log->setMethod($methods[0]);
            $log->setPath($selectedPath);
            $log->setTargetUrl($selectedBackend->getUrl() . $selectedPath);
            $log->setClientIp('192.168.' . random_int(1, 255) . '.' . random_int(1, 255));
            $log->setUserAgent('Mozilla/5.0 (Test Bot)');
            $log->setProcessedRequestHeaders([
                'accept' => ['text/html', 'application/json'],
                'user-agent' => ['Mozilla/5.0 (Test Bot)'],
            ]);

            $responseStatus = $statuses[random_int(0, count($statuses) - 1)];
            $log->setResponseStatus($responseStatus);
            $log->setResponseHeaders([
                'content-type' => [200 === $responseStatus ? 'application/json' : 'text/plain'],
                'server' => ['nginx/1.20.0'],
            ]);

            $log->setDurationMs(random_int(50, 2000));
            $log->setRetryCountUsed($responseStatus >= 500 ? random_int(0, 2) : 0);
            $log->setFallbackUsed($responseStatus >= 500 && 1 === random_int(0, 1));

            // 性能指标
            $log->setUpstreamConnectTime(random_int(5, 50));
            $log->setUpstreamHeaderTime(random_int(10, 100));
            $log->setUpstreamResponseTime(random_int(30, 500));
            $log->setRequestSize(random_int(200, 800));
            $log->setResponseSize(random_int(100, 2000));

            // 追踪信息
            $log->setRequestId('req-' . uniqid());
            $log->setTraceId('trace-' . uniqid());
            $log->setSpanId('span-' . uniqid());

            $manager->persist($log);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            BackendFixtures::class,
            ForwardRuleFixtures::class,
        ];
    }
}
