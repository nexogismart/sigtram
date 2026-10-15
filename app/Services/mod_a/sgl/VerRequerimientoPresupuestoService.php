<?php

namespace App\Services\mod_a\sgl;

use App\Services\mod_gene\mod_tram_inter\IngresarTramiteService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\SessionKeys;
use Throwable;
use ZipArchive;

/**
 * Pago a proveedores — Presupuesto
 * (migrado desde ver_requerimiento_presupuesto.php + lista_pago_proveedores_presupuesto.php).
 */
class VerRequerimientoPresupuestoService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private const AREA_ABASTECIMIENTO = 101;

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
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
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
            'tiposMemo' => $this->tiposDocumento([7, 69]),
        ];
    }

    public function listar(array $filtros): array
    {
        $campo = (string) ($filtros['campo'] ?? 'nu_tram_todo');
        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $fecha = trim((string) ($filtros['fecha'] ?? ''));
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = 20;
        $offset = ($pagina - 1) * $porPagina;
        $sistema = $this->schemaSistema;

        $from = '
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            LEFT JOIN ' . $sistema . 'p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
            LEFT JOIN ' . $sistema . 'a_tpo_trmtes ON p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte
            LEFT JOIN ' . $sistema . 'a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
            WHERE p_pgo_prvdres.in_esta = 12
              AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
        ';
        $params = [];

        if ($criterio !== '' && isset(self::CAMPOS[$campo])) {
            $from .= ' AND ' . self::CAMPOS[$campo] . ' ILIKE ?';
            $params[] = '%' . $criterio . '%';
        }
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            $from .= ' AND DATE(p_pgo_prvdres.fe_pgo_prvdres) = ?';
            $params[] = $fecha;
        }

        try {
            $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $from, $params)->total ?? 0);
            $sql = '
                SELECT p_pgo_prvdres.cdgo_pgo_prvdres,
                    p_pgo_prvdres.in_esta AS in_esta_prvdres,
                    p_dtos_ntrnos.nu_tram_todo,
                    p_dtos_ntrnos.fe_ingrso,
                    a_tpo_trmtes.dc_tpo_trmte,
                    a_areas.dc_area,
                    a_areas.de_area
                ' . $from . '
                ORDER BY p_pgo_prvdres.cdgo_pgo_prvdres DESC
                LIMIT ' . $porPagina . ' OFFSET ' . $offset;

            $rows = DB::select($sql, $params);
            $n = $offset;
            $items = array_map(function ($row) use (&$n) {
                $n++;
                $tramite = trim((string) ($row->dc_tpo_trmte ?? ''));
                $numero = trim((string) ($row->nu_tram_todo ?? ''));

                return [
                    'n' => $n,
                    'id' => (int) $row->cdgo_pgo_prvdres,
                    'titulo' => trim($tramite . ($numero !== '' ? ' N° ' . $numero : '')) ?: 'Sin trámite',
                    'area' => (string) ($row->dc_area ?? ''),
                    'area_nombre' => (string) ($row->de_area ?? ''),
                    'fecha' => $this->fechaCorta($row->fe_ingrso ?? null),
                    'estado' => (int) ($row->in_esta_prvdres ?? 0),
                ];
            }, $rows);

            return $this->ok([
                'items' => $items,
                'total' => $total,
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'paginas' => max(1, (int) ceil($total / $porPagina)),
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_presupuesto] listar: ' . $e->getMessage());

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
            $completo = $items !== [] && collect($items)->every(
                fn ($it) => trim((string) ($it['ccp_nta'] ?? '')) !== '' && ! empty($it['url_ccp_nta'])
            );

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
                'puede_enviar' => $completo && $estado === 12,
                'puede_devolver' => ! ($completo && $estado === 12),
                'puede_publicar' => $estado === 10,
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_presupuesto] detalle: ' . $e->getMessage());

            return $this->fail('No se pudo cargar el requerimiento.');
        }
    }

    public function itemDetalle(int $itemId): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $nombre = trim((string) ($item->no_crto ?? ''));

        return $this->ok([
            'id' => (int) $item->cdgo_deta_pgo_prvdres,
            'pago_id' => (int) $item->cdgo_pgo_prvdres,
            'servicio' => (string) ($item->de_deta_pgo_prvdres ?? ''),
            'siga' => (string) ($item->nu_sga ?? ''),
            'url_siga' => $this->urlOpcional($item->de_urls_siga ?? null),
            'url_tdr' => $this->urlOpcional($item->de_urls_tdr ?? null),
            'url_ccp_nta' => $this->urlOpcional($item->de_urls_ccp_nta ?? null),
            'ccp' => (string) ($item->nu_ccp ?? ''),
            'ccp_nta' => (string) ($item->nu_ccp_nta ?? ''),
            'rubro' => (string) ($item->nu_rbro ?? ''),
            'meta' => (string) ($item->nu_mta ?? ''),
            'esp_gasto' => (string) ($item->nu_espcfccon_gsto ?? ''),
            'nombre' => $nombre,
            'monto' => $item->nu_mnto !== null ? (float) $item->nu_mnto : null,
            'pagos' => (int) ($item->nu_pgos_cntdad ?? 0),
            'dias_pago' => (string) ($item->no_das_pgos ?? ''),
            'readonly' => $nombre !== '',
        ]);
    }

    public function semilla(int $tipoId): array
    {
        $areaId = $this->areaUsuario();
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
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

    public function actualizarSiaf(int $itemId, string $valor): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $valor = trim($valor);
        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update([
                'nu_siaf' => $valor,
                'nu_ccp_nta' => $valor,
            ]);

        return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres, 'valor' => $valor], 'Actualizado.');
    }

    public function guardarHabilitacion(int $itemId, array $datos, ?UploadedFile $siaf): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $update = [
            'nu_ccp' => trim((string) ($datos['ccp'] ?? '')),
            'nu_ccp_nta' => trim((string) ($datos['ccp_nta'] ?? '')),
            'nu_mta' => trim((string) ($datos['meta'] ?? '')),
            'nu_pddo' => '',
            'nu_rbro' => trim((string) ($datos['rubro'] ?? '')),
            'nu_espcfccon_gsto' => trim((string) ($datos['esp_gasto'] ?? '')),
        ];
        if ($siaf instanceof UploadedFile) {
            if (! $this->esPdf($siaf)) {
                return $this->fail('El SIAF debe ser un PDF.');
            }
            $nuSga = trim((string) ($item->nu_sga ?? $itemId));
            $update['de_urls_ccp_nta'] = $this->guardarPdf($siaf, $this->dirAdjuntos(), $nuSga . '-' . $itemId . '-SIAF.pdf');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update($update);

        return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres], 'Se generó el detalle de la certificación presupuestal.');
    }

    public function enviarAbastecimiento(int $id, int $tipoId, string $semilla, ?UploadedFile $adjunto): array
    {
        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if ((int) ($cab->in_esta_prvdres ?? 0) !== 12) {
            return $this->fail('El requerimiento no está en estado para enviar a Abastecimiento.');
        }
        if (! in_array($tipoId, [7, 69], true)) {
            return $this->fail('Tipo de documento no permitido.');
        }

        $items = $this->itemsDeRequerimiento($id, 12);
        $completo = $items !== [] && collect($items)->every(
            fn ($it) => trim((string) ($it['ccp_nta'] ?? '')) !== '' && ! empty($it['url_ccp_nta'])
        );
        if (! $completo) {
            return $this->fail('Falta completar SIAF / CCP nota en todos los ítems.');
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
        $nu = (int) ltrim((string) ($partes[0]), '0');
        $anioDe = (string) ($partes[1] ?? date('Y'));
        if ($nu <= 0) {
            return $this->fail('Número de trámite no válido.');
        }
        $nuAuto = $nu . '-' . $anioDe;
        $company = $this->compania();
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);

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
            return $this->fail('El número del memorandum que desea ingresar ya existe, por favor agrege uno nuevo.');
        }

        $dest = $this->cargoJefeArea(self::AREA_ABASTECIMIENTO);
        if ($dest <= 0) {
            return $this->fail('No hay cargo jefe en Abastecimiento.');
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
                $adjunto, $cab, $tipo, $dest
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
                    . str_pad(trim(($expe[0]) . '-' . ($expe[1] ?? '')), 13, '0', STR_PAD_LEFT)
                    . ' DEL AREA ' . (string) ($cab->de_area ?? '');
                $deObse = '; SE DERIVA CON ' . (string) ($tipo->de_tpo_trmte ?? '') . ' N° '
                    . str_pad($nuAuto, 10, '0', STR_PAD_LEFT);
                $obsMov = 'CERTIFICACIÓN DE CRÉDITO PRESUPUESTARIO' . $deObse;
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
                $this->armarZipAdjuntos($id, $zipName, $adjunto);
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

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->where('cdgo_pgo_prvdres', $id)->update([
                    'cdgo_dtos_ntrnos_prspsto' => $nuevoId,
                    'in_esta' => 11,
                    'fe_pgo_prvdres' => now(),
                ]);

                return $this->ok(['id' => $id], 'Se generó y envió el memorandum.');
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_presupuesto] enviarAbastecimiento: ' . $e->getMessage());

            return $this->fail($e->getMessage() !== '' ? $e->getMessage() : 'No se pudo generar el trámite.');
        }
    }

    public function devolver(int $id, string $motivo): array
    {
        $motivo = mb_strtoupper(trim($motivo), 'UTF-8');
        if ($motivo === '') {
            return $this->fail('Ingrese el motivo de devolución.');
        }

        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }

        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        $dest = $this->cargoJefeArea(self::AREA_ABASTECIMIENTO);
        $tramiteId = (int) ($cab->cdgo_dtos_ntrnos ?? 0);

        try {
            return DB::transaction(function () use ($id, $motivo, $userId, $cargoId, $dest, $tramiteId) {
                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->where('cdgo_pgo_prvdres', $id)->update([
                    'in_esta' => 9,
                    'cdgo_dtos_ntrnos_hbltar' => null,
                ]);

                if ($tramiteId > 0 && $dest > 0) {
                    $this->cerrarMovimientos($tramiteId);
                    DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insert([
                        'cdgo_dtos_ntrnos' => $tramiteId,
                        'cdgo_estdo_trmte' => 1,
                        'cdgo_lgin' => $userId,
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $dest,
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
            Log::error('[ver_requerimiento_presupuesto] devolver: ' . $e->getMessage());

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

    private function armarZipAdjuntos(int $pagoId, string $zipName, UploadedFile $adjunto): void
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

        $nom = preg_replace('/[^a-zA-Z0-9_\-\. \[\]\(\)]/', '_', $adjunto->getClientOriginalName()) ?: 'adjunto.pdf';
        $zip->addFile((string) $adjunto->getRealPath(), $nom);
        $zip->addEmptyDir('TDR-SIGA-ANEXOS-CCP');

        $items = DB::select(
            'SELECT h.cdgo_deta_pgo_prvdres, h.nu_sga, h.de_urls_pgo, h.de_urls_ccp_nta, h.de_urls_anxos
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             WHERE h.cdgo_pgo_prvdres = ? AND h.in_esta NOT IN (0, 2)
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$pagoId]
        );
        foreach ($items as $it) {
            $pref = 'TDR-SIGA-ANEXOS-CCP/' . trim((string) ($it->nu_sga ?? '')) . '-';
            foreach (['de_urls_pgo', 'de_urls_ccp_nta', 'de_urls_anxos'] as $col) {
                $nomArch = trim((string) ($it->{$col} ?? ''));
                $ruta = $nomArch !== '' ? $this->resolverRutaArchivo($nomArch) : null;
                if ($ruta) {
                    $zip->addFile($ruta, $pref . basename($nomArch));
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
                    h.nu_sga, h.nu_pgos_cntdad, h.nu_ccp, h.nu_ccp_nta, h.de_urls_ccp, h.de_urls_ccp_nta,
                    h.in_esta AS in_esta_pgo
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             WHERE h.in_esta NOT IN (0) AND h.cdgo_pgo_prvdres = ?
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$id]
        );

        $n = 0;
        $out = [];
        foreach ($rows as $row) {
            $n++;
            $ccp = trim((string) ($row->nu_ccp ?? ''));
            $urlNta = $this->urlOpcional($row->de_urls_ccp_nta ?? null);
            $amarillo = $estado === 12 && ($ccp === '' || $urlNta === null);
            $out[] = [
                'n' => $n,
                'id' => (int) $row->cdgo_deta_pgo_prvdres,
                'cantidad' => $row->nu_cntdad_prvdres !== null ? (int) $row->nu_cntdad_prvdres : null,
                'descripcion' => (string) ($row->de_deta_pgo_prvdres ?? ''),
                'pagos' => (int) ($row->nu_pgos_cntdad ?? 0),
                'monto' => $row->nu_mnto !== null ? (float) $row->nu_mnto : null,
                'siga' => (string) ($row->nu_sga ?? ''),
                'ccp' => $ccp,
                'ccp_nta' => trim((string) ($row->nu_ccp_nta ?? '')),
                'url_ccp' => $this->urlOpcional($row->de_urls_ccp ?? null),
                'url_ccp_nta' => $urlNta,
                'in_esta' => (int) ($row->in_esta_pgo ?? 1),
                'icono' => $amarillo ? 'amarillo' : 'ver',
                'titulo_icono' => $amarillo ? 'Agregar detalle para la habilitación' : '',
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
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);

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

    private function detalleVacio(): array
    {
        return [
            'id' => 0, 'asunto' => '', 'area' => '', 'cdgo_area' => 0, 'estado' => 0,
            'cdgo_dtos_ntrnos' => 0, 'documento' => '', 'items' => [],
            'puede_enviar' => false, 'puede_devolver' => false, 'puede_publicar' => false,
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

    private function compania(): int
    {
        return (int) session(SessionKeys::USER_COMPANY, 0);
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
