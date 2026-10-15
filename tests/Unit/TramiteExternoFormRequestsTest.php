<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\mod_gm\mod_tram_exte\ArchivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\AsignarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ConsultarDetalleRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ConsultarHistorialRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ConsultarSemillaRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DerivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DevolverExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\EnviarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ExportarBandejaExternaRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\FinalizarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ListarBandejaExternaRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\PrestarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RecepcionarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RedirigirExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RetornarPrestamoRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\SolicitudVideoRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TramiteExternoFormRequestsTest extends TestCase
{
    public function test_recepcionar_request_valida_array_limites_y_normaliza_fecha(): void
    {
        $request = new RecepcionarExpedienteRequest();

        // Vacío falla
        $vVacio = Validator::make(['ids' => []], $request->rules(), $request->messages());
        $this->assertTrue($vVacio->fails());

        // Más de 100 falla
        $vExceso = Validator::make(['ids' => range(1, 101)], $request->rules(), $request->messages());
        $this->assertTrue($vExceso->fails());

        // Válido con normalización de fecha datetime-local
        $reqInstancia = new RecepcionarExpedienteRequest();
        $reqInstancia->merge([
            'ids' => [1, 2, 3],
            'fecha' => '2026-10-10T14:30',
        ]);
        $ref = new \ReflectionMethod($reqInstancia, 'prepareForValidation');
        $ref->setAccessible(true);
        $ref->invoke($reqInstancia);

        $this->assertSame('2026-10-10 14:30:00', $reqInstancia->input('fecha'));

        $vValido = Validator::make($reqInstancia->all(), $reqInstancia->rules(), $reqInstancia->messages());
        $this->assertFalse($vValido->fails());
    }

    public function test_derivar_request_exige_gerencia_positiva_y_expedientes(): void
    {
        $request = new DerivarExpedienteRequest();

        $vInvalido = Validator::make([
            'ids' => [1],
            'gerencia' => 0,
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertTrue($vInvalido->fails());

        $vValido = Validator::make([
            'ids' => [1, 2],
            'gerencia' => 55,
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertFalse($vValido->fails());
    }

    public function test_asignar_request_exige_cargo_interno(): void
    {
        $request = new AsignarExpedienteRequest();

        $vInvalido = Validator::make([
            'ids' => [1],
            'cargo' => 0,
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertTrue($vInvalido->fails());

        $vValido = Validator::make([
            'ids' => [1],
            'cargo' => 42,
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertFalse($vValido->fails());
    }

    public function test_enviar_request_exige_areas_destino_array(): void
    {
        $request = new EnviarExpedienteRequest();

        $vSinAreas = Validator::make([
            'ids' => [1],
            'areas' => [],
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertTrue($vSinAreas->fails());

        $vValido = Validator::make([
            'ids' => [1],
            'areas' => [10, 20],
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertFalse($vValido->fails());
    }

    public function test_finalizar_request_exige_observaciones_minimas_y_correo_valido(): void
    {
        $request = new FinalizarExpedienteRequest();

        $vCorta = Validator::make([
            'ids' => [1],
            'observaciones' => 'ab', // < 3 caracteres
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertTrue($vCorta->fails());

        $vCorreoInvalido = Validator::make([
            'ids' => [1],
            'observaciones' => 'Expediente cerrado con resolución',
            'correo' => 'no-es-correo',
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertTrue($vCorreoInvalido->fails());

        $vValido = Validator::make([
            'ids' => [1],
            'observaciones' => 'Expediente cerrado con resolución',
            'correo' => 'usuario@puentepiedra.gob.pe',
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertFalse($vValido->fails());
    }

    public function test_devolver_request_exige_motivo_minimo(): void
    {
        $request = new DevolverExpedienteRequest();

        $vVacio = Validator::make([
            'ids' => [1],
            'motivo' => '',
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertTrue($vVacio->fails());

        $vValido = Validator::make([
            'ids' => [1],
            'motivo' => 'Devolución formal por falta de firma',
            'fecha' => '2026-10-10 10:00:00',
        ], $request->rules(), $request->messages());
        $this->assertFalse($vValido->fails());
    }

    public function test_archivar_prestar_y_retornar_requests(): void
    {
        // Archivar
        $reqArch = new ArchivarExpedienteRequest();
        $vArchInvalido = Validator::make(['ids' => [1], 'ubicacion' => 'a'], $reqArch->rules(), $reqArch->messages());
        $this->assertTrue($vArchInvalido->fails());

        $vArchValido = Validator::make(['ids' => [1], 'ubicacion' => 'CAJA-12 / ESTANTE 4'], $reqArch->rules(), $reqArch->messages());
        $this->assertFalse($vArchValido->fails());

        // Prestar
        $reqPrest = new PrestarExpedienteRequest();
        $vPrestInvalido = Validator::make(['expediente_id' => 1, 'prestatario' => 'ab', 'finalidad' => 'a', 'fecha_salida' => '2026-10-10'], $reqPrest->rules(), $reqPrest->messages());
        $this->assertTrue($vPrestInvalido->fails());

        $vPrestValido = Validator::make([
            'expediente_id' => 1,
            'prestatario' => 'Juan Pérez',
            'finalidad' => 'Auditoría interna de rentas',
            'fecha_salida' => '2026-10-10 10:00:00',
        ], $reqPrest->rules(), $reqPrest->messages());
        $this->assertFalse($vPrestValido->fails());

        // Retornar
        $reqRet = new RetornarPrestamoRequest();
        $vRetInvalido = Validator::make(['expediente_id' => 0], $reqRet->rules(), $reqRet->messages());
        $this->assertTrue($vRetInvalido->fails());

        $vRetValido = Validator::make(['expediente_id' => 1, 'observaciones' => 'Devuelto íntegro sin observaciones'], $reqRet->rules(), $reqRet->messages());
        $this->assertFalse($vRetValido->fails());
    }

    public function test_redirigir_solicitud_video_y_consultas_requests(): void
    {
        // Redirigir
        $reqRedir = new RedirigirExpedienteRequest();
        $vRedirInvalido = Validator::make(['expediente_id' => 1, 'nuevo_cargo_id' => 0, 'motivo' => 'a'], $reqRedir->rules(), $reqRedir->messages());
        $this->assertTrue($vRedirInvalido->fails());

        $vRedirValido = Validator::make(['expediente_id' => 1, 'nuevo_cargo_id' => 20, 'motivo' => 'Reasignación por competencia funcional'], $reqRedir->rules(), $reqRedir->messages());
        $this->assertFalse($vRedirValido->fails());

        // Solicitud video
        $reqVideo = new SolicitudVideoRequest();
        $vVideoInvalido = Validator::make(['id' => 0], $reqVideo->rules(), $reqVideo->messages());
        $this->assertTrue($vVideoInvalido->fails());

        $vVideoValido = Validator::make(['id' => 10], $reqVideo->rules(), $reqVideo->messages());
        $this->assertFalse($vVideoValido->fails());

        // Consultas
        $reqHist = new ConsultarHistorialRequest();
        $this->assertFalse(Validator::make(['id' => 123], $reqHist->rules(), $reqHist->messages())->fails());

        $reqDet = new ConsultarDetalleRequest();
        $this->assertFalse(Validator::make(['id' => 456], $reqDet->rules(), $reqDet->messages())->fails());

        $reqSem = new ConsultarSemillaRequest();
        $this->assertFalse(Validator::make(['doc_tipo' => 789], $reqSem->rules(), $reqSem->messages())->fails());
    }

    /**
     * Valida los 10 casos obligatorios de paridad de rango de fechas en ListarBandejaExternaRequest
     * y ExportarBandejaExternaRequest con Validator real y ciclo prepareForValidation().
     * Requisito AG-FINAL-06.
     */
    public function test_paridad_validacion_rango_fechas_en_listar_y_exportar_bandeja_externa(): void
    {
        /** @var array<string, class-string<FormRequest>> $requests */
        $requests = [
            'ListarBandejaExternaRequest' => ListarBandejaExternaRequest::class,
            'ExportarBandejaExternaRequest' => ExportarBandejaExternaRequest::class,
        ];

        foreach ($requests as $nombre => $clase) {
            // Caso 1: Solo desde es válido
            $req1 = new $clase();
            $v1 = $this->ejecutarValidacionRequest($req1, ['bandeja' => 'todos', 'desde' => '2026-10-01']);
            $this->assertFalse($v1->fails(), "[$nombre] Caso 1: Solo desde debe ser válido.");

            // Caso 2: Solo hasta es válido
            $req2 = new $clase();
            $v2 = $this->ejecutarValidacionRequest($req2, ['bandeja' => 'todos', 'hasta' => '2026-10-10']);
            $this->assertFalse($v2->fails(), "[$nombre] Caso 2: Solo hasta debe ser válido.");

            // Caso 3: Desde igual a hasta es válido
            $req3 = new $clase();
            $v3 = $this->ejecutarValidacionRequest($req3, [
                'bandeja' => 'todos',
                'desde' => '2026-10-10',
                'hasta' => '2026-10-10',
            ]);
            $this->assertFalse($v3->fails(), "[$nombre] Caso 3: Desde igual a hasta debe ser válido.");

            // Caso 4: Hasta posterior a desde es válido
            $req4 = new $clase();
            $v4 = $this->ejecutarValidacionRequest($req4, [
                'bandeja' => 'todos',
                'desde' => '2026-10-01',
                'hasta' => '2026-10-10',
            ]);
            $this->assertFalse($v4->fails(), "[$nombre] Caso 4: Hasta posterior a desde debe ser válido.");

            // Caso 5: Hasta anterior a desde es inválido en ambos requests y el error corresponde a hasta
            $req5 = new $clase();
            $v5 = $this->ejecutarValidacionRequest($req5, [
                'bandeja' => 'todos',
                'desde' => '2026-10-15',
                'hasta' => '2026-10-10',
            ]);
            $this->assertTrue($v5->fails(), "[$nombre] Caso 5: Hasta anterior a desde debe ser rechazado.");
            $this->assertTrue($v5->errors()->has('hasta'), "[$nombre] Caso 5: El error debe corresponder al campo hasta.");

            // Caso 6: Fecha desde inválida debe ser rechazada
            $req6 = new $clase();
            $v6 = $this->ejecutarValidacionRequest($req6, [
                'bandeja' => 'todos',
                'desde' => 'fecha-invalida',
            ]);
            $this->assertTrue($v6->fails(), "[$nombre] Caso 6: Fecha desde inválida debe ser rechazada.");
            $this->assertTrue($v6->errors()->has('desde'), "[$nombre] Caso 6: El error debe corresponder al campo desde.");

            // Caso 7: Fecha hasta inválida debe ser rechazada
            $req7 = new $clase();
            $v7 = $this->ejecutarValidacionRequest($req7, [
                'bandeja' => 'todos',
                'hasta' => 'fecha-invalida',
            ]);
            $this->assertTrue($v7->fails(), "[$nombre] Caso 7: Fecha hasta inválida debe ser rechazada.");
            $this->assertTrue($v7->errors()->has('hasta'), "[$nombre] Caso 7: El error debe corresponder al campo hasta.");

            // Caso 8: Formatos admitidos por NormalizaFechasRequestTrait se normalizan igual en ambos requests
            $req8 = new $clase();
            $this->ejecutarValidacionRequest($req8, [
                'bandeja' => 'todos',
                'desde' => '2026-10-01T08:30',
                'hasta' => '2026-10-10T17:45:00',
            ]);
            $this->assertSame('2026-10-01 08:30:00', $req8->input('desde'), "[$nombre] Caso 8: desde debe normalizarse a formato canónico.");
            $this->assertSame('2026-10-10 17:45:00', $req8->input('hasta'), "[$nombre] Caso 8: hasta debe normalizarse a formato canónico.");

            // Caso 9: Rango invertido después de normalización debe seguir siendo rechazado
            $req9 = new $clase();
            $v9 = $this->ejecutarValidacionRequest($req9, [
                'bandeja' => 'todos',
                'desde' => '2026-10-20T10:00',
                'hasta' => '2026-10-10T10:00',
            ]);
            $this->assertTrue($v9->fails(), "[$nombre] Caso 9: Rango invertido post-normalización debe ser rechazado.");
            $this->assertTrue($v9->errors()->has('hasta'), "[$nombre] Caso 9: El error debe ser en el campo hasta.");

            // Caso 10: Mensaje funcional exacto
            $this->assertSame(
                'La fecha final no puede ser anterior a la fecha inicial.',
                $v5->errors()->first('hasta'),
                "[$nombre] Caso 10: Mensaje funcional exacto en caso 5."
            );
            $this->assertSame(
                'La fecha final no puede ser anterior a la fecha inicial.',
                $v9->errors()->first('hasta'),
                "[$nombre] Caso 10: Mensaje funcional exacto en caso 9."
            );
        }
    }

    /**
     * Helper para preparar y validar un FormRequest ejecutando su ciclo prepareForValidation().
     *
     * @param ListarBandejaExternaRequest|ExportarBandejaExternaRequest $request
     * @param array<string, mixed> $data
     * @return \Illuminate\Validation\Validator
     */
    private function ejecutarValidacionRequest(ListarBandejaExternaRequest|ExportarBandejaExternaRequest $request, array $data): \Illuminate\Validation\Validator
    {
        $request->replace($data);
        $ref = new \ReflectionMethod($request, 'prepareForValidation');
        $ref->setAccessible(true);
        $ref->invoke($request);

        return Validator::make($request->all(), $request->rules(), $request->messages());
    }
}
