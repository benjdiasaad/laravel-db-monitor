<?php

use BenjdiaSaad\DbMonitor\DbMonitor;

it('returns a slow query finding', function () {
    $findings = app(DbMonitor::class)->runDetectors([
        ['sql' => 'SELECT * FROM orders', 'duration_ms' => 3000, 'connection' => 'mysql'],
    ]);

    expect($findings)->not->toBeEmpty();
    
    expect($findings[0]['type'])->toBe('slow_query');
});

it('returns no findings for healthy queries', function () {
    $findings = app(DbMonitor::class)->runDetectors([
        ['sql' => 'SELECT * FROM users WHERE id = 1', 'duration_ms' => 10, 'connection' => 'mysql'],
    ]);

    expect($findings)->toBeEmpty();
});