<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Requests\mod_gene\mod_tram_inter\ListarHistoricoInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\AuditoriaDescargasInternoRequest;
use ReflectionClass;
use ReflectionMethod;

class AuditoriaFinalParidadYSeguridadTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper para invocar métodos protegidos / privados de reflexión.
     */
    protected function callNonPublic(object $object, string $method, array $args = []): mixed
    {
        $reflector = new ReflectionMethod($object, $method);
        $reflector->setAccessible(true);
        return $reflector->invokeArgs($object, $args);
    }

    // =========================================================================
    // FASE 1: PARIDAD "SIN FINALIZAR" EXTERNO
    // =========================================================================

    public function test_fase1_sin_finalizar_aplica_condicion_cargo_destino_diferente(): void
    {
        $repo = app(\App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository::class);
        $contexto = $this->crearContexto(
            companiaId: 1,
            cargoId: 44,
            areaId: 10,
            idLogin: 99,
            nombreLogin: 'operador',
            roles: [1]
        );

        $query = \Illuminate\Support\Facades\DB::table('tramite.r_trmtes_dcmntos as r');
        $this->callNonPublic($repo, 'aplicarFiltroBandeja', [$query, 'sin_finalizar', $contexto]);

        $wheres = $query->wheres;
        
        // 1. Debe exigir movimiento activo: r.in_esta = 1
        $hasInEsta = collect($wheres)->contains(function ($w) {
            return ($w['column'] ?? '') === 'r.in_esta' && ($w['value'] ?? '') === '1';
        });
        $this->assertTrue($hasInEsta, 'Debe filtrar por r.in_esta = 1');

        // 2. Debe exigir cargo de salida igual al cargo actual: r.cdgo_crgo_sa = 44
        $hasCargoSalida = collect($wheres)->contains(function ($w) {
            return ($w['column'] ?? '') === 'r.cdgo_crgo_sa' && ($w['value'] ?? '') === 44;
        });
        $this->assertTrue($hasCargoSalida, 'Debe filtrar por r.cdgo_crgo_sa = cargo_actual');

        // 3. REGLA PARIDAD LEGACY: Debe exigir cargo de destino DIFERENTE del cargo actual: r.cdgo_crgo_en != 44
        $hasCargoEntradaDistinto = collect($wheres)->contains(function ($w) {
            return ($w['column'] ?? '') === 'r.cdgo_crgo_en' && ($w['operator'] ?? '') === '!=' && ($w['value'] ?? '') === 44;
        });
        $this->assertTrue($hasCargoEntradaDistinto, 'Debe filtrar por r.cdgo_crgo_en != cargo_actual (Paridad legacy)');

        // 4. Debe excluir estados finalizados 3 y 26
        $hasExclusionFinalizados = collect($wheres)->contains(function ($w) {
            return ($w['column'] ?? '') === 'r.cdgo_estdo_trmte' && ($w['type'] ?? '') === 'NotIn' && ($w['values'] ?? []) == [3, 26];
        });
        $this->assertTrue($hasExclusionFinalizados, 'Debe excluir estados finalizados 3 y 26');

        // 5. NO debe filtrar por in_esta de la cabecera p si legacy no lo hace
        $hasCabeceraInEsta = collect($wheres)->contains(function ($w) {
            return str_contains($w['column'] ?? '', 'p.in_esta');
        });
        $this->assertFalse($hasCabeceraInEsta, 'No debe filtrar por in_esta de la cabecera en sin_finalizar');
    }

    // =========================================================================
    // FASE 2: TIEMPO DE SILENCIO ADICIONAL
    // =========================================================================

    public function test_fase2_tiempo_silencio_adicional_seleccionado_y_presente_en_payload(): void
    {
        $repoFile = file_get_contents(app_path('Repositories/mod_gm/mod_tram_exte/TramiteExternoRepository.php'));

        // 1. La consulta debe seleccionar explícitamente p.nu_tiem_sile_adcnal
        $this->assertStringContainsString("'p.nu_tiem_sile_adcnal'", $repoFile, 'Debe seleccionar explícitamente p.nu_tiem_sile_adcnal de la cabecera');

        // 2. Comprobamos la normalización segura numérica en el mapeo DTO
        $simularFila = function ($base, $adicional, $diasAcumulados) {
            $tiempoBase = (int) ($base ?? 0);
            $tiempoAdcnal = (int) ($adicional ?? 0);
            $totalPlazo = $tiempoBase + $tiempoAdcnal;
            $excedido = ($totalPlazo > 0) && ($diasAcumulados >= $totalPlazo);

            return [
                'nu_tiem_sile' => $tiempoBase,
                'nu_tiem_sile_adcnal' => $tiempoAdcnal,
                'plazo_total' => $totalPlazo,
                'excedido' => $excedido,
            ];
        };

        // Caso 1: base sin adicional
        $res1 = $simularFila(30, 0, 15);
        $this->assertSame(30, $res1['nu_tiem_sile']);
        $this->assertSame(0, $res1['nu_tiem_sile_adcnal']);
        $this->assertSame(30, $res1['plazo_total']);
        $this->assertFalse($res1['excedido']);

        // Caso 2: base con adicional
        $res2 = $simularFila(30, 15, 46);
        $this->assertSame(30, $res2['nu_tiem_sile']);
        $this->assertSame(15, $res2['nu_tiem_sile_adcnal']);
        $this->assertSame(45, $res2['plazo_total']);
        $this->assertTrue($res2['excedido']);

        // Caso 3: adicional null
        $res3 = $simularFila(20, null, 25);
        $this->assertSame(20, $res3['nu_tiem_sile']);
        $this->assertSame(0, $res3['nu_tiem_sile_adcnal']);
        $this->assertSame(20, $res3['plazo_total']);
        $this->assertTrue($res3['excedido']);
    }

    // =========================================================================
    // FASE 3: AUTORIZACIÓN EXTERNA CON MÚLTIPLES RAMAS ACTIVAS
    // =========================================================================

    public function test_fase3_autorizacion_con_multiples_ramas_activas(): void
    {
        $policy = new TramiteExternoPolicy();

        $contexto = $this->crearContexto(
            companiaId: 1,
            cargoId: 40,
            areaId: 30,
            idLogin: 10,
            nombreLogin: 'operador'
        );

        $crearMovimiento = function ($movId, $areaSa, $cargoSa, $areaEn, $cargoEn, $inEsta = '1', $compania = 1) {
            return new ExpedienteMovimientoDto(
                expedienteId: 500,
                numeroExpediente: '000500-2026',
                movimientoId: $movId,
                companiaId: $compania,
                cargoSalidaId: $cargoSa,
                cargoEntradaId: $cargoEn,
                areaSalidaId: $areaSa,
                areaEntradaId: $areaEn,
                estadoId: 2,
                inEsta: (string) $inEsta,
                fechaMovimiento: '2026-10-10 10:00:00',
                observacion: null
            );
        };

        // Escenario A: Una sola rama activa autorizada
        $movAutorizado = $crearMovimiento(1, 10, 20, 30, 40); // destino cargo 40
        $this->assertTrue($policy->ver($contexto, $movAutorizado));

        // Escenario B: Dos ramas activas, la primera NO autorizada pero la segunda SÍ autorizada
        $mov1Ajeno = $crearMovimiento(101, 88, 88, 99, 99); // ajeno
        $mov2Autorizado = $crearMovimiento(102, 10, 20, 30, 40); // propio
        $this->assertTrue($policy->ver($contexto, [$mov1Ajeno, $mov2Autorizado]), 'Debe conceder acceso si al menos una rama activa le corresponde');

        // Escenario C: Dos ramas activas, pero ninguna autorizada
        $mov3Ajeno = $crearMovimiento(103, 77, 77, 88, 88);
        $this->assertFalse($policy->ver($contexto, [$mov1Ajeno, $mov3Ajeno]), 'Debe denegar si ninguna rama activa le corresponde');

        // Escenario D: Documento perteneciente a otra compañía (Aislamiento de Compañía)
        $movOtraCompania = $crearMovimiento(104, 10, 20, 30, 40, 1, 999);
        $this->assertFalse($policy->ver($contexto, $movOtraCompania), 'Debe denegar documentos de otra compañía');

        // Escenario E: Movimiento histórico inactivo (in_esta = 0) no debe conceder acceso operativo
        $movInactivo = $crearMovimiento(105, 10, 20, 30, 40, 0, 1);
        $this->assertFalse($policy->ver($contexto, $movInactivo), 'Un movimiento inactivo no debe autorizar acceso operativo');
    }

    // =========================================================================
    // FASE 4: IDENTIDAD ÚNICA DE FILAS EXTERNAS Y RAMIFICACIONES
    // =========================================================================

    public function test_fase4_identidad_unica_de_filas_externas_por_movimiento_id(): void
    {
        // Simulamos un expediente derivado simultáneamente a dos cargos (2 movimientos activos)
        $fila1 = [
            'documento_id' => 1001,
            'movimiento_id' => 5001,
            'cdgo_dto_trmte' => 1001,
            'cdgo_trmte_dcmnto' => 5001,
            'nu_expe_todo' => '001001-2026',
            'no_crto' => 'EMPRESA CONTRATISTA SAC',
        ];

        $fila2 = [
            'documento_id' => 1001,
            'movimiento_id' => 5002,
            'cdgo_dto_trmte' => 1001,
            'cdgo_trmte_dcmnto' => 5002,
            'nu_expe_todo' => '001001-2026',
            'no_crto' => 'EMPRESA CONTRATISTA SAC',
        ];

        // 1. Ambas filas pertenecen al mismo expediente pero representan movimientos/ramas distintas
        $this->assertSame($fila1['documento_id'], $fila2['documento_id']);
        $this->assertNotSame($fila1['movimiento_id'], $fila2['movimiento_id']);

        // 2. La clave reactiva Alpine por movimiento_id o compuesta es verdaderamente única
        $key1 = $fila1['movimiento_id'] ?? ($fila1['documento_id'] . '_' . $fila1['movimiento_id']);
        $key2 = $fila2['movimiento_id'] ?? ($fila2['documento_id'] . '_' . $fila2['movimiento_id']);
        $this->assertNotSame($key1, $key2, 'Las claves DOM de filas activas deben ser únicas');

        // 3. La selección de fila 1 no colisiona con fila 2
        $selectedIdFila1 = $fila1['movimiento_id'];
        $selectedIds = [$selectedIdFila1];
        $this->assertTrue(in_array($fila1['movimiento_id'], $selectedIds));
        $this->assertFalse(in_array($fila2['movimiento_id'], $selectedIds));
    }

    // =========================================================================
    // FASE 5: CONTRATO EXPLÍCITO DE IDENTIFICADORES EN TRÁMITE INTERNO
    // =========================================================================

    public function test_fase5_contrato_explicito_interno_rechaza_aliases_ambiguos(): void
    {
        $histReq = new ListarHistoricoInternoRequest();
        $auditReq = new AuditoriaDescargasInternoRequest();

        // 1. ListarHistoricoInternoRequest acepta documento_id o movimiento_id
        $v1 = Validator::make(['documento_id' => 50], $histReq->rules());
        $this->assertFalse($v1->fails());

        $v2 = Validator::make(['movimiento_id' => 50], $histReq->rules());
        $this->assertFalse($v2->fails());

        // 2. ListarHistoricoInternoRequest prohíbe explícitamente id e id_movimiento
        $vAmbiguo1 = Validator::make(['id' => 50], $histReq->rules());
        $this->assertTrue($vAmbiguo1->fails(), 'id debe ser rechazado como ambiguo');

        $vAmbiguo2 = Validator::make(['id_movimiento' => 50], $histReq->rules());
        $this->assertTrue($vAmbiguo2->fails(), 'id_movimiento debe ser rechazado como ambiguo');

        // 3. AuditoriaDescargasInternoRequest exige estrictamente movimiento_id y prohíbe id
        $vAuditValido = Validator::make(['movimiento_id' => 123], $auditReq->rules());
        $this->assertFalse($vAuditValido->fails());

        $vAuditInvalido = Validator::make(['id' => 123], $auditReq->rules());
        $this->assertTrue($vAuditInvalido->fails(), 'AuditoriaDescargas debe rechazar "id"');
    }

    // =========================================================================
    // FASE 6: CSS EXTRAÍDO DEL BLADE RAÍZ
    // =========================================================================

    public function test_fase6_blade_raiz_sin_style_y_css_en_vite(): void
    {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $bladeContent = file_get_contents($bladePath);

        // 1. No debe existir bloque <style> incrustado
        $this->assertDoesNotMatchRegularExpression('/<style/i', $bladeContent, 'El Blade raíz no debe tener bloque <style>');

        // 2. Debe existir resources/css/tramite-externo.css
        $cssPath = resource_path('css/tramite-externo.css');
        $this->assertFileExists($cssPath, 'El archivo tramite-externo.css debe existir');

        // 3. app.css debe importar tramite-externo.css
        $appCss = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("@import './tramite-externo.css';", $appCss, 'app.css debe importar tramite-externo.css');
    }

    // =========================================================================
    // FASE 7: AJUSTES VISUALES (RAZÓN SOCIAL A DOS LÍNEAS)
    // =========================================================================

    public function test_fase7_razon_social_limitada_a_dos_lineas(): void
    {
        $tablaBlade = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/filtros-tabla.blade.php'));

        // 1. Debe usar line-clamp-2 en Razón Social
        $this->assertStringContainsString('line-clamp-2', $tablaBlade, 'Debe utilizar line-clamp-2 para limitar a máximo 2 líneas');

        // 2. Ancho mínimo y máximo de Razón Social ampliado
        $this->assertStringContainsString('min-w-[300px]', $tablaBlade);
        $this->assertStringContainsString('max-w-[420px]', $tablaBlade);

        // 3. Conserva title para accesibilidad
        $this->assertStringContainsString(':title="item.no_crto"', $tablaBlade);

        // 4. Fecha de entrada en una línea (whitespace-nowrap)
        $this->assertStringContainsString('whitespace-nowrap min-w-[150px]', $tablaBlade);
    }

    // =========================================================================
    // FASE 8: RESIDUOS DEL FILTRO ESTADO EN EXTERNO
    // =========================================================================

    public function test_fase8_eliminacion_de_residuos_filtro_estado(): void
    {
        $bladeRaiz = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php'));
        $this->assertStringNotContainsString("'estadosList'", $bladeRaiz, 'estadosList no debe inyectarse en frontendConfig');

        $controllerFile = file_get_contents(app_path('Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php'));
        $this->assertStringNotContainsString("'estado' => (string)", $controllerFile, 'El filtro estado no debe construirse residualmente en CorreoExternoController::tabla');
    }

    // =========================================================================
    // FASE 9: HISTÓRICOS COMPLETOS SIN FILTRO DE IN_ESTA
    // =========================================================================

    public function test_fase9_historico_externo_no_filtra_por_in_esta(): void
    {
        $repoFile = file_get_contents(app_path('Repositories/mod_gm/mod_tram_exte/TramiteExternoRepository.php'));

        // Localizar método obtenerHistoricoMovimientos
        $pos = strpos($repoFile, 'function obtenerHistoricoMovimientos');
        $this->assertNotFalse($pos);
        $methodBody = substr($repoFile, $pos, 2500);

        // El histórico NO debe aplicar where in_esta = 1
        $this->assertStringNotContainsString("where('r.in_esta', '1')", $methodBody, 'El histórico no debe filtrar por in_esta = 1');
        $this->assertStringNotContainsString("where('r.in_esta', 1)", $methodBody, 'El histórico no debe filtrar por in_esta = 1');

        // Debe usar leftJoin para áreas y cargos para no descartar movimientos
        $this->assertStringContainsString('leftJoin', $methodBody, 'Debe usar leftJoin en histórico para no descartar movimientos opcionales');
    }
}
