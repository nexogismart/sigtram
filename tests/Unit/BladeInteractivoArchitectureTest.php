<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class BladeInteractivoArchitectureTest extends TestCase
{
    /**
     * Allowlist estricta y justificada únicamente para vistas de renderizado no interactivo
     * (documentos PDF generados por DomPDF o comprobantes físicos de impresora).
     *
     * Reglas para la allowlist:
     * - Puede contener <style> exclusivamente para diseño físico de impresión o DomPDF.
     * - NO puede contener scripts ejecutables.
     * - NO puede contener dependencias externas vía CDN.
     * - NO puede contener atributos HTML de eventos inline (on*).
     */
    protected array $allowlistNoInteractivas = [
        'resources/views/mod_gene/mod_tram_inter/hoja-ruta.blade.php',
        'resources/views/mod_gm/mod_tram_exte/hoja_ruta.blade.php',
        'resources/views/mod_gm/mod_tram_exte/impr_expedi.blade.php',
        'resources/views/mod_gm/mod_tram_exte/pdf/reporte-expediente-pdf.blade.php',
        'resources/views/mod_gm/mod_tram_exte/pdf/reporte-general.blade.php',
        'resources/views/mod_gm/mod_tram_exte/pdf/segi-archivo-print.blade.php',
        'resources/views/mod_gm/mod_tram_exte/reporte_bandeja.blade.php',
        'resources/views/mod_gm/mod_tram_exte/voucher.blade.php',
        'resources/views/mod_gm/mod_tram_exte/voucher_anexo.blade.php',
        'resources/views/mod_gm/mod_tram_exte/prescripcion.blade.php',
        'resources/views/mod_gm/mod_tram_exte/stub_pendiente.blade.php',
    ];

    /**
     * Obtiene todos los archivos blade recursivamente en los módulos evaluados.
     */
    protected function obtenerArchivosBlade(): array
    {
        $basePath = realpath(__DIR__ . '/../../');
        $dirs = [
            $basePath . '/resources/views/mod_gene/mod_tram_inter',
            $basePath . '/resources/views/mod_gm/mod_tram_exte',
        ];

        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($dir)->name('*.blade.php');

            foreach ($finder as $file) {
                $relPath = str_replace('\\', '/', substr($file->getRealPath(), strlen($basePath) + 1));
                $files[$relPath] = $file->getContents();
            }
        }

        ksort($files);
        return $files;
    }

    public function test_vistas_allowlist_cumplen_reglas_estrictas_sin_js_ni_cdn_ni_handlers(): void
    {
        $basePath = realpath(__DIR__ . '/../../');
        $violaciones = [];

        foreach ($this->allowlistNoInteractivas as $relPath) {
            $fullPath = $basePath . '/' . $relPath;
            if (!file_exists($fullPath)) {
                continue;
            }

            $content = file_get_contents($fullPath);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                // 1. Prohibido JavaScript ejecutable
                if (preg_match('/<script\b[^>]*>/i', $line, $matches)) {
                    if (!str_contains($line, 'type="application/json"') && !str_contains($line, "type='application/json'")) {
                        $violaciones[] = "[{$relPath}:L" . ($lineNum + 1) . "] Script ejecutable en vista allowlist: " . trim($matches[0]);
                    }
                }

                // 2. Prohibido CDNs
                if (preg_match('/(https?:)?\/\/(cdn\.tailwindcss\.com|cdn\.jsdelivr\.net|code\.jquery\.com|cdnjs\.cloudflare\.com|unpkg\.com)/i', $line, $matches)) {
                    $violaciones[] = "[{$relPath}:L" . ($lineNum + 1) . "] CDN remoto en vista allowlist: " . trim($matches[0]);
                }

                // 3. Prohibido handlers de eventos HTML inline (on*)
                if (preg_match('/<[^>]*\b(on[a-z]{2,20})\s*=\s*["\']/i', $line, $matches) || preg_match('/^\s*\b(on[a-z]{2,20})\s*=\s*["\']/i', $line, $matches)) {
                    $violaciones[] = "[{$relPath}:L" . ($lineNum + 1) . "] Handler inline en vista allowlist: " . trim($matches[1]);
                }
            }
        }

        $this->assertEmpty(
            $violaciones,
            "Se detectaron violaciones en vistas de la allowlist (deben carecer de JS ejecutable, CDN y eventos inline):\n" . implode("\n", $violaciones)
        );
    }

    public function test_vistas_interactivas_recursivamente_sin_script_style_cdn_ni_handlers_inline(): void
    {
        $archivos = $this->obtenerArchivosBlade();
        $this->assertGreaterThan(30, count($archivos), "Debe existir una cantidad sustancial de vistas evaluadas recursivamente.");

        $violaciones = [];

        foreach ($archivos as $relPath => $content) {
            // Si está en la allowlist, se omite de la prueba interactiva (evaluado por la prueba específica)
            if (in_array($relPath, $this->allowlistNoInteractivas, true)) {
                continue;
            }

            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                $num = $lineNum + 1;

                // 1. Prohibido <style
                if (preg_match('/<style\b[^>]*>/i', $line, $m)) {
                    $violaciones[] = "[{$relPath}:L{$num}] Bloque <style> detectado en vista interactiva: " . trim($m[0]);
                }

                // 2. Prohibido <script ejecutable (solo se permite type="application/json")
                if (preg_match('/<script\b[^>]*>/i', $line, $m)) {
                    if (!str_contains($line, 'type="application/json"') && !str_contains($line, "type='application/json'")) {
                        $violaciones[] = "[{$relPath}:L{$num}] Bloque <script> ejecutable en vista interactiva: " . trim($m[0]);
                    }
                }

                // 3. Prohibido handlers HTML inline (onclick, onchange, onsubmit, oninput, onload, etc.)
                if (preg_match('/<[^>]*\b(on[a-z]{2,20})\s*=\s*["\']/i', $line, $m) || preg_match('/^\s*\b(on[a-z]{2,20})\s*=\s*["\']/i', $line, $m)) {
                    $violaciones[] = "[{$relPath}:L{$num}] Evento HTML inline detectado: " . trim($m[1]);
                }

                // 4. Prohibido CDNs remotos
                if (preg_match('/(https?:)?\/\/(cdn\.tailwindcss\.com|cdn\.jsdelivr\.net|code\.jquery\.com|cdnjs\.cloudflare\.com|unpkg\.com)/i', $line, $m)) {
                    $violaciones[] = "[{$relPath}:L{$num}] Enlace a CDN remoto detectado: " . trim($m[0]);
                }
            }
        }

        $this->assertEmpty(
            $violaciones,
            "Se detectaron violaciones en vistas Blade interactivas (deben estar migradas a módulos Vite/Alpine):\n" . implode("\n", $violaciones)
        );
    }
}
