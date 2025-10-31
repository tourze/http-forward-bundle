<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Auth\RemoveAuthHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RemoveAuthHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class RemoveAuthHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private RemoveAuthHeaderMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(RemoveAuthHeaderMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testProcessRequestRemovesExistingAuthorizationHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer test-token');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestRemovesBasicAuthHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Basic encoded-credentials');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithNoAuthorizationHeader(): void
    {
        $request = Request::create('/');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestPreservesOtherHeaders(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer test-token');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-Custom-Header', 'custom-value');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
        $this->assertEquals('custom-value', $processed->headers->get('X-Custom-Header'));
    }

    public function testProcessRequestWithConfig(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer test-token');

        $config = [
            'some_config' => 'value',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyConfig(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer test-token');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->middleware->isEnabled());
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(100, $this->middleware->getPriority());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('remove_auth_header', RemoveAuthHeaderMiddleware::getServiceAlias());
    }

    public function testProcessRequestReturnsRequestInstance(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer test-token');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertInstanceOf(Request::class, $processed);
        $this->assertSame($request, $processed);
    }

    public function testProcessRequestWithMultipleAuthHeaders(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token1');
        // 模拟多个Authorization header的情况（虽然HTTP标准不推荐）
        $request->headers->set('Authorization', 'Bearer token2', false);

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertNull($processed->headers->get('Authorization'));
    }
}
