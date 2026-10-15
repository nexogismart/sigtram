<?php

declare(strict_types=1);

namespace App\Policies\mod_gene;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;

class TramiteInternoPolicy
{
    /**
     * Verifica si el funcionario puede ver la información del trámite / histórico.
     */
    public function ver(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        // Acceso por cargo directo, área participante o super-permiso 18
        return $ctx->idCargo === $mov->cargoEntradaId
            || $ctx->idCargo === $mov->cargoSalidaId
            || $ctx->idArea === $mov->areaEntradaId
            || $ctx->idArea === $mov->areaSalidaId
            || $ctx->tienePermiso(18);
    }

    /**
     * Valida si el funcionario puede consultar el histórico de un movimiento.
     */
    public function consultarHistorico(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        if ($mov->companiaId <= 0 || $ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        return $ctx->cargoAsignadoValido();
    }

    /**
     * Valida la lectura histórica de un documento interno por ID de compañía:
     * - Contexto de funcionario válido y cargo asignado válido.
     * - Misma compañía activa.
     */
    public function verDocumentoHistorico(ContextoUsuarioAutenticadoDto $ctx, int $companiaId): bool
    {
        if ($companiaId <= 0 || $ctx->idCompania !== $companiaId) {
            return false;
        }

        return $ctx->cargoAsignadoValido();
    }

    /**
     * Valida si el funcionario puede descargar los adjuntos del trámite.
     */
    public function descargar(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        return $this->ver($ctx, $mov);
    }

    /**
     * Valida si el funcionario puede recepcionar el trámite en Entrada.
     */
    public function recepcionar(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        // El cargo destino debe coincidir con el cargo activo
        if ($mov->cargoEntradaId !== $ctx->idCargo) {
            return false;
        }

        if ($mov->inEsta !== '1') {
            return false;
        }

        // Estados entrantes permitidos: 1 (Ingresado/Pendiente), 7 (Asignado), 53 (Reiterativo)
        // Nunca 2 (Ya recibido), 3/24 (Finalizado), 4 (Devuelto)
        return in_array($mov->estadoId, [1, 7, 53], true);
    }

    /**
     * Valida si el funcionario puede asignar internamente (solo a su misma área).
     */
    public function asignar(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov, int $cargoDestinoAreaId): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        if ($mov->cargoEntradaId !== $ctx->idCargo) {
            return false;
        }

        if ($mov->inEsta !== '1') {
            return false;
        }

        // Debe estar recibido (2 o 23)
        if (!in_array($mov->estadoId, [2, 23], true)) {
            return false;
        }

        // La asignación solo se permite a cargos dentro de la misma área activa
        return $cargoDestinoAreaId === $ctx->idArea;
    }

    /**
     * Valida si el funcionario puede derivar a otra área.
     */
    public function derivar(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        if ($mov->cargoEntradaId !== $ctx->idCargo) {
            return false;
        }

        if ($mov->inEsta !== '1') {
            return false;
        }

        // Debe estar recibido (2 o 23)
        return in_array($mov->estadoId, [2, 23], true);
    }

    /**
     * Valida si el funcionario puede finalizar el expediente.
     */
    public function finalizar(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        if ($mov->cargoEntradaId !== $ctx->idCargo) {
            return false;
        }

        if ($mov->inEsta !== '1') {
            return false;
        }

        // Debe estar recibido (2 o 23)
        return in_array($mov->estadoId, [2, 23], true);
    }

    /**
     * Valida si el funcionario puede devolver/rechazar el trámite.
     * Solo el cargo remitente original puede solicitar devolución mientras siga en tránsito (estado 1).
     */
    public function devolver(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov, string $causal): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        // Solo el emisor del movimiento de salida puede devolverlo
        if ($mov->cargoSalidaId !== $ctx->idCargo) {
            return false;
        }

        if ($mov->inEsta !== '1') {
            return false;
        }

        // Debe seguir pendiente en destino (estado 1)
        if ($mov->estadoId !== 1) {
            return false;
        }

        // Causal no puede ser vacía ni el placeholder por defecto
        $causalLimpia = trim($causal);
        return $causalLimpia !== '' && $causalLimpia !== 'Sin novedades para devolver';
    }

    /**
     * Valida si el funcionario puede reiterar un trámite enviado.
     */
    public function reiterar(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        // Solo el emisor puede reiterar
        if ($mov->cargoSalidaId !== $ctx->idCargo) {
            return false;
        }

        if ($mov->inEsta !== '1') {
            return false;
        }

        // El trámite debe seguir en tránsito pendiente en destino (estado 1)
        return $mov->estadoId === 1;
    }

    /**
     * Valida reemplazo de adjunto firmado (ver_adjunto_modi.php legacy).
     */
    public function reemplazarFirmado(ContextoUsuarioAutenticadoDto $ctx, TramiteInternoMovimientoDto $mov): bool
    {
        if ($ctx->idCompania !== $mov->companiaId) {
            return false;
        }

        if (!$ctx->cargoAsignadoValido()) {
            return false;
        }

        if ($mov->inEsta !== '1') {
            return false;
        }

        return $mov->cargoSalidaId === $ctx->idCargo || $ctx->tienePermiso(18);
    }

    /**
     * Valida acceso a bandeja de Contabilidad.
     */
    public function verContabilidad(ContextoUsuarioAutenticadoDto $ctx): bool
    {
        return $ctx->idArea === 21 || $ctx->esContabilidad || $ctx->tienePermiso(18);
    }
}