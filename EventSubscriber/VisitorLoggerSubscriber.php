<?php

namespace Beast\VisitorTrackerBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class VisitorLoggerSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        $data = [
            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'ip' => $request->getClientIp(),
            'uri' => $request->getRequestUri(),
            'user_agent' => $request->headers->get('User-Agent'),
            'country' => 'unknown',
            'utm' => [],
        ];

        // Collect UTM parameters
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmKeys as $key) {
            if ($request->query->has($key)) {
                $data['utm'][$key] = $request->query->get($key);
            }
        }

        // Try to resolve country (optional)
        try {
            $geo = @file_get_contents("https://ipapi.co/{$data['ip']}/country_name/");
            if ($geo !== false) {
                $data['country'] = trim($geo);
            }
        } catch (\Throwable) {
            // Fail silently
        }

        // Use relative path to project root
        $logFile = __DIR__ . '/../../../../var/visitor_tracker/visits.log';

        @mkdir(dirname($logFile), 0777, true);
        file_put_contents($logFile, json_encode($data) . PHP_EOL, FILE_APPEND);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}

