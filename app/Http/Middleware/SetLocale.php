<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['en', 'ru', 'lv'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale', 'en');

        if (in_array($locale, self::LOCALES)) {
            App::setLocale($locale);
        } else {
            App::setLocale('en');
        }

        return $next($request);
    }
}
