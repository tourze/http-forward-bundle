<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Repository\ForwardLogRepository;
use Tourze\HttpForwardBundle\Service\HeaderBuilder;
use Tourze\HttpForwardBundle\Service\RequestExecutor;

/**
 * @internal
 */
#[CoversClass(RequestExecutor::class)]
final class RequestExecutorTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private HeaderBuilder $headerBuilder;

    private ForwardLogRepository $logRepository;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->headerBuilder = new HeaderBuilder();
        $this->logRepository = $this->createMock(ForwardLogRepository::class);
    }

    public function testExecuteSuccessfulRequest(): void
    {
        $request = new Request();
        $targetUrl = 'http://target.com/api/users';
        $rule = $this->createRule();
        $log = new ForwardLog();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getContent')->willReturn('{"users":[]}');
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('getHeaders')->willReturn(['Content-Type' => ['application/json']]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($httpResponse);

        $requestExecutor = new RequestExecutor($httpClient, $this->eventDispatcher, $this->headerBuilder, $this->logRepository);
        $response = $requestExecutor->execute($request, $targetUrl, $rule, $log);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"users":[]}', $response->getContent());
        $this->assertSame(0, $log->getRetryCountUsed());
    }

    public function testExecuteWithRetryAfterFailure(): void
    {
        $request = new Request();
        $targetUrl = 'http://target.com/api';
        $rule = $this->createRule();
        $log = new ForwardLog();

        $failedResponse = $this->createMock(ResponseInterface::class);
        $failedResponse->method('getContent')->willReturn('Server Error');
        $failedResponse->method('getStatusCode')->willReturn(500);
        $failedResponse->method('getHeaders')->willReturn(['Content-Type' => ['text/plain']]);

        $successResponse = $this->createMock(ResponseInterface::class);
        $successResponse->method('getContent')->willReturn('Success');
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getHeaders')->willReturn(['Content-Type' => ['text/plain']]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnOnConsecutiveCalls($failedResponse, $successResponse)
        ;

        $requestExecutor = new RequestExecutor($httpClient, $this->eventDispatcher, $this->headerBuilder, $this->logRepository);
        $response = $requestExecutor->execute($request, $targetUrl, $rule, $log);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Success', $response->getContent());
        $this->assertSame(1, $log->getRetryCountUsed());
    }

    public function testExecuteWithMaxRetriesExhausted(): void
    {
        $request = new Request();
        $targetUrl = 'http://target.com/api';
        $rule = $this->createRule();
        $rule->setRetryCount(2);
        $log = new ForwardLog();

        $failedResponse = $this->createMock(ResponseInterface::class);
        $failedResponse->method('getContent')->willReturn('Server Error');
        $failedResponse->method('getStatusCode')->willReturn(500);
        $failedResponse->method('getHeaders')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($failedResponse);

        $requestExecutor = new RequestExecutor($httpClient, $this->eventDispatcher, $this->headerBuilder, $this->logRepository);
        $response = $requestExecutor->execute($request, $targetUrl, $rule, $log);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Server Error', $response->getContent());
        $this->assertSame(2, $log->getRetryCountUsed());
    }

    public function testExecuteWithTransportException(): void
    {
        $request = new Request();
        $targetUrl = 'http://target.com/api';
        $rule = $this->createRule();
        $rule->setRetryCount(1);
        $log = new ForwardLog();

        $transportException = $this->createMock(TransportExceptionInterface::class);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException($transportException);

        $requestExecutor = new RequestExecutor($httpClient, $this->eventDispatcher, $this->headerBuilder, $this->logRepository);
        $this->expectException(TransportExceptionInterface::class);
        $requestExecutor->execute($request, $targetUrl, $rule, $log);
    }

    private function createRule(): ForwardRule
    {
        $rule = new ForwardRule();
        $rule->setRetryCount(3);
        $rule->setRetryInterval(100);
        $rule->setTimeout(30);

        return $rule;
    }
}
