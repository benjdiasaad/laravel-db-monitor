<?php

namespace BenjdiaSaad\DbMonitor\Detectors;

class MissingIndexDetector implements DetectorInterface
{
    public function __construct(private readonly int $minOccurrences) {}

    public function analyze(array $queries): array
    {
        $findings = [];

        $candidates = [];

        foreach ($queries as $query) {
            foreach ($this->extractWhereColumns($query['sql']) as [$table, $column]) {
                $key              = "{$table}.{$column}";
                $candidates[$key] = ($candidates[$key] ?? 0) + 1;
            }
        }

        foreach ($candidates as $tableColumn => $count) {
            if ($count >= $this->minOccurrences) {
                [$table, $column] = explode('.', $tableColumn, 2);

                $findings[] = [
                    'type' => 'missing_index',
                    'severity' => 'warning',
                    'message' => "Possible missing index on `{$table}`.`{$column}` — used in {$count} queries",
                    'context' => [
                        'table' => $table,
                        'column' => $column,
                        'occurrences' => $count,
                        'suggestion' => "\$table->index('{$column}');",
                    ],
                ];
            }
        }

        usort($findings, fn ($a, $b) => $b['context']['occurrences'] <=> $a['context']['occurrences']);

        return $findings;
    }

    private function extractWhereColumns(string $sql): array
    {
        $pairs = [];

        preg_match_all(
            '/(?:`?(\w+)`?\.)?`?(\w+)`?\s*(?:=|LIKE|IN|>|<|>=|<=|<>|!=)/i',
            $sql, $matches, PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $table = $match[1] ?? '';
            $column = $match[2] ?? '';
            if ($column && ! in_array(strtolower($column), ['id', '1', '0'])) {
                $pairs[] = [$table ?: 'unknown', $column];
            }
        }

        return $pairs;
    }
}