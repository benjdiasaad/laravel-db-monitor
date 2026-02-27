<?php

namespace BenjdiaSaad\DbMonitor\Detectors;

interface DetectorInterface
{
    public function analyze(array $queries): array;
}