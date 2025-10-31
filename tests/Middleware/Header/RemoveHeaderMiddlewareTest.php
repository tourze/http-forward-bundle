<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Header\RemoveHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RemoveHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class RemoveHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private RemoveHeaderMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(RemoveHeaderMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(85, $this->middleware->getPriority());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('remove_header', RemoveHeaderMiddleware::getServiceAlias());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->middleware->isEnabled());
    }

    public function testProcessRequestWithoutConfig(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('Content-Type', 'application/json');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('Bearer token', $processed->headers->get('Authorization'));
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
    }

    public function testProcessRequestWithEmptyHeaders(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = ['headers' => []];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $processed);
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestRemoveSingleHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['Authorization'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestRemoveMultipleHeaders(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('X-Debug', 'true');
        $request->headers->set('X-Trace-ID', '123456');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['Authorization', 'X-Debug', 'X-Trace-ID'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertNull($processed->headers->get('X-Debug'));
        $this->assertNull($processed->headers->get('X-Trace-ID'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestRemoveNonExistentHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Existing-Header', 'existing-value');

        $config = [
            'headers' => ['Non-Existent-Header'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Non-Existent-Header'));
        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
    }

    public function testProcessRequestRemoveHeaderWithEmptyValue(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-Empty', '');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['X-Empty'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-Empty'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestCaseInsensitiveHeaderNames(): void
    {
        $request = Request::create('/test');
        $request->headers->set('content-type', 'application/json');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['Content-Type'], // Different case
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // HTTP headers are case-insensitive
        $this->assertNull($processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('content-type'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestMixedExistingAndNonExistent(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('X-Debug', 'true');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => [
                'Authorization',      // exists
                'Non-Existent',      // doesn't exist
                'X-Debug',           // exists
                'Another-Missing',   // doesn't exist
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertNull($processed->headers->get('Non-Existent'));
        $this->assertNull($processed->headers->get('X-Debug'));
        $this->assertNull($processed->headers->get('Another-Missing'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestWithDuplicateHeadersInConfig(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('X-Debug', 'true');

        $config = [
            'headers' => [
                'Authorization',
                'X-Debug',
                'Authorization', // Duplicate
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertNull($processed->headers->get('X-Debug'));
    }

    public function testProcessRequestWithAllHeadersRemoved(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Header-1', 'value1');
        $request->headers->set('Header-2', 'value2');
        $request->headers->set('Header-3', 'value3');

        $config = [
            'headers' => ['Header-1', 'Header-2', 'Header-3'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Header-1'));
        $this->assertNull($processed->headers->get('Header-2'));
        $this->assertNull($processed->headers->get('Header-3'));
    }
}
