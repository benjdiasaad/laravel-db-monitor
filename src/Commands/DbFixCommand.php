<?php

namespace BenjdiaSaad\DbMonitor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use BenjdiaSaad\DbMonitor\Models\DbFinding;

class DbFixCommand extends Command
{
    protected $signature = 'db:fix
                            {--table= : Table name to add the index to}
                            {--column= : Column name to index}
                            {--all : Auto-generate migrations for all missing index findings}
                            {--dry-run : Show which migrations would be generated without writing them}';

    protected $description = 'Auto-generate migration files to fix missing index findings';

    private int $timestamp;

    public function handle(): int
    {
        $this->timestamp = time();

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

        if (! $this->isValidIdentifier($table) || ! $this->isValidIdentifier($column)) {
            $this->error('Table and column names may only contain letters, numbers and underscores.');
            return self::FAILURE;
        }

        if (! $this->generateMigration($table, $column)) {
            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            $this->newLine();
            $this->line("Now run:");
            $this->line("  <fg=yellow>php artisan migrate</>");
        }

        return self::SUCCESS;
    }

    private function generateMigration(string $table, string $column): bool
    {
        if ($reason = $this->skipReason($table, $column)) {
            $this->warn("Skipped {$table}.{$column}: {$reason}");
            return false;
        }

        $migrationName = "add_index_{$column}_to_{$table}_table";

        if ($existing = glob(database_path("migrations/*_{$migrationName}.php"))) {
            $this->warn('Skipped ' . "{$table}.{$column}: migration already exists (" . basename($existing[0]) . ')');
            return false;
        }

        // Increment per migration so each file gets a unique, ordered timestamp
        $fileName = date('Y_m_d_His', $this->timestamp++) . "_{$migrationName}.php";
        $filePath = database_path("migrations/{$fileName}");

        if ($this->option('dry-run')) {
            $this->line("Would create: database/migrations/{$fileName}");
            return true;
        }

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

        return true;
    }

    private function skipReason(string $table, string $column): ?string
    {
        try {
            if (! Schema::hasTable($table)) {
                return 'table does not exist';
            }

            if (! Schema::hasColumn($table, $column)) {
                return 'column does not exist';
            }

            if (Schema::hasIndex($table, [$column])) {
                return 'an index already exists';
            }
        } catch (\Throwable) {
            // Can't inspect the schema (e.g. no DB connection) — generate anyway.
        }

        return null;
    }

    private function isValidIdentifier(string $name): bool
    {
        return (bool) preg_match('/^\w+$/', $name);
    }

    private function fixAll(): int
    {
        $pairs = DbFinding::where('type', 'missing_index')
            ->get()
            ->map(fn ($f) => [$f->context['table'] ?? null, $f->context['column'] ?? null])
            ->filter(fn ($p) => $p[0] && $p[1] && $p[0] !== 'unknown'
                && $this->isValidIdentifier($p[0]) && $this->isValidIdentifier($p[1]))
            ->unique(fn ($p) => "{$p[0]}.{$p[1]}")
            ->values();

        if ($pairs->isEmpty()) {
            $this->info('No missing index findings found.');
            return self::SUCCESS;
        }

        $this->info("Generating migrations for {$pairs->count()} missing index(es)...");
        $this->newLine();

        $created = $pairs->filter(fn ($p) => $this->generateMigration($p[0], $p[1]))->count();

        $this->newLine();

        if ($created > 0 && ! $this->option('dry-run')) {
            $this->info('Run <fg=yellow>php artisan migrate</> to apply all indexes.');
        }

        return self::SUCCESS;
    }
}
