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
            \App\Contracts\Repositories\AccionPermisoRepositoryInterface::class,
            \App\Repositories\mod_sistema\AccionPermisoRepository::class
        );

        $this->app->singleton(
            \App\Contracts\Repositories\mod_gene\mod_tram_inter\IngresarTramiteRepositoryInterface::class,
            \App\Repositories\mod_gene\mod_tram_inter\IngresarTramiteRepository::class
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

        $this->app->singleton(
            \App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface::class,
            \App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository::class
        );

        $this->app->singleton(
            \App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface::class,
            \App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository::class
        );

        $this->app->singleton(
            \App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface::class,
            \App\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepository::class
        );

        $this->app->singleton(
            \App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface::class,
            \App\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepository::class
        );

        $this->app->singleton(
            \App\Repositories\mod_gm\mod_tram_exte\CorrelativoExpedienteLock::class
        );

        $this->app->singleton(
            \App\Contracts\Services\GestorAdjuntosInterface::class,
            \App\Services\Support\GestorAdjuntos::class
        );

        $this->app->singleton(
            \App\Contracts\Services\mod_gm\mod_tram_exte\PdfPageCounterInterface::class,
            \App\Services\mod_gm\mod_tram_exte\PdfPageCounterService::class
        );

        $this->app->singleton(
            \App\Services\mod_gm\mod_tram_exte\DocumentoInternoDesdeExpedienteExternoService::class
        );

        $this->app->singleton(
            \App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface::class,
            \App\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepository::class
        );

        $this->app->singleton(
            \App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface::class,
            \App\Services\Core\ContextoUsuarioAutenticadoService::class
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
