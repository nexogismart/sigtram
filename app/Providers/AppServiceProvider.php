<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Contracts\Repositories\PermisosRepositoryInterface::class,
            \App\Repositories\mod_sistema\PermisosRepository::class
        );

        $this->app->singleton(
            \App\Contracts\Repositories\mod_gene\IngresarTramiteRepositoryInterface::class,
            \App\Repositories\mod_gene\IngresarTramiteRepository::class
        );

        $this->app->singleton(
            \App\Contracts\Services\SecurityCoreInterface::class,
            \App\Services\Core\SecurityCore::class
        );

        $this->app->singleton(
            \App\Contracts\Services\BdCoreInterface::class,
            \App\Services\Core\BdCore::class
        );

        $this->app->bind(
            \App\Contracts\Services\WhatsAppProviderInterface::class,
            function ($app) {
                $provider = config('whatsapp.proveedor', 'mock');
                if ($provider === 'whapi') {
                    return new \App\Services\Core\Notifications\WhatsApp\WhapiProvider(
                        (string) config('whatsapp.token'),
                        (string) config('whatsapp.endpoint')
                    );
                }
                return new \App\Services\Core\Notifications\WhatsApp\MockProvider();
            }
        );

        $this->app->singleton(
            \App\Contracts\Services\NotificationCoreInterface::class,
            \App\Services\Core\Notifications\NotificationCore::class
        );

        $this->app->singleton(
            \App\Contracts\Services\PideCoreInterface::class,
            \App\Services\Core\Pide\PideCore::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
