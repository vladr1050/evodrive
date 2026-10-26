<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['en', 'ru', 'lv'];

    private const UTM_KEYS = [
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
        $locale = $request->route('locale', 'en');

        if (in_array($locale, self::LOCALES)) {
            App::setLocale($locale);
        } else {
            App::setLocale('en');
        }

        // Persist ad attribution across pages until apply form submit.
        foreach (self::UTM_KEYS as $key) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '') {
                $request->session()->put($key, $value);
            }
        }

        return $next($request);
    }
}
