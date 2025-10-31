<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Builtin\AuthHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AuthHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class AuthHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private AuthHeaderMiddleware $middleware;

    private ForwardLog $forwardLog;

    public function testAddAuthorizationHeader(): void
    {
        $request = Request::create('/');

        $config = [
            'action' => 'add',
            'scheme' => 'Bearer',
            'token' => 'test-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer test-token', $processed->headers->get('Authorization'));
    }

    public function testReplaceAuthorizationHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Basic old-token');

        $config = [
            'action' => 'replace',
            'scheme' => 'Bearer',
            'token' => 'new-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer new-token', $processed->headers->get('Authorization'));
    }

    public function testRemoveAuthorizationHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token');

        $config = [
            'action' => 'remove',
            'token' => 'dummy',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

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

    public function testProcessRequest(): void
    {
        $request = Request::create('/');

        $config = [
            'action' => 'add',
            'scheme' => 'Bearer',
            'token' => 'test-token',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertInstanceOf(Request::class, $processed);
        $this->assertEquals('Bearer test-token', $processed->headers->get('Authorization'));
    }

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(AuthHeaderMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }
}
