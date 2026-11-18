<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use App\Services\MicrosoftGraphMail;
use Illuminate\Support\Facades\Mail;
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
        Mail::extend('microsoft-graph', fn () => new MicrosoftGraphTransport(new MicrosoftGraphMail));

        require_once app_path('Support/helpers.php');
        require_once app_path('Support/catalog.php');
        require_once app_path('Support/icons.php');
    }
}
