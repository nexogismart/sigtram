<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArchivoExpedienteArchivarRequest;
use App\Http\Requests\ArchivoExpedienteDetalleRequest;
use App\Http\Requests\ArchivoExpedienteNotificarRequest;
use App\Http\Requests\ArchivoExpedientePrestarRequest;
use App\Http\Requests\ArchivoExpedienteTablaRequest;
use App\Mail\ExpedienteNotificadoMail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Support\SessionKeys;

class ArchivoExpedienteController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        return view($this->vistaDesdeMenu($pathVista, $opcionMenu), [
            'desde' => '',
            'hasta' => '',
            'searchFields' => $this->searchFields(),
        ]);
    }

    public function tabla(ArchivoExpedienteTablaRequest $request): JsonResponse
    {
        $this->authorizeArchiveAccess();

        $data = $request->validated();
        $perPage = (int) ($data['per_page'] ?? 10);
        $page = (int) ($data['page'] ?? 1);
        $cargoId = $this->currentCargoId();
        $companiaId = (int) session(SessionKeys::USER_COMPANY, 0);

        $res = $this->tramiteRepo->obtenerTablaArchivos($data, $page, $perPage, $cargoId, $companiaId);
        $counts = $this->tramiteRepo->obtenerConteosArchivos($cargoId, $companiaId);

        return response()->json([
            'status' => 'success',
            'data' => collect($res['items'])->map(fn ($row) => $this->formatRow((object) $row))->values(),
            'pagination' => [
                'current_page' => $res['current_page'],
                'last_page' => $res['last_page'],
                'per_page' => $res['per_page'],
                'total' => $res['total'],
            ],
            'counts' => $counts,
        ]);
    }

    public function archivar(ArchivoExpedienteArchivarRequest $request): JsonResponse
    {
        $this->authorizeArchiveAccess();

        $data = $request->validated();
        $cargoId = $this->currentCargoId();
        if ($cargoId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Seleccione un cargo activo.'], 422);
        }

        $user = Auth::user();
        $fecha = Carbon::now('America/Lima')->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($data['observaciones'] ?: 'SE ARCHIVA SIN NOVEDAD'), 'UTF-8');
        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $companiaId = (int) session(SessionKeys::USER_COMPANY, 0);

        $archivados = $this->tramiteRepo->archivarExpedientes(
            $ids->all(),
            $cargoId,
            (int) ($user->cdgo_lgin ?? 0),
            $fecha,
            $observaciones,
            $request->ip() ?: '',
            $companiaId
        );

        return response()->json([
            'status' => 'success',
            'message' => "{$archivados} expediente(s) archivado(s).",
            'archivados' => $archivados,
            'counts' => $this->tramiteRepo->obtenerConteosArchivos($cargoId, $companiaId),
        ]);
    }

    public function notificar(ArchivoExpedienteNotificarRequest $request): JsonResponse
    {
        $this->authorizeArchiveAccess();

        $data = $request->validated();
        $cargoId = $this->currentCargoId();
        $user = Auth::user();
        $fecha = Carbon::now('America/Lima')->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($data['observaciones'] ?: 'SE LE ENTREGA EL EXPEDIENTE AL ADMINISTRADO'), 'UTF-8');
        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $companiaId = (int) session(SessionKeys::USER_COMPANY, 0);

        $adjunto = '';
        if ($request->hasFile('documento')) {
            $file = $request->file('documento');
            $filename = 'NOTIF_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $adjunto = 'notificaciones/' . now('America/Lima')->format('Y/m') . '/' . $filename;
            $file->storeAs('adjuntos/notificaciones/' . now('America/Lima')->format('Y/m'), $filename, 'public');
        }

        $validIds = $this->tramiteRepo->notificarExpedientesArchivo(
            $ids->all(),
            $cargoId,
            (int) ($user->cdgo_lgin ?? 0),
            $fecha,
            $observaciones,
            $adjunto,
            $request->ip() ?: '',
            $companiaId
        );

        foreach ($validIds as $id) {
            $this->sendNotificationEmail((int) $id, $observaciones, $adjunto);
        }

        $notificados = count($validIds);

        return response()->json([
            'status' => 'success',
            'message' => "{$notificados} expediente(s) notificado(s).",
            'notificados' => $notificados,
            'counts' => $this->tramiteRepo->obtenerConteosArchivos($cargoId, $companiaId),
        ]);
    }

    public function prestar(ArchivoExpedientePrestarRequest $request): JsonResponse
    {
        $this->authorizeArchiveAccess();

        $data = $request->validated();
        $cargoId = $this->currentCargoId();
        $user = Auth::user();
        $fecha = Carbon::now('America/Lima')->format('Y-m-d H:i:s');
        $observaciones = mb_strtoupper(trim($data['observaciones']), 'UTF-8');
        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $companiaId = (int) session(SessionKeys::USER_COMPANY, 0);

        $prestados = $this->tramiteRepo->prestarExpedientes(
            $ids->all(),
            $cargoId,
            (int) ($user->cdgo_lgin ?? 0),
            $fecha,
            $observaciones,
            $request->ip() ?: '',
            $companiaId
        );

        return response()->json([
            'status' => 'success',
            'message' => "{$prestados} expediente(s) prestado(s).",
            'prestados' => $prestados,
            'counts' => $this->tramiteRepo->obtenerConteosArchivos($cargoId, $companiaId),
        ]);
    }

    public function matriz(): JsonResponse
    {
        $this->authorizeArchiveAccess();

        $celdas = $this->tramiteRepo->obtenerMatrizArchivador();

        return response()->json([
            'status' => 'success',
            'data' => $celdas,
        ]);
    }

    public function imprimir(ArchivoExpedienteTablaRequest $request)
    {
        $this->authorizeArchiveAccess();

        $data = $request->validated();
        $cargoId = $this->currentCargoId();
        $companiaId = (int) session(SessionKeys::USER_COMPANY, 0);

        $rows = collect($this->tramiteRepo->obtenerExpedientesArchivoParaImprimir($data, $cargoId, $companiaId, 1000))
            ->map(fn ($row) => $this->formatRow((object) $row));

        return view('mod_gm.mod_tram_exte.pdf.segi-archivo-print', [
            'bandeja' => $data['bandeja'],
            'rows' => $rows,
            'generado' => Carbon::now('America/Lima')->format('d/m/Y H:i'),
        ]);
    }

    public function detalle(ArchivoExpedienteDetalleRequest $request): JsonResponse
    {
        $this->authorizeArchiveAccess();

        $data = $request->validated();
        $res = $this->tramiteRepo->obtenerDetalleCompletoArchivo((int) $data['id']);

        if (! $res) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado.'], 404);
        }

        $historial = collect($res['historial'])->map(fn ($item) => [
            'movimiento_id' => (int) $item->cdgo_trmte_dcmnto,
            'fecha' => $this->formatDateTime($item->fe_rcbdo_trmte),
            'estado' => $item->dc_estdo_trmte ?: $item->de_estdo_trmte,
            'origen' => $item->area_origen ?: '-',
            'destino' => $item->area_destino ?: '-',
            'observacion' => $item->de_obse ?: '',
            'activo' => (string) $item->in_esta === '1',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatRow((object) $res['row']),
            'historial' => $historial,
        ]);
    }

    private function sendNotificationEmail(int $id, string $observaciones, string $adjunto): void
    {
        $companiaId = (int) session(SessionKeys::USER_COMPANY, 0);
        $contacto = $this->tramiteRepo->obtenerContactoNotificacionExpediente($id, $companiaId);

        if (! $contacto) {
            return;
        }

        $emailInfo = (object) $contacto;
        $targetMail = strtolower(trim((string) ($emailInfo->de_usrios_mail ?: $emailInfo->de_mail)));
        if ($targetMail === '') {
            return;
        }

        $filePath = $adjunto !== '' ? storage_path('app/public/adjuntos/' . $adjunto) : null;
        $area = (string) session(SessionKeys::AREA_NOMBRE, '');

        try {
            Mail::to($targetMail)->send(new ExpedienteNotificadoMail(
                $emailInfo,
                $observaciones,
                $area !== '' ? $area : 'Archivo',
                $filePath,
                $adjunto !== '' ? basename($adjunto) : null
            ));
        } catch (\Throwable $exception) {
            Log::warning("No se pudo enviar correo de notificacion del expediente {$id}: " . $exception->getMessage());
        }
    }

    private function formatRow(object $row): array
    {
        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'movimiento_id' => (int) $row->cdgo_trmte_dcmnto,
            'expediente' => $this->formatNumeroExpediente($row->nu_expe_todo),
            'expediente_raw' => $row->nu_expe_todo,
            'expediente_varios' => $row->nu_expe_trmte,
            'folios' => (int) ($row->nu_foli_trmte ?? 0),
            'solicitante' => $row->no_crto,
            'documento' => $row->nu_docu,
            'fecha_ingreso' => $this->formatDate($row->fe_ingr_trmte),
            'fecha_movimiento' => $this->formatDateTime($row->fe_rcbdo_trmte),
            'estado_id' => (int) $row->cdgo_estdo_trmte,
            'estado' => $row->dc_estdo_trmte ?: $row->de_estdo_trmte,
            'area' => $row->dc_area ?: $row->de_area,
            'area_larga' => $row->de_area,
            'observaciones' => $row->de_obse_trmte ?: $row->de_obse_todo ?: $row->movimiento_observacion,
            'movimiento_observacion' => $row->movimiento_observacion,
            'usuario' => $row->usuario_plataforma,
            'adjunto' => $row->de_urls_imag,
        ];
    }

    private function formatNumeroExpediente(?string $numero): string
    {
        if (! $numero) {
            return '';
        }

        $parts = explode('-', $numero);
        if (count($parts) >= 3) {
            return $parts[0] . '-' . str_pad($parts[1] . '-' . substr($parts[2], 2), 8, '0', STR_PAD_LEFT);
        }

        return $numero;
    }

    private function formatDate($value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y') : null;
    }

    private function formatDateTime($value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y H:i') : null;
    }

    private function currentCargoId(): int
    {
        return (int) session(SessionKeys::CARGO_ID, 0);
    }

    private function authorizeArchiveAccess(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $cargoId = $this->currentCargoId();
        if ($cargoId <= 0) {
            abort(403, 'Debe seleccionar un cargo activo para acceder a la bandeja de archivo.');
        }

        $hasPermission = $this->tramiteRepo->verificarPermisoArchivo((int) $user->cdgo_prfle, $cargoId);

        if (! $hasPermission) {
            abort(403, 'No tiene permiso para acceder a la bandeja de archivo.');
        }
    }

    private function searchFields(): array
    {
        return [
            ['value' => 'expediente', 'label' => 'Nro de Expediente'],
            ['value' => 'expediente_varios', 'label' => 'Nro de Expediente Varios'],
            ['value' => 'nombre', 'label' => 'Nombre o Razon Social'],
            ['value' => 'estado', 'label' => 'Estado'],
            ['value' => 'observaciones', 'label' => 'Observaciones'],
        ];
    }
}
