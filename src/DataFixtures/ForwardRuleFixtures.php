<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

class ForwardRuleFixtures extends Fixture implements DependentFixtureInterface
{
    public const RULE_API_GATEWAY = 'forward-rule-api-gateway';

    public function load(ObjectManager $manager): void
    {
        // IP查询服务负载均衡规则
        $rule1 = new ForwardRule();
        $rule1->setName('IP查询负载均衡');
        $rule1->setSourcePath('/ip/*');
        $rule1->setHttpMethods(['GET']);
        $rule1->setEnabled(true);
        $rule1->setPriority(100);
        $rule1->setStripPrefix(true);
        $rule1->setTimeout(15);
        $rule1->setRetryCount(2);
        $rule1->setRetryInterval(1000);
        $rule1->setStreamEnabled(false);
        $rule1->setBufferSize(8192);
        $rule1->setLoadBalanceStrategy('weighted_round_robin');

        // 添加IP查询服务后端
        $rule1->addBackend($this->getReference(BackendFixtures::BACKEND_CIP_CC, Backend::class));
        $rule1->addBackend($this->getReference(BackendFixtures::BACKEND_IP_CC, Backend::class));
        $rule1->addBackend($this->getReference(BackendFixtures::BACKEND_IP_SB, Backend::class));
        $rule1->addBackend($this->getReference(BackendFixtures::BACKEND_IPAPI_CO, Backend::class));

        $manager->persist($rule1);
        $this->addReference(self::RULE_API_GATEWAY, $rule1);

        // OpenAI代理规则（单一后端）
        $rule2 = new ForwardRule();
        $rule2->setName('OpenAI Proxy');
        $rule2->setSourcePath('/openai/*');
        $rule2->setHttpMethods(['POST']);
        $rule2->setEnabled(true);
        $rule2->setPriority(90);
        $rule2->setStripPrefix(true);
        $rule2->setTimeout(120);
        $rule2->setRetryCount(1);
        $rule2->setRetryInterval(2000);
        $rule2->setStreamEnabled(true);
        $rule2->setBufferSize(1024);
        $rule2->setLoadBalanceStrategy('round_robin');
        $rule2->setFallbackType('STATIC');
        $rule2->setFallbackConfig([
            'content' => 'Service temporarily unavailable',
            'status' => 503,
        ]);

        // 添加OpenAI后端
        $rule2->addBackend($this->getReference(BackendFixtures::BACKEND_OPENAI_API, Backend::class));

        $rule2->setMiddlewares([
            'auth_header' => [
                'action' => 'add',
                'scheme' => 'Bearer',
                'token' => 'example-token',
            ],
        ]);

        $manager->persist($rule2);

        // API网关负载均衡规则（JSONPlaceholder）
        $rule3 = new ForwardRule();
        $rule3->setName('API Gateway');
        $rule3->setSourcePath('/api/v1/*');
        $rule3->setHttpMethods(['GET', 'POST', 'PUT', 'DELETE']);
        $rule3->setEnabled(true);
        $rule3->setPriority(80);
        $rule3->setStripPrefix(true);
        $rule3->setTimeout(30);
        $rule3->setRetryCount(2);
        $rule3->setRetryInterval(1000);
        $rule3->setStreamEnabled(false);
        $rule3->setBufferSize(8192);
        $rule3->setLoadBalanceStrategy('round_robin');

        // 添加JSONPlaceholder后端实现负载均衡
        $rule3->addBackend($this->getReference(BackendFixtures::BACKEND_JSONPLACEHOLDER_1, Backend::class));
        $rule3->addBackend($this->getReference(BackendFixtures::BACKEND_JSONPLACEHOLDER_2, Backend::class));

        $manager->persist($rule3);

        // CC中转服务规则（单一后端，支持流式传输）
        $rule4 = new ForwardRule();
        $rule4->setName('CC中转-001');
        $rule4->setSourcePath('/cc/api*');
        $rule4->setHttpMethods(['GET', 'POST', 'PUT', 'DELETE']);
        $rule4->setEnabled(true);
        $rule4->setPriority(95);
        $rule4->setStripPrefix(true);
        $rule4->setTimeout(100);
        $rule4->setRetryCount(2);
        $rule4->setRetryInterval(1000);
        $rule4->setStreamEnabled(true);
        $rule4->setBufferSize(8192);
        $rule4->setLoadBalanceStrategy('round_robin');

        // 添加CC中转后端
        $rule4->addBackend($this->getReference(BackendFixtures::BACKEND_CC_RELAY_001, Backend::class));

        $manager->persist($rule4);

        // HTTP测试服务规则
        $rule5 = new ForwardRule();
        $rule5->setName('HTTP测试工具');
        $rule5->setSourcePath('/test/*');
        $rule5->setHttpMethods(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS']);
        $rule5->setEnabled(true);
        $rule5->setPriority(70);
        $rule5->setStripPrefix(true);
        $rule5->setTimeout(20);
        $rule5->setRetryCount(1);
        $rule5->setRetryInterval(500);
        $rule5->setStreamEnabled(false);
        $rule5->setBufferSize(4096);
        $rule5->setLoadBalanceStrategy('random');

        // 添加HTTPBin测试后端
        $rule5->addBackend($this->getReference(BackendFixtures::BACKEND_HTTPBIN_ORG, Backend::class));

        $manager->persist($rule5);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            BackendFixtures::class,
        ];
    }
}
