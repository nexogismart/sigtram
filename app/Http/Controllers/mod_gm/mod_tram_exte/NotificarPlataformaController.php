<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\NotificarTramiteRequest;
use App\Services\NotificarPlataformaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\SessionKeys;

class NotificarPlataformaController extends Controller
{
    private NotificarPlataformaService $service;
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(
        NotificarPlataformaService $service,
        TramiteExternoRepositoryInterface $tramiteRepo
    ) {
        $this->service = $service;
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->ajax()) {
                return response()->json(['status' => 'error', 'message' => 'No autorizado'], 401);
            }

            return redirect()->route('login');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return $this->tabla($request);
        }

        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Notificar Plataforma'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Notificar Plataforma';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
        ]);
    }

    public function tabla(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado'], 401);
        }

        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        if ($cmpnia <= 0) {
            return response()->json(['status' => 'error', 'message' => 'No tiene una compañía activa válida en la sesión.'], 403);
        }

        $perPage = (int) $request->input('per_page', 20);
        if (! in_array($perPage, [10, 15, 20, 25, 50, 100], true)) {
            $perPage = 20;
        }
        $page = max(1, (int) $request->input('page', 1));

        $res = $this->tramiteRepo->obtenerTablaNotificarPlataforma($request->all(), $page, $perPage, $cmpnia);

        $items = collect($res['items'])->map(function ($row) {
            $row->nu_expe_fmt = $this->formatExpediente((string) ($row->nu_expe_todo ?? ''));
            $row->silencio = ((string) $row->in_sile_admi === '1') ? 'Sí' : 'No';
            $row->tipo_corto = mb_substr((string) ($row->de_tpo_trmte ?? ''), 0, 8);

            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'total' => $res['total'],
            'current_page' => $res['current_page'],
            'last_page' => $res['last_page'],
        ]);
    }

    public function notificar(NotificarTramiteRequest $request, $id)
    {
        try {
            $pathEvidencia = $this->service->notificar(
                (int) $id,
                (string) $request->input('fecha'),
                $request->input('observaciones'),
                $request->file('documento'),
                (string) $request->ip()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'El expediente fue notificado exitosamente.',
                'file' => $pathEvidencia ? asset('storage/'.$pathEvidencia) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatExpediente(string $nu): string
    {
        $parts = explode('-', $nu);
        if (count($parts) < 3) {
            return $nu;
        }

        $mid = ($parts[1] ?? '').'-'.substr((string) ($parts[2] ?? ''), 2, 10);

        return ($parts[0] ?? '').'-'.str_pad($mid, 9, '0', STR_PAD_LEFT);
    }
}
