<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\SessionKeys;

/**
 * Equivalente a v2_lista_reporte_area_cargo.php (módulo externos):
 * dashboard + analytics por cargo del área, sobre p_dto_trmtes / r_trmtes_dcmntos.
 */
class V2ListaReporteAreaCargoExternoController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
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

        $evoData = $this->tramiteRepo->obtenerV2EvolucionExterno($usIdsArea, $fechaDesdeSql, $fechaHastaSql);
        $cargos = $this->tramiteRepo->obtenerCargosAreaExterno($usIdsArea);
        $metricas = $this->tramiteRepo->obtenerMetricasCargosV2($cargos, $fechaDesdeSql, $fechaHastaSql);

        $rows_data = $metricas['rows_data'];
        $totales = $metricas['totales'];

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

        $res = $this->tramiteRepo->obtenerDetalleBandejaCargoV2($cargo_id, $tipo_bandeja, $fechaDesdeSql, $fechaHastaSql);
        $tramites = $res['tramites'];
        $adjuntos = $res['adjuntos'];
        $rutas = $res['rutas'];

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
        $company = (int) session(SessionKeys::USER_COMPANY, 0);
        $res = $this->tramiteRepo->obtenerHistoricoMovimientosV2($id, $company);

        $ahora = Carbon::now('America/Lima')->format('Y-m-d H:i:s');
        $rows = $res['rows'];
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
            'expediente' => $this->formatoExpediente($res['expediente']),
            'movimientos' => $out,
        ]);
    }

    private function areaUsuarioActual(): int
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        if ($cargoId <= 0) {
            return 0;
        }

        return $this->tramiteRepo->obtenerAreaPorCargo($cargoId);
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
