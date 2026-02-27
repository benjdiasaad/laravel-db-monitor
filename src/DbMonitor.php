<?php

namespace BenjdiaSaad\DbMonitor;

use Illuminate\Support\Facades\Notification;
use BenjdiaSaad\DbMonitor\Detectors\MissingIndexDetector;
use BenjdiaSaad\DbMonitor\Detectors\NPlusOneDetector;
use BenjdiaSaad\DbMonitor\Detectors\SlowQueryDetector;
use BenjdiaSaad\DbMonitor\Models\DbFinding;
use BenjdiaSaad\DbMonitor\Models\QueryLog;
use BenjdiaSaad\DbMonitor\Notifications\DbIssueDetected;

class DbMonitor
{
    public function __construct(private readonly QueryRecorder $recorder) {}

    public function processRequest(): void
    {
        $queries = $this->recorder->getQueries();

        if (empty($queries)) {
            return;
        }

        if (config('db-monitor.store_queries', true)) {

            QueryLog::insert(array_map(fn ($q) => array_merge($q, [
                'bindings'   => json_encode($q['bindings']),
                'created_at' => now(),
                'updated_at' => now(),
            ]), $queries));

        }

        foreach ($this->runDetectors($queries) as $finding) {
            $record = DbFinding::create([
                'type' => $finding['type'],
                'severity' => $finding['severity'],
                'message' => $finding['message'],
                'context' => $finding['context'],
                'request_path' => request()?->path(),
            ]);

            $notifiable = config('db-monitor.notify');

            if ($notifiable && $finding['severity'] === 'critical') {
                Notification::route('mail', $notifiable)->notify(new DbIssueDetected($record));
                $record->update(['notified' => true]);
            }
        }

        $this->recorder->flush();
    }

    public function runDetectors(array $queries): array
    {
        return array_merge(
            (new SlowQueryDetector(config('db-monitor.slow_query_threshold_ms', 500)))->analyze($queries),
            (new NPlusOneDetector(config('db-monitor.n_plus_one_threshold', 10)))->analyze($queries),
            (new MissingIndexDetector(config('db-monitor.missing_index_min_occurrences', 50)))->analyze($queries),
        );
    }

    public function analyzeStoredLogs(int $hours = 24): array
    {
        $queries = QueryLog::where('created_at', '>=', now()->subHours($hours))
            ->get()
            ->map(fn ($log) => [
                'sql' => $log->sql,
                'bindings' => $log->bindings ?? [],
                'duration_ms' => $log->duration_ms,
                'connection' => $log->connection,
            ])->toArray();

        return $this->runDetectors($queries);
    }

    public function getRecorder(): QueryRecorder { return $this->recorder; }
}