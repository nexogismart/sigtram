<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Enums\mod_gm\EstadoTramiteExterno;
use InvalidArgumentException;

/**
 * Máquina de estados determinista para Trámite Externo.
 * Implementa las reglas de transición formalizadas en SDD 01 y SDD 03,
 * resolviendo el contrato de vigencia in_esta compatible con la base existente.
 */
class MaquinaEstadosTramiteExterno
{
    /**
     * Matriz de transiciones legales: [EstadoOrigen => [Acción => EstadoDestino]]
     *
     * @var array<int, array<string, EstadoTramiteExterno>>
     */
    private const MATRIZ_TRANSICIONES = [
        EstadoTramiteExterno::PENDIENTE->value => [
            'recepcionar' => EstadoTramiteExterno::RECIBIDO,
            'devolver'    => EstadoTramiteExterno::DEVUELTO,
            'redirigir'   => EstadoTramiteExterno::REDIRIGIDO,
        ],
        EstadoTramiteExterno::RECIBIDO->value => [
            'derivar'     => EstadoTramiteExterno::PENDIENTE,
            'asignar'     => EstadoTramiteExterno::ASIGNADO,
            'enviar'      => EstadoTramiteExterno::ENVIADO_MULTIAREA,
            'finalizar'   => EstadoTramiteExterno::FINALIZADO,
            'anexo'       => EstadoTramiteExterno::ANEXO,
            'redirigir'   => EstadoTramiteExterno::REDIRIGIDO,
        ],
        EstadoTramiteExterno::ASIGNADO->value => [
            'recepcionar' => EstadoTramiteExterno::RECIBIDO,
            'derivar'     => EstadoTramiteExterno::PENDIENTE,
            'finalizar'   => EstadoTramiteExterno::FINALIZADO,
            'anexo'       => EstadoTramiteExterno::ANEXO,
            'redirigir'   => EstadoTramiteExterno::REDIRIGIDO,
        ],
        EstadoTramiteExterno::ENVIADO_MULTIAREA->value => [
            'recepcionar' => EstadoTramiteExterno::RECIBIDO,
            'finalizar'   => EstadoTramiteExterno::FINALIZADO,
            'redirigir'   => EstadoTramiteExterno::REDIRIGIDO,
        ],
        EstadoTramiteExterno::DEVUELTO->value => [
            'recepcionar' => EstadoTramiteExterno::RECIBIDO,
            'derivar'     => EstadoTramiteExterno::PENDIENTE,
            'redirigir'   => EstadoTramiteExterno::REDIRIGIDO,
        ],
        EstadoTramiteExterno::FINALIZADO->value => [
            'notificar' => EstadoTramiteExterno::NOTIFICADO,
            'archivar'  => EstadoTramiteExterno::ARCHIVADO,
        ],
        EstadoTramiteExterno::NOTIFICADO->value => [
            'archivar' => EstadoTramiteExterno::ARCHIVADO,
        ],
        EstadoTramiteExterno::ARCHIVADO->value => [
            'prestar' => EstadoTramiteExterno::PRESTADO,
        ],
        EstadoTramiteExterno::PRESTADO->value => [
            'retornar_prestamo' => EstadoTramiteExterno::ARCHIVADO,
        ],
        EstadoTramiteExterno::ANEXO->value => [
            'recepcionar' => EstadoTramiteExterno::RECIBIDO,
            'derivar'     => EstadoTramiteExterno::PENDIENTE,
        ],
        EstadoTramiteExterno::REINGRESO->value => [
            'recepcionar' => EstadoTramiteExterno::RECIBIDO,
            'derivar'     => EstadoTramiteExterno::PENDIENTE,
        ],
        EstadoTramiteExterno::REDIRIGIDO->value => [
            'recepcionar' => EstadoTramiteExterno::RECIBIDO,
            'derivar'     => EstadoTramiteExterno::PENDIENTE,
        ],
    ];

    /**
     * Valida si una transición es legal desde un estado origen dado.
     *
     * @param int|EstadoTramiteExterno $origen Estado origen actual
     * @param string $accion Nombre de la acción requerida
     * @return bool
     */
    public function esTransicionValida(int|EstadoTramiteExterno $origen, string $accion): bool
    {
        $estadoId = $origen instanceof EstadoTramiteExterno ? $origen->value : $origen;
        $accionNormalizada = strtolower(trim($accion));

        return isset(self::MATRIZ_TRANSICIONES[$estadoId][$accionNormalizada]);
    }

    /**
     * Obtiene el estado destino para una acción legal o lanza excepción si es inválida.
     *
     * @param int|EstadoTramiteExterno $origen
     * @param string $accion
     * @return EstadoTramiteExterno
     * @throws InvalidArgumentException
     */
    public function resolverEstadoDestino(int|EstadoTramiteExterno $origen, string $accion): EstadoTramiteExterno
    {
        $estadoId = $origen instanceof EstadoTramiteExterno ? $origen->value : $origen;
        $accionNormalizada = strtolower(trim($accion));

        if (!$this->esTransicionValida($estadoId, $accionNormalizada)) {
            $nombreOrigen = EstadoTramiteExterno::tryFrom($estadoId)?->label() ?? "ID {$estadoId}";
            throw new InvalidArgumentException(
                "Transición inválida: la acción '{$accion}' no está permitida desde el estado {$nombreOrigen}."
            );
        }

        return self::MATRIZ_TRANSICIONES[$estadoId][$accionNormalizada];
    }

    /**
     * Resuelve el contrato unificado de vigencia e histórico para expedientes finalizados (3 vs 26).
     *
     * En la BD existente de SIGTRAM legacy, tanto el estado 3 como el 26 representan expedientes
     * cerrados/finalizados. Para no alterar la base ni romper paridad, cualquier consulta de
     * finalizados evalúa si el estado es 3 o 26, sin importar si in_esta quedó en '0' o '1'.
     *
     * @param int $estadoId
     * @return bool
     */
    public static function esEstadoFinalizado(int $estadoId): bool
    {
        return $estadoId === EstadoTramiteExterno::FINALIZADO->value
            || $estadoId === EstadoTramiteExterno::NOTIFICADO->value;
    }

    /**
     * Evalúa si un expediente en un estado determinado puede entrar en la Bandeja de Entrada.
     *
     * @param int $estadoId
     * @return bool
     */
    public static function admisibleEnEntrada(int $estadoId): bool
    {
        // Estados explícitamente excluidos por la regla legacy y v3
        $excluidos = [
            EstadoTramiteExterno::EXCLUIDO_5->value,
            EstadoTramiteExterno::EXCLUIDO_12->value,
            EstadoTramiteExterno::ANULADO->value,
            EstadoTramiteExterno::FINALIZADO->value,
            EstadoTramiteExterno::ARCHIVADO->value,
            EstadoTramiteExterno::PRESTADO->value,
        ];

        return !in_array($estadoId, $excluidos, true);
    }
}
