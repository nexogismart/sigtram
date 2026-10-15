<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\SessionKeys;

class ListaReporAreaController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Reporte de Expedientes'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Reporte de Expedientes';
        }

        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
            'mostrarAutomaticos' => $cargoId === 14,
        ]);
    }

    public function buscar(Request $request)
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        if ($cargoId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay un cargo activo en la sesión.',
            ], 422);
        }

        $bandeja = (string) $request->input('bandeja', '1');
        if ($bandeja === '4') {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
            ]);
        }

        $perPage = 37;
        $page = max(1, (int) $request->input('page', 1));
        $company = (int) session(SessionKeys::USER_COMPANY, 0);

        $res = $this->tramiteRepo->buscarReporteArea($request->all(), $cargoId, $bandeja, $page, $perPage, $company);

        return response()->json([
            'status' => 'success',
            'data' => collect($res['rows'])->map(fn ($row) => $this->mapRow((object) $row))->values(),
            'current_page' => $res['current_page'],
            'last_page' => $res['last_page'],
            'total' => $res['total'],
        ]);
    }

    private function mapRow(object $row): array
    {
        $nombre = (string) ($row->no_crto ?? '');
        $obs = trim((string) ($row->de_obse_trmte ?? ''));
        $desc = $obs !== '' ? $obs : (string) ($row->de_docmnto_trmte ?? '');

        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'expediente' => $this->formatoExpediente($row->nu_expe_todo),
            'folios' => $row->nu_foli_trmte,
            'fecha' => $row->fe_rcbdo_trmte ? Carbon::parse($row->fe_rcbdo_trmte)->format('d/m') : '',
            'fecha_completa' => $row->fe_rcbdo_trmte ? Carbon::parse($row->fe_rcbdo_trmte)->format('d/m/Y H:i') : '',
            'silencio' => ((string) $row->in_sile_admi === '1') ? 'Si' : 'No',
            'solicitante' => $nombre,
            'solicitante_corto' => mb_strlen($nombre, 'UTF-8') > 28 ? mb_substr($nombre, 0, 28, 'UTF-8') : $nombre,
            'descripcion' => $desc,
            'usuario' => $row->usuario,
        ];
    }

    private function formatoExpediente(?string $numero): string
    {
        if (! $numero) {
            return '';
        }
        $parts = explode('-', $numero);
        if (count($parts) >= 3) {
            return $parts[0] . '-' . str_pad($parts[1] . '-' . substr($parts[2], 2, 10), 8, '0', STR_PAD_LEFT);
        }

        return $numero;
    }
}
