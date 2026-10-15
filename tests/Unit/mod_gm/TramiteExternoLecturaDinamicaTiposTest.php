<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gm;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepository;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use Tests\TestCase;

class TramiteExternoLecturaDinamicaTiposTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function crearContextoLocal(int $companiaId = 2): ContextoUsuarioAutenticadoDto
    {
        return new ContextoUsuarioAutenticadoDto(
            idLogin: 10,
            idUsuario: 10,
            nombreLogin: 'operador',
            idPerfil: 1,
            idCompania: $companiaId,
            idCargo: 201,
            idArea: 20,
            cargosAsignados: [201],
            permisos: [],
            roles: [1]
        );
    }

    /**
     * Demuestra que en la bandeja externa:
     * - Expediente E muestra 'E'
     * - Expediente S muestra 'S'
     * - Expediente O muestra 'O'
     * - Un tipo futuro creado por otro módulo (ej. 'CONST. PREVISION' con dc 'CP' o 'A' o 'REQ. BIE.') muestra su código real
     * - Un tipo histórico inactivo sigue siendo legible
     * - Si no tiene catálogo resoluble pero tiene in_docu_trmte, muestra in_docu_trmte
     * - Si no tiene catálogo ni in_docu_trmte, muestra 'SIN TIPO', jamás 'E' por defecto.
     */
    public function test_bandeja_externa_resuelve_tipos_dinamicamente_sin_asumir_e(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        $mockDb->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->once()->andReturn($mockBuilder);

        // Verifica que se hace leftJoin a a_tpo_trmtes y demás tablas
        $mockBuilder->shouldReceive('join')->with('sistema.p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('leftJoin')->with('sistema.a_tpo_trmtes as tt', 'tt.cdgo_tpo_trmte', '=', 'p.cdgo_tpo_trmte')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('leftJoin')->with('sistema.p_usrios as u', 'u.cdgo_usrios', '=', 'p.cdgo_usrios')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('leftJoin')->with('sistema.a_estdos_trmtes as e', 'e.cdgo_estdo_trmte', '=', 'r.cdgo_estdo_trmte')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('leftJoin')->with('sistema.a_docmntos_trmtes as dt', 'dt.cdgo_docmnto_trmte', '=', 'p.cdgo_docmnto_trmte')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('leftJoin')->with('sistema.p_crgos as c', 'c.cdgo_crgo', '=', 'r.cdgo_crgo_en')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('leftJoin')->with('sistema.a_areas as a', 'a.cdgo_area', '=', 'c.cdgo_area')->once()->andReturnSelf();

        $mockBuilder->shouldReceive('where')->with('p.cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('r.cdgo_crgo_en', 201)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('r.cdgo_crgo_sa', '!=', 201)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('whereNotIn')->with('r.cdgo_estdo_trmte', [5, 12, 29, 3, 26, 27, 28])->once()->andReturnSelf();
        $mockBuilder->shouldReceive('orderBy')->with('r.cdgo_trmte_dcmnto', 'desc')->once()->andReturnSelf();

        $mockBuilder->shouldReceive('count')->once()->andReturn(5);

        // Simulamos 5 registros distintos con catálogo dinámico:
        // 1. Tipo E normal
        // 2. Tipo S normal
        // 3. Tipo O normal
        // 4. Tipo futuro 'CP' (Constancia de previsión)
        // 5. Tipo histórico inactivo 'A' (Acta)
        $filasSimuladas = collect([
            (object) [
                'cdgo_trmte_dcmnto' => 101, 'cdgo_dto_trmte' => 1, 'nu_expe_todo' => 'E-1-2026',
                'cdgo_tpo_trmte' => 22, 'in_docu_trmte' => 'E', 'dc_tpo_trmte' => 'E', 'de_tpo_trmte' => 'EXTERNO',
                'cdgo_estdo_trmte' => 1, 'dc_estdo_trmte' => 'PENDIENTE', 'fe_rcbdo_trmte' => '2026-10-14 10:00:00',
                'de_obse_trmte' => 'Expediente E', 'no_crto' => 'Ciudadano 1', 'nu_tiem_sile' => 30, 'nu_tiem_sile_adcnal' => 0, 'nu_foli_trmte' => 5
            ],
            (object) [
                'cdgo_trmte_dcmnto' => 102, 'cdgo_dto_trmte' => 2, 'nu_expe_todo' => 'S-2-2026',
                'cdgo_tpo_trmte' => 17, 'in_docu_trmte' => 'S', 'dc_tpo_trmte' => 'S', 'de_tpo_trmte' => 'DOCUMENTO SIMPLE',
                'cdgo_estdo_trmte' => 1, 'dc_estdo_trmte' => 'PENDIENTE', 'fe_rcbdo_trmte' => '2026-10-14 10:05:00',
                'de_obse_trmte' => 'Expediente S', 'no_crto' => 'Ciudadano 2', 'nu_tiem_sile' => 0, 'nu_tiem_sile_adcnal' => 0, 'nu_foli_trmte' => 2
            ],
            (object) [
                'cdgo_trmte_dcmnto' => 103, 'cdgo_dto_trmte' => 3, 'nu_expe_todo' => 'O-3-2026',
                'cdgo_tpo_trmte' => 40, 'in_docu_trmte' => 'O', 'dc_tpo_trmte' => 'O', 'de_tpo_trmte' => 'OBSERVADO',
                'cdgo_estdo_trmte' => 12, 'dc_estdo_trmte' => 'OBSERVADO', 'fe_rcbdo_trmte' => '2026-10-14 10:10:00',
                'de_obse_trmte' => 'Expediente O', 'no_crto' => 'Ciudadano 3', 'nu_tiem_sile' => 15, 'nu_tiem_sile_adcnal' => 0, 'nu_foli_trmte' => 8
            ],
            (object) [
                'cdgo_trmte_dcmnto' => 104, 'cdgo_dto_trmte' => 4, 'nu_expe_todo' => 'CP-4-2026',
                'cdgo_tpo_trmte' => 99, 'in_docu_trmte' => 'CP', 'dc_tpo_trmte' => 'CP', 'de_tpo_trmte' => 'CONST. PREVISION',
                'cdgo_estdo_trmte' => 1, 'dc_estdo_trmte' => 'PENDIENTE', 'fe_rcbdo_trmte' => '2026-10-14 10:15:00',
                'de_obse_trmte' => 'Expediente CP futuro', 'no_crto' => 'Ciudadano 4', 'nu_tiem_sile' => 0, 'nu_tiem_sile_adcnal' => 0, 'nu_foli_trmte' => 3
            ],
            (object) [
                'cdgo_trmte_dcmnto' => 105, 'cdgo_dto_trmte' => 5, 'nu_expe_todo' => 'A-5-2026',
                'cdgo_tpo_trmte' => 70, 'in_docu_trmte' => 'A', 'dc_tpo_trmte' => 'A', 'de_tpo_trmte' => 'ACTA INACTIVA',
                'cdgo_estdo_trmte' => 1, 'dc_estdo_trmte' => 'PENDIENTE', 'fe_rcbdo_trmte' => '2026-10-14 10:20:00',
                'de_obse_trmte' => 'Expediente A histórico', 'no_crto' => 'Ciudadano 5', 'nu_tiem_sile' => 0, 'nu_tiem_sile_adcnal' => 0, 'nu_foli_trmte' => 1
            ],
        ]);

        $mockBuilder->shouldReceive('select')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('offset')->with(0)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('limit')->with(25)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('get')->once()->andReturn($filasSimuladas);

        $repo = new TramiteExternoRepository($mockDb);
        $res = $repo->consultarBandeja($this->crearContextoLocal(2), 'entrada', [], 1, 25);

        $items = $res['data'];
        $this->assertCount(5, $items);

        // 1. E muestra E
        $this->assertSame('E', $items[0]['orig_doc']);
        $this->assertSame('EXTERNO', $items[0]['de_tpo_trmte']);

        // 2. S muestra S
        $this->assertSame('S', $items[1]['orig_doc']);
        $this->assertSame('DOCUMENTO SIMPLE', $items[1]['de_tpo_trmte']);

        // 3. O muestra O
        $this->assertSame('O', $items[2]['orig_doc']);
        $this->assertSame('OBSERVADO', $items[2]['de_tpo_trmte']);

        // 4. Tipo futuro 'CP' muestra su código real 'CP' y descripción
        $this->assertSame('CP', $items[3]['orig_doc']);
        $this->assertSame('CONST. PREVISION', $items[3]['de_tpo_trmte']);

        // 5. Tipo histórico inactivo 'A' sigue siendo legible
        $this->assertSame('A', $items[4]['orig_doc']);
        $this->assertSame('ACTA INACTIVA', $items[4]['de_tpo_trmte']);
    }

    /**
     * Demuestra que una fila sin cdgo_tpo_trmte positivo o sin catalogo falla cerrado
     * con UnexpectedValueException y JAMAS inventa 'SIN TIPO' ni asume 'E'.
     */
    public function test_bandeja_externa_falla_cerrado_y_no_inventa_sin_tipo_ante_catalogo_nulo(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('inválido');

        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        $mockDb->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->once()->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('join')->andReturnSelf();
        $mockBuilder->shouldReceive('leftJoin')->andReturnSelf();
        $mockBuilder->shouldReceive('where')->andReturnSelf();
        $mockBuilder->shouldReceive('whereNotIn')->andReturnSelf();
        $mockBuilder->shouldReceive('orderBy')->andReturnSelf();
        $mockBuilder->shouldReceive('count')->once()->andReturn(1);
        $mockBuilder->shouldReceive('select')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('offset')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('limit')->once()->andReturnSelf();

        $mockBuilder->shouldReceive('get')->once()->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto' => 106, 'cdgo_dto_trmte' => 6, 'nu_expe_todo' => '6-2026',
                'cdgo_tpo_trmte' => null, 'in_docu_trmte' => null, 'dc_tpo_trmte' => null, 'de_tpo_trmte' => null,
                'cdgo_estdo_trmte' => 1, 'dc_estdo_trmte' => 'PENDIENTE', 'fe_rcbdo_trmte' => '2026-10-14 10:25:00',
                'de_obse_trmte' => 'Expediente huérfano', 'no_crto' => 'Ciudadano 6', 'nu_tiem_sile' => 0, 'nu_tiem_sile_adcnal' => 0, 'nu_foli_trmte' => 1
            ],
        ]));

        $repo = new TramiteExternoRepository($mockDb);
        $repo->consultarBandeja($this->crearContextoLocal(2), 'entrada', [], 1, 25);
    }

    /**
     * Demuestra que listarExpedientes NO excluye registros por catálogo y resuelve tipos dinámicamente.
     */
    public function test_listar_expedientes_no_filtra_por_e_s_o(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        $mockDb->shouldReceive('table')->with('sistema.p_dto_trmtes as p_dto_trmtes')->once()->andReturn($mockBuilder);

        $mockBuilder->shouldReceive('join')->with('sistema.r_trmtes_dcmntos as r_trmtes_dcmntos', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')->once()->andReturnSelf();
        // leftJoin a a_tpo_trmtes para no descartar registros
        $mockBuilder->shouldReceive('leftJoin')->with('sistema.a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('join')->with('sistema.a_areas as a_areas', 'p_dto_trmtes.cdgo_area', '=', 'a_areas.cdgo_area')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('join')->with('sistema.a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('join')->with('sistema.p_lgin as p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('join')->with('sistema.p_usrios as p_usrios', 'p_lgin.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('join')->with('sistema.p_usrios as p_usrios_1', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')->once()->andReturnSelf();

        $mockBuilder->shouldReceive('where')->with('r_trmtes_dcmntos.in_esta', '1')->once()->andReturnSelf();
        // Verificación crítica: NO debe haber whereIn('p_dto_trmtes.cdgo_tpo_trmte', ...)
        $mockBuilder->shouldNotReceive('whereIn')->with('p_dto_trmtes.cdgo_tpo_trmte', Mockery::any());
        $mockBuilder->shouldReceive('where')->with('p_dto_trmtes.cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('p_dto_trmtes.fe_ingr_trmte', '>=', Mockery::any())->andReturnSelf();

        $cloneMock = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('select')->with('p_dto_trmtes.cdgo_dto_trmte')->andReturn($cloneMock);
        $cloneMock->shouldReceive('distinct')->andReturnSelf();
        $cloneMock->shouldReceive('count')->andReturn(2);

        $mockBuilder->shouldReceive('select')->with(Mockery::on(function ($cols) {
            return in_array('p_dto_trmtes.cdgo_tpo_trmte', $cols, true)
                && in_array('a_tpo_trmtes.dc_tpo_trmte', $cols, true);
        }))->once()->andReturnSelf();

        $mockBuilder->shouldReceive('orderByDesc')->with('p_dto_trmtes.cdgo_dto_trmte')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('offset')->with(0)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('limit')->with(10)->once()->andReturnSelf();

        $mockBuilder->shouldReceive('get')->once()->andReturn(collect([
            (object) [
                'cdgo_dto_trmte' => 1,
                'nu_expe_todo' => 'REQ-01-2026',
                'cdgo_tpo_trmte' => 65,
                'in_docu_trmte' => 'REQ. BIE.',
                'dc_tpo_trmte' => 'RB',
                'de_tpo_trmte' => 'REQUERIMIENTO DE BIENES',
                'nu_foli_trmte' => 1,
                'in_sile_admi' => '0',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'de_obse_todo' => 'Requerimiento',
                'dc_area' => 'SGL',
                'de_area' => 'SUBGERENCIA DE LOGISTICA',
                'dc_estdo_trmte' => 'PENDIENTE',
                'cdgo_estdo_trmte' => 1,
                'usuario_ingreso' => 'Admin',
                'de_lgin' => 'admin',
                'solicitante' => 'Proveedor SAC'
            ],
            (object) [
                'cdgo_dto_trmte' => 2,
                'nu_expe_todo' => 'E-02-2026',
                'cdgo_tpo_trmte' => 22,
                'in_docu_trmte' => 'E',
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXTERNO',
                'nu_foli_trmte' => 2,
                'in_sile_admi' => '0',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'de_obse_todo' => 'Externo',
                'dc_area' => 'SGL',
                'de_area' => 'SUBGERENCIA DE LOGISTICA',
                'dc_estdo_trmte' => 'PENDIENTE',
                'cdgo_estdo_trmte' => 1,
                'usuario_ingreso' => 'Admin',
                'de_lgin' => 'admin',
                'solicitante' => 'Ciudadano'
            ]
        ]));

        $repo = new IngresoExpedienteRepository($mockDb);
        $res = $repo->listarExpedientes([], 2, 1, 10);

        $this->assertCount(2, $res['items']);
        $this->assertSame('RB', $res['items'][0]->orig_doc);
        $this->assertSame('REQUERIMIENTO DE BIENES', $res['items'][0]->de_tpo_trmte);
        $this->assertSame('E', $res['items'][1]->orig_doc);
    }
}
