<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        date_default_timezone_set(config('services.mwcn.timezone', 'America/Phoenix'));
        require_once app_path('Support/helpers.php');
        require_once app_path('Support/catalog.php');
        require_once app_path('Support/icons.php');
    }
}
