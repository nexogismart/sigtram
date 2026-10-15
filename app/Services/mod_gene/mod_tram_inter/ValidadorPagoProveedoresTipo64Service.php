<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class ValidadorPagoProveedoresTipo64Service
{
    public function __construct(
        private readonly ?TramiteInternoRepositoryInterface $repository = null
    ) {
    }

    private function repo(): TramiteInternoRepositoryInterface
    {
        return $this->repository ?? app(TramiteInternoRepositoryInterface::class);
    }

    /**
     * Valida en lote si algún trámite del lote es de Tipo 64 (Pago a Proveedores)
     * y garantiza que cuente con todos los anexos obligatorios completos.
     * En caso de indisponibilidad del servicio de logística, aplica bloqueo fail-closed.
     *
     * @param array<int> $documentoIds
     * @return array{valido: bool, mensaje?: string, incompletos?: array<int, mixed>, servicio_no_disponible?: bool}
     */
    public function validarLoteParaRecepcion(array $documentoIds): array
    {
        if (empty($documentoIds)) {
            return ['valido' => true];
        }

        try {
            // 1. Consulta en lote de tipos de trámite delegada al repositorio
            $rawTramites = $this->repo()->obtenerTramitesTipo64($documentoIds);
            $tramitesTipo64 = collect($rawTramites);

            if ($tramitesTipo64->isEmpty()) {
                return ['valido' => true];
            }

            $idsTipo64 = $tramitesTipo64->pluck('cdgo_dtos_ntrnos')->map(fn($id) => (int) $id)->toArray();

            // 2. Consulta en lote de anexos en el módulo de Logística delegada al repositorio
            $incompletos = $this->repo()->obtenerPagosProveedoresIncompletos($idsTipo64);

            if (!empty($incompletos)) {
                $incompletoId = (int) $incompletos[0];
                $tramiteInfo = $tramitesTipo64->firstWhere('cdgo_dtos_ntrnos', $incompletoId);
                $numeroTramite = $tramiteInfo ? $tramiteInfo->nu_tram_todo : (string) $incompletoId;

                return [
                    'valido' => false,
                    'mensaje' => "El trámite N° {$numeroTramite} (Pago a Proveedores) no puede ser recepcionado porque cuenta con anexos obligatorios incompletos.",
                    'incompletos' => $incompletos,
                ];
            }

            return ['valido' => true];
        } catch (Throwable $th) {
            // Regla AG-TI-08: Bloqueo FAIL-CLOSED con log de error estructurado
            Log::error("Fallo crítico verificando anexos de pago a proveedores (Tipo 64): {$th->getMessage()}", [
                'documento_ids' => $documentoIds,
                'exception' => $th::class,
            ]);

            return [
                'valido' => false,
                'mensaje' => 'El servicio de verificación de Logística no está disponible temporalmente. Por seguridad institucional (fail-closed), la recepción no puede procesarse.',
                'servicio_no_disponible' => true,
            ];
        }
    }
}