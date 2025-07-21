<?php

namespace Beast\VisitorTrackerBundle\EventSubscriber;

use Beast\VisitorTrackerBundle\Service\VisitorLogBuffer;
use Beast\VisitorTrackerBundle\Service\VisitorSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class VisitorLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(private VisitorLogBuffer $buffer, private VisitorSettings $settings)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        $ip = $request->getClientIp();
        $ua = $request->headers->get('User-Agent', '');

        if ($this->settings->isIpAnonymize() && $ip) {
            $ip = preg_replace('/\.\d+$/', '.0', $ip); // IPv4: anonymize last block
        }

        $visitorId = sha1(($ip ?? '') . $ua);

        // Locale from Accept-Language
        $localeHeader = $request->headers->get('Accept-Language');
        $locale = $localeHeader ? substr($localeHeader, 0, 5) : null;

        // Referrer breakdown
        $referrerUrl = $request->headers->get('referer', null);
        $referrerDomain = null;
        $referrerPath = null;
        if ($referrerUrl) {
            $parsed = parse_url($referrerUrl);
            $referrerDomain = $parsed['host'] ?? null;
            $referrerPath = $parsed['path'] ?? null;
        }

        $sessionId = null;
        $landingPage = null;

        if ($this->settings->isSessionEnabled()) {
            // Session ID
            $session = $request->getSession();
            $sessionId = $session->getId();

            // Landing page: store only on first hit
            if (!$session->has('landing_page')) {
                $session->set('landing_page', $request->getRequestUri());
            }

            $landingPage = $session->get('landing_page');
        }

        $data = [
            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'ip' => $ip,
            'uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'user_agent' => $ua,
            'visitor_id' => $visitorId,
            'referrer' => $referrerUrl,
            'referrer_domain' => $referrerDomain,
            'referrer_path' => $referrerPath,
            'country' => 'unknown',
            'city' => null,
            'isp' => null,
            'browser' => null,
            'os' => null,
            'device' => null,
            'is_bot' => false,
            'utm' => [],
            'locale' => $locale,
            'session_id' => $sessionId,
            'landing_page' => $landingPage,
        ];

        // 🌍 Geo Data
        if ($this->settings->isGeoEnabled() && $ip) {
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

        // 🧠 UA Parsing
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

        // 🤖 Bot Detection (improved)
        $uaLower = strtolower($ua);
        $botSignatures = [
            'bot', 'crawl', 'slurp', 'spider', 'curl', 'wget', 'python', 'scrapy', 'feedfetcher',
            'mediapartners', 'facebookexternalhit', 'adsbot', 'google', 'bingpreview', 'ahrefs',
            'semrush', 'mj12bot', 'yandex', 'pinterest', 'duckduckbot', 'telegrambot',
            'linkedinbot', 'whatsapp', 'twitterbot', 'applebot'
        ];

        foreach ($botSignatures as $signature) {
            if (str_contains($uaLower, $signature)) {
                $data['is_bot'] = true;
                $data['bot_name'] = $signature;
                break;
            }
        }

        // 🧪 Additional headers-based heuristics
        if (
            $request->headers->get('X-Requested-With') === 'XMLHttpRequest' ||
            stripos($request->headers->get('Accept', ''), 'application/json') !== false
        ) {
            // Could be AJAX – not necessarily a bot, but can be noted if needed
            $data['ajax_request'] = true;
        }

        $this->buffer->addEntry($data);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}

