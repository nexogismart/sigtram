<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificarTramiteRequest;
use App\Services\NotificarPlataformaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificarPlataformaController extends Controller
{
    public function __construct(
        private NotificarPlataformaService $service
    ) {}

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->ajax()) {
                return response()->json(['status' => 'error', 'message' => 'No autorizado'], 401);
            }

            return redirect()->route('login');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return $this->tabla($request);
        }

        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Notificar Plataforma'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Notificar Plataforma';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
        ]);
    }

    public function tabla(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado'], 401);
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.').'.';
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        $query = DB::table($schema.'r_trmtes_dcmntos as r')
            ->join($schema.'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->leftJoin($schema.'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->leftJoin($schema.'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($schema.'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->leftJoin($schema.'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($schema.'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('r.in_esta', '1')
            ->whereIn('r.cdgo_estdo_trmte', [3, 26])
            ->where('p.cdgo_cmpnia', $cmpnia);

        $modo = (string) $request->input('modo', 'generico');
        $criterio = trim((string) $request->input('criterio', ''));
        $campo = (string) $request->input('campo', 'p.nu_expe_todo');
        $desde = trim((string) $request->input('desde', ''));
        $hasta = trim((string) $request->input('hasta', ''));

        $campos = [
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.no_crto' => 'u.no_crto',
            'u.no_crto' => 'u.no_crto',
            'a_tpo_trmtes.de_tpo_trmte' => 't.de_tpo_trmte',
            't.de_tpo_trmte' => 't.de_tpo_trmte',
        ];

        if ($modo === 'fechas') {
            if ($desde !== '') {
                try {
                    $f = Carbon::createFromFormat('Y-m-d', $desde)->startOfDay();
                    $query->where('p.fe_ingr_trmte', '>=', $f);
                } catch (\Exception $e) {
                    // ignore
                }
            }
            if ($hasta !== '') {
                try {
                    $f = Carbon::createFromFormat('Y-m-d', $hasta)->endOfDay();
                    $query->where('p.fe_ingr_trmte', '<=', $f);
                } catch (\Exception $e) {
                    // ignore
                }
            }
        } elseif ($criterio !== '' && isset($campos[$campo])) {
            $col = $campos[$campo];
            $query->whereRaw("UPPER({$col}::text) LIKE UPPER(?)", [$criterio.'%']);
        }

        $perPage = (int) $request->input('per_page', 20);
        if (! in_array($perPage, [10, 15, 20, 25, 50, 100], true)) {
            $perPage = 20;
        }
        $page = max(1, (int) $request->input('page', 1));

        $expedientes = $query
            ->select(
                'r.cdgo_trmte_dcmnto',
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.in_sile_admi',
                'p.nu_foli_trmte',
                'p.fe_ingr_trmte',
                'u.no_crto',
                't.de_tpo_trmte',
                'a.dc_area',
                'a.de_area',
                'e.dc_estdo_trmte',
                'e.cdgo_estdo_trmte',
                'r.de_urls_imag'
            )
            ->orderByDesc('p.cdgo_dto_trmte')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($expedientes->items())->map(function ($row) {
            $row->nu_expe_fmt = $this->formatExpediente((string) ($row->nu_expe_todo ?? ''));
            $row->silencio = ((string) $row->in_sile_admi === '1') ? 'Sí' : 'No';
            $row->tipo_corto = mb_substr((string) ($row->de_tpo_trmte ?? ''), 0, 8);

            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'total' => $expedientes->total(),
            'current_page' => $expedientes->currentPage(),
            'last_page' => $expedientes->lastPage(),
        ]);
    }

    public function notificar(NotificarTramiteRequest $request, $id)
    {
        try {
            $pathEvidencia = $this->service->notificar(
                (int) $id,
                (string) $request->input('fecha'),
                $request->input('observaciones'),
                $request->file('documento'),
                (string) $request->ip()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'El expediente fue notificado exitosamente.',
                'file' => $pathEvidencia ? asset('storage/'.$pathEvidencia) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatExpediente(string $nu): string
    {
        $parts = explode('-', $nu);
        if (count($parts) < 3) {
            return $nu;
        }

        $mid = ($parts[1] ?? '').'-'.substr((string) ($parts[2] ?? ''), 2, 10);

        return ($parts[0] ?? '').'-'.str_pad($mid, 9, '0', STR_PAD_LEFT);
    }
}
