<?php

namespace BenjdiaSaad\DbMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class QueryLog extends Model
{
    use Prunable;

    protected $table = 'db_monitor_query_logs';

    protected $fillable = ['sql', 'bindings', 'duration_ms', 'connection', 'request_id', 'request_path', 'request_method'];

    protected $casts    = ['bindings' => 'array'];

    public function prunable(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('created_at', '<', now()->subDays(config('db-monitor.retention_days', 7)));
    }
}