<?php

namespace App\Services\mod_gdu;

use App\Helpers\GduHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProcedenteLicenciasService
{
    private const PER_PAGE = 12;

    private function schema(string $key): string
    {
        return rtrim((string) config("gdu.{$key}", ''), '.') . '.';
    }

    private const CAMPOS_BUSQUEDA = [
        'p_dto_trmtes.nu_expe_todo' => 'N° de Expediente',
        'p_dclrcon_jrda.nu_cdgo_cntrbynte' => 'Codigo Predio',
        'p_usrios.no_crto' => 'Nombre del solicitante',
        'p_dclrcon_jrda.nu_ruc_estblcmnto' => 'RUC del solicitante',
        'p_dclrcon_jrda.no_estblcmnto' => 'Nombre del establecimiento',
        'p_dclrcon_jrda.nu_crtfcdo' => 'N° de Licencia',
        'p_dclrcon_jrda.de_drccon_estblcmnto' => 'Direccion del establecimiento',
        'p_dclrcon_jrda.de_gro_actvdad' => 'Giro',
        'a_tpos_grpos.dc_tpos_grpos' => 'Grupo',
        'a_tpos_atrzcon.de_tpos_atrzcon' => 'Tipo Licencia',
        'a_estdos_lcncias.de_estdo_lcncias' => 'Estado',
    ];

    private const CAMPOS_FECHA = [
        'p_dto_trmtes.fe_ingr_trmte' => 'Expe',
        'p_dclrcon_jrda.fe_lcncia_fncnmnto' => 'Licen',
    ];

    public function listar(Request $request): LengthAwarePaginator
    {
        $query = $this->queryBase();
        $this->aplicarFiltros($query, $request);

        return $query
            ->orderByDesc('p_dclrcon_jrda.fe_lcncia_fncnmnto')
            ->paginate(self::PER_PAGE)
            ->withPath(route('gdu.procedente_licencias'))
            ->withQueryString()
            ->through(function ($row) {
                $plantillas = $this->resolverPlantillas($row);

                $row->nu_expe_fmt = GduHelper::formatoExpediente($row->nu_expe_todo);
                $row->fe_lcncia_fmt = GduHelper::fechaPeru($row->fe_lcncia_fncnmnto);
                $row->no_crto_corto = GduHelper::truncar($row->no_crto, 28);
                $row->direccion_corta = GduHelper::truncar($row->de_drccon_estblcmnto, 27);
                $row->riesgo = str_replace('RIESGO ', '', (string) $row->dc_tpos_grpos);
                $row->plantilla = $plantillas['plantilla'];
                $row->plantilla_cer = $plantillas['plantilla_cer'];
                $row->expediente_url = url('/Presentacion/mod_gm/mod_tram_exte/segi_tramite.php') . '?nu_expe=' . $row->cdgo_dto_trmte;
                $row->certificado_url = url('/Presentacion/mod_gdu/licencias/gene_docu.php') . '?' . http_build_query([
                    'id_dclrcon_jrda' => $row->cdgo_dclrcon_jrda,
                    'plantilla' => $plantillas['plantilla_cer'],
                    'outputFile' => 1,
                ]);
                $row->resolucion_url = !empty($row->nu_rslcon)
                    ? url('/Presentacion/mod_gdu/licencias/gene_docu.php') . '?' . http_build_query([
                        'id_dclrcon_jrda' => $row->cdgo_dclrcon_jrda,
                        'plantilla' => $plantillas['plantilla'],
                        'outputFile' => 1,
                    ])
                    : null;

                return $row;
            });
    }

    private function queryBase()
    {
        $licencias = $this->schema('schema_licencias');
        $sistema = $this->schema('schema_sistema');

        return DB::table("{$licencias}p_dclrcon_jrda as p_dclrcon_jrda")
            ->leftJoin("{$sistema}p_dto_trmtes as p_dto_trmtes", 'p_dclrcon_jrda.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->leftJoin("{$sistema}p_dto_trmtes as p_dto_trmtes_cse", 'p_dclrcon_jrda.cdgo_dto_trmte_cse', '=', 'p_dto_trmtes_cse.cdgo_dto_trmte')
            ->leftJoin("{$sistema}p_usrios as p_usrios", 'p_dclrcon_jrda.cdgo_usrios_slctnte', '=', 'p_usrios.cdgo_usrios')
            ->leftJoin("{$sistema}p_usrios as p_usrios_repr", 'p_dclrcon_jrda.cdgo_usrios_rprsntnte', '=', 'p_usrios_repr.cdgo_usrios')
            ->join("{$licencias}a_estdos_lcncias as a_estdos_lcncias", 'p_dclrcon_jrda.cdgo_estdo_lcncias', '=', 'a_estdos_lcncias.cdgo_estdo_lcncias')
            ->join("{$licencias}a_tpos_grpos as a_tpos_grpos", 'p_dclrcon_jrda.cdgo_tpos_grpos', '=', 'a_tpos_grpos.cdgo_tpos_grpos')
            ->join("{$licencias}a_tpos_atrzcon as a_tpos_atrzcon", 'p_dclrcon_jrda.cdgo_tpos_atrzcon', '=', 'a_tpos_atrzcon.cdgo_tpos_atrzcon')
            ->leftJoin("{$sistema}p_lgin as p_lgin", 'p_dclrcon_jrda.co_usua_modi', '=', 'p_lgin.cdgo_lgin')
            ->leftJoin("{$sistema}p_usrios as p_usrios_crea", 'p_lgin.cdgo_usrios', '=', 'p_usrios_crea.cdgo_usrios')
            ->where('p_dclrcon_jrda.in_esta', '1')
            ->whereIn('p_dclrcon_jrda.cdgo_estdo_lcncias', ['2', '17', '18', '19'])
            ->select(
                'p_dclrcon_jrda.cdgo_dclrcon_jrda',
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_usrios.no_crto',
                'p_dclrcon_jrda.de_drccon_estblcmnto',
                'p_dclrcon_jrda.nu_crtfcdo',
                'p_dclrcon_jrda.nu_rslcon',
                'a_tpos_grpos.dc_tpos_grpos',
                'a_tpos_grpos.cdgo_tpos_grpos',
                'a_tpos_atrzcon.cdgo_tpos_atrzcon',
                'p_dclrcon_jrda.fe_lcncia_fncnmnto',
                'p_dclrcon_jrda.cdgo_pri_dclrcon_jrda',
                'p_dclrcon_jrda.cdgo_estdo_lcncias',
                'p_dto_trmtes.cdgo_dto_trmte'
            );
    }

    private function aplicarFiltros($query, Request $request): void
    {
        $tipos = $request->input('tipo') === '4' ? ['4'] : ['1', '2', '3'];
        $query->whereIn('p_dclrcon_jrda.cdgo_tpos_atrzcon', $tipos);

        $campo = $request->input('D1');
        $criterio = trim((string) $request->input('criterio', ''));

        if ($criterio !== '' && isset(self::CAMPOS_BUSQUEDA[$campo])) {
            $query->whereRaw('UPPER(CAST(' . $campo . ' AS TEXT)) LIKE UPPER(?)', ['%' . $criterio . '%']);
        }

        $fechaFin = trim((string) $request->input('txt_fin', ''));
        if ($fechaFin !== '') {
            $campoFecha = $request->input('sel_tipo_fecha', 'p_dto_trmtes.fe_ingr_trmte');
            if (!isset(self::CAMPOS_FECHA[$campoFecha])) {
                $campoFecha = 'p_dto_trmtes.fe_ingr_trmte';
            }

            $inicio = GduHelper::fechaEeuu($request->input('txt_inicio'));
            $fin = GduHelper::fechaEeuu($fechaFin);

            if ($inicio && $fin) {
                $query->whereRaw("($campoFecha >= ? AND $campoFecha <= ?)", [
                    $inicio . ' 00:00:00',
                    $fin . ' 23:59:59',
                ]);
            }
        }
    }

    public function resolverPlantillas(object $row): array
    {
        if ((string) $row->cdgo_tpos_atrzcon === '2') {
            return ['plantilla' => 'r_lf45.odt', 'plantilla_cer' => 'clmf.odt'];
        }

        if ((string) $row->cdgo_tpos_atrzcon === '4') {
            return ['plantilla' => 'rlf1t.odt', 'plantilla_cer' => 'clmfa.odt'];
        }

        $plantillaCer = 'clmf.odt';

        if (!empty($row->cdgo_pri_dclrcon_jrda)) {
            $plantilla = 'r_lf_transferencia.odt';
            $plantillaCer = 'clmf_transferencia.odt';

            if ((string) $row->cdgo_estdo_lcncias === '19') {
                $plantilla = 'r_lf_duplicado.odt';
                $plantillaCer = 'clmf_duplicado.odt';
            }

            return ['plantilla' => $plantilla, 'plantilla_cer' => $plantillaCer];
        }

        $plantilla = 'rlf1.odt';
        $mapaGrupos = [
            '2' => 'rlf2.odt',
            '3' => 'rlf3.odt',
            '4' => 'r_lf45.odt',
            '5' => 'r_lf45.odt',
            '6' => 'r_lf67.odt',
            '7' => 'r_lf67.odt',
        ];

        $grupo = (string) ($row->cdgo_tpos_grpos ?? '');
        if (isset($mapaGrupos[$grupo])) {
            $plantilla = $mapaGrupos[$grupo];
        }

        return ['plantilla' => $plantilla, 'plantilla_cer' => $plantillaCer];
    }

    public function construirCriterioReporte(Request $request): string
    {
        $tipos = $request->input('tipo') === '4' ? "'4'" : "'1','2','3'";
        $criterio = " AND p_dclrcon_jrda.cdgo_tpos_atrzcon IN ($tipos) ";

        $campo = $request->input('D1');
        $texto = trim((string) $request->input('criterio', ''));

        if ($texto !== '' && isset(self::CAMPOS_BUSQUEDA[$campo])) {
            $criterio .= ' AND ' . $campo . " LIKE UPPER('%" . addslashes($texto) . "%')";
        }

        $fechaFin = trim((string) $request->input('txt_fin', ''));
        if ($fechaFin !== '') {
            $campoFecha = $request->input('sel_tipo_fecha', 'p_dto_trmtes.fe_ingr_trmte');
            if (!isset(self::CAMPOS_FECHA[$campoFecha])) {
                $campoFecha = 'p_dto_trmtes.fe_ingr_trmte';
            }

            $inicio = GduHelper::fechaEeuu($request->input('txt_inicio'));
            $fin = GduHelper::fechaEeuu($fechaFin);

            if ($inicio && $fin) {
                $criterio .= " AND ($campoFecha >= '{$inicio} 00:00:00' AND $campoFecha <= '{$fin} 23:59:59') ";
            }
        }

        return $criterio;
    }

    public function camposBusqueda(): array
    {
        return self::CAMPOS_BUSQUEDA;
    }

    public function camposFecha(): array
    {
        return self::CAMPOS_FECHA;
    }
}
