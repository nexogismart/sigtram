<?php

declare(strict_types=1);

namespace App\Contracts\Services\mod_gm\mod_tram_exte;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface PdfPageCounterInterface
{
    /**
     * Cuenta de forma segura y fiable las páginas reales de un archivo PDF utilizando pdfinfo de Poppler.
     *
     * @param UploadedFile|string $file Archivo subido o ruta local a inspeccionar.
     * @return int Cantidad de páginas reales mayor que cero.
     *
     * @throws \App\Exceptions\mod_gm\PdfValidationException Si el archivo no es un PDF válido o es ilegible.
     * @throws \App\Exceptions\mod_gm\PdfPageCounterUnavailableException Si pdfinfo no está disponible o falla su ejecución.
     */
    public function countPages(UploadedFile|string $file): int;
}
