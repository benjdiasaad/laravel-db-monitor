<?php

namespace BenjdiaSaad\DbMonitor;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use BenjdiaSaad\DbMonitor\Commands\DbAnalyzeCommand;
use BenjdiaSaad\DbMonitor\Commands\DbClearCommand;
use BenjdiaSaad\DbMonitor\Commands\DbReportCommand;

class DbMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/db-monitor.php', 'db-monitor');

        $this->app->singleton(QueryRecorder::class, fn () => new QueryRecorder());

        $this->app->singleton(DbMonitor::class, fn ($app) => new DbMonitor(
            $app->make(QueryRecorder::class)
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/db-monitor.php' => config_path('db-monitor.php'),
            ], 'db-monitor-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'db-monitor-migrations');

            $this->commands([DbReportCommand::class, DbAnalyzeCommand::class, DbClearCommand::class]);
        }

        if (! config('db-monitor.enabled', true)) {
            return;
        }

        $recorder = $this->app->make(QueryRecorder::class);
        
        Event::listen(QueryExecuted::class, fn (QueryExecuted $e) => $recorder->record($e));

        $this->app['router']->aliasMiddleware('db.monitor', Http\Middleware\MonitorQueries::class);
    }
}