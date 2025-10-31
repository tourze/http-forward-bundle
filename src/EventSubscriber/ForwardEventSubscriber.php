<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\EventSubscriber;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tourze\HttpForwardBundle\Event\AfterForwardEvent;
use Tourze\HttpForwardBundle\Event\BeforeForwardEvent;
use Tourze\HttpForwardBundle\Event\FallbackTriggeredEvent;
use Tourze\HttpForwardBundle\Event\ForwardEvents;
use Tourze\HttpForwardBundle\Event\RetryAttemptEvent;
use Tourze\HttpForwardBundle\Service\ForwarderService;
use Tourze\HttpForwardBundle\Service\RuleMatcher;

#[WithMonologChannel(channel: 'http_forward')]
readonly class ForwardEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RuleMatcher $ruleMatcher,
        private ForwarderService $forwarderService,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            ForwardEvents::BEFORE_FORWARD => 'onBeforeForward',
            ForwardEvents::AFTER_FORWARD => 'onAfterForward',
            ForwardEvents::RETRY_ATTEMPT => 'onRetryAttempt',
            ForwardEvents::FALLBACK_TRIGGERED => 'onFallbackTriggered',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        try {
            $rule = $this->ruleMatcher->match($request);
        } catch (TableNotFoundException $exception) {
            $this->logger->error('表名不存在，应该是还没部署好', [
                'request' => $event->getRequest(),
                'exception' => $exception,
            ]);

            return;
        } catch (DriverException $exception) {
            $this->logger->error('表名不存在，应该是还没部署好', [
                'request' => $event->getRequest(),
                'exception' => $exception,
            ]);

            return;
        }

        if (null === $rule) {
            return;
        }

        $this->logger->info('Matched forward rule', [
            'rule_id' => $rule->getId(),
            'rule_name' => $rule->getName(),
            'path' => $request->getPathInfo(),
        ]);

        try {
            $response = $this->forwarderService->forward($request, $rule);
            $event->setResponse($response);
            $event->stopPropagation();
        } catch (\Exception $e) {
            $this->logger->error('Forward failed', [
                'rule_id' => $rule->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onBeforeForward(BeforeForwardEvent $event): void
    {
        $this->logger->debug('Before forward', [
            'rule_id' => $event->getRule()->getId(),
            'method' => $event->getRequest()->getMethod(),
            'path' => $event->getRequest()->getPathInfo(),
        ]);
    }

    public function onAfterForward(AfterForwardEvent $event): void
    {
        $this->logger->debug('After forward', [
            'rule_id' => $event->getRule()->getId(),
            'status' => $event->getResponse()->getStatusCode(),
        ]);
    }

    public function onRetryAttempt(RetryAttemptEvent $event): void
    {
        $this->logger->warning('Retry attempt', [
            'rule_id' => $event->getRule()->getId(),
            'attempt' => $event->getAttemptNumber(),
            'error' => $event->getLastException()?->getMessage(),
        ]);
    }

    public function onFallbackTriggered(FallbackTriggeredEvent $event): void
    {
        $this->logger->warning('Fallback triggered', [
            'rule_id' => $event->getRule()->getId(),
            'type' => $event->getFallbackType(),
            'error' => $event->getException()->getMessage(),
        ]);
    }
}
