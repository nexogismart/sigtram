<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gm;

use Tests\TestCase;

class IngresoExpedienteFrontendContractTest extends TestCase
{
    private string $viewContent;
    private string $jsContent;
    private string $combinedContent;

    protected function setUp(): void
    {
        parent::setUp();
        $path = resource_path('views/mod_gm/mod_tram_exte/index.blade.php');
        $this->assertFileExists($path, 'La vista index.blade.php de ingreso de expedientes debe existir.');
        $this->viewContent = file_get_contents($path);

        $jsPath = resource_path('js/tramite-externo/ingresar-expediente.js');
        $this->assertFileExists($jsPath, 'El componente JS ingresar-expediente.js debe existir.');
        $this->jsContent = file_get_contents($jsPath);

        $this->combinedContent = $this->viewContent . "\n" . $this->jsContent;
    }

    /**
     * Test 12: Form reset limpia inputs manteniendo el banner del último expediente registrado.
     */
    public function test_reset_formulario_limpia_inputs_y_mantiene_ultimo_expediente(): void
    {
        // 1. Debe existir la sección con x-show="ultimoExpediente" para desplegar el número y folios
        $this->assertStringContainsString('x-show="ultimoExpediente"', $this->combinedContent);
        $this->assertStringContainsString('ultimoExpediente.numero', $this->combinedContent);
        $this->assertStringContainsString('ultimoExpediente.folios', $this->combinedContent);

        // 2. En el método resetFormulario() NO se debe limpiar ultimoExpediente
        $this->assertMatchesRegularExpression('/resetFormulario\s*\(\)\s*\{/', $this->combinedContent);
        preg_match('/resetFormulario\s*\(\)\s*\{(.*?)\n\s*validarCliente/s', $this->combinedContent, $matches);
        $this->assertNotEmpty($matches, 'Debe encontrarse el cuerpo de resetFormulario()');
        $resetBody = $matches[1];

        $this->assertStringNotContainsString('this.ultimoExpediente = null', $resetBody, 'resetFormulario no debe borrar el banner de ultimoExpediente');
        $this->assertStringContainsString('this.archivo = null', $resetBody);
        $this->assertStringContainsString('this.errors = {}', $resetBody);

        // 3. registrarOtro() sí reinicia ultimoExpediente para iniciar un flujo nuevo
        preg_match('/registrarOtro\s*\(\)\s*\{(.*?)\}/s', $this->combinedContent, $matchesOtro);
        $this->assertNotEmpty($matchesOtro);
        $this->assertStringContainsString('this.ultimoExpediente = null', $matchesOtro[1]);
        $this->assertStringContainsString('this.resetFormulario()', $matchesOtro[1]);
    }

    /**
     * Test 13: Detección de bloqueador de popups mantiene opción y botón manual para imprimir comprobante.
     */
    public function test_deteccion_bloqueador_popups_ofrece_boton_manual_de_voucher(): void
    {
        // 1. Debe existir bandera reactiva voucherBloqueado
        $this->assertStringContainsString('voucherBloqueado', $this->combinedContent);

        // 2. Debe verificarse el popup abierto en abrirComprobante
        $this->assertStringContainsString('window.open(url, \'_blank\'', $this->combinedContent);
        $this->assertStringContainsString('this.voucherBloqueado = true', $this->combinedContent);

        // 3. Debe mostrarse mensaje al usuario si el popup fue bloqueado
        $this->assertStringContainsString('El comprobante no pudo abrirse automáticamente', $this->combinedContent);

        // 4. Debe existir botón explícito para abrir/imprimir el comprobante
        $this->assertStringContainsString('abrirComprobante(ultimoExpediente.voucher_url)', $this->combinedContent);
        $this->assertStringContainsString('Imprimir comprobante', $this->combinedContent);
    }

    /**
     * Test 14: Acciones hacia bandeja usan la ruta nombrada tramites.bandeja y validan cambios pendientes (dirty form).
     */
    public function test_boton_volver_a_bandeja_usa_ruta_nombrada_y_controla_cambios_pendientes(): void
    {
        // 1. Botón "volverBandeja" disponible en las acciones post-registro (y no en el encabezado)
        $this->assertStringContainsString('@click="volverBandeja()"', $this->combinedContent);

        // 2. La ruta named de la bandeja debe inyectarse en el objeto routes
        $this->assertStringContainsString("route('tramites.bandeja'", $this->combinedContent);

        // 3. Debe existir método isDirty() para comprobar si hay datos ingresados sin guardar
        $this->assertStringContainsString('isDirty()', $this->combinedContent);
        $this->assertStringContainsString("confirm('Tiene cambios sin guardar en el formulario", $this->combinedContent);
        $this->assertStringContainsString('window.location.href = routes.bandeja', $this->combinedContent);

        // 4. Se eliminó completamente la recarga automática indiscriminada
        $this->assertStringNotContainsString('setTimeout(() => window.location.reload()', $this->combinedContent);
        $this->assertStringNotContainsString('setTimeout(function() { window.location.reload()', $this->combinedContent);
    }
}
