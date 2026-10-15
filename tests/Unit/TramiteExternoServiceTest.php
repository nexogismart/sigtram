<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\DTOs\mod_gm\DocumentoInternoAsociadoDto;
use App\Services\mod_gm\mod_tram_exte\DocumentoInternoDesdeExpedienteExternoService;
use App\Services\mod_gm\mod_tram_exte\IdempotenciaService;
use App\Services\mod_gm\mod_tram_exte\MaquinaEstadosTramiteExterno;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class TramiteExternoServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $repository;
    private $db;
    private $builder;
    private $documentoInternoService;
    private TramiteExternoPolicy $policy;
    private MaquinaEstadosTramiteExterno $maquina;
    private IdempotenciaService $idempotencia;
    private TramiteExternoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $this->db = Mockery::mock(DatabaseManager::class);
        $this->builder = Mockery::mock(Builder::class);
        $this->documentoInternoService = Mockery::mock(DocumentoInternoDesdeExpedienteExternoService::class);

        $this->db->shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });
        $this->db->shouldReceive('table')->andReturn($this->builder);

        $this->builder->shouldReceive('where')->andReturnSelf();
        $this->builder->shouldReceive('orderBy')->andReturnSelf();
        $this->builder->shouldReceive('first')->andReturn((object) ['cdgo_crgo' => 500]);
        $this->builder->shouldReceive('update')->andReturn(1);
        $this->builder->shouldReceive('insert')->andReturn(true);
        $this->builder->shouldReceive('exists')->byDefault()->andReturn(true);

        $this->repository->shouldReceive('desactivarMovimientoExternoEsperado')->byDefault()->andReturn(1);
        $this->repository->shouldReceive('crearMovimientoExterno')->byDefault()->andReturn(1);
        $this->repository->shouldReceive('obtenerCargoPrincipalDeArea')->byDefault()->andReturn((object) ['cdgo_crgo' => 500]);
        $this->repository->shouldReceive('validarCargoEnArea')->byDefault()->andReturn(true);
        $this->repository->shouldReceive('obtenerContactoNotificacionExpediente')->byDefault()->andReturn(null);

        $this->documentoInternoService->shouldReceive('generarDocumentoInterno')
            ->byDefault()
            ->andReturn(new DocumentoInternoAsociadoDto(
                documentoInternoId: 101,
                movimientoInternoId: 201,
                numeroFormateado: 'OFICIO-001-2026',
                tipoDocumentalId: 1,
                tipoDocumentalNombre: 'OFICIO'
            ));

        $this->app->instance(DocumentoInternoDesdeExpedienteExternoService::class, $this->documentoInternoService);

        $this->maquina = new MaquinaEstadosTramiteExterno();
        $this->policy = new TramiteExternoPolicy();
        $this->idempotencia = new IdempotenciaService(\Illuminate\Support\Facades\Cache::store('array'));

        $this->service = new TramiteExternoService(
            $this->repository,
            $this->maquina,
            $this->policy,
            $this->idempotencia,
            $this->db,
            $this->documentoInternoService
        );
    }

    public function test_recepcionar_actualiza_estado_a_recibido_con_autorizacion(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $resultado = $this->service->recepcionar(
            $contexto,
            [1],
            '2026-10-10 10:00:00',
            'Observación recepción',
            'idem-rec-1'
        );

        $this->assertSame(1, $resultado['procesados']);
        $this->assertStringContainsString('recepcionaron', $resultado['mensaje']);
    }

    public function test_recepcionar_falla_si_usuario_no_es_destinatario_por_policy(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 999, 20, [], 1001); // cargo 999
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value); // destino 105

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Acceso denegado');

        $this->service->recepcionar(
            $contexto,
            [1],
            '2026-10-10 10:00:00',
            null,
            'idem-rec-fail'
        );
    }

    public function test_derivar_exitoso_genera_nuevo_movimiento_hacia_destino(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $resultado = $this->service->derivar(
            $contexto,
            [1],
            45,
            '2026-10-10 11:00:00',
            'Derivación a subgerencia',
            'idem-der-1'
        );

        $this->assertSame(1, $resultado['procesados']);
    }

    public function test_devolver_exitoso_actualiza_a_estado_devuelto_con_motivo_obligatorio(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 80, 15, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $resultado = $this->service->devolver(
            $contexto,
            [1],
            'Devolución motivada por falta de folios',
            '2026-10-10 12:00:00',
            'idem-dev-1'
        );

        $this->assertSame(1, $resultado['procesados']);
    }

    public function test_archivar_exitoso_actualiza_a_estado_archivado(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::FINALIZADO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $resultado = $this->service->archivar(
            $contexto,
            [1],
            'CAJA-05 / ARCHIVADOR-B',
            'Custodia definitiva',
            'idem-arch-1'
        );

        $this->assertSame(1, $resultado['procesados']);
    }

    public function test_prestar_y_retornar_prestamo_cumplen_ciclo_de_custodia(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimientoArchivado = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::ARCHIVADO->value);
        $movimientoPrestado = new ExpedienteMovimientoDto(1, '2026-0001', 51, 1, 105, 105, 20, 20, EstadoTramiteExterno::PRESTADO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->andReturn([$movimientoArchivado], [$movimientoPrestado]);

        $resPrestamo = $this->service->prestar(
            $contexto,
            1,
            'Dr. Carlos Ruiz',
            'Peritaje técnico',
            '2026-10-10 14:00:00',
            '2026-10-15 18:00:00',
            'idem-prest-1'
        );

        $this->assertStringContainsString('prestado', $resPrestamo['mensaje']);

        $resRetorno = $this->service->retornarPrestamo(
            $contexto,
            1,
            'Retorno de expediente sin tachas',
            'idem-ret-1'
        );

        $this->assertStringContainsString('retornado', $resRetorno['mensaje']);
    }

    public function test_redirigir_registra_nuevo_movimiento_con_cargo_corregido(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 80, 15, [1], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $resultado = $this->service->redirigir(
            $contexto,
            1,
            95,
            'Reasignación técnica por competencia',
            'idem-redir-1'
        );

        $this->assertStringContainsString('redirigido', $resultado['mensaje']);
    }

    public function test_asignar_exitoso_hacia_cargo_interno(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $resultado = $this->service->asignar(
            $contexto,
            [1],
            106,
            '2026-10-10 13:00:00',
            'Asignación a especialista técnico',
            'idem-asig-1'
        );

        $this->assertSame(1, $resultado['procesados']);
        $this->assertStringContainsString('asignaron', $resultado['mensaje']);
    }

    public function test_enviar_multi_area_procesa_todos_los_destinos(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $resultado = $this->service->enviar(
            $contexto,
            [1],
            [30, 40],
            '2026-10-10 14:00:00',
            'Envío simultáneo a dos gerencias',
            1,
            'OFICIO-001',
            5,
            null,
            'idem-env-1'
        );

        $this->assertSame(1, $resultado['procesados']);
        $this->assertSame(2, $resultado['total_destinos']);
        $this->assertStringContainsString('enviaron', $resultado['mensaje']);
    }

    public function test_finalizar_con_y_sin_notificacion(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->twice()
            ->andReturn([$movimiento]);

        // Sin correo
        $resSinCorreo = $this->service->finalizar(
            $contexto,
            [1],
            '2026-10-10 15:00:00',
            'Trámite atendido en su totalidad',
            null,
            'idem-fin-1'
        );
        $this->assertSame(1, $resSinCorreo['procesados']);

        // Con correo
        $resConCorreo = $this->service->finalizar(
            $contexto,
            [1],
            '2026-10-10 15:00:00',
            'Trámite atendido y notificado al administrado',
            'ciudadano@ejemplo.com',
            'idem-fin-2'
        );
        $this->assertSame(1, $resConCorreo['procesados']);
    }

    public function test_retornar_prestamo_rechaza_si_no_esta_en_estado_prestado(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimientoRecibido = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimientoRecibido]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no se encuentra prestado');

        $this->service->retornarPrestamo(
            $contexto,
            1,
            'Intento de retorno inválido',
            'idem-ret-fail'
        );
    }

    public function test_finalizar_utiliza_contacto_correo_h_cuando_no_se_provee_correo_explicito(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $this->repository->shouldReceive('obtenerContactoNotificacionExpediente')
            ->with(1, 1)
            ->once()
            ->andReturn([
                'correo_h' => 'titular@muni.gob.pe',
                'correo_u' => 'usuario@muni.gob.pe',
            ]);

        $res = $this->service->finalizar(
            $contexto,
            [1],
            '2026-10-10 15:00:00',
            'Atendido y resuelto',
            null,
            'idem-fin-correo-h',
            1
        );

        $this->assertSame(1, $res['procesados']);
    }

    public function test_finalizar_hace_fallback_a_correo_u_cuando_correo_h_es_nulo(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $this->repository->shouldReceive('obtenerContactoNotificacionExpediente')
            ->with(1, 1)
            ->once()
            ->andReturn([
                'correo_h' => null,
                'correo_u' => 'fallback_u@muni.gob.pe',
            ]);

        $res = $this->service->finalizar(
            $contexto,
            [1],
            '2026-10-10 15:00:00',
            'Atendido con fallback de correo',
            null,
            'idem-fin-correo-u',
            1
        );

        $this->assertSame(1, $res['procesados']);
    }

    public function test_finalizar_funciona_cuando_contacto_es_inexistente(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 105, 105, 20, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->once()
            ->andReturn([$movimiento]);

        $this->repository->shouldReceive('obtenerContactoNotificacionExpediente')
            ->with(1, 1)
            ->once()
            ->andReturn(null);

        $res = $this->service->finalizar(
            $contexto,
            [1],
            '2026-10-10 15:00:00',
            'Atendido sin contacto',
            null,
            'idem-fin-sin-contacto',
            1
        );

        $this->assertSame(1, $res['procesados']);
    }
}
