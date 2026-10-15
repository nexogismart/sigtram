<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\mod_gene\mod_tram_inter\IngresarTramiteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Services\mod_gene\mod_tram_inter\IngresarTramiteService;
use App\Services\IngresoExpedienteService;
use App\Contracts\Repositories\mod_gene\mod_tram_inter\IngresarTramiteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use ReflectionClass;

class ArquitecturaControllersYServicesTest extends TestCase
{
    /**
     * Valida que IngresarTramiteController sea un controlador delgado:
     * - No usa DB:: directos ni imports de DB.
     * - No construye queries con ->table().
     * - Inyecta IngresarTramiteRepositoryInterface.
     */
    public function test_ingresar_tramite_controller_no_contiene_accesos_directos_a_bd(): void
    {
        $reflection = new ReflectionClass(IngresarTramiteController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = (string) file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'IngresarTramiteController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'IngresarTramiteController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'IngresarTramiteController no debe importar la fachada DB');

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(IngresarTramiteRepositoryInterface::class, $params, 'IngresarTramiteController debe inyectar IngresarTramiteRepositoryInterface');
    }

    /**
     * Valida que IngresarTramiteService no contenga consultas SQL directas ni construya queries:
     * - Todo acceso a datos delegado al repositorio.
     * - Único uso permitido de DB es DB::transaction para atomicidad.
     */
    public function test_ingresar_tramite_service_no_contiene_consultas_directas_a_bd(): void
    {
        $reflection = new ReflectionClass(IngresarTramiteService::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = (string) file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'IngresarTramiteService no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::table/', $content, 'IngresarTramiteService no debe llamar a DB::table()');

        // Verificar que si usa DB::, sea exclusivamente DB::transaction
        preg_match_all('/(?<!\\\\)DB::([a-zA-Z0-9_]+)/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $method) {
                $this->assertSame('transaction', $method, "IngresarTramiteService solo puede usar DB::transaction, pero usa DB::{$method}");
            }
        }

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(IngresarTramiteRepositoryInterface::class, $params, 'IngresarTramiteService debe inyectar IngresarTramiteRepositoryInterface');
    }

    /**
     * Valida que IngresoExpedienteController sea delgado y sin DB::.
     */
    public function test_ingreso_expediente_controller_no_contiene_accesos_directos_a_bd(): void
    {
        $reflection = new ReflectionClass(IngresoExpedienteController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = (string) file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'IngresoExpedienteController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'IngresoExpedienteController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'IngresoExpedienteController no debe importar la fachada DB');

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(IngresoExpedienteRepositoryInterface::class, $params, 'IngresoExpedienteController debe inyectar IngresoExpedienteRepositoryInterface');
    }

    /**
     * Valida que IngresoExpedienteService delegue la persistencia al repositorio.
     */
    public function test_ingreso_expediente_service_no_contiene_consultas_directas_a_bd(): void
    {
        $reflection = new ReflectionClass(IngresoExpedienteService::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = (string) file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'IngresoExpedienteService no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::table/', $content, 'IngresoExpedienteService no debe llamar a DB::table()');

        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(IngresoExpedienteRepositoryInterface::class, $params, 'IngresoExpedienteService debe inyectar IngresoExpedienteRepositoryInterface');
    }

    /**
     * Valida que los contratos de repositorio para trámites internos y externos
     * estén correctamente registrados en el ServiceProvider de Laravel.
     */
    public function test_interfaces_de_repositorios_estan_registradas_en_el_container(): void
    {
        $repoInterno = $this->app->make(IngresarTramiteRepositoryInterface::class);
        $this->assertInstanceOf(IngresarTramiteRepositoryInterface::class, $repoInterno);

        $repoIngresoExp = $this->app->make(IngresoExpedienteRepositoryInterface::class);
        $this->assertInstanceOf(IngresoExpedienteRepositoryInterface::class, $repoIngresoExp);
    }

    /**
     * Valida que la resolución de tipos E, S, O en IngresoExpedienteRepository
     * se haga dinámicamente mediante consulta al catálogo y NO con IDs hardcodeados (17, 22, 40).
     */
    public function test_ingreso_expediente_repository_no_hardcodea_ids_de_catalogo(): void
    {
        $reflection = new ReflectionClass(\App\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepository::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = (string) file_get_contents($fileName);

        // No debe haber asignaciones directas de tipo 17, 22 o 40 a cdgo_tpo_trmte
        $this->assertDoesNotMatchRegularExpression(
            '/\$tipoTramiteId\s*=\s*(17|22|40)\s*;/',
            $content,
            'No debe existir asignación hardcodeada de IDs 17, 22 o 40 en IngresoExpedienteRepository'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/cdgo_tpo_trmte[\'"]?\s*=>\s*(17|22|40)\b/',
            $content,
            'No debe existir persistencia con ID hardcodeado 17, 22 o 40 en IngresoExpedienteRepository'
        );

        // Debe consultar a_tpo_trmtes por dc_tpo_trmte
        $this->assertMatchesRegularExpression(
            '/dc_tpo_trmte/',
            $content,
            'IngresoExpedienteRepository debe buscar por dc_tpo_trmte dinámicamente'
        );
    }

    /**
     * Valida que no existan patrones funcionales de IDs hardcodeados (17, 22, 40)
     * en los componentes de tipo de trámite (Blades, Requests, Controllers y Repositories).
     */
    public function test_ausencia_de_ids_funcionales_hardcodeados_en_componentes_de_tipo_tramite(): void
    {
        $archivos = [
            'Blade principal' => resource_path('views/mod_gm/mod_tram_exte/index.blade.php'),
            'Voucher' => resource_path('views/mod_gm/mod_tram_exte/voucher.blade.php'),
            'Blade alternativo observado' => resource_path('views/mod_gm/mod_tram_exte/gene_expedi_obser.blade.php'),
            'Request alternativo observado' => app_path('Http/Requests/mod_gm/mod_tram_exte/GenerarExpedienteObservadoRequest.php'),
            'Controller alternativo busca' => app_path('Http/Controllers/mod_gm/mod_tram_exte/BuscaExpediController.php'),
            'Repository ingreso expediente' => app_path('Repositories/mod_gm/mod_tram_exte/IngresoExpedienteRepository.php'),
            'Repository busca expediente' => app_path('Repositories/mod_gm/mod_tram_exte/BuscaExpedienteRepository.php'),
        ];

        foreach ($archivos as $nombre => $ruta) {
            $this->assertFileExists($ruta, "El archivo para {$nombre} debe existir en {$ruta}");
            $contenido = (string) file_get_contents($ruta);

            // 1. Prohibido: cdgo_tpo_trmte === 17 / 22 / 40
            $this->assertDoesNotMatchRegularExpression(
                '/cdgo_tpo_trmte\s*===\s*(17|22|40)\b/',
                $contenido,
                "En {$nombre}: prohibido comparar cdgo_tpo_trmte con 17, 22 o 40"
            );

            // 2. Prohibido: in:17,22 o similar en reglas de validación de tipos
            $this->assertDoesNotMatchRegularExpression(
                '/in:(17|22|40)\b/',
                $contenido,
                "En {$nombre}: prohibida regla de validación con IDs numéricos hardcodeados (17, 22 o 40)"
            );

            // 3. Prohibido: rdn_cdgo_tpo_trmtes con value 17, 22 o 40
            $this->assertDoesNotMatchRegularExpression(
                '/name=[\'"]rdn_cdgo_tpo_trmtes[\'"][^>]*value=[\'"](17|22|40)[\'"]/',
                $contenido,
                "En {$nombre}: prohibido radio rdn_cdgo_tpo_trmtes con valor hardcodeado 17, 22 o 40"
            );
            $this->assertDoesNotMatchRegularExpression(
                '/value=[\'"](17|22|40)[\'"][^>]*name=[\'"]rdn_cdgo_tpo_trmtes[\'"]/',
                $contenido,
                "En {$nombre}: prohibido radio rdn_cdgo_tpo_trmtes con valor hardcodeado 17, 22 o 40"
            );

            // 4. Prohibido: fallbacks ?? 17, ?? 22 o ?? 40 en resolución de tipo
            $this->assertDoesNotMatchRegularExpression(
                '/\?\?\s*(17|22|40)\b/',
                $contenido,
                "En {$nombre}: prohibido usar fallback numérico hardcodeado (?? 17, ?? 22 o ?? 40)"
            );
        }
    }

    /**
     * Valida recursivamente que TODOS los controladores en mod_gene/mod_tram_inter y mod_gm/mod_tram_exte
     * sean controladores delgados sin acceso directo a base de datos:
     * - Zero DB::
     * - Zero ConnectionInterface
     * - Zero ->table()
     * - Zero lockForUpdate()
     * - Zero imports de Illuminate\Support\Facades\DB
     */
    public function test_todos_los_controladores_recursivamente_sin_accesos_directos_a_bd(): void
    {
        $directorios = [
            app_path('Http/Controllers/mod_gene/mod_tram_inter'),
            app_path('Http/Controllers/mod_gm/mod_tram_exte'),
        ];

        $archivosAuditados = 0;

        foreach ($directorios as $directorio) {
            $this->assertDirectoryExists($directorio);
            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directorio));

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
                    "El controlador [{$relPath}] no debe invocar la fachada DB::"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/->table\(/',
                    $content,
                    "El controlador [{$relPath}] no debe construir queries con ->table()"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/ConnectionInterface/',
                    $content,
                    "El controlador [{$relPath}] no debe inyectar ConnectionInterface"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/lockForUpdate\(/',
                    $content,
                    "El controlador [{$relPath}] no debe invocar lockForUpdate()"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/',
                    $content,
                    "El controlador [{$relPath}] no debe importar la fachada DB"
                );

                $archivosAuditados++;
            }
        }

        $this->assertGreaterThanOrEqual(17, $archivosAuditados, 'Debe haber al menos 17 controladores auditados recursivamente.');
    }

    /**
     * Valida recursivamente que TODOS los servicios en mod_gene/mod_tram_inter y mod_gm/mod_tram_exte
     * no contengan consultas SQL directas ni manipulen tablas:
     * - Zero ConnectionInterface
     * - Zero ->table()
     * - Zero lockForUpdate()
     * - Si usan DB::, debe ser estrictamente DB::transaction()
     */
    public function test_todos_los_servicios_recursivamente_sin_consultas_directas_a_bd(): void
    {
        $directorios = [
            app_path('Services/mod_gene/mod_tram_inter'),
            app_path('Services/mod_gm/mod_tram_exte'),
        ];

        $archivosAuditados = 0;

        foreach ($directorios as $directorio) {
            $this->assertDirectoryExists($directorio);
            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directorio));

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
                    "El servicio [{$relPath}] no debe construir queries con ->table()"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/ConnectionInterface/',
                    $content,
                    "El servicio [{$relPath}] no debe inyectar ConnectionInterface"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/lockForUpdate\(/',
                    $content,
                    "El servicio [{$relPath}] no debe invocar lockForUpdate()"
                );

                // Si usa DB::, debe ser exclusivamente DB::transaction()
                preg_match_all('/(?<!\\\\)DB::([a-zA-Z0-9_]+)/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $method) {
                        $this->assertSame(
                            'transaction',
                            $method,
                            "El servicio [{$relPath}] solo puede usar DB::transaction, pero invoca DB::{$method}"
                        );
                    }
                }

                $archivosAuditados++;
            }
        }

        $this->assertGreaterThanOrEqual(15, $archivosAuditados, 'Debe haber al menos 15 servicios auditados recursivamente.');
    }
}
