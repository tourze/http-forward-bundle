<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Middleware\Builtin\RetryMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RetryMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class RetryMiddlewareTest extends AbstractIntegrationTestCase
{
    private RetryMiddleware $middleware;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(RetryMiddleware::class);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(10, $this->middleware->getPriority());
    }

    public function testProcessResponseWithSuccessfulStatusCode(): void
    {
        $response = new Response('success', 200);
        $config = [];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertSame($response, $processed);
        $this->assertEquals(200, $processed->getStatusCode());
        $this->assertFalse($processed->headers->has('X-Retry-Count'));
        $this->assertFalse($processed->headers->has('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithDefaultRetryableStatusCode(): void
    {
        $testCases = [429, 500, 502, 503, 504];

        foreach ($testCases as $statusCode) {
            $response = new Response('error', $statusCode);
            $processed = $this->middleware->processResponse($response, []);

            $this->assertEquals('1', $processed->headers->get('X-Retry-Count'));
            $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
            $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
        }
    }

    public function testProcessResponseWithCustomRetryableStatusCodes(): void
    {
        $response = new Response('error', 400);
        $config = [
            'retryable_status_codes' => [400, 401, 403],
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('1', $processed->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithNonRetryableStatusCode(): void
    {
        $response = new Response('not found', 404);
        $config = [];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertSame($response, $processed);
        $this->assertEquals(404, $processed->getStatusCode());
        $this->assertFalse($processed->headers->has('X-Retry-Count'));
        $this->assertFalse($processed->headers->has('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithExistingRetryCount(): void
    {
        $response = new Response('error', 500);
        $response->headers->set('X-Retry-Count', '2');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertEquals('3', $processed->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithMaxRetriesReached(): void
    {
        $response = new Response('error', 500);
        $response->headers->set('X-Retry-Count', '3');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertEquals('3', $processed->headers->get('X-Retry-Count'));
        $this->assertFalse($processed->headers->has('X-Should-Retry'));
        $this->assertEquals('true', $processed->headers->get('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithCustomMaxRetries(): void
    {
        $response = new Response('error', 502);
        $response->headers->set('X-Retry-Count', '4');
        $config = [
            'max_retries' => 5,
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('5', $processed->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithCustomMaxRetriesReached(): void
    {
        $response = new Response('error', 502);
        $response->headers->set('X-Retry-Count', '5');
        $config = [
            'max_retries' => 5,
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('5', $processed->headers->get('X-Retry-Count'));
        $this->assertFalse($processed->headers->has('X-Should-Retry'));
        $this->assertEquals('true', $processed->headers->get('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithCustomRetryHeader(): void
    {
        $response = new Response('error', 500);
        $response->headers->set('X-Custom-Retry', '1');
        $config = [
            'retry_header' => 'X-Custom-Retry',
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('2', $processed->headers->get('X-Custom-Retry'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Retry-Count'));
    }

    public function testProcessResponseWithZeroMaxRetries(): void
    {
        $response = new Response('error', 500);
        $config = [
            'max_retries' => 0,
        ];

        $processed = $this->middleware->processResponse($response, $config);

        // With max_retries = 0, current retries (0) is not < max_retries (0)
        // So it goes to the else branch and sets max retries reached
        $this->assertFalse($processed->headers->has('X-Retry-Count'));
        $this->assertFalse($processed->headers->has('X-Should-Retry'));
        $this->assertEquals('true', $processed->headers->get('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithInvalidRetryCountHeader(): void
    {
        $response = new Response('error', 500);
        $response->headers->set('X-Retry-Count', 'invalid');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertEquals('1', $processed->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithNegativeRetryCount(): void
    {
        $response = new Response('error', 500);
        $response->headers->set('X-Retry-Count', '-1');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertEquals('0', $processed->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
    }

    public function testProcessResponseRetrySequence(): void
    {
        $response = new Response('error', 503);

        // First attempt - no retry count header
        $attempt1 = $this->middleware->processResponse($response, []);
        $this->assertEquals('1', $attempt1->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $attempt1->headers->get('X-Should-Retry'));
        $this->assertFalse($attempt1->headers->has('X-Max-Retries-Reached'));

        // Second attempt - increment retry count
        $response->headers->set('X-Retry-Count', '1');
        $attempt2 = $this->middleware->processResponse($response, []);
        $this->assertEquals('2', $attempt2->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $attempt2->headers->get('X-Should-Retry'));
        $this->assertFalse($attempt2->headers->has('X-Max-Retries-Reached'));

        // Third attempt - increment retry count
        $response->headers->set('X-Retry-Count', '2');
        $attempt3 = $this->middleware->processResponse($response, []);
        $this->assertEquals('3', $attempt3->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $attempt3->headers->get('X-Should-Retry'));
        $this->assertFalse($attempt3->headers->has('X-Max-Retries-Reached'));

        // Fourth attempt - max retries reached
        $response->headers->set('X-Retry-Count', '3');
        $attempt4 = $this->middleware->processResponse($response, []);
        $this->assertEquals('3', $attempt4->headers->get('X-Retry-Count'));
        // X-Should-Retry is still there from previous attempts, it's not removed
        $this->assertEquals('true', $attempt4->headers->get('X-Should-Retry'));
        $this->assertEquals('true', $attempt4->headers->get('X-Max-Retries-Reached'));
    }

    public function testProcessResponseWithAllConfigOptions(): void
    {
        $response = new Response('gateway error', 502);
        $response->headers->set('X-Custom-Retry', '1');

        $config = [
            'retryable_status_codes' => [502, 503, 504],
            'max_retries' => 5,
            'retry_header' => 'X-Custom-Retry',
        ];

        $processed = $this->middleware->processResponse($response, $config);

        $this->assertEquals('2', $processed->headers->get('X-Custom-Retry'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
        $this->assertFalse($processed->headers->has('X-Max-Retries-Reached'));
        $this->assertFalse($processed->headers->has('X-Retry-Count'));
    }

    public function testProcessResponsePreservesExistingHeaders(): void
    {
        $response = new Response('error', 500);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Custom', 'preserve-me');
        $response->headers->set('Authorization', 'Bearer token');

        $processed = $this->middleware->processResponse($response, []);

        $this->assertEquals('application/json', $processed->headers->get('Content-Type'));
        $this->assertEquals('preserve-me', $processed->headers->get('X-Custom'));
        $this->assertEquals('Bearer token', $processed->headers->get('Authorization'));
        $this->assertEquals('1', $processed->headers->get('X-Retry-Count'));
        $this->assertEquals('true', $processed->headers->get('X-Should-Retry'));
    }
}
