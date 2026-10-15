<?php

namespace App\Http\Controllers\mod_oac;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccesoInfoController extends Controller
{
    /**
     * Muestra la lista de expedientes o el detalle de un expediente específico.
     */
    public function index(Request $request)
    {
        $ver_detalle = $request->filled('id');

        if ($ver_detalle) {
            $cdgo_solicitud = (int) $request->input('id');

            if ($cdgo_solicitud <= 0 || $cdgo_solicitud > 999999999) {
                abort(400, 'ID de expediente inválido.');
            }

            // 1. Consulta del Expediente
            $expediente = DB::table('sistema.p_dto_trmtes as dto')
                ->join('sistema.p_usrios as u', 'dto.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->join('sistema.a_tpo_trmtes as at', 'dto.cdgo_tpo_trmte', '=', 'at.cdgo_tpo_trmte')
                ->leftJoin('sistema.h_usrios_ntral as hun', 'u.cdgo_usrios', '=', 'hun.cdgo_usrios')
                ->leftJoin('sistema.h_usrios_jrid as huj', 'u.cdgo_usrios', '=', 'huj.cdgo_usrios')
                ->where('dto.cdgo_dto_trmte', $cdgo_solicitud)
                ->select(
                    'dto.cdgo_dto_trmte',
                    'dto.nu_expe_todo',
                    'dto.fe_ingr_trmte',
                    'dto.de_obse_trmte',
                    'dto.nu_foli_trmte',
                    'u.nu_docu',
                    'u.no_crto_sstma',
                    'hun.no_usrio',
                    'hun.ap_pate',
                    'hun.ap_mate',
                    'huj.rz_soci',
                    'at.de_tpo_trmte'
                )
                ->first();

            if (!$expediente) {
                return view('mod_oac.acceso_informacion.acceso_info', [
                    'ver_detalle' => true,
                    'expediente' => null
                ]);
            }

            // 2. Consulta de la Solicitud Completa (acceso a la información)
            $solicitud_completa = DB::table('acceso_informacion.expedientes_acceso_info as eai')
                ->join('acceso_informacion.solicitudes as s', 'eai.cdgo_vinculacion', '=', 's.cdgo_vinculacion')
                ->where('eai.cdgo_dto_trmte', $cdgo_solicitud)
                ->select('s.*', 'eai.cdgo_vinculacion as vinculacion_expediente')
                ->first();

            // 3. Consulta de Formas de Entrega
            $formas_entrega = DB::table('acceso_informacion.expedientes_acceso_info as eai')
                ->join('acceso_informacion.solicitudes as s', 'eai.cdgo_vinculacion', '=', 's.cdgo_vinculacion')
                ->join('acceso_informacion.solicitudes_formas_entrega as sfe', 's.cdgo_solicitud', '=', 'sfe.cdgo_solicitud')
                ->join('acceso_informacion.a_formas_entrega as afe', 'sfe.cdgo_forma_entrega', '=', 'afe.cdgo_forma_entrega')
                ->where('eai.cdgo_dto_trmte', $cdgo_solicitud)
                ->select(
                    'sfe.cdgo_solicitud_forma_entrega',
                    'sfe.cdgo_forma_entrega',
                    'afe.de_descripcion',
                    'afe.nu_orden'
                )
                ->orderBy('afe.nu_orden')
                ->get();

            // 4. Consulta del Historial de Movimientos
            $resultado_historial = DB::table('sistema.r_trmtes_dcmntos as r')
                ->join('sistema.a_estdos_trmtes as aet', 'r.cdgo_estdo_trmte', '=', 'aet.cdgo_estdo_trmte')
                ->join('sistema.p_lgin as pl', 'r.cdgo_lgin', '=', 'pl.cdgo_lgin')
                ->join('sistema.p_crgos as pc', 'r.cdgo_crgo_en', '=', 'pc.cdgo_crgo')
                ->join('sistema.a_areas as aa', 'pc.cdgo_area', '=', 'aa.cdgo_area')
                ->where('r.cdgo_dto_trmte', $cdgo_solicitud)
                ->select(
                    'r.cdgo_dto_trmte',
                    'r.cdgo_trmte_dcmnto',
                    'r.fe_rcbdo_trmte',
                    'r.cdgo_estdo_trmte',
                    'r.de_urls_imag',
                    'r.cdgo_lgin',
                    'r.de_obse',
                    'aet.de_estdo_trmte',
                    'aa.de_area'
                )
                ->orderBy('r.cdgo_trmte_dcmnto', 'desc')
                ->get();

            return view('mod_oac.acceso_informacion.acceso_info', [
                'ver_detalle' => true,
                'expediente' => $expediente,
                'solicitud_completa' => $solicitud_completa,
                'formas_entrega' => $formas_entrega,
                'resultado_historial' => $resultado_historial
            ]);

        } else {
            // Lógica del Listado General
            $request->validate([
                'numero' => ['nullable', 'string', 'max:50'],
                'documento' => ['nullable', 'string', 'max:20'],
                'estado' => ['nullable', 'string', 'in:PENDIENTE,EN_PROCESO,RESUELTA,RECHAZADA'],
                'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
                'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
                'tipo_expediente' => ['nullable', 'string', 'in:todos,acceso_informacion']
            ]);

            $filtro_numero = $request->input('numero', '');
            $filtro_documento = $request->input('documento', '');
            $filtro_estado = $request->input('estado', '');
            $filtro_fecha_desde = $request->input('fecha_desde', '');
            $filtro_fecha_hasta = $request->input('fecha_hasta', '');
            $filtro_tipo_expediente = $request->input('tipo_expediente', 'acceso_informacion');

            // Construir Query Principal
            $query = DB::table('sistema.p_dto_trmtes as dto')
                ->join('sistema.p_usrios as u', 'dto.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->join('sistema.a_tpo_trmtes as at', 'dto.cdgo_tpo_trmte', '=', 'at.cdgo_tpo_trmte')
                ->leftJoin('sistema.h_usrios_ntral as hun', 'u.cdgo_usrios', '=', 'hun.cdgo_usrios')
                ->leftJoin('sistema.h_usrios_jrid as huj', 'u.cdgo_usrios', '=', 'huj.cdgo_usrios')
                ->select(
                    'dto.cdgo_dto_trmte',
                    'dto.nu_expe_todo',
                    'dto.fe_ingr_trmte',
                    'dto.de_obse_trmte',
                    'u.nu_docu',
                    'u.no_crto_sstma',
                    DB::raw("COALESCE(hun.no_usrio || ' ' || hun.ap_pate || ' ' || hun.ap_mate, huj.rz_soci) as solicitante"),
                    'at.de_tpo_trmte',
                    DB::raw("(SELECT aet.de_estdo_trmte 
                              FROM sistema.r_trmtes_dcmntos r 
                              INNER JOIN sistema.a_estdos_trmtes aet ON r.cdgo_estdo_trmte = aet.cdgo_estdo_trmte
                              WHERE r.cdgo_dto_trmte = dto.cdgo_dto_trmte 
                                AND r.in_esta = '1'
                              ORDER BY r.cdgo_trmte_dcmnto DESC 
                              LIMIT 1) as estado_actual")
                );

            // Aplicar Filtros
            if (!empty($filtro_numero)) {
                $query->where('dto.nu_expe_todo', 'ILIKE', '%' . $filtro_numero . '%');
            }

            if (!empty($filtro_documento)) {
                $query->where('u.nu_docu', 'ILIKE', '%' . $filtro_documento . '%');
            }

            if (!empty($filtro_estado)) {
                // Filtramos por el estado actual de la última transacción
                $query->whereRaw("(
                    SELECT aet.de_estdo_trmte 
                    FROM sistema.r_trmtes_dcmntos r 
                    INNER JOIN sistema.a_estdos_trmtes aet ON r.cdgo_estdo_trmte = aet.cdgo_estdo_trmte
                    WHERE r.cdgo_dto_trmte = dto.cdgo_dto_trmte 
                      AND r.in_esta = '1'
                    ORDER BY r.cdgo_trmte_dcmnto DESC 
                    LIMIT 1
                ) = ?", [$filtro_estado]);
            }

            if (!empty($filtro_fecha_desde)) {
                $query->where('dto.fe_ingr_trmte', '>=', $filtro_fecha_desde . ' 00:00:00');
            }

            if (!empty($filtro_fecha_hasta)) {
                $query->where('dto.fe_ingr_trmte', '<=', $filtro_fecha_hasta . ' 23:59:59');
            }

            if ($filtro_tipo_expediente === 'acceso_informacion') {
                $query->where('at.de_tpo_trmte', 'ACCESO A LA INFORMACION PUBLICA');
            }

            // Asegurar que tengan al menos una transacción activa
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sistema.r_trmtes_dcmntos as r')
                    ->whereColumn('r.cdgo_dto_trmte', 'dto.cdgo_dto_trmte')
                    ->where('r.in_esta', '1');
            });

            $query->orderBy('dto.cdgo_dto_trmte', 'desc');

            $expedientes = $query->paginate(15)->appends($request->except('page'));
            $expedientes->withPath(route('oac.acceso_info'));

            // 5. Consulta de Estadísticas para "ACCESO A LA INFORMACIÓN"
            $stats_raw = DB::table('sistema.p_dto_trmtes as dto')
                ->join('sistema.a_tpo_trmtes as at', 'dto.cdgo_tpo_trmte', '=', 'at.cdgo_tpo_trmte')
                ->where('at.de_tpo_trmte', 'ACCESO A LA INFORMACION PUBLICA')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('sistema.r_trmtes_dcmntos as r')
                        ->whereColumn('r.cdgo_dto_trmte', 'dto.cdgo_dto_trmte')
                        ->where('r.in_esta', '1');
                })
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN (
                        SELECT aet.de_estdo_trmte 
                        FROM sistema.r_trmtes_dcmntos r 
                        INNER JOIN sistema.a_estdos_trmtes aet ON r.cdgo_estdo_trmte = aet.cdgo_estdo_trmte
                        WHERE r.cdgo_dto_trmte = dto.cdgo_dto_trmte 
                          AND r.in_esta = '1'
                        ORDER BY r.cdgo_trmte_dcmnto DESC 
                        LIMIT 1
                    ) = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN (
                        SELECT aet.de_estdo_trmte 
                        FROM sistema.r_trmtes_dcmntos r 
                        INNER JOIN sistema.a_estdos_trmtes aet ON r.cdgo_estdo_trmte = aet.cdgo_estdo_trmte
                        WHERE r.cdgo_dto_trmte = dto.cdgo_dto_trmte 
                          AND r.in_esta = '1'
                        ORDER BY r.cdgo_trmte_dcmnto DESC 
                        LIMIT 1
                    ) = 'EN_PROCESO' THEN 1 ELSE 0 END) as en_proceso,
                    SUM(CASE WHEN (
                        SELECT aet.de_estdo_trmte 
                        FROM sistema.r_trmtes_dcmntos r 
                        INNER JOIN sistema.a_estdos_trmtes aet ON r.cdgo_estdo_trmte = aet.cdgo_estdo_trmte
                        WHERE r.cdgo_dto_trmte = dto.cdgo_dto_trmte 
                          AND r.in_esta = '1'
                        ORDER BY r.cdgo_trmte_dcmnto DESC 
                        LIMIT 1
                    ) = 'RESUELTA' THEN 1 ELSE 0 END) as resueltas
                ")
                ->first();

            $stats = [
                'total' => $stats_raw->total ?? 0,
                'pendientes' => $stats_raw->pendientes ?? 0,
                'en_proceso' => $stats_raw->en_proceso ?? 0,
                'resueltas' => $stats_raw->resueltas ?? 0,
            ];

            return view('mod_oac.acceso_informacion.acceso_info', [
                'ver_detalle' => false,
                'expedientes' => $expedientes,
                'stats' => $stats,
                'filtro_numero' => $filtro_numero,
                'filtro_documento' => $filtro_documento,
                'filtro_estado' => $filtro_estado,
                'filtro_fecha_desde' => $filtro_fecha_desde,
                'filtro_fecha_hasta' => $filtro_fecha_hasta,
                'filtro_tipo_expediente' => $filtro_tipo_expediente
            ]);
        }
    }
}
