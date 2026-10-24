<?php

namespace BenjdiaSaad\DbMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use BenjdiaSaad\DbMonitor\DbMonitor;
use BenjdiaSaad\DbMonitor\QueryRecorder;

class MonitorQueries
{
    public function __construct(
        private readonly DbMonitor $monitor,
        private readonly QueryRecorder $recorder
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('db-monitor.enabled', true) || $this->isExcluded($request)) {
            return $next($request);
        }

        $this->recorder->startRequest();

        return $next($request);
    }

    /**
     * Analyse after the response has been sent, so monitoring adds
     * no latency for the user.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->monitor->processRequest();

        $this->recorder->stop();
    }

    private function isExcluded(Request $request): bool
    {
        foreach (config('db-monitor.exclude_paths', []) as $pattern) {
            if ($request->is($pattern)) return true;
        }
        return false;
    }
}
