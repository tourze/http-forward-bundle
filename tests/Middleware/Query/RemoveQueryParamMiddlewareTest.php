<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\Query\RemoveQueryParamMiddleware;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RemoveQueryParamMiddleware::class)]
#[RunTestsInSeparateProcesses]
final class RemoveQueryParamMiddlewareTest extends AbstractIntegrationTestCase
{
    private RemoveQueryParamMiddleware $middleware;

    private ForwardLog $forwardLog;

    protected function onSetUp(): void
    {
        $this->middleware = self::getService(RemoveQueryParamMiddleware::class);
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
        $this->assertEquals('remove_query_param', RemoveQueryParamMiddleware::getServiceAlias());
    }

    public function testProcessRequestWithoutConfig(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('value1', $processed->query->get('param1'));
        $this->assertEquals('value2', $processed->query->get('param2'));
    }

    public function testProcessRequestWithEmptyConfig(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2');

        $processed = $this->middleware->processRequest($request, $this->forwardLog, []);

        $this->assertSame($request, $processed);
        $this->assertEquals('value1', $processed->query->get('param1'));
        $this->assertEquals('value2', $processed->query->get('param2'));
    }

    public function testProcessRequestRemoveSingleParameter(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2&param3=value3');
        $config = ['params' => ['param2']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value1', $processed->query->get('param1'));
        $this->assertNull($processed->query->get('param2'));
        $this->assertEquals('value3', $processed->query->get('param3'));
    }

    public function testProcessRequestRemoveMultipleParameters(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2&param3=value3&param4=value4');
        $config = ['params' => ['param1', 'param3']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('param1'));
        $this->assertEquals('value2', $processed->query->get('param2'));
        $this->assertNull($processed->query->get('param3'));
        $this->assertEquals('value4', $processed->query->get('param4'));
    }

    public function testProcessRequestRemoveAllParameters(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2');
        $config = ['params' => ['param1', 'param2']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('param1'));
        $this->assertNull($processed->query->get('param2'));
        $this->assertEmpty($processed->query->all());
    }

    public function testProcessRequestRemoveNonExistentParameters(): void
    {
        $request = Request::create('/test?existing=value');
        $config = ['params' => ['non_existent', 'another_missing']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertEquals('value', $processed->query->get('existing'));
        $this->assertNull($processed->query->get('non_existent'));
        $this->assertNull($processed->query->get('another_missing'));
    }

    public function testProcessRequestRemoveMixedExistingAndNonExistent(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2&param3=value3');
        $config = ['params' => ['param1', 'non_existent', 'param3', 'another_missing']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('param1'));
        $this->assertEquals('value2', $processed->query->get('param2'));
        $this->assertNull($processed->query->get('param3'));
        $this->assertNull($processed->query->get('non_existent'));
        $this->assertNull($processed->query->get('another_missing'));
    }

    public function testProcessRequestRemoveArrayParameters(): void
    {
        $request = Request::create('/test?tags[]=tag1&tags[]=tag2&simple=value&filters[status]=active');
        $config = ['params' => ['tags', 'filters']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('tags'));
        $this->assertNull($processed->query->get('filters'));
        $this->assertEquals('value', $processed->query->get('simple'));
    }

    public function testProcessRequestRemoveParametersWithSpecialCharacters(): void
    {
        $request = Request::create('/test?special_chars=value&encoded=user@domain.com&unicode=测试&normal=keep');
        $config = ['params' => ['special_chars', 'encoded', 'unicode']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('special_chars'));
        $this->assertNull($processed->query->get('encoded'));
        $this->assertNull($processed->query->get('unicode'));
        $this->assertEquals('keep', $processed->query->get('normal'));
    }

    public function testProcessRequestRemoveNumericKeys(): void
    {
        $request = Request::create('/test?0=zero&123=numeric&normal=value');
        $config = ['params' => ['0', '123']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('0'));
        $this->assertNull($processed->query->get('123'));
        $this->assertEquals('value', $processed->query->get('normal'));
    }

    public function testProcessRequestRemoveParametersWithEmptyValues(): void
    {
        $request = Request::create('/test?empty=&zero=0&false=false&null_like=null&keep=value');
        $config = ['params' => ['empty', 'zero', 'false', 'null_like']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('empty'));
        $this->assertNull($processed->query->get('zero'));
        $this->assertNull($processed->query->get('false'));
        $this->assertNull($processed->query->get('null_like'));
        $this->assertEquals('value', $processed->query->get('keep'));
    }

    public function testProcessRequestWithComplexQueryString(): void
    {
        $queryString = 'page=1&limit=20&sort[field]=name&sort[order]=asc&filters[status]=active&filters[type]=premium&api_key=secret';
        $request = Request::create("/test?{$queryString}");
        $config = ['params' => ['sort', 'api_key']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Preserved parameters
        $this->assertEquals('1', $processed->query->get('page'));
        $this->assertEquals('20', $processed->query->get('limit'));
        $filters = $processed->query->all('filters');
        $this->assertEquals('active', $filters['status'] ?? null);
        $this->assertEquals('premium', $filters['type'] ?? null);

        // Removed parameters
        $this->assertNull($processed->query->get('sort'));
        $this->assertNull($processed->query->get('api_key'));
    }

    public function testProcessRequestRemoveParametersFromEmptyRequest(): void
    {
        $request = Request::create('/test');
        $config = ['params' => ['param1', 'param2']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('param1'));
        $this->assertNull($processed->query->get('param2'));
        $this->assertEmpty($processed->query->all());
    }

    public function testProcessRequestWithDuplicateParametersInConfig(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2&param3=value3');
        $config = ['params' => ['param1', 'param2', 'param1', 'param2']]; // Duplicates

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('param1'));
        $this->assertNull($processed->query->get('param2'));
        $this->assertEquals('value3', $processed->query->get('param3'));
    }

    public function testProcessRequestWithSingleParameterArray(): void
    {
        $request = Request::create('/test?remove_me=value&keep_me=value');
        $config = ['params' => ['remove_me']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('remove_me'));
        $this->assertEquals('value', $processed->query->get('keep_me'));
    }

    public function testProcessRequestRemoveParametersWithSpecialNames(): void
    {
        $request = Request::create('/test?_token=csrf&__secret=hidden&normal_param=value&_internal=data');
        $config = ['params' => ['_token', '__secret', '_internal']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('_token'));
        $this->assertNull($processed->query->get('__secret'));
        $this->assertNull($processed->query->get('_internal'));
        $this->assertEquals('value', $processed->query->get('normal_param'));
    }

    public function testProcessRequestModifiesOriginalRequest(): void
    {
        $request = Request::create('/test?param1=value1&param2=value2');
        $config = ['params' => ['param1']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        // Should return the same request object (modified in place)
        $this->assertSame($request, $processed);
        $this->assertNull($request->query->get('param1'));
        $this->assertEquals('value2', $request->query->get('param2'));
    }

    public function testProcessRequestWithNestedArrayParameters(): void
    {
        $request = Request::create('/test?data[user][name]=john&data[user][email]=john@example.com&data[meta][version]=1&simple=keep');
        $config = ['params' => ['data']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('data'));
        $this->assertEquals('keep', $processed->query->get('simple'));
    }

    public function testProcessRequestRemoveWithUrlEncodedParameters(): void
    {
        $request = Request::create('/test?email=user%40domain.com&message=hello%20world&keep=value');
        $config = ['params' => ['email', 'message']];

        $processed = $this->middleware->processRequest($request, $this->forwardLog, $config);

        $this->assertNull($processed->query->get('email'));
        $this->assertNull($processed->query->get('message'));
        $this->assertEquals('value', $processed->query->get('keep'));
    }
}
