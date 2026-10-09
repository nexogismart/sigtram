<?php

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CorreoInternoController extends Controller
{
    private $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        $cargoInfo = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();

        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);

        // Videos tutoriales (mismo set que segi_expedi.php; solo los que existan en public/)
        $videosDisponibles = collect([
            ['key' => 'ingresar', 'titulo' => '¿Cómo Ingreso?', 'archivo' => 'i.Ingresar.mp4', 'icono' => 'fa-file-circle-plus'],
            ['key' => 'recepcionar', 'titulo' => '¿Cómo Recepciono?', 'archivo' => 'i.Recepcionar.mp4', 'icono' => 'fa-inbox'],
            ['key' => 'derivar', 'titulo' => '¿Cómo Derivo?', 'archivo' => 'i.Derivar.mp4', 'icono' => 'fa-share'],
            ['key' => 'asignar', 'titulo' => '¿Cómo Asigno?', 'archivo' => 'i.Asignar.mp4', 'icono' => 'fa-user-check'],
            ['key' => 'finalizar', 'titulo' => '¿Cómo Finalizo?', 'archivo' => 'i.Finalizar.mp4', 'icono' => 'fa-flag-checkered'],
            ['key' => 'devolver', 'titulo' => '¿Cómo Devuelvo?', 'archivo' => 'i.Devolver.mp4', 'icono' => 'fa-rotate-left'],
        ])->map(function ($v) {
            $path = public_path('asset/videos/' . $v['archivo']);
            $v['existe'] = is_file($path);
            $v['url'] = $v['existe'] ? asset('asset/videos/' . $v['archivo']) : null;
            return $v;
        })->all();

        return view('mod_gene.mod_tram_inter.correo-interno', [
            'areaId' => $areaId,
            'cargoId' => $cargoId,
            'esAreaContabilidad' => $areaId === 21,
            'videos' => $videosDisponibles,
        ]);
    }

    public function tabla(Request $request)
    {
        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        
        // Obtener cdgo_area del cargo actual
        $cargoInfo = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();
        
        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);
        $companiaId = (int) ($user->cdgo_cmpnia ?? 1);

        $bandeja = $request->input('bandeja', 'entrada');
        $criterio = $request->input('criterio');
        $descripcion = trim((string) $request->input('descripcion', ''));
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $reporte = $request->input('reporte');
        $faltantes = (int) $request->input('faltantes', 0) === 1
            || $reporte === 'sin_finalizar'; // legacy UI: checkbox faltantes reutilizaba ese flag

        // JOINs de referencia solo existen en Entrada (como segi_entrada)
        $tieneJoinRef = in_array($bandeja, ['entrada'], true) || $faltantes;

        $where = ' ';

        if ($descripcion !== '') {
            // Misma semántica que el antiguo (modo 5 / 2 / 1), no ILIKE libre
            $descSql = str_replace("'", "''", strtoupper($descripcion));
            $descExact = str_replace("'", "''", $descripcion);

            switch ($criterio) {
                case 'tramite':
                    // Antiguo modo 5: igual exacto en trámite o trámite ref
                    if ($tieneJoinRef) {
                        $where .= " AND (p_dtos_ntrnos.nu_tram_todo::text = '{$descExact}' OR p_dtos_ntrnos_ref.nu_tram_todo::text = '{$descExact}') ";
                    } else {
                        $where .= " AND p_dtos_ntrnos.nu_tram_todo::text = '{$descExact}' ";
                    }
                    break;
                case 'envio':
                    // Antiguo: p_dtos_ntrnos_ref.nu_tram_todo = X
                    if ($tieneJoinRef) {
                        $where .= " AND p_dtos_ntrnos_ref.nu_tram_todo::text = '{$descExact}' ";
                    } else {
                        $where .= " AND p_dtos_ntrnos.nu_tram_todo::text = '{$descExact}' ";
                    }
                    break;
                case 'referencia':
                    // Antiguo: p_dtos_ntrnos.nu_tram_todo = X (nº del trámite referenciado en celda khaki)
                    $where .= " AND p_dtos_ntrnos.nu_tram_todo::text = '{$descExact}' ";
                    break;
                case 'area':
                    $where .= " AND UPPER(a_areas.de_area) LIKE '%{$descSql}%' ";
                    break;
                case 'tipo':
                    $where .= " AND UPPER(a_tpo_trmtes.de_tpo_trmte) LIKE '%{$descSql}%' ";
                    break;
                case 'observaciones':
                    $where .= " AND UPPER(p_dtos_ntrnos.de_obser) LIKE '%{$descSql}%' ";
                    break;
                case 'usuario':
                    $where .= " AND UPPER(p_usrios.no_crto) LIKE '%{$descSql}%' ";
                    break;
                case 'cargo':
                    $where .= " AND UPPER(p_crgos_en.de_crgo) LIKE '%{$descSql}%' ";
                    break;
            }
        }

        // Antiguo: solo filtra fechas si AMBAS están llenas
        if (!empty($desde) && !empty($hasta)) {
            $desde = preg_replace('/[^0-9\-]/', '', (string) $desde);
            $hasta = preg_replace('/[^0-9\-]/', '', (string) $hasta);
            if ($bandeja === 'sin_finalizar') {
                $where .= " AND r_trmtes_ntrnos.fe_rcbdo >= '{$desde} 00:00:00' AND r_trmtes_ntrnos.fe_rcbdo <= '{$hasta} 23:59:59' ";
            } else {
                $where .= " AND p_dtos_ntrnos.fe_ingrso >= '{$desde} 00:00:00' AND p_dtos_ntrnos.fe_ingrso <= '{$hasta} 23:59:59' ";
            }
        }

        $sql = '';

        switch ($bandeja) {
            case 'enviados':
                $sql = "SELECT DISTINCT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.dc_area, p_dtos_ntrnos.fe_ingrso, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, p_dtos_ntrnos.cdgo_dto_trmte, p_dtos_ntrnos.cdgo_dtos_ntrnos_pri, p_usrios.no_crto
                        FROM {$this->schema}p_dtos_ntrnos p_dtos_ntrnos
                        INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                        INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                        INNER JOIN {$this->schema}p_lgin p_lgin ON (p_dtos_ntrnos.cdgo_lgin = p_lgin.cdgo_lgin)
                        INNER JOIN {$this->schema}p_usrios p_usrios ON (p_lgin.cdgo_usrios = p_usrios.cdgo_usrios)
                        INNER JOIN {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos ON (p_dtos_ntrnos.cdgo_dtos_ntrnos = r_trmtes_ntrnos.cdgo_dtos_ntrnos AND p_dtos_ntrnos.cdgo_lgin = r_trmtes_ntrnos.cdgo_lgin AND r_trmtes_ntrnos.cdgo_estdo_trmte = 1)
                        WHERE p_dtos_ntrnos.in_esta = '1' AND a_areas.cdgo_area = '$areaId' $where
                        ORDER BY p_dtos_ntrnos.cdgo_dtos_ntrnos DESC LIMIT 100";
                break;

            case 'todos':
                $sql = "SELECT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.de_area, p_dtos_ntrnos.fe_ingrso, a_estdos_trmtes.cdgo_estdo_trmte, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, r_trmtes_ntrnos.in_copia, a_areas.dc_area, p_crgos_en.de_crgo, r_trmtes_ntrnos.fe_rcbdo, a_estdos_trmtes.dc_estdo_trmte
                        FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos
                        INNER JOIN {$this->schema}p_crgos p_crgos_en ON r_trmtes_ntrnos.cdgo_crgo_en = p_crgos_en.cdgo_crgo
                        INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                        INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                        INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                        INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                        WHERE p_dtos_ntrnos.in_esta = '1' AND p_crgos_en.cdgo_area = '$areaId' AND r_trmtes_ntrnos.in_esta = '1' $where
                        ORDER BY r_trmtes_ntrnos.fe_rcbdo DESC LIMIT 100";
                break;

            case 'recibidos':
                $sql = "SELECT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.de_area, p_dtos_ntrnos.fe_ingrso, a_estdos_trmtes.cdgo_estdo_trmte, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, r_trmtes_ntrnos.in_copia, a_areas.dc_area, p_dtos_ntrnos.cdgo_dto_trmte, p_dtos_ntrnos.cdgo_dtos_ntrnos_pri, p_dtos_ntrnos.in_envo_fsco, p_dtos_ntrnos.in_prrdad_trmte, a_tpo_trmtes.de_tpo_trmte
                        FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos
                        INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                        INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                        INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                        INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                        WHERE p_dtos_ntrnos.in_esta = '1' AND (r_trmtes_ntrnos.cdgo_crgo_en = '$cargoId') AND (r_trmtes_ntrnos.cdgo_crgo_sa = '$cargoId') AND (r_trmtes_ntrnos.in_esta = '1') AND r_trmtes_ntrnos.cdgo_estdo_trmte NOT IN ('1','3','24') $where
                        ORDER BY r_trmtes_ntrnos.cdgo_trmtes_ntrnos DESC LIMIT 100";
                break;

            case 'salida':
                $sql = "SELECT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.de_area, p_dtos_ntrnos.fe_ingrso, a_estdos_trmtes.cdgo_estdo_trmte, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, p_dtos_ntrnos.in_envo_fsco, p_dtos_ntrnos.in_prrdad_trmte, r_trmtes_ntrnos.in_copia, a_areas.dc_area, a_tpo_trmtes.de_tpo_trmte
                        FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos
                        INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                        INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                        INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                        INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                        WHERE p_dtos_ntrnos.in_esta = '1' AND (r_trmtes_ntrnos.cdgo_crgo_en != '$cargoId') AND (r_trmtes_ntrnos.cdgo_crgo_sa = '$cargoId') AND (r_trmtes_ntrnos.in_esta = '1') $where
                        ORDER BY r_trmtes_ntrnos.fe_rcbdo DESC LIMIT 100";
                break;

            case 'finalizados':
                $sql = "SELECT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.de_area, p_dtos_ntrnos.fe_ingrso, a_estdos_trmtes.cdgo_estdo_trmte, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, p_dtos_ntrnos.in_envo_fsco, p_dtos_ntrnos.in_prrdad_trmte, r_trmtes_ntrnos.in_copia, a_areas.dc_area, a_tpo_trmtes.de_tpo_trmte
                        FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos
                        INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                        INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                        INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                        INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                        WHERE (r_trmtes_ntrnos.cdgo_crgo_en = '$cargoId') AND (r_trmtes_ntrnos.cdgo_crgo_sa = '$cargoId') AND (r_trmtes_ntrnos.in_esta = '1') AND r_trmtes_ntrnos.cdgo_estdo_trmte IN ('3','24') $where
                        ORDER BY r_trmtes_ntrnos.fe_rcbdo DESC LIMIT 100";
                break;

            case 'sin_finalizar':
                // segi_sinfinalizar.php (NO es lo mismo que "faltantes" de entrada)
                $sql = "SELECT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.de_area, r_trmtes_ntrnos.fe_rcbdo AS fe_ingrso, a_estdos_trmtes.cdgo_estdo_trmte, a_estdos_trmtes.dc_estdo_trmte, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, r_trmtes_ntrnos.in_copia, a_areas.dc_area, a_tpo_trmtes.de_tpo_trmte
                        FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos
                        INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                        INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                        INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                        INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                        WHERE p_dtos_ntrnos.in_esta = '1'
                          AND r_trmtes_ntrnos.cdgo_crgo_en != '$cargoId'
                          AND r_trmtes_ntrnos.cdgo_crgo_sa = '$cargoId'
                          AND r_trmtes_ntrnos.in_esta = '1'
                          AND (
                            SELECT a_estdos_trmtes_2.cdgo_estdo_trmte
                            FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos_2
                            INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes_2 ON r_trmtes_ntrnos_2.cdgo_estdo_trmte = a_estdos_trmtes_2.cdgo_estdo_trmte
                            WHERE r_trmtes_ntrnos_2.cdgo_crgo_en != '$cargoId'
                              AND r_trmtes_ntrnos_2.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
                              AND r_trmtes_ntrnos_2.in_esta = '1'
                            LIMIT 1
                          ) NOT IN ('3','24')
                          $where
                        ORDER BY r_trmtes_ntrnos.fe_rcbdo DESC LIMIT 100";
                break;

            case 'contabilidad':
                $sql = "SELECT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.de_area, p_dtos_ntrnos.fe_ingrso, a_estdos_trmtes.cdgo_estdo_trmte, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, r_trmtes_ntrnos.in_copia, a_areas.dc_area, a_tpo_trmtes.de_tpo_trmte
                        FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos
                        INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                        INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                        INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                        INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                        WHERE p_dtos_ntrnos.in_esta = '1'
                          AND (
                                (p_dtos_ntrnos.cdgo_tpo_trmte = 7 AND r_trmtes_ntrnos.cdgo_crgo_en = '21' AND r_trmtes_ntrnos.cdgo_crgo_sa = '20')
                                OR (p_dtos_ntrnos.cdgo_tpo_trmte = 15)
                          )
                          $where
                        ORDER BY r_trmtes_ntrnos.fe_rcbdo DESC LIMIT 100";
                break;

            case 'entrada':
            default:
                $joinsRef = "
                            LEFT JOIN {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos_ref ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos_rfrncia = r_trmtes_ntrnos_ref.cdgo_trmtes_ntrnos)
                            LEFT JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos_ref ON (r_trmtes_ntrnos_ref.cdgo_dtos_ntrnos = p_dtos_ntrnos_ref.cdgo_dtos_ntrnos)
                            LEFT JOIN {$this->schema}a_areas a_areas_ref ON (p_dtos_ntrnos_ref.cdgo_area = a_areas_ref.cdgo_area)
                            LEFT JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes_ref ON (p_dtos_ntrnos_ref.cdgo_tpo_trmte = a_tpo_trmtes_ref.cdgo_tpo_trmte)";

                $selectEntrada = "SELECT p_dtos_ntrnos.cdgo_dtos_ntrnos, a_tpo_trmtes.dc_tpo_trmte, p_dtos_ntrnos.nu_tram_todo, a_areas.de_area, r_trmtes_ntrnos.fe_rcbdo AS fe_ingrso, a_estdos_trmtes.cdgo_estdo_trmte, p_dtos_ntrnos.de_obser, p_dtos_ntrnos.de_urls_adjnto, r_trmtes_ntrnos.in_copia, a_areas.dc_area, p_dtos_ntrnos.cdgo_dto_trmte, p_dtos_ntrnos.cdgo_dtos_ntrnos_pri, a_tpo_trmtes.de_tpo_trmte, p_dtos_ntrnos.in_envo_fsco, p_dtos_ntrnos.in_prrdad_trmte, p_dtos_ntrnos.cdgo_tpo_trmte, a_tpo_trmtes_ref.dc_tpo_trmte AS dc_tpo_trmte_ref, a_tpo_trmtes_ref.de_tpo_trmte AS de_tpo_trmte_ref, p_dtos_ntrnos_ref.nu_tram_todo AS nu_tram_todo_ref, a_areas_ref.de_area AS de_area_ref, a_areas_ref.dc_area AS dc_area_ref, p_dtos_ntrnos_ref.fe_ingrso AS fe_ingrso_ref, p_dtos_ntrnos_ref.de_urls_adjnto AS de_urls_adjnto_ref, r_trmtes_ntrnos_ref.cdgo_trmtes_ntrnos AS cdgo_trmtes_ntrnos_ref, p_dtos_ntrnos.nu_tram_todo_rfncia, r_trmtes_ntrnos.cdgo_trmtes_ntrnos, r_trmtes_ntrnos.nu_tram_todo_rfncia AS nu_tram_todo_rfncia_trmtes
                            FROM {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos
                            INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                            INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                            INNER JOIN {$this->schema}a_areas a_areas ON (p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area)
                            INNER JOIN {$this->schema}a_tpo_trmtes a_tpo_trmtes ON (p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte)
                            {$joinsRef}
                            WHERE p_dtos_ntrnos.in_esta = '1' AND (r_trmtes_ntrnos.cdgo_crgo_en = '$cargoId') AND (r_trmtes_ntrnos.cdgo_crgo_sa != '$cargoId') AND (r_trmtes_ntrnos.in_esta = '1')";

                if ($faltantes) {
                    // Checkbox "Mostrar solo faltantes" (gene_lista_req_incpleto) — NO es menú Sin Finalizar
                    $sql = $selectEntrada . "
                            AND p_dtos_ntrnos.cdgo_tpo_trmte = 64
                            AND EXISTS (
                                SELECT 1 FROM logistica.p_pgo_prvdres ppp
                                INNER JOIN logistica.h_deta_pgo_prvdres hdp ON ppp.cdgo_pgo_prvdres = hdp.cdgo_pgo_prvdres
                                WHERE ppp.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos AND ppp.in_esta NOT IN (0) AND hdp.in_esta NOT IN (0)
                                AND (COALESCE(TRIM(hdp.de_urls_anxos), '') = '' OR COALESCE(TRIM(hdp.de_urls_adicional), '') = '')
                            ) $where
                            ORDER BY r_trmtes_ntrnos.fe_rcbdo DESC LIMIT 100";
                } else {
                    $sql = $selectEntrada . " $where
                            ORDER BY r_trmtes_ntrnos.fe_rcbdo DESC LIMIT 100";
                }
                break;
        }

        $data = DB::select($sql);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'area_id' => $areaId,
            'cargo_id' => $cargoId
        ]);
    }

    public function historial(Request $request)
    {
        $id = (int) $request->input('id');
        if (empty($id)) {
            return response()->json(['status' => 'error', 'message' => 'ID no proporcionado'], 400);
        }

        $sql = "SELECT r_trmtes_ntrnos.cdgo_trmtes_ntrnos, r_trmtes_ntrnos.fe_rcbdo, r_trmtes_ntrnos.de_obser_ntrnos, a_areas.de_area, a_areas_1.de_area AS de_area_1, r_trmtes_ntrnos.de_urls_agrdo, r_trmtes_ntrnos.in_esta, r_trmtes_ntrnos.in_copia, a_areas.dc_area, a_areas_1.dc_area AS dc_area_1, a_estdos_trmtes.dc_estdo_trmte, p_dtos_ntrnos.fe_ingrso, r_trmtes_ntrnos.cdgo_lgin AS cdgo_lgin, r_trmtes_ntrnos.cdgo_crgo_sa AS cdgo_crgo_sa, r_trmtes_ntrnos.cdgo_crgo_en AS cdgo_crgo_en, p_dtos_ntrnos.nu_tram_todo, r_trmtes_ntrnos.cdgo_dtos_ntrnos_rfrncia AS cdgo_dtos_ntrnos_rfrncia
                FROM {$this->schema}p_crgos p_crgos
                INNER JOIN {$this->schema}a_areas a_areas ON (p_crgos.cdgo_area = a_areas.cdgo_area)
                INNER JOIN {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos ON (r_trmtes_ntrnos.cdgo_crgo_sa = p_crgos.cdgo_crgo)
                INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                INNER JOIN {$this->schema}p_crgos p_crgos_1 ON (r_trmtes_ntrnos.cdgo_crgo_en = p_crgos_1.cdgo_crgo)
                INNER JOIN {$this->schema}a_areas a_areas_1 ON (p_crgos_1.cdgo_area = a_areas_1.cdgo_area)
                WHERE (p_dtos_ntrnos.cdgo_dtos_ntrnos = '$id') 
                ORDER BY r_trmtes_ntrnos.cdgo_trmtes_ntrnos DESC";
        
        $data = DB::select($sql);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function recepcionar(Request $request)
    {
        $ids = $request->input('ids', []);
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($request->input('observaciones', 'SIN NOVEDADES PARA RECEPCIONAR')), 'UTF-8');

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un trámite.'], 400);
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        if (!$cargoId) {
            return response()->json(['status' => 'error', 'message' => 'No tiene un cargo activo en la sesión.'], 400);
        }

        try {
            DB::transaction(function() use ($ids, $fecha, $observaciones, $user, $cargoId, $request) {
                foreach ($ids as $id) {
                    $id = (int)$id;

                    // Buscar el movimiento pendiente de entrada para este trámite hacia el cargo del usuario
                    $movActual = DB::table($this->schema . 'r_trmtes_ntrnos')
                        ->where(function($q) use ($id) {
                            $q->where('cdgo_dtos_ntrnos', $id)
                              ->orWhere('cdgo_trmtes_ntrnos', $id);
                        })
                        ->where('cdgo_crgo_en', $cargoId)
                        ->where('cdgo_crgo_sa', '!=', $cargoId)
                        ->where('in_esta', '1')
                        ->orderBy('cdgo_trmtes_ntrnos', 'desc')
                        ->first();

                    if (!$movActual) {
                        // Búsqueda de contingencia por cdgo_dtos_ntrnos
                        $movActual = DB::table($this->schema . 'r_trmtes_ntrnos')
                            ->where('cdgo_dtos_ntrnos', $id)
                            ->where('cdgo_crgo_en', $cargoId)
                            ->where('in_esta', '1')
                            ->orderBy('cdgo_trmtes_ntrnos', 'desc')
                            ->first();
                    }

                    if ($movActual) {
                        // 1. Deshabilitar el movimiento en la bandeja de entrada
                        DB::table($this->schema . 'r_trmtes_ntrnos')
                            ->where('cdgo_trmtes_ntrnos', $movActual->cdgo_trmtes_ntrnos)
                            ->update(['in_esta' => '0']);

                        // 2. Determinar estado de recepción: 2 = RECEPCIONADO, 23 = RECEP. COPIA
                        $estadoRecep = ($movActual->in_copia != '1') ? 23 : 2;

                        // 3. Insertar nuevo movimiento (Recibido)
                        DB::table($this->schema . 'r_trmtes_ntrnos')->insert([
                            'cdgo_dtos_ntrnos' => $movActual->cdgo_dtos_ntrnos,
                            'cdgo_estdo_trmte' => $estadoRecep,
                            'cdgo_lgin' => $user->cdgo_lgin ?? $movActual->cdgo_lgin,
                            'cdgo_crgo_sa' => $cargoId,
                            'cdgo_crgo_en' => $cargoId,
                            'de_urls_agrdo' => $movActual->de_urls_agrdo ?? '',
                            'fe_rcbdo' => $fecha,
                            'de_obser_ntrnos' => $observaciones,
                            'in_copia' => $movActual->in_copia ?? '1',
                            'in_esta' => '1',
                            'cdgo_dtos_ntrnos_rfrncia' => $movActual->cdgo_dtos_ntrnos_rfrncia ?? null,
                            'nu_tram_todo_rfncia' => $movActual->nu_tram_todo_rfncia ?? null,
                        ]);

                        // 4. Registrar en log
                        try {
                            DB::table($this->schema . 'p_sgmto_log')->insert([
                                'cdgo_lgin' => $user->cdgo_lgin ?? 0,
                                'de_sgto_log' => "Se recepciono tramite interno dto: {$movActual->cdgo_dtos_ntrnos} mov: {$movActual->cdgo_trmtes_ntrnos}",
                                'fe_sgto_log' => now(),
                                'de_ip' => $request->ip()
                            ]);
                        } catch (\Throwable $th) {
                            // Ignorar si falla el log
                        }
                    }
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Trámite(s) recepcionado(s) correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al recepcionar: ' . $e->getMessage()
            ], 500);
        }
    }
}
