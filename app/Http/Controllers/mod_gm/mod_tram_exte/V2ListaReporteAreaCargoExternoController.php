<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Equivalente a v2_lista_reporte_area_cargo.php (módulo externos):
 * dashboard + analytics por cargo del área, sobre p_dto_trmtes / r_trmtes_dcmntos.
 */
class V2ListaReporteAreaCargoExternoController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = is_string($pathVista) && $pathVista !== ''
            ? $this->vistaDesdeMenu($pathVista, $opcionMenu)
            : 'mod_gm.mod_tram_exte.v2_lista_reporte_area_cargo';

        $usIdsArea = $this->areaUsuarioActual();
        $hoy = Carbon::now('America/Lima');

        $desdeStr = $request->input('txt_fe_inicio', $hoy->copy()->startOfMonth()->format('d/m/Y'));
        $hastaStr = $request->input('txt_fe_fin', $hoy->format('d/m/Y'));

        try {
            $fechaDesdeSql = Carbon::createFromFormat('d/m/Y', $desdeStr)->format('Y-m-d') . ' 00:01';
            $fechaHastaSql = Carbon::createFromFormat('d/m/Y', $hastaStr)->format('Y-m-d') . ' 23:59';
        } catch (\Exception $e) {
            $desdeStr = $hoy->copy()->startOfMonth()->format('d/m/Y');
            $hastaStr = $hoy->format('d/m/Y');
            $fechaDesdeSql = $hoy->copy()->startOfMonth()->format('Y-m-d') . ' 00:01';
            $fechaHastaSql = $hoy->format('Y-m-d') . ' 23:59';
        }

        $s = $this->schema;
        $evoData = $this->buildEvolution($usIdsArea, $fechaDesdeSql, $fechaHastaSql);

        $cargos = $usIdsArea > 0
            ? DB::table($s . 'p_crgos')
                ->where('in_esta', '1')
                ->where('cdgo_area', $usIdsArea)
                ->orderBy('de_crgo')
                ->get(['cdgo_crgo', 'de_crgo'])
            : collect();

        $rows_data = [];
        $sum_entregado = 0;
        $sum_recibido = 0;
        $sum_salida = 0;
        $sum_finalizado = 0;
        $sum_total_doc = 0;
        $sum_total_ges = 0;

        foreach ($cargos as $cargo) {
            $cdgo = (int) $cargo->cdgo_crgo;

            $con_entrada = $this->countEntrada($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_recibido = $this->countRecibidos($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_salida = $this->countSalida($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_finalizado = $this->countFinalizado($cdgo, $fechaDesdeSql, $fechaHastaSql);

            $total = $con_entrada + $con_recibido + $con_salida + $con_finalizado;
            $gestionados = $con_salida + $con_finalizado;
            $pendientes = $con_entrada + $con_recibido;

            $porcentaje = $total > 0 ? ($gestionados / $total) * 100 : 100;

            if ($pendientes == 0) {
                $porcentaje = 100;
                $status_text = 'EXCELENTE';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 80) {
                $status_text = 'AL DÍA';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 50) {
                $status_text = 'REVISAR';
                $status_color = 'var(--warning)';
            } else {
                $status_text = 'CRÍTICO';
                $status_color = 'var(--danger)';
            }

            $sum_entregado += $con_entrada;
            $sum_recibido += $con_recibido;
            $sum_salida += $con_salida;
            $sum_finalizado += $con_finalizado;
            $sum_total_doc += $total;
            $sum_total_ges += $gestionados;

            $usuarios = $this->usuariosDelCargo($cdgo);
            $iniciales = array_map(fn ($n) => $this->geneIniciales($n), $usuarios);

            $rows_data[] = [
                'id' => $cdgo,
                'cargo' => (string) $cargo->de_crgo,
                'usuarios' => $usuarios,
                'iniciales' => $iniciales,
                'entrada' => $con_entrada,
                'recibido' => $con_recibido,
                'salida' => $con_salida,
                'finalizado' => $con_finalizado,
                'total' => $total,
                'gestionados' => $gestionados,
                'porcentaje' => round($porcentaje, 1),
                'status_text' => $status_text,
                'status_color' => $status_color,
            ];
        }

        usort($rows_data, function ($a, $b) {
            $aTiene = $a['total'] > 0;
            $bTiene = $b['total'] > 0;
            if ($aTiene !== $bTiene) {
                return $bTiene <=> $aTiene;
            }
            $pctA = $a['total'] > 0 ? ($a['gestionados'] / $a['total']) * 100 : 0;
            $pctB = $b['total'] > 0 ? ($b['gestionados'] / $b['total']) * 100 : 0;

            return $pctB <=> $pctA;
        });

        $sum_gestionado_global = $sum_salida + $sum_finalizado;
        $pct_trabajo = $sum_total_doc > 0 ? round(($sum_gestionado_global / $sum_total_doc) * 100, 1) : 0;

        if ($pct_trabajo >= 80) {
            $pct_color = '#10b981';
            $pct_bg = 'rgba(16,185,129,0.1)';
        } elseif ($pct_trabajo >= 50) {
            $pct_color = '#f59e0b';
            $pct_bg = 'rgba(245,158,11,0.1)';
        } else {
            $pct_color = '#ef4444';
            $pct_bg = 'rgba(239,68,68,0.1)';
        }

        $totales = [
            'sum_entregado' => $sum_entregado,
            'sum_recibido' => $sum_recibido,
            'sum_salida' => $sum_salida,
            'sum_finalizado' => $sum_finalizado,
            'sum_total_doc' => $sum_total_doc,
            'sum_total_ges' => $sum_total_ges,
            'pct_trabajo' => $pct_trabajo,
            'pct_color' => $pct_color,
            'pct_bg' => $pct_bg,
        ];

        return view($pathVista, compact(
            'rows_data',
            'totales',
            'evoData',
            'desdeStr',
            'hastaStr'
        ));
    }

    public function ajaxDetalle(Request $request)
    {
        $cargo_id = (int) $request->input('cargo_id');
        $tipo_bandeja = (int) $request->input('tipo_bandeja');
        $desde = (string) $request->input('fecha_inicio');
        $hasta = (string) $request->input('fecha_fin');

        try {
            $fechaDesdeSql = Carbon::createFromFormat('d/m/Y', $desde)->format('Y-m-d') . ' 00:01';
            $fechaHastaSql = Carbon::createFromFormat('d/m/Y', $hasta)->format('Y-m-d') . ' 23:59';
        } catch (\Exception $e) {
            $fechaDesdeSql = Carbon::now('America/Lima')->startOfMonth()->format('Y-m-d') . ' 00:01';
            $fechaHastaSql = Carbon::now('America/Lima')->format('Y-m-d') . ' 23:59';
        }

        $s = $this->schema;

        $baseQuery = DB::table("{$s}r_trmtes_dcmntos as r")
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'u.no_crto',
                'r.fe_rcbdo_trmte',
                'p.fe_ingr_trmte',
            ])
            ->join("{$s}p_dto_trmtes as p", 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_usrios as u", 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->join("{$s}a_estdos_trmtes as e", 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingr_trmte', [$fechaDesdeSql, $fechaHastaSql]);

        if ($tipo_bandeja === 1) {
            $baseQuery->where(function ($q) use ($cargo_id) {
                $q->where(function ($q2) use ($cargo_id) {
                    $q2->where('r.cdgo_crgo_en', $cargo_id)
                        ->where('r.cdgo_crgo_sa', '!=', $cargo_id)
                        ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29']);
                })->orWhere(function ($q2) use ($cargo_id) {
                    $q2->where('r.cdgo_crgo_en', $cargo_id)
                        ->whereIn('r.cdgo_estdo_trmte', ['25', '51']);
                });
            });
        } elseif ($tipo_bandeja === 2) {
            $baseQuery->where('r.cdgo_crgo_en', $cargo_id)
                ->where('r.cdgo_crgo_sa', $cargo_id)
                ->whereNotIn('r.cdgo_estdo_trmte', ['3']);
        } elseif ($tipo_bandeja === 3) {
            $baseQuery->where('r.cdgo_crgo_sa', $cargo_id)
                ->where('r.cdgo_crgo_en', '!=', $cargo_id);
        } elseif ($tipo_bandeja === 4) {
            $baseQuery->where('r.cdgo_crgo_en', $cargo_id)
                ->where('r.cdgo_crgo_sa', $cargo_id)
                ->whereIn('r.cdgo_estdo_trmte', ['3']);
        }

        $tramites = $baseQuery->orderByDesc('p.fe_ingr_trmte')->get();
        $ids = $tramites->pluck('cdgo_dto_trmte')->filter()->unique()->all();
        $adjuntos = $this->primerosAdjuntos($ids);
        $rutas = $this->rutasAreas($ids);

        foreach ($tramites as $tramite) {
            $id = (int) $tramite->cdgo_dto_trmte;
            $ruta_areas = $rutas[$id] ?? [];
            $tramite->ruta_areas = $ruta_areas;
            $tramite->area_actual = ! empty($ruta_areas) ? end($ruta_areas) : '-';
            $tramite->nom_archivo = $adjuntos[$id] ?? '';

            $dias_area = 0;
            if ($tramite->fe_rcbdo_trmte) {
                $dias_area = Carbon::parse($tramite->fe_rcbdo_trmte)->startOfDay()
                    ->diffInDays(Carbon::now('America/Lima')->startOfDay());
            }
            $tramite->dias_area = $dias_area;
            $tramite->fechaini = $tramite->fe_ingr_trmte
                ? Carbon::parse($tramite->fe_ingr_trmte)->format('d/m/Y H:i')
                : '-';
            $tramite->fe_derivacion = $tramite->fe_rcbdo_trmte
                ? Carbon::parse($tramite->fe_rcbdo_trmte)->format('d/m/Y H:i')
                : '-';
            $tramite->nu_expe_format = $this->formatoExpediente((string) $tramite->nu_expe_todo);
        }

        $viewHtml = view('mod_gm.mod_tram_exte.partials.v2_lista_reporte_area_cargo_detalle', [
            'tramites' => $tramites,
            'uid' => 'tbl_v2_' . $cargo_id . '_' . $tipo_bandeja,
        ])->render();

        return response()->json(['html' => $viewHtml]);
    }

    public function historico(int $id)
    {
        $s = $this->schema;
        $company = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));
        $ahora = Carbon::now('America/Lima')->format('Y-m-d H:i:s');

        $expediente = (string) DB::table($s . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $id)
            ->value('nu_expe_todo');

        $query = DB::table($s . 'r_trmtes_dcmntos as r')
            ->join($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->join($s . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($s . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($s . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select([
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                'e.cdgo_estdo_trmte',
                'r.de_obse',
                'a.de_area',
                'r.de_urls_imag',
                'p.fe_ingr_trmte',
                'r.in_esta',
                DB::raw('COALESCE(u.no_crto, l.de_lgin) as usuario'),
            ])
            ->where('p.cdgo_dto_trmte', $id)
            ->orderByDesc('r.cdgo_trmte_dcmnto');

        if ($company > 0) {
            $query->where('p.cdgo_cmpnia', $company);
        }

        $rows = $query->get();
        $out = [];
        foreach ($rows as $i => $row) {
            $prev = $i > 0 ? $rows[$i - 1] : null;
            $estadoNom = (string) ($row->de_estdo_trmte ?: $row->dc_estdo_trmte);
            $inEsta = (string) $row->in_esta;
            if ($inEsta === '0') {
                $fin = $prev && $prev->fe_rcbdo_trmte ? (string) $prev->fe_rcbdo_trmte : $ahora;
                $ta = $this->tiempoEstimado((string) ($row->fe_rcbdo_trmte ?? ''), $fin);
            } elseif ($inEsta === '1' && mb_strtoupper($estadoNom, 'UTF-8') === 'FINALIZADO') {
                $ta = $this->tiempoEstimado((string) ($row->fe_ingr_trmte ?? ''), (string) ($row->fe_rcbdo_trmte ?? $ahora));
            } else {
                $ta = $this->tiempoEstimado((string) ($row->fe_rcbdo_trmte ?? ''), $ahora);
            }

            $estadoId = (int) $row->cdgo_estdo_trmte;
            $color = '';
            if (in_array($estadoId, [3, 26], true)) {
                $color = '#88DE3B';
            } elseif ($estadoId === 51) {
                $color = '#DCDE3B';
            }

            $out[] = [
                'id' => (int) $row->cdgo_trmte_dcmnto,
                'fecha' => $row->fe_rcbdo_trmte ? Carbon::parse($row->fe_rcbdo_trmte)->format('d/m/Y') : '',
                'ta' => $ta,
                'area' => (string) ($row->de_area ?? ''),
                'estado' => (string) ($row->dc_estdo_trmte ?? ''),
                'observaciones' => (string) ($row->de_obse ?? ''),
                'usuario' => (string) ($row->usuario ?? ''),
                'adjunto' => (string) ($row->de_urls_imag ?? ''),
                'color' => $color,
            ];
        }

        return view('mod_gm.mod_tram_exte.partials.v2_lista_reporte_area_cargo_historico', [
            'expediente' => $this->formatoExpediente($expediente),
            'movimientos' => $out,
        ]);
    }

    private function buildEvolution(int $usIdsArea, string $fechaDesdeSql, string $fechaHastaSql): array
    {
        $s = $this->schema;
        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        if ($usIdsArea <= 0) {
            return [
                'labels' => [],
                'finalizados' => [],
                'pendientes' => [],
                'total_fin' => 0,
                'avg_fin' => 0,
                'trend_pct' => 0,
                'avg_time' => 1.5,
            ];
        }

        $sqlEvo = "
            SELECT
                EXTRACT(YEAR FROM p.fe_ingr_trmte)::int  AS anio,
                EXTRACT(MONTH FROM p.fe_ingr_trmte)::int AS mes,
                SUM(CASE WHEN r.cdgo_estdo_trmte = '3' THEN 1 ELSE 0 END) AS finalizados,
                SUM(CASE WHEN r.cdgo_estdo_trmte IN ('1','2') THEN 1 ELSE 0 END) AS pendientes
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE c.cdgo_area = ?
              AND r.in_esta = '1'
              AND p.fe_ingr_trmte >= ?
              AND p.fe_ingr_trmte <= ?
            GROUP BY anio, mes
            ORDER BY anio ASC, mes ASC
            LIMIT 19
        ";

        $resEvo = DB::select($sqlEvo, [$usIdsArea, $fechaDesdeSql, $fechaHastaSql]);

        $labels = [];
        $finalizados = [];
        $pendientes = [];
        $total_fin = 0;

        foreach ($resEvo as $row) {
            $idx = (int) $row->mes - 1;
            $labels[] = $meses[$idx] . ' ' . substr((string) $row->anio, 2);
            $finalizados[] = (int) $row->finalizados;
            $pendientes[] = (int) $row->pendientes;
            $total_fin += (int) $row->finalizados;
        }

        $avg_fin = count($finalizados) > 0 ? round($total_fin / count($finalizados), 1) : 0;
        $last_val = end($finalizados);
        $prev_val = prev($finalizados);
        $trend_pct = 0;
        if ($prev_val !== false && $prev_val > 0) {
            $trend_pct = round((($last_val - $prev_val) / $prev_val) * 100);
        }

        $sqlTime = "
            SELECT AVG(EXTRACT(DAY FROM (r.fe_rcbdo_trmte - p.fe_ingr_trmte))) as avg_time
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE c.cdgo_area = ?
              AND r.cdgo_estdo_trmte = '3'
              AND r.in_esta = '1'
              AND r.fe_rcbdo_trmte IS NOT NULL
              AND p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= ?
        ";
        $resTime = DB::selectOne($sqlTime, [$usIdsArea, $fechaDesdeSql, $fechaHastaSql]);
        $avg_time = $resTime && $resTime->avg_time !== null ? round((float) $resTime->avg_time, 1) : 1.5;

        return [
            'labels' => $labels,
            'finalizados' => $finalizados,
            'pendientes' => $pendientes,
            'total_fin' => $total_fin,
            'avg_fin' => $avg_fin,
            'trend_pct' => $trend_pct,
            'avg_time' => $avg_time,
        ];
    }

    private function areaUsuarioActual(): int
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return 0;
        }

        return (int) (DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('cdgo_area') ?? 0);
    }

    private function countEntrada(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_dcmntos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dto_trmtes as p", 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where(function ($q) use ($cdgo) {
                $q->whereNull('c_sa.cdgo_crgo')->orWhere('c_sa.cdgo_crgo', '!=', $cdgo);
            })
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27'])
            ->whereBetween('p.fe_ingr_trmte', [$desde, $hasta])
            ->count();
    }

    private function countRecibidos(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_dcmntos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dto_trmtes as p", 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['3', '26', '27'])
            ->whereBetween('p.fe_ingr_trmte', [$desde, $hasta])
            ->count();
    }

    private function countSalida(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_dcmntos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dto_trmtes as p", 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('c_en.cdgo_crgo', '!=', $cdgo)
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingr_trmte', [$desde, $hasta])
            ->count();
    }

    private function countFinalizado(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_dcmntos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dto_trmtes as p", 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('r.in_esta', '1')
            ->whereIn('r.cdgo_estdo_trmte', ['3'])
            ->whereBetween('p.fe_ingr_trmte', [$desde, $hasta])
            ->count();
    }

    private function usuariosDelCargo(int $cdgo): array
    {
        $s = $this->schema;

        return DB::table("{$s}r_crgos_lgin as rcl")
            ->join("{$s}p_lgin as l", 'rcl.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->join("{$s}p_usrios as u", 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('rcl.cdgo_crgo', $cdgo)
            ->where('l.in_esta', '1')
            ->orderBy('u.no_crto')
            ->pluck('u.no_crto')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    private function geneIniciales(string $nombre): string
    {
        $parts = preg_split('/\s+/', trim($nombre)) ?: [];
        $ini = '';
        foreach ($parts as $p) {
            if ($p !== '') {
                $ini .= mb_substr($p, 0, 1);
            }
        }

        return mb_strtoupper($ini);
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

    private function primerosAdjuntos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $s = $this->schema;
        $rows = DB::table("{$s}r_trmtes_dcmntos")
            ->select('cdgo_dto_trmte', 'de_urls_imag', 'cdgo_trmte_dcmnto')
            ->whereIn('cdgo_dto_trmte', $ids)
            ->whereNotNull('de_urls_imag')
            ->where('de_urls_imag', '!=', '')
            ->orderBy('cdgo_trmte_dcmnto')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->cdgo_dto_trmte;
            if (! isset($out[$id])) {
                $out[$id] = (string) $row->de_urls_imag;
            }
        }

        return $out;
    }

    private function rutasAreas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $s = $this->schema;
        $rows = DB::table("{$s}r_trmtes_dcmntos as r")
            ->join("{$s}p_crgos as c", 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->join("{$s}a_areas as a", 'c.cdgo_area', '=', 'a.cdgo_area')
            ->whereIn('r.cdgo_dto_trmte', $ids)
            ->orderBy('r.cdgo_dto_trmte')
            ->orderBy('r.cdgo_trmte_dcmnto')
            ->get(['r.cdgo_dto_trmte', 'a.dc_area']);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->cdgo_dto_trmte;
            $area = (string) ($row->dc_area ?? '');
            if ($area === '') {
                continue;
            }
            if (! isset($out[$id])) {
                $out[$id] = [];
            }
            $last = $out[$id] === [] ? '' : end($out[$id]);
            if ($area !== $last) {
                $out[$id][] = $area;
            }
        }

        return $out;
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

        return $s . 's';
    }
}
