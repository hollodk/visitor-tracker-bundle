<?php

namespace Beast\VisitorTrackerBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class VisitorPerformanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
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

        $today = (new \DateTime())->format('Y-m-d');
        $logDir = __DIR__ . '/../../../../var/visitor_tracker/logs';
        $logFile = "$logDir/$today.log";

        if (!file_exists($logFile)) return;

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $updated = false;

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $entry = json_decode($lines[$i], true);
            if (!is_array($entry)) continue;

            if (
                ($entry['visitor_id'] ?? '') === $visitorId &&
                ($entry['uri'] ?? '') === $uri &&
                empty($entry['duration_ms'])
            ) {
                $entry['duration_ms'] = $duration;
                $entry['status_code'] = $response instanceof Response ? $response->getStatusCode() : null;
                $entry['route'] = $request->attributes->get('_route');
                $entry['memory_usage_bytes'] = round(memory_get_peak_usage(true), 2);
                $entry['auth'] = $this->tokenStorage?->getToken()?->getUser() ? 'user' : 'anon';
                $entry['content_type'] = $response->headers->get('Content-Type');

                $lines[$i] = json_encode($entry);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            @mkdir($logDir, 0777, true);
            file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }
}
