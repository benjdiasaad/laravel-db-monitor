# Laravel DB Monitor — Real-Time Database Performance Monitoring, Slow Query Detection & N+1 Analysis

Laravel DB Monitor is a real-time database performance monitoring package for Laravel applications.  
It automatically detects **slow queries**, **N+1 query problems**, and **missing indexes**, then suggests SQL optimizations and can even generate index migrations for you.

>  A production-ready Laravel performance monitoring tool for Laravel 10, 11 & 12.

![Laravel](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012-red?style=flat-square&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue?style=flat-square&logo=php)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

---

## ✨ Features

- 🐢 Detect slow database queries in real time
- 🔁 Identify N+1 query problems automatically
- 📭 Suggest missing indexes based on real usage
- 💡 Show actionable fix suggestions
- 🔧 Generate index migrations automatically
- 📬 Send alerts via Email or Slack
- 📋 Clean CLI reporting (`db:report`)
- 🧹 Auto-prune old logs using Laravel’s Prunable trait
- ⚡ Works with MySQL, PostgreSQL, SQLite

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

## 🔧 Configuration

Add to `.env`:

```env
DB_MONITOR_ENABLED=true
DB_MONITOR_SLOW_THRESHOLD=500
DB_MONITOR_N1_THRESHOLD=10
DB_MONITOR_INDEX_THRESHOLD=50
DB_MONITOR_NOTIFY=admin@yourapp.com
DB_MONITOR_RETENTION=7
```

Config file: `config/db-monitor.php`

```php
return [

    'enabled' => env('DB_MONITOR_ENABLED', true),

    'slow_query_threshold_ms' => env('DB_MONITOR_SLOW_THRESHOLD', 500),

    'n_plus_one_threshold' => env('DB_MONITOR_N1_THRESHOLD', 10),

    'missing_index_min_occurrences' => env('DB_MONITOR_INDEX_THRESHOLD', 50),

    'store_queries' => env('DB_MONITOR_STORE_QUERIES', true),

    'retention_days' => env('DB_MONITOR_RETENTION', 7),

    'notify' => env('DB_MONITOR_NOTIFY', null),

    'notification_channels' => ['mail'],

    'exclude_paths' => [
        'telescope/*',
        '_debugbar/*',
        'horizon/*',
        'livewire/*',
    ],
];
```

---

## 🚀 Usage

### Generate a Database Health Report

```bash
php artisan db:report
```

Example output:

```
DB Monitor Report — Last 24 hours

▶ SLOW QUERY
  Slow query detected: 2300ms
  Path: api/orders
  💡 Suggestion: Use select() instead of SELECT *
  💡 Add index:
  php artisan db:fix --table=orders --column=user_id

▶ N+1 QUERY
  Same query executed 47 times
  💡 Suggestion: Model::with('user')->get()

▶ MISSING INDEX
  Column orders.user_id used in 230 queries
  💡 Auto-generate migration:
  php artisan db:fix --table=orders --column=user_id
```

---

### Filter Reports

```bash
php artisan db:report --hours=48
php artisan db:report --severity=critical
php artisan db:report --type=n_plus_one
```

---

### Auto-Fix Missing Indexes

Generate migration:

```bash
php artisan db:fix --table=orders --column=user_id
php artisan migrate
```

Fix all at once:

```bash
php artisan db:fix --all
php artisan migrate
```

---

### Analyze Stored Logs

```bash
php artisan db:analyze --hours=24
```

---

### Clear Logs

```bash
php artisan db:clear --days=7
php artisan db:clear --all
```

---

## 📬 Notifications

Enable email alerts:

```env
DB_MONITOR_NOTIFY=admin@yourapp.com
```

Enable Slack notifications in `config/db-monitor.php`:

```php
'notification_channels' => ['slack'],
```

Configure Slack in `config/services.php`:

```php
'slack' => [
    'notifications' => [
        'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
],
```

---

## 🧰 Facade Usage

```php
use BenjdiaSaad\DbMonitor\Facades\DbMonitor;

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
| `db:analyze` | Analyze stored logs |
| `db:fix --table=x --column=y` | Generate index migration |
| `db:fix --all` | Fix all missing indexes |
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

### `db_monitor_findings`

Stores detected issues.

- type (`slow_query`, `n_plus_one`, `missing_index`)
- severity (`warning`, `critical`)
- message
- context (json)
- request_path
- notified

---

## 🧹 Auto-Pruning

Both tables use Laravel's `Prunable` trait.

Run manually:

```bash
php artisan model:prune
```

Schedule daily:

```php
Schedule::command('model:prune')->daily();
```

---

## ✅ Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Any Laravel-supported database

---

## 🎯 Why Use Laravel DB Monitor?

Unlike traditional profilers, Laravel DB Monitor:

- Stores historical query patterns
- Detects recurring performance issues
- Suggests fixes automatically
- Can generate index migrations for you
- Works in production environments

It helps you catch database problems before your users experience slow pages.

---

## 📄 License

MIT — free for personal and commercial use.

---

## 👤 Author

**Benjdia Saad**  
GitHub: https://github.com/benjdiasaad

---

> Built to help Laravel developers optimize database performance before it becomes a problem.