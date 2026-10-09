<?php

declare(strict_types=1);

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Contracts\Services\PideCoreInterface;
use Illuminate\Http\Request;
use App\Http\Requests\Core\ConsultarPideRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class PideController extends Controller
{
    public function __construct(
        protected PideCoreInterface $pideCore
    ) {}

    /**
     * Consulta informativa detallada (para visualización/búsqueda simple).
     * Devuelve datos oficiales enriquecidos con SUNARP si están activos.
     */
    public function consultar(ConsultarPideRequest $request): JsonResponse
    {

        $documento = $request->input('documento');
        $tipo = $request->input('tipo');

        try {
            $resultado = $this->pideCore->consultarDetalle($documento, $tipo);

            if (!$resultado) {
                return response()->json([
                    'success' => false,
                    'message' => $this->pideCore->obtenerMensajeError($tipo),
                    'aviso_codigo' => $this->pideCore->obtenerUltimoAviso()
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $resultado->toArray()
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado al procesar la consulta externa.'
            ], 500);
        }
    }

    /**
     * Consulta transaccional para Mesa de Partes / Trámites.
     * Registra o actualiza al ciudadano/empresa localmente y devuelve cdgo_usrio.
     */
    public function buscarYRegistrar(ConsultarPideRequest $request): JsonResponse
    {

        $documento = $request->input('documento');
        $tipo = $request->input('tipo');
        $forzar = $request->boolean('forzar');

        // Si se fuerza la actualización, omitimos la BD local y consultamos directo a la PIDE/Decolecta
        $forceProviders = $forzar ? ['Pide', 'Decolecta'] : null;

        try {
            $resultado = $this->pideCore->buscarYRegistrarParaTramite($documento, $tipo, $forceProviders);

            if (!$resultado) {
                $ultimoAviso = $this->pideCore->obtenerUltimoAviso();
                return response()->json([
                    'success' => false,
                    'error_code' => $ultimoAviso,
                    'desbloquear_formulario' => ($ultimoAviso === 'EXTERNAL_SERVICE_UNAVAILABLE'),
                    'message' => $this->pideCore->obtenerMensajeError($tipo)
                ], 404);
            }

            // Si se detectaron discrepancias entre RENIEC/SUNAT y los datos locales municipales
            if ($resultado->tieneDiscrepancia) {
                return response()->json([
                    'success' => true,
                    'discrepancia' => true,
                    'message' => 'Se detectaron discrepancias entre los datos oficiales y los registrados localmente.',
                    'data' => $resultado->toArray()
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $resultado->toArray()
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error interno al registrar la persona para el trámite.'
            ], 500);
        }
    }
}
