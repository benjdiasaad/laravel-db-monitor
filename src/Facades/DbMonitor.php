<?php

namespace BenjdiaSaad\DbMonitor\Facades;

use Illuminate\Support\Facades\Facade;
use BenjdiaSaad\DbMonitor\DbMonitor as DbMonitorConcrete;

/**
 * @method static void  processRequest()
 * @method static array runDetectors(array $queries)
 * @method static array analyzeStoredLogs(int $hours = 24)
 */
class DbMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DbMonitorConcrete::class;
    }
}