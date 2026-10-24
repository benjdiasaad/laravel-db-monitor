<?php

namespace BenjdiaSaad\DbMonitor\Events;

use BenjdiaSaad\DbMonitor\Models\DbFinding;

/**
 * Dispatched every time a finding is stored, so apps can hook in
 * (log it, push it to Sentry, open a ticket, ...).
 */
class FindingRecorded
{
    public function __construct(public readonly DbFinding $finding) {}
}
