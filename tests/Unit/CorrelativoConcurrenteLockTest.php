<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepository;
use App\Repositories\mod_gm\mod_tram_exte\CorrelativoExpedienteLock;
use App\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use RuntimeException;

class CorrelativoConcurrenteLockTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolver_correlativo_rechaza_compania_cero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Compañía inválida para generación de correlativo.');

        $mockDb = Mockery::mock(DatabaseManager::class);
        $repo = new BuscaExpedienteRepository($mockDb);

        $repo->resolverCorrelativoAnio(0, 22, 2026);
    }

    /**
     * REQUISITO 1: Misma compañía y mismo año producen la misma clave de bloqueo
     * aunque el tipo documental sea diferente.
     */
    public function test_misma_compania_y_mismo_anio_producen_misma_clave_de_bloqueo_para_distinto_tipo_documental(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $repo = new BuscaExpedienteRepository($mockDb);

        // Tipos documentales distintos (22 = externo ordinario, 17 = simple, 40 = observado)
        $claveTipo22 = $repo->obtenerClaveSerializacion(2027, 22);
        $claveTipo17 = $repo->obtenerClaveSerializacion(2027, 17);
        $claveTipo40 = $repo->obtenerClaveSerializacion(2027, 40);

        // La clave de serialización anual es idéntica
        $this->assertSame($claveTipo22, $claveTipo17);
        $this->assertSame($claveTipo22, $claveTipo40);
        $this->assertEquals(2027, $claveTipo22);

        // Los parámetros vinculados al advisory lock [cmpnia, clave] son idénticos
        $paramsTipo22 = $repo->obtenerParametrosLock(2, 2027, 22);
        $paramsTipo17 = $repo->obtenerParametrosLock(2, 2027, 17);
        $this->assertSame($paramsTipo22, $paramsTipo17);
        $this->assertEquals([2, 2027], $paramsTipo22);
    }

    /**
     * REQUISITO 2: Compañías diferentes producen claves/ámbitos diferentes.
     */
    public function test_companias_diferentes_producen_ambitos_diferentes(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $repo = new BuscaExpedienteRepository($mockDb);

        $paramsCmp1 = $repo->obtenerParametrosLock(1, 2027);
        $paramsCmp2 = $repo->obtenerParametrosLock(2, 2027);

        $this->assertNotEquals($paramsCmp1, $paramsCmp2);
        $this->assertEquals(1, $paramsCmp1[0]);
        $this->assertEquals(2, $paramsCmp2[0]);
    }

    /**
     * REQUISITO 3: Años diferentes producen claves/ámbitos diferentes.
     */
    public function test_anios_diferentes_producen_claves_y_ambitos_diferentes(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $repo = new BuscaExpedienteRepository($mockDb);

        $clave2026 = $repo->obtenerClaveSerializacion(2026);
        $clave2027 = $repo->obtenerClaveSerializacion(2027);

        $this->assertNotEquals($clave2026, $clave2027);
        $this->assertEquals(2026, $clave2026);
        $this->assertEquals(2027, $clave2027);

        $params2026 = $repo->obtenerParametrosLock(2, 2026);
        $params2027 = $repo->obtenerParametrosLock(2, 2027);

        $this->assertNotEquals($params2026, $params2027);
        $this->assertEquals([2, 2026], $params2026);
        $this->assertEquals([2, 2027], $params2027);
    }

    /**
     * REQUISITO 4: El fallo del advisory lock se propaga fail-closed y detiene toda persistencia.
     * Comprueba que no se consulta a_anios ni se calcula MAX().
     */
    public function test_fallo_advisory_lock_se_propaga_fail_closed_y_no_consulta_anios_ni_max(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockConn = Mockery::mock(ConnectionInterface::class);

        $mockDb->shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($mockConn);

        $mockConn->shouldReceive('getDriverName')
            ->atLeast()->once()
            ->andReturn('pgsql');

        // Simulamos fallo en la adquisición del advisory lock en PostgreSQL
        $mockDb->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [2, 2026])
            ->andThrow(new RuntimeException('PostgreSQL advisory lock failure: lock timeout'));

        // REGLA FAIL-CLOSED: Al fallar el lock, NO se debe consultar a_anios ni p_dto_trmtes
        $mockDb->shouldNotReceive('table');

        $repo = new BuscaExpedienteRepository($mockDb);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PostgreSQL advisory lock failure: lock timeout');

        $repo->resolverCorrelativoAnio(2, 22, 2026);
    }

    /**
     * REQUISITO 5 & 7: Si a_anios ya existe, se utiliza la fila del año exacto con lock
     * y MAX() se ejecuta sin FOR UPDATE.
     */
    public function test_si_a_anios_ya_existe_se_utiliza_fila_del_anio_exacto_con_lock_y_max_sin_for_update(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockConn = Mockery::mock(ConnectionInterface::class);

        $mockDb->shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($mockConn);

        $mockConn->shouldReceive('getDriverName')
            ->atLeast()->once()
            ->andReturn('pgsql');

        $mockDb->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [2, 2026])
            ->andReturn(true);

        $builderAnio = Mockery::mock(Builder::class);
        $builderExp = Mockery::mock(Builder::class);

        // 1. Consulta específica por compañía y año exacto (no por orden desc del último año)
        $mockDb->shouldReceive('table')
            ->with('sistema.a_anios')
            ->once()
            ->andReturn($builderAnio);

        $builderAnio->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $builderAnio->shouldReceive('where')->with('de_anio', 2026)->once()->andReturnSelf();
        $builderAnio->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $builderAnio->shouldReceive('first')->once()->andReturn((object) [
            'cdgo_anio' => 2026,
            'de_anio' => 2026,
        ]);

        // 2. Consulta de p_dto_trmtes para calcular max + 1 SIN FOR UPDATE
        $mockDb->shouldReceive('table')
            ->with('sistema.p_dto_trmtes')
            ->once()
            ->andReturn($builderExp);

        $builderExp->shouldReceive('where')->with('cdgo_anio', 2026)->once()->andReturnSelf();
        $builderExp->shouldReceive('where')->with('cdgo_tpo_trmte', 22)->once()->andReturnSelf();
        $builderExp->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        // Garantizar que NO se aplica lockForUpdate en el query agregado
        $builderExp->shouldNotReceive('lockForUpdate');
        $builderExp->shouldReceive('max')->with('nu_expe_trmte')->once()->andReturn(150);

        $repo = new BuscaExpedienteRepository($mockDb);
        $res = $repo->resolverCorrelativoAnio(2, 22, 2026);

        $this->assertEquals(2026, $res['idAnio']);
        $this->assertEquals(2026, $res['deAnio']);
        $this->assertEquals(151, $res['nuExpe']);
    }

    /**
     * REQUISITO 6: Si no existe, se crea una sola vez bajo el bloqueo común.
     */
    public function test_si_a_anios_no_existe_se_crea_una_sola_vez_bajo_bloqueo_comun_e_inicia_en_uno(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockConn = Mockery::mock(ConnectionInterface::class);

        $mockDb->shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($mockConn);

        $mockConn->shouldReceive('getDriverName')
            ->atLeast()->once()
            ->andReturn('pgsql');

        $mockDb->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [2, 2027])
            ->andReturn(true);

        $builderAnio = Mockery::mock(Builder::class);
        $builderInsert = Mockery::mock(Builder::class);

        // Búsqueda del año exacto 2027 (no existe)
        $mockDb->shouldReceive('table')
            ->with('sistema.a_anios')
            ->once()
            ->andReturn($builderAnio);

        $builderAnio->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $builderAnio->shouldReceive('where')->with('de_anio', 2027)->once()->andReturnSelf();
        $builderAnio->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $builderAnio->shouldReceive('first')->once()->andReturn(null);

        // Inserción atómica del nuevo año
        $mockDb->shouldReceive('table')
            ->with('sistema.a_anios')
            ->once()
            ->andReturn($builderInsert);

        $builderInsert->shouldReceive('insertGetId')
            ->once()
            ->with([
                'de_anio' => 2027,
                'dc_anio' => '27',
                'cdgo_cmpnia' => 2,
            ], 'cdgo_anio')
            ->andReturn(2027);

        $repo = new BuscaExpedienteRepository($mockDb);
        $res = $repo->resolverCorrelativoAnio(2, 22, 2027);

        $this->assertEquals(2027, $res['idAnio']);
        $this->assertEquals(2027, $res['deAnio']);
        $this->assertEquals(1, $res['nuExpe']);
    }

    public function test_driver_no_pgsql_no_ejecuta_advisory_lock(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockConn = Mockery::mock(ConnectionInterface::class);

        $mockDb->shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($mockConn);

        $mockConn->shouldReceive('getDriverName')
            ->atLeast()->once()
            ->andReturn('sqlite');

        // statement() no debe llamarse para pg_advisory_xact_lock en sqlite
        $mockDb->shouldNotReceive('statement');

        $builderAnio = Mockery::mock(Builder::class);
        $builderExp = Mockery::mock(Builder::class);

        $mockDb->shouldReceive('table')
            ->with('sistema.a_anios')
            ->once()
            ->andReturn($builderAnio);

        $builderAnio->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $builderAnio->shouldReceive('where')->with('de_anio', 2026)->once()->andReturnSelf();
        $builderAnio->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $builderAnio->shouldReceive('first')->once()->andReturn((object) [
            'cdgo_anio' => 2026,
            'de_anio' => 2026,
        ]);

        $mockDb->shouldReceive('table')
            ->with('sistema.p_dto_trmtes')
            ->once()
            ->andReturn($builderExp);

        $builderExp->shouldReceive('where')->with('cdgo_anio', 2026)->once()->andReturnSelf();
        $builderExp->shouldReceive('where')->with('cdgo_tpo_trmte', 22)->once()->andReturnSelf();
        $builderExp->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $builderExp->shouldReceive('max')->with('nu_expe_trmte')->once()->andReturn(10);

        $repo = new BuscaExpedienteRepository($mockDb);
        $res = $repo->resolverCorrelativoAnio(2, 22, 2026);

        $this->assertEquals(11, $res['nuExpe']);
    }

    /**
     * REQUISITO 8: Ambos repositorios utilizan el mismo mecanismo.
     */
    public function test_ambos_repositorios_utilizan_el_mismo_mecanismo(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);

        $repoBusca = new BuscaExpedienteRepository($mockDb);
        $repoIngreso = new IngresoExpedienteRepository($mockDb);

        $refBusca = new ReflectionClass($repoBusca);
        $propBusca = $refBusca->getProperty('correlativoLock');
        $propBusca->setAccessible(true);
        $lockBusca = $propBusca->getValue($repoBusca);

        $refIngreso = new ReflectionClass($repoIngreso);
        $propIngreso = $refIngreso->getProperty('correlativoLock');
        $propIngreso->setAccessible(true);
        $lockIngreso = $propIngreso->getValue($repoIngreso);

        $this->assertInstanceOf(CorrelativoExpedienteLock::class, $lockBusca);
        $this->assertInstanceOf(CorrelativoExpedienteLock::class, $lockIngreso);

        // Ambos generan exactamente la misma clave y ámbito para los mismos parámetros
        $this->assertSame(
            $repoBusca->obtenerClaveSerializacion(2027, 22),
            $repoIngreso->obtenerClaveSerializacion(2027, 22)
        );

        $this->assertSame(
            $repoBusca->obtenerParametrosLock(2, 2027, 22),
            $repoIngreso->obtenerParametrosLock(2, 2027, 22)
        );
    }

    /**
     * REQUISITO 9: No queda ningún catch (\Throwable) que permita continuar sin bloqueo.
     */
    public function test_no_queda_ningun_catch_throwable_que_silencie_bloqueos(): void
    {
        $archivoLock = app_path('Repositories/mod_gm/mod_tram_exte/CorrelativoExpedienteLock.php');
        $this->assertFileExists($archivoLock);
        $contenidoLock = (string) file_get_contents($archivoLock);

        // CorrelativoExpedienteLock debe ser 100% fail-closed: sin ningún bloque try/catch
        $this->assertDoesNotMatchRegularExpression(
            '/catch\s*\(/i',
            $contenidoLock,
            'CorrelativoExpedienteLock no debe contener ningún bloque catch'
        );

        $repositorios = [
            app_path('Repositories/mod_gm/mod_tram_exte/BuscaExpedienteRepository.php'),
            app_path('Repositories/mod_gm/mod_tram_exte/IngresoExpedienteRepository.php'),
        ];

        foreach ($repositorios as $archivo) {
            $this->assertFileExists($archivo);
            $contenido = (string) file_get_contents($archivo);

            // Ningún catch en serializarCorrelativoPostgres
            $this->assertDoesNotMatchRegularExpression(
                '/function\s+serializarCorrelativoPostgres[^{]*\{[^}]*catch/s',
                $contenido,
                "serializarCorrelativoPostgres no debe contener try/catch en {$archivo}"
            );

            // Ningún catch en resolverCorrelativoAnio o generarNumeroExpediente
            $this->assertDoesNotMatchRegularExpression(
                '/function\s+(?:resolverCorrelativoAnio|generarNumeroExpediente)[^{]*\{[^}]*catch/s',
                $contenido,
                "El flujo de resolución de correlativo no debe atrapar excepciones en {$archivo}"
            );
        }
    }
}