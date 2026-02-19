<?php

use App\Http\Controllers\ApplyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Locale-prefixed public routes: /{locale}/...
| Supported locales: en, ru, lv (default: en)
|--------------------------------------------------------------------------
*/

$locales = implode('|', ['en', 'ru', 'lv']);

Route::prefix('{locale}')
    ->where(['locale' => $locales])
    ->middleware([\App\Http\Middleware\SetLocale::class])
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('g', [LandingController::class, 'google'])->name('landing.google');
        Route::get('m', [LandingController::class, 'meta'])->name('landing.meta');
        Route::get('work', fn () => redirect()->route('landing.google', ['locale' => request()->route('locale')]))->name('work');
        Route::get('rent', fn () => redirect()->route('landing.meta', ['locale' => request()->route('locale')]))->name('rent');
        Route::get('rent/{id}', [LandingController::class, 'rentDetail'])->name('landing.rent.detail');
        Route::get('apply', [ApplyController::class, 'show'])->name('apply.show');
        Route::post('apply', [ApplyController::class, 'submit'])->name('apply.submit')->middleware('throttle:10,1');
        Route::get('thanks', [ApplyController::class, 'thanks'])->name('apply.thanks');
        Route::get('faq', [LandingController::class, 'faq'])->name('landing.faq');
        Route::get('privacy', fn () => view('legal.privacy'))->name('legal.privacy');
        Route::get('terms', fn () => view('legal.terms'))->name('legal.terms');
    });

Route::get('/sitemap.xml', function () {
    $base = rtrim(url('/'), '/');
    $locales = ['en', 'ru', 'lv'];
    $paths = ['', 'g', 'm', 'apply', 'thanks', 'faq', 'privacy', 'terms'];

    $rentIds = \App\Models\RentalVehicle::where('is_active', true)->pluck('id')->all();

    $lastmod = now()->format('Y-m-d');
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
    foreach ($locales as $loc) {
        foreach ($paths as $p) {
            $url = $base . '/' . $loc . ($p ? '/' . $p : '');
            $xml .= '<url><loc>' . htmlspecialchars($url) . '</loc><lastmod>' . $lastmod . '</lastmod><priority>' . ($p === '' ? '1.0' : '0.8') . '</priority><xhtml:link rel="alternate" hreflang="' . $loc . '" href="' . htmlspecialchars($url) . '"/></url>';
        }
        foreach ($rentIds as $id) {
            $url = $base . '/' . $loc . '/rent/' . $id;
            $xml .= '<url><loc>' . htmlspecialchars($url) . '</loc><lastmod>' . $lastmod . '</lastmod><priority>0.7</priority><xhtml:link rel="alternate" hreflang="' . $loc . '" href="' . htmlspecialchars($url) . '"/></url>';
        }
    }
    $xml .= '</urlset>';
    return response($xml)->header('Content-Type', 'application/xml');
});

Route::get('/robots.txt', function () {
    $sitemap = url('/sitemap.xml');
    return response("User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: {$sitemap}\n")->header('Content-Type', 'text/plain');
});

Route::get('/', function () {
    return redirect('/en')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
});
