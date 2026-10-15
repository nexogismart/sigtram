<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\AccionPermisoRepositoryInterface;
use App\Contracts\Services\SecurityCoreInterface;
use App\Models\User;
use App\Services\mod_gm\mod_tram_exte\ExportarTramiteExternoExcelService;
use App\Support\SessionKeys;
use Mockery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Feature Test para el endpoint canónico mod_gm.segi_expedi.exportar_excel.
 * Cubre los casos de integración 14, 15 y 16, verificación de rutas, 401, 403 y 200.
 */
class TramiteExternoExportarExcelEndpointTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ruta_exportar_excel_esta_registrada_y_nombrada(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('mod_gm.segi_expedi.exportar_excel'));
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('mod_gm.segi_expedi.exportar_excel');
        $this->assertNotNull($route);
        $this->assertSame('mod_gm/mod_tram_exte/segi_expedi/exportar-excel', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    public function test_solicitud_sin_autenticar_es_redireccionada_o_denegada(): void
    {
        $response = $this->get(route('mod_gm.segi_expedi.exportar_excel', ['bandeja' => 'entrada']));
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /**
     * Caso 14: Usuario con acción asignada pero SIN permiso base de módulo Trámite Externo => 403 Forbidden.
     */
    public function test_caso_14_deniega_403_si_no_tiene_permiso_base_del_modulo(): void
    {
        $user = new User();
        $user->cdgo_lgin = 15844;
        $user->de_lgin = 'jperez';
        $user->cdgo_usrios = 500;
        $user->cdgo_prfle = 4;
        $user->cdgo_cmpnia = 3;

        // Mock de SecurityCoreInterface: deniega el módulo base
        $securityMock = Mockery::mock(SecurityCoreInterface::class);
        $securityMock->shouldReceive('tienePermiso')
            ->once()
            ->with(15844, 'mod_gm/mod_tram_exte/segi_expedi', 10, 'ver')
            ->andReturn(false);
        $this->app->instance(SecurityCoreInterface::class, $securityMock);

        // Incluso si el repo de acciones tuviera la acción permitida
        $repoMock = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $this->app->instance(AccionPermisoRepositoryInterface::class, $repoMock);

        $response = $this->actingAs($user)
            ->withSession([
                SessionKeys::USER_COMPANY => 3,
                SessionKeys::USER_CARGO   => 10,
                SessionKeys::USER_AREA    => 20,
            ])
            ->get(route('mod_gm.segi_expedi.exportar_excel', ['bandeja' => 'entrada']));

        $response->assertStatus(403);
    }

    /**
     * Caso 15: Usuario con acceso al módulo pero SIN permiso granular 'tramite_externo.exportar_excel' => 403 Forbidden.
     */
    public function test_caso_15_deniega_403_si_no_tiene_permiso_granular_de_accion(): void
    {
        $user = new User();
        $user->cdgo_lgin = 15844;
        $user->de_lgin = 'jperez';
        $user->cdgo_usrios = 500;
        $user->cdgo_prfle = 4;
        $user->cdgo_cmpnia = 3;

        // Mock de SecurityCoreInterface: autoriza el módulo base
        $securityMock = Mockery::mock(SecurityCoreInterface::class);
        $securityMock->shouldReceive('tienePermiso')
            ->once()
            ->with(15844, 'mod_gm/mod_tram_exte/segi_expedi', 10, 'ver')
            ->andReturn(true);
        $this->app->instance(SecurityCoreInterface::class, $securityMock);

        // Mock del repo de acciones: deniega la acción granular
        $repoMock = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repoMock->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, 'tramite_externo.exportar_excel')
            ->andReturn(false);
        $this->app->instance(AccionPermisoRepositoryInterface::class, $repoMock);

        $response = $this->actingAs($user)
            ->withSession([
                SessionKeys::USER_COMPANY => 3,
                SessionKeys::USER_CARGO   => 10,
                SessionKeys::USER_AREA    => 20,
            ])
            ->get(route('mod_gm.segi_expedi.exportar_excel', ['bandeja' => 'entrada']));

        $response->assertStatus(403);
    }

    /**
     * Caso 16: Usuario con permiso base AND permiso granular de acción => 200 OK con descarga de archivo.
     */
    public function test_caso_16_autoriza_y_descarga_cuando_cumple_ambos_permisos(): void
    {
        $user = new User();
        $user->cdgo_lgin = 15844;
        $user->de_lgin = 'jperez';
        $user->cdgo_usrios = 500;
        $user->cdgo_prfle = 4;
        $user->cdgo_cmpnia = 3;

        // 1. Mock de SecurityCore: autoriza módulo base
        $securityMock = Mockery::mock(SecurityCoreInterface::class);
        $securityMock->shouldReceive('tienePermiso')
            ->once()
            ->with(15844, 'mod_gm/mod_tram_exte/segi_expedi', 10, 'ver')
            ->andReturn(true);
        $this->app->instance(SecurityCoreInterface::class, $securityMock);

        // 2. Mock de AccionPermisoRepo: autoriza acción granular
        $repoMock = Mockery::mock(AccionPermisoRepositoryInterface::class);
        $repoMock->shouldReceive('tieneAccionPermitida')
            ->once()
            ->with(15844, 3, 'tramite_externo.exportar_excel')
            ->andReturn(true);
        $this->app->instance(AccionPermisoRepositoryInterface::class, $repoMock);

        // 3. Mock de ExportarTramiteExternoExcelService: genera respuesta de descarga
        $tempFile = tempnam(sys_get_temp_dir(), 'test_exp_') . '.xlsx';
        file_put_contents($tempFile, 'PK' . str_repeat('0', 600));

        $exportServiceMock = Mockery::mock(ExportarTramiteExternoExcelService::class);
        $dummyResponse = response()->download(
            $tempFile,
            'reporte_expedientes_20261011_120000.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);

        $exportServiceMock->shouldReceive('exportar')
            ->once()
            ->andReturn($dummyResponse);
        $this->app->instance(ExportarTramiteExternoExcelService::class, $exportServiceMock);

        $response = $this->actingAs($user)
            ->withSession([
                SessionKeys::USER_COMPANY => 3,
                SessionKeys::USER_CARGO   => 10,
                SessionKeys::USER_AREA    => 20,
            ])
            ->get(route('mod_gm.segi_expedi.exportar_excel', ['bandeja' => 'entrada']));

        $response->assertStatus(200);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('reporte_expedientes_', (string) $response->headers->get('Content-Disposition'));
    }
}
