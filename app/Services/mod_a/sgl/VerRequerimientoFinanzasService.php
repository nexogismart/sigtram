<?php

namespace App\Services\mod_a\sgl;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pago a proveedores — Finanzas
 * (migrado desde ver_requerimiento_finanzas.php + lista_pago_proveedores_contabilidad.php).
 */
class VerRequerimientoFinanzasService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private const AREA_LOGISTICA = 101;

    private const AREA_FINANZAS = 99;

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
            WHERE p_pgo_prvdres.in_esta = 6
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
            Log::error('[ver_requerimiento_finanzas] listar: ' . $e->getMessage());

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

            return $this->ok([
                'id' => (int) $cab->cdgo_pgo_prvdres,
                'asunto' => (string) ($cab->de_pgo_prvdres ?? ''),
                'area' => (string) ($cab->de_area ?? ''),
                'estado' => $estado,
                'cdgo_dtos_ntrnos' => (int) ($cab->cdgo_dtos_ntrnos ?? 0),
                'documento' => $documento !== ''
                    ? trim($documento . ' N° ' . $numero . ($areaDoc !== '' ? '-' . $areaDoc : ''))
                    : '',
                'puede_devolver' => $estado === 6,
                'puede_finalizar' => $estado === 6,
                'nu_pgo_mmos' => $this->ultimoPagoMemo($id),
                'items' => $this->itemsDeRequerimiento($id),
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_finanzas] detalle: ' . $e->getMessage());

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
            'url_siga' => $this->urlOpcional($item->de_urls_siga ?? null),
            'url_tdr' => $this->urlOpcional($item->de_urls_tdr ?? null),
            'url_os' => $this->urlOpcional($item->de_urls_os ?? null),
            'url_ccp' => $this->urlOpcional($item->de_urls_ccp ?? null),
            'url_ccp_nta' => $this->urlOpcional($item->de_urls_ccp_nta ?? null),
            'os' => (string) ($item->nu_orden_srvco ?? ''),
            'fecha_os' => $this->fechaCorta($item->fe_orden_srvco ?? null),
            'ccp' => (string) ($item->nu_ccp ?? ''),
            'ccp_nta' => (string) ($item->nu_ccp_nta ?? ''),
            'rubro' => (string) ($item->nu_rbro ?? ''),
            'siaf' => (string) ($item->nu_siaf ?? ''),
            'meta' => (string) ($item->nu_mta ?? ''),
            'esp_gasto' => (string) ($item->nu_espcfccon_gsto ?? ''),
            'nombre' => (string) ($item->no_crto ?? ''),
            'ruc' => (string) ($item->nu_ruc ?? ''),
            'cci' => (string) ($item->nu_cci ?? ''),
            'monto' => $item->nu_mnto !== null ? (float) $item->nu_mnto : null,
            'pagos' => (int) ($item->nu_pgos_cntdad ?? 0),
            'dias_pago' => (string) ($item->no_das_pgos ?? ''),
            'in_esta_item' => (int) ($item->in_esta_pgo ?? 0),
            'memos' => $this->memosDeItem($itemId, $item),
        ]);
    }

    public function finalizar(int $id): array
    {
        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);
        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if ((int) ($cab->in_esta_prvdres ?? 0) !== 6) {
            return $this->fail('El requerimiento no está en estado para finalizar el pago.');
        }

        $tramiteId = (int) ($cab->cdgo_dtos_ntrnos ?? 0);
        if ($tramiteId <= 0) {
            return $this->fail('El requerimiento no tiene trámite asociado.');
        }

        $cargoFinanzas = $this->cargoJefeArea(self::AREA_FINANZAS);
        if ($cargoFinanzas <= 0) {
            return $this->fail('No hay cargo jefe en Finanzas (área 99).');
        }

        try {
            return DB::transaction(function () use ($id, $tramiteId, $userId, $cargoFinanzas) {
                DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                    ->where('cdgo_dtos_ntrnos', $tramiteId)
                    ->update(['in_esta' => '0']);

                DB::table($this->schemaSistema . 'r_trmtes_ntrnos')->insert([
                    'cdgo_dtos_ntrnos' => $tramiteId,
                    'cdgo_estdo_trmte' => 3,
                    'cdgo_lgin' => $userId,
                    'cdgo_crgo_sa' => $cargoFinanzas,
                    'cdgo_crgo_en' => $cargoFinanzas,
                    'de_urls_agrdo' => '',
                    'fe_rcbdo' => now(),
                    'de_obser_ntrnos' => 'SE FINALIZA EL TRAMITE CON EL PAGO REALIZADO',
                    'in_copia' => '1',
                    'in_esta' => '1',
                ]);

                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->update(['in_esta' => 13]);

                return $this->ok(['id' => 0], 'Se finalizó el trámite.');
            });
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_finanzas] finalizar: ' . $e->getMessage());

            return $this->fail('No se pudo finalizar el trámite.');
        }
    }

    public function devolver(int $id, string $motivo): array
    {
        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);
        $cargoId = (int) session('sigtram_cargo_id', 0);
        $motivo = mb_strtoupper(trim($motivo), 'UTF-8');
        if ($motivo === '') {
            return $this->fail('Ingrese el motivo de devolución.');
        }

        $cab = $this->cabecera($id);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if ((int) ($cab->in_esta_prvdres ?? 0) !== 6) {
            return $this->fail('El requerimiento no está en estado para devolver.');
        }

        $tramiteId = (int) ($cab->cdgo_dtos_ntrnos ?? 0);
        $nuPgo = $this->ultimoPagoMemo($id);
        $dest = $this->cargoJefeArea(self::AREA_LOGISTICA);
        if ($dest <= 0) {
            return $this->fail('No hay cargo jefe en Logística (área 101).');
        }

        try {
            return DB::transaction(function () use ($id, $motivo, $userId, $cargoId, $tramiteId, $nuPgo, $dest) {
                DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
                    ->where('cdgo_pgo_prvdres', $id)
                    ->update(['in_esta' => 5]);

                $memoIds = DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
                    ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
                    ->where('h.cdgo_pgo_prvdres', $id)
                    ->where('m.in_esta', 6)
                    ->when($nuPgo > 0, fn ($q) => $q->where('m.nu_pgo_mmos', $nuPgo))
                    ->pluck('m.cdgo_deta_pgo_mmos');

                if ($memoIds->isNotEmpty()) {
                    DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                        ->whereIn('cdgo_deta_pgo_mmos', $memoIds->all())
                        ->update([
                            'cdgo_dtos_ntrnos_dvngar' => null,
                            'de_urls_dvngar' => null,
                            'in_esta' => 5,
                        ]);
                }

                if ($tramiteId > 0) {
                    DB::table($this->schemaSistema . 'r_trmtes_ntrnos')
                        ->where('cdgo_dtos_ntrnos', $tramiteId)
                        ->update(['in_esta' => '0']);

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
            Log::error('[ver_requerimiento_finanzas] devolver: ' . $e->getMessage());

            return $this->fail('No se pudo devolver el requerimiento.');
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
                    h.nu_sga, h.nu_orden_srvco, h.de_urls_pgo, h.de_urls_anxos, h.nu_pgos_cntdad,
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
            $urlTdr = $this->urlOpcional($row->de_urls_pgo ?? null);
            $urlAne = $this->urlOpcional($row->de_urls_anxos ?? null);
            $out[] = [
                'n' => $n,
                'id' => (int) $row->cdgo_deta_pgo_prvdres,
                'cantidad' => $row->nu_cntdad_prvdres !== null ? (int) $row->nu_cntdad_prvdres : null,
                'descripcion' => (string) ($row->de_deta_pgo_prvdres ?? ''),
                'pagos' => (int) ($row->nu_pgos_cntdad ?? 0),
                'monto' => $row->nu_mnto !== null ? (float) $row->nu_mnto : null,
                'siga' => trim((string) ($row->nu_sga ?? '')),
                'os' => trim((string) ($row->nu_orden_srvco ?? '')),
                'url_tdr' => $urlTdr,
                'url_ane' => $urlAne,
                'in_esta' => (int) ($row->in_esta_pgo ?? 1),
                'puede_ver_con' => $urlTdr !== null && $urlAne !== null,
            ];
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
                    n.nu_tram_todo, n.fe_ingrso, a.dc_area
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos m
             LEFT JOIN ' . $sistema . 'p_dtos_ntrnos n ON m.cdgo_dtos_ntrnos_cnfrmdad = n.cdgo_dtos_ntrnos
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

            $out[] = [
                'n' => $i + 1,
                'id' => (int) $row->cdgo_deta_pgo_mmos,
                'documento' => 'MEMORANDUM N° ' . trim((string) ($row->nu_tram_todo ?? '')) . '-' . trim((string) ($row->dc_area ?? '')),
                'monto' => $row->nu_mnto_sldo !== null ? (float) $row->nu_mnto_sldo : null,
                'dias' => $dias,
                'fecha' => $this->fechaCorta($row->fe_ingrso ?? $fechaConf),
                'url' => $this->urlOpcional($row->de_urls_cnfrmdad ?? null),
            ];
        }

        return $out;
    }

    private function ultimoPagoMemo(int $pagoId): int
    {
        return (int) (DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos as m')
            ->join(self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres as h', 'm.cdgo_deta_pgo_prvdres', '=', 'h.cdgo_deta_pgo_prvdres')
            ->where('h.cdgo_pgo_prvdres', $pagoId)
            ->where('m.in_esta', 6)
            ->max('m.nu_pgo_mmos') ?? 0);
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

    private function detalleVacio(): array
    {
        return [
            'id' => 0,
            'asunto' => '',
            'area' => '',
            'estado' => 0,
            'cdgo_dtos_ntrnos' => 0,
            'documento' => '',
            'puede_devolver' => false,
            'puede_finalizar' => false,
            'nu_pgo_mmos' => 0,
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
