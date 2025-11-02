<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\HttpForwardBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(HttpForwardBundle::class)]
#[RunTestsInSeparateProcesses]
final class HttpForwardBundleTest extends AbstractBundleTestCase
{
}
