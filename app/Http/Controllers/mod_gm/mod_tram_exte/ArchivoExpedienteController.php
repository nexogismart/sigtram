<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use App\Mail\ExpedienteNotificadoMail;
use App\Http\Requests\ArchivoExpedienteArchivarRequest;
use App\Http\Requests\ArchivoExpedienteDetalleRequest;
use App\Http\Requests\ArchivoExpedienteNotificarRequest;
use App\Http\Requests\ArchivoExpedientePrestarRequest;
use App\Http\Requests\ArchivoExpedienteTablaRequest;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ArchivoExpedienteController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
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

        $query = $this->baseQuery();
        $this->applyBandeja($query, $data['bandeja']);
        $this->applyCompanyFilter($query);
        $this->applySearch($query, $data['campo'] ?? 'expediente', $data['criterio'] ?? '');
        $this->applyDates($query, $data['desde'] ?? null, $data['hasta'] ?? null);

        $perPage = (int) ($data['per_page'] ?? 10);
        $page = (int) ($data['page'] ?? 1);
        $paginator = $query->orderByDesc('r.fe_rcbdo_trmte')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => collect($paginator->items())->map(fn ($row) => $this->formatRow($row))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'counts' => $this->counts(),
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

        $archivados = DB::transaction(function () use ($ids, $cargoId, $user, $fecha, $observaciones, $request) {
            $validQuery = $this->baseQuery()
                ->whereIn('p.cdgo_dto_trmte', $ids->all())
                ->where(function (Builder $query) use ($cargoId) {
                    $query->where('r.cdgo_estdo_trmte', '28')
                        ->orWhere(function (Builder $subQuery) use ($cargoId) {
                            $subQuery->whereIn('r.cdgo_estdo_trmte', ['3', '26'])
                                ->where('r.cdgo_crgo_en', $cargoId)
                                ->where('r.cdgo_crgo_sa', $cargoId);
                        });
                });
            $this->applyCompanyFilter($validQuery);

            $validIds = $validQuery->pluck('p.cdgo_dto_trmte')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($validIds as $id) {
                $this->closeCargoTracking($id);
                $this->createMovement($id, 27, $cargoId, $fecha, $observaciones, '', $user);
                $this->appendObservacion($id, $observaciones, $user);
                $this->writeLog($id, 'Se archivo el expediente', $request->ip(), $user);
            }

            return $validIds->count();
        });

        return response()->json([
            'status' => 'success',
            'message' => "{$archivados} expediente(s) archivado(s).",
            'archivados' => $archivados,
            'counts' => $this->counts(),
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

        $notificados = DB::transaction(function () use ($ids, $cargoId, $user, $fecha, $observaciones, $request) {
            $validQuery = $this->baseQuery()
                ->whereIn('p.cdgo_dto_trmte', $ids->all())
                ->whereIn('r.cdgo_estdo_trmte', ['3', '26']);
            $this->scopeCurrentCargo($validQuery);
            $this->applyCompanyFilter($validQuery);

            $validIds = $validQuery->pluck('p.cdgo_dto_trmte')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $adjunto = '';
            if ($request->hasFile('documento')) {
                $file = $request->file('documento');
                $filename = 'NOTIF_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $adjunto = 'notificaciones/' . now('America/Lima')->format('Y/m') . '/' . $filename;
                $file->storeAs('adjuntos/notificaciones/' . now('America/Lima')->format('Y/m'), $filename, 'public');
            }

            foreach ($validIds as $id) {
                $this->closeCargoTracking($id);
                $this->createMovement($id, 26, $cargoId, $fecha, $observaciones, $adjunto, $user);
                $this->appendObservacion($id, $observaciones, $user);
                $this->writeLog($id, 'Se notifico el expediente', $request->ip(), $user);
                $this->sendNotificationEmail($id, $observaciones, $adjunto);
            }

            return $validIds->count();
        });

        return response()->json([
            'status' => 'success',
            'message' => "{$notificados} expediente(s) notificado(s).",
            'notificados' => $notificados,
            'counts' => $this->counts(),
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

        $prestados = DB::transaction(function () use ($ids, $cargoId, $user, $fecha, $observaciones, $request) {
            $validQuery = $this->baseQuery()
                ->whereIn('p.cdgo_dto_trmte', $ids->all())
                ->where('r.cdgo_estdo_trmte', '27');
            $this->scopeCurrentCargo($validQuery);
            $this->applyCompanyFilter($validQuery);

            $validIds = $validQuery->pluck('p.cdgo_dto_trmte')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($validIds as $id) {
                $this->closeCargoTracking($id);
                $this->createMovement($id, 28, $cargoId, $fecha, $observaciones, '', $user);
                $this->appendObservacion($id, $observaciones, $user);
                $this->releaseArchiveSlot($id);
                $this->writeLog($id, 'Se presto el expediente', $request->ip(), $user);
            }

            return $validIds->count();
        });

        return response()->json([
            'status' => 'success',
            'message' => "{$prestados} expediente(s) prestado(s).",
            'prestados' => $prestados,
            'counts' => $this->counts(),
        ]);
    }

    public function matriz(): JsonResponse
    {
        $this->authorizeArchiveAccess();

        $celdas = DB::table($this->schema . 'a_mtriz_archvdor as m')
            ->leftJoin($this->schema . 'r_mtriz_archvdor_trmtes as rm', function ($join) {
                $join->on('m.cdgo_mtriz_archvdor', '=', 'rm.cdgo_mtriz_archvdor')
                    ->where('rm.in_esta', '1');
            })
            ->leftJoin($this->schema . 'p_dto_trmtes as p', 'rm.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('m.in_esta', '1')
            ->select([
                'm.cdgo_mtriz_archvdor',
                'm.nu_psllo',
                'm.nu_fla',
                'm.nu_clmna',
                'm.no_mtriz_almcen',
                'm.nu_mxmo',
                DB::raw('count(rm.cdgo_mtriz_archvdor_trmtes) as ocupados'),
                DB::raw("string_agg(p.nu_expe_todo::text, ', ' order by p.nu_expe_todo) as expedientes"),
            ])
            ->groupBy('m.cdgo_mtriz_archvdor', 'm.nu_psllo', 'm.nu_fla', 'm.nu_clmna', 'm.no_mtriz_almcen', 'm.nu_mxmo')
            ->orderBy('m.nu_psllo')
            ->orderBy('m.nu_fla')
            ->orderBy('m.nu_clmna')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->cdgo_mtriz_archvdor,
                'pasillo' => (int) $row->nu_psllo,
                'fila' => (int) $row->nu_fla,
                'columna' => (int) $row->nu_clmna,
                'nombre' => $row->no_mtriz_almcen,
                'maximo' => (int) $row->nu_mxmo,
                'ocupados' => (int) $row->ocupados,
                'expedientes' => $row->expedientes ?: '',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $celdas,
        ]);
    }

    public function imprimir(ArchivoExpedienteTablaRequest $request)
    {
        $this->authorizeArchiveAccess();

        $data = $request->validated();

        $query = $this->baseQuery();
        $this->applyBandeja($query, $data['bandeja']);
        $this->applyCompanyFilter($query);
        $this->applySearch($query, $data['campo'] ?? 'expediente', $data['criterio'] ?? '');
        $this->applyDates($query, $data['desde'] ?? null, $data['hasta'] ?? null);

        $rows = $query->orderByDesc('r.fe_rcbdo_trmte')
            ->limit(1000)
            ->get()
            ->map(fn ($row) => $this->formatRow($row));

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
        $row = $this->baseQuery()->where('p.cdgo_dto_trmte', (int) $data['id'])->first();

        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado.'], 404);
        }

        $historial = DB::table($this->schema . 'r_trmtes_dcmntos as r')
            ->leftJoin($this->schema . 'p_crgos as c_sa', 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a_sa', 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->leftJoin($this->schema . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('r.cdgo_dto_trmte', (int) $data['id'])
            ->select([
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                'a_sa.de_area as area_origen',
                'a_en.de_area as area_destino',
                'r.de_obse',
                'r.in_esta',
            ])
            ->orderByDesc('r.cdgo_trmte_dcmnto')
            ->get()
            ->map(fn ($item) => [
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
            'data' => $this->formatRow($row),
            'historial' => $historial,
        ]);
    }

    private function baseQuery(): Builder
    {
        return DB::table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->join($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin($this->schema . 'p_lgin as l', 'p.co_usua_ingr', '=', 'l.cdgo_lgin')
            ->leftJoin($this->schema . 'p_usrios as usuario_lgin', 'l.cdgo_usrios', '=', 'usuario_lgin.cdgo_usrios')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.nu_expe_trmte',
                'p.nu_foli_trmte',
                'p.fe_ingr_trmte',
                'p.de_obse_trmte',
                'p.de_obse_todo',
                'u.no_crto',
                'u.nu_docu',
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'r.cdgo_estdo_trmte',
                'r.de_obse as movimiento_observacion',
                'r.de_urls_imag',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                'a_en.dc_area',
                'a_en.de_area',
                'usuario_lgin.no_crto as usuario_plataforma',
            ])
            ->where('r.in_esta', '1');
    }

    private function applyBandeja(Builder $query, string $bandeja): void
    {
        match ($bandeja) {
            'finalizados' => $this->scopeCurrentCargo($query)->whereIn('r.cdgo_estdo_trmte', ['3', '26']),
            'archivados' => $this->scopeCurrentCargo($query)->where('r.cdgo_estdo_trmte', '27'),
            'prestados' => $query->where('r.cdgo_estdo_trmte', '28'),
        };
    }

    private function scopeCurrentCargo(Builder $query): Builder
    {
        return $query
            ->where('r.cdgo_crgo_en', $this->currentCargoId())
            ->where('r.cdgo_crgo_sa', $this->currentCargoId());
    }

    private function applyCompanyFilter(Builder $query): void
    {
        $company = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));
        if ($company > 0) {
            $query->where('p.cdgo_cmpnia', $company);
        }
    }

    private function applySearch(Builder $query, string $field, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        if ($field === 'expediente_varios') {
            $numbers = collect(explode(',', $value))->map(fn ($part) => trim($part))->filter()->all();
            if ($numbers) {
                $query->whereIn('p.nu_expe_trmte', $numbers);
            }
            return;
        }

        $column = match ($field) {
            'nombre' => 'u.no_crto',
            'estado' => 'e.dc_estdo_trmte',
            'observaciones' => 'p.de_obse_trmte',
            default => 'p.nu_expe_todo',
        };

        $query->where($column, 'ILIKE', '%' . mb_strtoupper($value, 'UTF-8') . '%');
    }

    private function applyDates(Builder $query, ?string $desde, ?string $hasta): void
    {
        if ($desde) {
            $query->where('r.fe_rcbdo_trmte', '>=', $desde . ' 00:00:00');
        }

        if ($hasta) {
            $query->where('r.fe_rcbdo_trmte', '<=', $hasta . ' 23:59:59');
        }
    }

    private function createMovement(int $id, int $estado, int $cargoId, string $fecha, string $observaciones, string $adjunto, $user): void
    {
        DB::table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $id)
            ->update(['in_esta' => '0']);

        DB::table($this->schema . 'r_trmtes_dcmntos')->insert([
            'cdgo_crgo_sa' => $cargoId,
            'cdgo_crgo_en' => $cargoId,
            'cdgo_dto_trmte' => $id,
            'cdgo_estdo_trmte' => (string) $estado,
            'fe_rcbdo_trmte' => $fecha,
            'de_obse' => $observaciones,
            'in_esta' => '1',
            'de_urls_imag' => $adjunto,
            'cdgo_lgin' => (int) ($user->cdgo_lgin ?? 0),
            'nu_foli_anex' => null,
        ]);
    }

    private function closeCargoTracking(int $id): void
    {
        $trackingId = DB::table($this->schema . 'r_dtos_crgos_trmtes')
            ->where('cdgo_dto_trmte', $id)
            ->where('in_esta', '1')
            ->orderByDesc('cdgo_dto_crgo_trmte')
            ->value('cdgo_dto_crgo_trmte');

        if ($trackingId) {
            DB::table($this->schema . 'r_dtos_crgos_trmtes')
                ->where('cdgo_dto_crgo_trmte', $trackingId)
                ->update(['fe_dto_crgo_fin' => now()]);
        }
    }

    private function appendObservacion(int $id, string $observaciones, $user): void
    {
        $currentObse = (string) (DB::table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $id)
            ->value('de_obse_todo') ?? '');

        DB::table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $id)
            ->update([
                'de_obse_todo' => $currentObse !== '' ? $currentObse . ' - ' . $observaciones : $observaciones,
                'co_usua_modi' => (int) ($user->cdgo_lgin ?? 0),
                'ts_usua_modi' => now(),
            ]);
    }

    private function releaseArchiveSlot(int $id): void
    {
        DB::table($this->schema . 'r_mtriz_archvdor_trmtes')
            ->where('cdgo_dto_trmte', $id)
            ->where('in_esta', '1')
            ->update(['in_esta' => '0']);
    }

    private function writeLog(int $id, string $message, ?string $ip, $user): void
    {
        DB::table($this->schema . 'p_sgmto_log')->insert([
            'cdgo_lgin' => (int) ($user->cdgo_lgin ?? 0),
            'de_sgto_log' => "{$message} {$id}",
            'fe_sgto_log' => now(),
            'de_ip' => $ip,
        ]);
    }

    private function sendNotificationEmail(int $id, string $observaciones, string $adjunto): void
    {
        $emailInfo = DB::table($this->schema . 'p_dto_trmtes as p')
            ->join($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->leftJoin($this->schema . 'h_usrios_crreo as h', 'p.cdgo_usrios_crreo', '=', 'h.cdgo_usrios_crreo')
            ->select('u.de_mail', 'p.nu_expe_todo', 'u.no_crto', 'h.de_mail as de_usrios_mail')
            ->where('p.cdgo_dto_trmte', $id)
            ->first();

        if (!$emailInfo) {
            return;
        }

        $targetMail = strtolower(trim($emailInfo->de_usrios_mail ?: $emailInfo->de_mail));
        if ($targetMail === '') {
            return;
        }

        $filePath = $adjunto !== '' ? storage_path('app/public/adjuntos/' . $adjunto) : null;
        $area = (string) session('sigtram_area_nombre', '');

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

    private function counts(): array
    {
        $base = $this->baseQuery();
        $this->applyCompanyFilter($base);

        $finalizados = clone $base;
        $archivados = clone $base;
        $prestados = clone $base;

        return [
            'finalizados' => $this->scopeCurrentCargo($finalizados)->whereIn('r.cdgo_estdo_trmte', ['3', '26'])->count(),
            'archivados' => $this->scopeCurrentCargo($archivados)->where('r.cdgo_estdo_trmte', '27')->count(),
            'prestados' => $prestados->where('r.cdgo_estdo_trmte', '28')->count(),
        ];
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
        if (!$numero) {
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
        return (int) session('sigtram_cargo_id', 0);
    }

    private function authorizeArchiveAccess(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $cargoId = $this->currentCargoId();
        if ($cargoId <= 0) {
            abort(403, 'Debe seleccionar un cargo activo para acceder a la bandeja de archivo.');
        }

        $hasPermission = DB::table($this->schema . 'r_prmsos')
            ->where('cdgo_prfle', (int) $user->cdgo_prfle)
            ->where('cdgo_rle', 11)
            ->where(function (Builder $query) use ($cargoId) {
                $query->whereNull('cdgo_crgo')->orWhere('cdgo_crgo', $cargoId);
            })
            ->exists();

        if (!$hasPermission) {
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
