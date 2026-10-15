<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Http\Requests\mod_gm\mod_tram_exte\DerivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\EnviarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\FinalizarExpedienteRequest;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Services\mod_gm\mod_tram_exte\ConsolidacionExpedienteService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Pruebas de regresión que evidencian las brechas reales P0 documentadas en CEF-01 y Auditoría 09.
 */
class BrechasRealesRegresionTest extends TestCase
{
    /**
     * Brecha CEF-P0-04: TramiteExternoPolicy::ver no debe autorizar a usuarios
     * de la misma compañía que no tengan relación con el expediente (no emisor, no receptor, no custodio, no admin).
     */
    public function test_policy_ver_rechaza_usuario_sin_relacion_con_el_expediente(): void
    {
        $policy = new TramiteExternoPolicy();

        // Usuario en cargo 99, área 9, rol 2 (funcionario ordinario)
        $contexto = $this->crearContexto(
            login: 'jdoe',
            usuarioId: 999,
            companiaId: 1,
            cargoId: 99,
            areaId: 9,
            roles: [2]
        );

        // Movimiento de compañía 1, pero entre cargo 10 y cargo 20 (sin relación con cargo 99)
        $movimiento = new ExpedienteMovimientoDto(
            expedienteId: 500,
            numeroExpediente: 'EXP-500',
            movimientoId: 101,
            companiaId: 1,
            cargoSalidaId: 10,
            cargoEntradaId: 20,
            areaSalidaId: 1,
            areaEntradaId: 2,
            estadoId: 1,
            inEsta: '1'
        );

        // El usuario no es emisor (10), ni receptor (20), ni admin (rol 1/3)
        // Debe denegar la lectura para evitar IDOR transversal
        $this->assertFalse(
            $policy->ver($contexto, $movimiento),
            'FALLO DE AUDITORÍA: TramiteExternoPolicy::ver autoriza IDOR a funcionarios sin relación con el expediente.'
        );
    }

    /**
     * Brecha CEF-P0-01: EnviarExpedienteRequest debe incluir fle_adjunto_pdf y anexos en sus reglas.
     */
    public function test_enviar_expediente_request_incluye_reglas_para_adjuntos_blade(): void
    {
        $request = new EnviarExpedienteRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey(
            'fle_adjunto_pdf',
            $rules,
            'FALLO DE AUDITORÍA: EnviarExpedienteRequest no tiene regla para fle_adjunto_pdf (enviado por modal Blade).'
        );
        $this->assertArrayHasKey(
            'fle_adjunto1',
            $rules,
            'FALLO DE AUDITORÍA: EnviarExpedienteRequest no tiene regla para fle_adjunto1 (enviado por modal Blade).'
        );
    }

    /**
     * Brecha CEF-P0-01 y CEF-P0-06: FinalizarExpedienteRequest debe validar los campos reales del formulario Blade.
     */
    public function test_finalizar_expediente_request_incluye_campos_reales_de_cierre_blade(): void
    {
        $request = new FinalizarExpedienteRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey(
            'enviar_correo',
            $rules,
            'FALLO DE AUDITORÍA: FinalizarExpedienteRequest no tiene regla para enviar_correo.'
        );
        $this->assertArrayHasKey(
            'enviar_con_tramite',
            $rules,
            'FALLO DE AUDITORÍA: FinalizarExpedienteRequest no tiene regla para enviar_con_tramite.'
        );
        $this->assertArrayHasKey(
            'doc_tipo',
            $rules,
            'FALLO DE AUDITORÍA: FinalizarExpedienteRequest no tiene regla para doc_tipo.'
        );
        $this->assertArrayHasKey(
            'tramite_nro',
            $rules,
            'FALLO DE AUDITORÍA: FinalizarExpedienteRequest no tiene regla para tramite_nro.'
        );
        $this->assertArrayHasKey(
            'folios',
            $rules,
            'FALLO DE AUDITORÍA: FinalizarExpedienteRequest no tiene regla para folios.'
        );
        $this->assertArrayHasKey(
            'fle_adjunto_pdf',
            $rules,
            'FALLO DE AUDITORÍA: FinalizarExpedienteRequest no tiene regla para fle_adjunto_pdf.'
        );
    }

    /**
     * Brecha CEF-P0-01: DerivarExpedienteRequest debe validar fle_adjunto_pdf y campos de documento asociado.
     */
    public function test_derivar_expediente_request_incluye_adjuntos_y_tramite_asociado(): void
    {
        $request = new DerivarExpedienteRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey(
            'fle_adjunto_pdf',
            $rules,
            'FALLO DE AUDITORÍA: DerivarExpedienteRequest no tiene regla para fle_adjunto_pdf.'
        );
        $this->assertArrayHasKey(
            'enviar_con_tramite',
            $rules,
            'FALLO DE AUDITORÍA: DerivarExpedienteRequest no tiene regla para enviar_con_tramite.'
        );
        $this->assertArrayHasKey(
            'doc_tipo',
            $rules,
            'FALLO DE AUDITORÍA: DerivarExpedienteRequest no tiene regla para doc_tipo.'
        );
    }

    /**
     * Brecha CEF-05: AsignarExpedienteRequest debe validar fle_adjunto_pdf y campos de documento asociado.
     */
    public function test_asignar_expediente_request_incluye_adjuntos_y_tramite_asociado(): void
    {
        $request = new \App\Http\Requests\mod_gm\mod_tram_exte\AsignarExpedienteRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey(
            'fle_adjunto_pdf',
            $rules,
            'FALLO DE AUDITORÍA: AsignarExpedienteRequest no tiene regla para fle_adjunto_pdf.'
        );
        $this->assertArrayHasKey(
            'enviar_con_tramite',
            $rules,
            'FALLO DE AUDITORÍA: AsignarExpedienteRequest no tiene regla para enviar_con_tramite.'
        );
        $this->assertArrayHasKey(
            'doc_tipo',
            $rules,
            'FALLO DE AUDITORÍA: AsignarExpedienteRequest no tiene regla para doc_tipo.'
        );
    }
}
