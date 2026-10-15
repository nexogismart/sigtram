<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gm;

use App\Exceptions\mod_gm\PdfPageCounterUnavailableException;
use App\Exceptions\mod_gm\PdfValidationException;
use App\Services\mod_gm\mod_tram_exte\PdfPageCounterService;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\PdfFixtureHelper;
use Tests\TestCase;

class PdfPageCounterServiceTest extends TestCase
{
    private PdfPageCounterService $service;
    /** @var array<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PdfPageCounterService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    private function trackTempFile(string $path): string
    {
        $this->temporaryFiles[] = $path;
        return $path;
    }

    /**
     * Test 1: Conteo normal de documento PDF multi-página.
     */
    public function test_conteo_normal_pdf_multi_pagina(): void
    {
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createMultiPagePdf(3));
        $pages = $this->service->countPages($pdfPath);

        $this->assertSame(3, $pages, 'Un PDF sintético de 3 páginas debe ser reportado exactamente con 3 páginas.');
    }

    /**
     * Test 2: Conteo de PDF con revisiones incrementales/firmas (el regex fallaría contando tokens obsoletos).
     */
    public function test_conteo_pdf_con_revisiones_incrementales_o_firmas(): void
    {
        $realPages = 2;
        $fakePageTokens = 7;
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createIncrementalUpdatePdf($realPages, $fakePageTokens));

        // Verificamos que el regex ingenuo encontraría 2 + 7 = 9 ocurrencias
        $rawContent = file_get_contents($pdfPath);
        preg_match_all('/\/Type\s*\/Page(?!s)/', $rawContent, $matches);
        $this->assertGreaterThanOrEqual(9, count($matches[0]), 'El regex ingenuo debe encontrar más ocurrencias en el contenido crudo.');

        // Pero nuestro servicio con Poppler debe retornar exactamente el número real de páginas del catálogo
        $countedPages = $this->service->countPages($pdfPath);
        $this->assertSame($realPages, $countedPages, 'Poppler debe ignorar objetos de revisiones y retornar las 2 páginas reales.');
    }

    /**
     * Test 3: Conteo sintético exacto de 26 páginas (emulando la cantidad de páginas de informe_2.pdf sin usar PII).
     */
    public function test_conteo_exacto_26_paginas_con_fixture_sintetico(): void
    {
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createMultiPagePdf(26));
        $pages = $this->service->countPages($pdfPath);

        $this->assertSame(26, $pages, 'El fixture sintético de 26 páginas debe retornar exactamente 26 páginas.');
    }

    /**
     * Test 4: Manejo controlado de PDF corrupto.
     */
    public function test_pdf_corrupto_lanza_excepcion_controlada(): void
    {
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createCorruptPdf());

        $this->expectException(PdfPageCounterUnavailableException::class);
        $this->service->countPages($pdfPath);
    }

    /**
     * Test 5: Manejo de archivo no-PDF renombrado a .pdf (falla validación de encabezado o MIME).
     */
    public function test_archivo_renombrado_a_pdf_lanza_pdf_validation_exception(): void
    {
        $fakePdf = $this->trackTempFile(PdfFixtureHelper::createRenamedNonPdf());

        $this->expectException(PdfValidationException::class);
        $this->expectExceptionMessage('El archivo no posee un encabezado PDF válido (%PDF-).');
        $this->service->countPages($fakePdf);
    }

    /**
     * Test 5b: Archivo vacío de 0 bytes lanza PdfValidationException.
     */
    public function test_archivo_vacio_lanza_pdf_validation_exception(): void
    {
        $zeroPdf = $this->trackTempFile(PdfFixtureHelper::createZeroBytePdf());

        $this->expectException(PdfValidationException::class);
        $this->expectExceptionMessage('El archivo PDF está vacío (0 bytes).');
        $this->service->countPages($zeroPdf);
    }

    /**
     * Test 7: Binario de pdfinfo inexistente o no ejecutable lanza PdfPageCounterUnavailableException.
     */
    public function test_pdfinfo_no_instalado_o_ruta_invalida_lanza_excepcion_controlada(): void
    {
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createMultiPagePdf(1));
        $invalidService = new PdfPageCounterService('C:\\ruta\\inexistente\\bin\\pdfinfo_fake.exe');

        $this->expectException(PdfPageCounterUnavailableException::class);
        $invalidService->countPages($pdfPath);
    }

    /**
     * Test 8: Timeout de pdfinfo controlado por configuración.
     */
    public function test_pdfinfo_timeout_lanza_pdf_page_counter_unavailable_exception(): void
    {
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createMultiPagePdf(5));
        // Configuramos timeout ultra bajo de 0.000001 segundos para forzar ProcessTimedOutException
        $timeoutService = new PdfPageCounterService(null, 0.000001);

        $this->expectException(PdfPageCounterUnavailableException::class);
        $timeoutService->countPages($pdfPath);
    }

    /**
     * Test 9: Archivo mayor a 20MB lanza PdfValidationException.
     */
    public function test_archivo_mayor_a_20mb_lanza_pdf_validation_exception(): void
    {
        $oversizedPdf = $this->trackTempFile(PdfFixtureHelper::createOversizedPdf());

        $this->expectException(PdfValidationException::class);
        $this->expectExceptionMessage('El archivo supera el tamaño máximo permitido de 20MB.');
        $this->service->countPages($oversizedPdf);
    }

    /**
     * Test con UploadedFile de Laravel / Symfony.
     */
    public function test_conteo_funciona_con_instancia_uploaded_file(): void
    {
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createMultiPagePdf(4));
        $uploadedFile = new UploadedFile($pdfPath, 'documento_prueba.pdf', 'application/pdf', null, true);

        $pages = $this->service->countPages($uploadedFile);
        $this->assertSame(4, $pages);
    }

    /**
     * Test con UploadedFile que no tiene extensión .pdf lanza excepción.
     */
    public function test_uploaded_file_con_extension_invalida_lanza_pdf_validation_exception(): void
    {
        $pdfPath = $this->trackTempFile(PdfFixtureHelper::createMultiPagePdf(1));
        $uploadedFile = new UploadedFile($pdfPath, 'documento_prueba.docx', 'application/pdf', null, true);

        $this->expectException(PdfValidationException::class);
        $this->expectExceptionMessage('El archivo debe tener extensión .pdf.');
        $this->service->countPages($uploadedFile);
    }
}
