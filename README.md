# Laravel DB Monitor — Real-Time Database Performance Monitoring, Slow Query Detection & N+1 Analysis

[![Tests](https://github.com/benjdiasaad/laravel-db-monitor/actions/workflows/tests.yml/badge.svg)](https://github.com/benjdiasaad/laravel-db-monitor/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/benjdiasaad/laravel-db-monitor?style=flat-square)](https://packagist.org/packages/benjdiasaad/laravel-db-monitor)
![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-red?style=flat-square&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue?style=flat-square&logo=php)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

Laravel DB Monitor finds database performance problems in your Laravel app: **slow queries**, **N+1 queries**, **duplicate queries**, **query-heavy requests**, and **missing indexes**. It shows the exact line of code behind each query, suggests a fix, and can generate index migrations for you.

---

## 🆕 What's New in v2.0.0

- Laravel 13 support (requires PHP 8.3+ and Laravel 12 or 13)
- New detectors: duplicate queries and too many queries per request
- Source `file:line` for every query
- Test assertions: `assertNoNPlusOneQueries()`, `assertMaxQueries()`, and more
- `DbMonitor::profile()` to measure any block of code
- CI-friendly `db:report --json` and `--fail-on=critical`
- `db:fix --dry-run`, and `db:fix` now skips indexes that already exist
- Queue job monitoring, sampling, auto-registered middleware
- Analysis runs after the response is sent, so users don't wait for it
- Throttled multi-channel notifications and a `FindingRecorded` event
- Custom detectors

Upgrading from 1.x? See **Upgrading from 1.x** below. Still on Laravel 10/11? Use `^1.0`.

---

## 📦 Installation

```bash
composer require benjdiasaad/laravel-db-monitor
php artisan vendor:publish --tag=db-monitor-config
php artisan vendor:publish --tag=db-monitor-migrations
php artisan migrate
```

Turn on monitoring for every request:

```env
DB_MONITOR_AUTO_MIDDLEWARE=true
```

Or add it yourself in `bootstrap/app.php`, or per route with the `db.monitor` alias:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \BenjdiaSaad\DbMonitor\Http\Middleware\MonitorQueries::class,
    ]);
})
```

---

## 🔧 Configuration

Common `.env` options:

```env
DB_MONITOR_ENABLED=true
DB_MONITOR_SAMPLE_RATE=1.0          # 0.1 = monitor 10% of requests
DB_MONITOR_SLOW_THRESHOLD=500       # ms
DB_MONITOR_N1_THRESHOLD=10
DB_MONITOR_MAX_QUERIES=50           # query budget per request
DB_MONITOR_JOBS=false               # also monitor queued jobs
DB_MONITOR_NOTIFY=admin@yourapp.com
DB_MONITOR_NOTIFY_SEVERITY=critical
DB_MONITOR_RETENTION=7              # days
```

Every option is documented in `config/db-monitor.php`, including turning detectors on/off, ignoring queries by regex, excluding paths, and adding custom detectors.

---

## 🚀 Usage

```bash
php artisan db:report
```

```
▶ N PLUS ONE
  ● Potential N+1 detected: same query ran 47 times (total: 312ms)
    Path: api/orders
    Source: app/Http/Controllers/OrderController.php:42
    💡 Add eager loading to avoid 47 queries:
    💡     Model::with('user')->get()
```

| Command | Description |
|---------|------------|
| `db:report` | Database health report (`--hours`, `--type`, `--severity`) |
| `db:report --json` | JSON output |
| `db:report --fail-on=critical` | Exit code 1 if critical issues exist (for CI) |
| `db:analyze --hours=24` | Analyze stored query logs |
| `db:fix --table=orders --column=user_id` | Generate an index migration |
| `db:fix --all [--dry-run]` | Generate migrations for all missing indexes |
| `db:clear --days=7` / `--all` | Delete old logs and findings |

---

## 🧪 Testing Helpers

Catch N+1s and query regressions in your test suite:

```php
use BenjdiaSaad\DbMonitor\Testing\InteractsWithDbMonitor;

uses(InteractsWithDbMonitor::class);

it('loads orders efficiently', function () {
    $this->assertNoNPlusOneQueries(fn () => $this->get('/orders'));
    $this->assertMaxQueries(10, fn () => $this->get('/dashboard'));
    $this->assertNoDuplicateQueries(fn () => $this->get('/settings'));
    $this->assertNoSlowQueries(fn () => $this->get('/reports'), thresholdMs: 200);
});
```

Failures list the SQL and the `file:line` that ran it.

Profile any block of code:

```php
use BenjdiaSaad\DbMonitor\Facades\DbMonitor;

$profile = DbMonitor::profile(fn () => $orderService->export());

$profile->queryCount();          // 128
$profile->totalTimeMs();         // 940
$profile->hasIssues('n_plus_one');
```

---

## 📬 Notifications & Events

```php
// config/db-monitor.php
'notify' => [
    'mail' => 'admin@yourapp.com',
    'slack' => env('DB_MONITOR_SLACK_WEBHOOK'), // needs laravel/slack-notification-channel
],
'notification_channels' => ['mail', 'slack'],
```

The same issue alerts at most once every 60 minutes (`DB_MONITOR_NOTIFY_THROTTLE`).

Every stored finding also dispatches `BenjdiaSaad\DbMonitor\Events\FindingRecorded`, so you can log it or send it to Sentry.

---

## 🧹 Pruning Old Logs

```php
// routes/console.php
Schedule::command('db:clear --days=7')->daily();
```

---

## ⬆️ Upgrading from 1.x

1. Use PHP 8.3+ and Laravel 12 or 13.
2. Publish and run the new migration (adds the `location` column):

   ```bash
   php artisan vendor:publish --tag=db-monitor-migrations
   php artisan migrate
   ```

3. Two new detectors are on by default: `duplicate_query` and `too_many_queries`. Turn them off under `detectors` in the config if you don't want them.

---

## 📄 License

MIT — free for personal and commercial use.

**Author:** [Benjdia Saad](https://github.com/benjdiasaad)
