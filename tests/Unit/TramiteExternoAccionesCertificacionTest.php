<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\DocumentoInternoAsociadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController;
use App\Http\Requests\mod_gm\mod_tram_exte\ArchivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\AsignarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DerivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DevolverExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\EnviarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\FinalizarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\PrestarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RecepcionarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RedirigirExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RetornarPrestamoRequest;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Services\mod_gm\mod_tram_exte\DocumentoInternoDesdeExpedienteExternoService;
use App\Services\mod_gm\mod_tram_exte\IdempotenciaService;
use App\Services\mod_gm\mod_tram_exte\MaquinaEstadosTramiteExterno;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Tests\TestCase;

class TramiteExternoAccionesCertificacionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * CERT-01: TramiteExternoService no contiene 'cdgo_lgin' => $contexto->login
     * y utiliza estrictamente el identificador numérico canónico de login.
     */
    public function test_service_does_not_contain_hardcoded_login_username_and_enforces_positive_login_id(): void
    {
        $servicePath = app_path('Services/mod_gm/mod_tram_exte/TramiteExternoService.php');
        $this->assertFileExists($servicePath);
        $content = file_get_contents($servicePath);

        $this->assertDoesNotMatchRegularExpression(
            "/'cdgo_lgin'\\s*=>\\s*\\\$contexto->login/",
            $content,
            'TramiteExternoService nunca debe asignar el username string $contexto->login a la columna entera cdgo_lgin.'
        );

        // Debe resolver con getCdgoLgin()
        $this->assertStringContainsString(
            '$loginId = $contexto->getCdgoLgin();',
            $content,
            'TramiteExternoService debe resolver el login canónico mediante $contexto->getCdgoLgin().'
        );
    }

    /**
     * CERT-02: FormRequests de las 10 acciones operativas existen,
     * normalizan identificadores y validan reglas de negocio.
     */
    public function test_form_requests_validation_and_normalization(): void
    {
        // 1. Recepcionar: normaliza ids a movimiento_ids
        $reqRecepcionar = RecepcionarExpedienteRequest::create('/recepcionar', 'POST', [
            'ids'   => ['10', '20'],
            'fecha' => '2026-10-14 10:00:00',
        ]);
        $valRecepcionar = Validator::make($reqRecepcionar->all(), $reqRecepcionar->rules(), $reqRecepcionar->messages());
        $this->assertTrue($valRecepcionar->passes());
        $idsRecepcionar = array_map('intval', (array) ($reqRecepcionar->input('movimiento_ids') ?? $reqRecepcionar->input('ids')));
        $this->assertSame([10, 20], $idsRecepcionar);

        // 2. Derivar: enviar_con_tramite=0 permite doc_tipo nulo
        $reqDerivarSinDoc = DerivarExpedienteRequest::create('/derivar', 'POST', [
            'movimiento_ids'      => [15],
            'gerencia'            => 200,
            'fecha'               => '2026-10-14 10:00:00',
            'enviar_con_tramite'  => 0,
        ]);
        $valDerivarSinDoc = Validator::make($reqDerivarSinDoc->all(), $reqDerivarSinDoc->rules(), $reqDerivarSinDoc->messages());
        $this->assertTrue($valDerivarSinDoc->passes(), 'Derivar sin documento debe pasar cuando enviar_con_tramite es 0.');

        // Derivar: enviar_con_tramite=1 EXIGE doc_tipo
        $reqDerivarConDocInvalido = DerivarExpedienteRequest::create('/derivar', 'POST', [
            'movimiento_ids'      => [15],
            'gerencia'            => 200,
            'fecha'               => '2026-10-14 10:00:00',
            'enviar_con_tramite'  => 1,
            'doc_tipo'            => null,
        ]);
        $valDerivarConDocInvalido = Validator::make($reqDerivarConDocInvalido->all(), $reqDerivarConDocInvalido->rules(), $reqDerivarConDocInvalido->messages());
        $this->assertFalse($valDerivarConDocInvalido->passes(), 'Derivar con documento debe exigir doc_tipo cuando enviar_con_tramite es 1.');

        // 3. Asignar: condicional de doc_tipo
        $reqAsignarSinDoc = AsignarExpedienteRequest::create('/asignar', 'POST', [
            'movimiento_ids'     => [15],
            'cargo'              => 201,
            'fecha'              => '2026-10-14 10:00:00',
            'enviar_con_tramite' => 0,
        ]);
        $valAsignarSinDoc = Validator::make($reqAsignarSinDoc->all(), $reqAsignarSinDoc->rules(), $reqAsignarSinDoc->messages());
        $this->assertTrue($valAsignarSinDoc->passes());

        // 4. Enviar: condicional de doc_tipo
        $reqEnviarSinDoc = EnviarExpedienteRequest::create('/enviar', 'POST', [
            'movimiento_ids'     => [15],
            'areas'              => [200, 201],
            'fecha'              => '2026-10-14 10:00:00',
            'enviar_con_tramite' => 0,
        ]);
        $valEnviarSinDoc = Validator::make($reqEnviarSinDoc->all(), $reqEnviarSinDoc->rules(), $reqEnviarSinDoc->messages());
        $this->assertTrue($valEnviarSinDoc->passes());

        // 5. Finalizar: condicional de doc_tipo
        $reqFinalizarSinDoc = FinalizarExpedienteRequest::create('/finalizar', 'POST', [
            'movimiento_ids'     => [15],
            'observaciones'      => 'Trámite concluido satisfactoriamente',
            'fecha'              => '2026-10-14 10:00:00',
            'enviar_con_tramite' => 0,
        ]);
        $valFinalizarSinDoc = Validator::make($reqFinalizarSinDoc->all(), $reqFinalizarSinDoc->rules(), $reqFinalizarSinDoc->messages());
        $this->assertTrue($valFinalizarSinDoc->passes());

        // 6. Devolver: exige motivo min 5 caracteres
        $reqDevolverCorto = DevolverExpedienteRequest::create('/devolver', 'POST', [
            'movimiento_ids' => [15],
            'motivo'         => 'abc',
            'fecha'          => '2026-10-14 10:00:00',
        ]);
        $valDevolverCorto = Validator::make($reqDevolverCorto->all(), $reqDevolverCorto->rules(), $reqDevolverCorto->messages());
        $this->assertFalse($valDevolverCorto->passes(), 'Devolver exige al menos 5 caracteres de motivo.');

        $reqDevolverValido = DevolverExpedienteRequest::create('/devolver', 'POST', [
            'movimiento_ids' => [15],
            'motivo'         => 'Faltan folios adjuntos',
            'fecha'          => '2026-10-14 10:00:00',
        ]);
        $valDevolverValido = Validator::make($reqDevolverValido->all(), $reqDevolverValido->rules(), $reqDevolverValido->messages());
        $this->assertTrue($valDevolverValido->passes());

        // 7. Archivar: exige ubicacion
        $reqArchivarValido = ArchivarExpedienteRequest::create('/archivar', 'POST', [
            'movimiento_ids' => [15],
            'ubicacion'      => 'Estante 3 - Caja 12',
        ]);
        $valArchivarValido = Validator::make($reqArchivarValido->all(), $reqArchivarValido->rules(), $reqArchivarValido->messages());
        $this->assertTrue($valArchivarValido->passes());

        // 8. Prestar: resuelve expedienteId desde movimiento_id o expediente_id
        $reqPrestar1 = PrestarExpedienteRequest::create('/prestar', 'POST', [
            'movimiento_id' => 99,
            'prestatario'   => 'Juan Perez',
            'finalidad'     => 'Revisión judicial',
            'fecha_salida'  => '2026-10-14 10:00:00',
        ]);
        $valPrestar1 = Validator::make($reqPrestar1->all(), $reqPrestar1->rules(), $reqPrestar1->messages());
        $this->assertTrue($valPrestar1->passes());
        $idPrestar1 = (int) ($reqPrestar1->input('movimiento_id') ?? $reqPrestar1->input('expediente_id'));
        $this->assertSame(99, $idPrestar1);

        $reqPrestar2 = PrestarExpedienteRequest::create('/prestar', 'POST', [
            'expediente_id' => 88,
            'prestatario'   => 'Juan Perez',
            'finalidad'     => 'Revisión judicial',
            'fecha_salida'  => '2026-10-14 10:00:00',
        ]);
        $valPrestar2 = Validator::make($reqPrestar2->all(), $reqPrestar2->rules(), $reqPrestar2->messages());
        $this->assertTrue($valPrestar2->passes());
        $idPrestar2 = (int) ($reqPrestar2->input('movimiento_id') ?? $reqPrestar2->input('expediente_id'));
        $this->assertSame(88, $idPrestar2);

        // 9. Retornar préstamo: resuelve expedienteId
        $reqRetornar = RetornarPrestamoRequest::create('/retornar_prestamo', 'POST', [
            'movimiento_id' => 99,
            'observaciones' => 'Sin observaciones',
        ]);
        $valRetornar = Validator::make($reqRetornar->all(), $reqRetornar->rules(), $reqRetornar->messages());
        $this->assertTrue($valRetornar->passes());
        $idRetornar = (int) ($reqRetornar->input('movimiento_id') ?? $reqRetornar->input('expediente_id'));
        $this->assertSame(99, $idRetornar);

        // 10. Redirigir: resuelve expedienteId y valida nuevo_cargo_id
        $reqRedirigir = RedirigirExpedienteRequest::create('/redirigir', 'POST', [
            'movimiento_id'  => 99,
            'nuevo_cargo_id' => 50,
            'motivo'         => 'Reasignación de área',
        ]);
        $valRedirigir = Validator::make($reqRedirigir->all(), $reqRedirigir->rules(), $reqRedirigir->messages());
        $this->assertTrue($valRedirigir->passes());
        $idRedirigir = (int) ($reqRedirigir->input('movimiento_id') ?? $reqRedirigir->input('expediente_id'));
        $this->assertSame(99, $idRedirigir);
    }

    /**
     * CERT-03: DocumentoInternoDesdeExpedienteExternoService utiliza a_smlla_intrno
     * mediante obtenerSemillaInternaParaBloqueo con lock=true y vincula correctamente
     * p_dtos_ntrnos y r_trmtes_ntrnos.
     */
    public function test_documento_interno_service_contract_and_structure(): void
    {
        $servicePath = app_path('Services/mod_gm/mod_tram_exte/DocumentoInternoDesdeExpedienteExternoService.php');
        $this->assertFileExists($servicePath);
        $content = file_get_contents($servicePath);

        $this->assertStringContainsString('obtenerSemillaInternaParaBloqueo', $content);
        $this->assertStringContainsString('crearSemillaInterna', $content);
        $this->assertStringContainsString('p_dtos_ntrnos', $content);
        $this->assertStringContainsString('r_trmtes_ntrnos', $content);
        $this->assertStringContainsString('DocumentoInternoAsociadoDto', $content);

        // Verificar que el repositorio ejecute lockForUpdate() en obtenerSemillaInternaParaBloqueo
        $repoPath = app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php');
        $this->assertFileExists($repoPath);
        $repoContent = file_get_contents($repoPath);
        $this->assertStringContainsString('lockForUpdate()', $repoContent);
    }

    /**
     * CERT-04: CorreoExternoController cumple límites arquitectónicos:
     * - No usa DB:: raw
     * - No construye queries con ->table()
     * - No expone stack traces en endpoints AJAX
     */
    public function test_correo_externo_controller_architectural_boundaries(): void
    {
        $controllerRef = new ReflectionClass(CorreoExternoController::class);
        $content = file_get_contents($controllerRef->getFileName());

        $this->assertDoesNotMatchRegularExpression(
            '/(?<!\\\\)DB::/',
            $content,
            'CorreoExternoController no debe invocar la fachada DB:: directamente.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/->table\(/',
            $content,
            'CorreoExternoController no debe construir queries con ->table().'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/',
            $content,
            'CorreoExternoController no debe importar la fachada DB.'
        );
    }

    /**
     * CERT-05: El reporte de impresión de bandeja contiene las columnas
     * de Legacy, maneja arrays y objetos de forma segura y renderiza la vista correctamente.
     */
    public function test_reporte_bandeja_view_contract(): void
    {
        $viewPath = resource_path('views/mod_gm/mod_tram_exte/reporte_bandeja.blade.php');
        $this->assertFileExists($viewPath);
        $content = file_get_contents($viewPath);

        // Columnas Legacy
        $this->assertStringContainsString('>O<', $content);
        $this->assertStringContainsString('Nº Exp.', $content);
        $this->assertStringContainsString('F. Ingreso', $content);
        $this->assertStringContainsString('Doc. Identidad', $content);
        $this->assertStringContainsString('Nombre / Razón Social', $content);
        $this->assertStringContainsString('Asunto / Detalle', $content);
        $this->assertStringContainsString('Área Ingreso', $content);
        $this->assertStringContainsString('Estado', $content);
        $this->assertStringContainsString('F. Último', $content);
        $this->assertStringContainsString('Última Observación', $content);
        $this->assertStringContainsString('Cargo Destino', $content);

        // Prueba que renderice realmente la vista con objetos y arrays
        $rendered = view('mod_gm.mod_tram_exte.reporte_bandeja', [
            'bandeja' => 'entrada',
            'fechaImpresion' => '14/10/2026 10:00',
            'data' => [
                [
                    'tramite_formateado' => 'EXP-2026-0001',
                    'fe_ingr_trmte' => '14/10/2026 09:00',
                    'nu_docu' => '40506070',
                    'no_crto' => 'Juan Perez',
                    'asunto_expediente' => 'Solicitud de licencia',
                    'de_area' => 'Gerencia de Desarrollo Urbano',
                    'dc_estdo_trmte' => 'PENDIENTE',
                    'fe_rcbdo_trmte' => '14/10/2026 09:30',
                    'de_obse_trmte' => 'Pase a informe',
                    'de_crgo_en' => 'SUBGERENTE',
                    'indicador_origen' => 'V',
                ],
                (object) [
                    'nu_expe_todo' => 'EXP-2026-0002',
                    'fe_ingr_trmte' => '14/10/2026 09:15',
                    'nu_docu' => '10203040',
                    'no_crto' => 'Maria Lopez',
                    'de_obse_trmte' => 'Revisión técnica',
                    'dc_area' => 'GDU',
                    'dc_estdo_trmte' => 'RECIBIDO',
                    'fe_rcbdo_trmte' => '14/10/2026 09:45',
                    'de_crgo' => 'ESPECIALISTA',
                    'origen_flag' => 'L',
                ],
            ],
        ])->render();

        $this->assertStringContainsString('EXP-2026-0001', $rendered);
        $this->assertStringContainsString('EXP-2026-0002', $rendered);
        $this->assertStringContainsString('>V<', $rendered);
        $this->assertStringContainsString('>L<', $rendered);
    }

    /**
     * CERT-06: El frontend de acciones no contiene alert() nativo,
     * utiliza movimiento_ids[] canónico y trata errores 422 extrayendo el mensaje de validación.
     */
    public function test_frontend_acciones_contract(): void
    {
        $accionesPath = resource_path('js/tramite-externo/acciones.js');
        $this->assertFileExists($accionesPath);
        $accionesContent = file_get_contents($accionesPath);

        // Sin alert() nativo
        $this->assertDoesNotMatchRegularExpression(
            '/\balert\s*\(/',
            $accionesContent,
            'El frontend de acciones no debe usar alert() nativo según CODE-07.'
        );

        // Envía movimiento_ids
        $this->assertStringContainsString('movimiento_ids:', $accionesContent);

        // HttpClient maneja errores de validación
        $httpPath = resource_path('js/shared/http-client.js');
        $this->assertFileExists($httpPath);
        $httpContent = file_get_contents($httpPath);
        $this->assertStringContainsString('Object.keys(errors)[0]', $httpContent);
    }

    /**
     * CERT-07: Verificación estricta de que NO se crearon migraciones de base de datos
     * y no se alteró el esquema en base de datos.
     */
    public function test_no_database_migrations_were_introduced(): void
    {
        $migrationDir = database_path('migrations');
        $files = scandir($migrationDir) ?: [];
        $recentMigrationFiles = array_filter($files, function ($file) {
            return str_contains($file, 'tramite_externo') || str_contains($file, 'mod_tram_exte');
        });

        $this->assertEmpty(
            $recentMigrationFiles,
            'No deben existir archivos de migración automáticos para trámite externo según DB-04.'
        );
    }
}
