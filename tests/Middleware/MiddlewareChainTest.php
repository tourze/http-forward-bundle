<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\MiddlewareChain;
use Tourze\HttpForwardBundle\Middleware\MiddlewareInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MiddlewareChain::class)]
#[RunTestsInSeparateProcesses]
final class MiddlewareChainTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // This method is required by AbstractIntegrationTestCase
    }

    private function getMiddlewareChain(): MiddlewareChain
    {
        return self::getService(MiddlewareChain::class);
    }

    public function testAddMiddleware(): void
    {
        $chain = $this->getMiddlewareChain();
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('isEnabled')->willReturn(true);

        $chain->addMiddleware($middleware);

        $this->assertCount(1, $chain->getMiddlewares());
    }

    public function testProcessRequestWithMultipleMiddlewares(): void
    {
        $chain = $this->getMiddlewareChain();
        $request = Request::create('/test');
        $log = new ForwardLog();

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware1->method('isEnabled')->willReturn(true);
        $middleware1->method('processRequest')
            ->willReturnCallback(function (Request $req) {
                $req->headers->set('X-Middleware-1', 'true');

                return $req;
            })
        ;

        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware2->method('isEnabled')->willReturn(true);
        $middleware2->method('processRequest')
            ->willReturnCallback(function (Request $req) {
                $req->headers->set('X-Middleware-2', 'true');

                return $req;
            })
        ;

        $chain->addMiddleware($middleware1);
        $chain->addMiddleware($middleware2);

        $processedRequest = $chain->processRequest($request, $log);

        $this->assertEquals('true', $processedRequest->headers->get('X-Middleware-1'));
        $this->assertEquals('true', $processedRequest->headers->get('X-Middleware-2'));
    }

    public function testProcessResponseWithMultipleMiddlewares(): void
    {
        $chain = $this->getMiddlewareChain();
        $response = new Response('test content');

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware1->method('isEnabled')->willReturn(true);
        $middleware1->method('processResponse')
            ->willReturnCallback(function (Response $res) {
                $res->headers->set('X-Middleware-1', 'processed');

                return $res;
            })
        ;

        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware2->method('isEnabled')->willReturn(true);
        $middleware2->method('processResponse')
            ->willReturnCallback(function (Response $res) {
                $res->headers->set('X-Middleware-2', 'processed');

                return $res;
            })
        ;

        $chain->addMiddleware($middleware1);
        $chain->addMiddleware($middleware2);

        $processedResponse = $chain->processResponse($response);

        $this->assertEquals('processed', $processedResponse->headers->get('X-Middleware-1'));
        $this->assertEquals('processed', $processedResponse->headers->get('X-Middleware-2'));
    }

    public function testSkipsDisabledMiddlewares(): void
    {
        $chain = $this->getMiddlewareChain();
        $request = Request::create('/test');
        $log = new ForwardLog();

        $enabledMiddleware = $this->createMock(MiddlewareInterface::class);
        $enabledMiddleware->method('isEnabled')->willReturn(true);
        $enabledMiddleware->method('processRequest')
            ->willReturnCallback(function (Request $req) {
                $req->headers->set('X-Enabled', 'true');

                return $req;
            })
        ;

        $disabledMiddleware = $this->createMock(MiddlewareInterface::class);
        $disabledMiddleware->method('isEnabled')->willReturn(false);
        $disabledMiddleware->method('processRequest')
            ->willReturnCallback(function (Request $req) {
                $req->headers->set('X-Disabled', 'true');

                return $req;
            })
        ;

        $chain->addMiddleware($enabledMiddleware);
        $chain->addMiddleware($disabledMiddleware);

        $processedRequest = $chain->processRequest($request, $log);

        $this->assertEquals('true', $processedRequest->headers->get('X-Enabled'));
        $this->assertNull($processedRequest->headers->get('X-Disabled'));
    }

    public function testClearMiddlewares(): void
    {
        $chain = $this->getMiddlewareChain();
        $middleware = $this->createMock(MiddlewareInterface::class);
        $chain->addMiddleware($middleware);

        $this->assertCount(1, $chain->getMiddlewares());

        // Clear by clearing the chain's middleware collection
        $chain->clearMiddlewares();

        $this->assertCount(0, $chain->getMiddlewares());
    }

    public function testGetMiddlewares(): void
    {
        $chain = $this->getMiddlewareChain();
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $chain->addMiddleware($middleware1);
        $chain->addMiddleware($middleware2);

        $middlewares = $chain->getMiddlewares();

        $this->assertCount(2, $middlewares);
        $this->assertContains($middleware1, $middlewares);
        $this->assertContains($middleware2, $middlewares);
    }
}
