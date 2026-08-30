<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fails closed: anything short of a live, unrevoked token belonging to an
 * existing user is a 401, and every failure returns the identical body so the
 * response cannot distinguish "unknown" from "revoked".
 */
class EnsureRelayToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $presented = $request->bearerToken();

        if (! is_string($presented) || $presented === '') {
            return $this->deny();
        }

        // Indexed lookup on the keyed HMAC: one query, constant work, no
        // row-by-row comparison to time.
        $token = ApiToken::findActive($presented);

        if ($token === null || $token->user === null) {
            return $this->deny();
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('relay_token', $token);
        $request->attributes->set('relay_user', $token->user);

        return $next($request);
    }

    private function deny(): Response
    {
        return response()->json(['error' => 'Unauthorized.'], 401);
    }
}
