<?php

namespace App\Services\mod_ti;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

/**
 * Generador de firmas institucionales de correo
 * (migrado desde sigtram_antiguo/Negocio/mod_ti/firma_email/firma_email.class.php).
 */
class FirmaEmailService
{
    private const CANVAS_W = 484;

    private const CANVAS_H = 153;

    private const COLOR_BLACK = [19, 20, 20];

    private const COLOR_RED = [230, 0, 0];

    private const COLOR_WHITE = [255, 255, 255];

    private const POS_X_INFO = 135;

    private const LOGO_TARGET_SIZE = 125;

    /** @var array<string, \GdImage|resource|null> */
    private array $resourceCache = [];

    private readonly string $schemaSistema;

    private readonly string $assetsPath;

    private readonly string $tempPath;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $this->assetsPath = public_path('mod_ti/firma_email');
        $this->tempPath = storage_path('app/mod_ti/firma_email/temp');
        if (! is_dir($this->tempPath)) {
            @mkdir($this->tempPath, 0775, true);
        }
    }

    /**
     * @return array{success: bool, data?: array, error?: string}
     */
    public function buscarPorDni(string $dni): array
    {
        $dni = trim($dni);
        if ($dni === '' || ! preg_match('/^\d{8}$/', $dni)) {
            return $this->fail('El DNI es obligatorio (8 dígitos).');
        }

        try {
            $row = DB::selectOne(
                "SELECT
                    pu.cdgo_usrios,
                    TRIM(pu.no_crto) AS nombre,
                    TRIM(pc.de_crgo) AS cargo,
                    TRIM(pu.de_mail) AS correo,
                    TRIM(pu.nu_tele) AS celular
                 FROM {$this->schemaSistema}p_usrios pu
                 LEFT JOIN {$this->schemaSistema}p_lgin pl ON pu.cdgo_usrios = pl.cdgo_usrios
                 LEFT JOIN {$this->schemaSistema}r_crgos_lgin rcl ON pl.cdgo_lgin = rcl.cdgo_lgin
                 LEFT JOIN {$this->schemaSistema}p_crgos pc ON rcl.cdgo_crgo = pc.cdgo_crgo
                 WHERE pu.nu_docu = ? AND pu.in_esta = '1'
                 ORDER BY (pc.de_crgo IS NOT NULL) DESC, rcl.in_crgo_prmro DESC, pc.cdgo_crgo ASC
                 LIMIT 1",
                [$dni]
            );

            if (! $row) {
                return $this->fail('No se encontró ningún funcionario con ese DNI en SIGTRAM');
            }

            return $this->ok([
                'cdgo_usrios' => (int) $row->cdgo_usrios,
                'nombre' => (string) ($row->nombre ?? ''),
                'cargo' => (string) ($row->cargo ?? ''),
                'correo' => (string) ($row->correo ?? ''),
                'celular' => (string) ($row->celular ?? ''),
            ]);
        } catch (Throwable $e) {
            Log::error('[firma_email] buscarPorDni: ' . $e->getMessage());

            return $this->fail('Error interno del servidor');
        }
    }

    /**
     * @param  array{titulo?: string, nombre: string, cargo?: string, celular?: string, anexo?: string, correo?: string}  $data
     * @return array{success: bool, data?: array{base64: string, filename: string}, error?: string}
     */
    public function generarIndividual(array $data, string $filename = 'generada.png'): array
    {
        $nombre = trim((string) ($data['nombre']));
        if ($nombre === '') {
            return $this->fail('El nombre es obligatorio');
        }

        if (! extension_loaded('gd')) {
            return $this->fail('La extensión GD de PHP no está disponible.');
        }

        try {
            $base64 = $this->generarImagenBase64($data);

            return $this->ok([
                'base64' => $base64,
                'filename' => $filename,
            ]);
        } catch (Throwable $e) {
            Log::error('[firma_email] generarIndividual: ' . $e->getMessage());

            return $this->fail('Error al generar imagen');
        }
    }

    /**
     * @return array{success: bool, data?: array{zip_file: string, results: list<array>}, error?: string}
     */
    public function generarMasivo(string $csvPath): array
    {
        if (! extension_loaded('gd')) {
            return $this->fail('La extensión GD de PHP no está disponible.');
        }

        try {
            $processData = $this->procesarCsv($csvPath);
            if (! empty($processData['error']) && empty($processData['results'])) {
                return $this->fail((string) $processData['error']);
            }

            if (empty($processData['results'])) {
                return $this->fail('No se detectaron filas válidas en el archivo CSV.');
            }

            $zipName = 'firma_masivo_' . time();
            $zipResult = $this->crearZip($processData, $zipName);
            if (empty($zipResult['success'])) {
                return $this->fail('No se pudo crear el archivo ZIP.');
            }

            return $this->ok([
                'zip_file' => $zipResult['file'],
                'results' => $processData['results'],
            ]);
        } catch (Throwable $e) {
            Log::error('[firma_email] generarMasivo: ' . $e->getMessage());

            return $this->fail('Error procesando el archivo CSV');
        }
    }

    /**
     * Ruta absoluta segura a un archivo temporal (solo basename, png/zip).
     */
    public function rutaTempSegura(string $archivo): ?string
    {
        $archivo = basename($archivo);
        $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'zip'], true)) {
            return null;
        }

        $path = $this->tempPath . DIRECTORY_SEPARATOR . $archivo;
        if (! is_file($path)) {
            return null;
        }

        $realTemp = realpath($this->tempPath);
        $realFile = realpath($path);
        if ($realTemp === false || $realFile === false || ! str_starts_with($realFile, $realTemp)) {
            return null;
        }

        return $realFile;
    }

    /**
     * @param  array{titulo?: string, nombre: string, cargo?: string, celular?: string, anexo?: string, correo?: string}  $data
     */
    public function generarImagenBase64(array $data): string
    {
        $im = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        $clr = $this->allocateColors($im);
        imagefill($im, 0, 0, $clr['white']);

        $this->drawBranding($im, $clr['red']);
        $yOffset = $this->drawTextInfo($im, $data, $clr);
        $this->drawContactSection($im, $data, $clr, $yOffset);

        ob_start();
        imagepng($im);
        $imgData = (string) ob_get_clean();
        imagedestroy($im);

        return base64_encode($imgData);
    }

    /**
     * @param  array{titulo?: string, nombre: string, cargo?: string, celular?: string, anexo?: string, correo?: string}  $data
     * @return array{success: bool, file: string, path: string}
     */
    public function generarImagen(array $data, string $outputName, ?string $customDir = null): array
    {
        $im = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        $clr = $this->allocateColors($im);
        imagefill($im, 0, 0, $clr['white']);

        $this->drawBranding($im, $clr['red']);
        $yOffset = $this->drawTextInfo($im, $data, $clr);
        $this->drawContactSection($im, $data, $clr, $yOffset);

        $baseDir = $customDir ?: ($this->tempPath . DIRECTORY_SEPARATOR);
        if (! is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $outputPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $outputName . '.png';
        imagepng($im, $outputPath);
        $success = is_file($outputPath);
        imagedestroy($im);

        return ['success' => $success, 'file' => $outputName . '.png', 'path' => $outputPath];
    }

    /**
     * @return array{results: list<array>, batchPath?: string, batchFolder?: string, error: ?string}
     */
    public function procesarCsv(string $filePath): array
    {
        $this->limpiarTemporales();

        $results = [];
        $batchFolder = 'batch_' . time() . '_' . random_int(100, 999) . DIRECTORY_SEPARATOR;
        $fullBatchPath = $this->tempPath . DIRECTORY_SEPARATOR . $batchFolder;

        if (! is_dir($this->tempPath)) {
            @mkdir($this->tempPath, 0775, true);
        }
        if (! is_dir($fullBatchPath)) {
            @mkdir($fullBatchPath, 0775, true);
        }

        if (! is_writable($this->tempPath)) {
            return ['error' => 'La carpeta temp no tiene permisos de escritura', 'results' => []];
        }

        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return ['error' => 'El archivo está vacío o es ilegible', 'results' => []];
        }

        $content = preg_replace('/^[\xEF\xBB\xBF\xFF\xFE\xFE\xFF]/', '', $content) ?? $content;
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines)));

        if (count($lines) < 2) {
            return ['error' => 'El archivo debe tener un encabezado y al menos una fila de datos', 'results' => []];
        }

        $delimiter = (str_contains($lines[0], ';')) ? ';' : ',';
        $isFirst = true;

        foreach ($lines as $line) {
            if ($isFirst) {
                $isFirst = false;
                continue;
            }

            $row = str_getcsv($line, $delimiter);
            if (count($row) === 1 && str_contains((string) $row[0], $delimiter)) {
                $row = str_getcsv((string) $row[0], $delimiter);
            }

            $row = array_map(static function ($val) {
                return trim(preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', (string) $val) ?? (string) $val);
            }, $row);

            if (count($row) < 3) {
                continue;
            }
            if (count($row) < 7) {
                $row = array_pad($row, 7, '');
            }

            if ($row[0] === '' || $row[2] === '') {
                continue;
            }

            $data = [
                'dni' => $row[0],
                'titulo' => $row[1],
                'nombre' => $row[2],
                'cargo' => $row[3],
                'celular' => $row[4],
                'anexo' => $row[5],
                'correo' => $row[6],
            ];

            $safeName = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $data['dni']) ?: ('firma_' . count($results));
            $res = $this->generarImagen($data, $safeName, $fullBatchPath);
            if ($res['success']) {
                $results[] = [
                    'nombre' => $data['nombre'],
                    'success' => true,
                    'message' => 'Firma generada correctamente',
                    'file' => $batchFolder . $res['file'],
                ];
            }
        }

        if (count($results) === 0) {
            $debug = 'Leído: ' . count($lines) . " líneas. Delim: '{$delimiter}'. Primera fila data: " . ($lines[1]);

            return ['error' => "No se pudieron procesar las filas. {$debug}", 'results' => []];
        }

        return [
            'results' => $results,
            'batchPath' => $fullBatchPath,
            'batchFolder' => $batchFolder,
            'error' => null,
        ];
    }

    /**
     * @param  array{results: list<array{file: string}>, batchPath: string}  $processData
     * @return array{success: bool, file?: string}
     */
    public function crearZip(array $processData, string $zipName): array
    {
        $zip = new ZipArchive;
        $zipPath = $this->tempPath . DIRECTORY_SEPARATOR . $zipName . '.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false];
        }

        foreach ($processData['results'] as $f) {
            $fPath = $this->tempPath . DIRECTORY_SEPARATOR . $f['file'];
            if (is_file($fPath)) {
                $zip->addFile($fPath, basename((string) $f['file']));
            }
        }
        $zip->close();

        foreach ($processData['results'] as $f) {
            @unlink($this->tempPath . DIRECTORY_SEPARATOR . $f['file']);
        }
        if (! empty($processData['batchPath'])) {
            @rmdir($processData['batchPath']);
        }

        return ['success' => true, 'file' => $zipName . '.zip'];
    }

    public function limpiarTemporales(int $horas = 24): void
    {
        if (! is_dir($this->tempPath)) {
            return;
        }

        $ahora = time();
        $segundos = $horas * 3600;
        $files = scandir($this->tempPath) ?: [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === '.gitignore') {
                continue;
            }

            $path = $this->tempPath . DIRECTORY_SEPARATOR . $file;
            if (! file_exists($path) || ($ahora - (int) filemtime($path)) <= $segundos) {
                continue;
            }

            if (is_dir($path)) {
                $this->borrarCarpetaRecursiva($path);
            } else {
                @unlink($path);
            }
        }
    }

    /** @param  \GdImage|resource  $im */
    private function allocateColors($im): array
    {
        return [
            'white' => imagecolorallocate($im, ...self::COLOR_WHITE),
            'black' => imagecolorallocate($im, ...self::COLOR_BLACK),
            'red' => imagecolorallocate($im, ...self::COLOR_RED),
        ];
    }

    /** @param  \GdImage|resource  $im */
    private function drawBranding($im, int $red): void
    {
        $logoPath = $this->assetsPath . '/images/logo.png';
        if (is_file($logoPath)) {
            $logo = $this->getCachedResource($logoPath);
            if ($logo) {
                imagecopyresampled(
                    $im,
                    $logo,
                    2,
                    14,
                    0,
                    0,
                    self::LOGO_TARGET_SIZE,
                    self::LOGO_TARGET_SIZE,
                    imagesx($logo),
                    imagesy($logo)
                );
            }
        }

        imageline($im, 122, 17, 122, 136, $red);
        imageline($im, 123, 17, 123, 136, $red);
    }

    /**
     * @param  \GdImage|resource  $im
     * @param  array{titulo?: string, nombre: string, cargo?: string}  $data
     * @param  array{black: int, red: int}  $clr
     */
    private function drawTextInfo($im, array $data, array $clr): int
    {
        $fBlack = $this->assetsPath . '/fonts/Roboto-Black.ttf';
        $fReg = $this->assetsPath . '/fonts/Arial.ttf';

        $nombre = (! empty($data['titulo']) ? trim((string) $data['titulo']) . ' ' : '') . ($data['nombre']);
        $nombre = mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
        $cargo = mb_convert_case((string) ($data['cargo'] ?? ''), MB_CASE_TITLE, 'UTF-8');

        imagettftext($im, 11, 0, self::POS_X_INFO, 35, $clr['black'], $fBlack, $nombre);

        $maxWidth = self::CANVAS_W - self::POS_X_INFO - 15;
        $totalLines = $this->drawWrappedText($im, 10, 0, self::POS_X_INFO, 51, $clr['black'], $fReg, $cargo, $maxWidth, 14);

        $cargoExtraHeight = ($totalLines - 1) * 14;
        $yMunicipio = 69 + $cargoExtraHeight;
        imagettftext($im, 10, 0, self::POS_X_INFO, $yMunicipio, $clr['red'], $fBlack, 'Municipalidad de Puente Piedra');

        return $cargoExtraHeight;
    }

    /**
     * @param  \GdImage|resource  $im
     * @param  array{celular?: string, anexo?: string, correo?: string}  $data
     * @param  array{black: int}  $clr
     */
    private function drawContactSection($im, array $data, array $clr, int $yOffset = 0): void
    {
        $iconDir = $this->assetsPath . '/images/icono/';
        $fReg = $this->assetsPath . '/fonts/Arial.ttf';

        $configs = [
            [
                'icon' => 'telefono.png',
                'y' => 83 + $yOffset,
                'text' => '(01) 219-6200'
                    . (! empty($data['anexo']) ? ' Anexo: ' . $data['anexo'] : '')
                    . (! empty($data['celular']) ? ' | ' . $data['celular'] : ''),
            ],
            [
                'icon' => 'correo-electronico.png',
                'y' => 101 + $yOffset,
                'text' => (string) ($data['correo'] ?? ''),
            ],
            [
                'icon' => 'web.png',
                'y' => 119 + $yOffset,
                'text' => 'web.munipuentepiedra.gob.pe',
            ],
        ];

        foreach ($configs as $cfg) {
            $path = $iconDir . $cfg['icon'];
            if (is_file($path)) {
                $icon = $this->getCachedResource($path);
                if ($icon) {
                    imagecopyresampled($im, $icon, self::POS_X_INFO, $cfg['y'], 0, 0, 14, 14, imagesx($icon), imagesy($icon));
                }
            }
            imagettftext($im, 9, 0, 155, $cfg['y'] + 12, $clr['black'], $fReg, $cfg['text']);
        }
    }

    /**
     * @param  \GdImage|resource  $im
     */
    private function drawWrappedText($im, float $size, float $angle, int $x, int $y, int $color, string $font, string $text, int $maxWidth, int $lineHeight): int
    {
        $words = explode(' ', $text);
        $line = '';
        $linesRendered = 0;

        foreach ($words as $word) {
            $testLine = $line . ($line === '' ? '' : ' ') . $word;
            $bbox = imagettfbbox($size, $angle, $font, $testLine);
            $testWidth = abs(($bbox[4] ?? 0) - ($bbox[0] ?? 0));

            if ($testWidth > $maxWidth && $line !== '') {
                imagettftext($im, $size, $angle, $x, $y + ($linesRendered * $lineHeight), $color, $font, trim($line));
                $line = $word;
                $linesRendered++;
            } else {
                $line = $testLine;
            }
        }

        imagettftext($im, $size, $angle, $x, $y + ($linesRendered * $lineHeight), $color, $font, trim($line));

        return $linesRendered + 1;
    }

    /** @return \GdImage|resource|null */
    private function getCachedResource(string $path)
    {
        $key = md5($path);
        if (! array_key_exists($key, $this->resourceCache)) {
            $this->resourceCache[$key] = @imagecreatefrompng($path) ?: null;
        }

        return $this->resourceCache[$key];
    }

    private function borrarCarpetaRecursiva(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->borrarCarpetaRecursiva($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function __destruct()
    {
        foreach ($this->resourceCache as $res) {
            if (is_object($res) || (is_resource($res))) {
                @imagedestroy($res);
            }
        }
    }

    private function ok(array $data): array
    {
        return ['success' => true, 'data' => $data];
    }

    private function fail(string $error): array
    {
        return ['success' => false, 'error' => $error];
    }
}
