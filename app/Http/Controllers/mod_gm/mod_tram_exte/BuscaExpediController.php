<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Equivalente a busca_expedi.php — ficha popup del expediente.
 * Incluye modificar, imprimir, anexar/reabrir y lista de vouchers.
 */
class BuscaExpediController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function show(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        $anex = (string) $request->query('anex', '');

        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $data = $this->cargarFicha($idTram);
        if (! $data) {
            abort(404, 'Expediente no encontrado.');
        }

        return view('mod_gm.mod_tram_exte.busca_expedi', array_merge($data, [
            'anex' => $anex,
            'voucherUrl' => route('tramites.lista_vaucher', [
                'id_tram' => $idTram,
                'fecha' => $data['fechaIngreso'],
            ]),
            'imprUrl' => route('tramites.impr_expedi', ['cdgo_tram' => $idTram]),
            'anexoUrl' => route('tramites.agre_anexo', ['id_tram' => $idTram, 'tipo' => 1]),
            'reabrirUrl' => route('tramites.agre_anexo', ['id_tram' => $idTram, 'tipo' => 2]),
            'citaUrl' => route('tramites.gene_expedi_cita', ['cdgo_tram' => $idTram]),
            'generarUrl' => route('tramites.gene_expedi_obser', ['cdgo_tram' => $idTram]),
            'modificarUrl' => route('tramites.busca_expedi.update'),
        ]));
    }

    /**
     * Popup del ojo en Notificar Plataforma (busca_expedi_deta.php).
     */
    public function detallePopup(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $data = $this->cargarFicha($idTram);
        if (! $data) {
            abort(404, 'Expediente no encontrado.');
        }

        $cdgoTpo = (string) ($data['tramite']->cdgo_tpo_trmte ?? '');
        if ($cdgoTpo === '17') {
            $data['tituloPrefijo'] = 'Trámite Simple Nº: ';
        } elseif ($cdgoTpo === '22') {
            $data['tituloPrefijo'] = 'Correspondencia Nº: ';
        } else {
            $data['tituloPrefijo'] = 'N° de Tramite: ';
        }

        return view('mod_gm.mod_tram_exte.busca_expedi_deta', array_merge($data, [
            'imprUrl' => route('tramites.impr_expedi', ['cdgo_tram' => $idTram]),
        ]));
    }

    /**
     * POST busca_expedi — modificar observación y requisitos.
     */
    public function update(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->input('id_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro id_tram inválido.');
        }

        $s = $this->schema;
        $deObse = mb_strtoupper(trim((string) $request->input('TA1', '')), 'UTF-8');

        $exists = DB::table($s . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->exists();

        if (! $exists) {
            abort(404, 'Expediente no encontrado.');
        }

        DB::beginTransaction();
        try {
            DB::table($s . 'p_dto_trmtes')
                ->where('cdgo_dto_trmte', $idTram)
                ->update(['de_obse_trmte' => $deObse]);

            // agre_obser: concatena en historial de_obse_todo
            $obseTodo = (string) (DB::table($s . 'p_dto_trmtes')
                ->where('cdgo_dto_trmte', $idTram)
                ->value('de_obse_todo') ?? '');

            $nuevoTodo = mb_strtoupper(trim($obseTodo . ' - ' . $deObse), 'UTF-8');
            DB::table($s . 'p_dto_trmtes')
                ->where('cdgo_dto_trmte', $idTram)
                ->update(['de_obse_todo' => $nuevoTodo]);

            $reqCount = (int) $request->input('req_count', 0);
            for ($a = 1; $a <= $reqCount; $a++) {
                $nuRequ = (int) $request->input('cont' . $a, 0);
                if ($nuRequ <= 0) {
                    continue;
                }
                $elRequ = $request->has('Che' . $a) ? '1' : '0';
                DB::table($s . 'r_dtos_rqtos')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->where('cdgo_rqto_trmte', $nuRequ)
                    ->update(['in_pres' => $elRequ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            abort(500, 'No se pudo modificar el expediente: ' . $e->getMessage());
        }

        $voucherUrl = route('tramites.voucher', $idTram);

        return response(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Modificado</title>'
            . '<style>body{font-family:Segoe UI,sans-serif;text-align:center;padding:40px;color:#0f172a}</style></head><body>'
            . '<div style="font-weight:700">El expediente fue modificado</div>'
            . '<script>'
            . 'window.open(' . json_encode($voucherUrl) . ', "_blank", "scrollbars=yes,width=410,height=580");'
            . 'setTimeout(function(){ window.close(); }, 1000);'
            . '</script></body></html>'
        );
    }

    /**
     * GET impr_expedi — vista de impresión (onload print).
     */
    public function imprimir(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $data = $this->cargarFicha($idTram);
        if (! $data) {
            abort(404, 'Expediente no encontrado.');
        }

        $tramite = $data['tramite'];
        $fechaSalida = '';
        if (! empty($tramite->fe_limi_trmte)) {
            $fechaSalida = Carbon::parse($tramite->fe_limi_trmte)
                ->timezone('America/Lima')
                ->format('d/m/Y H:i');
        }

        return view('mod_gm.mod_tram_exte.impr_expedi', array_merge($data, [
            'fechaSalida' => $fechaSalida,
        ]));
    }

    /**
     * GET agre_anexo — formulario anexar (tipo=1) / reabrir (tipo=2).
     */
    public function agreAnexoForm(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('id_tram', 0);
        $tipo = (int) $request->query('tipo', 1);
        if ($idTram <= 0) {
            abort(400, 'Parámetro id_tram inválido.');
        }
        if (! in_array($tipo, [1, 2], true)) {
            abort(400, 'Parámetro tipo inválido.');
        }

        $s = $this->schema;
        $exists = DB::table($s . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->exists();
        if (! $exists) {
            abort(404, 'Expediente no encontrado.');
        }

        $ahora = Carbon::now('America/Lima');
        $fechaMostrar = $ahora->format('d/m/Y H:i:s');
        $fechaObs = $ahora->format('d/m/Y');

        if ($tipo === 1) {
            $titulo = 'ANEXAR FOLIOS';
            $mensaje = 'Anexo.- Con fecha ' . $fechaObs;
        } else {
            $titulo = 'REAPERTURA';
            $mensaje = 'Reapertura.- Con fecha ' . $fechaObs;
        }

        return view('mod_gm.mod_tram_exte.agre_anexo', [
            'idTram' => $idTram,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'fechaMostrar' => $fechaMostrar,
            'storeUrl' => route('tramites.agre_anexo.store'),
        ]);
    }

    /**
     * POST agre_anexo — crear movimiento anexo/reingreso.
     */
    public function agreAnexoStore(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->input('Cha', 0);
        $tipo = (int) $request->input('hdn_tipo', 1);
        $deObse = trim((string) $request->input('TA1', ''));
        $nuFoli = (int) $request->input('txt_nu_foli_anex', 0);

        if ($idTram <= 0) {
            abort(400, 'Parámetro id_tram inválido.');
        }
        if ($deObse === '') {
            return back()->withErrors(['TA1' => 'Debe especificar las observaciones.'])->withInput();
        }
        if ($request->input('txt_nu_foli_anex') === null || $request->input('txt_nu_foli_anex') === '') {
            return back()->withErrors(['txt_nu_foli_anex' => 'Debe especificar la cantidad de folios.'])->withInput();
        }

        $s = $this->schema;
        $user = Auth::user();
        $idUsua = (int) ($user->cdgo_lgin ?? 0);

        // mos_cargo_actu: cargos del movimiento activo (antes de desactivar)
        $cargo = DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('in_esta', '1')
            ->select('cdgo_crgo_sa', 'cdgo_crgo_en')
            ->first();

        if (! $cargo) {
            // Fallback: último movimiento del expediente
            $cargo = DB::table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $idTram)
                ->orderByDesc('cdgo_trmte_dcmnto')
                ->select('cdgo_crgo_sa', 'cdgo_crgo_en')
                ->first();
        }

        if (! $cargo) {
            abort(422, 'No se encontraron cargos activos para el expediente.');
        }

        $idEsta = $tipo === 2 ? '51' : '25'; // 51=reingreso, 25=anexo
        $deObseUpper = mb_strtoupper($deObse, 'UTF-8');

        DB::beginTransaction();
        try {
            // edit_sgmt_tram: desactivar movimientos activos
            DB::table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $idTram)
                ->update(['in_esta' => '0']);

            // alma_sgmt_tram(1): insertar nuevo movimiento activo
            $movId = DB::table($s . 'r_trmtes_dcmntos')->insertGetId([
                'cdgo_crgo_sa' => $cargo->cdgo_crgo_sa,
                'cdgo_crgo_en' => $cargo->cdgo_crgo_en,
                'cdgo_dto_trmte' => $idTram,
                'cdgo_estdo_trmte' => $idEsta,
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $deObseUpper,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => $nuFoli,
            ], 'cdgo_trmte_dcmnto');

            $adjun = '';
            if ($request->hasFile('fle_adjunto') && $request->file('fle_adjunto')->isValid()) {
                $file = $request->file('fle_adjunto');
                $extension = strtolower((string) $file->getClientOriginalExtension());
                $filename = 'adjunto_' . $movId . '_' . time() . ($extension !== '' ? '.' . $extension : '');
                $file->storeAs('adjuntos', $filename, 'public');
                $adjun = $filename;

                DB::table($s . 'r_trmtes_dcmntos')
                    ->where('cdgo_trmte_dcmnto', $movId)
                    ->update(['de_urls_imag' => $adjun]);
            }

            // agre_obser_anexo
            $obseTodo = (string) (DB::table($s . 'p_dto_trmtes')
                ->where('cdgo_dto_trmte', $idTram)
                ->value('de_obse_todo') ?? '');

            DB::table($s . 'p_dto_trmtes')
                ->where('cdgo_dto_trmte', $idTram)
                ->update([
                    'de_obse_todo' => mb_strtoupper(trim($obseTodo . ' - ' . $deObse), 'UTF-8'),
                    'de_obse_trmte' => $deObseUpper,
                ]);

            try {
                DB::table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $idUsua,
                    'de_sgto_log' => "Se anexo al expediente {$idTram}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                // log opcional
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            abort(500, 'No se pudo anexar al expediente: ' . $e->getMessage());
        }

        // Stub: email / whatsapp omitidos por ahora
        $voucherAnexUrl = route('tramites.voucher_anexo', ['id_mov' => $movId]);

        return response(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Anexado</title>'
            . '<style>body{font-family:Segoe UI,sans-serif;text-align:center;padding:40px;color:#0f172a}</style></head><body>'
            . '<div style="font-weight:700">El expediente fue anexado</div>'
            . '<script>'
            . 'try{ if(window.opener){ window.opener.location.reload(); } }catch(e){}'
            . 'window.open(' . json_encode($voucherAnexUrl) . ', "_blank", "scrollbars=yes,width=400,height=500");'
            . 'setTimeout(function(){ window.close(); }, 1000);'
            . '</script></body></html>'
        );
    }

    /**
     * GET lista_vaucher — principal + anexos, o redirect si no hay anexos.
     */
    public function listaVaucher(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('id_tram', 0);
        $fecha = (string) $request->query('fecha', '');

        if ($idTram <= 0) {
            abort(400, 'Parámetro id_tram inválido.');
        }

        $s = $this->schema;
        $anexos = DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_estdo_trmte', 25)
            ->select('cdgo_trmte_dcmnto', 'fe_rcbdo_trmte')
            ->orderBy('cdgo_trmte_dcmnto')
            ->get();

        if ($anexos->isEmpty()) {
            return redirect()->route('tramites.voucher', $idTram);
        }

        $fechaDia = $fecha !== '' ? substr($fecha, 0, 10) : '';
        $fechaHora = strlen($fecha) >= 16 ? substr($fecha, 11, 5) : '';

        $anexosFmt = $anexos->map(function ($row) {
            $fe = $row->fe_rcbdo_trmte
                ? Carbon::parse($row->fe_rcbdo_trmte)->timezone('America/Lima')
                : null;

            return (object) [
                'id' => (int) $row->cdgo_trmte_dcmnto,
                'fecha' => $fe ? $fe->format('d/m/Y') : '',
                'hora' => $fe ? $fe->format('H:i') : '',
                'url' => route('tramites.voucher_anexo', ['id_mov' => $row->cdgo_trmte_dcmnto]),
            ];
        });

        return view('mod_gm.mod_tram_exte.lista_vaucher', [
            'idTram' => $idTram,
            'fechaDia' => $fechaDia,
            'fechaHora' => $fechaHora,
            'anexos' => $anexosFmt,
            'voucherPrincipalUrl' => route('tramites.voucher', $idTram),
        ]);
    }

    /**
     * GET voucher de un movimiento anexo (busca_vauche_anex).
     */
    public function voucherAnexo(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idMov = (int) $request->query('id_mov', $request->query('id_tram', 0));
        if ($idMov <= 0) {
            abort(400, 'Parámetro id_mov inválido.');
        }

        $s = $this->schema;
        $cmpnia = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));

        $row = DB::table($s . 'r_trmtes_dcmntos as r')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->leftJoin($s . 'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($s . 'a_docmntos_trmtes as d', 'p.cdgo_docmnto_trmte', '=', 'd.cdgo_docmnto_trmte')
            ->select(
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'r.nu_foli_anex',
                'r.de_obse',
                'r.de_urls_imag',
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.cdgo_docmnto_trmte',
                'p.de_obse_trmte',
                'u.nu_docu',
                'u.no_crto',
                'u.de_dire',
                'u.nu_tele',
                'a.de_area',
                'd.de_docmnto_trmte'
            )
            ->where('r.cdgo_trmte_dcmnto', $idMov)
            ->when($cmpnia > 0, fn ($q) => $q->where('p.cdgo_cmpnia', $cmpnia))
            ->first();

        if (! $row) {
            abort(404, 'Anexo no encontrado.');
        }

        $fecha = '';
        $hora = '';
        if ($row->fe_rcbdo_trmte) {
            $fe = Carbon::parse($row->fe_rcbdo_trmte)->timezone('America/Lima');
            $fecha = $fe->format('d/m/Y');
            $hora = $fe->format('H:i');
        }

        $nomComp = (string) (session('usNomComp') ?: 'Municipalidad');

        return view('mod_gm.mod_tram_exte.voucher_anexo', [
            'row' => $row,
            'numeroExpediente' => $this->formatNumeroExpediente((string) ($row->nu_expe_todo ?? '')),
            'fecha' => $fecha,
            'hora' => $hora,
            'nomComp' => $nomComp,
            'voucherPrincipalUrl' => route('tramites.voucher', $row->cdgo_dto_trmte),
        ]);
    }

    /**
     * GET gene_expedi_cita — formulario de cita virtual.
     */
    public function citaVirtualForm(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $data = $this->cargarFicha($idTram);
        if (! $data) {
            abort(404, 'Expediente no encontrado.');
        }

        return view('mod_gm.mod_tram_exte.gene_expedi_cita', array_merge($data, [
            'storeUrl' => route('tramites.gene_expedi_cita.store'),
        ]));
    }

    /**
     * POST gene_expedi_cita — crea movimiento con enlace Meet (estado 1).
     */
    public function citaVirtualStore(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->input('id_tram', 0);
        $fecha = trim((string) $request->input('txt_fecha', ''));
        $hora = trim((string) $request->input('txt_hora', ''));
        $deObse = trim((string) $request->input('TA1', ''));

        if ($idTram <= 0 || $fecha === '' || $hora === '') {
            return back()->withErrors(['txt_fecha' => 'Debe indicar fecha y hora de la cita.'])->withInput();
        }

        $s = $this->schema;
        $user = Auth::user();
        $idUsua = (int) ($user->cdgo_lgin ?? 0);

        $cargo = DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('in_esta', '1')
            ->select('cdgo_crgo_sa', 'cdgo_crgo_en')
            ->first();

        if (! $cargo) {
            $cargo = DB::table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $idTram)
                ->orderByDesc('cdgo_trmte_dcmnto')
                ->select('cdgo_crgo_sa', 'cdgo_crgo_en')
                ->first();
        }

        if (! $cargo) {
            abort(422, 'No se encontraron cargos para el expediente.');
        }

        // En el antiguo se invierten sa/en al crear la cita
        $crgoSa = $cargo->cdgo_crgo_en;
        $crgoEn = $cargo->cdgo_crgo_sa;

        try {
            $feCita = Carbon::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora, 'America/Lima');
        } catch (\Throwable $e) {
            try {
                $feCita = Carbon::parse($fecha . ' ' . $hora, 'America/Lima');
            } catch (\Throwable $e2) {
                return back()->withErrors(['txt_fecha' => 'Fecha u hora inválida.'])->withInput();
            }
        }

        $enlace = 'https://meet.jit.si/CitasVirtualesMDB' . $idTram;
        $fechaPeru = $feCita->format('d/m/Y');
        $obs = mb_strtoupper(
            $deObse . ' - SE CREA CITA VIRTUAL PARA EL DIA ' . $fechaPeru
            . ' A LAS ' . $hora
            . ' EL SIGUIENTE ENLACE <a href="' . $enlace . '" target="_blank">Entrar a reunión</a>',
            'UTF-8'
        );

        DB::beginTransaction();
        try {
            DB::table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $idTram)
                ->update(['in_esta' => '0']);

            DB::table($s . 'r_trmtes_dcmntos')->insert([
                'cdgo_crgo_sa' => $crgoSa,
                'cdgo_crgo_en' => $crgoEn,
                'cdgo_dto_trmte' => $idTram,
                'cdgo_estdo_trmte' => '1',
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $obs,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => null,
                'fe_cta_vrtal' => $feCita->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            abort(500, 'No se pudo generar la cita: ' . $e->getMessage());
        }

        return response(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Cita</title></head><body>'
            . '<script>try{window.opener&&window.opener.location.reload()}catch(e){} window.close();</script>'
            . '</body></html>'
        );
    }

    /**
     * GET gene_expedi_obser — formulario para generar expediente desde virtual (40/55).
     */
    public function generarExpedienteForm(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $data = $this->cargarFicha($idTram);
        if (! $data) {
            abort(404, 'Expediente no encontrado.');
        }

        $s = $this->schema;
        $idSubarea = (int) ($data['tramite']->cdgo_area ?? 0);
        $idArea = (int) (DB::table($s . 'a_areas')->where('cdgo_area', $idSubarea)->value('cdgo_area_prim') ?? 0);
        if ($idArea <= 0) {
            $idArea = $idSubarea;
        }

        // Igual que tipo_oficina del antiguo: áreas activas (excepto 0)
        $todasAreas = DB::table($s . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_area', '<>', 0)
            ->orderBy('de_area')
            ->get(['cdgo_area', 'de_area', 'dc_area', 'cdgo_area_prim']);

        $gerencias = $todasAreas
            ->filter(fn ($a) => (int) ($a->cdgo_area_prim ?? 0) === 0)
            ->values();

        if ($gerencias->isEmpty()) {
            $gerencias = $todasAreas;
        }

        $subgerencias = $todasAreas
            ->filter(fn ($a) => (int) ($a->cdgo_area_prim ?? 0) === $idArea)
            ->values();

        return view('mod_gm.mod_tram_exte.gene_expedi_obser', array_merge($data, [
            'idArea' => $idArea,
            'idSubarea' => $idSubarea,
            'gerencias' => $gerencias,
            'subgerencias' => $subgerencias,
            'todasAreas' => $todasAreas,
            'storeUrl' => route('tramites.gene_expedi_obser.store'),
        ]));
    }

    /**
     * POST gene_expedi_obser — crea expediente real y finaliza el virtual.
     */
    public function generarExpedienteStore(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $idTramVirtual = (int) $request->input('id_tram', 0);
        $tpoTrmtes = (int) $request->input('rdn_cdgo_tpo_trmtes', 22);
        $folios = (int) $request->input('T6', 0);
        $deObse = trim((string) $request->input('TA1', ''));
        $idUsuaSol = (int) $request->input('hdn_cdgo_usrios', 0);
        $se2 = (int) $request->input('Se2', 0);
        $se6 = (int) $request->input('Se6', 0);

        if ($idTramVirtual <= 0 || $idUsuaSol <= 0) {
            abort(400, 'Datos del expediente virtual inválidos.');
        }
        if (! in_array($tpoTrmtes, [17, 22], true)) {
            $tpoTrmtes = 22;
        }
        if ($se2 <= 0) {
            return back()->withErrors(['Se2' => 'Debe seleccionar la gerencia de destino.'])->withInput();
        }

        $s = $this->schema;
        $user = Auth::user();
        $idUsua = (int) ($user->cdgo_lgin ?? 0);
        $cmpnia = (int) (session('usComp') ?: ($user->cdgo_cmpnia ?? 0));

        $virtual = DB::table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $idTramVirtual)->first();
        if (! $virtual) {
            abort(404, 'Expediente virtual no encontrado.');
        }

        $destAreaId = ($se6 > 0) ? $se6 : $se2;
        $destCargoId = DB::table($s . 'p_crgos')
            ->where('cdgo_area', $destAreaId)
            ->where('nu_orde', '1')
            ->where('in_esta', '1')
            ->orderBy('cdgo_crgo')
            ->value('cdgo_crgo');

        if (! $destCargoId) {
            return back()->withErrors(['Se2' => 'No hay cargo activo (orden 1) en el área destino.'])->withInput();
        }

        $movVirtual = DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTramVirtual)
            ->where('in_esta', '1')
            ->first();

        $urlsImag = $movVirtual->de_urls_imag ?? '';

        $letter = (string) (DB::table($s . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $tpoTrmtes)
            ->value('dc_tpo_trmte') ?? ($tpoTrmtes === 17 ? 'S' : 'E'));

        $currYear = (int) date('Y');
        $anioRow = DB::table($s . 'a_anios')
            ->where('cdgo_cmpnia', $cmpnia)
            ->orderByDesc('de_anio')
            ->first();

        DB::beginTransaction();
        try {
            if (! $anioRow || (int) $anioRow->de_anio < $currYear) {
                $idAnio = DB::table($s . 'a_anios')->insertGetId([
                    'de_anio' => $currYear,
                    'dc_anio' => substr((string) $currYear, 2, 2),
                    'cdgo_cmpnia' => $cmpnia,
                ], 'cdgo_anio');
                $deAnio = $currYear;
                $nuExpe = 1;
            } else {
                $idAnio = (int) $anioRow->cdgo_anio;
                $deAnio = (int) $anioRow->de_anio;
                $maxExp = (int) (DB::table($s . 'p_dto_trmtes')
                    ->where('cdgo_anio', $idAnio)
                    ->where('cdgo_tpo_trmte', $tpoTrmtes)
                    ->where('cdgo_cmpnia', $cmpnia)
                    ->max('nu_expe_trmte') ?? 0);
                $nuExpe = $maxExp + 1;
            }

            $nuExpeTodo = $letter . '-' . $nuExpe . '-' . $deAnio;
            $tiSile = ! empty($virtual->cdgo_docmnto_trmte) ? '1' : '0';
            $obsNueva = mb_strtoupper(
                trim($deObse . ' - GENERADO DESDE EL TRÁMITE VIRTUAL ' . ($virtual->nu_expe_todo ?? '')),
                'UTF-8'
            );

            $cdgoCorreo = $virtual->cdgo_usrios_crreo ?? null;
            if (empty($cdgoCorreo)) {
                $cdgoCorreo = DB::table($s . 'h_usrios_crreo')
                    ->where('cdgo_usrios', $idUsuaSol)
                    ->value('cdgo_usrios_crreo');
            }

            $idNuevo = DB::table($s . 'p_dto_trmtes')->insertGetId([
                'cdgo_usrios' => $idUsuaSol,
                'cdgo_docmnto_trmte' => $virtual->cdgo_docmnto_trmte ?: null,
                'cdgo_tpo_trmte' => $tpoTrmtes,
                'cdgo_anio' => $idAnio,
                'cdgo_area' => $destAreaId,
                'cdgo_mdlo' => 1,
                'cdgo_cmpnia' => $cmpnia,
                'cdgo_usrios_crreo' => $cdgoCorreo ?: null,
                'nu_expe_todo' => $nuExpeTodo,
                'nu_expe_trmte' => $nuExpe,
                'nu_docu_trmte' => '',
                'in_docu_trmte' => $letter,
                'nu_foli_trmte' => $folios,
                'fe_ingr_trmte' => now(),
                'fe_limi_trmte' => null,
                'de_obse_trmte' => $obsNueva,
                'in_sile_admi' => $tiSile,
                'in_encu_tram' => '0',
                'de_obse_todo' => $obsNueva,
                'co_usua_modi' => $idUsua,
                'co_usua_ingr' => $idUsua,
                'ts_usua_modi' => now(),
            ], 'cdgo_dto_trmte');

            DB::table($s . 'r_trmtes_dcmntos')->insert([
                'cdgo_crgo_sa' => 0,
                'cdgo_crgo_en' => $destCargoId,
                'cdgo_dto_trmte' => $idNuevo,
                'cdgo_estdo_trmte' => '1',
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $obsNueva,
                'in_esta' => '1',
                'de_urls_imag' => $urlsImag ?: '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => null,
            ]);

            // Copiar requisitos del virtual
            $reqs = DB::table($s . 'r_dtos_rqtos')
                ->where('cdgo_dto_trmte', $idTramVirtual)
                ->get(['cdgo_rqto_trmte', 'in_pres']);

            foreach ($reqs as $req) {
                DB::table($s . 'r_dtos_rqtos')->insert([
                    'cdgo_dto_trmte' => $idNuevo,
                    'cdgo_rqto_trmte' => $req->cdgo_rqto_trmte,
                    'in_pres' => '1',
                ]);
            }

            // Finalizar trámite virtual (estado 3) — área atención al ciudadano (18)
            $cargoFin = DB::table($s . 'p_crgos')
                ->where('cdgo_area', 18)
                ->where('nu_orde', '1')
                ->where('in_esta', '1')
                ->orderBy('cdgo_crgo')
                ->value('cdgo_crgo');

            if (! $cargoFin) {
                $cargoFin = $destCargoId;
            }

            $deTpo = (string) (DB::table($s . 'a_tpo_trmtes')
                ->where('cdgo_tpo_trmte', $tpoTrmtes)
                ->value('de_tpo_trmte') ?? '');

            $obsFin = mb_strtoupper(
                'SE FINALIZA TRÁMITE CON LA GENERACION DEL TRAMITE ' . $deTpo . ' N° ' . $nuExpeTodo,
                'UTF-8'
            );

            DB::table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $idTramVirtual)
                ->update(['in_esta' => '0']);

            DB::table($s . 'r_trmtes_dcmntos')->insert([
                'cdgo_crgo_sa' => $cargoFin,
                'cdgo_crgo_en' => $cargoFin,
                'cdgo_dto_trmte' => $idTramVirtual,
                'cdgo_estdo_trmte' => '3',
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $obsFin,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => null,
            ]);

            try {
                DB::table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $idUsua,
                    'de_sgto_log' => "Ingreso el expediente Nro{$idNuevo} desde virtual {$idTramVirtual}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                // log opcional
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            abort(500, 'No se pudo generar el expediente: ' . $e->getMessage());
        }

        $voucherUrl = route('tramites.voucher', $idNuevo);

        return response(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Generado</title></head><body>'
            . '<script>'
            . 'window.open(' . json_encode($voucherUrl) . ', "_blank", "scrollbars=yes,width=400,height=550");'
            . 'try{window.opener&&window.opener.location.reload()}catch(e){}'
            . 'window.close();'
            . '</script></body></html>'
        );
    }

    /**
     * Carga datos compartidos de la ficha (show / imprimir).
     */
    private function cargarFicha(int $idTram): ?array
    {
        $cmpnia = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));
        $s = $this->schema;

        $tramite = DB::table($s . 'p_dto_trmtes as p_dto_trmtes')
            ->join($s . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($s . 'a_areas as a_areas', 'p_dto_trmtes.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($s . 'a_anios as a_anios', 'p_dto_trmtes.cdgo_anio', '=', 'a_anios.cdgo_anio')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p_dto_trmtes.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->leftJoin($s . 'h_usrios_crreo as h_usrios_crreo', 'p_dto_trmtes.cdgo_usrios_crreo', '=', 'h_usrios_crreo.cdgo_usrios_crreo')
            ->select(
                'p_dto_trmtes.cdgo_dto_trmte',
                'a_tpo_trmtes.cdgo_tpo_trmte',
                'a_tpo_trmtes.de_tpo_trmte',
                'p_dto_trmtes.nu_docu_trmte',
                'p_dto_trmtes.cdgo_usrios',
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.nu_foli_trmte',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_dto_trmtes.fe_limi_trmte',
                'p_dto_trmtes.cdgo_docmnto_trmte',
                'p_dto_trmtes.in_sile_admi',
                'p_dto_trmtes.de_obse_trmte',
                'p_dto_trmtes.cdgo_area',
                'a_docmntos_trmtes.nu_tiem_sile',
                'p_usrios.de_mail',
                'p_usrios.in_tipo_pers',
                'h_usrios_crreo.de_mail as de_usrios_mail'
            )
            ->where('p_dto_trmtes.cdgo_dto_trmte', $idTram)
            ->when($cmpnia > 0, fn ($q) => $q->where('p_dto_trmtes.cdgo_cmpnia', $cmpnia))
            ->first();

        if (! $tramite) {
            return null;
        }

        $persona = $this->cargarPersona((int) $tramite->cdgo_usrios, (string) ($tramite->in_tipo_pers ?? ''));

        $servicio = null;
        $tipoTramiteServ = null;
        if (! empty($tramite->cdgo_docmnto_trmte)) {
            $servRow = DB::table($s . 'a_docmntos_trmtes as a_docmntos_trmtes')
                ->join($s . 'a_srvcio_areas as a_srvcio_areas', 'a_docmntos_trmtes.cdgo_srvcio_area', '=', 'a_srvcio_areas.cdgo_srvcio_area')
                ->select('a_srvcio_areas.de_srvcio', 'a_docmntos_trmtes.de_docmnto_trmte')
                ->where('a_docmntos_trmtes.cdgo_docmnto_trmte', (int) $tramite->cdgo_docmnto_trmte)
                ->first();

            if ($servRow) {
                $servicio = $servRow->de_srvcio;
                $tipoTramiteServ = $servRow->de_docmnto_trmte;
            }
        }

        $requisitos = DB::table($s . 'r_dtos_rqtos as r_dtos_rqtos')
            ->join($s . 'h_rqtos_trmtes as h_rqtos_trmtes', 'r_dtos_rqtos.cdgo_rqto_trmte', '=', 'h_rqtos_trmtes.cdgo_rqto_trmte')
            ->select(
                'r_dtos_rqtos.in_pres',
                'h_rqtos_trmtes.dc_rqto_trmte',
                'r_dtos_rqtos.cdgo_rqto_trmte',
                'h_rqtos_trmtes.de_rqto_trmte'
            )
            ->where('r_dtos_rqtos.cdgo_dto_trmte', $idTram)
            ->get();

        $cdgoEstado = DB::table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('in_esta', '1')
            ->value('cdgo_estdo_trmte');

        $cdgoTpo = (string) ($tramite->cdgo_tpo_trmte ?? '');
        if ($cdgoTpo === '17') {
            $tituloPrefijo = 'Trámite Simple Nº: ';
        } elseif ($cdgoTpo === '22') {
            $tituloPrefijo = 'Expediente Nº: ';
        } else {
            $tituloPrefijo = 'N° de Tramite: ';
        }

        $numeroExpediente = $this->formatNumeroExpediente((string) ($tramite->nu_expe_todo ?? ''));

        $fechaIngreso = '';
        if ($tramite->fe_ingr_trmte) {
            $fechaIngreso = Carbon::parse($tramite->fe_ingr_trmte)
                ->timezone('America/Lima')
                ->format('d/m/Y H:i');
        }

        $correo = $tramite->de_usrios_mail
            ? strtolower((string) $tramite->de_usrios_mail)
            : (string) ($tramite->de_mail ?? '');

        return [
            'tramite' => $tramite,
            'persona' => $persona,
            'servicio' => $servicio,
            'tipoTramiteServ' => $tipoTramiteServ,
            'requisitos' => $requisitos,
            'cdgoEstado' => (string) ($cdgoEstado ?? ''),
            'tituloPrefijo' => $tituloPrefijo,
            'numeroExpediente' => $numeroExpediente,
            'fechaIngreso' => $fechaIngreso,
            'correo' => $correo,
        ];
    }

    /**
     * Persona natural (0) o jurídica (1), equivalente a gene_bus_usua.
     */
    private function cargarPersona(int $idUsua, string $tipoPers): ?object
    {
        $s = $this->schema;

        if ($tipoPers === '') {
            $tipoPers = (string) DB::table($s . 'p_usrios')
                ->where('cdgo_usrios', $idUsua)
                ->value('in_tipo_pers');
        }

        if ($tipoPers === '0') {
            $row = DB::table($s . 'h_usrios_ntral as h')
                ->join($s . 'a_dcmntos_iden as d', 'h.cdgo_dcmntos_iden', '=', 'd.cdgo_dcmntos_iden')
                ->join($s . 'p_usrios as u', 'h.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select(
                    'd.dc_dcmntos_iden as tipo_doc',
                    'u.nu_docu',
                    'h.no_usrio as nombres',
                    'h.ap_pate',
                    'h.ap_mate',
                    'u.de_dire',
                    'u.nu_tele'
                )
                ->where('u.cdgo_usrios', $idUsua)
                ->first();

            return (object) [
                'tipo' => '0',
                'data' => $row,
            ];
        }

        if ($tipoPers === '1') {
            $row = DB::table($s . 'h_usrios_jrid as h')
                ->join($s . 'p_usrios as u', 'h.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select(
                    'u.nu_docu as ruc',
                    'h.rz_soci',
                    'u.de_dire',
                    'u.nu_tele'
                )
                ->where('u.cdgo_usrios', $idUsua)
                ->first();

            return (object) [
                'tipo' => '1',
                'data' => $row,
            ];
        }

        return null;
    }

    /**
     * Equivalente a gene_numero_expediente: PREFIX-XXXXX-YYYY
     */
    private function formatNumeroExpediente(string $expe): string
    {
        $parts = explode('-', $expe);
        if (count($parts) < 3) {
            return $expe;
        }

        return $parts[0] . '-' . str_pad($parts[1], 5, '0', STR_PAD_LEFT) . '-' . $parts[2];
    }
}
