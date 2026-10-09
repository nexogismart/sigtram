<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Interface NotificationCoreInterface
 *
 * Contrato principal para la gestión de envíos asíncronos y centralizados
 * de notificaciones (WhatsApp, Correo Electrónico, etc.) en SIGTRAM3.
 */
interface NotificationCoreInterface
{
    /**
     * Envía una notificación de texto plano por WhatsApp.
     *
     * @param string $celular Número telefónico con código de país.
     * @param string $mensaje Contenido del mensaje.
     * @param bool $async Si es true, el envío se delega a las colas (Redis).
     * @return array Resumen del envío: ['success' => bool, 'message' => string]
     */
    public function enviarWhatsappTexto(string $celular, string $mensaje, bool $async = true): array;

    /**
     * Envía una notificación con imagen por WhatsApp.
     *
     * @param string $celular Número telefónico con código de país.
     * @param string $mensaje Texto acompañante.
     * @param string $urlImagen URL absoluta de la imagen.
     * @param bool $async Si es true, el envío se delega a las colas (Redis).
     * @return array
     */
    public function enviarWhatsappImagen(string $celular, string $mensaje, string $urlImagen, bool $async = true): array;

    /**
     * Envía una notificación con documento por WhatsApp.
     *
     * @param string $celular Número telefónico con código de país.
     * @param string $mensaje Texto acompañante.
     * @param string $urlDocumento URL absoluta del documento.
     * @param string $nombreDoc Nombre del archivo.
     * @param bool $async Si es true, el envío se delega a las colas (Redis).
     * @return array
     */
    public function enviarWhatsappDocumento(string $celular, string $mensaje, string $urlDocumento, string $nombreDoc, bool $async = true): array;

    /**
     * Envía una notificación de correo electrónico.
     *
     * @param string $correo Dirección de correo electrónico del destinatario.
     * @param string $asunto Asunto del correo.
     * @param string $vista Nombre de la vista Blade del cuerpo del correo.
     * @param array $datos Datos a pasar a la plantilla Blade.
     * @param bool $async Si es true, el envío se delega a las colas (Redis).
     * @return array
     */
    public function enviarCorreo(string $correo, string $asunto, string $vista, array $datos = [], bool $async = true): array;
}
