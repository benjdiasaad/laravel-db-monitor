<?php

namespace BenjdiaSaad\DbMonitor\Detectors;

/**
 * Flags the exact same query (same SQL and same bindings) executed
 * several times in one request — usually a missing cache or a
 * relation loaded repeatedly instead of being reused.
 */
class DuplicateQueryDetector implements DetectorInterface
{
    public function __construct(private readonly int $threshold) {}

    public function analyze(array $queries): array
    {
        $findings = [];

        $grouped = [];

        foreach ($queries as $query) {
            $key = $query['sql'] . '|' . json_encode($query['bindings'] ?? []);
            $grouped[$key][] = $query;
        }

        foreach ($grouped as $group) {
            $count = count($group);

            if ($count < $this->threshold) {
                continue;
            }

            $totalMs = array_sum(array_column($group, 'duration_ms'));

            $findings[] = [
                'type' => 'duplicate_query',
                'severity' => 'warning',
                'message' => "Duplicate query: identical query ran {$count} times (total: {$totalMs}ms)",
                'context' => [
                    'sql' => $group[0]['sql'],
                    'bindings' => $group[0]['bindings'] ?? [],
                    'count' => $count,
                    'threshold' => $this->threshold,
                    'total_ms' => $totalMs,
                    'location' => $group[0]['location'] ?? null,
                ],
            ];
        }

        return $findings;
    }
}
