<?php

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Equivalente a ver_secuencial_area.php
 * Vista BD: /mod_gene/mod_tram_inter/ver_secuencial_area
 */
class SecuencialTramiteController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request)
    {
        // Generar número (POST del formulario, como el antiguo)
        if ($request->isMethod('post') && $request->filled('sel_cdgo_tpo_trmte') && (int) $request->input('sel_cdgo_tpo_trmte') > 0) {
            return $this->generar($request);
        }

        $ctx = $this->contextoSesion();
        $intCdgoTpoTrmte = (int) $request->input('intCdgoTpoTrmte', 0);
        $anioActual = (int) date('Y');

        $anios = DB::table($this->schema . 'a_anios')
            ->where('cdgo_cmpnia', $ctx['cmpnia'])
            ->orderByDesc('de_anio')
            ->get(['cdgo_anio', 'de_anio']);

        $cdgoAnioActual = (int) ($anios->firstWhere('de_anio', (string) $anioActual)->cdgo_anio
            ?? $anios->first()->cdgo_anio
            ?? 0);

        $tipos = DB::table($this->schema . 'a_tpo_trmtes as t')
            ->join($this->schema . 'r_tpo_trmte_areas as r', 't.cdgo_tpo_trmte', '=', 'r.cdgo_tpo_trmte')
            ->where('r.in_esta', '1')
            ->where('t.in_esta', '1')
            ->where('r.cdgo_area', $ctx['area_id'])
            ->distinct()
            ->orderBy('t.de_tpo_trmte')
            ->get(['t.cdgo_tpo_trmte', 't.de_tpo_trmte']);

        return view('mod_gene.mod_tram_inter.ver_secuencial_area', [
            'areaNombre' => $ctx['area_nombre'],
            'anios' => $anios,
            'cdgoAnioActual' => $cdgoAnioActual,
            'tipos' => $tipos,
            'intCdgoTpoTrmte' => $intCdgoTpoTrmte,
        ]);
    }

    /**
     * Equivalente a ajax_lista_secuencial_documentos.php
     */
    public function lista(Request $request)
    {
        $cdgoTpo = (int) $request->input('cdgo_tpo_trmte', 0);
        $cdgoAnio = (int) $request->input('cdgo_anio', 0);
        $ctx = $this->contextoSesion();
        $s = $this->schema;

        if ($cdgoTpo <= 0 || $cdgoAnio <= 0) {
            return response()->json(['html' => '<p class="det-empty">Seleccione año y tipo de documento.</p>']);
        }

        $inScncal = (int) DB::table($s . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $cdgoTpo)
            ->value('in_scncal');

        $query = DB::table($s . 'r_scncal_tpo_trmtes_area as r')
            ->join($s . 'a_tpo_trmtes as t', 'r.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->join($s . 'a_anios as a', 'r.cdgo_anio', '=', 'a.cdgo_anio')
            ->join($s . 'p_lgin as l', 'r.co_usua_modi', '=', 'l.cdgo_lgin')
            ->join($s . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('r.in_esta', '1')
            ->where('r.cdgo_anio', $cdgoAnio)
            ->where('r.cdgo_tpo_trmte', $cdgoTpo);

        if ($inScncal === 1) {
            $query->where('r.cdgo_crgo', $ctx['cargo_id']);
        } else {
            $query->where('r.cdgo_area', $ctx['area_id']);
        }

        $rows = $query
            ->orderByDesc('r.cdgo_scncal_tpo_trmtes_area')
            ->get([
                'r.cdgo_scncal_tpo_trmtes_area',
                'r.nu_scncal_tpo_trmtes_area',
                'a.de_anio',
                'r.de_scncal_tpo_trmtes_area',
                'r.fe_scncal_tpo_trmtes_area',
                'u.no_crto',
            ]);

        foreach ($rows as $row) {
            $row->documento = str_pad(
                (string) $row->nu_scncal_tpo_trmtes_area . '-' . $row->de_anio,
                8,
                '0',
                STR_PAD_LEFT
            );
            $row->fecha_fmt = $row->fe_scncal_tpo_trmtes_area
                ? Carbon::parse($row->fe_scncal_tpo_trmtes_area)->timezone('America/Lima')->format('d/m/Y H:i')
                : '-';
            $row->usu_ini = $this->geneIniciales((string) ($row->no_crto ?? ''));
        }

        $html = view('mod_gene.mod_tram_inter.partials.ver_secuencial_area_lista', [
            'rows' => $rows,
        ])->render();

        return response()->json(['html' => $html]);
    }

    private function generar(Request $request)
    {
        $request->validate([
            'sel_cdgo_tpo_trmte' => 'required|integer|min:1',
            'sel_cdgo_anio' => 'required|integer|min:1',
            'txt_de_scncal_tpo_trmtes_area' => 'nullable|string|max:2000',
        ]);

        $ctx = $this->contextoSesion();
        $s = $this->schema;
        $cdgoTpo = (int) $request->input('sel_cdgo_tpo_trmte');
        $cdgoAnio = (int) $request->input('sel_cdgo_anio');
        $detalle = mb_strtoupper(trim((string) $request->input('txt_de_scncal_tpo_trmtes_area', '')));

        $inScncal = (int) DB::table($s . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $cdgoTpo)
            ->value('in_scncal');

        $maxQuery = DB::table($s . 'r_scncal_tpo_trmtes_area')
            ->where('cdgo_anio', $cdgoAnio)
            ->where('cdgo_tpo_trmte', $cdgoTpo);

        if ($inScncal === 1) {
            $maxQuery->where('cdgo_crgo', $ctx['cargo_id']);
        } else {
            $maxQuery->where('cdgo_area', $ctx['area_id']);
        }

        $nu = (int) $maxQuery->max('nu_scncal_tpo_trmtes_area') + 1;
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $historial = $detalle . '+' . $userId . '+' . Carbon::now('America/Lima')->format('d/m/Y H:i:s');

        DB::table($s . 'r_scncal_tpo_trmtes_area')->insert([
            'cdgo_tpo_trmte' => $cdgoTpo,
            'cdgo_area' => $ctx['area_id'],
            'cdgo_crgo' => $ctx['cargo_id'],
            'cdgo_anio' => $cdgoAnio,
            'fe_scncal_tpo_trmtes_area' => now(),
            'nu_scncal_tpo_trmtes_area' => $nu,
            'de_scncal_tpo_trmtes_area' => $detalle,
            'de_scncal_tpo_trmtes_area_hstral' => mb_strtoupper($historial),
            'ts_usua_modi' => now(),
            'co_usua_modi' => $userId,
            'in_esta' => '1',
        ]);

        return redirect()
            ->to(url('/mod_gene/mod_tram_inter/ver_secuencial_area') . '?intCdgoTpoTrmte=' . $cdgoTpo)
            ->with('ok', 'Número generado: ' . $nu);
    }

    private function contextoSesion(): array
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        $user = Auth::user();
        $cmpnia = (int) (session('usComp') ?: ($user->cdgo_cmpnia ?? 0));

        $cargo = DB::table($this->schema . 'p_crgos as c')
            ->leftJoin($this->schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->where('c.cdgo_crgo', $cargoId)
            ->first(['c.cdgo_area', 'a.de_area']);

        $areaId = (int) ($cargo->cdgo_area ?? 0);
        $areaNombre = (string) (session('sigtram_area_nombre') ?: ($cargo->de_area ?? ''));

        return [
            'cargo_id' => $cargoId,
            'area_id' => $areaId,
            'area_nombre' => $areaNombre !== '' ? $areaNombre : '—',
            'cmpnia' => $cmpnia > 0 ? $cmpnia : 2,
        ];
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
}
