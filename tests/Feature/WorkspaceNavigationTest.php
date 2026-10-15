<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\WorkspaceNavigation;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    public function test_helpdesk_separates_user_requests_from_oti_support_without_adding_accesses(): void
    {
        $menu = [['id' => 134, 'label' => 'Mesa De Ayuda', 'url' => '#', 'popup' => false, 'children' => [
            ['id' => 135, 'label' => 'Ingresar Servicios', 'url' => '/servicios', 'popup' => false],
            ['id' => 143, 'label' => 'Ingresar Bandeja', 'url' => '/bandeja', 'popup' => false],
            ['id' => 144, 'label' => 'Consultas', 'url' => '/consulta', 'popup' => true],
        ]]];
        $result = WorkspaceNavigation::decorate($menu);
        $entries = $result[0]['children'];
        $this->assertCount(3, $entries);
        $this->assertSame('documentos', $entries[0]['workspace']['space']);
        $this->assertSame('Mis solicitudes de soporte', $entries[1]['workspace']['service']);
        $this->assertSame('tecnologia', $entries[2]['workspace']['space']);
        $this->assertSame('Soporte técnico', $entries[2]['workspace']['service']);
        $this->assertSame(['Oficina de Tecnologías de Información'], $entries[2]['workspace']['owners']);
        $this->assertSame('/consulta', $entries[2]['url']);
        $this->assertTrue($entries[2]['popup']);
        $this->assertSame('Ingresar Bandeja', $entries[1]['label']);
    }

    public function test_restricted_and_unmapped_menus_keep_exactly_the_received_accesses(): void
    {
        $menu = [
            ['id' => 194, 'label' => 'OTI', 'url' => '#', 'popup' => false, 'children' => [
                ['id' => 195, 'label' => 'Firma Email', 'url' => '/firma', 'popup' => false],
            ]],
            ['id' => 9999, 'label' => 'Servicio nuevo', 'url' => '/nuevo', 'popup' => true, 'children' => []],
        ];
        $result = WorkspaceNavigation::decorate($menu);

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['children']);
        $this->assertSame('tecnologia', $result[0]['workspace']['space']);
        $this->assertSame('otros', $result[1]['workspace']['space']);
        $this->assertSame('/nuevo', $result[1]['url']);
        foreach ($result as &$group) {
            unset($group['workspace']);
            foreach ($group['children'] as &$entry) unset($entry['workspace']);
        }
        $this->assertSame($menu, $result);
    }

    public function test_stable_source_ids_take_precedence_over_names_and_old_contract_is_supported(): void
    {
        $result = WorkspaceNavigation::decorate([
            ['id' => 30, 'label' => 'Nombre actualizado', 'children' => []],
            ['label' => 'Cer. Parámetros', 'children' => []],
            ['id' => 9999, 'label' => 'Seguridad', 'children' => []],
        ]);

        $this->assertSame('Usuarios y accesos', $result[0]['workspace']['service']);
        $this->assertSame('Parámetros y autorizaciones', $result[1]['workspace']['service']);
        $this->assertSame('otros', $result[2]['workspace']['space']);
    }
    public function test_authorized_individual_entry_does_not_expand_to_the_full_helpdesk(): void
    {
        $result = WorkspaceNavigation::decorate([['id' => 134, 'label' => 'Mesa De Ayuda', 'children' => [
            ['id' => 143, 'label' => 'Ingresar Bandeja', 'url' => '/mi-bandeja', 'popup' => false],
        ]]]);
        $this->assertCount(1, $result[0]['children']);
        $this->assertSame(143, $result[0]['children'][0]['id']);
        $this->assertSame('documentos', $result[0]['children'][0]['workspace']['space']);
    }

    public function test_inactive_sources_are_marked_without_deleting_records_or_reactivating_children(): void
    {
        $result = WorkspaceNavigation::decorate([
            ['id' => '138', 'label' => 'PIDE', 'children' => []],
            ['id' => 175, 'label' => 'Asistente Virtual', 'children' => [
                ['id' => 135, 'label' => 'Prueba', 'url' => '/prueba'],
            ]],
            ['label' => 'Colas', 'children' => []],
            ['id' => 181, 'label' => 'Colas 2', 'children' => []],
        ]);
        $this->assertCount(4, $result);
        $this->assertFalse($result[0]['workspace']['active']);
        $this->assertFalse($result[1]['children'][0]['workspace']['active']);
        $this->assertFalse($result[2]['workspace']['active']);
        $this->assertTrue($result[3]['workspace']['active']);
        $this->assertSame(['Oficina de Atención al Ciudadano'], $result[3]['workspace']['owners']);
    }

    public function test_parameters_and_possession_keep_different_responsible_areas(): void
    {
        $result = WorkspaceNavigation::decorate([
            ['id' => 79, 'label' => 'Cer. Parametros', 'children' => []],
            ['id' => 117, 'label' => 'Const. Posesión', 'children' => []],
        ]);
        $this->assertSame(['Subgerencia de Autorizaciones Municipales'], $result[0]['workspace']['owners']);
        $this->assertSame(['Subgerencia de Saneamiento y Catastro'], $result[1]['workspace']['owners']);
        $this->assertNotSame($result[0]['workspace']['service'], $result[1]['workspace']['service']);
    }

    public function test_shared_provider_service_keeps_its_secretarial_audience_and_managing_areas(): void
    {
        $result = WorkspaceNavigation::decorate([['id' => 149, 'label' => 'Gestión Proveedores', 'children' => []]]);
        $meta = $result[0]['workspace'];
        $this->assertSame('documentos', $meta['space']);
        $this->assertSame(['Todas las áreas orgánicas'], $meta['sharedWith']);
        $this->assertSame(['Oficina de Logística', 'Finanzas', 'OGAF'], $meta['owners']);
        $this->assertStringContainsString('Secretarías autorizadas', $meta['audience']);
        $this->assertCount(1, $result);
    }

    public function test_pide_v2_is_classified_only_when_received_without_inventing_a_route(): void
    {
        $result = WorkspaceNavigation::decorate([['id' => 9001, 'label' => 'PIDE V2', 'url' => '/ruta-asignada', 'children' => []]]);
        $this->assertSame('tecnologia', $result[0]['workspace']['space']);
        $this->assertSame(['Oficina de Tecnologías de Información'], $result[0]['workspace']['owners']);
        $this->assertSame('/ruta-asignada', $result[0]['url']);
        $this->assertTrue($result[0]['workspace']['active']);
        $this->assertSame([], WorkspaceNavigation::decorate([]));
    }

    public function test_whole_catalogue_is_classified_before_any_user_signs_in(): void
    {
        // Structural snapshot only; never depend on a local report or query the real DB in CI.
        $report = json_decode(file_get_contents(base_path('tests/Fixtures/workspace-catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(43, $report['groups']);
        foreach ($report['groups'] as $group) {
            $decorated = WorkspaceNavigation::decorate([$group])[0];
            $this->assertSame($group['id'], $decorated['id']);
            $this->assertCount(count($group['children']), $decorated['children']);
            if (!$decorated['workspace']['active']) {
                foreach ($decorated['children'] as $entry) $this->assertFalse($entry['workspace']['active']);
                continue;
            }
            $this->assertNotEmpty($decorated['workspace']['owners'], $group['label']);
            $this->assertArrayHasKey($decorated['workspace']['space'], config('workspace.spaces'));
            foreach ($decorated['children'] as $entry) $this->assertNotEmpty($entry['workspace']['owners'], $entry['label']);
        }
    }

    public function test_real_area_names_resolve_to_the_same_presentation_owner(): void
    {
        foreach ([
            'OFICINA DE FINANZAS' => 'Finanzas',
            'OFICINA GENERAL DE ADMINISTRACIÓN Y FINANZAS' => 'OGAF',
            'GERENCIA DE GESTION DEL RIESGO DE DESASTRES' => config('workspace.areas.riesgos'),
            'SUBGERENCIA DE LOGÍSTICA' => config('workspace.areas.logistica'),
        ] as $actual => $canonical) {
            $this->assertSame($canonical, WorkspaceNavigation::canonicalArea($actual));
        }
        $this->assertSame('Área no clasificada', WorkspaceNavigation::canonicalArea('Área no clasificada'));
    }

    public function test_ambulatoria_and_applicant_search_are_inactive_and_second_helpdesk_is_owned_by_oti(): void
    {
        $result = WorkspaceNavigation::decorate([
            ['id' => 27, 'label' => 'Aut. Ambulatoria', 'children' => [['id' => 28, 'label' => 'Bandeja']]],
            ['id' => 78, 'label' => 'Buscar Solicitante', 'children' => []],
            ['id' => 161, 'label' => 'Mesa de Ayuda 2', 'children' => [['id' => 162, 'label' => 'Registrar ticket']]],
        ]);
        $this->assertFalse($result[0]['workspace']['active']);
        $this->assertFalse($result[0]['children'][0]['workspace']['active']);
        $this->assertSame([config('workspace.areas.sam')], $result[0]['workspace']['owners']);
        $this->assertFalse($result[1]['workspace']['active']);
        $this->assertSame([config('workspace.areas.oti')], $result[2]['children'][0]['workspace']['owners']);
    }
}
