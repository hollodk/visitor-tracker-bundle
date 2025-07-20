<?php

namespace Beast\VisitorTrackerBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class VisitorLoggerSubscriber implements EventSubscriberInterface
{
    private bool $geoEnabled;
    private bool $ipAnonymize;

    public function __construct(bool $geoEnabled = true, bool $ipAnonymize = false)
    {
        $this->geoEnabled = $geoEnabled;
        $this->ipAnonymize = $ipAnonymize;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        $ip = $request->getClientIp();
        $ua = $request->headers->get('User-Agent', '');

        if ($this->ipAnonymize && $ip) {
            $ip = preg_replace('/\.\d+$/', '.0', $ip); // IPv4: anonymize last block
        }

        $visitorId = sha1(($ip ?? '') . $ua);

        $data = [
            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'ip' => $ip,
            'uri' => $request->getRequestUri(),
            'user_agent' => $ua,
            'visitor_id' => $visitorId,
            'referrer' => $request->headers->get('referer', null),
            'country' => 'unknown',
            'city' => null,
            'isp' => null,
            'browser' => null,
            'os' => null,
            'device' => null,
            'is_bot' => false,
            'utm' => [],
        ];

        // 🌍 Geo Data (country, city, isp)
        if ($this->geoEnabled && $ip) {
            try {
                $geoRaw = @file_get_contents("https://ipapi.co/{$ip}/json/");
                if ($geoRaw !== false) {
                    $geo = json_decode($geoRaw, true);
                    if (is_array($geo)) {
                        $data['country'] = $geo['country_name'] ?? 'unknown';
                        $data['city'] = $geo['city'] ?? null;
                        $data['isp'] = $geo['org'] ?? null;
                    }
                }
            } catch (\Throwable) {
                // silent
            }
        }

        // 📣 UTM Parameters
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmKeys as $key) {
            if ($request->query->has($key)) {
                $data['utm'][$key] = $request->query->get($key);
            }
        }

        // 🧠 UA Parsing (basic)
        if (preg_match('/(Mobile|Android|iPhone|iPad|iPod)/i', $ua)) {
            $data['device'] = 'mobile';
        } elseif (preg_match('/Tablet|iPad/i', $ua)) {
            $data['device'] = 'tablet';
        } else {
            $data['device'] = 'desktop';
        }

        if (preg_match('/Chrome/i', $ua)) {
            $data['browser'] = 'Chrome';
        } elseif (preg_match('/Firefox/i', $ua)) {
            $data['browser'] = 'Firefox';
        } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $data['browser'] = 'Safari';
        } elseif (preg_match('/Edge/i', $ua)) {
            $data['browser'] = 'Edge';
        } elseif (preg_match('/MSIE|Trident/i', $ua)) {
            $data['browser'] = 'IE';
        }

        if (preg_match('/Windows/i', $ua)) {
            $data['os'] = 'Windows';
        } elseif (preg_match('/Macintosh/i', $ua)) {
            $data['os'] = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $data['os'] = 'Linux';
        } elseif (preg_match('/Android/i', $ua)) {
            $data['os'] = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $data['os'] = 'iOS';
        }

        // 🤖 Bot Detection (basic)
        if (preg_match('/bot|crawl|spider|slurp|curl|wget/i', $ua)) {
            $data['is_bot'] = true;
        }

        // 💾 Write log
        $today = (new \DateTime())->format('Y-m-d');
        $logDir = __DIR__ . '/../../../../var/visitor_tracker/logs';
        $logFile = "$logDir/$today.log";

        @mkdir($logDir, 0777, true);
        file_put_contents($logFile, json_encode($data) . PHP_EOL, FILE_APPEND);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
