<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Policies\mod_gene\TramiteInternoPolicy;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DescargaSeguraTramiteInternoService
{

    public function __construct(
        private readonly TramiteInternoRepositoryInterface $repository,
        private readonly GestorAdjuntosInterface $gestorAdjuntos,
        private readonly TramiteInternoPolicy $policy
    ) {
    }

    /**
     * Resuelve, audita y entrega un adjunto privado de forma segura por movimiento y nombre relativo.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function procesarDescarga(
        int $movimientoId,
        string $nombreArchivo,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        $normalizado = str_replace('\\', '/', ltrim($nombreArchivo, '/\\'));

        // 1. Detección temprana de Path Traversal
        if (
            str_contains($nombreArchivo, '..') ||
            str_contains($nombreArchivo, '\\') ||
            str_contains($nombreArchivo, "\0") ||
            str_starts_with($nombreArchivo, '/') ||
            str_contains($nombreArchivo, ':')
        ) {
            throw new InvalidArgumentException("Intento de acceso no autorizado o Path Traversal detectado.");
        }

        // 2. Autorización vía Repository + Policy (IDOR Protection)
        $movimiento = $this->repository->obtenerMovimientoPorId($movimientoId, $contexto->idCompania);
        if (!$movimiento) {
            throw new RuntimeException("El recurso solicitado no fue encontrado o no está disponible.");
        }

        if (!$this->policy->descargar($contexto, $movimiento)) {
            throw new RuntimeException("No tiene permisos para descargar adjuntos de este expediente.");
        }

        // 3. Resolución segura de ruta física en almacenamiento privado
        $rutaSegura = $this->gestorAdjuntos->resolverRutaSegura($normalizado);

        $realPath = realpath($rutaSegura);
        if ($realPath === false || !is_file($realPath) || !str_starts_with($realPath, storage_path())) {
            throw new RuntimeException("El archivo solicitado no existe o no es un archivo regular.");
        }

        // Prevenir symlinks externos
        if (is_link($rutaSegura)) {
            $target = readlink($rutaSegura);
            if ($target === false || !str_starts_with(realpath($target) ?: '', storage_path())) {
                throw new InvalidArgumentException("Enlace simbólico no seguro detectado.");
            }
        }

        // 4. Auditoría obligatoria en p_sgmto_dscrgas (AUD-TI-01 Fail-Closed)
        $this->auditarDescarga($contexto->getCdgoLginAudit(), $ip, $normalizado);

        return [
            'ruta_fisica' => $realPath,
            'nombre_descarga' => basename($normalizado),
        ];
    }

    /**
     * Resuelve, autoriza, audita y entrega el adjunto de un documento por su ID canónico (RTI-C01).
     */
    public function descargarPorDocumento(
        int $documentoId,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if ($documentoId <= 0) {
            throw new InvalidArgumentException("Identificador de documento no válido.");
        }

        $movDto = $this->repository->obtenerExpedienteAutorizadoParaConsolidado($documentoId, $contexto);
        if (!$movDto) {
            throw new RuntimeException("El recurso solicitado no fue encontrado o no está disponible.");
        }

        if (!$this->policy->descargar($contexto, $movDto)) {
            throw new RuntimeException("No tiene permisos para descargar adjuntos de este expediente.");
        }

        // Obtener la ruta del archivo almacenada en BD vía repositorio
        $doc = $this->repository->obtenerDocumentoParaDescarga($documentoId, $contexto->idCompania);

        $rawRuta = trim((string) ($doc->de_urls_adjnto ?? ''));
        if ($rawRuta === '') {
            throw new RuntimeException("El documento no contiene ningún archivo adjunto registrado.");
        }

        $rawRuta = trim(explode(',', $rawRuta)[0]);

        return $this->resolverYAuditar($rawRuta, $contexto, $ip);
    }

    /**
     * Resuelve, autoriza, audita y entrega el adjunto de un movimiento por su ID canónico (RTI-C01).
     */
    public function descargarPorMovimiento(
        int $movimientoId,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if ($movimientoId <= 0) {
            throw new InvalidArgumentException("Identificador de movimiento no válido.");
        }

        $movDto = $this->repository->obtenerMovimientoPorId($movimientoId, $contexto->idCompania);
        if (!$movDto) {
            throw new RuntimeException("El recurso solicitado no fue encontrado o no está disponible.");
        }

        if (!$this->policy->descargar($contexto, $movDto)) {
            throw new RuntimeException("No tiene permisos para descargar adjuntos de este expediente.");
        }

        $movRow = $this->repository->obtenerMovimientoParaDescarga($movimientoId, $contexto->idCompania);

        $rawRuta = trim((string) ($movRow->de_urls_agrdo ?? $movRow->de_urls_adjnto ?? ''));
        if ($rawRuta === '') {
            throw new RuntimeException("El movimiento no contiene ningún archivo adjunto registrado.");
        }

        $rawRuta = trim(explode(',', $rawRuta)[0]);

        return $this->resolverYAuditar($rawRuta, $contexto, $ip);
    }

    /**
     * Valida la ruta relativa, resuelve la contención en storage y registra la auditoría.
     */
    private function resolverYAuditar(
        string $rawRuta,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        $normalizado = str_replace('\\', '/', ltrim($rawRuta, '/\\'));

        if (
            str_contains($normalizado, '..') ||
            str_contains($normalizado, "\0") ||
            str_contains($normalizado, ':')
        ) {
            throw new InvalidArgumentException("Intento de acceso no autorizado o Path Traversal detectado.");
        }

        $rutaSegura = $this->gestorAdjuntos->resolverRutaSegura($normalizado);
        $realPath = realpath($rutaSegura);

        if ($realPath === false || !is_file($realPath) || !str_starts_with($realPath, storage_path())) {
            throw new RuntimeException("El archivo solicitado no existe o no es un archivo regular.");
        }

        if (is_link($rutaSegura)) {
            $target = readlink($rutaSegura);
            if ($target === false || !str_starts_with(realpath($target) ?: '', storage_path())) {
                throw new InvalidArgumentException("Enlace simbólico no seguro detectado.");
            }
        }

        $this->auditarDescarga($contexto->getCdgoLginAudit(), $ip, $normalizado);

        return [
            'ruta_fisica' => $realPath,
            'nombre_descarga' => basename($normalizado),
        ];
    }

    /**
     * Registra obligatoriamente la descarga en la tabla de auditoría usando las 4 columnas reales.
     * Si la BD o inserción falla, la excepción no se suprime y aborta la entrega.
     */
    public function auditarDescarga(int $loginId, string $ip, string $archivo): void
    {
        try {
            $this->repository->registrarAuditoriaDescargaInterna([
                'cdgo_lgin'        => $loginId,
                'de_urls_dscrgas'  => $archivo,
                'fe_sgmto_dscrgas' => date('Y-m-d H:i:s'),
                'de_ip'            => $ip,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Fallo en el registro de auditoría legal de descarga: no es posible proceder. " . $e->getMessage(), 0, $e);
        }
    }
}