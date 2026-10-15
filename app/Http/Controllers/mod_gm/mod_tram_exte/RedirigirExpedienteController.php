<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\mod_gm\mod_tram_exte\ReenviarExpedienteRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Support\SessionKeys;

class RedirigirExpedienteController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    /** @var array<string, bool>|null */
    private ?array $cacheFestivos = null;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu->dc_rle ?? 'Redirigir'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Redirigir';
        }

        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        if ($cmpnia <= 0) {
            abort(403, 'No tiene una compañía activa válida en la sesión.');
        }

        $gerencias = $this->tramiteRepo->obtenerGerenciasParaRedirigir($cmpnia);

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
            'gerencias' => $gerencias,
            'fechaActual' => now()->format('d/m/Y H:i'),
        ]);
    }

    public function buscar(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $page = max(1, (int) $request->input('page', 1));
        if ($perPage < 1) {
            $perPage = 15;
        }

        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        $res = $this->tramiteRepo->buscarExpedientesParaRedirigir($request->all(), $page, $perPage, $cmpnia);

        $items = collect($res['rows'])->map(function ($row) {
            $silen = $row->nu_tiem_sile ?? null;
            $tieneDoc = $row->cdgo_docmnto_trmte !== null && (string) $row->cdgo_docmnto_trmte !== '';
            $resta = $tieneDoc ? $this->diasSilencioRestantes($row->fe_ingr_trmte, $silen) : null;
            $alerta = $resta !== null && $resta <= 2 && (string) $silen !== '0';

            $nombre = (string) ($row->no_crto ?? '');
            $obs = (string) ($row->de_obse_trmte ?? '');
            $area = (string) ($row->dc_area ?? '');

            $fechaEntrada = $row->fe_rcbdo_trmte
                ? Carbon::parse($row->fe_rcbdo_trmte)->format('d/m/Y')
                : '';

            return [
                'cdgo_dto_trmte' => (int) $row->cdgo_dto_trmte,
                'cdgo_trmte_dcmnto' => (int) $row->cdgo_trmte_dcmnto,
                'nu_expe_todo' => $row->nu_expe_todo,
                'solicitante' => $nombre,
                'solicitante_corto' => $this->cortar($nombre, 22),
                'ubicacion' => ((string) $row->in_encu_tram === '0') ? 'Ex' : 'In',
                'fecha' => $fechaEntrada,
                'estado' => $row->de_estdo_trmte,
                'area' => $area,
                'area_corta' => $this->cortar($area, 9),
                'observaciones' => $obs,
                'observaciones_corta' => $this->cortar($obs, 30),
                'usuario' => $row->de_lgin,
                'alerta' => $alerta,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'current_page' => $res['current_page'],
            'last_page' => $res['last_page'],
            'total' => $res['total'],
        ]);
    }

    public function derivar(ReenviarExpedienteRequest $request)
    {
        $ids = array_values(array_filter((array) ($request->input('movimiento_ids') ?? $request->input('ids', [])), fn ($id) => (int) $id > 0));
        if ($ids === []) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar al menos un Documento',
            ], 422);
        }

        $gerenciaId = (int) $request->input('cdgo_gerencia');
        $subId = (int) $request->input('cdgo_subgerencia', 0);
        $usarSub = $request->boolean('usar_subgerencia');
        $observacion = trim((string) $request->input('observacion', 'Sin novedades para reenviar'));
        $userId = (int) (Auth::id() ?? session(SessionKeys::LOGIN_ID, 0));
        $ip = $request->ip() ?: '';
        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);

        $areaDestino = ($usarSub && $subId > 0) ? $subId : $gerenciaId;

        try {
            $this->tramiteRepo->derivarExpedientesRedirigir($ids, $areaDestino, $observacion, $userId, $ip, $cmpnia);

            return response()->json([
                'success' => true,
                'message' => 'Los expedientes fueron reedirigidos',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error redirigiendo expedientes', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Ocurrió un error al redirigir.',
            ], 500);
        }
    }

    public function anular(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'motivo' => 'required|string',
            'expediente' => 'nullable|string',
        ]);

        $id = (int) $request->input('id');
        $motivo = trim((string) $request->input('motivo'));
        $userId = (int) (Auth::id() ?? session(SessionKeys::LOGIN_ID, 0));
        $ip = $request->ip() ?: '';
        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);

        if ($motivo === '') {
            return response()->json([
                'success' => false,
                'message' => 'Debe especificar el motivo',
            ], 422);
        }

        try {
            $this->tramiteRepo->anularExpedienteRedirigir($id, $motivo, $userId, $ip, $cmpnia);

            return response()->json([
                'success' => true,
                'message' => 'El trámite fue anulado',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error anulando expediente', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Error al anular.',
            ], 500);
        }
    }

    public function imprimir(Request $request)
    {
        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        if ($cmpnia <= 0) {
            abort(403, 'No tiene una compañía activa válida en la sesión.');
        }

        $rows = $this->tramiteRepo->obtenerExpedientesParaRedirigirImprimir($request->all(), $cmpnia);

        $areas = [];
        foreach ($rows as $row) {
            $areaId = (string) $row->cdgo_area;
            if (! isset($areas[$areaId])) {
                $areas[$areaId] = [
                    'de_area' => $row->de_area,
                    'expedientes' => [],
                    'simples' => [],
                ];
            }
            $item = [
                'expediente' => $row->nu_expe_todo,
                'folios' => $row->nu_foli_trmte,
                'usuario' => $row->de_lgin,
            ];
            if ((string) $row->cdgo_tpo_trmte === '17') {
                $areas[$areaId]['simples'][] = $item;
            } else {
                $areas[$areaId]['expedientes'][] = $item;
            }
        }

        return response()->json([
            'status' => 'success',
            'criterio' => (string) $request->input('palabra', ''),
            'fecha' => (string) $request->input('fecha', ''),
            'usuario' => Auth::user()->de_lgin ?? '',
            'cmpnia' => $cmpnia,
            'areas' => array_values($areas),
        ]);
    }

    public function detalle(Request $request)
    {
        $id = (int) $request->input('id');
        $dtoTrmte = $this->tramiteRepo->obtenerDetalleExpedienteRedirigir($id);

        if (! $dtoTrmte) {
            return response()->json(['success' => false, 'message' => 'Expediente no encontrado']);
        }

        return response()->json([
            'success' => true,
            'data' => $dtoTrmte,
        ]);
    }

    private function diasSilencioRestantes($fechaIngreso, $silen): ?int
    {
        if ($fechaIngreso === null || $silen === null || (string) $silen === '' || (string) $silen === '0') {
            return null;
        }

        $silen = (int) $silen;
        $fecha = Carbon::parse($fechaIngreso)->startOfDay();
        $hoy = Carbon::today();
        $findesemana = intdiv($silen, 5);
        $diaFeri = $silen + $findesemana;
        $deadline = $fecha->copy()->addDays($diaFeri);
        $nuFstvos = $this->contarFestivos($fecha->toDateString(), $deadline->toDateString());
        $deadline = $fecha->copy()->addDays($diaFeri + $nuFstvos);

        return (int) round(($deadline->getTimestamp() - $hoy->getTimestamp()) / 86400);
    }

    private function contarFestivos(string $desde, string $hasta): int
    {
        $festivos = $this->festivos();
        $n = 0;
        foreach ($festivos as $dia => $_) {
            if ($dia >= $desde && $dia <= $hasta) {
                $n++;
            }
        }

        return $n;
    }

    /** @return array<string, bool> */
    private function festivos(): array
    {
        if ($this->cacheFestivos !== null) {
            return $this->cacheFestivos;
        }

        $this->cacheFestivos = $this->tramiteRepo->obtenerDiasFestivos();

        return $this->cacheFestivos;
    }

    private function cortar(string $texto, int $largo): string
    {
        if (mb_strlen($texto, 'UTF-8') <= $largo) {
            return $texto;
        }

        return mb_substr($texto, 0, $largo, 'UTF-8');
    }
}
