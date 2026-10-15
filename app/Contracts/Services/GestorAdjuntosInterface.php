<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Support\ArchivoAdjuntoDto;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface GestorAdjuntosInterface
{
    /**
     * Valida, procesa y almacena un archivo subido en storage privado.
     *
     * @param UploadedFile $archivo
     * @param string $categoria
     * @return ArchivoAdjuntoDto
     */
    public function guardar(UploadedFile $archivo, string $categoria = 'externos'): ArchivoAdjuntoDto;

    /**
     * Resuelve un archivo privado de forma segura impidiendo Path Traversal.
     *
     * @param string $rutaRelativa
     * @return string Ruta absoluta validada dentro del storage privado
     */
    public function resolverRutaSegura(string $rutaRelativa): string;

    /**
     * Elimina un archivo del storage privado en caso de rollback o limpieza.
     */
    public function eliminar(string $rutaRelativa): bool;
}
