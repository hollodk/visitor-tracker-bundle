# 🕵️ Beast Visitor Tracker Bundle

A simple and powerful Symfony bundle for tracking visitors on your website. It logs useful visitor data to daily JSON log files and comes with CLI tools to monitor traffic and generate aggregated statistics with charts and summaries.

---

## 📦 Features

- Logs visitor info on each HTTP request:
  - IP, user-agent, URI, referrer, UTM params
  - Device type, browser, OS, country, city, ISP
  - Bot detection and visitor fingerprinting
- Daily rotating log files
- Real-time log tailing with filtering options
- CLI statistics summary with:
  - Daily/weekly unique and returning visitors
  - Device, OS, browser usage
  - Top UTM sources, campaigns, pages, referrers
  - Country and city distribution

---

## 🚀 Installation

1. Require the bundle in your Symfony project:

```bash
composer require beast/visitor-tracker-bundle

Register the bundle if you're not using Symfony Flex:

```php
// config/bundles.php
return [
    // ...
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

## 📝 Usage

### Visitor Logging

Once installed, the bundle will automatically log every incoming main request (excluding Symfony internals like /_profiler, etc.).

Log files are saved in:

```bash
/var/visitor_tracker/logs/YYYY-MM-DD.log
```

Each line is a JSON object containing visitor metadata.

---

### 👀 Tail Visitor Logs

```bash
php bin/console visitor:tail --follow
```

Options:

- --date=YYYY-MM-DD – Tail a specific date's log
- --follow or -f – Real-time mode (like tail -f)
- --preview=10 – Show last 10 entries
- --filter=bot|utm|referrer|new|return – Filter specific entries

---

## 📈 View Statistics

```bash
php bin/console visitor:stats
```

This command parses all log files and shows:
- Total visits, unique/returning visitors
- Hourly/daily/weekly traffic
- Most common browsers, OS, devices
- Referrers, UTM sources & campaigns
- Country/city breakdown
- Top visited pages

Charts and tables are rendered directly in the CLI using SymfonyStyle.

---

## 🧠 Example Log Entry

```json
{
  "date": "2025-07-20 12:34:56",
  "ip": "123.123.123.123",
  "uri": "/products/42",
  "user_agent": "Mozilla/5.0...",
  "visitor_id": "sha1 hash",
  "referrer": "https://google.com",
  "country": "Germany",
  "city": "Berlin",
  "isp": "Deutsche Telekom",
  "browser": "Chrome",
  "os": "Windows",
  "device": "desktop",
  "is_bot": false,
  "utm": {
    "utm_source": "newsletter",
    "utm_campaign": "summer-sale"
  }
}
```

---

## 📂 File Structure

- EventSubscriber/VisitorLoggerSubscriber.php – Logs each request
- Command/VisitorTailCommand.php – Real-time or previewed visitor log reader
- Command/VisitorStatsCommand.php – CLI stats and traffic visualizer

---

## 🛠 Roadmap Ideas

- Database driver (e.g., Doctrine or SQLite)
- Web dashboard for viewing stats
- More advanced bot/device detection
- Configurable exclusions

---

## 🧑‍💻 Author
Michael Holm Kristensen – github.com/hollodk
Part of the Clubmaster GmbH ecosystem.

---

## 📄 License

MIT License. Use it freely and modify as needed.
