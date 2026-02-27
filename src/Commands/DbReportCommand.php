<?php

namespace BenjdiaSaad\DbMonitor\Commands;

use Illuminate\Console\Command;
use BenjdiaSaad\DbMonitor\Models\DbFinding;
use BenjdiaSaad\DbMonitor\Suggestions\SuggestionGenerator;

class DbReportCommand extends Command
{
    protected $signature = 'db:report
                            {--hours=24 : How many hours back to report on}
                            {--type= : Filter by type: slow_query, n_plus_one, missing_index}
                            {--severity= : Filter by severity: warning, critical}';

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

        return self::SUCCESS;
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