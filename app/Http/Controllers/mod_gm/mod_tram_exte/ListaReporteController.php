<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\SessionKeys;

class ListaReporteController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Reportes de Expedientes Ingresados'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Reportes de Expedientes Ingresados';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
        ]);
    }

    public function buscar(Request $request)
    {
        $perPage = 37;
        $page = max(1, (int) $request->input('page', 1));
        $company = (int) session(SessionKeys::USER_COMPANY, 0);

        $res = $this->tramiteRepo->buscarReporteExpedientesIngresados($request->all(), $page, $perPage, $company);

        return response()->json([
            'status' => 'success',
            'data' => collect($res['rows'])->map(fn ($row) => $this->mapRow((object) $row))->values(),
            'current_page' => $res['current_page'],
            'last_page' => $res['last_page'],
            'total' => $res['total'],
        ]);
    }

    public function imprimir(Request $request)
    {
        $company = (int) session(SessionKeys::USER_COMPANY, 0);
        $rows = collect($this->tramiteRepo->obtenerReporteExpedientesIngresadosParaImprimir($request->all(), $company, 2000))
            ->map(fn ($row) => $this->mapRow((object) $row))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'filtros' => $request->only([
                'modo', 'palabra', 'campo', 'fecha', 'desde', 'hasta',
                'palabra1', 'campo1', 'palabra2', 'campo2', 'excluir', 'simple',
            ]),
            'usuario' => Auth::user()->de_lgin ?? '',
            'fecha' => now()->format('d/m/Y'),
        ]);
    }

    private function mapRow(object $row): array
    {
        $obs = trim((string) ($row->de_obse_trmte ?? ''));
        $desc = $obs !== '' ? $obs : (string) ($row->de_docmnto_trmte ?? '');

        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'expediente' => $this->formatoExpediente($row->nu_expe_todo),
            'expediente_raw' => $row->nu_expe_todo,
            'folios' => $row->nu_foli_trmte,
            'fecha' => $this->fechaCorta($row->fe_ingr_trmte),
            'fecha_completa' => $row->fe_ingr_trmte ? Carbon::parse($row->fe_ingr_trmte)->format('d/m/Y') : '',
            'silencio' => ((string) $row->in_sile_admi === '1') ? 'Si' : 'No',
            'area' => $row->dc_area,
            'estado' => $row->de_estdo_trmte,
            'estado_corto' => mb_substr((string) ($row->dc_estdo_trmte ?: $row->de_estdo_trmte), 0, 5, 'UTF-8'),
            'estado_id' => (int) $row->cdgo_estdo_trmte,
            'solicitante' => $row->no_crto,
            'descripcion' => $desc,
            'usuario' => $row->usuario,
            'anex' => (int) $row->cdgo_estdo_trmte === 3 ? 0 : 1,
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

    private function fechaCorta($valor): string
    {
        if (! $valor) {
            return '';
        }

        return Carbon::parse($valor)->format('d/m');
    }
}
