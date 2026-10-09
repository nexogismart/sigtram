<?php

declare(strict_types=1);

namespace App\Services\Core\Notifications\WhatsApp;

use App\Contracts\Services\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Class MockProvider
 *
 * Adaptador Mock para simular el envío de notificaciones por WhatsApp en SIGTRAM3.
 * Escribe los mensajes en el canal de logs 'notifications'.
 */
class MockProvider implements WhatsAppProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function enviarTexto(string $celular, string $mensaje): array
    {
        $mensajeLimpio = str_replace(["\r", "\n"], " ", $mensaje);
        Log::channel('notifications')->info("[MOCK_ENVIO] [TEXTO] Celular: {$celular} | Mensaje: {$mensajeLimpio}");

        return [
            'success' => true,
            'message' => 'Simulación de texto plano completada con éxito.'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function enviarImagen(string $celular, string $mensaje, string $urlImagen): array
    {
        $mensajeLimpio = str_replace(["\r", "\n"], " ", $mensaje);
        Log::channel('notifications')->info("[MOCK_ENVIO] [IMAGEN] Celular: {$celular} | Imagen: {$urlImagen} | Mensaje: {$mensajeLimpio}");

        return [
            'success' => true,
            'message' => 'Simulación de envío de imagen completada con éxito.'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function enviarDocumento(string $celular, string $mensaje, string $urlDocumento, string $nombreDoc): array
    {
        $mensajeLimpio = str_replace(["\r", "\n"], " ", $mensaje);
        Log::channel('notifications')->info("[MOCK_ENVIO] [DOCUMENTO] Celular: {$celular} | Doc: {$nombreDoc} ({$urlDocumento}) | Mensaje: {$mensajeLimpio}");

        return [
            'success' => true,
            'message' => 'Simulación de envío de documento completada con éxito.'
        ];
    }
}
