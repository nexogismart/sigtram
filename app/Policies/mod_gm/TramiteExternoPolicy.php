<?php

declare(strict_types=1);

namespace App\Policies\mod_gm;

use App\Constants\mod_gm\TramiteExternoAcciones;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Services\mod_sistema\AutorizacionAccionService;

/**
 * Política de autorización por recurso para Trámite Externo.
 * Cierra vulnerabilidades IDOR (TE-001, TE-043) validando pertenencia a compañía,
 * cargo activo, custodia del movimiento y estado admitido.
 */
class TramiteExternoPolicy
{
    public function __construct(
        private readonly ?AutorizacionAccionService $autorizacionAccionService = null
    ) {}

    /**
     * Valida si el funcionario puede ver la información/histórico del expediente
     * evaluando una o múltiples ramas activas (in_esta = '1').
     * Cierra brecha IDOR CEF-P0-04 exigiendo pertenencia a compañía y relación directa
     * (cargo/área origen o destino) o rol administrativo (1, 3 o 18) en al menos una rama activa.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<ExpedienteMovimientoDto>|ExpedienteMovimientoDto $movimiento
     */
    public function ver(ContextoUsuarioAutenticadoDto $contexto, array|ExpedienteMovimientoDto $movimiento): bool
    {
        $movimientos = is_array($movimiento) ? $movimiento : [$movimiento];

        if (empty($movimientos)) {
            return false;
        }

        foreach ($movimientos as $mov) {
            if (!$mov instanceof ExpedienteMovimientoDto) {
                continue;
            }
            if ($mov->inEsta !== '1') {
                continue;
            }
            if ($contexto->idCompania !== $mov->companiaId) {
                continue;
            }

            if (
                $mov->cargoEntradaId === $contexto->idCargo
                || $mov->cargoSalidaId === $contexto->idCargo
                || $mov->areaEntradaId === $contexto->idArea
                || $mov->areaSalidaId === $contexto->idArea
                || $contexto->tieneRol(1)
                || $contexto->tieneRol(3)
                || $contexto->tieneRol(18)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida si el funcionario puede ver la información histórica/participantes de un movimiento (FASE 1).
     * Diferencia explícitamente la autorización de lectura histórica de la mutación activa:
     * permite movimientos con in_esta = '0' siempre que pertenezcan a la compañía y el documento
     * esté autorizado mediante sus ramas activas (o el usuario tenga relación directa o rol administrativo).
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param ExpedienteMovimientoDto $movimiento
     * @param array<ExpedienteMovimientoDto> $movimientosActivos
     */
    public function verHistorico(
        ContextoUsuarioAutenticadoDto $contexto,
        ExpedienteMovimientoDto $movimiento,
        array $movimientosActivos = []
    ): bool {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        // 1. Roles administrativos con acceso irrestricto
        if ($contexto->tieneRol(1) || $contexto->tieneRol(3) || $contexto->tieneRol(18)) {
            return true;
        }

        // 2. Si el movimiento consultado es activo (in_esta = '1'), delegar en ver()
        if ($movimiento->inEsta === '1' && $this->ver($contexto, $movimiento)) {
            return true;
        }

        // 3. Autorización canónica mediante ramas activas del documento
        if (!empty($movimientosActivos) && $this->ver($contexto, $movimientosActivos)) {
            return true;
        }

        // 4. Si el funcionario perteneció al cargo o área emisora/receptora del movimiento histórico
        if (
            $movimiento->cargoEntradaId === $contexto->idCargo
            || $movimiento->cargoSalidaId === $contexto->idCargo
            || $movimiento->areaEntradaId === $contexto->idArea
            || $movimiento->areaSalidaId === $contexto->idArea
        ) {
            return true;
        }

        return false;
    }

    /**
     * Autoriza la lectura del histórico completo de un expediente aunque todas
     * sus ramas estén cerradas. No habilita ninguna operación de mutación.
     *
     * @param array<ExpedienteMovimientoDto> $movimientos
     */
    public function verHistorialExpediente(ContextoUsuarioAutenticadoDto $contexto, array $movimientos): bool
    {
        if (empty($movimientos)) {
            return false;
        }

        if ($contexto->tieneRol(1) || $contexto->tieneRol(3) || $contexto->tieneRol(18)) {
            foreach ($movimientos as $movimiento) {
                if ($movimiento instanceof ExpedienteMovimientoDto
                    && $movimiento->companiaId === $contexto->idCompania) {
                    return true;
                }
            }
        }

        foreach ($movimientos as $movimiento) {
            if (!$movimiento instanceof ExpedienteMovimientoDto
                || $movimiento->companiaId !== $contexto->idCompania) {
                continue;
            }

            if (
                $movimiento->cargoEntradaId === $contexto->idCargo
                || $movimiento->cargoSalidaId === $contexto->idCargo
                || $movimiento->areaEntradaId === $contexto->idArea
                || $movimiento->areaSalidaId === $contexto->idArea
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida si el funcionario puede recepcionar el expediente.
     */
    public function recepcionar(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        if ($movimiento->inEsta !== '1') {
            return false;
        }

        // El cargo destino debe ser el cargo activo del usuario
        if ($movimiento->cargoEntradaId !== $contexto->idCargo) {
            return false;
        }

        // Estados admitidos para recepción (no finalizados ni anulados)
        $estadosPermitidos = [
            EstadoTramiteExterno::PENDIENTE->value,
            EstadoTramiteExterno::ASIGNADO->value,
            EstadoTramiteExterno::ENVIADO_MULTIAREA->value,
            EstadoTramiteExterno::DEVUELTO->value,
            EstadoTramiteExterno::ANEXO->value,
            EstadoTramiteExterno::REINGRESO->value,
            EstadoTramiteExterno::REDIRIGIDO->value,
        ];

        return in_array($movimiento->estadoId, $estadosPermitidos, true);
    }

    /**
     * Valida si el funcionario puede derivar el expediente.
     */
    public function derivar(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        if ($movimiento->inEsta !== '1') {
            return false;
        }

        // El cargo receptor debe ser el cargo activo
        if ($movimiento->cargoEntradaId !== $contexto->idCargo) {
            return false;
        }

        return $movimiento->estadoId === EstadoTramiteExterno::RECIBIDO->value
            || $movimiento->estadoId === EstadoTramiteExterno::ASIGNADO->value;
    }

    /**
     * Valida si el funcionario puede asignar el expediente a un cargo interno de su área.
     */
    public function asignar(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        if ($movimiento->inEsta !== '1') {
            return false;
        }

        if ($movimiento->cargoEntradaId !== $contexto->idCargo) {
            return false;
        }

        return $movimiento->estadoId === EstadoTramiteExterno::RECIBIDO->value;
    }

    /**
     * Valida si el funcionario puede realizar un envío multi-área.
     */
    public function enviar(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        return $this->derivar($contexto, $movimiento);
    }

    /**
     * Valida si el funcionario puede finalizar el expediente.
     */
    public function finalizar(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        if ($movimiento->inEsta !== '1') {
            return false;
        }

        if ($movimiento->cargoEntradaId !== $contexto->idCargo) {
            return false;
        }

        return $movimiento->estadoId === EstadoTramiteExterno::RECIBIDO->value
            || $movimiento->estadoId === EstadoTramiteExterno::ASIGNADO->value;
    }

    /**
     * Valida si el funcionario puede devolver/regresar el expediente desde la bandeja de salida.
     */
    public function devolver(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        if ($movimiento->inEsta !== '1') {
            return false;
        }

        // Sólo el cargo emisor original puede devolver su propio envío
        if ($movimiento->cargoSalidaId !== $contexto->idCargo) {
            return false;
        }

        // El movimiento debe seguir pendiente en destino sin haber sido recepcionado aún
        return $movimiento->estadoId === EstadoTramiteExterno::PENDIENTE->value;
    }

    /**
     * Valida si el funcionario puede archivar el expediente.
     */
    public function archivar(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        if ($movimiento->inEsta !== '1') {
            return false;
        }

        $estadosAdmitidos = [
            EstadoTramiteExterno::FINALIZADO->value,
            EstadoTramiteExterno::NOTIFICADO->value,
        ];

        return in_array($movimiento->estadoId, $estadosAdmitidos, true);
    }

    /**
     * Valida si el funcionario puede prestar un expediente archivado.
     */
    public function prestar(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        return $movimiento->estadoId === EstadoTramiteExterno::ARCHIVADO->value;
    }

    /**
     * Valida si el funcionario puede retornar un expediente prestado.
     */
    public function retornarPrestamo(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        return $movimiento->estadoId === EstadoTramiteExterno::PRESTADO->value;
    }

    /**
     * Valida si el funcionario puede descargar un adjunto del expediente.
     */
    public function descargarAdjunto(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        return $movimiento->areaEntradaId === $contexto->idArea
            || $movimiento->areaSalidaId === $contexto->idArea
            || $movimiento->cargoEntradaId === $contexto->idCargo
            || $movimiento->cargoSalidaId === $contexto->idCargo
            || $contexto->tieneRol(1)
            || $contexto->tieneRol(3);
    }

    /**
     * Valida si el funcionario puede consultar la hoja de ruta del expediente.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<ExpedienteMovimientoDto>|ExpedienteMovimientoDto $movimiento
     */
    public function hojaRuta(ContextoUsuarioAutenticadoDto $contexto, array|ExpedienteMovimientoDto $movimiento): bool
    {
        return $this->ver($contexto, $movimiento);
    }

    /**
     * Valida si el funcionario puede descargar todos los adjuntos consolidados.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<ExpedienteMovimientoDto>|ExpedienteMovimientoDto $movimiento
     */
    public function descargarTodos(ContextoUsuarioAutenticadoDto $contexto, array|ExpedienteMovimientoDto $movimiento): bool
    {
        $movimientos = is_array($movimiento) ? $movimiento : [$movimiento];

        if (empty($movimientos)) {
            return false;
        }

        foreach ($movimientos as $mov) {
            if (!$mov instanceof ExpedienteMovimientoDto) {
                continue;
            }
            if ($mov->inEsta !== '1') {
                continue;
            }
            if ($this->descargarAdjunto($contexto, $mov)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida si el funcionario puede redirigir el expediente a otra gerencia/área.
     */
    public function redirigir(ContextoUsuarioAutenticadoDto $contexto, ExpedienteMovimientoDto $movimiento): bool
    {
        if ($contexto->idCompania !== $movimiento->companiaId) {
            return false;
        }

        // Requiere rol administrativo (ej. rol 1 o permiso especial de mesa/administración)
        return $contexto->tieneRol(1) || $contexto->tieneRol(3);
    }

    /**
     * Valida si el funcionario tiene autorización para exportar expedientes a Excel.
     * Delega estrictamente en el servicio centralizado de autorización de acciones
     * bajo el código canónico 'tramite_externo.exportar_excel' y ámbito de la compañía activa.
     */
    public function exportarExcel(ContextoUsuarioAutenticadoDto $contexto): bool
    {
        $cdgoLgin = $contexto->getCdgoLgin();
        if ($cdgoLgin <= 0 || $contexto->idCompania <= 0) {
            return false;
        }

        $servicio = $this->autorizacionAccionService ?? app(AutorizacionAccionService::class);

        return $servicio->puedeEjecutar(
            $cdgoLgin,
            $contexto->idCompania,
            TramiteExternoAcciones::EXPORTAR_EXCEL
        );
    }
}

