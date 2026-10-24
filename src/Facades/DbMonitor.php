<?php

namespace BenjdiaSaad\DbMonitor\Facades;

use Illuminate\Support\Facades\Facade;
use BenjdiaSaad\DbMonitor\DbMonitor as DbMonitorConcrete;

/**
 * @method static void  processRequest()
 * @method static \BenjdiaSaad\DbMonitor\Profile profile(callable $callback)
 * @method static array runDetectors(array $queries, bool $includeAggregate = true)
 * @method static array analyzeStoredLogs(int $hours = 24)
 * @method static \BenjdiaSaad\DbMonitor\Models\DbFinding recordFinding(array $finding, ?string $requestPath = null)
 *
 * @see \BenjdiaSaad\DbMonitor\DbMonitor
 */
class DbMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DbMonitorConcrete::class;
    }
}
