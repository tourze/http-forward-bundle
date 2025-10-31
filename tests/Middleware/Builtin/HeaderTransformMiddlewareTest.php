<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Builtin\HeaderTransformMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(HeaderTransformMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class HeaderTransformMiddlewareTest extends AbstractIntegrationTestCase
{
    private HeaderTransformMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(HeaderTransformMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(90, $this->middleware->getPriority());
    }

    public function testProcessRequestWithoutConfig(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('Bearer token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestAddHeaders(): void
    {
        $request = Request::create('/test');
        $config = [
            'add' => [
                'X-API-Key' => 'secret-key',
                'X-Client-ID' => '12345',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('secret-key', $processed->headers->get('X-API-Key'));
        $this->assertEquals('12345', $processed->headers->get('X-Client-ID'));
    }

    public function testProcessRequestRemoveHeaders(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('X-Debug', 'true');
        $request->headers->set('X-Keep', 'keep-this');

        $config = [
            'remove' => ['Authorization', 'X-Debug'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertNull($processed->headers->get('X-Debug'));
        $this->assertEquals('keep-this', $processed->headers->get('X-Keep'));
    }

    public function testProcessRequestRenameHeaders(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('X-Old-Header', 'value');

        $config = [
            'rename' => [
                'Authorization' => 'X-Auth-Token',
                'X-Old-Header' => 'X-New-Header',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('Authorization'));
        $this->assertNull($processed->headers->get('X-Old-Header'));
        $this->assertEquals('Bearer token', $processed->headers->get('X-Auth-Token'));
        $this->assertEquals('value', $processed->headers->get('X-New-Header'));
    }

    public function testProcessRequestRenameNonExistentHeader(): void
    {
        $request = Request::create('/test');
        $config = [
            'rename' => [
                'Non-Existent' => 'X-New-Header',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-New-Header'));
        $this->assertNull($processed->headers->get('Non-Existent'));
    }

    public function testProcessRequestCombinedOperations(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer old-token');
        $request->headers->set('X-Debug', 'remove-me');
        $request->headers->set('X-Rename-Me', 'rename-value');

        $config = [
            'add' => [
                'X-API-Key' => 'new-key',
                'X-Version' => '2.0',
            ],
            'remove' => ['X-Debug'],
            'rename' => [
                'X-Rename-Me' => 'X-Renamed',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Added headers
        $this->assertEquals('new-key', $processed->headers->get('X-API-Key'));
        $this->assertEquals('2.0', $processed->headers->get('X-Version'));

        // Removed header
        $this->assertNull($processed->headers->get('X-Debug'));

        // Renamed header
        $this->assertNull($processed->headers->get('X-Rename-Me'));
        $this->assertEquals('rename-value', $processed->headers->get('X-Renamed'));

        // Untouched header
        $this->assertEquals('Bearer old-token', $processed->headers->get('Authorization'));
    }

    public function testProcessResponseWithoutConfig(): void
    {
        $response = new Response('test content');
        $response->headers->set('Content-Type', 'text/plain');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertSame($response, $processed);
        $this->assertEquals('text/plain', $processed->headers->get('Content-Type'));
    }

    public function testProcessResponseAddHeaders(): void
    {
        $response = new Response('test content');
        $config = [
            'add_response' => [
                'X-Powered-By' => 'Custom API',
                'X-Version' => '1.0',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Custom API', $processed->headers->get('X-Powered-By'));
        $this->assertEquals('1.0', $processed->headers->get('X-Version'));
    }

    public function testProcessResponseRemoveHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');
        $response->headers->set('X-Debug-Info', 'debug-data');
        $response->headers->set('Content-Type', 'text/plain');

        $config = [
            'remove_response' => ['Server', 'X-Debug-Info'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Server'));
        $this->assertNull($processed->headers->get('X-Debug-Info'));
        $this->assertEquals('text/plain', $processed->headers->get('Content-Type'));
    }

    public function testProcessResponseRenameHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Old-Cache', 'public');

        $config = [
            'rename_response' => [
                'Content-Type' => 'X-Content-Type',
                'X-Old-Cache' => 'Cache-Control',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('X-Old-Cache'));
        $this->assertEquals('application/json', $processed->headers->get('X-Content-Type'));
        $this->assertEquals('public', $processed->headers->get('Cache-Control'));
    }

    public function testProcessResponseCombinedOperations(): void
    {
        $response = new Response('{"data": "test"}');
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Server', 'nginx');
        $response->headers->set('X-Old-Header', 'old-value');

        $config = [
            'add_response' => [
                'X-API-Version' => '2.0',
                'X-Custom' => 'custom-value',
            ],
            'remove_response' => ['Server'],
            'rename_response' => [
                'X-Old-Header' => 'X-New-Header',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // Added headers
        $this->assertEquals('2.0', $processed->headers->get('X-API-Version'));
        $this->assertEquals('custom-value', $processed->headers->get('X-Custom'));

        // Removed header
        $this->assertNull($processed->headers->get('Server'));

        // Renamed header
        $this->assertNull($processed->headers->get('X-Old-Header'));
        $this->assertEquals('old-value', $processed->headers->get('X-New-Header'));

        // Untouched header
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
    }

    public function testProcessRequestAndResponseTogether(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer token');

        $response = new Response('{"result": "success"}');
        $response->headers->set('Content-Type', 'application/json');

        $requestConfig = [
            'add' => ['X-API-Key' => 'secret'],
            'rename' => ['Authorization' => 'X-Auth-Token'],
        ];

        $responseConfig = [
            'add_response' => ['X-Processing-Time' => '100ms'],
            'rename_response' => ['Content-Type' => 'X-Content-Type'],
        ];

        $config = array_merge($requestConfig, $responseConfig);

        $processedRequest = $this->middleware->processRequest($request, $this->forwardLog, $config);
        $processedResponse = $this->middleware->processResponse($response, $config);

        // Request assertions
        $this->assertEquals('secret', $processedRequest->headers->get('X-API-Key'));
        $this->assertEquals('Bearer token', $processedRequest->headers->get('X-Auth-Token'));
        $this->assertNull($processedRequest->headers->get('Authorization'));

        // Response assertions
        $this->assertEquals('100ms', $processedResponse->headers->get('X-Processing-Time'));
        $this->assertEquals('application/json', $processedResponse->headers->get('X-Content-Type'));
        $this->assertNull($processedResponse->headers->get('Content-Type'));
    }

    public function testProcessRequestWithEmptyHeaderValue(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-Empty', '');

        $config = [
            'rename' => ['X-Empty' => 'X-Renamed'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('', $processed->headers->get('X-Renamed'));
        $this->assertNull($processed->headers->get('X-Empty'));
    }

    public function testProcessResponseWithEmptyHeaderValue(): void
    {
        $response = new Response('test');
        $response->headers->set('X-Empty', '');

        $config = [
            'rename_response' => ['X-Empty' => 'X-Renamed'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('', $processed->headers->get('X-Renamed'));
        $this->assertNull($processed->headers->get('X-Empty'));
    }
}
