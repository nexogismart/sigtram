<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;

/**
 * Puerto que deben implementar todos los proveedores de búsqueda individuales (PIDE, Decolecta, Local).
 */
interface PideProviderInterface
{
    /**
     * Busca información sobre un DNI.
     */
    public function buscarDni(string $dni): ?PersonaDto;

    /**
     * Busca información sobre un RUC.
     */
    public function buscarRuc(string $ruc, bool $full = false): ?EmpresaDto;

    /**
     * Retorna el nombre legible de la fuente de datos (ej. 'PIDE', 'API DECOLECTA', 'SIGTRAM').
     */
    public function obtenerNombreFuente(): string;

    /**
     * Retorna el último código de error registrado por el proveedor.
     */
    public function obtenerUltimoError(): ?string;
}
