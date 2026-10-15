<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Enums\mod_gene\EstadoTramiteInterno;
use InvalidArgumentException;

class MaquinaEstadosTramiteInterno
{
    /**
     * Matriz de transiciones legales: [EstadoOrigen => [Acción => EstadoDestino]]
     */
    private const MATRIZ_TRANSICIONES = [
        EstadoTramiteInterno::PENDIENTE->value => [
            'recepcionar' => EstadoTramiteInterno::RECIBIDO,
            'devolver'    => EstadoTramiteInterno::DEVUELTO,
            'reiterar'    => EstadoTramiteInterno::REITERADO,
        ],
        EstadoTramiteInterno::RECIBIDO->value => [
            'derivar'     => EstadoTramiteInterno::PENDIENTE,
            'asignar'     => EstadoTramiteInterno::ASIGNADO,
            'finalizar'   => EstadoTramiteInterno::FINALIZADO,
            'devolver'    => EstadoTramiteInterno::DEVUELTO,
        ],
        EstadoTramiteInterno::ASIGNADO->value => [
            'recepcionar' => EstadoTramiteInterno::RECIBIDO,
            'derivar'     => EstadoTramiteInterno::PENDIENTE,
            'asignar'     => EstadoTramiteInterno::ASIGNADO,
            'finalizar'   => EstadoTramiteInterno::FINALIZADO,
            'devolver'    => EstadoTramiteInterno::DEVUELTO,
        ],
        EstadoTramiteInterno::DEVUELTO->value => [
            'derivar'     => EstadoTramiteInterno::PENDIENTE,
            'finalizar'   => EstadoTramiteInterno::FINALIZADO,
        ],
        EstadoTramiteInterno::REITERADO->value => [
            'recepcionar' => EstadoTramiteInterno::RECIBIDO,
            'devolver'    => EstadoTramiteInterno::DEVUELTO,
        ],
        23 => [
            'derivar'     => EstadoTramiteInterno::PENDIENTE,
            'asignar'     => EstadoTramiteInterno::ASIGNADO,
            'finalizar'   => EstadoTramiteInterno::FINALIZADO,
            'devolver'    => EstadoTramiteInterno::DEVUELTO,
        ],
        EstadoTramiteInterno::FINALIZADO->value => [],
        EstadoTramiteInterno::FINALIZADO_ATENDIDO->value => [],
    ];

    public function esTransicionValida(int|EstadoTramiteInterno $origen, string $accion): bool
    {
        $estadoId = $origen instanceof EstadoTramiteInterno ? $origen->value : $origen;
        $accionNormalizada = strtolower(trim($accion));

        return isset(self::MATRIZ_TRANSICIONES[$estadoId][$accionNormalizada]);
    }

    public function obtenerEstadoDestino(int|EstadoTramiteInterno $origen, string $accion): EstadoTramiteInterno
    {
        $estadoId = $origen instanceof EstadoTramiteInterno ? $origen->value : $origen;
        $accionNormalizada = strtolower(trim($accion));

        if (!$this->esTransicionValida($estadoId, $accionNormalizada)) {
            $enum = EstadoTramiteInterno::tryFrom($estadoId);
            $nombreEstado = $enum ? $enum->name : "ID_{$estadoId}";
            throw new InvalidArgumentException(
                "Transición inválida: La acción '{$accion}' no está permitida desde el estado {$nombreEstado}."
            );
        }

        return self::MATRIZ_TRANSICIONES[$estadoId][$accionNormalizada];
    }
}
