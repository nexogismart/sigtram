<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ExpedienteFinalizadoMail;
use App\Models\Cargo;
use App\Models\DtoTramite;
use App\Models\TramiteDocumento;
use App\Models\SeguimientoLog;
use Carbon\Carbon;

class CorreoExternoController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * Muestra la bandeja de seguimiento de expedientes (correo externo)
     */
    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            // Obtener el primer cargo activo del usuario si no está en sesión
            $cargoId = DB::table($this->schema . 'r_crgos_lgin')
                ->where('cdgo_lgin', $user->cdgo_lgin)
                ->value('cdgo_crgo') ?? 0;
            if ($cargoId > 0) {
                $request->session()->put('sigtram_cargo_id', $cargoId);
            }
        }

        $cargoFila = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        // 2. Cargar jerarquía de Áreas
        $allAreas = DB::table($this->schema . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $cmpnia)
            ->orderBy('de_area', 'asc')
            ->get();

        $areasList = [];
        $gerencias = $allAreas->whereNull('cdgo_area_prim');
        foreach ($gerencias as $ger) {
            $areasList[] = [
                'id' => $ger->cdgo_area,
                'nombre' => $ger->de_area,
                'level' => 0,
                'cdgo_area_prim' => null
            ];
            $subs = $allAreas->where('cdgo_area_prim', $ger->cdgo_area);
            foreach ($subs as $sub) {
                $areasList[] = [
                    'id' => $sub->cdgo_area,
                    'nombre' => '↪ ' . $sub->de_area,
                    'level' => 1,
                    'cdgo_area_prim' => $ger->cdgo_area
                ];
                $subSubs = $allAreas->where('cdgo_area_prim', $sub->cdgo_area);
                foreach ($subSubs as $ss) {
                    $areasList[] = [
                        'id' => $ss->cdgo_area,
                        'nombre' => '↪↪ ' . $ss->de_area,
                        'level' => 2,
                        'cdgo_area_prim' => $sub->cdgo_area
                    ];
                }
            }
        }

        // 3. Cargar cargos del mismo área (excluyendo el actual)
        $cargosList = DB::table($this->schema . 'p_crgos as c')
            ->join($this->schema . 'r_crgos_lgin as rl', 'c.cdgo_crgo', '=', 'rl.cdgo_crgo')
            ->join($this->schema . 'p_lgin as l', 'rl.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->join($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select('c.cdgo_crgo', 'c.de_crgo', 'u.no_crto')
            ->where('c.cdgo_area', $areaId)
            ->where('c.cdgo_crgo', '<>', $cargoId)
            ->where('c.in_esta', '1')
            ->where('u.cdgo_usrios', '<>', 8) // Excluir usuario sistema/admin
            ->orderBy('u.no_crto', 'asc')
            ->get();

        // 4. Cargar tipos de documentos para trámite interno permitidos en el área
        $tiposDocumento = DB::table($this->schema . 'r_tpo_trmte_areas as r')
            ->join($this->schema . 'a_tpo_trmtes as t', 'r.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->select('t.cdgo_tpo_trmte', 't.de_tpo_trmte')
            ->where('r.in_esta', '1')
            ->where('r.cdgo_area', $areaId)
            ->orderBy('t.de_tpo_trmte', 'asc')
            ->get();

        // 5. Contadores reales de bandeja
        $countEntrada = DB::table($this->schema . 'r_trmtes_dcmntos as r_trmtes_dcmntos')
            ->join($this->schema . 'p_crgos as p_crgos_en', 'r_trmtes_dcmntos.cdgo_crgo_en', '=', 'p_crgos_en.cdgo_crgo')
            ->join($this->schema . 'p_dto_trmtes as p_dto_trmtes', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->where(function($q) use ($cargoId) {
                $q->where(function($q2) use ($cargoId) {
                    $q2->where('r_trmtes_dcmntos.cdgo_crgo_en', $cargoId)
                       ->where('r_trmtes_dcmntos.cdgo_crgo_sa', '<>', $cargoId)
                       ->where('r_trmtes_dcmntos.in_esta', '1')
                       ->whereNotIn('r_trmtes_dcmntos.cdgo_estdo_trmte', ['5','12','29']);
                })->orWhere(function($q3) use ($cargoId) {
                    $q3->where('r_trmtes_dcmntos.cdgo_crgo_en', $cargoId)
                       ->whereIn('r_trmtes_dcmntos.cdgo_estdo_trmte', ['25','51'])
                       ->where('r_trmtes_dcmntos.in_esta', '1');
                });
            })
            ->where('p_dto_trmtes.cdgo_cmpnia', $cmpnia)
            ->count();

        $countRecibidos = DB::table($this->schema . 'r_trmtes_dcmntos as r_trmtes_dcmntos')
            ->join($this->schema . 'p_dto_trmtes as p_dto_trmtes', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->where('r_trmtes_dcmntos.cdgo_crgo_en', $cargoId)
            ->where('r_trmtes_dcmntos.cdgo_crgo_sa', $cargoId)
            ->where('r_trmtes_dcmntos.in_esta', '1')
            ->whereNotIn('r_trmtes_dcmntos.cdgo_estdo_trmte', ['3', '26', '27'])
            ->where('p_dto_trmtes.cdgo_cmpnia', $cmpnia)
            ->count();

        $estadosList = DB::table($this->schema . 'a_estdos_trmtes')
            ->select('cdgo_estdo_trmte', 'dc_estdo_trmte')
            ->orderBy('dc_estdo_trmte', 'asc')
            ->get();

        return view($this->vistaDesdeMenu($pathVista, $opcionMenu), [
                'areasList' => $areasList,
                'cargosList' => $cargosList,
                'tiposDocumento' => $tiposDocumento,
                'countEntrada' => $countEntrada,
                'countRecibidos' => $countRecibidos,
                'estadosList' => $estadosList,
                'bandejaInicial' => (string) $request->query('bandeja', 'entrada'),
                'puedeExportarExcel' => in_array((int) ($user->cdgo_lgin ?? 0), [15844, 411, 15481, 15749], true),
            ]);
    }

    /**
     * Consulta y filtra los expedientes de la bandeja seleccionada
     */
    public function tabla(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'error', 'message' => 'No autenticado'], 401);
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Seleccione un cargo activo'], 400);
        }

        $cargoFila = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        $bandeja = (string) $request->input('bandeja', 'entrada');
        $criterio = (string) $request->input('criterio', 'expe_todo');
        $descripcion = trim((string) $request->input('descripcion', ''));
        $desde = (string) $request->input('desde', '');
        $hasta = (string) $request->input('hasta', '');
        $reporte = (string) $request->input('reporte', 'todos');

        $estado = (string) $request->input('estado', '0');
        $page = (int) $request->input('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $perPage = (int) $request->input('per_page', $request->input('txt_cantidad', 8));
        if ($perPage < 1) {
            $perPage = 8;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }
        $offset = ($page - 1) * $perPage;

        $search = $this->buildSearchWhere($criterio, $descripcion);
        $dateField = ($bandeja === 'todos' && $reporte !== 'sin_finalizar')
            ? 'p_dto_trmtes.fe_ingr_trmte'
            : 'r_trmtes_dcmntos.fe_rcbdo_trmte';

        if ($desde !== '' && $hasta !== '') {
            $desde = preg_replace('/[^0-9\-]/', '', $desde);
            $hasta = preg_replace('/[^0-9\-]/', '', $hasta);
            if ($desde !== '' && $hasta !== '') {
                $search .= " AND {$dateField} >= '{$desde} 00:00:00' AND {$dateField} <= '{$hasta} 23:59:59' ";
            }
        }

        if ($estado !== '0' && $estado !== '') {
            $search .= " AND r_trmtes_dcmntos.cdgo_estdo_trmte = '" . intval($estado) . "' ";
        }

        $whereBandeja = $this->buildBandejaWhere($bandeja, $cargoId, $areaId, $reporte);

        $baseQuery = $this->baseFromJoin()
            . ' WHERE p_dto_trmtes.cdgo_cmpnia = ' . $cmpnia . $whereBandeja . $search;

        $sqlCount = "SELECT COUNT(*) as total " . $baseQuery;

        try {
            $totalRows = DB::select($sqlCount)[0]->total ?? 0;
            $lastPage = max(1, ceil($totalRows / $perPage));
            if ($page > $lastPage) {
                $page = $lastPage;
                $offset = ($page - 1) * $perPage;
            }

            $sql = $this->baseSelect() . $baseQuery
                . ' ORDER BY r_trmtes_dcmntos.fe_rcbdo_trmte DESC NULLS LAST LIMIT ' . $perPage . ' OFFSET ' . $offset;

            $rows = DB::select($sql);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar la bandeja: ' . $e->getMessage(),
            ], 500);
        }

        $data = array_map(function ($row) {
            $a = (array) $row;
            $a['tramite_formateado'] = $this->formatNumeroExpediente($a['nu_expe_todo'] ?? null);
            $a['referencia'] = $a['de_docmnto_trmte'] ?? ($a['dc_area'] ?? '—');
            
            // Semaforización y datos de días
            $a['dias_acumulados'] = $this->calcularDiasTranscurridos($a['fe_ingr_trmte']);
            $a['nu_tiem_sile'] = (int) ($row->nu_tiem_sile ?? 0);
            $a['nu_tiem_sile_adcnal'] = (int) ($row->nu_tiem_sile_adcnal ?? 0);
            $a['orig_doc'] = $row->in_docu_trmte ?? 'E';
            
            // Check de solicitudes de video
            $a['has_video'] = DB::table($this->schema . 'p_solicitud_video')
                ->where('cdgo_dto_trmte', $row->cdgo_dto_trmte)
                ->where('in_esta', '1')
                ->exists();

            return (object) $a;
        }, $rows);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'cargo_id' => $cargoId,
            'area_id' => $areaId,
            'pagination' => [
                'total' => $totalRows,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage
            ]
        ]);
    }

    /**
     * Consulta el historial de movimientos de un expediente (Traza)
     */
    public function historial(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID inválido'], 400);
        }

        $historial = DB::table($this->schema . 'r_trmtes_dcmntos as r')
            ->leftJoin($this->schema . 'p_crgos as p_crgos_en', 'r.cdgo_crgo_en', '=', 'p_crgos_en.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a_areas_en', 'p_crgos_en.cdgo_area', '=', 'a_areas_en.cdgo_area')
            ->leftJoin($this->schema . 'p_crgos as p_crgos_sa', 'r.cdgo_crgo_sa', '=', 'p_crgos_sa.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a_areas_sa', 'p_crgos_sa.cdgo_area', '=', 'a_areas_sa.cdgo_area')
            ->leftJoin($this->schema . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select(
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte as fe_entrada',
                'a_areas_sa.de_area as area_origen',
                'a_areas_en.de_area as area',
                'a_estdos_trmtes.dc_estdo_trmte as estado',
                'r.de_obse as observaciones',
                'r.de_urls_imag',
                'u.no_crto as usuario'
            )
            ->where('r.cdgo_dto_trmte', $id)
            ->orderBy('r.cdgo_trmte_dcmnto', 'asc')
            ->get();

        $data = $historial->map(function($mov, $index) use ($historial) {
            $nextMov = $historial->get($index + 1);
            $mov->dias = $this->calcularTiempoTranscurrido($mov->fe_entrada, $nextMov ? $nextMov->fe_entrada : null);
            $mov->fe_entrada = $mov->fe_entrada ? Carbon::parse($mov->fe_entrada)->format('d/m/Y H:i') : '—';
            return $mov;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Recepciona expedientes en bloque (Bandeja de Entrada -> Recibidos)
     */
    public function recepcionar(Request $request)
    {
        $ids = $request->input('ids', []);
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($request->input('observaciones', 'SIN NOVEDADES PARA RECEPCIONAR')), 'UTF-8');

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un expediente.'], 400);
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        try {
            DB::transaction(function() use ($ids, $fecha, $observaciones, $user, $cargoId, $request) {
                foreach ($ids as $id) {
                    $id = (int)$id;

                    // Deshabilitar movimiento actual en la bandeja
                    DB::table($this->schema . 'r_trmtes_dcmntos')
                        ->where('cdgo_dto_trmte', $id)
                        ->where('in_esta', '1')
                        ->update(['in_esta' => '0']);

                    // Insertar nuevo movimiento (Recibido = 2)
                    DB::table($this->schema . 'r_trmtes_dcmntos')->insert([
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $cargoId,
                        'cdgo_dto_trmte' => $id,
                        'cdgo_estdo_trmte' => 2, // 2 = Recibido
                        'fe_rcbdo_trmte' => $fecha,
                        'de_obse' => $observaciones,
                        'in_esta' => '1',
                        'de_urls_imag' => '',
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'nu_foli_anex' => null,
                        'fe_cta_vrtal' => null,
                        'nu_anxo' => null,
                    ]);

                    // Actualizar bitácora del expediente
                    $currentObse = DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->value('de_obse_todo') ?? '';
                    $newObse = $currentObse !== '' ? $currentObse . ' - ' . $observaciones : $observaciones;
                    DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->update(['de_obse_todo' => mb_strtoupper($newObse, 'UTF-8')]);

                    // Registrar log legacy
                    DB::table($this->schema . 'p_sgmto_log')->insert([
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'de_sgto_log' => "Se recepciono el expediente {$id}",
                        'fe_sgto_log' => now(),
                        'de_ip' => $request->ip()
                    ]);
                }
            });

            return response()->json(['status' => 'success', 'message' => 'Los expedientes fueron recepcionados correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al recepcionar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deriva expedientes a otra gerencia/subgerencia
     */
    public function derivar(Request $request)
    {
        $ids = $request->input('ids', []);
        $gerenciaDest = (int) $request->input('gerencia');
        $subgerenciaDest = (int) $request->input('subgerencia');
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($request->input('observaciones', 'SIN NOVEDADES PARA ENVIAR')), 'UTF-8');
        $folios = (int) $request->input('folios', 1);

        $enviarConTramite = $request->boolean('enviar_con_tramite');
        $docTipo = (int) $request->input('doc_tipo');
        $tramiteNro = $request->input('tramite_nro'); // e.g. "0142-2026"

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un expediente.'], 400);
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        $cargoFila = DB::table($this->schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        // Resolver Cargo de Destino
        $destAreaId = $subgerenciaDest > 0 ? $subgerenciaDest : $gerenciaDest;
        if ($destAreaId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar un área de destino.'], 400);
        }

        $destCargoId = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_area', $destAreaId)
            ->where('nu_orde', 1)
            ->where('in_esta', '1')
            ->orderBy('cdgo_crgo', 'asc')
            ->value('cdgo_crgo');

        if (!$destCargoId) {
            return response()->json(['status' => 'error', 'message' => 'No se encontró un cargo responsable activo en el área destino.'], 400);
        }

        try {
            DB::transaction(function() use ($ids, $fecha, $observaciones, $folios, $destCargoId, $destAreaId, $enviarConTramite, $docTipo, $tramiteNro, $user, $cargoId, $areaId, $cmpnia, $request) {
                // Resolver Año Fiscal
                $anioId = DB::table($this->schema . 'a_anios')
                    ->where('de_anio', date('Y'))
                    ->where('cdgo_cmpnia', $cmpnia)
                    ->value('cdgo_anio');

                if (!$anioId) {
                    $anioId = DB::table($this->schema . 'a_anios')->insertGetId([
                        'de_anio' => date('Y'),
                        'dc_anio' => substr(date('Y'), 2, 2),
                        'cdgo_cmpnia' => $cmpnia
                    ], 'cdgo_anio');
                }

                foreach ($ids as $id) {
                    $id = (int)$id;
                    $idInternalDoc = null;
                    $anexoTexto = '';

                    // 1. Crear Trámite Interno Asociado si corresponde
                    if ($enviarConTramite && $docTipo > 0) {
                        $expeTodo = DB::table($this->schema . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->value('nu_expe_todo') ?? '—';
                        $referenciaObse = "CON REFERENCIA AL EXPEDIENTE N° " . $expeTodo;
                        
                        // Generar número secuencial correlativo
                        $nextSeed = $this->generarSeedNumber($docTipo, $anioId, $areaId, $cargoId, $user->cdgo_lgin);
                        $formattedSeed = $nextSeed . '-' . date('Y');
                        
                        // Generar título del trámite interno
                        $tituloCompleto = $this->generarTituloTramiteInterno($docTipo, $formattedSeed, $areaId, $cargoId, $user->cdgo_lgin);
                        
                        $idInternalDoc = DB::table($this->schema . 'p_dtos_ntrnos')->insertGetId([
                            'cdgo_tpo_trmte' => $docTipo,
                            'cdgo_anio' => $anioId,
                            'cdgo_area' => $areaId,
                            'cdgo_lgin' => $user->cdgo_lgin,
                            'cdgo_cmpnia' => $cmpnia,
                            'cdgo_dto_trmte' => $id,
                            'cdgo_dtos_ntrnos_pri' => null,
                            'nu_tram_todo' => $nextSeed . '-' . date('Y'),
                            'nu_tram_cmplto' => $tituloCompleto,
                            'nu_tram' => $nextSeed,
                            'de_obser' => mb_strtoupper($referenciaObse, 'UTF-8'),
                            'de_urls_adjnto' => '',
                            'fe_ingrso' => $fecha,
                            'in_envo_fsco' => '0',
                            'in_prrdad_trmte' => 1
                        ], 'cdgo_dtos_ntrnos');

                        // Registrar movimiento del trámite interno
                        DB::table($this->schema . 'r_trmtes_ntrnos')->insert([
                            'cdgo_dtos_ntrnos' => $idInternalDoc,
                            'cdgo_estdo_trmte' => 1, // 1 = Pendiente
                            'cdgo_lgin' => $user->cdgo_lgin,
                            'cdgo_crgo_sa' => $cargoId,
                            'cdgo_crgo_en' => $cargoId,
                            'de_urls_agrdo' => '',
                            'fe_rcbdo' => $fecha,
                            'de_obser_ntrnos' => mb_strtoupper($observaciones, 'UTF-8'),
                            'in_copia' => '1'
                        ]);

                        $docTypeName = DB::table($this->schema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $docTipo)->value('de_tpo_trmte') ?? 'TRÁMITE';
                        $anexoTexto = "; SE DERIVA CON {$docTypeName} N° " . str_pad($nextSeed, 8, '0', STR_PAD_LEFT) . "-" . date('Y') . ".";
                    }

                    // 2. Deshabilitar movimiento actual del expediente externo
                    DB::table($this->schema . 'r_trmtes_dcmntos')
                        ->where('cdgo_dto_trmte', $id)
                        ->where('in_esta', '1')
                        ->update(['in_esta' => '0']);

                    // 3. Crear el nuevo movimiento del expediente externo
                    $finalObse = mb_strtoupper($observaciones . $anexoTexto, 'UTF-8');
                    $newMovimientoId = DB::table($this->schema . 'r_trmtes_dcmntos')->insertGetId([
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $destCargoId,
                        'cdgo_dto_trmte' => $id,
                        'cdgo_estdo_trmte' => 1, // 1 = Pendiente en destino
                        'fe_rcbdo_trmte' => $fecha,
                        'de_obse' => $finalObse,
                        'in_esta' => '1',
                        'de_urls_imag' => '',
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'nu_foli_anex' => $folios,
                        'fe_cta_vrtal' => null,
                        'nu_anxo' => null,
                        'cdgo_dtos_ntrnos' => $idInternalDoc // Enlazar trámite interno
                    ], 'cdgo_trmte_dcmnto');

                    // 4. Procesar adjuntos (ZIP o PDF)
                    $attachments = $this->processAttachments($request, 'fle_adjunto', 'fle_adjunto_pdf', $newMovimientoId, $idInternalDoc);
                    if ($attachments['filename'] !== '') {
                        DB::table($this->schema . 'r_trmtes_dcmntos')
                            ->where('cdgo_trmte_dcmnto', $newMovimientoId)
                            ->update(['de_urls_imag' => $attachments['filename']]);

                        if ($idInternalDoc) {
                            DB::table($this->schema . 'p_dtos_ntrnos')
                                ->where('cdgo_dtos_ntrnos', $idInternalDoc)
                                ->update(['de_urls_adjnto' => $attachments['filename_internal']]);

                            DB::table($this->schema . 'r_trmtes_ntrnos')
                                ->where('cdgo_dtos_ntrnos', $idInternalDoc)
                                ->update(['de_urls_agrdo' => $attachments['filename_internal']]);
                        }
                    }

                    // 5. Concatenar observaciones generales al expediente externo
                    $currentObse = DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->value('de_obse_todo') ?? '';
                    $newObseTodo = $currentObse !== '' ? $currentObse . ' - ' . $finalObse : $finalObse;
                    DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->update(['de_obse_todo' => mb_strtoupper($newObseTodo, 'UTF-8')]);

                    // 6. Registrar log legacy
                    DB::table($this->schema . 'p_sgmto_log')->insert([
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'de_sgto_log' => "Se derivo el expediente {$id} al cargo {$destCargoId}",
                        'fe_sgto_log' => now(),
                        'de_ip' => $request->ip()
                    ]);
                }
            });

            return response()->json(['status' => 'success', 'message' => 'Los expedientes fueron derivados exitosamente.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al derivar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Asigna expedientes a un personal de su propia área
     */
    public function asignar(Request $request)
    {
        $ids = $request->input('ids', []);
        $personalCargoId = (int) $request->input('cargo');
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($request->input('observaciones', 'SIN NOVEDADES PARA ASIGNAR')), 'UTF-8');
        $folios = (int) $request->input('folios', 1);

        $enviarConTramite = $request->boolean('enviar_con_tramite');
        $docTipo = (int) $request->input('doc_tipo');

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un expediente.'], 400);
        }

        if ($personalCargoId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar un responsable para asignar.'], 400);
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        $cargoFila = DB::table($this->schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        try {
            DB::transaction(function() use ($ids, $fecha, $observaciones, $folios, $personalCargoId, $enviarConTramite, $docTipo, $user, $cargoId, $areaId, $cmpnia, $request) {
                $anioId = DB::table($this->schema . 'a_anios')
                    ->where('de_anio', date('Y'))
                    ->where('cdgo_cmpnia', $cmpnia)
                    ->value('cdgo_anio');

                if (!$anioId) {
                    $anioId = DB::table($this->schema . 'a_anios')->insertGetId([
                        'de_anio' => date('Y'),
                        'dc_anio' => substr(date('Y'), 2, 2),
                        'cdgo_cmpnia' => $cmpnia
                    ], 'cdgo_anio');
                }

                foreach ($ids as $id) {
                    $id = (int)$id;
                    $idInternalDoc = null;
                    $anexoTexto = '';

                    // 1. Crear Trámite Interno Asociado si corresponde
                    if ($enviarConTramite && $docTipo > 0) {
                        $expeTodo = DB::table($this->schema . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->value('nu_expe_todo') ?? '—';
                        $referenciaObse = "CON REFERENCIA AL EXPEDIENTE N° " . $expeTodo;
                        
                        $nextSeed = $this->generarSeedNumber($docTipo, $anioId, $areaId, $cargoId, $user->cdgo_lgin);
                        $formattedSeed = $nextSeed . '-' . date('Y');
                        $tituloCompleto = $this->generarTituloTramiteInterno($docTipo, $formattedSeed, $areaId, $cargoId, $user->cdgo_lgin);
                        
                        $idInternalDoc = DB::table($this->schema . 'p_dtos_ntrnos')->insertGetId([
                            'cdgo_tpo_trmte' => $docTipo,
                            'cdgo_anio' => $anioId,
                            'cdgo_area' => $areaId,
                            'cdgo_lgin' => $user->cdgo_lgin,
                            'cdgo_cmpnia' => $cmpnia,
                            'cdgo_dto_trmte' => $id,
                            'cdgo_dtos_ntrnos_pri' => null,
                            'nu_tram_todo' => $nextSeed . '-' . date('Y'),
                            'nu_tram_cmplto' => $tituloCompleto,
                            'nu_tram' => $nextSeed,
                            'de_obser' => mb_strtoupper($referenciaObse, 'UTF-8'),
                            'de_urls_adjnto' => '',
                            'fe_ingrso' => $fecha,
                            'in_envo_fsco' => '0',
                            'in_prrdad_trmte' => 1
                        ], 'cdgo_dtos_ntrnos');

                        DB::table($this->schema . 'r_trmtes_ntrnos')->insert([
                            'cdgo_dtos_ntrnos' => $idInternalDoc,
                            'cdgo_estdo_trmte' => 1,
                            'cdgo_lgin' => $user->cdgo_lgin,
                            'cdgo_crgo_sa' => $cargoId,
                            'cdgo_crgo_en' => $cargoId,
                            'de_urls_agrdo' => '',
                            'fe_rcbdo' => $fecha,
                            'de_obser_ntrnos' => mb_strtoupper($observaciones, 'UTF-8'),
                            'in_copia' => '1'
                        ]);

                        $docTypeName = DB::table($this->schema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $docTipo)->value('de_tpo_trmte') ?? 'TRÁMITE';
                        $anexoTexto = "; CON {$docTypeName} N° " . str_pad($nextSeed, 8, '0', STR_PAD_LEFT) . "-" . date('Y') . ".";
                    }

                    // 2. Deshabilitar movimiento actual del expediente externo
                    DB::table($this->schema . 'r_trmtes_dcmntos')
                        ->where('cdgo_dto_trmte', $id)
                        ->where('in_esta', '1')
                        ->update(['in_esta' => '0']);

                    // 3. Crear el nuevo movimiento (Asignado = 7)
                    $finalObse = mb_strtoupper($observaciones . $anexoTexto, 'UTF-8');
                    $newMovimientoId = DB::table($this->schema . 'r_trmtes_dcmntos')->insertGetId([
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $personalCargoId,
                        'cdgo_dto_trmte' => $id,
                        'cdgo_estdo_trmte' => 7, // 7 = Asignado
                        'fe_rcbdo_trmte' => $fecha,
                        'de_obse' => $finalObse,
                        'in_esta' => '1',
                        'de_urls_imag' => '',
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'nu_foli_anex' => $folios,
                        'fe_cta_vrtal' => null,
                        'nu_anxo' => null,
                        'cdgo_dtos_ntrnos' => $idInternalDoc
                    ], 'cdgo_trmte_dcmnto');

                    // 4. Procesar adjuntos
                    $attachments = $this->processAttachments($request, 'fle_adjunto', 'fle_adjunto_pdf', $newMovimientoId, $idInternalDoc);
                    if ($attachments['filename'] !== '') {
                        DB::table($this->schema . 'r_trmtes_dcmntos')
                            ->where('cdgo_trmte_dcmnto', $newMovimientoId)
                            ->update(['de_urls_imag' => $attachments['filename']]);

                        if ($idInternalDoc) {
                            DB::table($this->schema . 'p_dtos_ntrnos')
                                ->where('cdgo_dtos_ntrnos', $idInternalDoc)
                                ->update(['de_urls_adjnto' => $attachments['filename_internal']]);
                        }
                    }

                    // 5. Concatenar observaciones generales al expediente externo
                    $currentObse = DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->value('de_obse_todo') ?? '';
                    $newObseTodo = $currentObse !== '' ? $currentObse . ' - ' . $finalObse : $finalObse;
                    DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->update(['de_obse_todo' => mb_strtoupper($newObseTodo, 'UTF-8')]);

                    // 6. Registrar log legacy
                    DB::table($this->schema . 'p_sgmto_log')->insert([
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'de_sgto_log' => "Se asigno el expediente {$id} al cargo {$personalCargoId}",
                        'fe_sgto_log' => now(),
                        'de_ip' => $request->ip()
                    ]);
                }
            });

            return response()->json(['status' => 'success', 'message' => 'Los expedientes fueron asignados correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al asignar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envía expedientes en paralelo a múltiples áreas de destino
     */
    public function enviar(Request $request)
    {
        $ids = $request->input('ids', []);
        $areasDestino = $request->input('areas', []); // Array de IDs de áreas
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($request->input('observaciones', 'SIN NOVEDADES PARA ENVIAR')), 'UTF-8');
        $folios = (int) $request->input('folios', 1);

        $enviarConTramite = $request->boolean('enviar_con_tramite');
        $docTipo = (int) $request->input('doc_tipo');

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un expediente.'], 400);
        }

        if (empty($areasDestino)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un área de destino.'], 400);
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        $cargoFila = DB::table($this->schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        try {
            DB::transaction(function() use ($ids, $fecha, $observaciones, $folios, $areasDestino, $enviarConTramite, $docTipo, $user, $cargoId, $areaId, $cmpnia, $request) {
                $anioId = DB::table($this->schema . 'a_anios')
                    ->where('de_anio', date('Y'))
                    ->where('cdgo_cmpnia', $cmpnia)
                    ->value('cdgo_anio');

                if (!$anioId) {
                    $anioId = DB::table($this->schema . 'a_anios')->insertGetId([
                        'de_anio' => date('Y'),
                        'dc_anio' => substr(date('Y'), 2, 2),
                        'cdgo_cmpnia' => $cmpnia
                    ], 'cdgo_anio');
                }

                foreach ($ids as $id) {
                    $id = (int)$id;
                    $idInternalDoc = null;
                    $anexoTexto = '';

                    // 1. Crear Trámite Interno Asociado si corresponde
                    if ($enviarConTramite && $docTipo > 0) {
                        $expeTodo = DB::table($this->schema . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->value('nu_expe_todo') ?? '—';
                        $referenciaObse = "CON REFERENCIA AL EXPEDIENTE N° " . $expeTodo;
                        
                        $nextSeed = $this->generarSeedNumber($docTipo, $anioId, $areaId, $cargoId, $user->cdgo_lgin);
                        $formattedSeed = $nextSeed . '-' . date('Y');
                        $tituloCompleto = $this->generarTituloTramiteInterno($docTipo, $formattedSeed, $areaId, $cargoId, $user->cdgo_lgin);
                        
                        $idInternalDoc = DB::table($this->schema . 'p_dtos_ntrnos')->insertGetId([
                            'cdgo_tpo_trmte' => $docTipo,
                            'cdgo_anio' => $anioId,
                            'cdgo_area' => $areaId,
                            'cdgo_lgin' => $user->cdgo_lgin,
                            'cdgo_cmpnia' => $cmpnia,
                            'cdgo_dto_trmte' => $id,
                            'cdgo_dtos_ntrnos_pri' => null,
                            'nu_tram_todo' => $nextSeed . '-' . date('Y'),
                            'nu_tram_cmplto' => $tituloCompleto,
                            'nu_tram' => $nextSeed,
                            'de_obser' => mb_strtoupper($referenciaObse, 'UTF-8'),
                            'de_urls_adjnto' => '',
                            'fe_ingrso' => $fecha,
                            'in_envo_fsco' => '0',
                            'in_prrdad_trmte' => 1
                        ], 'cdgo_dtos_ntrnos');

                        // Enviar copias del trámite interno a todas las áreas seleccionadas
                        foreach ($areasDestino as $destAreaId) {
                            $destCargoId = DB::table($this->schema . 'p_crgos')
                                ->where('cdgo_area', (int)$destAreaId)
                                ->where('nu_orde', 1)
                                ->where('in_esta', '1')
                                ->orderBy('cdgo_crgo', 'asc')
                                ->value('cdgo_crgo');

                            if ($destCargoId) {
                                DB::table($this->schema . 'r_trmtes_ntrnos')->insert([
                                    'cdgo_dtos_ntrnos' => $idInternalDoc,
                                    'cdgo_estdo_trmte' => 1,
                                    'cdgo_lgin' => $user->cdgo_lgin,
                                    'cdgo_crgo_sa' => $cargoId,
                                    'cdgo_crgo_en' => $destCargoId,
                                    'de_urls_agrdo' => '',
                                    'fe_rcbdo' => $fecha,
                                    'de_obser_ntrnos' => mb_strtoupper($observaciones, 'UTF-8'),
                                    'in_copia' => '1'
                                ]);
                            }
                        }

                        $docTypeName = DB::table($this->schema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $docTipo)->value('de_tpo_trmte') ?? 'TRÁMITE';
                        $anexoTexto = "; SE DERIVA CON {$docTypeName} N° " . str_pad($nextSeed, 8, '0', STR_PAD_LEFT) . "-" . date('Y') . ", ";
                    }

                    // 2. Deshabilitar movimiento actual del expediente externo
                    DB::table($this->schema . 'r_trmtes_dcmntos')
                        ->where('cdgo_dto_trmte', $id)
                        ->where('in_esta', '1')
                        ->update(['in_esta' => '0']);

                    // 3. Crear el nuevo movimiento en el expediente externo (Estado = 8, Copia/Derivado con tramite)
                    // Nota: el expediente externo se queda en la bandeja del operador con estado 8
                    $finalObse = mb_strtoupper($observaciones . $anexoTexto, 'UTF-8');
                    $newMovimientoId = DB::table($this->schema . 'r_trmtes_dcmntos')->insertGetId([
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $cargoId, // El expediente externo queda en su propio cargo con estado derivado
                        'cdgo_dto_trmte' => $id,
                        'cdgo_estdo_trmte' => 8, // 8 = Derivado con trámite interno
                        'fe_rcbdo_trmte' => $fecha,
                        'de_obse' => $finalObse,
                        'in_esta' => '1',
                        'de_urls_imag' => '',
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'nu_foli_anex' => $folios,
                        'fe_cta_vrtal' => null,
                        'nu_anxo' => null,
                        'cdgo_dtos_ntrnos' => $idInternalDoc
                    ], 'cdgo_trmte_dcmnto');

                    // 4. Procesar adjuntos
                    $attachments = $this->processAttachments($request, 'fle_adjunto', 'fle_adjunto_pdf', $newMovimientoId, $idInternalDoc);
                    if ($attachments['filename'] !== '') {
                        DB::table($this->schema . 'r_trmtes_dcmntos')
                            ->where('cdgo_trmte_dcmnto', $newMovimientoId)
                            ->update(['de_urls_imag' => $attachments['filename']]);

                        if ($idInternalDoc) {
                            DB::table($this->schema . 'p_dtos_ntrnos')
                                ->where('cdgo_dtos_ntrnos', $idInternalDoc)
                                ->update(['de_urls_adjnto' => $attachments['filename_internal']]);

                            DB::table($this->schema . 'r_trmtes_ntrnos')
                                ->where('cdgo_dtos_ntrnos', $idInternalDoc)
                                ->update(['de_urls_agrdo' => $attachments['filename_internal']]);
                        }
                    }

                    // 5. Concatenar observaciones generales al expediente externo
                    $currentObse = DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->value('de_obse_todo') ?? '';
                    $newObseTodo = $currentObse !== '' ? $currentObse . ' - ' . $finalObse : $finalObse;
                    DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->update(['de_obse_todo' => mb_strtoupper($newObseTodo, 'UTF-8')]);

                    // 6. Registrar log legacy
                    DB::table($this->schema . 'p_sgmto_log')->insert([
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'de_sgto_log' => "Se envio tramite interno el expediente {$id}",
                        'fe_sgto_log' => now(),
                        'de_ip' => $request->ip()
                    ]);
                }
            });

            return response()->json(['status' => 'success', 'message' => 'Los trámites internos fueron enviados exitosamente.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al enviar trámites: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Finaliza y archiva un expediente (Bandeja -> Finalizados)
     */
    public function finalizar(Request $request)
    {
        $ids = $request->input('ids', []);
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha'))->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($request->input('observaciones', 'TRÁMITE FINALIZADO')), 'UTF-8');
        $folios = (int) $request->input('folios', 1);

        $enviarConTramite = $request->boolean('enviar_con_tramite');
        $docTipo = (int) $request->input('doc_tipo');
        $enviarCorreo = $request->input('enviar_correo') === '1';

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Debe seleccionar al menos un expediente.'], 400);
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        $cargoFila = DB::table($this->schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $areaNombre = $cargoFila->de_crgo ?? 'OFICINA DE TRÁMITE';
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        try {
            DB::transaction(function() use ($ids, $fecha, $observaciones, $folios, $enviarConTramite, $docTipo, $enviarCorreo, $user, $cargoId, $areaId, $areaNombre, $cmpnia, $request) {
                $anioId = DB::table($this->schema . 'a_anios')
                    ->where('de_anio', date('Y'))
                    ->where('cdgo_cmpnia', $cmpnia)
                    ->value('cdgo_anio');

                if (!$anioId) {
                    $anioId = DB::table($this->schema . 'a_anios')->insertGetId([
                        'de_anio' => date('Y'),
                        'dc_anio' => substr(date('Y'), 2, 2),
                        'cdgo_cmpnia' => $cmpnia
                    ], 'cdgo_anio');
                }

                foreach ($ids as $id) {
                    $id = (int)$id;
                    $idInternalDoc = null;
                    $anexoTexto = '';

                    // 1. Crear Trámite Interno Asociado si corresponde
                    if ($enviarConTramite && $docTipo > 0) {
                        $expeTodo = DB::table($this->schema . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->value('nu_expe_todo') ?? '—';
                        $referenciaObse = "CON REFERENCIA AL EXPEDIENTE N° " . $expeTodo;
                        
                        $nextSeed = $this->generarSeedNumber($docTipo, $anioId, $areaId, $cargoId, $user->cdgo_lgin);
                        $formattedSeed = $nextSeed . '-' . date('Y');
                        $tituloCompleto = $this->generarTituloTramiteInterno($docTipo, $formattedSeed, $areaId, $cargoId, $user->cdgo_lgin);
                        
                        $idInternalDoc = DB::table($this->schema . 'p_dtos_ntrnos')->insertGetId([
                            'cdgo_tpo_trmte' => $docTipo,
                            'cdgo_anio' => $anioId,
                            'cdgo_area' => $areaId,
                            'cdgo_lgin' => $user->cdgo_lgin,
                            'cdgo_cmpnia' => $cmpnia,
                            'cdgo_dto_trmte' => $id,
                            'cdgo_dtos_ntrnos_pri' => null,
                            'nu_tram_todo' => $nextSeed . '-' . date('Y'),
                            'nu_tram_cmplto' => $tituloCompleto,
                            'nu_tram' => $nextSeed,
                            'de_obser' => mb_strtoupper($referenciaObse, 'UTF-8'),
                            'de_urls_adjnto' => '',
                            'fe_ingrso' => $fecha,
                            'in_envo_fsco' => '0',
                            'in_prrdad_trmte' => 1
                        ], 'cdgo_dtos_ntrnos');

                        DB::table($this->schema . 'r_trmtes_ntrnos')->insert([
                            'cdgo_dtos_ntrnos' => $idInternalDoc,
                            'cdgo_estdo_trmte' => 1,
                            'cdgo_lgin' => $user->cdgo_lgin,
                            'cdgo_crgo_sa' => $cargoId,
                            'cdgo_crgo_en' => $cargoId,
                            'de_urls_agrdo' => '',
                            'fe_rcbdo' => $fecha,
                            'de_obser_ntrnos' => mb_strtoupper($observaciones, 'UTF-8'),
                            'in_copia' => '1'
                        ]);

                        $docTypeName = DB::table($this->schema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $docTipo)->value('de_tpo_trmte') ?? 'TRÁMITE';
                        $anexoTexto = "; CON {$docTypeName} N° " . str_pad($nextSeed, 8, '0', STR_PAD_LEFT) . "-" . date('Y') . ".";
                    }

                    // 2. Deshabilitar movimiento actual
                    DB::table($this->schema . 'r_trmtes_dcmntos')
                        ->where('cdgo_dto_trmte', $id)
                        ->where('in_esta', '1')
                        ->update(['in_esta' => '0']);

                    // 3. Crear el nuevo movimiento final de cierre (Estado = 3, Finalizado, in_esta = '0')
                    $finalObse = mb_strtoupper($observaciones . $anexoTexto, 'UTF-8');
                    $newMovimientoId = DB::table($this->schema . 'r_trmtes_dcmntos')->insertGetId([
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $cargoId,
                        'cdgo_dto_trmte' => $id,
                        'cdgo_estdo_trmte' => 3, // 3 = Finalizado
                        'fe_rcbdo_trmte' => $fecha,
                        'de_obse' => $finalObse,
                        'in_esta' => '0', // Inactivo por ser final
                        'de_urls_imag' => '',
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'nu_foli_anex' => $folios,
                        'fe_cta_vrtal' => null,
                        'nu_anxo' => null,
                        'cdgo_dtos_ntrnos' => $idInternalDoc
                    ], 'cdgo_trmte_dcmnto');

                    // 4. Procesar adjuntos
                    $attachments = $this->processAttachments($request, 'fle_adjunto', 'fle_adjunto_pdf', $newMovimientoId, $idInternalDoc);
                    if ($attachments['filename'] !== '') {
                        DB::table($this->schema . 'r_trmtes_dcmntos')
                            ->where('cdgo_trmte_dcmnto', $newMovimientoId)
                            ->update(['de_urls_imag' => $attachments['filename']]);

                        if ($idInternalDoc) {
                            DB::table($this->schema . 'p_dtos_ntrnos')
                                ->where('cdgo_dtos_ntrnos', $idInternalDoc)
                                ->update(['de_urls_adjnto' => $attachments['filename_internal']]);
                        }
                    }

                    // 5. Concatenar observaciones generales al expediente externo
                    $currentObse = DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->value('de_obse_todo') ?? '';
                    $newObseTodo = $currentObse !== '' ? $currentObse . ' - ' . $finalObse : $finalObse;
                    DB::table($this->schema . 'p_dto_trmtes')
                        ->where('cdgo_dto_trmte', $id)
                        ->update(['de_obse_todo' => mb_strtoupper($newObseTodo, 'UTF-8')]);

                    // 6. Registrar log legacy
                    DB::table($this->schema . 'p_sgmto_log')->insert([
                        'cdgo_lgin' => $user->cdgo_lgin,
                        'de_sgto_log' => "Se finalizo el expediente {$id}",
                        'fe_sgto_log' => now(),
                        'de_ip' => $request->ip()
                    ]);

                    // 7. Notificación por Correo al Solicitante
                    if ($enviarCorreo) {
                        $emailInfo = DB::table($this->schema . 'p_dto_trmtes as p')
                            ->join($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
                            ->leftJoin($this->schema . 'h_usrios_crreo as h', 'p.cdgo_usrios_crreo', '=', 'h.cdgo_usrios_crreo')
                            ->select('u.de_mail', 'p.nu_expe_todo', 'u.no_crto', 'h.de_mail as de_usrios_mail')
                            ->where('p.cdgo_dto_trmte', $id)
                            ->first();

                        if ($emailInfo) {
                            $targetMail = strtolower(trim($emailInfo->de_usrios_mail ?: $emailInfo->de_mail));
                            if ($targetMail !== '') {
                                $filePath = null;
                                if ($attachments['filename'] !== '') {
                                    $filePath = storage_path('app/public/adjuntos/' . $attachments['filename']);
                                }

                                try {
                                    Mail::to($targetMail)->send(new ExpedienteFinalizadoMail(
                                        $emailInfo,
                                        $finalObse,
                                        $areaNombre,
                                        $filePath,
                                        $attachments['filename']
                                    ));
                                } catch (\Exception $mailEx) {
                                    Log::error("Error al enviar correo de finalización del expediente {$id}: " . $mailEx->getMessage());
                                }
                            }
                        }
                    }
                }
            });

            return response()->json(['status' => 'success', 'message' => 'Los expedientes fueron finalizados correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al finalizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Consulta las solicitudes de copia de video asociadas a un expediente
     */
    public function solicitudVideo(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID inválido'], 400);
        }

        $solicitudes = DB::table($this->schema . 'p_solicitud_video')
            ->select(
                'cdgo_solicitud_video',
                'cdgo_tipo_solicitante',
                'tipo_documento',
                'nu_documento',
                'de_nombre_usuario',
                'de_cargo',
                'de_institucion_uo',
                'fe_inicio_grabacion',
                'fe_fin_grabacion',
                'de_observaciones',
                'fe_entrega',
                'fe_solicitud',
                'de_correo_solicitante'
            )
            ->where('cdgo_dto_trmte', $id)
            ->where('in_esta', '1')
            ->orderBy('fe_solicitud', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $solicitudes
        ]);
    }

    // ==========================================
    // MÉTODOS AUXILIARES Y DE CONSULTA
    // ==========================================

    private function baseSelect(): string
    {
        return 'SELECT p_dto_trmtes.cdgo_dto_trmte,
        p_dto_trmtes.nu_expe_todo,
        p_usrios.no_crto,
        r_trmtes_dcmntos.fe_rcbdo_trmte,
        a_estdos_trmtes.dc_estdo_trmte,
        a_estdos_trmtes.cdgo_estdo_trmte,
        p_dto_trmtes.de_obse_trmte,
        p_dto_trmtes.fe_ingr_trmte,
        p_dto_trmtes.cdgo_docmnto_trmte,
        adoc.de_docmnto_trmte,
        p_dto_trmtes.nu_tiem_sile_adcnal,
        p_dto_trmtes.cdgo_usrios AS cdgo_usrios_trmte,
        p_lgin.cdgo_usrios AS cdgo_usrios_lgin,
        r_trmtes_dcmntos.de_obse,
        a_areas.de_area,
        a_areas.dc_area,
        p_crgos_en.de_crgo AS de_crgo_en,
        p_dto_trmtes.in_docu_trmte,
        adoc.nu_tiem_sile,
        adoc.in_sile_admi ';
    }

    private function baseFromJoin(): string
    {
        $s = $this->schema;

        return "FROM {$s}r_trmtes_dcmntos r_trmtes_dcmntos
            INNER JOIN {$s}p_crgos p_crgos_en ON r_trmtes_dcmntos.cdgo_crgo_en = p_crgos_en.cdgo_crgo
            LEFT JOIN {$s}p_crgos p_crgos_sa ON r_trmtes_dcmntos.cdgo_crgo_sa = p_crgos_sa.cdgo_crgo
            INNER JOIN {$s}p_dto_trmtes p_dto_trmtes ON r_trmtes_dcmntos.cdgo_dto_trmte = p_dto_trmtes.cdgo_dto_trmte
            INNER JOIN {$s}a_areas a_areas ON p_dto_trmtes.cdgo_area = a_areas.cdgo_area
            INNER JOIN {$s}p_lgin p_lgin ON p_dto_trmtes.co_usua_ingr = p_lgin.cdgo_lgin
            INNER JOIN {$s}p_usrios p_usrios ON p_dto_trmtes.cdgo_usrios = p_usrios.cdgo_usrios
            INNER JOIN {$s}a_estdos_trmtes a_estdos_trmtes ON r_trmtes_dcmntos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte
            LEFT JOIN {$s}a_docmntos_trmtes adoc ON p_dto_trmtes.cdgo_docmnto_trmte = adoc.cdgo_docmnto_trmte ";
    }

    private function buildBandejaWhere(string $bandeja, int $cargoId, int $areaId, string $reporte): string
    {
        if ($reporte === 'sin_finalizar') {
            return " AND r_trmtes_dcmntos.cdgo_crgo_en <> '{$cargoId}'
                AND r_trmtes_dcmntos.cdgo_crgo_sa = '{$cargoId}'
                AND (SELECT ae_sf.cdgo_estdo_trmte
                    FROM {$this->schema}r_trmtes_dcmntos rtd_sf
                    INNER JOIN {$this->schema}a_estdos_trmtes ae_sf ON rtd_sf.cdgo_estdo_trmte = ae_sf.cdgo_estdo_trmte
                    WHERE rtd_sf.cdgo_dto_trmte = p_dto_trmtes.cdgo_dto_trmte AND rtd_sf.in_esta = '1'
                    ORDER BY rtd_sf.cdgo_trmte_dcmnto DESC LIMIT 1) <> '3' ";
        }

        switch ($bandeja) {
            case 'todos':
                return " AND p_crgos_en.cdgo_area = '{$areaId}' AND r_trmtes_dcmntos.in_esta = '1' ";

            case 'recibidos':
                return " AND r_trmtes_dcmntos.cdgo_crgo_en = '{$cargoId}'
                    AND r_trmtes_dcmntos.cdgo_crgo_sa = '{$cargoId}'
                    AND r_trmtes_dcmntos.in_esta = '1'
                    AND r_trmtes_dcmntos.cdgo_estdo_trmte NOT IN ('3','26','27') ";

            case 'salida':
                return " AND r_trmtes_dcmntos.cdgo_crgo_en <> '{$cargoId}'
                    AND r_trmtes_dcmntos.cdgo_crgo_sa = '{$cargoId}'
                    AND r_trmtes_dcmntos.in_esta = '1' ";

            case 'finalizados':
                return " AND r_trmtes_dcmntos.cdgo_crgo_en = '{$cargoId}'
                    AND r_trmtes_dcmntos.cdgo_crgo_sa = '{$cargoId}'
                    AND r_trmtes_dcmntos.in_esta = '0'
                    AND r_trmtes_dcmntos.cdgo_estdo_trmte IN ('3','26') ";

            case 'archivo':
                return " AND r_trmtes_dcmntos.cdgo_crgo_en = '{$cargoId}'
                    AND r_trmtes_dcmntos.cdgo_crgo_sa = '{$cargoId}'
                    AND r_trmtes_dcmntos.in_esta = '1'
                    AND r_trmtes_dcmntos.cdgo_estdo_trmte IN ('27') ";

            case 'entrada':
            default:
                return " AND (
                    (
                        r_trmtes_dcmntos.cdgo_crgo_en = '{$cargoId}'
                        AND r_trmtes_dcmntos.cdgo_crgo_sa <> '{$cargoId}'
                        AND r_trmtes_dcmntos.in_esta = '1'
                        AND r_trmtes_dcmntos.cdgo_estdo_trmte NOT IN ('5','12','29')
                    )
                    OR (
                        r_trmtes_dcmntos.cdgo_crgo_en = '{$cargoId}'
                        AND r_trmtes_dcmntos.cdgo_estdo_trmte IN ('25','51')
                        AND r_trmtes_dcmntos.in_esta = '1'
                    )
                ) ";
        }
    }

    private function buildSearchWhere(string $criterio, string $descripcion): string
    {
        if ($descripcion === '') {
            return '';
        }

        $esc = str_replace("'", "''", $descripcion);

        switch ($criterio) {
            case 'expe_varios':
                $parts = array_filter(array_map('intval', explode(',', preg_replace('/[^0-9,]/', '', $descripcion))));
                if ($parts === []) {
                    return '';
                }
                return ' AND p_dto_trmtes.nu_expe_trmte IN (' . implode(',', $parts) . ') ';

            case 'solicitante':
                return " AND p_usrios.no_crto ILIKE '%{$esc}%' ";

            case 'observaciones':
                return " AND p_dto_trmtes.de_obse_trmte ILIKE '%{$esc}%' ";

            case 'area':
                return " AND a_areas.de_area ILIKE '%{$esc}%' ";

            case 'tipo':
                return " AND adoc.de_docmnto_trmte ILIKE '%{$esc}%' ";

            case 'cargo':
                return " AND p_crgos_en.de_crgo ILIKE '%{$esc}%' ";

            case 'expe_todo':
            default:
                if (str_contains($descripcion, ',')) {
                    $quoted = [];
                    foreach (array_map('trim', explode(',', $descripcion)) as $p) {
                        if ($p === '') {
                            continue;
                        }
                        $quoted[] = "'" . str_replace("'", "''", $p) . "'";
                    }
                    if ($quoted !== []) {
                        return ' AND p_dto_trmtes.nu_expe_todo IN (' . implode(',', $quoted) . ') ';
                    }
                }
                return " AND p_dto_trmtes.nu_expe_todo::text ILIKE '%{$esc}%' ";
        }
    }

    private function formatNumeroExpediente(?string $nu): string
    {
        if ($nu === null || $nu === '') {
            return '—';
        }

        $expe = explode('-', $nu);
        if (count($expe) < 3) {
            return $nu;
        }

        $middle = $expe[1] . '-' . substr($expe[2], 2, 10);

        return $expe[0] . '-' . $this->padCerosIzquierda($middle, 8);
    }

    private function padCerosIzquierda(string $expe, int $ancho): string
    {
        $nume = explode('.', $expe);
        $dife = $ancho - strlen($nume[0]);
        if ($dife <= 0) {
            return $expe;
        }

        return str_repeat('0', $dife) . $expe;
    }

    private function calcularDiasTranscurridos($fechaIngreso): int
    {
        if (!$fechaIngreso) return 0;
        return (int) Carbon::parse($fechaIngreso)->diffInDays(Carbon::now());
    }

    private function calcularTiempoTranscurrido($fechaInicio, $fechaFin = null): string
    {
        if (!$fechaInicio) {
            return '—';
        }
        $start = Carbon::parse($fechaInicio);
        $end = $fechaFin ? Carbon::parse($fechaFin) : Carbon::now();
        
        $diffMinutes = $start->diffInMinutes($end);
        $hours = floor($diffMinutes / 60);
        $minutes = $diffMinutes % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . 'm';
    }

    private function generarTituloTramiteInterno(int $tipoDocumentoId, string $nuSmlla, int $areaId, int $cargoId, int $userId): string
    {
        $tipo = DB::table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $tipoDocumentoId)
            ->first();
        if (!$tipo) {
            return '';
        }
        
        $de_tpo_trmte_plntlla = $tipo->de_tpo_trmte_plntlla ?: $tipo->de_tpo_trmte;
        
        $parts = explode('-', $nuSmlla);
        $seedNum = $parts[0] ?? '0';
        $year = $parts[1] ?? date('Y');
        
        $cargoInfo = DB::table($this->schema . 'p_crgos as c')
            ->join($this->schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($this->schema . 'a_areas as ap', 'a.cdgo_area_prim', '=', 'ap.cdgo_area')
            ->join($this->schema . 'r_crgos_lgin as rl', 'c.cdgo_crgo', '=', 'rl.cdgo_crgo')
            ->join($this->schema . 'p_lgin as l', 'rl.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->join($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select('c.cdgo_area', 'a.dc_area', 'a.cdgo_area_prim', 'ap.dc_area as dc_area_prim', 'u.no_crto')
            ->where('c.cdgo_crgo', $cargoId)
            ->first();
            
        $no_crto = '';
        if ($tipo->in_scncal == '1' && $cargoInfo) {
            $no_crto = '-' . $this->obtenerIniciales($cargoInfo->no_crto);
        }
        
        $gerencia = '';
        $subgerencia = '';
        if ($cargoInfo) {
            if ($cargoInfo->dc_area_prim) {
                $gerencia = '-' . str_replace('.', '', $cargoInfo->dc_area_prim);
            }
            if ($cargoInfo->dc_area) {
                $subgerencia = '-' . str_replace('.', '', $cargoInfo->dc_area);
            }
        }
        
        $nroFormateado = str_pad($seedNum, 4, '0', STR_PAD_LEFT);
        
        return "{$de_tpo_trmte_plntlla} Nº {$nroFormateado}-{$year}/MDPP{$gerencia}{$subgerencia}{$no_crto}";
    }

    private function obtenerIniciales(string $nombre): string
    {
        $iniciales = '';
        $palabras = explode(' ', preg_replace('/\s+/', ' ', trim($nombre)));
        foreach ($palabras as $p) {
            if (strlen($p) > 0) {
                $iniciales .= strtoupper($p[0]);
            }
        }
        return $iniciales;
    }

    private function generarSeedNumber(int $tipoDocumentoId, int $anioId, int $areaId, int $cargoId, int $userId): int
    {
        $tipo = DB::table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $tipoDocumentoId)
            ->first();
        
        $inScncal = $tipo->in_scncal ?? 0;
        
        $nextSeed = DB::table($this->schema . 'a_smlla_intrno')
            ->where('cdgo_tpo_trmte', $tipoDocumentoId)
            ->where('cdgo_anio', $anioId)
            ->where(function($q) use ($areaId, $cargoId, $inScncal) {
                if ($inScncal == 1) {
                    $q->where('cdgo_crgo', $cargoId);
                } else {
                    $q->where('cdgo_area', $areaId);
                }
            })
            ->max('nu_smlla_intrno');
            
        $nextSeed = $nextSeed ? $nextSeed + 1 : 1;
        
        DB::table($this->schema . 'a_smlla_intrno')->insert([
            'cdgo_anio' => $anioId,
            'cdgo_area' => $areaId,
            'cdgo_crgo' => $cargoId,
            'cdgo_tpo_trmte' => $tipoDocumentoId,
            'nu_smlla_intrno' => $nextSeed,
            'co_usua_modi' => $userId,
            'ts_usua_modi' => now(),
            'in_esta' => '1'
        ]);
        
        return $nextSeed;
    }

    private function processAttachments(Request $request, string $inputNamePrefix, string $primaryUploadName, string $movimientoId, ?string $internalId = null): array
    {
        $uploadedFiles = [];
        
        // Archivo principal firmado digitalmente (habitualmente fle_adjunto)
        if ($request->hasFile($primaryUploadName)) {
            $uploadedFiles[] = [
                'file' => $request->file($primaryUploadName),
                'type' => 'primary'
            ];
        }
        
        // Múltiples anexos (fle_adjunto1, fle_adjunto2, etc.)
        for ($i = 1; $i <= 4; $i++) {
            $inputName = $inputNamePrefix . $i;
            if ($request->hasFile($inputName)) {
                $uploadedFiles[] = [
                    'file' => $request->file($inputName),
                    'type' => 'annex'
                ];
            }
        }
        
        if (empty($uploadedFiles)) {
            return ['filename' => '', 'filename_internal' => ''];
        }
        
        // Asegurar que existan los directorios
        if (!file_exists(storage_path('app/public/adjuntos'))) {
            @mkdir(storage_path('app/public/adjuntos'), 0755, true);
        }
        if (!file_exists(storage_path('app/public/tramites'))) {
            @mkdir(storage_path('app/public/tramites'), 0755, true);
        }
        
        // Si hay UN solo archivo y es el principal PDF
        if (count($uploadedFiles) === 1 && $uploadedFiles[0]['type'] === 'primary') {
            $file = $uploadedFiles[0]['file'];
            $ext = $file->getClientOriginalExtension();
            $filename = 'adjunto_' . $movimientoId . '_' . time() . '.' . $ext;
            $file->storeAs('public/adjuntos', $filename);
            
            $filename_internal = '';
            if ($internalId) {
                $filename_internal = 'pri' . $internalId . '.pdf';
                @copy(storage_path('app/public/adjuntos/' . $filename), storage_path('app/public/tramites/' . $filename_internal));
            }
            
            return ['filename' => $filename, 'filename_internal' => $filename_internal];
        }
        
        // Si son múltiples archivos, comprimirlos
        $zipFilename = $movimientoId . '.zip';
        $zipPath = storage_path('app/public/adjuntos/' . $zipFilename);
        
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            foreach ($uploadedFiles as $uFile) {
                $file = $uFile['file'];
                $name = $uFile['type'] === 'annex' ? 'anexos/' . $file->getClientOriginalName() : $file->getClientOriginalName();
                $zip->addFile($file->getRealPath(), $name);
            }
            $zip->close();
        }
        
        $zipFilenameInternal = '';
        if ($internalId) {
            $zipFilenameInternal = 'pri' . $internalId . '.zip';
            @copy($zipPath, storage_path('app/public/tramites/' . $zipFilenameInternal));
        }
        
        return ['filename' => $zipFilename, 'filename_internal' => $zipFilenameInternal];
    }

    /**
     * Consulta la siguiente semilla numérica correlativa para un trámite interno
     */
    public function obtenerSemilla(Request $request)
    {
        $tipoDocumentoId = (int) $request->input('doc_tipo');
        if ($tipoDocumentoId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Tipo de documento inválido'], 400);
        }
        
        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        $cargoFila = DB::table($this->schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);
        
        $tipo = DB::table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $tipoDocumentoId)
            ->first();
            
        if (!$tipo) {
            return response()->json(['status' => 'error', 'message' => 'Tipo de documento no encontrado'], 400);
        }
        
        $inScncal = $tipo->in_scncal ?? 0;
        
        $anioId = DB::table($this->schema . 'a_anios')
            ->where('de_anio', date('Y'))
            ->where('cdgo_cmpnia', $cmpnia)
            ->value('cdgo_anio');
            
        if (!$anioId) {
            $anioId = DB::table($this->schema . 'a_anios')->insertGetId([
                'de_anio' => date('Y'),
                'dc_anio' => substr(date('Y'), 2, 2),
                'cdgo_cmpnia' => $cmpnia
            ], 'cdgo_anio');
        }
            
        $nextSeed = DB::table($this->schema . 'a_smlla_intrno')
            ->where('cdgo_tpo_trmte', $tipoDocumentoId)
            ->where('cdgo_anio', $anioId)
            ->where(function($q) use ($areaId, $cargoId, $inScncal) {
                if ($inScncal == 1) {
                    $q->where('cdgo_crgo', $cargoId);
                } else {
                    $q->where('cdgo_area', $areaId);
                }
            })
            ->max('nu_smlla_intrno');
            
        $nextSeed = $nextSeed ? $nextSeed + 1 : 1;
        
        return response()->json([
            'status' => 'success',
            'semilla' => $nextSeed . '-' . date('Y')
        ]);
    }
    
    /**
     * Muestra el detalle completo del expediente (Sustituye a busca_expedi_deta.php)
     */
    public function detalle(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID inválido'], 400);
        }

        $row = DB::table($this->schema . 'p_dto_trmtes as p')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'a_docmntos_trmtes as adoc', 'p.cdgo_docmnto_trmte', '=', 'adoc.cdgo_docmnto_trmte')
            ->leftJoin($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select(
                'p.nu_expe_todo',
                'p.fe_ingr_trmte',
                'p.de_obse_todo',
                'p.in_docu_trmte',
                'p.nu_foli_trmte',
                't.de_tpo_trmte as tipo_tramite',
                'adoc.de_docmnto_trmte as servicio',
                'adoc.nu_tiem_sile',
                'p.nu_tiem_sile_adcnal',
                'u.no_crto as solicitante_nombres',
                'u.nu_docu',
                'u.nu_ruc',
                'u.de_dire',
                'u.nu_tele',
                'u.de_mail'
            )
            ->where('p.cdgo_dto_trmte', $id)
            ->first();

        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado'], 404);
        }

        $requisitos = DB::table($this->schema . 'r_dtos_rqtos as r')
            ->join($this->schema . 'h_rqtos_trmtes as ttr', 'r.cdgo_rqto_trmte', '=', 'ttr.cdgo_rqto_trmte')
            ->select('r.in_pres as cumplido', 'ttr.de_rqto_trmte as nombre')
            ->where('r.cdgo_dto_trmte', $id)
            ->get();

        $data = [
            'expediente' => $row->nu_expe_todo,
            'tipo_documento' => $row->in_docu_trmte === '1' ? 'Empresa' : 'Persona Natural',
            'solicitante' => $row->solicitante_nombres,
            'documento_id' => $row->in_docu_trmte === '1' ? ($row->nu_ruc ?: $row->nu_docu) : ($row->nu_docu ?: $row->nu_ruc),
            'domicilio' => $row->de_dire,
            'telefono' => $row->nu_tele,
            'correo' => $row->de_mail,
            'folios' => $row->nu_foli_trmte,
            'fecha_ingreso' => Carbon::parse($row->fe_ingr_trmte)->format('d/m/Y H:i'),
            'servicio' => $row->servicio,
            'tipo_tramite' => $row->tipo_tramite,
            'silencio_admin' => ((int)$row->nu_tiem_sile > 0) ? ($row->nu_tiem_sile + $row->nu_tiem_sile_adcnal) . ' Días' : 'Sin Silencio',
            'observaciones' => $row->de_obse_todo,
            'requisitos' => $requisitos
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Muestra la Hoja de Ruta para impresión
     */
    public function hojaRuta($id)
    {
        $id = (int) $id;
        
        $expediente = DB::table($this->schema . 'p_dto_trmtes as p')
            ->leftJoin($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select('p.nu_expe_todo', 'p.fe_ingr_trmte', 'u.no_crto')
            ->where('p.cdgo_dto_trmte', $id)
            ->first();
            
        if (!$expediente) {
            abort(404, 'Expediente no encontrado.');
        }

        $historial = DB::table($this->schema . 'r_trmtes_dcmntos as r')
            ->leftJoin($this->schema . 'p_crgos as p_crgos_en', 'r.cdgo_crgo_en', '=', 'p_crgos_en.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a_areas_en', 'p_crgos_en.cdgo_area', '=', 'a_areas_en.cdgo_area')
            ->leftJoin($this->schema . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select(
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte as fe_entrada',
                'a_areas_en.de_area as area',
                'a_estdos_trmtes.dc_estdo_trmte as estado',
                'u.no_crto as usuario'
            )
            ->where('r.cdgo_dto_trmte', $id)
            ->orderBy('r.cdgo_trmte_dcmnto', 'asc')
            ->get();

        $historial = $historial->map(function($mov) {
            $mov->fe_entrada = $mov->fe_entrada ? Carbon::parse($mov->fe_entrada)->format('d/m/Y H:i:s') : '—';
            return $mov;
        });

        return view('mod_gm.mod_tram_exte.hoja_ruta', compact('expediente', 'historial'));
    }

    /**
     * Descarga segura de archivos adjuntos del movimiento
     */
    public function descargarAdjunto($id)
    {
        $movimientoId = (int) $id;
        if ($movimientoId <= 0) {
            abort(400, 'ID inválido');
        }

        $movimiento = DB::table($this->schema . 'r_trmtes_dcmntos')
            ->select('de_urls_imag')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->first();

        if (!$movimiento || empty($movimiento->de_urls_imag)) {
            abort(404, 'Archivo no encontrado');
        }

        $filePath = storage_path('app/public/adjuntos/' . $movimiento->de_urls_imag);

        if (!file_exists($filePath)) {
            abort(404, 'Archivo físico no encontrado');
        }

        return response()->download($filePath);
    }

    /**
     * Genera un reporte PDF de la bandeja actual
     */
    public function imprimirBandeja(Request $request)
    {
        if (!Auth::check()) {
            abort(401, 'No autenticado');
        }

        $user = Auth::user();
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            abort(400, 'Seleccione un cargo activo');
        }

        $cargoFila = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();
        $areaId = (int) ($cargoFila->cdgo_area ?? 0);
        $cmpnia = (int) ($user->cdgo_cmpnia ?? 2);

        $bandeja = (string) $request->input('bandeja', 'entrada');
        $criterio = (string) $request->input('criterio', 'expe_todo');
        $descripcion = trim((string) $request->input('descripcion', ''));
        $desde = (string) $request->input('desde', '');
        $hasta = (string) $request->input('hasta', '');
        $reporte = (string) $request->input('reporte', 'todos');
        $estado = (string) $request->input('estado', '0');

        $search = $this->buildSearchWhere($criterio, $descripcion);
        $dateField = ($bandeja === 'todos' && $reporte !== 'sin_finalizar')
            ? 'p_dto_trmtes.fe_ingr_trmte'
            : 'r_trmtes_dcmntos.fe_rcbdo_trmte';

        if ($desde !== '' && $hasta !== '') {
            $desde = preg_replace('/[^0-9\-]/', '', $desde);
            $hasta = preg_replace('/[^0-9\-]/', '', $hasta);
            if ($desde !== '' && $hasta !== '') {
                $search .= " AND {$dateField} >= '{$desde} 00:00:00' AND {$dateField} <= '{$hasta} 23:59:59' ";
            }
        }

        if ($estado !== '0' && $estado !== '') {
            $search .= " AND r_trmtes_dcmntos.cdgo_estdo_trmte = '" . intval($estado) . "' ";
        }

        $whereBandeja = $this->buildBandejaWhere($bandeja, $cargoId, $areaId, $reporte);

        $baseQuery = $this->baseFromJoin()
            . ' WHERE p_dto_trmtes.cdgo_cmpnia = ' . $cmpnia . $whereBandeja . $search;

        $sql = $this->baseSelect() . $baseQuery
            . ' ORDER BY r_trmtes_dcmntos.fe_rcbdo_trmte DESC NULLS LAST LIMIT 500';

        $rows = DB::select($sql);

        $data = array_map(function ($row) {
            $a = (array) $row;
            $a['tramite_formateado'] = $this->formatNumeroExpediente($a['nu_expe_todo'] ?? null);
            return (object) $a;
        }, $rows);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('mod_gm.mod_tram_exte.reporte_bandeja', [
            'data' => $data,
            'bandeja' => $bandeja,
            'fechaImpresion' => now()->format('d/m/Y H:i')
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte_bandeja_' . now()->format('YmdHi') . '.pdf');
    }
}
