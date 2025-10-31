<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Header\AddHeaderMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AddHeaderMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class AddHeaderMiddlewareTest extends AbstractIntegrationTestCase
{
    private AddHeaderMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(AddHeaderMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(90, $this->middleware->getPriority());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('add_header', AddHeaderMiddleware::getServiceAlias());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->middleware->isEnabled());
    }

    public function testProcessRequestWithoutConfig(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Existing-Header', 'existing-value');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
    }

    public function testProcessRequestWithEmptyHeaders(): void
    {
        $request = Request::create('/test');
        $config = ['headers' => []];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $processed);
    }

    public function testProcessRequestAddSingleHeader(): void
    {
        $request = Request::create('/test');
        $config = [
            'headers' => [
                'X-API-Key' => 'secret-key',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('secret-key', $processed->headers->get('X-API-Key'));
    }

    public function testProcessRequestAddMultipleHeaders(): void
    {
        $request = Request::create('/test');
        $config = [
            'headers' => [
                'X-API-Key' => 'secret-key',
                'X-Client-ID' => '12345',
                'X-Version' => '2.0',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('secret-key', $processed->headers->get('X-API-Key'));
        $this->assertEquals('12345', $processed->headers->get('X-Client-ID'));
        $this->assertEquals('2.0', $processed->headers->get('X-Version'));
    }

    public function testProcessRequestOverwriteExistingHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'Bearer old-token');

        $config = [
            'headers' => [
                'Authorization' => 'Bearer new-token',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('Bearer new-token', $processed->headers->get('Authorization'));
    }

    public function testProcessRequestWithEmptyHeaderValue(): void
    {
        $request = Request::create('/test');
        $config = [
            'headers' => [
                'X-Empty' => '',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('', $processed->headers->get('X-Empty'));
    }

    public function testProcessRequestWithNullHeaderValue(): void
    {
        $request = Request::create('/test');
        $config = [
            'headers' => [
                'X-Null' => null,
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->headers->get('X-Null'));
    }

    public function testProcessRequestMixedWithExistingHeaders(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Existing-Header', 'existing-value');
        $request->headers->set('Content-Type', 'application/json');

        $config = [
            'headers' => [
                'X-API-Key' => 'secret-key',
                'X-Version' => '1.0',
                'Content-Type' => 'text/plain', // Overwrite existing
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('existing-value', $processed->headers->get('Existing-Header'));
        $this->assertEquals('secret-key', $processed->headers->get('X-API-Key'));
        $this->assertEquals('1.0', $processed->headers->get('X-Version'));
        $this->assertEquals('text/plain', $processed->headers->get('Content-Type'));
    }

    public function testProcessRequestWithSpecialCharactersInValues(): void
    {
        $request = Request::create('/test');
        $config = [
            'headers' => [
                'X-Custom' => 'value with spaces',
                'X-Unicode' => 'café',
                'X-Special' => 'value@#$%^&*()',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value with spaces', $processed->headers->get('X-Custom'));
        $this->assertEquals('café', $processed->headers->get('X-Unicode'));
        $this->assertEquals('value@#$%^&*()', $processed->headers->get('X-Special'));
    }

    public function testProcessRequestCaseInsensitiveHeaderNames(): void
    {
        $request = Request::create('/test');
        $request->headers->set('content-type', 'application/json');

        $config = [
            'headers' => [
                'Content-Type' => 'text/plain',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // HTTP headers are case-insensitive, so this should overwrite
        $this->assertEquals('text/plain', $processed->headers->get('Content-Type'));
        $this->assertEquals('text/plain', $processed->headers->get('content-type'));
    }
}
