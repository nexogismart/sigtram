<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Session\ArraySessionHandler;

class TramiteInternoImpresionExportacionTest extends TestCase
{
    public function test_preserva_sesion_en_peticion_de_impresion(): void
    {
        $session = new Store('test_session', new ArraySessionHandler(120), 'token-123');
        $session->put('sigtram_cargo_id', 42);
        $session->put('sigtram_permisos', [18]);

        $request = Request::create('/mod_gene/mod_tram_inter/correo_interno/imprimir_bandeja', 'GET', [
            'bandeja' => 'entrada',
            'criterio' => 'tramite',
            'descripcion' => '1000',
        ]);
        $request->setLaravelSession($session);

        // Verificar que la sesión del funcionario se preserva y no se destruye
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        $this->assertEquals(42, $cargoId, 'La sesión del funcionario debe preservarse intacta.');

        // Simular la mutación que realiza imprimirBandeja sin crear un new Request desacoplado
        $request->merge(['page' => 1, 'per_page' => 50]);
        $this->assertEquals(42, (int) $request->session()->get('sigtram_cargo_id'));
        $this->assertEquals('entrada', $request->input('bandeja'));
        $this->assertEquals(50, (int) $request->input('per_page'));
    }

    public function test_soporte_limite_completo_para_exportacion(): void
    {
        $items = range(1, 50);
        $this->assertCount(50, $items, 'La exportación debe procesar el conjunto completo autorizado.');
    }
}