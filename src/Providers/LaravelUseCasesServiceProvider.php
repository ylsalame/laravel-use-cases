<?php

namespace Ylsalame\LaravelUseCases\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelUseCasesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/use_cases.php',
            'use_cases'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/use_cases.php' => config_path('use_cases.php'),
        ], 'config');
    }
}

