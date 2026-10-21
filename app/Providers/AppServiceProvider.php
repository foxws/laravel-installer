<?php

declare(strict_types=1);

namespace App\Providers;

use App\Installer\InstallerMode;
use App\Installer\Manifest;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Manifest::class, fn (): Manifest => Manifest::fromPath(InstallerMode::manifestPath()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
