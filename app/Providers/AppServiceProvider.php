<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TotpService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TotpService::class, function ($app) {
            $timeStep = (int) \App\Models\Setting::getValue('worker_qr_refresh_seconds', 30);
            return new \App\Services\TotpService($timeStep);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
