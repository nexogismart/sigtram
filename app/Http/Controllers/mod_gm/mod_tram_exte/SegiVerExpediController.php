<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SegiVerExpediController extends Controller
{
    private string $schema;

    private const CAMPOS = [
        'p_dto_trmtes.nu_expe_todo',
        'p_usrios.no_crto',
        'a_tpo_trmtes.dc_tpo_trmte',
        'p_dto_trmtes.de_obse_todo',
    ];

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Seguimiento del expediente'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Seguimiento del expediente';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
            'estados' => $this->estadosDelArea(),
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

        $perPage = 6;
        $page = max(1, (int) $request->input('page', 1));
        $query = $this->queryBase($request, $cargoId);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $festivos = $this->festivos();
        $rows = $query
            ->orderByDesc('p.cdgo_dto_trmte')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $rows->map(fn ($row) => $this->mapRow($row, $festivos))->values(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
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
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'p.in_docu_trmte',
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

        return response()->json([
            'status' => 'success',
            'expediente' => $expediente,
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

        $cargoId = (int) session('sigtram_cargo_id', 0);
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

        $s = $this->schema;
        $estadoActual = (string) DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $doc)
            ->value('cdgo_estdo_trmte');

        if ($estadoActual === '' || $estadoActual === $esta) {
            return response()->json([
                'status' => 'error',
                'message' => 'El estado ya no coincide con el movimiento actual.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            if (in_array($esta, ['5', '17'], true)) {
                $this->iniciarLapsoSilencio($idTram);
            }
            if ($esta === '7') {
                $this->cerrarLapsoSilencio($idTram);
            }
            if (in_array($estAct, ['5', '17'], true)) {
                $this->cerrarLapsoSilencio($idTram);
            }

            DB::table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $idTram)
                ->update(['in_esta' => '0']);

            $inEsta = $esta === '3' ? '0' : '1';
            DB::table($s . 'r_trmtes_dcmntos')->insert([
                'cdgo_crgo_sa' => $cargoId,
                'cdgo_crgo_en' => $cargoId,
                'cdgo_dto_trmte' => $idTram,
                'cdgo_estdo_trmte' => $esta,
                'fe_rcbdo_trmte' => now(),
                'de_obse' => mb_strtoupper($observacion, 'UTF-8'),
                'in_esta' => $inEsta,
                'de_urls_imag' => '',
                'cdgo_lgin' => $userId,
                'nu_foli_anex' => null,
            ]);

            try {
                DB::table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => mb_strtoupper($observacion, 'UTF-8'),
                    'fe_sgto_log' => now(),
                    'de_ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo registrar p_sgmto_log al cambiar estado', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'El estado se actualizó correctamente.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado en segi_verexpedi', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo cambiar el estado.',
            ], 500);
        }
    }

    private function queryBase(Request $request, int $cargoId)
    {
        $s = $this->schema;
        $company = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));

        $query = DB::table($s . 'p_dto_trmtes as p')
            ->join($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p_usrios.no_crto',
                'r.fe_rcbdo_trmte',
                'a_estdos_trmtes.dc_estdo_trmte',
                'p.de_obse_trmte',
                'p.in_encu_tram',
                'p.fe_ingr_trmte',
                'p.cdgo_docmnto_trmte',
                'r.cdgo_trmte_dcmnto',
                'a_estdos_trmtes.cdgo_estdo_trmte',
                'a_docmntos_trmtes.nu_tiem_sile',
            ])
            ->where('r.cdgo_crgo_en', $cargoId)
            ->where('r.cdgo_crgo_sa', $cargoId)
            ->where('r.in_esta', '1')
            ->where('r.cdgo_estdo_trmte', '<>', '3');

        if ($company > 0) {
            $query->where('p.cdgo_cmpnia', $company);
        }

        $palabra = trim((string) $request->input('palabra', ''));
        if ($palabra !== '') {
            $campo = (string) $request->input('campo', 'p_dto_trmtes.nu_expe_todo');
            if (! in_array($campo, self::CAMPOS, true)) {
                $campo = 'p_dto_trmtes.nu_expe_todo';
            }
            $col = $this->aliasCampo($campo);
            $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $palabra . '%']);
        }

        return $query;
    }

    private function aliasCampo(string $campo): string
    {
        return match ($campo) {
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.no_crto' => 'p_usrios.no_crto',
            'a_tpo_trmtes.dc_tpo_trmte' => 'a_tpo_trmtes.dc_tpo_trmte',
            'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
            default => 'p.nu_expe_todo',
        };
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

    private function estadosDelArea(): array
    {
        try {
            $cargoId = (int) session('sigtram_cargo_id', 0);
            if ($cargoId <= 0) {
                return [];
            }

            $areaId = (int) DB::table($this->schema . 'p_crgos')
                ->where('cdgo_crgo', $cargoId)
                ->value('cdgo_area');

            if ($areaId <= 0) {
                return [];
            }

            return DB::table($this->schema . 'r_stad_aras as r')
                ->join($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
                ->where('r.cdgo_area', $areaId)
                ->orderBy('e.dc_estdo_trmte')
                ->get(['e.cdgo_estdo_trmte', 'e.dc_estdo_trmte'])
                ->map(fn ($row) => [
                    'id' => (string) $row->cdgo_estdo_trmte,
                    'nombre' => (string) $row->dc_estdo_trmte,
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function iniciarLapsoSilencio(int $idTram): void
    {
        try {
            $s = $this->schema;
            $row = DB::table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->first();
            if ($row) {
                DB::table($s . 'h_slncio')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->update(['fe_inic' => now(), 'in_esta' => '1']);
            } else {
                DB::table($s . 'h_slncio')->insert([
                    'cdgo_dto_trmte' => $idTram,
                    'nu_dias' => null,
                    'fe_inic' => now(),
                    'in_esta' => '1',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo actualizar h_slncio (inicio)', ['error' => $e->getMessage()]);
        }
    }

    private function cerrarLapsoSilencio(int $idTram): void
    {
        try {
            $s = $this->schema;
            $row = DB::table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->first();
            if (! $row || (string) $row->in_esta !== '1' || ! $row->fe_inic) {
                return;
            }

            $dias = (int) round((Carbon::today()->getTimestamp() - Carbon::parse($row->fe_inic)->startOfDay()->getTimestamp()) / 86400);
            if ($dias < 0) {
                $dias = 0;
            }
            $total = $dias + (int) ($row->nu_dias ?? 0);
            DB::table($s . 'h_slncio')
                ->where('cdgo_dto_trmte', $idTram)
                ->update(['nu_dias' => $total, 'in_esta' => '2']);
        } catch (\Throwable $e) {
            Log::warning('No se pudo actualizar h_slncio (cierre)', ['error' => $e->getMessage()]);
        }
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

    /** @return array<string, bool> */
    private function festivos(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $rows = DB::table($this->schema . 'a_dias_fstvos')
                ->where('in_esta', '1')
                ->get(['fe_dias_fstvos']);
            foreach ($rows as $row) {
                if ($row->fe_dias_fstvos) {
                    $cache[Carbon::parse($row->fe_dias_fstvos)->toDateString()] = true;
                }
            }
        } catch (\Throwable $e) {
            $cache = [];
        }

        return $cache;
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
