<?php

namespace App\Http\Controllers\mod_ti\helpdesk;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bandeja de tickets helpdesk (Área externa)
 */
class IngresarServiciosController extends Controller
{
    private const LOGIN_OTI_SECRETARIA = 14528;
    private const LOGIN_EXCLUIDO_OTI = 469;
    private const AREA_OTI = 103;
    private const LOGIN_HELP_SPECIAL = 72;

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

        $this->assertPermisoBandejaExterna($user, $cargoId);

        $idLogin = (int) $user->cdgo_lgin;
        $ctx = $this->cargoAreaContext($user, $cargoId);

        $tickets = $this->listarTicketsBandeja($idLogin);
        $incidencias = $this->listarIncidencias();
        
        // 2. VALIDAR EL TELÉFONO DEL USUARIO
        $phone_ok = false;
        $usrData = DB::table('sistema.p_usrios as u')
            ->join('sistema.p_lgin as l', 'u.cdgo_usrios', '=', 'l.cdgo_usrios')
            ->where('l.cdgo_lgin', $idLogin)
            ->select('u.nu_tele', 'u.de_dire')
            ->first();
            
        $phone_val = $usrData ? trim((string)$usrData->nu_tele) : '';
        $anydesk_val = $usrData ? trim((string)$usrData->de_dire) : '';
        $phone_ok = ($phone_val !== '');

        // 3. VALIDACIÓN DE HORARIO
        $horario = $this->obtenerHorarioAtencion();
        $horaActual = now()->format('H:i:s');
        $isRestricted = true;

        if ($cargoId === 496 || ($horaActual >= $horario['inicio'] && $horaActual <= $horario['fin'])) {
            $isRestricted = false;
        }

        $horarioInicioFmt = substr($horario['inicio'], 0, 5);
        $horarioFinFmt = substr($horario['fin'], 0, 5);

        return view('mod_ti.helpdesk.index', [
            'tickets' => $tickets,
            'incidencias' => $incidencias,
            'idLogin' => $idLogin,
            'areaId' => $ctx['area_id'],
            'phone_ok' => $phone_ok,
            'phone_val' => $phone_val,
            'anydesk_val' => $anydesk_val,
            'isRestricted' => $isRestricted,
            'horarioInicioFmt' => $horarioInicioFmt,
            'horarioFinFmt' => $horarioFinFmt,
            'showPhoneAlert' => session('showPhoneAlert', false)
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) abort(401);
        $idLogin = (int) $user->cdgo_lgin;
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        // 1. VALIDACIÓN DE HORARIO EN EL SERVIDOR
        $horario = $this->obtenerHorarioAtencion();
        $horaActual = now()->format('H:i:s');
        $isRestricted = true;

        if ($cargoId === 496 || ($horaActual >= $horario['inicio'] && $horaActual <= $horario['fin'])) {
            $isRestricted = false;
        }

        if ($isRestricted) {
            $horarioInicioFmt = substr($horario['inicio'], 0, 5);
            $horarioFinFmt = substr($horario['fin'], 0, 5);
            return redirect()->back()->withErrors([
                'horario' => "Fuera del horario de atención ({$horarioInicioFmt} a {$horarioFinFmt})."
            ]);
        }

        $ctx = $this->cargoAreaContext($user, $cargoId);
        $idArea = $ctx['area_id'];

        $phone_ok = false;
        $usrData = DB::table('sistema.p_usrios as u')
            ->join('sistema.p_lgin as l', 'u.cdgo_usrios', '=', 'l.cdgo_usrios')
            ->where('l.cdgo_lgin', $idLogin)
            ->select('u.nu_tele')
            ->first();
        if ($usrData && trim((string)$usrData->nu_tele) !== '') {
            $phone_ok = true;
        }

        $telefono = $request->input('telefono_contacto', '');
        $anydesk = $request->input('anydesk_contacto', '');

        if (!$phone_ok && $telefono === '') {
            return redirect()->back()->with('showPhoneAlert', true);
        }

        // Actualizar el teléfono y AnyDesk del usuario en la base de datos si son proporcionados (como respaldo)
        if ($telefono !== '' || $anydesk !== '') {
            $schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
            $updateData = [];
            if ($telefono !== '') $updateData['nu_tele'] = $telefono;
            if ($anydesk !== '') $updateData['de_dire'] = $anydesk;

            DB::table($schemaSistema . 'p_usrios as u')
                ->join($schemaSistema . 'p_lgin as l', 'u.cdgo_usrios', '=', 'l.cdgo_usrios')
                ->where('l.cdgo_lgin', $idLogin)
                ->update($updateData);
        }

        $asunto = $request->input('asunto', '');
        $incidencia_id = (int) $request->input('incidencia', 0);
        $descripcionHtml = $request->input('descripcion', '');
        $esUrgente = $request->input('urgencia') === 'urgente';
        $urgenciaNombre = $esUrgente ? 'Urgente' : 'Normal';
        $urgenciaRazon = $esUrgente ? $request->input('urgencia_descripcion', '') : '';

        $ti = rtrim((string) config('auth.sigtram_ti_schema', 'ti'), '.') . '.';

        $idPrioridad = DB::table($ti . 'prrdd')->insertGetId([
            'nombre' => $urgenciaNombre,
            'razon' => $urgenciaRazon
        ], 'id');

        $fechaActual = now();
        $idTicket = DB::table($ti . 'tckt_slctd')->insertGetId([
            'asunto' => $asunto,
            'incidencia' => $incidencia_id,
            'descripcion' => '', 
            'fecha' => $fechaActual,
            'estado' => 1,
            'idcargo' => $cargoId,
            'idarea' => $idArea,
            'idlogin_or' => $idLogin,
            'id_prioridad' => $idPrioridad
        ], 'id');

        if ($idTicket) {
            $nombreArchivoHtml = $idTicket . '.html';
            $rutaHtml = public_path('contenidos');
            if (!file_exists($rutaHtml)) { mkdir($rutaHtml, 0755, true); }
            file_put_contents($rutaHtml . '/' . $nombreArchivoHtml, $descripcionHtml);

            DB::table($ti . 'tckt_slctd')
                ->where('id', $idTicket)
                ->update(['descripcion' => $nombreArchivoHtml]);

            DB::table($ti . 'bndja')->insert([
                'fecha' => $fechaActual,
                'idticket' => $idTicket,
                'idlogin_or' => $idLogin,
                'idlogin_des' => self::LOGIN_OTI_SECRETARIA, 
                'situacion' => 1,
                'estado' => 1
            ]);

            if ($request->hasFile('archivos') && class_exists('ZipArchive')) {
                $archivos = $request->file('archivos');
                $rutaZip = public_path('adjuntados');
                if (!file_exists($rutaZip)) { mkdir($rutaZip, 0755, true); }
                
                $nombreZip = $rutaZip . '/' . $idTicket . '.zip';
                $zip = new \ZipArchive();
                
                if ($zip->open($nombreZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                    foreach ($archivos as $archivo) {
                        if ($archivo->isValid()) {
                            $zip->addFile($archivo->getRealPath(), $archivo->getClientOriginalName());
                        }
                    }
                    $zip->close();
                }
            }

            return redirect()->back()->with('ticket_enviado', true)->with('ticket_id', $idTicket);
        }

        return redirect()->back();
    }

    public function detalle(Request $request): JsonResponse
    {
        $ticketId = (int) $request->input('id');
        $user = Auth::user();
        $idLogin = (int) $user->cdgo_lgin;
        $ti = rtrim((string) config('auth.sigtram_ti_schema', 'ti'), '.') . '.';

        $sistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        
        $ticket = DB::table($ti . 'tckt_slctd as t')
            ->leftJoin($ti . 'incdncia as i', 't.incidencia', '=', 'i.id')
            ->leftJoin($ti . 'prrdd as p', 't.id_prioridad', '=', 'p.id')
            ->join($sistema . 'p_lgin as pl', 't.idlogin_or', '=', 'pl.cdgo_lgin')
            ->join($sistema . 'p_usrios as pu', 'pu.cdgo_usrios', '=', 'pl.cdgo_usrios')
            ->where('t.id', $ticketId)
            ->where('t.idlogin_or', $idLogin) 
            ->select('t.*', 'i.detalle as nombre_incidencia', 'p.nombre as prioridad_nombre', 'pu.no_crto', 'pu.nu_tele', 'pu.de_dire')
            ->first();

        if ($ticket) {
            $contenidoDesc = '<p class="text-gray-400 italic">Sin descripción detallada.</p>';
            if (!empty($ticket->descripcion)) {
                $rutaArchivo = public_path('contenidos/' . $ticket->descripcion);
                if (file_exists($rutaArchivo)) {
                    $contenidoDesc = file_get_contents($rutaArchivo);
                }
            }
            $ticket->descripcion_html = $contenidoDesc;
            $ticket->fecha_fmt = Carbon::parse($ticket->fecha)->format('d/m/Y H:i');
            $ticket->has_zip = file_exists(public_path('adjuntados/' . $ticket->id . '.zip'));
            $ticket->url_zip = asset('adjuntados/' . $ticket->id . '.zip');
            
            return response()->json($ticket);
        }
        return response()->json(['error' => 'No encontrado'], 404);
    }
    
    public function calificar(Request $request): JsonResponse { return response()->json(['success' => true]); }
    public function anular(Request $request): JsonResponse { return response()->json(['success' => true]); }

    public function tablaExternos(Request $request): View
    {
        $user = Auth::user();
        if (!$user instanceof User) { abort(401); }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) { abort(403); }

        $this->assertPermisoBandejaExterna($user, $cargoId);

        $idLogin = (int) $user->cdgo_lgin;
        $ctx = $this->cargoAreaContext($user, $cargoId);
        $tickets = $this->listarTicketsBandeja($idLogin);

        return view('mod_ti.helpdesk.partials.tabla_externos_inner', [
            'tickets' => $tickets,
            'idLogin' => $idLogin,
            'areaId' => $ctx['area_id'],
        ]);
    }

    private function assertPermisoBandejaExterna(User $user, int $cargoId): void
    {
        // Debe coincidir con de_dreccon del menú/permisos en BD
        $rutas = [
            '/mod_ti/helpdesk/index',
            '/mod_ti/helpdesk/index.blade.php',
            '/mod_ti/helpdesk/index.php',
            '/mod_ti/helpdesk/bandeja_externa',
            '/mod_ti/helpdesk/bandeja_externa.blade.php',
            '/mod_ti/helpdesk/bandeja_externa.php',
        ];
        $perfil = (int) $user->cdgo_prfle;
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        $tienePermiso = DB::table($schema . 'r_prmsos as p')
            ->join($schema . 'a_rles as r', 'p.cdgo_rle', '=', 'r.cdgo_rle')
            ->where('p.cdgo_prfle', $perfil)
            ->whereIn('r.de_dreccon', $rutas)
            ->where(function ($q) use ($cargoId) {
                $q->whereNull('p.cdgo_crgo')->orWhere('p.cdgo_crgo', $cargoId);
            })
            ->exists();

        if (!$tienePermiso) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function cargoAreaContext(User $user, int $cargoId): array
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $idLogin = (int) $user->cdgo_lgin;

        $cargo = DB::table($schema . 'r_crgos_lgin as r')
            ->join($schema . 'p_crgos as c', 'c.cdgo_crgo', '=', 'r.cdgo_crgo')
            ->leftJoin($schema . 'a_areas as a', 'a.cdgo_area', '=', 'c.cdgo_area')
            ->where('r.cdgo_lgin', $idLogin)
            ->where('c.cdgo_crgo', $cargoId)
            ->select('a.cdgo_area')
            ->first();

        return [
            'area_id' => $cargo ? (int) ($cargo->cdgo_area ?? 0) : 0,
        ];
    }

    private function listarTicketsBandeja(int $idLogin)
    {
        $ti = rtrim((string) config('auth.sigtram_ti_schema', 'ti'), '.') . '.';
        $sistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        $rows = DB::table($ti . 'tckt_slctd as ts')
            ->join($ti . 'bndja as b', 'ts.id', '=', 'b.idticket')
            ->join($ti . 'incdncia as i', 'i.id', '=', 'ts.incidencia')
            ->join($sistema . 'p_lgin as pl', 'ts.idlogin_or', '=', 'pl.cdgo_lgin')
            ->join($sistema . 'p_usrios as pu', 'pu.cdgo_usrios', '=', 'pl.cdgo_usrios')
            ->where('ts.idlogin_or', $idLogin)
            ->where('b.estado', 1)
            ->whereIn('b.situacion', [1, 2, 3, 4])
            ->select([
                'ts.id',
                'ts.asunto',
                'i.detalle',
                'ts.fecha',
                'pu.no_crto',
                'b.situacion',
            ])
            ->distinct()
            ->orderByDesc('ts.fecha')
            ->limit(50)
            ->get();

        return $rows->map(function ($row) {
            $fechaRaw = $row->fecha ?? null;
            $fechaFmt = '';
            if ($fechaRaw !== null && $fechaRaw !== '') {
                try {
                    $fechaFmt = Carbon::parse($fechaRaw)->format('d/m/Y H:i');
                } catch (\Throwable) {
                    $fechaFmt = (string) $fechaRaw;
                }
            }

            return (object) [
                'id' => $row->id,
                'asunto' => $row->asunto,
                'detalle' => $row->detalle,
                'fecha' => $fechaRaw,
                'fecha_fmt' => $fechaFmt,
                'no_crto' => $row->no_crto,
                'situacion' => (int) $row->situacion,
            ];
        });
    }

    private function listarIncidencias()
    {
        $ti = rtrim((string) config('auth.sigtram_ti_schema', 'ti'), '.') . '.';

        return DB::table($ti . 'incdncia')
            ->select('id', 'detalle')
            ->orderBy('detalle')
            ->get();
    }

    public function updateContacto(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) return response()->json(['success' => false, 'message' => 'No autorizado'], 401);
        
        $idLogin = (int) $user->cdgo_lgin;
        $telefono = $request->input('telefono_contacto', '');
        $anydesk = $request->input('anydesk_contacto', '');

        $schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $updateData = [];
        if ($telefono !== '') $updateData['nu_tele'] = $telefono;
        if ($anydesk !== '') $updateData['de_dire'] = $anydesk;

        if (empty($updateData)) {
            return response()->json(['success' => false, 'message' => 'No hay datos para actualizar.']);
        }

        try {
            DB::table($schemaSistema . 'p_usrios as u')
                ->join($schemaSistema . 'p_lgin as l', 'u.cdgo_usrios', '=', 'l.cdgo_usrios')
                ->where('l.cdgo_lgin', $idLogin)
                ->update($updateData);

            return response()->json(['success' => true, 'message' => 'Datos de contacto actualizados correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al actualizar los datos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el horario de atención configurado en la base de datos (ti.configuracion)
     * con fallback seguro a 07:30 - 20:00 (igual que el antiguo).
     */
    private function obtenerHorarioAtencion(): array
    {
        $horaInicio = '07:30:00';
        $horaFin = '20:00:00';

        try {
            $config = DB::table('ti.configuracion')
                ->whereIn('id_configuracion', [1, 2])
                ->where('activo', true)
                ->get();

            foreach ($config as $row) {
                $valorH = trim((string)$row->valor);
                if (preg_match('/^\d{1,2}:\d{2}$/', $valorH)) {
                    $valorH .= ':00';
                }
                $clave = trim((string)$row->clave);
                $idConf = (int)$row->id_configuracion;
                if ($idConf === 1 || $clave === 'horario_inicio') {
                    $horaInicio = $valorH;
                }
                if ($idConf === 2 || $clave === 'horario_fin') {
                    $horaFin = $valorH;
                }
            }
        } catch (\Throwable $e) {
            // Silently fall back to defaults
        }

        return [
            'inicio' => $horaInicio,
            'fin' => $horaFin
        ];
    }
}
