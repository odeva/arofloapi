<?php

namespace ContactGroup;

use Illuminate\Support\ServiceProvider;
use ContactGroup\ArofloApi;

class ArofloApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('arofloApi', ArofloApi::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/aroflo.php' => config_path('aroflo.php'),
            ['arofloapi']
        ]);
    }
}
