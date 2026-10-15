<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Exceptions\ContextoInvalidoException;
use App\Models\User;
use App\Services\Core\ContextoUsuarioAutenticadoService;
use App\Support\SessionKeys;
use Mockery;
use Tests\TestCase;

class ContextoUsuarioAutenticadoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function crearUserMock(int $loginId = 10, int $usuarioId = 5, string $login = 'jperez'): User
    {
        $user = new User();
        $user->cdgo_lgin = $loginId;
        $user->cdgo_usrios = $usuarioId;
        $user->de_lgin = $login;
        $user->cdgo_prfle = 3;
        return $user;
    }

    /**
     * Caso 1: Login con una compañía y un cargo válidos resuelve DTO canónico tipado.
     */
    public function test_01_resuelve_contexto_valido_con_un_login_compania_y_cargo(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        session([
            SessionKeys::COMPANIA_ID => 2,
            SessionKeys::CARGO_ID => 15,
        ]);

        $dtoEsperado = new ContextoUsuarioAutenticadoDto(
            idLogin: 10,
            idUsuario: 5,
            nombreLogin: 'jperez',
            idPerfil: 3,
            idCompania: 2,
            idCargo: 15,
            idArea: 7,
            cargosAsignados: [15],
            nombreCompania: 'MUNICIPALIDAD DE PUENTE PIEDRA',
            nombreCargo: 'ESPECIALISTA TRAMITE',
            nombreArea: 'SUBGERENCIA DE TRAMITE'
        );

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldReceive('validarYObtenerContexto')
            ->once()
            ->with(10, 2, 15)
            ->andReturn($dtoEsperado);

        $service = new ContextoUsuarioAutenticadoService($repoMock);
        $contexto = $service->resolverContextoObligatorio();

        $this->assertSame(10, $contexto->idLogin);
        $this->assertSame(5, $contexto->idUsuario);
        $this->assertSame('jperez', $contexto->nombreLogin);
        $this->assertSame(2, $contexto->idCompania);
        $this->assertSame(15, $contexto->idCargo);
        $this->assertSame(7, $contexto->idArea);
        $this->assertSame(10, $contexto->getCdgoLginAudit());
    }

    /**
     * Caso 2: Login con múltiples compañías y cargos valida estrictamente los activos.
     */
    public function test_02_login_con_multiples_companias_valida_la_seleccionada(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        session([
            SessionKeys::COMPANIA_ID => 3,
            SessionKeys::CARGO_ID => 22,
        ]);

        $dtoEsperado = new ContextoUsuarioAutenticadoDto(
            idLogin: 10,
            idUsuario: 5,
            nombreLogin: 'jperez',
            idPerfil: 3,
            idCompania: 3,
            idCargo: 22,
            idArea: 9,
            cargosAsignados: [22, 23],
            nombreCompania: 'PALACIO CULTURA',
            nombreCargo: 'COORDINADOR',
            nombreArea: 'DIRECCION DE CULTURA'
        );

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldReceive('validarYObtenerContexto')
            ->once()
            ->with(10, 3, 22)
            ->andReturn($dtoEsperado);

        $service = new ContextoUsuarioAutenticadoService($repoMock);
        $contexto = $service->resolverContextoObligatorio();

        $this->assertSame(3, $contexto->idCompania);
        $this->assertSame(22, $contexto->idCargo);
        $this->assertTrue($contexto->cargoAsignadoValido());
    }

    /**
     * Caso 3: Compañía no asignada, inactiva o adulterada produce fail-closed.
     */
    public function test_03_compania_no_asignada_o_inactiva_falla_cerrado(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        // Intento de acceder a empresa 1 (que en BD está inactiva)
        session([
            SessionKeys::COMPANIA_ID => 1,
            SessionKeys::CARGO_ID => 15,
        ]);

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldReceive('validarYObtenerContexto')
            ->twice()
            ->with(10, 1, 15)
            ->andReturn(null);

        $service = new ContextoUsuarioAutenticadoService($repoMock);

        $this->assertNull($service->obtenerContextoActivo());

        $this->expectException(ContextoInvalidoException::class);
        $service->resolverContextoObligatorio();
    }

    /**
     * Caso 4: Cargo no asignado o perteneciente a otra compañía produce fail-closed.
     */
    public function test_04_cargo_no_asignado_o_de_otra_compania_falla_cerrado(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        session([
            SessionKeys::COMPANIA_ID => 2,
            SessionKeys::CARGO_ID => 999, // Cargo inexistente o de otra empresa
        ]);

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldReceive('validarYObtenerContexto')
            ->twice()
            ->with(10, 2, 999)
            ->andReturn(null);

        $service = new ContextoUsuarioAutenticadoService($repoMock);

        $this->assertNull($service->obtenerContextoActivo());

        $this->expectException(ContextoInvalidoException::class);
        $service->resolverContextoObligatorio();
    }

    /**
     * Caso 5: Área incoherente con cargo o compañía produce fallo controlado.
     */
    public function test_05_area_incoherente_produce_fallo_cerrado(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        session([
            SessionKeys::COMPANIA_ID => 2,
            SessionKeys::CARGO_ID => 15,
        ]);

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldReceive('validarYObtenerContexto')
            ->once()
            ->with(10, 2, 15)
            ->andReturn(null);

        $service = new ContextoUsuarioAutenticadoService($repoMock);

        $this->expectException(ContextoInvalidoException::class);
        $service->resolverContextoObligatorio();
    }

    /**
     * Caso 6: Sesión sin compañía o sin cargo falla cerrado de inmediato.
     */
    public function test_06_sesion_parcial_o_vacia_falla_cerrado(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        session()->forget([SessionKeys::COMPANIA_ID, SessionKeys::CARGO_ID]);

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldNotReceive('validarYObtenerContexto');

        $service = new ContextoUsuarioAutenticadoService($repoMock);

        $this->assertNull($service->obtenerContextoActivo());

        $this->expectException(ContextoInvalidoException::class);
        $service->resolverContextoObligatorio();
    }

    /**
     * Caso 7: Distinción real e inequívoca entre idLogin e idUsuario en auditoría.
     */
    public function test_07_distincion_inequivoca_entre_login_id_y_usuario_id(): void
    {
        $dto = new ContextoUsuarioAutenticadoDto(
            idLogin: 888,
            idUsuario: 777,
            nombreLogin: 'operador_sigtram',
            idPerfil: 2,
            idCompania: 2,
            idCargo: 10,
            idArea: 4
        );

        $this->assertSame(888, $dto->idLogin);
        $this->assertSame(777, $dto->idUsuario);
        $this->assertNotSame($dto->idLogin, $dto->idUsuario);
        $this->assertSame(888, $dto->getCdgoLginAudit());
    }

    /**
     * Caso 8: Establecer contexto nuevo persiste claves canónicas y actualiza sesión.
     */
    public function test_08_establecer_contexto_persiste_canonico_y_actualiza_sesion(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        $dtoEsperado = new ContextoUsuarioAutenticadoDto(
            idLogin: 10,
            idUsuario: 5,
            nombreLogin: 'jperez',
            idPerfil: 3,
            idCompania: 2,
            idCargo: 15,
            idArea: 7,
            nombreCompania: 'MUNICIPALIDAD DE PUENTE PIEDRA',
            nombreCargo: 'ESPECIALISTA',
            nombreArea: 'TRAMITE'
        );

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldReceive('validarYObtenerContexto')
            ->once()
            ->with(10, 2, 15)
            ->andReturn($dtoEsperado);

        $service = new ContextoUsuarioAutenticadoService($repoMock);
        $resultado = $service->establecerContexto(2, 15);

        $this->assertSame(2, session(SessionKeys::COMPANIA_ID));
        $this->assertSame(15, session(SessionKeys::CARGO_ID));
        $this->assertSame(7, session(SessionKeys::AREA_ID));
        $this->assertSame(10, session(SessionKeys::LOGIN_ID));
        $this->assertSame(5, session(SessionKeys::USUARIO_ID));
        $this->assertSame($dtoEsperado, $resultado);
    }

    /**
     * Caso 9: Limpiar contexto elimina claves canónicas de sesión.
     */
    public function test_09_limpiar_contexto_purga_sesion(): void
    {
        session([
            SessionKeys::COMPANIA_ID => 2,
            SessionKeys::CARGO_ID => 15,
            SessionKeys::AREA_ID => 7,
            SessionKeys::LOGIN_ID => 10,
        ]);

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);

        $service = new ContextoUsuarioAutenticadoService($repoMock);
        $service->limpiarContexto();

        $this->assertNull(session(SessionKeys::COMPANIA_ID));
        $this->assertNull(session(SessionKeys::CARGO_ID));
        $this->assertNull(session(SessionKeys::AREA_ID));
        $this->assertNull(session(SessionKeys::LOGIN_ID));
    }

    /**
     * Caso 10: Establecer contexto escribe únicamente claves canónicas y cero claves legacy o aliases prohibidos.
     */
    public function test_10_establecer_contexto_no_escribe_claves_legacy_ni_dual_writes(): void
    {
        $user = $this->crearUserMock(10, 5, 'jperez');
        $this->actingAs($user);

        $dtoEsperado = new ContextoUsuarioAutenticadoDto(
            idLogin: 10,
            idUsuario: 5,
            nombreLogin: 'jperez',
            idPerfil: 3,
            idCompania: 2,
            idCargo: 15,
            idArea: 7,
            nombreCompania: 'MDPP',
            nombreCargo: 'ESPECIALISTA',
            nombreArea: 'TRAMITE'
        );

        $repoMock = Mockery::mock(ContextoUsuarioAutenticadoRepositoryInterface::class);
        $repoMock->shouldReceive('validarYObtenerContexto')
            ->once()
            ->with(10, 2, 15)
            ->andReturn($dtoEsperado);

        $service = new ContextoUsuarioAutenticadoService($repoMock);
        $service->establecerContexto(2, 15);

        // Claves prohibidas / legacy eliminadas que NUNCA deben existir en sesión:
        $this->assertNull(session('sigtram_compania_id'));
        $this->assertNull(session('usIds'));
        $this->assertNull(session('usIdsCargo'));
        $this->assertNull(session('usIdsArea'));
        $this->assertSame(10, session(SessionKeys::LOGIN_ID));
    }
}
