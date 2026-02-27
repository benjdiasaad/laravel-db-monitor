<?php

use BenjdiaSaad\DbMonitor\Detectors\NPlusOneDetector;

it('detects N+1 when same pattern repeats', function () {
    $queries = collect(range(1, 10))->map(fn ($id) => [
        'sql' => "SELECT * FROM users WHERE id = {$id}",
        'duration_ms' => 5,
        'connection'  => 'mysql',
    ])->toArray();

    $findings = (new NPlusOneDetector(5))->analyze($queries);

    expect($findings)->toHaveCount(1);

    expect($findings[0]['type'])->toBe('n_plus_one');
    
    expect($findings[0]['context']['count'])->toBe(10);
});

it('does not flag queries below threshold', function () {
    $queries = collect(range(1, 3))->map(fn ($id) => [
        'sql' => "SELECT * FROM posts WHERE id = {$id}", 'duration_ms' => 5, 'connection' => 'mysql',
    ])->toArray();

    expect((new NPlusOneDetector(10))->analyze($queries))->toBeEmpty();
});