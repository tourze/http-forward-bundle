<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Enum\BackendStatus;

class BackendFixtures extends Fixture
{
    // IP查询服务Backend引用常量
    public const BACKEND_CIP_CC = 'backend-cip-cc';
    public const BACKEND_IP_CC = 'backend-api-ip-cc';
    public const BACKEND_IP_SB = 'backend-ip-sb';
    public const BACKEND_IPAPI_CO = 'backend-ipapi-co';
    public const BACKEND_HTTPBIN_ORG = 'backend-httpbin-org';
    public const BACKEND_JSONPLACEHOLDER_1 = 'backend-jsonplaceholder-1';
    public const BACKEND_JSONPLACEHOLDER_2 = 'backend-jsonplaceholder-2';
    public const BACKEND_OPENAI_API = 'backend-openai-api';
    public const BACKEND_CC_RELAY_001 = 'backend-cc-relay-001';

    public function load(ObjectManager $manager): void
    {
        // IP查询服务后端
        $cipCc = new Backend();
        $cipCc->setName('CIP.CC IP查询');
        $cipCc->setUrl('http://cip.cc');
        $cipCc->setWeight(10);
        $cipCc->setEnabled(true);
        $cipCc->setStatus(BackendStatus::UNHEALTHY); // 预设为不健康（实测不可访问）
        $cipCc->setTimeout(10);
        $cipCc->setMaxConnections(100);
        $cipCc->setHealthCheckPath('/');
        $cipCc->setLastHealthCheck(new \DateTimeImmutable('now'));
        $cipCc->setLastHealthStatus(false); // 健康检查失败
        $cipCc->setDescription('简洁的IP地址查询服务，支持多种格式输出');
        $manager->persist($cipCc);
        $this->addReference(self::BACKEND_CIP_CC, $cipCc);

        $apiIpCc = new Backend();
        $apiIpCc->setName('API.IP.CC IP查询');
        $apiIpCc->setUrl('https://api.ip.cc');
        $apiIpCc->setWeight(8);
        $apiIpCc->setEnabled(true);
        $apiIpCc->setStatus(BackendStatus::ACTIVE); // 预设为健康（实测可访问）
        $apiIpCc->setTimeout(15);
        $apiIpCc->setMaxConnections(80);
        $apiIpCc->setHealthCheckPath('/');
        $apiIpCc->setLastHealthCheck(new \DateTimeImmutable('now'));
        $apiIpCc->setLastHealthStatus(true); // 健康检查成功
        $apiIpCc->setAvgResponseTime(150.5); // 预设响应时间
        $apiIpCc->setDescription('IP.CC提供的API接口，支持JSON格式输出');
        $manager->persist($apiIpCc);
        $this->addReference(self::BACKEND_IP_CC, $apiIpCc);

        $ipSb = new Backend();
        $ipSb->setName('IP.SB IP查询');
        $ipSb->setUrl('https://api.ip.sb');
        $ipSb->setWeight(9);
        $ipSb->setEnabled(true);
        $ipSb->setStatus(BackendStatus::UNHEALTHY); // 预设为不健康（实测不可访问）
        $ipSb->setTimeout(12);
        $ipSb->setMaxConnections(120);
        $ipSb->setHealthCheckPath('/geoip');
        $ipSb->setLastHealthCheck(new \DateTimeImmutable('now'));
        $ipSb->setLastHealthStatus(false); // 健康检查失败
        $ipSb->setDescription('IP.SB地理位置查询API，提供详细的IP信息');
        $manager->persist($ipSb);
        $this->addReference(self::BACKEND_IP_SB, $ipSb);

        $ipapiCo = new Backend();
        $ipapiCo->setName('IPAPI.CO IP查询');
        $ipapiCo->setUrl('https://ipapi.co');
        $ipapiCo->setWeight(7);
        $ipapiCo->setEnabled(true);
        $ipapiCo->setStatus(BackendStatus::UNHEALTHY); // 预设为不健康（实测频率限制）
        $ipapiCo->setTimeout(18);
        $ipapiCo->setMaxConnections(60);
        $ipapiCo->setHealthCheckPath('/json/');
        $ipapiCo->setLastHealthCheck(new \DateTimeImmutable('now'));
        $ipapiCo->setLastHealthStatus(false); // 健康检查失败（429错误）
        $ipapiCo->setDescription('IPAPI.CO提供免费IP地理位置查询服务');
        $manager->persist($ipapiCo);
        $this->addReference(self::BACKEND_IPAPI_CO, $ipapiCo);

        // HTTP测试服务后端
        $httpbinOrg = new Backend();
        $httpbinOrg->setName('HTTPBin测试服务');
        $httpbinOrg->setUrl('https://httpbin.org');
        $httpbinOrg->setWeight(5);
        $httpbinOrg->setEnabled(true);
        $httpbinOrg->setStatus(BackendStatus::ACTIVE);
        $httpbinOrg->setTimeout(20);
        $httpbinOrg->setMaxConnections(50);
        $httpbinOrg->setHealthCheckPath('/status/200');
        $httpbinOrg->setLastHealthCheck(new \DateTimeImmutable('now'));
        $httpbinOrg->setLastHealthStatus(true); // 预设为健康
        $httpbinOrg->setAvgResponseTime(200.0);
        $httpbinOrg->setDescription('HTTP请求测试工具，用于调试和测试HTTP客户端');
        $manager->persist($httpbinOrg);
        $this->addReference(self::BACKEND_HTTPBIN_ORG, $httpbinOrg);

        // JSON模拟API服务后端 - 主后端
        $jsonplaceholder1 = new Backend();
        $jsonplaceholder1->setName('JSONPlaceholder主节点');
        $jsonplaceholder1->setUrl('https://jsonplaceholder.typicode.com');
        $jsonplaceholder1->setWeight(15);
        $jsonplaceholder1->setEnabled(true);
        $jsonplaceholder1->setStatus(BackendStatus::ACTIVE);
        $jsonplaceholder1->setTimeout(30);
        $jsonplaceholder1->setMaxConnections(200);
        $jsonplaceholder1->setHealthCheckPath('/posts/1');
        $jsonplaceholder1->setLastHealthCheck(new \DateTimeImmutable('now'));
        $jsonplaceholder1->setLastHealthStatus(true); // 预设为健康
        $jsonplaceholder1->setAvgResponseTime(180.2);
        $jsonplaceholder1->setDescription('JSONPlaceholder主节点，提供RESTful API模拟服务');
        $manager->persist($jsonplaceholder1);
        $this->addReference(self::BACKEND_JSONPLACEHOLDER_1, $jsonplaceholder1);

        // JSON模拟API服务后端 - 备用节点
        $jsonplaceholder2 = new Backend();
        $jsonplaceholder2->setName('JSONPlaceholder备用节点');
        $jsonplaceholder2->setUrl('https://my-json-server.typicode.com');
        $jsonplaceholder2->setWeight(5);
        $jsonplaceholder2->setEnabled(true);
        $jsonplaceholder2->setStatus(BackendStatus::ACTIVE);
        $jsonplaceholder2->setTimeout(25);
        $jsonplaceholder2->setMaxConnections(100);
        $jsonplaceholder2->setHealthCheckPath('/typicode/demo/posts/1');
        $jsonplaceholder2->setLastHealthCheck(new \DateTimeImmutable('now'));
        $jsonplaceholder2->setLastHealthStatus(true); // 预设为健康
        $jsonplaceholder2->setAvgResponseTime(220.8);
        $jsonplaceholder2->setDescription('JSONPlaceholder备用节点，用于负载均衡');
        $manager->persist($jsonplaceholder2);
        $this->addReference(self::BACKEND_JSONPLACEHOLDER_2, $jsonplaceholder2);

        // OpenAI API后端
        $openaiApi = new Backend();
        $openaiApi->setName('OpenAI API');
        $openaiApi->setUrl('https://api.openai.com');
        $openaiApi->setWeight(12);
        $openaiApi->setEnabled(true);
        $openaiApi->setStatus(BackendStatus::UNHEALTHY); // 预设为不健康（需要鉴权）
        $openaiApi->setTimeout(120);
        $openaiApi->setMaxConnections(50);
        $openaiApi->setHealthCheckPath('/v1/models');
        $openaiApi->setLastHealthCheck(new \DateTimeImmutable('now'));
        $openaiApi->setLastHealthStatus(false); // 未经鉴权无法访问
        $openaiApi->setDescription('OpenAI官方API服务，提供ChatGPT等AI模型接口');
        $manager->persist($openaiApi);
        $this->addReference(self::BACKEND_OPENAI_API, $openaiApi);

        // CC中转服务后端
        $ccRelay = new Backend();
        $ccRelay->setName('CC中转-001');
        $ccRelay->setUrl('http://1.15.246.163:3000');
        $ccRelay->setWeight(8);
        $ccRelay->setEnabled(true);
        $ccRelay->setStatus(BackendStatus::UNHEALTHY); // 预设为不健康（私有IP）
        $ccRelay->setTimeout(100);
        $ccRelay->setMaxConnections(80);
        $ccRelay->setHealthCheckPath('/api/health');
        $ccRelay->setLastHealthCheck(new \DateTimeImmutable('now'));
        $ccRelay->setLastHealthStatus(false); // 私有网络不可访问
        $ccRelay->setDescription('CC中转服务器001，用于API转发代理');
        $manager->persist($ccRelay);
        $this->addReference(self::BACKEND_CC_RELAY_001, $ccRelay);

        $manager->flush();
    }
}
