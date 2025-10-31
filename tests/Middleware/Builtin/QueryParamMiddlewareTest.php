<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Builtin\QueryParamMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(QueryParamMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class QueryParamMiddlewareTest extends AbstractIntegrationTestCase
{
    private QueryParamMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(QueryParamMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(80, $this->middleware->getPriority());
    }

    public function testProcessRequestWithoutConfig(): void
    {
        $request = Request::create('/test?existing=value');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('value', $processed->query->get('existing'));
    }

    public function testProcessRequestAddParameters(): void
    {
        $request = Request::create('/test');
        $config = [
            'add' => [
                'api_key' => 'secret-key',
                'version' => '2.0',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('secret-key', $processed->query->get('api_key'));
        $this->assertEquals('2.0', $processed->query->get('version'));
    }

    public function testProcessRequestAddParametersDoesNotOverrideExisting(): void
    {
        $request = Request::create('/test?existing=original');
        $config = [
            'add' => [
                'existing' => 'should-not-override',
                'new_param' => 'new-value',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('original', $processed->query->get('existing'));
        $this->assertEquals('new-value', $processed->query->get('new_param'));
    }

    public function testProcessRequestRemoveParameters(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2&param3=value3');
        $config = [
            'remove' => ['param1', 'param3'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('param1'));
        $this->assertEquals('value2', $processed->query->get('param2'));
        $this->assertNull($processed->query->get('param3'));
    }

    public function testProcessRequestRemoveNonExistentParameters(): void
    {
        $request = Request::create('/test?existing=value');
        $config = [
            'remove' => ['non_existent', 'another_missing'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value', $processed->query->get('existing'));
        $this->assertNull($processed->query->get('non_existent'));
        $this->assertNull($processed->query->get('another_missing'));
    }

    public function testProcessRequestOverrideParameters(): void
    {
        $request = Request::create('/test?param1=original&param2=keep');
        $config = [
            'override' => [
                'param1' => 'overridden',
                'param3' => 'new-value',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('overridden', $processed->query->get('param1'));
        $this->assertEquals('keep', $processed->query->get('param2'));
        $this->assertEquals('new-value', $processed->query->get('param3'));
    }

    public function testProcessRequestCombinedOperations(): void
    {
        $request = Request::create('/test?existing=original&remove_me=bye&override_me=old');
        $config = [
            'add' => [
                'new_param' => 'new-value',
                'existing' => 'should-not-add', // Should not override existing
            ],
            'remove' => ['remove_me'],
            'override' => [
                'override_me' => 'new-value',
                'another_override' => 'another-value',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Added parameter (not overriding existing)
        $this->assertEquals('new-value', $processed->query->get('new_param'));
        $this->assertEquals('original', $processed->query->get('existing'));

        // Removed parameter
        $this->assertNull($processed->query->get('remove_me'));

        // Overridden parameters
        $this->assertEquals('new-value', $processed->query->get('override_me'));
        $this->assertEquals('another-value', $processed->query->get('another_override'));
    }

    public function testProcessRequestWithArrayParameters(): void
    {
        $request = Request::create('/test?tags[]=tag1&tags[]=tag2');
        $config = [
            'add' => [
                'status' => 'active',
                'simple' => 'value',
            ],
            'remove' => ['tags'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('tags'));
        $this->assertEquals('active', $processed->query->get('status'));
        $this->assertEquals('value', $processed->query->get('simple'));
    }

    public function testProcessRequestWithSpecialCharacters(): void
    {
        $request = Request::create('/test');
        $config = [
            'add' => [
                'special_chars' => 'value with spaces & symbols!',
                'encoded' => 'user@domain.com',
                'unicode' => '测试值',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value with spaces & symbols!', $processed->query->get('special_chars'));
        $this->assertEquals('user@domain.com', $processed->query->get('encoded'));
        $this->assertEquals('测试值', $processed->query->get('unicode'));
    }

    public function testProcessRequestWithEmptyValues(): void
    {
        $request = Request::create('/test?existing=');
        $config = [
            'add' => [
                'empty_add' => '',
                'null_add' => null,
            ],
            'override' => [
                'existing' => '',
                'override_null' => null,
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('', $processed->query->get('empty_add'));
        $this->assertNull($processed->query->get('null_add'));
        $this->assertEquals('', $processed->query->get('existing'));
        $this->assertNull($processed->query->get('override_null'));
    }

    public function testProcessRequestWithNumericKeys(): void
    {
        $request = Request::create('/test');
        $config = [
            'add' => [
                '0' => 'zero',
                '123' => 'numeric-key',
            ],
            'override' => [
                '456' => 'another-numeric',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('zero', $processed->query->get('0'));
        $this->assertEquals('numeric-key', $processed->query->get('123'));
        $this->assertEquals('another-numeric', $processed->query->get('456'));
    }

    public function testProcessRequestOrderOfOperations(): void
    {
        $request = Request::create('/test?test=original');
        $config = [
            'add' => [
                'test' => 'from-add',  // Should not override existing
            ],
            'remove' => ['test'],     // Should remove the original
            'override' => [
                'test' => 'from-override',  // Should add after removal
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // The order is: add (no effect), remove (removes original), override (adds new)
        $this->assertEquals('from-override', $processed->query->get('test'));
    }

    public function testProcessRequestWithComplexQueryString(): void
    {
        $queryString = 'page=1&limit=20&sort[field]=name&sort[order]=asc&filters[status]=active&filters[type]=premium';
        $request = Request::create("/test?{$queryString}");

        $config = [
            'add' => [
                'api_key' => 'secret',
                'page' => '999', // Should not override existing page=1
            ],
            'remove' => ['sort'],
            'override' => [
                'limit' => '50',
                'new_filter' => 'added',
            ],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Original values
        $this->assertEquals('1', $processed->query->get('page')); // Not overridden by add
        $filters = $processed->query->all('filters');
        $this->assertEquals('active', $filters['status'] ?? null);
        $this->assertEquals('premium', $filters['type'] ?? null);

        // Added parameter
        $this->assertEquals('secret', $processed->query->get('api_key'));

        // Removed parameter
        $this->assertNull($processed->query->get('sort'));

        // Overridden parameter
        $this->assertEquals('50', $processed->query->get('limit'));
        $this->assertEquals('added', $processed->query->get('new_filter'));
    }
}
