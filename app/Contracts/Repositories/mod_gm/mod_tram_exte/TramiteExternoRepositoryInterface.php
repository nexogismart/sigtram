<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\mod_gm\mod_tram_exte;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use Illuminate\Database\Query\Builder;

/**
 * Contrato para el repositorio de Trámite Externo.
 * Resuelve ARCH-04/14 aislando las consultas complejas de base de datos.
 */
interface TramiteExternoRepositoryInterface
{
    /**
     * Obtiene el movimiento activo vigente de un expediente.
     */
    public function obtenerMovimientoActual(int $expedienteId): ?ExpedienteMovimientoDto;

    /**
     * Obtiene todos los movimientos activos (in_esta = '1') de un expediente
     * dentro del contexto aislado de la compañía especificada.
     */
    public function obtenerMovimientosActivosPorExpediente(int $expedienteId, int $companiaId): array;

    /**
     * Obtiene todas las ramas históricas de un expediente dentro de la compañía,
     * sin filtrar por vigencia. Se usa exclusivamente para autorización de lectura.
     */
    public function obtenerMovimientosHistoricosPorExpediente(int $expedienteId, int $companiaId): array;

    /**
     * Obtiene múltiples expedientes por sus IDs dentro del contexto de la compañía.
     */
    public function obtenerMovimientosPorIds(array $expedienteIds, int $companiaId): array;

    /**
     * Construye la consulta canónica base para una bandeja y criterios de búsqueda dados.
     */
    public function construirQueryBandejaBase(
        ContextoUsuarioAutenticadoDto $contexto,
        string $bandeja,
        array $filtros
    ): Builder;

    /**
     * Consulta el listado de expedientes filtrado por bandeja y criterios.
     */
    public function consultarBandeja(
        ContextoUsuarioAutenticadoDto $contexto,
        string $bandeja,
        array $filtros,
        int $pagina = 1,
        int $porPagina = 20
    ): array;

    /**
     * Obtiene el histórico completo de movimientos de un expediente ordenados del movimiento más reciente al más antiguo.
     */
    public function obtenerHistoricoMovimientos(int $expedienteId): array;

    /**
     * Registra una traza de auditoría de descarga en p_sgmto_dscrgas.
     */
    public function registrarAuditoriaDescarga(int $movimientoId, string $login, string $ip): void;

    /**
     * Obtiene el detalle formal de un expediente para el modal de detalle.
     */
    public function obtenerDetalleExpediente(int $expedienteId, int $companiaId): ?array;

    /**
     * Obtiene el correlativo semilla para un tipo de trámite interno vinculado.
     */
    public function obtenerSemillaInterno(int $cdgoTpoTrmte, int $cargoId, int $areaId, int $companiaId): array;

    /**
     * Obtiene las solicitudes de video asociadas a un expediente.
     */
    public function obtenerSolicitudesVideo(int $expedienteId, int $companiaId): array;

    /**
     * Obtiene los catálogos y contadores iniciales para la vista principal index.
     */
    public function obtenerDatosIndex(ContextoUsuarioAutenticadoDto $contexto): array;

    /**
     * Obtiene un movimiento específico por su ID de movimiento validando compañía.
     */
    public function obtenerMovimientoPorMovimientoId(int $movimientoId, int $companiaId): ?ExpedienteMovimientoDto;

    /**
     * Obtiene múltiples movimientos activos por sus IDs de movimiento (cdgo_trmte_dcmnto) validando compañía.
     */
    public function obtenerMovimientosPorMovimientoIds(array $movimientoIds, int $companiaId): array;

    /**
     * Obtiene los movimientos vigentes bloqueándolos para actualización concurrente dentro de transacción.
     */
    public function obtenerMovimientosVigentesParaBloqueo(array $movimientoIds, int $companiaId): array;

    /**
     * Obtiene los conteos globales de las 10 bandejas canónicas para el contexto.
     */
    public function obtenerConteosBandejas(ContextoUsuarioAutenticadoDto $contexto): array;

    /**
     * Obtiene los participantes U de un movimiento de forma lazy, paginada y segura (Spec 10).
     */
    public function obtenerParticipantesU(
        int $movimientoId,
        ContextoUsuarioAutenticadoDto $contexto,
        int $page = 1,
        int $perPage = 25,
        ?string $search = null
    ): array;

    /**
     * Obtiene la auditoría de descargas (p_sgmto_dscrgas) de un movimiento de forma lazy y paginada.
     */
    public function obtenerAuditoriaDescargasPaginada(
        int $movimientoId,
        int $companiaId,
        int $page = 1,
        int $perPage = 10
    ): array;

    /**
     * Obtiene el archivo adjunto autorizado de un movimiento documental.
     */
    public function obtenerAdjuntoAutorizadoPorMovimiento(int $movimientoId, int $companiaId): ?array;

    /**
     * Obtiene los datos del expediente y su historial para la hoja de ruta externa.
     */
    public function obtenerDatosHojaRuta(int $expedienteId, int $companiaId): ?array;

    /**
     * Resuelve de forma segura datos auxiliares de contexto (cargo, área, compañía).
     */
    public function resolverDatosContexto(?int $cdgoLgin, int $cargoId, int $areaId): array;

    /**
     * Desactiva un movimiento externo activo esperado garantizando in_esta = '1'. Retorna filas afectadas.
     */
    public function desactivarMovimientoExternoEsperado(int $movimientoId): int;

    /**
     * Inserta un nuevo movimiento externo y retorna su ID generado.
     */
    public function crearMovimientoExterno(array $datos): int;

    /**
     * Obtiene el cargo principal (orden 1 y activo) de un área.
     */
    public function obtenerCargoPrincipalDeArea(int $areaId): ?object;

    /**
     * Valida si un cargo pertenece a un área y está activo.
     */
    public function validarCargoEnArea(int $cargoId, int $areaId): bool;

    /**
     * Obtiene los datos de contacto y número de expediente para notificaciones.
     */
    public function obtenerContactoNotificacionExpediente(int $expedienteId, int $companiaId): ?array;

    /**
     * Registra un evento en la bitácora de seguimiento p_sgmto_log.
     */
    public function registrarSeguimientoExterno(int $cdgoLgin, string $deSgtoLog, string $ip): void;

    // =========================================================================
    // MÉTODOS EXTENDIDOS PARA DESACOPLAR CONTROLADORES DE TRÁMITE EXTERNO
    // =========================================================================

    // --- 1. Adjuntar Trámite ---
    public function obtenerMovimientosParaAdjuntar(array $filtros, int $pagina, int $porPagina, int $companiaId): array;
    public function obtenerDetalleMovimientoAdjuntar(int $movimientoId): ?array;
    public function actualizarAdjuntoMovimiento(int $movimientoId, string $filename, int $loginId, string $ip): void;

    // --- 2. Archivo Expediente ---
    public function verificarPermisoArchivo(int $perfilId, int $cargoId): bool;
    public function obtenerTablaArchivos(array $filtros, int $page, int $perPage, ?int $cargoId, int $companiaId): array;
    public function obtenerConteosArchivos(?int $cargoId, int $companiaId): array;
    public function archivarExpedientes(array $ids, int $cargoId, int $userId, string $fecha, string $observaciones, string $ip, int $companiaId): int;
    public function notificarExpedientesArchivo(array $ids, int $cargoId, int $userId, string $fecha, string $observaciones, string $adjunto, string $ip, int $companiaId): array;
    public function prestarExpedientes(array $ids, int $cargoId, int $userId, string $fecha, string $observaciones, string $ip, int $companiaId): int;
    public function obtenerMatrizArchivador(): array;
    public function obtenerExpedientesArchivoParaImprimir(array $filtros, ?int $cargoId, int $companiaId, int $limit = 1000): array;
    public function obtenerDetalleCompletoArchivo(int $id): ?array;

    // --- 3. Lista Expedi Todo ---
    public function buscarExpedientesTodos(array $filtros, int $page, int $perPage, int $companiaId): array;
    public function obtenerExpedientesTodosParaImprimir(array $filtros, int $companiaId, int $limit = 2000): array;

    // --- 4. Lista Repor Area ---
    public function buscarReporteArea(array $filtros, int $cargoId, string $bandeja, int $page, int $perPage, int $companiaId): array;

    // --- 5. Lista Reporte ---
    public function buscarReporteExpedientesIngresados(array $filtros, int $page, int $perPage, int $companiaId): array;
    public function obtenerReporteExpedientesIngresadosParaImprimir(array $filtros, int $companiaId, int $limit = 2000): array;

    // --- 6. Notificar Plataforma ---
    public function obtenerTablaNotificarPlataforma(array $filtros, int $page, int $perPage, int $companiaId): array;
    public function notificarExpedientePlataforma(int $cdgoDtoTrmte, string $fecha, ?string $observaciones, ?string $urlImag, ?int $cargoId, int $userId, string $ip): void;

    // --- 7. Redirigir Expediente ---
    public function obtenerGerenciasParaRedirigir(int $companiaId): array;
    public function buscarExpedientesParaRedirigir(array $filtros, int $page, int $perPage, int $companiaId): array;
    public function derivarExpedientesRedirigir(array $ids, int $areaDestino, string $observacion, int $userId, string $ip, int $companiaId): void;
    public function anularExpedienteRedirigir(int $id, string $motivo, int $userId, string $ip, int $companiaId): void;
    public function obtenerExpedientesParaRedirigirImprimir(array $filtros, int $companiaId): array;
    public function obtenerDetalleExpedienteRedirigir(int $id): ?array;

    // --- 8. Reporte Expediente ---
    public function verificarPermisoReporte(int $perfilId, array $roleIds): bool;
    public function obtenerAreaPorCargo(int $cargoId): int;
    public function buscarReporteGeneral(array $filtros, int $page, int $perPage, int $companiaId): array;
    public function obtenerReporteGeneralResumenArea(string $desde, string $hasta, int $companiaId): array;
    public function obtenerReporteGeneralResumenCargo(string $desde, string $hasta, int $areaId, int $companiaId): array;
    public function obtenerReporteGeneralDetalleBandeja(string $tipo, int $id, string $categoria, string $desde, string $hasta, int $companiaId): array;
    public function obtenerDetalleReporteGeneral(int $id, int $companiaId): ?array;
    public function obtenerReporteGeneralParaImprimir(array $filtros, int $companiaId, ?array $selectedIds = null, int $limit = 1000): array;

    // --- 9. Segi Ver Expedi ---
    public function obtenerEstadosDelAreaSeguimiento(int $cargoId): array;
    public function obtenerDiasFestivos(): array;
    public function buscarSeguimientoExpedientes(array $filtros, int $cargoId, int $page, int $perPage, int $companiaId): array;
    public function obtenerHistoricoSeguimiento(int $expedienteId, int $companiaId): array;
    public function cambiarEstadoSeguimiento(int $idTram, int $doc, string $esta, string $estAct, string $observacion, int $cargoId, int $userId, string $ip, int $companiaId): void;

    // --- 10. V2 Lista Reporte Area Cargo Externo ---
    public function obtenerV2EvolucionExterno(int $areaId, string $desde, string $hasta): array;
    public function obtenerCargosAreaExterno(int $areaId): array;
    public function obtenerMetricasCargosV2(array $cargos, string $desde, string $hasta): array;
    public function obtenerDetalleBandejaCargoV2(int $cargoId, int $tipoBandeja, string $desde, string $hasta): array;
    public function obtenerHistoricoMovimientosV2(int $expedienteId, int $companiaId): array;
}