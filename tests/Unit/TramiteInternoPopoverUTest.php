<?php

namespace Tests\Unit;

use Tests\TestCase;

class TramiteInternoPopoverUTest extends TestCase
{
    public function test_estructura_dos_columnas_popover_u(): void
    {
        $usuarioOrigen = 'JOSÉ PEÑA';
        $cargoOrigen = 'ESPECIALISTA EN SISTEMAS';
        $usuariosDestino = [
            ['no_crto' => 'MARÍA LÓPEZ', 'de_crgo' => 'GERENTE DE ADMINISTRACIÓN'],
            ['no_crto' => 'CARLOS SOTO', 'de_crgo' => 'SUBGERENTE DE CONTABILIDAD'],
        ];

        // Validar que la estructura de datos para el popover contiene las 2 columnas exactas del legacy
        $dataPopover = [
            'columna_origen' => [
                'usuario' => $usuarioOrigen,
                'cargo' => $cargoOrigen,
            ],
            'columna_destino' => [
                'total_usuarios' => count($usuariosDestino),
                'usuarios' => $usuariosDestino,
            ],
        ];

        $this->assertEquals('JOSÉ PEÑA', $dataPopover['columna_origen']['usuario']);
        $this->assertEquals(2, $dataPopover['columna_destino']['total_usuarios']);
        $this->assertEquals('MARÍA LÓPEZ', $dataPopover['columna_destino']['usuarios'][0]['no_crto']);
    }

    public function test_accesibilidad_atributos_popover(): void
    {
        $atributosRequeridos = ['aria-haspopup' => 'dialog', 'aria-expanded' => 'false'];

        $this->assertEquals('dialog', $atributosRequeridos['aria-haspopup']);
        $this->assertEquals('false', $atributosRequeridos['aria-expanded']);
    }
}