<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HttpForwardBundle\Service\ForwarderService;
use Tourze\HttpForwardBundle\Service\RuleMatcher;

final class ForwardController extends AbstractController
{
    public function __construct(
        private readonly RuleMatcher $ruleMatcher,
        private readonly ForwarderService $forwarderService,
    ) {
    }

    #[Route(
        path: '/forward/{path}',
        name: 'http_forward',
        requirements: ['path' => '.*'],
        methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'],
        priority: -100
    )]
    public function __invoke(Request $request, string $path = ''): Response
    {
        $request->headers->remove('proxy');
        $request->headers->remove('x-php-ob-level');

        $rule = $this->ruleMatcher->match($request);

        if (null === $rule) {
            return new Response('No forwarding rule found', 404);
        }

        try {
            return $this->forwarderService->forward($request, $rule);
        } catch (\Exception $e) {
            return new Response(
                'Forward failed: ' . $e->getMessage(),
                502,
                ['X-Forward-Error' => 'true']
            );
        }
    }
}
