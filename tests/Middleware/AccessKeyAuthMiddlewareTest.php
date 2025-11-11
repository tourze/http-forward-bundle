<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\HttpForwardBundle\Constant\RequestAttributes;
use Tourze\HttpForwardBundle\DTO\AuthorizationResult;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AccessKeyAuthMiddleware;
use Tourze\HttpForwardBundle\Service\AccessKeyAuthService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * AccessKeyAuthMiddleware 的单元测试。
 * 使用 Mock 对象隔离依赖,专注测试中间件的业务逻辑。
 *
 * @internal
 */
#[CoversClass(AccessKeyAuthMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class AccessKeyAuthMiddlewareTest extends AbstractIntegrationTestCase
{
    private AccessKeyAuthMiddleware $middleware;

    /** @var ForwardLog&MockObject */
    private ForwardLog $forwardLog;

    /** @var AccessKeyAuthService&MockObject */
    private AccessKeyAuthService $authService;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function onSetUp(): void
    {
        // For integration tests, we get the service from container if needed
        // But this test still uses mocks for unit testing style

        /** @var AccessKeyAuthService&MockObject $authService */
        $authService = $this->createMock(AccessKeyAuthService::class);
        $this->authService = $authService;

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        /** @var ForwardLog&MockObject $forwardLog */
        $forwardLog = $this->createMock(ForwardLog::class);
        $this->forwardLog = $forwardLog;

        // Create middleware instance with mock dependencies for unit testing
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        $this->middleware = new AccessKeyAuthMiddleware($this->authService, $this->logger);
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('access_key_auth', AccessKeyAuthMiddleware::getServiceAlias());
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(200, $this->middleware->getPriority());
    }

    public function testProcessRequestWithDisabledAuth(): void
    {
        $request = Request::create('/', 'GET');
        $config = ['enabled' => false];

        $result = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $result);
        $this->assertNull($request->attributes->get(RequestAttributes::AUTH_RESULT));
    }

    public function testProcessRequestWithoutTokenAndNotRequired(): void
    {
        $request = Request::create('/', 'GET');
        $config = [
            'enabled' => true,
            'required' => false,
        ];

        $result = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $result);
        $this->assertNull($request->attributes->get(RequestAttributes::AUTH_RESULT));
    }

    public function testProcessRequestWithoutTokenAndRequired(): void
    {
        $request = Request::create('/', 'GET');
        $config = [
            'enabled' => true,
            'required' => true,
        ];

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Missing Authorization header', [
                'path' => '/',
                'method' => 'GET',
            ])
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Authorization header is required');
        $this->expectExceptionCode(401);

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testProcessRequestWithValidBearerToken(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-app-id');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $config = ['enabled' => true, 'required' => true];

        $accessKey = $this->createMock(AccessKey::class);
        $authResult = AuthorizationResult::success($accessKey);

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->with('test-app-id', '192.168.1.100')
            ->willReturn($authResult)
        ;

        $this->forwardLog
            ->expects($this->once())
            ->method('setAccessKey')
            ->with($accessKey)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Authorization successful', [
                'appId' => 'test-app-id',
                'clientIp' => '192.168.1.100',
            ])
        ;

        $result = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $result);
        $this->assertEquals($authResult, $request->attributes->get(RequestAttributes::AUTH_RESULT));
        $this->assertEquals($accessKey, $request->attributes->get(RequestAttributes::ACCESS_KEY));
        $this->assertEquals('192.168.1.100', $request->attributes->get(RequestAttributes::CLIENT_IP));
    }

    public function testProcessRequestWithAuthorizationFailure(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $config = ['enabled' => true, 'required' => true];

        $authResult = AuthorizationResult::failure('INVALID_TOKEN', 'Invalid access key');

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->with('invalid-token', '192.168.1.100')
            ->willReturn($authResult)
        ;

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Authorization failed', [
                'appId' => 'invalid-token',
                'clientIp' => '192.168.1.100',
                'errorCode' => 'INVALID_TOKEN',
                'errorMessage' => 'Invalid access key',
            ])
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid access key');
        $this->expectExceptionCode(401);

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testProcessRequestWithPermissiveFallbackMode(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $config = [
            'enabled' => true,
            'required' => true,
            'fallback_mode' => 'permissive',
        ];

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->willThrowException(new \RuntimeException('Service unavailable'))
        ;

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Authorization failed but using permissive fallback', [
                'error' => 'Service unavailable',
                'path' => '/',
            ])
        ;

        $result = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $result);
    }

    public function testProcessRequestWithStrictFallbackMode(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');

        $config = [
            'enabled' => true,
            'required' => true,
            'fallback_mode' => 'strict',
        ];

        $exception = new \RuntimeException('Database connection failed');

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->willThrowException($exception)
        ;

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Authorization failed in strict mode', [
                'error' => 'Database connection failed',
                'path' => '/',
            ])
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testExtractBearerTokenFromAuthorizationHeader(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token-123');

        $config = ['enabled' => true, 'required' => false];

        $accessKey = $this->createMock(AccessKey::class);
        $authResult = AuthorizationResult::success($accessKey);

        // Request::create() 默认会设置 REMOTE_ADDR 为 127.0.0.1
        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->with('test-token-123', '127.0.0.1')
            ->willReturn($authResult)
        ;

        $this->forwardLog
            ->expects($this->once())
            ->method('setAccessKey')
            ->with($accessKey)
        ;

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testExtractBearerTokenWithEmptyValue(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer ');

        $config = ['enabled' => true, 'required' => false];

        $result = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $result);
    }

    public function testExtractBearerTokenWithNonBearerScheme(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $config = ['enabled' => true, 'required' => false];

        $result = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertSame($request, $result);
    }

    public function testGetClientIpFromXForwardedFor(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');
        $request->headers->set('X-Forwarded-For', '203.0.113.1, 192.168.1.1');

        $config = ['enabled' => true, 'required' => true];

        $accessKey = $this->createMock(AccessKey::class);
        $authResult = AuthorizationResult::success($accessKey);

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->with('test-token', '203.0.113.1')
            ->willReturn($authResult)
        ;

        $this->forwardLog
            ->expects($this->once())
            ->method('setAccessKey')
            ->with($accessKey)
        ;

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testGetClientIpFromXRealIp(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');
        $request->headers->set('X-Real-IP', '203.0.113.2');

        $config = ['enabled' => true, 'required' => true];

        $accessKey = $this->createMock(AccessKey::class);
        $authResult = AuthorizationResult::success($accessKey);

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->with('test-token', '203.0.113.2')
            ->willReturn($authResult)
        ;

        $this->forwardLog
            ->expects($this->once())
            ->method('setAccessKey')
            ->with($accessKey)
        ;

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testGetClientIpFromRemoteAddr(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $config = ['enabled' => true, 'required' => true];

        $accessKey = $this->createMock(AccessKey::class);
        $authResult = AuthorizationResult::success($accessKey);

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->with('test-token', '192.168.1.100')
            ->willReturn($authResult)
        ;

        $this->forwardLog
            ->expects($this->once())
            ->method('setAccessKey')
            ->with($accessKey)
        ;

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testGetHttpStatusCodeForInvalidToken(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid');

        $config = ['enabled' => true, 'required' => true];

        $authResult = AuthorizationResult::failure('INVALID_TOKEN', 'Invalid token');

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->willReturn($authResult)
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testGetHttpStatusCodeForInactiveKey(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer inactive');

        $config = ['enabled' => true, 'required' => true];

        $authResult = AuthorizationResult::failure('INACTIVE_KEY', 'Key is inactive');

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->willReturn($authResult)
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testGetHttpStatusCodeForIpDenied(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');

        $config = ['enabled' => true, 'required' => true];

        $authResult = AuthorizationResult::failure('IP_DENIED', 'IP not allowed');

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->willReturn($authResult)
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testGetHttpStatusCodeForUnknownError(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');

        $config = ['enabled' => true, 'required' => true];

        $authResult = AuthorizationResult::failure('UNKNOWN_ERROR', 'Unknown error');

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->willReturn($authResult)
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(500);

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testProcessRequestWithEmptyConfig(): void
    {
        $request = Request::create('/', 'GET');
        $config = [];

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Missing Authorization header')
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Authorization header is required');

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }

    public function testProcessRequestSetsClientIpAttribute(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');
        $request->headers->set('X-Real-IP', '203.0.113.5');

        $config = ['enabled' => true, 'required' => true];

        $accessKey = $this->createMock(AccessKey::class);
        $authResult = AuthorizationResult::success($accessKey);

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->with('test-token', '203.0.113.5')
            ->willReturn($authResult)
        ;

        $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('203.0.113.5', $request->attributes->get(RequestAttributes::CLIENT_IP));
    }

    public function testProcessRequestUpdatesForwardLogWithAccessKey(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');

        $config = ['enabled' => true, 'required' => true];

        $accessKey = $this->createMock(AccessKey::class);
        $authResult = AuthorizationResult::success($accessKey);

        $this->authService
            ->expects($this->once())
            ->method('authorize')
            ->willReturn($authResult)
        ;

        $this->forwardLog
            ->expects($this->once())
            ->method('setAccessKey')
            ->with($accessKey)
        ;

        $this->middleware->processRequest($request, $this->forwardLog, $config);
    }
}
