<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\Policies\mod_gene\TramiteInternoPolicy;
use Tests\TestCase;

class TramiteInternoPolicyTest extends TestCase
{
    private TramiteInternoPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TramiteInternoPolicy();
    }

    public function test_rechaza_por_compania_distinta(): void
    {
        $ctx = $this->crearContexto('admin', 1, 1, 10, 5, [10]);
        $mov = new TramiteInternoMovimientoDto(100, 500, '001-2026', 2, 8, 10, 4, 5, 1);

        $this->assertFalse($this->policy->ver($ctx, $mov));
        $this->assertFalse($this->policy->recepcionar($ctx, $mov));
    }

    public function test_rechaza_cargo_no_asignado_al_login(): void
    {
        // El usuario declara tener cargo 99, pero sus cargos asignados son solo [10, 20]
        $ctx = $this->crearContexto('admin', 1, 1, 99, 5, [10, 20]);
        $mov = new TramiteInternoMovimientoDto(100, 500, '001-2026', 1, 8, 99, 4, 5, 1);

        $this->assertFalse($this->policy->recepcionar($ctx, $mov));
    }

    public function test_recepcion_autorizada_solo_para_cargo_destino_y_estado_valido(): void
    {
        $ctx = $this->crearContexto('operador', 2, 1, 15, 6, [15]);
        $movValido = new TramiteInternoMovimientoDto(101, 501, '002-2026', 1, 8, 15, 4, 6, 1);
        $this->assertTrue($this->policy->recepcionar($ctx, $movValido));

        // Rechaza si el estado ya es 2 (ya fue recibido)
        $movRecibido = new TramiteInternoMovimientoDto(101, 501, '002-2026', 1, 8, 15, 4, 6, 2);
        $this->assertFalse($this->policy->recepcionar($ctx, $movRecibido));

        // Rechaza si el cargo destino es otro
        $movOtroCargo = new TramiteInternoMovimientoDto(101, 501, '002-2026', 1, 8, 99, 4, 6, 1);
        $this->assertFalse($this->policy->recepcionar($ctx, $movOtroCargo));
    }

    public function test_asignacion_solo_hacia_la_misma_area(): void
    {
        $ctx = $this->crearContexto('jefe', 3, 1, 20, 8, [20]);
        $mov = new TramiteInternoMovimientoDto(102, 502, '003-2026', 1, 20, 20, 8, 8, 2);

        // Asignación válida hacia la misma área 8
        $this->assertTrue($this->policy->asignar($ctx, $mov, 8));

        // Asignación rechazada hacia otra área (ej: área 12)
        $this->assertFalse($this->policy->asignar($ctx, $mov, 12));
    }

    public function test_devolucion_exige_emisor_original_y_causal_no_vacia(): void
    {
        $ctxEmisor = $this->crearContexto('emisor', 4, 1, 30, 9, [30]);
        $movPendiente = new TramiteInternoMovimientoDto(103, 503, '004-2026', 1, 30, 40, 9, 10, 1);

        // Rechaza causal vacía
        $this->assertFalse($this->policy->devolver($ctxEmisor, $movPendiente, ''));

        // Rechaza causal por defecto
        $this->assertFalse($this->policy->devolver($ctxEmisor, $movPendiente, 'Sin novedades para devolver'));

        // Acepta con causal explícita legítima
        $this->assertTrue($this->policy->devolver($ctxEmisor, $movPendiente, 'Falta firma del gerente responsable en foja 2'));

        // Rechaza si quien intenta devolver no fue el emisor
        $ctxOtro = $this->crearContexto('otro', 5, 1, 99, 10, [99]);
        $this->assertFalse($this->policy->devolver($ctxOtro, $movPendiente, 'Motivo'));
    }

    public function test_reiterar_autorizado_solo_para_emisor_en_transito(): void
    {
        $ctxEmisor = $this->crearContexto('emisor', 4, 1, 30, 9, [30]);
        $movPendiente = new TramiteInternoMovimientoDto(104, 504, '005-2026', 1, 30, 40, 9, 10, 1);
        $this->assertTrue($this->policy->reiterar($ctxEmisor, $movPendiente));

        // Rechaza si ya fue recibido en destino (estado 2)
        $movRecibido = new TramiteInternoMovimientoDto(104, 504, '005-2026', 1, 30, 40, 9, 10, 2);
        $this->assertFalse($this->policy->reiterar($ctxEmisor, $movRecibido));
    }

    public function test_super_permiso_18_autoriza_lectura_y_contabilidad(): void
    {
        $ctxConPermiso18 = $this->crearContexto('auditor', 9, 1, 50, 15, [50], [18]);
        $movAjeno = new TramiteInternoMovimientoDto(105, 505, '006-2026', 1, 80, 85, 30, 31, 2);

        $this->assertTrue($this->policy->ver($ctxConPermiso18, $movAjeno));
        $this->assertTrue($this->policy->verContabilidad($ctxConPermiso18));
    }

    public function test_view_history_autoriza_misma_compania_sin_exigir_participacion(): void
    {
        $ctx = $this->crearContexto('operador', 10, 1, 20, 5, [20]);
        // Movimiento entre áreas 30 y 31 y cargos 90 y 91, totalmente ajenos al usuario, pero de compañía 1
        $movAjenoMismaCompania = new TramiteInternoMovimientoDto(100, 506, '001-2026', 1, 90, 91, 30, 31, 1, '0');

        $this->assertTrue($this->policy->consultarHistorico($ctx, $movAjenoMismaCompania));
    }

    public function test_view_history_rechaza_compania_distinta(): void
    {
        $ctx = $this->crearContexto('operador', 10, 1, 20, 5, [20]);
        // Movimiento de compañía 2
        $movOtraCompania = new TramiteInternoMovimientoDto(100, 507, '001-2026', 2, 20, 30, 5, 8, 1, '1');

        $this->assertFalse($this->policy->consultarHistorico($ctx, $movOtraCompania));
    }

    public function test_view_historical_document_autoriza_misma_compania_y_rechaza_otra(): void
    {
        $ctx = $this->crearContexto('operador', 10, 1, 20, 5, [20]);

        $this->assertTrue($this->policy->verDocumentoHistorico($ctx, 1));
        $this->assertFalse($this->policy->verDocumentoHistorico($ctx, 2));
    }

    public function test_separacion_estricta_lectura_historica_vs_mutacion_operativa(): void
    {
        $ctx = $this->crearContexto('operador', 10, 1, 20, 5, [20]);
        // Movimiento histórico inactivo entre áreas ajenas
        $movInactivoAjeno = new TramiteInternoMovimientoDto(100, 508, '001-2026', 1, 90, 91, 30, 31, 1, '0');

        // Lectura histórica: AUTORIZADA
        $this->assertTrue($this->policy->consultarHistorico($ctx, $movInactivoAjeno));

        // Mutaciones: ESTRICTAMENTE RECHAZADAS
        $this->assertFalse($this->policy->recepcionar($ctx, $movInactivoAjeno));
        $this->assertFalse($this->policy->asignar($ctx, $movInactivoAjeno, 5));
        $this->assertFalse($this->policy->derivar($ctx, $movInactivoAjeno));
        $this->assertFalse($this->policy->finalizar($ctx, $movInactivoAjeno));
        $this->assertFalse($this->policy->devolver($ctx, $movInactivoAjeno, 'Motivo'));
        $this->assertFalse($this->policy->reiterar($ctx, $movInactivoAjeno));
        $this->assertFalse($this->policy->reemplazarFirmado($ctx, $movInactivoAjeno));
    }
}