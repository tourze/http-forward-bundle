<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Event\BeforeForwardEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(BeforeForwardEvent::class)]
final class BeforeForwardEventTest extends AbstractEventTestCase
{
    public function testConstructorAndGetters(): void
    {
        $request = Request::create('/test', 'GET', ['param' => 'value']);
        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $rule->setSourcePath('/test');

        $event = new BeforeForwardEvent($request, $rule);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame($rule, $event->getRule());
    }

    public function testSetRequest(): void
    {
        $originalRequest = Request::create('/original', 'GET');
        $newRequest = Request::create('/updated', 'POST');
        $rule = new ForwardRule();

        $event = new BeforeForwardEvent($originalRequest, $rule);
        $this->assertSame($originalRequest, $event->getRequest());

        $event->setRequest($newRequest);
        $this->assertSame($newRequest, $event->getRequest());
        $this->assertNotSame($originalRequest, $event->getRequest());
    }

    public function testRequestModification(): void
    {
        $request = Request::create('/api/users', 'GET');
        $request->headers->set('Authorization', 'Bearer original-token');
        $rule = new ForwardRule();
        $rule->setName('API Rule');

        $event = new BeforeForwardEvent($request, $rule);

        $modifiedRequest = Request::create('/api/v2/users', 'POST');
        $modifiedRequest->headers->set('Authorization', 'Bearer new-token');
        $modifiedRequest->headers->set('X-Modified', 'true');

        $event->setRequest($modifiedRequest);

        $this->assertEquals('/api/v2/users', $event->getRequest()->getPathInfo());
        $this->assertEquals('POST', $event->getRequest()->getMethod());
        $this->assertEquals('Bearer new-token', $event->getRequest()->headers->get('Authorization'));
        $this->assertEquals('true', $event->getRequest()->headers->get('X-Modified'));
    }

    public function testWithComplexRequest(): void
    {
        $request = Request::create(
            '/complex?query=param',
            'PUT',
            [],
            [],
            [],
            ['HTTP_CUSTOM_HEADER' => 'custom-value'],
            '{"json": "data"}'
        );
        $rule = new ForwardRule();

        $event = new BeforeForwardEvent($request, $rule);

        $this->assertEquals('/complex', $event->getRequest()->getPathInfo());
        $this->assertEquals('PUT', $event->getRequest()->getMethod());
        $this->assertEquals('param', $event->getRequest()->query->get('query'));
        $this->assertEquals('custom-value', $event->getRequest()->headers->get('custom-header'));
        $this->assertEquals('{"json": "data"}', $event->getRequest()->getContent());
    }

    public function testMultipleRequestModifications(): void
    {
        $request = Request::create('/test');
        $rule = new ForwardRule();

        $event = new BeforeForwardEvent($request, $rule);

        $firstModification = Request::create('/modified1');
        $firstModification->headers->set('X-Step', '1');
        $event->setRequest($firstModification);

        $secondModification = Request::create('/modified2');
        $secondModification->headers->set('X-Step', '2');
        $event->setRequest($secondModification);

        $this->assertEquals('/modified2', $event->getRequest()->getPathInfo());
        $this->assertEquals('2', $event->getRequest()->headers->get('X-Step'));
    }
}
