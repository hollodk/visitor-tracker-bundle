# Visitor Tracker Bundle

A lightweight Symfony 7+ bundle for tracking visitors using file-based logging — no database required.

Track the following:

* ✅ Visit date and time
* ✅ IP address and country (via `ipapi.co`)
* ✅ User agent
* ✅ Visited URI
* ✅ UTM parameters (`utm_source`, `utm_medium`, `utm_campaign`, etc.)
* ✅ CLI stats report

---

## 📦 Installation

1. Add the bundle to your `src/` or use as a local Composer package.

> If inside `src/`:

```php
// config/bundles.php
return [
    Beast\VisitorTrackerBundle\VisitorTrackingBundle::class => ['all' => true],
];
```

2. (Optional) Add as a local Composer package:

```json
// composer.json (in root)
"repositories": [
  {
    "type": "path",
    "url": "src/VisitorTrackerBundle"
  }
]
```

Then run:

```bash
composer require hollo/visitor-tracker-bundle:dev-main
```

---

## 🚀 How It Works

The bundle automatically logs visitor data to:

```
var/visitor_tracker/visits.log
```

Each visit is logged as a single JSON line:

```json
{
  "date": "2025-07-20 21:30:00",
  "ip": "1.2.3.4",
  "country": "Germany",
  "uri": "/promo",
  "user_agent": "...",
  "utm": {
    "utm_source": "facebook",
    "utm_campaign": "summer_sale"
  }
}
```

---

## 🔎 CLI Stats Command

Analyze traffic with:

```bash
php bin/console visitor:stats
```

The command displays:

* Total visits
* Daily breakdown
* Top UTM sources and campaigns
* Top countries
* Most visited URIs

---

## 📁 File Structure

```
src/VisitorTrackerBundle/
├── Command/
│   └── VisitorStatsCommand.php
├── EventSubscriber/
│   └── VisitorLoggerSubscriber.php
├── VisitorTrackingBundle.php
└── composer.json
```

---

## 🔧 Configuration

* No config is required
* UTM detection works automatically if visitors use tracking links
* Country lookup is done via [ipapi.co](https://ipapi.co) using the free tier

---

## ✅ Requirements

* PHP 8.1+
* Symfony 6.4 or 7.x
* Internet access for IP geolocation

---

## 📘 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

Made with ❤️ by [Michael Holm Kristensen](https://github.com/holloDK)

