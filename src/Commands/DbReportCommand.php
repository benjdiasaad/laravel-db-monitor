<?php

namespace BenjdiaSaad\DbMonitor\Commands;

use Illuminate\Console\Command;
use BenjdiaSaad\DbMonitor\Models\DbFinding;
use BenjdiaSaad\DbMonitor\Suggestions\SuggestionGenerator;

class DbReportCommand extends Command
{
    protected $signature = 'db:report
                            {--hours=24 : How many hours back to report on}
                            {--type= : Filter by type: slow_query, n_plus_one, duplicate_query, too_many_queries, missing_index}
                            {--severity= : Filter by severity: warning, critical}
                            {--json : Output findings as JSON}
                            {--fail-on= : Exit with code 1 if any finding has this severity or higher (warning, critical) — handy in CI}';

    protected $description = 'Show a database health report from stored findings';

    public function handle(): int
    {
        $hours    = (int) $this->option('hours');
        $type     = $this->option('type');
        $severity = $this->option('severity');

        $query = DbFinding::where('created_at', '>=', now()->subHours($hours));

        if ($type)     $query->where('type', $type);
        if ($severity) $query->where('severity', $severity);

        $findings = $query->orderBy('severity', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get();

        $exitCode = $this->exitCodeFor($findings);

        if ($this->option('json')) {
            $this->line($findings->map(fn (DbFinding $f) => [
                'id' => $f->id,
                'type' => $f->type,
                'severity' => $f->severity,
                'message' => $f->message,
                'request_path' => $f->request_path,
                'context' => $f->context,
                'suggestion' => SuggestionGenerator::forFinding(['type' => $f->type, 'context' => $f->context ?? []]),
                'created_at' => $f->created_at?->toIso8601String(),
            ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $exitCode;
        }

        if ($findings->isEmpty()) {
            $this->info("No issues found in the last {$hours} hours.");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("  <fg=white;bg=blue> DB Monitor Report — Last {$hours} hours </>");
        $this->newLine();

        foreach ($findings->groupBy('type') as $groupType => $group) {
            $this->printSection($groupType, $group);
        }

        $this->line('─────────────────────────────────────────────────────');
        $this->table(
            ['Type', 'Warning', 'Critical', 'Total'],
            $findings->groupBy('type')->map(fn ($items, $t) => [
                str_replace('_', ' ', ucfirst($t)),
                $items->where('severity', 'warning')->count(),
                $items->where('severity', 'critical')->count(),
                $items->count(),
            ])->values()
        );

        return $exitCode;
    }

    private function exitCodeFor($findings): int
    {
        $failOn = $this->option('fail-on');

        if (! $failOn) {
            return self::SUCCESS;
        }

        $levels = ['warning' => 1, 'critical' => 2];
        $min = $levels[$failOn] ?? 2;

        return $findings->contains(fn ($f) => ($levels[$f->severity] ?? 0) >= $min)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function printSection(string $type, $findings): void
    {
        $label = strtoupper(str_replace('_', ' ', $type));
        $this->line("  <fg=yellow>▶ {$label}</>");

        foreach ($findings->take(5) as $finding) {
            $icon = $finding->severity === 'critical' ? '<fg=red>●</>' : '<fg=yellow>●</>';

            $this->line("    {$icon}  {$finding->message}");

            if ($finding->request_path) {
                $this->line("       <fg=gray>Path: {$finding->request_path}</>");
            }

            if ($location = $finding->context['location'] ?? null) {
                $this->line("       <fg=gray>Source: {$location}</>");
            }

            // 💡 Fix suggestion
            $suggestion = SuggestionGenerator::forFinding([
                'type'    => $finding->type,
                'context' => $finding->context ?? [],
            ]);

            foreach (explode("\n", $suggestion) as $line) {
                $this->line("       <fg=cyan>💡 {$line}</>");
            }

            $this->newLine();
        }

        if ($findings->count() > 5) {
            $this->line("       <fg=gray>... and " . ($findings->count() - 5) . " more</>");
        }

        $this->newLine();
    }
}
