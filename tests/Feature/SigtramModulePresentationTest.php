<?php

declare(strict_types=1);
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\ViewErrorBag;

class SigtramModulePresentationTest extends TestCase
{
    public function test_operational_views_share_the_design_system_without_touching_pdf_templates(): void
    {
        $inventory=json_decode(file_get_contents(base_path('docs/frontend/inventario-vistas.json')),true,512,JSON_THROW_ON_ERROR);
        foreach($inventory as $row){
            $source=file_get_contents(base_path($row['file']));
            if($row['treatment']==='pantalla'){
                $this->assertStringContainsString("components.sigtram.module-head",$source,$row['file']);
                $this->assertStringContainsString("components.sigtram.module-bar",$source,$row['file']);
                $this->assertStringContainsString('sigtram-module',$source,$row['file']);
            }
            if($row['treatment']==='impresion-pdf'){
                $this->assertStringContainsString("components.sigtram.print-brand",$source,$row['file']);
                $this->assertStringNotContainsString('sigtram-modules.js',$source,$row['file']);
            }
        }
    }
    public function test_module_header_uses_family_metadata_and_keeps_navigation_in_the_top_window(): void
    {
        $html=view('components.sigtram.module-bar',['screenKey'=>'mod_gene.mod_tram_inter.buscar-tramite'])->render();
        $this->assertStringContainsString('Trámites internos',$html);
        $this->assertStringContainsString('target="_top"',$html);
        $this->assertStringContainsString('nexosigtram-documental-logo.png',$html);
        $this->assertStringContainsString('aria-pressed="false"',$html);
    }
    public function test_public_emergency_views_do_not_offer_private_work_actions(): void
    {
        $html=view('components.sigtram.module-bar',['screenKey'=>'mod_gp.omaped.ver_datos'])->render();
        $this->assertStringNotContainsString('data-sigtram-density',$html);
        $this->assertStringNotContainsString('class="sigtram-return"',$html);
    }
    public function test_role_selection_preserves_exactly_the_assigned_cargos_and_submission_contract(): void
    {
        $cargos=collect([(object)['cdgo_crgo'=>462,'de_crgo'=>'Asistente administrativo','de_area'=>'Oficina de Logística'],(object)['cdgo_crgo'=>596,'de_crgo'=>'Operador logístico','de_area'=>'Oficina de Logística']]);
        $html=view('seleccionar-cargo',['usuario'=>'Usuario de auditoría','cargos'=>$cargos,'errors'=>new ViewErrorBag()])->render();
        $this->assertSame(2,substr_count($html,'name="cargo_id"'));
        $this->assertStringContainsString('value="462"',$html);
        $this->assertStringContainsString('value="596"',$html);
        $this->assertStringContainsString('action="'.route('seleccionar.cargo.guardar').'"',$html);
        $this->assertStringContainsString('action="'.route('logout').'"',$html);
        $this->assertStringContainsString('name="_token"',$html);
        $this->assertStringContainsString('id="lista-cargos"',$html);
        $this->assertStringContainsString('id="empty-state"',$html);
        $this->assertStringNotContainsString('puentepiedra.jpg',$html);
    }
    public function test_print_brand_uses_local_file_for_pdf_and_url_for_browser(): void
    {
        $pdf=view('components.sigtram.print-brand',['sigtramPdf'=>true])->render();
        $browser=view('components.sigtram.print-brand')->render();
        $this->assertStringContainsString(public_path('images/nexosigtram-documental-logo.png'),$pdf);
        $this->assertStringContainsString(asset('images/nexosigtram-documental-logo.png'),$browser);
    }
    public function test_unknown_family_keeps_a_neutral_label_without_inventing_responsibility(): void
    {
        $html=view('components.sigtram.module-bar',['screenKey'=>'nuevo.index'])->render();
        $this->assertStringContainsString('Gestión de trámites',$html);
        $this->assertStringNotContainsString('Logística',$html);
    }
}
