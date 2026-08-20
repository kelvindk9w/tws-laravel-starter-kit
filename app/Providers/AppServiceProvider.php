<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Support\Platform;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Configuração centralizada da plataforma (ADR-007) — singleton tipado.
        $this->app->singleton(Platform::class, fn (): Platform => Platform::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
