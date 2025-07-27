# 🕵️ Beast Visitor Tracker Bundle

A modern, developer-friendly, privacy-aware Symfony bundle for tracking, analyzing, and auditing traffic to your app or site — with zero JavaScript, no cookies, and full CLI access.

> Built for privacy-first analytics, sysadmin diagnostics, devops monitoring, and marketing insights — from a single log source.
>
> Note: This tool is not designed to replace Google Analytics or enterprise-grade analytics suites. It shines in the early stages of development, during MVP testing, or in debugging phases where performance insights, traffic logs, and CLI-driven analytics are more valuable than dashboards. It prioritizes simplicity, speed, and visibility for developers and teams.

---

## ✨ Features

🚀 Features
🧾 File-Based Logging
Tracks each request in structured .log files — no database needed.

- ✅ Rich Visitor Metadata
  Captures:
  - IP address (anonymized if enabled)
  - Browser, OS, device type
  - Referrer, UTM parameters
  - Country, city, ISP (via optional geo API)
  - Auth status, route name, HTTP status
  - Request duration, memory usage, response size
  - Bot detection and unique visitor fingerprinting
- ⚙️ CLI-First Analytics
  Everything runs from the Symfony CLI — no external dashboards, no browser needed.
- 📊 Purpose-Driven Tools
  Tailored commands for:
  - Sysadmin: detect warnings, high memory usage, and timeouts
  - DevOps: status code trends, duration spikes, CDN usage
  - Marketing: campaign UTM performance and visitor source summaries
  - Developers: route usage, controller profiling
- 📈 Real-Time Monitoring
  Stream logs live using visitor:tail and analyze hot traffic without delay.
- 🧠 Smart Aggregation
  Group by route, URI, hour, date, browser, country, UTM source, and more.
- 🔒 Privacy & Compliance Ready
  - No cookies or sessions
  - IP masking and optional geolocation
  - Consent-free operation (GDPR/CCPA friendly by default)
- 📂 Minimal Setup, Zero Overhead
  Plug-and-play with Symfony. Just install and go — logging starts immediately.

---

## 🚀 Installation

```bash
composer require beast/visitor-tracker-bundle
```

If you're not using Symfony Flex, manually register the bundle:

```php
// config/bundles.php
return [
    Beast\VisitorTrackerBundle\BeastVisitorTrackerBundle::class => ['all' => true],
];
```

```yaml
// config/packages/beast_visitor_tracker.yaml
beast_visitor_tracker:
  geo_enabled: false         # Disable geo API calls for privacy
  ip_anonymize: true         # Mask last part of IP address
  log_dir: '%kernel.project_dir%/var/visitor_tracker/logs'
```

---

## 🧠 How It Works

Every main request triggers the logger:

```php
Beast\VisitorTrackerBundle\EventSubscriber\VisitorLoggerSubscriber
```

...and at termination, enriches the last matching log entry with:

* request duration
* memory usage
* HTTP status
* route name
* content type
* authentication status

Log entries are stored per day:

```bash
var/visitor_tracker/logs/YYYY-MM-DD.log
```

Example entry:

```json
{
    "date": "2025-07-27 11:31:25",
    "ip": "127.0.0.1",
    "uri": "\/serial-number?utm_source=facebook&utm_campaign=summer",
    "method": "GET",
    "user_agent": "Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/136.0.0.0 Safari\/537.36",
    "visitor_id": "574136fe4bbfcbdf48c98e38e50604cbd448e9d9",
    "referrer": null,
    "referrer_domain": null,
    "referrer_path": null,
    "country": "unknown",
    "city": null,
    "isp": null,
    "browser": "Chrome",
    "os": "Linux",
    "device": "desktop",
    "is_bot": false,
    "utm": {
        "utm_source": "facebook",
        "utm_campaign": "summer"
    },
    "locale": "en-US",
    "duration_ms": 21.02,
    "status_code": 200,
    "route": "app_serialnumber_index",
    "memory_usage_bytes": 4194304,
    "auth": "anon",
    "content_type": "text\/html; charset=UTF-8",
    "response_size_bytes": 45423,
    "php_warnings": {
        "notice": 0,
        "warning": 0,
        "deprecated": 0,
        "error": 0
    }
}
```

---

## 🧪 CLI Commands


### visitor:tail

Real-time traffic monitor

Stream new visitor logs as they happen.

```bash
bin/console visitor:tail --follow
```

Options:
```bash
--filter=bot|utm|referrer|new|return
--date=YYYY-MM-DD
--preview=20
```


### visitor:metric

Internal system metrics from logs

Summarizes durations, memory, payload, response size, errors, and performance.

```bash
bin/console visitor:metric --from=-7days
```

Great for: performance profiling and trend detection.


### visitor:trend

Track visitor trends over time

Useful for spotting traffic spikes or drop-offs.

```bash
bin/console visitor:trend --type=requests|bots|utm --days=30
```


### visitor:sysadmin

Health check for your app/server

Scans for:
- PHP warnings & errors
- Memory spikes
- Long-running requests
- Unexpected status codes

```bash
bin/console visitor:sysadmin
```


### visitor:devops

Operational diagnostics

Breaks down:
- CDN or URI usage patterns
- Route-level performance
- Traffic load by hour
- Status code trends

```bash
bin/console visitor:devops
```


### visitor:snapshot

Point-in-time export

Generate a snapshot JSON of visitor data for sharing, archiving, or analysis.

```bash
bin/console visitor:snapshot --output=stats.json
```

### 📈 `visitor:cleanup`

Full dashboard for the last 30 days.

```bash
php bin/console visitor:stats
```

Includes:

* Total / unique / returning / bot visitors
* Hourly & daily charts
* Country, city, browser, device, OS, UTM, referrer stats
* Top visited pages
* Weekly aggregates

---

## 💡 Use Cases

* Lightweight, private web analytics
* Monitoring APIs/microservices
* Campaign/UTM effectiveness analysis
* Debugging slow or memory-hungry routes
* GDPR-safe internal dashboards

---

## 🔐 Privacy & Compliance

We collect only:

* IP (anonymized if enabled)
* User-Agent
* UTM/referrer
* Route/URI & status
* Duration & memory usage (no personal data)

✅ No cookies, sessions, or user tracking unless you add it manually.
✅ Fully usable without consent banners.
❗ Geolocation via `ipapi.co` can be disabled.

---

## 👤 Author

Michael Holm Kristensen
Part of the Clubmaster GmbH ecosystem
🔗 [github.com/hollodk](https://github.com/hollodk)

---

## 📄 License

MIT — Use it freely, fork it proudly.
