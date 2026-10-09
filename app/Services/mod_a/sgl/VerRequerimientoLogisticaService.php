<?php

namespace App\Services\mod_a\sgl;

use App\Services\mod_gene\IngresarTramiteService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

/**
 * Pago a proveedores — Logística
 * (migrado desde ver_requerimiento_logistica.php + lista_pago_proveedores_logistica.php).
 */
class VerRequerimientoLogisticaService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private const AREA_FINANZAS = 99;

    private const AREA_PRESUPUESTO = 97;

    private const AREA_INFORME = 22;

    private const CAMPOS = [
        'nu_tram_todo' => 'p_dtos_ntrnos.nu_tram_todo',
        'de_area' => 'a_areas.de_area',
        'de_pgo_prvdres' => 'p_pgo_prvdres.de_pgo_prvdres',
    ];

    private readonly string $schemaSistema;

    public function __construct(
        private readonly IngresarTramiteService $ingresarTramiteService
    ) {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function areaUsuario(): int
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return 0;
        }

        return (int) (DB::table($this->schemaSistema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('cdgo_area') ?? 0);
    }

    public function catalogos(): array
    {
        return [
            'tiposFinanzas' => $this->tiposDocumento([44, 7]),
            'tiposInforme' => $this->tiposDocumento([8]),
            'tiposDoc' => $this->tiposDocumentoIdentidad(),
            'proveedores' => $this->sugerenciasProveedor(),
        ];
    }

    public function listar(array $filtros): array
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return $this->fail('No se pudo determinar el cargo.');
        }

        $campo = (string) ($filtros['campo'] ?? 'nu_tram_todo');
        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $etapa = (int) ($filtros['etapa'] ?? 0);
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = 30;
        $offset = ($pagina - 1) * $porPagina;
        $sistema = $this->schemaSistema;

        $from = '
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            LEFT JOIN ' . $sistema . 'p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
            LEFT JOIN ' . $sistema . 'a_tpo_trmtes ON p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte
            LEFT JOIN ' . $sistema . 'a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
            WHERE p_pgo_prvdres.in_esta NOT IN (0, 2, 4, 10, 12, 6, 7, 8)
              AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
              AND EXISTS (
                    SELECT 1 FROM ' . $sistema . 'r_trmtes_ntrnos r
                    WHERE r.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                      AND r.in_esta = \'1\'
                      AND r.cdgo_crgo_en = ?
              )
        ';
        $params = [$cargoId];

        if ($criterio !== '' && isset(self::CAMPOS[$campo])) {
            $from .= ' AND ' . self::CAMPOS[$campo] . ' ILIKE ?';
            $params[] = '%' . $criterio . '%';
        }
        if (in_array($etapa, [5, 9, 11], true)) {
            $from .= ' AND p_pgo_prvdres.in_esta = ?';
            $params[] = $etapa;
        }

        try {
            $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $from, $params)->total ?? 0);
            $sql = '
                SELECT p_pgo_prvdres.cdgo_pgo_prvdres,
                    p_pgo_prvdres.in_esta AS in_esta_prvdres,
                    p_pgo_prvdres.fe_pgo_prvdres,
                    p_dtos_ntrnos.nu_tram_todo,
                    a_tpo_trmtes.dc_tpo_trmte,
                    a_areas.dc_area,
                    a_areas.de_area
                ' . $from . '
                ORDER BY p_pgo_prvdres.in_esta DESC, p_pgo_prvdres.fe_pgo_prvdres DESC
                LIMIT ' . $porPagina . ' OFFSET ' . $offset;

            $rows = DB::select($sql, $params);
            $n = $offset;
            $items = [];
            foreach ($rows as $row) {
                $id = (int) $row->cdgo_pgo_prvdres;
                $estado = (int) ($row->in_esta_prvdres ?? 0);
                $sinAnexo = (int) (DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->where('in_esta', '<>', 0)
                    ->whereNull('de_urls_anxos')
                    ->count());

                if ($estado === 9 && $sinAnexo > 0) {
                    DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                        ->where('cdgo_pgo_prvdres', $id)
                        ->update(['in_esta' => 10]);
                    $estado = 10;
                }

                $n++;
                $tramite = trim((string) ($row->dc_tpo_trmte ?? ''));
                $numero = trim((string) ($row->nu_tram_todo ?? ''));
                $items[] = [
                    'n' => $n,
                    'id' => $id,
                    'titulo' => trim($tramite . ($numero !== '' ? ' N° ' . $numero : '')) ?: 'Sin trámite',
                    'area' => (string) ($row->dc_area ?? ''),
                    'area_nombre' => (string) ($row->de_area ?? ''),
                    'fecha' => $this->fechaCorta($row->fe_pgo_prvdres ?? null),
                    'estado' => $estado,
                    'icono' => $this->iconoLista($estado, $sinAnexo),
                    'titulo_icono' => $this->tituloLista($estado, $sinAnexo),
                ];
            }

            return $this->ok([
                'items' => $items,
                'total' => $total,
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'paginas' => max(1, (int) ceil($total / $porPagina)),
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_logistica] listar: ' . $e->getMessage());

            return $this->fail('No se pudo listar los requerimientos.');
        }
    }

    public function detalle(int $id): array
    {
        if ($id <= 0) {
            return $this->ok($this->detalleVacio());
        }

        try {
            $cab = $this->cabecera($id);
            if ($cab === null) {
                return $this->fail('No se encontró el requerimiento.');
            }

            $estado = (int) ($cab->in_esta_prvdres ?? 0);
            $items = $this->itemsDeRequerimiento($id, $estado);

            $sinUsuario = collect($items)->contains(fn ($it) => empty($it['usuario_id']));
            if (in_array($estado, [3, 4], true) && $sinUsuario) {
                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->update(['in_esta' => 11]);
                $estado = 11;
            }

            $confPend = $this->tieneConformidadParaFinanzas($id);
            $ccpOk = $items !== [] && collect($items)->every(
                fn ($it) => trim((string) ($it['ccp'] ?? '')) !== '' && ! empty($it['url_ccp'])
            );
            $habOk = $items !== [] && collect($items)->every(fn ($it) => (float) ($it['monto'] ?? 0) >= 0.01);
            $todosCcpNum = $items !== [] && collect($items)->every(fn ($it) => trim((string) ($it['ccp'] ?? '')) !== '');
            $todosOsNum = $items !== [] && collect($items)->every(fn ($it) => trim((string) ($it['os'] ?? '')) !== '');
            $faltaCcpFile = collect($items)->contains(fn ($it) => empty($it['url_ccp']));
            $faltaOsFile = collect($items)->contains(fn ($it) => empty($it['url_os']) || empty($it['usuario_id']));

            $documento = trim((string) ($cab->de_tpo_trmte ?? ''));
            $numero = trim((string) ($cab->nu_tram_todo ?? ''));
            $areaDoc = trim((string) ($cab->dc_area ?? ''));

            return $this->ok([
                'id' => (int) $cab->cdgo_pgo_prvdres,
                'asunto' => (string) ($cab->de_pgo_prvdres ?? ''),
                'area' => (string) ($cab->de_area ?? ''),
                'cdgo_area' => (int) ($cab->cdgo_area ?? 0),
                'estado' => $estado,
                'cdgo_dtos_ntrnos' => (int) ($cab->cdgo_dtos_ntrnos ?? 0),
                'documento' => $documento !== ''
                    ? trim($documento . ' N° ' . $numero . ($areaDoc !== '' ? '-' . $areaDoc : ''))
                    : '',
                'nu_pgo_mmos' => $this->ultimoPagoMemo($id, 5),
                'puede_enviar_finanzas' => $confPend && $estado === 5,
                'puede_devolver_finanzas' => $confPend && $estado === 5,
                'puede_devolver_area' => $estado === 9,
                'puede_enviar_presupuesto' => $ccpOk && $estado === 9,
                'puede_informe_tecnico' => $habOk && $estado === 13,
                'puede_publicar' => $estado === 10,
                'puede_subir_ccmn' => $estado === 9 && $todosCcpNum && $faltaCcpFile,
                'puede_subir_os' => in_array($estado, [11, 3], true) && $todosOsNum && $faltaOsFile,
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_logistica] detalle: ' . $e->getMessage());

            return $this->fail('No se pudo cargar el requerimiento.');
        }
    }

    public function itemDetalle(int $itemId): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $estado = (int) ($item->in_esta_prvdres ?? 0);
        $nombre = trim((string) ($item->no_crto ?? ''));
        $urlTdr = $this->urlOpcional($item->de_urls_pgo ?? $item->de_urls_tdr ?? null);
        $urlAne = $this->urlOpcional($item->de_urls_anxos ?? null);
        $urlOs = $this->urlOpcional($item->de_urls_os ?? null);

        $modo = 'ver';
        if ($estado === 9 && $urlAne) {
            $modo = 'habilitacion';
        } elseif ($estado === 13) {
            $modo = 'informe';
        } elseif ($urlTdr && $urlAne && ! $urlOs) {
            $modo = 'os';
        }

        $memos = $this->memosDeItem($itemId, $item);
        $tieneConf = collect($memos)->contains(fn ($m) => ! empty($m['url']));

        return $this->ok([
            'id' => (int) $item->cdgo_deta_pgo_prvdres,
            'pago_id' => (int) $item->cdgo_pgo_prvdres,
            'servicio' => (string) ($item->de_deta_pgo_prvdres ?? ''),
            'siga' => (string) ($item->nu_sga ?? ''),
            'url_siga' => $this->urlOpcional($item->de_urls_siga ?? null),
            'url_tdr' => $this->urlOpcional($item->de_urls_tdr ?? null),
            'url_pgo' => $urlTdr,
            'url_ane' => $urlAne,
            'url_os' => $urlOs,
            'url_ccp' => $this->urlOpcional($item->de_urls_ccp ?? null),
            'url_ccp_nta' => $this->urlOpcional($item->de_urls_ccp_nta ?? null),
            'os' => (string) ($item->nu_orden_srvco ?? ''),
            'fecha_os' => $this->fechaIso($item->fe_orden_srvco ?? null),
            'ccp' => (string) ($item->nu_ccp ?? ''),
            'ccp_nta' => (string) ($item->nu_ccp_nta ?? ''),
            'rubro' => (string) ($item->nu_rbro ?? ''),
            'siaf' => (string) ($item->nu_siaf ?? ''),
            'meta' => (string) ($item->nu_mta ?? ''),
            'esp_gasto' => (string) ($item->nu_espcfccon_gsto ?? ''),
            'nombre' => $nombre,
            'usuario_id' => (int) ($item->cdgo_usrios ?? 0),
            'ruc' => (string) ($item->nu_ruc ?? ''),
            'cci' => (string) ($item->nu_cci ?? ''),
            'monto' => $item->nu_mnto !== null ? (float) $item->nu_mnto : null,
            'pagos' => (int) ($item->nu_pgos_cntdad ?? 0),
            'dias_pago' => (string) ($item->no_das_pgos ?? ''),
            'in_esta_item' => (int) ($item->in_esta_pgo ?? 0),
            'estado' => $estado,
            'modo' => $modo,
            'memos' => $memos,
            'puede_anular_migra' => $nombre !== '' && ! $tieneConf,
            'readonly' => $nombre !== '' && $modo !== 'habilitacion',
        ]);
    }

    public function semilla(int $tipoId): array
    {
        $areaId = $this->areaUsuario();
        $cargoId = (int) session('sigtram_cargo_id', 0);
        $company = $this->compania();
        if ($tipoId <= 0 || $areaId <= 0) {
            return $this->fail('Tipo de documento no válido.');
        }

        $anioTxt = (string) date('Y');
        $anio = DB::table($this->schemaSistema . 'a_anios')
            ->where('de_anio', $anioTxt)
            ->when($company > 0, fn ($q) => $q->where('cdgo_cmpnia', $company))
            ->first() ?: DB::table($this->schemaSistema . 'a_anios')->orderByDesc('de_anio')->first();
        if (! $anio) {
            return $this->fail('Año de ejercicio no configurado.');
        }

        $tipo = DB::table($this->schemaSistema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $tipoId)->first();
        if (! $tipo) {
            return $this->fail('Tipo de documento no válido.');
        }

        $query = DB::table($this->schemaSistema . 'a_smlla_intrno')
            ->where('cdgo_tpo_trmte', $tipoId)
            ->where('cdgo_anio', $anio->cdgo_anio);
        if ((int) ($tipo->in_scncal ?? 0) === 1) {
            $query->where('cdgo_crgo', $cargoId);
        } else {
            $query->where('cdgo_area', $areaId);
        }

        $nu = ((int) $query->max('nu_smlla_intrno')) + 1;
        if ($nu < 1) {
            $nu = 1;
        }

        return $this->ok([
            'nu_smlla_intrno' => $nu . '-' . (string) ($anio->de_anio ?? $anioTxt),
            'requiere_firma' => (int) ($tipo->in_frma_dgtal ?? 0) === 1 && $this->cargoRequiereFirma(),
        ]);
    }

    public function publicar(int $id): array
    {
        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
            ->where('cdgo_pgo_prvdres', $id)
            ->update(['in_esta' => 3]);

        return $this->ok(['id' => $id], 'Se guardó y publicó correctamente.');
    }

    public function actualizarCampo(int $itemId, string $campo, string $valor): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $valor = trim($valor);
        $update = match ($campo) {
            'ccp' => ['nu_ccp' => $valor],
            'os' => ['nu_orden_srvco' => $valor],
            'siaf' => ['nu_siaf' => $valor, 'nu_ccp_nta' => $valor],
            default => null,
        };
        if ($update === null) {
            return $this->fail('Campo no permitido.');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update($update);

        return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres, 'valor' => $valor], 'Actualizado.');
    }

    public function actualizarMontoMemo(int $memoId, float $monto): array
    {
        $row = DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
            ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
            ->where('m.cdgo_deta_pgo_mmos', $memoId)
            ->first(['h.cdgo_pgo_prvdres']);
        if (! $row) {
            return $this->fail('No se encontró el pago.');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
            ->where('cdgo_deta_pgo_mmos', $memoId)
            ->update(['nu_mnto_sldo' => $monto]);

        return $this->ok(['id' => (int) $row->cdgo_pgo_prvdres], 'Monto actualizado.');
    }

    public function guardarHabilitacion(int $itemId, array $datos, ?UploadedFile $ccp): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $update = [
            'nu_mnto' => (float) str_replace(',', '.', (string) ($datos['monto'] ?? 0)),
            'nu_ccp' => trim((string) ($datos['ccp'] ?? '')),
            'nu_espcfccon_gsto' => trim((string) ($datos['esp_gasto'] ?? '')),
        ];
        if ($ccp instanceof UploadedFile) {
            if (! $this->esPdf($ccp)) {
                return $this->fail('El CCMN debe ser un PDF.');
            }
            $nuSga = trim((string) ($item->nu_sga ?? $itemId));
            $update['de_urls_ccp'] = $this->guardarPdf($ccp, $this->dirAdjuntos(), $nuSga . '-' . $itemId . '-CCMN.pdf');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update($update);

        return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres], 'Se generó el detalle de la habilitación presupuestal.');
    }

    public function guardarOs(int $itemId, array $datos, ?UploadedFile $os): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $userProv = (int) ($datos['usuario_id'] ?? 0);
        if ($userProv <= 0) {
            $nombre = mb_strtoupper(trim((string) ($datos['nombre'] ?? '')), 'UTF-8');
            if ($nombre !== '') {
                $userProv = (int) (DB::table($this->schemaSistema . 'p_usrios')
                    ->whereRaw('UPPER(TRIM(no_crto)) = ?', [$nombre])
                    ->value('cdgo_usrios') ?? 0);
            }
        }
        if ($userProv <= 0) {
            return $this->fail('No se encontró al proveedor ganador en la BD.');
        }

        $pagos = max(1, (int) ($item->nu_pgos_cntdad ?? 1));
        $monto = (float) str_replace(',', '.', (string) ($datos['monto'] ?? $item->nu_mnto ?? 0));
        $sldo = $pagos > 0 ? $monto / $pagos : $monto;
        $dias = explode('|', (string) ($item->no_das_pgos ?? ''));
        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);

        $adjun = (string) ($item->de_urls_os ?? '');
        if ($os instanceof UploadedFile) {
            if (! $this->esPdf($os)) {
                return $this->fail('La orden de servicio debe ser un PDF.');
            }
            $nuOs = trim((string) ($datos['os'] ?? $item->nu_orden_srvco ?? $itemId));
            $adjun = $this->guardarPdf($os, $this->dirAdjuntos(), $nuOs . '-' . $itemId . '-OS.pdf');
        }

        try {
            return DB::transaction(function () use ($item, $itemId, $datos, $userProv, $pagos, $sldo, $dias, $userId, $adjun, $monto) {
                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_prvdres', $itemId)
                    ->delete();

                for ($i = 1; $i <= $pagos; $i++) {
                    DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')->insert([
                        'cdgo_deta_pgo_prvdres' => $itemId,
                        'cdgo_dtos_ntrnos_cnfrmdad' => null,
                        'de_urls_cnfrmdad' => null,
                        'nu_mnto_sldo' => $sldo,
                        'nu_pgo_mmos' => $i,
                        'nu_dia_pgo' => (int) ($dias[$i - 1] ?? 0),
                        'ts_usua_modi' => now(),
                        'co_usua_modi' => $userId,
                        'in_esta' => 1,
                    ]);
                }

                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
                    ->where('cdgo_deta_pgo_prvdres', $itemId)
                    ->update([
                        'nu_cci' => trim((string) ($datos['cci'] ?? '')),
                        'nu_ruc' => trim((string) ($datos['ruc'] ?? '')),
                        'nu_orden_srvco' => trim((string) ($datos['os'] ?? '')),
                        'fe_orden_srvco' => $this->fechaSql($datos['fecha_os'] ?? null),
                        'de_urls_os' => $adjun !== '' ? $adjun : null,
                        'nu_siaf' => trim((string) ($datos['siaf'] ?? '')),
                        'nu_rbro' => trim((string) ($datos['rubro'] ?? '')),
                        'nu_mnto' => $monto,
                        'cdgo_usrios' => $userProv,
                    ]);

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', (int) $item->cdgo_pgo_prvdres)
                    ->update(['in_esta' => 3]);

                return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres], 'Se generó el cronograma de pagos.');
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_logistica] guardarOs: ' . $e->getMessage());

            return $this->fail('No se pudo guardar la orden de servicio.');
        }
    }

    public function anularMigra(int $itemId): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')->where('cdgo_deta_pgo_prvdres', $itemId)->delete();
        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update([
                'fe_orden_srvco' => null,
                'de_urls_os' => null,
                'nu_siaf' => null,
                'cdgo_usrios' => null,
            ]);

        return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres], 'Se anuló el proceso de pagos.');
    }

    public function buscarProveedor(string $nombre): array
    {
        $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
        if ($nombre === '') {
            return $this->ok(['id' => 0]);
        }

        $id = (int) (DB::table($this->schemaSistema . 'p_usrios')
            ->whereRaw('UPPER(TRIM(no_crto)) = ?', [$nombre])
            ->value('cdgo_usrios') ?? 0);

        return $this->ok(['id' => $id, 'nombre' => $nombre]);
    }

    public function guardarProveedor(array $datos): array
    {
        $tipo = (int) ($datos['tipo_doc'] ?? 1);
        $num = trim((string) ($datos['num_doc'] ?? ''));
        $apPate = mb_strtoupper(trim((string) ($datos['ap_pate'] ?? '')), 'UTF-8');
        $apMate = mb_strtoupper(trim((string) ($datos['ap_mate'] ?? '')), 'UTF-8');
        $nombres = mb_strtoupper(trim((string) ($datos['nombres'] ?? '')), 'UTF-8');
        $dire = mb_strtoupper(trim((string) ($datos['direccion'] ?? '')), 'UTF-8');
        $corto = trim($nombres . ' ' . $apPate . ' ' . $apMate);
        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);

        if ($corto === '') {
            return $this->fail('Ingrese los nombres del proveedor.');
        }

        try {
            return DB::transaction(function () use ($tipo, $num, $apPate, $apMate, $nombres, $dire, $corto, $userId) {
                $existente = null;
                if ($tipo !== 5 && $num !== '') {
                    $existente = DB::table($this->schemaSistema . 'p_usrios')
                        ->where('nu_docu', $num)
                        ->where('in_tipo_pers', '0')
                        ->first();
                }

                $payload = [
                    'no_crto' => $corto,
                    'no_crto_sstma' => $corto,
                    'de_dire' => $dire,
                    'nu_tele' => '',
                    'co_ubig' => '150101',
                    'de_mail' => '',
                    'nu_docu' => $tipo === 5 ? null : $num,
                    'de_ocup_rubr' => '',
                    'in_tipo_pers' => '0',
                    'nu_ruc' => '',
                    'in_esta' => '1',
                ];

                if ($existente) {
                    $id = (int) $existente->cdgo_usrios;
                    DB::table($this->schemaSistema . 'p_usrios')->where('cdgo_usrios', $id)->update($payload);
                    $msg = 'Se corrigió al proveedor correctamente.';
                } else {
                    $id = (int) DB::table($this->schemaSistema . 'p_usrios')->insertGetId($payload, 'cdgo_usrios');
                    $msg = 'Se agregó al nuevo proveedor correctamente.';
                }

                $ntral = [
                    'cdgo_usrios' => $id,
                    'cdgo_estdo_cvil' => 1,
                    'cdgo_dcmntos_iden' => $tipo > 0 ? $tipo : 1,
                    'ap_pate' => $apPate,
                    'ap_mate' => $apMate,
                    'no_usrio' => $nombres,
                    'nu_edad' => 0,
                    'in_sexo' => '1',
                    'co_usua_modi' => $userId,
                    'ts_usua_modi' => now(),
                ];
                if (DB::table($this->schemaSistema . 'h_usrios_ntral')->where('cdgo_usrios', $id)->exists()) {
                    DB::table($this->schemaSistema . 'h_usrios_ntral')->where('cdgo_usrios', $id)->update($ntral);
                } else {
                    DB::table($this->schemaSistema . 'h_usrios_ntral')->insert($ntral);
                }

                return $this->ok(['id' => $id, 'nombre' => $corto], $msg);
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_logistica] guardarProveedor: ' . $e->getMessage());

            return $this->fail('No se pudo guardar el proveedor.');
        }
    }

    public function subirZip(int $id, int $tipo, ?UploadedFile $zipFile): array
    {
        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if (! $zipFile instanceof UploadedFile) {
            return $this->fail('Debe adjuntar un archivo ZIP.');
        }

        $tmp = $zipFile->getRealPath();
        $zip = new ZipArchive();
        if ($zip->open((string) $tmp) !== true) {
            return $this->fail('No se pudo abrir el archivo ZIP.');
        }

        $items = DB::select(
            'SELECT h.cdgo_deta_pgo_prvdres, h.nu_ccp, h.nu_orden_srvco, h.nu_sga, h.nu_mnto,
                    h.nu_pgos_cntdad, h.no_das_pgos, h.cdgo_usrios
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             WHERE h.cdgo_pgo_prvdres = ? AND h.in_esta NOT IN (0)
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$id]
        );

        $noExiste = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);
            if (str_ends_with($nombre, '/')) {
                continue;
            }
            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $zip->close();

                return $this->fail('En el archivo hay documentos que no tienen la extensión PDF.');
            }
            $clave = $this->claveZip($nombre);
            $ok = false;
            foreach ($items as $it) {
                $ref = $tipo === 1 ? trim((string) ($it->nu_ccp ?? '')) : trim((string) ($it->nu_orden_srvco ?? ''));
                if ($clave !== '' && $clave === $ref) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                $noExiste[] = $nombre;
            }
        }
        if ($noExiste !== []) {
            $zip->close();

            return $this->fail('Los archivos ' . implode(', ', $noExiste) . ' no existen en la BD, por favor revise su archivo ZIP.');
        }

        $dir = $this->dirAdjuntos();
        $extract = $dir . DIRECTORY_SEPARATOR . 'zip_' . $id . '_' . time();
        @mkdir($extract, 0775, true);
        $zip->extractTo($extract);

        $entro = 1;
        $anio = (int) date('Y');
        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);
            if (str_ends_with($nombre, '/')) {
                continue;
            }
            $clave = $this->claveZip($nombre);
            $origen = $extract . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $nombre);
            if (! is_file($origen)) {
                continue;
            }

            foreach ($items as $it) {
                $itemId = (int) $it->cdgo_deta_pgo_prvdres;
                if ($tipo === 1 && $clave === trim((string) ($it->nu_ccp ?? ''))) {
                    $adjun = trim((string) ($it->nu_sga ?? $itemId)) . '-' . $itemId . '-CCNM.pdf';
                    @copy($origen, $dir . DIRECTORY_SEPARATOR . $adjun);
                    $siga = $this->consultaSigaCcmn(trim((string) $it->nu_ccp), $anio);
                    $update = ['de_urls_ccp' => $adjun];
                    if ($siga) {
                        $update['nu_mnto'] = $siga['monto'] ?? $it->nu_mnto;
                        $update['nu_espcfccon_gsto'] = $siga['clasificador'] ?? null;
                    }
                    DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
                        ->where('cdgo_deta_pgo_prvdres', $itemId)
                        ->update($update);
                }

                if ($tipo === 2 && $clave === trim((string) ($it->nu_orden_srvco ?? ''))) {
                    $siga = $this->consultaSigaOrden(trim((string) $it->nu_orden_srvco), trim((string) $it->nu_ccp), $anio);
                    $adjun = trim((string) ($it->nu_orden_srvco ?? $itemId)) . '-' . $itemId . '-OS.pdf';
                    @copy($origen, $dir . DIRECTORY_SEPARATOR . $adjun);
                    $cdgoUsrios = 0;
                    if ($siga && trim((string) ($siga['proveedor'] ?? '')) !== '') {
                        $cdgoUsrios = (int) (DB::table($this->schemaSistema . 'h_usrios_ntral')
                            ->whereRaw("TRIM(CONCAT_WS(' ', TRIM(ap_pate), TRIM(ap_mate), TRIM(no_usrio))) = TRIM(?)", [
                                preg_replace('/\s+/', ' ', (string) $siga['proveedor']),
                            ])
                            ->value('cdgo_usrios') ?? 0);
                    }
                    if ($cdgoUsrios > 0 && empty($it->cdgo_usrios)) {
                        $this->aplicarOrdenSiga($itemId, $it, $siga, $adjun, $cdgoUsrios, $userId);
                    } else {
                        $entro = 2;
                    }
                }
            }
        }
        $zip->close();
        $this->borrarDir($extract);

        if ($tipo === 2) {
            if ($entro === 1) {
                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->where('cdgo_pgo_prvdres', $id)->update(['in_esta' => 3]);
            } else {
                return $this->fail('Existen usuarios no registrados, por favor ingréselos.');
            }
        }

        return $this->ok(['id' => $id], 'Los archivos fueron migrados satisfactoriamente.');
    }

    public function enviarFinanzas(int $id, int $tipoId, string $semilla, ?UploadedFile $adjunto): array
    {
        return $this->enviarDocumento($id, $tipoId, $semilla, $adjunto, 'finanzas');
    }

    public function enviarInforme(int $id, int $tipoId, string $semilla, ?UploadedFile $adjunto): array
    {
        return $this->enviarDocumento($id, $tipoId, $semilla, $adjunto, 'informe');
    }

    public function enviarPresupuesto(int $id): array
    {
        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if ((int) ($cab->in_esta_prvdres ?? 0) !== 9) {
            return $this->fail('El requerimiento no está en estado para enviar a Presupuesto.');
        }

        $tramiteId = (int) ($cab->cdgo_dtos_ntrnos ?? 0);
        $dest = $this->cargoJefeArea(self::AREA_PRESUPUESTO);
        if ($tramiteId <= 0 || $dest <= 0) {
            return $this->fail('No hay trámite o cargo jefe en Presupuesto (área 97).');
        }

        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);
        $cargoId = (int) session('sigtram_cargo_id', 0);

        try {
            return DB::transaction(function () use ($id, $tramiteId, $dest, $userId, $cargoId) {
                $this->cerrarMovimientos($tramiteId);
                $movId = (int) DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insertGetId([
                    'cdgo_dtos_ntrnos' => $tramiteId,
                    'cdgo_estdo_trmte' => 1,
                    'cdgo_lgin' => $userId,
                    'cdgo_crgo_sa' => $cargoId,
                    'cdgo_crgo_en' => $dest,
                    'de_urls_agrdo' => '',
                    'fe_rcbdo' => now(),
                    'de_obser_ntrnos' => 'SOLICITUD DE CCP',
                    'in_copia' => '1',
                    'in_esta' => '1',
                ], 'cdgo_trmtes_ntrnos');

                $zipName = 'adjun' . $movId . '.zip';
                $this->armarZipAdjuntos($id, $zipName, 'presupuesto');
                DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                    ->where('cdgo_trmtes_ntrnos', $movId)
                    ->update(['de_urls_agrdo' => $zipName]);

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->update([
                        'cdgo_dtos_ntrnos_hbltar' => $movId,
                        'in_esta' => 12,
                        'fe_pgo_prvdres' => now(),
                    ]);

                return $this->ok(['id' => $id], 'Se generó y envió el memorándum.');
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_logistica] enviarPresupuesto: ' . $e->getMessage());

            return $this->fail($e->getMessage() !== '' ? $e->getMessage() : 'No se pudo enviar a Presupuesto.');
        }
    }

    public function devolver(int $id, string $motivo, string $etapa): array
    {
        $motivo = mb_strtoupper(trim($motivo), 'UTF-8');
        if ($motivo === '') {
            return $this->fail('Ingrese el motivo de devolución.');
        }

        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }

        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);
        $cargoId = (int) session('sigtram_cargo_id', 0);
        $areaDest = (int) ($cab->cdgo_area ?? 0);
        $dest = $this->cargoJefeArea($areaDest);
        $tramiteId = (int) ($cab->cdgo_dtos_ntrnos ?? 0);
        $nuPgo = $this->ultimoPagoMemo($id, 5);

        try {
            return DB::transaction(function () use ($id, $motivo, $etapa, $userId, $cargoId, $dest, $tramiteId, $nuPgo, $cab) {
                if ($etapa === 'finanzas') {
                    if ((int) ($cab->in_esta_prvdres ?? 0) !== 5) {
                        throw new \RuntimeException('El requerimiento no está en estado para devolver.');
                    }
                    DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->where('cdgo_pgo_prvdres', $id)->update(['in_esta' => 5]);
                    $memoIds = DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
                        ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
                        ->where('h.cdgo_pgo_prvdres', $id)
                        ->where('m.in_esta', 5)
                        ->when($nuPgo > 0, fn ($q) => $q->where('m.nu_pgo_mmos', $nuPgo))
                        ->pluck('m.cdgo_deta_pgo_mmos');
                    if ($memoIds->isNotEmpty()) {
                        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')->whereIn('cdgo_deta_pgo_mmos', $memoIds->all())->update([
                            'cdgo_dtos_ntrnos_cnfrmdad' => null,
                            'cdgo_dtos_ntrnos_dvngar' => null,
                            'de_urls_dvngar' => null,
                            'in_esta' => 4,
                        ]);
                    }
                    $estadoTram = 1;
                    $crgoEn = $dest;
                    $crgoSa = $cargoId;
                } elseif ($etapa === 'area') {
                    if ((int) ($cab->in_esta_prvdres ?? 0) !== 9) {
                        throw new \RuntimeException('El requerimiento no está en estado para devolver al área.');
                    }
                    DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->where('cdgo_pgo_prvdres', $id)->update([
                        'in_esta' => 1,
                        'cdgo_dtos_ntrnos_hbltar' => null,
                    ]);
                    $estadoTram = 3;
                    $crgoEn = $dest;
                    $crgoSa = $dest;
                } else {
                    throw new \RuntimeException('Etapa de devolución no válida.');
                }

                if ($tramiteId > 0 && $crgoEn > 0) {
                    $this->cerrarMovimientos($tramiteId);
                    DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insert([
                        'cdgo_dtos_ntrnos' => $tramiteId,
                        'cdgo_estdo_trmte' => $estadoTram,
                        'cdgo_lgin' => $userId,
                        'cdgo_crgo_sa' => $crgoSa,
                        'cdgo_crgo_en' => $crgoEn,
                        'de_urls_agrdo' => '',
                        'fe_rcbdo' => now(),
                        'de_obser_ntrnos' => $motivo,
                        'in_copia' => '1',
                        'in_esta' => '1',
                    ]);
                }

                return $this->ok(['id' => 0], 'Se devolvió el requerimiento satisfactoriamente.');
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_logistica] devolver: ' . $e->getMessage());

            return $this->fail($e->getMessage() !== '' ? $e->getMessage() : 'No se pudo devolver el requerimiento.');
        }
    }

    public function resolverRutaArchivo(string $archivo): ?string
    {
        $archivo = basename(str_replace('\\', '/', $archivo));
        if ($archivo === '' || str_contains($archivo, '..')) {
            return null;
        }

        $legacyRoot = rtrim((string) config('filesystems.legacy_sigtram_root', base_path('../sigtram_antiguo')), '/\\');
        foreach ([
            storage_path('app/mod_a/sgl/pago_proveedores/adjunto/' . $archivo),
            $legacyRoot . '/Presentacion/mod_a/sgl/pago_proveedores/adjunto/' . $archivo,
        ] as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    private function enviarDocumento(int $id, int $tipoId, string $semilla, ?UploadedFile $adjunto, string $modo): array
    {
        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }

        $tipos = $modo === 'finanzas' ? [44, 7] : [8];
        if (! in_array($tipoId, $tipos, true)) {
            return $this->fail('Tipo de documento no permitido.');
        }
        if ($modo === 'finanzas' && ((int) ($cab->in_esta_prvdres ?? 0) !== 5 || ! $this->tieneConformidadParaFinanzas($id))) {
            return $this->fail('No hay conformidades para enviar a Finanzas.');
        }
        if ($modo === 'informe' && (int) ($cab->in_esta_prvdres ?? 0) !== 13) {
            return $this->fail('El requerimiento no está en estado para informe técnico.');
        }
        if (! $adjunto instanceof UploadedFile || ! $this->esPdf($adjunto)) {
            return $this->fail('Debe adjuntar un PDF.');
        }

        $tipo = DB::table($this->schemaSistema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $tipoId)->first();
        if (! $tipo) {
            return $this->fail('Tipo de documento no válido.');
        }
        if ((int) ($tipo->in_frma_dgtal ?? 0) === 1 && $this->cargoRequiereFirma() && ! $this->nombreFirmado($adjunto)) {
            return $this->fail('El primer adjunto debe ser PDF y estar firmado digitalmente ([R].PDF).');
        }

        $partes = explode('-', trim($semilla));
        $nu = (int) ltrim((string) ($partes[0] ?? '0'), '0');
        $anioDe = (string) ($partes[1] ?? date('Y'));
        if ($nu <= 0) {
            return $this->fail('Número de trámite no válido.');
        }
        $nuAuto = $nu . '-' . $anioDe;
        $company = $this->compania();
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);
        $cargoId = (int) session('sigtram_cargo_id', 0);

        $anio = DB::table($this->schemaSistema . 'a_anios')
            ->where('de_anio', $anioDe)
            ->when($company > 0, fn ($q) => $q->where('cdgo_cmpnia', $company))
            ->first();
        if (! $anio) {
            return $this->fail('Año de ejercicio no válido.');
        }
        if (DB::table($this->schemaSistema . 'p_dtos_ntrnos')
            ->where('cdgo_tpo_trmte', $tipoId)
            ->where('cdgo_area', $areaId)
            ->where('nu_tram', (string) $nu)
            ->where('cdgo_anio', $anio->cdgo_anio)
            ->exists()) {
            return $this->fail('El número de trámite que desea ingresar ya existe.');
        }

        $destArea = $modo === 'finanzas' ? self::AREA_FINANZAS : self::AREA_INFORME;
        $dest = $this->cargoJefeArea($destArea);
        if ($dest <= 0) {
            return $this->fail('No hay cargo jefe en el área de destino.');
        }

        $titulo = $this->ingresarTramiteService->generarTituloTramiteInterno(
            (string) ($tipo->de_tpo_trmte ?? ''),
            $nu,
            $anioDe,
            ''
        );

        try {
            return DB::transaction(function () use (
                $id, $tipoId, $nu, $nuAuto, $titulo, $anio, $areaId, $userId, $cargoId, $company,
                $adjunto, $cab, $modo, $tipo, $dest
            ) {
                DB::table($this->schemaSistema . 'a_smlla_intrno')->insert([
                    'cdgo_anio' => $anio->cdgo_anio,
                    'cdgo_area' => $areaId,
                    'cdgo_crgo' => $cargoId,
                    'cdgo_tpo_trmte' => $tipoId,
                    'nu_smlla_intrno' => $nu,
                    'co_usua_modi' => $userId,
                    'ts_usua_modi' => now(),
                    'in_esta' => '1',
                ]);

                $expe = explode('-', (string) ($cab->nu_tram_todo ?? ''));
                $obs = 'CON REFERENCIA AL ' . (string) ($cab->de_tpo_trmte ?? '') . ' N° '
                    . str_pad(trim(($expe[0] ?? '') . '-' . ($expe[1] ?? '')), 13, '0', STR_PAD_LEFT)
                    . ' DEL AREA ' . (string) ($cab->de_area ?? '');
                $deObse = '; SE DERIVA CON ' . (string) ($tipo->de_tpo_trmte ?? '') . ' N° '
                    . str_pad($nuAuto, 10, '0', STR_PAD_LEFT);
                $obsMov = ($modo === 'finanzas' ? 'TRAMITE DE PAGO PARA DEVENGAR' : 'INDAGACIÓN DE MERCADO') . $deObse;
                $refId = (int) ($cab->cdgo_dtos_ntrnos ?? 0);

                $nuevoId = (int) DB::table($this->schemaSistema . 'p_dtos_ntrnos')->insertGetId([
                    'cdgo_tpo_trmte' => $tipoId,
                    'cdgo_anio' => $anio->cdgo_anio,
                    'cdgo_area' => $areaId,
                    'cdgo_lgin' => $userId,
                    'cdgo_cmpnia' => $company > 0 ? $company : null,
                    'cdgo_dto_trmte' => null,
                    'cdgo_dtos_ntrnos_pri' => $refId > 0 ? $refId : null,
                    'nu_tram_todo' => $nuAuto,
                    'nu_tram_cmplto' => $titulo,
                    'nu_tram' => (string) $nu,
                    'de_obser' => $obs,
                    'de_urls_adjnto' => '',
                    'fe_ingrso' => now(),
                    'in_envo_fsco' => '0',
                    'in_prrdad_trmte' => 1,
                    'in_esta' => 1,
                ], 'cdgo_dtos_ntrnos');

                $zipName = 'pri' . $nuevoId . '.zip';
                $this->armarZipAdjuntos($id, $zipName, $modo, $adjunto);
                DB::table($this->schemaSistema . 'p_dtos_ntrnos')
                    ->where('cdgo_dtos_ntrnos', $nuevoId)
                    ->update(['de_urls_adjnto' => $zipName]);

                $movNuevo = (int) DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insertGetId([
                    'cdgo_dtos_ntrnos' => $nuevoId,
                    'cdgo_estdo_trmte' => 1,
                    'cdgo_lgin' => $userId,
                    'cdgo_crgo_sa' => $cargoId,
                    'cdgo_crgo_en' => $cargoId,
                    'de_urls_agrdo' => $zipName,
                    'fe_rcbdo' => now(),
                    'de_obser_ntrnos' => $obsMov,
                    'in_copia' => '1',
                    'in_esta' => '1',
                ], 'cdgo_trmtes_ntrnos');

                if ($refId > 0) {
                    $this->cerrarMovimientos($refId);
                    $movOrig = (int) DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insertGetId([
                        'cdgo_dtos_ntrnos' => $refId,
                        'cdgo_estdo_trmte' => 1,
                        'cdgo_lgin' => $userId,
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $dest,
                        'de_urls_agrdo' => '',
                        'fe_rcbdo' => now(),
                        'de_obser_ntrnos' => $obsMov,
                        'in_copia' => '1',
                        'in_esta' => '1',
                    ], 'cdgo_trmtes_ntrnos');
                    DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                        ->where('cdgo_trmtes_ntrnos', $movOrig)
                        ->update(['cdgo_dtos_ntrnos_rfrncia' => $movNuevo]);
                }

                if ($modo === 'finanzas') {
                    $memoIds = DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
                        ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
                        ->where('h.cdgo_pgo_prvdres', $id)
                        ->where('m.in_esta', 5)
                        ->whereNotNull('m.de_urls_cnfrmdad')
                        ->pluck('m.cdgo_deta_pgo_mmos');
                    if ($memoIds->isNotEmpty()) {
                        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')->whereIn('cdgo_deta_pgo_mmos', $memoIds->all())->update([
                            'cdgo_dtos_ntrnos_dvngar' => $nuevoId,
                            'de_urls_dvngar' => $zipName,
                            'in_esta' => 6,
                        ]);
                    }
                    DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->where('cdgo_pgo_prvdres', $id)->update([
                        'in_esta' => 6,
                        'fe_pgo_prvdres' => now(),
                    ]);
                    $msg = 'Se generó y envió a Finanzas.';
                } else {
                    DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->where('cdgo_pgo_prvdres', $id)->update([
                        'cdgo_dtos_ntrnos_indgcon' => $nuevoId,
                        'in_esta' => 9,
                        'fe_pgo_prvdres' => now(),
                    ]);
                    $msg = 'Se generó y envió el informe técnico.';
                }

                return $this->ok(['id' => $id], $msg);
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_logistica] enviarDocumento: ' . $e->getMessage());

            return $this->fail($e->getMessage() !== '' ? $e->getMessage() : 'No se pudo generar el trámite.');
        }
    }

    private function armarZipAdjuntos(int $pagoId, string $zipName, string $modo, ?UploadedFile $adjunto = null): void
    {
        $dirs = [
            storage_path('app/private/mod_gene/mod_tram_inter/adjunto'),
            storage_path('app/mod_gene/mod_tram_inter/adjunto'),
        ];
        foreach ($dirs as $d) {
            if (! is_dir($d)) {
                @mkdir($d, 0775, true);
            }
        }

        $zipPath = $dirs[0] . DIRECTORY_SEPARATOR . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo ZIP del trámite.');
        }

        $add = function (string $ruta, string $entrada) use ($zip): void {
            if (is_file($ruta)) {
                $zip->addFile($ruta, $entrada);
            }
        };

        if ($adjunto instanceof UploadedFile) {
            $nom = preg_replace('/[^a-zA-Z0-9_\-\. \[\]\(\)]/', '_', $adjunto->getClientOriginalName()) ?: 'adjunto.pdf';
            $add((string) $adjunto->getRealPath(), $nom);
        }

        $items = DB::select(
            'SELECT h.cdgo_deta_pgo_prvdres, h.nu_sga, h.de_urls_pgo, h.de_urls_anxos, h.de_urls_ccp,
                    h.nu_orden_srvco, u.no_crto
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             LEFT JOIN ' . $this->schemaSistema . 'p_usrios u ON h.cdgo_usrios = u.cdgo_usrios
             WHERE h.cdgo_pgo_prvdres = ? AND h.in_esta NOT IN (0, 2)
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$pagoId]
        );

        if ($modo === 'finanzas') {
            $zip->addEmptyDir('DEVENGAR');
            $memos = DB::select(
                'SELECT m.cdgo_deta_pgo_mmos, m.de_urls_cnfrmdad, h.nu_orden_srvco, u.no_crto
                 FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
                 INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h ON m.cdgo_deta_pgo_prvdres = h.cdgo_deta_pgo_prvdres
                 LEFT JOIN ' . $this->schemaSistema . 'p_usrios u ON h.cdgo_usrios = u.cdgo_usrios
                 WHERE h.cdgo_pgo_prvdres = ? AND m.in_esta = 5 AND m.de_urls_cnfrmdad IS NOT NULL
                 ORDER BY m.cdgo_deta_pgo_prvdres ASC',
                [$pagoId]
            );
            foreach ($memos as $m) {
                $ruta = $this->resolverRutaArchivo((string) $m->de_urls_cnfrmdad);
                if ($ruta) {
                    $entrada = 'DEVENGAR/' . trim((string) ($m->nu_orden_srvco ?? '')) . '-'
                        . trim((string) ($m->no_crto ?? '')) . '-' . basename((string) $m->de_urls_cnfrmdad);
                    $add($ruta, $entrada);
                }
            }
        } elseif ($modo === 'informe') {
            $zip->addEmptyDir('TDR-SIGA-ANEXOS');
            foreach ($items as $it) {
                foreach (['de_urls_anxos', 'de_urls_pgo'] as $col) {
                    $nom = trim((string) ($it->{$col} ?? ''));
                    $ruta = $nom !== '' ? $this->resolverRutaArchivo($nom) : null;
                    if ($ruta) {
                        $add($ruta, 'TDR-SIGA-ANEXOS/' . trim((string) ($it->nu_sga ?? '')) . '-' . basename($nom));
                    }
                }
            }
        } else {
            foreach ($items as $it) {
                foreach (['de_urls_ccp', 'de_urls_pgo'] as $col) {
                    $nom = trim((string) ($it->{$col} ?? ''));
                    $ruta = $nom !== '' ? $this->resolverRutaArchivo($nom) : null;
                    if ($ruta) {
                        $add($ruta, trim((string) ($it->nu_sga ?? '')) . '-' . basename($nom));
                    }
                }
            }
        }

        $zip->close();
        if ($dirs[1] !== $dirs[0] && is_file($zipPath)) {
            @copy($zipPath, $dirs[1] . DIRECTORY_SEPARATOR . $zipName);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id:int,nombre:string}>
     */
    private function tiposDocumento(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::select(
            "SELECT DISTINCT t.cdgo_tpo_trmte AS id, t.de_tpo_trmte AS nombre
             FROM {$this->schemaSistema}r_tpo_trmte_areas r
             INNER JOIN {$this->schemaSistema}a_tpo_trmtes t ON r.cdgo_tpo_trmte = t.cdgo_tpo_trmte
             WHERE r.in_esta = '1' AND t.cdgo_tpo_trmte IN ({$ph})
             ORDER BY t.de_tpo_trmte ASC",
            $ids
        );

        return array_map(fn ($r) => ['id' => (int) $r->id, 'nombre' => (string) $r->nombre], $rows);
    }

    private function tiposDocumentoIdentidad(): array
    {
        return DB::table($this->schemaSistema . 'a_dcmntos_iden')
            ->where('in_esta', '1')
            ->orderBy('nu_orde')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) ($r->cdgo_dcmntos_iden ?? 0),
                'nombre' => (string) ($r->dc_dcmntos_iden ?? $r->de_dcmntos_iden ?? ''),
            ])
            ->all();
    }

    private function sugerenciasProveedor(): array
    {
        return DB::table($this->schemaSistema . 'p_usrios')
            ->where('in_tipo_pers', '0')
            ->where('in_esta', '1')
            ->whereNotNull('no_crto')
            ->orderBy('no_crto')
            ->limit(300)
            ->pluck('no_crto')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->values()
            ->all();
    }

    private function cabecera(int $id): ?object
    {
        $sistema = $this->schemaSistema;

        return DB::selectOne(
            'SELECT p.cdgo_pgo_prvdres, p.de_pgo_prvdres, p.in_esta AS in_esta_prvdres,
                    p.cdgo_dtos_ntrnos, p.cdgo_area, n.nu_tram_todo, a.dc_area, a.de_area, t.de_tpo_trmte
             FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON p.cdgo_dtos_ntrnos = n.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_tpo_trmtes t ON n.cdgo_tpo_trmte = t.cdgo_tpo_trmte
             LEFT JOIN ' . $sistema . 'a_areas a ON p.cdgo_area = a.cdgo_area
             WHERE p.in_esta NOT IN (0) AND p.cdgo_pgo_prvdres = ?',
            [$id]
        );
    }

    private function itemsDeRequerimiento(int $id, int $estado): array
    {
        $rows = DB::select(
            'SELECT h.cdgo_deta_pgo_prvdres, h.nu_cntdad_prvdres, h.de_deta_pgo_prvdres, h.nu_mnto,
                    h.nu_sga, h.nu_orden_srvco, h.de_urls_pgo, h.de_urls_anxos, h.de_urls_os, h.de_urls_ccp,
                    h.nu_pgos_cntdad, h.nu_ccp, h.cdgo_usrios, h.in_esta AS in_esta_pgo
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             WHERE h.in_esta NOT IN (0) AND h.cdgo_pgo_prvdres = ?
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$id]
        );

        $n = 0;
        $out = [];
        foreach ($rows as $row) {
            $n++;
            $icono = $this->iconoItem($estado, $row);
            $out[] = [
                'n' => $n,
                'id' => (int) $row->cdgo_deta_pgo_prvdres,
                'cantidad' => $row->nu_cntdad_prvdres !== null ? (int) $row->nu_cntdad_prvdres : null,
                'descripcion' => (string) ($row->de_deta_pgo_prvdres ?? ''),
                'pagos' => (int) ($row->nu_pgos_cntdad ?? 0),
                'monto' => $row->nu_mnto !== null ? (float) $row->nu_mnto : null,
                'ccp' => trim((string) ($row->nu_ccp ?? '')),
                'os' => trim((string) ($row->nu_orden_srvco ?? '')),
                'url_tdr' => $this->urlOpcional($row->de_urls_pgo ?? null),
                'url_ane' => $this->urlOpcional($row->de_urls_anxos ?? null),
                'url_os' => $this->urlOpcional($row->de_urls_os ?? null),
                'url_ccp' => $this->urlOpcional($row->de_urls_ccp ?? null),
                'usuario_id' => (int) ($row->cdgo_usrios ?? 0),
                'in_esta' => (int) ($row->in_esta_pgo ?? 1),
                'icono' => $icono['icono'],
                'titulo_icono' => $icono['titulo'],
            ];
        }

        return $out;
    }

    private function itemRaw(int $itemId): ?object
    {
        $sistema = $this->schemaSistema;

        return DB::selectOne(
            'SELECT h.*, h.in_esta AS in_esta_pgo, p.in_esta AS in_esta_prvdres, u.no_crto
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             INNER JOIN ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p ON h.cdgo_pgo_prvdres = p.cdgo_pgo_prvdres
             LEFT JOIN ' . $sistema . 'p_usrios u ON h.cdgo_usrios = u.cdgo_usrios
             WHERE h.cdgo_deta_pgo_prvdres = ? AND h.in_esta NOT IN (0)',
            [$itemId]
        );
    }

    private function memosDeItem(int $itemId, object $item): array
    {
        $sistema = $this->schemaSistema;
        $rows = DB::select(
            'SELECT m.cdgo_deta_pgo_mmos, m.de_urls_cnfrmdad, m.nu_mnto_sldo, m.nu_dia_pgo,
                    n.nu_tram_todo, n.fe_ingrso, a.dc_area,
                    nd.nu_tram_todo AS nu_tram_todo_dvngdo, ad.dc_area AS dc_area_dvngdo
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON m.cdgo_dtos_ntrnos_cnfrmdad = n.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_areas a ON n.cdgo_area = a.cdgo_area
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos nd ON m.cdgo_dtos_ntrnos_dvngdo = nd.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_areas ad ON nd.cdgo_area = ad.cdgo_area
             WHERE m.cdgo_deta_pgo_prvdres = ? AND m.in_esta NOT IN (0)
             ORDER BY m.cdgo_deta_pgo_mmos ASC',
            [$itemId]
        );

        $out = [];
        foreach ($rows as $i => $row) {
            $dias = (int) ($row->nu_dia_pgo ?? 0);
            $fechaConf = '';
            if (! empty($item->fe_orden_srvco)) {
                $ts = strtotime((string) $item->fe_orden_srvco . ' +' . $dias . ' days');
                $fechaConf = $ts ? date('Y-m-d', $ts) : '';
                if ($fechaConf !== '') {
                    DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                        ->where('cdgo_deta_pgo_mmos', (int) $row->cdgo_deta_pgo_mmos)
                        ->update(['fe_pgo_aprxmdo' => $row->fe_ingrso ?: $fechaConf]);
                }
            }
            $doc = trim((string) ($row->nu_tram_todo_dvngdo ?? ''));
            $areaDoc = trim((string) ($row->dc_area_dvngdo ?? ''));
            if ($doc === '') {
                $doc = trim((string) ($row->nu_tram_todo ?? ''));
                $areaDoc = trim((string) ($row->dc_area ?? ''));
            }

            $out[] = [
                'n' => $i + 1,
                'id' => (int) $row->cdgo_deta_pgo_mmos,
                'documento' => 'MEMORANDUM N° ' . $doc . ($areaDoc !== '' ? '-' . $areaDoc : ''),
                'monto' => $row->nu_mnto_sldo !== null ? (float) $row->nu_mnto_sldo : null,
                'dias' => $dias,
                'fecha' => $this->fechaCorta($row->fe_ingrso ?? $fechaConf),
                'url' => $this->urlOpcional($row->de_urls_cnfrmdad ?? null),
            ];
        }

        return $out;
    }

    private function tieneConformidadParaFinanzas(int $pagoId): bool
    {
        return DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
            ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
            ->where('h.cdgo_pgo_prvdres', $pagoId)
            ->where('m.in_esta', 5)
            ->whereNotNull('m.cdgo_dtos_ntrnos_cnfrmdad')
            ->exists();
    }

    private function ultimoPagoMemo(int $pagoId, int $estado): int
    {
        return (int) (DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
            ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
            ->where('h.cdgo_pgo_prvdres', $pagoId)
            ->where('m.in_esta', $estado)
            ->max('m.nu_pgo_mmos') ?? 0);
    }

    private function aplicarOrdenSiga(int $itemId, object $it, ?array $siga, string $adjun, int $cdgoUsrios, int $userId): void
    {
        $fecha = $siga['fecha'] ?? null;
        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update([
                'fe_orden_srvco' => $fecha,
                'nu_siaf' => $siga['siaf'] ?? null,
                'nu_espcfccon_gsto' => $siga['clasificador'] ?? null,
                'de_urls_os' => $adjun,
                'cdgo_usrios' => $cdgoUsrios,
            ]);

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')->where('cdgo_deta_pgo_prvdres', $itemId)->delete();
        $pagos = max(1, (int) ($it->nu_pgos_cntdad ?? 1));
        $sldo = $pagos > 0 ? ((float) ($it->nu_mnto ?? 0)) / $pagos : 0;
        $dias = explode('|', (string) ($it->no_das_pgos ?? ''));
        for ($u = 1; $u <= $pagos; $u++) {
            DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')->insert([
                'cdgo_deta_pgo_prvdres' => $itemId,
                'cdgo_dtos_ntrnos_cnfrmdad' => null,
                'de_urls_cnfrmdad' => null,
                'nu_mnto_sldo' => $sldo,
                'nu_pgo_mmos' => $u,
                'nu_dia_pgo' => (int) ($dias[$u - 1] ?? 0),
                'ts_usua_modi' => now(),
                'co_usua_modi' => $userId,
                'in_esta' => 1,
            ]);
        }
    }

    private function consultaSigaCcmn(string $ccp, int $anio): ?array
    {
        if ($ccp === '') {
            return null;
        }
        try {
            $row = DB::connection('siga')->selectOne(
                "SELECT TOP 1 scf.VALOR_SOLES as MONTO, spm.CLASIFICADOR as CLASIFICADOR
                 FROM SIG_CERTIFICACION_FASE scf
                 INNER JOIN SIG_PAAC_CONSOLIDADO spc ON spc.NRO_CONSOLID = scf.NRO_CONSOLID
                 INNER JOIN SIG_PAAC_METAS spm ON spm.NRO_CONSOLID = scf.NRO_CONSOLID
                 INNER JOIN SIG_CERTIFICACION_OPER_FUENTE scof ON scof.NRO_CERTIFICA = scf.NRO_CERTIFICA
                 WHERE spc.tipo_consolid = '2'
                   AND scf.ANO_EJE = ? AND spc.ANO_EJE = ? AND spm.ANO_EJE = ? AND scof.ANO_EJE = ?
                   AND scf.NRO_CONSOLID = ?
                 ORDER BY scf.SECUENCIA_FASE DESC",
                [$anio, $anio, $anio, $anio, $ccp]
            );
        } catch (Throwable $e) {
            Log::warning('[ver_requerimiento_logistica] SIGA CCMN: ' . $e->getMessage());

            return null;
        }

        return $row ? ['monto' => $row->MONTO ?? null, 'clasificador' => $row->CLASIFICADOR ?? null] : null;
    }

    private function consultaSigaOrden(string $os, string $ccp, int $anio): ?array
    {
        if ($os === '') {
            return null;
        }
        try {
            $row = DB::connection('siga')->selectOne(
                "SELECT TOP 1 soa.EXP_SIAF as EXP_SIAF, spm.CLASIFICADOR as CLASIFICADOR,
                        spc.FECHA_TIPO_EJEC as FECHA_ORDEN, sc.NOMBRE_PROV as NOMBRE_PROVEEDOR
                 FROM SIG_CERTIFICACION_FASE scf
                 INNER JOIN SIG_PAAC_CONSOLIDADO spc ON spc.NRO_CONSOLID = scf.NRO_CONSOLID
                 INNER JOIN SIG_PAAC_METAS spm ON spm.NRO_CONSOLID = scf.NRO_CONSOLID
                 INNER JOIN SIG_CERTIFICACION_OPER_FUENTE scof ON scof.NRO_CERTIFICA = scf.NRO_CERTIFICA
                 INNER JOIN SIG_ORDEN_ADQUISICION soa ON soa.NRO_ORDEN = scf.NRO_ORDEN
                 INNER JOIN SIG_CONTRATISTAS sc ON soa.PROVEEDOR = sc.PROVEEDOR
                 WHERE soa.TIPO_BIEN = 'S' AND scf.TIPO_BIEN = 'S' AND spc.tipo_consolid = '2'
                   AND scf.ANO_EJE = ? AND spc.ANO_EJE = ? AND spm.ANO_EJE = ? AND scof.ANO_EJE = ? AND soa.ANO_EJE = ?
                   AND scf.NRO_ORDEN = ? AND scf.NRO_CONSOLID = ?
                 ORDER BY scf.SECUENCIA_FASE DESC",
                [$anio, $anio, $anio, $anio, $anio, $os, $ccp]
            );
        } catch (Throwable $e) {
            Log::warning('[ver_requerimiento_logistica] SIGA OS: ' . $e->getMessage());

            return null;
        }
        if (! $row) {
            return null;
        }
        $fecha = $row->FECHA_ORDEN ?? null;
        if (is_object($fecha) && method_exists($fecha, 'format')) {
            $fecha = $fecha->format('Y-m-d');
        }

        return [
            'siaf' => $row->EXP_SIAF ?? null,
            'clasificador' => $row->CLASIFICADOR ?? null,
            'fecha' => $fecha,
            'proveedor' => $row->NOMBRE_PROVEEDOR ?? null,
        ];
    }

    private function claveZip(string $nombreZip): string
    {
        $base = basename(str_replace('\\', '/', $nombreZip));
        $partes = preg_split('/\s+/', $base) ?: [];
        $token = (string) ($partes[1] ?? $partes[0] ?? '');
        $token = explode('[', $token)[0];

        return trim($token);
    }

    private function iconoLista(int $estado, int $sinAnexo): string
    {
        return match (true) {
            $estado === 9 && $sinAnexo === 0 => 'amarillo',
            $estado === 9 && $sinAnexo > 0, $estado === 10 => 'rojo',
            $estado === 11 => 'verde',
            $estado === 5 => 'azul',
            default => 'ver',
        };
    }

    private function tituloLista(int $estado, int $sinAnexo): string
    {
        return match (true) {
            $estado === 9 && $sinAnexo === 0 => 'Autorizar Presupuesto',
            $estado === 9 && $sinAnexo > 0, $estado === 10, $estado === 3 => 'Esperando Anexos',
            $estado === 11 => 'Generar y subir O/S',
            $estado === 5 => 'Generar Memorandum a devengar',
            default => 'Sin actividad',
        };
    }

    private function iconoItem(int $estado, object $row): array
    {
        $monto = (float) ($row->nu_mnto ?? 0);
        $user = (int) ($row->cdgo_usrios ?? 0);
        $ccp = trim((string) ($row->nu_ccp ?? ''));
        $urlCcp = trim((string) ($row->de_urls_ccp ?? ''));
        $ane = trim((string) ($row->de_urls_anxos ?? ''));

        if ($estado === 13 && $monto < 0.01) {
            return ['icono' => 'verde', 'titulo' => 'Agregar detalle para la habilitación'];
        }
        if (in_array($estado, [11, 3, 4], true) && $user === 0) {
            return ['icono' => 'verde', 'titulo' => 'Agregar orden de servicio'];
        }
        if ($estado === 9 && ($ccp === '' || $urlCcp === '') && $ane !== '') {
            return ['icono' => 'amarillo', 'titulo' => 'Agregar detalle para la habilitación'];
        }
        if ($estado === 9 && $ane === '') {
            return ['icono' => 'rojo', 'titulo' => 'Esperando ANEXOS Y SERVIR'];
        }

        return ['icono' => 'ver', 'titulo' => 'Ver documentación'];
    }

    private function cerrarMovimientos(int $tramiteId): void
    {
        DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
            ->where('cdgo_dtos_ntrnos', $tramiteId)
            ->update(['in_esta' => '0']);
    }

    private function cargoJefeArea(int $areaId): int
    {
        if ($areaId <= 0) {
            return 0;
        }

        return (int) (DB::table($this->schemaSistema . 'p_crgos')
            ->where('cdgo_area', $areaId)
            ->where('nu_orde', '1')
            ->orderByDesc('cdgo_crgo')
            ->value('cdgo_crgo') ?? 0);
    }

    private function cargoRequiereFirma(): bool
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);

        return $cargoId > 0 && (int) DB::table($this->schemaSistema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('in_frma_dgtal') === 1;
    }

    private function dirAdjuntos(): string
    {
        $dir = storage_path('app/mod_a/sgl/pago_proveedores/adjunto');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function guardarPdf(UploadedFile $file, string $dir, string $nombre): string
    {
        $nombre = basename($nombre);
        if (is_file($dir . DIRECTORY_SEPARATOR . $nombre)) {
            @unlink($dir . DIRECTORY_SEPARATOR . $nombre);
        }
        $file->move($dir, $nombre);

        return $nombre;
    }

    private function esPdf(UploadedFile $file): bool
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        return $ext === 'pdf' || str_contains($mime, 'pdf');
    }

    private function nombreFirmado(UploadedFile $file): bool
    {
        return str_ends_with(strtoupper($file->getClientOriginalName()), '[R].PDF');
    }

    private function borrarDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            is_dir($f) ? $this->borrarDir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    private function detalleVacio(): array
    {
        return [
            'id' => 0, 'asunto' => '', 'area' => '', 'cdgo_area' => 0, 'estado' => 0,
            'cdgo_dtos_ntrnos' => 0, 'documento' => '', 'nu_pgo_mmos' => 0,
            'puede_enviar_finanzas' => false, 'puede_devolver_finanzas' => false,
            'puede_devolver_area' => false, 'puede_enviar_presupuesto' => false,
            'puede_informe_tecnico' => false, 'puede_publicar' => false,
            'puede_subir_ccmn' => false, 'puede_subir_os' => false, 'items' => [],
        ];
    }

    private function urlOpcional(?string $nombre): ?string
    {
        $nombre = trim((string) $nombre);

        return $nombre !== '' ? $nombre : null;
    }

    private function fechaCorta(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        $ts = strtotime((string) $fecha);

        return $ts ? date('d/m/Y', $ts) : '';
    }

    private function fechaIso(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        $ts = strtotime((string) $fecha);

        return $ts ? date('Y-m-d', $ts) : '';
    }

    private function fechaSql(mixed $fecha): ?string
    {
        $iso = $this->fechaIso($fecha);

        return $iso !== '' ? $iso : null;
    }

    private function compania(): int
    {
        return (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));
    }

    private function ok(mixed $data, string $message = ''): array
    {
        return ['success' => true, 'data' => $data, 'message' => $message];
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'error' => $message, 'message' => $message];
    }
}
