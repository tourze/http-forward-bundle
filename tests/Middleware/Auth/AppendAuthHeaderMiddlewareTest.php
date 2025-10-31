<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Auth\AppendAuthHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AppendAuthHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class AppendAuthHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private AppendAuthHeaderMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(AppendAuthHeaderMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testProcessRequestWithNoExistingHeader(): void
    {
        $request = Request::create('/');

        $config = [
            'scheme' => 'Bearer',
            'token' => 'test-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer test-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestAppendsToExistingHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer existing-token');

        $config = [
            'scheme' => 'Basic',
            'token' => 'new-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer existing-token, Basic new-token', $processed->headers->get('Authorization'));
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

    public function testProcessRequestWithDefaultSchemeAndExistingHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Basic old-token');

        $config = [
            'token' => 'new-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Basic old-token, Bearer new-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyToken(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer existing-token');

        $config = [
            'scheme' => 'Bearer',
            'token' => '',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer existing-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithNoTokenConfig(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer existing-token');

        $config = [
            'scheme' => 'Bearer',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer existing-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyTokenAndNoExistingHeader(): void
    {
        $request = Request::create('/');

        $config = [
            'scheme' => 'Bearer',
            'token' => '',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyConfig(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer existing-token');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertEquals('Bearer existing-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyConfigAndNoExistingHeader(): void
    {
        $request = Request::create('/');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertNull($processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithCustomScheme(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token1');

        $config = [
            'scheme' => 'Digest',
            'token' => 'digest-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer token1, Digest digest-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyExistingHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', '');

        $config = [
            'scheme' => 'Bearer',
            'token' => 'test-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer test-token', $processed->headers->get('Authorization'));
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
        $this->assertEquals('append_auth_header', AppendAuthHeaderMiddleware::getServiceAlias());
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
