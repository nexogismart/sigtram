<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\WhatsAppProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class SendWhatsAppNotificationJob
 *
 * Trabajo en cola (Redis) para procesar el envío asíncrono de mensajes de WhatsApp.
 */
class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    protected string $tipo;

    /**
     * @var string
     */
    protected string $celular;

    /**
     * @var string
     */
    protected string $mensaje;

    /**
     * @var string|null
     */
    protected ?string $urlMedia;

    /**
     * @var string|null
     */
    protected ?string $nombreDoc;

    /**
     * Create a new job instance.
     *
     * @param string $tipo Tipo de mensaje: 'texto', 'imagen', 'documento'
     * @param string $celular
     * @param string $mensaje
     * @param string|null $urlMedia
     * @param string|null $nombreDoc
     */
    public function __construct(string $tipo, string $celular, string $mensaje, ?string $urlMedia = null, ?string $nombreDoc = null)
    {
        $this->tipo = $tipo;
        $this->celular = $celular;
        $this->mensaje = $mensaje;
        $this->urlMedia = $urlMedia;
        $this->nombreDoc = $nombreDoc;
    }

    /**
     * Execute the job.
     *
     * @param WhatsAppProviderInterface $provider Inyección automática del proveedor resuelto.
     * @return void
     */
    public function handle(WhatsAppProviderInterface $provider): void
    {
        if ($this->tipo === 'imagen' && $this->urlMedia) {
            $provider->enviarImagen($this->celular, $this->mensaje, $this->urlMedia);
        } elseif ($this->tipo === 'documento' && $this->urlMedia && $this->nombreDoc) {
            $provider->enviarDocumento($this->celular, $this->mensaje, $this->urlMedia, $this->nombreDoc);
        } else {
            $provider->enviarTexto($this->celular, $this->mensaje);
        }
    }
}
