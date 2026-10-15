<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\mod_gm\mod_tram_exte\AdjuntarTramiteUploadRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Support\SessionKeys;

/**
 * Equivalente a segi_adjuntar.php
 * Vista BD: /mod_gm/mod_tram_exte/segi_adjuntar
 */
class AdjuntarTramiteController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // POST batch adjuntar (como el antiguo con hdn_cont)
        if ($request->isMethod('post') && $request->filled('hdn_cont')) {
            return $this->adjuntarLote($request);
        }

        $criterio = trim((string) $request->input('criterio', ''));
        $d1 = (string) $request->input('D1', 'p_dto_trmtes.nu_expe_todo');
        $fecha = trim((string) $request->input('fecha', ''));
        $pagina = max(1, (int) $request->input('pagina', 1));
        $tamPag = 15;
        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);

        $filtros = [
            'criterio' => $criterio,
            'd1' => $d1,
            'fecha' => $fecha,
        ];

        $resultado = $this->tramiteRepo->obtenerMovimientosParaAdjuntar($filtros, $pagina, $tamPag, $cmpnia);
        $rows = $resultado['rows'];
        $total = $resultado['total'];
        $pagina = $resultado['pagina'];
        $numPags = $resultado['numPags'];

        foreach ($rows as $row) {
            // Antiguo: F. Entrada = fe_rcbdo_trmte (no fe_ingr_trmte)
            $row->fe_fmt = $row->fe_rcbdo_trmte
                ? Carbon::parse($row->fe_rcbdo_trmte)->timezone('America/Lima')->format('d/m/Y')
                : '-';
            $row->nu_expe_fmt = $this->formatExpediente((string) ($row->nu_expe_todo ?? ''));
            $row->no_crto_corto = mb_substr((string) ($row->no_crto ?? ''), 0, 22);
            $row->dc_area_corto = mb_substr((string) ($row->dc_area ?? ''), 0, 9);
            $row->obse_corto = mb_substr((string) ($row->de_obse_trmte ?? ''), 0, 30);
        }

        return view($this->vistaDesdeMenu($pathVista, $opcionMenu), [
            'rows' => $rows,
            'total' => $total,
            'pagina' => $pagina,
            'numPags' => $numPags,
            'criterio' => $criterio,
            'd1' => $d1,
            'fecha' => $fecha,
            'ok' => session('ok'),
            'urlModulo' => url()->current(),
        ]);
    }

    public function upload(AdjuntarTramiteUploadRequest $request, $movimientoId)
    {
        try {
            if (! $request->hasFile('documento')) {
                return response()->json(['success' => false, 'message' => 'No se recibió ningún archivo.'], 400);
            }

            $filename = $this->guardarArchivo($request->file('documento'), (int) $movimientoId);
            $this->actualizarMovimiento((int) $movimientoId, $filename, $request);

            return response()->json([
                'success' => true,
                'message' => 'Archivo adjuntado correctamente.',
                'file' => $filename,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al subir el archivo adjunto', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el archivo.',
            ], 500);
        }
    }

    public function detalle(Request $request)
    {
        $id = (int) $request->id;
        $detalle = $this->tramiteRepo->obtenerDetalleMovimientoAdjuntar($id);

        if ($detalle === null) {
            return response()->json(['success' => false, 'message' => 'Movimiento no encontrado.']);
        }

        return response()->json([
            'success' => true,
            'data' => $detalle,
        ]);
    }

    private function adjuntarLote(Request $request)
    {
        $cont = (int) $request->input('hdn_cont', 0);
        $subidos = 0;

        for ($i = 1; $i <= $cont; $i++) {
            $cdgo = (int) $request->input('hdn_cdgo_trmte_dcmnto' . $i, 0);
            $fileKey = 'fle_de_urls_imag' . $i;

            if ($cdgo <= 0 || ! $request->hasFile($fileKey)) {
                continue;
            }

            $file = $request->file($fileKey);
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $filename = $this->guardarArchivo($file, $cdgo);
            $this->actualizarMovimiento($cdgo, $filename, $request);
            $subidos++;
        }

        $qs = http_build_query(array_filter([
            'criterio' => $request->input('criterio_keep'),
            'D1' => $request->input('D1_keep'),
            'fecha' => $request->input('fecha_keep'),
            'pagina' => $request->input('pagina_keep'),
        ]));

        return redirect()
            ->to(url()->current() . ($qs ? '?' . $qs : ''))
            ->with('ok', $subidos > 0
                ? "Se adjuntaron {$subidos} archivo(s)."
                : 'No se seleccionó ningún archivo.');
    }

    private function guardarArchivo($file, int $movimientoId): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = 'adjunto_' . $movimientoId . '_' . time() . '.' . $extension;
        $file->storeAs('adjuntos', $filename, 'public');

        return $filename;
    }

    private function actualizarMovimiento(int $movimientoId, string $filename, Request $request): void
    {
        $this->tramiteRepo->actualizarAdjuntoMovimiento(
            $movimientoId,
            $filename,
            Auth::user()->cdgo_lgin ?? 0,
            $request->ip()
        );
    }

    public function verArchivo(string $nombre)
    {
        $nombre = basename($nombre);
        $path = storage_path('app/public/adjuntos/' . $nombre);
        if (! is_file($path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->file($path);
    }

    private function formatExpediente(string $nu): string
    {
        $parts = explode('-', $nu);
        if (count($parts) < 3) {
            return $nu;
        }

        $mid = ($parts[1]) . '-' . substr((string) ($parts[2]), 2, 10);

        return ($parts[0]) . '-' . str_pad($mid, 8, '0', STR_PAD_LEFT);
    }
}

