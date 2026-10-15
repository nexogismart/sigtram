<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class NexoWorkspaceTest extends TestCase
{
    private function renderWorkspace(array $menu = [], string $usuario = 'Usuario de prueba', array $context = []): string
    {
        return view('inicio', array_merge([
            'menuItems' => $menu,
            'usuario' => $usuario,
            'area' => 'Tecnología',
            'cargo' => 'Soporte',
        ], $context))->render();
    }

    public function test_workspace_preserves_assigned_module_urls_and_popup_behavior(): void
    {
        $html = $this->renderWorkspace([
            ['label' => 'Documentos', 'url' => '#', 'popup' => false, 'children' => [
                ['label' => 'Registrar documento', 'url' => '/documentos/registro', 'popup' => false],
                ['label' => 'Consulta externa', 'url' => 'https://example.com/consulta', 'popup' => true],
            ]],
        ]);

        $this->assertStringContainsString('href="/documentos/registro" data-module-link', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('Registrar documento', $html);
        $this->assertStringContainsString('name="contentFrame"', $html);
        $this->assertStringContainsString('action="' . route('logout') . '"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_workspace_has_honest_empty_states_without_example_statistics(): void
    {
        $html = $this->renderWorkspace();

        $this->assertStringContainsString('No hay módulos asignados a este cargo.', $html);
        $this->assertStringContainsString('Nexosigtram', $html);
        $this->assertStringNotContainsString('1,234', $html);
        $this->assertStringNotContainsString('Juan Pérez', $html);
        $this->assertStringNotContainsString('Puente Piedra', $html);
    }

    public function test_workspace_escapes_user_and_menu_content_in_html_and_embedded_json(): void
    {
        $payload = '</script><script>alert(1)</script>';
        $html = $this->renderWorkspace([
            ['label' => $payload, 'url' => '/consulta', 'popup' => false, 'children' => []],
        ], $payload);

        $this->assertStringNotContainsString($payload, $html);
        $this->assertStringContainsString('&lt;/script&gt;', $html);
        preg_match('/<script id="workspaceData" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        $data = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($payload, $data['menu'][0]['label']);
    }

    public function test_progressive_explorer_keeps_all_permissions_with_large_menus(): void
    {
        $menu = [];
        for ($group = 0; $group < 41; $group++) {
            $children = [];
            for ($item = 0; $item < ($group === 0 ? 5 : 3); $item++) {
                $children[] = ['label' => 'Bandeja', 'url' => "/grupo/{$group}/modulo/{$item}", 'popup' => false];
            }
            $menu[] = ['label' => "Espacio {$group}", 'url' => '#', 'popup' => false, 'children' => $children];
        }
        $html = $this->renderWorkspace($menu);
        preg_match('/<script id="workspaceData" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        $data = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(41, $data['menu']);
        $this->assertSame(125, array_sum(array_map(fn (array $group): int => count($group['children']), $data['menu'])));
        $this->assertSame('/grupo/40/modulo/2', $data['menu'][40]['children'][2]['url']);
        $this->assertStringContainsString('id="explorerDialog"', $html);
        $this->assertStringNotContainsString('id="sidebarSearch"', $html);
        $this->assertStringContainsString('<noscript>', $html);
    }

    public function test_workspace_uses_active_area_and_cargo_without_inventing_module_ownership(): void
    {
        $html = $this->renderWorkspace([
            ['label' => 'Módulo compartido', 'url' => '/compartido', 'popup' => false, 'children' => []],
        ], 'Usuario de prueba', ['area' => 'Área A', 'cargo' => 'Cargo A']);
        preg_match('/<script id="workspaceData" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        $data = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['area' => 'Área A', 'cargo' => 'Cargo A'], $data['context']);
        $this->assertCount(1, $data['menu']);
        $this->assertSame('Módulo compartido', $data['menu'][0]['label']);
        $this->assertStringContainsString('Área activa', $html);
        $this->assertStringContainsString('id="moduleBoard"', $html);
        $this->assertStringNotContainsString('orbit-scene', $html);
    }

    public function test_personal_access_storage_is_separate_for_each_active_context(): void
    {
        $keys = [];
        foreach (['A', 'B'] as $area) {
            $html = $this->renderWorkspace([], 'Mismo usuario', ['area' => "Área {$area}", 'cargo' => "Cargo {$area}"]);
            preg_match('/<script id="workspaceData" type="application\/json">(.*?)<\/script>/s', $html, $matches);
            $keys[] = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['storageKey'];
        }
        $this->assertNotSame($keys[0], $keys[1]);
    }

    public function test_no_javascript_navigation_excludes_inactive_sources_but_keeps_authorized_active_actions(): void
    {
        $html = $this->renderWorkspace([
            ['id' => 138, 'label' => 'PIDE', 'url' => '/pide-antiguo', 'popup' => true, 'children' => []],
            ['id' => 194, 'label' => 'OTI', 'url' => '#', 'popup' => false, 'children' => [
                ['id' => 195, 'label' => 'Firma Email', 'url' => '/firma-asignada', 'popup' => false],
            ]],
        ]);

        $this->assertStringNotContainsString('href="/pide-antiguo"', $html);
        $this->assertStringContainsString('href="/firma-asignada" data-module-link', $html);
    }

    public function test_same_area_and_cargo_labels_do_not_share_preferences_between_companies_or_cargo_ids(): void
    {
        $keys = [];
        foreach ([[2,101], [3,101], [2,102], [2,101]] as [$company, $cargo]) {
            session([\App\Support\SessionKeys::COMPANIA_ID=>$company, \App\Support\SessionKeys::CARGO_ID=>$cargo]);
            $html = $this->renderWorkspace([], 'Mismo usuario', ['area'=>'Misma área', 'cargo'=>'Mismo cargo']);
            preg_match('/<script id="workspaceData" type="application\/json">(.*?)<\/script>/s', $html, $matches);
            $keys[] = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['storageKey'];
        }
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertNotSame($keys[0], $keys[2]);
        $this->assertSame($keys[0], $keys[3]);
        $renamed = $this->renderWorkspace([], 'Mismo usuario', ['area'=>'Área renombrada', 'cargo'=>'Cargo renombrado']);
        preg_match('/<script id="workspaceData" type="application\/json">(.*?)<\/script>/s', $renamed, $matches);
        $this->assertSame($keys[0], json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['storageKey']);
    }
}
