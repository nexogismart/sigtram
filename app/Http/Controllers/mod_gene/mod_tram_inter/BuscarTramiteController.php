<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface;
use App\Http\Controllers\Controller;
use App\Services\mod_gene\mod_tram_inter\BuscarTramiteService;
use App\Services\mod_gene\mod_tram_inter\DescargaSeguraTramiteInternoService;
use App\Support\SessionKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BuscarTramiteController extends Controller
{
    public function __construct(
        private readonly DescargaSeguraTramiteInternoService $descargaSeguraService,
        private ?BuscarTramiteService $buscarTramiteService = null,
        private ?ContextoUsuarioAutenticadoServiceInterface $contextoService = null
    ) {
        $this->buscarTramiteService = $this->buscarTramiteService ?? app(BuscarTramiteService::class);
        $this->contextoService = $this->contextoService ?? app(ContextoUsuarioAutenticadoServiceInterface::class);
    }

    public function index(Request $request)
    {
        $request->validate([
            'sel_cdgo_area' => ['nullable', 'integer'],
            'sel_cdgo_area_en' => ['nullable', 'integer'],
            'sel_cdgo_tpo_trmte' => ['nullable', 'integer'],
            'txt_nu_tram_todo' => ['nullable', 'string', 'max:100', 'regex:/^[0-9\-]*$/u'],
            'T1' => ['nullable', 'date_format:Y-m-d'],
            'T2' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = Auth::user();
        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        $idLogin = (int) ($user->cdgo_lgin ?? 0);

        $areaUsuario = $this->buscarTramiteService->obtenerAreaUsuario($cargoId);
        // Mismos IDs admin que lista_tramite_todo.php (usIds 1 y 72)
        $isAdmin = ($idLogin === 1 || $idLogin === 72);

        // Área de filtro: por defecto la del usuario (como el antiguo)
        $cdgoArea = $request->filled('sel_cdgo_area')
            ? (int) $request->input('sel_cdgo_area')
            : $areaUsuario;

        if (! $isAdmin) {
            $cdgoArea = $areaUsuario;
        }

        $areas = $this->buscarTramiteService->obtenerAreas($isAdmin, $areaUsuario);
        $destinoAreas = $this->buscarTramiteService->obtenerDestinoAreas();
        $tiposDocumento = $this->buscarTramiteService->obtenerTiposDocumento($cdgoArea > 0 ? $cdgoArea : null);

        $filtros = [
            'cdgo_area' => $cdgoArea,
            'sel_cdgo_area_en' => $request->input('sel_cdgo_area_en'),
            'sel_cdgo_tpo_trmte' => $request->input('sel_cdgo_tpo_trmte'),
            'txt_nu_tram_todo' => $request->input('txt_nu_tram_todo'),
            'T1' => $request->input('T1'),
            'T2' => $request->input('T2'),
        ];
        $page = (int) $request->input('page', 1);

        $tramites = $this->buscarTramiteService->buscarTramites($filtros, 36, $page);

        foreach ($tramites->items() as $item) {
            $item->fe_ingrso_fmt = $this->formatFeIngrsoLocal($item->fe_ingrso);
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'data' => array_values($tramites->items()),
                'total' => $tramites->total(),
                'current_page' => $tramites->currentPage(),
                'last_page' => $tramites->lastPage(),
            ]);
        }

        return view('mod_gene.mod_tram_inter.buscar-tramite', [
            'areas' => $areas,
            'destinoAreas' => $destinoAreas,
            'tiposDocumento' => $tiposDocumento,
            'tramites' => $tramites,
            'filtros' => [
                'sel_cdgo_area' => $cdgoArea,
                'sel_cdgo_area_en' => $request->input('sel_cdgo_area_en', ''),
                'sel_cdgo_tpo_trmte' => $request->input('sel_cdgo_tpo_trmte', '0'),
                'txt_nu_tram_todo' => $request->input('txt_nu_tram_todo', ''),
                'T1' => $request->input('T1', ''),
                'T2' => $request->input('T2', ''),
            ],
            'isAdmin' => $isAdmin,
            'areaUsuario' => $areaUsuario,
        ]);
    }

    /**
     * Tipos de documento por área (como ajax_semilla_interno / tipo_documento_area).
     */
    public function tiposPorArea(Request $request): JsonResponse
    {
        $area = (int) $request->query('area', 0);
        $tipos = $this->buscarTramiteService->obtenerTiposDocumento($area > 0 ? $area : null);

        return response()->json(['status' => 'success', 'data' => $tipos]);
    }

    // Detalle del Trámite (ojo / voucher)
    public function detalle(Request $request): JsonResponse
    {
        $id = (int) $request->id;
        $tramite = $this->buscarTramiteService->obtenerDetalleTramite($id);

        if (! $tramite) {
            return response()->json(['success' => false, 'message' => 'Trámite no encontrado.']);
        }

        $tramite->fe_ingrso_fmt = $this->formatFeIngrsoLocal($tramite->fe_ingrso);

        return response()->json([
            'success' => true,
            'data' => $tramite,
        ]);
    }

    /** Historial de movimientos (equivalente segi_tramite popup). */
    public function historial(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID no proporcionado'], 400);
        }

        $data = $this->buscarTramiteService->obtenerHistorialMovimientos($id);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function formatFeIngrsoLocal($feIngrso): string
    {
        if (! $feIngrso) {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($feIngrso)->timezone('America/Lima')->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $feIngrso;
        }
    }

    /**
     * Descarga segura de adjuntos de trámite interno (RTI-C01).
     * Recibe exclusivamente identificador canónico del recurso (id/documento_id o movimiento_id).
     * Resuelve contexto de funcionario, autoriza mediante policy, valida contención en storage privado
     */
    public function descargar(Request $request): BinaryFileResponse
    {
        // 1. Detección temprana y rechazo estricto: prohibido enviar path o nom_archivo
        if ($request->has('path') || $request->has('nom_archivo')) {
            abort(400, 'Parámetro de ruta o intento de Path Traversal (path / nom_archivo) estrictamente prohibido.');
        }

        $movimientoId = (int) $request->input('movimiento_id', 0);
        $documentoId = (int) ($request->input('id') ?: $request->input('doc_id') ?: $request->input('documento_id') ?: 0);

        if ($movimientoId <= 0 && $documentoId <= 0) {
            abort(400, 'Identificador canónico de documento o movimiento requerido.');
        }

        $contexto = $this->contextoService->obtenerContextoActivo();
        if ($contexto === null) {
            abort(403, 'Sesión o contexto de funcionario no válido.');
        }

        $ip = $request->ip() ?: '127.0.0.1';

        try {
            if ($movimientoId > 0) {
                $resultado = $this->descargaSeguraService->descargarPorMovimiento($movimientoId, $contexto, $ip);
            } else {
                $resultado = $this->descargaSeguraService->descargarPorDocumento($documentoId, $contexto, $ip);
            }

            return response()->download($resultado['ruta_fisica'], $resultado['nombre_descarga']);
        } catch (\InvalidArgumentException $e) {
            abort(400, 'Solicitud de descarga no válida.');
        } catch (\RuntimeException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'permiso') || str_contains($msg, 'no tiene permisos') || str_contains($msg, 'otra compañía') || str_contains($msg, 'no autorizado')) {
                abort(403, 'No autorizado para descargar este documento.');
            }
            abort(404, 'Documento no encontrado o no disponible.');
        } catch (\Throwable $e) {
            abort(500, 'Error interno procesando la descarga.');
        }
    }
}
