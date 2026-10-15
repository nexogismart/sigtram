<?php

namespace App\Services\mod_a\sgl;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pago a proveedores — Tesorería
 * (migrado desde ver_requerimiento_tesoreria.php + lista_pago_proveedores_tesoreria.php
 *  + ajax/lista_items_requerimientos_tesoreria.ajax.php + ajax/conformidad_tesoreria.ajax.php
 *  + ver_comprobantes_pago.php en overlay).
 */
class VerRequerimientoTesoreriaService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private const SCHEMA_CAJA = 'caja.';

    private const CAMPOS = [
        'nu_tram_todo' => 'p_dtos_ntrnos.nu_tram_todo',
        'de_area' => 'a_areas.de_area',
        'de_pgo_prvdres' => 'p_pgo_prvdres.de_pgo_prvdres',
    ];

    private readonly string $schemaSistema;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function catalogos(): array
    {
        try {
            $bancos = DB::table($this->schemaSistema . 'a_bncos')
                ->where('in_esta', 1)
                ->orderBy('de_bncos')
                ->get(['cdgo_bncos', 'de_bncos'])
                ->map(fn ($r) => ['id' => (int) $r->cdgo_bncos, 'nombre' => (string) $r->de_bncos])
                ->all();

            $distintos = function (string $col): array {
                $permitidas = ['nu_tpo_cmprbnte_pgo', 'no_bnfcrio', 'no_orden', 'no_dtlle'];
                if (! in_array($col, $permitidas, true)) {
                    return [];
                }
                $rows = DB::select(
                    'SELECT DISTINCT ' . $col . ' AS v FROM ' . self::SCHEMA_CAJA . 'p_cmprbnte_pgo WHERE in_esta = 1 AND ' . $col . ' IS NOT NULL ORDER BY 1'
                );

                return array_values(array_filter(array_map(
                    fn ($r) => trim((string) ($r->v ?? '')),
                    $rows
                )));
            };

            $max = (int) (DB::table(self::SCHEMA_CAJA . 'p_cmprbnte_pgo')->where('in_esta', 1)->max('nu_cmprbnte_pgo') ?? 0);

            return [
                'bancos' => $bancos,
                'tipos' => $distintos('nu_tpo_cmprbnte_pgo'),
                'beneficiarios' => $distintos('no_bnfcrio'),
                'ordenes' => $distintos('no_orden'),
                'detalles' => $distintos('no_dtlle'),
                'siguiente_numero' => $max + 1,
            ];
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_tesoreria] catalogos: ' . $e->getMessage());

            return [
                'bancos' => [],
                'tipos' => [],
                'beneficiarios' => [],
                'ordenes' => [],
                'detalles' => [],
                'siguiente_numero' => 1,
            ];
        }
    }

    public function listar(array $filtros): array
    {
        $campo = (string) ($filtros['campo'] ?? 'nu_tram_todo');
        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $fecha = trim((string) ($filtros['fecha'] ?? ''));
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = 25;
        $offset = ($pagina - 1) * $porPagina;
        $sistema = $this->schemaSistema;

        $from = '
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            LEFT JOIN ' . $sistema . 'p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
            LEFT JOIN ' . $sistema . 'a_tpo_trmtes ON p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte
            LEFT JOIN ' . $sistema . 'a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
            WHERE p_pgo_prvdres.in_esta IN (7)
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
            Log::error('[ver_requerimiento_tesoreria] listar: ' . $e->getMessage());

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
            $documento = trim((string) ($cab->de_tpo_trmte ?? ''));
            $numero = trim((string) ($cab->nu_tram_todo ?? ''));
            $areaDoc = trim((string) ($cab->dc_area ?? ''));
            $items = $this->itemsDeRequerimiento($id);
            $pendientePago = $this->tieneMemoPendientePago($id);

            return $this->ok([
                'id' => (int) $cab->cdgo_pgo_prvdres,
                'asunto' => (string) ($cab->de_pgo_prvdres ?? ''),
                'area' => (string) ($cab->de_area ?? ''),
                'estado' => $estado,
                'cdgo_dtos_ntrnos' => (int) ($cab->cdgo_dtos_ntrnos ?? 0),
                'documento' => $documento !== ''
                    ? trim($documento . ' N° ' . $numero . ($areaDoc !== '' ? '-' . $areaDoc : ''))
                    : '',
                'puede_terminar' => $estado === 7 && ! $pendientePago,
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_tesoreria] detalle: ' . $e->getMessage());

            return $this->fail('No se pudo cargar el requerimiento.');
        }
    }

    public function itemDetalle(int $itemId): array
    {
        $item = $this->itemRaw($itemId);
        if ($item === null) {
            return $this->fail('No se encontró el ítem.');
        }

        return $this->ok([
            'id' => (int) $item->cdgo_deta_pgo_prvdres,
            'pago_id' => (int) $item->cdgo_pgo_prvdres,
            'servicio' => (string) ($item->de_deta_pgo_prvdres ?? ''),
            'siga' => (string) ($item->nu_sga ?? ''),
            'os' => (string) ($item->nu_orden_srvco ?? ''),
            'url_os' => $this->urlOpcional($item->de_urls_os ?? null),
            'rubro' => (string) ($item->nu_rbro ?? ''),
            'siaf' => (string) ($item->nu_siaf ?? ''),
            'nombre' => (string) ($item->no_crto ?? ''),
            'memos' => $this->memosDeItem($itemId, $item),
        ]);
    }

    public function terminar(int $id): array
    {
        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if ((int) ($cab->in_esta_prvdres ?? 0) !== 7) {
            return $this->fail('El requerimiento no está en estado para concluir el pago.');
        }
        if ($this->tieneMemoPendientePago($id)) {
            return $this->fail('Hay pagos sin comprobante. Registre el comprobante antes de terminar.');
        }

        try {
            return DB::transaction(function () use ($id) {
                $memos = DB::select(
                    'SELECT m.cdgo_deta_pgo_mmos
                     FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
                     INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
                        ON m.cdgo_deta_pgo_prvdres = h.cdgo_deta_pgo_prvdres
                     WHERE h.cdgo_pgo_prvdres = ?
                       AND m.in_esta = 5
                       AND m.de_urls_cnfrmdad IS NOT NULL',
                    [$id]
                );

                foreach ($memos as $memo) {
                    DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                        ->where('cdgo_deta_pgo_mmos', (int) $memo->cdgo_deta_pgo_mmos)
                        ->update(['in_esta' => 7]);
                }

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->update(['in_esta' => 7]);

                return $this->ok(['id' => $id], 'Se concluyó el proceso de pago.');
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_tesoreria] terminar: ' . $e->getMessage());

            return $this->fail('No se pudo concluir el proceso.');
        }
    }

    public function formComprobante(int $memoId): array
    {
        $memo = $this->memoRaw($memoId);
        if ($memo === null) {
            return $this->fail('No se encontró el cronograma de pago.');
        }

        $inEsta = (int) ($memo->in_esta_mmos ?? 0);
        $dvngdo = (int) ($memo->cdgo_dtos_ntrnos_dvngdo ?? 0);
        if ($dvngdo <= 0 || ($inEsta !== 7 && $inEsta !== 8)) {
            return $this->fail('El memorandum aún no está listo para registrar el comprobante.');
        }

        $catalogos = $this->catalogos();
        $tramite = trim((string) ($memo->de_tpo_trmte_dvngdo ?? 'MEMORANDUM'));
        $numero = trim((string) ($memo->nu_tram_todo_dvngdo ?? ''));
        $area = trim((string) ($memo->dc_area_dvngdo ?? ''));

        return $this->ok([
            'memo_id' => $memoId,
            'tramite_id' => $dvngdo,
            'tramite' => trim($tramite . ' N° ' . $numero . ($area !== '' ? '-' . $area : '')),
            'nu_cmprbnte_pgo' => $catalogos['siguiente_numero'],
            'fe_cmprbnte_pgo' => date('Y-m-d'),
            'cdgo_bncos' => 0,
            'nu_siaf' => (string) ($memo->nu_siaf ?? ''),
            'nu_tpo_cmprbnte_pgo' => '',
            'nu_co' => '',
            'no_bnfcrio' => (string) ($memo->no_crto ?? ''),
            'no_rb' => (string) ($memo->nu_rbro ?? ''),
            'no_tr' => '',
            'nu_imprte' => $memo->nu_mnto_sldo !== null ? (float) $memo->nu_mnto_sldo : null,
            'no_orden' => '',
            'no_dtlle' => '',
            'ya_pagado' => ! empty($memo->cdgo_cmprbnte_pgo),
            'catalogos' => $catalogos,
        ]);
    }

    public function guardarComprobante(array $datos): array
    {
        $memoId = (int) ($datos['memo_id'] ?? 0);
        $tramiteId = (int) ($datos['tramite_id'] ?? 0);
        $bancoId = (int) ($datos['cdgo_bncos'] ?? 0);
        $numero = trim((string) ($datos['nu_cmprbnte_pgo'] ?? ''));
        $fecha = trim((string) ($datos['fe_cmprbnte_pgo'] ?? ''));
        $tipo = mb_strtoupper(trim((string) ($datos['nu_tpo_cmprbnte_pgo'] ?? '')), 'UTF-8');
        $siaf = mb_strtoupper(trim((string) ($datos['nu_siaf'] ?? '')), 'UTF-8');
        $beneficiario = mb_strtoupper(trim((string) ($datos['no_bnfcrio'] ?? '')), 'UTF-8');
        $rubro = mb_strtoupper(trim((string) ($datos['no_rb'] ?? '')), 'UTF-8');
        $tr = mb_strtoupper(trim((string) ($datos['no_tr'] ?? '')), 'UTF-8');
        $importe = (float) ($datos['nu_imprte'] ?? 0);
        $orden = mb_strtoupper(trim((string) ($datos['no_orden'] ?? '')), 'UTF-8');
        $detalle = mb_strtoupper(trim((string) ($datos['no_dtlle'] ?? '')), 'UTF-8');
        $co = mb_strtoupper(trim((string) ($datos['nu_co'] ?? '')), 'UTF-8');

        if ($memoId <= 0 || $tramiteId <= 0) {
            return $this->fail('Falta el memorandum de pago.');
        }
        if ($bancoId <= 0) {
            return $this->fail('Seleccione el banco.');
        }
        if ($numero === '' || $fecha === '' || $tipo === '' || $siaf === '' || $beneficiario === '' || $rubro === '' || $tr === '' || $orden === '' || $detalle === '') {
            return $this->fail('Complete todos los datos del comprobante.');
        }
        if ($importe <= 0) {
            return $this->fail('El importe debe ser mayor a cero.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            return $this->fail('La fecha del comprobante no es válida.');
        }

        $memo = $this->memoRaw($memoId);
        if ($memo === null) {
            return $this->fail('No se encontró el cronograma de pago.');
        }

        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);

        try {
            return DB::transaction(function () use (
                $memo, $memoId, $tramiteId, $bancoId, $numero, $fecha, $tipo, $co, $siaf,
                $beneficiario, $rubro, $tr, $importe, $orden, $detalle, $userId
            ) {
                $id = (int) DB::table(self::SCHEMA_CAJA . 'p_cmprbnte_pgo')->insertGetId([
                    'cdgo_bncos' => $bancoId,
                    'cdgo_dtos_ntrnos' => $tramiteId,
                    'cdgo_deta_pgo_mmos' => $memoId,
                    'fe_cmprbnte_pgo' => $fecha,
                    'nu_cmprbnte_pgo' => (int) $numero,
                    'nu_tpo_cmprbnte_pgo' => $tipo,
                    'nu_co' => $co,
                    'nu_siaf' => $siaf,
                    'no_bnfcrio' => $beneficiario,
                    'no_rb' => $rubro,
                    'no_tr' => $tr,
                    'nu_imprte' => $importe,
                    'no_orden' => $orden,
                    'no_dtlle' => $detalle,
                    'ts_usua_modi' => now(),
                    'co_usua_modi' => $userId,
                    'in_esta' => '1',
                ], 'cdgo_cmprbnte_pgo');

                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_mmos', $memoId)
                    ->update([
                        'cdgo_cmprbnte_pgo' => $id,
                        'in_esta' => 8,
                    ]);

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', (int) $memo->cdgo_pgo_prvdres)
                    ->update(['in_esta' => 8]);

                return $this->ok(['id' => (int) $memo->cdgo_pgo_prvdres], 'Se generó el comprobante de pago.');
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_tesoreria] guardarComprobante: ' . $e->getMessage());

            return $this->fail('No se pudo generar el comprobante.');
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

    private function cabecera(int $id): ?object
    {
        $sistema = $this->schemaSistema;

        return DB::selectOne(
            'SELECT p.cdgo_pgo_prvdres, p.de_pgo_prvdres, p.in_esta AS in_esta_prvdres,
                    p.cdgo_dtos_ntrnos, n.nu_tram_todo, a.dc_area, a.de_area, t.de_tpo_trmte
             FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON p.cdgo_dtos_ntrnos = n.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_tpo_trmtes t ON n.cdgo_tpo_trmte = t.cdgo_tpo_trmte
             LEFT JOIN ' . $sistema . 'a_areas a ON p.cdgo_area = a.cdgo_area
             WHERE p.in_esta NOT IN (0) AND p.cdgo_pgo_prvdres = ?',
            [$id]
        );
    }

    private function itemsDeRequerimiento(int $id): array
    {
        $rows = DB::select(
            'SELECT h.cdgo_deta_pgo_prvdres, h.nu_cntdad_prvdres, h.de_deta_pgo_prvdres, h.nu_mnto,
                    h.nu_sga, h.de_urls_pgo, h.de_urls_anxos, h.nu_pgos_cntdad,
                    h.in_esta AS in_esta_pgo
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             WHERE h.in_esta NOT IN (0) AND h.cdgo_pgo_prvdres = ?
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$id]
        );

        $n = 0;
        $out = [];
        $marcarEstado7 = false;
        foreach ($rows as $row) {
            $n++;
            $urlTdr = $this->urlOpcional($row->de_urls_pgo ?? null);
            $urlAne = $this->urlOpcional($row->de_urls_anxos ?? null);
            $pendiente = $this->itemTieneMemoPendiente((int) $row->cdgo_deta_pgo_prvdres);
            if ($pendiente) {
                $marcarEstado7 = true;
            }
            $out[] = [
                'n' => $n,
                'id' => (int) $row->cdgo_deta_pgo_prvdres,
                'cantidad' => $row->nu_cntdad_prvdres !== null ? (int) $row->nu_cntdad_prvdres : null,
                'descripcion' => (string) ($row->de_deta_pgo_prvdres ?? ''),
                'pagos' => (int) ($row->nu_pgos_cntdad ?? 0),
                'monto' => $row->nu_mnto !== null ? (float) $row->nu_mnto : null,
                'siga' => trim((string) ($row->nu_sga ?? '')),
                'url_tdr' => $urlTdr,
                'url_ane' => $urlAne,
                'in_esta' => (int) ($row->in_esta_pgo ?? 1),
                'pendiente_pago' => $pendiente,
                'puede_ver_con' => $urlTdr !== null && $urlAne !== null,
            ];
        }

        if ($marcarEstado7) {
            DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                ->where('cdgo_pgo_prvdres', $id)
                ->update(['in_esta' => 7]);
        }

        return $out;
    }

    private function itemRaw(int $itemId): ?object
    {
        $sistema = $this->schemaSistema;

        return DB::selectOne(
            'SELECT h.*, h.in_esta AS in_esta_pgo, u.no_crto
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
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
                    m.in_esta AS in_esta_mmos, m.cdgo_dtos_ntrnos_dvngdo, m.cdgo_cmprbnte_pgo,
                    n.nu_tram_todo AS nu_tram_todo_dvngdo, n.fe_ingrso, a.dc_area AS dc_area_dvngdo
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON m.cdgo_dtos_ntrnos_dvngdo = n.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_areas a ON n.cdgo_area = a.cdgo_area
             WHERE m.cdgo_deta_pgo_prvdres = ? AND m.in_esta NOT IN (0)
             ORDER BY m.cdgo_deta_pgo_mmos ASC',
            [$itemId]
        );

        $out = [];
        foreach ($rows as $i => $row) {
            $dias = (int) ($row->nu_dia_pgo ?? 0);
            $fechaConf = null;
            if (! empty($item->fe_orden_srvco)) {
                $fechaConf = date('Y-m-d', strtotime((string) $item->fe_orden_srvco . ' +' . $dias . ' days'));
                DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                    ->where('cdgo_deta_pgo_mmos', (int) $row->cdgo_deta_pgo_mmos)
                    ->update(['fe_pgo_aprxmdo' => $row->fe_ingrso ?: $fechaConf]);
            }

            $inEsta = (int) ($row->in_esta_mmos ?? 0);
            $dvngdo = (int) ($row->cdgo_dtos_ntrnos_dvngdo ?? 0);

            $out[] = [
                'n' => $i + 1,
                'id' => (int) $row->cdgo_deta_pgo_mmos,
                'documento' => 'MEMORANDUM N° ' . trim((string) ($row->nu_tram_todo_dvngdo ?? '')) . '-' . trim((string) ($row->dc_area_dvngdo ?? '')),
                'monto' => $row->nu_mnto_sldo !== null ? number_format((float) $row->nu_mnto_sldo, 2, '.', '') : null,
                'fecha' => $this->fechaCorta($row->fe_ingrso ?? $fechaConf),
                'url' => $this->urlOpcional($row->de_urls_cnfrmdad ?? null),
                'puede_pagar' => $dvngdo > 0 && ($inEsta === 7 || $inEsta === 8),
            ];
        }

        return $out;
    }

    private function memoRaw(int $memoId): ?object
    {
        $sistema = $this->schemaSistema;

        return DB::selectOne(
            'SELECT m.cdgo_deta_pgo_mmos, m.in_esta AS in_esta_mmos, m.cdgo_dtos_ntrnos_dvngdo,
                    m.cdgo_cmprbnte_pgo, m.nu_mnto_sldo, h.cdgo_pgo_prvdres, h.nu_siaf, h.nu_rbro,
                    u.no_crto, n.nu_tram_todo AS nu_tram_todo_dvngdo, t.de_tpo_trmte AS de_tpo_trmte_dvngdo,
                    a.dc_area AS dc_area_dvngdo
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
             INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h ON m.cdgo_deta_pgo_prvdres = h.cdgo_deta_pgo_prvdres
             LEFT JOIN ' . $sistema . 'p_usrios u ON h.cdgo_usrios = u.cdgo_usrios
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON m.cdgo_dtos_ntrnos_dvngdo = n.cdgo_dtos_ntrnos
             LEFT JOIN ' . $sistema . 'a_tpo_trmtes t ON n.cdgo_tpo_trmte = t.cdgo_tpo_trmte
             LEFT JOIN ' . $sistema . 'a_areas a ON n.cdgo_area = a.cdgo_area
             WHERE m.cdgo_deta_pgo_mmos = ? AND m.in_esta NOT IN (0)',
            [$memoId]
        );
    }

    private function itemTieneMemoPendiente(int $itemId): bool
    {
        return DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
            ->where('cdgo_deta_pgo_prvdres', $itemId)
            ->where('in_esta', 7)
            ->whereNull('cdgo_cmprbnte_pgo')
            ->exists();
    }

    private function tieneMemoPendientePago(int $pagoId): bool
    {
        return DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
            ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
            ->where('h.cdgo_pgo_prvdres', $pagoId)
            ->where('m.in_esta', 7)
            ->whereNull('m.cdgo_cmprbnte_pgo')
            ->exists();
    }

    private function detalleVacio(): array
    {
        return [
            'id' => 0,
            'asunto' => '',
            'area' => '',
            'estado' => 0,
            'cdgo_dtos_ntrnos' => 0,
            'documento' => '',
            'puede_terminar' => false,
            'items' => [],
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

    private function ok(mixed $data, string $message = ''): array
    {
        return ['success' => true, 'data' => $data, 'message' => $message];
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'error' => $message, 'message' => $message];
    }
}
