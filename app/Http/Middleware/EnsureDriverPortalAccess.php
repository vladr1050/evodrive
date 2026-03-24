<?php

namespace App\Http\Middleware;

use App\Enums\DriverStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverPortalAccess
{
    /**
     * Only drivers with Active status may use the portal (not Suspended or Inactive).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $driver = $request->user('driver');
        if (! $driver || $driver->status !== DriverStatus::Active) {
            auth()->guard('driver')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('driverportal.login', ['locale' => $request->route('locale', app()->getLocale())])
                ->with('driverportal.error', __('portal.portal_access_denied'));
        }

        return $next($request);
    }
}
