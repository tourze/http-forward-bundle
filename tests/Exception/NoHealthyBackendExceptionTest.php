<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HttpForwardBundle\Exception\NoHealthyBackendException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(NoHealthyBackendException::class)]
final class NoHealthyBackendExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCreation(): void
    {
        $message = 'No healthy backend servers available';
        $code = 503;
        $previous = new \RuntimeException('Previous exception');

        $exception = new NoHealthyBackendException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithDefaults(): void
    {
        $message = 'No healthy backend servers available';
        $exception = new NoHealthyBackendException($message);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithEmptyMessage(): void
    {
        $exception = new NoHealthyBackendException('');

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionInheritance(): void
    {
        $exception = new NoHealthyBackendException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionWithCustomCode(): void
    {
        $message = 'Backend service unavailable';
        $code = 502;

        $exception = new NoHealthyBackendException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \InvalidArgumentException('Invalid configuration');
        $intermediateCause = new \RuntimeException('Service initialization failed', 0, $rootCause);
        $finalException = new NoHealthyBackendException('No healthy backends', 503, $intermediateCause);

        $this->assertEquals('No healthy backends', $finalException->getMessage());
        $this->assertEquals(503, $finalException->getCode());
        $this->assertSame($intermediateCause, $finalException->getPrevious());
        $this->assertSame($rootCause, $finalException->getPrevious()->getPrevious());
    }
}
