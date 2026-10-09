<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Interface WhatsAppProviderInterface
 *
 * Contrato estandarizado para los proveedores de envío de notificaciones de WhatsApp.
 */
interface WhatsAppProviderInterface
{
    /**
     * Envía un mensaje de texto plano.
     *
     * @param string $celular Número telefónico con código de país.
     * @param string $mensaje Contenido del mensaje.
     * @return array Resumen del envío: ['success' => bool, 'message' => string, 'error' => string|null]
     */
    public function enviarTexto(string $celular, string $mensaje): array;

    /**
     * Envía un mensaje con imagen adjunta.
     *
     * @param string $celular Número telefónico con código de país.
     * @param string $mensaje Texto acompañante.
     * @param string $urlImagen URL absoluta de la imagen.
     * @return array Resumen del envío: ['success' => bool, 'message' => string, 'error' => string|null]
     */
    public function enviarImagen(string $celular, string $mensaje, string $urlImagen): array;

    /**
     * Envía un mensaje con un documento adjunto.
     *
     * @param string $celular Número telefónico con código de país.
     * @param string $mensaje Texto acompañante.
     * @param string $urlDocumento URL absoluta del documento.
     * @param string $nombreDoc Nombre del archivo.
     * @return array Resumen del envío: ['success' => bool, 'message' => string, 'error' => string|null]
     */
    public function enviarDocumento(string $celular, string $mensaje, string $urlDocumento, string $nombreDoc): array;
}
