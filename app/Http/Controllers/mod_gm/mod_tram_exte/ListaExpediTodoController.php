<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\SessionKeys;

class ListaExpediTodoController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Lista de Expedientes'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Lista de Expedientes';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
        ]);
    }

    public function buscar(Request $request)
    {
        $perPage = 36;
        $page = max(1, (int) $request->input('page', 1));
        $company = (int) session(SessionKeys::USER_COMPANY, 0);

        $res = $this->tramiteRepo->buscarExpedientesTodos($request->all(), $page, $perPage, $company);

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
        $rows = collect($this->tramiteRepo->obtenerExpedientesTodosParaImprimir($request->all(), $company, 2000))
            ->map(fn ($row) => $this->mapRow((object) $row))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'usuario' => Auth::user()->de_lgin ?? '',
            'fecha' => now('America/Lima')->format('d/m/Y'),
        ]);
    }

    private function mapRow(object $row): array
    {
        $estado = (string) ($row->de_estdo_trmte ?? '');
        $fin = (mb_strtoupper($estado, 'UTF-8') === 'FINALIZADO' && $row->fe_rcbdo_trmte)
            ? (string) $row->fe_rcbdo_trmte
            : Carbon::now('America/Lima')->format('Y-m-d H:i:s');

        $tipo = (string) ($row->de_tpo_trmte ?? '');

        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'expediente' => (string) ($row->nu_expe_todo ?? ''),
            'expediente_fmt' => $this->formatoExpediente($row->nu_expe_todo),
            'silencio' => ((string) $row->in_sile_admi === '1') ? 'Si' : 'No',
            'ta' => $this->tiempoEstimado((string) ($row->fe_ingr_trmte ?? ''), $fin),
            'oficina' => (string) ($row->de_area ?? ''),
            'folios' => $row->nu_foli_trmte,
            'estado' => $estado,
            'tipo' => $tipo,
            'tipo_corto' => $tipo !== '' ? mb_substr($tipo, 0, 1, 'UTF-8') : '',
            'fecha' => $row->fe_ingr_trmte ? Carbon::parse($row->fe_ingr_trmte)->format('d/m/Y') : '',
        ];
    }

    private function tiempoEstimado(string $comien, string $hoy): string
    {
        $inicio = strtotime($comien);
        $fin = strtotime($hoy);
        if ($inicio === false || $fin === false) {
            return '0s';
        }

        $s = $fin - $inicio;
        if ($s < 0) {
            $s = 0;
        }

        $d = intdiv($s, 86400);
        $s -= $d * 86400;
        $h = intdiv($s, 3600);
        $s -= $h * 3600;
        $m = intdiv($s, 60);
        $s -= $m * 60;
        $horas = ($d * 24) + $h;

        if ($d === 1) {
            return '1D';
        }
        if ($d >= 2) {
            return ($d + 1) . 'D';
        }
        if ($horas === 1) {
            return '1H';
        }
        if ($horas >= 2) {
            return $horas . 'H';
        }
        if ($m === 1) {
            return '1m';
        }
        if ($m >= 2) {
            return $m . 'm';
        }
        if ($s === 1) {
            return '1s';
        }
        if ($s >= 2) {
            return $s . 's';
        }

        return '0s';
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
