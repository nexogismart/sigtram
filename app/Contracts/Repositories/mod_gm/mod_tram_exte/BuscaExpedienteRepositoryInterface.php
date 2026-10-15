<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\mod_gm\mod_tram_exte;

interface BuscaExpedienteRepositoryInterface
{
    /**
     * Verifica si un expediente existe por ID y compañía activa obligatoria.
     */
    public function existeExpediente(int $idTram, int $companiaId): bool;

    /**
     * Busca los datos crudos del expediente asegurando aislamiento de compañía.
     */
    public function buscarExpediente(int $idTram, int $companiaId): ?object;

    /**
     * Obtiene la ficha consolidada del expediente (datos de trámite, persona, servicio, requisitos y estado).
     */
    public function obtenerFichaExpediente(int $idTram, int $companiaId): ?array;

    /**
     * Carga los datos de la persona solicitante (natural o jurídica).
     */
    public function obtenerPersona(int $idUsua, string $tipoPers = ''): ?object;

    /**
     * Obtiene el expediente para operaciones de actualización con validación de compañía y bloqueo opcional.
     */
    public function obtenerExpedienteParaActualizar(int $idTram, int $companiaId, bool $lock = false): ?object;

    /**
     * Actualiza la observación de trámite, observación general acumulada y el estado de presentación de requisitos.
     *
     * @param array<int, string> $requisitos Map de cdgo_rqto_trmte => '1'|'0'
     */
    public function actualizarDatosExpediente(int $idTram, int $companiaId, string $deObse, string $nuevoTodo, array $requisitos): void;

    /**
     * Obtiene los cargos activo y último del expediente para la derivación/anexo con validación de compañía y bloqueo.
     */
    public function obtenerMovimientoParaAnexo(int $idTram, int $companiaId, bool $lock = false): ?object;

    /**
     * Obtiene y bloquea todas las ramas activas autorizadas del expediente (in_esta = '1').
     *
     * @return array<int, object>
     */
    public function obtenerMovimientosActivosParaBloqueo(int $idTram, int $companiaId): array;

    /**
     * Desactiva los movimientos activos autorizados del expediente (in_esta = '0').
     * Si se proporciona $movimientoIds, desactiva únicamente esos IDs autorizados.
     *
     * @param array<int, int> $movimientoIds
     * @throws \RuntimeException|\App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException
     */
    public function desactivarMovimientosActivos(int $idTram, int $companiaId, ?int $expectedCount = null, array $movimientoIds = []): int;

    /**
     * Inserta un nuevo movimiento documental y retorna su ID generado.
     */
    public function insertarMovimiento(array $datos): int;

    /**
     * Actualiza la ruta del archivo adjunto en un movimiento documental.
     */
    public function actualizarAdjuntoMovimiento(int $movId, string $filename): void;

    /**
     * Actualiza las observaciones del expediente en p_dto_trmtes asegurando la compañía.
     */
    public function actualizarObservacionesExpediente(int $idTram, int $companiaId, string $nuevoTodo, string $deObse): void;

    /**
     * Registra un evento en la bitácora de seguimiento p_sgmto_log (fail-closed).
     *
     * @throws \Throwable Si falla la inserción de auditoría en base de datos.
     */
    public function registrarSeguimiento(int $idUsua, string $deSgtoLog, string $deIp): void;

    /**
     * Lista los vouchers de un expediente (existencia y movimientos de anexo estado 25).
     *
     * @return array{expedienteExiste: bool, anexos: \Illuminate\Support\Collection}
     */
    public function listarVouchers(int $idTram, int $companiaId): array;

    /**
     * Obtiene la información para emitir el voucher de un movimiento anexo.
     */
    public function obtenerVoucherAnexo(int $idMov, int $companiaId): ?object;

    /**
     * Obtiene las áreas activas agrupadas en gerencias y subgerencias para el formulario de generación.
     *
     * @return array{
     *     idArea: int,
     *     idSubarea: int,
     *     gerencias: \Illuminate\Support\Collection,
     *     subgerencias: \Illuminate\Support\Collection,
     *     todasAreas: \Illuminate\Support\Collection
     * }
     */
    public function obtenerDatosGeneracionExpediente(int $idSubarea): array;

    /**
     * Obtiene el cargo con orden 1 y activo en el área especificada.
     */
    public function obtenerCargoOrdenUnoArea(int $areaId): ?int;

    /**
     * Obtiene el expediente virtual origen y su movimiento activo asegurando compañía y bloqueo opcional.
     *
     * @return array{virtual: ?object, movVirtual: ?object}
     */
    public function obtenerExpedienteVirtualConMovimiento(int $idTramVirtual, int $companiaId, bool $lock = false): array;

    /**
     * Resuelve el tipo de trámite activo por su código semántico ('E', 'S').
     * Exige catálogo activo (in_esta = '1') y único. Rechaza si no existe, si está inactivo o si está duplicado.
     *
     * @throws \RuntimeException Si no existe catálogo activo, si está inactivo o si hay duplicados.
     */
    public function resolverTipoTramiteActivoPorCodigo(string $codigo): object;

    /**
     * Obtiene la letra/prefijo asociada al tipo de trámite.
     */
    public function obtenerPrefijoTipoTramite(int $tpoTrmtes): string;

    /**
     * Obtiene la descripción del tipo de trámite.
     */
    public function obtenerDescripcionTipoTramite(int $tpoTrmtes): string;

    /**
     * Resuelve o crea el año y calcula el siguiente número correlativo de expediente bajo lock for update estricto.
     *
     * @return array{idAnio: int, deAnio: int, nuExpe: int}
     */
    public function resolverCorrelativoAnio(int $cmpnia, int $tpoTrmtes, int $currYear): array;

    /**
     * Obtiene el código de correo del usuario solicitante si no está en el virtual.
     */
    public function obtenerCodigoCorreoUsuario(int $idUsuaSol): ?int;

    /**
     * Inserta un nuevo expediente en p_dto_trmtes y retorna su ID generado.
     */
    public function insertarExpediente(array $datos): int;

    /**
     * Copia los requisitos del expediente virtual al nuevo expediente con in_pres = '1'.
     */
    public function copiarRequisitosVirtual(int $idTramVirtual, int $idNuevo): void;
}
