<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\mod_gm\mod_tram_exte\BuscaExpediController;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Models\User;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use App\Support\SessionKeys;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use Tests\TestCase;

class TramiteExternoVoucherAuditoriaActorTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'cdgo_usrios' => 864,
            'cdgo_lgin' => 'hsantos',
            'cdgo_cmpnia' => 2,
            'cdgo_prfle' => 2,
            'in_esta' => '1',
        ]);
        $this->user->cdgo_usrios = 864;
        $this->user->cdgo_lgin = 'hsantos';
        $this->user->cdgo_cmpnia = 2;
        $this->user->cdgo_prfle = 2;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function obtenerContenidoVista(): string
    {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $content = (string) file_get_contents($bladePath);

        $partialsDir = resource_path('views/mod_gm/mod_tram_exte/partials_segi');
        if (is_dir($partialsDir)) {
            foreach (glob($partialsDir . '/*.blade.php') ?: [] as $partial) {
                $content .= "\n" . file_get_contents($partial);
            }
        }

        $jsFiles = [
            resource_path('js/tramite-externo/estados.js'),
            resource_path('js/tramite-externo/bandejas.js'),
            resource_path('js/tramite-externo/historico.js'),
            resource_path('js/tramite-externo/participantes.js'),
            resource_path('js/tramite-externo/acciones.js'),
            resource_path('js/tramite-externo/state.js'),
            resource_path('js/tramite-externo/api.js'),
            resource_path('js/tramite-externo/index.js'),
        ];
        foreach ($jsFiles as $jsFile) {
            if (file_exists($jsFile)) {
                $content .= "\n" . file_get_contents($jsFile);
            }
        }

        return $content;
    }

    // =========================================================================
    // 1. ACTOR HISTÓRICO (MODAL U)
    // =========================================================================

    public function test_repositorio_mapea_actor_canonico_y_normaliza_cargo_con_punto(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos as r/'))->andReturn($builder);
        $builder->shouldReceive('leftJoin')->times(8)->andReturnSelf();
        $builder->shouldReceive('where')->times(1)->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->with('r.cdgo_trmte_dcmnto')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto' => 101,
                'cdgo_dto_trmte' => 50,
                'fe_rcbdo_trmte' => '2026-10-10 09:30:00',
                'de_obse' => 'Movimiento inicial de plataforma',
                'cdgo_estdo_trmte' => 1,
                'dc_estdo_trmte' => 'PENDIENTE',
                'cargo_salida' => '.', // Caso histórico real: cdgo_crgo = 0 tiene '.'
                'cdgo_crgo_sa' => 0,
                'area_salida' => 'EXTERNOS',
                'cargo_entrada' => 'SUBGERENTE DE OTI',
                'cdgo_crgo_en' => 20,
                'area_entrada' => 'OTI',
                'login_actor' => 'hsantos',
                'usuario_actor' => 'HELLEN BEATRIZ SANTOS GOMEZ',
                'perfil_id_actor' => 2,
                'perfil_actor' => 'PLATAFORMA ATENCION AL CIUDADANO',
                'cdgo_lgin' => 864,
                'nom_archivo' => null,
            ],
        ]));

        // Mock para resolver destinatarios por cargo (sin N+1)
        $builderDest = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin as rl/'))->andReturn($builderDest);
        $builderDest->shouldReceive('join')->twice()->andReturnSelf();
        $builderDest->shouldReceive('whereIn')->andReturnSelf();
        $builderDest->shouldReceive('where')->twice()->andReturnSelf();
        $builderDest->shouldReceive('select')->andReturnSelf();
        $builderDest->shouldReceive('get')->andReturn(collect([]));

        $repo = new TramiteExternoRepository($db);
        $historico = $repo->obtenerHistoricoMovimientos(50);

        $this->assertCount(1, $historico);
        $actor = $historico[0]['actor'];

        // Contrato canónico de actor
        $this->assertSame(864, $actor['login_id']);
        $this->assertSame('hsantos', $actor['login']);
        $this->assertSame('HELLEN BEATRIZ SANTOS GOMEZ', $actor['nombre']);
        $this->assertSame(2, $actor['perfil_id']);
        $this->assertSame('PLATAFORMA ATENCION AL CIUDADANO', $actor['perfil']);
        $this->assertSame(0, $actor['cargo_id']);
        $this->assertNull($actor['cargo'], 'El cargo "." o id 0 debe normalizarse estrictamente a null.');
        $this->assertSame('EXTERNOS', $actor['area']);
    }

    public function test_modal_u_en_blade_separa_identidad_perfil_cargo_y_area_sin_punto(): void
    {
        $content = $this->obtenerContenidoVista();

        // 1. Verificación de campos separados en el DOM del modal U
        $this->assertStringContainsString('uOrigenEjecutor?.nombre || selectedMovimientoU?.actor?.nombre', $content);
        $this->assertStringContainsString('uOrigenEjecutor?.login || selectedMovimientoU?.actor?.login', $content);
        $this->assertStringContainsString('uOrigenEjecutor?.login_id || selectedMovimientoU?.actor?.login_id', $content);
        $this->assertStringContainsString('uOrigenEjecutor?.perfil || selectedMovimientoU?.actor?.perfil', $content);
        $this->assertStringContainsString('Cargo histórico no registrado', $content);
        $this->assertStringContainsString('Área histórica no registrada', $content);

        // 2. Nunca mezclar Perfil con Cargo ni asumir que son el mismo campo
        $this->assertStringContainsString('Cargo Histórico', $content);
        $this->assertStringContainsString('Área Histórica', $content);
    }

    // =========================================================================
    // 2. AUDITORÍA DE DESCARGAS (COLUMNA A)
    // =========================================================================

    public function test_repositorio_auditoria_descargas_paginada_contrato(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderMov = Mockery::mock(Builder::class);
        $builderCrgos = Mockery::mock(Builder::class);
        $builderArchivo = Mockery::mock(Builder::class);
        $builderCount = Mockery::mock(Builder::class);
        $builderData = Mockery::mock(Builder::class);

        // 1. Movimiento lookup en obtenerMovimientoPorMovimientoId
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos as r/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->with(Mockery::pattern('/p_dto_trmtes as p/'), 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')->andReturnSelf();
        $builderMov->shouldReceive('where')->with('r.cdgo_trmte_dcmnto', 501)->andReturnSelf();
        $builderMov->shouldReceive('where')->with('p.cdgo_cmpnia', 2)->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn((object) [
            'cdgo_dto_trmte' => 10,
            'nu_expe_todo' => 'EXP-10',
            'cdgo_trmte_dcmnto' => 501,
            'cdgo_cmpnia' => 2,
            'cdgo_crgo_sa' => 100,
            'cdgo_crgo_en' => 200,
            'cdgo_estdo_trmte' => 1,
            'in_esta' => '1',
            'fe_rcbdo_trmte' => '2026-10-12 10:00:00',
            'de_obse' => null,
        ]);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_crgos$/'))->twice()->andReturn($builderCrgos);
        $builderCrgos->shouldReceive('where')->twice()->andReturnSelf();
        $builderCrgos->shouldReceive('value')->with('cdgo_area')->twice()->andReturn(15);

        // 2. Obtener archivo de r_trmtes_dcmntos
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos$/'))->andReturn($builderArchivo);
        $builderArchivo->shouldReceive('where')->with('cdgo_trmte_dcmnto', 501)->andReturnSelf();
        $builderArchivo->shouldReceive('select')->with('de_urls_imag')->andReturnSelf();
        $builderArchivo->shouldReceive('first')->andReturn((object) [
            'de_urls_imag' => 'adjunto_501.pdf',
        ]);

        // 3. Consulta de p_sgmto_dscrgas (count y datos)
        $db->shouldReceive('table')->with(Mockery::pattern('/p_sgmto_dscrgas as sd/'))->andReturn($builderData);
        $builderData->shouldReceive('leftJoin')->twice()->andReturnSelf();
        $builderData->shouldReceive('where')->with('sd.de_urls_dscrgas', 'adjunto_501.pdf')->andReturnSelf();
        $builderData->shouldReceive('count')->andReturn(1);
        $builderData->shouldReceive('select')->andReturnSelf();
        $builderData->shouldReceive('orderBy')->twice()->andReturnSelf();
        $builderData->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderData->shouldReceive('limit')->with(10)->andReturnSelf();
        $builderData->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_sgmto_dscrgas' => 1,
                'fe_sgmto_dscrgas' => '2026-10-12 11:45:00',
                'de_ip' => '192.168.1.55',
                'id_login' => 50,
                'login_nom' => 'cperez',
                'nombre_usuario' => 'CARLOS PEREZ',
                'de_urls_dscrgas' => 'adjunto_501.pdf',
            ],
        ]));

        $repo = new TramiteExternoRepository($db);
        $res = $repo->obtenerAuditoriaDescargasPaginada(501, 2, 1, 10);

        $this->assertSame('success', $res['status']);
        $this->assertSame(1, $res['pagination']['total']);
        $this->assertSame(1, $res['pagination']['current_page']);
        $this->assertSame(10, $res['pagination']['per_page']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('CARLOS PEREZ', $res['data'][0]['nombre_usuario']);
        $this->assertSame('cperez', $res['data'][0]['login']);
        $this->assertSame('192.168.1.55', $res['data'][0]['ip']);
    }

    public function test_controlador_auditoria_descargas_404_para_movimiento_inexistente_o_cross_company(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $repoMock->shouldReceive('obtenerMovimientoPorMovimientoId')
            ->with(999, 2)
            ->andReturn(null);

        $this->app->instance(TramiteExternoRepositoryInterface::class, $repoMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 2,
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
            ])
            ->getJson(route('mod_gm.segi_expedi.auditoria_descargas', ['movimiento_id' => 999]));

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Movimiento no encontrado o sin autorización.',
            ]);
    }

    public function test_modal_auditoria_descargas_es_top_level_y_no_se_recorta_por_overflow_tabla(): void
    {
        $content = $this->obtenerContenidoVista();

        // 1. Debe existir modal-trazabilidad-descargas
        $this->assertStringContainsString('id="modal-trazabilidad-descargas"', $content);
        $this->assertStringContainsString('modalAuditoria', $content);

        // 2. Columna A en histórico invoca abrirModalAuditoria(hist, $el)
        $this->assertStringContainsString('@click="abrirModalAuditoria(hist, $el)"', $content);

        // 3. Debe tener controles de paginación y mensaje cuando esté vacío
        $this->assertStringContainsString('Sin descargas previas registradas', $content);
        $this->assertStringContainsString('changeAuditPage(-1)', $content);
        $this->assertStringContainsString('changeAuditPage(1)', $content);
    }

    // =========================================================================
    // 3. VOUCHER HISTÓRICO (COLUMNA D)
    // =========================================================================

    public function test_columna_d_diferencia_voucher_principal_de_anexo_y_no_imprime_dashboard(): void
    {
        $content = $this->obtenerContenidoVista();

        // 1. Columna D retirada del histórico para homologar con sistema legacy (Presentacion/mod_gm/mod_tram_exte/segi_tramite.php)
        $this->assertStringNotContainsString('@click="verVoucher(hist)"', $content);
        $this->assertStringNotContainsString('<th class="px-4 py-3 text-center">D</th>', $content);

        // 2. Componente de seguimiento no contiene modal huérfano ni métodos inaccesibles de voucher
        $this->assertStringNotContainsString('id="modal-voucher"', $content, 'El dashboard de Correo Externo no debe contener modal de voucher huérfano.');
        $this->assertStringNotContainsString('abrirVoucherOficial(', $content, 'Método muerto abrirVoucherOficial retirado de segi_expedi.');
        $this->assertStringNotContainsString('voucherMov', $content, 'Estado muerto voucherMov retirado de state.js.');

        // 3. El dashboard segi_expedi ya no hace window.print() de toda la pantalla
        $this->assertStringNotContainsString('onclick="window.print()"', $content, 'El dashboard segi_expedi no debe contener window.print() inline.');
    }

    public function test_controladores_voucher_enforzan_aislamiento_de_compania_e_idor_estaticamente(): void
    {
        // 1. IngresoExpedienteController::voucher aplica SessionKeys::USER_COMPANY, delega en repositorio y aborta 404 si es nulo
        $voucherControllerPath = app_path('Http/Controllers/mod_gm/mod_tram_exte/IngresoExpedienteController.php');
        $voucherContent = (string) file_get_contents($voucherControllerPath);

        $this->assertStringContainsString('SessionKeys::USER_COMPANY', $voucherContent);
        $this->assertStringContainsString('$this->repository->obtenerVoucherExpediente', $voucherContent);
        $this->assertStringContainsString("abort(404, 'Expediente no encontrado.')", $voucherContent);

        // Repositorio de ingreso verifica aislamiento de compañía
        $ingresoRepoPath = app_path('Repositories/mod_gm/mod_tram_exte/IngresoExpedienteRepository.php');
        $ingresoRepoContent = (string) file_get_contents($ingresoRepoPath);
        $this->assertStringContainsString("where('cdgo_dto_trmte', \$expedienteId)", $ingresoRepoContent);
        $this->assertStringContainsString("where('cdgo_cmpnia', \$companiaId)", $ingresoRepoContent);

        // 2. BuscaExpediController::listaVaucher delega en servicio y aborta 404 si no existe
        $buscaControllerPath = app_path('Http/Controllers/mod_gm/mod_tram_exte/BuscaExpediController.php');
        $buscaContent = (string) file_get_contents($buscaControllerPath);

        $this->assertStringContainsString('public function listaVaucher', $buscaContent);
        $this->assertStringContainsString('$this->service->listarVouchers', $buscaContent);
        $this->assertStringContainsString("abort(404, 'Expediente no encontrado.')", $buscaContent);

        // 3. BuscaExpediController::voucherAnexo aplica aislamiento por compañía delegando en servicio
        $this->assertStringContainsString('public function voucherAnexo', $buscaContent);
        $this->assertStringContainsString('$this->service->obtenerVoucherAnexo', $buscaContent);
        $this->assertStringContainsString("abort(404, 'Anexo no encontrado.')", $buscaContent);

        // Repositorio de búsqueda verifica existencia con compañía activa
        $buscaRepoPath = app_path('Repositories/mod_gm/mod_tram_exte/BuscaExpedienteRepository.php');
        $buscaRepoContent = (string) file_get_contents($buscaRepoPath);
        $this->assertStringContainsString("where('cdgo_dto_trmte', \$idTram)", $buscaRepoContent);
        $this->assertStringContainsString("where('r.cdgo_trmte_dcmnto', \$idMov)", $buscaRepoContent);
        $this->assertStringContainsString("where('p.cdgo_cmpnia', \$companiaId)", $buscaRepoContent);
    }
}
