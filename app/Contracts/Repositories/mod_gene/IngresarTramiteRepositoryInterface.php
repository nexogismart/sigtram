<?php
declare(strict_types=1);

namespace App\Contracts\Repositories\mod_gene;

interface IngresarTramiteRepositoryInterface
{
    /**
     * Obtiene los tipos de documento (trámites).
     */
    public function obtenerTiposDocumento(): \Illuminate\Support\Collection;

    /**
     * Obtiene el listado de áreas activas para destino.
     */
    public function obtenerAreasDestino(): \Illuminate\Support\Collection;

    /**
     * Obtiene la información de un año por su valor.
     */
    public function obtenerAnioPorValor(int $anio): ?object;

    /**
     * Obtiene el año más reciente.
     */
    public function obtenerAnioReciente(): ?object;

    /**
     * Obtiene la información de un tipo de trámite.
     */
    public function obtenerInfoTipoTramite(int $cdgoTpoTrmte): ?object;

    /**
     * Bloquea la fila y obtiene la siguiente semilla para el trámite.
     */
    public function obtenerNuevaSemilla(int $cdgoTpoTrmte, int $cdgoAnio, int $areaId, int $cargoId, int $inScncal): int;

    /**
     * Guarda el registro de la nueva semilla generada.
     */
    public function guardarSemilla(array $datosSemilla): void;

    /**
     * Registra un nuevo trámite y retorna el ID autogenerado.
     */
    public function registrarNuevoTramite(array $datosTramite): int;

    /**
     * Actualiza las rutas de los adjuntos de un trámite existente.
     */
    public function actualizarRutaAdjunto(int $idTramite, string $rutaAdjuntos): void;

    /**
     * Obtiene los cargos principales o específicos de un conjunto de áreas para destino.
     */
    public function obtenerCargosDestinoPorAreas(array $areasDestino, ?int $selCrgoEspecifico = null): \Illuminate\Support\Collection;

    /**
     * Registra las filas en el histórico (r_trmtes_ntrnos) para cada área de destino.
     */
    public function registrarHistoricoDestinos(int $idTramite, array $areasDestino, \Illuminate\Support\Collection $cargosDestino, array $datosComunes): void;

    /**
     * Actualiza el histórico de un trámite referente (Responder trámite).
     */
    public function actualizarHistoricoReferencia(int $idTramiteReferente, string $fecha, string $rutaAdjunto): void;

    /**
     * Obtiene el destinatario principal cruzando con logins y usuarios.
     */
    public function obtenerDestinatarioPrincipal(int $areaDestinoId): ?object;

    /**
     * Obtiene múltiples destinatarios para cuando el trámite no soporta CC.
     */
    public function obtenerMultiplesDestinatarios(array $areasDestino): \Illuminate\Support\Collection;

    /**
     * Obtiene los nombres descriptivos de un conjunto de áreas.
     */
    public function obtenerNombresAreas(array $areasIds): array;

    /**
     * Obtiene la jerarquía de un área (Gerencia y Subgerencia) para el título del documento.
     */
    public function obtenerJerarquiaArea(int $areaId): ?object;
}
