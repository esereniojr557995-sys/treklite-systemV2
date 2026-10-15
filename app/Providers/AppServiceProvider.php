<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The pages use Bootstrap, so pagination links must use Bootstrap markup too.
        Paginator::useBootstrapFive();

        // Render serves the app over HTTPS behind a proxy, so force
        // https:// in generated URLs (links, redirects, form actions).
        // Use app()->environment(), not env(): env() returns null once
        // the config is cached (php artisan config:cache).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}