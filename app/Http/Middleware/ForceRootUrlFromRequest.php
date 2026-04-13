<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Align generated asset / route URLs with the host the user actually uses
 * (e.g. www vs apex). Otherwise @vite() and asset() use APP_URL and scripts
 * load cross-origin — browsers block ES modules (CORS).
 */
class ForceRootUrlFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() !== '') {
            URL::forceRootUrl($request->getSchemeAndHttpHost());
        }

        return $next($request);
    }
}
