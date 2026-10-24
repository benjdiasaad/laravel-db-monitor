# Laravel DB Monitor — Real-Time Database Performance Monitoring, Slow Query Detection & N+1 Analysis

Laravel DB Monitor is a real-time database performance monitoring package for Laravel applications.
It automatically detects **slow queries**, **N+1 query problems**, **duplicate queries**, **query-heavy requests**, and **missing indexes**. It points you to the exact line of code that ran each query, suggests fixes, and can generate index migrations for you.

> A production-ready Laravel performance monitoring tool for Laravel 12 & 13.

![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-red?style=flat-square&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue?style=flat-square&logo=php)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

---

## ✨ Features

- 🐢 Detect slow database queries in real time
- 🔁 Identify N+1 query problems automatically
- ♻️ Catch duplicate queries (same SQL + same bindings run over and over)
- 📈 Flag requests that blow their query budget
- 📭 Suggest missing indexes based on real usage
- 📍 Show the **file and line** in your app that ran the query
- 💡 Show actionable fix suggestions
- 🔧 Generate index migrations automatically (with `--dry-run`)
- 🧪 Test assertions: `assertNoNPlusOneQueries()`, `assertMaxQueries()`, and more
- 🤖 CI-friendly reports: `db:report --json` and `--fail-on=critical`
- ⚙️ Queue job monitoring, sampling, and zero-latency analysis after the response is sent
- 📬 Throttled alerts via Email, Slack, or any notification channel
- 🔔 `FindingRecorded` event to plug into Sentry, logs, or tickets
- 🧩 Custom detectors
- 🧹 Auto-prune old logs using Laravel's Prunable trait
- ⚡ Works with MySQL, MariaDB, PostgreSQL, SQLite, SQL Server

---

## ✅ Requirements

- PHP 8.3+
- Laravel 12 or 13
- Any Laravel-supported database

> Need Laravel 10 / 11 or PHP 8.2? Use the `1.x` release: `composer require benjdiasaad/laravel-db-monitor:^1.0`

---

## 📦 Installation

```bash
composer require benjdiasaad/laravel-db-monitor
```

Publish config & migrations:

```bash
php artisan vendor:publish --tag=db-monitor-config
php artisan vendor:publish --tag=db-monitor-migrations
php artisan migrate
```

---

## ⚙️ Register the Middleware

### Option 1 — automatic

Set this in `.env` and the middleware is pushed onto the global HTTP stack for you:

```env
DB_MONITOR_AUTO_MIDDLEWARE=true
```

### Option 2 — manual, in `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \BenjdiaSaad\DbMonitor\Http\Middleware\MonitorQueries::class,
    ]);
})
```

Or on specific routes with the `db.monitor` alias:

```php
Route::middleware('db.monitor')->group(function () {
    // ...
});
```

Analysis runs in the middleware's `terminate()` step, **after the response has been sent**, so users don't wait on it. Monitoring errors are reported with `report()` and never break the request.

### Monitor queued jobs

```env
DB_MONITOR_JOBS=true
```

Each job is monitored as its own scope. Its findings show up with a path like `job:App\Jobs\SendInvoice`.

---

## 🔧 Configuration

Add to `.env`:

```env
DB_MONITOR_ENABLED=true
DB_MONITOR_SAMPLE_RATE=1.0
DB_MONITOR_SLOW_THRESHOLD=500
DB_MONITOR_N1_THRESHOLD=10
DB_MONITOR_DUPLICATE_THRESHOLD=3
DB_MONITOR_MAX_QUERIES=50
DB_MONITOR_INDEX_THRESHOLD=50
DB_MONITOR_CAPTURE_LOCATION=true
DB_MONITOR_NOTIFY=admin@yourapp.com
DB_MONITOR_NOTIFY_SEVERITY=critical
DB_MONITOR_NOTIFY_THROTTLE=60
DB_MONITOR_RETENTION=7
```

Config file: `config/db-monitor.php`

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Master switch |
| `sample_rate` | `1.0` | Fraction of requests/jobs to monitor (e.g. `0.1` = 10%) |
| `slow_query_threshold_ms` | `500` | A query slower than this is a warning; 5× slower is critical |
| `n_plus_one_threshold` | `10` | Same query *shape* repeated this many times = N+1 |
| `duplicate_query_threshold` | `3` | *Identical* query + bindings repeated this many times |
| `max_queries_per_request` | `50` | Query budget per request/job (`0` disables) |
| `missing_index_min_occurrences` | `50` | WHERE-column usage before an index is suggested |
| `detectors` | all `true` | Turn individual built-in detectors on/off |
| `custom_detectors` | `[]` | Your own detector classes |
| `store_queries` | `true` | Store raw queries in `db_monitor_query_logs` |
| `capture_location` | `true` | Record the app `file:line` that ran each query |
| `auto_register_middleware` | `false` | Push the middleware globally |
| `monitor_jobs` | `false` | Monitor queued jobs too |
| `notify` | `null` | Email address, or `['mail' => '...', 'slack' => '...']` |
| `notification_channels` | `['mail']` | Channels used by the notification |
| `notify_min_severity` | `critical` | `warning` or `critical` |
| `notification_throttle_minutes` | `60` | Don't repeat an alert for the same issue within this window |
| `retention_days` | `7` | Used by `model:prune` |
| `exclude_paths` | Telescope, Debugbar, Horizon, Livewire, Pulse | Paths that are never monitored |
| `ignore_queries` | `[]` | Regexes. Matching SQL is ignored |

---

## 🚀 Usage

### Generate a Database Health Report

```bash
php artisan db:report
```

Example output:

```
DB Monitor Report — Last 24 hours

▶ N PLUS ONE
  ● Potential N+1 detected: same query ran 47 times (total: 312ms)
    Path: api/orders
    Source: app/Http/Controllers/OrderController.php:42
    💡 Add eager loading to avoid 47 queries:
    💡     Model::with('user')->get()

▶ DUPLICATE QUERY
  ● Duplicate query: identical query ran 6 times (total: 18ms)
    Source: app/Services/Settings.php:17
    💡 The exact same query ran 6 times. Reuse the result:
    💡     once(fn () => ...) or Cache::remember('key', 60, fn () => ...)

▶ MISSING INDEX
  ● Possible missing index on `orders`.`user_id` — used in 230 queries
    💡 Auto-generate the migration:
    💡     php artisan db:fix --table=orders --column=user_id
```

### Filter Reports

```bash
php artisan db:report --hours=48
php artisan db:report --severity=critical
php artisan db:report --type=n_plus_one
```

### Use It in CI

```bash
# Machine-readable output
php artisan db:report --json > db-report.json

# Exit code 1 if any critical finding exists
php artisan db:report --fail-on=critical
```

---

### Auto-Fix Missing Indexes

```bash
php artisan db:fix --table=orders --column=user_id
php artisan migrate
```

Fix all at once, or preview first:

```bash
php artisan db:fix --all --dry-run
php artisan db:fix --all
php artisan migrate
```

`db:fix` skips tables or columns that don't exist, columns that already have an index, and migrations it already generated.

---

### Analyze Stored Logs

```bash
php artisan db:analyze --hours=24
```

Per-request detectors (slow, N+1, duplicate, too many queries) run on each stored request separately. Missing-index detection runs across the whole window.

---

### Clear Logs

```bash
php artisan db:clear --days=7
php artisan db:clear --all
```

---

## 🧪 Testing Helpers

Catch N+1s and query regressions in your test suite, before they reach production.

```php
use BenjdiaSaad\DbMonitor\Testing\InteractsWithDbMonitor;

class OrderTest extends TestCase
{
    use InteractsWithDbMonitor;

    public function test_orders_page_is_efficient(): void
    {
        $this->assertNoNPlusOneQueries(fn () => $this->get('/orders'));

        $this->assertMaxQueries(10, fn () => $this->get('/dashboard'));

        $this->assertNoDuplicateQueries(fn () => $this->get('/settings'));

        $this->assertNoSlowQueries(fn () => $this->get('/reports'), thresholdMs: 200);
    }
}
```

With Pest:

```php
uses(InteractsWithDbMonitor::class);

it('loads orders without N+1', function () {
    $this->assertNoNPlusOneQueries(fn () => $this->get('/orders'));
});
```

When an assertion fails, the message lists the offending SQL and the `file:line` that ran it. These helpers work even when `DB_MONITOR_ENABLED=false`.

---

## 🔬 Profiling Code

```php
use BenjdiaSaad\DbMonitor\Facades\DbMonitor;

$profile = DbMonitor::profile(fn () => $orderService->export());

$profile->queryCount();          // 128
$profile->totalTimeMs();         // 940
$profile->hasIssues();           // true
$profile->hasIssues('n_plus_one');
$profile->findings;              // detector findings
$profile->queries;               // raw queries (sql, bindings, duration_ms, location, ...)
```

Profiling doesn't store anything or send notifications.

---

## 📬 Notifications

Enable email alerts:

```env
DB_MONITOR_NOTIFY=admin@yourapp.com
DB_MONITOR_NOTIFY_SEVERITY=critical   # or "warning"
DB_MONITOR_NOTIFY_THROTTLE=60         # minutes between alerts for the same issue
```

Send to several channels by mapping each channel to its route in `config/db-monitor.php`:

```php
'notify' => [
    'mail' => 'admin@yourapp.com',
    'slack' => env('DB_MONITOR_SLACK_WEBHOOK'),
],

'notification_channels' => ['mail', 'slack'],
```

Slack requires `laravel/slack-notification-channel`.

---

## 🔔 Events

Every stored finding dispatches `BenjdiaSaad\DbMonitor\Events\FindingRecorded`:

```php
use BenjdiaSaad\DbMonitor\Events\FindingRecorded;
use Illuminate\Support\Facades\Event;

Event::listen(function (FindingRecorded $event) {
    logger()->warning($event->finding->message, $event->finding->context);
});
```

---

## 🧩 Custom Detectors

Implement `DetectorInterface` and register it in the config:

```php
use BenjdiaSaad\DbMonitor\Detectors\DetectorInterface;

class SelectStarDetector implements DetectorInterface
{
    public function analyze(array $queries): array
    {
        return collect($queries)
            ->filter(fn ($q) => str_starts_with(strtolower($q['sql']), 'select *'))
            ->map(fn ($q) => [
                'type' => 'select_star',
                'severity' => 'warning',
                'message' => 'SELECT * used',
                'context' => ['sql' => $q['sql'], 'location' => $q['location'] ?? null],
            ])
            ->values()
            ->all();
    }
}
```

```php
'custom_detectors' => [
    App\Monitoring\SelectStarDetector::class,
],
```

---

## 🧰 Facade Usage

```php
use BenjdiaSaad\DbMonitor\Facades\DbMonitor;

$profile = DbMonitor::profile(fn () => ...);

$findings = DbMonitor::runDetectors($queries);

$analysis = DbMonitor::analyzeStoredLogs(hours: 48);
```

---

## 🛠 Artisan Commands

| Command | Description |
|---------|------------|
| `db:report` | Show database health report |
| `db:report --hours=48` | Report for last 48 hours |
| `db:report --severity=critical` | Only critical issues |
| `db:report --type=n_plus_one` | Only N+1 findings |
| `db:report --json` | JSON output |
| `db:report --fail-on=critical` | Exit code 1 if critical issues exist (CI) |
| `db:analyze` | Analyze stored logs |
| `db:fix --table=x --column=y` | Generate index migration |
| `db:fix --all` | Fix all missing indexes |
| `db:fix --all --dry-run` | Preview generated migrations |
| `db:clear --days=7` | Clear old logs |
| `db:clear --all` | Clear everything |

---

## 🗄 Database Tables

### `db_monitor_query_logs`

Stores every captured query per request.

- sql
- bindings
- duration_ms
- connection
- request_id
- request_path
- request_method
- location

### `db_monitor_findings`

Stores detected issues.

- type (`slow_query`, `n_plus_one`, `duplicate_query`, `too_many_queries`, `missing_index`)
- severity (`warning`, `critical`)
- message
- context (json)
- request_path
- notified

---

## 🧹 Auto-Pruning

Both tables use Laravel's `Prunable` trait. Because the models live in this package, pass them to `model:prune` explicitly:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [
        \BenjdiaSaad\DbMonitor\Models\QueryLog::class,
        \BenjdiaSaad\DbMonitor\Models\DbFinding::class,
    ],
])->daily();
```

Or simply:

```php
Schedule::command('db:clear --days=7')->daily();
```

---

## ⬆️ Upgrading from 1.x to 2.0

1. Make sure you're on **PHP 8.3+** and **Laravel 12 or 13**.
2. Publish and run the new migration, which adds the `location` column:

   ```bash
   php artisan vendor:publish --tag=db-monitor-migrations
   php artisan migrate
   ```

3. Optionally re-publish the config to get the new options (`--force` overwrites your copy, so merge by hand if you customized it).
4. Two new detectors are on by default: `duplicate_query` and `too_many_queries`. Turn them off under `detectors` if you don't want them.
5. Notifications are now throttled (60 minutes per issue by default).

---

## 🎯 Why Use Laravel DB Monitor?

Unlike traditional profilers, Laravel DB Monitor:

- Stores historical query patterns
- Detects recurring performance issues
- Points to the exact line of code responsible
- Suggests fixes automatically
- Can generate index migrations for you
- Guards against regressions in your test suite and CI
- Works in production environments (with sampling)

It helps you catch database problems before your users experience slow pages.

---

## 🧪 Running the Package Tests

```bash
composer install
composer test
```

---

## 📄 License

MIT — free for personal and commercial use.

---

## 👤 Author

**Benjdia Saad**
GitHub: https://github.com/benjdiasaad

---

> Built to help Laravel developers optimize database performance before it becomes a problem.
