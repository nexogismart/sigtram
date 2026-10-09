<?php

declare(strict_types=1);

namespace App\Services\Core\Notifications\WhatsApp;

use App\Contracts\Services\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Class WhapiProvider
 *
 * Adaptador de Whapi Cloud para el envío real de notificaciones por WhatsApp en SIGTRAM3.
 * Realiza peticiones HTTP POST estructuradas con control de timeouts usando el cliente HTTP de Laravel.
 */
class WhapiProvider implements WhatsAppProviderInterface
{
    /**
     * @var string
     */
    protected string $token;

    /**
     * @var string
     */
    protected string $endpoint;

    /**
     * WhapiProvider constructor.
     *
     * @param string $token
     * @param string $endpoint
     */
    public function __construct(string $token, string $endpoint)
    {
        $this->token = $token;
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function enviarTexto(string $celular, string $mensaje): array
    {
        $celularFormateado = $this->formatearCelular($celular);
        $postData = [
            'to' => $celularFormateado . '@s.whatsapp.net',
            'body' => $mensaje
        ];

        $resultado = $this->ejecutarRequest('messages/text', $postData);
        $this->registrarFallo($resultado, 'TEXTO', $celular, "Texto: " . substr(str_replace("\n", " ", $mensaje), 0, 50) . "...");

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function enviarImagen(string $celular, string $mensaje, string $urlImagen): array
    {
        $celularFormateado = $this->formatearCelular($celular);
        $postData = [
            'to' => $celularFormateado . '@s.whatsapp.net',
            'media' => $urlImagen,
            'caption' => $mensaje
        ];

        $resultado = $this->ejecutarRequest('messages/image', $postData);
        $this->registrarFallo($resultado, 'IMAGEN', $celular, "Imagen: {$urlImagen}");

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function enviarDocumento(string $celular, string $mensaje, string $urlDocumento, string $nombreDoc): array
    {
        $celularFormateado = $this->formatearCelular($celular);
        $postData = [
            'to' => $celularFormateado . '@s.whatsapp.net',
            'media' => $urlDocumento,
            'filename' => $nombreDoc,
            'caption' => $mensaje
        ];

        $resultado = $this->ejecutarRequest('messages/document', $postData);
        $this->registrarFallo($resultado, 'DOCUMENTO', $celular, "Doc: {$nombreDoc} ({$urlDocumento})");

        return $resultado;
    }

    /**
     * Formatea el número telefónico al estándar E.164 (sin símbolos)
     * e.g., '+51 999 888 777' -> '51999888777'
     *
     * @param string $celular
     * @return string
     */
    private function formatearCelular(string $celular): string
    {
        $limpio = preg_replace('/[^0-9]/', '', $celular) ?? '';
        if (strlen($limpio) === 9) {
            $limpio = '51' . $limpio;
        }

        return $limpio;
    }

    /**
     * Ejecuta una petición HTTP POST usando el cliente HTTP de Laravel.
     *
     * @param string $subUrl
     * @param array $postData
     * @return array
     */
    private function ejecutarRequest(string $subUrl, array $postData): array
    {
        $url = $this->endpoint . '/' . ltrim($subUrl, '/');

        try {
            $response = Http::withToken($this->token)
                ->timeout(30)          // Máximo 30s de respuesta
                ->connectTimeout(10)   // Máximo 10s para establecer conexión
                ->post($url, $postData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => "Enviado con código HTTP " . $response->status()
                ];
            }

            $errData = $response->json();
            $errMsg = $errData['error'] ?? $errData['message'] ?? 'Respuesta desconocida';
            if (is_array($errMsg)) {
                $errMsg = json_encode($errMsg, JSON_UNESCAPED_UNICODE);
            }

            return [
                'success' => false,
                'error' => "HTTP {$response->status()}: {$errMsg}"
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => "Error de red/petición: " . $e->getMessage()
            ];
        }
    }

    /**
     * Registra en log de errores las comunicaciones fallidas.
     *
     * @param array $resultado
     * @param string $tipo
     * @param string $celular
     * @param string $info
     * @return void
     */
    private function registrarFallo(array $resultado, string $tipo, string $celular, string $info): void
    {
        if (!$resultado['success']) {
            Log::channel('notifications')->error("[FALLIDO] [WHATSAPP] [{$tipo}] Celular: {$celular} | Detalle: {$resultado['error']} | Info: {$info}");
        }
    }
}
