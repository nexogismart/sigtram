<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Support\PublicTrackingToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeguimientoPublicoRepositorioRealTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection('sqlite')->getPdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.a_tpo_trmtes (
            cdgo_tpo_trmte INTEGER PRIMARY KEY,
            dc_tpo_trmte TEXT,
            de_tpo_trmte TEXT,
            in_esta TEXT DEFAULT '1'
        );");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.p_dto_trmtes (
            cdgo_dto_trmte INTEGER PRIMARY KEY,
            cdgo_cmpnia INTEGER,
            cdgo_tpo_trmte INTEGER,
            cdgo_usrios INTEGER,
            cdgo_area INTEGER,
            nu_expe_todo TEXT,
            fe_ingr_trmte TEXT,
            de_asno_trmte TEXT,
            de_obse_trmte TEXT,
            in_docu_trmte TEXT,
            in_esta TEXT DEFAULT '1'
        );");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.r_trmtes_dcmntos (
            cdgo_trmte_dcmnto INTEGER PRIMARY KEY AUTOINCREMENT,
            cdgo_dto_trmte INTEGER,
            fe_rcbdo_trmte TEXT,
            cdgo_crgo_en INTEGER,
            cdgo_estdo_trmte INTEGER,
            in_esta TEXT DEFAULT '1'
        );");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.a_estdos_trmtes (
            cdgo_estdo_trmte INTEGER PRIMARY KEY,
            dc_estdo_trmte TEXT,
            in_esta TEXT DEFAULT '1'
        );");

        // Datos de prueba: Catálogo de tipos
        DB::table('sistema.a_tpo_trmtes')->insertOrIgnore([
            ['cdgo_tpo_trmte' => 40, 'dc_tpo_trmte' => 'O', 'de_tpo_trmte' => 'OBSERVADO', 'in_esta' => '1'],
            ['cdgo_tpo_trmte' => 22, 'dc_tpo_trmte' => 'E', 'de_tpo_trmte' => 'EXTERNO', 'in_esta' => '1'],
        ]);

        // Compañía
        DB::table('sistema.a_cmpnias')->insertOrIgnore([
            'cdgo_cmpnia' => 1,
            'de_cmpnia' => 'MUNICIPALIDAD DISTRITAL DE PRUEBA',
            'dc_cmpnia' => 'MDP',
            'in_esta' => '1',
        ]);

        // Usuario solicitante
        DB::table('sistema.p_usrios')->insertOrIgnore([
            'cdgo_usrios' => 999,
            'no_crto' => 'JUAN PEREZ MENDOZA',
            'in_esta' => '1',
        ]);

        // Áreas y Cargos
        DB::table('sistema.a_areas')->insertOrIgnore([
            'cdgo_area' => 10,
            'de_area' => 'MESA DE PARTES CENTRAL',
            'cdgo_cmpnia' => 1,
            'in_esta' => '1',
        ]);
        DB::table('sistema.a_areas')->insertOrIgnore([
            'cdgo_area' => 20,
            'de_area' => 'SUBGERENCIA DE TRAMITE',
            'cdgo_cmpnia' => 1,
            'in_esta' => '1',
        ]);

        DB::table('sistema.p_crgos')->insertOrIgnore([
            ['cdgo_crgo' => 101, 'cdgo_area' => 10, 'de_crgo' => 'RECEPCIONISTA', 'in_esta' => '1'],
            ['cdgo_crgo' => 201, 'cdgo_area' => 20, 'de_crgo' => 'SUBGERENTE', 'in_esta' => '1'],
        ]);

        // Estados
        DB::table('sistema.a_estdos_trmtes')->insertOrIgnore([
            ['cdgo_estdo_trmte' => 1, 'dc_estdo_trmte' => 'REGISTRADO', 'in_esta' => '1'],
            ['cdgo_estdo_trmte' => 2, 'dc_estdo_trmte' => 'DERIVADO', 'in_esta' => '1'],
        ]);

        // Expediente de prueba
        DB::table('sistema.p_dto_trmtes')->insertOrIgnore([
            'cdgo_dto_trmte' => 829986,
            'cdgo_cmpnia' => 1,
            'cdgo_tpo_trmte' => 40,
            'cdgo_usrios' => 999,
            'cdgo_area' => 20,
            'nu_expe_todo' => 'O-2-2026',
            'fe_ingr_trmte' => '2026-10-14 10:10:00',
            'de_asno_trmte' => 'SOLICITUD OBSERVADA',
            'de_obse_trmte' => 'VALIDACION DOCUMENTARIA',
            'in_docu_trmte' => 'O',
            'in_esta' => '1',
        ]);

        // Movimientos históricos: Uno inactivo (in_esta = 0) y uno activo (in_esta = 1)
        DB::table('sistema.r_trmtes_dcmntos')->insertOrIgnore([
            [
                'cdgo_trmte_dcmnto' => 1001,
                'cdgo_dto_trmte' => 829986,
                'fe_rcbdo_trmte' => '2026-10-14 10:10:00',
                'cdgo_crgo_en' => 101,
                'cdgo_estdo_trmte' => 1,
                'in_esta' => '0', // Inactivo / histórico
            ],
            [
                'cdgo_trmte_dcmnto' => 1002,
                'cdgo_dto_trmte' => 829986,
                'fe_rcbdo_trmte' => '2026-10-14 10:15:00',
                'cdgo_crgo_en' => 201,
                'cdgo_estdo_trmte' => 2,
                'in_esta' => '1', // Activo
            ],
        ]);
    }

    /**
     * Test: Ejecuta obtenerSeguimientoPublico en el repositorio real (sin mocks)
     * verificando que define todos los campos obligatorios y recupera tanto in_esta = '0' como in_esta = '1'.
     */
    public function test_repositorio_real_obtener_seguimiento_publico_define_todos_los_campos(): void
    {
        /** @var IngresoExpedienteRepositoryInterface $repo */
        $repo = $this->app->make(IngresoExpedienteRepositoryInterface::class);

        $datos = $repo->obtenerSeguimientoPublico(829986);

        $this->assertNotNull($datos, 'El expediente debe ser recuperado por el repositorio.');
        $this->assertIsArray($datos);

        // Claves obligatorias definidas
        $this->assertArrayHasKey('expediente_id', $datos);
        $this->assertArrayHasKey('numero_expediente', $datos);
        $this->assertArrayHasKey('fecha_ingreso', $datos);
        $this->assertArrayHasKey('solicitante', $datos);
        $this->assertArrayHasKey('area_actual', $datos);
        $this->assertArrayHasKey('compania_nombre', $datos);
        $this->assertArrayHasKey('tipo_tramite', $datos);
        $this->assertArrayHasKey('estado_actual', $datos);
        $this->assertArrayHasKey('movimientos', $datos);

        // Verificación de contenidos no vacíos ni indefinidos
        $this->assertSame(829986, $datos['expediente_id']);
        $this->assertSame('O-2-2026', $datos['numero_expediente']);
        $this->assertSame('JUAN PEREZ MENDOZA', $datos['solicitante']);
        $this->assertSame('MUNICIPALIDAD DISTRITAL DE PRUEBA', $datos['compania_nombre']);
        $this->assertSame('SUBGERENCIA DE TRAMITE', $datos['area_actual']);
        $this->assertSame('OBSERVADO', $datos['tipo_tramite']);
        $this->assertSame('DERIVADO', $datos['estado_actual']);

        // Comprueba punto 19: El histórico incluye movimientos tanto in_esta = 0 como in_esta = 1
        $this->assertCount(2, $datos['movimientos']);
        $this->assertSame('MESA DE PARTES CENTRAL', $datos['movimientos'][0]['oficina']);
        $this->assertSame('REGISTRADO', $datos['movimientos'][0]['estado']);
        $this->assertSame('SUBGERENCIA DE TRAMITE', $datos['movimientos'][1]['oficina']);
        $this->assertSame('DERIVADO', $datos['movimientos'][1]['estado']);
    }

    /**
     * Test: Invocación de ruta HTTP con repositorio real y token firmado con QR_KEY
     * retorna HTTP 200, vista pública y headers de seguridad requeridos.
     */
    public function test_endpoint_publico_con_repositorio_real_retorna_200_y_headers(): void
    {
        $token = PublicTrackingToken::generate(829986);

        $response = $this->get('/tramite/seguimiento/' . $token);

        $response->assertStatus(200);
        $response->assertViewIs('publico.seguimiento');

        // Headers de seguridad obligatorios
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('X-Frame-Options', 'DENY');

        // Contenidos requeridos
        $response->assertSee('O-2-2026');
        $response->assertSee('JUAN PEREZ MENDOZA');
        $response->assertSee('MUNICIPALIDAD DISTRITAL DE PRUEBA');
        $response->assertSee('SUBGERENCIA DE TRAMITE');
        $response->assertSee('MESA DE PARTES CENTRAL');
        $response->assertSee('OBSERVADO');

        // Seguridad: no exponer rutas de descarga ni adjuntos
        $response->assertDontSee('/descarga');
        $response->assertDontSee('fle_adjunto');
    }

    /**
     * Test: Token adulterado con repositorio real retorna HTTP 404 uniforme.
     */
    public function test_token_adulterado_con_repositorio_real_retorna_404(): void
    {
        $token = PublicTrackingToken::generate(829986);
        $tamperedToken = substr($token, 0, -1) . 'X';

        $response = $this->get('/tramite/seguimiento/' . $tamperedToken);

        $response->assertStatus(404);
    }
}
