<?php

namespace Tests\Feature;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class HelpdeskPresentationTest extends TestCase
{
    private function renderDesk(bool $restricted = false, array $tickets = []): string
    {
        return view('mod_ti.helpdesk.index', [
            'tickets'=>collect($tickets), 'incidencias'=>collect(),
            'phone_val'=>'', 'anydesk_val'=>'', 'phone_ok'=>true,
            'showPhoneAlert'=>false, 'isRestricted'=>$restricted,
            'horarioInicioFmt'=>'08:00', 'horarioFinFmt'=>'17:00',
            'errors'=>new ViewErrorBag(),
        ])->render();
    }

    public function test_request_and_history_keep_form_contracts_and_empty_state(): void
    {
        $html = $this->renderDesk();
        $this->assertStringContainsString('action="'.route('helpdesk.index.store').'"', $html);
        foreach (['asunto','incidencia','descripcion','archivos[]','urgencia','urgencia_descripcion','telefono_contacto','anydesk_contacto','_token'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('aria-controls="hd-history-panel"', $html);
        $this->assertStringContainsString('Aún no tienes solicitudes de soporte.', $html);
    }

    public function test_restricted_hours_explain_the_limit_without_exposing_the_request_form(): void
    {
        $html = $this->renderDesk(true);
        $this->assertStringNotContainsString('<form id="ticketForm"', $html);
        $this->assertStringContainsString('Puedes registrar solicitudes de 08:00 a 17:00.', $html);
        $this->assertStringContainsString('Ver mis tickets', $html);
    }

    public function test_history_escapes_actual_ticket_content_and_preserves_actions(): void
    {
        $html = $this->renderDesk(false, [(object)[
            'id'=>999001, 'asunto'=>'<script>unsafe</script>', 'detalle'=>'Servicio',
            'fecha_fmt'=>'13/10/2026', 'situacion'=>1,
        ]]);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertStringContainsString("openAjaxModal(999001, 'detalle')", $html);
        $this->assertStringContainsString("openAjaxModal(999001, 'anular')", $html);
        $this->assertStringNotContainsString('class="hd-empty-cell', $html);
    }

    public function test_full_width_editor_keeps_one_description_without_the_removed_guide(): void
    {
        $html = $this->renderDesk();
        $this->assertSame(1, substr_count($html, 'name="descripcion"'));
        $this->assertStringNotContainsString('data-writing-guide="request"', $html);
        $this->assertStringNotContainsString('data-writing-guide="incident"', $html);
        $this->assertStringNotContainsString('data-writing-guide="question"', $html);
        $this->assertStringContainsString('id="descripcionError"', $html);
        $this->assertStringContainsString('classic/translations/es.js', $html);
        $this->assertStringNotContainsString('name="writing-guide"', $html);
    }
}
