<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Service\LoadBalanceService;
use Tourze\HttpForwardBundle\Service\UrlBuilder;

/**
 * @internal
 */
#[CoversClass(UrlBuilder::class)]
final class UrlBuilderTest extends TestCase
{
    private UrlBuilder $urlBuilder;

    /** @var LoadBalanceService&MockObject */
    private LoadBalanceService $loadBalanceService;

    protected function setUp(): void
    {
        $this->loadBalanceService = $this->createMockLoadBalanceService();
        $this->urlBuilder = new UrlBuilder($this->loadBalanceService);
    }

    /**
     * @return LoadBalanceService&MockObject
     */
    private function createMockLoadBalanceService(): LoadBalanceService
    {
        return $this->createMock(LoadBalanceService::class);
    }

    private function setMockBackend(Backend $backend): void
    {
        $this->loadBalanceService
            ->method('selectBackend')
            ->willReturn($backend)
        ;
    }

    public function testBuildSimpleUrl(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/users', 'PATH_INFO' => '/api/users']);
        $rule = $this->createRule('/api/users', false);
        $backend = $this->createBackend('http://target.com');

        $this->setMockBackend($backend);

        $result = $this->urlBuilder->build($request, $rule);

        $this->assertSame('http://target.com/api/users', $result);
    }

    public function testBuildUrlWithStripPrefix(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/users', 'PATH_INFO' => '/api/users']);
        $rule = $this->createRule('/api', true);
        $backend = $this->createBackend('http://target.com');

        $this->setMockBackend($backend);

        $result = $this->urlBuilder->build($request, $rule);

        $this->assertSame('http://target.com/users', $result);
    }

    public function testBuildUrlWithParameters(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/users/123', 'PATH_INFO' => '/api/users/123']);
        $rule = $this->createRule('/api/users/{id}', false);
        $backend = $this->createBackend('http://target.com/users/{id}');

        $this->setMockBackend($backend);

        $result = $this->urlBuilder->build($request, $rule);

        $this->assertSame('http://target.com/users/123', $result);
    }

    public function testBuildUrlWithQueryString(): void
    {
        $request = new Request(['page' => '2', 'limit' => '10'], [], [], [], [], [
            'REQUEST_URI' => '/api/users?page=2&limit=10',
            'PATH_INFO' => '/api/users',
            'QUERY_STRING' => 'page=2&limit=10',
        ]);
        $rule = $this->createRule('/api/users', false);
        $backend = $this->createBackend('http://target.com');

        $this->setMockBackend($backend);

        $result = $this->urlBuilder->build($request, $rule);

        $this->assertStringContainsString('http://target.com/api/users?', $result);
        $this->assertStringContainsString('page=2', $result);
        $this->assertStringContainsString('limit=10', $result);
    }

    public function testBuildWithBackend(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/users', 'PATH_INFO' => '/api/users']);
        $rule = $this->createRule('/api/users', false);
        $backend = $this->createBackend('http://target.com');

        $result = $this->urlBuilder->buildWithBackend($request, $rule, $backend);

        $this->assertSame('http://target.com/api/users', $result);
    }

    private function createRule(string $sourcePath, bool $stripPrefix): ForwardRule
    {
        $rule = new ForwardRule();
        $rule->setSourcePath($sourcePath);
        $rule->setStripPrefix($stripPrefix);

        return $rule;
    }

    private function createBackend(string $url): Backend
    {
        $backend = new Backend();
        $backend->setUrl($url);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $backend->setName('test-backend');

        return $backend;
    }
}
