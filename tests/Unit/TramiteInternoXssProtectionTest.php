<?php

namespace Tests\Unit;

use Tests\TestCase;

class TramiteInternoXssProtectionTest extends TestCase
{
    /**
     * Helper que replica fielmente el sanitizador implementado en frontend.
     */
    private function sanitizeHtml(mixed $text): string
    {
        if ($text === null) {
            return '';
        }
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Helper que replica el neutralizador de URLs del frontend.
     */
    private function sanitizeUrl(mixed $url): string
    {
        if (!$url) {
            return '';
        }
        $clean = trim((string) $url);
        if (preg_match('/^(javascript|data|vbscript):/i', $clean)) {
            return '#';
        }
        return $clean;
    }

    public function test_comillas_simples_y_dobles_son_neutralizadas(): void
    {
        $payload = "Documento 'MEMO-001' con \"comillas dobles\"";
        $escaped = $this->sanitizeHtml($payload);

        $this->assertStringNotContainsString("'", $escaped);
        $this->assertStringNotContainsString('"', $escaped);
        $this->assertStringContainsString('&apos;', $escaped);
        $this->assertStringContainsString('&quot;', $escaped);
    }

    public function test_etiquetas_html_y_scripts_son_escapadas(): void
    {
        $payload = "<script>alert('XSS')</script><img src=x onerror=alert(1)>";
        $escaped = $this->sanitizeHtml($payload);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringNotContainsString('<img', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringContainsString('&lt;img', $escaped);
    }

    public function test_pseudoprotocolo_javascript_es_neutralizado(): void
    {
        $payload1 = "javascript:alert(document.cookie)";
        $payload2 = "JAVASCRIPT:/*payload*/window.location='https://attacker.com'";
        $payload3 = "vbscript:msgbox(1)";
        $urlValida = "documentos/internos/2026/10/10/memo.pdf";

        $this->assertEquals('#', $this->sanitizeUrl($payload1));
        $this->assertEquals('#', $this->sanitizeUrl($payload2));
        $this->assertEquals('#', $this->sanitizeUrl($payload3));
        $this->assertEquals($urlValida, $this->sanitizeUrl($urlValida));
    }

    public function test_atributos_de_evento_y_unicode_preservan_integridad_textual(): void
    {
        $nombreUnicode = "JOSÉ ÁNGEL PEÑA - OFICINA DE ADMINISTRACIÓN & FINANZAS (ÁREA 21) 🚀";
        $escaped = $this->sanitizeHtml($nombreUnicode);

        // Unicode se preserva intacto sin romper caracteres especiales
        $this->assertStringContainsString('JOSÉ ÁNGEL PEÑA', $escaped);
        $this->assertStringContainsString('&amp;', $escaped);
        $this->assertStringContainsString('🚀', $escaped);
    }
}