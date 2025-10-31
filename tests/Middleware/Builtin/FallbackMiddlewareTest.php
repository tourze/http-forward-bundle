<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\Builtin\FallbackMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(FallbackMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class FallbackMiddlewareTest extends AbstractIntegrationTestCase
{
    private FallbackMiddleware $middleware;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(FallbackMiddleware::class);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(5, $this->middleware->getPriority());
    }

    public function testProcessResponseWithSuccessfulResponse(): void
    {
        $response = new Response('success', 200);
        $config = [];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertSame($response, $processed);
        $this->assertEquals('success', $processed->getContent());
        $this->assertEquals(200, $processed->getStatusCode());
        $this->assertFalse($processed->headers->has('X-Fallback'));
    }

    public function testProcessResponseWithDefaultFallbackStatusCodes(): void
    {
        $response = new Response('Internal Server Error', 500);
        $config = [];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertNotSame($response, $processed);
        $this->assertEquals('Service temporarily unavailable', $processed->getContent());
        $this->assertEquals(503, $processed->getStatusCode());
        $this->assertEquals('true', $processed->headers->get('X-Fallback'));
    }

    public function testProcessResponseWithCustomFallbackConfig(): void
    {
        $response = new Response('Gateway Error', 502);
        $config = [
            'fallback_status_codes' => [502, 503],
            'fallback_content' => 'Custom fallback message',
            'fallback_status' => 200,
            'fallback_headers' => ['X-Custom' => 'fallback'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Custom fallback message', $processed->getContent());
        $this->assertEquals(200, $processed->getStatusCode());
        $this->assertEquals('true', $processed->headers->get('X-Fallback'));
        $this->assertEquals('fallback', $processed->headers->get('X-Custom'));
    }

    public function testProcessResponseWithAllDefaultFallbackCodes(): void
    {
        $testCases = [
            ['status' => 500, 'content' => 'Server Error'],
            ['status' => 502, 'content' => 'Bad Gateway'],
            ['status' => 503, 'content' => 'Service Unavailable'],
            ['status' => 504, 'content' => 'Gateway Timeout'],
        ];

        foreach ($testCases as $testCase) {
            $response = new Response($testCase['content'], $testCase['status']);
            $processed = $this->middleware->processResponse($response, []);

            $this->assertEquals('Service temporarily unavailable', $processed->getContent());
            $this->assertEquals(503, $processed->getStatusCode());
            $this->assertEquals('true', $processed->headers->get('X-Fallback'));
        }
    }

    public function testProcessResponseWithNonFallbackStatusCode(): void
    {
        $response = new Response('Client Error', 400);
        $config = [];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertSame($response, $processed);
        $this->assertEquals('Client Error', $processed->getContent());
        $this->assertEquals(400, $processed->getStatusCode());
        $this->assertFalse($processed->headers->has('X-Fallback'));
    }

    public function testProcessResponseWithPreserveOriginalHeaders(): void
    {
        $response = new Response('Error', 500);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Original', 'true');

        $config = [
            'preserve_original_headers' => true,
            'fallback_headers' => ['X-Custom' => 'fallback'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Service temporarily unavailable', $processed->getContent());
        $this->assertEquals(503, $processed->getStatusCode());
        $this->assertEquals('true', $processed->headers->get('X-Fallback'));
        $this->assertEquals('fallback', $processed->headers->get('X-Custom'));
        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
        $this->assertEquals('true', $processed->headers->get('X-Original'));
    }

    public function testProcessResponseWithoutPreserveOriginalHeaders(): void
    {
        $response = new Response('Error', 500);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Original', 'true');

        $config = [
            'preserve_original_headers' => false,
            'fallback_headers' => ['X-Custom' => 'fallback'],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Service temporarily unavailable', $processed->getContent());
        $this->assertEquals(503, $processed->getStatusCode());
        $this->assertEquals('true', $processed->headers->get('X-Fallback'));
        $this->assertEquals('fallback', $processed->headers->get('X-Custom'));
        $this->assertNull($processed->headers->get('Content-Type'));
        $this->assertNull($processed->headers->get('X-Original'));
    }

    public function testProcessResponseWithFallbackHeaderPrecedence(): void
    {
        $response = new Response('Error', 500);
        $response->headers->set('X-Fallback', 'original');

        $config = [
            'preserve_original_headers' => true,
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // Fallback middleware header should take precedence
        $this->assertEquals('true', $processed->headers->get('X-Fallback'));
    }

    public function testProcessResponseWithEmptyFallbackContent(): void
    {
        $response = new Response('Error', 500);
        $config = [
            'fallback_content' => '',
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('', $processed->getContent());
        $this->assertEquals(503, $processed->getStatusCode());
        $this->assertEquals('true', $processed->headers->get('X-Fallback'));
    }

    public function testProcessResponseWithCustomStatusCodeList(): void
    {
        $response1 = new Response('Error', 400);
        $response2 = new Response('Error', 500);

        $config = [
            'fallback_status_codes' => [400, 401, 403],
        ];

        $processed1 = $this->middleware->processResponse($response1, $config);
        $processed2 = $this->middleware->processResponse($response2, $config);

        // 400 should trigger fallback
        $this->assertEquals('Service temporarily unavailable', $processed1->getContent());
        $this->assertEquals(503, $processed1->getStatusCode());
        $this->assertEquals('true', $processed1->headers->get('X-Fallback'));

        // 500 should not trigger fallback (not in custom list)
        $this->assertSame($response2, $processed2);
        $this->assertEquals('Error', $processed2->getContent());
        $this->assertEquals(500, $processed2->getStatusCode());
        $this->assertFalse($processed2->headers->has('X-Fallback'));
    }

    public function testProcessResponseWithComplexHeaders(): void
    {
        $response = new Response('Error', 502);
        $response->headers->set('Authorization', 'Bearer token');
        $response->headers->set('Content-Length', '100');

        $config = [
            'preserve_original_headers' => true,
            'fallback_headers' => [
                'X-Retry-After' => '30',
                'X-Service-Status' => 'maintenance',
            ],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('Service temporarily unavailable', $processed->getContent());
        $this->assertEquals(503, $processed->getStatusCode());
        $this->assertEquals('true', $processed->headers->get('X-Fallback'));
        $this->assertEquals('30', $processed->headers->get('X-Retry-After'));
        $this->assertEquals('maintenance', $processed->headers->get('X-Service-Status'));
        $this->assertEquals('Bearer token', $processed->headers->get('Authorization'));
        $this->assertEquals('100', $processed->headers->get('Content-Length'));
    }
}
