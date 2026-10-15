<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\mod_gene\mod_tram_inter;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;

interface TramiteInternoRepositoryInterface
{
    /**
     * Obtiene el movimiento interno activo asegurando scope de compania.
     */
    public function obtenerMovimientoPorId(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto;

    /**
     * Obtiene un movimiento histórico sin filtrar vigencia (in_esta = 0 o 1) con scope de compañía.
     */
    public function obtenerMovimientoHistoricoPorId(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto;

    /**
     * Alias canónico de obtenerMovimientoHistoricoPorId para autorización histórica centralizada.
     */
    public function obtenerMovimientoHistoricoAutorizado(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto;

    /**
     * Obtiene la cabecera de un documento histórico asegurando scope de compañía
     * sin filtrar vigencia (in_esta). Retorna null si no existe o es de otra compañía.
     */
    public function obtenerDocumentoHistoricoAutorizado(int $documentoId, int $companiaId): ?object;

    /**
     * Obtiene un lote de movimientos garantizando que todos pertenezcan a la compania indicada.
     *
     * @param array<int> $movimientoIds
     * @return array<TramiteInternoMovimientoDto>
     */
    public function obtenerMovimientosPorIds(array $movimientoIds, int $companiaId): array;

    /**
     * Retorna las columnas canónicas seleccionadas explícitamente para una bandeja.
     *
     * @return array<string>
     */
    public function obtenerColumnasBandeja(string $bandeja): array;

    /**
     * Lista registros de una bandeja con paginacion y filtros seguros.
     *
     * @param array<string, mixed> $filtros
     * @return array{data: array, meta: array}
     */
    public function listarBandeja(
        string $bandeja,
        ContextoUsuarioAutenticadoDto $contexto,
        array $filtros = [],
        int $page = 1,
        int $perPage = 25
    ): array;

    /**
     * Cuenta el total de registros para una bandeja aplicando los mismos scopes y filtros.
     *
     * @param array<string, mixed> $filtros
     */
    public function contarBandeja(
        string $bandeja,
        ContextoUsuarioAutenticadoDto $contexto,
        array $filtros = []
    ): int;

    /**
     * Obtiene el historico de movimientos de un tramite interno con scope de compania.
     *
     * @return array{data: array, meta: array}
     */
    public function obtenerHistorial(
        int $movimientoId,
        ContextoUsuarioAutenticadoDto $contexto,
        int $page = 1,
        int $perPage = 50,
        ?int $documentoId = null
    ): array;

    /**
     * Obtiene el detalle completo del documento (voucher/ficha) con autorizacion de compania.
     */
    public function obtenerDetalleExpediente(int $movimientoId, ContextoUsuarioAutenticadoDto $contexto): ?array;

    /**
     * Obtiene los conteos globales de las bandejas autorizadas para el contexto interno.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @return array<string, int>
     */
    public function obtenerConteosBandejas(ContextoUsuarioAutenticadoDto $contexto): array;

    /**
     * Obtiene los participantes U de un movimiento interno de forma lazy, paginada y segura (Spec 10).
     *
     * @param int $movimientoId
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param int $page
     * @param int $perPage
     * @param string|null $search
     * @return array<string, mixed>
     */
    public function obtenerParticipantesU(
        int $movimientoId,
        ContextoUsuarioAutenticadoDto $contexto,
        int $page = 1,
        int $perPage = 25,
        ?string $search = null
    ): array;

    /**
     * Retorna la trazabilidad paginada de descargas (p_sgmto_dscrgas) asociadas al archivo adjunto del movimiento.
     *
     * @param int $movimientoId
     * @param int $companiaId
     * @param int $page
     * @param int $perPage
     * @return array<string, mixed>
     */
    public function obtenerAuditoriaDescargasPaginada(
        int $movimientoId,
        int $companiaId,
        int $page = 1,
        int $perPage = 10
    ): array;

    /**
     * Recupera movimientos vigentes bajo lock pesimista agrupado para operaciones transaccionales por lote.
     *
     * @param array<int> $ids
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @return array<TramiteInternoMovimientoDto>
     */
    public function obtenerMovimientosVigentesParaOperacion(
        array $ids,
        ContextoUsuarioAutenticadoDto $contexto
    ): array;

    /**
     * Obtiene los documentos vigentes bajo lock pesimista para operaciones transaccionales.
     *
     * @param array<int> $documentoIds
     * @return array<TramiteInternoMovimientoDto>
     */
    public function obtenerDocumentosVigentesParaOperacion(
        array $documentoIds,
        ContextoUsuarioAutenticadoDto $contexto
    ): array;

    /**
     * Obtiene un movimiento interno vigente por su ID único de movimiento (r.cdgo_trmtes_ntrnos).
     */
    public function obtenerMovimientoVigentePorId(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto;

    /**
     * Resuelve de forma inequívoca el expediente y su movimiento autorizado para descarga consolidada.
     */
    public function obtenerExpedienteAutorizadoParaConsolidado(
        int $documentoId,
        ContextoUsuarioAutenticadoDto $contexto
    ): ?TramiteInternoMovimientoDto;

    /**
     * Obtiene los datos del cargo por su identificador.
     */
    public function obtenerCargoPorId(int $cargoId): ?object;

    /**
     * Obtiene el último movimiento histórico de un documento asegurando scope de compañía.
     */
    public function obtenerUltimoMovimientoPorDocumento(int $documentoId, int $companiaId): ?TramiteInternoMovimientoDto;

    /**
     * Obtiene el voucher o comprobante de movimiento interno con datos completos para la vista.
     */
    public function obtenerVoucherMovimientoInterno(int $movimientoId, ContextoUsuarioAutenticadoDto $contexto): ?object;

    /**
     * Obtiene los comprobantes de pago de caja asociados a trámites internos autorizados.
     *
     * @param array<string, mixed> $criterios
     * @return array<object>
     */
    public function obtenerComprobantesPago(array $criterios, ContextoUsuarioAutenticadoDto $contexto): array;

    /**
     * Obtiene el documento interno para la hoja de ruta evaluando autorización dentro de la consulta.
     */
    public function obtenerDocumentoParaHojaRuta(int $documentoId, ContextoUsuarioAutenticadoDto $contexto): ?object;

    /**
     * Obtiene los datos de cabecera de impresión para el reporte de bandeja.
     *
     * @return array{cargo: ?object, cargo_nombre: string, area_nombre: string}
     */
    public function obtenerCabeceraImpresion(ContextoUsuarioAutenticadoDto $contexto): array;

    /**
     * Obtiene cargos y colaboradores de una misma área.
     *
     * @return array<array{cdgo_crgo: int, de_crgo: string, no_crto: string, texto: string}>
     */
    public function obtenerCargosDeArea(int $areaId, int $cargoIdExcluir = 0): array;

    /**
     * Obtiene todas las áreas municipales destino activas ordenadas canónicamente.
     *
     * @return array<object>
     */
    public function obtenerAreasDestino(): array;

    /**
     * Obtiene los tipos de documento autorizados para un área.
     *
     * @return array<object>
     */
    public function obtenerTiposDocumentoInterno(int $areaId): array;

    /**
     * Obtiene información del año municipal por su número/descripción.
     */
    public function obtenerAnioPorNumero(int $anio): ?object;

    /**
     * Obtiene el último año municipal registrado.
     */
    public function obtenerUltimoAnio(): ?object;

    /**
     * Obtiene información del tipo de documento interno por su ID.
     */
    public function obtenerTipoDocumentoInterno(int $tipoDocId): ?object;

    /**
     * Obtiene y reserva la siguiente semilla interna aplicando lock pesimista si es solicitado.
     */
    public function obtenerSemillaInternaParaBloqueo(int $cdgoAnio, int $cdgoTpoTrmte, ?int $cargoId, ?int $areaId, bool $inScncal, bool $lock = false): int;

    /**
     * Inserta un registro en la tabla de semillas internas.
     *
     * @param array<string, mixed> $datos
     */
    public function crearSemillaInterna(array $datos): void;

    /**
     * Obtiene datos del trámite de referencia para nuevo documento interno.
     */
    public function obtenerReferenciaDocumentoInterno(int $refDocId): ?object;

    /**
     * Inserta un nuevo documento interno (p_dtos_ntrnos) y retorna su ID generado.
     *
     * @param array<string, mixed> $datos
     */
    public function crearDocumentoInterno(array $datos): int;

    /**
     * Inserta un nuevo movimiento interno (r_trmtes_ntrnos) y retorna su ID generado.
     *
     * @param array<string, mixed> $datos
     */
    public function crearMovimientoInterno(array $datos): int;

    /**
     * Desactiva un movimiento interno vigente esperado validando concurrencia estricta.
     */
    public function desactivarMovimientoInternoEsperado(int $movimientoId): int;

    /**
     * Registra un evento en la bitácora de seguimiento (p_sgmto_log).
     */
    public function registrarSeguimientoInterno(int $cdgoLgin, string $deSgtoLog, string $ip): void;

    /**
     * Obtiene un cargo por su ID con opción de bloqueo pesimista.
     */
    public function obtenerCargoDestino(int $cargoId, bool $lockForUpdate = false): ?object;

    /**
     * Obtiene un área por su ID con opción de bloqueo pesimista.
     */
    public function obtenerArea(int $areaId, bool $lockForUpdate = false): ?object;

    /**
     * Obtiene el cargo principal de un área con opción de bloqueo pesimista.
     */
    public function obtenerCargoPrincipalDeArea(int $areaId, bool $lockForUpdate = false): ?object;

    /**
     * Obtiene la cabecera completa del trámite para impresión de hoja de ruta.
     */
    public function obtenerDetalleTramiteParaHojaRuta(int $tramiteId): ?object;

    /**
     * Obtiene todos los movimientos de un trámite para la hoja de ruta en orden ascendente.
     *
     * @return array<object>
     */
    public function obtenerMovimientosHojaRuta(int $tramiteId): array;

    /**
     * Obtiene el siguiente valor de secuencia para adjuntos de trámite interno.
     */
    public function obtenerSiguienteSecuenciaInterna(): int;

    // --- MÉTODOS MIGRADOS DE SERVICES Y CONTROLLERS ---

    public function obtenerDocumentoParaConsolidado(int $documentoId, int $companiaId): ?object;

    public function obtenerMovimientosParaConsolidado(int $documentoId): array;

    public function obtenerDocumentosAutorizadosLote(array $ids, int $companiaId): array;

    public function obtenerMovimientosLote(array $ids, int $companiaId): array;

    public function obtenerDocumentoParaDescarga(int $documentoId, int $companiaId): ?object;

    public function obtenerMovimientoParaDescarga(int $movimientoId, int $companiaId): ?object;

    public function actualizarDocumentoFirmado(int $cdgoDtoNtrno, int $companiaId, array $datos): int;

    public function registrarAuditoriaDescargaInterna(array $datos): void;

    public function obtenerTramitesTipo64(array $ids): array;

    public function obtenerPagosProveedoresIncompletos(array $idsTipo64): array;

    public function obtenerCargoInfo(int $cargoId): ?object;

    public function obtenerAreasCatalogo(bool $isAdmin, int $areaUsuario): array;

    public function obtenerDestinoAreasCatalogo(): array;

    public function obtenerTiposPorArea(?int $areaId): array;

    public function buscarTramitesInternos(array $filtros, int $perPage = 36, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function obtenerDetalleTramite(int $id): ?object;

    public function obtenerHistorialMovimientos(int $id): array;

    public function obtenerAniosCatalogo(): array;

    public function obtenerTiposSecuencialCatalogo(): array;

    public function obtenerInScncalTipo(int $cdgoTpoTrmte): int;

    public function obtenerListaSecuenciales(int $cdgoAnio, int $cdgoTpoTrmte, int $areaId, int $cargoId, int $inScncal): array;

    public function obtenerMaxSecuencial(int $cdgoAnio, int $cdgoTpoTrmte, int $areaId, int $cargoId, int $inScncal): int;

    public function insertarSecuencial(array $datos): void;

    public function obtenerCargosPorArea(int $areaId): array;

    public function obtenerAreaUsuarioPorCargo(int $cargoId): int;

    public function contarReporteEntrada(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null, ?int $cargoSa = null): int;

    public function contarReporteRecibidos(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null, ?int $cargoSa = null): int;

    public function contarReporteSalida(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null): int;

    public function contarReporteFinalizado(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null, ?int $cargoSa = null): int;

    public function obtenerUsuariosDeCargo(int $cargoId): array;

    public function obtenerDetalleReporteCargoInterno(array $filtros, int $perPage, int $page): array;

    public function obtenerEvolucionReporteInterno(array $cargosIds, string $desde, string $hasta): array;

    public function obtenerReporteTramitesPorAreas(string $desde, string $hasta): array;

    public function contarReporteCargoEntrada(int $cdgo, string $desde, string $hasta): int;

    public function contarReporteCargoRecibidos(int $cdgo, string $desde, string $hasta): int;

    public function contarReporteCargoSalida(int $cdgo, string $desde, string $hasta): int;

    public function contarReporteCargoFinalizado(int $cdgo, string $desde, string $hasta): int;

    public function obtenerTramitesReporteCargoDetalle(int $cargoId, int $tipoBandeja, string $desde, string $hasta): array;

    public function obtenerReporteTramitesPorAreasDetallado(string $fechaDesde, string $fechaHasta): array;

    public function obtenerTramitesReporteAreaDetalle(int $areaId, int $tipo, string $fechaDesde, string $fechaHasta): array;

    public function obtenerV2Evolucion(int $areaId, string $desde, string $hasta): array;

    public function obtenerV2AvgTiempo(int $areaId, string $desde, string $hasta): ?float;

    public function obtenerV2TramitesDetalle(int $cargoId, int $tipoBandeja, string $desde, string $hasta): array;

    public function obtenerRutaAreasPorDocumento(int $documentoId): array;
}
