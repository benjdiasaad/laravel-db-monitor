<?php

namespace BenjdiaSaad\DbMonitor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DbFixCommand extends Command
{
    protected $signature = 'db:fix
                            {--table= : Table name to add the index to}
                            {--column= : Column name to index}
                            {--all : Auto-generate migrations for all missing index findings}';

    protected $description = 'Auto-generate migration files to fix missing index findings';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->fixAll();
        }

        $table  = $this->option('table');
        $column = $this->option('column');

        if (! $table || ! $column) {
            $this->error('Please provide --table and --column options, or use --all.');
            $this->line('Example: php artisan db:fix --table=orders --column=user_id');
            return self::FAILURE;
        }

        return $this->generateMigration($table, $column);
    }

    private function generateMigration(string $table, string $column): int
    {
        $migrationName = "add_index_{$column}_to_{$table}_table";
        $fileName = date('Y_m_d_His') . "_{$migrationName}.php";
        $filePath = database_path("migrations/{$fileName}");
        $className = Str::studly($migrationName);

        $content = <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('{$table}', function (Blueprint \$table) {
                    \$table->index('{$column}');
                });
            }

            public function down(): void
            {
                Schema::table('{$table}', function (Blueprint \$table) {
                    \$table->dropIndex(['{$column}']);
                });
            }
        };
        PHP;

        file_put_contents($filePath, $content);

        $this->info("✅ Migration created: database/migrations/{$fileName}");
        $this->newLine();
        $this->line("Now run:");
        $this->line("  <fg=yellow>php artisan migrate</>");

        return self::SUCCESS;
    }

    private function fixAll(): int
    {
        $findings = \BenjdiaSaad\DbMonitor\Models\DbFinding::where('type', 'missing_index')
            ->get();

        if ($findings->isEmpty()) {
            $this->info('No missing index findings found.');
            return self::SUCCESS;
        }

        $this->info("Generating migrations for {$findings->count()} missing index(es)...");
        $this->newLine();

        foreach ($findings as $finding) {
            $table  = $finding->context['table']  ?? null;
            $column = $finding->context['column'] ?? null;

            if ($table && $column) {
                $this->generateMigration($table, $column);
                sleep(1); // ensure unique timestamps
            }
        }

        $this->newLine();
        $this->info('Run <fg=yellow>php artisan migrate</> to apply all indexes.');

        return self::SUCCESS;
    }
}