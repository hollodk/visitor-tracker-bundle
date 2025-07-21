<?php

namespace Beast\VisitorTrackerBundle\EventSubscriber;

use Beast\VisitorTrackerBundle\Service\VisitorLogBuffer;
use Beast\VisitorTrackerBundle\Service\VisitorLogConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class VisitorPerformanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly VisitorLogBuffer $buffer,
        private VisitorLogConfig $config,
        private readonly ?TokenStorageInterface $tokenStorage = null
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        $event->getRequest()->attributes->set('_visitor_tracker_start', microtime(true));
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest() || str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        $start = $request->attributes->get('_visitor_tracker_start');
        if (!$start) return;

        $duration = round((microtime(true) - $start) * 1000, 2); // ms
        $ip = $request->getClientIp();
        $ua = $request->headers->get('User-Agent', '');
        $visitorId = sha1(($ip ?? '') . $ua);
        $uri = $request->getRequestUri();

        $this->buffer->updateLastEntry(function (array $entry) use ($request, $response, $duration) {
            $entry['duration_ms'] = $duration;
            $entry['status_code'] = $response instanceof Response ? $response->getStatusCode() : null;
            $entry['route'] = $request->attributes->get('_route');
            $entry['memory_usage_bytes'] = memory_get_peak_usage(true);
            $entry['auth'] = $this->tokenStorage?->getToken()?->getUser() ? 'user' : 'anon';
            $entry['content_type'] = $response->headers->get('Content-Type');
            $entry['response_size_bytes'] = strlen($response->getContent() ?? '');

            return $entry;
        });

        $logFile = $this->config->getTodayLogFile();

        $this->buffer->flush($logFile);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }
}
