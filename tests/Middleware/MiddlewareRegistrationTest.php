<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HttpForwardBundle\DependencyInjection\HttpForwardExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(HttpForwardExtension::class)]
class MiddlewareRegistrationTest extends AbstractDependencyInjectionExtensionTestCase
{
}
