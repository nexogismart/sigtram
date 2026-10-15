<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Trait para estandarizar respuestas HTTP seguras y observabilidad (RA-06).
 * - Oculta stack traces, paths y SQL ante excepciones tecnicas.
 * - Mapea excepciones de dominio a 400/401/403/404/409/422.
 * - Registra correlation ID estructurado server-side para errores 500.
 */
trait ApiResponseTrait
{
    protected function respuestaExito(string $mensaje, array $data = [], int $codigoHttp = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $mensaje,
            'data' => $data,
        ], $codigoHttp);
    }

    protected function respuestaSeguraError(
        Throwable $e,
        string $mensajePublico = 'Ocurrió un error al procesar la solicitud.',
        int $codigoHttp = 500
    ): JsonResponse {
        if ($e instanceof ValidationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'No autenticado en el sistema.',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'No autorizado para realizar esta acción.',
            ], 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'El recurso solicitado no fue encontrado.',
            ], 404);
        }

        if ($e instanceof InvalidArgumentException) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }

        if ($e instanceof RuntimeException && (str_contains(strtolower($e->getMessage()), 'conflicto') || str_contains(strtolower($e->getMessage()), 'concurrencia'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Conflicto de concurrencia: el expediente ya fue procesado o modificado por otra operación.',
            ], 409);
        }

        $correlationId = (string) Str::uuid();

        Log::error("[CORRELATION-{$correlationId}] Error interno: {$e->getMessage()}", [
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
