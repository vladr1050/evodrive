<?php

namespace App\Providers;

use App\Contracts\SmsProviderInterface;
use App\Events\ShiftCancelled;
use App\Listeners\SendShiftCancellationTelegramNotification;
use App\Services\CarControl\CarActionCommandResolver;
use App\Services\CarControl\CarControlTransportRouter;
use App\Services\CarControl\GprsCarDeviceTransport;
use App\Services\CarControl\SmsCarDeviceTransport;
use App\Services\CarControlService;
use App\Services\DatabaseTranslationLoader;
use App\Services\NessSmsProvider;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
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

        $this->app->bind(TelegramNotifier::class, fn () => TelegramNotifier::fromConfig());
        $this->app->bind(SmsProviderInterface::class, fn () => NessSmsProvider::fromConfig());
        $this->app->singleton(CarActionCommandResolver::class);
        $this->app->singleton(SmsCarDeviceTransport::class);
        $this->app->singleton(GprsCarDeviceTransport::class);
        $this->app->singleton(CarControlTransportRouter::class);
        $this->app->singleton(CarControlService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ShiftCancelled::class, SendShiftCancellationTelegramNotification::class);

        // Root-relative URLs so JS/CSS always match the page host (www vs apex). Absolute URLs
        // from asset() ignore URL::forceRootUrl() when ASSET_URL / asset root is set in .env.
        Vite::createAssetPathsUsing(fn (string $path) => '/'.ltrim($path, '/'));

        View::composer('*', function ($view) {
            $locale = app()->getLocale();
            $utm = array_filter(request()->only(['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term']));
            $view->with('applyUrl', route('apply.show', ['locale' => $locale]).(empty($utm) ? '' : '?'.http_build_query($utm)));
        });
    }
}
