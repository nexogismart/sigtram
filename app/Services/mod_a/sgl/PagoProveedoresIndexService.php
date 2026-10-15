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
 * Pago a proveedores (área del cargo).
 * Migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/index.php
 */
class PagoProveedoresIndexService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private const AREA_LOGISTICA = 101;

    private const AREA_OGAF = 98;

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

        $cargo = DB::table($this->schemaSistema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first(['cdgo_area']);

        return (int) ($cargo->cdgo_area ?? 0);
    }

    public function catalogos(): array
    {
        return [
            'sugerencias' => $this->sugerenciasDescripcion(),
            'tiposRequerimiento' => $this->tiposDocumento([64, 65]),
            'tiposMemo' => $this->tiposDocumento([7, 24]),
        ];
    }

    public function sugerenciasDescripcion(): array
    {
        $areaId = $this->areaUsuario();
        if ($areaId <= 0) {
            return [];
        }

        $rows = DB::select(
            'SELECT DISTINCT h.de_deta_pgo_prvdres AS nombre
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             INNER JOIN ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p ON h.cdgo_pgo_prvdres = p.cdgo_pgo_prvdres
             WHERE h.in_esta = \'1\' AND p.cdgo_area = ?
             ORDER BY h.de_deta_pgo_prvdres
             LIMIT 200',
            [$areaId]
        );

        return array_values(array_filter(array_map(
            fn ($r) => trim((string) ($r->nombre ?? '')),
            $rows
        )));
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id:int,nombre:string}>
     */
    public function tiposDocumento(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = DB::select(
            "SELECT DISTINCT t.cdgo_tpo_trmte AS id, t.de_tpo_trmte AS nombre
             FROM {$this->schemaSistema}r_tpo_trmte_areas r
             INNER JOIN {$this->schemaSistema}a_tpo_trmtes t ON r.cdgo_tpo_trmte = t.cdgo_tpo_trmte
             WHERE r.in_esta = '1'
               AND t.cdgo_tpo_trmte IN ({$placeholders})
             ORDER BY t.de_tpo_trmte ASC",
            $ids
        );

        return array_map(fn ($r) => [
            'id' => (int) $r->id,
            'nombre' => (string) $r->nombre,
        ], $rows);
    }

    public function listar(array $filtros): array
    {
        $areaId = $this->areaUsuario();
        if ($areaId <= 0) {
            return $this->fail('No se pudo determinar el área del cargo.');
        }

        $campo = (string) ($filtros['campo'] ?? 'nu_tram_todo');
        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $fecha = trim((string) ($filtros['fecha'] ?? ''));
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = 50;
        $offset = ($pagina - 1) * $porPagina;
        $sistema = $this->schemaSistema;

        $from = '
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            LEFT JOIN ' . $sistema . 'p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
            LEFT JOIN ' . $sistema . 'a_tpo_trmtes ON p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte
            LEFT JOIN ' . $sistema . 'a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
            WHERE p_pgo_prvdres.in_esta NOT IN (0)
              AND p_pgo_prvdres.cdgo_area = ?
        ';
        $params = [$areaId];

        if ($criterio !== '') {
            if ($campo === 'de_pgo_prvdres') {
                $from .= ' AND p_pgo_prvdres.de_pgo_prvdres ILIKE ?';
            } else {
                $from .= ' AND p_dtos_ntrnos.nu_tram_todo ILIKE ?';
            }
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
                    p_pgo_prvdres.fe_pgo_prvdres,
                    p_pgo_prvdres.in_esta AS in_esta_prvdres,
                    p_pgo_prvdres.de_pgo_prvdres,
                    p_dtos_ntrnos.nu_tram_todo,
                    a_tpo_trmtes.dc_tpo_trmte
                ' . $from . '
                ORDER BY p_pgo_prvdres.cdgo_pgo_prvdres DESC
                LIMIT ' . $porPagina . ' OFFSET ' . $offset;

            $rows = DB::select($sql, $params);
            $n = $offset;
            $data = array_map(function ($row) use (&$n) {
                $n++;
                $estado = (int) ($row->in_esta_prvdres ?? 0);
                $tramite = trim((string) ($row->dc_tpo_trmte ?? ''));
                $numero = trim((string) ($row->nu_tram_todo ?? ''));
                $titulo = trim($tramite . ($numero !== '' ? ' N° ' . $numero : ''));
                if ($titulo === '') {
                    $titulo = mb_strimwidth((string) ($row->de_pgo_prvdres ?? 'Sin trámite'), 0, 48, '…', 'UTF-8');
                }

                return [
                    'n' => $n,
                    'id' => (int) $row->cdgo_pgo_prvdres,
                    'titulo' => $titulo,
                    'fecha' => $this->fechaCorta($row->fe_pgo_prvdres ?? null),
                    'estado' => $estado,
                    'icono' => $this->iconoEstado($estado),
                    'titulo_icono' => $this->tituloIcono($estado),
                ];
            }, $rows);

            return $this->ok([
                'items' => $data,
                'total' => $total,
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'paginas' => (int) ceil($total / $porPagina),
            ]);
        } catch (Throwable $e) {
            Log::error('[pago_proveedores] listar: ' . $e->getMessage());

            return $this->fail('No se pudo listar los pagos.');
        }
    }

    public function detalle(int $id): array
    {
        $areaId = $this->areaUsuario();
        if ($areaId <= 0) {
            return $this->fail('No se pudo determinar el área del cargo.');
        }

        if ($id <= 0) {
            return $this->ok($this->detalleVacio());
        }

        try {
            $cab = $this->cabecera($id, $areaId);
            if ($cab === null) {
                return $this->fail('No se encontró el requerimiento.');
            }

            $items = $this->itemsDeRequerimiento($id, $areaId, true);
            $estado = (int) ($cab->in_esta_prvdres ?? 0);
            $tieneTramite = (int) ($cab->cdgo_dtos_ntrnos ?? 0) > 0;
            $activos = array_values(array_filter($items, fn ($it) => (int) $it['in_esta'] !== 2));
            $conTdr = array_values(array_filter($activos, fn ($it) => $it['url_tdr'] !== null && $it['url_tdr'] !== ''));
            $conformidadPendiente = $this->tieneConformidadPendiente($id);

            $documento = trim((string) ($cab->de_tpo_trmte ?? ''));
            $numero = trim((string) ($cab->nu_tram_todo ?? ''));
            $areaDoc = trim((string) ($cab->dc_area ?? ''));
            $docLabel = $documento !== ''
                ? trim($documento . ' N° ' . $numero . ($areaDoc !== '' ? '-' . $areaDoc : ''))
                : '';

            return $this->ok([
                'id' => (int) $cab->cdgo_pgo_prvdres,
                'asunto' => (string) ($cab->de_pgo_prvdres ?? ''),
                'locadores' => (int) ($cab->in_pgo_prvdres ?? 1) === 1,
                'estado' => $estado,
                'cdgo_dtos_ntrnos' => (int) ($cab->cdgo_dtos_ntrnos ?? 0),
                'documento' => $docLabel,
                'editable' => $estado === 1 && ! $tieneTramite,
                'readonly' => $tieneTramite || $estado !== 1,
                'puede_anular' => ! $tieneTramite,
                'puede_finalizar' => $tieneTramite && $estado !== 2,
                'puede_generar_tramite' => $estado === 1 && count($activos) > 0 && count($activos) === count($conTdr),
                'puede_generar_memo' => $estado === 4 && $conformidadPendiente,
                'items' => $items,
                'nu_items' => count($items),
            ]);
        } catch (Throwable $e) {
            Log::error('[pago_proveedores] detalle: ' . $e->getMessage());

            return $this->fail('No se pudo cargar el requerimiento.');
        }
    }

    public function guardar(array $datos): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        if ($areaId <= 0 || $userId <= 0) {
            return $this->fail('Sesión de cargo no válida.');
        }

        $id = (int) ($datos['id'] ?? 0);
        $asunto = mb_strtoupper(trim((string) ($datos['asunto'] ?? '')), 'UTF-8');
        if ($asunto === '') {
            return $this->fail('Ingrese el asunto del requerimiento.');
        }

        $locadores = ! empty($datos['locadores']);
        $items = is_array($datos['items'] ?? null) ? $datos['items'] : [];
        if ($items === []) {
            return $this->fail('Ingrese al menos un ítem.');
        }
        if (count($items) > 200) {
            return $this->fail('El máximo de ítems es 200.');
        }

        try {
            return DB::transaction(function () use ($id, $areaId, $userId, $asunto, $locadores, $items) {
                $ahora = now();
                $inPgo = $locadores ? 1 : 2;

                if ($id <= 0) {
                    $id = (int) DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')->insertGetId([
                        'cdgo_dtos_ntrnos' => null,
                        'cdgo_area' => $areaId,
                        'de_pgo_prvdres' => $asunto,
                        'fe_pgo_prvdres' => $ahora,
                        'in_pgo_prvdres' => $inPgo,
                        'ts_usua_modi' => $ahora,
                        'co_usua_modi' => $userId,
                        'in_esta' => 1,
                    ], 'cdgo_pgo_prvdres');
                    $mensaje = 'Se almacenó correctamente.';
                } else {
                    $cab = $this->cabecera($id, $areaId);
                    if ($cab === null) {
                        return $this->fail('No se encontró el requerimiento.');
                    }
                    if ((int) ($cab->cdgo_dtos_ntrnos ?? 0) > 0) {
                        return $this->fail('El requerimiento ya tiene trámite y no se puede editar.');
                    }

                    DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                        ->where('cdgo_pgo_prvdres', $id)
                        ->update([
                            'de_pgo_prvdres' => $asunto,
                            'fe_pgo_prvdres' => $ahora,
                            'in_pgo_prvdres' => $inPgo,
                            'ts_usua_modi' => $ahora,
                            'co_usua_modi' => $userId,
                        ]);
                    $mensaje = 'Se editó correctamente.';
                }

                foreach ($items as $item) {
                    $itemId = (int) ($item['id'] ?? 0);
                    $descripcion = mb_strtoupper(trim((string) ($item['descripcion'] ?? '')), 'UTF-8');
                    $cantidad = (int) ($item['cantidad'] ?? 0);
                    $pagos = (int) ($item['pagos'] ?? 0);
                    $siga = trim((string) ($item['siga'] ?? ''));

                    if ($descripcion === '' || $cantidad < 1 || $pagos < 1 || $siga === '') {
                        return $this->fail('Complete cantidad, descripción, pagos y SIGA de cada ítem.');
                    }

                    if ($itemId <= 0) {
                        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')->insert([
                            'cdgo_pgo_prvdres' => $id,
                            'cdgo_usrios' => null,
                            'de_deta_pgo_prvdres' => $descripcion,
                            'nu_mnto' => 0,
                            'nu_sga' => $siga,
                            'de_urls_pgo' => null,
                            'nu_cntdad_prvdres' => $cantidad,
                            'nu_pgos_cntdad' => $pagos,
                            'ts_usua_modi' => $ahora,
                            'co_usua_modi' => $userId,
                            'in_esta' => 1,
                        ]);
                    } else {
                        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
                            ->where('cdgo_deta_pgo_prvdres', $itemId)
                            ->where('cdgo_pgo_prvdres', $id)
                            ->update([
                                'de_deta_pgo_prvdres' => $descripcion,
                                'nu_mnto' => 0,
                                'nu_sga' => $siga,
                                'nu_cntdad_prvdres' => $cantidad,
                                'nu_pgos_cntdad' => $pagos,
                                'ts_usua_modi' => $ahora,
                                'co_usua_modi' => $userId,
                            ]);
                    }
                }

                return $this->ok(['id' => $id], $mensaje);
            });
        } catch (Throwable $e) {
            Log::error('[pago_proveedores] guardar: ' . $e->getMessage());

            return $this->fail('No se pudo guardar el requerimiento.');
        }
    }

    public function anular(int $id): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $cab = $this->cabecera($id, $areaId);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if ((int) ($cab->cdgo_dtos_ntrnos ?? 0) > 0) {
            return $this->fail('No se puede anular un requerimiento con trámite generado.');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
            ->where('cdgo_pgo_prvdres', $id)
            ->update([
                'in_esta' => 0,
                'ts_usua_modi' => now(),
                'co_usua_modi' => $userId,
            ]);

        return $this->ok(['id' => $id], 'Se anuló el pre-requerimiento correctamente.');
    }

    public function finalizar(int $id): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        $cab = $this->cabecera($id, $areaId);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }

        $tramiteId = (int) ($cab->cdgo_dtos_ntrnos ?? 0);
        if ($tramiteId <= 0) {
            return $this->fail('El requerimiento no tiene trámite para finalizar.');
        }
        if ((int) ($cab->in_esta_prvdres ?? 0) === 2) {
            return $this->fail('El requerimiento ya está finalizado.');
        }

        $cargoArea = $this->cargoJefeArea($areaId);
        if ($cargoArea <= 0) {
            $cargoArea = $cargoId;
        }

        try {
            return DB::transaction(function () use ($id, $tramiteId, $userId, $cargoArea) {
                DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                    ->where('cdgo_dtos_ntrnos', $tramiteId)
                    ->update(['in_esta' => '0']);

                DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insert([
                    'cdgo_dtos_ntrnos' => $tramiteId,
                    'cdgo_estdo_trmte' => 3,
                    'cdgo_lgin' => $userId,
                    'cdgo_crgo_sa' => $cargoArea,
                    'cdgo_crgo_en' => $cargoArea,
                    'de_urls_agrdo' => '',
                    'fe_rcbdo' => now(),
                    'de_obser_ntrnos' => 'SE FINALIZA EL REQUERIMIENTO',
                    'in_copia' => '1',
                    'in_esta' => '1',
                ]);

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->update([
                        'in_esta' => 2,
                        'ts_usua_modi' => now(),
                        'co_usua_modi' => $userId,
                    ]);

                return $this->ok(['id' => $id], 'Se finalizó el requerimiento correctamente.');
            });
        } catch (Throwable $e) {
            Log::error('[pago_proveedores] finalizar: ' . $e->getMessage());

            return $this->fail('No se pudo finalizar el requerimiento.');
        }
    }

    public function bajaItem(int $itemId): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $item = $this->itemRaw($itemId, $areaId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update([
                'in_esta' => 2,
                'ts_usua_modi' => now(),
                'co_usua_modi' => $userId,
            ]);

        $nombre = trim((string) ($item->no_crto ?? ''));

        return $this->ok(
            ['id' => (int) $item->cdgo_pgo_prvdres],
            'Se dio de baja al proveedor' . ($nombre !== '' ? ' ' . $nombre : '') . '.'
        );
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
            ->first();
        if (! $anio) {
            $anio = DB::table($this->schemaSistema . 'a_anios')->orderByDesc('de_anio')->first();
        }
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

        $max = (int) $query->max('nu_smlla_intrno');
        $nu = $max > 0 ? $max + 1 : 1;
        $anioDe = (string) ($anio->de_anio ?? $anioTxt);

        return $this->ok([
            'nu_smlla_intrno' => $nu . '-' . $anioDe,
            'requiere_firma' => (int) ($tipo->in_frma_dgtal ?? 0) === 1 && $this->cargoRequiereFirma(),
        ]);
    }

    public function generarTramite(int $id, int $tipoId, string $semilla, ?UploadedFile $adjunto): array
    {
        return $this->generarDocumentoInterno($id, $tipoId, $semilla, $adjunto, 'tramite');
    }

    public function generarMemo(int $id, int $tipoId, string $semilla, ?UploadedFile $adjunto): array
    {
        return $this->generarDocumentoInterno($id, $tipoId, $semilla, $adjunto, 'memo');
    }

    public function itemDetalle(int $itemId): array
    {
        $areaId = $this->areaUsuario();
        $item = $this->itemRaw($itemId, $areaId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $estado = (int) ($item->in_esta_prvdres ?? 0);
        $nombre = trim((string) ($item->no_crto ?? ''));
        $modo = 'ver';
        if ($estado === 1) {
            $modo = 'tdr';
        } elseif ($nombre === '' && in_array($estado, [9, 10, 11, 12], true)) {
            $modo = 'anexos';
        }

        $memos = [];
        $puedeGuardarConformidad = false;
        if ($nombre !== '') {
            $memos = $this->memosDeItem($itemId, $item, $puedeGuardarConformidad);
        }

        return $this->ok([
            'id' => (int) $item->cdgo_deta_pgo_prvdres,
            'pago_id' => (int) $item->cdgo_pgo_prvdres,
            'servicio' => (string) ($item->de_deta_pgo_prvdres ?? ''),
            'siga' => (string) ($item->nu_sga ?? ''),
            'url_siga' => $this->urlOpcional($item->de_urls_siga ?? null),
            'url_tdr' => $this->urlOpcional($item->de_urls_tdr ?? null),
            'url_pgo' => $this->urlOpcional($item->de_urls_pgo ?? null),
            'url_ane' => $this->urlOpcional($item->de_urls_anxos ?? null),
            'url_servir' => $this->urlOpcional($item->de_urls_adicional ?? null),
            'url_os' => $this->urlOpcional($item->de_urls_os ?? null),
            'ruc' => (string) ($item->nu_ruc ?? ''),
            'cci' => (string) ($item->nu_cci ?? ''),
            'monto' => $item->nu_mnto !== null ? (float) $item->nu_mnto : null,
            'pagos' => (int) ($item->nu_pgos_cntdad ?? 0),
            'os' => (string) ($item->nu_orden_srvco ?? ''),
            'fecha_os' => $this->fechaCorta($item->fe_orden_srvco ?? null),
            'rubro' => (string) ($item->nu_rbro ?? ''),
            'siaf' => (string) ($item->nu_siaf ?? ''),
            'nombre' => $nombre,
            'estado' => $estado,
            'in_esta_item' => (int) ($item->in_esta_pgo ?? 0),
            'modo' => $modo,
            'memos' => $memos,
            'puede_guardar_conformidad' => $puedeGuardarConformidad,
        ]);
    }

    public function guardarTdrSiga(int $itemId, ?UploadedFile $tdr, ?UploadedFile $siga): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $item = $this->itemRaw($itemId, $areaId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }
        if ((int) ($item->in_esta_prvdres ?? 0) !== 1) {
            return $this->fail('Este ítem ya no admite TDR/SIGA.');
        }
        if (! $tdr instanceof UploadedFile || ! $siga instanceof UploadedFile) {
            return $this->fail('Debe adjuntar TDR y pedido SIGA en PDF.');
        }
        if (! $this->esPdf($tdr) || ! $this->esPdf($siga)) {
            return $this->fail('TDR y SIGA deben ser archivos PDF.');
        }

        $nuSga = trim((string) ($item->nu_sga ?? $itemId));
        $dir = $this->dirAdjuntos();
        $nomTdr = $this->guardarPdf($tdr, $dir, $nuSga . '-' . $itemId . '-TDR.pdf');
        $nomSiga = $this->guardarPdf($siga, $dir, $nuSga . '-' . $itemId . '-SIGA.pdf');
        $nomPgo = $nuSga . '-' . $itemId . '-TDRSIGA.pdf';
        @copy($dir . DIRECTORY_SEPARATOR . $nomTdr, $dir . DIRECTORY_SEPARATOR . $nomPgo);

        $pagos = max(1, (int) ($item->nu_pgos_cntdad ?? 1));
        $dias = [];
        $n = 10;
        for ($i = 0; $i < $pagos; $i++) {
            $dias[] = $n;
            $n += 25;
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update([
                'de_urls_tdr' => $nomTdr,
                'de_urls_siga' => $nomSiga,
                'de_urls_pgo' => $nomPgo,
                'no_das_pgos' => implode('|', $dias),
                'co_usua_modi' => $userId,
                'ts_usua_modi' => now(),
            ]);

        return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres], 'Se adjuntó TDR/SIGA correctamente.');
    }

    public function guardarAnexos(int $itemId, array $datos, ?UploadedFile $anexos, ?UploadedFile $servir): array
    {
        $areaId = $this->areaUsuario();
        $item = $this->itemRaw($itemId, $areaId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $pagoId = (int) $item->cdgo_pgo_prvdres;
        $nuSga = trim((string) ($item->nu_sga ?? $itemId));
        $dir = $this->dirAdjuntos();
        $update = [
            'nu_mnto' => (float) str_replace(',', '.', (string) ($datos['monto'] ?? 0)),
            'nu_cci' => trim((string) ($datos['cci'] ?? '')),
            'nu_ruc' => trim((string) ($datos['ruc'] ?? '')),
            'ts_usua_modi' => now(),
        ];

        if ($anexos instanceof UploadedFile) {
            if (! $this->esPdf($anexos)) {
                return $this->fail('Los anexos deben ser un PDF.');
            }
            $update['de_urls_anxos'] = $this->guardarPdf($anexos, $dir, $nuSga . '-' . $itemId . '-ANEXO.pdf');
        }
        if ($servir instanceof UploadedFile) {
            if (! $this->esPdf($servir)) {
                return $this->fail('SERVIR debe ser un PDF.');
            }
            $update['de_urls_adicional'] = $this->guardarPdf($servir, $dir, $nuSga . '-' . $itemId . '-SERVIR.pdf');
        }

        DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->update($update);

        DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
            ->where('cdgo_pgo_prvdres', $pagoId)
            ->update([
                'fe_pgo_prvdres' => now(),
                'ts_usua_modi' => now(),
            ]);

        $tramiteId = (int) ($item->cdgo_dtos_ntrnos ?? 0);
        if ($tramiteId > 0) {
            DB::table($this->schemaSistema . 'p_dtos_ntrnos')
                ->where('cdgo_dtos_ntrnos', $tramiteId)
                ->update(['fe_ingrso' => now()]);
            DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                ->where('cdgo_dtos_ntrnos', $tramiteId)
                ->update(['fe_rcbdo' => now()]);
        }

        $incompletos = (int) DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_pgo_prvdres', $pagoId)
            ->whereNotIn('in_esta', [0, 2])
            ->where(function ($q) {
                $q->whereNull('nu_ruc')->orWhereRaw("TRIM(COALESCE(nu_ruc,'')) = ''")
                    ->orWhereNull('nu_cci')->orWhereRaw("TRIM(COALESCE(nu_cci,'')) = ''")
                    ->orWhereNull('de_urls_anxos')->orWhereRaw("TRIM(COALESCE(de_urls_anxos,'')) = ''")
                    ->orWhereNull('de_urls_adicional')->orWhereRaw("TRIM(COALESCE(de_urls_adicional,'')) = ''");
            })
            ->count();

        if ($incompletos === 0 && (int) ($item->in_esta_prvdres ?? 0) === 10) {
            DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                ->where('cdgo_pgo_prvdres', $pagoId)
                ->update(['in_esta' => 9]);
        }

        return $this->ok(['id' => $pagoId], 'Se adjuntaron los anexos correctamente.');
    }

    public function guardarConformidad(int $itemId, array $pagos, ?UploadedFile $adicional): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $item = $this->itemRaw($itemId, $areaId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        $dir = $this->dirAdjuntos();
        $n = 0;
        foreach ($pagos as $pago) {
            $n++;
            $memoId = (int) ($pago['id'] ?? 0);
            if ($memoId <= 0) {
                continue;
            }

            $file = $pago['archivo'] ?? null;
            if ($file instanceof UploadedFile && $this->esPdf($file)) {
                $nombre = $n . '-' . $itemId . '-' . $memoId . '-CONFORMIDAD.pdf';
                $this->guardarPdf($file, $dir, $nombre);
                @copy($dir . DIRECTORY_SEPARATOR . $nombre, $dir . DIRECTORY_SEPARATOR . $memoId . '-CONFORMIDAD.pdf');
                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_mmos', $memoId)
                    ->update(['de_urls_cnfrmdad' => $nombre]);
            }

            if (array_key_exists('recibo', $pago)) {
                $susp = ! empty($pago['suspension']) ? 1 : 2;
                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_mmos', $memoId)
                    ->update([
                        'in_sspncon_crta' => $susp,
                        'nu_rcbo_hnrros' => mb_strtoupper(trim((string) $pago['recibo']), 'UTF-8'),
                    ]);
            }
        }

        if ($adicional instanceof UploadedFile && $this->esPdf($adicional)) {
            $nuSga = trim((string) ($item->nu_sga ?? $itemId));
            $nom = $this->guardarPdf($adicional, $dir, $nuSga . '-' . $itemId . '-ADICIONAL.pdf');
            DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
                ->where('cdgo_deta_pgo_prvdres', $itemId)
                ->update([
                    'de_urls_adicional' => $nom,
                    'co_usua_modi' => $userId,
                    'ts_usua_modi' => now(),
                ]);
        }

        return $this->ok(['id' => (int) $item->cdgo_pgo_prvdres], 'Se adjuntó la conformidad correctamente.');
    }

    public function resolverRutaArchivo(string $archivo): ?string
    {
        $archivo = basename(str_replace('\\', '/', $archivo));
        if ($archivo === '' || str_contains($archivo, '..')) {
            return null;
        }

        $legacyRoot = rtrim((string) config('filesystems.legacy_sigtram_root', base_path('../sigtram_antiguo')), '/\\');
        $candidatos = [
            storage_path('app/mod_a/sgl/pago_proveedores/adjunto/' . $archivo),
            $legacyRoot . '/Presentacion/mod_a/sgl/pago_proveedores/adjunto/' . $archivo,
        ];

        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    private function generarDocumentoInterno(int $id, int $tipoId, string $semilla, ?UploadedFile $adjunto, string $modo): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        $company = $this->compania();
        $cab = $this->cabecera($id, $areaId);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }

        $tiposPermitidos = $modo === 'memo' ? [7, 24] : [64, 65];
        if (! in_array($tipoId, $tiposPermitidos, true)) {
            return $this->fail('Tipo de documento no permitido.');
        }

        if ($modo === 'tramite' && (int) ($cab->in_esta_prvdres ?? 0) !== 1) {
            return $this->fail('El requerimiento no está en estado para generar trámite.');
        }
        if ($modo === 'memo') {
            if ((int) ($cab->in_esta_prvdres ?? 0) !== 4 || ! $this->tieneConformidadPendiente($id)) {
                return $this->fail('No hay conformidades pendientes para generar el memorando.');
            }
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
        if ($nu <= 0) {
            $nu = (int) ($partes[0]);
        }
        $anioDe = (string) ($partes[1] ?? date('Y'));
        if ($nu <= 0) {
            return $this->fail('Número de trámite no válido.');
        }
        $nuAuto = $nu . '-' . $anioDe;

        $anio = DB::table($this->schemaSistema . 'a_anios')
            ->where('de_anio', $anioDe)
            ->when($company > 0, fn ($q) => $q->where('cdgo_cmpnia', $company))
            ->first();
        if (! $anio) {
            return $this->fail('Año de ejercicio no válido.');
        }

        $existe = DB::table($this->schemaSistema . 'p_dtos_ntrnos')
            ->where('cdgo_tpo_trmte', $tipoId)
            ->where('cdgo_area', $areaId)
            ->where('nu_tram', (string) $nu)
            ->where('cdgo_anio', $anio->cdgo_anio)
            ->value('cdgo_dtos_ntrnos');
        if ($existe) {
            return $this->fail($modo === 'memo'
                ? 'El número de conformidad que desea ingresar ya existe.'
                : 'El número de requerimiento que desea ingresar ya existe.');
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
                $adjunto, $cab, $modo, $tipo
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

                $obs = mb_strtoupper((string) ($cab->de_pgo_prvdres ?? ''), 'UTF-8');
                $refId = null;
                if ($modo === 'memo') {
                    $tramOrig = (int) ($cab->cdgo_dtos_ntrnos ?? 0);
                    $expe = explode('-', (string) ($cab->nu_tram_todo ?? ''));
                    $deArea = (string) ($cab->de_area ?? '');
                    $obs = 'CON REFERENCIA AL ' . (string) ($cab->de_tpo_trmte ?? '') . ' N° '
                        . str_pad(trim(($expe[0]) . '-' . ($expe[1] ?? '')), 13, '0', STR_PAD_LEFT)
                        . ' DEL AREA ' . $deArea;
                    $refId = $tramOrig > 0 ? $tramOrig : null;
                }

                $nuevoId = (int) DB::table($this->schemaSistema . 'p_dtos_ntrnos')->insertGetId([
                    'cdgo_tpo_trmte' => $tipoId,
                    'cdgo_anio' => $anio->cdgo_anio,
                    'cdgo_area' => $areaId,
                    'cdgo_lgin' => $userId,
                    'cdgo_cmpnia' => $company > 0 ? $company : null,
                    'cdgo_dto_trmte' => null,
                    'cdgo_dtos_ntrnos_pri' => $refId,
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
                $this->armarZipTramite($nuevoId, $id, $areaId, $adjunto, $modo);

                DB::table($this->schemaSistema . 'p_dtos_ntrnos')
                    ->where('cdgo_dtos_ntrnos', $nuevoId)
                    ->update(['de_urls_adjnto' => $zipName]);

                if ($modo === 'tramite') {
                    $dest = $this->cargoJefeArea(self::AREA_LOGISTICA);
                    if ($dest <= 0) {
                        throw new \RuntimeException('No hay cargo jefe en logística (área 101).');
                    }
                    DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insert([
                        'cdgo_dtos_ntrnos' => $nuevoId,
                        'cdgo_estdo_trmte' => 1,
                        'cdgo_lgin' => $userId,
                        'cdgo_crgo_sa' => $cargoId,
                        'cdgo_crgo_en' => $dest,
                        'de_urls_agrdo' => $zipName,
                        'fe_rcbdo' => now(),
                        'de_obser_ntrnos' => $obs,
                        'in_copia' => '1',
                        'in_esta' => '1',
                    ]);

                    DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                        ->where('cdgo_pgo_prvdres', $id)
                        ->update([
                            'cdgo_dtos_ntrnos' => $nuevoId,
                            'in_esta' => 10,
                            'fe_pgo_prvdres' => now(),
                        ]);

                    return $this->ok(['id' => $id], 'Se generó y envió el requerimiento.');
                }

                $obsMemo = 'CONFORMIDAD DE PAGO; SE DERIVA CON ' . (string) ($tipo->de_tpo_trmte ?? '')
                    . ' N° ' . str_pad($nuAuto, 10, '0', STR_PAD_LEFT);

                $movMemo = (int) DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insertGetId([
                    'cdgo_dtos_ntrnos' => $nuevoId,
                    'cdgo_estdo_trmte' => 1,
                    'cdgo_lgin' => $userId,
                    'cdgo_crgo_sa' => $cargoId,
                    'cdgo_crgo_en' => $cargoId,
                    'de_urls_agrdo' => $zipName,
                    'fe_rcbdo' => now(),
                    'de_obser_ntrnos' => $obsMemo,
                    'in_copia' => '1',
                    'in_esta' => '1',
                ], 'cdgo_trmtes_ntrnos');

                $tramOrig = (int) ($cab->cdgo_dtos_ntrnos ?? 0);
                $destArea = $areaId === self::AREA_OGAF ? self::AREA_LOGISTICA : self::AREA_OGAF;
                $dest = $this->cargoJefeArea($destArea);
                if ($dest <= 0) {
                    throw new \RuntimeException('No hay cargo jefe en el área destino.');
                }

                DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                    ->where('cdgo_dtos_ntrnos', $tramOrig)
                    ->update(['in_esta' => '0']);

                $movOrig = (int) DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insertGetId([
                    'cdgo_dtos_ntrnos' => $tramOrig,
                    'cdgo_estdo_trmte' => 1,
                    'cdgo_lgin' => $userId,
                    'cdgo_crgo_sa' => $cargoId,
                    'cdgo_crgo_en' => $dest,
                    'de_urls_agrdo' => $zipName,
                    'fe_rcbdo' => now(),
                    'de_obser_ntrnos' => $obsMemo,
                    'in_copia' => '1',
                    'in_esta' => '1',
                ], 'cdgo_trmtes_ntrnos');

                DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                    ->where('cdgo_trmtes_ntrnos', $movOrig)
                    ->update(['cdgo_dtos_ntrnos_rfrncia' => $movMemo]);

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->update([
                        'in_esta' => 5,
                        'fe_pgo_prvdres' => now(),
                    ]);

                return $this->ok(['id' => $id], 'Se generó y envió el trámite.');
            });
        } catch (Throwable $e) {
            Log::error('[pago_proveedores] generarDocumentoInterno: ' . $e->getMessage());

            return $this->fail($e->getMessage() !== '' ? $e->getMessage() : 'No se pudo generar el trámite.');
        }
    }

    private function armarZipTramite(int $nuevoId, int $pagoId, int $areaId, UploadedFile $adjunto, string $modo): void
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

        $zipPath = $dirs[0] . DIRECTORY_SEPARATOR . 'pri' . $nuevoId . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo ZIP del trámite.');
        }

        $usados = [];
        $addZip = function (string $ruta, string $entrada) use ($zip, &$usados): void {
            if (! is_file($ruta) || isset($usados[$entrada])) {
                return;
            }
            $usados[$entrada] = true;
            $zip->addFile($ruta, $entrada);
        };

        $nombreAdj = preg_replace('/[^a-zA-Z0-9_\-\. \[\]\(\)]/', '_', $adjunto->getClientOriginalName()) ?: 'adjunto.pdf';
        $addZip((string) $adjunto->getRealPath(), $nombreAdj);

        if ($modo === 'tramite') {
            $zip->addEmptyDir('TDR-SIGA');
            $items = DB::select(
                'SELECT cdgo_deta_pgo_prvdres, de_urls_pgo, de_urls_tdr, de_urls_siga
                 FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres
                 WHERE cdgo_pgo_prvdres = ? AND in_esta NOT IN (0, 2)
                 ORDER BY cdgo_deta_pgo_prvdres ASC',
                [$pagoId]
            );
            foreach ($items as $it) {
                foreach (['de_urls_pgo', 'de_urls_tdr', 'de_urls_siga'] as $col) {
                    $nom = trim((string) ($it->{$col} ?? ''));
                    if ($nom === '') {
                        continue;
                    }
                    $ruta = $this->resolverRutaArchivo($nom);
                    if ($ruta) {
                        $addZip($ruta, 'TDR-SIGA/' . basename($nom));
                    }
                }
            }
        } else {
            $zip->addEmptyDir('CONFORMIDADES');
            $rows = DB::select(
                'SELECT m.cdgo_deta_pgo_mmos, m.de_urls_cnfrmdad, h.nu_orden_srvco, u.no_crto,
                        h.de_urls_adicional, h.de_urls_anxos, h.de_urls_os
                 FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
                 INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h ON m.cdgo_deta_pgo_prvdres = h.cdgo_deta_pgo_prvdres
                 INNER JOIN ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p ON h.cdgo_pgo_prvdres = p.cdgo_pgo_prvdres
                 LEFT JOIN ' . $this->schemaSistema . 'p_usrios u ON h.cdgo_usrios = u.cdgo_usrios
                 WHERE p.cdgo_pgo_prvdres = ? AND p.cdgo_area = ?
                   AND m.in_esta = 4 AND m.de_urls_cnfrmdad IS NOT NULL
                   AND m.cdgo_dtos_ntrnos_cnfrmdad IS NULL
                 ORDER BY m.cdgo_deta_pgo_prvdres ASC',
                [$pagoId, $areaId]
            );
            foreach ($rows as $row) {
                foreach (['de_urls_cnfrmdad', 'de_urls_adicional', 'de_urls_anxos', 'de_urls_os'] as $col) {
                    $nom = trim((string) ($row->{$col} ?? ''));
                    if ($nom === '') {
                        continue;
                    }
                    $ruta = $this->resolverRutaArchivo($nom);
                    if (! $ruta) {
                        continue;
                    }
                    $os = trim((string) ($row->nu_orden_srvco ?? ''));
                    $ini = $this->iniciales((string) ($row->no_crto ?? ''));
                    $addZip($ruta, 'CONFORMIDADES/' . trim($os . '-' . $ini . '-' . basename($nom), '-'));
                }
                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_mmos', (int) $row->cdgo_deta_pgo_mmos)
                    ->update([
                        'cdgo_dtos_ntrnos_cnfrmdad' => $nuevoId,
                        'in_esta' => 5,
                    ]);
            }
        }

        $zip->close();
        if (is_file($zipPath) && $dirs[1] !== $dirs[0]) {
            @copy($zipPath, $dirs[1] . DIRECTORY_SEPARATOR . 'pri' . $nuevoId . '.zip');
        }
    }

    private function memosDeItem(int $itemId, object $item, bool &$puedeGuardar): array
    {
        $sistema = $this->schemaSistema;
        $rows = DB::select(
            'SELECT m.cdgo_deta_pgo_mmos, m.cdgo_dtos_ntrnos_cnfrmdad, m.de_urls_cnfrmdad,
                    m.nu_mnto_sldo, m.nu_dia_pgo, m.nu_rcbo_hnrros, m.in_sspncon_crta, m.in_esta,
                    n.nu_tram_todo, n.fe_ingrso, a.dc_area
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON m.cdgo_dtos_ntrnos_cnfrmdad = n.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_areas a ON n.cdgo_area = a.cdgo_area
             WHERE m.cdgo_deta_pgo_prvdres = ? AND m.in_esta NOT IN (0)
             ORDER BY m.cdgo_deta_pgo_mmos ASC',
            [$itemId]
        );

        $out = [];
        $hoy = strtotime(date('Y-m-d 23:59:00'));
        foreach ($rows as $row) {
            $dias = (int) ($row->nu_dia_pgo ?? 0);
            $fechaBase = $item->fe_orden_srvco ?? null;
            $fechaConf = null;
            if ($fechaBase) {
                $fechaConf = date('Y-m-d', strtotime((string) $fechaBase . ' +' . max(0, $dias - 9) . ' days'));
                $aprox = date('Y-m-d', strtotime((string) $fechaBase . ' +' . $dias . ' days'));
                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_mmos', (int) $row->cdgo_deta_pgo_mmos)
                    ->update(['fe_pgo_aprxmdo' => $aprox]);
            }

            $editable = false;
            if ($fechaConf && $hoy > strtotime($fechaConf . ' 00:00:00')
                && empty($row->cdgo_dtos_ntrnos_cnfrmdad)
                && (int) ($item->in_esta_pgo ?? 0) !== 2
            ) {
                $editable = true;
                $puedeGuardar = true;
                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', (int) $item->cdgo_pgo_prvdres)
                    ->update(['in_esta' => 4]);
                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_mmos', (int) $row->cdgo_deta_pgo_mmos)
                    ->update(['in_esta' => 4]);
            }

            $out[] = [
                'id' => (int) $row->cdgo_deta_pgo_mmos,
                'documento' => 'MEMO N° ' . trim((string) ($row->nu_tram_todo ?? '')) . '-' . trim((string) ($row->dc_area ?? '')),
                'monto' => $row->nu_mnto_sldo !== null ? (float) $row->nu_mnto_sldo : null,
                'dias' => $dias,
                'recibo' => (string) ($row->nu_rcbo_hnrros ?? ''),
                'suspension' => (string) ($row->in_sspncon_crta ?? '') === '1',
                'url' => $this->urlOpcional($row->de_urls_cnfrmdad ?? null)
                    ?? $this->urlOpcional(((int) $row->cdgo_deta_pgo_mmos) . '-CONFORMIDAD.pdf'),
                'editable' => $editable,
                'fecha' => $this->fechaCorta($row->fe_ingrso ?? $fechaConf),
            ];
        }

        return $out;
    }

    private function tieneConformidadPendiente(int $pagoId): bool
    {
        $items = DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres')
            ->where('cdgo_pgo_prvdres', $pagoId)
            ->whereNotIn('in_esta', [0, 2])
            ->pluck('cdgo_deta_pgo_prvdres');

        if ($items->isEmpty()) {
            return false;
        }

        foreach ($items as $itemId) {
            $n = (int) DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                ->where('cdgo_deta_pgo_prvdres', $itemId)
                ->where('in_esta', 4)
                ->whereNull('cdgo_dtos_ntrnos_cnfrmdad')
                ->whereNotNull('de_urls_cnfrmdad')
                ->count();
            if ($n === 0) {
                return false;
            }
        }

        return true;
    }

    private function cabecera(int $id, int $areaId): ?object
    {
        $sistema = $this->schemaSistema;

        return DB::selectOne(
            'SELECT p.cdgo_pgo_prvdres, p.de_pgo_prvdres, p.in_pgo_prvdres, p.in_esta AS in_esta_prvdres,
                    p.cdgo_dtos_ntrnos, n.nu_tram_todo, n.cdgo_area, a.dc_area, a.de_area,
                    t.de_tpo_trmte, t.dc_tpo_trmte
             FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON p.cdgo_dtos_ntrnos = n.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_tpo_trmtes t ON n.cdgo_tpo_trmte = t.cdgo_tpo_trmte
             LEFT JOIN ' . $sistema . 'a_areas a ON COALESCE(n.cdgo_area, p.cdgo_area) = a.cdgo_area
             WHERE p.in_esta NOT IN (0) AND p.cdgo_pgo_prvdres = ? AND p.cdgo_area = ?',
            [$id, $areaId]
        );
    }

    private function itemsDeRequerimiento(int $id, int $areaId, bool $incluirBaja): array
    {
        $filtroEsta = $incluirBaja ? 'h.in_esta NOT IN (0)' : 'h.in_esta NOT IN (0, 2)';
        $rows = DB::select(
            'SELECT h.cdgo_deta_pgo_prvdres, h.nu_cntdad_prvdres, h.de_deta_pgo_prvdres, h.nu_mnto,
                    h.nu_sga, h.nu_orden_srvco, h.de_urls_pgo, h.de_urls_anxos, h.nu_pgos_cntdad,
                    h.in_esta AS in_esta_pgo, h.de_urls_tdr, h.de_urls_siga, h.de_urls_adicional,
                    p.in_esta AS in_esta_prvdres, p.cdgo_dtos_ntrnos
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             INNER JOIN ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p ON h.cdgo_pgo_prvdres = p.cdgo_pgo_prvdres
             WHERE ' . $filtroEsta . ' AND p.cdgo_pgo_prvdres = ? AND p.cdgo_area = ?
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$id, $areaId]
        );

        $n = 0;
        $out = [];
        foreach ($rows as $row) {
            $n++;
            $pendAnexo = (int) ($row->in_esta_prvdres ?? 0) === 10 && trim((string) ($row->de_urls_anxos ?? '')) === '';
            $pendMemo = $this->itemTieneMemoPendiente((int) $row->cdgo_deta_pgo_prvdres);
            $icono = 'ver';
            $titulo = 'Documentación del servicio';
            if ($pendAnexo) {
                $icono = 'anexo';
                $titulo = 'Esperando anexos';
            } elseif ($pendMemo) {
                $icono = 'alerta';
                $titulo = 'Esperando conformidad';
            } elseif (trim((string) ($row->de_urls_pgo ?? '')) === '') {
                $icono = 'alerta';
                $titulo = 'Esperando TDR/SIGA';
            }

            $out[] = [
                'n' => $n,
                'id' => (int) $row->cdgo_deta_pgo_prvdres,
                'cantidad' => $row->nu_cntdad_prvdres !== null ? (int) $row->nu_cntdad_prvdres : 1,
                'descripcion' => (string) ($row->de_deta_pgo_prvdres ?? ''),
                'pagos' => (int) ($row->nu_pgos_cntdad ?? 0),
                'monto' => $row->nu_mnto !== null ? (float) $row->nu_mnto : null,
                'siga' => trim((string) ($row->nu_sga ?? '')),
                'os' => trim((string) ($row->nu_orden_srvco ?? '')),
                'url_tdr' => $this->urlOpcional($row->de_urls_pgo ?? null),
                'url_ane' => $this->urlOpcional($row->de_urls_anxos ?? null),
                'in_esta' => (int) ($row->in_esta_pgo ?? 1),
                'icono' => $icono,
                'titulo_icono' => $titulo,
            ];
        }

        return $out;
    }

    private function itemTieneMemoPendiente(int $itemId): bool
    {
        return DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->where('in_esta', 4)
            ->where(function ($q) {
                $q->whereNull('de_urls_cnfrmdad')->orWhere('de_urls_cnfrmdad', '');
            })
            ->exists();
    }

    private function itemRaw(int $itemId, int $areaId): ?object
    {
        $sistema = $this->schemaSistema;

        return DB::selectOne(
            'SELECT h.*, p.in_esta AS in_esta_prvdres, p.cdgo_dtos_ntrnos, p.cdgo_area,
                    h.in_esta AS in_esta_pgo, u.no_crto
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             INNER JOIN ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p ON h.cdgo_pgo_prvdres = p.cdgo_pgo_prvdres
             LEFT JOIN ' . $sistema . 'p_usrios u ON h.cdgo_usrios = u.cdgo_usrios
             WHERE h.cdgo_deta_pgo_prvdres = ? AND p.cdgo_area = ? AND h.in_esta NOT IN (0)',
            [$itemId, $areaId]
        );
    }

    private function cargoJefeArea(int $areaId): int
    {
        $row = DB::table($this->schemaSistema . 'p_crgos')
            ->where('cdgo_area', $areaId)
            ->where('nu_orde', '1')
            ->orderByDesc('cdgo_crgo')
            ->first(['cdgo_crgo']);

        return (int) ($row->cdgo_crgo ?? 0);
    }

    private function cargoRequiereFirma(): bool
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        if ($cargoId <= 0) {
            return false;
        }

        return (int) DB::table($this->schemaSistema . 'p_crgos')
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
        $file->move($dir, $nombre);

        return $nombre;
    }

    private function esPdf(UploadedFile $file): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return $ext === 'pdf' || str_contains($mime, 'pdf');
    }

    private function nombreFirmado(UploadedFile $file): bool
    {
        return str_ends_with(strtoupper($file->getClientOriginalName()), '[R].PDF');
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

    private function iconoEstado(int $estado): string
    {
        return match ($estado) {
            10 => 'anexo',
            4 => 'alerta',
            2 => 'finalizado',
            default => 'ver',
        };
    }

    private function tituloIcono(int $estado): string
    {
        return match ($estado) {
            10 => 'Subir ANEXOS',
            4 => 'Tiempo cumplido para conformidad',
            2 => 'Finalizado sin terminar el proceso',
            default => 'Sin actividad',
        };
    }

    private function detalleVacio(): array
    {
        return [
            'id' => 0,
            'asunto' => '',
            'locadores' => true,
            'estado' => 1,
            'cdgo_dtos_ntrnos' => 0,
            'documento' => '',
            'editable' => true,
            'readonly' => false,
            'puede_anular' => false,
            'puede_finalizar' => false,
            'puede_generar_tramite' => false,
            'puede_generar_memo' => false,
            'items' => [],
            'nu_items' => 0,
        ];
    }

    private function iniciales(string $nombre): string
    {
        $out = '';
        foreach (preg_split('/\s+/', trim($nombre)) ?: [] as $parte) {
            if ($parte !== '') {
                $out .= mb_strtoupper(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
            }
        }

        return $out;
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
