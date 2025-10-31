<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\Header\RemoveResponseHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RemoveResponseHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class RemoveResponseHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private RemoveResponseHeaderMiddleware $middleware;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(RemoveResponseHeaderMiddleware::class);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(35, $this->middleware->getPriority());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('remove_response_header', RemoveResponseHeaderMiddleware::getServiceAlias());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->middleware->isEnabled());
    }

    public function testProcessResponseWithoutConfig(): void
    {
        $response = new Response('test content');
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Server', 'nginx/1.0');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertSame($response, $processed);
        $this->assertEquals('text/plain', $processed->headers->get('Content-Type'));
        $this->assertEquals('nginx/1.0', $processed->headers->get('Server'));
    }

    public function testProcessResponseWithEmptyHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = ['headers' => []];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertSame($response, $processed);
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseRemoveSingleHeader(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['Server'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Server'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseRemoveMultipleHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');
        $response->headers->set('X-Powered-By', 'PHP/8.0');
        $response->headers->set('X-Debug-Info', 'debug-data');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['Server', 'X-Powered-By', 'X-Debug-Info'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Server'));
        $this->assertNull($processed->headers->get('X-Powered-By'));
        $this->assertNull($processed->headers->get('X-Debug-Info'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseRemoveNonExistentHeader(): void
    {
        $response = new Response('test content');
        $response->headers->set('Existing-Header', 'existing-value');

        $config = [
            'headers' => ['Non-Existent-Header'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Non-Existent-Header'));
        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
    }

    public function testProcessResponseRemoveHeaderWithEmptyValue(): void
    {
        $response = new Response('test content');
        $response->headers->set('X-Empty', '');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['X-Empty'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-Empty'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseCaseInsensitiveHeaderNames(): void
    {
        $response = new Response('test content');
        $response->headers->set('content-type', 'application/json');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => ['Content-Type'], // Different case
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // HTTP headers are case-insensitive
        $this->assertNull($processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('content-type'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseMixedExistingAndNonExistent(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');
        $response->headers->set('X-Debug-Info', 'debug-data');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'headers' => [
                'Server',            // exists
                'Non-Existent',      // doesn't exist
                'X-Debug-Info',      // exists
                'Another-Missing',   // doesn't exist
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Server'));
        $this->assertNull($processed->headers->get('Non-Existent'));
        $this->assertNull($processed->headers->get('X-Debug-Info'));
        $this->assertNull($processed->headers->get('Another-Missing'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseWithDuplicateHeadersInConfig(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');
        $response->headers->set('X-Debug-Info', 'debug-data');

        $config = [
            'headers' => [
                'Server',
                'X-Debug-Info',
                'Server', // Duplicate
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Server'));
        $this->assertNull($processed->headers->get('X-Debug-Info'));
    }

    public function testProcessResponseRemoveSecurityHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('X-Powered-By', 'PHP/8.0');
        $response->headers->set('Server', 'Apache/2.4');
        $response->headers->set('X-Debug-Token', '123456');
        $response->headers->set('Content-Type', 'text/html');

        $config = [
            'headers' => ['X-Powered-By', 'Server', 'X-Debug-Token'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // Security-sensitive headers should be removed
        $this->assertNull($processed->headers->get('X-Powered-By'));
        $this->assertNull($processed->headers->get('Server'));
        $this->assertNull($processed->headers->get('X-Debug-Token'));

        // Content headers should remain
        $this->assertEquals('text/html', $processed->headers->get('Content-Type'));
    }

    public function testProcessResponseWithAllHeadersRemoved(): void
    {
        $response = new Response('test content');
        $response->headers->set('Header-1', 'value1');
        $response->headers->set('Header-2', 'value2');
        $response->headers->set('Header-3', 'value3');

        $config = [
            'headers' => ['Header-1', 'Header-2', 'Header-3'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Header-1'));
        $this->assertNull($processed->headers->get('Header-2'));
        $this->assertNull($processed->headers->get('Header-3'));
    }

    public function testProcessResponsePreservesResponseContent(): void
    {
        $content = '{"message": "Hello, World!"}';
        $response = new Response($content, 201);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Server', 'nginx/1.0');

        $config = [
            'headers' => ['Server'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals($content, $processed->getContent());
        $this->assertEquals(201, $processed->getStatusCode());
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('Server'));
    }

    public function testProcessResponseRemoveCacheHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        $response->headers->set('ETag', '"123456"');
        $response->headers->set('Last-Modified', 'Wed, 18 Sep 2024 12:00:00 GMT');
        $response->headers->set('Expires', 'Thu, 19 Sep 2024 12:00:00 GMT');
        $response->headers->set('Content-Type', 'application/json');

        $config = [
            'headers' => ['Cache-Control', 'ETag', 'Last-Modified', 'Expires'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Cache-Control'));
        $this->assertNull($processed->headers->get('ETag'));
        $this->assertNull($processed->headers->get('Last-Modified'));
        $this->assertNull($processed->headers->get('Expires'));
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
    }

    public function testProcessResponseRemoveCorsHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('Content-Type', 'application/json');

        $config = [
            'headers' => [
                'Access-Control-Allow-Origin',
                'Access-Control-Allow-Methods',
                'Access-Control-Allow-Headers',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($processed->headers->get('Access-Control-Allow-Methods'));
        $this->assertNull($processed->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
    }
}
