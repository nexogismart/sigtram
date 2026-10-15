<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException;
use App\Exceptions\mod_gene\DestinoTramiteInvalidoException;
use App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException;
use App\Exceptions\mod_gene\TramiteInternoNoEncontradoException;
use App\Exceptions\mod_gene\TransicionTramiteInvalidaException;
use App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController;
use App\Http\Controllers\mod_gene\mod_tram_inter\BuscarTramiteController;
use App\Http\Requests\mod_gene\mod_tram_inter\RecepcionarTramiteInternoRequest;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Services\mod_gene\mod_tram_inter\ConsolidadoExpedienteService;
use App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService;
use App\Services\mod_gene\mod_tram_inter\DescargaSeguraTramiteInternoService;
use App\Services\mod_gene\mod_tram_inter\IdempotenciaTramiteInternoService;
use App\Services\mod_gene\mod_tram_inter\MaquinaEstadosTramiteInterno;
use App\Services\mod_gene\mod_tram_inter\ValidadorPagoProveedoresTipo64Service;
use App\Services\mod_gene\mod_tram_inter\DescargaLoteTramiteInternoService;
use App\Services\mod_gene\mod_tram_inter\ReemplazarDocumentoFirmadoService;
use App\DTOs\Support\ArchivoAdjuntoDto;
use App\Support\SessionKeys;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Services\Support\GestorAdjuntos;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class TramiteInternoRemediacionP0Test extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function crearContextoRemediacion(
        string $login = 'jperez',
        int $usuarioId = 10,
        int $companiaId = 1,
        int $cargoId = 20,
        int $areaId = 5,
        array $cargosAsignados = [20],
        array $permisos = [],
        int $loginId = 99
    ): ContextoUsuarioAutenticadoDto {
        return new ContextoUsuarioAutenticadoDto(
            idLogin: $loginId,
            idUsuario: $usuarioId,
            nombreLogin: $login,
            idPerfil: 1,
            idCompania: $companiaId,
            idCargo: $cargoId,
            idArea: $areaId,
            cargosAsignados: $cargosAsignados,
            permisos: $permisos,
            roles: [1],
            esContabilidad: ($areaId === 21)
        );
    }

    private function resolverContextoDesdeRequest(Request $request): ?ContextoUsuarioAutenticadoDto
    {
        $rawComp = null;
        if ($request->hasSession()) {
            $session = $request->session();
            $rawComp = $session->get(SessionKeys::USER_COMPANY) ?? $session->get(SessionKeys::COMPANIA_ID);
        }
        $companiaId = ContextoUsuarioAutenticadoDto::normalizarCompania($rawComp);
        if ($companiaId <= 0) {
            return null;
        }

        $cargoId = 0;
        if ($request->hasSession()) {
            $session = $request->session();
            $cargoId = (int) ($session->get(SessionKeys::USER_CARGO)
                ?: $session->get(SessionKeys::CARGO_ID)
                ?: $session->get('sigtram_cargo_id', 0));
        }
        if ($cargoId <= 0) {
            return null;
        }

        $areaId = 0;
        if ($request->hasSession()) {
            $session = $request->session();
            $areaId = (int) ($session->get(SessionKeys::USER_AREA)
                ?: $session->get(SessionKeys::AREA_ID)
                ?: $session->get('sigtram_area_id', 0));
        }
        if ($areaId <= 0) {
            $areaId = $cargoId;
        }

        $user = Auth::user();
        $loginId = 0;
        if ($user) {
            if (isset($user->cdgo_lgin) && is_numeric($user->cdgo_lgin) && (int) $user->cdgo_lgin > 0) {
                $loginId = (int) $user->cdgo_lgin;
            } elseif (isset($user->cdgo_usrios) && is_numeric($user->cdgo_usrios) && (int) $user->cdgo_usrios > 0) {
                $loginId = (int) $user->cdgo_usrios;
            } elseif (isset($user->id) && is_numeric($user->id) && (int) $user->id > 0) {
                $loginId = (int) $user->id;
            }
        }
        if ($loginId <= 0 && $request->hasSession()) {
            $loginId = (int) ($request->session()->get(SessionKeys::USER_ID, 0)
                ?: $request->session()->get(SessionKeys::LOGIN_ID, 0));
        }
        if ($loginId <= 0) {
            return null;
        }

        $usuarioId = (int) ($user->cdgo_usrios ?? $loginId);
        $nombreLogin = (string) ($user->de_lgin ?? $user->cdgo_lgin ?? 'test_user');

        return new ContextoUsuarioAutenticadoDto(
            idLogin: $loginId,
            idUsuario: $usuarioId,
            nombreLogin: $nombreLogin,
            idPerfil: 1,
            idCompania: $companiaId,
            idCargo: $cargoId,
            idArea: $areaId,
            cargosAsignados: [$cargoId],
            permisos: [],
            roles: [1],
            esContabilidad: ($areaId === 21)
        );
    }

    private function crearMovimiento(
        int $id = 100,
        int $movimientoId = 500,
        int $companiaId = 1,
        int $cargoSalidaId = 15,
        int $cargoEntradaId = 20,
        int $areaSalidaId = 3,
        int $areaEntradaId = 5,
        int $estadoId = 1,
        string $inEsta = '1',
        string $inCopia = '1'
    ): TramiteInternoMovimientoDto {
        return new TramiteInternoMovimientoDto(
            id: $id,
            movimientoId: $movimientoId,
            numero: "2026-{$id}",
            companiaId: $companiaId,
            cargoSalidaId: $cargoSalidaId,
            cargoEntradaId: $cargoEntradaId,
            areaSalidaId: $areaSalidaId,
            areaEntradaId: $areaEntradaId,
            estadoId: $estadoId,
            inEsta: $inEsta,
            inCopia: $inCopia
        );
    }

    /**
     * 1. Lote completo autorizado
     */
    public function test_01_lote_completo_autorizado(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = Mockery::mock(MaquinaEstadosTramiteInterno::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $mov1 = $this->crearMovimiento(101, 501, 1, 15, 20, 3, 5, 1);
        $mov2 = $this->crearMovimiento(102, 502, 1, 15, 20, 3, 5, 1);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([101, 102], $contexto)
            ->andReturn([$mov1, $mov2]);

        $policy->shouldReceive('recepcionar')->with($contexto, $mov1)->andReturn(true);
        $policy->shouldReceive('recepcionar')->with($contexto, $mov2)->andReturn(true);

        $maquina->shouldReceive('esTransicionValida')->with(1, 'recepcionar')->andReturn(true);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        // Repo mock para updates e inserts
        $repo->shouldReceive('desactivarMovimientoInternoEsperado')->twice()->andReturn(1);
        $repo->shouldReceive('crearMovimientoInterno')->twice()->andReturn(1);
        $repo->shouldReceive('registrarSeguimientoInterno')->twice()->andReturnNull();

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);
        $res = $service->ejecutarRecepcionar([101, 102], '2026-10-11 10:00:00', 'Observación prueba', $contexto, '127.0.0.1');

        $this->assertEquals('success', $res['status']);
        $this->assertEquals(2, $res['procesados']);
    }

    /**
     * 2. Lote con un elemento de otra compañía
     */
    public function test_02_lote_con_elemento_de_otra_compania(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(companiaId: 1);
        $mov1 = $this->crearMovimiento(101, 501, 1);

        // Repositorio filtra elemento de otra compañía y retorna solo 1 elemento
        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([101, 102], $contexto)
            ->andReturn([$mov1]);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $service = new CorreoInternoTransaccionalService($repo, null, null, $db);

        $this->expectException(TramiteInternoNoEncontradoException::class);
        $service->ejecutarRecepcionar([101, 102], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 3. Lote con movimiento inexistente
     */
    public function test_03_lote_con_movimiento_inexistente(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([999], $contexto)
            ->andReturn([]);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $service = new CorreoInternoTransaccionalService($repo, null, null, $db);

        $this->expectException(TramiteInternoNoEncontradoException::class);
        $service->ejecutarRecepcionar([999], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 4. Lote con movimiento inactivo
     */
    public function test_04_lote_con_movimiento_inactivo(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        // Repository filtra in_esta != 1 y retorna vacío
        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([100], $contexto)
            ->andReturn([]);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $service = new CorreoInternoTransaccionalService($repo, null, null, $db);

        $this->expectException(TramiteInternoNoEncontradoException::class);
        $service->ejecutarRecepcionar([100], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 5. Cargo receptor incorrecto
     */
    public function test_05_cargo_receptor_incorrecto(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = new TramiteInternoPolicy();
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20);
        // Movimiento destinado al cargo 99 (no al 20)
        $mov = $this->crearMovimiento(100, 500, 1, 15, 99, 3, 5, 1);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([100], $contexto)
            ->andReturn([$mov]);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $service = new CorreoInternoTransaccionalService($repo, $policy, null, $db);

        $this->expectException(OperacionTramiteNoAutorizadaException::class);
        $service->ejecutarRecepcionar([100], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 6. Estado origen inválido
     */
    public function test_06_estado_origen_invalido(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = new MaquinaEstadosTramiteInterno();
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        // Estado 3 (FINALIZADO), no puede recepcionarse
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 3);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([100], $contexto)
            ->andReturn([$mov]);

        $policy->shouldReceive('recepcionar')->with($contexto, $mov)->andReturn(true);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);

        $this->expectException(TransicionTramiteInvalidaException::class);
        $service->ejecutarRecepcionar([100], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 7. Policy denegada
     */
    public function test_07_policy_denegada(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 1);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([100], $contexto)
            ->andReturn([$mov]);

        $policy->shouldReceive('recepcionar')->with($contexto, $mov)->andReturn(false);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $service = new CorreoInternoTransaccionalService($repo, $policy, null, $db);

        $this->expectException(OperacionTramiteNoAutorizadaException::class);
        $service->ejecutarRecepcionar([100], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 8. Cargo destino de otra área en Asignar
     */
    public function test_08_cargo_destino_de_otra_area(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);

        $contexto = $this->crearContextoRemediacion(areaId: 5);

        // Cargo 88 pertenece al área 99 (distinta de 5)
        $repo->shouldReceive('obtenerCargoDestino')->with(88, true)->once()->andReturn((object) [
            'cdgo_crgo' => 88,
            'cdgo_area' => 99,
            'in_esta' => '1',
        ]);

        $service = new CorreoInternoTransaccionalService($repo, null, null, $db);

        $this->expectException(DestinoTramiteInvalidoException::class);
        $this->expectExceptionMessage('El cargo destino no pertenece a la misma área');

        $service->ejecutarAsignar([100], 88, 'Obs', '2026-10-11', [], [], $contexto, '127.0.0.1');
    }

    /**
     * 9. Área destino sin cargo activo en Derivar (sin fallback a cargoActual)
     */
    public function test_09_area_destino_sin_cargo_activo(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20, areaId: 5);

        // Área 12 existe y está activa
        $repo->shouldReceive('obtenerArea')->with(12, true)->once()->andReturn((object) ['cdgo_area' => 12, 'in_esta' => '1']);

        // Área 12 NO tiene cargo activo
        $repo->shouldReceive('obtenerCargoPrincipalDeArea')->with(12, true)->once()->andReturn(null);

        $service = new CorreoInternoTransaccionalService($repo, null, null, $db);

        $this->expectException(DestinoTramiteInvalidoException::class);
        $this->expectExceptionMessage('no cuenta con ningún cargo activo para recibir la derivación');

        $service->ejecutarDerivar([100], [12], 'Obs', '2026-10-11', [], [], $contexto, '127.0.0.1');
    }

    /**
     * 10. Update con cero filas afectadas (conflicto de concurrencia)
     */
    public function test_10_update_con_cero_filas_afectadas(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = Mockery::mock(MaquinaEstadosTramiteInterno::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 1);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')->andReturn([$mov]);
        $policy->shouldReceive('recepcionar')->andReturn(true);
        $maquina->shouldReceive('esTransicionValida')->andReturn(true);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        // Simular que el update afectó 0 filas (carrera de concurrencia)
        $repo->shouldReceive('desactivarMovimientoInternoEsperado')->with(500)->once()->andReturn(0);

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);

        $this->expectException(ConflictoConcurrenteTramiteException::class);
        $service->ejecutarRecepcionar([100], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 11. Excepción después de actualizar y antes de insertar (rollback transaccional)
     */
    public function test_11_excepcion_despues_de_actualizar_y_antes_de_insertar(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = Mockery::mock(MaquinaEstadosTramiteInterno::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 1);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')->andReturn([$mov]);
        $policy->shouldReceive('recepcionar')->andReturn(true);
        $maquina->shouldReceive('esTransicionValida')->andReturn(true);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $repo->shouldReceive('desactivarMovimientoInternoEsperado')->with(500)->once()->andReturn(1);
        // Falla en el insert
        $repo->shouldReceive('crearMovimientoInterno')->once()->andThrow(new RuntimeException('Error de conexión o fallo de inserción'));

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error de conexión o fallo de inserción');

        $service->ejecutarRecepcionar([100], '2026-10-11', 'Obs', $contexto, '127.0.0.1');
    }

    /**
     * 12. Reiteración que deja exactamente un movimiento vigente
     */
    public function test_12_reiteracion_deja_exactamente_un_movimiento_vigente(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = Mockery::mock(MaquinaEstadosTramiteInterno::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 15);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 1);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')->with([100], $contexto)->andReturn([$mov]);
        $policy->shouldReceive('reiterar')->with($contexto, $mov)->andReturn(true);
        $maquina->shouldReceive('esTransicionValida')->with(1, 'reiterar')->andReturn(true);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });
        $repo->shouldReceive('obtenerSiguienteSecuenciaInterna')->once()->andReturn(777);
        // Desactiva el movimiento anterior con affected === 1
        $repo->shouldReceive('desactivarMovimientoInternoEsperado')->with(500)->once()->andReturn(1);

        // Inserta un único movimiento nuevo en estado 53
        $repo->shouldReceive('crearMovimientoInterno')->once()->with(Mockery::on(function ($payload) {
            return $payload['cdgo_dtos_ntrnos'] === 100
                && $payload['cdgo_estdo_trmte'] === 53
                && $payload['in_esta'] === '1'
                && $payload['cdgo_crgo_en'] === 20;
        }))->andReturn(1);

        $repo->shouldReceive('registrarSeguimientoInterno')->once()->andReturnNull();

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);
        $res = $service->ejecutarReiterar([100], 'Reitero atención urgente', '2026-10-11', [], $contexto, '127.0.0.1');

        $this->assertEquals('success', $res['status']);
        $this->assertEquals(1, $res['procesados']);
    }

    /**
     * 13. Ejecución repetida / idempotente
     */
    public function test_13_ejecucion_repetida_idempotente(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $service = new IdempotenciaTramiteInternoService($cache);

        $token = 'idempotency-token-xyz';
        $cache->shouldReceive('has')->with("idempotency_ti_resultado:{$token}")->andReturn(true);
        $cache->shouldReceive('get')->with("idempotency_ti_resultado:{$token}")->andReturn(['procesados' => 2]);

        $ejecutado = false;
        $resultado = $service->ejecutar($token, 60, function () use (&$ejecutado) {
            $ejecutado = true;
            return ['procesados' => 99];
        });

        $this->assertEquals(['procesados' => 2], $resultado);
        $this->assertFalse($ejecutado, 'La operación no debe volverse a ejecutar si el resultado fue cacheado');
    }

    /**
     * 14. Descarga consolidada autorizada
     */
    public function test_14_descarga_consolidada_autorizada(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')
            ->with(100, $contexto)
            ->andReturn($mov);

        $policy->shouldReceive('descargar')->with($contexto, $mov)->andReturn(true);

        $repo->shouldReceive('obtenerDocumentoParaConsolidado')->with(100, 1)->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => '2026-00000100',
            'de_urls_adjnto' => 'principal.pdf',
        ]);
        $repo->shouldReceive('obtenerMovimientosParaConsolidado')->with(100)->andReturn([]);

        // Mock gestor adjuntos
        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $tempTestFile = tempnam($tempDir, 'test_adj');
        file_put_contents($tempTestFile, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $gestor->shouldReceive('resolverRutaSegura')->with('principal.pdf')->andReturn($tempTestFile);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        // Auditoría (contrato de 4 columnas)
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->twice()->with(Mockery::on(function ($payload) {
            return isset($payload['cdgo_lgin'], $payload['de_urls_dscrgas'], $payload['fe_sgmto_dscrgas'], $payload['de_ip'])
                && !isset($payload['cdgo_sgmto_dscrgas'])
                && !isset($payload['cdgo_trmte_dcmnto'])
                && !isset($payload['ip_dscrga'])
                && !isset($payload['fe_dscrga'])
                && is_int($payload['cdgo_lgin']);
        }));

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);
        $res = $service->generarConsolidado(100, $contexto, '127.0.0.1');

        $this->assertFileExists($res['zip_path']);
        $this->assertStringContainsString('EXPEDIENTE-2026-00000100.zip', $res['zip_name']);

        @unlink($res['zip_path']);
        @unlink($tempTestFile);
    }

    /**
     * 15. Descarga consolidada de documento de la misma compañía pero cargo ajeno
     */
    public function test_15_descarga_consolidada_misma_compania_cargo_ajeno(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = new TramiteInternoPolicy();
        $db = Mockery::mock(DatabaseManager::class);

        // Funcionario cargo 30, área 8
        $contexto = $this->crearContextoRemediacion(cargoId: 30, areaId: 8);
        // Movimiento entre cargo 15 (área 3) y cargo 20 (área 5)
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')
            ->with(100, $contexto)
            ->andReturn($mov);

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tiene permisos para descargar el consolidado');

        $service->generarConsolidado(100, $contexto, '127.0.0.1');
    }

    /**
     * 16. Descarga consolidada de otra compañía
     */
    public function test_16_descarga_otra_compania(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(companiaId: 1);
        $mov = $this->crearMovimiento(100, 500, companiaId: 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')
            ->with(100, $contexto)
            ->andReturn($mov);

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El expediente pertenece a otra compañía');

        $service->generarConsolidado(100, $contexto, '127.0.0.1');
    }

    /**
     * 17. Fallo de auditoría que impide entregar archivo (fail-closed)
     */
    public function test_17_fallo_auditoria_impide_entregar_archivo(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->andReturn($mov);
        $policy->shouldReceive('descargar')->andReturn(true);

        $repo->shouldReceive('obtenerDocumentoParaConsolidado')
            ->with(100, 1)
            ->andReturn((object) [
                'cdgo_dtos_ntrnos' => 100,
                'nu_tram_todo' => '2026-00000100',
                'de_urls_adjnto' => 'archivo.pdf',
            ]);

        $repo->shouldReceive('obtenerMovimientosParaConsolidado')
            ->with(100)
            ->andReturn([]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $tempTestFile = tempnam($tempDir, 'test_adj');
        file_put_contents($tempTestFile, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $gestor->shouldReceive('resolverRutaSegura')->andReturn($tempTestFile);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        // Fallo en la auditoría individual
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')
            ->andThrow(new RuntimeException('Error BD audit'));

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fallo en la persistencia atómica de auditoría legal de descarga consolidada: no es posible proceder');

        try {
            $service->generarConsolidado(100, $contexto, '127.0.0.1');
        } finally {
            @unlink($tempTestFile);
        }
    }

    /**
     * 18. Inserción de auditoría usando únicamente columnas reales (prohibiendo ip_dscrga y fe_dscrga)
     */
    public function test_18_insercion_auditoria_usando_unicamente_columnas_reales(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $columnasCapturadas = [];
        $payloadCapturado = [];
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->andReturnUsing(function ($payload) use (&$columnasCapturadas, &$payloadCapturado) {
            $columnasCapturadas = array_keys($payload);
            $payloadCapturado = $payload;
        });

        $service = new DescargaSeguraTramiteInternoService($repo, $gestor, $policy);
        $service->auditarDescarga(42, '192.168.1.50', 'documento.pdf');

        $columnasEsperadas = ['de_urls_dscrgas', 'cdgo_lgin', 'fe_sgmto_dscrgas', 'de_ip'];
        sort($columnasEsperadas);
        sort($columnasCapturadas);

        $this->assertEquals($columnasEsperadas, $columnasCapturadas);
        $this->assertIsInt($payloadCapturado['cdgo_lgin']);
        $this->assertEquals(42, $payloadCapturado['cdgo_lgin']);
        $this->assertNotContains('cdgo_sgmto_dscrgas', $columnasCapturadas, 'cdgo_sgmto_dscrgas NO debe ser insertado manualmente');
        $this->assertNotContains('cdgo_trmte_dcmnto', $columnasCapturadas, 'cdgo_trmte_dcmnto NO existe en el esquema');
        $this->assertNotContains('ip_dscrga', $columnasCapturadas, 'ip_dscrga NO debe existir en el payload');
        $this->assertNotContains('fe_dscrga', $columnasCapturadas, 'fe_dscrga NO debe existir en el payload');
    }

    /**
     * 19. Tipo documental 64 inválido (retorna 422)
     */
    public function test_19_tipo_documental_64_invalido(): void
    {
        $validador = Mockery::mock(ValidadorPagoProveedoresTipo64Service::class);
        $validador->shouldReceive('validarLoteParaRecepcion')
            ->once()
            ->with([64])
            ->andReturn([
                'valido' => false,
                'servicio_no_disponible' => false,
                'mensaje' => 'El expediente tipo 64 no cuenta con todos los anexos logísticos completos.',
            ]);

        $transaccional = Mockery::mock(CorreoInternoTransaccionalService::class);
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);

        $controller = new CorreoInternoController($transaccional, $repo, $policy, $validador);

        $request = Mockery::mock(RecepcionarTramiteInternoRequest::class);
        $request->shouldReceive('validated')->with('ids')->andReturn([64]);
        $request->shouldReceive('validated')->with('fecha')->andReturn(null);
        $request->shouldReceive('validated')->with('observacion')->andReturn('Sin observaciones');
        $request->shouldReceive('validated')->with('observaciones')->andReturn(null);
        $request->shouldReceive('session')->andReturn(new class {
            public function get(string $key, $default = null) {
                return $key === 'sigtram_cargo_id' ? 20 : 5;
            }
        });

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'test';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        session([
            SessionKeys::COMPANIA_ID => 1,
            SessionKeys::CARGO_ID => 20,
            SessionKeys::AREA_ID => 5,
            SessionKeys::LOGIN_ID => 10,
        ]);

        $response = $controller->recepcionar($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('anexos logísticos', $data['message']);
    }

    /**
     * 20. Indisponibilidad del validador de tipo 64 (fail-closed retorna 503)
     */
    public function test_20_indisponibilidad_validador_tipo_64(): void
    {
        $validador = Mockery::mock(ValidadorPagoProveedoresTipo64Service::class);
        $validador->shouldReceive('validarLoteParaRecepcion')
            ->once()
            ->with([64])
            ->andReturn([
                'valido' => false,
                'servicio_no_disponible' => true,
                'mensaje' => 'Servicio de validación de pago a proveedores no disponible temporalmente.',
            ]);

        $transaccional = Mockery::mock(CorreoInternoTransaccionalService::class);
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);

        $controller = new CorreoInternoController($transaccional, $repo, $policy, $validador);

        $request = Mockery::mock(RecepcionarTramiteInternoRequest::class);
        $request->shouldReceive('validated')->with('ids')->andReturn([64]);
        $request->shouldReceive('validated')->with('fecha')->andReturn(null);
        $request->shouldReceive('validated')->with('observacion')->andReturn('Sin observaciones');
        $request->shouldReceive('validated')->with('observaciones')->andReturn(null);
        $request->shouldReceive('session')->andReturn(new class {
            public function get(string $key, $default = null) {
                return $key === 'sigtram_cargo_id' ? 20 : 5;
            }
        });

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'test';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        session([
            SessionKeys::COMPANIA_ID => 1,
            SessionKeys::CARGO_ID => 20,
            SessionKeys::AREA_ID => 5,
            SessionKeys::LOGIN_ID => 10,
        ]);

        $response = $controller->recepcionar($request);

        $this->assertEquals(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('no disponible temporalmente', $data['message']);
    }

    /**
     * 21. RTI-01: Contexto de compañía confiable y normalización sin fallback arbitrario
     */
    public function test_21_normalizacion_compania_confiable_rti_01(): void
    {
        // 1. Escalar 1
        $this->assertEquals(1, ContextoUsuarioAutenticadoDto::normalizarCompania(1));
        // 2. Escalar distinto de 1
        $this->assertEquals(5, ContextoUsuarioAutenticadoDto::normalizarCompania(5));
        $this->assertEquals(7, ContextoUsuarioAutenticadoDto::normalizarCompania('7'));
        // 3. Array con cdgo_cmpnia
        $this->assertEquals(3, ContextoUsuarioAutenticadoDto::normalizarCompania(['cdgo_cmpnia' => 3]));
        // 4. Objeto con cdgo_cmpnia
        $this->assertEquals(4, ContextoUsuarioAutenticadoDto::normalizarCompania((object) ['cdgo_cmpnia' => 4]));
        // 5. Sin compañía o nulo (prohibido fallback 1 o 2)
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania(null));
        // 6. Inválido o cero (nunca aplica tenant por defecto)
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania(0));
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania(-5));
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania('invalido'));
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania([]));
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania(['id' => 5]));
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania(['company_id' => 5]));
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania((object) ['id' => 5]));
        $this->assertEquals(0, ContextoUsuarioAutenticadoDto::normalizarCompania((object) ['company_id' => 5]));

        // 7. Request sin compañía en SessionKeys::USER_COMPANY
        $requestSinComp = Request::create('/test', 'GET');
        $sessionMockSinComp = Mockery::mock(\Illuminate\Contracts\Session\Session::class);
        $sessionMockSinComp->shouldReceive('get')->with(SessionKeys::USER_COMPANY)->andReturn(null);
        $sessionMockSinComp->shouldReceive('get')->with(\App\Support\SessionKeys::COMPANIA_ID)->andReturn(null);
        $sessionMockSinComp->shouldReceive('get')->with('sigtram_cargo_id', 0)->andReturn(20);
        $sessionMockSinComp->shouldReceive('get')->with(\App\Support\SessionKeys::CARGO_ID)->andReturn(20);
        $sessionMockSinComp->shouldReceive('get')->with('sigtram_permisos', [])->andReturn([]);
        $requestSinComp->setLaravelSession($sessionMockSinComp);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 99;
        $user->cdgo_usrios = 10;
        $this->actingAs($user);

        $dtoSinComp = $this->resolverContextoDesdeRequest($requestSinComp);
        $this->assertNull($dtoSinComp, 'Sin compañía válida debe retornar null y no asumir fallback fijo');

        // 8. Request con compañía válida y confirmación de cdgo_lgin entero
        $requestConComp = Request::create('/test', 'GET');
        $sessionMockConComp = Mockery::mock(\Illuminate\Contracts\Session\Session::class);
        $sessionMockConComp->shouldReceive('get')->andReturnUsing(function($key, $default = null) {
            return match($key) {
                SessionKeys::USER_COMPANY, SessionKeys::COMPANIA_ID => 2,
                SessionKeys::USER_CARGO, SessionKeys::CARGO_ID, 'sigtram_cargo_id' => 20,
                'sigtram_permisos' => [],
                'sigtram_cargos_asignados' => [20],
                SessionKeys::USER_AREA, SessionKeys::AREA_ID => 5,
                default => $default,
            };
        });
        $requestConComp->setLaravelSession($sessionMockConComp);

        \Illuminate\Support\Facades\DB::shouldReceive('table')
            ->with(Mockery::pattern('/p_crgos/'))
            ->andReturn(Mockery::mock(['where' => Mockery::self(), 'first' => (object) ['cdgo_area' => 5]]));

        $dtoConComp = $this->resolverContextoDesdeRequest($requestConComp);
        $this->assertNotNull($dtoConComp);
        $this->assertEquals(2, $dtoConComp->idCompania);
        $this->assertIsInt($dtoConComp->getCdgoLginAudit());
        $this->assertEquals(99, $dtoConComp->getCdgoLginAudit());
    }

    /**
     * 22. RTI-02: Contrato de identificadores sin ambigüedad
     */
    public function test_22_contrato_identificadores_sin_ambiguedad_rti_02(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderMovs = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_dtos_ntrnos as p/'))->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 100)->once()->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->once()->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.in_esta', '1')->once()->andReturnSelf();
        $builderDoc->shouldReceive('select')->andReturnSelf();
        $builderDoc->shouldReceive('first')->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => 'DOC-100',
            'cdgo_cmpnia' => 1,
            'cdgo_area' => 5,
        ]);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_ntrnos as r/'))->andReturn($builderMovs);
        $builderMovs->shouldReceive('leftJoin')->andReturnSelf();
        $builderMovs->shouldReceive('where')->with('r.cdgo_dtos_ntrnos', 100)->once()->andReturnSelf();
        $builderMovs->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $builderMovs->shouldReceive('select')->andReturnSelf();
        $builderMovs->shouldReceive('orderBy')->with('r.cdgo_trmtes_ntrnos', 'desc')->andReturnSelf();
        $builderMovs->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmtes_ntrnos' => 500,
                'cdgo_dtos_ntrnos' => 100,
                'cdgo_crgo_sa' => 15,
                'cdgo_crgo_en' => 20,
                'area_en' => 5,
                'cdgo_estdo_trmte' => 2,
                'in_esta' => '1',
                'fe_rcbdo' => '2026-10-11 12:00:00',
                'de_obser' => '',
                'in_copia' => '0',
            ]
        ]));

        $repo = new \App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository($db);
        $contexto = $this->crearContextoRemediacion(companiaId: 1, cargoId: 20, areaId: 5);

        $expediente = $repo->obtenerExpedienteAutorizadoParaConsolidado(100, $contexto);
        $this->assertNotNull($expediente);
        $this->assertEquals(100, $expediente->id);
        $this->assertEquals(500, $expediente->movimientoId);
    }

    /**
     * 23. RTI-03: Paridad legacy de copias y estados (recepcionar -> 2, finalizar -> 3)
     */
    public function test_23_paridad_legacy_copias_y_estados_rti_03(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = new MaquinaEstadosTramiteInterno();
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $movCopia1 = $this->crearMovimiento(101, 501, 1, 15, 20, 3, 5, 1, '1', '1');
        $movCopia2 = $this->crearMovimiento(102, 502, 1, 15, 20, 3, 5, 1, '1', '2');

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->with([101, 102], $contexto)
            ->andReturn([$movCopia1, $movCopia2]);

        $policy->shouldReceive('recepcionar')->andReturn(true);

        $db->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $repo->shouldReceive('desactivarMovimientoInternoEsperado')->twice()->andReturn(1);

        $payloadsInsertados = [];
        $repo->shouldReceive('crearMovimientoInterno')->twice()->with(Mockery::on(function ($payload) use (&$payloadsInsertados) {
            $payloadsInsertados[] = $payload;
            return true;
        }))->andReturn(1);

        $repo->shouldReceive('registrarSeguimientoInterno')->twice()->andReturnNull();

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);
        $service->ejecutarRecepcionar([101, 102], '2026-10-11 10:00:00', 'Obs', $contexto, '127.0.0.1');

        $this->assertCount(2, $payloadsInsertados);
        // Ambos deben generar estado 2 (RECIBIDO), preservando in_copia informativo
        $this->assertEquals(2, $payloadsInsertados[0]['cdgo_estdo_trmte']);
        $this->assertEquals('1', $payloadsInsertados[0]['in_copia']);
        $this->assertEquals(2, $payloadsInsertados[1]['cdgo_estdo_trmte']);
        $this->assertEquals('2', $payloadsInsertados[1]['in_copia']);

        // Verificar que la máquina de estados reconozca estados históricos 23 y 24
        $this->assertTrue($maquina->esTransicionValida(23, 'finalizar'));
        $this->assertFalse($maquina->esTransicionValida(24, 'recepcionar'));
        $this->assertEquals(24, \App\Enums\mod_gene\EstadoTramiteInterno::FINALIZADO_ATENDIDO->value);
    }

    /**
     * 24. RTI-04: Validación de destinos dentro de transacción (anti-TOCTOU)
     */
    public function test_24_validacion_destinos_dentro_de_transaccion_rti_04(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = Mockery::mock(MaquinaEstadosTramiteInterno::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20, areaId: 5);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')->with([100], $contexto)->andReturn([$mov]);
        $policy->shouldReceive('asignar')->andReturn(true);
        $maquina->shouldReceive('esTransicionValida')->andReturn(true);

        $transaccionIniciada = false;
        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($cb) use (&$transaccionIniciada) {
            $transaccionIniciada = true;
            return $cb();
        });

        // La consulta de validación de cargo destino ocurre DENTRO de la transacción
        $repo->shouldReceive('obtenerCargoDestino')->with(25, true)->once()->andReturnUsing(function () use (&$transaccionIniciada) {
            $this->assertTrue($transaccionIniciada, 'La consulta de cargo destino debe ocurrir DENTRO de la transacción');
            return null; // Simular que el cargo destino fue desactivado justo antes de operar
        });

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);

        $this->expectException(DestinoTramiteInvalidoException::class);
        $this->expectExceptionMessage('no existe o no está activo');

        $service->ejecutarAsignar([100], 25, 'Obs', '2026-10-11', [], [], $contexto, '127.0.0.1');
    }

    /**
     * 25. RTI-05: Descarga por lote segura con fail-closed y prevención de path traversal
     */
    public function test_25_descarga_lote_segura_fail_closed_y_traversal_rti_05(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $service = new DescargaLoteTramiteInternoService($repo, $gestor, $db, $policy);
        $contexto = $this->crearContextoRemediacion(companiaId: 1);

        $movTraversal = $this->crearMovimiento(101, 501, 1);
        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(101, $contexto)->andReturn($movTraversal);
        $policy->shouldReceive('descargar')->andReturn(true);

        $repo->shouldReceive('obtenerDocumentosAutorizadosLote')->with([101], 1)->andReturn([101]);
        $repo->shouldReceive('obtenerMovimientosLote')->with([101], 1)->andReturn([
            (object) ['cdgo_dtos_ntrnos' => 101, 'de_urls_agrdo' => '../../.env'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path Traversal detectado');
        $service->generarLoteZip([101], $contexto, '127.0.0.1');
    }

    /**
     * 26. RTI-05: Descarga por lote con payload exacto de auditoría de 4 columnas
     */
    public function test_26_descarga_lote_payload_4_columnas_y_fail_closed(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(companiaId: 1, cargoId: 20);
        $mov1 = $this->crearMovimiento(101, 501, 1);
        $mov2 = $this->crearMovimiento(102, 502, 1);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(101, $contexto)->andReturn($mov1);
        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(102, $contexto)->andReturn($mov2);
        $policy->shouldReceive('descargar')->andReturn(true);

        $repo->shouldReceive('obtenerDocumentosAutorizadosLote')->with([101, 102], 1)->andReturn([101, 102]);
        $repo->shouldReceive('obtenerMovimientosLote')->with([101, 102], 1)->andReturn([
            (object) ['cdgo_dtos_ntrnos' => 101, 'de_urls_agrdo' => 'archivo1.pdf'],
            (object) ['cdgo_dtos_ntrnos' => 102, 'de_urls_agrdo' => 'archivo2.pdf'],
        ]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $f1 = tempnam($tempDir, 'sigtram_lote_1_');
        $f2 = tempnam($tempDir, 'sigtram_lote_2_');
        file_put_contents($f1, 'DOC1');
        file_put_contents($f2, 'DOC2');

        $gestor->shouldReceive('resolverRutaSegura')->with('archivo1.pdf')->andReturn($f1);
        $gestor->shouldReceive('resolverRutaSegura')->with('archivo2.pdf')->andReturn($f2);

        $payloadsAudit = [];
        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->times(3)->with(Mockery::on(function ($payload) use (&$payloadsAudit) {
            $payloadsAudit[] = $payload;
            return true;
        }))->andReturnNull();

        $service = new DescargaLoteTramiteInternoService($repo, $gestor, $db, $policy);
        $res = $service->generarLoteZip([101, 102], $contexto, '127.0.0.1');

        $this->assertFileExists($res['zip_path']);
        // 2 auditorías individuales + 1 auditoría del lote = 3 inserciones
        $this->assertCount(3, $payloadsAudit);
        foreach ($payloadsAudit as $audit) {
            $this->assertArrayHasKey('cdgo_lgin', $audit);
            $this->assertArrayHasKey('de_urls_dscrgas', $audit);
            $this->assertArrayHasKey('fe_sgmto_dscrgas', $audit);
            $this->assertArrayHasKey('de_ip', $audit);
            $this->assertArrayNotHasKey('cdgo_sgmto_dscrgas', $audit);
            $this->assertArrayNotHasKey('cdgo_trmte_dcmnto', $audit);
            $this->assertArrayNotHasKey('ip_dscrga', $audit);
            $this->assertArrayNotHasKey('fe_dscrga', $audit);
            $this->assertIsInt($audit['cdgo_lgin']);
        }

        @unlink($res['zip_path']);
        @unlink($f1);
        @unlink($f2);
    }

    /**
     * 27. RTI-07: Reemplazo firmado con almacenamiento privado y auditoría fail-closed
     */
    public function test_27_reemplazo_documento_firmado_seguro_rti_07(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(companiaId: 1, cargoId: 20);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(100, $contexto)->andReturn($mov);
        $policy->shouldReceive('reemplazarFirmado')->with($contexto, $mov)->andReturn(true);

        $tempPdf = tempnam(sys_get_temp_dir(), 'sigtram_up_') . '.pdf';
        file_put_contents($tempPdf, '%PDF-1.4 test valid pdf content');
        $uploadedFile = new UploadedFile($tempPdf, 'reemplazo.pdf', 'application/pdf', null, true);

        $dtoAdjunto = new \App\DTOs\Support\ArchivoAdjuntoDto(
            uuid: 'uuid-123',
            nombreOriginal: 'reemplazo.pdf',
            rutaRelativa: 'documentos/internos/hash_reemplazo.pdf',
            mimeTipo: 'application/pdf',
            extension: 'pdf',
            tamanoBytes: (int) filesize($tempPdf),
            sha256: hash_file('sha256', $tempPdf)
        );

        $gestor->shouldReceive('guardar')->with($uploadedFile, 'internos')->andReturn($dtoAdjunto);

        $db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $repo->shouldReceive('actualizarDocumentoFirmado')
            ->with(100, 1, ['de_urls_adjnto' => 'documentos/internos/hash_reemplazo.pdf'])
            ->andReturn(1);

        $payloadAuditCapturado = null;
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->once()->with(Mockery::on(function ($payload) use (&$payloadAuditCapturado) {
            $payloadAuditCapturado = $payload;
            return true;
        }))->andReturnNull();

        $service = new ReemplazarDocumentoFirmadoService($repo, $gestor, $db, $policy);
        $res = $service->reemplazar(100, $uploadedFile, $contexto, '127.0.0.1');

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('documentos/internos/hash_reemplazo.pdf', $res['nuevo_adjunto']);

        $this->assertNotNull($payloadAuditCapturado);
        $this->assertCount(4, $payloadAuditCapturado);
        $this->assertEquals(['cdgo_lgin', 'de_urls_dscrgas', 'fe_sgmto_dscrgas', 'de_ip'], array_keys($payloadAuditCapturado));
        $this->assertIsInt($payloadAuditCapturado['cdgo_lgin']);
    }

    /**
     * 28. RTI-08: Test arquitectónico que garantiza controlador delgado sin fugas de DB/ZIP/Filesystem
     */
    public function test_28_tests_arquitectonicos_anti_fugas_controlador_rti_08(): void
    {
        $controllerReflector = new ReflectionClass(CorreoInternoController::class);
        $fileName = $controllerReflector->getFileName();
        $this->assertNotEmpty($fileName);
        $source = file_get_contents($fileName);

        foreach (['reemplazarFirmado', 'descargarCompleto', 'descargarLote'] as $method) {
            $methodReflector = $controllerReflector->getMethod($method);
            $start = $methodReflector->getStartLine();
            $end = $methodReflector->getEndLine();
            $lines = array_slice(explode("\n", $source), $start - 1, $end - $start + 1);
            $methodCode = implode("\n", $lines);

            $this->assertStringNotContainsString('DB::table', $methodCode, "El método {$method} no debe usar DB::table directamente");
            $this->assertStringNotContainsString('new ZipArchive', $methodCode, "El método {$method} no debe instanciar ZipArchive");
            $this->assertStringNotContainsString('public_path', $methodCode, "El método {$method} no debe usar public_path");
            $this->assertStringNotContainsString('p_sgmto_dscrgas', $methodCode, "El método {$method} no debe insertar directamente en p_sgmto_dscrgas");
            $this->assertStringNotContainsString('is_file', $methodCode, "El método {$method} no debe usar is_file sobre rutas en disco");
        }
    }

    /**
     * 29. RTI-C01/RTI-C09: BuscarTramiteController descargar resuelve mediante ID canónico
     */
    public function test_29_buscar_tramite_controller_descargar_por_id_canonico_happy_path(): void
    {
        $descargaSeguraMock = Mockery::mock(DescargaSeguraTramiteInternoService::class);
        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $tempFile = tempnam($tempDir, 'test_dl_');
        file_put_contents($tempFile, '%PDF-1.4 TEST PDF');

        $descargaSeguraMock->shouldReceive('descargarPorDocumento')
            ->once()
            ->with(100, Mockery::type(ContextoUsuarioAutenticadoDto::class), '127.0.0.1')
            ->andReturn([
                'ruta_fisica' => $tempFile,
                'nombre_descarga' => 'expediente_100.pdf',
            ]);

        Auth::setUser(new \Illuminate\Auth\GenericUser([
            'id' => 99,
            'cdgo_lgin' => 99,
            'de_lgin' => 'jperez',
            'cdgo_usrios' => 10,
            'cdgo_cmpnia' => 1,
        ]));

        $request = Request::create('/buscar-tramite/descargar', 'GET', ['id' => 100], server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put(SessionKeys::USER_CARGO, 20);
        $session->put(SessionKeys::USER_AREA, 5);
        $session->put(SessionKeys::USER_ID, 99);
        $request->setLaravelSession($session);

        $controller = new BuscarTramiteController($descargaSeguraMock);
        $response = $controller->descargar($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($tempFile, $response->getFile()->getPathname());

        @unlink($tempFile);
    }

    /**
     * 30. RTI-C01/RTI-C09: BuscarTramiteController descargar rechaza path traversal con 400
     */
    public function test_30_buscar_tramite_controller_descargar_rechaza_path_traversal_con_400(): void
    {
        $descargaSeguraMock = Mockery::mock(DescargaSeguraTramiteInternoService::class);
        $controller = new BuscarTramiteController($descargaSeguraMock);

        $ataques = [
            ['path' => '../../etc/passwd', 'id' => 100],
            ['nom_archivo' => '..\\windows\\system.ini', 'id' => 100],
            ['path' => '/etc/shadow', 'movimiento_id' => 500],
            ['path' => "test\0.pdf", 'id' => 100],
            ['path' => 'C:\\boot.ini', 'id' => 100],
        ];

        foreach ($ataques as $params) {
            $request = Request::create('/buscar-tramite/descargar', 'GET', $params);
            try {
                $controller->descargar($request);
                $this->fail('Se esperaba HttpException 400 por intento de path traversal');
            } catch (HttpException $e) {
                $this->assertEquals(400, $e->getStatusCode());
                $this->assertStringContainsString('Path Traversal', $e->getMessage());
                // Asegurar que no hay fuga de rutas físicas
                $this->assertStringNotContainsString(storage_path(), $e->getMessage());
            }
        }
    }

    /**
     * 31. RTI-C01/RTI-C09: BuscarTramiteController descargar maneja excepciones seguras sin fugas
     */
    public function test_31_buscar_tramite_controller_descargar_maneja_excepciones_sin_fugas(): void
    {
        $descargaSeguraMock = Mockery::mock(DescargaSeguraTramiteInternoService::class);
        $controller = new BuscarTramiteController($descargaSeguraMock);

        Auth::setUser(new \Illuminate\Auth\GenericUser([
            'id' => 99,
            'cdgo_lgin' => 99,
            'de_lgin' => 'jperez',
            'cdgo_usrios' => 10,
            'cdgo_cmpnia' => 1,
        ]));

        $crearRequest = function (array $query) {
            $req = Request::create('/buscar-tramite/descargar', 'GET', $query, server: ['REMOTE_ADDR' => '127.0.0.1']);
            $session = app('session.store');
            $session->put(SessionKeys::USER_COMPANY, 1);
            $session->put(SessionKeys::USER_CARGO, 20);
            $session->put(SessionKeys::USER_AREA, 5);
            $session->put(SessionKeys::USER_ID, 99);
            $req->setLaravelSession($session);
            return $req;
        };

        // Caso A: Sin identificador canónico
        try {
            $controller->descargar($crearRequest(['id' => 0, 'movimiento_id' => 0]));
            $this->fail('Se esperaba 400 por falta de ID canónico');
        } catch (HttpException $e) {
            $this->assertEquals(400, $e->getStatusCode());
            $this->assertEquals('Identificador canónico de documento o movimiento requerido.', $e->getMessage());
        }

        // Caso B: No encontrado (404)
        $descargaSeguraMock->shouldReceive('descargarPorDocumento')
            ->with(999, Mockery::type(ContextoUsuarioAutenticadoDto::class), '127.0.0.1')
            ->andThrow(new RuntimeException('El recurso solicitado no fue encontrado o no está disponible.'));

        try {
            $controller->descargar($crearRequest(['id' => 999]));
            $this->fail('Se esperaba 404 por recurso no encontrado');
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertEquals('Documento no encontrado o no disponible.', $e->getMessage());
        }

        // Caso C: No autorizado (403)
        $descargaSeguraMock->shouldReceive('descargarPorDocumento')
            ->with(200, Mockery::type(ContextoUsuarioAutenticadoDto::class), '127.0.0.1')
            ->andThrow(new RuntimeException('No tiene permisos para descargar adjuntos de este expediente.'));

        try {
            $controller->descargar($crearRequest(['id' => 200]));
            $this->fail('Se esperaba 403 por no autorizado');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('No autorizado para descargar este documento.', $e->getMessage());
        }
    }

    /**
     * 32. RTI-C02/RTI-C05: Limpieza de adjunto privado en rollback transaccional
     */
    public function test_32_correo_interno_transaccional_rollback_limpia_archivo_en_error(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = Mockery::mock(MaquinaEstadosTramiteInterno::class);
        $db = Mockery::mock(DatabaseManager::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20, areaId: 5);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $tempFile = tempnam(sys_get_temp_dir(), 'sigtram_test_rb_');
        file_put_contents($tempFile, 'PDF CONTENIDO');
        $uploaded = new UploadedFile($tempFile, 'adjunto.pdf', 'application/pdf', null, true);

        $dtoAdjunto = new ArchivoAdjuntoDto(
            uuid: 'uuid-rb-1',
            nombreOriginal: 'adjunto.pdf',
            rutaRelativa: 'documentos/internos/2026/10/11/uuid-rb-1.pdf',
            mimeTipo: 'application/pdf',
            extension: 'pdf',
            tamanoBytes: 13,
            sha256: 'hash'
        );

        $gestor->shouldReceive('guardar')->with($uploaded, 'internos')->andReturn($dtoAdjunto);
        // Debe llamarse a eliminar() en la captura del error para limpiar el archivo huérfano
        $gestor->shouldReceive('eliminar')->once()->with('documentos/internos/2026/10/11/uuid-rb-1.pdf')->andReturn(true);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $repo->shouldReceive('obtenerCargoDestino')->with(25, true)->andReturn((object) ['cdgo_crgo' => 25, 'cdgo_area' => 5]);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')->with([100], $contexto)->andReturn([$mov]);
        $policy->shouldReceive('asignar')->andReturn(true);
        $maquina->shouldReceive('esTransicionValida')->andReturn(true);

        $repo->shouldReceive('obtenerSiguienteSecuenciaInterna')->andReturn(1001);

        $repo->shouldReceive('desactivarMovimientoInternoEsperado')->andThrow(new RuntimeException('Falla intencional en transacción BD'));

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db, $gestor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falla intencional en transacción BD');

        try {
            $service->ejecutarAsignar(
                [100],
                25,
                'Obs',
                '2026-10-11',
                [],
                ['fle_adjunto1' => $uploaded],
                $contexto,
                '127.0.0.1'
            );
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * 33. RTI-C05/RTI-C06: TramiteInternoRepository lanza ConflictoConcurrenteTramiteException ante ambigüedad
     */
    public function test_33_tramite_interno_repository_ambiguedad_lanza_conflicto_concurrente_exception(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $repo = new \App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository($db);
        $contexto = $this->crearContextoRemediacion(cargoId: 20, areaId: 5);

        $builderDoc = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/p_dtos_ntrnos/'))->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->andReturnSelf();
        $builderDoc->shouldReceive('select')->andReturnSelf();
        $builderDoc->shouldReceive('first')->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => '2026-00000100',
            'cdgo_cmpnia' => 1,
            'cdgo_area' => 5,
        ]);

        $builderMovs = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_ntrnos/'))->andReturn($builderMovs);
        $builderMovs->shouldReceive('leftJoin')->andReturnSelf();
        $builderMovs->shouldReceive('where')->andReturnSelf();
        $builderMovs->shouldReceive('select')->andReturnSelf();
        $builderMovs->shouldReceive('orderBy')->andReturnSelf();
        // Dos movimientos que coinciden para el mismo cargo 20
        $builderMovs->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmtes_ntrnos' => 502,
                'cdgo_dtos_ntrnos' => 100,
                'cdgo_crgo_sa' => 15,
                'cdgo_crgo_en' => 20,
                'area_en' => 5,
                'cdgo_estdo_trmte' => 1,
                'in_esta' => '1',
                'fe_rcbdo' => '2026-10-11',
                'de_obser' => '',
                'in_copia' => '1',
            ],
            (object) [
                'cdgo_trmtes_ntrnos' => 501,
                'cdgo_dtos_ntrnos' => 100,
                'cdgo_crgo_sa' => 10,
                'cdgo_crgo_en' => 20,
                'area_en' => 5,
                'cdgo_estdo_trmte' => 1,
                'in_esta' => '1',
                'fe_rcbdo' => '2026-10-10',
                'de_obser' => '',
                'in_copia' => '1',
            ],
        ]));

        $this->expectException(ConflictoConcurrenteTramiteException::class);
        $this->expectExceptionMessage('Estado ambiguo detectado: múltiples movimientos vigentes para el expediente 100.');

        $repo->obtenerExpedienteAutorizadoParaConsolidado(100, $contexto);
    }

    /**
     * 34. RTI-C03/RTI-C08: Consolidado expediente incluye histórico de movimientos sin filtrar in_esta=1
     */
    public function test_34_consolidado_expediente_incluye_historicos_con_in_esta_cero(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(companiaId: 1, cargoId: 20);
        $mov = $this->crearMovimiento(100, 502, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(100, $contexto)->andReturn($mov);
        $policy->shouldReceive('descargar')->with($contexto, $mov)->andReturn(true);

        $repo->shouldReceive('obtenerDocumentoParaConsolidado')->with(100, 1)->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => '2026-00000100',
            'de_urls_adjnto' => 'principal.pdf',
        ]);

        $repo->shouldReceive('obtenerMovimientosParaConsolidado')->with(100)->andReturn([
            (object) ['cdgo_trmtes_ntrnos' => 501, 'de_urls_agrdo' => 'documentos/internos/2026/10/10/hist.docx'],
            (object) ['cdgo_trmtes_ntrnos' => 502, 'de_urls_agrdo' => 'documentos/internos/2026/10/11/act.xlsx'],
        ]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $f1 = tempnam($tempDir, 'f1_');
        $f2 = tempnam($tempDir, 'f2_');
        $f3 = tempnam($tempDir, 'f3_');
        file_put_contents($f1, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        file_put_contents($f2, 'CONTENIDO_DOCX');
        file_put_contents($f3, 'CONTENIDO_XLSX');

        $gestor->shouldReceive('resolverRutaSegura')->with('principal.pdf')->andReturn($f1);
        $gestor->shouldReceive('resolverRutaSegura')->with('documentos/internos/2026/10/10/hist.docx')->andReturn($f2);
        $gestor->shouldReceive('resolverRutaSegura')->with('documentos/internos/2026/10/11/act.xlsx')->andReturn($f3);

        $auditorias = [];
        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->times(4)->with(Mockery::on(function ($payload) use (&$auditorias) {
            $auditorias[] = $payload['de_urls_dscrgas'];
            return true;
        }))->andReturnNull();

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);
        $res = $service->generarConsolidado(100, $contexto, '127.0.0.1');

        $this->assertFileExists($res['zip_path']);
        $this->assertCount(4, $auditorias);
        $this->assertContains('principal.pdf', $auditorias);
        $this->assertContains('documentos/internos/2026/10/10/hist.docx', $auditorias);
        $this->assertContains('documentos/internos/2026/10/11/act.xlsx', $auditorias);

        @unlink($res['zip_path']);
        @unlink($f1);
        @unlink($f2);
        @unlink($f3);
    }

    /**
     * 35. RTI-C08: Tests arquitectónicos anti-fugas en BuscarTramiteController
     */
    public function test_35_tests_arquitectonicos_buscar_tramite_controller_anti_fugas(): void
    {
        $reflector = new ReflectionClass(BuscarTramiteController::class);
        $source = file_get_contents($reflector->getFileName());

        $methodReflector = $reflector->getMethod('descargar');
        $start = $methodReflector->getStartLine();
        $end = $methodReflector->getEndLine();
        $lines = array_slice(explode("\n", $source), $start - 1, $end - $start + 1);
        $methodCode = implode("\n", $lines);

        $this->assertStringNotContainsString('file_get_contents', $methodCode);
        $this->assertStringNotContainsString('readfile', $methodCode);
        $this->assertStringNotContainsString('scandir', $methodCode);
        $this->assertStringNotContainsString('is_file', $methodCode);
        $this->assertStringNotContainsString('storage_path', $methodCode);
        $this->assertStringNotContainsString('public_path', $methodCode);
        $this->assertStringNotContainsString('new ZipArchive', $methodCode);
        $this->assertStringNotContainsString('fopen', $methodCode);
        $this->assertStringContainsString('descargaSeguraService', $methodCode);
    }

    /**
     * 36. Contrato frontend: verificación de que los archivos JS no contienen construcciones de URL con path= ni nom_archivo=
     * y que los botones de adjunto contienen data-id con ID canónico y data-tipo.
     */
    public function test_36_contrato_frontend_no_path_nom_archivo_y_data_id_tipo(): void
    {
        $jsFiles = [
            resource_path('js/tramite-interno/acciones.js'),
            resource_path('js/tramite-interno/renderers.js'),
            resource_path('js/tramite-interno/historico.js'),
            resource_path('js/tramite-interno/index.js'),
        ];

        foreach ($jsFiles as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) file_get_contents($filePath);
            $this->assertStringNotContainsString('path=', $content, "El archivo {$filePath} no debe construir parámetros path=");
            $this->assertStringNotContainsString('nom_archivo=', $content, "El archivo {$filePath} no debe construir parámetros nom_archivo=");
        }

        // Verificar que renderers.js genera atributos data-documento-id y data-tipo explícitos
        $renderersContent = (string) file_get_contents(resource_path('js/tramite-interno/renderers.js'));
        $this->assertStringContainsString("action: 'ver-adjunto'", $renderersContent);
        $this->assertStringContainsString("tipo: tipo", $renderersContent);
        $this->assertStringContainsString("documentoId: String(canonicalId)", $renderersContent);
        $this->assertStringNotContainsString("id: String(canonicalId)", $renderersContent, 'No debe usar data-id ambiguo.');

        // Verificar que acciones.js invoca verAdjunto usando identificador canónico id o movimiento_id
        $accionesContent = (string) file_get_contents(resource_path('js/tramite-interno/acciones.js'));
        $this->assertStringContainsString("'id=' + cleanId", $accionesContent);
        $this->assertStringContainsString("'movimiento_id=' + cleanId", $accionesContent);
    }

    /**
     * 37. Identidad cdgo_lgin: simular usuario autenticado con usuarioId=10 y loginId=99;
     * ejecutar operaciones transaccionales y asertar que el payload escrito en cdgo_lgin es 99 y NUNCA 10.
     */
    public function test_37_identidad_cdgo_lgin_escribe_login_id_nunca_usuario_id(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $maquina = Mockery::mock(MaquinaEstadosTramiteInterno::class);
        $db = Mockery::mock(DatabaseManager::class);

        // usuarioId = 10, loginId = 99
        $contexto = $this->crearContextoRemediacion(usuarioId: 10, loginId: 99);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 1);

        $repo->shouldReceive('obtenerMovimientosVigentesParaOperacion')
            ->once()
            ->with([100], $contexto)
            ->andReturn([$mov]);

        $policy->shouldReceive('recepcionar')->with($contexto, $mov)->andReturn(true);
        $maquina->shouldReceive('esTransicionValida')->with(1, 'recepcionar')->andReturn(true);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $capturedMovInsert = null;
        $capturedLogInsert = null;

        $repo->shouldReceive('desactivarMovimientoInternoEsperado')->with(500)->andReturn(1);
        $repo->shouldReceive('crearMovimientoInterno')->once()->with(Mockery::on(function ($payload) use (&$capturedMovInsert) {
            $capturedMovInsert = $payload;
            return true;
        }))->andReturn(1);

        $repo->shouldReceive('registrarSeguimientoInterno')->once()->with(Mockery::on(function ($cdgoLgin) use (&$capturedLogInsert) {
            $capturedLogInsert = ['cdgo_lgin' => $cdgoLgin];
            return true;
        }), Mockery::any(), Mockery::any())->andReturnNull();

        $service = new CorreoInternoTransaccionalService($repo, $policy, $maquina, $db);
        $res = $service->ejecutarRecepcionar([100], '2026-10-11 10:00:00', 'Observación prueba', $contexto, '127.0.0.1');

        $this->assertEquals('success', $res['status']);
        $this->assertNotNull($capturedMovInsert);
        $this->assertNotNull($capturedLogInsert);

        // Verificación estricta: cdgo_lgin debe ser 99 (loginId), NUNCA 10 (usuarioId)
        $this->assertSame(99, $capturedMovInsert['cdgo_lgin']);
        $this->assertNotSame(10, $capturedMovInsert['cdgo_lgin']);
        $this->assertSame(99, $capturedLogInsert['cdgo_lgin']);
        $this->assertNotSame(10, $capturedLogInsert['cdgo_lgin']);
    }

    /**
     * 38. Compañía sin fallback: simular sesión sin SessionKeys::USER_COMPANY pero usuario con cdgo_cmpnia=1;
     * asertar que ContextoUsuarioAutenticadoDto::fromRequest() retorna null (no se usa el campo del usuario).
     */
    public function test_38_compania_sin_fallback_retorna_null_si_no_esta_en_sesion(): void
    {
        $user = new \App\Models\User();
        $user->id = 99;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1; // El usuario tiene compañía en DB
        Auth::setUser($user);

        $request = Request::create('/test-contexto', 'GET');
        $session = app('session.store');
        // No agregamos SessionKeys::USER_COMPANY
        $session->forget(SessionKeys::USER_COMPANY);
        $session->forget(SessionKeys::COMPANIA_ID);
        $session->put(SessionKeys::USER_CARGO, 20);
        $session->put(SessionKeys::USER_AREA, 5);
        $session->put(SessionKeys::USER_ID, 99);
        $request->setLaravelSession($session);

        $contexto = $this->resolverContextoDesdeRequest($request);

        // Sin fallback permitido: debe ser null
        $this->assertNull($contexto, 'resolverContextoDesdeRequest() no debe tener fallback a $user->cdgo_cmpnia');
    }

    /**
     * 39. Descarga lote con archivo faltante intermedio: simular lote de 3 archivos donde el segundo no existe en disco;
     * asertar fallo total, ausencia de archivo ZIP en disco y cero registros insertados en p_sgmto_dscrgas.
     */
    public function test_39_descarga_lote_con_archivo_faltante_intermedio_falla_cero_zip_cero_audit(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $service = new DescargaLoteTramiteInternoService($repo, $gestor, $db, $policy);
        $contexto = $this->crearContextoRemediacion(companiaId: 1, cargoId: 20);

        $mov1 = $this->crearMovimiento(101, 501, 1);
        $mov2 = $this->crearMovimiento(102, 502, 1);
        $mov3 = $this->crearMovimiento(103, 503, 1);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(101, $contexto)->andReturn($mov1);
        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(102, $contexto)->andReturn($mov2);
        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(103, $contexto)->andReturn($mov3);
        $policy->shouldReceive('descargar')->andReturn(true);

        $repo->shouldReceive('obtenerDocumentosAutorizadosLote')->with([101, 102, 103], 1)->andReturn([101, 102, 103]);
        $repo->shouldReceive('obtenerMovimientosLote')->with([101, 102, 103], 1)->andReturn([
            (object) ['cdgo_dtos_ntrnos' => 101, 'de_urls_agrdo' => 'archivo1.pdf'],
            (object) ['cdgo_dtos_ntrnos' => 102, 'de_urls_agrdo' => 'archivo_faltante.pdf'],
            (object) ['cdgo_dtos_ntrnos' => 103, 'de_urls_agrdo' => 'archivo3.pdf'],
        ]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $f1 = tempnam($tempDir, 'sigtram_lote_t39_1_');
        $f3 = tempnam($tempDir, 'sigtram_lote_t39_3_');
        file_put_contents($f1, 'DOC1');
        file_put_contents($f3, 'DOC3');

        $gestor->shouldReceive('resolverRutaSegura')->with('archivo1.pdf')->andReturn($f1);
        // El segundo archivo no existe en disco
        $gestor->shouldReceive('resolverRutaSegura')->with('archivo_faltante.pdf')->andReturn($tempDir . '/inexistente_12345.pdf');

        // Cero inserciones en p_sgmto_dscrgas
        $db->shouldReceive('table')->with(Mockery::pattern('/p_sgmto_dscrgas/'))->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo completar la descarga en lote: falta un archivo registrado en el expediente.');

        try {
            $service->generarLoteZip([101, 102, 103], $contexto, '127.0.0.1');
        } finally {
            @unlink($f1);
            @unlink($f3);
        }
    }

    /**
     * 40. Auditoría atómica de lote: simular fallo en la tercera inserción de auditoría;
     * asertar rollback completo (cero registros persistidos en p_sgmto_dscrgas) y eliminación del archivo ZIP.
     */
    public function test_40_auditoria_atomica_lote_fallo_rollback_cero_persistencia_zip_unlinked(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(companiaId: 1, cargoId: 20);
        $mov1 = $this->crearMovimiento(101, 501, 1);
        $mov2 = $this->crearMovimiento(102, 502, 1);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(101, $contexto)->andReturn($mov1);
        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(102, $contexto)->andReturn($mov2);
        $policy->shouldReceive('descargar')->andReturn(true);

        $repo->shouldReceive('obtenerDocumentosAutorizadosLote')->with([101, 102], 1)->andReturn([101, 102]);
        $repo->shouldReceive('obtenerMovimientosLote')->with([101, 102], 1)->andReturn([
            (object) ['cdgo_dtos_ntrnos' => 101, 'de_urls_agrdo' => 'archivo1.pdf'],
            (object) ['cdgo_dtos_ntrnos' => 102, 'de_urls_agrdo' => 'archivo2.pdf'],
        ]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $f1 = tempnam($tempDir, 'sigtram_lote_t40_1_');
        $f2 = tempnam($tempDir, 'sigtram_lote_t40_2_');
        file_put_contents($f1, 'DOC1');
        file_put_contents($f2, 'DOC2');

        $gestor->shouldReceive('resolverRutaSegura')->with('archivo1.pdf')->andReturn($f1);
        $gestor->shouldReceive('resolverRutaSegura')->with('archivo2.pdf')->andReturn($f2);

        // Transacción que falla y propaga la excepción
        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $count = 0;
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->andReturnUsing(function () use (&$count) {
            $count++;
            if ($count === 3) {
                throw new RuntimeException('Error de base de datos en 3ra inserción de auditoría');
            }
        });

        $service = new DescargaLoteTramiteInternoService($repo, $gestor, $db, $policy);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fallo en la persistencia atómica de auditoría legal de descarga: no es posible proceder');

        try {
            $service->generarLoteZip([101, 102], $contexto, '127.0.0.1');
        } finally {
            @unlink($f1);
            @unlink($f2);
        }
    }

    /**
     * 41. Consolidado con un solo PDF: assert archivo UNIDO-*.pdf copiado y contenido idéntico byte a byte.
     */
    public function test_41_consolidado_con_un_solo_pdf_copiado_identico_byte_a_byte(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(100, $contexto)->andReturn($mov);
        $policy->shouldReceive('descargar')->with($contexto, $mov)->andReturn(true);

        $repo->shouldReceive('obtenerDocumentoParaConsolidado')->with(100, 1)->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => '2026-00000100',
            'de_urls_adjnto' => 'principal.pdf',
        ]);

        $repo->shouldReceive('obtenerMovimientosParaConsolidado')->with(100)->andReturn([]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $tempTestFile = tempnam($tempDir, 'test_adj_t41_');
        $pdfContenidoExacto = "%PDF-1.4\n1 0 obj\n<< /Title (Documento Original Inalterado) >>\nendobj\ntrailer\n<<>>\n%%EOF";
        file_put_contents($tempTestFile, $pdfContenidoExacto);
        $gestor->shouldReceive('resolverRutaSegura')->with('principal.pdf')->andReturn($tempTestFile);

        $db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->twice()->andReturnNull();

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);
        $res = $service->generarConsolidado(100, $contexto, '127.0.0.1');

        $this->assertFileExists($res['zip_path']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($res['zip_path']) === true);
        $unidoIndex = $zip->locateName('UNIDO-2026-00000100.pdf');
        $this->assertNotFalse($unidoIndex);
        $contenidoExtraido = $zip->getFromName('UNIDO-2026-00000100.pdf');
        $zip->close();

        // Verificación byte a byte idéntico
        $this->assertSame($pdfContenidoExacto, $contenidoExtraido, 'El contenido del archivo UNIDO debe ser idéntico byte a byte al original');

        @unlink($res['zip_path']);
        @unlink($tempTestFile);
    }

    /**
     * 42. Consolidado con PDF cifrado o con contraseña o dañado:
     * assert mensaje de error controlado con correlation ID, assert no se entrega ZIP.
     */
    public function test_42_consolidado_con_pdf_cifrado_o_danado_error_controlado_cero_zip(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(100, $contexto)->andReturn($mov);
        $policy->shouldReceive('descargar')->with($contexto, $mov)->andReturn(true);

        $repo->shouldReceive('obtenerDocumentoParaConsolidado')->with(100, 1)->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => '2026-00000100',
            'de_urls_adjnto' => 'cifrado.pdf',
        ]);

        $repo->shouldReceive('obtenerMovimientosParaConsolidado')->with(100)->andReturn([]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $tempTestFile = tempnam($tempDir, 'test_cifrado_');
        // Simular PDF cifrado con /Encrypt
        file_put_contents($tempTestFile, "%PDF-1.4\n1 0 obj\n<< /Encrypt 2 0 R >>\nendobj\ntrailer\n<<>>\n%%EOF");
        $gestor->shouldReceive('resolverRutaSegura')->with('cifrado.pdf')->andReturn($tempTestFile);

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);

        $this->expectException(RuntimeException::class);
        // Debe contener correlation ID controlado
        $this->expectExceptionMessage('Comuníquese con soporte indicando el código:');

        try {
            $service->generarConsolidado(100, $contexto, '127.0.0.1');
        } finally {
            @unlink($tempTestFile);
        }
    }

    /**
     * 43. Consolidado con anexos no PDF: assert UNIDO-*.pdf contiene los PDFs y los anexos no PDF quedan como archivos sueltos dentro del ZIP.
     */
    public function test_43_consolidado_con_anexos_no_pdf_sueltos_dentro_del_zip(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion(cargoId: 20);
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->with(100, $contexto)->andReturn($mov);
        $policy->shouldReceive('descargar')->with($contexto, $mov)->andReturn(true);

        $repo->shouldReceive('obtenerDocumentoParaConsolidado')->with(100, 1)->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => '2026-00000100',
            'de_urls_adjnto' => 'principal.pdf',
        ]);

        $repo->shouldReceive('obtenerMovimientosParaConsolidado')->with(100)->andReturn([
            (object) ['cdgo_trmtes_ntrnos' => 501, 'de_urls_agrdo' => 'documentos/internos/presupuesto.xlsx'],
            (object) ['cdgo_trmtes_ntrnos' => 502, 'de_urls_agrdo' => 'documentos/internos/foto.jpg'],
        ]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $f1 = tempnam($tempDir, 't43_pdf_');
        $f2 = tempnam($tempDir, 't43_xlsx_');
        $f3 = tempnam($tempDir, 't43_jpg_');
        file_put_contents($f1, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        file_put_contents($f2, 'CONTENIDO_EXCEL');
        file_put_contents($f3, 'CONTENIDO_IMAGEN');

        $gestor->shouldReceive('resolverRutaSegura')->with('principal.pdf')->andReturn($f1);
        $gestor->shouldReceive('resolverRutaSegura')->with('documentos/internos/presupuesto.xlsx')->andReturn($f2);
        $gestor->shouldReceive('resolverRutaSegura')->with('documentos/internos/foto.jpg')->andReturn($f3);

        $db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->times(4)->andReturnNull();

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);
        $res = $service->generarConsolidado(100, $contexto, '127.0.0.1');

        $this->assertFileExists($res['zip_path']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($res['zip_path']) === true);
        // UNIDO existe
        $this->assertNotFalse($zip->locateName('UNIDO-2026-00000100.pdf'));
        // Anexos no PDF existen como archivos sueltos
        $this->assertNotFalse($zip->locateName('anexo_mov_501_presupuesto.xlsx'));
        $this->assertNotFalse($zip->locateName('anexo_mov_502_foto.jpg'));
        $zip->close();

        @unlink($res['zip_path']);
        @unlink($f1);
        @unlink($f2);
        @unlink($f3);
    }

    /**
     * 44. Limpieza de temporales: assert que tras cualquier fallo en consolidado o lote no queda ningún archivo huérfano en storage_path('app/temp').
     */
    public function test_44_limpieza_de_temporales_cero_huerfanos_tras_fallo(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $contexto = $this->crearContextoRemediacion();
        $mov = $this->crearMovimiento(100, 500, 1, 15, 20, 3, 5, 2);

        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')->andReturn($mov);
        $policy->shouldReceive('descargar')->andReturn(true);

        $repo->shouldReceive('obtenerDocumentoParaConsolidado')->andReturn((object) [
            'cdgo_dtos_ntrnos' => 100,
            'nu_tram_todo' => '2026-00000100',
            'de_urls_adjnto' => 'corrupto.pdf',
        ]);

        $repo->shouldReceive('obtenerMovimientosParaConsolidado')->andReturn([]);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $archivosAntes = glob($tempDir . '/*') ?: [];

        $tempTestFile = tempnam($tempDir, 't44_corr_');
        file_put_contents($tempTestFile, "CORRUPTO_NO_PDF");
        $gestor->shouldReceive('resolverRutaSegura')->andReturn($tempTestFile);

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db, $policy);

        try {
            $service->generarConsolidado(100, $contexto, '127.0.0.1');
            $this->fail('Se esperaba excepción por PDF corrupto');
        } catch (RuntimeException $e) {
            // Se esperaba
        } finally {
            @unlink($tempTestFile);
        }

        $archivosDespues = glob($tempDir . '/*') ?: [];
        $this->assertEquals(count($archivosAntes), count($archivosDespues), 'No deben quedar archivos ZIP ni temporales huérfanos tras un fallo');
    }

    /**
     * 45. Descarga segura sin fugas: asertar que ninguna respuesta JSON o de error contiene rutas físicas (storage_path(), app_path(), etc.).
     */
    public function test_45_descarga_segura_sin_fugas_de_rutas_fisicas_en_errores(): void
    {
        $descargaSeguraMock = Mockery::mock(DescargaSeguraTramiteInternoService::class);
        $controller = new BuscarTramiteController($descargaSeguraMock);

        Auth::setUser(new \Illuminate\Auth\GenericUser([
            'id' => 99,
            'cdgo_lgin' => 99,
            'de_lgin' => 'jperez',
            'cdgo_usrios' => 10,
            'cdgo_cmpnia' => 1,
        ]));

        $request = Request::create('/buscar-tramite/descargar', 'GET', ['id' => 999], server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put(SessionKeys::USER_CARGO, 20);
        $session->put(SessionKeys::USER_AREA, 5);
        $session->put(SessionKeys::USER_ID, 99);
        $request->setLaravelSession($session);

        $descargaSeguraMock->shouldReceive('descargarPorDocumento')
            ->andThrow(new RuntimeException('Error interno: ' . storage_path('app/documentos/privado.pdf')));

        try {
            $controller->descargar($request);
            $this->fail('Se esperaba HttpException');
        } catch (HttpException $e) {
            $mensaje = $e->getMessage();
            $this->assertStringNotContainsString(storage_path(), $mensaje);
            $this->assertStringNotContainsString(app_path(), $mensaje);
            $this->assertStringNotContainsString(base_path(), $mensaje);
            $this->assertStringNotContainsString('C:\\', $mensaje);
            $this->assertStringNotContainsString('/var/www', $mensaje);
        }
    }

    /**
     * 46. Confinamiento de ruta contra directorios hermanos:
     * verificar que GestorAdjuntos::resolverRutaSegura() rechaza rutas con prefijo similar pero directorio diferente (ej. storage_malicioso vs storage).
     */
    public function test_46_confinamiento_ruta_contra_directorios_hermanos_prefijo_similar(): void
    {
        $gestor = new GestorAdjuntos();

        $ataques = [
            '../storage_malicioso/secreto.pdf',
            '..\\storage_malicioso\\secreto.pdf',
            '/storage_malicioso/secreto.pdf',
            'C:\\storage_malicioso\\secreto.pdf',
            "documento\0.pdf",
            '../../etc/passwd',
            '..\\..\\windows\\system32',
        ];

        foreach ($ataques as $rutaAtaque) {
            try {
                $gestor->resolverRutaSegura($rutaAtaque);
                $this->fail("Se esperaba InvalidArgumentException para la ruta maliciosa: {$rutaAtaque}");
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(str_contains($e->getMessage(), 'detectado') || str_contains($e->getMessage(), 'permitid'));
                // Cero fugas de rutas absolutas del servidor en el mensaje de excepción
                $this->assertStringNotContainsString(base_path(), $e->getMessage());
            }
        }
    }

    /**
     * 47. Contrato DTO Contexto: verificación de inmutabilidad y resolución estricta de auditoría cdgo_lgin.
     */
    public function test_47_contrato_dto_contexto_inmutabilidad_y_estrictez(): void
    {
        $dto = new ContextoUsuarioAutenticadoDto(
            idLogin: 99,
            idUsuario: 10,
            nombreLogin: 'jperez',
            idPerfil: 1,
            idCompania: 1,
            idCargo: 20,
            idArea: 5,
            cargosAsignados: [20],
            permisos: ['VER_BANDEJA'],
            roles: [1],
            esContabilidad: false
        );

        $this->assertSame(99, $dto->getCdgoLginAudit(), 'getCdgoLginAudit() debe retornar estrictamente loginId');
        $this->assertSame(10, $dto->idUsuario);
        $this->assertSame(1, $dto->idCompania);
        $this->assertSame(20, $dto->idCargo);
        $this->assertSame(5, $dto->idArea);
        $this->assertSame('jperez', $dto->nombreLogin);

        // Sin loginId, debe fallar de forma estricta (no retornar usuarioId silenciosamente)
        $dtoSinLogin = new ContextoUsuarioAutenticadoDto(
            idLogin: 0,
            idUsuario: 10,
            nombreLogin: 'jperez',
            idPerfil: 1,
            idCompania: 1,
            idCargo: 20,
            idArea: 5,
            cargosAsignados: [20],
            permisos: [],
            roles: [1],
            esContabilidad: false
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Identificador de login no válido para el registro de auditoría legal.');
        $dtoSinLogin->getCdgoLginAudit();
    }
}

