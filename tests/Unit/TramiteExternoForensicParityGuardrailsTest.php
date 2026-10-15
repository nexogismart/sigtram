<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\mod_gm\mod_tram_exte\ArchivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\AsignarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DerivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DevolverExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\EnviarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\FinalizarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\PrestarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RecepcionarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RedirigirExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RetornarPrestamoRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TramiteExternoForensicParityGuardrailsTest extends TestCase
{
    /**
     * Guardrail 1: Contrato canónico movimiento_ids, normalización de alias y rechazo de identificadores contradictorios.
     */
    public function test_form_requests_contrato_canonico_movimiento_ids_y_normalizacion_de_alias(): void
    {
        // 1. Recepcionar acepta contrato canónico movimiento_ids
        $reqRec = new RecepcionarExpedienteRequest();
        $vRecMov = Validator::make(['movimiento_ids' => [101, 102], 'fecha' => '2026-10-14 10:00:00'], $reqRec->rules());
        $this->assertFalse($vRecMov->fails(), 'Recepcionar debe aceptar movimiento_ids canónico');

        // Normalización de alias 'ids' vía prepareForValidation
        $reqRecInst = new RecepcionarExpedienteRequest();
        $reqRecInst->merge(['ids' => [101, 102], 'fecha' => '2026-10-14 10:00:00']);
        $ref = new \ReflectionMethod($reqRecInst, 'prepareForValidation');
        $ref->setAccessible(true);
        $ref->invoke($reqRecInst);
        $this->assertEquals([101, 102], $reqRecInst->input('movimiento_ids'), 'Alias ids debe normalizarse a movimiento_ids');

        // Rechazo de IDs contradictorios
        $reqRecContradictorio = RecepcionarExpedienteRequest::create('/test', 'POST', [
            'movimiento_ids' => [101],
            'ids' => [202],
            'fecha' => '2026-10-14 10:00:00',
        ]);
        $valRec = $this->app->get('validator')->make($reqRecContradictorio->all(), $reqRecContradictorio->rules(), $reqRecContradictorio->messages());
        $reqRecContradictorio->withValidator($valRec);
        $this->assertTrue($valRec->fails(), 'Envío de movimiento_ids e ids contradictorios debe ser rechazado');
        $this->assertArrayHasKey('movimiento_ids', $valRec->errors()->toArray());

        // 2. Derivar acepta contrato canónico
        $reqDer = new DerivarExpedienteRequest();
        $vDerMov = Validator::make(['movimiento_ids' => [101], 'gerencia' => 10, 'fecha' => '2026-10-14 10:00:00'], $reqDer->rules());
        $this->assertFalse($vDerMov->fails(), 'Derivar debe aceptar movimiento_ids');

        // Derivar rechaza adjuntos auxiliares fle_adjunto1..4
        $vDerAux = Validator::make([
            'movimiento_ids' => [101],
            'gerencia' => 10,
            'fecha' => '2026-10-14 10:00:00',
            'fle_adjunto1' => 'fake_file',
        ], $reqDer->rules());
        $this->assertTrue($vDerAux->fails(), 'fle_adjunto1 debe estar prohibido');

        // 3. Asignar acepta contrato canónico
        $reqAsig = new AsignarExpedienteRequest();
        $vAsigMov = Validator::make(['movimiento_ids' => [101], 'cargo' => 25, 'fecha' => '2026-10-14 10:00:00'], $reqAsig->rules());
        $this->assertFalse($vAsigMov->fails(), 'Asignar debe aceptar movimiento_ids');

        // 4. Enviar acepta contrato canónico
        $reqEnv = new EnviarExpedienteRequest();
        $vEnvMov = Validator::make(['movimiento_ids' => [101], 'areas' => [5, 6], 'fecha' => '2026-10-14 10:00:00'], $reqEnv->rules());
        $this->assertFalse($vEnvMov->fails(), 'Enviar debe aceptar movimiento_ids');

        // 5. Finalizar acepta contrato canónico
        $reqFin = new FinalizarExpedienteRequest();
        $vFinMov = Validator::make(['movimiento_ids' => [101], 'fecha' => '2026-10-14 10:00:00', 'observaciones' => 'Atención concluida con éxito.'], $reqFin->rules());
        $this->assertFalse($vFinMov->fails(), 'Finalizar debe aceptar movimiento_ids');

        // 6. Devolver acepta contrato canónico
        $reqDev = new DevolverExpedienteRequest();
        $vDevMov = Validator::make(['movimiento_ids' => [101], 'fecha' => '2026-10-14 10:00:00', 'motivo' => 'Devolución por falta de firma.'], $reqDev->rules());
        $this->assertFalse($vDevMov->fails(), 'Devolver debe aceptar movimiento_ids');

        // 7. Archivar acepta contrato canónico
        $reqArch = new ArchivarExpedienteRequest();
        $vArchMov = Validator::make(['movimiento_ids' => [101], 'ubicacion' => 'ESTANTE-01 / BALDA-B'], $reqArch->rules());
        $this->assertFalse($vArchMov->fails(), 'Archivar debe aceptar movimiento_ids');
    }

    /**
     * Guardrail 2: Operaciones individuales usan movimiento_id como contrato canónico y rechazan campos contradictorios.
     */
    public function test_operaciones_individuales_contrato_canonico_y_rechazo_de_contradictorios(): void
    {
        // 1. Prestar: contrato canónico movimiento_id
        $reqPrest = new PrestarExpedienteRequest();
        $vPrestMov = Validator::make(['movimiento_id' => 101, 'prestatario' => 'Carlos Diaz', 'finalidad' => 'Fiscalización', 'fecha_salida' => '2026-10-14'], $reqPrest->rules());
        $this->assertFalse($vPrestMov->fails(), 'Prestar debe aceptar movimiento_id');

        // Contradicción movimiento_id vs expediente_id
        $reqPrestContradictorio = PrestarExpedienteRequest::create('/test', 'POST', [
            'movimiento_id' => 101,
            'expediente_id' => 202,
            'prestatario' => 'Carlos Diaz',
            'finalidad' => 'Fiscalización',
            'fecha_salida' => '2026-10-14',
        ]);
        $valPrest = $this->app->get('validator')->make($reqPrestContradictorio->all(), $reqPrestContradictorio->rules(), $reqPrestContradictorio->messages());
        $reqPrestContradictorio->withValidator($valPrest);
        $this->assertTrue($valPrest->fails(), 'Prestar con movimiento_id y expediente_id contradictorios debe fallar');

        // 2. Retornar préstamo: contrato canónico movimiento_id
        $reqRet = new RetornarPrestamoRequest();
        $vRetMov = Validator::make(['movimiento_id' => 101], $reqRet->rules());
        $this->assertFalse($vRetMov->fails(), 'Retornar préstamo debe aceptar movimiento_id');

        // 3. Redirigir: contrato canónico movimiento_id
        $reqRed = new RedirigirExpedienteRequest();
        $vRedMov = Validator::make(['movimiento_id' => 101, 'nuevo_cargo_id' => 50, 'motivo' => 'Corrección de destino'], $reqRed->rules());
        $this->assertFalse($vRedMov->fails(), 'Redirigir debe aceptar movimiento_id');
    }

    /**
     * Guardrail 2.1: El frontend actual (acciones.js) envía exclusivamente identificadores canónicos y no alias.
     */
    public function test_frontend_acciones_js_envia_exclusivamente_campos_canonicos(): void
    {
        $accionesJsPath = resource_path('js/tramite-externo/acciones.js');
        $this->assertFileExists($accionesJsPath);
        $jsContent = file_get_contents($accionesJsPath);

        // Envía movimiento_ids / movimiento_id
        $this->assertStringContainsString('movimiento_ids:', $jsContent);
        $this->assertStringContainsString("fd.append('movimiento_ids[]',", $jsContent);
        $this->assertStringContainsString('movimiento_id:', $jsContent);

        // NO envía alias antiguos hacia el backend
        $this->assertDoesNotMatchRegularExpression('/(?<![_\w])ids:\s*this\.selectedIds/', $jsContent);
        $this->assertStringNotContainsString("fd.append('ids[]',", $jsContent);
        $this->assertStringNotContainsString('expediente_id: movId', $jsContent);

        // Hoja de ruta usa documento_id (expediente completo)
        $this->assertStringContainsString('item.documento_id', $jsContent);
    }

    /**
     * Guardrail 3: Validación condicional de enviar_con_tramite.
     */
    public function test_enviar_con_tramite_solo_exige_doc_tipo_cuando_esta_activo(): void
    {
        $req = new DerivarExpedienteRequest();

        // Sin trámite asociado: doc_tipo no debe ser exigido
        $vSin = Validator::make([
            'movimiento_ids' => [1],
            'gerencia' => 10,
            'fecha' => '2026-10-14 10:00:00',
            'enviar_con_tramite' => 0,
        ], $req->rules());
        $this->assertFalse($vSin->fails());

        // Con trámite asociado pero sin doc_tipo: DEBE FALLAR
        $vConSinTipo = Validator::make([
            'movimiento_ids' => [1],
            'gerencia' => 10,
            'fecha' => '2026-10-14 10:00:00',
            'enviar_con_tramite' => 1,
        ], $req->rules());
        $this->assertTrue($vConSinTipo->fails());
        $this->assertArrayHasKey('doc_tipo', $vConSinTipo->errors()->toArray());

        // Con trámite asociado y con doc_tipo válido: DEBE PASAR
        $vConConTipo = Validator::make([
            'movimiento_ids' => [1],
            'gerencia' => 10,
            'fecha' => '2026-10-14 10:00:00',
            'enviar_con_tramite' => 1,
            'doc_tipo' => 12,
        ], $req->rules());
        $this->assertFalse($vConConTipo->fails());
    }

    /**
     * Guardrail 4: TramiteExternoService NO contiene hardcoded logins o logins string en cdgo_lgin.
     */
    public function test_tramite_externo_service_no_contiene_login_string_en_cdgo_lgin(): void
    {
        $codigoService = file_get_contents(app_path('Services/mod_gm/mod_tram_exte/TramiteExternoService.php'));

        $this->assertStringNotContainsString(
            "'cdgo_lgin'         => \$contexto->login",
            $codigoService,
            'TramiteExternoService no debe asignar login string a cdgo_lgin (debe usar loginId numérico).'
        );
        $this->assertStringNotContainsString(
            "'cdgo_lgin' => \$contexto->login",
            $codigoService,
            'TramiteExternoService no debe asignar login string a cdgo_lgin.'
        );
    }

    /**
     * Guardrail 5: TramiteExternoService no degrada lockForUpdate a consultas desprotegidas con catch(Throwable).
     */
    public function test_obtener_movimientos_para_operacion_no_degrada_concurrencia(): void
    {
        $codigoService = file_get_contents(app_path('Services/mod_gm/mod_tram_exte/TramiteExternoService.php'));

        $this->assertStringNotContainsString(
            "catch (\\Throwable) {\n            // Fallback a lectura sin bloqueo",
            $codigoService,
            'TramiteExternoService no debe tener fallback que degrade lockForUpdate a consultas sin bloqueo.'
        );
    }

    /**
     * Guardrail 6: Las 10 rutas operativas y la impresión de bandeja están registradas en routes/web.php.
     */
    public function test_rutas_operativas_y_reporte_registradas(): void
    {
        $rutasEsperadas = [
            'mod_gm.segi_expedi.recepcionar',
            'mod_gm.segi_expedi.derivar',
            'mod_gm.segi_expedi.asignar',
            'mod_gm.segi_expedi.enviar',
            'mod_gm.segi_expedi.finalizar',
            'mod_gm.segi_expedi.devolver',
            'mod_gm.segi_expedi.archivar',
            'mod_gm.segi_expedi.prestar',
            'mod_gm.segi_expedi.retornar_prestamo',
            'mod_gm.segi_expedi.redirigir',
            'mod_gm.segi_expedi.imprimir_bandeja',
            'mod_gm.segi_expedi.hoja_ruta',
            'mod_gm.segi_expedi.hoja_ruta.alias',
        ];

        foreach ($rutasEsperadas as $nombreRuta) {
            $this->assertTrue(Route::has($nombreRuta), "La ruta {$nombreRuta} debe existir en el enrutador de Laravel.");
        }
    }

    /**
     * Guardrail 7: La vista reporte_bandeja.blade.php es segura contra objetos y arrays.
     */
    public function test_reporte_bandeja_blade_es_seguro(): void
    {
        $contenido = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/reporte_bandeja.blade.php'));

        $this->assertStringContainsString('$item = (object) $row;', $contenido);
        $this->assertStringContainsString('Nº Exp.', $contenido);
        $this->assertStringContainsString('F. Ingreso', $contenido);
        $this->assertStringContainsString('Doc. Identidad', $contenido);
        $this->assertStringContainsString('Nombre / Razón Social', $contenido);
        $this->assertStringContainsString('Asunto / Detalle', $contenido);
        $this->assertStringContainsString('Área Ingreso', $contenido);
        $this->assertStringContainsString('Estado', $contenido);
        $this->assertStringContainsString('F. Último', $contenido);
        $this->assertStringContainsString('Última Observación', $contenido);
        $this->assertStringContainsString('Cargo Destino', $contenido);
    }
}
