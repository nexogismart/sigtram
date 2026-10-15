<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gm\mod_tram_exte\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trait para estandarizar respuestas HTTP seguras en Trámite Externo.
 * Cierra TE-023 y TE-030:
 * - Respuestas tipadas con contrato consistente {status, message, data}.
 * - Ocultamiento de stack traces y errores de base de datos / servidor al usuario final.
 * - Registro estructurado con Correlation ID en logs protegidos.
 */
trait ApiResponseTrait
{
    /**
     * Retorna una respuesta exitosa con contrato JSON estándar.
     *
     * @param string $mensaje
     * @param array<string, mixed> $data
     * @param int $codigoHttp
     * @return JsonResponse
     */
    protected function respuestaExito(string $mensaje, array $data = [], int $codigoHttp = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $mensaje,
            'data' => $data,
        ], $codigoHttp);
    }

    /**
     * Retorna una respuesta de error con mensaje genérico seguro y correlation ID.
     *
     * @param Throwable $e
     * @param string $mensajePublico
     * @param int $codigoHttp
     * @return JsonResponse
     */
    protected function respuestaSeguraError(
        Throwable $e,
        string $mensajePublico = 'Ocurrió un error al procesar la solicitud.',
        int $codigoHttp = 500
    ): JsonResponse {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'No autenticado en el sistema.',
            ], 401);
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'No autorizado para realizar esta acción.',
            ], 403);
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'El recurso solicitado no fue encontrado.',
            ], 404);
        }

        if ($e instanceof \InvalidArgumentException) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }

        if ($e instanceof \RuntimeException && (str_contains(strtolower($e->getMessage()), 'conflicto') || str_contains(strtolower($e->getMessage()), 'concurrencia'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Conflicto de concurrencia: el expediente ya fue procesado o modificado por otra operación.',
            ], 409);
        }

        $correlationId = (string) Str::uuid();

        Log::error("[CORRELATION-{$correlationId}] Error en Trámite Externo: {$e->getMessage()}", [
            'correlation_id' => $correlationId,
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => $mensajePublico,
            'correlation_id' => $correlationId,
        ], $codigoHttp);
    }

    /**
     * Retorna una respuesta de error de validación o conflicto de negocio.
     *
     * @param string $mensaje
     * @param int $codigoHttp
     * @param array<string, mixed> $errores
     * @return JsonResponse
     */
    protected function respuestaConflictoOValidacion(
        string $mensaje,
        int $codigoHttp = 422,
        array $errores = []
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $mensaje,
            'errors' => $errores,
        ], $codigoHttp);
    }
}
