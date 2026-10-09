<?php

namespace App\Services\mod_gdu;

use App\Helpers\GduHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProcedenteConstanciaService
{
    private const PER_PAGE = 12;

    private function schema(string $key): string
    {
        return rtrim((string) config("gdu.{$key}", ''), '.') . '.';
    }

    public function listar(Request $request): LengthAwarePaginator
    {
        $catastro = $this->schema('schema_catastro');

        $query = DB::table("{$catastro}tb_crtfcdo_poss as p")
            ->join("{$catastro}pers_certif_poss as pcp", 'pcp.cod_pers', '=', 'p.cdgo_usrios_slctnte')
            ->join("{$catastro}estado_certif_poss as po", 'po.cdgo_estado', '=', 'p.cdgo_estdo_poss')
            ->where('p.cdgo_estdo_poss', 1)
            ->select(
                'p.cdgo_crtfcdo_poss',
                'p.num_expe',
                'p.fe_ingr_trmte',
                'p.fe_crtfcdo_poss',
                'p.nu_crtfcdo_poss',
                DB::raw("concat(pcp.ape_paterno, ' ', pcp.ape_materno, ' ', pcp.nom_pers) as nom_per"),
                'p.nu_cmprbnte_pago',
                'po.nom_estado',
                DB::raw("concat(p.de_drccon_prdio, ' ', p.manza_predio, ' ', p.lote_predio) as direccion")
            );

        $this->aplicarFiltros($query, $request);

        return $query
            ->orderByDesc('p.cdgo_crtfcdo_poss')
            ->paginate(self::PER_PAGE)
            ->withPath(route('gdu.procedente_constancia'))
            ->withQueryString()
            ->through(function ($row) {
                $row->fe_ingr_trmte_fmt = GduHelper::fechaPeru($row->fe_ingr_trmte);
                $row->fe_crtfcdo_poss_fmt = GduHelper::fechaPeru($row->fe_crtfcdo_poss);
                $row->direccion_corta = GduHelper::truncar($row->direccion, 30) . (strlen((string) $row->direccion) > 30 ? '...' : '');
                $row->doc_url = url('/Presentacion/mod_gdu/constancia/gene_docu.php') . '?' . http_build_query([
                    'intCdgoCrtfcdoPose' => $row->cdgo_crtfcdo_poss,
                    'outputFile' => 1,
                ]);

                return $row;
            });
    }

    private function aplicarFiltros($query, Request $request): void
    {
        $opcion = $request->input('cboOPCIONES');

        if (empty($opcion)) {
            return;
        }

        switch ($opcion) {
            case 'EXPEDIENTE':
                $query->where('p.num_expe', 'ILIKE', '%' . $request->input('txtBUSCAR', '') . '%');
                break;
            case 'CONSTANCIA':
                $query->where('p.nu_crtfcdo_poss', 'ILIKE', '%' . $request->input('txtBUSCAR', '') . '%');
                break;
            case 'EMISION':
                $inicio = $request->input('txtFechaInicio');
                $fin = $request->input('txtFechaFin');
                if ($inicio && $fin) {
                    $query->whereRaw('cast(p.fe_crtfcdo_poss as date) between ? and ?', [$inicio, $fin]);
                }
                break;
            case 'NOMBRE':
                $buscar = strtoupper(str_replace(' ', '%', $request->input('txtBUSCAR', '')));
                $query->whereRaw("concat(pcp.ape_paterno, ' ', pcp.ape_materno, ' ', pcp.nom_pers) LIKE ?", ['%' . $buscar . '%']);
                break;
            case 'DIRECCION':
                $buscar = strtoupper($request->input('txtBUSCAR', ''));
                $query->whereRaw("concat(p.de_drccon_prdio, ' ', p.manza_predio, ' ', p.lote_predio) LIKE ?", ['%' . $buscar . '%']);
                break;
        }
    }

    public function opcionesBusqueda(): array
    {
        return [
            'EXPEDIENTE' => 'N° de Expediente',
            'CONSTANCIA' => 'N° de Constancia',
            'EMISION' => 'Fecha de Emisión',
            'NOMBRE' => 'Nombre',
            'DIRECCION' => 'Dirección',
        ];
    }
}
