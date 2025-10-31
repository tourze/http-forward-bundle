<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\Header\AddResponseHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AddResponseHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class AddResponseHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private AddResponseHeaderMiddleware $middleware;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(AddResponseHeaderMiddleware::class);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(40, $this->middleware->getPriority());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('add_response_header', AddResponseHeaderMiddleware::getServiceAlias());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->middleware->isEnabled());
    }

    public function testProcessResponseWithoutConfig(): void
    {
        $response = new Response('test content');
        $response->headers->set('Content-Type', 'text/plain');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertSame($response, $processed);
        $this->assertEquals('text/plain', $processed->headers->get('Content-Type'));
    }

    public function testProcessResponseWithEmptyHeaders(): void
    {
        $response = new Response('test content');
        $response->headers->set('Existing-Header', 'existing-value');

        $config = ['headers' => []];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertSame($response, $processed);
        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
    }

    public function testProcessResponseAddSingleHeader(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'X-Powered-By' => 'Custom API',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Custom API', $processed->headers->get('X-Powered-By'));
    }

    public function testProcessResponseAddMultipleHeaders(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'X-Powered-By' => 'Custom API',
                'X-Version' => '1.0',
                'X-Environment' => 'production',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Custom API', $processed->headers->get('X-Powered-By'));
        $this->assertEquals('1.0', $processed->headers->get('X-Version'));
        $this->assertEquals('production', $processed->headers->get('X-Environment'));
    }

    public function testProcessResponseOverwriteExistingHeader(): void
    {
        $response = new Response('test content');
        $response->headers->set('Server', 'nginx/1.0');

        $config = [
            'headers' => [
                'Server' => 'Custom Server/2.0',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Custom Server/2.0', $processed->headers->get('Server'));
    }

    public function testProcessResponseWithEmptyHeaderValue(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'X-Empty' => '',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('', $processed->headers->get('X-Empty'));
    }

    public function testProcessResponseWithNullHeaderValue(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'X-Null' => null,
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNull($processed->headers->get('X-Null'));
    }

    public function testProcessResponseMixedWithExistingHeaders(): void
    {
        $response = new Response('{"data": "test"}');
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Existing-Header', 'existing-value');

        $config = [
            'headers' => [
                'X-API-Version' => '2.0',
                'X-Custom' => 'custom-value',
                'Content-Type' => 'application/xml', // Overwrite existing
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
        $this->assertEquals('2.0', $processed->headers->get('X-API-Version'));
        $this->assertEquals('custom-value', $processed->headers->get('X-Custom'));
        $this->assertEquals('application/xml', $processed->headers->get('Content-Type'));
    }

    public function testProcessResponseWithSpecialCharactersInValues(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'X-Custom' => 'value with spaces',
                'X-Unicode' => 'café',
                'X-Special' => 'value@#$%^&*()',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('value with spaces', $processed->headers->get('X-Custom'));
        $this->assertEquals('café', $processed->headers->get('X-Unicode'));
        $this->assertEquals('value@#$%^&*()', $processed->headers->get('X-Special'));
    }

    public function testProcessResponseCaseInsensitiveHeaderNames(): void
    {
        $response = new Response('test content');
        $response->headers->set('content-type', 'text/plain');

        $config = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // HTTP headers are case-insensitive, so this should overwrite
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
        $this->assertEquals('application/json', $processed->headers->get('content-type'));
    }

    public function testProcessResponseWithSecurityHeaders(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-XSS-Protection' => '1; mode=block',
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('nosniff', $processed->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $processed->headers->get('X-Frame-Options'));
        $this->assertEquals('1; mode=block', $processed->headers->get('X-XSS-Protection'));
        $this->assertEquals('max-age=31536000; includeSubDomains', $processed->headers->get('Strict-Transport-Security'));
    }

    public function testProcessResponseWithCacheHeaders(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'Cache-Control' => 'public, max-age=3600',
                'ETag' => '"123456"',
                'Last-Modified' => 'Wed, 18 Sep 2024 12:00:00 GMT',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $cacheControl = $processed->headers->get('Cache-Control');
        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertEquals('"123456"', $processed->headers->get('ETag'));
        $this->assertEquals('Wed, 18 Sep 2024 12:00:00 GMT', $processed->headers->get('Last-Modified'));
    }

    public function testProcessResponseWithCorsHeaders(): void
    {
        $response = new Response('test content');

        $config = [
            'headers' => [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('*', $processed->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, PUT, DELETE', $processed->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type, Authorization', $processed->headers->get('Access-Control-Allow-Headers'));
    }

    public function testProcessResponsePreservesResponseContent(): void
    {
        $content = '{"message": "Hello, World!"}';
        $response = new Response($content, 200);
        $response->headers->set('Content-Type', 'application/json');

        $config = [
            'headers' => [
                'X-Custom' => 'custom-value',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals($content, $processed->getContent());
        $this->assertEquals(200, $processed->getStatusCode());
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
        $this->assertEquals('custom-value', $processed->headers->get('X-Custom'));
    }
}
