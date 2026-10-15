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
 * Controlador para la Bandeja OTI (Helpdesk).
 * Migrado desde: antiguo\sigtram\Presentacion\mod_ti\helpdesk\bandeja.php
 *
 * Lógica del sistema antiguo:
 * - Usuarios de Área OTI (103): ven bandeja filtrada por fecha/área con acciones
 *   Confirmar (sit 2→3), Finalizar (sit 3→4), Derivar.
 * - Secretaria OTI (login 14528): recibe tickets y los deriva al personal OTI.
 * - La tabla se refresca via AJAX cada 5 segundos.
 */
class IngresarBandejaController extends Controller
{
    /** ID de login de la Secretaria OTI (Kassandra). */
    private const LOGIN_OTI_SECRETARIA = 14528;

    /** Área OTI */
    private const AREA_OTI = 103;

    /** Cargo Jefe OTI (excluido de la lista de derivación). */

    // ========================================================================
    // VISTAS PRINCIPALES
    // ========================================================================

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

        $idLogin = (int) $user->cdgo_lgin;
        $hoy = Carbon::now()->format('Y-m-d');

        // Determinar el área del usuario para saber si es OTI
        $areaId = $this->getAreaUsuario($idLogin, $cargoId);
        $esOti = ($areaId === self::AREA_OTI);
        $esSecretaria = ($idLogin === self::LOGIN_OTI_SECRETARIA);

        // Cargar listas para filtros (solo para OTI)
        $areas = $esOti ? $this->listAreas() : collect();

        // Cargar tickets iniciales
        if ($esOti) {
            // OTI: carga bandeja del usuario logueado filtrada por hoy
            $tickets = $this->listarBandejaOti($idLogin, $hoy, $hoy, null);
        } else {
            // Usuarios externos: solo ven sus propios tickets
            $tickets = $this->listarTicketsPersonales($idLogin);
        }

        // Cargar tickets de secretaria (si es secretaria)
        $ticketsSecretaria = $esSecretaria ? $this->listarTicketsSecretaria() : collect();

        // Personal OTI para derivar
        $personalOti = ($esOti || $esSecretaria) ? $this->listPersonalOti() : collect();

        return view('mod_ti.helpdesk.bandeja', [
            'tickets'            => $tickets,
            'ticketsSecretaria'  => $ticketsSecretaria,
            'areas'              => $areas,
            'personalOti'        => $personalOti,
            'esOti'              => $esOti,
            'esSecretaria'       => $esSecretaria,
            'idLogin'            => $idLogin,
            'fechaInicio'        => $hoy,
            'fechaFin'           => $hoy,
        ]);
    }

    // ========================================================================
    // ENDPOINTS AJAX
    // ========================================================================

    /**
     * Refrescar la tabla OTI (AJAX) - equivalente a report.ajax.php?query=listarbandeja
     */
    public function tablaOti(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(401);
        }

        $idLogin = (int) $user->cdgo_lgin;
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            abort(403);
        }

        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $areaId = $request->input('area') !== null && $request->input('area') !== '' ? (int) $request->input('area') : null;
        $ticketId = $request->input('search_id') !== null && $request->input('search_id') !== '' ? (int) $request->input('search_id') : null;

        $tickets = $this->listarBandejaOti($idLogin, $fechaInicio, $fechaFin, $areaId, $ticketId);

        return view('mod_ti.helpdesk.partials.tabla_oti_inner', [
            'tickets' => $tickets,
        ]);
    }

    /**
     * Detalle de un ticket (AJAX) - equivalente a bandeja_oti.php
     */
    public function ticketDetalle(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $idTicket = (int) $request->input('id_ticket', 0);
        if ($idTicket <= 0) {
            return response()->json(['error' => 'ID inválido'], 400);
        }

        $ti = $this->schemaTi();
        $sis = $this->schemaSistema();

        // Consulta principal - replica de bandeja_oti.php
        $ticket = DB::table($ti . 'tckt_slctd as ts')
            ->join($ti . 'incdncia as i', 'ts.incidencia', '=', 'i.id')
            ->join($sis . 'p_lgin as pl', 'ts.idlogin_or', '=', 'pl.cdgo_lgin')
            ->join($sis . 'p_usrios as pu', 'pl.cdgo_usrios', '=', 'pu.cdgo_usrios')
            ->join($sis . 'r_crgos_lgin as pc', 'ts.idcargo', '=', 'pc.cdgo_crgo')
            ->join($sis . 'p_crgos as pg', 'pc.cdgo_crgo', '=', 'pg.cdgo_crgo')
            ->join($sis . 'a_areas as aa', 'ts.idarea', '=', 'aa.cdgo_area')
            ->where('ts.id', $idTicket)
            ->select(
                'ts.id', 'ts.asunto', 'ts.descripcion', 'ts.fecha',
                'i.detalle as servicio',
                'pu.no_crto as solicitante', 'pu.nu_tele as telefono', 'pu.de_dire as anydesk',
                'pg.de_crgo as cargo', 'aa.de_area as area',
                'ts.puntuacion', 'ts.detalles'
            )
            ->first();

        if (!$ticket) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        // Urgencia / Prioridad
        $urgencia = DB::table($ti . 'tckt_slctd as t')
            ->join($ti . 'prrdd as p', 't.id_prioridad', '=', 'p.id')
            ->where('t.id', $idTicket)
            ->select('p.nombre', 'p.razon')
            ->first();

        $ticket->prioridad = $urgencia->nombre ?? 'Normal';
        $ticket->urgencia_razon = $urgencia->razon ?? '';
        $ticket->prioridad_razon = $urgencia->razon ?? '';

        // Obtener la situación actual
        $situacion = DB::table($ti . 'bndja')
            ->where('idticket', $idTicket)
            ->where('estado', 1)
            ->value('situacion');
        $ticket->situacion = $situacion !== null ? (int)$situacion : 1;

        // Derivado por - Quien derivó el ticket (situación 2, más reciente)
        $derivadoPor = DB::table($ti . 'bndja as tb')
            ->join($sis . 'p_lgin as pl', 'tb.idlogin_or', '=', 'pl.cdgo_lgin')
            ->join($sis . 'p_usrios as pu', 'pl.cdgo_usrios', '=', 'pu.cdgo_usrios')
            ->where('tb.idticket', $idTicket)
            ->where('tb.situacion', 2)
            ->whereRaw('tb.fecha = (SELECT MAX(fecha) FROM ' . $ti . 'bndja WHERE idticket = ? AND situacion = 2)', [$idTicket])
            ->select('pu.no_crto')
            ->first();
        $ticket->derivado_por = $derivadoPor->no_crto ?? '-';

        // Atendido por - Quien atendió (situación 3, 4 o 5, más reciente)
        $atendidoPor = DB::table($ti . 'bndja as tb')
            ->join($sis . 'p_lgin as pl', 'tb.idlogin_or', '=', 'pl.cdgo_lgin')
            ->join($sis . 'p_usrios as pu', 'pl.cdgo_usrios', '=', 'pu.cdgo_usrios')
            ->where('tb.idticket', $idTicket)
            ->whereIn('tb.situacion', [3, 4, 5])
            ->whereRaw('tb.fecha = (SELECT MAX(fecha) FROM ' . $ti . 'bndja WHERE idticket = ? AND situacion IN (3, 4, 5))', [$idTicket])
            ->select('pu.no_crto')
            ->first();
        $ticket->atendido_por = $atendidoPor->no_crto ?? '-';

        // Fecha fin (situación 4)
        $fechaFin = DB::table($ti . 'bndja')
            ->where('idticket', $idTicket)
            ->where('situacion', 4)
            ->orderByDesc('fecha')
            ->value('fecha');
        $ticket->fecha_fin = $fechaFin ? Carbon::parse($fechaFin)->format('d/m/Y H:i:s') : 'Aún pendiente';

        // Descripción HTML
        $descHTML = '<span class="text-gray-400 italic">Sin descripción</span>';
        if (!empty($ticket->descripcion) && file_exists(public_path('contenidos/' . $ticket->descripcion))) {
            $descHTML = file_get_contents(public_path('contenidos/' . $ticket->descripcion));
        }
        $ticket->descripcion_html = $descHTML;

        $ticket->fecha_formateada = Carbon::parse($ticket->fecha)->format('d/m/Y H:i:s');
        $ticket->has_zip = file_exists(public_path('adjuntados/' . $ticket->id . '.zip'));
        $ticket->url_zip = $ticket->has_zip ? asset('adjuntados/' . $ticket->id . '.zip') : null;

        return response()->json($ticket);
    }

    /**
     * Confirmar ticket OTI (sit 2 → 3) - equivalente a actualizar_estado_ticket_oti.php
     */
    public function confirmarTicket(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 401);
        }

        $idTicket = (int) $request->input('id_ticket', 0);
        if ($idTicket <= 0) {
            return response()->json(['success' => false, 'message' => 'ID inválido'], 400);
        }

        $ti = $this->schemaTi();

        // Desactivar el último registro activo del ticket
        DB::statement(
            "UPDATE {$ti}bndja SET estado = 0 WHERE id IN (SELECT id FROM {$ti}bndja WHERE idticket = ? ORDER BY id DESC LIMIT 1)",
            [$idTicket]
        );

        // Insertar nuevo registro con situación 3 (En Proceso)
        DB::statement(
            "INSERT INTO {$ti}bndja (idticket, idlogin_or, idlogin_des, situacion, estado)
             SELECT idticket, idlogin_des, idlogin_des, 3, 1 FROM {$ti}bndja WHERE idticket = ? ORDER BY id DESC LIMIT 1",
            [$idTicket]
        );

        return response()->json(['success' => true, 'message' => 'Ticket confirmado exitosamente.']);
    }

    /**
     * Finalizar ticket OTI (sit 3 → 4) - equivalente a actualizar_estado_ticket_oti_finalizar.php
     */
    public function finalizarTicket(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 401);
        }

        $idTicket = (int) $request->input('id_ticket', 0);
        if ($idTicket <= 0) {
            return response()->json(['success' => false, 'message' => 'ID inválido'], 400);
        }

        $ti = $this->schemaTi();

        // Desactivar el último registro activo del ticket
        DB::statement(
            "UPDATE {$ti}bndja SET estado = 0 WHERE id IN (SELECT id FROM {$ti}bndja WHERE idticket = ? ORDER BY id DESC LIMIT 1)",
            [$idTicket]
        );

        // Insertar nuevo registro con situación 4 (Finalizado)
        DB::statement(
            "INSERT INTO {$ti}bndja (idticket, idlogin_or, idlogin_des, situacion, estado)
             SELECT idticket, idlogin_des, idlogin_des, 4, 1 FROM {$ti}bndja WHERE idticket = ? ORDER BY id DESC LIMIT 1",
            [$idTicket]
        );

        return response()->json(['success' => true, 'message' => 'Ticket finalizado exitosamente.']);
    }

    /**
     * Derivar ticket a otro personal OTI - equivalente a derivar_oti1.php / derivar_oti2.php / derivar_secretaria_oti.php
     */
    public function derivarTicket(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 401);
        }

        $idTicket = (int) $request->input('id_ticket', 0);
        $idLoginDes = (int) $request->input('id_login_destino', 0);
        $situacionActual = (int) $request->input('situacion_actual', 2);

        if ($idTicket <= 0 || $idLoginDes <= 0) {
            return response()->json(['success' => false, 'message' => 'Parámetros inválidos'], 400);
        }

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

    /**
     * Listar áreas para el filtro (AJAX) - equivalente a report.ajax.php?query=area
     */
    public function listarAreasJson(Request $request): JsonResponse
    {
        return response()->json($this->listAreas());
    }

    // ========================================================================
    // CONSULTAS A LA BASE DE DATOS
    // ========================================================================

    /**
     * Bandeja OTI - lista de tickets asignados al personal OTI.
     * Replica: report.ajax.php?query=listarbandeja
     */
    private function listarBandejaOti(int $idLogin, string $fechaInicio, string $fechaFin, ?int $areaId, ?int $ticketId = null)
    {
        $ti = $this->schemaTi();
        $sis = $this->schemaSistema();

        $params = [$idLogin];

        $query = "SELECT DISTINCT ts.id, ts.asunto, i.detalle, ts.fecha, pu.no_crto, b.situacion,
                          p.nombre as prioridad, aa.de_area
                  FROM {$ti}tckt_slctd ts
                  INNER JOIN {$ti}bndja b ON ts.id = b.idticket
                  INNER JOIN {$ti}incdncia i ON i.id = ts.incidencia
                  INNER JOIN {$sis}p_lgin pl ON ts.idlogin_or = pl.cdgo_lgin
                  INNER JOIN {$sis}p_usrios pu ON pu.cdgo_usrios = pl.cdgo_usrios
                  LEFT JOIN {$ti}prrdd p ON ts.id_prioridad = p.id
                  LEFT JOIN {$sis}a_areas aa ON ts.idarea = aa.cdgo_area
                  WHERE b.idlogin_des = ? AND b.estado = 1";

        if ($ticketId) {
            $query .= " AND ts.id = ?";
            $params[] = $ticketId;
        } else {
            $query .= " AND (
                        (b.situacion IN (2,3)" . ($areaId ? " AND ts.idarea = {$areaId}" : "") . ")
                        OR (1=1" . ($areaId ? " AND ts.idarea = {$areaId}" : "") . " AND CAST(b.fecha AS date) BETWEEN ? AND ?)
                    )";
            $params[] = $fechaInicio;
            $params[] = $fechaFin;
        }

        $query .= " ORDER BY ts.fecha DESC";

        $rows = DB::select($query, $params);

        return collect($rows)->map(function ($row) {
            return (object) [
                'id'          => $row->id,
                'asunto'      => $row->asunto,
                'detalle'     => $row->detalle,
                'fecha'       => $row->fecha,
                'solicitante' => $row->no_crto,
                'situacion'   => (int) $row->situacion,
                'prioridad'   => $row->prioridad ?? 'Normal',
                'area'        => $row->de_area ?? '-',
            ];
        });
    }

    /**
     * Tickets personales (usuarios que no son OTI) - ven solo sus propios tickets.
     * Replica: bandeja.php línea 81-86
     */
    private function listarTicketsPersonales(int $idLogin)
    {
        $ti = $this->schemaTi();
        $sis = $this->schemaSistema();

        $rows = DB::table($ti . 'tckt_slctd as ts')
            ->join($ti . 'bndja as b', 'ts.id', '=', 'b.idticket')
            ->join($ti . 'incdncia as i', 'i.id', '=', 'ts.incidencia')
            ->join($sis . 'p_lgin as pl', 'ts.idlogin_or', '=', 'pl.cdgo_lgin')
            ->join($sis . 'p_usrios as pu', 'pu.cdgo_usrios', '=', 'pl.cdgo_usrios')
            ->leftJoin($ti . 'prrdd as p', 'ts.id_prioridad', '=', 'p.id')
            ->leftJoin($sis . 'a_areas as aa', 'ts.idarea', '=', 'aa.cdgo_area')
            ->where('ts.idlogin_or', $idLogin)
            ->where('b.estado', 1)
            ->select([
                'ts.id', 'ts.asunto', 'i.detalle', 'ts.fecha',
                'pu.no_crto', 'b.situacion', 'p.nombre as prioridad',
                'aa.de_area',
            ])
            ->distinct()
            ->orderByDesc('ts.fecha')
            ->limit(50)
            ->get();

        return $rows->map(function ($row) {
            return (object) [
                'id'          => $row->id,
                'asunto'      => $row->asunto,
                'detalle'     => $row->detalle,
                'fecha'       => $row->fecha,
                'solicitante' => $row->no_crto,
                'situacion'   => (int) $row->situacion,
                'prioridad'   => $row->prioridad ?? 'Normal',
                'area'        => $row->de_area ?? '-',
            ];
        });
    }

    /**
     * Tickets destinados a la Secretaria OTI (login 14528).
     * Replica: bandeja.php línea 92-97
     */
    private function listarTicketsSecretaria()
    {
        $ti = $this->schemaTi();
        $sis = $this->schemaSistema();

        $rows = DB::table($ti . 'tckt_slctd as ts')
            ->join($ti . 'bndja as b', 'ts.id', '=', 'b.idticket')
            ->join($ti . 'incdncia as i', 'i.id', '=', 'ts.incidencia')
            ->join($sis . 'p_lgin as pl', 'ts.idlogin_or', '=', 'pl.cdgo_lgin')
            ->join($sis . 'p_usrios as pu', 'pu.cdgo_usrios', '=', 'pl.cdgo_usrios')
            ->leftJoin($ti . 'prrdd as p', 'ts.id_prioridad', '=', 'p.id')
            ->leftJoin($sis . 'a_areas as aa', 'ts.idarea', '=', 'aa.cdgo_area')
            ->where('b.idlogin_des', self::LOGIN_OTI_SECRETARIA)
            ->where('b.estado', 1)
            ->select([
                'ts.id', 'ts.asunto', 'i.detalle', 'ts.fecha',
                'pu.no_crto', 'b.situacion', 'p.nombre as prioridad',
                'aa.de_area',
            ])
            ->distinct()
            ->orderByDesc('ts.fecha')
            ->get();

        return $rows->map(function ($row) {
            return (object) [
                'id'          => $row->id,
                'asunto'      => $row->asunto,
                'detalle'     => $row->detalle,
                'fecha'       => $row->fecha,
                'solicitante' => $row->no_crto,
                'situacion'   => (int) $row->situacion,
                'prioridad'   => $row->prioridad ?? 'Normal',
                'area'        => $row->de_area ?? '-',
            ];
        });
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function schemaTi(): string
    {
        return rtrim((string) config('auth.sigtram_ti_schema', 'ti'), '.') . '.';
    }

    private function schemaSistema(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * Obtener el área del usuario según su cargo activo.
     */
    private function getAreaUsuario(int $idLogin, int $cargoId): int
    {
        $sis = $this->schemaSistema();
        $cargo = DB::table($sis . 'r_crgos_lgin as r')
            ->join($sis . 'p_crgos as c', 'c.cdgo_crgo', '=', 'r.cdgo_crgo')
            ->leftJoin($sis . 'a_areas as a', 'a.cdgo_area', '=', 'c.cdgo_area')
            ->where('r.cdgo_lgin', $idLogin)
            ->where('c.cdgo_crgo', $cargoId)
            ->select('a.cdgo_area')
            ->first();

        return $cargo ? (int) ($cargo->cdgo_area ?? 0) : 0;
    }

    /**
     * Lista personal OTI para derivar (excluye jefe OTI cargo 496).
     * Replica: derivar_secretaria_oti.php / derivar_oti1.php
     */
    private function listPersonalOti()
    {
        $sis = $this->schemaSistema();
        return DB::table($sis . 'p_lgin as pl')
            ->join($sis . 'r_crgos_lgin as r', 'pl.cdgo_lgin', '=', 'r.cdgo_lgin')
            ->join($sis . 'p_crgos as c', 'r.cdgo_crgo', '=', 'c.cdgo_crgo')
            ->join($sis . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->join($sis . 'p_usrios as u', 'pl.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('c.cdgo_area', self::AREA_OTI)
            ->where('pl.in_esta', '1')
            ->select('pl.cdgo_lgin as id', 'u.no_crto as name')
            ->distinct()
            ->orderBy('u.no_crto')
            ->get();
    }

    /**
     * Lista de áreas activas para filtro.
     * Replica: report.ajax.php?query=area
     */
    private function listAreas()
    {
        $sis = $this->schemaSistema();
        return DB::table($sis . 'a_areas')
            ->where('in_esta', '1')
            ->orderBy('de_area')
            ->select('cdgo_area as id', 'de_area as name')
            ->get();
    }
}
