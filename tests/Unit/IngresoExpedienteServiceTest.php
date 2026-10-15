<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Models\User;
use App\Services\IngresoExpedienteService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use RuntimeException;

class IngresoExpedienteServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ingreso_expediente_service_registra_expediente_exitosamente(): void
    {
        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $user = new User();
        $user->cdgo_lgin = 10;

        $data = [
            'tipo_persona' => 'natural',
            'num_doc' => '12345678',
            'tipo_doc' => 1,
            'folios' => 5,
            'fecha_ingreso' => '2026-10-13 10:00:00',
            'observaciones' => 'Prueba de ingreso',
            'destino_modo' => 'areas',
            'gerencia' => 15,
            'subgerencia' => null,
        ];

        $mockRepo->shouldReceive('resolverSolicitante')
            ->once()
            ->with($data, 10, 2)
            ->andReturn(501);

        $mockRepo->shouldReceive('generarNumeroExpediente')
            ->once()
            ->with($data, 2)
            ->andReturn([
                'nu_expe_todo' => 'E-1-2026',
                'nu_expe_trmte' => 1,
                'cdgo_anio' => 2026,
                'cdgo_tpo_trmte' => 22,
                'in_docu_trmte' => 'E',
            ]);

        $mockRepo->shouldReceive('obtenerPrimerCargoUsuario')
            ->once()
            ->with(10)
            ->andReturn(100);

        $mockRepo->shouldReceive('obtenerPrimerCargoArea')
            ->once()
            ->with(15)
            ->andReturn(200);

        $mockRepo->shouldReceive('crearExpedienteCabecera')
            ->once()
            ->andReturn(9901);

        $mockRepo->shouldReceive('crearMovimientoInicial')
            ->once()
            ->andReturn(8801);

        $mockRepo->shouldReceive('registrarSeguimiento')
            ->once()
            ->with(10, 'Ingreso el expediente Nro E-1-2026', '127.0.0.1');

        $service = new IngresoExpedienteService($mockRepo, $mockDb);
        $resultado = $service->registrar($data, $user, 2, '127.0.0.1', null);

        $this->assertEquals(9901, $resultado['id']);
        $this->assertEquals('E-1-2026', $resultado['numero']);
    }

    public function test_ingreso_expediente_service_falla_si_auditoria_falla_fail_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fallo en auditoría de seguimiento');

        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $user = new User();
        $user->cdgo_lgin = 10;
        $user->cdgo_cmpnia = 2;

        $data = [
            'tipo_persona' => 'natural',
            'num_doc' => '12345678',
            'tipo_doc' => 1,
            'folios' => 1,
            'fecha_ingreso' => '2026-10-13 10:00:00',
            'observaciones' => 'Obs',
            'destino_modo' => 'areas',
            'gerencia' => 15,
            'subgerencia' => null,
        ];

        $mockRepo->shouldReceive('resolverSolicitante')->once()->andReturn(501);
        $mockRepo->shouldReceive('generarNumeroExpediente')->once()->andReturn([
            'nu_expe_todo' => 'E-2-2026',
            'nu_expe_trmte' => 2,
            'cdgo_anio' => 2026,
            'cdgo_tpo_trmte' => 22,
            'in_docu_trmte' => 'E',
        ]);
        $mockRepo->shouldReceive('obtenerPrimerCargoUsuario')->once()->andReturn(100);
        $mockRepo->shouldReceive('obtenerPrimerCargoArea')->once()->andReturn(200);
        $mockRepo->shouldReceive('crearExpedienteCabecera')->once()->andReturn(9902);
        $mockRepo->shouldReceive('crearMovimientoInicial')->once()->andReturn(8802);

        // La auditoría falla: debe abortar transaccionalmente (fail-closed)
        $mockRepo->shouldReceive('registrarSeguimiento')
            ->once()
            ->with(10, 'Ingreso el expediente Nro E-2-2026', '127.0.0.1')
            ->andThrow(new RuntimeException('Fallo en auditoría de seguimiento'));

        $service = new IngresoExpedienteService($mockRepo, $mockDb);
        $service->registrar($data, $user, 2, '127.0.0.1', null);
    }

    public function test_ingreso_expediente_service_falla_si_no_encuentra_cargo_destino(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se encontró un cargo de destino activo');

        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $user = new User();
        $user->cdgo_lgin = 10;
        $user->cdgo_cmpnia = 2;

        $data = [
            'tipo_persona' => 'natural',
            'num_doc' => '12345678',
            'tipo_doc' => 1,
            'folios' => 1,
            'fecha_ingreso' => '2026-10-13 10:00:00',
            'observaciones' => 'Obs',
            'destino_modo' => 'areas',
            'gerencia' => 999,
            'subgerencia' => null,
        ];

        $mockRepo->shouldReceive('resolverSolicitante')->once()->andReturn(501);
        $mockRepo->shouldReceive('generarNumeroExpediente')->once()->andReturn([
            'nu_expe_todo' => 'E-3-2026',
            'nu_expe_trmte' => 3,
            'cdgo_anio' => 2026,
            'cdgo_tpo_trmte' => 22,
            'in_docu_trmte' => 'E',
        ]);
        $mockRepo->shouldReceive('obtenerPrimerCargoUsuario')->once()->andReturn(100);
        $mockRepo->shouldReceive('obtenerPrimerCargoArea')->once()->with(999)->andReturn(null);

        $service = new IngresoExpedienteService($mockRepo, $mockDb);
        $service->registrar($data, $user, 2, '127.0.0.1', null);
    }
    public function test_ingreso_expediente_rechaza_usuario_sin_compania_valida_fail_closed(): void
    {
        $this->expectException(\App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException::class);
        $this->expectExceptionMessage('No existe una compañía activa válida en la sesión del usuario.');

        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        // Fail-closed estricto: Cero transacción, cero correlativos, cero inserciones
        $mockDb->shouldNotReceive('transaction');
        $mockRepo->shouldNotReceive('resolverSolicitante');
        $mockRepo->shouldNotReceive('generarNumeroExpediente');
        $mockRepo->shouldNotReceive('crearExpedienteCabecera');
        $mockRepo->shouldNotReceive('crearMovimientoInicial');

        $user = new User();
        $user->cdgo_lgin = 10;

        $data = [
            'tipo_persona' => 'natural',
            'num_doc' => '12345678',
            'tipo_doc' => 1,
            'folios' => 1,
            'fecha_ingreso' => '2026-10-13 10:00:00',
            'observaciones' => 'Prueba sin compañía',
            'destino_modo' => 'areas',
            'gerencia' => 15,
            'subgerencia' => null,
        ];

        $service = new IngresoExpedienteService($mockRepo, $mockDb);
        $service->registrar($data, $user, 0, '127.0.0.1', null);
    }

    public function test_ingreso_expediente_rechaza_usuario_con_compania_negativa(): void
    {
        $this->expectException(\App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException::class);
        $this->expectExceptionMessage('No existe una compañía activa válida en la sesión del usuario.');

        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldNotReceive('transaction');

        $user = new User();
        $user->cdgo_lgin = 10;
        $service = new IngresoExpedienteService($mockRepo, $mockDb);
        $service->registrar([], $user, -1, '127.0.0.1', null);
    }
}
