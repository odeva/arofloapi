<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\ArofloApi;

class ArofloApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('ArofloApi', ArofloApi::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/aroflo.php' => config_path('aroflo.php'),
        ]);
    }
}
