<?php

namespace App\Services\mod_ti;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lógica de negocio para dispositivos de seguridad (cámaras, alarmas, mufas).
 * BD cámaras: conexión camara_seg → camara.camaras_act
 * BD GIS:     conexión mdpp_gis   → seguridadciudadana.sectorsc / subsectorsc
 */
class DispositivoSegService
{
    private const CONN_CAMARA = 'camara_seg';
    private const CONN_GIS = 'mdpp_gis';
    private const TABLA = 'camara.camaras_act';
    private const TABLA_SECTOR = 'seguridadciudadana.sectorsc';
    private const TABLA_SUB_SECTOR = 'seguridadciudadana.subsectorsc';

    private const TIPOS = ['CAMARA', 'ALARMA', 'MUFA'];
    private const ZONAS = ['NORTE', 'CENTRO', 'SUR'];
    private const ESTADOS = ['OPERATIVO', 'INOPERATIVO', 'INTERMITENTE', 'MANTENIMIENTO'];

    private const COLUMNAS_SELECT = "
        fid AS id, tipo_dispositivo, nro AS item, sector AS zona,
        sector_id, sub_sector_id,
        nombre AS nuevo_codigo, codigo_kmz, direccion, lat AS latitud, lon AS longitud,
        CASE WHEN lat IS NOT NULL AND lon IS NOT NULL
             THEN 'https://www.google.com/maps?q=' || lat || ',' || lon
             ELSE NULL END AS url,
        ip, marca, modelo, tipo, resolucion, megapixeles, zoom, tecnologia,
        anio_inst AS anio_instalacion, conexion, estado, observciones AS observaciones,
        vcs, ulti_cone AS ultima_conexion, numero_oc_contrato, fecha_contrato, proveedor
    ";

    private const COLUMNAS_EXPORT = "
        fid AS id, tipo_dispositivo, nro AS item, sector AS zona,
        sector_id, sub_sector_id,
        codigo_kmz, nombre AS nuevo_codigo, direccion, lat AS latitud, lon AS longitud,
        CASE WHEN lat IS NOT NULL AND lon IS NOT NULL
             THEN 'https://www.google.com/maps?q=' || lat || ',' || lon
             ELSE NULL END AS url,
        ip, marca, modelo, tipo, resolucion, megapixeles, zoom, tecnologia,
        anio_inst AS anio_instalacion, conexion, estado, observciones AS observaciones,
        vcs, ulti_cone AS ultima_conexion, numero_oc_contrato, fecha_contrato, proveedor
    ";

    public function listar(
        int $pagina,
        int $porPagina,
        string $busqueda = '',
        string $tipo = '',
        string $zona = '',
        string $estado = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        ?int $sectorId = null,
        ?int $subSectorId = null
    ): array {
        $pagina = max(1, $pagina);
        $porPagina = max(1, $porPagina);
        $offset = ($pagina - 1) * $porPagina;

        [$where, $params] = $this->construirWhere($busqueda, $tipo, $zona, $estado, $fechaDesde, $fechaHasta, $sectorId, $subSectorId);

        try {
            $total = (int) DB::connection(self::CONN_CAMARA)
                ->selectOne('SELECT COUNT(*) AS total FROM ' . self::TABLA . $where, $params)
                ->total;

            $sql = 'SELECT ' . self::COLUMNAS_SELECT . ' FROM ' . self::TABLA
                . $where . ' ORDER BY fid DESC LIMIT ? OFFSET ?';
            $rows = DB::connection(self::CONN_CAMARA)->select($sql, array_merge($params, [$porPagina, $offset]));
            $items = array_map(fn ($r) => (array) $r, $rows);
            $this->resolverSectorSubSector($items);

            return [
                'success' => true,
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'page' => $pagina,
                        'per_page' => $porPagina,
                        'total' => $total,
                        'total_pages' => max(1, (int) ceil($total / $porPagina)),
                    ],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] listar: ' . $e->getMessage());

            return ['success' => false, 'error' => 'No se pudo listar dispositivos.'];
        }
    }

    public function exportar(
        string $busqueda = '',
        string $tipo = '',
        string $zona = '',
        string $estado = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        ?int $sectorId = null,
        ?int $subSectorId = null
    ): array {
        [$where, $params] = $this->construirWhere($busqueda, $tipo, $zona, $estado, $fechaDesde, $fechaHasta, $sectorId, $subSectorId);

        try {
            $sql = 'SELECT ' . self::COLUMNAS_EXPORT . ' FROM ' . self::TABLA . $where . ' ORDER BY fid DESC';
            $rows = DB::connection(self::CONN_CAMARA)->select($sql, $params);
            $items = array_map(fn ($r) => (array) $r, $rows);
            $this->resolverSectorSubSector($items);

            return ['success' => true, 'data' => $items];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] exportar: ' . $e->getMessage());

            return ['success' => false, 'error' => 'No se pudo exportar.'];
        }
    }

    public function obtener(int $id): array
    {
        try {
            $sql = 'SELECT ' . self::COLUMNAS_SELECT . ', usuario_visor, password_visor FROM ' . self::TABLA
                . ' WHERE fid = ? AND estado_aud = 1';
            $row = DB::connection(self::CONN_CAMARA)->selectOne($sql, [$id]);
            if (!$row) {
                return ['success' => false, 'error' => 'Registro no encontrado.'];
            }
            $items = [(array) $row];
            $this->resolverSectorSubSector($items);

            return ['success' => true, 'data' => $items[0]];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] obtener: ' . $e->getMessage());

            return ['success' => false, 'error' => 'No se pudo obtener el registro.'];
        }
    }

    public function guardar(array $datos): array
    {
        $errorValidacion = $this->validarDatosEntrada($datos);
        if ($errorValidacion) {
            return ['success' => false, 'error' => $errorValidacion];
        }

        $params = $this->sanitizarDatos($datos);

        try {
            $sql = 'INSERT INTO ' . self::TABLA . '
                (nro, tipo_dispositivo, sector, sector_id, sub_sector_id, codigo_kmz, nombre, direccion, lat, lon, ip,
                 marca, modelo, tipo, resolucion, megapixeles, zoom, tecnologia, anio_inst,
                 conexion, estado, observciones, vcs, numero_oc_contrato, fecha_contrato, proveedor,
                 usuario_visor, password_visor, estado_aud)
                VALUES (
                    (SELECT COALESCE(MAX(nro), 0) + 1 FROM ' . self::TABLA . '),
                    ?,?,?,?,?,?,?,?,?,?,
                    ?,?,?,?,?,?,?,?,
                    ?,?,?,?,?,?,?,?,
                    ?,?, 1) RETURNING fid';

            $row = DB::connection(self::CONN_CAMARA)->selectOne($sql, $params);

            return ['success' => true, 'data' => ['id' => (int) $row->fid]];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $this->parseDbError($e->getMessage(), 'guardar')];
        }
    }

    public function actualizar(array $datos): array
    {
        $id = (int) ($datos['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'ID no valido.'];
        }

        $errorValidacion = $this->validarDatosEntrada($datos);
        if ($errorValidacion) {
            return ['success' => false, 'error' => $errorValidacion];
        }

        $params = $this->sanitizarDatos($datos);
        $params[] = $id;

        try {
            $sql = 'UPDATE ' . self::TABLA . ' SET
                    tipo_dispositivo=?, sector=?, sector_id=?, sub_sector_id=?,
                    codigo_kmz=?, nombre=?, direccion=?, lat=?, lon=?, ip=?,
                    marca=?, modelo=?, tipo=?, resolucion=?, megapixeles=?,
                    zoom=?, tecnologia=?, anio_inst=?, conexion=?,
                    estado=?, observciones=?, vcs=?,
                    numero_oc_contrato=?, fecha_contrato=?, proveedor=?,
                    usuario_visor=COALESCE(NULLIF(?,\'\'), usuario_visor),
                    password_visor=COALESCE(NULLIF(?,\'\'), password_visor)
                WHERE fid=? AND estado_aud = 1';

            $affected = DB::connection(self::CONN_CAMARA)->update($sql, $params);
            if ($affected === 0) {
                return ['success' => false, 'error' => 'Registro no encontrado o ya eliminado.'];
            }

            return ['success' => true];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $this->parseDbError($e->getMessage(), 'actualizar')];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $affected = DB::connection(self::CONN_CAMARA)->update(
                'UPDATE ' . self::TABLA . ' SET estado_aud = 0 WHERE fid = ? AND estado_aud = 1',
                [$id]
            );
            if ($affected === 0) {
                return ['success' => false, 'error' => 'Registro no encontrado o ya eliminado.'];
            }

            return ['success' => true];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] eliminar: ' . $e->getMessage());

            return ['success' => false, 'error' => 'No se pudo eliminar el registro.'];
        }
    }

    public function listarSectores(): array
    {
        try {
            $rows = DB::connection(self::CONN_GIS)->select(
                'SELECT sector_id AS id, sector_descripcion AS nombre FROM ' . self::TABLA_SECTOR
                . ' ORDER BY sector_descripcion'
            );

            return [
                'success' => true,
                'data' => array_map(fn ($r) => [
                    'id' => (int) $r->id,
                    'nombre' => trim((string) ($r->nombre ?? '')),
                ], $rows),
            ];
        } catch (Throwable $e) {
            Log::warning('[dispositivo_seg] listarSectores: ' . $e->getMessage());

            return ['success' => true, 'data' => []];
        }
    }

    public function listarSubSectores(): array
    {
        try {
            $rows = DB::connection(self::CONN_GIS)->select(
                'SELECT gid AS id, subsector_descripcion AS nombre FROM ' . self::TABLA_SUB_SECTOR
                . ' ORDER BY subsector_descripcion'
            );

            return [
                'success' => true,
                'data' => array_map(fn ($r) => [
                    'id' => (int) $r->id,
                    'nombre' => trim((string) ($r->nombre ?? '')),
                ], $rows),
            ];
        } catch (Throwable $e) {
            Log::warning('[dispositivo_seg] listarSubSectores: ' . $e->getMessage());

            return ['success' => true, 'data' => []];
        }
    }

    public function listarSubSectoresPorSector(int $sectorId): array
    {
        if ($sectorId <= 0) {
            return ['success' => true, 'data' => []];
        }

        try {
            $rows = DB::connection(self::CONN_GIS)->select(
                'SELECT gid AS id, subsector_descripcion AS nombre FROM ' . self::TABLA_SUB_SECTOR
                . ' WHERE subsector_id = ? ORDER BY subsector_descripcion',
                [$sectorId]
            );

            return [
                'success' => true,
                'data' => array_map(fn ($r) => [
                    'id' => (int) $r->id,
                    'nombre' => trim((string) ($r->nombre ?? '')),
                ], $rows),
            ];
        } catch (Throwable $e) {
            Log::warning('[dispositivo_seg] listarSubSectoresPorSector: ' . $e->getMessage());

            return ['success' => false, 'error' => 'No se pudo listar sub_sectores.', 'data' => []];
        }
    }

    public function actualizarEstado(int $id, string $estado, bool $actualizarUltimaConexion = false): array
    {
        if ($id <= 0) {
            return ['success' => false, 'error' => 'ID no válido.'];
        }
        $estado = $this->validarEnum($estado, self::ESTADOS);
        if ($estado === null) {
            return ['success' => false, 'error' => 'Estado no válido.'];
        }

        try {
            $params = [$estado];
            $sql = 'UPDATE ' . self::TABLA . ' SET estado = ?';
            if ($actualizarUltimaConexion) {
                $params[] = now('America/Lima')->format('Y-m-d H:i:s');
                $sql .= ', ulti_cone = ?::timestamp';
            }
            $params[] = $id;
            $sql .= ' WHERE fid = ? AND estado_aud = 1';

            $affected = DB::connection(self::CONN_CAMARA)->update($sql, $params);
            if ($affected === 0) {
                return ['success' => false, 'error' => 'Registro no encontrado.'];
            }

            return ['success' => true, 'data' => ['id' => $id, 'estado' => $estado]];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] actualizarEstado: ' . $e->getMessage());

            return ['success' => false, 'error' => 'No se pudo actualizar el estado.'];
        }
    }

    public function estadisticas(
        string $busqueda = '',
        string $tipo = '',
        string $zona = '',
        string $estado = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        ?int $sectorId = null,
        ?int $subSectorId = null
    ): array {
        [$where, $params] = $this->construirWhere($busqueda, $tipo, $zona, $estado, $fechaDesde, $fechaHasta, $sectorId, $subSectorId);
        $out = [
            'por_estado' => [],
            'por_tipo_dispositivo' => [],
            'por_zona' => [],
            'por_marca' => [],
            'por_modelo' => [],
            'por_tipo' => [],
            'por_camara' => [],
            'por_sector_subsector' => [],
            'total' => 0,
        ];

        try {
            $out['total'] = (int) DB::connection(self::CONN_CAMARA)
                ->selectOne('SELECT COUNT(*) AS total FROM ' . self::TABLA . $where, $params)
                ->total;

            $grupos = [
                'por_estado' => 'estado',
                'por_tipo_dispositivo' => 'tipo_dispositivo',
                'por_zona' => 'sector',
                'por_tipo' => 'tipo',
            ];
            foreach ($grupos as $key => $col) {
                $sql = "SELECT COALESCE({$col}::text, 'Sin dato') AS label, COUNT(*) AS total,
                    ROUND(COALESCE(SUM(EXTRACT(EPOCH FROM (NOW() - ulti_cone)) / 3600), 0)::numeric, 1) AS horas_totales
                    FROM " . self::TABLA . $where . " GROUP BY {$col} ORDER BY total DESC";
                foreach (DB::connection(self::CONN_CAMARA)->select($sql, $params) as $row) {
                    $out[$key][] = [
                        'label' => $row->label,
                        'total' => (int) $row->total,
                        'horas_totales' => isset($row->horas_totales) ? round((float) $row->horas_totales, 1) : null,
                    ];
                }
            }

            $sqlMarca = "SELECT COALESCE(marca::text, 'Sin dato') AS label, COUNT(*) AS total,
                ROUND(COALESCE(SUM(EXTRACT(EPOCH FROM (NOW() - ulti_cone)) / 3600), 0)::numeric, 1) AS horas_totales
                FROM " . self::TABLA . $where . ' GROUP BY marca ORDER BY total DESC LIMIT 15';
            foreach (DB::connection(self::CONN_CAMARA)->select($sqlMarca, $params) as $row) {
                $out['por_marca'][] = [
                    'label' => $row->label,
                    'total' => (int) $row->total,
                    'horas_totales' => isset($row->horas_totales) ? round((float) $row->horas_totales, 1) : null,
                ];
            }

            $sqlModelo = "SELECT COALESCE(modelo::text, 'Sin dato') AS label, COUNT(*) AS total,
                ROUND(COALESCE(SUM(EXTRACT(EPOCH FROM (NOW() - ulti_cone)) / 3600), 0)::numeric, 1) AS horas_totales
                FROM " . self::TABLA . $where . ' GROUP BY modelo ORDER BY total DESC LIMIT 15';
            foreach (DB::connection(self::CONN_CAMARA)->select($sqlModelo, $params) as $row) {
                $out['por_modelo'][] = [
                    'label' => $row->label,
                    'total' => (int) $row->total,
                    'horas_totales' => isset($row->horas_totales) ? round((float) $row->horas_totales, 1) : null,
                ];
            }

            $sqlCamara = "SELECT fid, COALESCE(NULLIF(TRIM(nombre), ''), NULLIF(TRIM(codigo_kmz), ''), 'ID ' || fid) AS label,
                COALESCE(estado::text, 'Sin dato') AS estado,
                ROUND(COALESCE(EXTRACT(EPOCH FROM (NOW() - ulti_cone)) / 3600, 0)::numeric, 1) AS horas_totales,
                lat AS latitud, lon AS longitud, tipo, tipo_dispositivo
                FROM " . self::TABLA . $where . ' ORDER BY ulti_cone DESC NULLS LAST';
            foreach (DB::connection(self::CONN_CAMARA)->select($sqlCamara, $params) as $row) {
                $lat = $row->latitud !== null && $row->latitud !== '' ? (float) $row->latitud : null;
                $lon = $row->longitud !== null && $row->longitud !== '' ? (float) $row->longitud : null;
                $out['por_camara'][] = [
                    'id' => (int) $row->fid,
                    'label' => $row->label,
                    'estado' => $row->estado,
                    'horas_totales' => isset($row->horas_totales) ? round((float) $row->horas_totales, 1) : 0,
                    'latitud' => $lat,
                    'longitud' => $lon,
                    'tipo' => trim((string) ($row->tipo ?? '')),
                    'tipo_dispositivo' => trim((string) ($row->tipo_dispositivo ?? '')),
                ];
            }

            $sqlSectorSub = "SELECT fid, sector_id, sub_sector_id, tipo, tipo_dispositivo,
                COALESCE(NULLIF(TRIM(nombre), ''), NULLIF(TRIM(codigo_kmz), ''), 'ID ' || fid) AS label
                FROM " . self::TABLA . $where . ' ORDER BY sector_id, sub_sector_id, label';
            $filas = DB::connection(self::CONN_CAMARA)->select($sqlSectorSub, $params);
            if ($filas !== []) {
                $items = [];
                foreach ($filas as $r) {
                    $items[] = [
                        'fid' => (int) $r->fid,
                        'sector_id' => $r->sector_id !== null ? (int) $r->sector_id : null,
                        'sub_sector_id' => $r->sub_sector_id !== null ? (int) $r->sub_sector_id : null,
                        'label' => trim((string) ($r->label ?? '')),
                        'tipo' => trim((string) ($r->tipo ?? '')),
                        'tipo_dispositivo' => trim((string) ($r->tipo_dispositivo ?? '')),
                    ];
                }
                $this->resolverSectorSubSector($items);
                $gruposMap = [];
                foreach ($items as $r) {
                    $sector = ($r['sector'] ?? '') !== '' ? $r['sector'] : 'Sin sector';
                    $sub = ($r['sub_sector'] ?? '') !== '' ? $r['sub_sector'] : 'Sin subsector';
                    $k = $sector . '|' . $sub;
                    if (!isset($gruposMap[$k])) {
                        $gruposMap[$k] = ['sector' => $sector, 'sub_sector' => $sub, 'total' => 0, 'camaras' => []];
                    }
                    $gruposMap[$k]['total']++;
                    $gruposMap[$k]['camaras'][] = [
                        'id' => $r['fid'],
                        'label' => $r['label'],
                        'tipo' => $r['tipo'],
                        'tipo_dispositivo' => $r['tipo_dispositivo'],
                    ];
                }
                $out['por_sector_subsector'] = array_values($gruposMap);
            }

            return ['success' => true, 'data' => $out];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] estadisticas: ' . $e->getMessage());

            return ['success' => false, 'error' => 'No se pudieron obtener estadísticas.'];
        }
    }

    public function geojsonZonas(): array
    {
        return $this->geojsonFromGis(
            'SELECT gid, zona_id, zona, ST_AsGeoJSON(ST_Transform(geometria, 4326)) AS geom
             FROM seguridadciudadana.zonasc ORDER BY zona_id',
            function ($row) {
                return [
                    'gid' => (int) $row->gid,
                    'zona_id' => (int) $row->zona_id,
                    'zona' => $row->zona,
                ];
            }
        );
    }

    public function geojsonSectores(): array
    {
        return $this->geojsonFromGis(
            'SELECT gid, sector_id, sector, sector_descripcion,
                    ST_AsGeoJSON(ST_Transform(geometria, 4326)) AS geom
             FROM seguridadciudadana.sectorsc ORDER BY sector_id',
            function ($row) {
                return [
                    'gid' => (int) $row->gid,
                    'sector_id' => (int) $row->sector_id,
                    'sector' => $row->sector,
                    'sector_descripcion' => $row->sector_descripcion,
                ];
            }
        );
    }

    public function geojsonSubsectores(): array
    {
        return $this->geojsonFromGis(
            'SELECT gid, subsector_id, subsector, subsector_descripcion, subsector_den,
                    ST_AsGeoJSON(ST_Transform(geometria, 4326)) AS geom
             FROM seguridadciudadana.subsectorsc ORDER BY subsector_id, gid',
            function ($row) {
                return [
                    'gid' => (int) $row->gid,
                    'subsector_id' => (int) $row->subsector_id,
                    'subsector' => $row->subsector,
                    'subsector_descripcion' => $row->subsector_descripcion,
                    'subsector_den' => $row->subsector_den ?? '',
                ];
            }
        );
    }

    public function reporteVisores(): array
    {
        try {
            $rows = DB::connection(self::CONN_CAMARA)->select(
                'SELECT fid, nombre AS nuevo_codigo, codigo_kmz, direccion, tipo_dispositivo, ip, marca, modelo
                 FROM ' . self::TABLA . ' WHERE estado_aud = 1 ORDER BY fid DESC'
            );
            $conBoton = [];
            $sinBoton = [];
            foreach ($rows as $row) {
                $esCamara = strtoupper(trim((string) $row->tipo_dispositivo)) === 'CAMARA';
                $tieneIp = trim((string) ($row->ip ?? '')) !== '';
                $item = [
                    'id' => $row->fid,
                    'codigo' => $row->nuevo_codigo ?: $row->codigo_kmz,
                    'direccion' => $row->direccion,
                    'tipo' => $row->tipo_dispositivo,
                    'ip' => $row->ip,
                    'marca' => $row->marca,
                    'modelo' => $row->modelo,
                ];
                if ($esCamara && $tieneIp) {
                    $conBoton[] = $item;
                } else {
                    $razones = [];
                    if (!$esCamara) {
                        $razones[] = 'No es CAMARA';
                    }
                    if (!$tieneIp) {
                        $razones[] = 'Sin IP';
                    }
                    $item['razon'] = implode(', ', $razones);
                    $sinBoton[] = $item;
                }
            }

            return ['success' => true, 'data' => ['conBoton' => $conBoton, 'sinBoton' => $sinBoton]];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] reporteVisores: ' . $e->getMessage());

            return ['success' => false, 'error' => 'Error al consultar la base de datos.'];
        }
    }

    public function credencialesPorIp(string $ip): array
    {
        $ip = trim($ip);
        $defUsuario = (string) config('camara_visor.usuario', 'admin');
        $defPassword = (string) config('camara_visor.password', 'admin');
        if ($ip === '') {
            return ['usuario' => $defUsuario, 'password' => $defPassword];
        }

        try {
            $row = DB::connection(self::CONN_CAMARA)->selectOne(
                "SELECT usuario_visor, password_visor FROM " . self::TABLA . "
                 WHERE (estado_aud IS NULL OR estado_aud = 1)
                 AND (ip = ? OR ip LIKE ? OR TRIM(SPLIT_PART(TRIM(ip),'/',1)) = ?)
                 LIMIT 1",
                [$ip, $ip . '/%', $ip]
            );
            if ($row) {
                $u = trim((string) ($row->usuario_visor ?? ''));
                $p = trim((string) ($row->password_visor ?? ''));
                if ($u !== '' || $p !== '') {
                    return ['usuario' => $u, 'password' => $p];
                }
            }
        } catch (Throwable $e) {
            Log::warning('[dispositivo_seg] credencialesPorIp: ' . $e->getMessage());
        }

        return ['usuario' => $defUsuario, 'password' => $defPassword];
    }

    private function geojsonFromGis(string $sql, callable $props): array
    {
        try {
            $features = [];
            foreach (DB::connection(self::CONN_GIS)->select($sql) as $row) {
                $geomStr = (string) ($row->geom ?? '');
                if ($geomStr === '') {
                    continue;
                }
                $geojson = json_decode($geomStr, true);
                if (!$geojson) {
                    continue;
                }
                $features[] = [
                    'type' => 'Feature',
                    'properties' => $props($row),
                    'geometry' => $geojson,
                ];
            }

            return ['type' => 'FeatureCollection', 'features' => $features];
        } catch (Throwable $e) {
            Log::error('[dispositivo_seg] geojson: ' . $e->getMessage());

            return ['type' => 'FeatureCollection', 'features' => [], 'error' => $e->getMessage()];
        }
    }

    // ── Filtros / utilidades ──────────────────────────────────────────────

    private function construirWhere(
        string $busqueda,
        string $tipo,
        string $zona,
        string $estado,
        string $fechaDesde,
        string $fechaHasta,
        ?int $sectorId,
        ?int $subSectorId
    ): array {
        $where = ' WHERE estado_aud = 1';
        $params = [];

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $where .= ' AND (
                COALESCE(tipo_dispositivo,\'\') ILIKE ?
                OR COALESCE(codigo_kmz,\'\') ILIKE ?
                OR COALESCE(nombre,\'\') ILIKE ?
                OR COALESCE(direccion,\'\') ILIKE ?
                OR COALESCE(marca,\'\') ILIKE ?
                OR COALESCE(modelo,\'\') ILIKE ?
            )';
            $like = '%' . $busqueda . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }

        $tipo = trim($tipo);
        if ($tipo !== '' && in_array($tipo, self::TIPOS, true)) {
            $where .= ' AND tipo_dispositivo = ?';
            $params[] = $tipo;
        }

        $zona = trim($zona);
        if ($zona !== '') {
            $where .= ' AND sector = ?';
            $params[] = $zona;
        }

        $estado = trim($estado);
        if ($estado !== '') {
            $where .= ' AND estado = ?';
            $params[] = $estado;
        }

        $fechaDesde = trim($fechaDesde);
        if ($fechaDesde !== '') {
            $where .= ' AND ulti_cone >= ?::timestamp';
            $params[] = $fechaDesde . ' 00:00:00';
        }

        $fechaHasta = trim($fechaHasta);
        if ($fechaHasta !== '') {
            $where .= ' AND ulti_cone <= ?::timestamp';
            $params[] = $fechaHasta . ' 23:59:59';
        }

        if ($sectorId !== null && $sectorId > 0) {
            $where .= ' AND sector_id = ?';
            $params[] = $sectorId;
        }

        if ($subSectorId !== null && $subSectorId > 0) {
            $where .= ' AND sub_sector_id = ?';
            $params[] = $subSectorId;
        }

        return [$where, $params];
    }

    private function resolverSectorSubSector(array &$items): void
    {
        if ($items === []) {
            return;
        }

        foreach ($items as &$row) {
            $row['sector'] = '';
            $row['sub_sector'] = '';
        }
        unset($row);

        $idsSector = [];
        $idsSubSector = [];
        foreach ($items as $row) {
            if (!empty($row['sector_id']) && is_numeric($row['sector_id'])) {
                $idsSector[(int) $row['sector_id']] = true;
            }
            if (!empty($row['sub_sector_id']) && is_numeric($row['sub_sector_id'])) {
                $idsSubSector[(int) $row['sub_sector_id']] = true;
            }
        }
        $idsSector = array_keys($idsSector);
        $idsSubSector = array_keys($idsSubSector);

        $mapSector = [];
        $mapSubSector = [];

        try {
            if ($idsSector !== []) {
                $placeholders = implode(',', array_fill(0, count($idsSector), '?'));
                $rows = DB::connection(self::CONN_GIS)->select(
                    'SELECT sector_id AS id, sector_descripcion AS nombre FROM ' . self::TABLA_SECTOR
                    . " WHERE sector_id IN ($placeholders)",
                    $idsSector
                );
                foreach ($rows as $r) {
                    $mapSector[(int) $r->id] = trim((string) ($r->nombre ?? ''));
                }
            }
            if ($idsSubSector !== []) {
                $placeholders = implode(',', array_fill(0, count($idsSubSector), '?'));
                $rows = DB::connection(self::CONN_GIS)->select(
                    'SELECT gid AS id, subsector_descripcion AS nombre FROM ' . self::TABLA_SUB_SECTOR
                    . " WHERE gid IN ($placeholders)",
                    $idsSubSector
                );
                foreach ($rows as $r) {
                    $mapSubSector[(int) $r->id] = trim((string) ($r->nombre ?? ''));
                }
            }
        } catch (Throwable $e) {
            Log::warning('[dispositivo_seg] resolverSectorSubSector: ' . $e->getMessage());
        }

        foreach ($items as &$row) {
            $sid = (int) ($row['sector_id'] ?? 0);
            $ssid = (int) ($row['sub_sector_id'] ?? 0);
            $row['sector'] = $mapSector[$sid] ?? '';
            $row['sub_sector'] = $mapSubSector[$ssid] ?? '';
        }
        unset($row);
    }

    private function sanitizarDatos(array $datos): array
    {
        return [
            $this->validarEnum($this->val($datos, 'tipo_dispositivo'), self::TIPOS),
            $this->validarEnum($this->val($datos, 'zona'), self::ZONAS),
            $this->validarIntNull($this->val($datos, 'sector_id')),
            $this->validarIntNull($this->val($datos, 'sub_sector_id')),
            $this->str($this->val($datos, 'codigo_kmz')),
            $this->str($this->val($datos, 'nuevo_codigo')),
            $this->str($this->val($datos, 'direccion')),
            $this->validarCoordenada($this->val($datos, 'latitud'), -90, 90),
            $this->validarCoordenada($this->val($datos, 'longitud'), -180, 180),
            $this->validarIp($this->val($datos, 'ip')),
            $this->str($this->val($datos, 'marca')),
            $this->str($this->val($datos, 'modelo')),
            $this->str($this->val($datos, 'tipo')),
            $this->str($this->val($datos, 'resolucion')),
            $this->str($this->val($datos, 'megapixeles')),
            $this->str($this->val($datos, 'zoom')),
            $this->str($this->val($datos, 'tecnologia')),
            $this->str($this->val($datos, 'anio_instalacion')),
            $this->str($this->val($datos, 'conexion')),
            $this->validarEnum($this->val($datos, 'estado'), self::ESTADOS),
            $this->str($this->val($datos, 'observaciones')),
            $this->str($this->val($datos, 'vcs')),
            $this->str($this->val($datos, 'numero_oc_contrato')),
            $this->str($this->val($datos, 'fecha_contrato')),
            $this->str($this->val($datos, 'proveedor')),
            $this->str($this->val($datos, 'usuario_visor')),
            $this->str($this->val($datos, 'password_visor')),
        ];
    }

    private function validarDatosEntrada(array $datos): ?string
    {
        $lat = trim((string) $this->val($datos, 'latitud'));
        $lng = trim((string) $this->val($datos, 'longitud'));
        if ($lat !== '' && (!is_numeric($lat) || (float) $lat < -90 || (float) $lat > 90)) {
            return 'Latitud invalida. Debe ser un numero entre -90 y 90.';
        }
        if ($lng !== '' && (!is_numeric($lng) || (float) $lng < -180 || (float) $lng > 180)) {
            return 'Longitud invalida. Debe ser un numero entre -180 y 180.';
        }
        $ip = trim((string) $this->val($datos, 'ip'));
        if ($ip !== '') {
            $sinMascara = explode('/', $ip)[0];
            if (!filter_var($sinMascara, FILTER_VALIDATE_IP)) {
                return 'Direccion IP invalida. Ej: 172.16.161.17';
            }
        }

        return null;
    }

    private function parseDbError(string $errorMsg, string $accion): string
    {
        $errorMsg = strtolower($errorMsg);
        if (str_contains($errorMsg, 'camaras_act_nombre_key')) {
            return 'El Nuevo Código (o Nombre) ingresado ya se encuentra registrado.';
        }
        if (str_contains($errorMsg, 'numeric field overflow') || str_contains($errorMsg, 'numeric')) {
            return 'Un valor numérico excede el límite permitido (ej: Megapíxeles o Zoom demasiado grandes).';
        }
        if (str_contains($errorMsg, 'value too long')) {
            return 'Un texto ingresado excede el tamaño máximo permitido.';
        }
        if (str_contains($errorMsg, 'invalid input syntax')) {
            return 'Un dato tiene un formato inválido para su campo.';
        }
        if (str_contains($errorMsg, 'unique constraint')) {
            return 'El registro contiene un dato que ya existe y debe ser único.';
        }

        return "No se pudo $accion el registro. Revise los datos ingresados.";
    }

    private function validarEnum(mixed $valor, array $permitidos): ?string
    {
        $valor = strtoupper(trim((string) $valor));

        return in_array($valor, $permitidos, true) ? $valor : null;
    }

    private function str(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }

    private function val(array $datos, string $key): mixed
    {
        return $datos[$key] ?? '';
    }

    private function validarIntNull(mixed $valor): ?int
    {
        $valor = trim((string) $valor);
        if ($valor === '' || !is_numeric($valor)) {
            return null;
        }

        return (int) $valor;
    }

    private function validarCoordenada(mixed $valor, float $min, float $max): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || !is_numeric($valor)) {
            return null;
        }
        $num = (float) $valor;
        if ($num < $min || $num > $max) {
            return null;
        }

        return $valor;
    }

    private function validarIp(mixed $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }
        $sinMascara = explode('/', $ip)[0];
        if (filter_var($sinMascara, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return null;
    }
}
