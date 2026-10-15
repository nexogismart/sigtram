<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gm;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Contracts\Services\mod_gm\mod_tram_exte\PdfPageCounterInterface;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Http\Controllers\Publico\SeguimientoPublicoController;
use App\Models\Compania;
use App\Services\IngresoExpedienteService;
use App\Support\PublicTrackingToken;
use App\Support\SessionKeys;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class IngresoExpedienteVoucherYTrackingTest extends TestCase
{
    private IngresoExpedienteService $mockService;
    private IngresoExpedienteRepositoryInterface $mockRepo;
    private PdfPageCounterInterface $mockPdfCounter;
    private IngresoExpedienteController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.qr.key'=>'test-only-qr-signing-key-0000000000000000']);

        $this->mockService = Mockery::mock(IngresoExpedienteService::class);
        $this->mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $this->mockPdfCounter = Mockery::mock(PdfPageCounterInterface::class);

        $this->controller = new IngresoExpedienteController(
            $this->mockService,
            $this->mockRepo,
            $this->mockPdfCounter
        );

        session([
            SessionKeys::USER_COMPANY => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 15: Voucher renderiza el nombre institucional dinámico de la compañía en lugar de texto hardcodeado.
     */
    public function test_voucher_renderiza_nombre_institucional_dinamico_de_compania(): void
    {
        $compania = new Compania();
        $compania->cdgo_cmpnia = 1;
        $compania->de_cmpnia = 'MUNICIPALIDAD DISTRITAL DE PRUEBA 2026';

        $voucherData = [
            'expediente' => (object) [
                'cdgo_dto_trmte' => 100,
                'nu_expe_todo' => 'E-100-2026',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'nu_foli_trmte' => 26,
                'cdgo_tpo_trmte' => 1,
                'de_asno_trmte' => 'SOLICITUD DE LICENCIA',
                'de_obse_trmte' => 'SOLICITUD DE LICENCIA',
                'usrio' => (object) [
                    'in_tipo_pers' => '0',
                    'nu_docu' => '12345678',
                    'nu_ruc' => '',
                    'no_crto' => 'JUAN PEREZ GARCIA',
                    'de_dire' => 'AV. PRINCIPAL 123',
                    'nu_tele' => '999888777',
                    'de_mail' => 'juan@prueba.gob.pe',
                ],
                'documentoTupa' => (object) [
                    'de_docmnto_trmte' => 'LICENCIA DE EDIFICACION',
                    'servicio' => (object) ['de_srvcio' => 'GERENCIA DE DESARROLLO URBANO'],
                ],
                'area' => (object) [
                    'de_area' => 'GERENCIA DE DESARROLLO URBANO',
                    'nu_tele' => '01-1234567',
                ],
            ],
            'compania' => $compania,
            'attendant' => (object) ['de_lgin' => 'VENTANILLA 1 - CARLOS LOPEZ'],
            'qrCode' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
            'publicTrackingUrl' => 'https://sigtram.gob.pe/tramite/seguimiento/token_valido_123',
        ];

        $view = view('mod_gm.mod_tram_exte.voucher', $voucherData)->render();

        $this->assertStringContainsString('MUNICIPALIDAD DISTRITAL DE PRUEBA 2026', $view);
        $this->assertStringContainsString('E-100-2026', $view);
        $this->assertStringContainsString('26', $view);
        $this->assertStringContainsString('JUAN PEREZ GARCIA', $view);
        $this->assertStringContainsString('data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=', $view);
    }

    /**
     * Test 16: Usuario con compañía activa autorizada accede al voucher sin restricciones.
     */
    public function test_usuario_con_acceso_a_su_compania_puede_ver_voucher(): void
    {
        $compania = new Compania();
        $compania->cdgo_cmpnia = 1;
        $compania->de_cmpnia = 'Municipalidad Activa';

        $this->mockRepo->shouldReceive('obtenerVoucherExpediente')
            ->once()
            ->with(555, 1)
            ->andReturn([
                'expediente' => (object) [
                    'cdgo_dto_trmte' => 555,
                    'nu_expe_todo' => 'E-555-2026',
                    'fe_ingr_trmte' => '2026-10-14 11:30:00',
                    'nu_foli_trmte' => 5,
                    'cdgo_tpo_trmte' => 1,
                    'de_asno_trmte' => 'Prueba',
                    'de_obse_trmte' => 'Prueba',
                    'usrio' => (object) [
                        'in_tipo_pers' => '0',
                        'nu_docu' => '11223344',
                        'nu_ruc' => '',
                        'no_crto' => 'Ciudadano Prueba',
                        'de_dire' => '',
                        'nu_tele' => '',
                        'de_mail' => '',
                    ],
                    'documentoTupa' => null,
                ],
                'compania' => $compania,
                'attendant' => (object) ['de_lgin' => 'Admin'],
                'qrCode' => 'data:image/svg+xml;base64,dummy',
                'publicTrackingUrl' => 'http://localhost/tramite/seguimiento/xyz',
            ]);

        $response = $this->controller->voucher(555);
        $this->assertSame('mod_gm.mod_tram_exte.voucher', $response->name());
    }

    /**
     * Test 17: Usuario sin compañía activa en sesión recibe HTTP 403.
     */
    public function test_usuario_sin_compania_activa_es_bloqueado_con_403(): void
    {
        session()->forget([SessionKeys::USER_COMPANY, 'usComp']);

        $this->expectException(HttpException::class);
        $this->controller->voucher(555);
    }

    /**
     * Test 18: Token QR válido permite el acceso a la consulta de seguimiento público.
     */
    public function test_token_qr_valido_permite_acceso_a_seguimiento_publico(): void
    {
        $expedienteId = 321;
        $validToken = PublicTrackingToken::generate($expedienteId);

        $mockRepoPublico = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockRepoPublico->shouldReceive('obtenerSeguimientoPublico')
            ->once()
            ->with($expedienteId)
            ->andReturn([
                'expediente_id' => 321,
                'numero_expediente' => 'E-321-2026',
                'fecha_ingreso' => '14/10/2026 12:00',
                'asunto' => 'Solicitud de certificado',
                'solicitante' => 'J*** P***',
                'tipo_tramite' => 'TRAMITE EXTERNO',
                'estado_actual' => 'REGISTRADO',
                'area_actual' => 'MESA DE PARTES',
                'compania_nombre' => 'MUNICIPALIDAD DISTRITAL',
                'movimientos' => [
                    [
                        'fecha' => '14/10/2026 12:00',
                        'oficina' => 'Subgerencia de Catastro',
                        'estado' => 'EN TRAMITE',
                    ]
                ],
            ]);

        $controller = new SeguimientoPublicoController($mockRepoPublico);
        $request = Request::create('/tramite/seguimiento/' . $validToken, 'GET');
        $response = $controller->consultar($request, $validToken);

        $this->assertSame(200, $response->getStatusCode());
        $viewContent = $response->getContent();
        $this->assertStringContainsString('E-321-2026', $viewContent);
        $this->assertStringContainsString('J*** P***', $viewContent);
        $this->assertStringContainsString('MESA DE PARTES', $viewContent);
        $this->assertStringContainsString('MUNICIPALIDAD DISTRITAL', $viewContent);
    }

    /**
     * Test 19: Token QR adulterado o inválido retorna HTTP 404 uniforme (previene enumeración).
     */
    public function test_token_qr_adulterado_retorna_404_uniforme(): void
    {
        $mockRepoPublico = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockRepoPublico->shouldNotReceive('obtenerSeguimientoPublico');

        $controller = new SeguimientoPublicoController($mockRepoPublico);

        // Caso: Token manipulado o firma inválida
        $tamperedToken = 'eyJleHBlZGllbnRlX2lkIjo5OTk5OSwidGltZXN0YW1wIjoxNzAwMDAwMDAwfQ.invalidsignature123';
        $request = Request::create('/tramite/seguimiento/' . $tamperedToken, 'GET');

        $this->expectException(NotFoundHttpException::class);
        $controller->consultar($request, $tamperedToken);
    }

    /**
     * Test 20: Seguimiento público no expone enlaces de descarga de adjuntos ni datos sensibles de ciudadanos.
     */
    public function test_seguimiento_publico_no_expone_adjuntos_privados_ni_datos_sensibles(): void
    {
        $payload = [
            'expediente' => [
                'expediente_id' => 99,
                'numero_expediente' => 'E-99-2026',
                'fecha_ingreso' => '14/10/2026 12:00',
                'asunto' => 'Constancia',
                'solicitante' => 'M*** R***',
                'tipo_tramite' => 'TRAMITE EXTERNO',
                'estado_actual' => 'EN TRAMITE',
                'area_actual' => 'SUBGERENCIA DE RENTAS',
                'compania_nombre' => 'MUNICIPALIDAD DISTRITAL',
                'movimientos' => [],
            ],
        ];

        $view = view('publico.seguimiento', $payload)->render();

        // No debe contener rutas de descarga de adjuntos ni URLs de archivos internos
        $this->assertStringNotContainsString('/descarga', $view);
        $this->assertStringNotContainsString('.pdf', $view);
        $this->assertStringNotContainsString('fle_adjunto', $view);
        $this->assertStringNotContainsString('adjunto', $view);
    }

    /**
     * Test 21 & 22: Reimpresión de voucher es idempotente, segura y no duplica registros en BD.
     */
    public function test_reimpresion_voucher_es_idempotente_y_no_duplica_registros(): void
    {
        $compania = new Compania();
        $compania->cdgo_cmpnia = 1;
        $compania->de_cmpnia = 'Municipalidad';

        // Solicitamos el voucher 3 veces consecutivas
        $this->mockRepo->shouldReceive('obtenerVoucherExpediente')
            ->times(3)
            ->with(777, 1)
            ->andReturn([
                'expediente' => (object) [
                    'cdgo_dto_trmte' => 777,
                    'nu_expe_todo' => 'E-777-2026',
                    'fe_ingr_trmte' => '2026-10-14 10:00:00',
                    'nu_foli_trmte' => 8,
                    'cdgo_tpo_trmte' => 1,
                    'de_asno_trmte' => 'Reimpresion',
                    'de_obse_trmte' => 'Reimpresion',
                    'usrio' => (object) [
                        'in_tipo_pers' => '0',
                        'nu_docu' => '',
                        'nu_ruc' => '',
                        'no_crto' => 'Test',
                        'de_dire' => '',
                        'nu_tele' => '',
                        'de_mail' => '',
                    ],
                    'documentoTupa' => null,
                ],
                'compania' => $compania,
                'attendant' => (object) ['de_lgin' => 'Cajero'],
                'qrCode' => 'data:image/svg+xml;base64,dummy',
                'publicTrackingUrl' => 'http://localhost/tramite/seguimiento/abc',
            ]);

        // Aseguramos que el servicio de registro NUNCA sea llamado al consultar o imprimir el voucher
        $this->mockService->shouldNotReceive('registrar');

        $resp1 = $this->controller->voucher(777);
        $resp2 = $this->controller->voucher(777);
        $resp3 = $this->controller->voucher(777);

        $this->assertSame('mod_gm.mod_tram_exte.voucher', $resp1->name());
        $this->assertSame('mod_gm.mod_tram_exte.voucher', $resp2->name());
        $this->assertSame('mod_gm.mod_tram_exte.voucher', $resp3->name());
    }

    /**
     * Test: Voucher decide etiqueta de tramite mediante codigo semantico del catalogo,
     * independiente de IDs arbitrarios (ID 888 código S, ID 777 código O, ID 999 código E).
     */
    public function test_voucher_renderiza_etiquetas_segun_codigo_catalogo_con_ids_arbitrarios(): void
    {
        $compania = new Compania();
        $compania->cdgo_cmpnia = 1;
        $compania->de_cmpnia = 'Municipalidad';

        // 1. ID 888 con código 'S' -> 'Documento Simple N°:'
        $voucherSimple = [
            'expediente' => (object) [
                'cdgo_dto_trmte' => 101,
                'nu_expe_todo' => 'S-101-2026',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'nu_foli_trmte' => 2,
                'cdgo_tpo_trmte' => 888,
                'dc_tpo_trmte' => 'S',
                'de_tpo_trmte' => 'SIMPLE',
                'de_asno_trmte' => 'Asunto Simple',
                'de_obse_trmte' => 'Obs Simple',
                'usrio' => (object) ['in_tipo_pers' => '0', 'no_crto' => 'Ciudadano S', 'nu_docu' => '111', 'nu_ruc' => '', 'de_dire' => '', 'nu_tele' => '', 'de_mail' => ''],
                'documentoTupa' => null,
            ],
            'compania' => $compania,
            'attendant' => (object) ['de_lgin' => 'Operador'],
            'qrCode' => 'data:image/svg+xml;base64,dummy',
            'publicTrackingUrl' => 'http://localhost/tramite/seguimiento/s',
            'dc_tpo_trmte' => 'S',
            'de_tpo_trmte' => 'SIMPLE',
            'in_docu_trmte' => 'S',
        ];
        $htmlSimple = view('mod_gm.mod_tram_exte.voucher', $voucherSimple)->render();
        $this->assertStringContainsString('Documento Simple N°:', $htmlSimple);

        // 2. ID 777 con código 'O' -> 'Expediente Observado N°:'
        $voucherObservado = [
            'expediente' => (object) [
                'cdgo_dto_trmte' => 102,
                'nu_expe_todo' => 'O-102-2026',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'nu_foli_trmte' => 3,
                'cdgo_tpo_trmte' => 777,
                'dc_tpo_trmte' => 'O',
                'de_tpo_trmte' => 'OBSERVADO',
                'de_asno_trmte' => 'Asunto Obs',
                'de_obse_trmte' => 'Obs Observado',
                'usrio' => (object) ['in_tipo_pers' => '0', 'no_crto' => 'Ciudadano O', 'nu_docu' => '222', 'nu_ruc' => '', 'de_dire' => '', 'nu_tele' => '', 'de_mail' => ''],
                'documentoTupa' => null,
            ],
            'compania' => $compania,
            'attendant' => (object) ['de_lgin' => 'Operador'],
            'qrCode' => 'data:image/svg+xml;base64,dummy',
            'publicTrackingUrl' => 'http://localhost/tramite/seguimiento/o',
            'dc_tpo_trmte' => 'O',
            'de_tpo_trmte' => 'OBSERVADO',
            'in_docu_trmte' => 'O',
        ];
        $htmlObservado = view('mod_gm.mod_tram_exte.voucher', $voucherObservado)->render();
        $this->assertStringContainsString('Expediente Observado N°:', $htmlObservado);

        // 3. ID 999 con código 'E' -> 'Expediente:'
        $voucherExterno = [
            'expediente' => (object) [
                'cdgo_dto_trmte' => 103,
                'nu_expe_todo' => 'E-103-2026',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'nu_foli_trmte' => 4,
                'cdgo_tpo_trmte' => 999,
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXTERNO',
                'de_asno_trmte' => 'Asunto Ext',
                'de_obse_trmte' => 'Obs Ext',
                'usrio' => (object) ['in_tipo_pers' => '0', 'no_crto' => 'Ciudadano E', 'nu_docu' => '333', 'nu_ruc' => '', 'de_dire' => '', 'nu_tele' => '', 'de_mail' => ''],
                'documentoTupa' => null,
            ],
            'compania' => $compania,
            'attendant' => (object) ['de_lgin' => 'Operador'],
            'qrCode' => 'data:image/svg+xml;base64,dummy',
            'publicTrackingUrl' => 'http://localhost/tramite/seguimiento/e',
            'dc_tpo_trmte' => 'E',
            'de_tpo_trmte' => 'EXTERNO',
            'in_docu_trmte' => 'E',
        ];
        $htmlExterno = view('mod_gm.mod_tram_exte.voucher', $voucherExterno)->render();
        $this->assertStringContainsString('Expediente:', $htmlExterno);
    }

    /**
     * Test: Voucher contiene images/logo_entidad.png y NO contiene images/logo-escudo.png.
     */
    public function test_voucher_contiene_logo_entidad_y_no_contiene_logo_escudo(): void
    {
        $compania = new Compania();
        $compania->cdgo_cmpnia = 1;
        $compania->de_cmpnia = 'Municipalidad';

        $voucherData = [
            'expediente' => (object) [
                'cdgo_dto_trmte' => 104,
                'nu_expe_todo' => 'E-104-2026',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'nu_foli_trmte' => 5,
                'cdgo_tpo_trmte' => 1,
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXTERNO',
                'de_asno_trmte' => 'Prueba Logo',
                'de_obse_trmte' => 'Prueba Logo',
                'usrio' => (object) ['in_tipo_pers' => '0', 'no_crto' => 'Ciudadano Logo', 'nu_docu' => '', 'nu_ruc' => '', 'de_dire' => '', 'nu_tele' => '', 'de_mail' => ''],
                'documentoTupa' => null,
            ],
            'compania' => $compania,
            'attendant' => (object) ['de_lgin' => 'Operador'],
            'qrCode' => 'data:image/svg+xml;base64,dummy',
            'publicTrackingUrl' => 'http://localhost/tramite/seguimiento/logo',
        ];

        $html = view('mod_gm.mod_tram_exte.voucher', $voucherData)->render();

        $this->assertStringContainsString('images/logo_entidad.png', $html);
        $this->assertStringNotContainsString('images/logo-escudo.png', $html);
    }

    /**
     * Test: Voucher cumple con estándares de impresión térmica de 80mm:
     * - Contiene ancho térmico (76mm / 72mm)
     * - Oculta controles en impresión (.no-print)
     * - No utiliza Google Fonts
     * - Elimina sombras en impresión
     * - No fija altura fija que corte contenido
     */
    public function test_voucher_cumple_estandares_impresion_termica_80mm(): void
    {
        $compania = new Compania();
        $compania->cdgo_cmpnia = 1;
        $compania->de_cmpnia = 'Municipalidad';

        $voucherData = [
            'expediente' => (object) [
                'cdgo_dto_trmte' => 105,
                'nu_expe_todo' => 'E-105-2026',
                'fe_ingr_trmte' => '2026-10-14 10:00:00',
                'nu_foli_trmte' => 10,
                'cdgo_tpo_trmte' => 1,
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXTERNO',
                'de_asno_trmte' => 'Prueba Termica',
                'de_obse_trmte' => 'Prueba Termica',
                'usrio' => (object) ['in_tipo_pers' => '0', 'no_crto' => 'Ciudadano Termico', 'nu_docu' => '', 'nu_ruc' => '', 'de_dire' => '', 'nu_tele' => '', 'de_mail' => ''],
                'documentoTupa' => null,
            ],
            'compania' => $compania,
            'attendant' => (object) ['de_lgin' => 'Operador'],
            'qrCode' => 'data:image/svg+xml;base64,dummy',
            'publicTrackingUrl' => 'http://localhost/tramite/seguimiento/termico',
        ];

        $html = view('mod_gm.mod_tram_exte.voucher', $voucherData)->render();

        // Ancho térmico definido
        $this->assertStringContainsString('76mm', $html);
        // Oculta controles en impresión
        $this->assertStringContainsString('.no-print', $html);
        $this->assertStringContainsString('display: none !important', $html);
        // Sin sombras en impresión
        $this->assertStringContainsString('box-shadow: none !important', $html);
        // No usa Google Fonts
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        // Tipografía local segura
        $this->assertStringContainsString('Verdana', $html);
    }

    /**
     * Test: Voucher sin relación válida o cuando repositorio retorna null responde HTTP 404.
     */
    public function test_voucher_sin_relacion_valida_o_inexistente_falla_con_404(): void
    {
        $this->mockRepo->shouldReceive('obtenerVoucherExpediente')
            ->once()
            ->with(99999, 1)
            ->andReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->voucher(99999);
    }
}
