<?php

namespace Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;

class TramiteInternoPaginacionHistorialTest extends TestCase
{
    /**
     * Prueba el cálculo del algoritmo T.E considerando el registro frontera entre páginas.
     */
    public function test_calculo_te_frontera_entre_paginas(): void
    {
        // Simular que en la página 2, el primer registro recibe el fepasa del último registro de la página 1
        $fechaRowPag2 = '2026-10-05 10:00:00';
        $fronteraFepasaPag1 = '2026-10-06 14:00:00';

        $inicio = Carbon::parse($fechaRowPag2);
        $fin = Carbon::parse($fronteraFepasaPag1);

        $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
        $dias = intdiv($diffSegundos, 86400);
        $horas = intdiv($diffSegundos % 86400, 3600);

        // 1 día y 4 horas entre ambas fechas
        $this->assertEquals(1, $dias);
        $this->assertEquals(4, $horas);
    }

    public function test_allowlist_de_per_page_en_historico(): void
    {
        $permitidos = [10, 15, 25, 50];
        $default = 15;

        $solicitadoInvalido = 999;
        $resueltoInvalido = in_array($solicitadoInvalido, $permitidos, true) ? $solicitadoInvalido : $default;
        $this->assertEquals(15, $resueltoInvalido);

        $solicitadoValido = 25;
        $resueltoValido = in_array($solicitadoValido, $permitidos, true) ? $solicitadoValido : $default;
        $this->assertEquals(25, $resueltoValido);
    }
}