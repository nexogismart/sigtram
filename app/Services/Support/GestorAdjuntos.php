<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Support\ArchivoAdjuntoDto;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gestor centralizado de adjuntos privados de SIGTRAM.
 * Implementa SEC-11 y SEC-14:
 * - Almacenamiento en storage privado (no en public/ ni storage:link).
 * - Generación de nombres UUID impredecibles.
 * - Validación estricta de extensiones permitidas, doble extensión y MIME real.
 * - Prevención de Path Traversal.
 * - Cálculo de hash SHA-256 para verificación de integridad.
 */
class GestorAdjuntos implements GestorAdjuntosInterface
{
    /**
     * Extensiones permitidas institucionalmente.
     *
     * @var array<string>
     */
    private const EXTENSIONES_PERMITIDAS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'jpg', 'jpeg', 'png', 'zip'
    ];

    /**
     * Mapeo de extensiones a tipos MIME reales admitidos.
     *
     * @var array<string, array<string>>
     */
    private const MIMES_PERMITIDOS = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    /**
     * Tamaño máximo en bytes (25 MB).
     */
    private const TAMANO_MAXIMO_BYTES = 25 * 1024 * 1024;

    private readonly Filesystem $disk;

    public function __construct(?Filesystem $disk = null)
    {
        $this->disk = $disk ?? Storage::disk('local');
    }

    /**
     * {@inheritdoc}
     */
    public function guardar(UploadedFile $archivo, string $categoria = 'externos'): ArchivoAdjuntoDto
    {
        if (!$archivo->isValid()) {
            throw new InvalidArgumentException('El archivo subido no es válido o está corrupto.');
        }

        if ($archivo->getSize() > self::TAMANO_MAXIMO_BYTES) {
            throw new InvalidArgumentException('El archivo excede el tamaño máximo permitido de 25 MB.');
        }

        $nombreOriginal = $archivo->getClientOriginalName();
        $extension = strtolower($archivo->getClientOriginalExtension());

        // 1. Validar doble extensión maliciosa (ej. archivo.php.pdf, script.exe.jpg)
        $partes = explode('.', $nombreOriginal);
        if (count($partes) > 2) {
            $extensionIntermedia = strtolower($partes[count($partes) - 2]);
            $extensionesPeligrosas = ['php', 'phtml', 'exe', 'sh', 'bat', 'cmd', 'js', 'vbs'];
            if (in_array($extensionIntermedia, $extensionesPeligrosas, true)) {
                throw new InvalidArgumentException('Nombre de archivo sospechoso con doble extensión detectada.');
            }
        }

        // 2. Validar extensión permitida
        if (!in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            throw new InvalidArgumentException("La extensión '.{$extension}' no está permitida en el sistema.");
        }

        // 3. Validar MIME type real
        $realMime = (string) $archivo->getMimeType();
        $mimesValidos = self::MIMES_PERMITIDOS[$extension];
        if (!in_array($realMime, $mimesValidos, true)) {
            throw new InvalidArgumentException("El contenido del archivo no coincide con un formato válido para '.{$extension}'.");
        }

        // 4. Generar ruta con UUID en estructura YYYY/MM/DD
        $uuid = (string) Str::uuid();
        $fechaRuta = date('Y/m/d');
        $nombreFisico = "{$uuid}.{$extension}";
        $rutaRelativa = "documentos/{$categoria}/{$fechaRuta}/{$nombreFisico}";

        // 5. Almacenar en disco privado
        $path = $this->disk->putFileAs("documentos/{$categoria}/{$fechaRuta}", $archivo, $nombreFisico);
        if ($path === false) {
            throw new RuntimeException('No se pudo guardar el archivo en el storage privado.');
        }

        // 6. Calcular hash SHA-256
        $contenido = (string) $this->disk->get($rutaRelativa);
        $sha256 = hash('sha256', $contenido);

        return new ArchivoAdjuntoDto(
            uuid: $uuid,
            nombreOriginal: $nombreOriginal,
            rutaRelativa: $rutaRelativa,
            mimeTipo: $realMime,
            extension: $extension,
            tamanoBytes: (int) $archivo->getSize(),
            sha256: $sha256
        );
    }

    /**
     * {@inheritdoc}
     */
    public function resolverRutaSegura(string $rutaRelativa): string
    {
        // 1. Rechazo estricto de rutas absolutas, unidades Windows, bytes nulos y traversal
        if (
            str_contains($rutaRelativa, "\0") ||
            str_contains($rutaRelativa, '..') ||
            (bool) preg_match('/^[a-zA-Z]:/i', $rutaRelativa) ||
            str_starts_with($rutaRelativa, '/') ||
            str_starts_with($rutaRelativa, '\\')
        ) {
            throw new InvalidArgumentException('Intento de Path Traversal o ruta absoluta detectado en la ruta del archivo.');
        }

        $clean = ltrim(str_replace('\\', '/', trim($rutaRelativa)), '/');
        $candidatas = [];

        // Almacenamiento privado canónico de la migración.
        if ($this->disk->exists($clean)) {
            $candidatas[] = [
                'path' => $this->disk->path($clean),
                'root' => $this->disk->path(''),
            ];
        } elseif (!str_contains($clean, '/')) {
            if ($this->disk->exists("documentos/internos/{$clean}")) {
                $candidatas[] = [
                    'path' => $this->disk->path("documentos/internos/{$clean}"),
                    'root' => $this->disk->path(''),
                ];
            } elseif ($this->disk->exists("mod_gene/mod_tram_inter/adjunto/{$clean}")) {
                $candidatas[] = [
                    'path' => $this->disk->path("mod_gene/mod_tram_inter/adjunto/{$clean}"),
                    'root' => $this->disk->path(''),
                ];
            }
        }

        // Compatibilidad temporal con módulos migrados que todavía escriben en el disco public.
        $publicRoot = storage_path('app/public/adjuntos');
        $publicRelative = str_starts_with($clean, 'adjuntos/')
            ? substr($clean, strlen('adjuntos/'))
            : $clean;
        $candidatas[] = [
            'path' => $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $publicRelative),
            'root' => $publicRoot,
        ];

        // Compatibilidad de sólo lectura con adjuntos planos del despliegue legacy.
        if (!str_contains($clean, '/')) {
            $legacyRoot = (string) config('filesystems.legacy_sigtram_root', base_path('../sigtram'));
            if (!preg_match('/^(?:[a-zA-Z]:[\\\\\/]|[\\\\\/])/', $legacyRoot)) {
                $legacyRoot = base_path($legacyRoot);
            }

            foreach ([
                'Presentacion/mod_gm/mod_tram_exte/adjunto',
                'Presentacion/mod_gene/mod_tram_inter/adjunto',
            ] as $subdir) {
                $root = rtrim($legacyRoot, '/\\') . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
                $candidatas[] = [
                    'path' => $root . DIRECTORY_SEPARATOR . $clean,
                    'root' => $root,
                ];
            }
        }

        foreach ($candidatas as $candidata) {
            $realRoot = realpath($candidata['root']);
            $realPath = realpath($candidata['path']);
            if ($realRoot === false || $realPath === false || !is_file($realPath)) {
                continue;
            }

            $normalizedRoot = rtrim(
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realRoot),
                DIRECTORY_SEPARATOR
            );
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realPath);
            if ($normalizedPath === $normalizedRoot
                || str_starts_with($normalizedPath, $normalizedRoot . DIRECTORY_SEPARATOR)) {
                return $realPath;
            }
        }

        throw new RuntimeException('El archivo solicitado no existe en una ubicación autorizada.');
    }

    /**
     * {@inheritdoc}
     */
    public function eliminar(string $rutaRelativa): bool
    {
        if (str_contains($rutaRelativa, '..') || str_starts_with($rutaRelativa, '/') || str_starts_with($rutaRelativa, '\\')) {
            return false;
        }

        return $this->disk->delete($rutaRelativa);
    }
}
