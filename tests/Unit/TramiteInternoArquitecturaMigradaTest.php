<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController;
use App\Http\Requests\mod_gene\mod_tram_inter\ListarBandejaInternaRequest;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService;
use App\Support\SessionKeys;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class TramiteInternoArquitecturaMigradaTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * CEF-09B: Verifica que CorreoInternoController::tabla use ListarBandejaInternaRequest
     * y delegue completamente en TramiteInternoRepositoryInterface sin SQL crudo en el controlador.
     */
    public function test_tabla_delega_en_repositorio_con_form_request(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $service = Mockery::mock(CorreoInternoTransaccionalService::class);

        $repo->shouldReceive('listarBandeja')
            ->once()
            ->with(
                'entrada',
                Mockery::type(ContextoUsuarioAutenticadoDto::class),
                Mockery::type('array'),
                1,
                25
            )
            ->andReturn([
                'data' => [
                    [
                        'cdgo_dtos_ntrnos' => 101,
                        'nu_tram_todo'     => 'INT-2026-00101',
                        'dc_tpo_trmte'     => 'MEMORANDO',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page'     => 25,
                    'total'        => 1,
                    'last_page'    => 1,
                    'from'         => 1,
                    'to'           => 1,
                    'has_more_pages' => false,
                ],
            ]);

        $controller = new CorreoInternoController($service, $repo, $policy);

        // Simulamos un request con sesión y parámetros
        $req = ListarBandejaInternaRequest::create('/mod_gene/mod_tram_inter/correo_interno/tabla', 'POST', [
            'bandeja'  => 'entrada',
            'page'     => 1,
            'per_page' => 25,
        ]);

        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put(SessionKeys::CARGO_ID, 30);
        $session->put(SessionKeys::AREA_ID, 5);
        $req->setLaravelSession($session);

        // Simulamos un usuario autenticado
        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador1';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $response = $controller->tabla($req);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('INT-2026-00101', $payload['data'][0]['nu_tram_todo']);
        $this->assertSame(1, $payload['meta']['total']);
    }

    /**
     * CEF-09B: Verifica que el controlador no contenga SQL interpolado ni sentencias DB::select.
     */
    public function test_controlador_no_contiene_sql_interpolado_ni_db_select(): void
    {
        $archivo = app_path('Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php');
        $this->assertFileExists($archivo);

        $contenido = (string) file_get_contents($archivo);

        $this->assertStringNotContainsString(
            'DB::select',
            $contenido,
            'CorreoInternoController no debe contener DB::select directos; debe usar el repositorio.'
        );

        $this->assertStringNotContainsString(
            '$where .=',
            $contenido,
            'CorreoInternoController no debe concatenar cláusulas WHERE como SQL plano.'
        );
    }

    /**
     * CEF-09B: Verifica que conteos delegue en el repositorio y retorne estructura esperada.
     */
    public function test_conteos_delega_en_repositorio(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $service = Mockery::mock(CorreoInternoTransaccionalService::class);

        $repo->shouldReceive('obtenerConteosBandejas')
            ->once()
            ->with(Mockery::type(ContextoUsuarioAutenticadoDto::class))
            ->andReturn([
                'entrada' => 5,
                'enviados' => 2,
                'recibidos' => 8,
                'salida' => 1,
                'finalizados' => 10,
                'sin_finalizar' => 0,
                'todos' => 26,
            ]);

        $controller = new CorreoInternoController($service, $repo, $policy);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/conteos', 'GET');
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 30);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador1';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $response = $controller->conteos($req);
        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertSame(5, $payload['data']['entrada']);
        $this->assertSame(26, $payload['data']['todos']);
    }

    /**
     * CEF-09B: Verifica que participantes delegue en el repositorio con paginación y búsqueda.
     */
    public function test_participantes_delega_en_repositorio(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $service = Mockery::mock(CorreoInternoTransaccionalService::class);

        $movDto = new \App\DTOs\mod_gene\TramiteInternoMovimientoDto(
            id: 50,
            movimientoId: 105,
            numero: 'INT-001',
            companiaId: 1,
            cargoSalidaId: 30,
            cargoEntradaId: 30,
            areaSalidaId: 5,
            areaEntradaId: 5,
            estadoId: 1
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(105, 1)
            ->andReturn($movDto);

        $policy->shouldReceive('consultarHistorico')
            ->once()
            ->with(Mockery::type(ContextoUsuarioAutenticadoDto::class), $movDto)
            ->andReturn(true);

        $repo->shouldReceive('obtenerParticipantesU')
            ->once()
            ->with(105, Mockery::type(ContextoUsuarioAutenticadoDto::class), 1, 25, 'garcia')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    [
                        'login' => 'jgarcia',
                        'nombre_corto' => 'GARCIA J',
                        'nombre_completo' => 'JUAN GARCIA',
                        'cargo' => 'ANALISTA',
                        'area' => 'SISTEMAS',
                        'rol' => 'destino',
                        'origen_datos' => 'asignacion_actual',
                    ]
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 25,
                    'total' => 1,
                    'last_page' => 1,
                    'has_more_pages' => false,
                ],
            ]);

        $controller = new CorreoInternoController($service, $repo, $policy);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', [
            'movimiento_id' => 105,
            'page' => 1,
            'per_page' => 25,
            'search' => 'garcia',
        ]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 30);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador1';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $response = $controller->participantes($req);
        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('jgarcia', $payload['data'][0]['login']);
    }
}
