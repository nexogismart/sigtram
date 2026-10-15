<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\SessionKeys;
use Tests\TestCase;

class FrontendReleaseScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
    }

    public function test_login_conserva_identidad_documental_y_contrato_de_acceso(): void
    {
        $html = view('login')->render();
        $this->assertStringContainsString('images/sigtram-login-office.png', $html);
        $this->assertStringContainsString('images/nexosigtram-documental-logo.png', $html);
        $this->assertStringContainsString('Sistema de Gestión de Trámites', $html);
        $this->assertStringContainsString('name="usuario"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_seleccion_de_compania_conserva_formulario_y_logo_aprobado(): void
    {
        $html = view('seleccionar-compania', ['usuario'=>'Validación', 'companias'=>collect([
            (object)['cdgo_cmpnia'=>2, 'de_cmpnia'=>'Entidad de prueba'],
        ])])->render();
        $this->assertStringContainsString('name="compania_id" value="2"', $html);
        $this->assertStringContainsString(route('seleccionar.compania.guardar'), $html);
        $this->assertStringContainsString('images/nexosigtram-documental-emblema.png', $html);
        $this->assertStringContainsString('images/nexosigtram-documental-logo.png', $html);
        $this->assertStringNotContainsString('images/mpp.png', $html);
        $this->assertStringNotContainsString('Plataforma SGT', $html);
    }

    public function test_seleccion_de_cargo_conserva_los_cargos_asignados(): void
    {
        $html = view('seleccionar-cargo', ['usuario'=>'Validación', 'companiaNombre'=>'Entidad de prueba', 'cargos'=>collect([
            (object)['cdgo_crgo'=>101, 'de_crgo'=>'Asistente', 'de_area'=>'Área de prueba'],
            (object)['cdgo_crgo'=>102, 'de_crgo'=>'Terminalista', 'de_area'=>'Atención al Ciudadano'],
        ])])->render();
        $this->assertStringContainsString('name="cargo_id" value="101"', $html);
        $this->assertStringContainsString('name="cargo_id" value="102"', $html);
        $this->assertStringContainsString(route('seleccionar.cargo.guardar'), $html);
    }

    public function test_bandeja_interna_puede_resolver_contexto_canonico_de_sesion(): void
    {
        $this->withoutVite();
        session([SessionKeys::AREA_ID=>20, SessionKeys::CARGO_ID=>101]);
        $html = view('mod_gene.mod_tram_inter.correo-interno', ['videos'=>[]])->render();
        preg_match('/data-config=\x27(.*?)\x27/s', $html, $matches);
        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(20, $config['context']['areaId']);
        $this->assertSame(101, $config['context']['cargoId']);
        $this->assertStringContainsString('sigtram-native-brand.css', $html);
        $this->assertStringNotContainsString('sigtram-modules.js', $html);
        $this->assertSame(route('mod_tram_inter.correo_interno.tabla'), $config['routes']['tabla']);
    }
}
