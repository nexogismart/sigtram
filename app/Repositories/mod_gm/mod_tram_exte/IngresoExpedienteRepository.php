<?php

declare(strict_types=1);

namespace App\Repositories\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;


use App\Models\Anio;
use App\Models\Cargo;
use App\Models\CargoTramite;
use App\Models\Compania;
use App\Models\DocumentoTramiteTupa;
use App\Models\DtoCargoTramite;
use App\Models\DtoRequisito;
use App\Models\DtoTramite;
use App\Models\PUsrrio;
use App\Models\RequisitoTramite;
use App\Models\SeguimientoLog;
use App\Models\TramiteDocumento;
use App\Models\User;
use App\Models\UsrioJuridico;
use App\Models\UsrioNatural;
use App\Support\PublicTrackingToken;
use Carbon\Carbon;
use chillerlan\QRCode\QRCode;
use Illuminate\Database\DatabaseManager;

class IngresoExpedienteRepository implements IngresoExpedienteRepositoryInterface
{
    private string $schema;
    private CorrelativoExpedienteLock $correlativoLock;

    public function __construct(
        private readonly DatabaseManager $db,
        ?CorrelativoExpedienteLock $correlativoLock = null
    ) {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $this->correlativoLock = $correlativoLock ?? new CorrelativoExpedienteLock($this->db);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDatosFormularioIngreso(int $companiaId): array
    {
        $gerencias = $this->db->table($this->schema . 'a_areas')
            ->whereNull('cdgo_area_prim')
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $companiaId)
            ->where('cdgo_area', '!=', 0)
            ->orderBy('de_area', 'asc')
            ->get(['cdgo_area', 'de_area', 'dc_area']);

        $servicios = $this->db->table($this->schema . 'a_srvcio_areas')
            ->where('in_esta', '1')
            ->orderBy('de_srvcio', 'asc')
            ->get(['cdgo_srvcio_area', 'de_srvcio']);

        $tiposDocumento = $this->db->table($this->schema . 'a_dcmntos_iden')
            ->where('in_esta', '1')
            ->orderBy('nu_orde', 'asc')
            ->get(['cdgo_dcmntos_iden', 'dc_dcmntos_iden']);

        $tiposIngreso = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('in_esta', '1')
            ->whereIn('dc_tpo_trmte', ['E', 'S'])
            ->orderBy('nu_orde', 'asc')
            ->get(['cdgo_tpo_trmte', 'de_tpo_trmte', 'dc_tpo_trmte']);

        $tipoExterno = $tiposIngreso->firstWhere('dc_tpo_trmte', 'E');
        $tipoSimple = $tiposIngreso->firstWhere('dc_tpo_trmte', 'S');

        $tipoObservado = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('in_esta', '1')
            ->where('dc_tpo_trmte', 'O')
            ->first(['cdgo_tpo_trmte', 'de_tpo_trmte', 'dc_tpo_trmte']);

        $areaObservado = $this->db->table($this->schema . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $companiaId)
            ->where(function ($q) {
                $q->whereRaw("UPPER(de_area) LIKE '%GESTION DOCUMENTARIA%'")
                    ->orWhereRaw("UPPER(de_area) LIKE '%GESTIÓN DOCUMENTARIA%'");
            })
            ->orderBy('cdgo_area', 'asc')
            ->first(['cdgo_area', 'de_area']);

        return [
            'gerencias' => $gerencias,
            'servicios' => $servicios,
            'tiposDocumento' => $tiposDocumento,
            'tiposIngreso' => $tiposIngreso,
            'tipoExterno' => $tipoExterno,
            'tipoSimple' => $tipoSimple,
            'tipoObservado' => $tipoObservado,
            'areaObservado' => $areaObservado,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buscarUsuarioNatural(string $documento, int $companiaId): ?array
    {
        $usr = PUsrrio::where('nu_docu', $documento)
            ->where('in_tipo_pers', '0')
            ->with('natural')
            ->first();

        if (!$usr) {
            return null;
        }

        return [
            'id' => (int) $usr->cdgo_usrios,
            'nombres' => (string) ($usr->natural->no_usrio ?? $usr->no_crto_sstma ?? ''),
            'ape_paterno' => (string) ($usr->natural->ap_pate ?? ''),
            'ape_materno' => (string) ($usr->natural->ap_mate ?? ''),
            'telefono' => (string) ($usr->nu_tele ?? ''),
            'correo' => (string) ($usr->de_mail ?? ''),
            'direccion' => (string) ($usr->de_dire ?? ''),
            'tipo_doc' => (string) ($usr->natural->cdgo_dcmntos_iden ?? ''),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buscarUsuarioJuridico(string $ruc, int $companiaId): ?array
    {
        $usr = PUsrrio::where('nu_ruc', $ruc)
            ->where('in_tipo_pers', '1')
            ->with('juridico')
            ->first();

        if (!$usr) {
            return null;
        }

        return [
            'id' => (int) $usr->cdgo_usrios,
            'razon_social' => (string) ($usr->juridico->rz_soci ?? $usr->no_crto ?? ''),
            'ruc' => (string) ($usr->nu_ruc ?? ''),
            'tel_jur' => (string) ($usr->nu_tele ?? ''),
            'cor_jur' => (string) ($usr->de_mail ?? ''),
            'dir_jur' => (string) ($usr->de_dire ?? ''),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buscarUsuarioJuridicoPorRazon(string $razon, int $companiaId): ?array
    {
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
            return null;
        }

        $usr = PUsrrio::find($row->cdgo_usrios);
        if (!$usr) {
            return null;
        }

        return [
            'id' => (int) $usr->cdgo_usrios,
            'razon_social' => (string) ($row->rz_soci ?: $usr->no_crto),
            'ruc' => (string) ($usr->nu_ruc ?? ''),
            'tel_jur' => (string) ($usr->nu_tele ?? ''),
            'cor_jur' => (string) ($usr->de_mail ?? ''),
            'dir_jur' => (string) ($usr->de_dire ?? ''),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function sugerirUsuariosJuridicos(string $termino, int $companiaId, int $limite = 20): array
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtoupper($termino, 'UTF-8'));

        $items = UsrioJuridico::query()
            ->whereRaw('UPPER(rz_soci) LIKE ?', ['%' . $escaped . '%'])
            ->orderBy('rz_soci')
            ->limit($limite)
            ->get(['cdgo_usrios', 'rz_soci']);

        return $items->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function listarSubgerencias(int $gerenciaId, int $companiaId): array
    {
        $subgerencias = $this->db->table($this->schema . 'a_areas')
            ->where('cdgo_area_prim', $gerenciaId)
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $companiaId)
            ->orderBy('de_area', 'asc')
            ->get(['cdgo_area as id', 'de_area as nombre']);

        return $subgerencias->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function listarTramitesPorServicio(int $servicioId, int $companiaId): array
    {
        $tramites = $this->db->table($this->schema . 'a_docmntos_trmtes as d')
            ->join($this->schema . 'a_tupa as tupa', 'd.cdgo_tupa', '=', 'tupa.cdgo_tupa')
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

        return $tramites->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function listarRequisitosPorTramite(int $tramiteId, int $companiaId): array
    {
        $requisitos = $this->db->table($this->schema . 'h_rqtos_trmtes')
            ->where('cdgo_docmnto_trmte', $tramiteId)
            ->where('in_esta', '1')
            ->orderBy('nu_orde', 'asc')
            ->get([
                'cdgo_rqto_trmte as id',
                'de_rqto_trmte as descripcion',
                'in_ctgria',
                'prcio_trmte',
                'nu_orde',
            ]);

        $silencio = $this->db->table($this->schema . 'a_docmntos_trmtes')
            ->where('cdgo_docmnto_trmte', $tramiteId)
            ->first(['cdgo_docmnto_trmte', 'in_sile_admi', 'nu_tiem_sile']);

        return [
            'requisitos' => $requisitos,
            'silencio' => $silencio,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buscarTramitesTupa(string $termino, int $companiaId, int $limite = 20): array
    {
        $escapedTerm = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $termino);

        $tramites = $this->db->table($this->schema . 'a_docmntos_trmtes as d')
            ->join($this->schema . 'a_tupa as tupa', 'd.cdgo_tupa', '=', 'tupa.cdgo_tupa')
            ->whereRaw('LOWER(d.de_docmnto_trmte) LIKE ?', ['%' . mb_strtolower($escapedTerm, 'UTF-8') . '%'])
            ->where('d.in_esta', '1')
            ->where('tupa.in_esta', '1')
            ->orderBy('d.de_docmnto_trmte', 'asc')
            ->limit($limite)
            ->get([
                'd.cdgo_docmnto_trmte as id',
                'd.de_docmnto_trmte as nombre',
                'd.in_sile_admi',
                'd.nu_tiem_sile',
            ]);

        return $tramites->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function listarExpedientes(array $filtros, int $companiaId, int $pagina, int $porPagina): array
    {
        $allowed = [
            'p_dto_trmtes.nu_expe_todo',
            'p_usrios_1.nu_docu',
            'p_usrios_1.no_crto',
            'a_areas.de_area',
            'a_estdos_trmtes.dc_estdo_trmte',
            'p_lgin.de_lgin',
            'p_dto_trmtes.de_obse_todo',
        ];

        $query = $this->db->table($this->schema . 'p_dto_trmtes as p_dto_trmtes')
            ->join($this->schema . 'r_trmtes_dcmntos as r_trmtes_dcmntos', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->leftJoin($this->schema . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($this->schema . 'a_areas as a_areas', 'p_dto_trmtes.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($this->schema . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($this->schema . 'p_lgin as p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')
            ->join($this->schema . 'p_usrios as p_usrios', 'p_lgin.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($this->schema . 'p_usrios as p_usrios_1', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->where('r_trmtes_dcmntos.in_esta', '1')
            ->where('p_dto_trmtes.cdgo_cmpnia', $companiaId);

        $modo = (string) ($filtros['modo'] ?? 'generico');
        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $campo = (string) ($filtros['campo'] ?? 'p_dto_trmtes.nu_expe_todo');
        $fecha = trim((string) ($filtros['fecha'] ?? ''));
        $desde = trim((string) ($filtros['desde'] ?? ''));
        $hasta = trim((string) ($filtros['hasta'] ?? ''));

        if ($modo === 'fechas' && $desde !== '' && $hasta !== '') {
            try {
                $f1 = Carbon::createFromFormat('Y-m-d', $desde)->startOfDay();
                $f2 = Carbon::createFromFormat('Y-m-d', $hasta)->endOfDay();
                $query->whereBetween('p_dto_trmtes.fe_ingr_trmte', [$f1, $f2]);
            } catch (\Throwable) {
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
                } catch (\Throwable) {
                    // ignore
                }
            }
            if ($criterio === '' && $fecha === '') {
                $query->where('p_dto_trmtes.fe_ingr_trmte', '>=', now()->subDays(60)->startOfDay());
            }
        }

        $total = (clone $query)->select('p_dto_trmtes.cdgo_dto_trmte')->distinct()->count('p_dto_trmtes.cdgo_dto_trmte');
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $paginas);

        $rows = $query
            ->select([
                'p_dto_trmtes.cdgo_dto_trmte',
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.cdgo_tpo_trmte',
                'p_dto_trmtes.in_docu_trmte',
                'a_tpo_trmtes.dc_tpo_trmte',
                'a_tpo_trmtes.de_tpo_trmte',
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
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get();

        $items = $rows->map(function ($row) {
            $dc = trim((string) ($row->dc_tpo_trmte ?? ''));
            $inDoc = trim((string) ($row->in_docu_trmte ?? ''));
            $origDoc = $dc !== '' ? $dc : ($inDoc !== '' ? $inDoc : 'SIN TIPO');
            $row->orig_doc = $origDoc;
            $row->de_tpo_trmte = trim((string) ($row->de_tpo_trmte ?? '')) ?: $origDoc;

            return $row;
        });

        return [
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'items' => $items,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerVoucherExpediente(int $expedienteId, int $companiaId): ?array
    {
        if ($companiaId <= 0) {
            return null;
        }

        $expediente = DtoTramite::with([
            'usrio.natural',
            'usrio.juridico',
            'area',
            'documentoTupa.servicio',
            'compania',
        ])
            ->where('cdgo_dto_trmte', $expedienteId)
            ->where('cdgo_cmpnia', $companiaId)
            ->first();

        if (!$expediente) {
            return null;
        }

        $attendant = User::find($expediente->co_usua_ingr);

        $compania = $expediente->compania ?: $this->db->table($this->schema . 'a_cmpnias')
            ->where('cdgo_cmpnia', $expediente->cdgo_cmpnia)
            ->first();

        if (!$compania) {
            \Illuminate\Support\Facades\Log::warning("obtenerVoucherExpediente: expediente {$expedienteId} carece de compañía válida.");
            return null;
        }

        $cdgoTpo = (int) ($expediente->cdgo_tpo_trmte ?? 0);
        if ($cdgoTpo <= 0) {
            \Illuminate\Support\Facades\Log::warning("obtenerVoucherExpediente: expediente {$expedienteId} carece de cdgo_tpo_trmte positivo.");
            return null;
        }

        $tipoCatalogo = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $cdgoTpo)
            ->first(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte']);

        if (!$tipoCatalogo) {
            \Illuminate\Support\Facades\Log::warning("obtenerVoucherExpediente: tipo de trámite {$cdgoTpo} no existe en catálogo a_tpo_trmtes para expediente {$expedienteId}.");
            return null;
        }

        $dcTpo = trim((string) ($tipoCatalogo->dc_tpo_trmte ?? ''));
        $deTpo = trim((string) ($tipoCatalogo->de_tpo_trmte ?? ''));

        if ($dcTpo === '') {
            \Illuminate\Support\Facades\Log::warning("obtenerVoucherExpediente: tipo de trámite {$cdgoTpo} tiene dc_tpo_trmte vacío para expediente {$expedienteId}.");
            return null;
        }

        $expediente->dc_tpo_trmte = $dcTpo;
        $expediente->de_tpo_trmte = $deTpo !== '' ? $deTpo : null;
        $expediente->in_docu_trmte = !empty($expediente->in_docu_trmte) ? (string) $expediente->in_docu_trmte : null;

        $token = PublicTrackingToken::generate($expedienteId);
        $publicTrackingUrl = route('tramites.seguimiento_publico', ['token' => $token]);
        $qrCode = (new QRCode())->render($publicTrackingUrl);

        return [
            'expediente' => $expediente,
            'attendant' => $attendant,
            'compania' => $compania,
            'publicTrackingUrl' => $publicTrackingUrl,
            'qrCode' => $qrCode,
            'dc_tpo_trmte' => $expediente->dc_tpo_trmte,
            'de_tpo_trmte' => $expediente->de_tpo_trmte,
            'in_docu_trmte' => $expediente->in_docu_trmte,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function resolverSolicitante(array $datos, int $loginId, int $companiaId): int
    {
        if (($datos['tipo_persona'] ?? '') === 'natural') {
            $nombres = $this->sanitize($datos['nombres'] ?? '');
            $apePaterno = $this->sanitize($datos['ape_paterno'] ?? '');
            $apeMaterno = $this->sanitize($datos['ape_materno'] ?? '');
            $nombresComp = trim($nombres . ' ' . $apePaterno . ' ' . $apeMaterno);
            $direccion = $this->sanitize($datos['direccion'] ?? '');
            $telefono = (string) preg_replace('/[^0-9]/', '', (string) ($datos['telefono'] ?? ''));
            $correo = trim(strtolower((string) ($datos['correo'] ?? '')));
            $tipoDoc = (int) ($datos['tipo_doc'] ?? 0);
            $numDoc = (string) ($datos['num_doc'] ?? '');

            if ($tipoDoc === 5) {
                $usr = PUsrrio::create([
                    'no_crto' => $nombresComp,
                    'no_crto_sstma' => $nombresComp,
                    'de_dire' => mb_strtoupper($direccion, 'UTF-8'),
                    'nu_tele' => $telefono,
                    'co_ubig' => '150101',
                    'de_mail' => $correo,
                    'nu_docu' => null,
                    'de_ocup_rubr' => '',
                    'in_tipo_pers' => '0',
                    'nu_ruc' => '',
                ]);

                UsrioNatural::create([
                    'cdgo_usrios' => $usr->cdgo_usrios,
                    'cdgo_estdo_cvil' => 1,
                    'cdgo_dcmntos_iden' => $tipoDoc,
                    'ap_pate' => mb_strtoupper($apePaterno, 'UTF-8'),
                    'ap_mate' => mb_strtoupper($apeMaterno, 'UTF-8'),
                    'no_usrio' => mb_strtoupper($nombres, 'UTF-8'),
                    'nu_edad' => 0,
                    'in_sexo' => '1',
                    'co_usua_modi' => $loginId,
                    'ts_usua_modi' => Carbon::now(),
                ]);

                return (int) $usr->cdgo_usrios;
            }

            $usr = PUsrrio::where('nu_docu', $numDoc)
                ->where('in_tipo_pers', '0')
                ->first();

            if ($usr) {
                $usr->update([
                    'no_crto' => $nombresComp,
                    'no_crto_sstma' => $nombresComp,
                    'de_dire' => mb_strtoupper($direccion, 'UTF-8'),
                    'nu_tele' => $telefono,
                    'de_mail' => $correo,
                ]);

                UsrioNatural::where('cdgo_usrios', $usr->cdgo_usrios)->update([
                    'cdgo_dcmntos_iden' => $tipoDoc,
                    'ap_pate' => mb_strtoupper($apePaterno, 'UTF-8'),
                    'ap_mate' => mb_strtoupper($apeMaterno, 'UTF-8'),
                    'no_usrio' => mb_strtoupper($nombres, 'UTF-8'),
                    'co_usua_modi' => $loginId,
                    'ts_usua_modi' => Carbon::now(),
                ]);
            } else {
                $usr = PUsrrio::create([
                    'no_crto' => $nombresComp,
                    'no_crto_sstma' => $nombresComp,
                    'de_dire' => mb_strtoupper($direccion, 'UTF-8'),
                    'nu_tele' => $telefono,
                    'co_ubig' => '150101',
                    'de_mail' => $correo,
                    'nu_docu' => $numDoc,
                    'de_ocup_rubr' => '',
                    'in_tipo_pers' => '0',
                    'nu_ruc' => '',
                ]);

                UsrioNatural::create([
                    'cdgo_usrios' => $usr->cdgo_usrios,
                    'cdgo_estdo_cvil' => 1,
                    'cdgo_dcmntos_iden' => $tipoDoc,
                    'ap_pate' => mb_strtoupper($apePaterno, 'UTF-8'),
                    'ap_mate' => mb_strtoupper($apeMaterno, 'UTF-8'),
                    'no_usrio' => mb_strtoupper($nombres, 'UTF-8'),
                    'nu_edad' => 0,
                    'in_sexo' => '1',
                    'co_usua_modi' => $loginId,
                    'ts_usua_modi' => Carbon::now(),
                ]);
            }

            return (int) $usr->cdgo_usrios;
        }

        $razonSocial = $this->sanitize($datos['razon_social'] ?? '');
        $dirJur = $this->sanitize($datos['dir_jur'] ?? '');
        $telJur = (string) preg_replace('/[^0-9]/', '', (string) ($datos['tel_jur'] ?? ''));
        $corJur = trim(strtolower((string) ($datos['cor_jur'] ?? '')));
        $ruc = (string) ($datos['ruc'] ?? '');

        $usr = PUsrrio::where('nu_ruc', $ruc)
            ->where('in_tipo_pers', '1')
            ->first();

        if ($usr) {
            $usr->update([
                'no_crto' => $razonSocial,
                'no_crto_sstma' => $razonSocial,
                'de_dire' => mb_strtoupper($dirJur, 'UTF-8'),
                'nu_tele' => $telJur,
                'de_mail' => $corJur,
            ]);

            UsrioJuridico::where('cdgo_usrios', $usr->cdgo_usrios)->update([
                'rz_soci' => mb_strtoupper($razonSocial, 'UTF-8'),
                'co_usua_modi' => $loginId,
                'ts_usua_modi' => Carbon::now(),
            ]);
        } else {
            $usr = PUsrrio::create([
                'no_crto' => $razonSocial,
                'no_crto_sstma' => $razonSocial,
                'de_dire' => mb_strtoupper($dirJur, 'UTF-8'),
                'nu_tele' => $telJur,
                'co_ubig' => '150101',
                'de_mail' => $corJur,
                'nu_docu' => null,
                'de_ocup_rubr' => '',
                'in_tipo_pers' => '1',
                'nu_ruc' => $ruc,
            ]);

            UsrioJuridico::create([
                'cdgo_usrios' => $usr->cdgo_usrios,
                'rz_soci' => mb_strtoupper($razonSocial, 'UTF-8'),
                'co_usua_modi' => $loginId,
                'ts_usua_modi' => Carbon::now(),
                'in_esta' => '1',
            ]);
        }

        return (int) $usr->cdgo_usrios;
    }

    /**
     * {@inheritdoc}
     */
    /**
     * Retorna la clave determinística para serialización PostgreSQL.
     */
    public function obtenerClaveSerializacion(int $currYear, int $tpoTrmtes = 0): int
    {
        return $this->correlativoLock->obtenerClaveSerializacion($currYear, $tpoTrmtes);
    }

    /**
     * Retorna los parámetros vinculados para el advisory lock [cmpnia, clave].
     *
     * @return array{0: int, 1: int}
     */
    public function obtenerParametrosLock(int $cmpnia, int $currYear, int $tpoTrmtes = 0): array
    {
        return $this->correlativoLock->obtenerParametrosLock($cmpnia, $currYear, $tpoTrmtes);
    }

    /**
     * Aplica advisory lock transaccional determinístico si la conexión es PostgreSQL.
     */
    public function serializarCorrelativoPostgres(int $cmpnia, int $currYear, int $tpoTrmtes = 0): void
    {
        $this->correlativoLock->serializarCorrelativoPostgres($cmpnia, $currYear, $tpoTrmtes);
    }

    public function generarNumeroExpediente(array $datos, int $companiaId): array
    {
        // 1. Validar que no se intente enviar un tipo no permitido por manipulación del request
        if (!empty($datos['cdgo_tpo_trmte'])) {
            $tipoEnviado = $this->db->table($this->schema . 'a_tpo_trmtes')
                ->where('cdgo_tpo_trmte', (int) $datos['cdgo_tpo_trmte'])
                ->first(['dc_tpo_trmte', 'de_tpo_trmte']);
            if ($tipoEnviado && !in_array(trim((string) $tipoEnviado->dc_tpo_trmte), ['E', 'S', 'O'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cdgo_tpo_trmte' => "El tipo de trámite '{$tipoEnviado->de_tpo_trmte}' ({$tipoEnviado->dc_tpo_trmte}) no está permitido para creación desde Ingresar Expedientes."
                ]);
            }
        }

        if (!empty($datos['in_docu_trmte'])) {
            $letraEnviada = strtoupper(trim((string) $datos['in_docu_trmte']));
            if (!in_array($letraEnviada, ['E', 'S', 'O'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'in_docu_trmte' => "El código de trámite '{$letraEnviada}' no está permitido para creación desde Ingresar Expedientes."
                ]);
            }
        }

        // 2. Determinar la letra requerida según las opciones del formulario legacy:
        //    - EXTERNO -> 'E'
        //    - DOCUMENTO SIMPLE -> 'S'
        //    - OBSERVADO = true -> 'O' (reemplaza cualquier selección previa)
        if (!empty($datos['observado'])) {
            $targetLetter = 'O';
        } else {
            $tipoTramite = strtolower(trim((string) ($datos['tipo_tramite'] ?? 'externo')));
            if ($tipoTramite === 'simple') {
                $targetLetter = 'S';
            } elseif ($tipoTramite === 'externo') {
                $targetLetter = 'E';
            } else {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tipo_tramite' => "El tipo de trámite '{$tipoTramite}' no está permitido para creación desde Ingresar Expedientes."
                ]);
            }
        }

        // 3. Resolver dinámicamente desde a_tpo_trmtes.dc_tpo_trmte SIN IDs numéricos hardcodeados
        $tipos = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('dc_tpo_trmte', $targetLetter)
            ->get(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta']);

        if ($tipos->isEmpty()) {
            throw new \RuntimeException("El tipo de trámite con código '{$targetLetter}' no existe en el catálogo a_tpo_trmtes.");
        }

        if ($tipos->count() > 1) {
            throw new \RuntimeException("El tipo de trámite con código '{$targetLetter}' está duplicado de forma ambigua en a_tpo_trmtes.");
        }

        $tipoRow = $tipos->first();
        if ((string) $tipoRow->in_esta !== '1') {
            throw new \RuntimeException("El tipo de trámite '{$tipoRow->de_tpo_trmte}' ({$targetLetter}) se encuentra inactivo y no permite nuevas creaciones.");
        }

        $code = (int) $tipoRow->cdgo_tpo_trmte;
        $letter = $targetLetter;

        $currYear = (int) date('Y');
        $correlativo = $this->correlativoLock->resolver($companiaId, $code, $currYear);

        $nuExpeTodo = "{$letter}-{$correlativo['nuExpe']}-{$correlativo['deAnio']}";

        return [
            'nu_expe_todo' => $nuExpeTodo,
            'nu_expe_trmte' => $correlativo['nuExpe'],
            'cdgo_anio' => $correlativo['idAnio'],
            'cdgo_tpo_trmte' => $code,
            'in_docu_trmte' => $letter,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerPrimerCargoUsuario(int $loginId): int
    {
        $cargo = $this->db->table('sistema.r_crgos_lgin')
            ->where('cdgo_lgin', $loginId)
            ->value('cdgo_crgo');

        return $cargo !== null ? (int) $cargo : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function resolverAreaObservado(): int
    {
        $areaId = $this->db->table($this->schema . 'a_areas')
            ->where('in_esta', '1')
            ->where(function ($q) {
                $q->whereRaw("UPPER(de_area) LIKE '%GESTION DOCUMENTARIA%'")
                    ->orWhereRaw("UPPER(de_area) LIKE '%GESTIÓN DOCUMENTARIA%'");
            })
            ->orderBy('cdgo_area', 'asc')
            ->value('cdgo_area');

        return $areaId !== null ? (int) $areaId : 18;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerPrimerCargoArea(int $areaId): ?int
    {
        $cargoId = Cargo::where('cdgo_area', $areaId)
            ->where('nu_orde', 1)
            ->where('in_esta', '1')
            ->orderBy('cdgo_crgo', 'asc')
            ->value('cdgo_crgo');

        return $cargoId !== null ? (int) $cargoId : null;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDatosTupa(int $tipoTramTupa): ?object
    {
        return DocumentoTramiteTupa::where('cdgo_docmnto_trmte', $tipoTramTupa)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function crearExpedienteCabecera(array $datos): int
    {
        $expediente = DtoTramite::create($datos);

        return (int) $expediente->cdgo_dto_trmte;
    }

    /**
     * {@inheritdoc}
     */
    public function crearMovimientoInicial(array $datos): int
    {
        $movimiento = TramiteDocumento::create($datos);

        return (int) $movimiento->cdgo_trmte_dcmnto;
    }

    /**
     * {@inheritdoc}
     */
    public function actualizarAdjuntoMovimiento(int $movimientoId, string $filename): void
    {
        TramiteDocumento::where('cdgo_trmte_dcmnto', $movimientoId)->update([
            'de_urls_imag' => $filename,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function asignarRutasTupa(int $expedienteId, int $tipoTramTupa, string $fechaIngreso): void
    {
        $rutas = CargoTramite::where('cdgo_docmnto_trmte', $tipoTramTupa)
            ->where('in_esta', '1')
            ->orderBy('nu_orde', 'asc')
            ->get();

        foreach ($rutas as $ruta) {
            DtoCargoTramite::create([
                'cdgo_dto_trmte' => $expedienteId,
                'cdgo_crgo_trmte' => $ruta->cdgo_crgo_trmte,
                'cdgo_crgo' => $ruta->cdgo_crgo,
                'fe_dto_crgo_incio' => (int) $ruta->nu_orde === 1 ? $fechaIngreso : null,
                'nu_orde' => $ruta->nu_orde,
                'in_esta' => (int) $ruta->nu_orde === 1 ? '1' : '0',
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registrarRequisitosTupa(int $expedienteId, int $tipoTramTupa, array $marcados): void
    {
        $requisitosTupa = RequisitoTramite::where('cdgo_docmnto_trmte', $tipoTramTupa)
            ->where('in_esta', '!=', '0')
            ->get();

        $marcadosInt = array_map('intval', $marcados);

        foreach ($requisitosTupa as $req) {
            $presentado = in_array((int) $req->cdgo_rqto_trmte, $marcadosInt, true) ? '1' : '0';
            DtoRequisito::create([
                'cdgo_dto_trmte' => $expedienteId,
                'cdgo_rqto_trmte' => $req->cdgo_rqto_trmte,
                'in_pres' => $presentado,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registrarSeguimiento(int $loginId, string $descripcion, string $ip): void
    {
        SeguimientoLog::create([
            'cdgo_lgin' => $loginId,
            'de_sgto_log' => $descripcion,
            'fe_sgto_log' => Carbon::now(),
            'de_ip' => $ip,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerSeguimientoPublico(int $expedienteId): ?array
    {
        if ($expedienteId <= 0) {
            return null;
        }

        $expediente = DtoTramite::with([
            'usrio.natural',
            'usrio.juridico',
            'area',
            'documentoTupa',
            'compania',
        ])
            ->where('cdgo_dto_trmte', $expedienteId)
            ->first();

        if (!$expediente) {
            return null;
        }

        $companiaNombre = trim((string) ($expediente->compania?->de_cmpnia ?? ''));
        if ($companiaNombre === '') {
            \Illuminate\Support\Facades\Log::warning("obtenerSeguimientoPublico: expediente {$expedienteId} carece de compañía válida.");
            return null;
        }

        $cdgoTpo = (int) ($expediente->cdgo_tpo_trmte ?? 0);
        if ($cdgoTpo <= 0) {
            \Illuminate\Support\Facades\Log::warning("obtenerSeguimientoPublico: expediente {$expedienteId} carece de cdgo_tpo_trmte positivo.");
            return null;
        }

        $tipoCatalogo = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $cdgoTpo)
            ->first(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte']);

        if (!$tipoCatalogo) {
            \Illuminate\Support\Facades\Log::warning("obtenerSeguimientoPublico: catálogo de tipo {$cdgoTpo} no encontrado para expediente {$expedienteId}.");
            return null;
        }

        $descTupa = trim((string) ($expediente->documentoTupa?->de_docmnto_trmte ?? ''));
        $deTpo = trim((string) ($tipoCatalogo->de_tpo_trmte ?? ''));
        $dcTpo = trim((string) ($tipoCatalogo->dc_tpo_trmte ?? ''));

        if ($descTupa !== '') {
            $tipoTramite = $descTupa;
        } elseif ($deTpo !== '') {
            $tipoTramite = $deTpo;
        } elseif ($dcTpo !== '') {
            $tipoTramite = $dcTpo;
        } else {
            return null;
        }

        $solicitanteNombre = trim((string) ($expediente->usrio?->no_crto ?? ''));
        if ($solicitanteNombre === '') {
            $solicitanteNombre = 'Solicitante no disponible';
        }

        $areaActual = trim((string) ($expediente->area?->de_area ?? 'Mesa de Partes'));

        // Consultar movimientos históricos públicos (sin exponer IDs sensibles ni adjuntos privados)
        // Incluye todos los movimientos históricos: tanto in_esta = 0 como in_esta = 1
        $movimientosRows = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->leftJoin($this->schema . 'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('r.cdgo_dto_trmte', $expedienteId)
            ->orderBy('r.cdgo_trmte_dcmnto', 'asc')
            ->select([
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'a.de_area as area_destino',
                'e.dc_estdo_trmte as estado_nombre',
            ])
            ->get();

        $movimientos = [];
        foreach ($movimientosRows as $mov) {
            $movimientos[] = [
                'fecha' => $mov->fe_rcbdo_trmte ? Carbon::parse($mov->fe_rcbdo_trmte)->format('d/m/Y H:i') : '-',
                'oficina' => (string) ($mov->area_destino ?: $areaActual),
                'estado' => (string) ($mov->estado_nombre ?: 'En Proceso'),
            ];
        }

        $estadoActual = count($movimientos) > 0 ? end($movimientos)['estado'] : 'Registrado';

        return [
            'expediente_id' => $expedienteId,
            'numero_expediente' => (string) $expediente->nu_expe_todo,
            'fecha_ingreso' => $expediente->fe_ingr_trmte ? Carbon::parse($expediente->fe_ingr_trmte)->format('d/m/Y H:i') : '-',
            'tipo_tramite' => (string) $tipoTramite,
            'solicitante' => (string) $solicitanteNombre,
            'asunto' => (string) ($expediente->de_obse_trmte ?: 'Trámite ingresado por Mesa de Partes'),
            'area_actual' => (string) $areaActual,
            'estado_actual' => (string) $estadoActual,
            'compania_nombre' => (string) $companiaNombre,
            'movimientos' => $movimientos,
        ];
    }

    /**
     * Sanitiza cadenas de texto previniendo XSS, tags HTML y espacios múltiples.
     */
    private function sanitize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $val = strip_tags((string) $value);
        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        $val = (string) preg_replace('/\s+/', ' ', $val);

        return trim($val);
    }
}
