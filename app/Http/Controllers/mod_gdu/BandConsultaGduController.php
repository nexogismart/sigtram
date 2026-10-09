<?php

namespace App\Http\Controllers\mod_gdu;

use App\Http\Controllers\Controller;
use App\Services\mod_gdu\ProcedenteConstanciaService;
use App\Services\mod_gdu\ProcedenteLicenciasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BandConsultaGduController extends Controller
{
    private ProcedenteConstanciaService $constanciaService;
    private ProcedenteLicenciasService $licenciasService;

    public function __construct(
        ProcedenteConstanciaService $constanciaService,
        ProcedenteLicenciasService $licenciasService
    ) {
        $this->constanciaService = $constanciaService;
        $this->licenciasService = $licenciasService;
    }

    public function index()
    {
        return view('mod_gdu.band_consulta_gdu', [
            'bandejas' => $this->bandejas(),
            'iframe_src' => route('gdu.procedente_constancia'),
        ]);
    }

    public function procedenteConstancia(Request $request)
    {
        try {
            $registros = $this->constanciaService->listar($request);

            return view('mod_gdu.procedente_constancia', [
                'registros' => $registros,
                'opciones' => $this->constanciaService->opcionesBusqueda(),
                'filtros' => [
                    'cboOPCIONES' => $request->input('cboOPCIONES', 'EXPEDIENTE'),
                    'txtBUSCAR' => $request->input('txtBUSCAR', ''),
                    'txtFechaInicio' => $request->input('txtFechaInicio', ''),
                    'txtFechaFin' => $request->input('txtFechaFin', ''),
                ],
                'error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('GDU procedente_constancia: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return view('mod_gdu.procedente_constancia', [
                'registros' => null,
                'opciones' => $this->constanciaService->opcionesBusqueda(),
                'filtros' => [
                    'cboOPCIONES' => $request->input('cboOPCIONES', 'EXPEDIENTE'),
                    'txtBUSCAR' => $request->input('txtBUSCAR', ''),
                    'txtFechaInicio' => $request->input('txtFechaInicio', ''),
                    'txtFechaFin' => $request->input('txtFechaFin', ''),
                ],
                'error' => 'No se pudo cargar la bandeja de constancias. Verifique la conexión a la base de datos.',
            ]);
        }
    }

    public function procedenteLicencias(Request $request)
    {
        try {
            $registros = $this->licenciasService->listar($request);
            $criterio = $this->licenciasService->construirCriterioReporte($request);

            return view('mod_gdu.procedente_licencias', [
                'registros' => $registros,
                'camposBusqueda' => $this->licenciasService->camposBusqueda(),
                'camposFecha' => $this->licenciasService->camposFecha(),
                'filtros' => [
                    'D1' => $request->input('D1', 'p_dto_trmtes.nu_expe_todo'),
                    'criterio' => $request->input('criterio', ''),
                    'sel_tipo_fecha' => $request->input('sel_tipo_fecha', 'p_dto_trmtes.fe_ingr_trmte'),
                    'txt_inicio' => $request->input('txt_inicio', ''),
                    'txt_fin' => $request->input('txt_fin', ''),
                    'tipo' => $request->input('tipo', ''),
                ],
                'urls' => [
                    'ver_todo' => url('/Presentacion/mod_gdu/segi_autorizados.php'),
                    'imprimir' => url('/Presentacion/mod_gdu/gene_reporte.php') . '?' . http_build_query([
                        'criterio' => base64_encode(base64_encode($criterio . " AND p_dclrcon_jrda.cdgo_estdo_lcncias IN ('2')")),
                        'fe_inicio' => base64_encode($request->input('txt_inicio', '')),
                        'fe_fin' => base64_encode($request->input('txt_fin', '')),
                    ]),
                    'descargar' => url('/Presentacion/mod_gdu/descargar_autorizados.php') . '?' . http_build_query([
                        'criterio' => base64_encode(base64_encode($criterio . " AND p_dclrcon_jrda.cdgo_estdo_lcncias IN ('2','17','18','19')")),
                    ]),
                ],
                'error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('GDU procedente_licencias: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return view('mod_gdu.procedente_licencias', [
                'registros' => null,
                'camposBusqueda' => $this->licenciasService->camposBusqueda(),
                'camposFecha' => $this->licenciasService->camposFecha(),
                'filtros' => [
                    'D1' => $request->input('D1', 'p_dto_trmtes.nu_expe_todo'),
                    'criterio' => $request->input('criterio', ''),
                    'sel_tipo_fecha' => $request->input('sel_tipo_fecha', 'p_dto_trmtes.fe_ingr_trmte'),
                    'txt_inicio' => $request->input('txt_inicio', ''),
                    'txt_fin' => $request->input('txt_fin', ''),
                    'tipo' => $request->input('tipo', ''),
                ],
                'urls' => [
                    'ver_todo' => url('/Presentacion/mod_gdu/segi_autorizados.php'),
                    'imprimir' => '#',
                    'descargar' => '#',
                ],
                'error' => 'No se pudo cargar la bandeja de licencias. Verifique la conexión a la base de datos.',
            ]);
        }
    }

    private function bandejas(): array
    {
        return [
            [
                'label' => 'Constancias',
                'url' => route('gdu.procedente_constancia'),
                'icon' => 'fa-file-signature',
            ],
            [
                'label' => 'Licencias Funcionamiento',
                'url' => route('gdu.procedente_licencias'),
                'icon' => 'fa-store',
            ],
        ];
    }
}
