<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single shared-secret auth for the meal plan API (MEAL_PLAN_API_TOKEN in
 * .env). When no token is configured the API is disabled.
 */
class EnsureMealPlanApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.meal_plan_api.token');

        if ($token === '') {
            abort(404);
        }

        if (! is_string($request->bearerToken()) || ! hash_equals($token, $request->bearerToken())) {
            abort(401, 'Invalid API token.');
        }

        return $next($request);
    }
}
