<?php

namespace App\Providers;

use App\Services\DatabaseTranslationLoader;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend('translation.loader', function ($loader) {
            return new DatabaseTranslationLoader($loader);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $locale = app()->getLocale();
            $utm = array_filter(request()->only(['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term']));
            $view->with('applyUrl', route('apply.show', ['locale' => $locale]) . (empty($utm) ? '' : '?' . http_build_query($utm)));
        });
    }
}
