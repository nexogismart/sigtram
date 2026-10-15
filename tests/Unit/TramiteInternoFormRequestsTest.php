<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use App\Http\Requests\mod_gene\mod_tram_inter\RecepcionarTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\DevolverTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\DerivarTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\ListarBandejaInternaRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\DescargarLoteTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\CorrelativoTramiteInternoRequest;

class TramiteInternoFormRequestsTest extends TestCase
{
    public function test_recepcion_rechaza_array_vacio(): void
    {
        $request = new RecepcionarTramiteInternoRequest();
        $validator = Validator::make(['ids' => []], $request->rules(), $request->messages());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ids', $validator->errors()->toArray());
    }

    public function test_recepcion_rechaza_mas_de_100_ids(): void
    {
        $request = new RecepcionarTramiteInternoRequest();
        $ids = range(1, 101);
        $validator = Validator::make(['ids' => $ids], $request->rules(), $request->messages());
        $this->assertTrue($validator->fails());
    }

    public function test_recepcion_rechaza_ids_duplicados(): void
    {
        $request = new RecepcionarTramiteInternoRequest();
        $validator = Validator::make(['ids' => [5, 10, 5]], $request->rules(), $request->messages());
        $this->assertTrue($validator->fails());
    }

    public function test_devolucion_rechaza_causal_vacia_o_generica(): void
    {
        $request = new DevolverTramiteInternoRequest();
        
        // Vacia
        $v1 = Validator::make(['ids' => [1], 'observacion' => ''], $request->rules(), $request->messages());
        $this->assertTrue($v1->fails());

        // Muy corta
        $v2 = Validator::make(['ids' => [1], 'observacion' => 'abc'], $request->rules(), $request->messages());
        $this->assertTrue($v2->fails());

        // Generica ("Devolucion")
        $v3 = Validator::make(['ids' => [1], 'observacion' => 'Devolución'], $request->rules(), $request->messages());
        $this->assertTrue($v3->fails());

        // Causal especifica valida
        $v4 = Validator::make(['ids' => [1], 'observacion' => 'Falta firma del gerente responsable en foja 4'], $request->rules(), $request->messages());
        $this->assertFalse($v4->fails());
    }

    public function test_derivar_valida_destinos_y_limite_archivos(): void
    {
        $request = new DerivarTramiteInternoRequest();
        
        // Destino invalido
        $v1 = Validator::make([
            'ids' => [1],
            'id_area_destino' => 0,
            'id_cargo_destino' => -5,
        ], $request->rules(), $request->messages());
        $this->assertTrue($v1->fails());

        // Mas de 4 archivos
        $files = [
            UploadedFile::fake()->create('doc1.pdf', 100),
            UploadedFile::fake()->create('doc2.pdf', 100),
            UploadedFile::fake()->create('doc3.pdf', 100),
            UploadedFile::fake()->create('doc4.pdf', 100),
            UploadedFile::fake()->create('doc5.pdf', 100),
        ];
        $v2 = Validator::make([
            'ids' => [1],
            'id_area_destino' => 10,
            'id_cargo_destino' => 20,
            'archivos' => $files,
        ], $request->rules(), $request->messages());
        $this->assertTrue($v2->fails());
    }

    public function test_listar_bandeja_valida_rango_fechas_y_paginacion(): void
    {
        $request = new ListarBandejaInternaRequest();

        // Rango de fechas invertido
        $v1 = Validator::make([
            'fecha_inicio' => '2026-05-10',
            'fecha_fin' => '2026-05-01',
        ], $request->rules());
        $this->assertTrue($v1->fails());

        // Fecha fin valida
        $v2 = Validator::make([
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-05-10',
            'per_page' => 50,
            'page' => 2,
        ], $request->rules());
        $this->assertFalse($v2->fails());
    }

    public function test_correlativo_requiere_tipo_documento_positivo(): void
    {
        $request = new CorrelativoTramiteInternoRequest();
        
        $v1 = Validator::make(['id_tipo_documento' => 0], $request->rules());
        $this->assertTrue($v1->fails());

        $v2 = Validator::make(['id_tipo_documento' => 3, 'anio' => 2026], $request->rules());
        $this->assertFalse($v2->fails());
    }

    public function test_asignar_request_acepta_aliases_y_valida_cardinalidad(): void
    {
        $request = new \App\Http\Requests\mod_gene\mod_tram_inter\AsignarTramiteInternoRequest();

        // Falla sin cargo destino
        $v1 = Validator::make(['ids' => [1]], $request->rules());
        $this->assertTrue($v1->fails());

        // Pasa con id_cargo_destino canónico
        $v2 = Validator::make([
            'ids' => [1, 2],
            'id_cargo_destino' => 10,
            'observacion' => 'Asignado para revisión',
        ], $request->rules());
        $this->assertFalse($v2->fails());

        // Pasa con cdgo_crgo_en y observaciones (frontend alias)
        $v3 = Validator::make([
            'ids' => [1, 2],
            'cdgo_crgo_en' => 10,
            'observaciones' => 'Asignado para revisión',
        ], $request->rules());
        $this->assertFalse($v3->fails());

        // Falla con más de 100 ids
        $v4 = Validator::make([
            'ids' => range(1, 101),
            'id_cargo_destino' => 10,
        ], $request->rules());
        $this->assertTrue($v4->fails());
    }

    public function test_finalizar_y_reiterar_validan_estructura(): void
    {
        $finReq = new \App\Http\Requests\mod_gene\mod_tram_inter\FinalizarTramiteInternoRequest();
        $reitReq = new \App\Http\Requests\mod_gene\mod_tram_inter\ReiterarTramiteInternoRequest();

        $vFin = Validator::make(['ids' => [10], 'observacion' => 'Completado'], $finReq->rules());
        $this->assertFalse($vFin->fails());

        $vReit = Validator::make(['ids' => [10], 'observaciones' => 'Urgente'], $reitReq->rules());
        $this->assertFalse($vReit->fails());

        // Duplicados en IDs fallan
        $vDup = Validator::make(['ids' => [10, 10]], $finReq->rules());
        $this->assertTrue($vDup->fails());
    }

    public function test_historico_y_descarga_lote_validan_contratos(): void
    {
        $histReq = new \App\Http\Requests\mod_gene\mod_tram_inter\ListarHistoricoInternoRequest();
        $loteReq = new \App\Http\Requests\mod_gene\mod_tram_inter\DescargarLoteTramiteInternoRequest();

        // Contrato estricto: documento_id y movimiento_id son válidos
        $vDoc = Validator::make(['documento_id' => 50, 'per_page' => 15], $histReq->rules());
        $this->assertFalse($vDoc->fails());

        $vMov = Validator::make(['movimiento_id' => 50, 'per_page' => 25], $histReq->rules());
        $this->assertFalse($vMov->fails());

        // Aliases ambiguos 'id' e 'id_movimiento' deben ser estrictamente rechazados
        $vId = Validator::make(['id' => 50, 'per_page' => 15], $histReq->rules());
        $this->assertTrue($vId->fails());

        $vIdMov = Validator::make(['id_movimiento' => 50, 'per_page' => 25], $histReq->rules());
        $this->assertTrue($vIdMov->fails());

        // per_page no permitido falla
        $vHist3 = Validator::make(['documento_id' => 50, 'per_page' => 999], $histReq->rules());
        $this->assertTrue($vHist3->fails());

        // Descarga por lote
        $vLote1 = Validator::make(['ids' => [1, 2, 3], 'incluir_unido' => true], $loteReq->rules());
        $this->assertFalse($vLote1->fails());

        $vLote2 = Validator::make(['ids' => []], $loteReq->rules());
        $this->assertTrue($vLote2->fails());
    }
}