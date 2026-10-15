<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\Http\Requests\mod_gene\mod_tram_inter\ListarBandejaInternaRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\ReemplazarFirmadoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\RecepcionarTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\AsignarTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\DerivarTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\FinalizarTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\DevolverTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\ReiterarTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\CorrelativoTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\ListarHistoricoInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\DescargarLoteTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\AuditoriaDescargasInternoRequest;
use App\Http\Controllers\Concerns\ApiResponseTrait;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Services\mod_gene\mod_tram_inter\ConsolidadoExpedienteService;
use App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService;
use App\Services\mod_gene\mod_tram_inter\DescargaLoteTramiteInternoService;
use App\Services\mod_gene\mod_tram_inter\ReemplazarDocumentoFirmadoService;
use App\Services\mod_gene\mod_tram_inter\ValidadorPagoProveedoresTipo64Service;
use App\Support\SessionKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException;
use App\Exceptions\mod_gene\DestinoTramiteInvalidoException;
use App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException;
use App\Exceptions\mod_gene\TramiteInternoNoEncontradoException;
use App\Exceptions\mod_gene\TransicionTramiteInvalidaException;
use InvalidArgumentException;

class CorreoInternoController extends Controller
{
    use ApiResponseTrait;

    private readonly ValidadorPagoProveedoresTipo64Service $validadorTipo64;
    private readonly ConsolidadoExpedienteService $consolidadoService;
    private readonly DescargaLoteTramiteInternoService $descargaLoteService;
    private readonly ReemplazarDocumentoFirmadoService $reemplazarService;

    public function __construct(
        private readonly CorreoInternoTransaccionalService $transaccionalService,
        private readonly TramiteInternoRepositoryInterface $repository,
        private readonly TramiteInternoPolicy $policy,
        ?ValidadorPagoProveedoresTipo64Service $validadorTipo64 = null,
        ?ConsolidadoExpedienteService $consolidadoService = null,
        ?DescargaLoteTramiteInternoService $descargaLoteService = null,
        ?ReemplazarDocumentoFirmadoService $reemplazarService = null,
        private ?ContextoUsuarioAutenticadoServiceInterface $contextoService = null
    ) {
        $this->validadorTipo64 = $validadorTipo64 ?? app(ValidadorPagoProveedoresTipo64Service::class);
        $this->consolidadoService = $consolidadoService ?? app(ConsolidadoExpedienteService::class);
        $this->descargaLoteService = $descargaLoteService ?? app(DescargaLoteTramiteInternoService::class);
        $this->reemplazarService = $reemplazarService ?? app(ReemplazarDocumentoFirmadoService::class);
        $this->contextoService = $this->contextoService ?? app(ContextoUsuarioAutenticadoServiceInterface::class);
    }

    public function index(Request $request): \Illuminate\View\View
    {
        $user = Auth::user();
        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        $cargoInfo = $this->repository->obtenerCargoPorId($cargoId);
        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);

        // Videos tutoriales institucionales (6 videos exactos del legacy segi_expedi.php)
        $videosDisponibles = collect([
            ['key' => 'ingresar', 'titulo' => '¿Cómo Ingreso?', 'archivo' => 'i.Ingresar.mp4', 'icono' => 'fa-file-circle-plus'],
            ['key' => 'recepcionar', 'titulo' => '¿Cómo Recepciono?', 'archivo' => 'i.Recepcionar.mp4', 'icono' => 'fa-inbox'],
            ['key' => 'derivar', 'titulo' => '¿Cómo Derivo?', 'archivo' => 'i.Derivar.mp4', 'icono' => 'fa-share'],
            ['key' => 'asignar', 'titulo' => '¿Cómo Asigno?', 'archivo' => 'i.Asignar.mp4', 'icono' => 'fa-user-check'],
            ['key' => 'finalizar', 'titulo' => '¿Cómo Finalizo?', 'archivo' => 'i.Finalizar.mp4', 'icono' => 'fa-flag-checkered'],
            ['key' => 'devolver', 'titulo' => '¿Cómo Devuelvo?', 'archivo' => 'i.Devolver.mp4', 'icono' => 'fa-rotate-left'],
        ])->map(function ($v) {
            $path = public_path('asset/videos/' . $v['archivo']);
            $v['existe'] = is_file($path);
            $v['url'] = $v['existe'] ? asset('asset/videos/' . $v['archivo']) : null;
            return $v;
        })->all();

        return view('mod_gene.mod_tram_inter.correo-interno', [
            'areaId' => $areaId,
            'cargoId' => $cargoId,
            'esAreaContabilidad' => $areaId === 21,
            'videos' => $videosDisponibles,
        ]);
    }

    public function tabla(ListarBandejaInternaRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesión o cargo no válido para el contexto interno.'
            ], 401);
        }

        $bandeja = (string) $request->input('bandeja', 'entrada');
        if ($bandeja === 'contabilidad' && !$this->policy->verContabilidad($contexto)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No autorizado para acceder a la bandeja de contabilidad.'
            ], 403);
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $criterio = $request->input('criterio');
        $descripcion = trim((string) $request->input('descripcion', ''));
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $reporte = $request->input('reporte');
        $faltantes = (int) $request->input('faltantes', 0) === 1 || $reporte === 'sin_finalizar';

        $filtros = [
            'criterio'    => $criterio,
            'descripcion' => $descripcion,
            'desde'       => $desde,
            'hasta'       => $hasta,
            'faltantes'   => $faltantes,
        ];

        $resultado = $this->repository->listarBandeja($bandeja, $contexto, $filtros, $page, $perPage);

        return response()->json([
            'status'   => 'success',
            'data'     => $resultado['data'],
            'meta'     => $resultado['meta'],
            'area_id'  => $contexto->idArea,
            'cargo_id' => $contexto->idCargo
        ]);
    }

    public function conteos(Request $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesión o cargo no válido para el contexto interno.'
            ], 401);
        }

        $conteos = $this->repository->obtenerConteosBandejas($contexto);

        return response()->json([
            'status'   => 'success',
            'data'     => $conteos,
            'area_id'  => $contexto->idArea,
            'cargo_id' => $contexto->idCargo
        ]);
    }

    public function participantes(Request $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesión o cargo no válido para el contexto interno.'
            ], 401);
        }

        $movimientoId = (int) $request->input('movimiento_id', 0);
        if ($movimientoId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Identificador de movimiento inválido.'
            ], 422);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50], true)
            ? (int) $request->input('per_page', 25)
            : 25;
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $mov = $this->repository->obtenerMovimientoHistoricoPorId($movimientoId, $contexto->idCompania);
        if (!$mov || !$this->policy->consultarHistorico($contexto, $mov)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Movimiento no encontrado o sin autorización.'
            ], 404);
        }

        try {
            $resultado = $this->repository->obtenerParticipantesU($movimientoId, $contexto, $page, $perPage, $search);

            if (isset($resultado['code']) && $resultado['code'] === 404) {
                return response()->json([
                    'status' => 'error',
                    'message' => $resultado['message']
                ], 404);
            }

            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error('[CorreoInternoController@participantes] Error: ' . $e->getMessage(), [
                'movimiento_id' => $movimientoId,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al consultar participantes U.',
            ], 500);
        }
    }

    /**
     * Retorna la trazabilidad de descargas (p_sgmto_dscrgas) de un movimiento interno de forma paginada.
     */
    public function auditoriaDescargas(AuditoriaDescargasInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesión o cargo no válido para el contexto interno.'
            ], 401);
        }

        $movimientoId = (int) $request->input('movimiento_id');
        if ($movimientoId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Identificador de movimiento inválido.'
            ], 422);
        }

        $mov = $this->repository->obtenerMovimientoHistoricoPorId($movimientoId, $contexto->idCompania);
        if (!$mov || !$this->policy->consultarHistorico($contexto, $mov)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Movimiento no encontrado o sin autorización.'
            ], 404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        try {
            $resultado = $this->repository->obtenerAuditoriaDescargasPaginada(
                $movimientoId,
                $contexto->idCompania,
                $page,
                $perPage
            );
            if (($resultado['status'] ?? '') === 'error') {
                return response()->json($resultado, $resultado['code'] ?? 404);
            }
            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error('[CorreoInternoController@auditoriaDescargas] Error: ' . $e->getMessage(), [
                'movimiento_id' => $movimientoId,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo cargar la auditoría de descargas.',
            ], 500);
        }
    }

    private function obtenerContextoInterno(Request $request): ?ContextoUsuarioAutenticadoDto
    {
        return $this->contextoService->obtenerContextoActivo();
    }

    /**
     * HISTÓRICO AL 1000% (segi_tramite.php legacy)
     * Retorna cabecera con badge Khaki, 9 columnas, cálculo de T.E,
     * Popover U (Origen vs Destino con r_crgos_lgin) y auditoría de descargas A.
     */
    public function historial(ListarHistoricoInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido'], 401);
        }

        $documentoId = $request->input('documento_id') !== null && $request->input('documento_id') !== ''
            ? (int) $request->input('documento_id')
            : null;
        $movimientoId = $request->input('movimiento_id') !== null && $request->input('movimiento_id') !== ''
            ? (int) $request->input('movimiento_id')
            : null;

        $id = $documentoId;
        $movimiento = null;
        if ($movimientoId !== null && $movimientoId > 0) {
            $movimiento = $this->repository->obtenerMovimientoHistoricoAutorizado(
                $movimientoId,
                $contexto->idCompania
            );
            if (!$movimiento) {
                return response()->json(['status' => 'error', 'message' => 'Movimiento no encontrado'], 404);
            }

            if ($documentoId !== null && $documentoId > 0 && $movimiento->id !== $documentoId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El movimiento especificado no pertenece al documento indicado.',
                ], 422);
            }

            $id = $movimiento->id;
        }

        if (empty($id)) {
            return response()->json(['status' => 'error', 'message' => 'ID de trámite no proporcionado o inválido'], 400);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = in_array((int) $request->input('per_page', 15), [10, 15, 25, 50], true) ? (int) $request->input('per_page', 15) : 15;

        // 1. Obtener cabecera del documento para el badge Khaki verificando compañía (sin filtrar in_esta de cabecera)
        $tramite = $this->repository->obtenerDocumentoHistoricoAutorizado($id, $contexto->idCompania);

        if (!$tramite) {
            return response()->json(['status' => 'error', 'message' => 'Trámite no encontrado'], 404);
        }

        if (!$this->policy->verDocumentoHistorico($contexto, (int) $tramite->cdgo_cmpnia)) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado para consultar el historial de este trámite.'], 403);
        }

        $tipo = $tramite->de_tpo_trmte ?? 'DOCUMENTO';
        $numero = $tramite->nu_tram_todo ?? $id;
        $siglaArea = str_replace('.', '', trim((string) ($tramite->dc_area ?? '')));
        $histTitle = trim("{$tipo} {$numero}" . ($siglaArea !== '' ? "-{$siglaArea}" : ''));

        $resultado = $this->repository->obtenerHistorial(
            $movimientoId ?? 0,
            $contexto,
            $page,
            $perPage,
            $id
        );

        return response()->json([
            'status' => 'success',
            'titulo' => $histTitle,
            'tramite' => $tramite,
            'data' => $resultado['data'],
            'meta' => $resultado['meta'],
        ]);
    }

    public function recepcionar(RecepcionarTramiteInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido.'], 401);
        }
        if (!$contexto->cargoAsignadoValido()) {
            return response()->json(['status' => 'error', 'message' => 'El cargo no se encuentra asignado al usuario activo.'], 403);
        }

        $ids = (array) ($request->validated('ids') ?? $request->input('ids', []));
        $fecha = $request->validated('fecha') ? Carbon::parse($request->validated('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim((string) ($request->validated('observacion') ?? $request->validated('observaciones') ?? $request->input('observaciones', 'SIN NOVEDADES PARA RECEPCIONAR'))), 'UTF-8');

        $intIds = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($intIds)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un trámite.'], 422);
        }

        // RA-04: Validación de pagos a proveedores (Tipo 64) FAIL-CLOSED
        $valTipo64 = $this->validadorTipo64->validarLoteParaRecepcion($intIds);
        if (!$valTipo64['valido']) {
            $status = !empty($valTipo64['servicio_no_disponible']) ? 503 : 422;
            return response()->json([
                'status' => 'error',
                'message' => $valTipo64['mensaje'] ?? 'No cumple validación de Tipo 64.',
            ], $status);
        }

        try {
            $result = $this->transaccionalService->ejecutarRecepcionar(
                $intIds,
                $fecha,
                $observaciones,
                $contexto,
                (string) $request->ip()
            );

            return response()->json($result);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (TransicionTramiteInvalidaException | ConflictoConcurrenteTramiteException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (DestinoTramiteInvalidoException | InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al recepcionar los trámites.');
        }
    }

    /**
     * DETALLE DEL DOCUMENTO INTERNO (busca_vauche.php legacy - Foto 1)
     * Modal con detalle cabecera del documento: Nº Tram, Fecha, Hora, Area Inicio, Detalles, Adjunto.
     */
    public function detalleDocumento(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $id = (int) $id;
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Identificador no proporcionado.'], 400);
        }

        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido'], 401);
        }

        $tramite = $this->repository->obtenerDocumentoHistoricoAutorizado($id, $contexto->idCompania);

        if (!$tramite) {
            return response()->json(['status' => 'error', 'message' => 'Documento no encontrado'], 404);
        }

        if (!$this->policy->verDocumentoHistorico($contexto, (int) $tramite->cdgo_cmpnia)) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado para consultar el detalle de este documento.'], 403);
        }

        $movDto = $this->repository->obtenerUltimoMovimientoPorDocumento($id, $contexto->idCompania);
        if (!$movDto) {
            $movDto = new TramiteInternoMovimientoDto(
                id: (int) $tramite->cdgo_dtos_ntrnos,
                movimientoId: $id,
                numero: (string) ($tramite->nu_tram_todo ?? $id),
                companiaId: (int) ($tramite->cdgo_cmpnia ?? $contexto->idCompania),
                cargoSalidaId: $contexto->idCargo,
                cargoEntradaId: 0,
                areaSalidaId: (int) ($tramite->cdgo_area ?? $contexto->idArea),
                areaEntradaId: 0,
                estadoId: 1,
                inEsta: '1',
                inCopia: '0'
            );
        }

        $puedeReemplazar = $this->policy->reemplazarFirmado($contexto, $movDto);

        $numeroTram = ($tramite->de_tpo_trmte ?? 'DOCUMENTO') . ' - ' . str_pad((string) ($tramite->nu_tram_todo ?? $id), 10, '0', STR_PAD_LEFT);
        $fecha = $tramite->fe_ingrso ? Carbon::parse($tramite->fe_ingrso)->format('d/m/Y') : '-';
        $hora = $tramite->fe_ingrso ? Carbon::parse($tramite->fe_ingrso)->format('H:i:s') : '-';

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $tramite->cdgo_dtos_ntrnos,
                'numero_tramite' => $numeroTram,
                'fecha' => $fecha,
                'hora' => $hora,
                'area_inicio' => $tramite->de_area ?? ($tramite->dc_area ?? '-'),
                'detalles' => $tramite->de_obser ?? 'SIN OBSERVACIONES',
                'adjunto' => $tramite->de_urls_adjnto ?? null,
                'puede_reemplazar' => $puedeReemplazar,
            ]
        ]);
    }

    /**
     * VOUCHER / COMPROBANTE DE MOVIMIENTO (Columna D - segi_movimiento legacy)
     */
    public function voucher(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $id = (int) $id;
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Identificador no proporcionado.'], 400);
        }

        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido'], 401);
        }

        $mov = $this->repository->obtenerVoucherMovimientoInterno($id, $contexto);

        if (!$mov) {
            return response()->json(['status' => 'error', 'message' => 'Comprobante no encontrado.'], 404);
        }

        $movDto = new TramiteInternoMovimientoDto(
            id: (int) $mov->cdgo_dtos_ntrnos,
            movimientoId: (int) $mov->cdgo_trmtes_ntrnos,
            numero: (string) ($mov->nu_tram_todo ?? ''),
            companiaId: (int) $mov->cdgo_cmpnia,
            cargoSalidaId: (int) $mov->cdgo_crgo_sa,
            cargoEntradaId: (int) $mov->cdgo_crgo_en,
            areaSalidaId: (int) ($mov->area_sa ?? 0),
            areaEntradaId: (int) ($mov->area_en ?? 0),
            estadoId: (int) ($mov->cdgo_estdo_trmte ?? 1),
            inEsta: (string) $mov->in_esta,
            inCopia: (string) ($mov->in_copia ?? '0')
        );

        if (!$this->policy->consultarHistorico($contexto, $movDto)) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado para consultar este comprobante.'], 403);
        }

        $numeroTram = ($mov->de_tpo_trmte ?? 'DOCUMENTO') . ' - ' . str_pad((string) ($mov->nu_tram_todo ?? $mov->cdgo_dtos_ntrnos), 10, '0', STR_PAD_LEFT);
        $fecha = $mov->fe_rcbdo ? Carbon::parse($mov->fe_rcbdo)->format('d/m/Y') : now()->format('d/m/Y');
        $hora = $mov->fe_rcbdo ? Carbon::parse($mov->fe_rcbdo)->format('H:i:s') : now()->format('H:i:s');

        $detalles = !empty(trim((string) ($mov->de_obser_ntrnos ?? '')))
            ? trim((string) $mov->de_obser_ntrnos)
            : (!empty(trim((string) ($mov->de_obser_documento ?? '')))
                ? trim((string) $mov->de_obser_documento)
                : 'Sin observaciones registradas.');

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => (int) $mov->cdgo_trmtes_ntrnos,
                'numero_tramite' => $numeroTram,
                'fecha' => $fecha,
                'hora' => $hora,
                'detalles' => $detalles,
                'adjuntado' => $mov->de_urls_agrdo ?: null,
            ]
        ]);
    }

    public function reemplazarFirmado(ReemplazarFirmadoRequest $request): \Illuminate\Http\JsonResponse
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Identificador de trámite inválido.'], 422);
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['status' => 'error', 'message' => 'Debe adjuntar un archivo PDF.'], 422);
        }

        $file = $request->file('archivo');
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o contexto de compañía no válido.'], 401);
        }

        try {
            $resultado = $this->reemplazarService->reemplazar($id, $file, $contexto, (string) $request->ip());
            return response()->json($resultado);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'No fue posible reemplazar el documento firmado.');
        }
    }

    public function comprobantesPago(Request $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido.'], 401);
        }

        if (!$this->policy->verContabilidad($contexto)) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado para consultar comprobantes de pago.'], 403);
        }

        try {
            $comprobantes = $this->repository->obtenerComprobantesPago([], $contexto);

            return response()->json([
                'status' => 'success',
                'data' => $comprobantes
            ]);
        } catch (\Throwable $e) {
            Log::warning('Consulta comprobantes de pago: ' . $e->getMessage());
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }
    }


    /**
     * SPEC-03: Datos de apoyo para los modales transaccionales (Asignar, Derivar)
     */
    public function datosTransaccionales(Request $request): \Illuminate\Http\JsonResponse
    {
        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        $cargoInfo = $this->repository->obtenerCargoPorId($cargoId);
        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);

        $datos = $this->transaccionalService->obtenerDatosTransaccionales($cargoId, $areaId);
        return response()->json(['status' => 'success', 'data' => $datos]);
    }

    /**
     * SPEC-03: Consultar siguiente número correlativo al seleccionar tipo de documento interno
     */
    public function correlativoInterno(CorrelativoTramiteInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        $cargoInfo = $this->repository->obtenerCargoPorId($cargoId);
        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);
        $cdgoTpoTrmte = (int) ($request->validated('cdgo_tpo_trmte') ?? $request->validated('id_tipo_documento') ?? $request->input('cdgo_tpo_trmte', 0));

        try {
            $datos = $this->transaccionalService->obtenerCorrelativoInterno($cdgoTpoTrmte, $cargoId, $areaId);
            return response()->json(['status' => 'success', 'data' => $datos]);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al obtener correlativo interno.');
        }
    }

    /**
     * SPEC-03 - FLUJO 2: ASIGNAR TRÁMITE INTERNAMENTE (segi_asignar.php legacy)
     */
    public function asignar(AsignarTramiteInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido.'], 401);
        }
        if (!$contexto->cargoAsignadoValido()) {
            return response()->json(['status' => 'error', 'message' => 'El cargo no se encuentra asignado al usuario activo.'], 403);
        }

        $ids = (array) ($request->validated('ids') ?? $request->input('ids', []));
        $cargoDestino = (int) ($request->validated('id_cargo_destino') ?? $request->validated('cdgo_crgo_en') ?? $request->input('cdgo_crgo_en', 0));
        $observaciones = (string) ($request->validated('observacion') ?? $request->validated('observaciones') ?? $request->input('observaciones', ''));
        $fecha = $request->validated('fecha') ? Carbon::parse($request->validated('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

        $docInternoParams = [
            'crear_tramite_interno' => $request->boolean('crear_tramite_interno'),
            'cdgo_tpo_trmte' => (int) ($request->validated('cdgo_tpo_trmte') ?? $request->input('cdgo_tpo_trmte', 0)),
            'txt_nu_smlla_intrno' => (string) ($request->validated('txt_nu_smlla_intrno') ?? $request->input('txt_nu_smlla_intrno', '')),
            'in_envo_fsco' => $request->boolean('in_envo_fsco'),
        ];

        $archivos = [
            'fle_adjunto1' => $request->file('fle_adjunto1') ?? $request->file('archivos.0'),
            'fle_adjunto2' => $request->file('fle_adjunto2') ?? $request->file('archivos.1'),
            'fle_adjunto3' => $request->file('fle_adjunto3') ?? $request->file('archivos.2'),
            'fle_adjunto4' => $request->file('fle_adjunto4') ?? $request->file('archivos.3'),
        ];

        try {
            $result = $this->transaccionalService->ejecutarAsignar(
                $ids, $cargoDestino, $observaciones, $fecha, $docInternoParams,
                $archivos, $contexto, (string) $request->ip()
            );
            return response()->json($result);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (TransicionTramiteInvalidaException | ConflictoConcurrenteTramiteException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (DestinoTramiteInvalidoException | InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al asignar el trámite.');
        }
    }

    /**
     * SPEC-03 - FLUJO 1: DERIVAR TRÁMITE A OTRAS ÁREAS (segi_reenviar.php legacy)
     */
    public function derivar(DerivarTramiteInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido.'], 401);
        }
        if (!$contexto->cargoAsignadoValido()) {
            return response()->json(['status' => 'error', 'message' => 'El cargo no se encuentra asignado al usuario activo.'], 403);
        }

        $ids = (array) ($request->validated('ids') ?? $request->input('ids', []));
        $areasDestino = (array) ($request->validated('areas_destino') ?? $request->validated('sel_areas') ?? $request->input('sel_areas', []));
        $observaciones = (string) ($request->validated('observacion') ?? $request->validated('observaciones') ?? $request->input('observaciones', ''));
        $fecha = $request->validated('fecha') ? Carbon::parse($request->validated('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

        $docInternoParams = [
            'crear_tramite_interno' => $request->boolean('crear_tramite_interno'),
            'cdgo_tpo_trmte' => (int) ($request->validated('cdgo_tpo_trmte') ?? $request->input('cdgo_tpo_trmte', 0)),
            'txt_nu_smlla_intrno' => (string) ($request->validated('txt_nu_smlla_intrno') ?? $request->input('txt_nu_smlla_intrno', '')),
            'in_envo_fsco' => $request->boolean('in_envo_fsco'),
        ];

        $archivos = [
            'fle_adjunto1' => $request->file('fle_adjunto1') ?? $request->file('archivos.0'),
            'fle_adjunto2' => $request->file('fle_adjunto2') ?? $request->file('archivos.1'),
            'fle_adjunto3' => $request->file('fle_adjunto3') ?? $request->file('archivos.2'),
            'fle_adjunto4' => $request->file('fle_adjunto4') ?? $request->file('archivos.3'),
        ];

        try {
            $result = $this->transaccionalService->ejecutarDerivar(
                $ids, $areasDestino, $observaciones, $fecha, $docInternoParams,
                $archivos, $contexto, (string) $request->ip()
            );
            return response()->json($result);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (TransicionTramiteInvalidaException | ConflictoConcurrenteTramiteException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (DestinoTramiteInvalidoException | InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al derivar el trámite.');
        }
    }

    /**
     * SPEC-03 - FLUJO 3: FINALIZAR TRÁMITE (segi_finaliza.php legacy)
     */
    public function finalizar(FinalizarTramiteInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido.'], 401);
        }
        if (!$contexto->cargoAsignadoValido()) {
            return response()->json(['status' => 'error', 'message' => 'El cargo no se encuentra asignado al usuario activo.'], 403);
        }

        $ids = (array) ($request->validated('ids') ?? $request->input('ids', []));
        $observaciones = (string) ($request->validated('observacion') ?? $request->validated('observaciones') ?? $request->input('observaciones', ''));
        $fecha = $request->validated('fecha') ? Carbon::parse($request->validated('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

        $archivos = [
            'fle_adjunto1' => $request->file('fle_adjunto1') ?? $request->file('archivos.0'),
            'fle_adjunto2' => $request->file('fle_adjunto2') ?? $request->file('archivos.1'),
            'fle_adjunto3' => $request->file('fle_adjunto3') ?? $request->file('archivos.2'),
            'fle_adjunto4' => $request->file('fle_adjunto4') ?? $request->file('archivos.3'),
        ];

        try {
            $result = $this->transaccionalService->ejecutarFinalizar(
                $ids, $observaciones, $fecha, $archivos, $contexto, (string) $request->ip()
            );
            return response()->json($result);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (TransicionTramiteInvalidaException | ConflictoConcurrenteTramiteException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (DestinoTramiteInvalidoException | InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al finalizar el trámite.');
        }
    }

    /**
     * SPEC-03 - FLUJO 4: DEVOLVER TRÁMITE (segi_regresar.php legacy)
     */
    public function devolver(DevolverTramiteInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido.'], 401);
        }
        if (!$contexto->cargoAsignadoValido()) {
            return response()->json(['status' => 'error', 'message' => 'El cargo no se encuentra asignado al usuario activo.'], 403);
        }

        $ids = (array) ($request->validated('ids') ?? $request->input('ids', []));
        $observaciones = (string) ($request->validated('observacion') ?? $request->validated('observaciones') ?? $request->validated('motivo') ?? $request->input('observaciones', ''));
        $fecha = $request->validated('fecha') ? Carbon::parse($request->validated('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

        try {
            $result = $this->transaccionalService->ejecutarDevolver(
                $ids, $observaciones, $fecha, $contexto, (string) $request->ip()
            );
            return response()->json($result);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (TransicionTramiteInvalidaException | ConflictoConcurrenteTramiteException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (DestinoTramiteInvalidoException | InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al devolver el trámite.');
        }
    }

    /**
     * SPEC-03 - FLUJO 5: REITERAR TRÁMITE (segi_reiterar.php legacy)
     */
    public function reiterar(ReiterarTramiteInternoRequest $request): \Illuminate\Http\JsonResponse
    {
        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o cargo no válido.'], 401);
        }
        if (!$contexto->cargoAsignadoValido()) {
            return response()->json(['status' => 'error', 'message' => 'El cargo no se encuentra asignado al usuario activo.'], 403);
        }

        $ids = (array) ($request->validated('ids') ?? $request->input('ids', []));
        $observaciones = (string) ($request->validated('observacion') ?? $request->validated('observaciones') ?? $request->input('observaciones', ''));
        $fecha = $request->validated('fecha') ? Carbon::parse($request->validated('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

        $archivos = [
            'fle_adjunto1' => $request->file('fle_adjunto1') ?? $request->file('archivos.0'),
            'fle_adjunto2' => $request->file('fle_adjunto2') ?? $request->file('archivos.1'),
            'fle_adjunto3' => $request->file('fle_adjunto3') ?? $request->file('archivos.2'),
            'fle_adjunto4' => $request->file('fle_adjunto4') ?? $request->file('archivos.3'),
        ];

        try {
            $result = $this->transaccionalService->ejecutarReiterar(
                $ids, $observaciones, $fecha, $archivos, $contexto, (string) $request->ip()
            );
            return response()->json($result);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (TransicionTramiteInvalidaException | ConflictoConcurrenteTramiteException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (DestinoTramiteInvalidoException | InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al reiterar el trámite.');
        }
    }

    /**
     * SPEC-03 - MÓDULO COMPLEMENTARIO: HOJA DE RUTA (segi_hoja_ruta.php legacy)
     */
    public function hojaRuta(Request $request, $id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            abort(404, 'Trámite no encontrado');
        }

        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            abort(401, 'No autenticado');
        }

        $tramite = $this->repository->obtenerDocumentoParaHojaRuta($id, $contexto);
        if (!$tramite) {
            abort(404, 'Trámite no encontrado');
        }

        if (empty($tramite->es_autorizado)) {
            abort(403, 'No autorizado para consultar la hoja de ruta de este trámite.');
        }

        try {
            $data = $this->transaccionalService->obtenerDatosHojaRuta($id);
            return view('mod_gene.mod_tram_inter.hoja-ruta', $data);
        } catch (\Throwable $e) {
            abort(404, 'Trámite no encontrado');
        }
    }

    /**
     * SPEC-04 (ERR-03 / Tarea 3): Descarga de lote de adjuntos en Enviados (segi_descargar.php legacy)
     */
    public function descargarCompleto($id, Request $request)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Identificador de trámite inválido.'], 422);
        }

        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o contexto de compañía no válido.'], 401);
        }

        try {
            $res = $this->consolidadoService->generarConsolidado($id, $contexto, (string) $request->ip());

            return response()->download($res['zip_path'], $res['zip_name'])->deleteFileAfterSend(true);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'No fue posible generar el consolidado del expediente.', 404);
        }
    }

    public function descargarLote(DescargarLoteTramiteInternoRequest $request)
    {
        $ids = (array) ($request->validated('ids') ?? $request->input('ids', []));
        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'No se seleccionaron trámites para descargar.'], 400);
        }

        $contexto = $this->obtenerContextoInterno($request);
        if (!$contexto) {
            return response()->json(['status' => 'error', 'message' => 'Sesión o contexto de compañía no válido.'], 401);
        }

        try {
            $res = $this->descargaLoteService->generarLoteZip($ids, $contexto, (string) $request->ip());

            return response()->download($res['zip_path'], $res['zip_name'])->deleteFileAfterSend(true);
        } catch (OperacionTramiteNoAutorizadaException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (TramiteInternoNoEncontradoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'No fue posible generar la descarga por lote.', 404);
        }
    }

    /**
     * SPEC-04 (Tarea 5): Generar reporte impreso oficial de la bandeja activa (impr_bandeja.php legacy)
     */
    public function imprimirBandeja(Request $request)
    {
        $contexto = $this->obtenerContextoInterno($request);
        $cabecera = $contexto ? $this->repository->obtenerCabeceraImpresion($contexto) : ['cargo' => null, 'cargo_nombre' => '', 'area_nombre' => 'MUNICIPALIDAD DE PUENTE PIEDRA'];
        $areaNombre = $cabecera['area_nombre'];
        $cargoNombre = $cabecera['cargo_nombre'];

        $bandeja = (string) $request->input('bandeja', 'entrada');
        $criterio = $request->input('criterio');
        $descripcion = trim((string) $request->input('descripcion', ''));
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $reporte = $request->input('reporte');
        $faltantes = (int) $request->input('faltantes', 0) === 1 || $reporte === 'sin_finalizar';

        if ($contexto) {
            if ($bandeja === 'contabilidad' && !$this->policy->verContabilidad($contexto)) {
                abort(403, 'No autorizado para acceder a la bandeja de contabilidad.');
            }

            $filtros = [
                'criterio'    => $criterio,
                'descripcion' => $descripcion,
                'desde'       => $desde,
                'hasta'       => $hasta,
                'faltantes'   => $faltantes,
            ];
            $resultado = $this->repository->listarBandeja($bandeja, $contexto, $filtros, 1, 50);
            $data = $resultado['data'];
        } else {
            $data = [];
        }

        $titulos = [
            'entrada' => 'BANDEJA DE ENTRADA',
            'enviados' => 'BANDEJA DE ENVIADOS',
            'todos' => 'BANDEJA DE TODOS',
            'recibidos' => 'BANDEJA DE RECIBIDOS',
            'salida' => 'BANDEJA DE SALIDA',
            'finalizados' => 'BANDEJA DE FINALIZADOS',
            'sin_finalizar' => 'BANDEJA DE SIN FINALIZAR',
            'contabilidad' => 'BANDEJA DE COMPROBANTES / CONTABILIDAD'
        ];

        $tituloBandeja = $titulos[$bandeja] ?? strtoupper($bandeja);

        return view('mod_gene.mod_tram_inter.reporte-bandeja', [
            'bandeja' => $bandeja,
            'tituloBandeja' => $tituloBandeja,
            'areaNombre' => $areaNombre,
            'cargoNombre' => $cargoNombre,
            'usuario' => Auth::user()->no_crto ?? (Auth::user()->cdgo_lgin ?? 'USUARIO'),
            'fechaImpresion' => now()->format('d/m/Y H:i:s'),
            'items' => $data
        ]);
    }

}
