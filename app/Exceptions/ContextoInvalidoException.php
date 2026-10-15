<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción de dominio lanzada cuando el contexto institucional/operativo
 * (compañía, cargo, área o login) es inexistente, incoherente, inactivo o no autorizado.
 */
class ContextoInvalidoException extends RuntimeException
{
    public static function sinAutenticacion(): self
    {
        return new self('No existe una sesión de funcionario autenticada en el sistema.');
    }

    public static function companiaInvalida(?int $idCompania = null): self
    {
        $id = $idCompania ?? 0;
        return new self("La compañía solicitada ({$id}) es inválida, inactiva o no está autorizada para este login.");
    }

    public static function cargoInvalido(?int $idCargo = null): self
    {
        $id = $idCargo ?? 0;
        return new self("El cargo solicitado ({$id}) es inválido, inactivo, no está asignado o no pertenece a la compañía activa.");
    }

    public static function contextoIncoherente(string $detalle): self
    {
        return new self("Contexto operativo incoherente: {$detalle}");
    }
}
