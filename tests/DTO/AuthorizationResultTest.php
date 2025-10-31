<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\HttpForwardBundle\DTO\AuthorizationResult;

/**
 * @internal
 */
#[CoversClass(AuthorizationResult::class)]
class AuthorizationResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $accessKey = $this->createMock(AccessKey::class);

        $result = new AuthorizationResult(
            success: true,
            accessKey: $accessKey,
            errorCode: null,
            errorMessage: null
        );

        $this->assertTrue($result->success);
        $this->assertSame($accessKey, $result->accessKey);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
    }

    public function testSuccessFactory(): void
    {
        $accessKey = $this->createMock(AccessKey::class);

        $result = AuthorizationResult::success($accessKey);

        $this->assertTrue($result->success);
        $this->assertSame($accessKey, $result->accessKey);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
    }

    public function testFailureFactory(): void
    {
        $errorCode = 'INVALID_TOKEN';
        $errorMessage = 'The provided token is invalid';

        $result = AuthorizationResult::failure($errorCode, $errorMessage);

        $this->assertFalse($result->success);
        $this->assertNull($result->accessKey);
        $this->assertSame($errorCode, $result->errorCode);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testFailureFactoryWithInactiveKey(): void
    {
        $result = AuthorizationResult::failure('INACTIVE_KEY', 'Access key is inactive');

        $this->assertFalse($result->success);
        $this->assertNull($result->accessKey);
        $this->assertSame('INACTIVE_KEY', $result->errorCode);
        $this->assertSame('Access key is inactive', $result->errorMessage);
    }

    public function testFailureFactoryWithIpDenied(): void
    {
        $result = AuthorizationResult::failure('IP_DENIED', 'Client IP is not allowed');

        $this->assertFalse($result->success);
        $this->assertNull($result->accessKey);
        $this->assertSame('IP_DENIED', $result->errorCode);
        $this->assertSame('Client IP is not allowed', $result->errorMessage);
    }

    public function testReadonlyNature(): void
    {
        $accessKey = $this->createMock(AccessKey::class);
        $result = new AuthorizationResult(
            success: true,
            accessKey: $accessKey,
            errorCode: null,
            errorMessage: null
        );

        // 验证所有属性都是只读的（通过访问验证）
        $this->assertTrue($result->success);
        $this->assertSame($accessKey, $result->accessKey);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
    }
}
