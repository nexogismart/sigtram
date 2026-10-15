<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\mod_gm\mod_tram_exte;

interface IngresoExpedienteRepositoryInterface
{
    /**
     * Obtiene los catálogos y parámetros necesarios para renderizar el formulario de ingreso.
     *
     * @return array{
     *     gerencias: \Illuminate\Support\Collection,
     *     servicios: \Illuminate\Support\Collection,
     *     tiposDocumento: \Illuminate\Support\Collection,
     *     tiposIngreso: \Illuminate\Support\Collection,
     *     tipoExterno: ?object,
     *     tipoSimple: ?object,
     *     tipoObservado: ?object,
     *     areaObservado: ?object
     * }
     */
    public function obtenerDatosFormularioIngreso(int $companiaId): array;

    /**
     * Busca solicitante persona natural por número de documento.
     */
    public function buscarUsuarioNatural(string $documento, int $companiaId): ?array;

    /**
     * Busca solicitante persona jurídica por número de RUC.
     */
    public function buscarUsuarioJuridico(string $ruc, int $companiaId): ?array;

    /**
     * Busca solicitante persona jurídica por razón social.
     */
    public function buscarUsuarioJuridicoPorRazon(string $razon, int $companiaId): ?array;

    /**
     * Sugiere personas jurídicas por término de razón social para autocompletado.
     */
    public function sugerirUsuariosJuridicos(string $termino, int $companiaId, int $limite = 20): array;

    /**
     * Lista subgerencias dependientes de una gerencia en la compañía indicada.
     */
    public function listarSubgerencias(int $gerenciaId, int $companiaId): array;

    /**
     * Lista trámites TUPA asociados a un servicio de área.
     */
    public function listarTramitesPorServicio(int $servicioId, int $companiaId): array;

    /**
     * Lista requisitos y condición de silencio administrativo de un trámite TUPA.
     *
     * @return array{requisitos: \Illuminate\Support\Collection, silencio: ?object}
     */
    public function listarRequisitosPorTramite(int $tramiteId, int $companiaId): array;

    /**
     * Busca trámites TUPA por descripción de trámite.
     */
    public function buscarTramitesTupa(string $termino, int $companiaId, int $limite = 20): array;

    /**
     * Lista expedientes paginados según filtros operativos.
     *
     * @return array{
     *     total: int,
     *     pagina: int,
     *     paginas: int,
     *     items: \Illuminate\Support\Collection
     * }
     */
    public function listarExpedientes(array $filtros, int $companiaId, int $pagina, int $porPagina): array;

    /**
     * Obtiene los datos del expediente y atendente para el voucher oficial.
     *
     * @return array{expediente: object, attendant: ?object}|null
     */
    public function obtenerVoucherExpediente(int $expedienteId, int $companiaId): ?array;

    /**
     * Resuelve o crea un solicitante (persona natural o jurídica) y retorna su cdgo_usrios.
     *
     * @param array<string, mixed> $datos
     */
    public function resolverSolicitante(array $datos, int $loginId, int $companiaId): int;

    /**
     * Genera el correlativo y número de expediente bajo bloqueo concurrente (lockForUpdate).
     *
     * @param array<string, mixed> $datos
     * @return array{
     *     nu_expe_todo: string,
     *     nu_expe_trmte: int,
     *     cdgo_anio: int,
     *     cdgo_tpo_trmte: int,
     *     in_docu_trmte: string
     * }
     */
    public function generarNumeroExpediente(array $datos, int $companiaId): array;

    /**
     * Obtiene el primer cargo activo asignado a un usuario de login.
     */
    public function obtenerPrimerCargoUsuario(int $loginId): int;

    /**
     * Resuelve el ID del área correspondiente a expedientes observados.
     */
    public function resolverAreaObservado(): int;

    /**
     * Obtiene el primer cargo activo con orden 1 para un área dada.
     */
    public function obtenerPrimerCargoArea(int $areaId): ?int;

    /**
     * Obtiene los datos del documento de trámite TUPA.
     */
    public function obtenerDatosTupa(int $tipoTramTupa): ?object;

    /**
     * Crea el registro de cabecera del expediente en p_dto_trmtes.
     *
     * @param array<string, mixed> $datos
     */
    public function crearExpedienteCabecera(array $datos): int;

    /**
     * Crea el movimiento inicial en r_trmtes_dcmntos.
     *
     * @param array<string, mixed> $datos
     */
    public function crearMovimientoInicial(array $datos): int;

    /**
     * Actualiza la URL del archivo adjunto en el movimiento.
     */
    public function actualizarAdjuntoMovimiento(int $movimientoId, string $filename): void;

    /**
     * Asigna las rutas TUPA configuradas para el trámite en p_dto_crgos_trmtes.
     */
    public function asignarRutasTupa(int $expedienteId, int $tipoTramTupa, string $fechaIngreso): void;

    /**
     * Registra los requisitos TUPA marcados o pendientes en p_dto_rqtos.
     *
     * @param list<int|string>|array<int, mixed> $marcados
     */
    public function registrarRequisitosTupa(int $expedienteId, int $tipoTramTupa, array $marcados): void;

    /**
     * Registra evento de auditoría en p_sgmto_log (fail-closed).
     */
    public function registrarSeguimiento(int $loginId, string $descripcion, string $ip): void;

    /**
     * Obtiene los datos públicos autorizados para la trazabilidad y seguimiento del expediente.
     *
     * @return array{
     *     expediente_id: int,
     *     numero_expediente: string,
     *     fecha_ingreso: string,
     *     tipo_tramite: string,
     *     solicitante: string,
     *     asunto: string,
     *     area_actual: string,
     *     estado_actual: string,
     *     compania_nombre: string,
     *     movimientos: array<int, array{fecha: string, oficina: string, estado: string}>
     * }|null
     */
    public function obtenerSeguimientoPublico(int $expedienteId): ?array;
}
