<?php

namespace BenjdiaSaad\DbMonitor\Suggestions;

class SuggestionGenerator
{
    public static function forFinding(array $finding): string
    {
        return match ($finding['type']) {
            'slow_query' => self::forSlowQuery($finding['context']),
            'n_plus_one' => self::forNPlusOne($finding['context']),
            'missing_index' => self::forMissingIndex($finding['context']),
            default => 'Review the query manually.',
        };
    }

    private static function forSlowQuery(array $context): string
    {
        $sql  = strtolower($context['sample_sql'] ?? $context['sql'] ?? '');
        $column  = self::extractWhereColumn($sql);
        $table = self::extractTable($sql);
        $lines = [];

        $lines[] = "Use select() to avoid SELECT *:";
        $lines[] = "  {$table}::select('id', 'name', ...)->...";

        if ($column && $table) {
            $lines[] = "Add an index on the WHERE column:";
            $lines[] = "  php artisan db:fix --table={$table} --column={$column}";
        }

        $lines[] = "Add pagination to limit rows returned:";
        $lines[] = "  ->paginate(25)";

        return implode("\n    ", $lines);
    }

    private static function forNPlusOne(array $context): string
    {
        $sql      = strtolower($context['sample_sql'] ?? $context['sql_pattern'] ?? '');
        $table    = self::extractTable($sql);
        $relation = self::guessRelation($table);

        return "Add eager loading to avoid {$context['count']} queries:\n" .
               "    Model::with('{$relation}')->get()\n" .
               "    (Replace 'Model' with your actual model name)";
    }

    private static function forMissingIndex(array $context): string
    {
        $table  = $context['table']  ?? 'your_table';
        $column = $context['column'] ?? 'your_column';

        return "Auto-generate the migration:\n" .
               "    php artisan db:fix --table={$table} --column={$column}\n" .
               "    Then run: php artisan migrate";
    }

    private static function extractTable(string $sql): string
    {
        preg_match('/from\s+`?(\w+)`?/i', $sql, $matches);
        return $matches[1] ?? 'your_table';
    }

    private static function extractWhereColumn(string $sql): string
    {
        preg_match('/where\s+`?(\w+)`?\s*=/i', $sql, $matches);
        return $matches[1] ?? '';
    }

    private static function guessRelation(string $table): string
    {
        // Remove trailing 's' to guess singular relation name
        return rtrim($table, 's');
    }
}