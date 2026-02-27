# Laravel DB Monitor

**Real-time database health monitoring for Laravel.**
Automatically detects slow queries, N+1 problems, and missing indexes — stores findings in your database and alerts you via email or Slack.

![Laravel](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012-red?style=flat-square&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue?style=flat-square&logo=php)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

---

## ✨ What It Does

| Problem | How It Helps |
|---|---|
| 🐢 Slow queries | Flags any query exceeding your threshold (default: 500ms) |
| 🔁 N+1 queries | Detects when the same query pattern runs 10+ times per request |
| 📭 Missing indexes | Suggests which columns need indexes based on real query patterns |
| 📬 Alerts | Sends email or Slack notifications for critical findings |
| 📋 Reports | Clean CLI report via `php artisan db:report` |

---

## Installation

```bash
composer require benjdiasaad/laravel-db-monitor
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=db-monitor-config
php artisan vendor:publish --tag=db-monitor-migrations
php artisan migrate
```

---

## Register the Middleware

### Laravel 11 / 12 — `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \BenjdiaSaad\DbMonitor\Http\Middleware\MonitorQueries::class,
    ]);
})
```

### Laravel 10 — `app/Http/Kernel.php`

```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \BenjdiaSaad\DbMonitor\Http\Middleware\MonitorQueries::class,
    ],
];
```

---

## Configuration

Add these to your `.env` file:

```env
DB_MONITOR_ENABLED=true
DB_MONITOR_SLOW_THRESHOLD=500
DB_MONITOR_N1_THRESHOLD=10
DB_MONITOR_NOTIFY=admin@yourapp.com
```

Full config file at `config/db-monitor.php`:

```php
return [
    // Enable or disable monitoring entirely
    'enabled' => env('DB_MONITOR_ENABLED', true),

    // Queries slower than this (ms) are flagged
    'slow_query_threshold_ms' => env('DB_MONITOR_SLOW_THRESHOLD', 500),

    // Flag as N+1 if same query pattern runs more than this per request
    'n_plus_one_threshold' => env('DB_MONITOR_N1_THRESHOLD', 10),

    // Suggest index if column appears in WHERE clauses this many times
    'missing_index_min_occurrences' => env('DB_MONITOR_INDEX_THRESHOLD', 50),

    // Save raw query logs to DB (set false to save disk space)
    'store_queries' => env('DB_MONITOR_STORE_QUERIES', true),

    // Delete logs older than N days
    'retention_days' => env('DB_MONITOR_RETENTION', 7),

    // Email address to receive critical alerts (null = disabled)
    'notify' => env('DB_MONITOR_NOTIFY', null),

    // Notification channels: 'mail', 'slack'
    'notification_channels' => ['mail'],

    // These routes will NOT be monitored
    'exclude_paths' => [
        'telescope/*',
        '_debugbar/*',
        'horizon/*',
        'livewire/*',
    ],
];
```

---

## Usage

### View a health report

```bash
php artisan db:report
```

```
  DB Monitor Report — Last 24 hours

  ▶ SLOW QUERY
    ●  Slow query detected: 2300ms (threshold: 500ms)
       Path: api/orders
    ●  Slow query detected: 890ms (threshold: 500ms)
       Path: admin/reports

  ▶ N+1 QUERY
    ●  Potential N+1 detected: same query ran 47 times (total: 235ms)
       Path: products/list

  ▶ MISSING INDEX
    ●  Possible missing index on `orders`.`user_id` — used in 230 queries

 Type           | Warning | Critical | Total
----------------|---------|----------|------
 Slow Query     |    4    |    1     |   5
 N+1 Query      |    2    |    0     |   2
 Missing Index  |    2    |    0     |   2
```

### Filter the report

```bash
# Last 48 hours only
php artisan db:report --hours=48

# Only critical findings
php artisan db:report --severity=critical

# Only N+1 findings
php artisan db:report --type=n_plus_one
```

### Analyze stored logs

```bash
php artisan db:analyze --hours=24
```

### Clear old logs

```bash
# Delete logs older than 7 days
php artisan db:clear --days=7

# Delete everything
php artisan db:clear --all
```

---

## Notifications

Set `DB_MONITOR_NOTIFY` in your `.env` to receive **email alerts** whenever a critical issue is detected (a query 5x over your threshold, or N+1 with 30+ repetitions).

```env
DB_MONITOR_NOTIFY=admin@yourapp.com
```

For **Slack**, update `config/db-monitor.php`:

```php
'notification_channels' => ['slack'],
```

And configure your Slack webhook in `config/services.php`:

```php
'slack' => [
    'notifications' => [
        'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
],
```

---

## Facade

You can also use the package directly via the facade:

```php
use BenjdiaSaad\DbMonitor\Facades\DbMonitor;

// Manually run detectors on any set of queries
$findings = DbMonitor::runDetectors($queries);

// Analyze stored logs from the last 48 hours
$findings = DbMonitor::analyzeStoredLogs(hours: 48);
```

---

## Database Tables

The package creates two tables:

**`db_monitor_query_logs`** — stores every recorded query per request.

| Column | Type | Description |
|---|---|---|
| `sql` | text | The raw SQL |
| `bindings` | json | Query bindings |
| `duration_ms` | bigint | Execution time in ms |
| `connection` | string | DB connection name |
| `request_id` | string | UUID grouping all queries in one request |
| `request_path` | string | The URL path |

**`db_monitor_findings`** — stores detected issues.

| Column | Type | Description |
|---|---|---|
| `type` | string | `slow_query`, `n_plus_one`, `missing_index` |
| `severity` | string | `warning` or `critical` |
| `message` | text | Human-readable description |
| `context` | json | Full details (sql, count, threshold, etc.) |
| `request_path` | string | The URL where it was detected |
| `notified` | boolean | Whether an alert was sent |

---

## Auto-pruning

Both tables use Laravel's `Prunable` trait. Logs older than `retention_days` (default: 7) are automatically deleted when you run:

```bash
php artisan model:prune
```

Add this to your scheduler in `routes/console.php` to run it daily:

```php
Schedule::command('model:prune')->daily();
```

---

## Requirements

- PHP **8.2+**
- Laravel **10, 11, or 12**
- Any database supported by Laravel (MySQL, PostgreSQL, SQLite)

---

## 📄 License

MIT — free to use in personal and commercial projects.

---

## Author

**Benjdia Saad** — [github.com/benjdiasaad](https://github.com/benjdiasaad)

---

> Built to help Laravel developers catch database problems before their users do.