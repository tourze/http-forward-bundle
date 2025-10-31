<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Auth\SetAuthHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SetAuthHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class SetAuthHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private SetAuthHeaderMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(SetAuthHeaderMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testProcessRequestWithValidToken(): void
    {
        $request = Request::create('/');

        $config = [
            'scheme' => 'Bearer',
            'token' => 'test-token-123',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer test-token-123', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithDefaultScheme(): void
    {
        $request = Request::create('/');

        $config = [
            'token' => 'test-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer test-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithCustomScheme(): void
    {
        $request = Request::create('/');

        $config = [
            'scheme' => 'Basic',
            'token' => 'encoded-credentials',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Basic encoded-credentials', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyToken(): void
    {
        $request = Request::create('/');

        $config = [
            'scheme' => 'Bearer',
            'token' => '',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithNoTokenConfig(): void
    {
        $request = Request::create('/');

        $config = [
            'scheme' => 'Bearer',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestReplacesExistingHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Basic old-token');

        $config = [
            'scheme' => 'Bearer',
            'token' => 'new-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer new-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyConfig(): void
    {
        $request = Request::create('/');

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
        $this->assertEquals('set_auth_header', SetAuthHeaderMiddleware::getServiceAlias());
    }

    public function testProcessRequestReturnsRequestInstance(): void
    {
        $request = Request::create('/');

        $config = [
            'scheme' => 'Bearer',
            'token' => 'test-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertInstanceOf(Request::class, $processed);
        $this->assertSame($request, $processed);
    }
}
