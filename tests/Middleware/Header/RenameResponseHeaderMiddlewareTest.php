<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\Header\RenameResponseHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RenameResponseHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class RenameResponseHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private RenameResponseHeaderMiddleware $middleware;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(RenameResponseHeaderMiddleware::class);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(30, $this->middleware->getPriority());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('rename_response_header', RenameResponseHeaderMiddleware::getServiceAlias());
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

    public function testProcessResponseWithEmptyMappings(): void
    {
        $response = new Response('test content');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = ['mappings' => []];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertSame($response, $processed);
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseRenameSingleHeader(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'mappings' => [
                'Server' => 'X-Server-Info',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Server'));
        $this->assertEquals('nginx/1.0', $processed->headers->get('X-Server-Info'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseRenameMultipleHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');
        $response->headers->set('X-Powered-By', 'PHP/8.0');
        $response->headers->set('X-Debug-Token', '123456');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'mappings' => [
                'Server' => 'X-Server-Info',
                'X-Powered-By' => 'X-Runtime-Info',
                'X-Debug-Token' => 'X-Trace-ID',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // Original headers should be removed
        $this->assertNull($processed->headers->get('Server'));
        $this->assertNull($processed->headers->get('X-Powered-By'));
        $this->assertNull($processed->headers->get('X-Debug-Token'));

        // New headers should exist with original values
        $this->assertEquals('nginx/1.0', $processed->headers->get('X-Server-Info'));
        $this->assertEquals('PHP/8.0', $processed->headers->get('X-Runtime-Info'));
        $this->assertEquals('123456', $processed->headers->get('X-Trace-ID'));

        // Untouched headers should remain
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseRenameNonExistentHeader(): void
    {
        $response = new Response('test content');
        $response->headers->set('Existing-Header', 'existing-value');

        $config = [
            'mappings' => [
                'Non-Existent' => 'X-New-Header',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-New-Header'));
        $this->assertNull($processed->headers->get('Non-Existent'));
        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
    }

    public function testProcessResponseRenameHeaderWithEmptyValue(): void
    {
        $response = new Response('test content');
        $response->headers->set('X-Empty', '');
        $response->headers->set('Keep-Me', 'keep-value');

        $config = [
            'mappings' => [
                'X-Empty' => 'X-Renamed-Empty',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-Empty'));
        $this->assertEquals('', $processed->headers->get('X-Renamed-Empty'));
        $this->assertEquals('keep-value', $processed->headers->get('Keep-Me'));
    }

    public function testProcessResponseRenameHeaderWithNullValue(): void
    {
        $response = new Response('test content');
        // Simulate a header that might return null
        $response->headers->set('X-Test', null);

        $config = [
            'mappings' => [
                'X-Test' => 'X-Renamed-Test',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-Test'));
        // The implementation uses $value ?? '' so null becomes empty string
        $this->assertEquals('', $processed->headers->get('X-Renamed-Test'));
    }

    public function testProcessResponseCaseInsensitiveHeaderNames(): void
    {
        $response = new Response('test content');
        $response->headers->set('content-type', 'application/json');

        $config = [
            'mappings' => [
                'Content-Type' => 'X-Content-Type', // Different case
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // HTTP headers are case-insensitive
        $this->assertNull($processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('content-type'));
        $this->assertEquals('application/json', $processed->headers->get('X-Content-Type'));
    }

    public function testProcessResponseOverwriteExistingHeader(): void
    {
        $response = new Response('test content');
        $response->headers->set('X-Source', 'source-value');
        $response->headers->set('X-Target', 'existing-target-value');

        $config = [
            'mappings' => [
                'X-Source' => 'X-Target', // Target already exists
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-Source'));
        $this->assertEquals('source-value', $processed->headers->get('X-Target'));
    }

    public function testProcessResponseChainRenames(): void
    {
        $response = new Response('test content');
        $response->headers->set('Header-A', 'value-a');
        $response->headers->set('Header-B', 'value-b');

        $config = [
            'mappings' => [
                'Header-A' => 'Header-X',
                'Header-B' => 'Header-Y',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // Both original headers should be gone
        $this->assertNull($processed->headers->get('Header-A'));
        $this->assertNull($processed->headers->get('Header-B'));

        // New headers should exist with original values
        $this->assertEquals('value-a', $processed->headers->get('Header-X'));
        $this->assertEquals('value-b', $processed->headers->get('Header-Y'));
    }

    public function testProcessResponseMixedExistingAndNonExistent(): void
    {
        $response = new Response('test content');
        $response->headers->set('Existing-1', 'value-1');
        $response->headers->set('Existing-2', 'value-2');

        $config = [
            'mappings' => [
                'Existing-1' => 'X-Renamed-1',
                'Non-Existent' => 'X-Renamed-Missing',
                'Existing-2' => 'X-Renamed-2',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // Existing headers should be renamed
        $this->assertNull($processed->headers->get('Existing-1'));
        $this->assertNull($processed->headers->get('Existing-2'));
        $this->assertEquals('value-1', $processed->headers->get('X-Renamed-1'));
        $this->assertEquals('value-2', $processed->headers->get('X-Renamed-2'));

        // Non-existent mapping should not create new header
        $this->assertNull($processed->headers->get('X-Renamed-Missing'));
        $this->assertNull($processed->headers->get('Non-Existent'));
    }

    public function testProcessResponseWithSpecialCharactersInValues(): void
    {
        $response = new Response('test content');
        $response->headers->set('X-Special', 'value@#$%^&*()');
        $response->headers->set('X-Unicode', 'café');

        $config = [
            'mappings' => [
                'X-Special' => 'X-Renamed-Special',
                'X-Unicode' => 'X-Renamed-Unicode',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-Special'));
        $this->assertNull($processed->headers->get('X-Unicode'));
        $this->assertEquals('value@#$%^&*()', $processed->headers->get('X-Renamed-Special'));
        $this->assertEquals('café', $processed->headers->get('X-Renamed-Unicode'));
    }

    public function testProcessResponseRenameContentHeaders(): void
    {
        $response = new Response('{"data": "test"}');
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', '15');

        $config = [
            'mappings' => [
                'Content-Type' => 'X-Original-Content-Type',
                'Content-Encoding' => 'X-Original-Encoding',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('Content-Encoding'));
        $this->assertEquals('application/json', $processed->headers->get('X-Original-Content-Type'));
        $this->assertEquals('gzip', $processed->headers->get('X-Original-Encoding'));
        $this->assertEquals('15', $processed->headers->get('Content-Length')); // Not renamed
    }

    public function testProcessResponseRenameSecurityHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000');

        $config = [
            'mappings' => [
                'X-Frame-Options' => 'X-Legacy-Frame-Options',
                'X-Content-Type-Options' => 'X-Legacy-Content-Type-Options',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-Frame-Options'));
        $this->assertNull($processed->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $processed->headers->get('X-Legacy-Frame-Options'));
        $this->assertEquals('nosniff', $processed->headers->get('X-Legacy-Content-Type-Options'));
        $this->assertEquals('max-age=31536000', $processed->headers->get('Strict-Transport-Security')); // Not renamed
    }

    public function testProcessResponsePreservesResponseContent(): void
    {
        $content = '{"message": "Hello, World!"}';
        $response = new Response($content, 201);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Server', 'nginx/1.0');

        $config = [
            'mappings' => [
                'Server' => 'X-Server-Info',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals($content, $processed->getContent());
        $this->assertEquals(201, $processed->getStatusCode());
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('Server'));
        $this->assertEquals('nginx/1.0', $processed->headers->get('X-Server-Info'));
    }
}
