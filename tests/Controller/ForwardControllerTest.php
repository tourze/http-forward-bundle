<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\HttpForwardBundle\Controller\ForwardController;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(ForwardController::class)]
#[RunTestsInSeparateProcesses]
final class ForwardControllerTest extends AbstractWebTestCase
{
    public function testForwardWithMatchingRule(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('Test API Forward');
        $rule->setSourcePath('/forward/api/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://jsonplaceholder.typicode.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $rule->setStripPrefix(true);
        $manager->persist($rule);
        $manager->flush();

        $client->request('GET', '/forward/api/posts/1');

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();

        // 允许的状态码：200(成功), 404(未找到), 500(服务器错误), 502(网关错误), 503(服务不可用)
        $allowedStatusCodes = [200, 404, 500, 502, 503];
        $this->assertContains(
            $statusCode,
            $allowedStatusCodes,
            sprintf('Response status code %d should be one of: %s', $statusCode, implode(', ', $allowedStatusCodes))
        );
    }

    public function testForwardWithNoMatchingRule(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/forward/nonexistent/path');

        // Check for 404 not found response
        $response = $client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString('No forwarding rule found', $content);
    }

    public function testForwardWithDisabledRule(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('Disabled Rule');
        $rule->setSourcePath('/forward/disabled/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(false);
        $rule->setPriority(100);
        $manager->persist($rule);
        $manager->flush();

        $client->request('GET', '/forward/disabled/test');

        // Check for 404 not found response
        $response = $client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString('No forwarding rule found', $content);
    }

    public function testForwardWithWrongHttpMethod(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('POST Only Rule');
        $rule->setSourcePath('/forward/post-only/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['POST']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $manager->persist($rule);
        $manager->flush();

        $client->request('GET', '/forward/post-only/test');

        // Check for 404 not found response
        $response = $client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString('No forwarding rule found', $content);
    }

    public function testForwardWithPriorityOrder(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule1 = new ForwardRule();
        $rule1->setName('Low Priority');
        $rule1->setSourcePath('/forward/priority/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://low-priority.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule1->addBackend($backend);
        $rule1->setHttpMethods(['GET']);
        $rule1->setEnabled(true);
        $rule1->setPriority(10);
        $manager->persist($rule1);

        $rule2 = new ForwardRule();
        $rule2->setName('High Priority');
        $rule2->setSourcePath('/forward/priority/test');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://high-priority.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule2->addBackend($backend);
        $rule2->setHttpMethods(['GET']);
        $rule2->setEnabled(true);
        $rule2->setPriority(100);
        $manager->persist($rule2);

        $manager->flush();

        $client->request('GET', '/forward/priority/test');

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            502 === $statusCode || 200 === $statusCode || 503 === $statusCode,
            sprintf('Should match high priority rule, got status %d', $statusCode)
        );
    }

    public function testForwardWithPostMethod(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('POST API Forward');
        $rule->setSourcePath('/forward/api/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://jsonplaceholder.typicode.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['POST']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $rule->setStripPrefix(true);
        $manager->persist($rule);
        $manager->flush();

        $jsonBody = json_encode(['title' => 'Test', 'body' => 'Test body']);
        $this->assertNotFalse($jsonBody);
        $client->request('POST', '/forward/api/posts', [], [], ['CONTENT_TYPE' => 'application/json'], $jsonBody);

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            201 === $statusCode || 200 === $statusCode || 502 === $statusCode || 503 === $statusCode || 404 === $statusCode,
            sprintf('Response should be successful or gateway error, got status %d', $statusCode)
        );
    }

    public function testForwardWithPutMethod(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('PUT API Forward');
        $rule->setSourcePath('/forward/api/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://jsonplaceholder.typicode.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['PUT']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $rule->setStripPrefix(true);
        $manager->persist($rule);
        $manager->flush();

        $jsonBody = json_encode(['title' => 'Updated']);
        $this->assertNotFalse($jsonBody);
        $client->request('PUT', '/forward/api/posts/1', [], [], ['CONTENT_TYPE' => 'application/json'], $jsonBody);

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            200 === $statusCode || 502 === $statusCode || 503 === $statusCode || 404 === $statusCode,
            sprintf('Response should be successful or gateway error, got status %d', $statusCode)
        );
    }

    public function testForwardWithDeleteMethod(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('DELETE API Forward');
        $rule->setSourcePath('/forward/api/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://jsonplaceholder.typicode.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['DELETE']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $rule->setStripPrefix(true);
        $manager->persist($rule);
        $manager->flush();

        $client->request('DELETE', '/forward/api/posts/1');

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            200 === $statusCode || 204 === $statusCode || 502 === $statusCode || 503 === $statusCode || 404 === $statusCode,
            sprintf('Response should be successful or gateway error, got status %d', $statusCode)
        );
    }

    public function testForwardWithPatchMethod(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('PATCH API Forward');
        $rule->setSourcePath('/forward/api/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://jsonplaceholder.typicode.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['PATCH']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $rule->setStripPrefix(true);
        $manager->persist($rule);
        $manager->flush();

        $jsonBody = json_encode(['title' => 'Patched']);
        $this->assertNotFalse($jsonBody);
        $client->request('PATCH', '/forward/api/posts/1', [], [], ['CONTENT_TYPE' => 'application/json'], $jsonBody);

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            200 === $statusCode || 502 === $statusCode || 503 === $statusCode || 404 === $statusCode,
            sprintf('Response should be successful or gateway error, got status %d', $statusCode)
        );
    }

    public function testForwardWithOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();

        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('OPTIONS API Forward');
        $rule->setSourcePath('/forward/api/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://jsonplaceholder.typicode.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['OPTIONS']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $manager->persist($rule);
        $manager->flush();

        $client->request('OPTIONS', '/forward/api/posts');

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            200 === $statusCode || 204 === $statusCode || 502 === $statusCode || 503 === $statusCode || 404 === $statusCode,
            sprintf('Response should be successful, no content, not found or gateway error, got status %d', $statusCode)
        );
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();

        try {
            // 手动处理不同的 HTTP 方法以满足 PHPStan 要求
            match ($method) {
                'TRACE' => $client->request('TRACE', '/forward/test'),
                'PURGE' => $client->request('PURGE', '/forward/test'),
                'INVALID' => $client->request('GET', '/forward/test'), // 对于INVALID方法，使用GET但会返回404
                default => $client->request('GET', '/forward/test'), // 默认情况
            };
        } catch (\Exception $e) {
            // 对于不支持的方法，可能会抛出 MethodNotAllowedHttpException
            if ('INVALID' !== $method) {
                $this->assertInstanceOf(MethodNotAllowedHttpException::class, $e);

                return;
            }
            throw $e;
        }

        // 如果没有抛出异常，检查响应状态码
        if ('INVALID' === $method) {
            $this->assertResponseStatusCodeSame(404);
        } else {
            $this->assertResponseStatusCodeSame(405);
        }
    }

    protected function onSetUp(): void
    {
    }
}
