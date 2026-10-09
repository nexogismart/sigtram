<?php

declare(strict_types=1);

namespace App\Services\Core\Notifications;

use App\Contracts\Services\NotificationCoreInterface;
use App\Contracts\Services\WhatsAppProviderInterface;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Jobs\SendMailNotificationJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Class NotificationCore
 *
 * Implementa el servicio núcleo de notificaciones asíncronas de WhatsApp
 * y Correo Electrónico en SIGTRAM3.
 */
class NotificationCore implements NotificationCoreInterface
{
    /**
     * @var WhatsAppProviderInterface
     */
    protected WhatsAppProviderInterface $whatsAppProvider;

    /**
     * NotificationCore constructor.
     *
     * @param WhatsAppProviderInterface $whatsAppProvider Inyección del proveedor activo.
     */
    public function __construct(WhatsAppProviderInterface $whatsAppProvider)
    {
        $this->whatsAppProvider = $whatsAppProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function enviarWhatsappTexto(string $celular, string $mensaje, bool $async = true): array
    {
        if (!config('whatsapp.activo')) {
            Log::channel('notifications')->warning("[PAUSADO] [WHATSAPP] Envío omitido por pausa del servicio | Destino: {$celular}");
            return ['success' => true, 'message' => 'Servicio de WhatsApp pausado administrativamente.'];
        }

        if ($async) {
            SendWhatsAppNotificationJob::dispatch('texto', $celular, $mensaje);
            return ['success' => true, 'message' => 'Envío de WhatsApp encolado.'];
        }

        $resultado = $this->whatsAppProvider->enviarTexto($celular, $mensaje);
        $this->difundirCopias($celular, $mensaje);

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function enviarWhatsappImagen(string $celular, string $mensaje, string $urlImagen, bool $async = true): array
    {
        if (!config('whatsapp.activo')) {
            return ['success' => true, 'message' => 'Servicio de WhatsApp pausado administrativamente.'];
        }

        if ($async) {
            SendWhatsAppNotificationJob::dispatch('imagen', $celular, $mensaje, $urlImagen);
            return ['success' => true, 'message' => 'Envío de imagen de WhatsApp encolado.'];
        }

        $resultado = $this->whatsAppProvider->enviarImagen($celular, $mensaje, $urlImagen);
        $this->difundirCopias($celular, $mensaje, $urlImagen, 'imagen');

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function enviarWhatsappDocumento(string $celular, string $mensaje, string $urlDocumento, string $nombreDoc, bool $async = true): array
    {
        if (!config('whatsapp.activo')) {
            return ['success' => true, 'message' => 'Servicio de WhatsApp pausado administrativamente.'];
        }

        if ($async) {
            SendWhatsAppNotificationJob::dispatch('documento', $celular, $mensaje, $urlDocumento, $nombreDoc);
            return ['success' => true, 'message' => 'Envío de documento de WhatsApp encolado.'];
        }

        $resultado = $this->whatsAppProvider->enviarDocumento($celular, $mensaje, $urlDocumento, $nombreDoc);
        $this->difundirCopias($celular, $mensaje, $urlDocumento, 'documento', $nombreDoc);

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function enviarCorreo(string $correo, string $asunto, string $vista, array $datos = [], bool $async = true): array
    {
        if ($async) {
            SendMailNotificationJob::dispatch($correo, $asunto, $vista, $datos);
            return ['success' => true, 'message' => 'Correo electrónico encolado.'];
        }

        try {
            Mail::send($vista, $datos, function ($message) use ($correo, $asunto) {
                $message->to($correo)->subject($asunto);
            });
            Log::channel('notifications')->info("[ENVIO_EXITO] [EMAIL] Destinatario: {$correo} | Asunto: {$asunto}");
            return ['success' => true, 'message' => 'Correo enviado con éxito.'];
        } catch (\Throwable $e) {
            Log::channel('notifications')->error("[FALLIDO] [EMAIL] Destinatario: {$correo} | Asunto: {$asunto} | Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Difunde copias de los mensajes a los supervisores configurados.
     *
     * @param string $celular Celular del destinatario principal.
     * @param string $mensaje Contenido del mensaje original.
     * @param string|null $urlMedia URL del contenido multimedia adjunto.
     * @param string $tipo Tipo de mensaje.
     * @param string|null $nombreDoc Nombre del documento (si aplica).
     * @return void
     */
    private function difundirCopias(string $celular, string $mensaje, ?string $urlMedia = null, string $tipo = 'texto', ?string $nombreDoc = null): void
    {
        $copias = config('whatsapp.copias_broadcast', []);
        if (empty($copias)) {
            return;
        }

        foreach ($copias as $copia) {
            if ($copia !== $celular) {
                $textoCopia = "📢 *[COPIA SUPERVISOR]*\n\n" . $mensaje;
                if ($tipo === 'imagen' && $urlMedia) {
                    $this->whatsAppProvider->enviarImagen($copia, $textoCopia, $urlMedia);
                } elseif ($tipo === 'documento' && $urlMedia && $nombreDoc) {
                    $this->whatsAppProvider->enviarDocumento($copia, $textoCopia, $urlMedia, $nombreDoc);
                } else {
                    $this->whatsAppProvider->enviarTexto($copia, $textoCopia);
                }
            }
        }
    }
}
