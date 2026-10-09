<?php

namespace App\Services\mod_a\sgl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lógica de negocio del módulo de SOLO CONSULTA "Buscar Requerimiento de Pago"
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago.php
 * y lista_pago_proveedores_reporte.php / lista_pago_proveedores.php).
 *
 * No realiza ningún INSERT/UPDATE/DELETE: únicamente lista y muestra el detalle
 * de los requerimientos de pago a proveedores ya existentes en logistica.p_pgo_prvdres.
 */
class BuscarRequerimientoPagoService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private const SCHEMA_CAJA = 'caja.';

    /** Campos de búsqueda permitidos (whitelist) para evitar inyección vía nombre de columna. */
    private const CAMPOS_PERMITIDOS = [
        'nu_tram_todo' => 'p_dtos_ntrnos.nu_tram_todo',
        'de_pgo_prvdres' => 'p_pgo_prvdres.de_pgo_prvdres',
    ];

    private readonly string $schemaSistema;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * Catálogo de áreas para el filtro (excluye áreas administrativas 1, 31, 32 y 0).
     */
    public function catalogoAreas(): array
    {
        try {
            $rows = DB::select(
                "SELECT cdgo_area, de_area FROM {$this->schemaSistema}a_areas
                 WHERE in_esta = '1' AND cdgo_area NOT IN (1, 31, 32, 0)
                 ORDER BY cdgo_area"
            );

            return $this->ok(array_map(fn ($row) => [
                'id' => (int) $row->cdgo_area,
                'nombre' => trim((string) ($row->de_area ?? '')),
            ], $rows));
        } catch (Throwable $e) {
            Log::error('[buscar_requerimiento_pago] catalogoAreas: ' . $e->getMessage());

            return $this->fail('No se pudo obtener el catálogo de áreas.');
        }
    }

    /**
     * Lista los requerimientos de pago a proveedores (equivalente a gene_pago_proveedor()
     * + los filtros de lista_pago_proveedores_reporte.php).
     *
     * Filtros esperados: area_id, campo (nu_tram_todo|de_pgo_prvdres), criterio, fecha (Y-m-d),
     * pagina, porPagina.
     */
    public function listar(array $filtros): array
    {
        $areaId = (int) ($filtros['area_id'] ?? 0);
        $campo = (string) ($filtros['campo'] ?? '');
        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $fecha = trim((string) ($filtros['fecha'] ?? ''));
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = (int) ($filtros['porPagina'] ?? 25);
        $porPagina = ($porPagina > 0 && $porPagina <= 100) ? $porPagina : 25;
        $offset = ($pagina - 1) * $porPagina;

        $sistema = $this->schemaSistema;

        $from = '
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            LEFT JOIN ' . $sistema . 'p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
            LEFT JOIN ' . $sistema . 'a_tpo_trmtes ON p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte
            LEFT JOIN ' . $sistema . 'a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
            WHERE p_pgo_prvdres.in_esta NOT IN (0, 2) AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
        ';
        $params = [];

        if ($areaId > 0) {
            $from .= ' AND p_pgo_prvdres.cdgo_area = ?';
            $params[] = $areaId;
        }

        if ($criterio !== '' && isset(self::CAMPOS_PERMITIDOS[$campo])) {
            $columna = self::CAMPOS_PERMITIDOS[$campo];
            $from .= " AND {$columna} ILIKE ?";
            $params[] = '%' . $criterio . '%';
        }

        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            $from .= ' AND p_pgo_prvdres.fe_pgo_prvdres BETWEEN ? AND ?';
            $params[] = $fecha . ' 00:00:00';
            $params[] = $fecha . ' 23:59:59';
        }

        try {
            $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $from, $params)->total ?? 0);

            $sql = '
                SELECT p_pgo_prvdres.cdgo_pgo_prvdres,
                    p_pgo_prvdres.fe_pgo_prvdres,
                    p_pgo_prvdres.cdgo_dtos_ntrnos,
                    p_dtos_ntrnos.nu_tram_todo,
                    p_dtos_ntrnos.fe_ingrso,
                    a_tpo_trmtes.dc_tpo_trmte,
                    p_pgo_prvdres.cdgo_area,
                    a_areas.dc_area,
                    a_areas.de_area,
                    (SELECT COUNT(*) FROM ' . self::SCHEMA_LOGISTICA . "h_deta_pgo_prvdres h
                        WHERE h.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres AND h.in_esta <> 0) AS cantidad_items
                {$from}
                ORDER BY p_pgo_prvdres.cdgo_pgo_prvdres DESC
                LIMIT ? OFFSET ?
            ";
            $rows = DB::select($sql, array_merge($params, [$porPagina, $offset]));

            $items = array_map(fn ($row) => [
                'id' => (int) $row->cdgo_pgo_prvdres,
                'documento' => trim(trim((string) ($row->dc_tpo_trmte ?? '')) . ' N° ' . trim((string) ($row->nu_tram_todo ?? ''))),
                'dc_area' => trim((string) ($row->dc_area ?? '')),
                'de_area' => trim((string) ($row->de_area ?? '')),
                'fecha' => $this->fechaPeru($row->fe_ingrso ?: $row->fe_pgo_prvdres),
                'cantidad_items' => (int) $row->cantidad_items,
            ], $rows);

            return $this->ok([
                'items' => $items,
                'pagination' => [
                    'page' => $pagina,
                    'per_page' => $porPagina,
                    'total' => $total,
                    'total_pages' => max(1, (int) ceil($total / $porPagina)),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('[buscar_requerimiento_pago] listar: ' . $e->getMessage());

            return $this->fail('No se pudo listar los requerimientos de pago.');
        }
    }

    /**
     * Cabecera del requerimiento (equivalente a la primera fila de gene_detalle_pago_proveedor()).
     */
    public function detalle(int $id): array
    {
        if ($id <= 0) {
            return $this->fail('Identificador no válido.');
        }

        try {
            $row = $this->cabeceraRaw($id);
            if (! $row) {
                return $this->fail('Requerimiento de pago no encontrado.');
            }

            $dcArea = trim((string) ($row->dc_area ?? ''));
            $documento = trim(trim((string) ($row->de_tpo_trmte ?? '')) . ' N° ' . trim((string) ($row->nu_tram_todo ?? '')));
            if ($dcArea !== '') {
                $documento .= '-' . $dcArea;
            }

            return $this->ok([
                'id' => $id,
                'documento' => $documento,
                'asunto' => (string) ($row->de_pgo_prvdres ?? ''),
                'de_area' => trim((string) ($row->de_area ?? '')),
                'cdgo_dtos_ntrnos' => $row->cdgo_dtos_ntrnos !== null ? (int) $row->cdgo_dtos_ntrnos : null,
                'nu_pgos_cntdad' => (int) ($row->nu_pgos_cntdad ?? 0),
            ]);
        } catch (Throwable $e) {
            Log::error('[buscar_requerimiento_pago] detalle: ' . $e->getMessage());

            return $this->fail('No se pudo obtener el detalle del requerimiento.');
        }
    }

    /**
     * Seguimiento del pago: para cada pago (1..nu_pgos_cntdad) devuelve las celdas
     * origen/abastecimiento/contabilidad/tesoreria (equivalente a gene_reporte_pago_proveedor()
     * + el estado de tesorería vía caja.p_cmprbnte_pgo).
     */
    public function seguimientoPagos(int $id): array
    {
        if ($id <= 0) {
            return $this->fail('Identificador no válido.');
        }

        try {
            $cabecera = $this->cabeceraRaw($id);
            $nuPagos = (int) ($cabecera->nu_pgos_cntdad ?? 0);
            $sistema = $this->schemaSistema;
            $pagos = [];

            for ($i = 1; $i <= $nuPagos; $i++) {
                $sql = '
                    SELECT DISTINCT
                        h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad,
                        p_dtos_ntrnos_cnfrmdad.nu_tram_todo AS nu_tram_todo_cnfrmdad,
                        p_dtos_ntrnos_cnfrmdad.fe_ingrso AS fe_ingrso_cnfrmdad,
                        a_tpo_trmtes_cnfrmdad.dc_tpo_trmte AS dc_tpo_trmte_cnfrmdad,
                        a_areas_cnfrmdad.dc_area AS dc_area_cnfrmdad,
                        h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngar,
                        p_dtos_ntrnos_dvngar.nu_tram_todo AS nu_tram_todo_dvngar,
                        p_dtos_ntrnos_dvngar.fe_ingrso AS fe_ingrso_dvngar,
                        a_tpo_trmtes_dvngar.dc_tpo_trmte AS dc_tpo_trmte_dvngar,
                        a_areas_dvngar.dc_area AS dc_area_dvngar,
                        h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngdo,
                        h_deta_pgo_mmos.fe_pgo_aprxmdo,
                        p_dtos_ntrnos_dvngdo.nu_tram_todo AS nu_tram_todo_dvngdo,
                        p_dtos_ntrnos_dvngdo.fe_ingrso AS fe_ingrso_dvngdo,
                        a_tpo_trmtes_dvngdo.dc_tpo_trmte AS dc_tpo_trmte_dvngdo,
                        a_areas_dvngdo.dc_area AS dc_area_dvngdo,
                        p_cmprbnte_pgo.nu_cmprbnte_pgo,
                        p_cmprbnte_pgo.fe_cmprbnte_pgo
                    FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
                    INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres ON p_pgo_prvdres.cdgo_pgo_prvdres = h_deta_pgo_prvdres.cdgo_pgo_prvdres
                    LEFT JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos ON h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres = h_deta_pgo_mmos.cdgo_deta_pgo_prvdres
                    LEFT JOIN ' . $sistema . 'p_dtos_ntrnos p_dtos_ntrnos_cnfrmdad ON h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad = p_dtos_ntrnos_cnfrmdad.cdgo_dtos_ntrnos
                    LEFT JOIN ' . $sistema . 'a_areas a_areas_cnfrmdad ON p_dtos_ntrnos_cnfrmdad.cdgo_area = a_areas_cnfrmdad.cdgo_area
                    LEFT JOIN ' . $sistema . 'a_tpo_trmtes a_tpo_trmtes_cnfrmdad ON p_dtos_ntrnos_cnfrmdad.cdgo_tpo_trmte = a_tpo_trmtes_cnfrmdad.cdgo_tpo_trmte
                    LEFT JOIN ' . $sistema . 'p_dtos_ntrnos p_dtos_ntrnos_dvngdo ON h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngdo = p_dtos_ntrnos_dvngdo.cdgo_dtos_ntrnos
                    LEFT JOIN ' . $sistema . 'a_areas a_areas_dvngdo ON p_dtos_ntrnos_dvngdo.cdgo_area = a_areas_dvngdo.cdgo_area
                    LEFT JOIN ' . $sistema . 'a_tpo_trmtes a_tpo_trmtes_dvngdo ON p_dtos_ntrnos_dvngdo.cdgo_tpo_trmte = a_tpo_trmtes_dvngdo.cdgo_tpo_trmte
                    LEFT JOIN ' . $sistema . 'p_dtos_ntrnos p_dtos_ntrnos_dvngar ON h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngar = p_dtos_ntrnos_dvngar.cdgo_dtos_ntrnos
                    LEFT JOIN ' . $sistema . 'a_areas a_areas_dvngar ON p_dtos_ntrnos_dvngar.cdgo_area = a_areas_dvngar.cdgo_area
                    LEFT JOIN ' . $sistema . 'a_tpo_trmtes a_tpo_trmtes_dvngar ON p_dtos_ntrnos_dvngar.cdgo_tpo_trmte = a_tpo_trmtes_dvngar.cdgo_tpo_trmte
                    LEFT JOIN ' . self::SCHEMA_CAJA . 'p_cmprbnte_pgo ON h_deta_pgo_mmos.cdgo_cmprbnte_pgo = p_cmprbnte_pgo.cdgo_cmprbnte_pgo
                    WHERE p_pgo_prvdres.in_esta NOT IN (0)
                        AND h_deta_pgo_mmos.in_esta NOT IN (0)
                        AND h_deta_pgo_prvdres.in_esta = 1
                        AND h_deta_pgo_mmos.nu_pgo_mmos = ?
                        AND p_pgo_prvdres.cdgo_pgo_prvdres = ?
                ';
                $rows = DB::select($sql, [$i, $id]);

                $filas = array_map(function ($row) {
                    $dcCnfrmdad = trim((string) ($row->dc_tpo_trmte_cnfrmdad ?? ''));
                    $origen = $dcCnfrmdad !== ''
                        ? $dcCnfrmdad . ' ' . trim((string) $row->nu_tram_todo_cnfrmdad) . '-' . str_replace('.', '', (string) ($row->dc_area_cnfrmdad ?? '')) . ' (Fecha: ' . $this->fechaPeru($row->fe_ingrso_cnfrmdad) . ')'
                        : 'Fecha a presentar: ' . $this->fechaPeru($row->fe_pgo_aprxmdo);

                    $dcDvngar = trim((string) ($row->dc_tpo_trmte_dvngar ?? ''));
                    $abastecimiento = $dcDvngar !== ''
                        ? $dcDvngar . ' ' . trim((string) $row->nu_tram_todo_dvngar) . '-' . str_replace('.', '', (string) ($row->dc_area_dvngar ?? '')) . ' (Fecha: ' . $this->fechaPeru($row->fe_ingrso_dvngar) . ')'
                        : '-';

                    $dcDvngdo = trim((string) ($row->dc_tpo_trmte_dvngdo ?? ''));
                    $contabilidad = $dcDvngdo !== ''
                        ? $dcDvngdo . ' ' . trim((string) $row->nu_tram_todo_dvngdo) . '-' . str_replace('.', '', (string) ($row->dc_area_dvngdo ?? '')) . ' (Fecha: ' . $this->fechaPeru($row->fe_ingrso_dvngdo) . ')'
                        : '-';

                    $tesoreria = ! empty($row->nu_cmprbnte_pgo)
                        ? 'PAGADO (Fecha: ' . $this->fechaPeru($row->fe_cmprbnte_pgo) . ')'
                        : '-';

                    return [
                        'origen' => $origen,
                        'abastecimiento' => $abastecimiento,
                        'contabilidad' => $contabilidad,
                        'tesoreria' => $tesoreria,
                    ];
                }, $rows);

                $pagos[] = [
                    'pago' => $i,
                    'total_pagos' => $nuPagos,
                    'filas' => $filas,
                ];
            }

            return $this->ok($pagos);
        } catch (Throwable $e) {
            Log::error('[buscar_requerimiento_pago] seguimientoPagos: ' . $e->getMessage());

            return $this->fail('No se pudo obtener el seguimiento de pagos.');
        }
    }

    /**
     * Lista los items del requerimiento (equivalente a gene_detalle_pago_proveedor()
     * usado por lista_items_requerimientos_contabilidad.ajax.php), sin la acción "CON"
     * (conformidad), que no aplica a un módulo de solo consulta.
     */
    public function listarItems(int $id): array
    {
        if ($id <= 0) {
            return $this->fail('Identificador no válido.');
        }

        try {
            $sql = '
                SELECT h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres,
                    h_deta_pgo_prvdres.nu_cntdad_prvdres,
                    h_deta_pgo_prvdres.de_deta_pgo_prvdres,
                    h_deta_pgo_prvdres.nu_mnto,
                    h_deta_pgo_prvdres.nu_sga,
                    h_deta_pgo_prvdres.nu_orden_srvco,
                    h_deta_pgo_prvdres.de_urls_pgo,
                    h_deta_pgo_prvdres.de_urls_anxos,
                    h_deta_pgo_prvdres.nu_pgos_cntdad
                FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
                INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres ON p_pgo_prvdres.cdgo_pgo_prvdres = h_deta_pgo_prvdres.cdgo_pgo_prvdres
                WHERE h_deta_pgo_prvdres.in_esta <> 0 AND p_pgo_prvdres.cdgo_pgo_prvdres = ?
                ORDER BY h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres ASC
            ';
            $rows = DB::select($sql, [$id]);

            $n = 0;
            $items = array_map(function ($row) use (&$n) {
                $n++;

                return [
                    'n' => $n,
                    'cantidad' => $row->nu_cntdad_prvdres !== null ? (int) $row->nu_cntdad_prvdres : null,
                    'descripcion' => (string) ($row->de_deta_pgo_prvdres ?? ''),
                    'pagos' => (int) ($row->nu_pgos_cntdad ?? 0),
                    'monto' => $row->nu_mnto !== null ? (float) $row->nu_mnto : null,
                    'siga' => $row->nu_sga !== null ? trim((string) $row->nu_sga) : '',
                    'os' => $row->nu_orden_srvco !== null ? trim((string) $row->nu_orden_srvco) : '',
                    'url_tdr' => $row->de_urls_pgo !== null && trim((string) $row->de_urls_pgo) !== '' ? trim((string) $row->de_urls_pgo) : null,
                    'url_ane' => $row->de_urls_anxos !== null && trim((string) $row->de_urls_anxos) !== '' ? trim((string) $row->de_urls_anxos) : null,
                ];
            }, $rows);

            return $this->ok($items);
        } catch (Throwable $e) {
            Log::error('[buscar_requerimiento_pago] listarItems: ' . $e->getMessage());

            return $this->fail('No se pudo listar los items del requerimiento.');
        }
    }

    /**
     * Cabecera cruda (equivalente a la primera fila de gene_detalle_pago_proveedor()),
     * compartida entre detalle() y seguimientoPagos().
     */
    private function cabeceraRaw(int $id): ?object
    {
        $sistema = $this->schemaSistema;

        $sql = '
            SELECT p_pgo_prvdres.cdgo_pgo_prvdres,
                p_pgo_prvdres.de_pgo_prvdres,
                p_pgo_prvdres.cdgo_dtos_ntrnos,
                h_deta_pgo_prvdres.nu_pgos_cntdad,
                p_dtos_ntrnos.nu_tram_todo,
                p_dtos_ntrnos.cdgo_area,
                a_areas.dc_area,
                a_areas.de_area,
                a_tpo_trmtes.de_tpo_trmte
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres ON p_pgo_prvdres.cdgo_pgo_prvdres = h_deta_pgo_prvdres.cdgo_pgo_prvdres
            LEFT JOIN ' . $sistema . 'p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
            LEFT JOIN ' . $sistema . 'a_tpo_trmtes ON p_dtos_ntrnos.cdgo_tpo_trmte = a_tpo_trmtes.cdgo_tpo_trmte
            LEFT JOIN ' . $sistema . 'a_areas ON p_dtos_ntrnos.cdgo_area = a_areas.cdgo_area
            WHERE h_deta_pgo_prvdres.in_esta <> 0 AND p_pgo_prvdres.cdgo_pgo_prvdres = ?
            ORDER BY h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres ASC
            LIMIT 1
        ';

        return DB::selectOne($sql, [$id]);
    }

    /**
     * Histórico del trámite interno (equivalente a segi_tramite.php).
     * Columnas: Entrada | T.E | Área salida | Estado | Área envío | Obs. | U | A | D
     */
    public function historialTramite(int $cdgoDtosNtrnos): array
    {
        try {
            if ($cdgoDtosNtrnos <= 0) {
                return $this->fail('Trámite no indicado.');
            }

            $sistema = $this->schemaSistema;

            $cabecera = DB::selectOne(
                "SELECT p.nu_tram_todo, t.de_tpo_trmte, a.dc_area
                 FROM {$sistema}p_dtos_ntrnos p
                 LEFT JOIN {$sistema}a_tpo_trmtes t ON p.cdgo_tpo_trmte = t.cdgo_tpo_trmte
                 LEFT JOIN {$sistema}a_areas a ON p.cdgo_area = a.cdgo_area
                 WHERE p.cdgo_dtos_ntrnos = ?",
                [$cdgoDtosNtrnos]
            );

            if (! $cabecera) {
                return $this->fail('Trámite no encontrado.');
            }

            $rows = DB::select(
                "SELECT r.cdgo_trmtes_ntrnos,
                        r.fe_rcbdo,
                        r.de_obser_ntrnos,
                        r.de_urls_agrdo,
                        r.in_esta,
                        r.cdgo_lgin,
                        r.cdgo_crgo_sa,
                        r.cdgo_crgo_en,
                        a_sa.de_area AS de_area_sa,
                        a_sa.dc_area AS dc_area_sa,
                        a_en.de_area AS de_area_en,
                        a_en.dc_area AS dc_area_en,
                        e.dc_estdo_trmte,
                        p.fe_ingrso,
                        u_orig.no_crto AS no_crto_origen
                 FROM {$sistema}r_trmtes_ntrnos r
                 INNER JOIN {$sistema}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
                 INNER JOIN {$sistema}a_areas a_sa ON c_sa.cdgo_area = a_sa.cdgo_area
                 INNER JOIN {$sistema}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
                 INNER JOIN {$sistema}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
                 INNER JOIN {$sistema}a_estdos_trmtes e ON r.cdgo_estdo_trmte = e.cdgo_estdo_trmte
                 INNER JOIN {$sistema}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
                 LEFT JOIN {$sistema}p_lgin l ON r.cdgo_lgin = l.cdgo_lgin
                 LEFT JOIN {$sistema}p_usrios u_orig ON l.cdgo_usrios = u_orig.cdgo_usrios
                 WHERE p.cdgo_dtos_ntrnos = ?
                 ORDER BY r.cdgo_trmtes_ntrnos DESC",
                [$cdgoDtosNtrnos]
            );

            // T.E. se calcula respecto al movimiento siguiente en el tiempo (fila anterior en este ORDER DESC)
            $ahora = now('America/Lima')->format('Y-m-d H:i:s');
            $fePasa = null;
            $items = [];

            foreach ($rows as $row) {
                $feRcbdo = $row->fe_rcbdo ? (string) $row->fe_rcbdo : null;
                $estado = strtoupper(trim((string) ($row->dc_estdo_trmte ?? '')));
                $inEsta = (string) ($row->in_esta ?? '');

                if ($inEsta === '0' && $fePasa !== null && $feRcbdo !== null) {
                    $te = $this->tiempoEstimado($feRcbdo, $fePasa);
                } elseif ($inEsta === '1' && (str_contains($estado, 'FINALIZADO') || str_contains($estado, 'FINAL. COPIA')) && $row->fe_ingrso && $feRcbdo) {
                    $te = $this->tiempoEstimado((string) $row->fe_ingrso, $feRcbdo);
                } elseif ($feRcbdo !== null) {
                    $te = $this->tiempoEstimado($feRcbdo, $ahora);
                } else {
                    $te = '0s';
                }

                $origen = strtoupper(trim((string) ($row->no_crto_origen ?? '')));
                $destinos = $this->usuariosDeCargo((int) ($row->cdgo_crgo_en ?? 0));
                if ((int) ($row->cdgo_crgo_en ?? 0) === (int) ($row->cdgo_crgo_sa ?? 0)) {
                    $destinos = $origen !== '' ? [$origen] : [];
                }

                $adjunto = trim((string) ($row->de_urls_agrdo ?? ''));

                $items[] = [
                    'id' => (int) $row->cdgo_trmtes_ntrnos,
                    'entrada' => $this->fechaPeru($feRcbdo ?: $row->fe_ingrso),
                    'te' => $te,
                    'area_salida' => trim((string) ($row->dc_area_sa ?: $row->de_area_sa)),
                    'area_salida_full' => trim((string) ($row->de_area_sa ?? '')),
                    'estado' => (string) ($row->dc_estdo_trmte ?? ''),
                    'area_envio' => trim((string) ($row->dc_area_en ?: $row->de_area_en)),
                    'area_envio_full' => trim((string) ($row->de_area_en ?? '')),
                    'observaciones' => (string) ($row->de_obser_ntrnos ?? ''),
                    'usuario_origen' => $origen,
                    'usuarios_destino' => $destinos,
                    'adjunto' => $adjunto !== '' ? $adjunto : null,
                ];

                if ($feRcbdo !== null) {
                    $fePasa = $feRcbdo;
                }
            }

            $titulo = trim(
                (string) ($cabecera->de_tpo_trmte ?? '') . ' ' .
                (string) ($cabecera->nu_tram_todo ?? '') . '-' .
                (string) ($cabecera->dc_area ?? '')
            );

            return $this->ok([
                'titulo' => $titulo,
                'cdgo_dtos_ntrnos' => $cdgoDtosNtrnos,
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            Log::error('[sgl] historialTramite: '.$e->getMessage());

            return $this->fail('No se pudo cargar el seguimiento del trámite.');
        }
    }

    /**
     * Detalle de un movimiento interno (equivalente a busca_vauche_adjun.php).
     */
    public function detalleMovimientoInterno(int $cdgoTrmtesNtrnos): array
    {
        try {
            if ($cdgoTrmtesNtrnos <= 0) {
                return $this->fail('Movimiento no indicado.');
            }

            $sistema = $this->schemaSistema;
            $row = DB::selectOne(
                "SELECT r.cdgo_trmtes_ntrnos,
                        r.fe_rcbdo,
                        r.de_obser_ntrnos,
                        r.de_urls_agrdo,
                        p.nu_tram_todo,
                        t.de_tpo_trmte
                 FROM {$sistema}r_trmtes_ntrnos r
                 INNER JOIN {$sistema}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
                 LEFT JOIN {$sistema}a_tpo_trmtes t ON p.cdgo_tpo_trmte = t.cdgo_tpo_trmte
                 WHERE r.cdgo_trmtes_ntrnos = ?",
                [$cdgoTrmtesNtrnos]
            );

            if (! $row) {
                return $this->fail('Movimiento no encontrado.');
            }

            $fe = (string) ($row->fe_rcbdo ?? '');
            $hora = '';
            if (strlen($fe) >= 16) {
                $hora = substr($fe, 11, 5);
            }

            return $this->ok([
                'id' => (int) $row->cdgo_trmtes_ntrnos,
                'numero' => trim((string) ($row->de_tpo_trmte ?? '') . ' - ' . str_pad((string) ($row->nu_tram_todo ?? ''), 10, '0', STR_PAD_LEFT)),
                'fecha' => $this->fechaPeru($fe),
                'hora' => $hora,
                'adjunto' => trim((string) ($row->de_urls_agrdo ?? '')),
                'detalles' => (string) ($row->de_obser_ntrnos ?? ''),
            ]);
        } catch (Throwable $e) {
            Log::error('[sgl] detalleMovimientoInterno: '.$e->getMessage());

            return $this->fail('No se pudo cargar el detalle del movimiento.');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function ok(array $data): array
    {
        return ['success' => true, 'data' => $data];
    }

    private function fail(string $mensaje): array
    {
        return ['success' => false, 'error' => $mensaje];
    }

    private function fechaPeru(mixed $fecha): string
    {
        if (! $fecha) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse((string) $fecha)->timezone('America/Lima')->format('d/m/Y');
        } catch (Throwable $e) {
            return '';
        }
    }

    /** Equivalente a clase_procesos::gene_estimado */
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

    /** @return list<string> */
    private function usuariosDeCargo(int $cdgoCrgo): array
    {
        if ($cdgoCrgo <= 0) {
            return [];
        }

        $sistema = $this->schemaSistema;
        try {
            $rows = DB::select(
                "SELECT DISTINCT u.no_crto
                 FROM {$sistema}r_crgos_lgin rcl
                 INNER JOIN {$sistema}p_lgin l ON rcl.cdgo_lgin = l.cdgo_lgin AND l.in_esta = '1'
                 INNER JOIN {$sistema}p_usrios u ON l.cdgo_usrios = u.cdgo_usrios
                 WHERE rcl.cdgo_crgo = ?
                 ORDER BY u.no_crto",
                [$cdgoCrgo]
            );
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $n = strtoupper(trim((string) ($r->no_crto ?? '')));
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return $out;
    }
}
