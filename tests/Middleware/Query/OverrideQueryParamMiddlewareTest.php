<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Query\OverrideQueryParamMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(OverrideQueryParamMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class OverrideQueryParamMiddlewareTest extends AbstractIntegrationTestCase
{
    private OverrideQueryParamMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(OverrideQueryParamMiddleware::class);
        $this->forwardLog = new ForwardLog();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(80, $this->middleware->getPriority());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->middleware->isEnabled());
    }

    public function testGetServiceAlias(): void
    {
        $this->assertEquals('override_query_param', OverrideQueryParamMiddleware::getServiceAlias());
    }

    public function testProcessRequestWithoutConfig(): void
    {
        $request = Request::create('/test?existing=value');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('value', $processed->query->get('existing'));
    }

    public function testProcessRequestWithEmptyConfig(): void
    {
        $request = Request::create('/test?existing=value');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('value', $processed->query->get('existing'));
    }

    public function testProcessRequestOverrideSingleParameter(): void
    {
        $request = Request::create('/test?param1=original');
        $config = [
            'param1' => 'overridden',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('overridden', $processed->query->get('param1'));
    }

    public function testProcessRequestOverrideMultipleParameters(): void
    {
        $request = Request::create('/test?param1=original1&param2=original2&param3=keep');
        $config = [
            'param1' => 'overridden1',
            'param2' => 'overridden2',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('overridden1', $processed->query->get('param1'));
        $this->assertEquals('overridden2', $processed->query->get('param2'));
        $this->assertEquals('keep', $processed->query->get('param3'));
    }

    public function testProcessRequestAddNewParameters(): void
    {
        $request = Request::create('/test?existing=value');
        $config = [
            'new_param1' => 'new-value1',
            'new_param2' => 'new-value2',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value', $processed->query->get('existing'));
        $this->assertEquals('new-value1', $processed->query->get('new_param1'));
        $this->assertEquals('new-value2', $processed->query->get('new_param2'));
    }

    public function testProcessRequestMixedOverrideAndAdd(): void
    {
        $request = Request::create('/test?existing=original&keep=unchanged');
        $config = [
            'existing' => 'overridden',
            'new_param' => 'new-value',
            'keep' => 'changed',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('overridden', $processed->query->get('existing'));
        $this->assertEquals('new-value', $processed->query->get('new_param'));
        $this->assertEquals('changed', $processed->query->get('keep'));
    }

    public function testProcessRequestOverrideWithSpecialCharacters(): void
    {
        $request = Request::create('/test?param1=original');
        $config = [
            'special_chars' => 'value with spaces & symbols!',
            'encoded' => 'user@domain.com',
            'unicode' => '测试值',
            'param1' => 'new value with special chars!',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('new value with special chars!', $processed->query->get('param1'));
        $this->assertEquals('value with spaces & symbols!', $processed->query->get('special_chars'));
        $this->assertEquals('user@domain.com', $processed->query->get('encoded'));
        $this->assertEquals('测试值', $processed->query->get('unicode'));
    }

    public function testProcessRequestOverrideWithEmptyAndNullValues(): void
    {
        $request = Request::create('/test?param1=original&param2=original&param3=original&param4=original');
        $config = [
            'param1' => '',
            'param2' => null,
            'param3' => '0',
            'param4' => 'false',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('', $processed->query->get('param1'));
        $this->assertNull($processed->query->get('param2'));
        $this->assertEquals('0', $processed->query->get('param3'));
        $this->assertEquals('false', $processed->query->get('param4'));
    }

    public function testProcessRequestOverrideWithNumericKeys(): void
    {
        $request = Request::create('/test?0=original&123=original');
        // 使用string keys以满足array<string, mixed>类型要求
        $config = [
            '0' => 'zero-overridden',
            '123' => 'numeric-overridden',
            '456' => 'new-numeric',
        ];

        // @phpstan-ignore argument.type
        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('zero-overridden', $processed->query->get('0'));
        $this->assertEquals('numeric-overridden', $processed->query->get('123'));
        $this->assertEquals('new-numeric', $processed->query->get('456'));
    }

    public function testProcessRequestOverrideWithArrayValues(): void
    {
        $request = Request::create('/test?tags[]=tag1&tags[]=tag2&simple=value');
        $config = [
            'tags' => ['new1', 'new2', 'new3'],
            'simple' => 'overridden',
            'new_array' => ['array1', 'array2'],
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $queryAll = $processed->query->all();
        $this->assertEquals(['new1', 'new2', 'new3'], $queryAll['tags']);
        $this->assertEquals('overridden', $processed->query->get('simple'));
        $this->assertEquals(['array1', 'array2'], $queryAll['new_array']);
    }

    public function testProcessRequestOverrideWithBooleanValues(): void
    {
        $request = Request::create('/test?param1=original&param2=original');
        $config = [
            'param1' => true,
            'param2' => false,
            'new_true' => true,
            'new_false' => false,
            'one' => 1,
            'zero' => 0,
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertTrue($processed->query->get('param1'));
        $this->assertFalse($processed->query->get('param2'));
        $this->assertTrue($processed->query->get('new_true'));
        $this->assertFalse($processed->query->get('new_false'));
        $this->assertEquals(1, $processed->query->get('one'));
        $this->assertEquals(0, $processed->query->get('zero'));
    }

    public function testProcessRequestWithComplexQueryString(): void
    {
        $queryString = 'page=1&limit=20&sort[field]=name&sort[order]=asc&filters[status]=active&filters[type]=premium';
        $request = Request::create("/test?{$queryString}");
        $config = [
            'page' => '2',
            'limit' => '50',
            'sort' => ['field' => 'date', 'order' => 'desc'],
            'api_key' => 'secret',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Overridden values
        $this->assertEquals('2', $processed->query->get('page'));
        $this->assertEquals('50', $processed->query->get('limit'));

        $queryAll = $processed->query->all();
        $this->assertEquals(['field' => 'date', 'order' => 'desc'], $queryAll['sort']);

        // Added value
        $this->assertEquals('secret', $processed->query->get('api_key'));

        // Preserved filters
        $filters = $processed->query->all('filters');
        $this->assertEquals('active', $filters['status'] ?? null);
        $this->assertEquals('premium', $filters['type'] ?? null);
    }

    public function testProcessRequestOverrideEmptyRequest(): void
    {
        $request = Request::create('/test');
        $config = [
            'param1' => 'value1',
            'param2' => 'value2',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value1', $processed->query->get('param1'));
        $this->assertEquals('value2', $processed->query->get('param2'));
    }

    public function testProcessRequestOverrideAllParameters(): void
    {
        $request = Request::create('/test?param1=original1&param2=original2&param3=original3');
        $config = [
            'param1' => 'new1',
            'param2' => 'new2',
            'param3' => 'new3',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('new1', $processed->query->get('param1'));
        $this->assertEquals('new2', $processed->query->get('param2'));
        $this->assertEquals('new3', $processed->query->get('param3'));
    }

    public function testProcessRequestOverrideWithNestedArrays(): void
    {
        $request = Request::create('/test?data[user][name]=john&data[user][email]=john@example.com&simple=value');
        $config = [
            'data' => [
                'user' => [
                    'name' => 'jane',
                    'email' => 'jane@example.com',
                    'age' => 25,
                ],
                'meta' => [
                    'version' => '2.0',
                ],
            ],
            'simple' => 'overridden',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $queryAll = $processed->query->all();
        $data = $queryAll['data'];
        $this->assertIsArray($data);
        $this->assertArrayHasKey('user', $data);
        $this->assertIsArray($data['user']);
        $this->assertEquals('jane', $data['user']['name'] ?? null);
        $this->assertEquals('jane@example.com', $data['user']['email'] ?? null);
        $this->assertEquals(25, $data['user']['age'] ?? null);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['meta']);
        $this->assertEquals('2.0', $data['meta']['version'] ?? null);
        $this->assertEquals('overridden', $processed->query->get('simple'));
    }

    public function testProcessRequestOverrideWithNumericAndFloatValues(): void
    {
        $request = Request::create('/test?int_param=10&float_param=3.14&string_param=text');
        $config = [
            'int_param' => 42,
            'float_param' => 2.71,
            'string_param' => 'overridden',
            'new_int' => 100,
            'new_float' => 1.41,
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals(42, $processed->query->get('int_param'));
        $this->assertEquals(2.71, $processed->query->get('float_param'));
        $this->assertEquals('overridden', $processed->query->get('string_param'));
        $this->assertEquals(100, $processed->query->get('new_int'));
        $this->assertEquals(1.41, $processed->query->get('new_float'));
    }

    public function testProcessRequestModifiesOriginalRequest(): void
    {
        $request = Request::create('/test?param1=original');
        $config = [
            'param1' => 'overridden',
            'param2' => 'new',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Should return the same request object (modified in place)
        $this->assertSame($request, $processed);
        $this->assertEquals('overridden', $request->query->get('param1'));
        $this->assertEquals('new', $request->query->get('param2'));
    }

    public function testProcessRequestOverrideSpecialParameterNames(): void
    {
        $request = Request::create('/test?_token=old&__internal=old&normal=old');
        $config = [
            '_token' => 'new-token',
            '__internal' => 'new-internal',
            'normal' => 'new-normal',
            '_new' => 'underscore-param',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('new-token', $processed->query->get('_token'));
        $this->assertEquals('new-internal', $processed->query->get('__internal'));
        $this->assertEquals('new-normal', $processed->query->get('normal'));
        $this->assertEquals('underscore-param', $processed->query->get('_new'));
    }

    public function testProcessRequestOverrideWithUrlEncodedValues(): void
    {
        $request = Request::create('/test?email=old%40domain.com&message=old%20message');
        $config = [
            'email' => 'new@domain.com',
            'message' => 'new message with spaces',
            'special' => 'value with & symbols',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('new@domain.com', $processed->query->get('email'));
        $this->assertEquals('new message with spaces', $processed->query->get('message'));
        $this->assertEquals('value with & symbols', $processed->query->get('special'));
    }
}
