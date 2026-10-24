<?php

namespace BenjdiaSaad\DbMonitor;

use BenjdiaSaad\DbMonitor\Detectors\DetectorInterface;
use BenjdiaSaad\DbMonitor\Detectors\DuplicateQueryDetector;
use BenjdiaSaad\DbMonitor\Detectors\MissingIndexDetector;
use BenjdiaSaad\DbMonitor\Detectors\NPlusOneDetector;
use BenjdiaSaad\DbMonitor\Detectors\SlowQueryDetector;
use BenjdiaSaad\DbMonitor\Detectors\TooManyQueriesDetector;
use BenjdiaSaad\DbMonitor\Events\FindingRecorded;
use BenjdiaSaad\DbMonitor\Models\DbFinding;
use BenjdiaSaad\DbMonitor\Models\QueryLog;
use BenjdiaSaad\DbMonitor\Notifications\DbIssueDetected;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DbMonitor
{
    public function __construct(private readonly QueryRecorder $recorder) {}

    public function processRequest(): void
    {
        $queries = $this->recorder->getQueries();

        if (empty($queries)) {
            return;
        }

        // Monitoring must never break the application it observes.
        try {
            if (config('db-monitor.store_queries', true)) {
                $this->storeQueries($queries);
            }

            foreach ($this->runDetectors($queries) as $finding) {
                $this->recordFinding($finding, $this->recorder->getRequestPath());
            }
        } catch (Throwable $e) {
            report($e);
        } finally {
            $this->recorder->flush();
        }
    }

    /**
     * Run the callback and return the queries it executed along with any
     * issues found in them. Nothing is stored or notified.
     */
    public function profile(callable $callback): Profile
    {
        $queries = $this->recorder->profile($callback);

        return new Profile($queries, $this->runDetectors($queries));
    }

    public function runDetectors(array $queries, bool $includeAggregate = true): array
    {
        $findings = [];

        foreach ($this->detectors($includeAggregate) as $detector) {
            array_push($findings, ...$detector->analyze($queries));
        }

        return $findings;
    }

    public function analyzeStoredLogs(int $hours = 24): array
    {
        $logs = QueryLog::where('created_at', '>=', now()->subHours($hours))
            ->get()
            ->map(fn ($log) => [
                'sql' => $log->sql,
                'bindings' => $log->bindings ?? [],
                'duration_ms' => $log->duration_ms,
                'connection' => $log->connection,
                'request_id' => $log->request_id,
                'request_path' => $log->request_path,
                'location' => $log->location,
            ]);

        // Per-request detectors only make sense within a single request…
        $findings = [];

        foreach ($logs->groupBy(fn ($q) => $q['request_id'] ?? 'none') as $requestQueries) {
            $path = $requestQueries->first()['request_path'];

            foreach ($this->runDetectors($requestQueries->all(), includeAggregate: false) as $finding) {
                $findings[] = $finding + ['request_path' => $path];
            }
        }

        // …while missing-index detection looks at the whole window.
        if ($this->isEnabled('missing_index')) {
            array_push($findings, ...$this->missingIndexDetector()->analyze($logs->all()));
        }

        return $findings;
    }

    public function recordFinding(array $finding, ?string $requestPath = null): DbFinding
    {
        $record = DbFinding::create([
            'type' => $finding['type'],
            'severity' => $finding['severity'],
            'message' => $finding['message'],
            'context' => $finding['context'],
            'request_path' => $finding['request_path'] ?? $requestPath,
        ]);

        event(new FindingRecorded($record));

        $this->notify($record);

        return $record;
    }

    public function getRecorder(): QueryRecorder { return $this->recorder; }

    /**
     * @return DetectorInterface[]
     */
    private function detectors(bool $includeAggregate): array
    {
        $detectors = [];

        if ($this->isEnabled('slow_query')) {
            $detectors[] = new SlowQueryDetector((int) config('db-monitor.slow_query_threshold_ms', 500));
        }

        if ($this->isEnabled('n_plus_one')) {
            $detectors[] = new NPlusOneDetector((int) config('db-monitor.n_plus_one_threshold', 10));
        }

        if ($this->isEnabled('duplicate_query')) {
            $detectors[] = new DuplicateQueryDetector((int) config('db-monitor.duplicate_query_threshold', 3));
        }

        if ($this->isEnabled('too_many_queries')) {
            $detectors[] = new TooManyQueriesDetector((int) config('db-monitor.max_queries_per_request', 50));
        }

        if ($includeAggregate && $this->isEnabled('missing_index')) {
            $detectors[] = $this->missingIndexDetector();
        }

        foreach (config('db-monitor.custom_detectors', []) as $class) {
            $detectors[] = app($class);
        }

        return $detectors;
    }

    private function missingIndexDetector(): MissingIndexDetector
    {
        return new MissingIndexDetector((int) config('db-monitor.missing_index_min_occurrences', 50));
    }

    private function isEnabled(string $detector): bool
    {
        return (bool) config("db-monitor.detectors.{$detector}", true);
    }

    private function storeQueries(array $queries): void
    {
        $now = now();

        foreach (array_chunk($queries, 500) as $chunk) {
            QueryLog::insert(array_map(fn ($q) => array_merge($q, [
                'bindings'   => json_encode($q['bindings']),
                'created_at' => $now,
                'updated_at' => $now,
            ]), $chunk));
        }
    }

    private function notify(DbFinding $record): void
    {
        $routes = config('db-monitor.notify');

        if (! $routes || ! $this->meetsNotifySeverity($record->severity)) {
            return;
        }

        $minutes = (int) config('db-monitor.notification_throttle_minutes', 60);

        if ($minutes > 0 && ! Cache::add('db-monitor:notified:' . $this->signature($record), true, $minutes * 60)) {
            return;
        }

        $notifiable = new AnonymousNotifiable;

        foreach (is_array($routes) ? $routes : ['mail' => $routes] as $channel => $route) {
            $notifiable->route($channel, $route);
        }

        $notifiable->notify(new DbIssueDetected($record));

        $record->update(['notified' => true]);
    }

    private function meetsNotifySeverity(string $severity): bool
    {
        $levels = ['warning' => 1, 'critical' => 2];

        return ($levels[$severity] ?? 0) >= ($levels[config('db-monitor.notify_min_severity', 'critical')] ?? 2);
    }

    private function signature(DbFinding $record): string
    {
        $context = $record->context ?? [];

        return md5($record->type . '|' . ($context['sql_pattern'] ?? $context['sql']
            ?? (isset($context['table'], $context['column']) ? "{$context['table']}.{$context['column']}" : null)
            ?? $record->request_path ?? $record->message));
    }
}
