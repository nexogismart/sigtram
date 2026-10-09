<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListaExpediTodoController extends Controller
{
    private string $schema;

    private const CAMPOS = [
        'p_dto_trmtes.nu_expe_todo',
        'p_usrios.nu_tele',
        'p_usrios.nu_docu',
        'p_usrios.no_crto',
        'a_areas.de_area',
        'a_tpo_trmtes.de_tpo_trmte',
        'a_estdos_trmtes.dc_estdo_trmte',
        'p_dto_trmtes.de_obse_todo',
    ];

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Lista de Expedientes'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Lista de Expedientes';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
        ]);
    }

    public function buscar(Request $request)
    {
        $perPage = 36;
        $page = max(1, (int) $request->input('page', 1));
        $query = $this->queryBase($request);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $rows = $query
            ->orderByDesc('p.cdgo_dto_trmte')
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

    public function imprimir(Request $request)
    {
        $rows = $this->queryBase($request)
            ->orderBy('p.cdgo_dto_trmte')
            ->limit(2000)
            ->get()
            ->map(fn ($row) => $this->mapRow($row))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'usuario' => Auth::user()->de_lgin ?? '',
            'fecha' => now('America/Lima')->format('d/m/Y'),
        ]);
    }

    private function queryBase(Request $request)
    {
        $s = $this->schema;
        $company = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));

        $query = DB::table($s . 'p_dto_trmtes as p')
            ->join($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->leftJoin($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_crgos as p_crgos', 'r.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_areas.de_area',
                'p.nu_foli_trmte',
                'a_estdos_trmtes.de_estdo_trmte',
                'p.in_sile_admi',
                'p.fe_ingr_trmte',
                'r.fe_rcbdo_trmte',
            ])
            ->where('r.in_esta', '1');

        if ($company > 0) {
            $query->where('p.cdgo_cmpnia', $company);
        }

        $modo = (string) $request->input('modo', 'generico');
        $palabra = trim((string) $request->input('palabra', ''));
        $fecha = $request->input('fecha');
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        if ($modo === 'fechas') {
            $this->aplicarRango($query, $desde, $hasta, false);
        } else {
            $this->aplicarCampo($query, $request->input('campo', 'p_dto_trmtes.nu_expe_todo'), $palabra);
            if ($fecha) {
                $this->aplicarRango($query, $fecha, $fecha, true);
            } elseif ($palabra === '') {
                $query->where('p.fe_ingr_trmte', '>=', Carbon::now('America/Lima')->subDays(60)->format('Y-m-d'));
            }
        }

        return $query;
    }

    private function aplicarRango($query, $desde, $hasta, bool $incluirDiaSiguiente): void
    {
        $d = $this->normalizarFecha($desde);
        $h = $this->normalizarFecha($hasta);
        if (! $d || ! $h) {
            return;
        }

        if ($incluirDiaSiguiente) {
            $query->whereRaw(
                "p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= (CAST(? AS date) + 1) + interval '23 hours 59 minutes 59 seconds'",
                [$d . ' 00:00:00', $h]
            );

            return;
        }

        $query->whereRaw(
            'p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= ?',
            [$d . ' 00:00:00', $h . ' 23:59:59']
        );
    }

    private function aplicarCampo($query, $campo, $valor): void
    {
        $campo = (string) $campo;
        if (! in_array($campo, self::CAMPOS, true)) {
            $campo = 'p_dto_trmtes.nu_expe_todo';
        }
        $valor = trim((string) $valor);
        if ($valor === '') {
            return;
        }

        $col = $this->aliasCampo($campo);
        $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $valor . '%']);
    }

    private function aliasCampo(string $campo): string
    {
        return match ($campo) {
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.nu_tele' => 'p_usrios.nu_tele',
            'p_usrios.nu_docu' => 'p_usrios.nu_docu',
            'p_usrios.no_crto' => 'p_usrios.no_crto',
            'a_areas.de_area' => 'a_areas.de_area',
            'a_tpo_trmtes.de_tpo_trmte' => 'a_tpo_trmtes.de_tpo_trmte',
            'a_estdos_trmtes.dc_estdo_trmte' => 'a_estdos_trmtes.dc_estdo_trmte',
            'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
            default => 'p.nu_expe_todo',
        };
    }

    private function mapRow(object $row): array
    {
        $estado = (string) ($row->de_estdo_trmte ?? '');
        $fin = (mb_strtoupper($estado, 'UTF-8') === 'FINALIZADO' && $row->fe_rcbdo_trmte)
            ? (string) $row->fe_rcbdo_trmte
            : Carbon::now('America/Lima')->format('Y-m-d H:i:s');

        $tipo = (string) ($row->de_tpo_trmte ?? '');

        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'expediente' => (string) ($row->nu_expe_todo ?? ''),
            'expediente_fmt' => $this->formatoExpediente($row->nu_expe_todo),
            'silencio' => ((string) $row->in_sile_admi === '1') ? 'Si' : 'No',
            'ta' => $this->tiempoEstimado((string) ($row->fe_ingr_trmte ?? ''), $fin),
            'oficina' => (string) ($row->de_area ?? ''),
            'folios' => $row->nu_foli_trmte,
            'estado' => $estado,
            'tipo' => $tipo,
            'tipo_corto' => $tipo !== '' ? mb_substr($tipo, 0, 1, 'UTF-8') : '',
            'fecha' => $row->fe_ingr_trmte ? Carbon::parse($row->fe_ingr_trmte)->format('d/m/Y') : '',
        ];
    }

    private function tiempoEstimado(string $comien, string $hoy): string
    {
        $inicio = strtotime($comien);
        $fin = strtotime($hoy);
        if ($inicio === false || $fin === false) {
            return '0s';
        }

        $s = $fin - $inicio;
        if ($s < 0) {
            $s = 0;
        }

        $d = intdiv($s, 86400);
        $s -= $d * 86400;
        $h = intdiv($s, 3600);
        $s -= $h * 3600;
        $m = intdiv($s, 60);
        $s -= $m * 60;
        $horas = ($d * 24) + $h;

        if ($d === 1) {
            return '1D';
        }
        if ($d >= 2) {
            return ($d + 1) . 'D';
        }
        if ($horas === 1) {
            return '1H';
        }
        if ($horas >= 2) {
            return $horas . 'H';
        }
        if ($m === 1) {
            return '1m';
        }
        if ($m >= 2) {
            return $m . 'm';
        }
        if ($s === 1) {
            return '1s';
        }
        if ($s >= 2) {
            return $s . 's';
        }

        return '0s';
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
