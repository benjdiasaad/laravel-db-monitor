<?php

namespace BenjdiaSaad\DbMonitor\Detectors;

class SlowQueryDetector implements DetectorInterface
{
    public function __construct(private readonly int $thresholdMs) {}

    public function analyze(array $queries): array
    {
        $findings = [];

        foreach ($queries as $query) {

            if ($query['duration_ms'] >= $this->thresholdMs) {
            
                $severity = $query['duration_ms'] >= ($this->thresholdMs * 5) ? 'critical' : 'warning';

                $findings[] = [
                    'type' => 'slow_query',
                    'severity' => $severity,
                    'message' => "Slow query detected: {$query['duration_ms']}ms (threshold: {$this->thresholdMs}ms)",
                    'context' => [
                        'sql' => strlen($query['sql']) > 500 ? substr($query['sql'], 0, 500) . '...' : $query['sql'],
                        'duration_ms' => $query['duration_ms'],
                        'threshold_ms' => $this->thresholdMs,
                        'connection' => $query['connection'],
                    ],
                ];
            }
        }

        return $findings;
    }
}