# 🕵️ Beast Visitor Tracker Bundle

A modern, privacy-aware Symfony bundle for tracking and analyzing visitors on your website or app.
No cookies. No JavaScript. No third-party analytics. Just clean, structured logs and CLI insights.

📦 File-based, no database required
📈 Real-time CLI tools: live traffic, slow route detection, memory usage, historical stats, weekly comparisons
🛡️ GDPR/CCPA friendly by default

---

## ✨ Features

- ✅ Logs each visitor request to a daily JSON file
- 📍 Captures:
  - IP, browser, OS, device type
  - Referrer and UTM parameters
  - Country, city, ISP (via ipapi.co)
  - Bot detection, visitor fingerprinting
  - Request duration, memory usage, route name
  - Auth status and HTTP response code
- 📊 Built-in CLI tools:
  - visitor:stats → analytics dashboard in your terminal
  - visitor:tail → real-time monitoring with filters
  - visitor:compare → compare two date ranges side by side
  - visitor:slow → find the slowest routes/URIs by duration
  - visitor:memory → show memory usage per route/URI
- ⚙️ Zero config, no DB, log files stored in var/visitor_tracker/logs
- 🔐 Compatible with cookie-free / consent-aware environments

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

### 📈 `visitor:stats`

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

### 🔍 `visitor:tail`

Live monitor logs like `tail -f` with filters:

```bash
php bin/console visitor:tail --follow
```

Available options:

```bash
--filter=bot|utm|referrer|new|return
--preview=20
--date=YYYY-MM-DD
```

---

### 🆚 `visitor:compare`

Compare two periods side-by-side:

```bash
php bin/console visitor:compare
```

Supports `--from`, `--to`, `--vs-from`, `--vs-to`, `--top=5`

---

### 🐢 `visitor:slow`

Find the slowest routes or URIs based on average or max duration:

```bash
php bin/console visitor:slow
```

Options:

* `--from`, `--to`: date range
* `--sort=avg|max|count`
* `--auth=user|anon`
* `--status=200`
* `--uri=/product`
* `--top=10`

---

### 🧠 `visitor:memory`

Check memory usage per route or URI:

```bash
php bin/console visitor:memory
```

Options:

* `--from`, `--to`
* `--sort=avg|max|count`
* `--top=10`

---

## 📂 File Structure

* `EventSubscriber/VisitorLoggerSubscriber.php` → tracks core visitor info
* `EventSubscriber/VisitorPerformanceSubscriber.php` → enriches with duration, memory, etc.
* `Service/VisitorLogHelper.php` → parser/aggregator
* `Command/Visitor*.php` → CLI tools

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
