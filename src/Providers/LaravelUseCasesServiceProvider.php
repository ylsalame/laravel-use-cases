<?php

namespace Ylsalame\LaravelUseCases\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelUseCasesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/laravel-use-cases.php',
            'laravel-use-cases'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/laravel-use-cases.php' => config_path('laravel-use-cases.php'),
        ], 'config');
    }
}

