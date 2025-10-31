<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Service\HeaderBuilder;

/**
 * @internal
 */
#[CoversClass(HeaderBuilder::class)]
final class HeaderBuilderTest extends TestCase
{
    private HeaderBuilder $headerBuilder;

    protected function setUp(): void
    {
        $this->headerBuilder = new HeaderBuilder();
    }

    public function testBuildForwardHeaders(): void
    {
        $request = Request::create('https://example.com/api', 'GET');
        $request->headers->set('Authorization', 'Bearer token123');
        $request->headers->set('User-Agent', 'TestClient/1.0');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Host', 'example.com');
        $request->headers->set('Content-Length', '100');
        $request->headers->set('Accept-Encoding', 'gzip, deflate');

        $headers = $this->headerBuilder->buildForwardHeaders($request);

        $this->assertArrayHasKey('authorization', $headers);
        $this->assertArrayHasKey('user-agent', $headers);
        $this->assertArrayHasKey('accept', $headers);
        $this->assertArrayHasKey('Accept-Encoding', $headers);

        $this->assertArrayNotHasKey('host', $headers);
        $this->assertArrayNotHasKey('content-length', $headers);

        $this->assertSame('Bearer token123', $headers['authorization']);
        $this->assertSame('TestClient/1.0', $headers['user-agent']);
        $this->assertSame('application/json', $headers['accept']);
        $this->assertSame('identity', $headers['Accept-Encoding']);
    }

    public function testBuildForwardHeadersWithClientIp(): void
    {
        $request = Request::create('https://example.com/api', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        $headers = $this->headerBuilder->buildForwardHeaders($request);

        // HeaderBuilder doesn't add X-Real-IP and X-Forwarded-For headers automatically
        // It only processes existing headers from the request
        $this->assertArrayHasKey('Accept-Encoding', $headers);
        $this->assertSame('identity', $headers['Accept-Encoding']);
    }

    public function testBuildForwardHeadersWithLocalIp(): void
    {
        $request = Request::create('https://example.com/api', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $headers = $this->headerBuilder->buildForwardHeaders($request);

        // HeaderBuilder doesn't add IP-related headers automatically
        $this->assertArrayHasKey('Accept-Encoding', $headers);
        $this->assertSame('identity', $headers['Accept-Encoding']);
    }

    public function testBuildForwardHeadersSkipsSpecificHeaders(): void
    {
        $request = Request::create('https://example.com/api', 'GET');
        $request->headers->set('Host', 'example.com');
        $request->headers->set('Content-Length', '200');
        $request->headers->set('Accept-Encoding', 'gzip');

        $headers = $this->headerBuilder->buildForwardHeaders($request);

        $this->assertArrayNotHasKey('host', $headers);
        $this->assertArrayNotHasKey('content-length', $headers);
        $this->assertSame('identity', $headers['Accept-Encoding']);
    }

    public function testBuildForwardHeadersWithEmptyHeaders(): void
    {
        $request = Request::create('https://example.com/api', 'GET');

        $headers = $this->headerBuilder->buildForwardHeaders($request);

        $this->assertArrayHasKey('Accept-Encoding', $headers);
        $this->assertSame('identity', $headers['Accept-Encoding']);

        // Request may have some default headers, but Accept-Encoding is always set to 'identity'
        $this->assertGreaterThanOrEqual(1, count($headers));
    }

    public function testBuildForwardHeadersFromArrayWithNormalHeaders(): void
    {
        $headerArray = [
            'Authorization' => 'Bearer token123',
            'User-Agent' => 'TestClient/1.0',
            'Accept' => 'application/json',
            'Custom-Header' => 'custom-value',
        ];

        $headers = $this->headerBuilder->buildForwardHeadersFromArray($headerArray);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('Custom-Header', $headers);
        $this->assertArrayHasKey('Accept-Encoding', $headers);

        $this->assertSame('Bearer token123', $headers['Authorization']);
        $this->assertSame('TestClient/1.0', $headers['User-Agent']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('custom-value', $headers['Custom-Header']);
        $this->assertSame('identity', $headers['Accept-Encoding']);
    }

    public function testBuildForwardHeadersFromArraySkipsSpecificHeaders(): void
    {
        $headerArray = [
            'Host' => 'example.com',
            'Content-Length' => '200',
            'Accept-Encoding' => 'gzip, deflate',
            'Authorization' => 'Bearer token123',
        ];

        $headers = $this->headerBuilder->buildForwardHeadersFromArray($headerArray);

        $this->assertArrayNotHasKey('Host', $headers);
        $this->assertArrayNotHasKey('Content-Length', $headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertArrayHasKey('Accept-Encoding', $headers);

        $this->assertSame('Bearer token123', $headers['Authorization']);
        $this->assertSame('identity', $headers['Accept-Encoding']);
    }

    public function testBuildForwardHeadersFromArrayWithArrayValues(): void
    {
        $headerArray = [
            'Accept' => ['application/json', 'text/html'],
            'Cache-Control' => ['no-cache', 'no-store'],
            'Authorization' => 'Bearer token123',
        ];

        $headers = $this->headerBuilder->buildForwardHeadersFromArray($headerArray);

        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertArrayHasKey('Authorization', $headers);

        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('no-cache', $headers['Cache-Control']);
        $this->assertSame('Bearer token123', $headers['Authorization']);
    }

    public function testBuildForwardHeadersFromArrayWithEmptyArray(): void
    {
        $headerArray = [];

        $headers = $this->headerBuilder->buildForwardHeadersFromArray($headerArray);

        $this->assertArrayHasKey('Accept-Encoding', $headers);
        $this->assertSame('identity', $headers['Accept-Encoding']);
        $this->assertCount(1, $headers);
    }
}
