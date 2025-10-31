<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(ForwardLog::class)]
final class ForwardLogTest extends AbstractEntityTestCase
{
    public function testEntityCreation(): void
    {
        $log = new ForwardLog();

        $this->assertNull($log->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getRequestTime());
    }

    public function testSettersAndGetters(): void
    {
        $log = new ForwardLog();
        $rule = new ForwardRule();

        $log->setRule($rule);
        $log->setMethod('POST');
        $log->setPath('/api/users');
        $log->setTargetUrl('https://api.example.com/users');
        $log->setClientIp('192.168.1.1');
        $log->setUserAgent('Test Agent');
        $log->setResponseStatus(201);
        $log->setDurationMs(150);
        $log->setRetryCountUsed(1);
        $log->setFallbackUsed(false);
        $log->setErrorMessage('Connection timeout');

        $this->assertSame($rule, $log->getRule());
        $this->assertSame('POST', $log->getMethod());
        $this->assertSame('/api/users', $log->getPath());
        $this->assertSame('https://api.example.com/users', $log->getTargetUrl());
        $this->assertSame('192.168.1.1', $log->getClientIp());
        $this->assertSame('Test Agent', $log->getUserAgent());
        $this->assertSame(201, $log->getResponseStatus());
        $this->assertSame(150, $log->getDurationMs());
        $this->assertSame(1, $log->getRetryCountUsed());
        $this->assertFalse($log->isFallbackUsed());
        $this->assertSame('Connection timeout', $log->getErrorMessage());
    }

    public function testHeadersAndBody(): void
    {
        $log = new ForwardLog();

        $originalHeaders = [
            'content-type' => ['application/json'],
            'accept' => ['application/json'],
        ];

        $processedHeaders = [
            'content-type' => ['application/json'],
            'accept' => ['application/json'],
            'authorization' => ['Bearer token123'],
        ];

        $responseHeaders = [
            'content-type' => ['application/json'],
            'cache-control' => ['no-cache'],
        ];

        $log->setOriginalRequestHeaders($originalHeaders);
        $log->setProcessedRequestHeaders($processedHeaders);
        $log->setRequestBody('{"name":"test"}');
        $log->setResponseHeaders($responseHeaders);
        $log->setResponseBody('{"id":1,"name":"test"}');

        $this->assertSame($originalHeaders, $log->getOriginalRequestHeaders());
        $this->assertSame($processedHeaders, $log->getProcessedRequestHeaders());
        $this->assertSame('{"name":"test"}', $log->getRequestBody());
        $this->assertSame($responseHeaders, $log->getResponseHeaders());
        $this->assertSame('{"id":1,"name":"test"}', $log->getResponseBody());
    }

    public function testToString(): void
    {
        $log = new ForwardLog();
        $log->setMethod('GET');
        $log->setPath('/api/v1/users');
        $log->setTargetUrl('https://api.example.com/v1/users');
        $log->setResponseStatus(200);
        $log->setDurationMs(100);

        $string = (string) $log;

        $this->assertStringContainsString('GET', $string);
        $this->assertStringContainsString('/api/v1/users', $string);
        $this->assertStringContainsString('https://api.example.com/v1/users', $string);
        $this->assertStringContainsString('200', $string);
        $this->assertStringContainsString('100ms', $string);
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $rule->setSourcePath('/api/*');

        // 创建 Backend 并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);

        yield 'rule' => ['rule', $rule];
        yield 'method' => ['method', 'POST'];
        yield 'path' => ['path', '/api/users'];
        yield 'targetUrl' => ['targetUrl', 'https://api.example.com/users'];
        yield 'originalRequestHeaders' => ['originalRequestHeaders', ['content-type' => ['application/json']]];
        yield 'processedRequestHeaders' => ['processedRequestHeaders', ['content-type' => ['application/json'], 'authorization' => ['Bearer token']]];
        yield 'requestBody' => ['requestBody', '{"name":"test"}'];
        yield 'responseStatus' => ['responseStatus', 201];
        yield 'responseHeaders' => ['responseHeaders', ['content-type' => ['application/json']]];
        yield 'responseBody' => ['responseBody', '{"id":1,"name":"test"}'];
        yield 'durationMs' => ['durationMs', 150];
        yield 'retryCountUsed' => ['retryCountUsed', 1];
        yield 'fallbackUsed' => ['fallbackUsed', false];
        yield 'errorMessage' => ['errorMessage', 'Connection timeout'];
        yield 'clientIp' => ['clientIp', '192.168.1.1'];
        yield 'userAgent' => ['userAgent', 'Test Agent'];
    }

    protected function createEntity(): ForwardLog
    {
        return new ForwardLog();
    }
}
