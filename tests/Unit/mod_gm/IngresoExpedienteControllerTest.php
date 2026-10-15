<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gm;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Contracts\Services\mod_gm\mod_tram_exte\PdfPageCounterInterface;
use App\Exceptions\mod_gm\PdfPageCounterUnavailableException;
use App\Exceptions\mod_gm\PdfValidationException;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Http\Requests\IngresoExpedienteRequest;
use App\Models\User;
use App\Services\IngresoExpedienteService;
use App\Support\SessionKeys;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class IngresoExpedienteControllerTest extends TestCase
{
    private IngresoExpedienteService $mockService;
    private IngresoExpedienteRepositoryInterface $mockRepo;
    private PdfPageCounterInterface $mockPdfCounter;
    private IngresoExpedienteController $controller;

    protected function setUp(): void
    {
        parent::setUp();

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
     * Test 10: Discrepancia entre folios ingresados manualmente en frontend y folios reales del PDF.
     * El backend recalcula los folios con el servicio y prevalece el conteo real sobre el frontend.
     */
    public function test_discrepancia_folios_frontend_recalcula_con_conteo_real_en_backend(): void
    {
        $user = new User();
        $user->cdgo_lgin = 99;
        Auth::setUser($user);

        $fakePdf = UploadedFile::fake()->create('expediente.pdf', 500, 'application/pdf');

        // El usuario envió 5 folios en el formulario
        $requestData = [
            'folios' => 5,
            'asunto' => 'Solicitud de licencia',
            'cdgo_tpo_trmte' => 1,
        ];

        // Pero el servicio de conteo de páginas determina que en realidad tiene 26 páginas
        $this->mockPdfCounter->shouldReceive('countPages')
            ->once()
            ->with($fakePdf)
            ->andReturn(26);

        // El servicio de registro debe recibir 'folios' => 26 (recalculado)
        $this->mockService->shouldReceive('registrar')
            ->once()
            ->with(
                Mockery::on(function (array $data) {
                    return isset($data['folios']) && $data['folios'] === 26;
                }),
                $user,
                1,
                Mockery::any(),
                $fakePdf
            )
            ->andReturn([
                'id' => 888,
                'numero' => 'E-1234-2026',
            ]);

        $request = Mockery::mock(IngresoExpedienteRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($requestData);
        $request->shouldReceive('file')->with('fle_adjunto')->andReturn($fakePdf);
        $request->shouldReceive('input')->with('cdgo_tpo_observado', 0)->andReturn(0);
        $request->shouldReceive('input')->with('cdgo_tpo_trmte', 0)->andReturn(1);
        $request->shouldReceive('input')->with('cdgo_area_observado', 0)->andReturn(0);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');

        $response = $this->controller->store($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame(26, $data['folios'], 'La respuesta debe confirmar los 26 folios reales recalculados.');
        $this->assertSame('E-1234-2026', $data['numero_expediente']);
        $this->assertSame(888, $data['expediente_id']);
    }

    /**
     * Test 11: Registro exitoso retorna las 3 URLs nombradas requeridas (voucher_url, show_url, inbox_url).
     */
    public function test_store_exitoso_retorna_todas_las_urls_nombradas(): void
    {
        $user = new User();
        $user->cdgo_lgin = 10;
        Auth::setUser($user);

        $requestData = [
            'folios' => 12,
            'asunto' => 'Trámite sin archivo',
            'cdgo_tpo_trmte' => 2,
        ];

        $this->mockService->shouldReceive('registrar')
            ->once()
            ->andReturn([
                'id' => 777,
                'numero' => 'E-7777-2026',
            ]);

        $request = Mockery::mock(IngresoExpedienteRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($requestData);
        $request->shouldReceive('file')->with('fle_adjunto')->andReturn(null);
        $request->shouldReceive('input')->with('cdgo_tpo_observado', 0)->andReturn(0);
        $request->shouldReceive('input')->with('cdgo_tpo_trmte', 0)->andReturn(2);
        $request->shouldReceive('input')->with('cdgo_area_observado', 0)->andReturn(0);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');

        $response = $this->controller->store($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('voucher_url', $data);
        $this->assertArrayHasKey('show_url', $data);
        $this->assertArrayHasKey('inbox_url', $data);

        $this->assertSame(route('tramites.voucher', ['id' => 777], false), $data['voucher_url']);
        $this->assertSame(route('tramites.busca_expedi', ['cdgo_tram' => 777], false), $data['show_url']);
        $this->assertSame(route('tramites.bandeja', [], false), $data['inbox_url']);
    }

    /**
     * Test endpoint contarFolios sin archivo adjunto retorna 422 con folios: 0.
     */
    public function test_contar_folios_sin_archivo_retorna_422(): void
    {
        $request = Request::create('/tramites/contar-folios', 'POST');
        $response = $this->controller->contarFolios($request);

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame(0, $data['folios']);
    }

    /**
     * Test endpoint contarFolios con archivo válido retorna 200 con folios y source pdfinfo.
     */
    public function test_contar_folios_con_archivo_valido_retorna_conteo_exitoso(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 300, 'application/pdf');

        $this->mockPdfCounter->shouldReceive('countPages')
            ->once()
            ->with($file)
            ->andReturn(15);

        $request = Request::create('/tramites/contar-folios', 'POST', [], [], ['fle_adjunto' => $file]);
        $response = $this->controller->contarFolios($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame(15, $data['folios']);
        $this->assertSame('pdfinfo', $data['source']);
    }

    /**
     * Test endpoint contarFolios cuando falla validación del PDF retorna 422.
     */
    public function test_contar_folios_con_pdf_invalido_retorna_422(): void
    {
        $file = UploadedFile::fake()->create('malicioso.pdf', 100, 'application/pdf');

        $this->mockPdfCounter->shouldReceive('countPages')
            ->once()
            ->with($file)
            ->andThrow(new PdfValidationException('El archivo no posee un encabezado PDF válido (%PDF-).'));

        $request = Request::create('/tramites/contar-folios', 'POST', [], [], ['fle_adjunto' => $file]);
        $response = $this->controller->contarFolios($request);

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame(0, $data['folios']);
        $this->assertStringContainsString('El archivo no posee un encabezado PDF válido', $data['message']);
    }

    /**
     * Test endpoint contarFolios cuando Poppler no está disponible retorna 503 controlado.
     */
    public function test_contar_folios_servicio_no_disponible_retorna_503(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->mockPdfCounter->shouldReceive('countPages')
            ->once()
            ->with($file)
            ->andThrow(new PdfPageCounterUnavailableException('Servicio temporalmente no disponible'));

        $request = Request::create('/tramites/contar-folios', 'POST', [], [], ['fle_adjunto' => $file]);
        $response = $this->controller->contarFolios($request);

        $this->assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame(0, $data['folios']);
        $this->assertStringContainsString('No se pudo determinar automáticamente', $data['message']);
    }

    /**
     * Test store con PDF corrupto o inválido retorna 422 y no registra expediente.
     */
    public function test_store_con_pdf_invalido_aborta_con_422(): void
    {
        $user = new User();
        $user->cdgo_lgin = 10;
        Auth::setUser($user);

        $file = UploadedFile::fake()->create('corrupto.pdf', 100, 'application/pdf');

        $this->mockPdfCounter->shouldReceive('countPages')
            ->once()
            ->with($file)
            ->andThrow(new PdfValidationException('El archivo no posee un encabezado PDF válido (%PDF-).'));

        // El servicio de registro NO debe ser invocado
        $this->mockService->shouldNotReceive('registrar');

        $request = Mockery::mock(IngresoExpedienteRequest::class);
        $request->shouldReceive('validated')->once()->andReturn(['asunto' => 'Prueba']);
        $request->shouldReceive('file')->with('fle_adjunto')->andReturn($file);
        $request->shouldReceive('input')->with('cdgo_tpo_observado', 0)->andReturn(0);
        $request->shouldReceive('input')->with('cdgo_tpo_trmte', 0)->andReturn(0);
        $request->shouldReceive('input')->with('cdgo_area_observado', 0)->andReturn(0);

        $response = $this->controller->store($request);
        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('El archivo PDF adjunto no es válido', $data['message']);
    }

    /**
     * Test store sin sesión de usuario retorna 401.
     */
    public function test_store_sin_usuario_autenticado_retorna_401(): void
    {
        Auth::logout();

        $request = new IngresoExpedienteRequest();
        $request->replace(['asunto' => 'Prueba']);
        $request->setContainer($this->app);

        $response = $this->controller->store($request);
        $this->assertSame(401, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame('Sesión no válida o expirada.', $data['message']);
    }
}
