<?php

declare(strict_types=1);

namespace App\Enums\mod_gm;

/**
 * Catálogo canónico de bandejas de Trámite Externo.
 * Mapeado contra las especificaciones de SDD 01.
 */
enum BandejaTramiteExterno: string
{
    case ENTRADA = 'entrada';
    case RECIBIDOS = 'recibidos';
    case SALIDA = 'salida';
    case FINALIZADOS = 'finalizados';
    case FINALIZADOS_NOTIFICADOS = 'finalizados_notificados';
    case TODOS = 'todos';
    case SIN_FINALIZAR = 'sin_finalizar';
    case ARCHIVO = 'archivo';
    case NOTIFICADOS = 'notificados';
    case PRESTADOS = 'prestados';
    case INVENTARIAR = 'inventariar';

    /**
     * Título formal para la interfaz de usuario.
     */
    public function titulo(): string
    {
        return match ($this) {
            self::ENTRADA => 'Bandeja de Entrada',
            self::RECIBIDOS => 'Bandeja de Recibidos',
            self::SALIDA => 'Bandeja de Salida',
            self::FINALIZADOS, self::FINALIZADOS_NOTIFICADOS => 'Finalizados / Notificados',
            self::TODOS => 'Todos los Expedientes',
            self::SIN_FINALIZAR => 'Expedientes Sin Finalizar',
            self::ARCHIVO => 'Archivo de Expedientes',
            self::NOTIFICADOS => 'Expedientes Notificados',
            self::PRESTADOS => 'Expedientes Prestados',
            self::INVENTARIAR => 'Inventario de Archivo',
        };
    }
}
