<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use Illuminate\Support\Facades\DB;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException;
use App\Exceptions\mod_gene\TramiteInternoNoEncontradoException;
use App\Policies\mod_gene\TramiteInternoPolicy;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

class ReemplazarDocumentoFirmadoService
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
     * Procesa el reemplazo seguro y auditado de un documento firmado.
     * Si la BD o la auditoría fallan, el nuevo archivo es eliminado físicamente y la transacción se revierte.
     *
     * @return array{nuevo_adjunto: string}
     */
    public function reemplazar(
        int $documentoId,
        UploadedFile $archivo,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if ($documentoId <= 0) {
            throw new InvalidArgumentException('Identificador de trámite inválido.');
        }

        if (!$archivo->isValid()) {
            throw new InvalidArgumentException('El archivo subido no es válido.');
        }

        $mime = $archivo->getMimeType();
        $ext = strtolower($archivo->getClientOriginalExtension());
        if ($mime !== 'application/pdf' || $ext !== 'pdf') {
            throw new InvalidArgumentException('El archivo debe ser un PDF válido.');
        }

        if ($archivo->getSize() > 20 * 1024 * 1024) {
            throw new InvalidArgumentException('El archivo no debe exceder los 20 MiB.');
        }

        // 1. Autorización previa por contexto de compañía y Policy
        $movDto = $this->repository->obtenerExpedienteAutorizadoParaConsolidado($documentoId, $contexto);
        if (!$movDto) {
            throw new TramiteInternoNoEncontradoException('El documento no fue encontrado o no está disponible.');
        }

        if (!$this->policy->reemplazarFirmado($contexto, $movDto)) {
            throw new OperacionTramiteNoAutorizadaException('No autorizado para reemplazar el documento firmado.');
        }

        // 2. Guardar nuevo archivo de forma privada
        $adjuntoDto = $this->gestorAdjuntos->guardar($archivo, 'internos');
        $nuevoPath = $adjuntoDto->rutaRelativa;

        try {
            // 3. Actualización y auditoría dentro de transacción atómica
            $this->db->transaction(function () use ($documentoId, $nuevoPath, $contexto, $ip) {
                $affected = $this->repository->actualizarDocumentoFirmado($documentoId, $contexto->idCompania, ['de_urls_adjnto' => $nuevoPath]);

                if ($affected !== 1) {
                    throw new RuntimeException("No fue posible actualizar el documento firmado en la base de datos.");
                }

                // 4. Registro de auditoría delegado al repositorio
                $this->repository->registrarAuditoriaDescargaInterna([
                    'cdgo_lgin'        => $contexto->getCdgoLginAudit(),
                    'de_urls_dscrgas'  => $nuevoPath,
                    'fe_sgmto_dscrgas' => date('Y-m-d H:i:s'),
                    'de_ip'            => $ip,
                ]);
            });

            return [
                'status'        => 'success',
                'nuevo_adjunto' => $nuevoPath,
            ];
        } catch (\Throwable $e) {
            // Limpieza inmediata del archivo nuevo si falla la transacción o auditoría
            try {
                $this->gestorAdjuntos->eliminar($nuevoPath);
            } catch (\Throwable $ignored) {
                // Silenciar error en limpieza para no enmascarar la excepción original
            }
            throw $e;
        }
    }
}
