<?php

declare(strict_types=1);

namespace App\Enums\mod_gene;

enum EstadoTramiteInterno: int
{
    case PENDIENTE = 1;
    case RECIBIDO = 2;
    case FINALIZADO = 3;
    case DEVUELTO = 4;
    case ASIGNADO = 7;
    case FINALIZADO_ATENDIDO = 24;
    case REITERADO = 53;

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'PENDIENTE EN DESTINO',
            self::RECIBIDO => 'RECIBIDO EN DESTINO',
            self::FINALIZADO => 'FINALIZADO',
            self::DEVUELTO => 'DEVUELTO AL EMISOR',
            self::ASIGNADO => 'ASIGNADO A CARGO INTERNO',
            self::FINALIZADO_ATENDIDO => 'FINALIZADO ATENDIDO',
            self::REITERADO => 'REITERADO EN TRÁNSITO',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDIENTE => 'bg-amber-100 text-amber-800 border-amber-300',
            self::RECIBIDO => 'bg-blue-100 text-blue-800 border-blue-300',
            self::FINALIZADO, self::FINALIZADO_ATENDIDO => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::DEVUELTO => 'bg-rose-100 text-rose-800 border-rose-300',
            self::ASIGNADO => 'bg-purple-100 text-purple-800 border-purple-300',
            self::REITERADO => 'bg-orange-100 text-orange-800 border-orange-300',
        };
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::FINALIZADO, self::FINALIZADO_ATENDIDO], true);
    }
}