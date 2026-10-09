<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;

/**
 * Puerto principal para el orquestador de PIDE en cascada.
 */
interface PideCoreInterface
{
    /**
     * Realiza una búsqueda simple informativa de datos sin registrar al ciudadano en BD local.
     * 
     * @param string $documento DNI o RUC
     * @param string $tipo 'DNI' para personas, 'RUC' para empresas
     * @return PersonaDto|EmpresaDto|null
     */
    public function consultarDetalle(string $documento, string $tipo = 'DNI'): ?object;

    /**
     * Realiza la búsqueda para trámite. Si el ciudadano no existe, se inserta en local
     * y genera su cdgo_usrio. Si existe pero discrepa, levanta una alerta.
     * 
     * @param string $documento DNI o RUC
     * @param string $tipo 'DNI' o 'RUC'
     * @param array|null $forceProviders Filtro de proveedores permitidos
     * @return PersonaDto|EmpresaDto|null
     */
    public function buscarYRegistrarParaTramite(string $documento, string $tipo = 'DNI', ?array $forceProviders = null): ?object;

    /**
     * Devuelve el último código de aviso/advertencia registrado (ej. 'EXTERNAL_SERVICE_UNAVAILABLE').
     */
    public function obtenerUltimoAviso(): ?string;

    /**
     * Devuelve un mensaje amigable explicativo del error.
     */
    public function obtenerMensajeError(string $tipo = 'DNI'): string;
}
