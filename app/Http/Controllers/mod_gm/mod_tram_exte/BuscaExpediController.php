<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use App\Http\Requests\mod_gm\mod_tram_exte\ActualizarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\AgregarAnexoExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\GenerarExpedienteObservadoRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RegistrarCitaVirtualRequest;
use App\Services\mod_gm\mod_tram_exte\BuscaExpedienteService;
use App\Support\SessionKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Equivalente a busca_expedi.php — ficha popup del expediente.
 * Incluye modificar, imprimir, anexar/reabrir y lista de vouchers.
 * Desacoplado bajo arquitectura Repository + Service (SPEC 16-02 / SPEC 17-00).
 */
class BuscaExpediController extends Controller
{
    public function __construct(
        private readonly BuscaExpedienteService $service
    ) {}

    public function show(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        $anex = (string) $request->query('anex', '');

        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $data = $this->service->obtenerFicha($idTram, $cmpnia);
        if (!$data) {
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
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $data = $this->service->obtenerFicha($idTram, $cmpnia);
        if (!$data) {
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
    public function update(ActualizarExpedienteRequest $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->validated('id_tram');
        $deObse = (string) ($request->validated('TA1') ?? '');
        $reqCount = (int) ($request->validated('req_count') ?? 0);

        $requisitos = [];
        for ($a = 1; $a <= $reqCount; $a++) {
            $nuRequ = (int) $request->input('cont' . $a, 0);
            if ($nuRequ <= 0) {
                continue;
            }
            $requisitos[$nuRequ] = $request->has('Che' . $a) ? '1' : '0';
        }

        $cmpnia = $this->resolverCompaniaActiva();

        try {
            $this->service->actualizarExpediente($idTram, $cmpnia, $deObse, $requisitos);
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'Expediente no encontrado.') {
                abort(404, 'Expediente no encontrado.');
            }
            $errorId = (string) Str::uuid();
            Log::error("Error modificando expediente {$idTram} [error_id: {$errorId}]: " . $e->getMessage());
            abort(500, 'No se pudo modificar el expediente. Referencia de error: ' . $errorId);
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
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $data = $this->service->obtenerFicha($idTram, $cmpnia);
        if (!$data) {
            abort(404, 'Expediente no encontrado.');
        }

        $tramite = $data['tramite'];
        $fechaSalida = '';
        if (!empty($tramite->fe_limi_trmte)) {
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
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('id_tram', 0);
        $tipo = (int) $request->query('tipo', 1);
        if ($idTram <= 0) {
            abort(400, 'Parámetro id_tram inválido.');
        }
        if (!in_array($tipo, [1, 2], true)) {
            abort(400, 'Parámetro tipo inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();

        try {
            $data = $this->service->obtenerDatosAnexo($idTram, $tipo, $cmpnia);
        } catch (\Throwable) {
            abort(404, 'Expediente no encontrado.');
        }

        return view('mod_gm.mod_tram_exte.agre_anexo', array_merge($data, [
            'storeUrl' => route('tramites.agre_anexo.store'),
        ]));
    }

    /**
     * POST agre_anexo — crear movimiento anexo/reingreso.
     */
    public function agreAnexoStore(AgregarAnexoExpedienteRequest $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->validated('Cha');
        $tipo = (int) $request->validated('hdn_tipo');
        $deObse = (string) $request->validated('TA1');
        $nuFoli = (int) $request->validated('txt_nu_foli_anex');
        $archivo = $request->file('fle_adjunto');

        $user = Auth::user();
        $idUsua = (int) ($user->cdgo_lgin ?? 0);
        $cmpnia = $this->resolverCompaniaActiva();

        try {
            $movId = $this->service->agregarAnexo($idTram, $cmpnia, $tipo, $deObse, $nuFoli, $idUsua, $archivo, (string) $request->ip());
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'No se encontraron cargos activos para el expediente.') {
                abort(422, $e->getMessage());
            }
            if ($e->getMessage() === 'Expediente no encontrado.') {
                abort(404, $e->getMessage());
            }
            $errorId = (string) Str::uuid();
            Log::error("Error al anexar a expediente {$idTram} [error_id: {$errorId}]: " . $e->getMessage());
            abort(500, 'No se pudo anexar al expediente. Referencia de error: ' . $errorId);
        }

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
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('id_tram', 0);
        $fecha = (string) $request->query('fecha', '');

        if ($idTram <= 0) {
            abort(400, 'Parámetro id_tram inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();

        try {
            $res = $this->service->listarVouchers($idTram, $fecha, $cmpnia);
        } catch (\Throwable) {
            abort(404, 'Expediente no encontrado.');
        }

        if (!$res['tieneAnexos']) {
            return redirect()->route('tramites.voucher', $idTram);
        }

        return view('mod_gm.mod_tram_exte.lista_vaucher', [
            'idTram' => $idTram,
            'fechaDia' => $res['fechaDia'],
            'fechaHora' => $res['fechaHora'],
            'anexos' => $res['anexos'],
            'voucherPrincipalUrl' => route('tramites.voucher', $idTram),
        ]);
    }

    /**
     * GET voucher de un movimiento anexo (busca_vauche_anex).
     */
    public function voucherAnexo(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idMov = (int) $request->query('id_mov', $request->query('id_tram', 0));
        if ($idMov <= 0) {
            abort(400, 'Parámetro id_mov inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $data = $this->service->obtenerVoucherAnexo($idMov, $cmpnia);

        if (!$data) {
            abort(404, 'Anexo no encontrado.');
        }

        $nomComp = (string) (session(\App\Support\SessionKeys::COMPANIA_NOMBRE) ?: 'Municipalidad');

        return view('mod_gm.mod_tram_exte.voucher_anexo', array_merge($data, [
            'nomComp' => $nomComp,
            'voucherPrincipalUrl' => route('tramites.voucher', $data['row']->cdgo_dto_trmte),
        ]));
    }

    /**
     * GET gene_expedi_cita — formulario de cita virtual.
     */
    public function citaVirtualForm(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $data = $this->service->obtenerFicha($idTram, $cmpnia);
        if (!$data) {
            abort(404, 'Expediente no encontrado.');
        }

        return view('mod_gm.mod_tram_exte.gene_expedi_cita', array_merge($data, [
            'storeUrl' => route('tramites.gene_expedi_cita.store'),
        ]));
    }

    /**
     * POST gene_expedi_cita — crea movimiento con enlace Meet (estado 1).
     */
    public function citaVirtualStore(RegistrarCitaVirtualRequest $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->validated('id_tram');
        $fecha = (string) $request->validated('txt_fecha');
        $hora = (string) $request->validated('txt_hora');
        $deObse = (string) ($request->validated('TA1') ?? '');
        $idUsua = (int) (Auth::user()->cdgo_lgin ?? 0);
        $cmpnia = $this->resolverCompaniaActiva();

        try {
            $this->service->registrarCitaVirtual($idTram, $cmpnia, $fecha, $hora, $deObse, $idUsua);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['txt_fecha' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'No se encontraron cargos para el expediente.') {
                abort(422, $e->getMessage());
            }
            $errorId = (string) Str::uuid();
            Log::error("Error registrando cita virtual en expediente {$idTram} [error_id: {$errorId}]: " . $e->getMessage());
            abort(500, 'No se pudo generar la cita. Referencia de error: ' . $errorId);
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
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTram = (int) $request->query('cdgo_tram', 0);
        if ($idTram <= 0) {
            abort(400, 'Parámetro cdgo_tram inválido.');
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $data = $this->service->obtenerDatosGeneracionExpediente($idTram, $cmpnia);
        if (!$data) {
            abort(404, 'Expediente no encontrado.');
        }

        return view('mod_gm.mod_tram_exte.gene_expedi_obser', array_merge($data, [
            'storeUrl' => route('tramites.gene_expedi_obser.store'),
        ]));
    }

    /**
     * POST gene_expedi_obser — crea expediente real y finaliza el virtual.
     */
    public function generarExpedienteStore(GenerarExpedienteObservadoRequest $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $idTramVirtual = (int) $request->validated('id_tram');
        $rawTipo = (string) $request->validated('tipo_tramite');
        $codigoTipo = strtoupper(trim($rawTipo));
        if (!in_array($codigoTipo, ['E', 'S'], true)) {
            abort(422, 'Tipo de trámite inválido. Solo se permite E o S.');
        }

        try {
            $tipoObj = $this->service->resolverTipoTramiteActivo($codigoTipo);
            $tpoTrmtes = (int) $tipoObj->cdgo_tpo_trmte;
        } catch (\Throwable $e) {
            abort(422, 'Configuración de catálogo incompleta: ' . $e->getMessage());
        }

        $folios = (int) ($request->validated('T6') ?? 0);
        $deObse = (string) ($request->validated('TA1') ?? '');
        $idUsuaSol = (int) $request->validated('hdn_cdgo_usrios');
        $se2 = (int) $request->validated('Se2');
        $se6 = (int) ($request->validated('Se6') ?? 0);
        $destAreaId = ($se6 > 0) ? $se6 : $se2;

        $user = Auth::user();
        $idUsua = (int) ($user->cdgo_lgin ?? 0);
        $cmpnia = $this->resolverCompaniaActiva();

        try {
            $idNuevo = $this->service->generarExpedienteDesdeObservado(
                $idTramVirtual,
                $tpoTrmtes,
                $folios,
                $deObse,
                $idUsuaSol,
                $destAreaId,
                $idUsua,
                $cmpnia,
                (string) $request->ip()
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'No hay cargo activo')) {
                return back()->withErrors(['Se2' => $e->getMessage()])->withInput();
            }
            if ($e->getMessage() === 'Expediente virtual no encontrado.') {
                abort(404, $e->getMessage());
            }
            $errorId = (string) Str::uuid();
            Log::error("Error generando expediente desde virtual {$idTramVirtual} [error_id: {$errorId}]: " . $e->getMessage());
            abort(500, 'No se pudo generar el expediente. Referencia de error: ' . $errorId);
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
     * Resuelve de forma estricta la compañía activa del usuario o sesion.
     */
    private function resolverCompaniaActiva(): int
    {
        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        if ($cmpnia <= 0) {
            abort(403, 'No tiene compañía activa asignada.');
        }

        return $cmpnia;
    }
}
