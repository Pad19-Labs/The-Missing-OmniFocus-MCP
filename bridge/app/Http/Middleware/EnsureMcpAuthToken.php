<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpAuthToken
{
    /**
     * Fails closed: with no MCP_AUTH_TOKEN configured, every request is
     * rejected rather than the endpoint silently becoming public.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.omnifocus_bridge.token');
        $given = $request->bearerToken();

        if (! is_string($expected) || $expected === '' || ! is_string($given) || ! hash_equals($expected, $given)) {
            abort(401, 'Missing or invalid bearer token.');
        }

        return $next($request);
    }
}
