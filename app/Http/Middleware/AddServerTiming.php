<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds `Server-Timing: app;dur=<ms>` — how long Laravel took to build the
 * response — so the page-switch timing badge (?perf=1) can tell server time
 * apart from network / Cloudflare time.
 */
class AddServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $start = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');
        $response->headers->set('Server-Timing', sprintf('app;dur=%.1f', (microtime(true) - $start) * 1000), false);

        return $response;
    }
}
