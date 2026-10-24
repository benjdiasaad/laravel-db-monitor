<?php

namespace BenjdiaSaad\DbMonitor\Detectors;

/**
 * Flags a request (or job) that runs more queries than the configured budget.
 */
class TooManyQueriesDetector implements DetectorInterface
{
    public function __construct(private readonly int $maxQueries) {}

    public function analyze(array $queries): array
    {
        $count = count($queries);

        if ($this->maxQueries <= 0 || $count <= $this->maxQueries) {
            return [];
        }

        $totalMs = array_sum(array_column($queries, 'duration_ms'));

        return [[
            'type' => 'too_many_queries',
            'severity' => $count >= ($this->maxQueries * 3) ? 'critical' : 'warning',
            'message' => "Too many queries: {$count} queries in one request (limit: {$this->maxQueries}, total: {$totalMs}ms)",
            'context' => [
                'count' => $count,
                'max_queries' => $this->maxQueries,
                'total_ms' => $totalMs,
            ],
        ]];
    }
}
