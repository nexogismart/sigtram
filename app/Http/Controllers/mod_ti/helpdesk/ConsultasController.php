<?php

namespace App\Http\Controllers\mod_ti\helpdesk;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reporte Kanban y Administración OTI (Jefatura)
 * Conectado a PostgreSQL - Esquemas: sistema, ti
 */
class ConsultasController extends Controller
{
    /** Cargos permitidos para el reporte (Jefatura, Secretaría OTI, Soporte). */
    private const CARGOS_CONSULTA_ADMIN = [496, 509, 498];

    /** Área OTI */
    private const AREA_OTI = 103;

    /**
     * Muestra la vista principal con los filtros.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $this->assertCargoConsultaAdmin($cargoId);
        $this->assertPermisoAdministracion($user, $cargoId);

        $hoy = Carbon::now()->format('Y-m-d');
        $filtros = [
            'fecha_inicio' => $this->normalizeDate($request->query('fecha_inicio'), $hoy),
            'fecha_fin' => $this->normalizeDate($request->query('fecha_fin'), $hoy),
            'oti' => (int) $request->query('oti', 0),
            'area' => $request->query('area') !== null && $request->query('area') !== '' ? (int) $request->query('area') : null,
            'servicio' => $request->query('servicio') !== null && $request->query('servicio') !== '' ? (int) $request->query('servicio') : null,
        ];

        // Carga inicial vacía para el Kanban (se llenará por AJAX al cargar la página)
        $ordenColumnas = ['Requerimiento', 'Pendiente', 'Proceso', 'Finalizado', 'Confirmado'];

        return view('mod_ti.helpdesk.consulta', [
            'kanban' => array_fill_keys($ordenColumnas, []),
            'totalTickets' => 0,
            'porcentajeAvance' => 0,
            'otiPersonal' => $this->listOtiPersonal(),
            'areas' => $this->listAreas(),
            'servicios' => $this->listIncidencias(),
            'filtros' => $filtros,
            'usuarioNombre' => $user->nombreMostrar(),
        ]);
    }

    // ========================================================================
    // MÉTODOS DE PROCESAMIENTO AJAX
    // ========================================================================

    private function checkAccess(Request $request): bool
    {
        $user = Auth::user();
        if (!$user instanceof User) return false;

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if (!in_array($cargoId, self::CARGOS_CONSULTA_ADMIN, true)) return false;
        
        return $this->tienePermisoAdministracion($user, $cargoId);
    }

    private function getFiltrosFromRequest(Request $request): array
    {
        $hoy = Carbon::now()->format('Y-m-d');
        return [
            $this->normalizeDate($request->input('fecha_inicio'), $hoy),
            $this->normalizeDate($request->input('fecha_fin'), $hoy),
            (int) $request->input('oti', 0),
            $request->input('area') !== null && $request->input('area') !== '' ? (int) $request->input('area') : null,
            $request->input('servicio') !== null && $request->input('servicio') !== '' ? (int) $request->input('servicio') : null,
            (int) $request->input('search_id', 0),
        ];
    }

    public function kanban(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) return response()->json(['message' => 'Acceso denegado.'], 403);
        [$fInicio, $fFin, $fOti, $fArea, $fServicio, $fId] = $this->getFiltrosFromRequest($request);

        $payload = $this->buildKanbanPayload($fInicio, $fFin, $fOti, $fArea, $fServicio, $fId);
        return response()->json([
            'count' => $payload['count'],
            'avance' => $payload['avance'],
            'bandeja' => $payload['bandeja_raw'],
        ]);
    }

    public function chart1(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) return response()->json(['message' => 'Acceso denegado.'], 403);
        [$fInicio, $fFin, $fOti, $fArea, $fServicio, $fId] = $this->getFiltrosFromRequest($request);

        $rows = $this->getBaseQuery($fInicio, $fFin, $fOti, $fArea, $fServicio, $fId)
            ->select(DB::raw('b.situacion, COUNT(*) as cantidad'))
            ->groupBy('b.situacion')
            ->get();
            
        $etiquetas = [1 => 'Requerimientos', 2 => 'Pendientes', 3 => 'En Proceso', 4 => 'Finalizados', 5 => 'Confirmados'];
        $resultado = [];
        
        foreach ($rows as $r) {
            $nombre = $etiquetas[$r->situacion] ?? 'Otros';
            $resultado[$nombre] = $r->cantidad;
        }
        
        return response()->json($resultado);
    }

    public function chart2(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) return response()->json(['message' => 'Acceso denegado.'], 403);
        [$fInicio, $fFin, $fOti, $fArea, $fServicio, $fId] = $this->getFiltrosFromRequest($request);

        $sis = $this->schemaSistema();
        $rows = $this->getBaseQuery($fInicio, $fFin, $fOti, $fArea, $fServicio, $fId)
            ->join($sis . 'p_lgin as p_des', 'b.idlogin_des', '=', 'p_des.cdgo_lgin')
            ->join($sis . 'p_usrios as u_des', 'p_des.cdgo_usrios', '=', 'u_des.cdgo_usrios')
            ->whereIn('b.situacion', [4, 5])
            ->select(DB::raw('u_des.no_crto as nombre, COUNT(*) as cantidad'))
            ->groupBy('u_des.no_crto')
            ->get();

        $resultado = [];
        foreach ($rows as $r) { $resultado[$r->nombre] = $r->cantidad; }
        return response()->json($resultado);
    }

    public function chart3(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) return response()->json(['message' => 'Acceso denegado.'], 403);
        [$fInicio, $fFin, $fOti, $fArea, $fServicio, $fId] = $this->getFiltrosFromRequest($request);

        $ti = $this->schemaTi();
        $rows = $this->getBaseQuery($fInicio, $fFin, $fOti, $fArea, $fServicio, $fId)
            ->join($ti . 'incdncia as inc', 't.incidencia', '=', 'inc.id')
            ->select(DB::raw('inc.detalle, COUNT(*) as cantidad'))
            ->groupBy('inc.detalle')
            ->orderByDesc('cantidad')
            ->limit(10)
            ->get();

        $resultado = [];
        foreach ($rows as $r) { $resultado[$r->detalle] = $r->cantidad; }
        return response()->json($resultado);
    }

    public function ticketDetalle(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) return response()->json(['message' => 'Acceso denegado.'], 403);
        $idTicket = (int) $request->input('id_ticket', 0);

        $ti = $this->schemaTi();
        $sis = $this->schemaSistema();

        $ticket = DB::table($ti . 'tckt_slctd as t')
            ->leftJoin($ti . 'incdncia as i', 't.incidencia', '=', 'i.id')
            ->leftJoin($ti . 'prrdd as p', 't.id_prioridad', '=', 'p.id')
            ->leftJoin($sis . 'p_lgin as l', 't.idlogin_or', '=', 'l.cdgo_lgin')
            ->leftJoin($sis . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->leftJoin($sis . 'a_areas as a', 't.idarea', '=', 'a.cdgo_area')
            ->leftJoin($sis . 'p_crgos as c', 't.idcargo', '=', 'c.cdgo_crgo')
            ->leftJoin($ti . 'bndja as b', 't.id', '=', 'b.idticket')
            ->leftJoin($sis . 'p_lgin as ldes', 'b.idlogin_des', '=', 'ldes.cdgo_lgin')
            ->leftJoin($sis . 'p_usrios as udes', 'ldes.cdgo_usrios', '=', 'udes.cdgo_usrios')
            ->where('t.id', $idTicket)
            ->orderBy('b.id', 'desc') // Obtener el último estado de la bandeja
            ->select(
                't.id', 't.asunto', 't.fecha', 't.descripcion',
                'i.detalle as servicio', 'p.nombre as prioridad', 'p.razon as prioridad_razon',
                'u.no_crto as solicitante', 'u.nu_tele as telefono', 'u.de_dire as anydesk',
                'a.de_area as area', 'c.de_crgo as cargo',
                'b.situacion', 'udes.no_crto as atendido_por', 'b.fecha as fecha_actualizacion'
            )
            ->first();

        if ($ticket) {
            $descHTML = '<span class="text-gray-400 italic">Sin descripción</span>';
            if (!empty($ticket->descripcion) && file_exists(public_path('contenidos/' . $ticket->descripcion))) {
                $descHTML = file_get_contents(public_path('contenidos/' . $ticket->descripcion));
            }
            $ticket->descripcion_html = $descHTML;
            $ticket->fecha_formateada = Carbon::parse($ticket->fecha)->format('d/m/Y H:i:s');
            $ticket->fecha_fin = in_array($ticket->situacion, [4, 5]) ? Carbon::parse($ticket->fecha_actualizacion)->format('d/m/Y H:i:s') : 'Aún pendiente';
            
            $estados = [1 => 'Requerimiento', 2 => 'Pendiente', 3 => 'En Proceso', 4 => 'Finalizado', 5 => 'Confirmado'];
            $ticket->estado_texto = $estados[$ticket->situacion] ?? 'Desconocido';
            $ticket->has_zip = file_exists(public_path('adjuntados/' . $ticket->id . '.zip'));
        }

        return response()->json($ticket ?: ['error' => 'No encontrado']);
    }

    public function derivar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) return response()->json(['message' => 'Acceso denegado.'], 403);
        
        $idTicket = (int) $request->input('id_ticket');
        $idLoginDes = (int) $request->input('id_login_destino');
        $situacionActual = (int) $request->input('situacion_actual');

        if ($idLoginDes <= 0) return response()->json(['success' => false, 'message' => 'Debe seleccionar un destino válido.']);

        $user = Auth::user();
        if (!$user instanceof User) return response()->json(['success' => false, 'message' => 'Sesión inválida.'], 401);

        $ti = $this->schemaTi();
        $idLogin = (int) $user->cdgo_lgin;

        // Desactivar registros con la situación actual
        DB::table($ti . 'bndja')
            ->where('idticket', $idTicket)
            ->where('situacion', $situacionActual)
            ->update(['estado' => 0]);

        // Insertar nuevo registro derivado a otra persona con situación 2 (Pendiente)
        DB::statement(
            "INSERT INTO {$ti}bndja (idticket, idlogin_or, idlogin_des, situacion, estado)
             SELECT idticket, ?, ?, 2, 1 FROM {$ti}bndja WHERE idticket = ? ORDER BY id DESC LIMIT 1",
            [$idLogin, $idLoginDes, $idTicket]
        );

        return response()->json(['success' => true, 'message' => 'Ticket derivado exitosamente.']);
    }
    // ========================================================================
    // MOTOR DE CONSULTAS Y REGLAS DE NEGOCIO (Query Builder)
    // ========================================================================

    /**
     * Consulta base centralizada. Aplica los filtros a la subconsulta del último estado
     * del ticket, garantizando que el Kanban y los gráficos coincidan perfectamente.
     */
    private function getBaseQuery(string $fInicio, string $fFin, int $fOti, ?int $fArea, ?int $fServicio, int $fId = 0)
    {
        $ti = $this->schemaTi();
        $sis = $this->schemaSistema();

        $latestBandeja = DB::table($ti . 'bndja')
            ->select('idticket', DB::raw('MAX(fecha) as max_fecha'))
            ->where('estado', 1);
            
        // Si hay búsqueda por ID, ignoramos el rango de fechas para mayor precisión
        if ($fId > 0) {
            $latestBandeja->where('idticket', $fId);
        } else {
            $latestBandeja->whereBetween('fecha', [$fInicio . ' 00:00:00', $fFin . ' 23:59:59']);
        }

        if ($fOti > 0) {
            $latestBandeja->where('idlogin_des', $fOti);
        }
        $latestBandeja->groupBy('idticket');

        $query = DB::table($ti . 'bndja as b')
            ->joinSub($latestBandeja, 'bx', function ($join) {
                $join->on('b.idticket', '=', 'bx.idticket')
                     ->on('b.fecha', '=', 'bx.max_fecha');
            })
            ->join($ti . 'tckt_slctd as t', 'b.idticket', '=', 't.id')
            ->leftJoin($sis . 'p_lgin as l', 't.idlogin_or', '=', 'l.cdgo_lgin')
            ->leftJoin($sis . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('b.estado', 1);

        if ($fArea !== null && $fArea > 0) {
            $query->where('t.idarea', $fArea);
        }
        if ($fServicio !== null && $fServicio > 0) {
            $query->where('t.incidencia', $fServicio);
        }

        return $query;
    }

    private function buildKanbanPayload(string $fInicio, string $fFin, int $filtroOti, ?int $filtroArea, ?int $filtroIncidencia, int $filtroId = 0): array 
    {
        $sis = $this->schemaSistema();
        $ti = $this->schemaTi();

        $rows = $this->getBaseQuery($fInicio, $fFin, $filtroOti, $filtroArea, $filtroIncidencia, $filtroId)
            ->join($sis . 'p_lgin as l_des', 'b.idlogin_des', '=', 'l_des.cdgo_lgin')
            ->join($sis . 'p_usrios as u_des', 'l_des.cdgo_usrios', '=', 'u_des.cdgo_usrios')
            ->leftJoin($ti . 'prrdd as p', 't.id_prioridad', '=', 'p.id')
            ->select('t.id', 't.asunto', 'b.fecha', 'u_des.no_crto AS nom', 'b.situacion', 'p.nombre as prioridad')
            ->orderByDesc('b.fecha') // ORDEN CORRECTO: los más recientes arriba
            ->get();

        $bandejaRaw = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];

        foreach ($rows as $row) {
            $sit = (int) $row->situacion;
            if ($sit < 1 || $sit > 5) continue;

            $fechaCarbon = Carbon::parse($row->fecha);
            $nom = (string) ($row->nom ?? '');
            
            $primerNombre = (string) ($row->primer_nombre ?? '');
            if ($primerNombre === '' && $nom !== '') {
                $primerNombre = explode(' ', trim($nom), 2)[0];
            }

            $bandejaRaw[$sit][] = [
                'id' => (int) $row->id,
                'texto' => (string) $row->asunto,
                'usuario' => $nom,
                'primer_nombre' => $primerNombre,
                'hora' => $fechaCarbon->format('d/m/Y H:i'),
                'prioridad' => (string) ($row->prioridad ?? 'Normal'),
            ];
        }

        return [
            'bandeja_raw' => $bandejaRaw,
            'count' => count($rows),
            'avance' => $this->calcularPorcentajeAvance($fInicio, $fFin, $filtroOti),
        ];
    }

    private function calcularPorcentajeAvance(string $fInicio, string $fFin, int $filtroOti, int $filtroId = 0): ?float
    {
        $ti = $this->schemaTi();
        $query = DB::table($ti . 'bndja as b')
            ->where('b.estado', 1);

        if ($filtroId > 0) {
            $query->where('b.idticket', $filtroId);
        } else {
            $query->whereBetween('b.fecha', [$fInicio . ' 00:00:00', $fFin . ' 23:59:59']);
        }

        if ($filtroOti > 0) {
            $query->where('b.idlogin_des', $filtroOti);
        }

        $totales = $query->selectRaw('
            COUNT(CASE WHEN situacion IN (4, 5) THEN 1 END) as resueltos,
            COUNT(*) as total
        ')->first();

        if (!$totales || $totales->total == 0) return 0;

        return round(($totales->resueltos / $totales->total) * 100, 2);
    }

    // ========================================================================
    // HELPERS Y LISTAS
    // ========================================================================

    private function schemaTi(): string
    {
        return rtrim((string) config('auth.sigtram_ti_schema', 'ti'), '.') . '.';
    }

    private function schemaSistema(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    private function assertCargoConsultaAdmin(int $cargoId): void
    {
        if (!in_array($cargoId, self::CARGOS_CONSULTA_ADMIN, true)) {
            abort(403, 'Su cargo no tiene acceso al reporte de administración de tickets.');
        }
    }

    private function tienePermisoAdministracion(User $user, int $cargoId): bool
    {
        // Debe coincidir con de_dreccon del menú/permisos en BD
        $rutas = [
            '/mod_ti/helpdesk/consulta',
            '/mod_ti/helpdesk/consulta.blade.php',
            '/mod_ti/helpdesk/consulta.php',
        ];
        $perfil = (int) $user->cdgo_prfle;
        $schema = $this->schemaSistema();

        return DB::table($schema . 'r_prmsos as p')
            ->join($schema . 'a_rles as r', 'p.cdgo_rle', '=', 'r.cdgo_rle')
            ->where('p.cdgo_prfle', $perfil)
            ->whereIn('r.de_dreccon', $rutas)
            ->where(function ($q) use ($cargoId) {
                $q->whereNull('p.cdgo_crgo')->orWhere('p.cdgo_crgo', $cargoId);
            })
            ->exists();
    }

    private function assertPermisoAdministracion(User $user, int $cargoId): void
    {
        if (!$this->tienePermisoAdministracion($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function normalizeDate(?string $value, string $default): string
    {
        if ($value === null || $value === '') return $default;
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return $default;
        }
    }

    private function listOtiPersonal()
    {
        $sis = $this->schemaSistema();
        return DB::table($sis . 'p_lgin as p_lgin')
            ->join($sis . 'r_crgos_lgin as r', 'p_lgin.cdgo_lgin', '=', 'r.cdgo_lgin')
            ->join($sis . 'p_crgos as c', 'r.cdgo_crgo', '=', 'c.cdgo_crgo')
            ->join($sis . 'p_usrios as u', 'p_lgin.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('c.cdgo_area', self::AREA_OTI)
            ->where('p_lgin.in_esta', '1')
            ->select('p_lgin.cdgo_lgin AS id', 'u.no_crto AS name')
            ->distinct()
            ->orderBy('u.no_crto')
            ->get();
    }

    private function listAreas()
    {
        return DB::table($this->schemaSistema() . 'a_areas')
            ->where('in_esta', '1')
            ->orderBy('de_area')
            ->select(['cdgo_area AS id', 'de_area AS name'])
            ->get();
    }

    private function listIncidencias()
    {
        return DB::table($this->schemaTi() . 'incdncia')
            ->orderBy('detalle')
            ->select(['id', 'detalle AS name'])
            ->get();
    }
}