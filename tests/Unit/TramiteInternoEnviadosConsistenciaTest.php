<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Http\Requests\mod_gene\mod_tram_inter\ReemplazarFirmadoRequest;
use Illuminate\Support\Facades\Validator;

class TramiteInternoEnviadosConsistenciaTest extends TestCase
{
    private TramiteInternoPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TramiteInternoPolicy();
    }

    /**
     * Retorna el contenido consolidado de la vista correo-interno.blade.php,
     * sus partials Blade y los módulos JS de tramite-interno (RF-03 / RF-04 / RF-07).
     */
    private function obtenerContenidoVista(): string
    {
        $bladePath = resource_path('views/mod_gene/mod_tram_inter/correo-interno.blade.php');
        $content = (string) file_get_contents($bladePath);

        $partialsDir = resource_path('views/mod_gene/mod_tram_inter/partials');
        if (is_dir($partialsDir)) {
            foreach (glob($partialsDir . '/*.blade.php') ?: [] as $partial) {
                $content .= "\n" . file_get_contents($partial);
            }
        }

        $jsFiles = [
            resource_path('js/tramite-interno/renderers.js'),
            resource_path('js/tramite-interno/bandejas.js'),
            resource_path('js/tramite-interno/historico.js'),
            resource_path('js/tramite-interno/participantes.js'),
            resource_path('js/tramite-interno/acciones.js'),
            resource_path('js/tramite-interno/state.js'),
            resource_path('js/tramite-interno/api.js'),
            resource_path('js/tramite-interno/index.js'),
        ];
        foreach ($jsFiles as $jsFile) {
            if (file_exists($jsFile)) {
                $content .= "\n" . file_get_contents($jsFile);
            }
        }

        return $content;
    }

    public function test_enviados_renderiza_encabezado_y_celda_d_separados_de_h_y_a(): void
    {
        $content = $this->obtenerContenidoVista();

        // 1. Encabezado en thead de 'enviados' debe contener H, A y D
        // En Safe DOM (RF-04), se construyen programáticamente con makeTh('H'...), makeTh('A'...), makeTh('D'...)
        $this->assertMatchesRegularExpression(
            '/bandeja\s*===\s*[\'"]enviados[\'"][\s\S]*?makeTh\([\'"]H[\'"][\s\S]*?makeTh\([\'"]A[\'"][\s\S]*?makeTh\([\'"]D[\'"]/',
            $content,
            'La bandeja Enviados debe incluir las cabeceras separadas H (Hoja de Ruta), A (Adjunto) y D (Detalle/Firmado).'
        );

        // 2. Filas de 'enviados' deben renderizar las tres acciones separadas
        // En Safe DOM (RF-04), se invocan createHojaRutaCell, createAdjuntoCell y createDetalleDocCell
        $this->assertMatchesRegularExpression(
            '/bandeja\s*===\s*[\'"]enviados[\'"][\s\S]*?createHojaRutaCell[\s\S]*?createAdjuntoCell[\s\S]*?createDetalleDocCell/',
            $content,
            'Cada fila de Enviados debe incluir Hoja de Ruta (H), Adjunto (A) y Detalle del Documento / Reemplazo Firmado (D).'
        );

        // 3. El ojo de D no debe confundirse con hoja de ruta o adjunto
        $this->assertStringContainsString('fa-file-invoice text-xs', $content, 'Hoja de ruta debe usar icono de documento/factura.');
        $this->assertStringContainsString('fa-paperclip', $content, 'Adjunto debe usar icono de clip.');
        $this->assertStringContainsString('fa-eye', $content, 'Detalle/Voucher debe usar icono de ojo.');
    }

    public function test_reemplazo_firmado_respeta_policy_y_autorizacion(): void
    {
        $ctx = $this->crearContexto(
            login: 'jgarcia',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 5,
            areaId: 12,
            cargosAsignados: [5],
            permisos: []
        );

        // Caso 1: Cargo de salida coincide con el cargo autenticado -> Autorizado
        $movEmisor = new TramiteInternoMovimientoDto(
            id: 50,
            movimientoId: 100,
            numero: '1000',
            companiaId: 1,
            cargoSalidaId: 5,
            cargoEntradaId: 8,
            areaSalidaId: 12,
            areaEntradaId: 14,
            estadoId: 1,
            inEsta: '1',
            inCopia: '0'
        );
        $this->assertTrue($this->policy->reemplazarFirmado($ctx, $movEmisor));

        // Caso 2: Cargo de salida es ajeno y sin permiso 18 -> No autorizado (fail-closed)
        $movAjeno = new TramiteInternoMovimientoDto(
            id: 51,
            movimientoId: 101,
            numero: '1001',
            companiaId: 1,
            cargoSalidaId: 99,
            cargoEntradaId: 8,
            areaSalidaId: 20,
            areaEntradaId: 14,
            estadoId: 1,
            inEsta: '1',
            inCopia: '0'
        );
        $this->assertFalse($this->policy->reemplazarFirmado($ctx, $movAjeno));

        // Caso 3: Cargo ajeno pero con super-permiso 18 -> Autorizado
        $ctxAdmin = $this->crearContexto(
            login: 'admin',
            usuarioId: 1,
            companiaId: 1,
            cargoId: 5,
            areaId: 12,
            cargosAsignados: [5],
            permisos: [18]
        );
        $this->assertTrue($this->policy->reemplazarFirmado($ctxAdmin, $movAjeno));

        // Caso 4: Compañía ajena -> Denegado siempre
        $movOtraCmpnia = new TramiteInternoMovimientoDto(
            id: 52,
            movimientoId: 102,
            numero: '1002',
            companiaId: 2,
            cargoSalidaId: 5,
            cargoEntradaId: 8,
            areaSalidaId: 12,
            areaEntradaId: 14,
            estadoId: 1,
            inEsta: '1',
            inCopia: '0'
        );
        $this->assertFalse($this->policy->reemplazarFirmado($ctx, $movOtraCmpnia));

        // Caso 5: Trámite inactivo (in_esta != '1') -> Denegado
        $movInactivo = new TramiteInternoMovimientoDto(
            id: 53,
            movimientoId: 103,
            numero: '1003',
            companiaId: 1,
            cargoSalidaId: 5,
            cargoEntradaId: 8,
            areaSalidaId: 12,
            areaEntradaId: 14,
            estadoId: 1,
            inEsta: '0',
            inCopia: '0'
        );
        $this->assertFalse($this->policy->reemplazarFirmado($ctx, $movInactivo));
    }

    public function test_botones_menu_conservan_contraste_y_eliminan_conflicto_hover(): void
    {
        $blade = $this->obtenerContenidoVista();

        // 1. El botón activo inicial 'entrada' tiene aria-current="page" y bg-slate-800 sin hover:bg-slate-50
        $this->assertMatchesRegularExpression(
            '/data-bandeja="entrada"[^>]*aria-current="page"/',
            $blade,
            'El botón activo inicial debe contar con el atributo de accesibilidad aria-current="page".'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-bandeja="entrada"[^>]*hover:bg-slate-50/',
            $blade,
            'El botón activo inicial no debe contener la clase hover:bg-slate-50 que causa texto blanco sobre fondo blanco.'
        );

        // 2. La función activarNav remueve hover:bg-slate-50 al activarse y lo restablece al desactivarse
        $this->assertStringContainsString("b.classList.add('text-gray-600', 'hover:bg-slate-50')", $blade);
        $this->assertStringContainsString("btn.classList.remove('text-gray-600', 'hover:bg-slate-50')", $blade);
        $this->assertStringContainsString("btn.setAttribute('aria-current', 'page')", $blade);
    }

    public function test_columnas_equivalentes_usan_misma_escala_tipografica_sin_font_mono_aislado(): void
    {
        $blade = $this->obtenerContenidoVista();

        // Extraer bloques de renderFilasSeguras para cada bandeja
        preg_match('/else\s+if\s*\(\s*bandeja\s*===\s*[\'"]enviados[\'"]\s*\)\s*\{([\s\S]*?)\}\s*else\s+if/', $blade, $mEnviados);
        $this->assertNotEmpty($mEnviados, 'Debe existir el bloque renderTabla de Enviados.');
        $this->assertStringNotContainsString('font-mono', $mEnviados[1], 'La bandeja Enviados no debe usar font-mono de manera aislada.');

        preg_match('/else\s+if\s*\(\s*bandeja\s*===\s*[\'"]todos[\'"]\s*\)\s*\{([\s\S]*?)\}\s*else\s+if/', $blade, $mTodos);
        $this->assertNotEmpty($mTodos, 'Debe existir el bloque renderTabla de Todos.');
        $this->assertStringNotContainsString('font-mono', $mTodos[1], 'La bandeja Todos no debe usar font-mono de manera aislada.');

        preg_match('/else\s+if\s*\(\s*bandeja\s*===\s*[\'"]sin_finalizar[\'"][\s\S]*?\)\s*\{([\s\S]*?)\}\s*else\s*\{/', $blade, $mSinFin);
        $this->assertNotEmpty($mSinFin, 'Debe existir el bloque renderTabla de Sin Finalizar.');
        $this->assertStringNotContainsString('font-mono', $mSinFin[1], 'La bandeja Sin Finalizar no debe usar font-mono de manera aislada.');
    }

    public function test_comprobantes_pago_sustituye_placeholder_y_valida_policy(): void
    {
        $blade = $this->obtenerContenidoVista();

        // El botón lista ya no debe mostrar el aviso placeholder
        $this->assertStringNotContainsString(
            "mostrarAviso('Comprobantes de Pago', 'Módulo en proceso de integración.')",
            $blade,
            'El botón de Lista de Comprobantes ya no debe ser un placeholder estático.'
        );
        $this->assertStringContainsString(
            'abrirModalComprobantesPago()',
            $blade,
            'El botón de Lista de Comprobantes debe abrir el modal funcional de comprobantes.'
        );

        // Policy viewAccounting:
        $ctxConta = $this->crearContexto(
            login: 'cajero',
            usuarioId: 15,
            companiaId: 1,
            cargoId: 20,
            areaId: 21, // Área de contabilidad
            cargosAsignados: [20],
            permisos: []
        );
        $this->assertTrue($this->policy->verContabilidad($ctxConta), 'Área 21 (Contabilidad) debe tener acceso permitido.');

        $ctxOtro = $this->crearContexto(
            login: 'operador',
            usuarioId: 16,
            companiaId: 1,
            cargoId: 22,
            areaId: 10, // Otra área
            cargosAsignados: [22],
            permisos: []
        );
        $this->assertFalse($this->policy->verContabilidad($ctxOtro), 'Áreas no contables sin permiso 18 deben ser rechazadas.');

        $ctxOtroConPermiso = $this->crearContexto(
            login: 'auditor',
            usuarioId: 17,
            companiaId: 1,
            cargoId: 22,
            areaId: 10,
            cargosAsignados: [22],
            permisos: [18]
        );
        $this->assertTrue($this->policy->verContabilidad($ctxOtroConPermiso), 'Usuarios con permiso 18 deben tener acceso permitido.');
    }

    public function test_reemplazar_firmado_request_valida_entradas_estrictas(): void
    {
        $rules = (new ReemplazarFirmadoRequest())->rules();

        $v1 = Validator::make(['id' => 0], $rules);
        $this->assertTrue($v1->fails(), 'id debe ser entero positivo.');

        $v2 = Validator::make(['id' => -5], $rules);
        $this->assertTrue($v2->fails(), 'id negativo debe fallar.');

        $v3 = Validator::make(['id' => 'no_numero'], $rules);
        $this->assertTrue($v3->fails(), 'id no numérico debe fallar.');
    }
}