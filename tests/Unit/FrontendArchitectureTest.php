<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FrontendArchitectureTest extends TestCase
{
    private string $internoBlade;
    private string $externoBlade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->internoBlade = resource_path('views/mod_gene/mod_tram_inter/correo-interno.blade.php');
        $this->externoBlade = resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
    }

    public function test_correo_interno_blade_and_partials_have_zero_inline_event_handlers(): void
    {
        $this->assertFileExists($this->internoBlade);

        $filesToCheck = [
            $this->internoBlade,
            resource_path('views/mod_gene/mod_tram_inter/partials/sidebar.blade.php'),
            resource_path('views/mod_gene/mod_tram_inter/partials/filtros-tabla.blade.php'),
            resource_path('views/mod_gene/mod_tram_inter/partials/historico.blade.php'),
            resource_path('views/mod_gene/mod_tram_inter/partials/modales.blade.php'),
        ];

        $pattern = '/\s(on(?:click|change|input|submit|mouseover|mouseout|keydown|keyup|keypress|load|error|focus|blur))\s*=/i';

        foreach ($filesToCheck as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) File::get($filePath);

            preg_match_all($pattern, $content, $matches);
            $occurrences = count($matches[0]);

            $this->assertSame(
                0,
                $occurrences,
                "Se encontraron {$occurrences} handlers inline on* prohibidos en: {$filePath}"
            );
        }
    }

    public function test_views_have_zero_prohibited_dom_sinks_for_dynamic_data(): void
    {
        $filesToCheck = [
            $this->internoBlade,
            $this->externoBlade,
            resource_path('views/mod_gene/mod_tram_inter/partials/sidebar.blade.php'),
            resource_path('views/mod_gene/mod_tram_inter/partials/filtros-tabla.blade.php'),
            resource_path('views/mod_gene/mod_tram_inter/partials/historico.blade.php'),
            resource_path('views/mod_gene/mod_tram_inter/partials/modales.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/sidebar.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/filtros-tabla.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/historico.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/modales.blade.php'),
        ];

        $prohibitedSinks = ['innerHTML', 'outerHTML', 'insertAdjacentHTML'];

        foreach ($filesToCheck as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) File::get($filePath);

            foreach ($prohibitedSinks as $sink) {
                $this->assertStringNotContainsString(
                    $sink,
                    $content,
                    "Se encontró el sink DOM prohibido '{$sink}' en el archivo: {$filePath}"
                );
            }
        }
    }

    public function test_segi_expedi_blade_has_zero_cdn_alpine_scripts(): void
    {
        $this->assertFileExists($this->externoBlade);
        $content = (string) File::get($this->externoBlade);

        $this->assertStringNotContainsString(
            'cdn.jsdelivr.net/npm/alpinejs',
            $content,
            'Se detectó inclusión de Alpine.js vía CDN en segi_expedi.blade.php. Debe importarse localmente con Vite.'
        );
        $this->assertStringNotContainsString(
            'unpkg.com/alpinejs',
            $content,
            'Se detectó inclusión de Alpine.js vía CDN unpkg en segi_expedi.blade.php.'
        );
    }

    public function test_segi_expedi_blade_has_zero_inline_god_component(): void
    {
        $this->assertFileExists($this->externoBlade);
        $content = (string) File::get($this->externoBlade);

        $this->assertStringNotContainsString(
            'function correoExternoApp()',
            $content,
            'Se detectó el componente monolítico correoExternoApp() dentro del Blade. Debe estar modularizado en resources/js/tramite-externo/.'
        );
    }

    public function test_views_have_zero_show_silencio_dead_code(): void
    {
        $filesToCheck = [
            $this->externoBlade,
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/sidebar.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/filtros-tabla.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/historico.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/modales.blade.php'),
        ];

        foreach ($filesToCheck as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) File::get($filePath);

            $this->assertStringNotContainsString(
                'showSilencio',
                $content,
                "Se encontró referencia a la función muerta showSilencio en: {$filePath}"
            );
        }
    }

    public function test_segi_bandeja_orphan_clone_does_not_exist(): void
    {
        $orphanPath = resource_path('views/mod_gm/mod_tram_exte/segi_bandeja.blade.php');

        $this->assertFalse(
            File::exists($orphanPath),
            "El archivo huérfano segi_bandeja.blade.php aún existe en el disco. Debe estar retirado."
        );
    }

    public function test_modales_have_only_one_modal_u_definition(): void
    {
        $modalesExterno = resource_path('views/mod_gm/mod_tram_exte/partials_segi/modales.blade.php');
        $this->assertFileExists($modalesExterno);

        $content = (string) File::get($modalesExterno);
        preg_match_all('/id=["\']modal-usuarios["\']/', $content, $matches);

        $this->assertCount(
            1,
            $matches[0],
            'Debe existir exactamente una sola definición de modal-usuarios (Modal U) en modales_segi.'
        );
    }

    public function test_canonical_views_have_secure_data_config_and_roots(): void
    {
        // 1. Trámite Interno
        $internoContent = (string) File::get($this->internoBlade);
        $this->assertStringContainsString('id="tramite-interno-app"', $internoContent);
        $this->assertStringContainsString('data-config=\'@json($frontendConfig)\'', $internoContent);
        $this->assertStringContainsString('mod_tram_inter.correo_interno.tabla', $internoContent);
        $this->assertStringContainsString('mod_tram_inter.correo_interno.historial', $internoContent);
        $this->assertStringContainsString('mod_tram_inter.correo_interno.recepcionar', $internoContent);

        // 2. Trámite Externo
        $externoContent = (string) File::get($this->externoBlade);
        $this->assertStringContainsString('id="tramite-externo-app"', $externoContent);
        $this->assertStringContainsString('data-config=\'@json($frontendConfig)\'', $externoContent);
        $this->assertStringContainsString('x-data="correoExternoApp()"', $externoContent);
        $this->assertStringContainsString('mod_gm.segi_expedi.tabla', $externoContent);
        $this->assertStringContainsString('mod_gm.segi_expedi.historial', $externoContent);
        $this->assertStringContainsString('mod_gm.segi_expedi.conteos', $externoContent);
    }

    public function test_page_specific_styles_are_not_imported_globally(): void
    {
        $globalCss = (string) File::get(resource_path('css/app.css'));
        $pageSpecificSheets = [
            'shared/v2-reporte-area-cargo.css',
            'tramite-interno/buscar-tramite.css',
            'tramite-interno/reporte-bandeja.css',
            'tramite-interno/reporte-cargo-interno.css',
            'tramite-interno/reporte-tramite.css',
            'tramite-interno/secuencial-area.css',
            'tramite-externo/modales-tramite.css',
            'tramite-externo/reportes-expedientes.css',
            'tramite-externo/segi-adjuntar.css',
        ];

        foreach ($pageSpecificSheets as $sheet) {
            $this->assertStringNotContainsString(
                $sheet,
                $globalCss,
                "La hoja específica {$sheet} no debe contaminar globalmente todas las vistas."
            );
        }

        $viewStyles = [
            'views/mod_gene/mod_tram_inter/buscar-tramite.blade.php' => 'tramite-interno/buscar-tramite.css',
            'views/mod_gene/mod_tram_inter/reporte-bandeja.blade.php' => 'tramite-interno/reporte-bandeja.css',
            'views/mod_gene/mod_tram_inter/reporte-cargo-interno.blade.php' => 'tramite-interno/reporte-cargo-interno.css',
            'views/mod_gene/mod_tram_inter/reporte-tramite.blade.php' => 'tramite-interno/reporte-tramite.css',
            'views/mod_gene/mod_tram_inter/ver_secuencial_area.blade.php' => 'tramite-interno/secuencial-area.css',
            'views/mod_gm/mod_tram_exte/segi_adjuntar.blade.php' => 'tramite-externo/segi-adjuntar.css',
            'views/mod_gm/mod_tram_exte/agre_anexo.blade.php' => 'tramite-externo/modales-tramite.css',
            'views/mod_gm/mod_tram_exte/lista_expedi_todo.blade.php' => 'tramite-externo/reportes-expedientes.css',
            'views/mod_gm/mod_tram_exte/v2_lista_reporte_area_cargo.blade.php' => 'shared/v2-reporte-area-cargo.css',
        ];

        foreach ($viewStyles as $view => $sheet) {
            $content = (string) File::get(resource_path($view));
            $this->assertStringContainsString(
                $sheet,
                $content,
                "La vista {$view} debe cargar explícitamente su hoja {$sheet}."
            );
        }
    }

    public function test_shared_filters_keep_primary_action_and_icon_only_clear_action(): void
    {
        $filters = [
            resource_path('views/mod_gene/mod_tram_inter/partials/filtros-tabla.blade.php'),
            resource_path('views/mod_gm/mod_tram_exte/partials_segi/filtros-tabla.blade.php'),
        ];

        foreach ($filters as $filter) {
            $content = (string) File::get($filter);

            $this->assertStringContainsString('sigtram-consult-button', $content);
            $this->assertStringContainsString('<span>Consultar</span>', $content);
            $this->assertStringContainsString('sigtram-clear-button', $content);
            $this->assertStringContainsString('aria-label="Limpiar filtros"', $content);
            $this->assertStringContainsString('<span class="sr-only">Limpiar filtros</span>', $content);
            $this->assertStringNotContainsString('<span>Limpiar</span>', $content);
        }
    }
}
