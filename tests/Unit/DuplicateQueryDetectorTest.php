<?php

use BenjdiaSaad\DbMonitor\Detectors\DuplicateQueryDetector;
use BenjdiaSaad\DbMonitor\Detectors\MissingIndexDetector;
use BenjdiaSaad\DbMonitor\Detectors\TooManyQueriesDetector;

it('flags identical queries with identical bindings', function () {
    $query = ['sql' => 'select * from settings where key = ?', 'bindings' => ['theme'], 'duration_ms' => 2, 'connection' => 'mysql'];

    $findings = (new DuplicateQueryDetector(3))->analyze([$query, $query, $query]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['type'])->toBe('duplicate_query')
        ->and($findings[0]['context']['count'])->toBe(3);
});

it('does not flag the same SQL with different bindings', function () {
    $queries = collect(range(1, 5))->map(fn ($id) => [
        'sql' => 'select * from users where id = ?', 'bindings' => [$id], 'duration_ms' => 1, 'connection' => 'mysql',
    ])->all();

    expect((new DuplicateQueryDetector(3))->analyze($queries))->toBeEmpty();
});

it('flags requests over the query budget', function () {
    $queries = array_fill(0, 12, ['sql' => 'select 1', 'duration_ms' => 1, 'connection' => 'mysql']);

    $findings = (new TooManyQueriesDetector(10))->analyze($queries);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['type'])->toBe('too_many_queries')
        ->and($findings[0]['severity'])->toBe('warning');

    expect((new TooManyQueriesDetector(20))->analyze($queries))->toBeEmpty();
});

it('attributes unqualified columns to the FROM table', function () {
    $queries = array_fill(0, 3, ['sql' => 'select * from "orders" where "user_id" = ?', 'duration_ms' => 1, 'connection' => 'pgsql']);

    $findings = (new MissingIndexDetector(3))->analyze($queries);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['context']['table'])->toBe('orders')
        ->and($findings[0]['context']['column'])->toBe('user_id');
});
