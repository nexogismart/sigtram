<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use \Tests\Support\ContextoTestHelper;
    protected function setUp(): void
    {
        parent::setUp();

        $this->inicializarBaseDesechableSiAplica();

        // Guardrail P0: Prevenir conexiones reales accidentales a PostgreSQL
        /** @phpstan-ignore-next-line */
        if (! (bool) env('ALLOW_DANGEROUS_DB_TESTS', false)) {
            DB::beforeExecuting(function (string $query, array $bindings, $connection): void {
                $driver = $connection->getDriverName();
                $database = (string) $connection->getDatabaseName();
                $host = (string) ($connection->getConfig('host') ?? '');

                $isDisposable = ($driver === 'sqlite' || $database === ':memory:' || str_contains($database, 'test'));

                if (! $isDisposable || $host === '192.168.2.40' || $database === 'sigtramv3') {
                    throw new RuntimeException(
                        "GUARDRAIL P0 ACTIVADO: Intento de ejecución DB no autorizado durante pruebas ({$connection->getName()}: {$query}). " .
                        "Está estrictamente prohibido tocar la base de datos configurada sin autorización explícita."
                    );
                }
            });
        }
    }

    /**
     * Inicializa base desechable SQLite en memoria para tests de integración que requieren BD,
     * garantizando que nunca apunten a la base real de producción.
     */
    protected function inicializarBaseDesechableSiAplica(): void
    {
        // Si el test es HarnessGuardTest, debe mantener la conexión real para certificar el Guardrail
        if (static::class === 'Tests\Unit\HarnessGuardTest') {
            return;
        }

        // Si la prueba está en tests/Feature o requiere BD, cambiar a sqlite :memory:
        $isFeature = str_contains(static::class, 'Tests\Feature');
        if ($isFeature) {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);

            // Crear esquema mínimo necesario en sqlite para aislar las pruebas
            try {
                $pdo = DB::connection('sqlite')->getPdo();
                $pdo->exec("ATTACH DATABASE ':memory:' AS sistema;");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.p_lgin (
                    cdgo_lgin INTEGER PRIMARY KEY AUTOINCREMENT,
                    de_lgin TEXT,
                    de_pass TEXT,
                    in_esta TEXT DEFAULT '1',
                    cdgo_prfle INTEGER DEFAULT 1,
                    cdgo_usrios INTEGER DEFAULT 1
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.p_usrios (
                    cdgo_usrios INTEGER PRIMARY KEY AUTOINCREMENT,
                    no_crto TEXT,
                    no_crto_sstma TEXT,
                    de_dire TEXT,
                    de_foto TEXT,
                    nu_tele TEXT,
                    co_ubig TEXT,
                    de_mail TEXT,
                    nu_docu TEXT,
                    de_ocup_rubr TEXT,
                    in_tipo_pers TEXT,
                    nu_ruc TEXT,
                    in_esta TEXT DEFAULT '1'
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.h_usrios_ntral (
                    cdgo_usrios INTEGER PRIMARY KEY,
                    cdgo_estdo_cvil INTEGER DEFAULT 1,
                    cdgo_dcmntos_iden INTEGER DEFAULT 1,
                    ap_pate TEXT,
                    ap_mate TEXT,
                    no_usrio TEXT,
                    nu_edad INTEGER,
                    in_sexo TEXT,
                    co_usua_modi INTEGER,
                    ts_usua_modi TEXT
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.h_usrios_jrid (
                    cdgo_usrios INTEGER PRIMARY KEY,
                    rz_soci TEXT,
                    co_usua_modi INTEGER,
                    ts_usua_modi TEXT,
                    in_esta TEXT DEFAULT '1'
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.r_cmpnias_lgin (
                    cdgo_cmpnia_lgin INTEGER PRIMARY KEY AUTOINCREMENT,
                    cdgo_lgin INTEGER,
                    cdgo_cmpnia INTEGER,
                    in_esta INTEGER DEFAULT 1,
                    ts_usua_modi TEXT
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.a_cmpnias (
                    cdgo_cmpnia INTEGER PRIMARY KEY,
                    de_cmpnia TEXT,
                    dc_cmpnia TEXT,
                    in_esta TEXT DEFAULT '1'
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.r_crgos_lgin (
                    cdgo_crgo_lgin INTEGER PRIMARY KEY AUTOINCREMENT,
                    cdgo_lgin INTEGER,
                    cdgo_crgo INTEGER,
                    in_esta TEXT DEFAULT '1',
                    ts_usua_modi TEXT
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.p_crgos (
                    cdgo_crgo INTEGER PRIMARY KEY,
                    cdgo_area INTEGER,
                    de_crgo TEXT,
                    in_esta TEXT DEFAULT '1'
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.a_areas (
                    cdgo_area INTEGER PRIMARY KEY,
                    de_area TEXT,
                    dc_area TEXT,
                    cdgo_cmpnia INTEGER,
                    in_esta TEXT DEFAULT '1'
                );");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sistema.a_rles (
                    cdgo_rle INTEGER PRIMARY KEY,
                    de_rle TEXT,
                    dc_rle TEXT,
                    de_dreccon TEXT,
                    cdgo_sub_rle INTEGER,
                    in_esta TEXT DEFAULT '1',
                    in_popup TEXT DEFAULT '0'
                );");
            } catch (\Throwable $e) {
                // Fallback silencioso si el driver sqlite no está disponible
            }
        }
    }
}
