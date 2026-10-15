<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfFixtureHelper
{
    /**
     * Genera un PDF sintético válido con el número exacto de páginas indicado.
     * Retorna la ruta absoluta del archivo temporal generado.
     */
    public static function createMultiPagePdf(int $pages = 1): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page { margin: 20px; } .page { page-break-after: always; height: 100%; }</style></head><body>';
        for ($i = 1; $i <= $pages; $i++) {
            $break = ($i < $pages) ? 'style="page-break-after: always;"' : '';
            $html .= "<div {$break}><h1>Pagina {$i} de {$pages}</h1><p>Contenido sintetico de prueba sin PII.</p></div>";
        }
        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->render();

        $tempPath = tempnam(sys_get_temp_dir(), 'test_pdf_') . '.pdf';
        file_put_contents($tempPath, $dompdf->output());

        return $tempPath;
    }

    /**
     * Genera un PDF sintético con actualizaciones incrementales simuladas (revisión firmada o borrador previo).
     * El documento tiene $realPages reales, pero contiene $fakePageTags instancias extra de '/Type /Page'
     * en streams de comentarios o metadata para provocar falsos positivos en contadores regex ingenuos.
     */
    public static function createIncrementalUpdatePdf(int $realPages = 2, int $fakePageTags = 7): string
    {
        $basePdfPath = self::createMultiPagePdf($realPages);
        $baseContent = file_get_contents($basePdfPath);

        // Agregamos un bloque de revisión incremental válido para PDF con comentarios y metadatos no activos
        $incrementalContent = "\n% Incremental revision update (simulated signature/revision)\n";
        for ($i = 1; $i <= $fakePageTags; $i++) {
            $incrementalContent .= "% Obsolete revision object: << /Type /Page /Parent 2 0 R /Contents [ 99 0 R ] >>\n";
            $incrementalContent .= "9{$i} 0 obj\n<< /Type /Metadata /Desc (Document revision with dummy /Type /Page token) >>\nendobj\n";
        }
        $incrementalContent .= "%%EOF\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'test_incremental_') . '.pdf';
        file_put_contents($tempPath, $baseContent . $incrementalContent);

        if (file_exists($basePdfPath)) {
            @unlink($basePdfPath);
        }

        return $tempPath;
    }

    /**
     * Crea un archivo con extensión .pdf pero con encabezado y contenido corrupto/inválido.
     */
    public static function createCorruptPdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_corrupt_') . '.pdf';
        // Inicia con encabezado PDF válido para pasar la primera validación, pero el cuerpo es basura
        file_put_contents($tempPath, "%PDF-1.4\nGarbage data that violates PDF structure \x00\xFF\xDE\xAD\xBE\xEF\n%%EOF");

        return $tempPath;
    }

    /**
     * Crea un archivo no-PDF renombrado a .pdf (e.g. texto plano).
     */
    public static function createRenamedNonPdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_fake_') . '.pdf';
        file_put_contents($tempPath, "This is plain text pretending to be a PDF document.\n");

        return $tempPath;
    }

    /**
     * Crea un archivo .pdf de 0 bytes.
     */
    public static function createZeroBytePdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_zero_') . '.pdf';
        file_put_contents($tempPath, '');

        return $tempPath;
    }

    /**
     * Crea un archivo .pdf que supera 20MB.
     */
    public static function createOversizedPdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_oversized_') . '.pdf';
        $handle = fopen($tempPath, 'wb');
        fwrite($handle, "%PDF-1.4\n");
        // Escribir 21 MB
        $chunk = str_repeat('A', 1024 * 1024); // 1 MB
        for ($i = 0; $i < 21; $i++) {
            fwrite($handle, $chunk);
        }
        fwrite($handle, "\n%%EOF");
        fclose($handle);

        return $tempPath;
    }
}
