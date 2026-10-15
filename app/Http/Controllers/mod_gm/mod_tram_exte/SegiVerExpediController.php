<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Support\SessionKeys;

class SegiVerExpediController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Seguimiento del expediente'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Seguimiento del expediente';
        }

        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
            'estados' => $this->tramiteRepo->obtenerEstadosDelAreaSeguimiento($cargoId),
        ]);
    }

    public function buscar(Request $request)
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        if ($cargoId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay un cargo activo en la sesión.',
            ], 422);
        }

        $perPage = 6;
        $page = max(1, (int) $request->input('page', 1));
        $company = (int) session(SessionKeys::USER_COMPANY, 0);

        $res = $this->tramiteRepo->buscarSeguimientoExpedientes($request->all(), $cargoId, $page, $perPage, $company);
        $festivos = $this->tramiteRepo->obtenerDiasFestivos();

        return response()->json([
            'status' => 'success',
            'data' => collect($res['rows'])->map(fn ($row) => $this->mapRow((object) $row, $festivos))->values(),
            'current_page' => $res['current_page'],
            'last_page' => $res['last_page'],
            'total' => $res['total'],
        ]);
    }

    public function historico(Request $request)
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return response()->json([
                'status' => 'success',
                'expediente' => '',
                'data' => [],
            ]);
        }

        $company = (int) session(SessionKeys::USER_COMPANY, 0);
        $res = $this->tramiteRepo->obtenerHistoricoSeguimiento($id, $company);

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

        return response()->json([
            'status' => 'success',
            'expediente' => $res['expediente'],
            'data' => $out,
        ]);
    }

    public function cambiarEstado(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'esta' => 'required',
            'doc' => 'required|integer',
            'est_act' => 'required',
            'observacion' => 'nullable|string',
        ]);

        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        if ($cargoId <= 0 || $userId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay un cargo o usuario activo.',
            ], 422);
        }

        $idTram = (int) $request->input('id');
        $esta = (string) $request->input('esta');
        $doc = (int) $request->input('doc');
        $estAct = (string) $request->input('est_act');
        $observacion = trim((string) $request->input('observacion', 'Se cambio el estado en el area'));
        if ($observacion === '') {
            $observacion = 'Se cambio el estado en el area';
        }

        if ($estAct === '7') {
            return response()->json([
                'status' => 'error',
                'message' => 'El expediente ya no puede cambiar de estado.',
            ], 422);
        }
        if ($esta === $estAct) {
            return response()->json([
                'status' => 'error',
                'message' => 'Seleccione un estado distinto.',
            ], 422);
        }

        $company = (int) session(SessionKeys::USER_COMPANY, 0);

        try {
            $this->tramiteRepo->cambiarEstadoSeguimiento(
                $idTram,
                $doc,
                $esta,
                $estAct,
                $observacion,
                $cargoId,
                $userId,
                $request->ip() ?: '',
                $company
            );

            return response()->json([
                'status' => 'success',
                'message' => 'El estado se actualizó correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al cambiar estado en segi_verexpedi', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() ?: 'No se pudo cambiar el estado.',
            ], 500);
        }
    }

    private function mapRow(object $row, array $festivos): array
    {
        $nombre = (string) ($row->no_crto ?? '');
        $obs = (string) ($row->de_obse_trmte ?? '');
        $plazo = (int) ($row->nu_tiem_sile ?? 0);
        $resta = $this->diasRestantesSilencio($row->fe_ingr_trmte, $plazo, $festivos);
        $alerta = $resta !== null && $resta <= 2 && $plazo !== 0 && $row->cdgo_docmnto_trmte !== null && $row->cdgo_docmnto_trmte !== '';

        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'expediente' => $this->formatoExpediente($row->nu_expe_todo),
            'solicitante' => $nombre,
            'solicitante_corto' => mb_strlen($nombre, 'UTF-8') > 22 ? mb_substr($nombre, 0, 22, 'UTF-8') : $nombre,
            'ubicacion' => ((string) $row->in_encu_tram === '0') ? 'Ex' : 'In',
            'fecha_entrada' => $row->fe_rcbdo_trmte ? Carbon::parse($row->fe_rcbdo_trmte)->format('d/m/Y') : '',
            'estado' => (string) ($row->dc_estdo_trmte ?? ''),
            'estado_id' => (string) $row->cdgo_estdo_trmte,
            'observaciones' => mb_strlen($obs, 'UTF-8') > 20 ? mb_substr($obs, 0, 20, 'UTF-8') : $obs,
            'observaciones_full' => $obs,
            'movimiento_id' => (int) $row->cdgo_trmte_dcmnto,
            'alerta' => $alerta,
        ];
    }

    private function diasRestantesSilencio($fechaIngreso, int $silen, array $festivos): ?int
    {
        if ($silen <= 0 || ! $fechaIngreso) {
            return null;
        }

        $fecha = substr((string) $fechaIngreso, 0, 10);
        $findesemana = intdiv($silen, 5);
        $diaFeri = $silen + $findesemana;
        $limite = Carbon::parse($fecha)->addDays($diaFeri);
        $extra = 0;
        foreach ($festivos as $dia => $ok) {
            if ($dia >= $fecha && $dia <= $limite->toDateString()) {
                $extra++;
            }
        }
        $limiteTs = Carbon::parse($fecha)->addDays($diaFeri + $extra)->startOfDay()->getTimestamp();
        $hoyTs = Carbon::today()->getTimestamp();

        return (int) round(($limiteTs - $hoyTs) / 86400);
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
}
