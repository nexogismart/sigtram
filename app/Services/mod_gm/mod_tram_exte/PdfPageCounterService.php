<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Contracts\Services\mod_gm\mod_tram_exte\PdfPageCounterInterface;
use App\Exceptions\mod_gm\PdfPageCounterUnavailableException;
use App\Exceptions\mod_gm\PdfValidationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class PdfPageCounterService implements PdfPageCounterInterface
{
    private const MAX_BYTES = 20971520; // 20 MB

    public function __construct(
        private readonly ?string $binary = null,
        private readonly float $timeout = 10.0
    ) {}

    /**
     * {@inheritdoc}
     */
    public function countPages(UploadedFile|string $file): int
    {
        $realPath = $this->validarArchivo($file);
        $binary = $this->resolverBinario();

        try {
            $process = new Process([$binary, $realPath]);
            $process->setTimeout($this->timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::warning('pdfinfo execution failed', [
                    'exit_code' => $process->getExitCode(),
                    'error_output' => $process->getErrorOutput(),
                ]);

                throw new PdfPageCounterUnavailableException(
                    'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte'
                );
            }

            $output = $process->getOutput();
            if (preg_match('/^Pages:\s+(\d+)\b/m', $output, $matches)) {
                $pages = (int) $matches[1];
                if ($pages > 0) {
                    return $pages;
                }
            }

            Log::warning('pdfinfo output did not contain valid Pages attribute', [
                'output' => $output,
            ]);

            throw new PdfPageCounterUnavailableException(
                'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte'
            );
        } catch (ProcessTimedOutException $e) {
            Log::error('pdfinfo process timed out', [
                'timeout' => $this->timeout,
                'message' => $e->getMessage(),
            ]);

            throw new PdfPageCounterUnavailableException(
                'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte'
            );
        } catch (PdfValidationException | PdfPageCounterUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Unexpected error running pdfinfo: ' . $e->getMessage());

            throw new PdfPageCounterUnavailableException(
                'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte'
            );
        }
    }

    /**
     * Valida estrictamente el archivo antes de pasarlo a Poppler.
     *
     * @return string Ruta real del archivo en disco.
     * @throws PdfValidationException
     */
    private function validarArchivo(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            if (!$file->isValid()) {
                throw new PdfValidationException('El archivo subido no es válido o está incompleto.');
            }

            $realPath = $file->getRealPath();
            $clientExt = strtolower($file->getClientOriginalExtension());
            if ($clientExt !== 'pdf') {
                throw new PdfValidationException('El archivo debe tener extensión .pdf.');
            }
        } else {
            $realPath = (string) realpath($file);
            if (!is_file($realPath) || !is_readable($realPath)) {
                throw new PdfValidationException('El archivo especificado no existe o no tiene permisos de lectura.');
            }

            $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                throw new PdfValidationException('El archivo debe tener extensión .pdf.');
            }
        }

        if (!$realPath || !is_file($realPath) || !is_readable($realPath)) {
            throw new PdfValidationException('El archivo PDF no se encuentra o no se puede leer.');
        }

        $fileSize = filesize($realPath);
        if ($fileSize === false || $fileSize === 0) {
            throw new PdfValidationException('El archivo PDF está vacío (0 bytes).');
        }

        if ($fileSize > self::MAX_BYTES) {
            throw new PdfValidationException('El archivo supera el tamaño máximo permitido de 20MB.');
        }

        // Validación de magic header %PDF-
        $handle = @fopen($realPath, 'rb');
        if ($handle === false) {
            throw new PdfValidationException('No se pudo abrir el archivo para verificar el encabezado.');
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            throw new PdfValidationException('El archivo no posee un encabezado PDF válido (%PDF-).');
        }

        // Validación de MIME type real con fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $realPath) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime !== 'application/pdf' && $mime !== 'application/x-pdf') {
            throw new PdfValidationException('El tipo MIME real del archivo no corresponde a un documento PDF.');
        }

        return $realPath;
    }

    /**
     * Resuelve de manera portable la ruta ejecutable de pdfinfo.
     *
     * @return string
     * @throws PdfPageCounterUnavailableException
     */
    private function resolverBinario(): string
    {
        if ($this->binary !== null) {
            if ($this->binary !== '' && $this->isExecutable($this->binary)) {
                return $this->binary;
            }

            Log::error('Explicit pdfinfo binary could not be found or executed: ' . $this->binary);

            throw new PdfPageCounterUnavailableException(
                'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte'
            );
        }

        $configured = config('services.pdfinfo.binary', env('PDFINFO_BINARY', 'pdfinfo'));
        if (is_string($configured) && $configured !== '' && $this->isExecutable($configured)) {
            return $configured;
        }

        // Candidatos comunes de respaldo según sistema operativo
        $fallbacks = PHP_OS_FAMILY === 'Windows'
            ? [
                'pdfinfo',
                'pdfinfo.exe',
                getenv('LOCALAPPDATA') . '\\Microsoft\\WinGet\\Packages\\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\\poppler-25.07.0\\Library\\bin\\pdfinfo.exe',
                'C:\\Program Files\\poppler\\bin\\pdfinfo.exe',
                'C:\\laragon\\bin\\poppler\\bin\\pdfinfo.exe',
            ]
            : [
                'pdfinfo',
                '/usr/bin/pdfinfo',
                '/usr/local/bin/pdfinfo',
            ];

        foreach ($fallbacks as $fb) {
            if ($this->isExecutable($fb)) {
                return $fb;
            }
        }

        Log::error('pdfinfo binary could not be located on the system');

        throw new PdfPageCounterUnavailableException(
            'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte'
        );
    }

    private function isExecutable(string $path): bool
    {
        if (is_file($path) && is_executable($path)) {
            return true;
        }

        // Si es un comando simple en PATH, probar con Process
        try {
            $cmd = PHP_OS_FAMILY === 'Windows' ? ['where.exe', $path] : ['which', $path];
            $proc = new Process($cmd);
            $proc->setTimeout(2.0);
            $proc->run();
            return $proc->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }
}
