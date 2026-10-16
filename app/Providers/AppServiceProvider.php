<?php

declare(strict_types=1);

namespace App\Providers;

use App\Installer\Manifest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Manifest::class, fn (): Manifest => Manifest::fromPath(Config::string('installer.path')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
