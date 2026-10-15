<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class ContextoIdentidadGuardrailsTest extends TestCase
{
    /**
     * Guardrail 1: El DTO canónico no debe contener dependencias de DB, Auth, Request o session().
     */
    public function test_dto_canonico_no_tiene_acceso_a_db_auth_request_ni_session(): void
    {
        $dtoPath = app_path('DTOs/Core/ContextoUsuarioAutenticadoDto.php');
        $this->assertFileExists($dtoPath);

        $content = file_get_contents($dtoPath);
        $this->assertStringNotContainsString('use Illuminate\Support\Facades\DB;', $content);
        $this->assertStringNotContainsString('use Illuminate\Support\Facades\Auth;', $content);
        $this->assertStringNotContainsString('use Illuminate\Http\Request;', $content);
        $this->assertStringNotContainsString('DB::', $content);
        $this->assertStringNotContainsString('Auth::', $content);
        $this->assertStringNotContainsString('session(', $content);
    }

    /**
     * Guardrail 2: Cero fallbacks fijos a compañía 1 o 2 en app/.
     */
    public function test_cero_fallbacks_fijos_a_compania_en_app(): void
    {
        $appPath = app_path();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                '/cdgo_cmpnia\s*(\?\?|\?:)\s*[12]\b/',
                $content,
                "Fallback fijo de compañía detectado en: " . $file->getPathname()
            );
        }
    }

    /**
     * Guardrail 3: Cero escrituras de sigtram_compania_id fuera de purga o bridge.
     */
    public function test_cero_escrituras_de_sigtram_compania_id(): void
    {
        $appPath = app_path();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                "/put\s*\(\s*['\"]sigtram_compania_id['\"]/i",
                $content,
                "Escritura prohibida de sigtram_compania_id en: " . $file->getPathname()
            );
        }
    }

    /**
     * Guardrail 4: Cero referencias a DTOs obsoletos en app/ y tests/.
     */
    public function test_cero_referencias_a_dtos_obsoletos(): void
    {
        $dirs = [app_path(), base_path('tests')];
        foreach ($dirs as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression(
                    '/\bContextoFuncionario(Interno)?Dto\b/',
                    $content,
                    "Referencia a DTO obsoleto detectada en: " . $file->getPathname()
                );
            }
        }
    }

    /**
     * Guardrail 5: Cero propiedades duplicadas en el DTO canónico.
     */
    public function test_cero_propiedades_duplicadas_en_dto_canonico(): void
    {
        $dtoPath = app_path('DTOs/Core/ContextoUsuarioAutenticadoDto.php');
        $content = file_get_contents($dtoPath);

        $duplicadasProhibidas = [
            'loginId',
            'usuarioId',
            'loginNombre',
            'perfilId',
            'companiaId',
            'cargoId',
            'areaId',
            'companiaNombre',
            'cargoNombre',
            'areaNombre',
            'login',
        ];

        foreach ($duplicadasProhibidas as $prop) {
            $this->assertDoesNotMatchRegularExpression(
                '/\$(?:' . preg_quote($prop, '/') . ')\b/',
                $content,
                "Propiedad duplicada prohibida \${$prop} detectada en ContextoUsuarioAutenticadoDto."
            );
        }
    }

    /**
     * Guardrail 6: Cero llamadas a runningUnitTests() en app/.
     */
    public function test_cero_running_unit_tests_en_app(): void
    {
        $appPath = app_path();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString(
                'runningUnitTests()',
                $content,
                "Llamada prohibida a runningUnitTests() detectada en: " . $file->getPathname()
            );
        }
    }

    /**
     * Guardrail 7: Cero credenciales predeterminadas no vacías en configuraciones sensibles.
     */
    public function test_cero_credenciales_predeterminadas_no_vacias_en_config(): void
    {
        $dbConfig = file_get_contents(config_path('database.php'));
        $this->assertDoesNotMatchRegularExpression(
            "/env\('(?:DB_[A-Z0-9_]*_PASSWORD)',\s*'[^']+'\)/",
            $dbConfig,
            'Contraseña predeterminada hardcodeada detectada en config/database.php'
        );

        $pideConfig = file_get_contents(config_path('pide.php'));
        $this->assertDoesNotMatchRegularExpression(
            "/'clave'\s*=>\s*'\d+'/",
            $pideConfig,
            'Clave en texto plano detectada en credentials_pool de config/pide.php'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/env\('PIDE_SUNARP_CLAVE',\s*'[^']+'\)/",
            $pideConfig,
            'Clave SUNARP predeterminada detectada en config/pide.php'
        );

        $whatsappConfig = file_get_contents(config_path('whatsapp.php'));
        $this->assertDoesNotMatchRegularExpression(
            "/env\('WHATSAPP_TOKEN',\s*'[a-zA-Z0-9]+'\)/",
            $whatsappConfig,
            'Token de WhatsApp predeterminado detectado en config/whatsapp.php'
        );

        $camaraConfig = file_get_contents(config_path('camara_visor.php'));
        $this->assertDoesNotMatchRegularExpression(
            "/env\('CAMARA_VISOR_PASSWORD',\s*'[^']+'\)/",
            $camaraConfig,
            'Contraseña predeterminada detectada en config/camara_visor.php'
        );
    }
}
