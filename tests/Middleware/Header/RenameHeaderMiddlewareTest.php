<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Header\RenameHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RenameHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class RenameHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private RenameHeaderMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(RenameHeaderMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(80, $this->middleware->getPriority());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('rename_header', RenameHeaderMiddleware::getServiceAlias());
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

    public function testProcessRequestWithEmptyMappings(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = ['mappings' => []];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $processed);
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestRenameSingleHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'mappings' => [
                'Authorization' => 'X-Auth-Token',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertEquals('Bearer token', $processed->headers->get('X-Auth-Token'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestRenameMultipleHeaders(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('X-Old-Header', 'old-value');
        $request->headers->set('X-Debug', 'debug-info');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'mappings' => [
                'Authorization' => 'X-Auth-Token',
                'X-Old-Header' => 'X-New-Header',
                'X-Debug' => 'X-Trace-Info',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Original headers should be removed
        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertNull($processed->headers->get('X-Old-Header'));
        $this->assertNull($processed->headers->get('X-Debug'));

        // New headers should exist with original values
        $this->assertEquals('Bearer token', $processed->headers->get('X-Auth-Token'));
        $this->assertEquals('old-value', $processed->headers->get('X-New-Header'));
        $this->assertEquals('debug-info', $processed->headers->get('X-Trace-Info'));

        // Untouched headers should remain
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestRenameNonExistentHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Existing-Header', 'existing-value');

        $config = [
            'mappings' => [
                'Non-Existent' => 'X-New-Header',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-New-Header'));
        $this->assertNull($processed->headers->get('Non-Existent'));
        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
    }

    public function testProcessRequestRenameHeaderWithEmptyValue(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-Empty', '');
        $request->headers->set('Keep-Me', 'keep-value');

        $config = [
            'mappings' => [
                'X-Empty' => 'X-Renamed-Empty',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-Empty'));
        $this->assertEquals('', $processed->headers->get('X-Renamed-Empty'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessRequestRenameHeaderWithNullValue(): void
    {
        $request = Request::create('/test');
        // Simulate a header that might return null
        $request->headers->set('X-Test', null);

        $config = [
            'mappings' => [
                'X-Test' => 'X-Renamed-Test',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-Test'));
        // The implementation uses $value ?? '' so null becomes empty string
        $this->assertEquals('', $processed->headers->get('X-Renamed-Test'));
    }

    public function testProcessRequestCaseInsensitiveHeaderNames(): void
    {
        $request = Request::create('/test');
        $request->headers->set('content-type', 'application/json');

        $config = [
            'mappings' => [
                'Content-Type' => 'X-Content-Type', // Different case
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // HTTP headers are case-insensitive
        $this->assertNull($processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('content-type'));
        $this->assertEquals('application/json', $processed->headers->get('X-Content-Type'));
    }

    public function testProcessRequestOverwriteExistingHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-Source', 'source-value');
        $request->headers->set('X-Target', 'existing-target-value');

        $config = [
            'mappings' => [
                'X-Source' => 'X-Target', // Target already exists
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-Source'));
        $this->assertEquals('source-value', $processed->headers->get('X-Target'));
    }

    public function testProcessRequestChainRenames(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Header-A', 'value-a');
        $request->headers->set('Header-B', 'value-b');

        $config = [
            'mappings' => [
                'Header-A' => 'Header-X',
                'Header-B' => 'Header-Y',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Both original headers should be gone
        $this->assertNull($processed->headers->get('Header-A'));
        $this->assertNull($processed->headers->get('Header-B'));

        // New headers should exist with original values
        $this->assertEquals('value-a', $processed->headers->get('Header-X'));
        $this->assertEquals('value-b', $processed->headers->get('Header-Y'));
    }

    public function testProcessRequestMixedExistingAndNonExistent(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Existing-1', 'value-1');
        $request->headers->set('Existing-2', 'value-2');

        $config = [
            'mappings' => [
                'Existing-1' => 'X-Renamed-1',
                'Non-Existent' => 'X-Renamed-Missing',
                'Existing-2' => 'X-Renamed-2',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Existing headers should be renamed
        $this->assertNull($processed->headers->get('Existing-1'));
        $this->assertNull($processed->headers->get('Existing-2'));
        $this->assertEquals('value-1', $processed->headers->get('X-Renamed-1'));
        $this->assertEquals('value-2', $processed->headers->get('X-Renamed-2'));

        // Non-existent mapping should not create new header
        $this->assertNull($processed->headers->get('X-Renamed-Missing'));
        $this->assertNull($processed->headers->get('Non-Existent'));
    }

    public function testProcessRequestWithSpecialCharactersInValues(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-Special', 'value@#$%^&*()');
        $request->headers->set('X-Unicode', 'café');

        $config = [
            'mappings' => [
                'X-Special' => 'X-Renamed-Special',
                'X-Unicode' => 'X-Renamed-Unicode',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-Special'));
        $this->assertNull($processed->headers->get('X-Unicode'));
        $this->assertEquals('value@#$%^&*()', $processed->headers->get('X-Renamed-Special'));
        $this->assertEquals('café', $processed->headers->get('X-Renamed-Unicode'));
    }
}
