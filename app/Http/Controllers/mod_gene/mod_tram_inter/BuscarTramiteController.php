<?php

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BuscarTramiteController extends Controller
{
    public function index(Request $request)
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        $request->validate([
            'sel_cdgo_area' => ['nullable', 'integer'],
            'sel_cdgo_area_en' => ['nullable', 'integer'],
            'sel_cdgo_tpo_trmte' => ['nullable', 'integer'],
            'txt_nu_tram_todo' => ['nullable', 'string', 'max:100', 'regex:/^[0-9\-]*$/u'],
            'T1' => ['nullable', 'date_format:Y-m-d'],
            'T2' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        $idLogin = (int) ($user->cdgo_lgin ?? 0);

        $cargoInfo = DB::table($schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();

        $areaUsuario = (int) ($cargoInfo->cdgo_area ?? 0);
        // Mismos IDs admin que lista_tramite_todo.php (usIds 1 y 72)
        $isAdmin = ($idLogin === 1 || $idLogin === 72);

        // Área de filtro: por defecto la del usuario (como el antiguo)
        $cdgoArea = $request->filled('sel_cdgo_area')
            ? (int) $request->input('sel_cdgo_area')
            : $areaUsuario;

        if (! $isAdmin) {
            $cdgoArea = $areaUsuario;
        }

        // Selects
        $areasQuery = DB::table($schema . 'a_areas')
            ->select('cdgo_area', 'de_area', 'dc_area')
            ->where('in_esta', '1');
        if (! $isAdmin) {
            $areasQuery->where('cdgo_area', $areaUsuario);
        }
        $areas = $areasQuery->orderBy('de_area')->get();

        $destinoAreas = DB::table($schema . 'a_areas')
            ->select('cdgo_area', 'de_area', 'dc_area')
            ->where('in_esta', '1')
            ->orderBy('de_area')
            ->get();

        $tiposDocumento = DB::table($schema . 'a_tpo_trmtes as t')
            ->join($schema . 'r_tpo_trmte_areas as r', 't.cdgo_tpo_trmte', '=', 'r.cdgo_tpo_trmte')
            ->select('t.cdgo_tpo_trmte', 't.de_tpo_trmte', 't.dc_tpo_trmte')
            ->where('t.in_esta', '1')
            ->where('r.in_esta', '1')
            ->when($cdgoArea > 0, fn ($q) => $q->where('r.cdgo_area', $cdgoArea))
            ->distinct()
            ->orderBy('t.de_tpo_trmte')
            ->get();

        // Consulta = gene_lista_tramites_internos_area
        $query = DB::table($schema . 'r_trmtes_ntrnos as r_trmtes_ntrnos')
            ->join($schema . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_ntrnos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($schema . 'p_dtos_ntrnos', 'r_trmtes_ntrnos.cdgo_dtos_ntrnos', '=', 'p_dtos_ntrnos.cdgo_dtos_ntrnos')
            ->join($schema . 'a_tpo_trmtes', 'p_dtos_ntrnos.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($schema . 'a_areas as origen', 'p_dtos_ntrnos.cdgo_area', '=', 'origen.cdgo_area')
            ->leftJoin($schema . 'p_crgos as crgo_en', 'r_trmtes_ntrnos.cdgo_crgo_en', '=', 'crgo_en.cdgo_crgo')
            ->leftJoin($schema . 'p_crgos as crgo_sa', 'r_trmtes_ntrnos.cdgo_crgo_sa', '=', 'crgo_sa.cdgo_crgo')
            ->leftJoin($schema . 'a_areas as destino', 'crgo_en.cdgo_area', '=', 'destino.cdgo_area')
            ->select(
                'p_dtos_ntrnos.cdgo_dtos_ntrnos',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_tpo_trmtes.dc_tpo_trmte',
                'p_dtos_ntrnos.nu_tram_todo',
                'p_dtos_ntrnos.fe_ingrso',
                'p_dtos_ntrnos.de_obser',
                'p_dtos_ntrnos.de_urls_adjnto',
                'origen.de_area as area_origen',
                'origen.dc_area as dc_area_origen',
                'destino.de_area as area_destino',
                'destino.dc_area as dc_area_destino',
                'p_dtos_ntrnos.cdgo_dto_trmte',
                'p_dtos_ntrnos.cdgo_dtos_ntrnos_pri'
            )
            ->where('p_dtos_ntrnos.in_esta', '1');

        // Criterio base antiguo: (cargo_en.área = X OR cargo_sa.área = X)
        if ($cdgoArea > 0) {
            $query->where(function ($q) use ($cdgoArea) {
                $q->where('crgo_en.cdgo_area', $cdgoArea)
                    ->orWhere('crgo_sa.cdgo_area', $cdgoArea);
            });
        }

        if ($request->filled('sel_cdgo_area_en')) {
            $query->where('crgo_en.cdgo_area', (int) $request->sel_cdgo_area_en);
        }

        if ($request->filled('sel_cdgo_tpo_trmte') && (int) $request->sel_cdgo_tpo_trmte > 0) {
            $query->where('p_dtos_ntrnos.cdgo_tpo_trmte', (int) $request->sel_cdgo_tpo_trmte);
        }

        // Nº trámite: igual exacto como el antiguo (no LIKE, no fechas forzadas a hoy)
        if ($request->filled('txt_nu_tram_todo')) {
            $query->where('p_dtos_ntrnos.nu_tram_todo', trim((string) $request->txt_nu_tram_todo));
        }

        // Fechas solo si ambas vienen (panel opcional del antiguo)
        if ($request->filled('T1') && $request->filled('T2')) {
            $query->where('p_dtos_ntrnos.fe_ingrso', '>=', $request->T1 . ' 00:00:00')
                ->where('p_dtos_ntrnos.fe_ingrso', '<=', $request->T2 . ' 23:59:59');
        }

        $query->orderBy('p_dtos_ntrnos.fe_ingrso', 'desc');

        $groupCols = [
            'p_dtos_ntrnos.cdgo_dtos_ntrnos',
            'a_tpo_trmtes.de_tpo_trmte',
            'a_tpo_trmtes.dc_tpo_trmte',
            'p_dtos_ntrnos.nu_tram_todo',
            'p_dtos_ntrnos.fe_ingrso',
            'p_dtos_ntrnos.de_obser',
            'p_dtos_ntrnos.de_urls_adjnto',
            'origen.de_area',
            'origen.dc_area',
            'destino.de_area',
            'destino.dc_area',
            'p_dtos_ntrnos.cdgo_dto_trmte',
            'p_dtos_ntrnos.cdgo_dtos_ntrnos_pri',
        ];

        // Antiguo: 36 por página
        $tramites = $query->groupBy($groupCols)->paginate(36)->withQueryString();

        $tramites->getCollection()->transform(function ($item) {
            $item->fe_ingrso_fmt = $this->formatFeIngrsoLocal($item->fe_ingrso);
            return $item;
        });

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'data' => array_values($tramites->items()),
                'total' => $tramites->total(),
                'current_page' => $tramites->currentPage(),
                'last_page' => $tramites->lastPage(),
            ]);
        }

        return view('mod_gene.mod_tram_inter.buscar-tramite', [
            'areas' => $areas,
            'destinoAreas' => $destinoAreas,
            'tiposDocumento' => $tiposDocumento,
            'tramites' => $tramites,
            'filtros' => [
                'sel_cdgo_area' => $cdgoArea,
                'sel_cdgo_area_en' => $request->input('sel_cdgo_area_en', ''),
                'sel_cdgo_tpo_trmte' => $request->input('sel_cdgo_tpo_trmte', '0'),
                'txt_nu_tram_todo' => $request->input('txt_nu_tram_todo', ''),
                'T1' => $request->input('T1', ''),
                'T2' => $request->input('T2', ''),
            ],
            'isAdmin' => $isAdmin,
            'areaUsuario' => $areaUsuario,
        ]);
    }

    /**
     * Tipos de documento por área (como ajax_semilla_interno / tipo_documento_area).
     */
    public function tiposPorArea(Request $request)
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $area = (int) $request->query('area', 0);
        $tipos = DB::table($schema . 'a_tpo_trmtes as t')
            ->join($schema . 'r_tpo_trmte_areas as r', 't.cdgo_tpo_trmte', '=', 'r.cdgo_tpo_trmte')
            ->select('t.cdgo_tpo_trmte', 't.de_tpo_trmte', 't.dc_tpo_trmte')
            ->where('t.in_esta', '1')
            ->where('r.in_esta', '1')
            ->when($area > 0, fn ($q) => $q->where('r.cdgo_area', $area))
            ->distinct()
            ->orderBy('t.de_tpo_trmte')
            ->get();

        return response()->json(['status' => 'success', 'data' => $tipos]);
    }

    // Detalle del Trámite (ojo / voucher)
    public function detalle(Request $request)
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $id = (int) $request->id;

        $tramite = DB::table($schema . 'p_dtos_ntrnos as p_dtos_ntrnos')
            ->join($schema . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dtos_ntrnos.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($schema . 'a_areas as origen', 'p_dtos_ntrnos.cdgo_area', '=', 'origen.cdgo_area')
            ->leftJoin($schema . 'r_trmtes_ntrnos as r', 'r.cdgo_dtos_ntrnos', '=', 'p_dtos_ntrnos.cdgo_dtos_ntrnos')
            ->leftJoin($schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('p_dtos_ntrnos.cdgo_dtos_ntrnos', $id)
            ->select(
                'p_dtos_ntrnos.cdgo_dtos_ntrnos',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_tpo_trmtes.dc_tpo_trmte',
                'p_dtos_ntrnos.nu_tram_todo',
                'p_dtos_ntrnos.fe_ingrso',
                'origen.de_area as area_origen',
                'origen.dc_area as dc_area_origen',
                'p_dtos_ntrnos.de_obser',
                'p_dtos_ntrnos.de_urls_adjnto',
                'e.dc_estdo_trmte as estado'
            )
            ->orderBy('r.cdgo_trmtes_ntrnos', 'desc')
            ->first();

        if (! $tramite) {
            return response()->json(['success' => false, 'message' => 'Trámite no encontrado.']);
        }

        $tramite->fe_ingrso_fmt = $this->formatFeIngrsoLocal($tramite->fe_ingrso);

        return response()->json([
            'success' => true,
            'data' => $tramite,
        ]);
    }

    /** Historial de movimientos (equivalente segi_tramite popup). */
    public function historial(Request $request)
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID no proporcionado'], 400);
        }

        $sql = "SELECT r_trmtes_ntrnos.cdgo_trmtes_ntrnos, r_trmtes_ntrnos.fe_rcbdo, r_trmtes_ntrnos.de_obser_ntrnos,
                       a_areas.de_area, a_areas_1.de_area AS de_area_1, r_trmtes_ntrnos.de_urls_agrdo,
                       a_areas.dc_area, a_areas_1.dc_area AS dc_area_1, a_estdos_trmtes.dc_estdo_trmte,
                       p_dtos_ntrnos.nu_tram_todo
                FROM {$schema}p_crgos p_crgos
                INNER JOIN {$schema}a_areas a_areas ON (p_crgos.cdgo_area = a_areas.cdgo_area)
                INNER JOIN {$schema}r_trmtes_ntrnos r_trmtes_ntrnos ON (r_trmtes_ntrnos.cdgo_crgo_sa = p_crgos.cdgo_crgo)
                INNER JOIN {$schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                INNER JOIN {$schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                INNER JOIN {$schema}p_crgos p_crgos_1 ON (r_trmtes_ntrnos.cdgo_crgo_en = p_crgos_1.cdgo_crgo)
                INNER JOIN {$schema}a_areas a_areas_1 ON (p_crgos_1.cdgo_area = a_areas_1.cdgo_area)
                WHERE p_dtos_ntrnos.cdgo_dtos_ntrnos = ?
                ORDER BY r_trmtes_ntrnos.cdgo_trmtes_ntrnos DESC";

        $data = DB::select($sql, [$id]);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function formatFeIngrsoLocal($feIngrso): string
    {
        if (! $feIngrso) {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($feIngrso)->timezone('America/Lima')->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $feIngrso;
        }
    }


    /**
     * Descarga segura de adjuntos de trámite interno.
     * Acepta ?path= (ruta storage nueva) o ?nom_archivo= (compatibilidad legacy).
     */
    public function descargar(Request $request)
    {
        $raw = trim((string) ($request->query('path')
            ?: $request->query('nom_archivo')
            ?: $request->input('path')
            ?: $request->input('nom_archivo')
            ?: ''));
        // En BD a veces vienen varios separados por coma; se toma el primero.
        $raw = trim(explode(',', $raw)[0] ?? '');

        if ($raw === '' || str_contains($raw, '..') || str_contains($raw, "\0")) {
            abort(400, 'Archivo inválido. Use ?nom_archivo=priXXXX.pdf o ?path=documentos/internos/...');
        }

        $ruta = $this->resolverRutaAdjuntoInterno($raw);
        if ($ruta === null) {
            $basename = basename(str_replace('\\', '/', $raw));
            $esperado = 'storage/app/private/mod_gene/mod_tram_inter/adjunto/' . $basename;

            // Mensaje visible (evita el 404 genérico sin pista)
            return response(
                "Documento no encontrado: {$basename}\n\n"
                . "Coloque el archivo en el SERVIDOR (no solo en su PC) en:\n"
                . "  {$esperado}\n\n"
                . "Ruta absoluta que busca esta app:\n"
                . '  ' . storage_path('app/private/mod_gene/mod_tram_inter/adjunto/' . $basename) . "\n\n"
                . '¿Existe la carpeta adjunto? '
                . (is_dir(storage_path('app/private/mod_gene/mod_tram_inter/adjunto')) ? 'SÍ' : 'NO') . "\n",
                404,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        return response()->download($ruta, basename($ruta));
    }

    /**
     * Resuelve la ruta física del adjunto (solo lectura).
     */
    private function resolverRutaAdjuntoInterno(string $raw): ?string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $normalizado = str_replace('\\', '/', ltrim($raw, '/'));

        // 1) Ruta completa relativa al disco local (ingresos nuevos)
        if ($disk->exists($normalizado)) {
            $full = $disk->path($normalizado);
            if (is_file($full)) {
                return $full;
            }
        }

        $basename = basename($normalizado);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return null;
        }

        // 2) Nombre solo: bajo adjunto del disco local
        $relAdjunto = 'mod_gene/mod_tram_inter/adjunto/' . $basename;
        if ($disk->exists($relAdjunto)) {
            $full = $disk->path($relAdjunto);
            if (is_file($full)) {
                return $full;
            }
        }
        $relTramites = 'mod_gene/mod_tram_inter/adjunto/tramites/' . $basename;
        if ($disk->exists($relTramites)) {
            $full = $disk->path($relTramites);
            if (is_file($full)) {
                return $full;
            }
        }

        $legacyRootRaw = (string) config(
            'filesystems.legacy_sigtram_root',
            base_path('../sigtram_antiguo')
        );
        $legacyRoot = rtrim($legacyRootRaw, '/\\');
        if ($legacyRoot !== '' && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $legacyRoot) && ! str_starts_with($legacyRoot, '/')) {
            $legacyRoot = rtrim(base_path($legacyRoot), '/\\');
        }

        $dirs = [
            storage_path('app/private/mod_gene/mod_tram_inter/adjunto'),
            storage_path('app/private/mod_gene/mod_tram_inter/adjunto/tramites'),
            storage_path('app/mod_gene/mod_tram_inter/adjunto'),
            storage_path('app/mod_gene/mod_tram_inter/adjunto/tramites'),
            $legacyRoot . DIRECTORY_SEPARATOR . 'Presentacion' . DIRECTORY_SEPARATOR . 'mod_gene' . DIRECTORY_SEPARATOR . 'mod_tram_inter' . DIRECTORY_SEPARATOR . 'adjunto',
            $legacyRoot . DIRECTORY_SEPARATOR . 'Presentacion' . DIRECTORY_SEPARATOR . 'mod_gene' . DIRECTORY_SEPARATOR . 'mod_tram_inter' . DIRECTORY_SEPARATOR . 'adjunto' . DIRECTORY_SEPARATOR . 'tramites',
        ];

        foreach ($dirs as $dir) {
            $hit = $this->buscarArchivoEnDir($dir, $basename);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * Busca archivo por nombre exacto o sin distinguir mayúsculas (Linux).
     */
    private function buscarArchivoEnDir(string $dir, string $basename): ?string
    {
        if ($dir === '' || ! is_dir($dir)) {
            return null;
        }

        $exacto = $dir . DIRECTORY_SEPARATOR . $basename;
        if (is_file($exacto)) {
            return $exacto;
        }

        // Case-insensitive (pri378812.PDF vs pri378812.pdf)
        $needle = strtolower($basename);
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower($entry) === $needle) {
                $full = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($full)) {
                    return $full;
                }
            }
        }

        return null;
    }

    /**
     * Helper para formatear fechas a UTC (ISO-8601)
     */
    private function formatFeIngrso($feIngrso)
    {
        if (!$feIngrso) return null;
        try {
            if ($feIngrso instanceof \DateTimeInterface) {
                return \Carbon\Carbon::instance($feIngrso)->setTimezone('UTC')->toIso8601String();
            }
            $dateStr = is_string($feIngrso) ? preg_replace('/\.\d+$/', '', trim($feIngrso)) : (string) $feIngrso;
            return \Carbon\Carbon::parse($dateStr, 'America/Lima')->setTimezone('UTC')->toIso8601String();
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::parse((string) $feIngrso)->setTimezone('UTC')->toIso8601String();
            } catch (\Exception $ex) {
                return (string) $feIngrso;
            }
        }
    }
}
