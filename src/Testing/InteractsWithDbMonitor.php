<?php

namespace BenjdiaSaad\DbMonitor\Testing;

use BenjdiaSaad\DbMonitor\DbMonitor;
use BenjdiaSaad\DbMonitor\Detectors\DuplicateQueryDetector;
use BenjdiaSaad\DbMonitor\Detectors\NPlusOneDetector;
use BenjdiaSaad\DbMonitor\Detectors\SlowQueryDetector;
use BenjdiaSaad\DbMonitor\Profile;
use PHPUnit\Framework\Assert;

/**
 * Query-performance assertions for your PHPUnit / Pest tests.
 *
 *   $this->assertNoNPlusOneQueries(fn () => $this->get('/orders'));
 *   $this->assertMaxQueries(10, fn () => $this->get('/dashboard'));
 */
trait InteractsWithDbMonitor
{
    protected function profileQueries(callable $callback): Profile
    {
        return app(DbMonitor::class)->profile($callback);
    }

    protected function assertMaxQueries(int $max, callable $callback): Profile
    {
        $profile = $this->profileQueries($callback);

        Assert::assertLessThanOrEqual(
            $max,
            $profile->queryCount(),
            "Expected at most {$max} queries, but {$profile->queryCount()} were executed:\n" . $this->formatQueries($profile->queries)
        );

        return $profile;
    }

    protected function assertNoNPlusOneQueries(callable $callback, ?int $threshold = null): Profile
    {
        $profile = $this->profileQueries($callback);
        $findings = (new NPlusOneDetector($threshold ?? (int) config('db-monitor.n_plus_one_threshold', 10)))->analyze($profile->queries);

        Assert::assertEmpty($findings, $this->formatFindings('N+1 queries detected', $findings));

        return $profile;
    }

    protected function assertNoDuplicateQueries(callable $callback, int $threshold = 2): Profile
    {
        $profile = $this->profileQueries($callback);
        $findings = (new DuplicateQueryDetector($threshold))->analyze($profile->queries);

        Assert::assertEmpty($findings, $this->formatFindings('Duplicate queries detected', $findings));

        return $profile;
    }

    protected function assertNoSlowQueries(callable $callback, ?int $thresholdMs = null): Profile
    {
        $profile = $this->profileQueries($callback);
        $findings = (new SlowQueryDetector($thresholdMs ?? (int) config('db-monitor.slow_query_threshold_ms', 500)))->analyze($profile->queries);

        Assert::assertEmpty($findings, $this->formatFindings('Slow queries detected', $findings));

        return $profile;
    }

    private function formatFindings(string $title, array $findings): string
    {
        $lines = array_map(function ($f) {
            $sql = $f['context']['sample_sql'] ?? $f['context']['sql'] ?? '';
            $location = $f['context']['location'] ?? null;

            return "  - {$f['message']}\n    {$sql}" . ($location ? "\n    at {$location}" : '');
        }, $findings);

        return "{$title}:\n" . implode("\n", $lines);
    }

    private function formatQueries(array $queries): string
    {
        return implode("\n", array_map(fn ($q) => "  - [{$q['duration_ms']}ms] {$q['sql']}", $queries));
    }
}
