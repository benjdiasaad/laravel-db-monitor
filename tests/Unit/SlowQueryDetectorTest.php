<?php

use BenjdiaSaad\DbMonitor\Detectors\SlowQueryDetector;

it('flags queries above threshold as slow', function () {
    $detector = new SlowQueryDetector(500);
    $findings = $detector->analyze([
        ['sql' => 'SELECT * FROM users', 'duration_ms' => 600, 'connection' => 'mysql'],
        ['sql' => 'SELECT * FROM orders', 'duration_ms' => 200, 'connection' => 'mysql'],
    ]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['type'])->toBe('slow_query');
});

it('marks as critical when 5x over threshold', function () {
    $findings = (new SlowQueryDetector(500))->analyze([
        ['sql' => 'SELECT * FROM big_table', 'duration_ms' => 2600, 'connection' => 'mysql'],
    ]);

    expect($findings[0]['severity'])->toBe('critical');
});

it('ignores fast queries', function () {
    expect((new SlowQueryDetector(500))->analyze([
        ['sql' => 'SELECT 1', 'duration_ms' => 10, 'connection' => 'mysql'],
    ]))->toBeEmpty();
});