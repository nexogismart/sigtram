<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\mod_gm\FilaBandejaExternaDto;
use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Http\Requests\mod_gm\mod_tram_exte\ListarBandejaExternaRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ContratosBandejaExternaTest extends TestCase
{
    /**
     * Verifica que el DTO canónico FilaBandejaExternaDto exponga exactamente los campos consumidos por la UI.
     */
    public function test_fila_bandeja_externa_dto_contiene_campos_canonicos(): void
    {
        $dto = new FilaBandejaExternaDto(
            cdgoDtoTrmte: 101,
            origDoc: 'E',
            nuExpeTodo: '2026-000101',
            cdgoEstdoTrmte: 2,
            dcEstdoTrmte: 'RECIBIDO',
            feRcbdoTrmte: '10/10/2026 14:00',
            deObseTrmte: 'Observación formal',
            noCrto: 'Juan Pérez',
            nuTiemSile: 30,
            nuTiemSileAdcnal: 10,
            diasAcumulados: 5,
            hasVideo: true
        );

        $array = $dto->toArray();

        $this->assertSame(101, $array['cdgo_dto_trmte']);
        $this->assertSame('E', $array['orig_doc']);
        $this->assertSame('2026-000101', $array['nu_expe_todo']);
        $this->assertSame(2, $array['cdgo_estdo_trmte']);
        $this->assertSame('RECIBIDO', $array['dc_estdo_trmte']);
        $this->assertSame('10/10/2026 14:00', $array['fe_rcbdo_trmte']);
        $this->assertSame('Observación formal', $array['de_obse_trmte']);
        $this->assertSame('Juan Pérez', $array['no_crto']);
        $this->assertSame(30, $array['nu_tiem_sile']);
        $this->assertSame(10, $array['nu_tiem_sile_adcnal']);
        $this->assertSame(5, $array['dias_acumulados']);
        $this->assertTrue($array['has_video']);
    }

    /**
     * Verifica que las bandejas visibles permitidas sean válidas en el FormRequest (FASE 10).
     */
    public function test_form_request_acepta_bandejas_visibles_permitidas(): void
    {
        $bandejas = ListarBandejaExternaRequest::BANDEJAS_PERMITIDAS;

        $request = new ListarBandejaExternaRequest();
        $rules = $request->rules();

        foreach ($bandejas as $bandeja) {
            $validator = Validator::make(['bandeja' => $bandeja], ['bandeja' => $rules['bandeja']]);
            $this->assertFalse($validator->fails(), "La bandeja permitida [{$bandeja}] debería ser válida.");
        }
    }

    /**
     * Verifica que bandejas ocultas y criterios no permitidos sean rechazados con error de validación 422.
     */
    public function test_form_request_rechaza_bandejas_y_criterios_desconocidos(): void
    {
        $request = new ListarBandejaExternaRequest();
        $rules = $request->rules();

        // Bandeja desconocida
        $val1 = Validator::make(['bandeja' => 'bandeja_inexistente'], ['bandeja' => $rules['bandeja']]);
        $this->assertTrue($val1->fails(), 'Bandeja inexistente debe ser rechazada.');

        // Bandejas ocultas no permitidas en Correo Externo (FASE 10)
        $bandejasOcultas = ['notificados', 'prestados', 'inventariar'];
        foreach ($bandejasOcultas as $oculta) {
            $valOculta = Validator::make(['bandeja' => $oculta], ['bandeja' => $rules['bandeja']]);
            $this->assertTrue($valOculta->fails(), "Bandeja oculta [{$oculta}] debe ser rechazada en Correo Externo.");
        }

        // Criterio desconocido
        $val2 = Validator::make(
            ['bandeja' => 'entrada', 'criterio' => 'criterio_invalido'],
            ['bandeja' => $rules['bandeja'], 'criterio' => $rules['criterio']]
        );
        $this->assertTrue($val2->fails(), 'Criterio inválido debe ser rechazado.');
    }
}
