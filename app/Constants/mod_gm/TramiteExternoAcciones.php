<?php

declare(strict_types=1);

namespace App\Constants\mod_gm;

/**
 * Constantes de identidad canónica para acciones de Trámite Externo.
 */
final class TramiteExternoAcciones
{
    /**
     * Acción canónica para exportar expedientes de Trámite Externo a Excel.
     * Corresponde al valor en sistema.a_acciones.de_codigo.
     */
    public const EXPORTAR_EXCEL = 'tramite_externo.exportar_excel';

    private function __construct() {}
}
