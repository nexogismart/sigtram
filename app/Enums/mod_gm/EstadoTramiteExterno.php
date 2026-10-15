<?php

declare(strict_types=1);

namespace App\Enums\mod_gm;

/**
 * Catálogo canónico de estados de Trámite Externo.
 * Mapeado contra la tabla a_estdos_trmtes y especificaciones SDD 01 y SDD 03.
 */
enum EstadoTramiteExterno: int
{
    case PENDIENTE = 1;
    case RECIBIDO = 2;
    case FINALIZADO = 3;
    case DEVUELTO = 4;
    case EXCLUIDO_5 = 5;
    case ASIGNADO = 7;
    case ENVIADO_MULTIAREA = 8;
    case EXCLUIDO_12 = 12;
    case REDIRIGIDO = 13;
    case ANEXO = 25;
    case NOTIFICADO = 26;
    case ARCHIVADO = 27;
    case PRESTADO = 28;
    case ANULADO = 29;
    case REINGRESO = 51;
    case ESPECIAL_HISTORICO = 52;

    /**
     * Retorna el nombre descriptivo legible del estado.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'PENDIENTE EN DESTINO',
            self::RECIBIDO => 'RECIBIDO',
            self::FINALIZADO => 'FINALIZADO',
            self::DEVUELTO => 'DEVUELTO / REGRESADO',
            self::EXCLUIDO_5 => 'EXCLUIDO',
            self::ASIGNADO => 'ASIGNADO A CARGO INTERNO',
            self::ENVIADO_MULTIAREA => 'ENVIADO MULTI-ÁREA',
            self::EXCLUIDO_12 => 'EXCLUIDO',
            self::REDIRIGIDO => 'REDIRIGIDO',
            self::ANEXO => 'ANEXO AGREGADO',
            self::NOTIFICADO => 'NOTIFICADO',
            self::ARCHIVADO => 'ARCHIVADO',
            self::PRESTADO => 'PRESTADO',
            self::ANULADO => 'ANULADO',
            self::REINGRESO => 'REINGRESO',
            self::ESPECIAL_HISTORICO => 'TRÁMITE INTERNO VINCULADO',
        };
    }

    /**
     * Determina si el estado es final (cerrado).
     */
    public function esFinal(): bool
    {
        return in_array($this, [self::FINALIZADO, self::NOTIFICADO, self::ARCHIVADO, self::ANULADO], true);
    }
}
