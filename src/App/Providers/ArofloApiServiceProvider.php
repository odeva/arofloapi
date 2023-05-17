<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ArofloApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
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
