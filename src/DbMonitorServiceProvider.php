<?php

namespace BenjdiaSaad\DbMonitor;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use BenjdiaSaad\DbMonitor\Commands\DbAnalyzeCommand;
use BenjdiaSaad\DbMonitor\Commands\DbClearCommand;
use BenjdiaSaad\DbMonitor\Commands\DbReportCommand;
use BenjdiaSaad\DbMonitor\Commands\DbFixCommand;
use BenjdiaSaad\DbMonitor\Http\Middleware\MonitorQueries;

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

            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'db-monitor-migrations');

            $this->commands([DbReportCommand::class, DbAnalyzeCommand::class, DbClearCommand::class, DbFixCommand::class]);
        }

        $this->app['router']->aliasMiddleware('db.monitor', MonitorQueries::class);

        // Always listen: the recorder itself decides whether to keep a query,
        // which lets DbMonitor::profile() work even when monitoring is off.
        $recorder = $this->app->make(QueryRecorder::class);

        Event::listen(QueryExecuted::class, fn (QueryExecuted $e) => $recorder->record($e));

        if (! config('db-monitor.enabled', true)) {
            return;
        }

        if (config('db-monitor.auto_register_middleware', false) && $this->app->bound(HttpKernel::class)) {
            $this->app->make(HttpKernel::class)->pushMiddleware(MonitorQueries::class);
        }

        if (config('db-monitor.monitor_jobs', false)) {
            $this->registerJobMonitoring($recorder);
        }
    }

    private function registerJobMonitoring(QueryRecorder $recorder): void
    {
        Event::listen(JobProcessing::class, fn (JobProcessing $e) => $recorder->start('job:' . $e->job->resolveName(), 'JOB'));

        $finish = function () use ($recorder) {
            $this->app->make(DbMonitor::class)->processRequest();
            $recorder->stop();
        };

        Event::listen(JobProcessed::class, $finish);
        Event::listen(JobExceptionOccurred::class, $finish);
    }
}
