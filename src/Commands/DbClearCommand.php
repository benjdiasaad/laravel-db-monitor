<?php

namespace BenjdiaSaad\DbMonitor\Commands;

use Illuminate\Console\Command;
use BenjdiaSaad\DbMonitor\Models\DbFinding;
use BenjdiaSaad\DbMonitor\Models\QueryLog;

class DbClearCommand extends Command
{
    protected $signature = 'db:clear {--days=7 : Delete logs older than N days} {--all : Delete ALL logs}';
    protected $description = 'Clear db-monitor query logs and findings';

    public function handle(): int
    {
        if ($this->option('all')) {
            if (! $this->confirm('This will delete ALL logs and findings. Are you sure?')) {
                return self::SUCCESS;
            }

            $logs = QueryLog::count();
            $findings = DbFinding::count();
            QueryLog::truncate();
            DbFinding::truncate();
            $this->info("Deleted {$logs} query logs and {$findings} findings.");
        } else {
            $days = (int) $this->option('days');
            $cutoff = now()->subDays($days);
            $logs = QueryLog::where('created_at', '<', $cutoff)->delete();
            $findings = DbFinding::where('created_at', '<', $cutoff)->delete();
            $this->info("Deleted {$logs} query logs and {$findings} findings older than {$days} days.");
        }

        return self::SUCCESS;
    }
}