<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RedirigirExpedienteController extends Controller
{
    private string $schema;

    /** @var array<string, bool>|null */
    private ?array $cacheFestivos = null;

    private const CAMPOS_BUSQUEDA = [
        'p_dto_trmtes.nu_expe_todo',
        'a_areas.de_area',
        'p_usrios.no_crto',
        'a_tpo_trmtes.dc_tpo_trmte',
        'p_dto_trmtes.de_obse_todo',
        'p_usrios_1.no_crto',
    ];

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Redirigir'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Redirigir';
        }

        $cmpnia = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 2));

        try {
            $gerencias = DB::table($this->schema . 'a_areas')
                ->whereNull('cdgo_area_prim')
                ->where('in_esta', '1')
                ->where('cdgo_cmpnia', $cmpnia)
                ->orderBy('de_area')
                ->get(['cdgo_area', 'de_area', 'in_part']);
        } catch (\Throwable $e) {
            $gerencias = DB::table($this->schema . 'a_areas')
                ->whereNull('cdgo_area_prim')
                ->where('in_esta', '1')
                ->where('cdgo_cmpnia', $cmpnia)
                ->orderBy('de_area')
                ->get(['cdgo_area', 'de_area']);
            $gerencias->each(function ($g) {
                $g->in_part = '0';
            });
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
            'gerencias' => $gerencias,
            'fechaActual' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Equivalente a gene_expediente_reenviar + filtros de segi_redirigir.php
     */
    public function buscar(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $page = max(1, (int) $request->input('page', 1));
        if ($perPage < 1) {
            $perPage = 15;
        }

        $query = $this->queryReenviar($request);
        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('r_trmtes_dcmntos.fe_rcbdo_trmte')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(function ($row) {
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

        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'current_page' => min($page, $lastPage),
            'last_page' => $lastPage,
            'total' => $total,
        ]);
    }

    /**
     * Equivalente a segi_otra.php (ids = cdgo_dto_trmte).
     */
    public function derivar(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'cdgo_gerencia' => 'required|integer|min:1',
            'cdgo_subgerencia' => 'nullable|integer',
            'usar_subgerencia' => 'nullable',
            'observacion' => 'nullable|string',
        ]);

        $ids = array_values(array_filter($request->input('ids', []), fn ($id) => (int) $id > 0));
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
        $userId = (int) (Auth::user()->cdgo_lgin ?? session('usIds') ?? 0);
        $ip = $request->ip() ?: '';

        $areaDestino = ($usarSub && $subId > 0) ? $subId : $gerenciaId;

        $cargoDestino = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_area', $areaDestino)
            ->where('nu_orde', 1)
            ->orderByDesc('cdgo_crgo')
            ->first(['cdgo_crgo']);

        if (! $cargoDestino) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un cargo válido para el área destino.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($ids as $idTram) {
                $idTram = (int) $idTram;
                $this->cerrarLapsoSilencio($idTram);

                DB::table($this->schema . 'r_trmtes_dcmntos')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->update(['in_esta' => '0']);

                DB::table($this->schema . 'p_dto_trmtes')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->update(['cdgo_area' => $areaDestino]);

                DB::table($this->schema . 'r_trmtes_dcmntos')->insert([
                    'cdgo_crgo_sa' => 0,
                    'cdgo_crgo_en' => $cargoDestino->cdgo_crgo,
                    'cdgo_dto_trmte' => $idTram,
                    'cdgo_estdo_trmte' => 13,
                    'fe_rcbdo_trmte' => now(),
                    'de_obse' => mb_strtoupper($observacion, 'UTF-8'),
                    'in_esta' => '1',
                    'de_urls_imag' => '',
                    'cdgo_lgin' => $userId,
                    'nu_foli_anex' => null,
                ]);

                $obseTodo = (string) (DB::table($this->schema . 'p_dto_trmtes')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->value('de_obse_todo') ?? '');
                DB::table($this->schema . 'p_dto_trmtes')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->update([
                        'de_obse_todo' => mb_strtoupper(trim($obseTodo . ' - ' . $observacion), 'UTF-8'),
                    ]);

                try {
                    DB::table($this->schema . 'p_sgmto_log')->insert([
                        'cdgo_lgin' => $userId,
                        'de_sgto_log' => "Se reedirigio el expediente {$idTram}",
                        'fe_sgto_log' => now(),
                        'de_ip' => $ip,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo registrar p_sgmto_log al redirigir', ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Los expedientes fueron reedirigidos',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error redirigiendo expedientes', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al redirigir: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Equivalente a segi_detalle_anular.php
     */
    public function anular(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'motivo' => 'required|string',
            'expediente' => 'nullable|string',
        ]);

        $id = (int) $request->input('id');
        $motivo = trim((string) $request->input('motivo'));
        $expediente = trim((string) $request->input('expediente', ''));
        $userId = (int) (Auth::user()->cdgo_lgin ?? session('usIds') ?? 0);

        if ($motivo === '') {
            return response()->json([
                'success' => false,
                'message' => 'Debe especificar el motivo',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $mov = DB::table($this->schema . 'r_trmtes_dcmntos')
                ->where('cdgo_trmte_dcmnto', $id)
                ->first();

            if (! $mov) {
                throw new \RuntimeException('Movimiento no encontrado.');
            }

            if ($expediente === '') {
                $expediente = (string) (DB::table($this->schema . 'p_dto_trmtes')
                    ->where('cdgo_dto_trmte', $mov->cdgo_dto_trmte)
                    ->value('nu_expe_todo') ?? '');
            }

            $deObse = mb_strtoupper($motivo . ', SE ANULA CON FECHA ' . now()->format('d/m/Y H:i:s'), 'UTF-8');

            DB::table($this->schema . 'r_trmtes_dcmntos')
                ->where('cdgo_trmte_dcmnto', $id)
                ->update([
                    'cdgo_estdo_trmte' => 29,
                    'de_obse' => $deObse,
                ]);

            try {
                DB::table($this->schema . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => "Se anulo el expediente {$expediente} con cdgo_trmte_dcmnto = {$id}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $request->ip() ?: '',
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo registrar p_sgmto_log al anular', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'El trámite fue anulado',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error anulando expediente', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al anular.',
            ], 500);
        }
    }

    /**
     * Equivalente a impr_redirigir.php: agrupado por área, expedientes y simples.
     */
    public function imprimir(Request $request)
    {
        $cmpnia = Auth::user()->cdgo_cmpnia ?? session('usComp') ?? 2;

        $query = $this->queryReenviar($request)
            ->addSelect(
                'p_dto_trmtes.nu_foli_trmte',
                'p_dto_trmtes.cdgo_tpo_trmte',
                'a_areas.de_area',
                'a_areas.cdgo_area'
            )
            ->orderBy('a_areas.de_area')
            ->orderByDesc('p_dto_trmtes.cdgo_dto_trmte');

        $rows = $query->get();

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
        $dtoTrmte = DB::table($this->schema . 'p_dto_trmtes as p')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select(
                'p.nu_expe_todo',
                'p.fe_ingr_trmte',
                'p.de_obse_todo',
                'p.nu_foli_trmte',
                't.de_tpo_trmte',
                'u.no_crto',
                'u.nu_docu',
                'u.nu_ruc',
                'u.de_dire',
                'u.nu_tele',
                'u.de_mail'
            )
            ->where('p.cdgo_dto_trmte', $id)
            ->first();

        if (! $dtoTrmte) {
            return response()->json(['success' => false, 'message' => 'Expediente no encontrado']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'expediente' => $dtoTrmte->nu_expe_todo,
                'fecha' => $dtoTrmte->fe_ingr_trmte ? Carbon::parse($dtoTrmte->fe_ingr_trmte)->format('d/m/Y H:i') : '',
                'solicitante' => $dtoTrmte->no_crto,
                'documento_identidad' => ($dtoTrmte->nu_ruc ?: $dtoTrmte->nu_docu) ?: 'No especificado',
                'domicilio' => $dtoTrmte->de_dire ?: 'No especificado',
                'telefono' => $dtoTrmte->nu_tele ?: 'No especificado',
                'correo' => $dtoTrmte->de_mail ?: 'No especificado',
                'folios' => $dtoTrmte->nu_foli_trmte ?: '0',
                'tipo_doc' => $dtoTrmte->de_tpo_trmte,
                'observacion' => $dtoTrmte->de_obse_todo ?: 'Ninguna',
            ],
        ]);
    }

    private function queryReenviar(Request $request)
    {
        $query = DB::table($this->schema . 'r_trmtes_dcmntos as r_trmtes_dcmntos')
            ->join($this->schema . 'p_crgos as p_crgos', 'r_trmtes_dcmntos.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($this->schema . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($this->schema . 'p_dto_trmtes as p_dto_trmtes', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->join($this->schema . 'p_lgin as p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')
            ->join($this->schema . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($this->schema . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'p_usrios as p_usrios_1', 'p_lgin.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->leftJoin($this->schema . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p_dto_trmtes.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->select(
                'p_dto_trmtes.cdgo_dto_trmte',
                'p_dto_trmtes.nu_expe_todo',
                'p_usrios.no_crto',
                'r_trmtes_dcmntos.fe_rcbdo_trmte',
                'a_areas.dc_area',
                'p_dto_trmtes.de_obse_trmte',
                'p_dto_trmtes.in_encu_tram',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_dto_trmtes.cdgo_docmnto_trmte',
                'r_trmtes_dcmntos.in_esta',
                'r_trmtes_dcmntos.cdgo_crgo_en',
                'a_estdos_trmtes.de_estdo_trmte',
                'p_lgin.de_lgin',
                'r_trmtes_dcmntos.cdgo_trmte_dcmnto',
                'a_docmntos_trmtes.nu_tiem_sile'
            )
            ->where('r_trmtes_dcmntos.cdgo_crgo_sa', '0')
            ->where('r_trmtes_dcmntos.in_esta', '1')
            ->whereNotIn('a_estdos_trmtes.cdgo_estdo_trmte', [29]);

        $palabra = trim((string) $request->input('palabra', ''));
        $campo = (string) $request->input('buscar_por', 'p_dto_trmtes.nu_expe_todo');
        if (! in_array($campo, self::CAMPOS_BUSQUEDA, true)) {
            $campo = 'p_dto_trmtes.nu_expe_todo';
        }

        if ($palabra !== '') {
            $query->whereRaw("UPPER({$campo}) LIKE UPPER(?)", ['%' . $palabra . '%']);
        }

        $fecha = $this->normalizarFecha($request->input('fecha'));
        if ($fecha !== null) {
            $query->whereRaw(
                "p_dto_trmtes.fe_ingr_trmte BETWEEN ? AND (CAST(? AS date) + 1)",
                [$fecha, $fecha]
            );
        }

        return $query;
    }

    private function cerrarLapsoSilencio(int $idTram): void
    {
        $row = DB::table($this->schema . 'h_slncio')
            ->where('cdgo_dto_trmte', $idTram)
            ->first();

        if (! $row || (string) $row->in_esta !== '1') {
            return;
        }

        $hoy = Carbon::today();
        $inicio = Carbon::parse($row->fe_inic)->startOfDay();
        $total = (int) $hoy->diffInDays($inicio) + (int) ($row->nu_dias ?? 0);

        DB::table($this->schema . 'h_slncio')
            ->where('cdgo_dto_trmte', $idTram)
            ->update([
                'nu_dias' => $total,
                'in_esta' => '2',
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

        $this->cacheFestivos = [];
        try {
            $rows = DB::table($this->schema . 'a_dias_fstvos')
                ->where('in_esta', '1')
                ->get(['fe_dias_fstvos']);
            foreach ($rows as $row) {
                if ($row->fe_dias_fstvos) {
                    $this->cacheFestivos[Carbon::parse($row->fe_dias_fstvos)->toDateString()] = true;
                }
            }
        } catch (\Throwable $e) {
            $this->cacheFestivos = [];
        }

        return $this->cacheFestivos;
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

    private function cortar(string $texto, int $largo): string
    {
        if (mb_strlen($texto, 'UTF-8') <= $largo) {
            return $texto;
        }

        return mb_substr($texto, 0, $largo, 'UTF-8');
    }
}
