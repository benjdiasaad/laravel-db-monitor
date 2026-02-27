<?php

namespace BenjdiaSaad\DbMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class DbFinding extends Model
{
    use Prunable;

    protected $table = 'db_monitor_findings';

    protected $fillable = ['type', 'severity', 'message', 'context', 'request_path', 'notified'];
    
    protected $casts = ['context' => 'array', 'notified' => 'boolean'];

    public function prunable(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('created_at', '<', now()->subDays(config('db-monitor.retention_days', 30)));
    }

    public function scopeSlowQueries($query) { return $query->where('type', 'slow_query'); }
    public function scopeNPlusOne($query) { return $query->where('type', 'n_plus_one'); }
    public function scopeUnnotified($query) { return $query->where('notified', false); }
}