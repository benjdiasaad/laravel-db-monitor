<?php

namespace BenjdiaSaad\DbMonitor\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use BenjdiaSaad\DbMonitor\DbMonitorServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [DbMonitorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        $app['config']->set('db-monitor.slow_query_threshold_ms', 500);
        
        $app['config']->set('db-monitor.n_plus_one_threshold', 5);
    }
}