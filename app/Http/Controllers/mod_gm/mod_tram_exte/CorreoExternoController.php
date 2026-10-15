<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Http\Controllers\Controller;
use App\Http\Controllers\mod_gm\mod_tram_exte\Concerns\ApiResponseTrait;
use App\Http\Requests\mod_gm\mod_tram_exte\ArchivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\AsignarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ConsultarDetalleRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ConsultarHistorialRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ConsultarSemillaRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DerivarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\DevolverExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\EnviarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\FinalizarExpedienteRequest;
use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Requests\mod_gm\mod_tram_exte\ExportarBandejaExternaRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\ListarBandejaExternaRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\PrestarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RecepcionarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RedirigirExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RetornarPrestamoRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\SolicitudVideoRequest;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Services\mod_gm\mod_tram_exte\ExportarTramiteExternoExcelService;
use App\Services\mod_gm\mod_tram_exte\ConsolidacionExpedienteService;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use App\Support\SessionKeys;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Controlador de Trámite Externo (Correo Externo).
 * Refactorizado bajo arquitectura limpia (ARCH-01/02), controladores delgados,
 * autorización por recurso (SEC-01), respuestas seguras (SEC-07) y cero SQL dinámico.
 */
class CorreoExternoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TramiteExternoService $service,
        private readonly TramiteExternoRepositoryInterface $repository,
        private readonly GestorAdjuntosInterface $gestorAdjuntos,
        private readonly ConsolidacionExpedienteService $consolidacionService,
        private readonly TramiteExternoPolicy $policy,
        private readonly ?SecurityCoreInterface $securityCore = null,
        private readonly ?ExportarTramiteExternoExcelService $exportarExcelService = null,
        private ?ContextoUsuarioAutenticadoServiceInterface $contextoService = null
    ) {
        $this->contextoService = $this->contextoService ?? app(ContextoUsuarioAutenticadoServiceInterface::class);
    }

    /**
     * Muestra la bandeja principal de Trámite Externo.
     */
    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            if (!Auth::check()) {
                return redirect()->route('login');
            }
            if ((int) $request->session()->get(SessionKeys::CARGO_ID, 0) <= 0) {
                return redirect()->route('seleccionar.cargo');
            }
            abort(403, 'No tiene autorización o contexto válido para acceder a Trámite Externo.');
        }

        $datosIndex = $this->repository->obtenerDatosIndex($contexto);
        $vista = $this->vistaDesdeMenu($pathVista, $opcionMenu);

        return view($vista, array_merge($datosIndex, [
            'bandejaInicial'     => (string) $request->query('bandeja', 'entrada'),
            'puedeExportarExcel' => $this->policy->exportarExcel($contexto),
        ]));
    }

    /**
     * Consulta y filtra los expedientes de una de las 10 bandejas canónicas.
     */
    public function tabla(ListarBandejaExternaRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado o sin cargo activo.'], 401);
        }

        $bandeja = (string) $request->validated('bandeja', BandejaTramiteExterno::ENTRADA->value);
        $filtros = [
            'criterio'    => (string) $request->validated('criterio', '1'),
            'descripcion' => (string) $request->validated('descripcion', ''),
            'desde'       => (string) $request->validated('desde', ''),
            'hasta'       => (string) $request->validated('hasta', ''),
        ];

        $pagina = max(1, (int) $request->validated('page', 1));
        $porPagina = min(100, max(1, (int) $request->validated('per_page', 25)));

        try {
            $resultado = $this->repository->consultarBandeja($contexto, $bandeja, $filtros, $pagina, $porPagina);

            return response()->json([
                'status'     => 'success',
                'data'       => $resultado['data'],
                'pagination' => [
                    'current_page' => $pagina,
                    'last_page'    => $resultado['last_page'],
                    'per_page'     => $porPagina,
                    'total'        => $resultado['total'],
                ],
                'meta'       => [
                    'page'      => $pagina,
                    'per_page'  => $porPagina,
                    'total'     => $resultado['total'],
                    'last_page' => $resultado['last_page'],
                ],
            ]);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'No se pudo cargar la bandeja solicitada.');
        }
    }

    /**
     * Consulta el histórico completo de movimientos con especificación HIST-001 (Columnas U, A, D).
     */
    public function historial(ConsultarHistorialRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado.'], 401);
        }

        $expedienteId = (int) $request->validated('id');
        $movimientosHistoricos = $this->repository->obtenerMovimientosHistoricosPorExpediente(
            $expedienteId,
            $contexto->idCompania
        );
        if (!$this->policy->verHistorialExpediente($contexto, $movimientosHistoricos)) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado o sin autorización.'], 404);
        }

        try {
            $movimientos = $this->repository->obtenerHistoricoMovimientos($expedienteId);

            return response()->json([
                'status' => 'success',
                'data'   => $movimientos,
            ]);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'No se pudo cargar el historial del expediente.');
        }
    }

    /**
     * Obtiene el detalle formal de un expediente para el modal de detalle.
     */
    public function detalle(ConsultarDetalleRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado.'], 401);
        }

        $id = (int) $request->validated('id');
        $movimientosActivos = $this->repository->obtenerMovimientosActivosPorExpediente($id, $contexto->idCompania);
        if (empty($movimientosActivos) || !$this->policy->ver($contexto, $movimientosActivos)) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado o sin autorización.'], 404);
        }

        $detalle = $this->repository->obtenerDetalleExpediente($id, $contexto->idCompania);
        if (!$detalle) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado.'], 404);
        }

        $payload = isset($detalle['data']) && is_array($detalle['data']) ? $detalle['data'] : $detalle;
        unset($payload['data'], $payload['movimiento_dto']);

        return response()->json([
            'status' => 'success',
            'data'   => $payload,
        ]);
    }

    /**
     * Recepciona uno o varios expedientes seleccionados.
     */
    public function recepcionar(RecepcionarExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $ids = array_map('intval', (array) $request->validated('movimiento_ids'));
        $fecha = (string) $request->validated('fecha');
        $obs = $request->validated('observaciones') ? (string) $request->validated('observaciones') : null;
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->recepcionar($contexto, $ids, $fecha, $obs, $idempotencyKey);
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al recepcionar el expediente.');
        }
    }

    /**
     * Deriva expedientes hacia otra área/cargo.
     */
    public function derivar(DerivarExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $ids = array_map('intval', (array) $request->validated('movimiento_ids'));
        $cargoDestinoId = (int) ($request->validated('cargo') ?? 0);
        $areaDestinoId = (int) ($request->validated('subgerencia') ?? $request->validated('gerencia') ?? 0);
        $fecha = (string) $request->validated('fecha');
        $obs = $request->validated('observaciones') ? (string) $request->validated('observaciones') : null;
        $docTipo = $request->validated('doc_tipo') ? (int) $request->validated('doc_tipo') : null;
        $tramiteNro = $request->validated('tramite_nro') ? (string) $request->validated('tramite_nro') : null;
        $folios = $request->validated('folios') !== null ? (int) $request->validated('folios') : null;
        $adjunto = $request->file('fle_adjunto_pdf') ?? $request->file('adjunto');
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->derivar(
                $contexto,
                $ids,
                $cargoDestinoId,
                $fecha,
                $obs,
                $idempotencyKey,
                $areaDestinoId,
                $adjunto,
                $docTipo,
                $tramiteNro,
                $folios
            );
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al derivar el expediente.');
        }
    }

    /**
     * Asigna expedientes a un funcionario interno de la misma área.
     */
    public function asignar(AsignarExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $ids = array_map('intval', (array) $request->validated('movimiento_ids'));
        $cargoInternoId = (int) $request->validated('cargo');
        $fecha = (string) $request->validated('fecha');
        $obs = $request->validated('observaciones') ? (string) $request->validated('observaciones') : null;
        $docTipo = $request->validated('doc_tipo') ? (int) $request->validated('doc_tipo') : null;
        $tramiteNro = $request->validated('tramite_nro') ? (string) $request->validated('tramite_nro') : null;
        $folios = $request->validated('folios') !== null ? (int) $request->validated('folios') : null;
        $adjunto = $request->file('fle_adjunto_pdf') ?? $request->file('adjunto');
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->asignar(
                $contexto,
                $ids,
                $cargoInternoId,
                $fecha,
                $obs,
                $idempotencyKey,
                $docTipo,
                $tramiteNro,
                $folios,
                $adjunto
            );
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al asignar el expediente.');
        }
    }

    /**
     * Envía expedientes a múltiples áreas (Envío Multi-Área).
     * Resuelve CE-P1-003, CE-P1-004 y CE-07.
     */
    public function enviar(EnviarExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $ids = array_map('intval', (array) $request->validated('movimiento_ids'));
        $areas = array_map('intval', (array) $request->validated('areas', []));
        $fecha = (string) $request->validated('fecha');
        $obs = $request->validated('observaciones') ? (string) $request->validated('observaciones') : null;
        $docTipo = $request->validated('doc_tipo') ? (int) $request->validated('doc_tipo') : null;
        $tramiteNro = $request->validated('tramite_nro') ? (string) $request->validated('tramite_nro') : null;
        $folios = $request->validated('folios') !== null ? (int) $request->validated('folios') : null;
        $adjunto = $request->file('fle_adjunto_pdf') ?? $request->file('adjunto');
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->enviar(
                $contexto,
                $ids,
                $areas,
                $fecha,
                $obs,
                $docTipo,
                $tramiteNro,
                $folios,
                $adjunto,
                $idempotencyKey
            );
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al enviar el expediente.');
        }
    }

    /**
     * Finaliza los expedientes seleccionados.
     */
    public function finalizar(FinalizarExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $ids = array_map('intval', (array) $request->validated('movimiento_ids'));
        $fecha = (string) $request->validated('fecha');
        $obs = (string) $request->validated('observaciones');
        $correo = $request->validated('correo') ? (string) $request->validated('correo') : null;
        $enviarCorreo = $request->validated('enviar_correo') !== null ? (int) $request->validated('enviar_correo') : 0;
        $docTipo = $request->validated('doc_tipo') ? (int) $request->validated('doc_tipo') : null;
        $tramiteNro = $request->validated('tramite_nro') ? (string) $request->validated('tramite_nro') : null;
        $folios = $request->validated('folios') !== null ? (int) $request->validated('folios') : null;
        $adjunto = $request->file('fle_adjunto_pdf') ?? $request->file('adjunto');
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->finalizar(
                $contexto,
                $ids,
                $fecha,
                $obs,
                $correo,
                $idempotencyKey,
                $enviarCorreo,
                $docTipo,
                $tramiteNro,
                $folios,
                $adjunto
            );
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al finalizar el expediente.');
        }
    }

    /**
     * Devolver/regresar expediente desde la bandeja de salida (TE-015).
     */
    public function devolver(DevolverExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $ids = array_map('intval', (array) $request->validated('movimiento_ids'));
        $motivo = (string) $request->validated('motivo');
        $fecha = (string) $request->validated('fecha');
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->devolver($contexto, $ids, $motivo, $fecha, $idempotencyKey);
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al devolver el expediente.');
        }
    }

    /**
     * Archiva expedientes finalizados (TE-016, TE-026).
     */
    public function archivar(ArchivarExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $ids = array_map('intval', (array) $request->validated('movimiento_ids'));
        $ubicacion = (string) $request->validated('ubicacion');
        $obs = $request->validated('observaciones') ? (string) $request->validated('observaciones') : null;
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->archivar($contexto, $ids, $ubicacion, $obs, $idempotencyKey);
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al archivar el expediente.');
        }
    }

    /**
     * Presta un expediente archivado (TE-025).
     */
    public function prestar(PrestarExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $movimientoId = (int) $request->validated('movimiento_id');
        $prestatario = (string) $request->validated('prestatario');
        $finalidad = (string) $request->validated('finalidad');
        $salida = (string) $request->validated('fecha_salida');
        $retorno = $request->validated('fecha_retorno') ? (string) $request->validated('fecha_retorno') : null;
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->prestar($contexto, $movimientoId, $prestatario, $finalidad, $salida, $retorno, $idempotencyKey);
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al prestar el expediente.');
        }
    }

    /**
     * Retorna un expediente prestado al archivo con validación de Policy estricta.
     */
    public function retornarPrestamo(RetornarPrestamoRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $movimientoId = (int) $request->validated('movimiento_id');
        $obs = (string) ($request->validated('observaciones') ?? 'Devolución confirmada');
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->retornarPrestamo($contexto, $movimientoId, $obs, $idempotencyKey);
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al retornar el préstamo del expediente.');
        }
    }

    /**
     * Redirige administrativamente un expediente (TE-044).
     */
    public function redirigir(RedirigirExpedienteRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        $movimientoId = (int) $request->validated('movimiento_id');
        $nuevoCargoId = (int) $request->validated('nuevo_cargo_id');
        $motivo = (string) $request->validated('motivo');
        $idempotencyKey = $this->resolverClaveIdempotencia($request);
        if ($idempotencyKey === null) {
            return response()->json(['status' => 'error', 'message' => 'Cabecera Idempotency-Key obligatoria no proporcionada.'], 422);
        }

        try {
            $res = $this->service->redirigir($contexto, $movimientoId, $nuevoCargoId, $motivo, $idempotencyKey);
            return $this->respuestaExito($res['mensaje']);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al redirigir el expediente.');
        }
    }

    /**
     * Resuelve de forma segura y estricta la clave de idempotencia según SEC-07 y RFC 9205.
     */
    private function resolverClaveIdempotencia(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if (!is_string($key)) {
            return null;
        }

        $key = trim($key);
        return $key !== '' ? $key : null;
    }

    /**
     * Descarga segura de un archivo adjunto con autorización por Policy y registro de auditoría (TE-002, TE-012).
     */
    public function descargarAdjunto(Request $request, int $id): BinaryFileResponse|Response
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            abort(401, 'No autenticado');
        }

        $movimiento = $this->repository->obtenerMovimientoPorMovimientoId($id, $contexto->idCompania);
        if (!$movimiento || !$this->policy->descargarAdjunto($contexto, $movimiento)) {
            abort(404, 'Movimiento no encontrado o sin autorización.');
        }

        $movFila = $this->repository->obtenerAdjuntoAutorizadoPorMovimiento($id, $contexto->idCompania);
        $archivo = (string) ($movFila['de_urls_imag'] ?? '');
        if (empty($archivo)) {
            abort(404, 'El movimiento no posee archivo adjunto.');
        }

        try {
            $rutaFisica = $this->gestorAdjuntos->resolverRutaSegura($archivo);
            $this->repository->registrarAuditoriaDescarga($id, $contexto->nombreLogin, (string) $request->ip());

            return response()->download($rutaFisica, basename($archivo));
        } catch (Throwable) {
            abort(404, 'Archivo no disponible en el almacenamiento seguro.');
        }
    }

    /**
     * Descarga consolidada de todos los adjuntos de un expediente en formato ZIP (HIST-001 / TE-014).
     */
    public function descargarTodos(Request $request, int $id): BinaryFileResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            abort(401, 'No autenticado');
        }

        $movimientosHistoricos = $this->repository->obtenerMovimientosHistoricosPorExpediente(
            $id,
            $contexto->idCompania
        );
        if (!$this->policy->verHistorialExpediente($contexto, $movimientosHistoricos)) {
            abort(404, 'Expediente no encontrado o sin autorización.');
        }

        return $this->consolidacionService->generarZipConsolidado(
            $contexto,
            $id,
            (string) $request->ip()
        );
    }

    /**
     * Muestra la Hoja de Ruta para impresión.
     */
    public function hojaRuta(Request $request, int $id)
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            abort(401, 'No autenticado');
        }

        $movimientosActivos = $this->repository->obtenerMovimientosActivosPorExpediente($id, $contexto->idCompania);
        if (empty($movimientosActivos) || !$this->policy->hojaRuta($contexto, $movimientosActivos)) {
            abort(404, 'Expediente no encontrado o sin autorización.');
        }

        $datosHojaRuta = $this->repository->obtenerDatosHojaRuta($id, $contexto->idCompania);
        if (!$datosHojaRuta) {
            abort(404, 'Expediente no encontrado.');
        }

        $expediente = $datosHojaRuta['expediente'];
        $historial = collect($datosHojaRuta['historial'])->map(function ($mov) {
            $mov->fe_entrada = $mov->fe_entrada ? Carbon::parse($mov->fe_entrada)->format('d/m/Y H:i:s') : '—';
            return $mov;
        });

        return view('mod_gm\mod_tram_exte\hoja_ruta', compact('expediente', 'historial'));
    }

    /**
     * Imprime reporte PDF de la bandeja activa sin truncamiento silencioso.
     */
    public function imprimirBandeja(ListarBandejaExternaRequest $request)
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            abort(401, 'No autenticado');
        }

        $bandeja = (string) $request->validated('bandeja', 'entrada');
        $filtros = [
            'criterio'    => (string) $request->validated('criterio', '1'),
            'descripcion' => (string) $request->validated('descripcion', ''),
            'desde'       => (string) $request->validated('desde', ''),
            'hasta'       => (string) $request->validated('hasta', ''),
        ];

        $resultado = $this->repository->consultarBandeja($contexto, $bandeja, $filtros, 1, 0);

        $pdf = Pdf::loadView('mod_gm.mod_tram_exte.reporte_bandeja', [
            'data'           => $resultado['data'],
            'bandeja'        => $bandeja,
            'fechaImpresion' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte_bandeja_' . now()->format('YmdHi') . '.pdf');
    }

    /**
     * Solicitud de video tutorial o lista de solicitudes asociadas a un expediente.
     * Resuelve CE-P1-010 y CEF-P0-04.
     */
    public function solicitudVideo(SolicitudVideoRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado.'], 401);
        }

        $id = (int) $request->validated('id');
        $movimientosActivos = $this->repository->obtenerMovimientosActivosPorExpediente($id, $contexto->idCompania);
        if (empty($movimientosActivos) || !$this->policy->ver($contexto, $movimientosActivos)) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado o sin autorización.'], 404);
        }

        $solicitudes = $this->repository->obtenerSolicitudesVideo($id, $contexto->idCompania);

        return response()->json([
            'status'  => 'success',
            'data'    => $solicitudes,
            'message' => 'Solicitudes obtenidas con éxito.',
        ]);
    }

    /**
     * Obtiene el correlativo semilla para un tipo de documento interno vinculado.
     * Resuelve CE-P0-002 y CE-01.
     */
    public function obtenerSemilla(ConsultarSemillaRequest $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado.'], 401);
        }

        $docTipo = (int) $request->validated('doc_tipo');
        $semilla = $this->repository->obtenerSemillaInterno(
            $docTipo,
            $contexto->idCargo,
            $contexto->idArea,
            $contexto->idCompania
        );

        return response()->json([
            'status'  => 'success',
            'semilla' => $semilla['nu_smlla_intrno'] ?? (string) ($semilla['nu_smlla'] ?? '1'),
            'data'    => $semilla,
        ]);
    }

    /**
     * Extrae el DTO de contexto activo del funcionario validando sesión según SEC-01.
     */
    private function obtenerContexto(Request $request): ?ContextoUsuarioAutenticadoDto
    {
        return $this->contextoService->obtenerContextoActivo();
    }

    /**
     * Exporta los expedientes filtrados de Trámite Externo a un archivo Excel XLSX real.
     *
     * Valida obligatoriamente:
     * 1. Autenticación y contexto activo (login + compañía sin fallbacks).
     * 2. Permiso base del módulo de Trámite Externo vía SecurityCore.
     * 3. Permiso granular 'tramite_externo.exportar_excel' vía TramiteExternoPolicy.
     */
    public function exportarExcel(ExportarBandejaExternaRequest $request): BinaryFileResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            abort(403, 'Acceso denegado: sesión o contexto activo no válido.');
        }

        // 1. Autorización base del módulo (SecurityCore)
        $security = $this->securityCore ?? app(SecurityCoreInterface::class);
        $cdgoLgin = $contexto->getCdgoLgin();
        $tieneAccesoModulo = $security->tienePermiso(
            $cdgoLgin,
            'mod_gm/mod_tram_exte/segi_expedi',
            $contexto->idCargo,
            'ver'
        );

        if (!$tieneAccesoModulo) {
            abort(403, 'Acceso denegado al módulo de Trámite Externo.');
        }

        // 2. Autorización granular de acción 'tramite_externo.exportar_excel' (Policy)
        if (!$this->policy->exportarExcel($contexto)) {
            abort(403, 'No tiene autorización para exportar expedientes a Excel.');
        }

        // 3. Ejecución de la exportación a XLSX real streaming
        $exporter = $this->exportarExcelService ?? app(ExportarTramiteExternoExcelService::class);

        return $exporter->exportar($contexto, $request->validated());
    }

    /**
     * Resuelve la ruta canónica de la vista de seguimiento.
     */
    protected function vistaDesdeMenu(?string $pathVista = null, ?object $opcionMenu = null): string
    {
        if (is_string($pathVista) && $pathVista !== '' && view()->exists($pathVista)) {
            return $pathVista;
        }

        $desdeOpcion = $this->vistaDesdeOpcion($opcionMenu);
        if ($desdeOpcion !== '' && view()->exists($desdeOpcion)) {
            return $desdeOpcion;
        }

        return 'mod_gm.mod_tram_exte.segi_expedi';
    }

    /**
     * Retorna los conteos globales de las 10 bandejas canónicas para el contexto activo (Spec 12).
     */
    public function conteos(Request $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado.'], 401);
        }

        try {
            $conteos = $this->repository->obtenerConteosBandejas($contexto);

            return response()->json([
                'status' => 'success',
                'data'   => $conteos,
                'meta'   => [
                    'contexto'       => 'cargo',
                    'actualizado_en' => Carbon::now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'No se pudieron calcular los conteos de las bandejas.');
        }
    }

    /**
     * Retorna los participantes de la columna U de un movimiento (Spec 10).
     */
    public function participantes(Request $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado.'], 401);
        }

        $movimientoId = (int) $request->input('movimiento_id');
        if ($movimientoId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID de movimiento inválido.'], 400);
        }

        $mov = $this->repository->obtenerMovimientoPorMovimientoId($movimientoId, $contexto->idCompania);
        if (!$mov) {
            return response()->json(['status' => 'error', 'message' => 'Movimiento no encontrado o sin autorización.'], 404);
        }

        $autorizado = ($mov->inEsta === '1' && $this->policy->ver($contexto, $mov));
        if (!$autorizado) {
            $movimientosActivos = $this->repository->obtenerMovimientosActivosPorExpediente($mov->expedienteId, $contexto->idCompania);
            $autorizado = $this->policy->verHistorico($contexto, $mov, $movimientosActivos);
        }
        if (!$autorizado) {
            $movimientosHistoricos = $this->repository->obtenerMovimientosHistoricosPorExpediente($mov->expedienteId, $contexto->idCompania);
            $autorizado = $this->policy->verHistorialExpediente($contexto, $movimientosHistoricos);
        }

        if (!$autorizado) {
            return response()->json(['status' => 'error', 'message' => 'Movimiento no encontrado o sin autorización.'], 404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50], true) ? (int) $request->input('per_page', 25) : 25;
        $search = $request->input('search') ? (string) $request->input('search') : null;

        try {
            $resultado = $this->repository->obtenerParticipantesU($movimientoId, $contexto, $page, $perPage, $search);
            if (($resultado['status'] ?? '') === 'error') {
                return response()->json($resultado, $resultado['code'] ?? 404);
            }
            return response()->json($resultado);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'No se pudieron cargar los participantes.');
        }
    }

    /**
     * Retorna la trazabilidad de descargas (p_sgmto_dscrgas) de un movimiento (Columna A).
     */
    public function auditoriaDescargas(Request $request): JsonResponse
    {
        $contexto = $this->obtenerContexto($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado.'], 401);
        }

        $movimientoId = (int) $request->input('movimiento_id');
        if ($movimientoId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID de movimiento inválido.'], 400);
        }

        $mov = $this->repository->obtenerMovimientoPorMovimientoId($movimientoId, $contexto->idCompania);
        if (!$mov) {
            return response()->json(['status' => 'error', 'message' => 'Movimiento no encontrado o sin autorización.'], 404);
        }

        $autorizado = ($mov->inEsta === '1' && $this->policy->ver($contexto, $mov));
        if (!$autorizado) {
            $movimientosActivos = $this->repository->obtenerMovimientosActivosPorExpediente($mov->expedienteId, $contexto->idCompania);
            $autorizado = $this->policy->verHistorico($contexto, $mov, $movimientosActivos);
        }
        if (!$autorizado) {
            $movimientosHistoricos = $this->repository->obtenerMovimientosHistoricosPorExpediente($mov->expedienteId, $contexto->idCompania);
            $autorizado = $this->policy->verHistorialExpediente($contexto, $movimientosHistoricos);
        }

        if (!$autorizado) {
            return response()->json(['status' => 'error', 'message' => 'Movimiento no encontrado o sin autorización.'], 404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50], true) ? (int) $request->input('per_page', 10) : 10;

        try {
            $resultado = $this->repository->obtenerAuditoriaDescargasPaginada($movimientoId, $contexto->idCompania, $page, $perPage);
            if (($resultado['status'] ?? '') === 'error') {
                return response()->json($resultado, $resultado['code'] ?? 404);
            }
            return response()->json($resultado);
        } catch (Throwable $e) {
            return $this->respuestaSeguraError($e, 'No se pudo cargar la auditoría de descargas.');
        }
    }
}
