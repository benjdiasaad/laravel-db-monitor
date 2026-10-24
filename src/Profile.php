<?php

namespace BenjdiaSaad\DbMonitor;

/**
 * The result of DbMonitor::profile(): the queries a callback ran and
 * the issues the detectors found in them.
 */
class Profile
{
    public function __construct(
        public readonly array $queries,
        public readonly array $findings,
    ) {}

    public function queryCount(): int
    {
        return count($this->queries);
    }

    public function totalTimeMs(): int
    {
        return (int) array_sum(array_column($this->queries, 'duration_ms'));
    }

    public function hasIssues(?string $type = null): bool
    {
        return $this->findingsOfType($type) !== [];
    }

    public function findingsOfType(?string $type = null): array
    {
        if ($type === null) {
            return $this->findings;
        }

        return array_values(array_filter($this->findings, fn ($f) => $f['type'] === $type));
    }
}
