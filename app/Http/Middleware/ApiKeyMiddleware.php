<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Authenticate machine clients via the X-API-Key header.
     * Usage: ->middleware('api.key:grades:read')
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $key = $request->header('X-API-Key');

        if (! $key) {
            return response()->json(['message' => 'API key required. Send it in the X-API-Key header.'], 401);
        }

        // hash_equals on a fixed-shape comparison to avoid timing leaks.
        $record = ApiKey::where('key_hash', hash('sha256', $key))->first();

        if (! $record || ! hash_equals($record->key_hash, hash('sha256', $key))) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        if (! $record->isActive()) {
            return response()->json(['message' => 'API key is revoked or expired.'], 403);
        }

        if ($abilities && ! $record->hasAbilities($abilities)) {
            return response()->json(['message' => 'API key lacks required ability.'], 403);
        }

        $record->touchUsage();
        $request->attributes->set('api_key', $record);

        return $next($request);
    }
}
