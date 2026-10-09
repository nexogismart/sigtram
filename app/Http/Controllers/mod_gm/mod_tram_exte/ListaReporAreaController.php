<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListaReporAreaController extends Controller
{
    private string $schema;

    private const CAMPOS = [
        'p_dto_trmtes.nu_expe_todo',
        'p_usrios.nu_docu',
        'p_usrios.no_crto',
        'a_docmntos_trmtes.de_docmnto_trmte',
        'h_usrios_ntral.no_usrio',
        'p_dto_trmtes.de_obse_todo',
    ];

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Reporte de Expedientes'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Reporte de Expedientes';
        }

        $cargoId = (int) session('sigtram_cargo_id', 0);

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
            'mostrarAutomaticos' => $cargoId === 14,
        ]);
    }

    public function buscar(Request $request)
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay un cargo activo en la sesión.',
            ], 422);
        }

        $bandeja = (string) $request->input('bandeja', '1');
        if ($bandeja === '4') {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
            ]);
        }

        $perPage = 37;
        $page = max(1, (int) $request->input('page', 1));
        $query = $this->queryBase($request, $cargoId, $bandeja);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $rows = $query
            ->orderByDesc('r.fe_rcbdo_trmte')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $rows->map(fn ($row) => $this->mapRow($row))->values(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
        ]);
    }

    private function queryBase(Request $request, int $cargoId, string $bandeja)
    {
        $s = $this->schema;
        $company = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));

        $query = DB::table($s . 'r_trmtes_dcmntos as r')
            ->leftJoin($s . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($s . 'h_usrios_ntral as h_usrios_ntral', 'l.cdgo_usrios', '=', 'h_usrios_ntral.cdgo_usrios')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.nu_foli_trmte',
                'r.fe_rcbdo_trmte',
                'p.in_sile_admi',
                'p_usrios.no_crto',
                'p.de_obse_trmte',
                DB::raw('COALESCE(h_usrios_ntral.no_usrio, l.de_lgin) as usuario'),
                'a_docmntos_trmtes.de_docmnto_trmte',
            ]);

        if ($company > 0) {
            $query->where('p.cdgo_cmpnia', $company);
        }

        if ($bandeja === '2') {
            $query->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', '<>', $cargoId);
        } elseif ($bandeja === '3') {
            $query->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', $cargoId)
                ->where('a_estdos_trmtes.cdgo_estdo_trmte', '3');
        } else {
            $query->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', $cargoId)
                ->where('a_estdos_trmtes.cdgo_estdo_trmte', '2');
        }

        $modo = (string) $request->input('modo', 'generico');
        if ($modo === 'fechas') {
            $this->aplicarRango($query, $request->input('desde'), $request->input('hasta'));
        } else {
            $palabra = trim((string) $request->input('palabra', ''));
            $campo = (string) $request->input('campo', 'p_dto_trmtes.nu_expe_todo');
            if ($palabra !== '') {
                if (! in_array($campo, self::CAMPOS, true)) {
                    $campo = 'p_dto_trmtes.nu_expe_todo';
                }
                $col = $this->aliasCampo($campo);
                $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $palabra . '%']);
            }
            $this->aplicarRango($query, $request->input('fecha'), $request->input('fecha'));
        }

        return $query;
    }

    private function aplicarRango($query, $desde, $hasta): void
    {
        $d = $this->normalizarFecha($desde);
        $h = $this->normalizarFecha($hasta);
        if ($d && $h) {
            $query->whereRaw('r.fe_rcbdo_trmte >= ? AND r.fe_rcbdo_trmte <= (CAST(? AS date) + 1)', [$d, $h]);
        }
    }

    private function aliasCampo(string $campo): string
    {
        return match ($campo) {
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.nu_docu' => 'p_usrios.nu_docu',
            'p_usrios.no_crto' => 'p_usrios.no_crto',
            'a_docmntos_trmtes.de_docmnto_trmte' => 'a_docmntos_trmtes.de_docmnto_trmte',
            'h_usrios_ntral.no_usrio' => 'h_usrios_ntral.no_usrio',
            'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
            default => 'p.nu_expe_todo',
        };
    }

    private function mapRow(object $row): array
    {
        $nombre = (string) ($row->no_crto ?? '');
        $obs = trim((string) ($row->de_obse_trmte ?? ''));
        $desc = $obs !== '' ? $obs : (string) ($row->de_docmnto_trmte ?? '');

        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'expediente' => $this->formatoExpediente($row->nu_expe_todo),
            'folios' => $row->nu_foli_trmte,
            'fecha' => $row->fe_rcbdo_trmte ? Carbon::parse($row->fe_rcbdo_trmte)->format('d/m') : '',
            'fecha_completa' => $row->fe_rcbdo_trmte ? Carbon::parse($row->fe_rcbdo_trmte)->format('d/m/Y H:i') : '',
            'silencio' => ((string) $row->in_sile_admi === '1') ? 'Si' : 'No',
            'solicitante' => $nombre,
            'solicitante_corto' => mb_strlen($nombre, 'UTF-8') > 28 ? mb_substr($nombre, 0, 28, 'UTF-8') : $nombre,
            'descripcion' => $desc,
            'usuario' => $row->usuario,
        ];
    }

    private function formatoExpediente(?string $numero): string
    {
        if (! $numero) {
            return '';
        }
        $parts = explode('-', $numero);
        if (count($parts) >= 3) {
            return $parts[0] . '-' . str_pad($parts[1] . '-' . substr($parts[2], 2, 10), 8, '0', STR_PAD_LEFT);
        }

        return $numero;
    }

    private function normalizarFecha($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
                return Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d');
            }

            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
