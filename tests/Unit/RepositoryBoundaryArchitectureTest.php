<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController;
use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use ReflectionClass;

class RepositoryBoundaryArchitectureTest extends TestCase
{
    public function test_correo_interno_controller_no_contiene_accesos_directos_a_base_de_datos(): void
    {
        $reflection = new ReflectionClass(CorreoInternoController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'CorreoInternoController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'CorreoInternoController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'CorreoInternoController no debe importar la fachada DB');
    }

    public function test_correo_externo_controller_no_contiene_accesos_directos_a_base_de_datos(): void
    {
        $reflection = new ReflectionClass(CorreoExternoController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'CorreoExternoController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'CorreoExternoController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'CorreoExternoController no debe importar la fachada DB');
    }

    public function test_ambos_controladores_inyectan_su_repositorio_correspondiente(): void
    {
        $refInterno = new ReflectionClass(CorreoInternoController::class);
        $constructorInterno = $refInterno->getConstructor();
        $this->assertNotNull($constructorInterno);
        $paramsInterno = array_map(fn($p) => $p->getType()?->getName(), $constructorInterno->getParameters());
        $this->assertContains(TramiteInternoRepositoryInterface::class, $paramsInterno);

        $refExterno = new ReflectionClass(CorreoExternoController::class);
        $constructorExterno = $refExterno->getConstructor();
        $this->assertNotNull($constructorExterno);
        $paramsExterno = array_map(fn($p) => $p->getType()?->getName(), $constructorExterno->getParameters());
        $this->assertContains(TramiteExternoRepositoryInterface::class, $paramsExterno);
    }

    public function test_ingreso_expediente_controller_no_contiene_accesos_directos_a_base_de_datos(): void
    {
        $reflection = new ReflectionClass(\App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'IngresoExpedienteController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'IngresoExpedienteController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'IngresoExpedienteController no debe importar la fachada DB');
    }

    public function test_busca_expedi_controller_no_contiene_accesos_directos_a_base_de_datos(): void
    {
        $reflection = new ReflectionClass(\App\Http\Controllers\mod_gm\mod_tram_exte\BuscaExpediController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'BuscaExpediController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'BuscaExpediController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'BuscaExpediController no debe importar la fachada DB');
    }

    public function test_repositorios_estan_registrados_en_el_contenedor_de_laravel(): void
    {
        $repoInterno = $this->app->make(TramiteInternoRepositoryInterface::class);
        $this->assertInstanceOf(TramiteInternoRepositoryInterface::class, $repoInterno);

        $repoExterno = $this->app->make(TramiteExternoRepositoryInterface::class);
        $this->assertInstanceOf(TramiteExternoRepositoryInterface::class, $repoExterno);

        $repoIngreso = $this->app->make(\App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface::class);
        $this->assertInstanceOf(\App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface::class, $repoIngreso);

        $repoBusca = $this->app->make(\App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface::class);
        $this->assertInstanceOf(\App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface::class, $repoBusca);
    }

    public function test_tramite_externo_service_no_contiene_consultas_directas_a_base_de_datos(): void
    {
        $reflection = new ReflectionClass(\App\Services\mod_gm\mod_tram_exte\TramiteExternoService::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'TramiteExternoService no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::table/', $content, 'TramiteExternoService no debe llamar a DB::table()');

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(TramiteExternoRepositoryInterface::class, $params, 'TramiteExternoService debe inyectar TramiteExternoRepositoryInterface');
    }

    public function test_correo_interno_transaccional_service_no_contiene_consultas_directas_a_base_de_datos(): void
    {
        $reflection = new ReflectionClass(\App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'CorreoInternoTransaccionalService no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'CorreoInternoTransaccionalService no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'CorreoInternoTransaccionalService no debe importar la fachada DB');

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(TramiteInternoRepositoryInterface::class, $params, 'CorreoInternoTransaccionalService debe inyectar TramiteInternoRepositoryInterface');
    }

    public function test_busca_expediente_service_no_contiene_consultas_directas_a_base_de_datos(): void
    {
        $reflection = new ReflectionClass(\App\Services\mod_gm\mod_tram_exte\BuscaExpedienteService::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'BuscaExpedienteService no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'BuscaExpedienteService no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'BuscaExpedienteService no debe importar la fachada DB');

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(\App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface::class, $params, 'BuscaExpedienteService debe inyectar BuscaExpedienteRepositoryInterface');
    }

    public function test_ingreso_expediente_service_no_contiene_consultas_directas_ni_modelos_de_persistencia(): void
    {
        $reflection = new ReflectionClass(\App\Services\IngresoExpedienteService::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'IngresoExpedienteService no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'IngresoExpedienteService no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'IngresoExpedienteService no debe importar la fachada DB');
        $this->assertDoesNotMatchRegularExpression('/use\s+App\\\\Models\\\\(DtoTramite|TramiteDocumento|PUsrrio|UsrioNatural|UsrioJuridico|Anio|Cargo|CargoTramite|DtoCargoTramite|RequisitoTramite|DtoRequisito|SeguimientoLog|DocumentoTramiteTupa);/', $content, 'IngresoExpedienteService no debe importar modelos de persistencia');

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(\App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface::class, $params, 'IngresoExpedienteService debe inyectar IngresoExpedienteRepositoryInterface');
    }

    public function test_services_de_tramite_no_contienen_llamadas_estaticas_a_modelos_eloquent(): void
    {
        $services = [
            \App\Services\IngresoExpedienteService::class,
            \App\Services\mod_gm\mod_tram_exte\BuscaExpedienteService::class,
            \App\Services\mod_gm\mod_tram_exte\TramiteExternoService::class,
            \App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService::class,
        ];

        foreach ($services as $serviceClass) {
            $reflection = new ReflectionClass($serviceClass);
            $fileName = $reflection->getFileName();
            $this->assertNotFalse($fileName);
            $content = (string) file_get_contents($fileName);

            $this->assertDoesNotMatchRegularExpression(
                '/(DtoTramite|TramiteDocumento|PUsrrio|UsrioNatural|UsrioJuridico|Anio|Cargo|Area)::(where|create|update|find|findOrFail|query)/',
                $content,
                "El servicio {$serviceClass} no debe ejecutar llamadas estáticas a modelos Eloquent."
            );
        }
    }

    public function test_busca_expediente_repository_interface_no_tiene_compania_con_valor_por_defecto_cero(): void
    {
        $reflection = new ReflectionClass(\App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface::class);

        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                if ($parameter->getName() === 'companiaId') {
                    $this->assertFalse(
                        $parameter->isDefaultValueAvailable(),
                        "El método {$method->getName()} de BuscaExpedienteRepositoryInterface no debe tener valor por defecto en companiaId."
                    );
                }
            }
        }
    }

    /**
     * Valida recursivamente los límites arquitectónicos en todos los controladores de ambos módulos:
     * - Zero DB::
     * - Zero ConnectionInterface
     * - Zero ->table()
     * - Zero lockForUpdate()
     */
    public function test_frontera_arquitectonica_controladores_recursivo(): void
    {
        $directorios = [
            app_path('Http/Controllers/mod_gene/mod_tram_inter'),
            app_path('Http/Controllers/mod_gm/mod_tram_exte'),
        ];

        $totalControladores = 0;

        foreach ($directorios as $dir) {
            $this->assertDirectoryExists($dir);
            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($iterador as $archivo) {
                if (!$archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }

                $path = $archivo->getPathname();
                $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
                $content = (string) file_get_contents($path);

                $this->assertDoesNotMatchRegularExpression(
                    '/(?<!\\\\)DB::/',
                    $content,
                    "Frontera violada: El controlador [{$relPath}] invoca DB::"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/->table\(/',
                    $content,
                    "Frontera violada: El controlador [{$relPath}] construye queries con ->table()"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/ConnectionInterface/',
                    $content,
                    "Frontera violada: El controlador [{$relPath}] inyecta ConnectionInterface"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/lockForUpdate\(/',
                    $content,
                    "Frontera violada: El controlador [{$relPath}] invoca lockForUpdate()"
                );

                $totalControladores++;
            }
        }

        $this->assertGreaterThanOrEqual(17, $totalControladores);
    }

    /**
     * Valida recursivamente los límites arquitectónicos en todos los servicios de ambos módulos:
     * - Zero ConnectionInterface
     * - Zero ->table()
     * - Zero lockForUpdate()
     * - Único uso permitido de DB:: es DB::transaction()
     */
    public function test_frontera_arquitectonica_servicios_recursivo(): void
    {
        $directorios = [
            app_path('Services/mod_gene/mod_tram_inter'),
            app_path('Services/mod_gm/mod_tram_exte'),
        ];

        $totalServicios = 0;

        foreach ($directorios as $dir) {
            $this->assertDirectoryExists($dir);
            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($iterador as $archivo) {
                if (!$archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }

                $path = $archivo->getPathname();
                $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
                $content = (string) file_get_contents($path);

                $this->assertDoesNotMatchRegularExpression(
                    '/->table\(/',
                    $content,
                    "Frontera violada: El servicio [{$relPath}] construye queries con ->table()"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/ConnectionInterface/',
                    $content,
                    "Frontera violada: El servicio [{$relPath}] inyecta ConnectionInterface"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/lockForUpdate\(/',
                    $content,
                    "Frontera violada: El servicio [{$relPath}] invoca lockForUpdate()"
                );

                preg_match_all('/(?<!\\\\)DB::([a-zA-Z0-9_]+)/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $method) {
                        $this->assertSame(
                            'transaction',
                            $method,
                            "Frontera violada: El servicio [{$relPath}] invoca DB::{$method} en lugar de DB::transaction"
                        );
                    }
                }

                $totalServicios++;
            }
        }

        $this->assertGreaterThanOrEqual(15, $totalServicios);
    }
}
