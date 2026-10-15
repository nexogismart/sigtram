<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Policies\mod_gene\TramiteInternoPolicy;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use RuntimeException;
use InvalidArgumentException;

class ConsolidadoExpedienteService
{
    private readonly TramiteInternoPolicy $policy;

    public function __construct(
        private readonly TramiteInternoRepositoryInterface $repository,
        private readonly GestorAdjuntosInterface $gestorAdjuntos,
        private readonly DatabaseManager $db,
        ?TramiteInternoPolicy $policy = null
    ) {
        $this->policy = $policy ?? app(TramiteInternoPolicy::class);
    }

    /**
     * Genera un archivo ZIP consolidado con todos los adjuntos autorizados del expediente y su UNIDO-*.pdf real.
     * Garantiza limpieza de archivos temporales y registro obligatorio de auditoría.
     *
     * @return array{zip_path: string, zip_name: string}
     */
    public function generarConsolidado(int $documentoId, ContextoUsuarioAutenticadoDto $contexto, string $ip): array
    {
        if ($documentoId <= 0) {
            throw new InvalidArgumentException("Identificador de expediente inválido.");
        }

        // 1. Resolver expediente autorizado de forma inequívoca por ID de documento (RTI-02 / RTI-06)
        $mov = $this->repository->obtenerExpedienteAutorizadoParaConsolidado($documentoId, $contexto);
        if (!$mov) {
            throw new RuntimeException("El expediente solicitado no existe o no está autorizado.");
        }

        if ($mov->companiaId !== $contexto->idCompania) {
            throw new RuntimeException("El expediente pertenece a otra compañía.");
        }

        if (!$this->policy->descargar($contexto, $mov)) {
            throw new RuntimeException("No tiene permisos para descargar el consolidado de este expediente.");
        }

        // Obtener cabecera del documento vía repositorio
        $doc = $this->repository->obtenerDocumentoParaConsolidado($mov->id, $contexto->idCompania);

        if (!$doc) {
            throw new RuntimeException("El expediente solicitado no existe o no está autorizado.");
        }

        $numeroTramite = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($doc->nu_tram_todo ?? $mov->numero));

        // 2. Obtener adjuntos del histórico de movimientos vía repositorio
        $movimientos = $this->repository->obtenerMovimientosParaConsolidado($mov->id);

        $adjuntosLista = [];
        if (!empty($doc->de_urls_adjnto)) {
            foreach (explode(',', (string) $doc->de_urls_adjnto) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $adjuntosLista[] = ['origen' => 'principal', 'archivo' => $part];
                }
            }
        }
        foreach ($movimientos as $m) {
            $rawAdj = (string) ($m->de_urls_agrdo ?? '');
            if ($rawAdj !== '') {
                foreach (explode(',', $rawAdj) as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $adjuntosLista[] = ['origen' => "mov_{$m->cdgo_trmtes_ntrnos}", 'archivo' => $part];
                    }
                }
            }
        }

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0750, true);
        }

        $randomId = bin2hex(random_bytes(8));
        $zipName = "UNIDO-{$numeroTramite}-{$randomId}.zip";
        $zipPath = "{$tempDir}/{$zipName}";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo inicializar el archivo ZIP temporal.");
        }

        $archivosParaAuditar = [];
        $pdfFiles = [];
        $anexosNoPdf = [];
        $nombresUsados = [];

        try {
            foreach ($adjuntosLista as $item) {
                $rawArchivo = trim((string) $item['archivo']);
                if ($rawArchivo === '') {
                    continue;
                }

                $normalizado = str_replace('\\', '/', ltrim($rawArchivo, '/\\'));

                // Detección estricta de Path Traversal
                if (
                    str_contains($normalizado, '..') ||
                    str_contains($normalizado, "\0") ||
                    (bool) preg_match('/^[a-zA-Z]:/i', $normalizado) ||
                    str_starts_with($normalizado, '/') ||
                    str_starts_with($normalizado, '\\')
                ) {
                    throw new InvalidArgumentException("Intento de acceso no autorizado o Path Traversal detectado en el adjunto.");
                }

                try {
                    $rutaSegura = $this->gestorAdjuntos->resolverRutaSegura($normalizado);
                    $real = realpath($rutaSegura);
                } catch (\Throwable $e) {
                    $correlationId = bin2hex(random_bytes(8));
                    Log::error("Fallo consolidado: Error al resolver ruta segura para {$normalizado}. Correlation ID: {$correlationId}. Error: {$e->getMessage()}");
                    throw new RuntimeException("No se pudo completar el consolidado del expediente: un archivo requerido no pudo ser localizado o validado. Código: {$correlationId}.");
                }

                if ($real === false || !is_file($real)) {
                    $correlationId = bin2hex(random_bytes(8));
                    Log::error("Fallo consolidado: Archivo inexistente o no regular: {$normalizado}. Correlation ID: {$correlationId}");
                    throw new RuntimeException("No se pudo completar el consolidado del expediente: falta un archivo registrado en el expediente. Código: {$correlationId}.");
                }

                if (is_link($rutaSegura)) {
                    $target = readlink($rutaSegura);
                    if ($target === false || !str_starts_with(realpath($target) ?: '', storage_path())) {
                        throw new InvalidArgumentException("Enlace simbólico no seguro detectado en el adjunto {$normalizado}.");
                    }
                }

                $ext = strtolower(pathinfo($normalizado, PATHINFO_EXTENSION)) ?: strtolower(pathinfo($real, PATHINFO_EXTENSION));
                $sample = (string) (file_get_contents($real, false, null, 0, 1024) ?: '');

                if ($ext === 'pdf' || str_starts_with($sample, '%PDF-')) {
                    if (filesize($real) === 0) {
                        $correlationId = bin2hex(random_bytes(8));
                        Log::error("Fallo consolidado: PDF vacío detectado en {$normalizado}. Correlation ID: {$correlationId}");
                        throw new RuntimeException("No se pudo completar la unión de los documentos PDF. No se generó el consolidado. Comuníquese con soporte indicando el código: {$correlationId}.");
                    }
                    if (!str_starts_with($sample, '%PDF-')) {
                        $correlationId = bin2hex(random_bytes(8));
                        Log::error("Fallo consolidado: Estructura PDF inválida o corrupta en {$normalizado}. Correlation ID: {$correlationId}");
                        throw new RuntimeException("No se pudo completar la unión de los documentos PDF. No se generó el consolidado. Comuníquese con soporte indicando el código: {$correlationId}.");
                    }

                    $fullContent = (string) file_get_contents($real);
                    if (str_contains($fullContent, '/Encrypt')) {
                        $correlationId = bin2hex(random_bytes(8));
                        Log::error("Fallo consolidado: PDF cifrado o con contraseña detectado en {$normalizado}. Correlation ID: {$correlationId}");
                        throw new RuntimeException("No se pudo completar la unión de los documentos PDF. No se generó el consolidado. Comuníquese con soporte indicando el código: {$correlationId}.");
                    }

                    $pdfFiles[] = [
                        'ruta' => $real,
                        'nombre' => $normalizado,
                        'origen' => $item['origen'],
                    ];
                } else {
                    $anexosNoPdf[] = [
                        'ruta' => $real,
                        'nombre' => $normalizado,
                        'origen' => $item['origen'],
                    ];
                }
            }

            if (count($pdfFiles) === 0 && count($anexosNoPdf) === 0) {
                $zip->close();
                if (is_file($zipPath)) {
                    @unlink($zipPath);
                }
                throw new RuntimeException("No existen documentos disponibles para generar el archivo consolidado.");
            }

            // 3. Generar documento UNIDO-*.pdf REAL si existen PDFs (P1 - CONSOLIDADO REAL)
            if (count($pdfFiles) > 0) {
                $unidoPdfName = "UNIDO-{$numeroTramite}.pdf";

                if (count($pdfFiles) === 1) {
                    // Si solo existe un PDF válido, se copia como UNIDO-*.pdf con su contenido binario real
                    $unidoPdfContenido = (string) file_get_contents($pdfFiles[0]['ruta']);
                } elseif (class_exists(\setasign\Fpdi\Fpdi::class)) {
                    try {
                        $fpdi = new \setasign\Fpdi\Fpdi();
                        foreach ($pdfFiles as $p) {
                            $pageCount = $fpdi->setSourceFile($p['ruta']);
                            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                                $templateId = $fpdi->importPage($pageNo);
                                $size = $fpdi->getTemplateSize($templateId);
                                $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                                $fpdi->useTemplate($templateId);
                            }
                        }
                        $unidoPdfContenido = $fpdi->Output('S');
                    } catch (\Throwable $e) {
                        $correlationId = bin2hex(random_bytes(8));
                        Log::error("Fallo durante la fusión de páginas con FPDI. Correlation ID: {$correlationId}. Error: {$e->getMessage()}");
                        throw new RuntimeException("No se pudo completar la unión de los documentos PDF. No se generó el consolidado. Comuníquese con soporte indicando el código: {$correlationId}.", 0, $e);
                    }
                } else {
                    // BLOCKED: Múltiples PDFs sin biblioteca de unión de páginas (setasign/fpdi) instalada
                    $correlationId = bin2hex(random_bytes(8));
                    Log::error("Fallo consolidado: Múltiples PDFs detectados (" . count($pdfFiles) . ") sin librería 'setasign/fpdi' instalada. No está permitido invocar binarios CLI externos. Correlation ID: {$correlationId}");
                    throw new RuntimeException("No se pudo completar la unión de los documentos PDF. No se generó el consolidado. Comuníquese con soporte indicando el código: {$correlationId}.");
                }

                $okString = $zip->addFromString($unidoPdfName, $unidoPdfContenido);
                if (!$okString) {
                    throw new RuntimeException("Error al empaquetar el documento consolidado PDF {$unidoPdfName}.");
                }

                foreach ($pdfFiles as $pf) {
                    $archivosParaAuditar[] = $pf['nombre'];
                }
            }

            // 4. Incluir anexos no PDF individualmente en el ZIP sin convertirlos
            foreach ($anexosNoPdf as $anexo) {
                $entryName = "anexo_{$anexo['origen']}_" . basename($anexo['nombre']);
                while (isset($nombresUsados[$entryName])) {
                    $entryName = "anexo_{$anexo['origen']}_" . bin2hex(random_bytes(4)) . "_" . basename($anexo['nombre']);
                }
                $nombresUsados[$entryName] = true;

                $okAnexo = $zip->addFile($anexo['ruta'], $entryName);
                if (!$okAnexo) {
                    $correlationId = bin2hex(random_bytes(8));
                    Log::error("Fallo al incorporar anexo al ZIP consolidado: {$entryName}. Correlation ID: {$correlationId}");
                    throw new RuntimeException("No se pudo incorporar un anexo al consolidado del expediente. Código: {$correlationId}.");
                }
                $archivosParaAuditar[] = $anexo['nombre'];
            }

            $okClose = $zip->close();
            if (!$okClose) {
                throw new RuntimeException("No se pudo cerrar el archivo ZIP consolidado.");
            }

            // 5. Persistencia ATÓMICA de todas las auditorías en una única transacción de BD (RTI-C07)
            $auditoriasParaPersistir = array_merge(array_unique($archivosParaAuditar), [$zipName]);
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
                $correlationId = bin2hex(random_bytes(8));
                Log::error("Fallo consolidado: Fallo en transacción de auditoría. Correlation ID: {$correlationId}. Error: {$e->getMessage()}");
                throw new RuntimeException("Fallo en la persistencia atómica de auditoría legal de descarga consolidada: no es posible proceder. Código: {$correlationId}.");
            }

            return [
                'zip_path' => $zipPath,
                'zip_name' => "EXPEDIENTE-{$numeroTramite}.zip",
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

    public function getRepository(): TramiteInternoRepositoryInterface
    {
        return $this->repository;
    }
}
