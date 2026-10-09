<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Equivalente a segi_adjuntar.php
 * Vista BD: /mod_gm/mod_tram_exte/segi_adjuntar
 */
class AdjuntarTramiteController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // POST batch adjuntar (como el antiguo con hdn_cont)
        if ($request->isMethod('post') && $request->filled('hdn_cont')) {
            return $this->adjuntarLote($request);
        }

        $criterio = trim((string) $request->input('criterio', ''));
        $d1 = (string) $request->input('D1', 'p_dto_trmtes.nu_expe_todo');
        $fecha = trim((string) $request->input('fecha', ''));
        $pagina = max(1, (int) $request->input('pagina', 1));
        $tamPag = 15;

        $query = $this->baseQuery();

        $allowed = [
            'p_dto_trmtes.nu_expe_todo',
            'a_areas.de_area',
            'p_usrios.no_crto',
            'a_tpo_trmtes.dc_tpo_trmte',
            'p_dto_trmtes.de_obse_todo',
            'p_usrios_1.no_crto',
        ];

        if ($criterio !== '' && in_array($d1, $allowed, true)) {
            $map = [
                'p_dto_trmtes.nu_expe_todo' => 'p_dto_trmtes.nu_expe_todo',
                'a_areas.de_area' => 'a_areas.de_area',
                'p_usrios.no_crto' => 'p_usrios.no_crto',
                'a_tpo_trmtes.dc_tpo_trmte' => 'a_tpo_trmtes.dc_tpo_trmte',
                'p_dto_trmtes.de_obse_todo' => 'p_dto_trmtes.de_obse_todo',
                'p_usrios_1.no_crto' => 'p_usrios_1.no_crto',
            ];
            $col = $map[$d1];
            $query->where($col, 'ILIKE', '%' . $criterio . '%');
        }

        if ($fecha !== '') {
            try {
                $f = Carbon::createFromFormat('d/m/Y', $fecha)->format('Y-m-d');
                $query->whereBetween('p_dto_trmtes.fe_ingr_trmte', [$f . ' 00:00:00', $f . ' 23:59:59']);
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        $total = (clone $query)->count();
        $numPags = max(1, (int) ceil($total / $tamPag));
        $pagina = min($pagina, $numPags);
        $offset = ($pagina - 1) * $tamPag;

        $rows = $query
            ->orderByDesc('p_dto_trmtes.cdgo_dto_trmte')
            ->offset($offset)
            ->limit($tamPag)
            ->get();

        foreach ($rows as $row) {
            // Antiguo: F. Entrada = fe_rcbdo_trmte (no fe_ingr_trmte)
            $row->fe_fmt = $row->fe_rcbdo_trmte
                ? Carbon::parse($row->fe_rcbdo_trmte)->timezone('America/Lima')->format('d/m/Y')
                : '-';
            $row->nu_expe_fmt = $this->formatExpediente((string) ($row->nu_expe_todo ?? ''));
            $row->no_crto_corto = mb_substr((string) ($row->no_crto ?? ''), 0, 22);
            $row->dc_area_corto = mb_substr((string) ($row->dc_area ?? ''), 0, 9);
            $row->obse_corto = mb_substr((string) ($row->de_obse_trmte ?? ''), 0, 30);
        }

        return view($this->vistaDesdeMenu($pathVista, $opcionMenu), [
            'rows' => $rows,
            'total' => $total,
            'pagina' => $pagina,
            'numPags' => $numPags,
            'criterio' => $criterio,
            'd1' => $d1,
            'fecha' => $fecha,
            'ok' => session('ok'),
            'urlModulo' => url()->current(),
        ]);
    }

    public function upload(Request $request, $movimientoId)
    {
        $request->validate([
            'documento' => 'required|file|mimes:pdf,jpg,jpeg,png,zip|max:10240',
        ]);

        try {
            if (! $request->hasFile('documento')) {
                return response()->json(['success' => false, 'message' => 'No se recibió ningún archivo.'], 400);
            }

            $filename = $this->guardarArchivo($request->file('documento'), (int) $movimientoId);
            $this->actualizarMovimiento((int) $movimientoId, $filename, $request);

            return response()->json([
                'success' => true,
                'message' => 'Archivo adjuntado correctamente.',
                'file' => $filename,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function detalle(Request $request)
    {
        $id = (int) $request->id;
        $s = $this->schema;

        $movimientoBase = DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $id)
            ->first();

        if (! $movimientoBase) {
            return response()->json(['success' => false, 'message' => 'Movimiento no encontrado.']);
        }

        $cdgoDto = $movimientoBase->cdgo_dto_trmte;

        $expediente = DB::table($s . 'p_dto_trmtes as p_dto_trmtes')
            ->leftJoin($s . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->select(
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_dto_trmtes.de_obse_todo',
                'p_dto_trmtes.de_obse_trmte',
                'p_usrios.no_crto',
                'p_usrios.nu_docu',
                'a_tpo_trmtes.de_tpo_trmte'
            )
            ->where('p_dto_trmtes.cdgo_dto_trmte', $cdgoDto)
            ->first();

        $historial = DB::table($s . 'r_trmtes_dcmntos as r')
            ->leftJoin($s . 'p_crgos as p_crgos_en', 'r.cdgo_crgo_en', '=', 'p_crgos_en.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_areas_en', 'p_crgos_en.cdgo_area', '=', 'a_areas_en.cdgo_area')
            ->leftJoin($s . 'p_crgos as p_crgos_sa', 'r.cdgo_crgo_sa', '=', 'p_crgos_sa.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_areas_sa', 'p_crgos_sa.cdgo_area', '=', 'a_areas_sa.cdgo_area')
            ->leftJoin($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->select(
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte as fe_trmte_dcmnto',
                'a_areas_sa.de_area as area_origen',
                'a_areas_en.de_area as area_destino',
                'a_estdos_trmtes.dc_estdo_trmte as estado',
                'r.de_obse as observacion',
                'r.de_urls_imag'
            )
            ->where('r.cdgo_dto_trmte', $cdgoDto)
            ->orderByDesc('r.cdgo_trmte_dcmnto')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'expediente' => $expediente,
                'historial' => $historial,
            ],
        ]);
    }

    private function adjuntarLote(Request $request)
    {
        $cont = (int) $request->input('hdn_cont', 0);
        $subidos = 0;

        for ($i = 1; $i <= $cont; $i++) {
            $cdgo = (int) $request->input('hdn_cdgo_trmte_dcmnto' . $i, 0);
            $fileKey = 'fle_de_urls_imag' . $i;

            if ($cdgo <= 0 || ! $request->hasFile($fileKey)) {
                continue;
            }

            $file = $request->file($fileKey);
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $filename = $this->guardarArchivo($file, $cdgo);
            $this->actualizarMovimiento($cdgo, $filename, $request);
            $subidos++;
        }

        $qs = http_build_query(array_filter([
            'criterio' => $request->input('criterio_keep'),
            'D1' => $request->input('D1_keep'),
            'fecha' => $request->input('fecha_keep'),
            'pagina' => $request->input('pagina_keep'),
        ]));

        return redirect()
            ->to(url()->current() . ($qs ? '?' . $qs : ''))
            ->with('ok', $subidos > 0
                ? "Se adjuntaron {$subidos} archivo(s)."
                : 'No se seleccionó ningún archivo.');
    }

    private function baseQuery()
    {
        $s = $this->schema;
        $cmpnia = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));

        // Misma estructura que segi_adjuntar.php (INNER JOINs del SELECT principal)
        $query = DB::table($s . 'p_dto_trmtes as p_dto_trmtes')
            ->join($s . 'p_lgin as p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')
            ->join($s . 'r_trmtes_dcmntos as r_trmtes_dcmntos', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->join($s . 'p_crgos as p_crgos', 'r_trmtes_dcmntos.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'p_usrios as p_usrios_1', 'p_lgin.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->select(
                'p_dto_trmtes.cdgo_dto_trmte',
                'p_dto_trmtes.nu_expe_todo',
                'p_usrios.no_crto',
                'r_trmtes_dcmntos.fe_rcbdo_trmte',
                'a_areas.dc_area',
                'a_areas.de_area',
                'p_dto_trmtes.de_obse_trmte',
                'r_trmtes_dcmntos.cdgo_trmte_dcmnto',
                'r_trmtes_dcmntos.de_urls_imag',
                'a_estdos_trmtes.de_estdo_trmte'
            )
            ->where('r_trmtes_dcmntos.cdgo_crgo_sa', '0')
            ->whereIn('r_trmtes_dcmntos.cdgo_estdo_trmte', ['25', '5', '1'])
            ->where('r_trmtes_dcmntos.in_esta', '1');

        if ($cmpnia > 0) {
            $query->where('p_dto_trmtes.cdgo_cmpnia', $cmpnia);
        }

        return $query;
    }

    private function guardarArchivo($file, int $movimientoId): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = 'adjunto_' . $movimientoId . '_' . time() . '.' . $extension;
        $file->storeAs('adjuntos', $filename, 'public');

        return $filename;
    }

    private function actualizarMovimiento(int $movimientoId, string $filename, Request $request): void
    {
        $s = $this->schema;

        DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->update(['de_urls_imag' => $filename]);

        $idTram = (int) DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->value('cdgo_dto_trmte');

        try {
            DB::table($s . 'p_sgmto_log')->insert([
                'cdgo_lgin' => Auth::user()->cdgo_lgin ?? 0,
                'de_sgto_log' => "Se asigno el expediente {$idTram}",
                'fe_sgto_log' => now(),
                'de_ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // log opcional; no bloquear adjunto
        }
    }

    public function verArchivo(string $nombre)
    {
        $nombre = basename($nombre);
        $path = storage_path('app/public/adjuntos/' . $nombre);
        if (! is_file($path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->file($path);
    }

    private function formatExpediente(string $nu): string
    {
        $parts = explode('-', $nu);
        if (count($parts) < 3) {
            return $nu;
        }

        $mid = ($parts[1] ?? '') . '-' . substr((string) ($parts[2] ?? ''), 2, 10);

        return ($parts[0] ?? '') . '-' . str_pad($mid, 8, '0', STR_PAD_LEFT);
    }
}
