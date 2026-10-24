<?php

namespace BenjdiaSaad\DbMonitor;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

class QueryRecorder
{
    private array   $queries       = [];
    private array   $profiles      = [];
    private bool    $active        = false;
    private ?string $requestId     = null;
    private ?string $requestPath   = null;
    private ?string $requestMethod = null;

    public function startRequest(): void
    {
        $this->start(request()?->path(), request()?->method());
    }

    /**
     * Begin a monitored scope (an HTTP request, a queued job, ...).
     */
    public function start(?string $path = null, ?string $method = null): void
    {
        $this->requestId = (string) Str::uuid();

        $this->requestPath = $path;

        $this->requestMethod = $method;

        $this->queries = [];

        $this->active = $this->shouldSample();
    }

    public function stop(): void
    {
        $this->active = false;
        $this->queries = [];
    }

    public function record(QueryExecuted $event): void
    {
        if (! $this->isRecording() || $this->isIgnored($event->sql)) {
            return;
        }

        $query = [
            'sql' => $event->sql,
            'bindings' => $event->bindings,
            'duration_ms' => (int) round($event->time),
            'connection' => $event->connectionName,
            'request_id' => $this->requestId,
            'request_path' => $this->requestPath,
            'request_method' => $this->requestMethod,
            'location' => config('db-monitor.capture_location', true) ? $this->resolveLocation() : null,
        ];

        if ($this->active && config('db-monitor.enabled', true)) {
            $this->queries[] = $query;
        }

        foreach (array_keys($this->profiles) as $key) {
            $this->profiles[$key][] = $query;
        }
    }

    /**
     * Run the callback and return every query it executed, whether or not
     * monitoring is enabled. Profiles can be nested.
     */
    public function profile(callable $callback): array
    {
        $this->profiles[] = [];

        try {
            $callback();
        } finally {
            $queries = array_pop($this->profiles);
        }

        return $queries;
    }

    public function isRecording(): bool
    {
        return ($this->active && config('db-monitor.enabled', true)) || $this->profiles !== [];
    }

    public function getQueries(): array { return $this->queries; }
    public function getRequestPath(): ?string { return $this->requestPath; }
    public function flush(): void  { $this->queries = []; }

    private function shouldSample(): bool
    {
        $rate = (float) config('db-monitor.sample_rate', 1.0);

        return $rate >= 1.0 || ($rate > 0 && mt_rand() / mt_getrandmax() < $rate);
    }

    private function isIgnored(string $sql): bool
    {
        if (str_contains($sql, 'db_monitor_')) {
            return true;
        }

        foreach (config('db-monitor.ignore_queries', []) as $pattern) {
            if (@preg_match($pattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the first stack frame that belongs to the application
     * (not the framework, vendor packages, or this package).
     */
    private function resolveLocation(): ?string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $packagePath = __DIR__ . DIRECTORY_SEPARATOR;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $file = $frame['file'] ?? null;

            if (! $file
                || str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                || str_starts_with($file, $packagePath)) {
                continue;
            }

            return Str::after($file, $basePath) . ':' . ($frame['line'] ?? 0);
        }

        return null;
    }
}
