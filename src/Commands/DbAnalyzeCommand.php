<?php

namespace BenjdiaSaad\DbMonitor\Commands;

use Illuminate\Console\Command;
use BenjdiaSaad\DbMonitor\DbMonitor;
use BenjdiaSaad\DbMonitor\Models\DbFinding;

class DbAnalyzeCommand extends Command
{
    protected $signature = 'db:analyze {--hours=24 : Analyze stored queries from the last N hours}';
    protected $description  = 'Analyze stored query logs and write new findings';

    public function __construct(private readonly DbMonitor $monitor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $findings = $this->monitor->analyzeStoredLogs($hours);

        if (empty($findings)) {
            $this->info('No issues detected.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($findings));
        $bar->start();

        foreach ($findings as $finding) {
            DbFinding::create([
                'type' => $finding['type'],
                'severity' => $finding['severity'],
                'message' => $finding['message'],
                'context' => $finding['context'],
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(count($findings) . " findings written. Run `php artisan db:report` to view them.");

        return self::SUCCESS;
    }
}