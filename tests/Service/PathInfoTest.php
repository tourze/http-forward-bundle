<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\HttpForwardBundle\Service\PathInfo;

/**
 * @internal
 */
#[CoversClass(PathInfo::class)]
final class PathInfoTest extends TestCase
{
    public function testGetOriginalPath(): void
    {
        $pathInfo = new PathInfo('/api/users/123');

        $this->assertSame('/api/users/123', $pathInfo->getOriginalPath());
    }

    public function testStripStringPrefix(): void
    {
        $pathInfo = new PathInfo('/api/users/123');

        $result = $pathInfo->stripPrefix('/api');

        $this->assertSame('/users/123', $result);
    }

    public function testStripRegexPrefix(): void
    {
        $pathInfo = new PathInfo('/api/v1/users/123');

        $result = $pathInfo->stripPrefix('^\/api\/v\d+');

        $this->assertSame('/users/123', $result);
    }

    public function testExtractParametersWithoutParameters(): void
    {
        $pathInfo = new PathInfo('/api/users');

        $result = $pathInfo->extractParameters('/api/users');

        $this->assertSame([], $result);
    }

    public function testExtractParametersWithParameters(): void
    {
        $pathInfo = new PathInfo('/api/users/123');

        $result = $pathInfo->extractParameters('/api/users/{id}');

        $this->assertSame(['id' => '123'], $result);
    }

    public function testExtractMultipleParameters(): void
    {
        $pathInfo = new PathInfo('/api/users/123/posts/456');

        $result = $pathInfo->extractParameters('/api/users/{userId}/posts/{postId}');

        $this->assertSame([
            'userId' => '123',
            'postId' => '456',
        ], $result);
    }

    public function testExtractParametersWithNoMatch(): void
    {
        $pathInfo = new PathInfo('/api/users');

        $result = $pathInfo->extractParameters('/api/posts/{id}');

        $this->assertSame([], $result);
    }

    public function testStripPrefixMethod(): void
    {
        $pathInfo = new PathInfo('/api/v1/users/123');

        $result1 = $pathInfo->stripPrefix('/api');
        $result2 = $pathInfo->stripPrefix('^\/api\/v\d+');

        $this->assertSame('/v1/users/123', $result1);
        $this->assertSame('/users/123', $result2);
    }
}
