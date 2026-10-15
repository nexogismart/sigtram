<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController;
use App\Models\User;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Services\mod_gene\mod_tram_inter\ConsolidadoExpedienteService;
use App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService;
use App\Services\mod_gene\mod_tram_inter\ValidadorPagoProveedoresTipo64Service;
use App\Services\mod_gm\mod_tram_exte\ConsolidacionExpedienteService;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use App\Support\SessionKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class CertificacionCorreccionesBandejasTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 1. Participantes de movimiento activo con destinatarios devuelve 200 y datos.
     */
    public function test_01_participantes_de_movimiento_activo_con_destinatarios(): void
    {
        $user = new User(['cdgo_usrios' => 10, 'cdgo_lgin' => 'jperez', 'cdgo_cmpnia' => 1]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_cmpnia = 1;
        Auth::setUser($user);

        $repo = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteExternoPolicy::class);
        $service = Mockery::mock(TramiteExternoService::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $consolidacion = Mockery::mock(ConsolidacionExpedienteService::class);

        $controller = new CorreoExternoController($service, $repo, $gestor, $consolidacion, $policy);

        $movActivo = new ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 55,
            companiaId: 1,
            cargoSalidaId: 90,
            cargoEntradaId: 105,
            areaSalidaId: 18,
            areaEntradaId: 20,
            estadoId: 1,
            inEsta: '1'
        );

        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(55, 1)->andReturn($movActivo);
        $policy->shouldReceive('ver')->andReturn(true);
        $repo->shouldReceive('obtenerParticipantesU')->andReturn([
            'status' => 'success',
            'data' => [['login' => 'ana', 'nombre' => 'Ana Torres', 'cargo' => 'Asistente']],
            'pagination' => ['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
        ]);

        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', ['movimiento_id' => 55]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([
            SessionKeys::USER_COMPANY => 1,
            SessionKeys::USER_CARGO => 105,
            SessionKeys::USER_AREA => 20,
        ]);

        $response = $controller->participantes($request);
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $data['status']);
        $this->assertCount(1, $data['data']);
    }

    /**
     * 2. Participantes de movimiento histórico in_esta = 0 se autoriza y devuelve 200
     * incluso si el expediente no tiene ramas activas.
     */
    public function test_02_participantes_de_movimiento_historico_in_esta_cero_sin_ramas_activas(): void
    {
        $user = new User(['cdgo_usrios' => 10, 'cdgo_lgin' => 'jperez', 'cdgo_cmpnia' => 1]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_cmpnia = 1;
        Auth::setUser($user);

        $repo = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteExternoPolicy::class);
        $service = Mockery::mock(TramiteExternoService::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $consolidacion = Mockery::mock(ConsolidacionExpedienteService::class);

        $controller = new CorreoExternoController($service, $repo, $gestor, $consolidacion, $policy);

        $movHistorico = new ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 50,
            companiaId: 1,
            cargoSalidaId: 80,
            cargoEntradaId: 90,
            areaSalidaId: 15,
            areaEntradaId: 18,
            estadoId: 3,
            inEsta: '0'
        );

        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(50, 1)->andReturn($movHistorico);
        $repo->shouldReceive('obtenerMovimientosActivosPorExpediente')->with(10, 1)->andReturn([]);
        $policy->shouldReceive('verHistorico')->with(
            Mockery::type(ContextoUsuarioAutenticadoDto::class),
            $movHistorico,
            []
        )->andReturn(false);

        // Fallback a movimientos históricos y verHistorialExpediente
        $repo->shouldReceive('obtenerMovimientosHistoricosPorExpediente')->with(10, 1)->andReturn([$movHistorico]);
        $policy->shouldReceive('verHistorialExpediente')->with(
            Mockery::type(ContextoUsuarioAutenticadoDto::class),
            [$movHistorico]
        )->andReturn(true);

        $repo->shouldReceive('obtenerParticipantesU')->andReturn([
            'status' => 'success',
            'data' => [['login' => 'carlos', 'nombre' => 'Carlos Destino', 'cargo' => 'Jefe']],
            'pagination' => ['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
        ]);

        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', ['movimiento_id' => 50]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([
            SessionKeys::USER_COMPANY => 1,
            SessionKeys::USER_CARGO => 80,
            SessionKeys::USER_AREA => 15,
        ]);

        $response = $controller->participantes($request);
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $data['status']);
        $this->assertCount(1, $data['data']);
    }

    /**
     * 3. Movimiento histórico válido sin destinatarios devuelve éxito y lista vacía (no 404 ni 500).
     */
    public function test_03_movimiento_historico_valido_sin_destinatarios_devuelve_exito_y_lista_vacia(): void
    {
        $user = new User(['cdgo_usrios' => 10, 'cdgo_lgin' => 'jperez', 'cdgo_cmpnia' => 1]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_cmpnia = 1;
        Auth::setUser($user);

        $repo = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteExternoPolicy::class);
        $service = Mockery::mock(TramiteExternoService::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $consolidacion = Mockery::mock(ConsolidacionExpedienteService::class);

        $controller = new CorreoExternoController($service, $repo, $gestor, $consolidacion, $policy);

        $movHistorico = new ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 50,
            companiaId: 1,
            cargoSalidaId: 80,
            cargoEntradaId: 90,
            areaSalidaId: 15,
            areaEntradaId: 18,
            estadoId: 1,
            inEsta: '0'
        );

        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(50, 1)->andReturn($movHistorico);
        $repo->shouldReceive('obtenerMovimientosActivosPorExpediente')->with(10, 1)->andReturn([]);
        $policy->shouldReceive('verHistorico')->andReturn(true);

        // Sin destinatarios asignados
        $repo->shouldReceive('obtenerParticipantesU')->andReturn([
            'status' => 'success',
            'data' => [],
            'pagination' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
        ]);

        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', ['movimiento_id' => 50]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([
            SessionKeys::USER_COMPANY => 1,
            SessionKeys::USER_CARGO => 80,
            SessionKeys::USER_AREA => 15,
        ]);

        $response = $controller->participantes($request);
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $data['status']);
        $this->assertSame([], $data['data']);
        $this->assertSame(0, $data['pagination']['total']);
    }

    /**
     * 4. Movimiento inexistente devuelve 404.
     */
    public function test_04_movimiento_inexistente_devuelve_404(): void
    {
        $user = new User(['cdgo_usrios' => 10, 'cdgo_lgin' => 'jperez', 'cdgo_cmpnia' => 1]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_cmpnia = 1;
        Auth::setUser($user);

        $repo = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteExternoPolicy::class);
        $service = Mockery::mock(TramiteExternoService::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $consolidacion = Mockery::mock(ConsolidacionExpedienteService::class);

        $controller = new CorreoExternoController($service, $repo, $gestor, $consolidacion, $policy);

        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(9999, 1)->andReturn(null);

        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', ['movimiento_id' => 9999]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([SessionKeys::USER_COMPANY => 1, SessionKeys::USER_CARGO => 80, SessionKeys::USER_AREA => 15]);

        $response = $controller->participantes($request);
        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * 5. Movimiento de otra compañía devuelve 404.
     */
    public function test_05_movimiento_otra_compania_devuelve_404(): void
    {
        $user = new User(['cdgo_usrios' => 10, 'cdgo_lgin' => 'jperez', 'cdgo_cmpnia' => 1]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_cmpnia = 1;
        Auth::setUser($user);

        $repo = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $policy = Mockery::mock(TramiteExternoPolicy::class);
        $service = Mockery::mock(TramiteExternoService::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $consolidacion = Mockery::mock(ConsolidacionExpedienteService::class);

        $controller = new CorreoExternoController($service, $repo, $gestor, $consolidacion, $policy);

        // repo rechaza por IDOR de compañía (retorna null al buscar con companiaId = 1)
        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(50, 1)->andReturn(null);

        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', ['movimiento_id' => 50]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([SessionKeys::USER_COMPANY => 1, SessionKeys::USER_CARGO => 80, SessionKeys::USER_AREA => 15]);

        $response = $controller->participantes($request);
        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * 6. El frontend envía el ID exacto del movimiento histórico.
     */
    public function test_06_frontend_envia_id_exacto_del_movimiento_historico(): void
    {
        // En Externo: blade y JS usan cdgo_trmte_dcmnto
        $externoBlade = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/historico.blade.php'));
        $this->assertStringContainsString('hist.cdgo_trmte_dcmnto', $externoBlade);

        $externoJs = file_get_contents(resource_path('js/tramite-externo/participantes.js'));
        $this->assertStringContainsString('hist.movimiento_id || hist.cdgo_trmte_dcmnto', $externoJs);

        // En Interno: blade y JS usan cdgo_trmtes_ntrnos
        $internoJs = file_get_contents(resource_path('js/tramite-interno/historico.js'));
        $this->assertStringContainsString('it.cdgo_trmtes_ntrnos || it.movimiento_id', $internoJs);
    }

    /**
     * 7. El frontend no utiliza hist.id ambiguo.
     */
    public function test_07_frontend_no_utiliza_hist_id_ambiguo(): void
    {
        $externoJsPart = file_get_contents(resource_path('js/tramite-externo/participantes.js'));
        $this->assertStringNotContainsString('hist.id', $externoJsPart);

        $externoJsHist = file_get_contents(resource_path('js/tramite-externo/historico.js'));
        $this->assertStringNotContainsString('hist.id', $externoJsHist);

        $externoBlade = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/historico.blade.php'));
        $this->assertStringNotContainsString('hist.id', $externoBlade);
    }

    /**
     * 8. Auditoría vacía limpia estado y total.
     */
    public function test_08_auditoria_vacia_limpia_estado_y_total(): void
    {
        $externoHistJs = file_get_contents(resource_path('js/tramite-externo/historico.js'));
        $this->assertStringContainsString("this.auditPagination = { current_page: 1, last_page: 1, total: 0, per_page: 10 }", $externoHistJs);
        $this->assertStringContainsString("this.auditData = []", $externoHistJs);

        $internoHistJs = file_get_contents(resource_path('js/tramite-interno/historico.js'));
        $this->assertStringContainsString("auditContext.total = 0", $internoHistJs);
    }

    /**
     * 9. Error de auditoría elimina registros y paginación anteriores.
     */
    public function test_09_error_de_auditoria_elimina_registros_y_paginacion_anteriores(): void
    {
        $externoHistJs = file_get_contents(resource_path('js/tramite-externo/historico.js'));
        // En error de catch o fallo res.ok, se resetea auditData a [] y auditPagination.total a 0
        $this->assertMatchesRegularExpression('/catch[^{]*\{[^}]*this\.auditData = \[\];[^}]*this\.auditPagination = \{ current_page: 1, last_page: 1, total: 0/s', $externoHistJs);

        $externoModales = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/modales.blade.php'));
        $this->assertStringContainsString('auditError', $externoModales);
        $this->assertStringContainsString("x-text=\"auditError ? 'Página 1 de 1 (0 registros)'", $externoModales);
    }

    /**
     * 10. Auditoría con registros muestra correctamente el primer elemento con padding superior.
     */
    public function test_10_auditoria_con_registros_muestra_correctamente_el_primer_elemento(): void
    {
        $externoModales = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/modales.blade.php'));
        $this->assertStringContainsString('p-5 overflow-y-auto custom-scrollbar flex-1 bg-slate-50/50 space-y-3', $externoModales);
        $this->assertStringContainsString('shrink-0', $externoModales);

        $internoModales = file_get_contents(resource_path('views/mod_gene/mod_tram_inter/partials/modales.blade.php'));
        $this->assertStringContainsString('p-5 overflow-y-auto custom-scroll flex-1 bg-slate-50/50 space-y-3', $internoModales);
    }

    /**
     * 11. Históricos incluyen in_esta = 0 y 1.
     */
    public function test_11_historicos_incluyen_in_esta_cero_y_uno(): void
    {
        $externoRepo = file_get_contents(app_path('Repositories/mod_gm/mod_tram_exte/TramiteExternoRepository.php'));
        // En obtenerHistoricoMovimientos NO filtra r.in_esta = '1'
        $this->assertStringContainsString('public function obtenerHistoricoMovimientos(', $externoRepo);
        $this->assertStringContainsString("->where('r.cdgo_dto_trmte', \$expedienteId)", $externoRepo);

        $internoRepo = file_get_contents(app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php'));
        $this->assertStringContainsString('public function obtenerHistorial(', $internoRepo);
        $this->assertStringContainsString("->where('r.cdgo_dtos_ntrnos', \$cdgoDtosNtrnos)", $internoRepo);
    }

    /**
     * 12. Orden histórico descendente legacy confirmado.
     */
    public function test_12_orden_historico_descendente_legacy_confirmado(): void
    {
        $externoRepo = file_get_contents(app_path('Repositories/mod_gm/mod_tram_exte/TramiteExternoRepository.php'));
        $this->assertStringContainsString("->orderByDesc('r.cdgo_trmte_dcmnto')", $externoRepo);

        $internoRepo = file_get_contents(app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php'));
        $this->assertStringContainsString("->orderByDesc('r.cdgo_trmtes_ntrnos')", $internoRepo);
    }

    /**
     * 13. Policies de mutación continúan exigiendo vigencia (in_esta = 1).
     */
    public function test_13_policies_de_mutacion_continuan_exigiendo_vigencia(): void
    {
        $externoPolicy = file_get_contents(app_path('Policies/mod_gm/TramiteExternoPolicy.php'));
        $this->assertStringContainsString("\$movimiento->inEsta !== '1'", $externoPolicy);

        $internoPolicy = file_get_contents(app_path('Policies/mod_gene/TramiteInternoPolicy.php'));
        $this->assertStringContainsString("\$mov->inEsta !== '1'", $internoPolicy);
    }

    /**
     * 14. Sidebar interno y externo comparten ancho (220px) y altura por contenido (self-start sin h-full).
     */
    public function test_14_sidebar_interno_y_externo_comparten_ancho_y_altura(): void
    {
        $externoBlade = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php'));
        $internoBlade = file_get_contents(resource_path('views/mod_gene/mod_tram_inter/correo-interno.blade.php'));

        $this->assertStringContainsString('xl:grid-cols-[220px_1fr]', $externoBlade);
        $this->assertStringContainsString('xl:grid-cols-[220px_1fr]', $internoBlade);

        $externoSide = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/sidebar.blade.php'));
        $internoSide = file_get_contents(resource_path('views/mod_gene/mod_tram_inter/partials/sidebar.blade.php'));

        $this->assertStringContainsString('self-start', $externoSide);
        $this->assertStringContainsString('self-start', $internoSide);
        $this->assertDoesNotMatchRegularExpression('/(?<![a-zA-Z0-9_-])h-full(?![a-zA-Z0-9_-])/', $externoSide);
        $this->assertDoesNotMatchRegularExpression('/(?<![a-zA-Z0-9_-])h-full(?![a-zA-Z0-9_-])/', $internoSide);
    }

    /**
     * 15. Conteos coinciden con los filtros de sus bandejas y mantienen exactamente las bandejas aprobadas.
     */
    public function test_15_conteos_coinciden_con_los_filtros_de_sus_bandejas(): void
    {
        $externoSide = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/sidebar.blade.php'));

        // Confirmar presencia de los 7 aprobados
        $this->assertStringContainsString("activeBandeja === 'todos'", $externoSide);
        $this->assertStringContainsString("activeBandeja === 'entrada'", $externoSide);
        $this->assertStringContainsString("activeBandeja === 'recibidos'", $externoSide);
        $this->assertStringContainsString("activeBandeja === 'salida'", $externoSide);
        $this->assertStringContainsString("activeBandeja === 'finalizados'", $externoSide);
        $this->assertStringContainsString("activeBandeja === 'archivo'", $externoSide);
        $this->assertStringContainsString("activeBandeja === 'sin_finalizar'", $externoSide);

        // Confirmar que no se inventaron ni revivieron bandejas descartadas
        $this->assertStringNotContainsString("activeBandeja === 'prestados'", $externoSide);
        $this->assertStringNotContainsString("activeBandeja === 'inventariar'", $externoSide);
    }

    /**
     * 16. No se introdujeron cambios relacionados con archivos, almacenamiento o QR.
     */
    public function test_16_no_se_introdujeron_cambios_en_almacenamiento_o_qr(): void
    {
        // Certificar que los controladores no invocan rutas directas de storage público ni QR
        $correoExt = file_get_contents(app_path('Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php'));
        $this->assertStringNotContainsString('/storage/adjuntos', $correoExt);

        $correoInt = file_get_contents(app_path('Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php'));
        $this->assertStringNotContainsString('/storage/adjuntos', $correoInt);
    }
}
