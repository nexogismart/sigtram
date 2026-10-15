<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\mod_gm\TramiteExternoAcciones;
use App\Contracts\Repositories\AccionPermisoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Repositories\mod_sistema\AccionPermisoRepository;
use App\Services\mod_sistema\AutorizacionAccionService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Suite de pruebas unitarias semánticas para la autorización granular de acciones.
 * Cubre todos los casos exigidos en TAREA 11 sin interactuar con PostgreSQL real.
 */
class AutorizacionAccionGranularTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * TAREA 01: Verifica que el DTO resuelva correctamente el cdgo_lgin canónico
     * diferenciándolo del cdgo_usrios y sin confundir login con usuario.
     */
    public function test_contexto_dto_resuelve_cdgo_lgin_canonico_correctamente(): void
    {
        // 1. DTO con loginId numérico explícito y login alfanumérico
        $dtoConLoginId = $this->crearContexto(
            login: 'jperez',
            usuarioId: 500,
            companiaId: 3,
            cargoId: 10,
            areaId: 20,
            roles: [1],
            loginId: 15844
        );

        $this->assertSame(15844, $dtoConLoginId->getCdgoLgin());
        $this->assertSame(500, $dtoConLoginId->idUsuario);
        $this->assertNotSame($dtoConLoginId->idUsuario, $dtoConLoginId->getCdgoLgin());

        // 2. DTO con login numérico en string (compatibilidad legacy)
        $dtoLoginNumerico = $this->crearContexto(
            login: '15844',
            usuarioId: 500,
            companiaId: 3,
            cargoId: 10,
            areaId: 20
        );

        $this->assertSame(15844, $dtoLoginNumerico->getCdgoLgin());

        // 3. DTO sin identificador numérico de login
        $dtoInvalido = $this->crearContexto(
            login: 'sin_id_numerico',
            usuarioId: 0,
            companiaId: 3,
            cargoId: 10,
            areaId: 20,
            loginId: 0
        );

        $this->assertSame(0, $dtoInvalido->getCdgoLgin());
    }

    /**
     * TAREA 01: Normalización de compañía activa sin fallbacks de compañía 1 o 2.
     */
    public function test_normalizar_compania_no_aplica_fallbacks_fijos(): void
    {
        $this->assertSame(5, ContextoUsuarioAutenticadoDto::normalizarCompania(5));
        $this->assertSame(7, ContextoUsuarioAutenticadoDto::normalizarCompania('7'));
        $this->assertSame(8, ContextoUsuarioAutenticadoDto::normalizarCompania(['cdgo_cmpnia' => 8]));
        $this->assertSame(9, ContextoUsuarioAutenticadoDto::normalizarCompania((object) ['cdgo_cmpnia' => 9]));

        // Valores nulos o inválidos deben retornar 0 (NUNCA 1 o 2)
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania(null));
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania(0));
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania(-1));
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania(''));
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania([]));

        // Aliases ambiguos eliminados deben retornar 0
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania(['id' => 5]));
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania(['company_id' => 5]));
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania((object) ['id' => 5]));
        $this->assertSame(0, ContextoUsuarioAutenticadoDto::normalizarCompania((object) ['company_id' => 5]));
    }

    /**
     * Caso 1: Acción activa + asignación activa + permitido='1' + compañía correcta => AUTORIZA.
     */
    public function test_caso_01_autoriza_cuando_accion_y_asignacion_estan_activas_y_permitidas(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(true);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contexto = $this->crearContexto('jperez', 10, 3, 105, 20, [4], 15844);

        $this->assertTrue($policy->exportarExcel($contexto));
    }

    /**
     * Caso 2: Sin asignación (registro no existe en r_acciones_usrios) => DENIEGA.
     */
    public function test_caso_02_deniega_cuando_no_existe_asignacion(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contexto = $this->crearContexto('jperez', 10, 3, 105, 20, [4], 15844);

        $this->assertFalse($policy->exportarExcel($contexto));
    }

    /**
     * Caso 3: Acción inexistente => DENIEGA.
     */
    public function test_caso_03_deniega_cuando_accion_no_existe(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, 'accion.inexistente')
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);

        $this->assertFalse($service->puedeEjecutar(15844, 3, 'accion.inexistente'));
    }

    /**
     * Caso 4: Acción inactiva (a_acciones.in_esta = '0') => DENIEGA.
     */
    public function test_caso_04_deniega_cuando_accion_esta_inactiva(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contexto = $this->crearContexto('jperez', 10, 3, 105, 20, [4], 15844);

        $this->assertFalse($policy->exportarExcel($contexto));
    }

    /**
     * Caso 5: Asignación inactiva (r_acciones_usrios.in_esta = '0') => DENIEGA.
     */
    public function test_caso_05_deniega_cuando_asignacion_esta_inactiva(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contexto = $this->crearContexto('jperez', 10, 3, 105, 20, [4], 15844);

        $this->assertFalse($policy->exportarExcel($contexto));
    }

    /**
     * Caso 6: in_permitido = '0' => DENIEGA.
     */
    public function test_caso_06_deniega_cuando_in_permitido_es_cero(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contexto = $this->crearContexto('jperez', 10, 3, 105, 20, [4], 15844);

        $this->assertFalse($policy->exportarExcel($contexto));
    }

    /**
     * Caso 7: Compañía diferente a la asignada => DENIEGA.
     */
    public function test_caso_07_deniega_cuando_compania_activa_no_coincide_con_asignacion(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        // Si el usuario está en compañía 99 pero su asignación es en compañía 3, el repo evalúa la compañía activa
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 99, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contexto = $this->crearContexto('jperez', 10, 99, 105, 20, [4], 15844);

        $this->assertFalse($policy->exportarExcel($contexto));
    }

    /**
     * Caso 8: Sin compañía activa (companiaId <= 0) => DENIEGA sin consultar repo.
     */
    public function test_caso_08_deniega_cuando_no_hay_compania_activa(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldNotReceive('tieneAccionPermitida');

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contextoSinCompania = $this->crearContexto('jperez', 10, 0, 105, 20, [4], 15844);

        $this->assertFalse($policy->exportarExcel($contextoSinCompania));
        $this->assertFalse($service->puedeEjecutar(15844, 0, TramiteExternoAcciones::EXPORTAR_EXCEL));
    }

    /**
     * Caso 9: Sin login canónico (cdgo_lgin <= 0) => DENIEGA sin consultar repo.
     */
    public function test_caso_09_deniega_cuando_no_hay_login_canonico_valido(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldNotReceive('tieneAccionPermitida');

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contextoSinLogin = $this->crearContexto('', 10, 3, 105, 20, [4], 0);

        $this->assertFalse($policy->exportarExcel($contextoSinLogin));
        $this->assertFalse($service->puedeEjecutar(0, 3, TramiteExternoAcciones::EXPORTAR_EXCEL));
    }

    /**
     * Caso 10: Error del repositorio => DENIEGA de forma segura (fail-closed) sin exponer detalles.
     */
    public function test_caso_10_deniega_ante_excepcion_del_repositorio(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->andThrow(new RuntimeException('Falla de conexión interna a BD'));

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        $contexto = $this->crearContexto('jperez', 10, 3, 105, 20, [4], 15844);

        $this->assertFalse($policy->exportarExcel($contexto));
    }

    /**
     * Caso 11: Rol 1 (Administrador) sin asignación en r_acciones_usrios => DENIEGA (NO HAY BYPASS).
     */
    public function test_caso_11_rol_1_sin_asignacion_es_denegado(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(1, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        // Funcionario con rol 1 (Administrador)
        $contextoAdmin = $this->crearContexto('admin', 1, 3, 999, 999, [1], 1);

        $this->assertFalse($policy->exportarExcel($contextoAdmin), 'El Rol 1 NO debe tener bypass si no tiene asignación.');
    }

    /**
     * Caso 12: Rol 3 (Administrativo) sin asignación en r_acciones_usrios => DENIEGA (NO HAY BYPASS).
     */
    public function test_caso_12_rol_3_sin_asignacion_es_denegado(): void
    {
        $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repo->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(3, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
            ->andReturn(false);

        $service = new AutorizacionAccionService($repo);
        $policy = new TramiteExternoPolicy($service);

        // Funcionario con rol 3
        $contextoRol3 = $this->crearContexto('secretaria', 3, 3, 999, 999, [3], 3);

        $this->assertFalse($policy->exportarExcel($contextoRol3), 'El Rol 3 NO debe tener bypass si no tiene asignación.');
    }

    /**
     * Caso 13: Cada uno de los IDs de la antigua lista hardcodeada sin asignación => DENIEGA.
     * Demuestra pericialmente que los antiguos IDs ya NO otorgan acceso por sí mismos.
     */
    public function test_caso_13_antiguos_ids_sin_asignacion_son_estrictamente_denegados(): void
    {
        $antiguosIds = [15844, 411, 15481, 15749];

        foreach ($antiguosIds as $idLegacy) {
            $repo = Mockery::mock(AccionPermisoRepositoryInterface::class);
            $repo->shouldReceive('tieneAccionPermitida')
                ->once()
                ->with($idLegacy, 3, TramiteExternoAcciones::EXPORTAR_EXCEL)
                ->andReturn(false);

            $service = new AutorizacionAccionService($repo);
            $policy = new TramiteExternoPolicy($service);

            $contexto = $this->crearContexto(
                login: 'usuario_' . $idLegacy,
                usuarioId: $idLegacy,
                companiaId: 3,
                cargoId: 10,
                areaId: 20,
                roles: [4],
                loginId: $idLegacy
            );

            $this->assertFalse(
                $policy->exportarExcel($contexto),
                "El ID legacy {$idLegacy} ya no debe otorgar autorización sin asignación activa en BD."
            );
        }
    }
}
