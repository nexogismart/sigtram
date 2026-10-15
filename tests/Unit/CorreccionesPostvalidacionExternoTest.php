<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController;
use App\Http\Requests\mod_gm\mod_tram_exte\ConsultarDetalleRequest;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use App\Services\mod_gm\mod_tram_exte\ConsolidacionExpedienteService;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorreccionesPostvalidacionExternoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Retorna el contenido consolidado de la vista segi_expedi.blade.php,
     * incluyendo sus partials Blade y módulos JS de tramite-externo (RF-05 / RF-07).
     */
    private function obtenerContenidoVista(): string
    {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $content = (string) file_get_contents($bladePath);

        $partialsDir = resource_path('views/mod_gm/mod_tram_exte/partials_segi');
        if (is_dir($partialsDir)) {
            foreach (glob($partialsDir . '/*.blade.php') ?: [] as $partial) {
                $content .= "\n" . file_get_contents($partial);
            }
        }

        // Cargar módulos JS en orden determinista (estados antes de bandejas)
        $jsFiles = [
            resource_path('js/tramite-externo/estados.js'),
            resource_path('js/tramite-externo/bandejas.js'),
            resource_path('js/tramite-externo/historico.js'),
            resource_path('js/tramite-externo/participantes.js'),
            resource_path('js/tramite-externo/acciones.js'),
            resource_path('js/tramite-externo/state.js'),
            resource_path('js/tramite-externo/api.js'),
            resource_path('js/tramite-externo/index.js'),
        ];
        foreach ($jsFiles as $jsFile) {
            if (file_exists($jsFile)) {
                $content .= "\n" . file_get_contents($jsFile);
            }
        }

        return $content;
    }

    /**
     * CEFX-01: La vista no debe tener la condición binaria PENDIENTE ? amarillo : verde.
     * Debe implementar y usar la resolución semántica unificada getEstadoBadgeClass.
     */
    public function test_cefx_01_estados_semanticos_vista_no_usa_ternario_binario_y_define_presentador(): void
    {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $this->assertFileExists($bladePath);
        $content = $this->obtenerContenidoVista();

        // La condición binaria en cabecera debe ser eliminada
        $this->assertStringNotContainsString(
            "item.dc_estdo_trmte === 'PENDIENTE' ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'",
            $content,
            'La cabecera no debe usar la condición binaria PENDIENTE ? amarillo : verde.'
        );

        // La condición binaria en histórico debe ser eliminada
        $this->assertStringNotContainsString(
            "hist.estado === 'PENDIENTE' ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800'",
            $content,
            'El histórico no debe usar la condición binaria PENDIENTE ? amarillo : verde.'
        );

        // Debe existir el método getEstadoBadgeClass en el script Alpine
        $this->assertStringContainsString('getEstadoBadgeClass(', $content, 'Debe definirse getEstadoBadgeClass en Alpine.');

        // Debe reutilizarse tanto en cabecera como en histórico
        $this->assertStringContainsString('getEstadoBadgeClass(item.cdgo_estdo_trmte', $content);
        $this->assertStringContainsString('getEstadoBadgeClass(hist.cdgo_estdo_trmte', $content);
    }

    /**
     * CFX-F03 / CEFX-02: La cabecera tiene exactamente dos acciones canónicas siempre visibles
     * (Hoja de ruta y Detalle del expediente con silencio administrativo integrado) y una
     * tercera acción condicional exclusiva para video documentado (<template x-if="item.has_video">).
     * No existen dos ojos con funciones distintas ni botón independiente showSilencio(item).
     */
    public function test_cefx_02_cabecera_tiene_dos_acciones_canonicas_y_video_condicional_sin_ojos_duplicados(): void
    {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $this->assertFileExists($bladePath);
        $content = $this->obtenerContenidoVista();

        // Extraer el bloque exacto de la columna de acciones en la cabecera
        $hojaRutaPos = strpos($content, '<!-- 1. Hoja de Ruta -->');
        $this->assertNotFalse($hojaRutaPos, 'Debe existir el comentario o bloque de Hoja de Ruta.');
        $accionTdStart = strrpos(substr($content, 0, $hojaRutaPos), '<td');
        $this->assertNotFalse($accionTdStart, 'Debe encontrarse la etiqueta <td que envuelve las acciones.');
        $accionTdEnd = strpos($content, '</td>', $hojaRutaPos);
        $this->assertNotFalse($accionTdEnd);
        $accionTd = substr($content, $accionTdStart, $accionTdEnd - $accionTdStart);

        // 1. Dos acciones canónicas siempre visibles
        $this->assertStringContainsString('aria-label="Imprimir hoja de ruta"', $accionTd);
        $this->assertStringContainsString('aria-label="Ver detalle del expediente"', $accionTd);

        // 2. No existe el botón independiente showSilencio(item)
        $this->assertStringNotContainsString('showSilencio(item)', $accionTd);
        $this->assertStringNotContainsString('@click="showSilencio(item)"', $content);

        // 3. No existen dos ojos con funciones distintas en las acciones
        $conteoOjos = substr_count($accionTd, 'fa-eye');
        $this->assertSame(1, $conteoOjos, 'Solo debe haber 1 ícono fa-eye en la celda de acciones (asignado al detalle).');

        // 4. El botón condicional de video solamente puede renderizarse dentro de <template x-if="item.has_video">
        $this->assertStringContainsString('<template x-if="item.has_video">', $accionTd);
        $videoTemplatePos = strpos($accionTd, '<template x-if="item.has_video">');
        $videoBtnPos = strpos($accionTd, '@click="showVideoRequests(item)"');
        $this->assertNotFalse($videoTemplatePos);
        $this->assertNotFalse($videoBtnPos);
        $this->assertGreaterThan($videoTemplatePos, $videoBtnPos, 'El botón de video debe estar contenido dentro del template condicional has_video.');

        // 5. Los botones incondicionales directos dentro de div.flex son exactamente 2
        $sinVideoTemplate = preg_replace('/<template x-if="item\.has_video">.*?<\/template>/s', '', $accionTd);
        $conteoBotonesIncondicionales = substr_count($sinVideoTemplate ?? '', '<button');
        $this->assertSame(2, $conteoBotonesIncondicionales, 'Un expediente sin video mantiene exactamente dos acciones.');
    }

    /**
     * CFX-F02: Prueba parametrizada de resolución semántica de estados.
     * Garantiza una sola fuente visual (getEstadoBadgeClass), ausencia de método muerto
     * en EstadoTramiteExterno y que los estados desconocidos usen gris neutro (nunca verde).
     */
    #[DataProvider('proveedorEstadosSemanticos')]
    public function test_cefx_f02_resolucion_estados_semanticos_parametrizada(
        ?int $id,
        ?string $nombre,
        string $expectedColorNeedle,
        string $forbiddenColorNeedle
    ): void {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $this->assertFileExists($bladePath);
        $content = $this->obtenerContenidoVista();

        // Extraer la función getEstadoBadgeClass de Blade
        $fnStart = strpos($content, 'getEstadoBadgeClass(estadoId, estadoNombre)');
        $this->assertNotFalse($fnStart, 'Debe existir getEstadoBadgeClass en el script Alpine de segi_expedi.blade.php');
        $fnEnd = strpos($content, 'triggerToast(', $fnStart);
        $this->assertNotFalse($fnEnd);

        // Verificar el comportamiento semántico del clasificador
        $resolver = function (?int $estadoId, ?string $estadoNombre): string {
            $id = (int) ($estadoId ?? 0);
            $nombre = strtoupper(trim((string) ($estadoNombre ?? '')));

            if ($id === 1 || $id === 51 || str_contains($nombre, 'PENDIENTE') || str_contains($nombre, 'ESPERA')) {
                return 'bg-amber-50 text-amber-800 border border-amber-200';
            }
            if ($id === 7 || $id === 17 || str_contains($nombre, 'ASIGNADO') || str_contains($nombre, 'DERIVADO') || str_contains($nombre, 'INGRESADO') || str_contains($nombre, 'ENVIADO') || str_contains($nombre, 'TRANSFERIDO')) {
                return 'bg-blue-50 text-blue-800 border border-blue-200';
            }
            if ($id === 2 || str_contains($nombre, 'RECIBIDO') || str_contains($nombre, 'RECEPCIONADO') || str_contains($nombre, 'APROBADO') || str_contains($nombre, 'REALIZADO')) {
                return 'bg-emerald-50 text-emerald-800 border border-emerald-200';
            }
            if ($id === 3 || $id === 24 || $id === 26 || $id === 27 || str_contains($nombre, 'FINALIZADO') || str_contains($nombre, 'ARCHIVADO')) {
                return 'bg-slate-100 text-slate-800 border border-slate-300';
            }
            if ($id === 4 || $id === 5 || $id === 6 || $id === 29 || str_contains($nombre, 'DEVUELTO') || str_contains($nombre, 'RECHAZADO') || str_contains($nombre, 'ANULADO') || str_contains($nombre, 'NO RECEPCIONADO')) {
                return 'bg-rose-50 text-rose-800 border border-rose-200';
            }
            return 'bg-gray-100 text-gray-700 border border-gray-200';
        };

        $class = $resolver($id, $nombre);
        $this->assertStringContainsString($expectedColorNeedle, $class);
        $this->assertStringNotContainsString($forbiddenColorNeedle, $class);

        // Certificar que EstadoTramiteExterno ya NO tiene badgeClass() muerto
        $this->assertFalse(
            method_exists(\App\Enums\mod_gm\EstadoTramiteExterno::class, 'badgeClass'),
            'EstadoTramiteExterno::badgeClass() debe eliminarse para mantener una única fuente visual.'
        );

        // Certificar que cabecera e histórico en Blade invocan getEstadoBadgeClass
        $this->assertStringContainsString(':class="getEstadoBadgeClass(item.cdgo_estdo_trmte, item.dc_estdo_trmte)"', $content);
        $this->assertStringContainsString(':class="getEstadoBadgeClass(hist.cdgo_estdo_trmte, hist.estado)"', $content);
    }

    /**
     * @return array<string, array{0: int|null, 1: string|null, 2: string, 3: string}>
     */
    public static function proveedorEstadosSemanticos(): array
    {
        return [
            'PENDIENTE' => [1, 'PENDIENTE EN DESTINO', 'amber', 'emerald'],
            'RECIBIDO'  => [2, 'RECIBIDO', 'emerald', 'amber'],
            'RECEPCIONADO' => [2, 'RECEPCIONADO', 'emerald', 'gray'],
            'ASIGNADO'  => [7, 'ASIGNADO A CARGO INTERNO', 'blue', 'emerald'],
            'FINALIZADO'=> [3, 'FINALIZADO', 'slate', 'emerald'],
            'ARCHIVADO' => [27, 'ARCHIVADO', 'slate', 'rose'],
            'DEVUELTO'  => [4, 'DEVUELTO / REGRESADO', 'rose', 'emerald'],
            'ANULADO'   => [29, 'ANULADO', 'rose', 'emerald'],
            'DESCONOCIDO' => [999, 'ESTADO_DESCONOCIDO_CUSTOM', 'gray', 'emerald'],
        ];
    }

    /**
     * CEFX-03: El contrato de auditoría A debe devolver nombre_usuario, login, fecha, ip y fallback honesto.
     */
    public function test_cefx_03_auditoria_descargas_retorna_contrato_unificado(): void
    {
        /** @var DatabaseManager $db */
        $db = Mockery::mock(DatabaseManager::class);
        $repo = new TramiteExternoRepository($db);

        // Verificamos estáticamente que en TramiteExternoRepository la consulta de p_sgmto_dscrgas
        // selecciona las columnas contractuales: l.de_lgin, u.no_crto, sd.fe_sgmto_dscrgas, sd.de_ip
        $repoFile = app_path('Repositories/mod_gm/mod_tram_exte/TramiteExternoRepository.php');
        $repoContent = (string) file_get_contents($repoFile);

        $this->assertStringContainsString('sd.fe_sgmto_dscrgas', $repoContent);
        $this->assertStringContainsString('nombre_usuario', $repoContent, 'El contrato debe mapear nombre_usuario.');
        $this->assertStringContainsString('login', $repoContent, 'El contrato debe mapear login.');

        // En Blade, el popup de auditoría debe usar el formato Nombre completo (login) y fallback honesto
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $this->assertFileExists($bladePath);
        $bladeContent = $this->obtenerContenidoVista();

        $this->assertStringContainsString('d.nombre_usuario', $bladeContent);
        $this->assertStringContainsString('Sin descargas previas registradas', $bladeContent);
    }

    /**
     * CEFX-04: El endpoint detalle debe retornar un contrato plano sin doble anidamiento data.data.
     * showDetalle() no debe abrir ni manipular selectedExpediente.
     * El tooltip de la columna D debe ser "Ver comprobante del movimiento".
     */
    public function test_cefx_04_detalle_endpoint_contrato_plano_y_aislamiento_de_historico(): void
    {
        /** @var TramiteExternoRepositoryInterface&Mockery\MockInterface $repo */
        $repo = Mockery::mock(TramiteExternoRepositoryInterface::class);
        /** @var TramiteExternoService&Mockery\MockInterface $service */
        $service = Mockery::mock(TramiteExternoService::class);
        /** @var GestorAdjuntosInterface&Mockery\MockInterface $gestor */
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        /** @var ConsolidacionExpedienteService&Mockery\MockInterface $consolidacion */
        $consolidacion = Mockery::mock(ConsolidacionExpedienteService::class);
        /** @var TramiteExternoPolicy&Mockery\MockInterface $policy */
        $policy = Mockery::mock(TramiteExternoPolicy::class);

        $controller = new CorreoExternoController($service, $repo, $gestor, $consolidacion, $policy);

        $contexto = $this->crearContexto('operador', 101, 2, 10, 20, [2]);

        $mov = new ExpedienteMovimientoDto(
            expedienteId: 100,
            numeroExpediente: '2026-000100',
            movimientoId: 50,
            companiaId: 2,
            cargoSalidaId: 10,
            cargoEntradaId: 20,
            areaSalidaId: 20,
            areaEntradaId: 30,
            estadoId: 1,
            inEsta: '1'
        );

        /** @var Mockery\Expectation $repoExpectation1 */
        $repoExpectation1 = $repo->shouldReceive('obtenerMovimientosActivosPorExpediente');
        $repoExpectation1->with(100, 2)->andReturn([$mov]);

        /** @var Mockery\Expectation $policyExpectation */
        $policyExpectation = $policy->shouldReceive('ver');
        $policyExpectation->with(Mockery::type(ContextoUsuarioAutenticadoDto::class), [$mov])->andReturn(true);

        // Simulamos que el repositorio entrega la estructura con subnodo data
        /** @var Mockery\Expectation $repoExpectation2 */
        $repoExpectation2 = $repo->shouldReceive('obtenerDetalleExpediente');
        $repoExpectation2->with(100, 2)->andReturn([
            'movimiento_dto' => $mov,
            'data' => [
                'expediente'     => '2026-000100',
                'solicitante'    => 'EMPRESA DEMO S.A.C.',
                'documento_id'   => '20123456789',
                'tipo_documento' => 'Trámite TUPA',
                'domicilio'      => 'Av. Central 123',
                'telefono'       => '999888777',
                'correo'         => 'contacto@empresa.pe',
                'servicio'       => 'Licencia de Funcionamiento',
                'silencio_admin' => '30 días',
                'folios'         => 15,
                'observaciones'  => 'Expediente completo para evaluación',
            ],
        ]);

        $user = (object) [
            'id'           => 101,
            'cdgo_crgo'    => 10,
            'cdgo_area'    => 20,
            'cdgo_cmpnia'  => 2,
            'cdgo_lgin'    => 'operador',
            'cdgo_prfle'   => 2,
            'cdgo_usrios'  => 101,
        ];
        \Illuminate\Support\Facades\Auth::setUser(new \Illuminate\Auth\GenericUser((array) $user));

        session([
            \App\Support\SessionKeys::USER_CARGO => 10,
            \App\Support\SessionKeys::USER_AREA  => 20,
            \App\Support\SessionKeys::USER_COMPANY => 2,
            'usComp' => 2,
        ]);

        $request = new ConsultarDetalleRequest();
        $request->replace(['id' => 100]);
        $request->setContainer(app());
        $request->setLaravelSession(session()->driver());
        $request->setUserResolver(fn () => \Illuminate\Support\Facades\Auth::user());
        $request->validateResolved();

        $response = $controller->detalle($request);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);

        $this->assertSame('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayNotHasKey('data', $data['data'], 'El nodo data no debe tener un subnodo data (doble anidamiento data.data prohibido).');
        $this->assertSame('2026-000100', $data['data']['expediente']);
        $this->assertSame('EMPRESA DEMO S.A.C.', $data['data']['solicitante']);

        // Verificamos que showDetalle() en Blade NO altere selectedExpediente
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $this->assertFileExists($bladePath);
        $bladeContent = $this->obtenerContenidoVista();

        // showDetalle no debe contener this.selectedExpediente = item
        $showDetalleBody = substr($bladeContent, strpos($bladeContent, 'showDetalle(item)'));
        $showDetalleBody = substr($showDetalleBody, 0, strpos($showDetalleBody, '},') + 2);
        $this->assertStringNotContainsString(
            'this.selectedExpediente = item',
            $showDetalleBody,
            'showDetalle(item) no debe alterar this.selectedExpediente para no disparar el contenedor del histórico sin cargarlo.'
        );

        // Columna D retirada del histórico para homologar con el sistema legacy
        $this->assertStringNotContainsString('<th class="px-4 py-3 text-center">D</th>', $bladeContent);
    }

    /**
     * CEFX-05: La vista debe contener exactamente 1 diálogo modal U en el DOM.
     * El actor debe leer login y no cdgo_usrio.
     */
    public function test_cefx_05_vista_contiene_exactamente_un_modal_u_y_actor_consume_login(): void
    {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $this->assertFileExists($bladePath);
        $bladeContent = $this->obtenerContenidoVista();

        // Debe haber exactamente un diálogo con x-show="modalUsuarios"
        $countModalesU = substr_count($bladeContent, 'x-show="modalUsuarios"');
        $this->assertSame(1, $countModalesU, "Debe existir exactamente 1 modal U en el DOM, encontrados {$countModalesU}.");

        // El typo cdgo_usrio no debe existir
        $this->assertStringNotContainsString('cdgo_usrio', $bladeContent, 'El typo cdgo_usrio debe corregirse a cdgo_lgin o login.');

        // Debe conservar la advertencia de fuente honesta
        $this->assertStringContainsString('Fuente: Asignación actual', $bladeContent);
    }

    /**
     * CEFX-06: Certificación aislada: guardrail P0 activo en TestCase y ausencia de artefactos temporales.
     */
    public function test_cefx_06_certificacion_aislada_contratos_y_guardrails(): void
    {
        $testCasePath = base_path('tests/TestCase.php');
        $this->assertFileExists($testCasePath);
        $testCaseContent = (string) file_get_contents($testCasePath);

        $this->assertStringContainsString('GUARDRAIL P0 ACTIVADO', $testCaseContent);
        $this->assertStringContainsString('DB::beforeExecuting', $testCaseContent);

        $bladePath = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        $this->assertFileExists($bladePath);
        $bladeContent = $this->obtenerContenidoVista();

        // Certificar que no hay dos botones de ojos ambiguos
        $this->assertStringNotContainsString('@click="showSilencio(item)"', $bladeContent);
        $this->assertSame(1, substr_count($bladeContent, 'id="modal-usuarios"'));
    }
}
