<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HttpForwardBundle\Exception\MiddlewareException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(MiddlewareException::class)]
final class MiddlewareExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCreation(): void
    {
        $message = 'Test middleware exception';
        $code = 100;
        $previous = new \RuntimeException('Previous exception');

        $exception = new MiddlewareException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testExceptionWithDefaults(): void
    {
        $message = 'Test exception';
        $exception = new MiddlewareException($message);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
