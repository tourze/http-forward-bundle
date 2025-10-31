<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Event\AfterForwardEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(AfterForwardEvent::class)]
final class AfterForwardEventTest extends AbstractEventTestCase
{
    public function testConstructorAndGetters(): void
    {
        $request = Request::create('/test', 'POST');
        $response = new Response('test content', 200);
        $rule = new ForwardRule();
        $rule->setName('Test Rule');

        $event = new AfterForwardEvent($request, $response, $rule);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame($response, $event->getResponse());
        $this->assertSame($rule, $event->getRule());
    }

    public function testSetResponse(): void
    {
        $request = Request::create('/test');
        $originalResponse = new Response('original', 200);
        $newResponse = new Response('updated', 201);
        $rule = new ForwardRule();

        $event = new AfterForwardEvent($request, $originalResponse, $rule);
        $this->assertSame($originalResponse, $event->getResponse());

        $event->setResponse($newResponse);
        $this->assertSame($newResponse, $event->getResponse());
        $this->assertNotSame($originalResponse, $event->getResponse());
    }

    public function testEventCanBeCreatedWithDifferentStatusCodes(): void
    {
        $request = Request::create('/api/test', 'GET');
        $rule = new ForwardRule();
        $rule->setName('API Rule');

        $successEvent = new AfterForwardEvent($request, new Response('success', 200), $rule);
        $errorEvent = new AfterForwardEvent($request, new Response('error', 500), $rule);

        $this->assertEquals(200, $successEvent->getResponse()->getStatusCode());
        $this->assertEquals(500, $errorEvent->getResponse()->getStatusCode());
    }

    public function testEventWithComplexResponse(): void
    {
        $request = Request::create('/complex');
        $response = new Response('{"data": "test"}', 200, ['Content-Type' => 'application/json']);
        $rule = new ForwardRule();
        $rule->setName('JSON Rule');

        $event = new AfterForwardEvent($request, $response, $rule);

        $this->assertEquals('{"data": "test"}', $event->getResponse()->getContent());
        $this->assertEquals('application/json', $event->getResponse()->headers->get('Content-Type'));
    }

    public function testResponseModification(): void
    {
        $request = Request::create('/modify');
        $originalResponse = new Response('original content', 200);
        $rule = new ForwardRule();

        $event = new AfterForwardEvent($request, $originalResponse, $rule);

        $modifiedResponse = new Response('modified content', 202);
        $modifiedResponse->headers->set('X-Modified', 'true');

        $event->setResponse($modifiedResponse);

        $this->assertEquals('modified content', $event->getResponse()->getContent());
        $this->assertEquals(202, $event->getResponse()->getStatusCode());
        $this->assertEquals('true', $event->getResponse()->headers->get('X-Modified'));
    }
}
