<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Query\AddQueryParamMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AddQueryParamMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class AddQueryParamMiddlewareTest extends AbstractIntegrationTestCase
{
    private AddQueryParamMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(AddQueryParamMiddleware::class);
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
        $this->assertEquals('add_query_param', AddQueryParamMiddleware::getServiceAlias());
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

    public function testProcessRequestAddSingleParameter(): void
    {
        $request = Request::create('/test');
        $config = [
            'api_key' => 'secret-key',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('secret-key', $processed->query->get('api_key'));
    }

    public function testProcessRequestAddMultipleParameters(): void
    {
        $request = Request::create('/test');
        $config = [
            'api_key' => 'secret-key',
            'version' => '2.0',
            'format' => 'json',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('secret-key', $processed->query->get('api_key'));
        $this->assertEquals('2.0', $processed->query->get('version'));
        $this->assertEquals('json', $processed->query->get('format'));
    }

    public function testProcessRequestDoesNotOverrideExistingParameters(): void
    {
        $request = Request::create('/test?existing=original&another=keep');
        $config = [
            'existing' => 'should-not-override',
            'another' => 'should-not-override-either',
            'new_param' => 'new-value',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Existing parameters should not be overridden
        $this->assertEquals('original', $processed->query->get('existing'));
        $this->assertEquals('keep', $processed->query->get('another'));

        // New parameter should be added
        $this->assertEquals('new-value', $processed->query->get('new_param'));
    }

    public function testProcessRequestWithExistingQueryString(): void
    {
        $request = Request::create('/test?page=1&limit=20');
        $config = [
            'api_key' => 'secret',
            'format' => 'json',
            'page' => '999', // Should not override existing page
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('1', $processed->query->get('page')); // Original value preserved
        $this->assertEquals('20', $processed->query->get('limit'));
        $this->assertEquals('secret', $processed->query->get('api_key'));
        $this->assertEquals('json', $processed->query->get('format'));
    }

    public function testProcessRequestWithSpecialCharacters(): void
    {
        $request = Request::create('/test');
        $config = [
            'special_chars' => 'value with spaces & symbols!',
            'encoded' => 'user@domain.com',
            'unicode' => '测试值',
            'symbols' => '!@#$%^&*()',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value with spaces & symbols!', $processed->query->get('special_chars'));
        $this->assertEquals('user@domain.com', $processed->query->get('encoded'));
        $this->assertEquals('测试值', $processed->query->get('unicode'));
        $this->assertEquals('!@#$%^&*()', $processed->query->get('symbols'));
    }

    public function testProcessRequestWithEmptyAndNullValues(): void
    {
        $request = Request::create('/test');
        $config = [
            'empty_string' => '',
            'null_value' => null,
            'zero' => '0',
            'false_string' => 'false',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('', $processed->query->get('empty_string'));
        $this->assertNull($processed->query->get('null_value'));
        $this->assertEquals('0', $processed->query->get('zero'));
        $this->assertEquals('false', $processed->query->get('false_string'));
    }

    public function testProcessRequestWithNumericKeys(): void
    {
        $request = Request::create('/test');
        // 使用string keys以满足array<string, mixed>类型要求
        $config = [
            '0' => 'zero',
            '123' => 'numeric-key',
            'normal_key' => 'normal-value',
        ];

        // @phpstan-ignore argument.type
        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('zero', $processed->query->get('0'));
        $this->assertEquals('numeric-key', $processed->query->get('123'));
        $this->assertEquals('normal-value', $processed->query->get('normal_key'));
    }

    public function testProcessRequestWithArrayValues(): void
    {
        $request = Request::create('/test');
        $config = [
            'tags' => ['tag1', 'tag2', 'tag3'],
            'simple' => 'value',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // The middleware sets the value directly in the query array
        $queryAll = $processed->query->all();
        $this->assertEquals(['tag1', 'tag2', 'tag3'], $queryAll['tags']);
        $this->assertEquals('value', $processed->query->get('simple'));
    }

    public function testProcessRequestPreservesExistingArrays(): void
    {
        $request = Request::create('/test?categories[]=cat1&categories[]=cat2&simple=keep');
        $config = [
            'categories' => ['should', 'not', 'override'],
            'new_array' => ['new1', 'new2'],
            'simple' => 'should-not-override',
            'new_simple' => 'added',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Existing array should be preserved
        $categories = $processed->query->all('categories');
        $this->assertEquals(['cat1', 'cat2'], $categories);

        // Existing simple parameter should be preserved
        $this->assertEquals('keep', $processed->query->get('simple'));

        // New parameters should be added
        $queryAll = $processed->query->all();
        $this->assertEquals(['new1', 'new2'], $queryAll['new_array']);
        $this->assertEquals('added', $processed->query->get('new_simple'));
    }

    public function testProcessRequestWithBooleanValues(): void
    {
        $request = Request::create('/test');
        $config = [
            'true_bool' => true,
            'false_bool' => false,
            'one' => 1,
            'zero' => 0,
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertTrue($processed->query->get('true_bool'));
        $this->assertFalse($processed->query->get('false_bool'));
        $this->assertEquals(1, $processed->query->get('one'));
        $this->assertEquals(0, $processed->query->get('zero'));
    }

    public function testProcessRequestWithComplexMixedTypes(): void
    {
        $request = Request::create('/test?existing=keep&override_me=original');
        $config = [
            'string' => 'text',
            'number' => 42,
            'float' => 3.14,
            'boolean' => true,
            'array' => ['a', 'b', 'c'],
            'existing' => 'should-not-change',
            'override_me' => 'should-not-change',
            'null_val' => null,
            'empty' => '',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Original values preserved
        $this->assertEquals('keep', $processed->query->get('existing'));
        $this->assertEquals('original', $processed->query->get('override_me'));

        // New values added
        $this->assertEquals('text', $processed->query->get('string'));
        $this->assertEquals(42, $processed->query->get('number'));
        $this->assertEquals(3.14, $processed->query->get('float'));
        $this->assertTrue($processed->query->get('boolean'));

        $queryAll = $processed->query->all();
        $this->assertEquals(['a', 'b', 'c'], $queryAll['array']);
        $this->assertNull($processed->query->get('null_val'));
        $this->assertEquals('', $processed->query->get('empty'));
    }

    public function testProcessRequestModifiesOriginalRequest(): void
    {
        $request = Request::create('/test');
        $config = [
            'new_param' => 'new-value',
        ];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Should return the same request object (modified in place)
        $this->assertSame($request, $processed);
        $this->assertEquals('new-value', $request->query->get('new_param'));
    }
}
