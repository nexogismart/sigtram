<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngresoExpedienteRequest;
use App\Models\Area;
use App\Models\DocumentoTramiteTupa;
use App\Models\PUsrrio;
use App\Models\RequisitoTramite;
use App\Models\ServicioArea;
use App\Models\User;
use App\Models\UsrioJuridico;
use App\Services\IngresoExpedienteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngresoExpedienteController extends Controller
{
    public function __construct(
        private IngresoExpedienteService $service
    ) {}

    private function schema(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * Vista de Ingreso de Expedientes.
     * El Blade se toma de a_rles.de_dreccon (no de un nombre fijo).
     */
    public function index(?Request $request = null, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Ingresar Expedientes'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Ingresar Expedientes';
        }

        $s = $this->schema();
        $cmpnia = auth()->user()->cdgo_cmpnia ?? 2;

        $gerencias = Area::whereNull('cdgo_area_prim')
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $cmpnia)
            ->where('cdgo_area', '!=', 0)
            ->orderBy('de_area', 'asc')
            ->get(['cdgo_area', 'de_area', 'dc_area']);

        $servicios = ServicioArea::where('in_esta', '1')
            ->orderBy('de_srvcio', 'asc')
            ->get(['cdgo_srvcio_area', 'de_srvcio']);

        $tiposDocumento = DB::table($s . 'a_dcmntos_iden')
            ->where('in_esta', '1')
            ->orderBy('nu_orde', 'asc')
            ->get(['cdgo_dcmntos_iden', 'dc_dcmntos_iden']);

        $tiposIngreso = DB::table($s . 'a_tpo_trmtes')
            ->where('in_esta', '1')
            ->whereIn('dc_tpo_trmte', ['E', 'S'])
            ->orderBy('nu_orde', 'asc')
            ->get(['cdgo_tpo_trmte', 'de_tpo_trmte', 'dc_tpo_trmte']);

        $tipoExterno = $tiposIngreso->firstWhere('dc_tpo_trmte', 'E');
        $tipoSimple = $tiposIngreso->firstWhere('dc_tpo_trmte', 'S');

        $tipoObservado = DB::table($s . 'a_tpo_trmtes')
            ->where('in_esta', '1')
            ->where('dc_tpo_trmte', 'O')
            ->first(['cdgo_tpo_trmte', 'de_tpo_trmte', 'dc_tpo_trmte']);

        $areaObservado = Area::where('in_esta', '1')
            ->where('cdgo_cmpnia', $cmpnia)
            ->where(function ($q) {
                $q->whereRaw("UPPER(de_area) LIKE '%GESTION DOCUMENTARIA%'")
                    ->orWhereRaw("UPPER(de_area) LIKE '%GESTIÓN DOCUMENTARIA%'");
            })
            ->orderBy('cdgo_area', 'asc')
            ->first(['cdgo_area', 'de_area']);

        $fechaServidor = now()->format('d/m/Y H:i:s');

        return view($pathVista, compact(
            'gerencias',
            'servicios',
            'tiposDocumento',
            'tiposIngreso',
            'tipoExterno',
            'tipoSimple',
            'tipoObservado',
            'areaObservado',
            'fechaServidor',
            'tituloOpcion',
            'pathVista'
        ));
    }

    public function store(IngresoExpedienteRequest $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesión no válida o expirada.',
                ], 401);
            }

            $validated = $request->validated();
            $validated['fecha_ingreso'] = now()->format('Y-m-d H:i:s');
            $validated['cdgo_tpo_observado'] = (int) $request->input('cdgo_tpo_observado', 0);
            $validated['cdgo_tpo_trmte'] = (int) $request->input('cdgo_tpo_trmte', 0);
            $validated['cdgo_area_observado'] = (int) $request->input('cdgo_area_observado', 0);

            $archivo = $request->file('fle_adjunto');

            $result = $this->service->registrar($validated, $user, $request->ip(), $archivo);

            return response()->json([
                'success' => true,
                'message' => 'Expediente registrado correctamente con el número: ' . $result['numero'],
                'expediente_id' => $result['id'],
                'voucher_url' => route('tramites.voucher', ['id' => $result['id']], false),
            ]);
        } catch (\Exception $e) {
            Log::error('Error registrando expediente: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['fle_adjunto']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al registrar el expediente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function buscarUsuario(Request $request)
    {
        $tipo = $request->query('tipo');
        $documento = preg_replace('/[^0-9]/', '', $request->query('documento') ?? '');

        if ($tipo !== 'juridica' && $tipo !== 'natural') {
            return response()->json(['success' => false, 'message' => 'Tipo de persona inválido.'], 400);
        }

        if (empty($documento)) {
            return response()->json([
                'success' => false,
                'message' => 'El número de documento es obligatorio y debe contener solo números.',
            ], 400);
        }

        try {
            if ($tipo === 'juridica') {
                $usr = PUsrrio::where('nu_ruc', $documento)
                    ->where('in_tipo_pers', '1')
                    ->with('juridico')
                    ->first();

                if (!$usr) {
                    return response()->json([
                        'success' => false,
                        'message' => 'RUC no encontrado en el sistema.',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $usr->cdgo_usrios,
                        'razon_social' => $usr->juridico->rz_soci ?? $usr->no_crto,
                        'ruc' => $usr->nu_ruc,
                        'tel_jur' => $usr->nu_tele,
                        'cor_jur' => $usr->de_mail,
                        'dir_jur' => $usr->de_dire,
                    ],
                ]);
            }

            $usr = PUsrrio::where('nu_docu', $documento)
                ->where('in_tipo_pers', '0')
                ->with('natural')
                ->first();

            if (!$usr) {
                return response()->json([
                    'success' => false,
                    'message' => 'DNI/Documento no encontrado en el sistema.',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $usr->cdgo_usrios,
                    'nombres' => $usr->natural->no_usrio ?? $usr->no_crto_sstma,
                    'ape_paterno' => $usr->natural->ap_pate ?? '',
                    'ape_materno' => $usr->natural->ap_mate ?? '',
                    'telefono' => $usr->nu_tele,
                    'correo' => $usr->de_mail,
                    'direccion' => $usr->de_dire,
                    'tipo_doc' => $usr->natural->cdgo_dcmntos_iden ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar el solicitante: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function buscarJuridico(Request $request)
    {
        $razon = trim(strip_tags((string) $request->query('razon', '')));
        if (mb_strlen($razon) < 3) {
            return response()->json(['success' => false, 'message' => 'Ingrese al menos 3 caracteres.']);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtoupper($razon, 'UTF-8'));

        $row = UsrioJuridico::query()
            ->whereRaw('UPPER(rz_soci) LIKE ?', [$escaped . '%'])
            ->orderBy('rz_soci')
            ->first();

        if (!$row) {
            $row = UsrioJuridico::query()
                ->whereRaw('UPPER(rz_soci) LIKE ?', ['%' . $escaped . '%'])
                ->orderBy('rz_soci')
                ->first();
        }

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Razón social no encontrada.']);
        }

        $usr = PUsrrio::find($row->cdgo_usrios);
        if (!$usr) {
            return response()->json(['success' => false, 'message' => 'Razón social no encontrada.']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $usr->cdgo_usrios,
                'razon_social' => $row->rz_soci ?: $usr->no_crto,
                'ruc' => $usr->nu_ruc,
                'tel_jur' => $usr->nu_tele,
                'cor_jur' => $usr->de_mail,
                'dir_jur' => $usr->de_dire,
            ],
        ]);
    }

    public function sugerirJuridicos(Request $request)
    {
        $term = trim(strip_tags((string) $request->query('term', '')));
        if (mb_strlen($term) < 3) {
            return response()->json([]);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtoupper($term, 'UTF-8'));

        $items = UsrioJuridico::query()
            ->whereRaw('UPPER(rz_soci) LIKE ?', ['%' . $escaped . '%'])
            ->orderBy('rz_soci')
            ->limit(20)
            ->get(['cdgo_usrios', 'rz_soci']);

        return response()->json($items);
    }

    public function listarSubgerencias($gerenciaId)
    {
        $cmpnia = auth()->user()->cdgo_cmpnia ?? 2;

        $subgerencias = Area::where('cdgo_area_prim', $gerenciaId)
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $cmpnia)
            ->orderBy('de_area', 'asc')
            ->get(['cdgo_area as id', 'de_area as nombre']);

        return response()->json($subgerencias);
    }

    public function listarTramites($servicioId)
    {
        $s = $this->schema();

        $tramites = DB::table($s . 'a_docmntos_trmtes as d')
            ->join($s . 'a_tupa as tupa', 'd.cdgo_tupa', '=', 'tupa.cdgo_tupa')
            ->where('d.cdgo_srvcio_area', $servicioId)
            ->where('d.in_esta', '1')
            ->where('tupa.in_esta', '1')
            ->orderBy('d.de_docmnto_trmte', 'asc')
            ->get([
                'd.cdgo_docmnto_trmte as id',
                'd.de_docmnto_trmte as nombre',
                'd.in_sile_admi',
                'd.nu_tiem_sile',
            ]);

        return response()->json($tramites);
    }

    public function listarRequisitos($tramiteId)
    {
        $requisitos = RequisitoTramite::where('cdgo_docmnto_trmte', $tramiteId)
            ->where('in_esta', '1')
            ->orderBy('nu_orde', 'asc')
            ->get([
                'cdgo_rqto_trmte as id',
                'de_rqto_trmte as descripcion',
                'in_ctgria',
                'prcio_trmte',
                'nu_orde',
            ]);

        $silencio = DocumentoTramiteTupa::where('cdgo_docmnto_trmte', $tramiteId)
            ->first(['cdgo_docmnto_trmte', 'in_sile_admi', 'nu_tiem_sile']);

        return response()->json([
            'requisitos' => $requisitos,
            'silencio' => $silencio,
        ]);
    }

    public function buscarTramitesTupa(Request $request)
    {
        $term = strip_tags(trim($request->query('term', '')));
        if (strlen($term) < 3) {
            return response()->json([]);
        }

        $s = $this->schema();
        $escapedTerm = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);

        $tramites = DB::table($s . 'a_docmntos_trmtes as d')
            ->join($s . 'a_tupa as tupa', 'd.cdgo_tupa', '=', 'tupa.cdgo_tupa')
            ->whereRaw('LOWER(d.de_docmnto_trmte) LIKE ?', ['%' . mb_strtolower($escapedTerm, 'UTF-8') . '%'])
            ->where('d.in_esta', '1')
            ->where('tupa.in_esta', '1')
            ->orderBy('d.de_docmnto_trmte', 'asc')
            ->limit(20)
            ->get([
                'd.cdgo_docmnto_trmte as id',
                'd.de_docmnto_trmte as nombre',
                'd.in_sile_admi',
                'd.nu_tiem_sile',
            ]);

        return response()->json($tramites);
    }

    public function lista(Request $request)
    {
        $s = $this->schema();
        $cmpnia = auth()->user()->cdgo_cmpnia ?? 2;
        $pagina = max(1, (int) $request->query('pagina', 1));
        $tamPag = 20;

        $allowed = [
            'p_dto_trmtes.nu_expe_todo',
            'p_usrios_1.nu_docu',
            'p_usrios_1.no_crto',
            'a_areas.de_area',
            'a_estdos_trmtes.dc_estdo_trmte',
            'p_lgin.de_lgin',
            'p_dto_trmtes.de_obse_todo',
        ];

        $tiposLista = DB::table($s . 'a_tpo_trmtes')
            ->whereIn('dc_tpo_trmte', ['E', 'S', 'O'])
            ->pluck('cdgo_tpo_trmte')
            ->all();

        if (empty($tiposLista)) {
            $tiposLista = [17, 22, 40, 42, 55, 65];
        }

        $query = DB::table($s . 'p_dto_trmtes as p_dto_trmtes')
            ->join($s . 'r_trmtes_dcmntos as r_trmtes_dcmntos', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->join($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($s . 'a_areas as a_areas', 'p_dto_trmtes.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_lgin as p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')
            ->join($s . 'p_usrios as p_usrios', 'p_lgin.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'p_usrios as p_usrios_1', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->where('r_trmtes_dcmntos.in_esta', '1')
            ->whereIn('p_dto_trmtes.cdgo_tpo_trmte', $tiposLista)
            ->where('p_dto_trmtes.cdgo_cmpnia', $cmpnia);

        $modo = (string) $request->query('modo', 'generico');
        $criterio = trim((string) $request->query('criterio', ''));
        $campo = (string) $request->query('campo', 'p_dto_trmtes.nu_expe_todo');
        $fecha = trim((string) $request->query('fecha', ''));
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));

        if ($modo === 'fechas' && $desde !== '' && $hasta !== '') {
            try {
                $f1 = Carbon::createFromFormat('Y-m-d', $desde)->startOfDay();
                $f2 = Carbon::createFromFormat('Y-m-d', $hasta)->endOfDay();
                $query->whereBetween('p_dto_trmtes.fe_ingr_trmte', [$f1, $f2]);
            } catch (\Exception $e) {
                // fecha inválida: ignorar filtro
            }
        } else {
            if ($criterio !== '' && in_array($campo, $allowed, true)) {
                if ($campo === 'p_lgin.de_lgin') {
                    $query->whereRaw("LOWER({$campo}) LIKE LOWER(?)", ['%' . $criterio . '%']);
                } else {
                    $query->whereRaw("UPPER({$campo}::text) LIKE UPPER(?)", ['%' . $criterio . '%']);
                }
            }
            if ($fecha !== '') {
                try {
                    $f = Carbon::createFromFormat('Y-m-d', $fecha);
                    $query->whereBetween('p_dto_trmtes.fe_ingr_trmte', [
                        $f->copy()->startOfDay(),
                        $f->copy()->endOfDay(),
                    ]);
                } catch (\Exception $e) {
                    // ignore
                }
            }
            if ($criterio === '' && $fecha === '') {
                $query->where('p_dto_trmtes.fe_ingr_trmte', '>=', now()->subDays(60)->startOfDay());
            }
        }

        $total = (clone $query)->select('p_dto_trmtes.cdgo_dto_trmte')->distinct()->count('p_dto_trmtes.cdgo_dto_trmte');
        $paginas = max(1, (int) ceil($total / $tamPag));
        $pagina = min($pagina, $paginas);

        $rows = $query
            ->select([
                'p_dto_trmtes.cdgo_dto_trmte',
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.nu_foli_trmte',
                'p_dto_trmtes.in_sile_admi',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_dto_trmtes.de_obse_todo',
                'a_areas.dc_area',
                'a_areas.de_area',
                'a_estdos_trmtes.dc_estdo_trmte',
                'a_estdos_trmtes.cdgo_estdo_trmte',
                'p_usrios.no_crto as usuario_ingreso',
                'p_lgin.de_lgin',
                'p_usrios_1.no_crto as solicitante',
            ])
            ->orderByDesc('p_dto_trmtes.cdgo_dto_trmte')
            ->offset(($pagina - 1) * $tamPag)
            ->limit($tamPag)
            ->get()
            ->map(function ($row) {
                $row->nu_expe_fmt = $this->formatExpediente((string) $row->nu_expe_todo);
                $row->fecha_dm = $row->fe_ingr_trmte
                    ? Carbon::parse($row->fe_ingr_trmte)->format('d/m')
                    : '';
                $row->silencio = ((string) $row->in_sile_admi === '1') ? 'Sí' : 'No';
                return $row;
            });

        return response()->json([
            'success' => true,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'items' => $rows,
        ]);
    }

    public function contarFolios(Request $request)
    {
        $folios = 0;
        $file = $request->file('fle_adjunto');
        if ($file && $file->isValid() && strtolower($file->getClientOriginalExtension()) === 'pdf') {
            $folios = $this->contarPaginasPdf($file->getRealPath());
        }

        return response()->json(['success' => true, 'folios' => $folios]);
    }

    public function voucher($id)
    {
        $expediente = \App\Models\DtoTramite::with(['usrio.natural', 'usrio.juridico', 'area', 'documentoTupa.servicio'])
            ->findOrFail($id);

        $attendant = User::find($expediente->co_usua_ingr);

        return view('mod_gm.mod_tram_exte.voucher', compact('expediente', 'attendant'));
    }

    private function formatExpediente(string $nu): string
    {
        $parts = explode('-', $nu);
        if (count($parts) < 3) {
            return $nu;
        }

        $mid = ($parts[1] ?? '') . '-' . substr((string) ($parts[2] ?? ''), 2, 10);

        return ($parts[0] ?? '') . '-' . str_pad($mid, 9, '0', STR_PAD_LEFT);
    }

    private function contarPaginasPdf(string $path): int
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return 0;
        }

        if (preg_match_all('/\/Type\s*\/Page(?!s)/', $content, $matches)) {
            return count($matches[0]);
        }

        return 0;
    }
}
