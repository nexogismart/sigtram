<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gm;

use App\Http\Requests\mod_gm\mod_tram_exte\GenerarExpedienteObservadoRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GenerarExpedienteObservadoRequestValidationTest extends TestCase
{
    private function validarRequest(array $input): array
    {
        $request = GenerarExpedienteObservadoRequest::create('/expediente/observado/generar', 'POST', $input);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        // validateResolved ejecuta prepareForValidation() y luego el validador
        $request->validateResolved();

        return $request->validated();
    }

    /**
     * Test: Petición sin tipo_tramite y sin rdn_cdgo_tpo_trmtes debe ser rechazada con 422.
     */
    public function test_sin_tipo_tramite_y_sin_alias_falla_con_422(): void
    {
        $this->expectException(ValidationException::class);

        $this->validarRequest([
            'id_tram' => 10,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 1,
            // Sin tipo_tramite ni rdn_cdgo_tpo_trmtes
        ]);
    }

    /**
     * Test: E válido es aceptado y normalizado en mayúsculas.
     */
    public function test_e_valido_es_aceptado_y_normalizado(): void
    {
        $validated = $this->validarRequest([
            'id_tram' => 10,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 1,
            'tipo_tramite' => 'e', // minúscula con trim
        ]);

        $this->assertSame('E', $validated['tipo_tramite']);
    }

    /**
     * Test: S válido es aceptado y normalizado en mayúsculas.
     */
    public function test_s_valido_es_aceptado_y_normalizado(): void
    {
        $validated = $this->validarRequest([
            'id_tram' => 10,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 1,
            'tipo_tramite' => ' s ', // con espacios
        ]);

        $this->assertSame('S', $validated['tipo_tramite']);
    }

    /**
     * Test: Alias Legacy rdn_cdgo_tpo_trmtes es copiado a tipo_tramite cuando este último no se envía.
     */
    public function test_alias_legacy_valido_se_normaliza_correctamente(): void
    {
        $validated = $this->validarRequest([
            'id_tram' => 10,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 1,
            'rdn_cdgo_tpo_trmtes' => 'e', // solo se envía el alias legacy
        ]);

        $this->assertSame('E', $validated['tipo_tramite']);
    }

    /**
     * Test: Si se reciben ambos campos y son contradictorios (ej. E vs S), falla con 422.
     */
    public function test_ambos_campos_contradictorios_falla_con_422(): void
    {
        $this->expectException(ValidationException::class);

        $this->validarRequest([
            'id_tram' => 10,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 1,
            'tipo_tramite' => 'E',
            'rdn_cdgo_tpo_trmtes' => 'S', // Contradicción explícita
        ]);
    }

    /**
     * Test: Tipos no permitidos en este flujo (ej. 'O', 'X', '17', '22') fallan con 422.
     */
    public function test_tipo_no_permitido_falla_con_422(): void
    {
        $this->expectException(ValidationException::class);

        $this->validarRequest([
            'id_tram' => 10,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 1,
            'tipo_tramite' => 'O', // En conversión virtual solo se permite E o S
        ]);
    }

    /**
     * Test: ID numérico enviado como tipo_tramite falla con 422.
     */
    public function test_id_numerico_como_tipo_falla_con_422(): void
    {
        $this->expectException(ValidationException::class);

        $this->validarRequest([
            'id_tram' => 10,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 1,
            'tipo_tramite' => '22',
        ]);
    }
}
