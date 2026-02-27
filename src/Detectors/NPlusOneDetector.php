<?php

namespace BenjdiaSaad\DbMonitor\Detectors;

class NPlusOneDetector implements DetectorInterface
{
    public function __construct(private readonly int $threshold) {}

    public function analyze(array $queries): array
    {
        $findings = [];

        $grouped = [];

        foreach ($queries as $query) {

            $normalized = $this->normalizeSql($query['sql']);
            $grouped[$normalized][] = $query;
        }

        foreach ($grouped as $normalizedSql => $group) {

            if (count($group) >= $this->threshold) {
                $count = count($group);
                $totalMs = array_sum(array_column($group, 'duration_ms'));

                $findings[] = [
                    'type' => 'n_plus_one',
                    'severity' => $count >= ($this->threshold * 3) ? 'critical' : 'warning',
                    'message' => "Potential N+1 detected: same query ran {$count} times (total: {$totalMs}ms)",
                    'context' => [
                        'sql_pattern' => $normalizedSql,
                        'count' => $count,
                        'threshold' => $this->threshold,
                        'total_ms' => $totalMs,
                        'avg_ms' => round($totalMs / $count, 2),
                        'sample_sql' => $group[0]['sql'],
                    ],
                ];
            }
        }

        return $findings;
    }

    private function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/\b\d+\b/', '?', $sql);
        $sql = preg_replace("/'[^']*'/", '?', $sql);
        $sql = preg_replace('/"[^"]*"/', '?', $sql);
        return trim(preg_replace('/\s+/', ' ', $sql));
    }
}