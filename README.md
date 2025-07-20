# 🕵️ Beast Visitor Tracker Bundle

A modern, file-based Symfony bundle for tracking and analyzing website visitors — perfect for small projects, privacy-aware apps, and internal tools.

📦 No database required.  
📈 Includes real-time CLI tools for tailing logs and comparing traffic.  
🇪🇺 GDPR-friendly and self-contained.

---

## ✨ Features

- ✅ Logs each visitor request to a **daily JSON file**
- 📍 Captures:
  - IP, browser, OS, device type
  - Referrer and UTM parameters
  - Country, city, ISP (via `ipapi.co`)
  - Bot detection, visitor fingerprinting
- 📊 Built-in CLI tools:
  - `visitor:stats` → analytics dashboard in your terminal
  - `visitor:tail` → real-time monitoring with filters
  - `visitor:compare` → compare two date ranges side by side
- ⚙️ Zero config, no DB, log files stored in `var/visitor_tracker/logs`
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
// config/services.yaml
services:
    Beast\VisitorTrackerBundle\:
        resource: '../vendor/beast/visitor-tracker-bundle/'
        exclude: '../vendor/beast/visitor-tracker-bundle/{Entity,Migrations,Tests}'
        autowire: true
        autoconfigure: true
```

---

## 🧠 How It Works

Every main request triggers the logger:

```php
Beast\VisitorTrackerBundle\EventSubscriber\VisitorLoggerSubscriber
```

It collects metadata from the request and stores a structured JSON entry in a file like:

```bash
var/visitor_tracker/logs/2025-07-20.log
```

Example log line:

```json
{
  "date": "2025-07-20 12:34:56",
  "ip": "123.45.67.89",
  "uri": "/products/42",
  "user_agent": "...",
  "visitor_id": "...",
  "referrer": "https://google.com",
  "country": "Germany",
  "city": "Berlin",
  "browser": "Chrome",
  "os": "Windows",
  "device": "desktop",
  "utm": {
    "utm_source": "newsletter",
    "utm_campaign": "july-sale"
  },
  "is_bot": false
}
```

---

## 🧪 CLI Commands

### 📈 visitor:stats

Show a complete traffic overview for the last 30 days:

```bash
php bin/console visitor:stats
```

Includes:

- Total / unique / returning visitors
- Hourly and daily bar charts
- Top browsers, devices, OS, cities, countries
- Referrers and UTM breakdowns

---

### 🔍 visitor:tail

Real-time log monitoring (like tail -f) with filters:

```bash
php bin/console visitor:tail --follow
```

Optional filters:

```bash
--filter=bot         # Only bots
--filter=utm         # Visitors with UTM
--filter=referrer    # Visitors with a referer
--filter=new         # First-time visitors
--filter=return      # Returning visitors
--preview=20         # Show last N entries
```
---

### 🆚 visitor:compare

Compare two time periods easily:

```bash
php bin/console visitor:compare
```

📅 Default: compares last week vs. the week before

Custom ranges:

```bash
php bin/console visitor:compare \
  --from=2025-07-01 --to=2025-07-07 \
  --vs-from=2025-07-08 --vs-to=2025-07-14
```

Shows:

- 📊 Totals (visits, unique, bots, etc.)
- 🔼 Changes in devices, browsers, referrers, campaigns
- 📄 Top pages, countries, UTM performance
- 🔧 Config & Customization (soon)

Planned:

- Option to change log path
- Pluggable geo/IP provider
- Opt-in cookie consent integration

---

## 📂 File Structure

- EventSubscriber/VisitorLoggerSubscriber.php – request tracking
- Service/VisitorLogHelper.php – shared log parser
- Command/VisitorStatsCommand.php – full traffic report
- Command/VisitorTailCommand.php – live tail CLI
- Command/VisitorCompareCommand.php – compare traffic between time ranges

---

## 💡 Use Cases

- Internal dashboards
- Monitoring microservices or APIs
- Marketing traffic audits (UTM, referrer, device data)
- Quick website insights without setting up GA or Matomo
- GDPR-friendly analytics for Europe

### 🛡️ Privacy Note

This bundle does not use cookies or persistent identifiers unless you add them. It logs IP + User-Agent + referrer, and uses a hash of those for sessionless fingerprinting.

---

## 🧑‍💻 Author

Michael Holm Kristensen
Part of the Clubmaster GmbH ecosystem
🔗 github.com/hollodk

---

## 📄 License

MIT — Use it freely, fork it proudly.
