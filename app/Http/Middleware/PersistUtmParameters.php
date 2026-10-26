<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture UTM (+ common click IDs) from the query string into the session
 * so they survive navigation until the apply form is submitted.
 */
class PersistUtmParameters
{
    private const KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gclid',
        'fbclid',
        'ttclid',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::KEYS as $key) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '') {
                $request->session()->put($key, $value);
            }
        }

        return $next($request);
    }
}
