<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException;
use App\Exceptions\mod_gene\TramiteInternoNoEncontradoException;
use App\Policies\mod_gene\TramiteInternoPolicy;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class DescargaLoteTramiteInternoService
{
    private string $schema;
    private readonly TramiteInternoPolicy $policy;

    public function __construct(
        private readonly TramiteInternoRepositoryInterface $repository,
        private readonly GestorAdjuntosInterface $gestorAdjuntos,
        private readonly DatabaseManager $db,
        ?TramiteInternoPolicy $policy = null
    ) {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $this->policy = $policy ?? app(TramiteInternoPolicy::class);
    }

    /**
     * Procesa la descarga por lote de adjuntos de forma segura, fail-closed y auditada.
     * Si cualquier elemento no está autorizado o falla su auditoría, se aborta y se limpia el ZIP temporal.
     *
     * @param array<int|string> $ids
     * @return array{zip_path: string, zip_name: string}
     */
    public function generarLoteZip(array $ids, ContextoUsuarioAutenticadoDto $contexto, string $ip): array
    {
        if (empty($ids)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un trámite para descargar.');
        }

        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($idsNormalizados)) {
            throw new InvalidArgumentException('No se indicaron identificadores válidos para la descarga.');
        }

        $countSolicitado = count($idsNormalizados);

        // 1. Autorización individual fail-closed vía repositorio
        $docsAutorizados = $this->repository->obtenerDocumentosAutorizadosLote($idsNormalizados, $contexto->idCompania);

        if (count($docsAutorizados) !== $countSolicitado) {
            throw new TramiteInternoNoEncontradoException(
                'Uno o más trámites solicitados no existen, están inactivos o pertenecen a otra compañía.'
            );
        }

        // 2. Comprobar permisos y participación de cada trámite vía Policy
        foreach ($idsNormalizados as $docId) {
            $mov = $this->repository->obtenerExpedienteAutorizadoParaConsolidado($docId, $contexto);
            if (!$mov || !$this->policy->descargar($contexto, $mov)) {
                throw new OperacionTramiteNoAutorizadaException(
                    "No tiene autorización para descargar los adjuntos del expediente {$docId}."
                );
            }
        }

        // 3. Obtener adjuntos de los movimientos vigentes vía repositorio
        $movimientos = $this->repository->obtenerMovimientosLote($idsNormalizados, $contexto->idCompania);

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0750, true);
        }

        $randomSuffix = bin2hex(random_bytes(16));
        $zipName = "LOTE_{$randomSuffix}.zip";
        $zipPath = "{$tempDir}/{$zipName}";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear el archivo ZIP temporal para el lote.");
        }

        $archivosAgregados = 0;
        $nombresUsados = [];
        $archivosParaAuditar = [];

        try {
            foreach ($movimientos as $index => $mov) {
                $rawArchivo = trim((string) $mov->de_urls_agrdo);
                if (empty($rawArchivo)) {
                    continue;
                }

                // Detección estricta de Path Traversal
                if (
                    str_contains($rawArchivo, '..') ||
                    str_contains($rawArchivo, '\\') ||
                    str_contains($rawArchivo, "\0") ||
                    str_starts_with($rawArchivo, '/') ||
                    str_contains($rawArchivo, ':')
                ) {
                    throw new InvalidArgumentException("Path Traversal detectado: Nombre de archivo sospechoso en el trámite {$mov->cdgo_dtos_ntrnos}.");
                }

                $normalizado = str_replace('\\', '/', ltrim($rawArchivo, '/\\'));

                // Resolución segura mediante GestorAdjuntosInterface
                try {
                    $rutaSegura = $this->gestorAdjuntos->resolverRutaSegura($normalizado);
                    $realPath = realpath($rutaSegura);
                } catch (\Throwable $e) {
                    $correlationId = bin2hex(random_bytes(8));
                    Log::error("Fallo descarga lote: No se pudo resolver la ruta del adjunto {$normalizado}. Correlation ID: {$correlationId}. Error: {$e->getMessage()}");
                    throw new RuntimeException("No se pudo completar la descarga en lote: un archivo requerido no pudo ser localizado o validado de forma segura. Código: {$correlationId}.");
                }

                if ($realPath === false || !is_file($realPath)) {
                    $correlationId = bin2hex(random_bytes(8));
                    Log::error("Fallo descarga lote: Archivo no regular o inexistente: {$normalizado}. Correlation ID: {$correlationId}");
                    throw new RuntimeException("No se pudo completar la descarga en lote: falta un archivo registrado en el expediente. Código: {$correlationId}.");
                }

                // Prevenir symlinks externos
                if (is_link($rutaSegura)) {
                    $target = readlink($rutaSegura);
                    if ($target === false || !str_starts_with(realpath($target) ?: '', storage_path())) {
                        throw new InvalidArgumentException("Enlace simbólico no seguro detectado en el archivo {$normalizado}.");
                    }
                }

                // Generar nombre opaco y seguro dentro del ZIP evitando colisiones
                $entryName = "doc_{$mov->cdgo_dtos_ntrnos}_" . ($index + 1) . "_" . basename($normalizado);
                while (isset($nombresUsados[$entryName])) {
                    $entryName = "doc_{$mov->cdgo_dtos_ntrnos}_" . ($index + 1) . "_" . bin2hex(random_bytes(4)) . "_" . basename($normalizado);
                }
                $nombresUsados[$entryName] = true;

                $agregado = $zip->addFile($realPath, $entryName);
                if (!$agregado) {
                    throw new RuntimeException("No se pudo agregar el archivo {$entryName} al paquete ZIP.");
                }
                $archivosAgregados++;
                $archivosParaAuditar[] = $normalizado;
            }

            if ($archivosAgregados === 0) {
                $zip->close();
                if (is_file($zipPath)) {
                    @unlink($zipPath);
                }
                throw new RuntimeException("Los trámites seleccionados no contienen archivos físicos disponibles para descargar.");
            }

            $cerrado = $zip->close();
            if (!$cerrado) {
                throw new RuntimeException("No se pudo cerrar el archivo ZIP temporal del lote.");
            }

            // Persistencia ATÓMICA de todas las auditorías en una única transacción de BD (RTI-C07)
            $auditoriasParaPersistir = array_merge($archivosParaAuditar, [$zipName]);
            $loginAuditId = $contexto->getCdgoLginAudit();
            $now = date('Y-m-d H:i:s');

            try {
                $this->db->transaction(function () use ($auditoriasParaPersistir, $loginAuditId, $ip, $now) {
                    foreach ($auditoriasParaPersistir as $itemAudit) {
                        $this->repository->registrarAuditoriaDescargaInterna([
                            'cdgo_lgin'        => $loginAuditId,
                            'de_urls_dscrgas'  => $itemAudit,
                            'fe_sgmto_dscrgas' => $now,
                            'de_ip'            => $ip,
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                if (is_file($zipPath)) {
                    @unlink($zipPath);
                }
                throw new RuntimeException("Fallo en la persistencia atómica de auditoría legal de descarga: no es posible proceder: " . $e->getMessage(), 0, $e);
            }

            return [
                'zip_path' => $zipPath,
                'zip_name' => "EXPEDIENTES_LOTE_" . date('Ymd_His') . ".zip",
            ];
        } catch (\Throwable $e) {
            try {
                @$zip->close();
            } catch (\Throwable) {
            }
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            throw $e;
        }
    }
}
