<?php

namespace App\Providers;

use App\Installer\Manifest;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Manifest::class, fn (): Manifest => Manifest::fromPath(config('installer.path')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
