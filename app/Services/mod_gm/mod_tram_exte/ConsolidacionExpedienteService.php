<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Servicio de Consolidación y Descarga de Expedientes (HIST-001).
 * Implementa la descarga consolidada ZIP con MANIFEST.json y prevención de Zip Slip.
 */
class ConsolidacionExpedienteService
{
    public function __construct(
        private readonly TramiteExternoRepositoryInterface $repository,
        private readonly GestorAdjuntosInterface $gestorAdjuntos
    ) {}

    /**
     * Genera un archivo ZIP consolidado con todos los adjuntos y su manifiesto.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param int $expedienteId
     * @return BinaryFileResponse
     * @throws RuntimeException
     */
    public function generarZipConsolidado(
        ContextoUsuarioAutenticadoDto $contexto,
        int $expedienteId,
        string $ip = ''
    ): BinaryFileResponse
    {
        $movimientos = $this->repository->obtenerHistoricoMovimientos($expedienteId);
        if (empty($movimientos)) {
            throw new RuntimeException("El expediente {$expedienteId} no tiene movimientos históricos registrados.");
        }

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFileName = "EXPEDIENTE-{$expedienteId}-" . date('YmdHis') . ".zip";
        $zipFilePath = "{$tempDir}/{$zipFileName}";

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo inicializar el archivo ZIP para el expediente {$expedienteId}.");
        }

        $manifestFiles = [];
        $archivosIncluidos = 0;

        foreach ($movimientos as $index => $mov) {
            $movimiento = is_array($mov) ? $mov : (array) $mov;
            $movimientoId = (int) ($movimiento['cdgo_trmte_dcmnto'] ?? 0);
            $nombreArchivo = (string) ($movimiento['nom_archivo'] ?? '');
            if (empty($nombreArchivo)) {
                continue;
            }

            // Normalización para prevenir Zip Slip (SEC-14)
            $safeName = basename($nombreArchivo);
            $safeName = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $safeName) ?: "adjunto_{$index}.dat";
            $entryName = "mov_{$movimientoId}_{$safeName}";

            try {
                $rutaFisica = $this->gestorAdjuntos->resolverRutaSegura($nombreArchivo);
                if (file_exists($rutaFisica)) {
                    $zip->addFile($rutaFisica, $entryName);
                    $sha256 = hash_file('sha256', $rutaFisica) ?: '';
                    $manifestFiles[] = [
                        'movement_id'     => $movimientoId,
                        'name'            => $safeName,
                        'zip_entry'       => $entryName,
                        'sha256'          => $sha256,
                        'checksum_sha256' => $sha256,
                        'status'          => 'included',
                    ];
                    $archivosIncluidos++;
                    if ($movimientoId > 0) {
                        $this->repository->registrarAuditoriaDescarga(
                            $movimientoId,
                            $contexto->nombreLogin,
                            $ip
                        );
                    }
                }
            } catch (\Throwable $e) {
                $manifestFiles[] = [
                    'movement_id' => $movimientoId,
                    'name'        => $safeName,
                    'status'      => 'not_accessible',
                    'reason'      => 'Archivo no disponible en storage privado',
                ];
            }
        }

        // Crear MANIFEST.json dentro del ZIP
        $manifest = [
            'expediente_id'      => $expedienteId,
            'generated_at'       => date('Y-m-d H:i:s'),
            'requested_by_login' => $contexto->nombreLogin,
            'exported_by'        => $contexto->nombreLogin,
            'total_files'        => $archivosIncluidos,
            'files_count'        => $archivosIncluidos,
            'pdf_unificado'      => [
                'status'  => 'BLOCKED_USER_DECISION',
                'detalle' => 'Fusión de páginas PDF bloqueada: no existe paquete de unión de PDFs instalado (FPDI/libmergepdf/pdftk) y la instalación de paquetes está congelada.',
            ],
            'files'              => $manifestFiles,
        ];

        try {
            // CEF-P0-05 / CEF-07: Se retira el falso UNIDO-*.pdf. Los archivos auténticos se preservan individualmente en el ZIP.
            $zip->addFromString('MANIFEST.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->close();

            Log::info("ZIP consolidado generado para expediente {$expedienteId} por {$contexto->nombreLogin} ({$archivosIncluidos} archivos incluidos).");

            return response()->download($zipFilePath, "EXPEDIENTE-{$expedienteId}-CONSOLIDADO.zip")->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (file_exists($zipFilePath)) {
                @unlink($zipFilePath);
            }
            throw $e;
        }
    }
}
