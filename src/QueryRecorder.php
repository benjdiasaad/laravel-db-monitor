<?php

namespace BenjdiaSaad\DbMonitor;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

class QueryRecorder
{
    private array   $queries       = [];
    private ?string $requestId     = null;
    private ?string $requestPath   = null;
    private ?string $requestMethod = null;

    public function startRequest(): void
    {
        $this->requestId = (string) Str::uuid();

        $this->requestPath = request()?->path();

        $this->requestMethod = request()?->method();

        $this->queries = [];
    }

    public function record(QueryExecuted $event): void
    {
        if (! config('db-monitor.enabled', true) || $this->isMonitorQuery($event->sql)) {
            return;
        }

        $this->queries[] = [
            'sql' => $event->sql,
            'bindings' => $event->bindings,
            'duration_ms' => (int) round($event->time),
            'connection' => $event->connectionName,
            'request_id' => $this->requestId,
            'request_path' => $this->requestPath,
            'request_method' => $this->requestMethod,
        ];
    }

    public function getQueries(): array { return $this->queries; }
    public function flush(): void  { $this->queries = []; }

    private function isMonitorQuery(string $sql): bool
    {
        return str_contains($sql, 'db_monitor_');
    }
}