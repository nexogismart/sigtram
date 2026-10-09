<?php

namespace App\Services\mod_ti;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reportes de publicidad estatal 2026 (ANEXO 2)
 * (migrado desde sigtram_antiguo/Presentacion/mod_ti/reporte_publicidad_2026/).
 */
class ReportePublicidad2026Service
{
    private const SCHEMA = 'reporte.';

    private const ANIO_MIN = 2026;

    private const POR_PAGINA = 10;

    private readonly string $adjuntosPath;

    public function __construct()
    {
        $this->adjuntosPath = storage_path('app/mod_ti/reporte_publicidad_2026/adjuntos');
        if (! is_dir($this->adjuntosPath)) {
            @mkdir($this->adjuntosPath, 0775, true);
        }
    }

    /**
     * @return array{status: string, message: string, data?: mixed}
     */
    public function listar(int $pagina = 1): array
    {
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * self::POR_PAGINA;

        try {
            $totalRow = DB::selectOne(
                'SELECT COUNT(DISTINCT r.cdgo_reporte) AS total
                 FROM ' . self::SCHEMA . 'reporte r
                 INNER JOIN ' . self::SCHEMA . 'cuadro c ON r.cdgo_reporte = c.cdgo_reporte
                 WHERE r.estado = 1
                   AND c.estado = 1
                   AND EXTRACT(YEAR FROM c.created_at) >= ?',
                [self::ANIO_MIN]
            );
            $totalRegistros = (int) ($totalRow->total ?? 0);
            $totalPaginas = (int) max(1, (int) ceil($totalRegistros / self::POR_PAGINA));
            if ($totalRegistros === 0) {
                $totalPaginas = 0;
            }

            $totalActivosRow = DB::selectOne(
                'SELECT COUNT(*) AS total FROM ' . self::SCHEMA . 'reporte WHERE estado = 1'
            );
            $totalActivos = (int) ($totalActivosRow->total ?? 0);

            $rows = DB::select(
                'WITH reportes_activos AS (
                    SELECT r.cdgo_reporte,
                           (? - ROW_NUMBER() OVER (ORDER BY r.cdgo_reporte DESC) + 1) AS contador
                    FROM ' . self::SCHEMA . 'reporte r
                    WHERE r.estado = 1
                )
                SELECT r.cdgo_reporte,
                       COALESCE(
                           NULLIF(TRIM(r.mdio_empleado), \'\'),
                           STRING_AGG(DISTINCT c.tipo_medio_empleado, \', \' ORDER BY c.tipo_medio_empleado),
                           \'\'
                       ) AS mdio_empleado,
                       COALESCE(r.ubicacion, \'\') AS ubicacion,
                       r.fech_ini, r.fech_fin,
                       COALESCE(r.enlace, \'\') AS enlace,
                       COALESCE(r.fundamento, \'\') AS fundamento,
                       ra.contador
                FROM ' . self::SCHEMA . 'reporte r
                INNER JOIN ' . self::SCHEMA . 'cuadro c ON r.cdgo_reporte = c.cdgo_reporte AND c.estado = 1
                INNER JOIN reportes_activos ra ON r.cdgo_reporte = ra.cdgo_reporte
                WHERE r.estado = 1
                  AND EXTRACT(YEAR FROM c.created_at) >= ?
                GROUP BY r.cdgo_reporte, r.mdio_empleado, r.ubicacion, r.fech_ini, r.fech_fin,
                         r.enlace, r.fundamento, ra.contador
                ORDER BY r.cdgo_reporte DESC
                LIMIT ? OFFSET ?',
                [$totalActivos, self::ANIO_MIN, self::POR_PAGINA, $offset]
            );

            $reportes = array_map(static function ($row) {
                return [
                    'cdgo_reporte' => (int) $row->cdgo_reporte,
                    'contador' => (int) ($row->contador ?? 0),
                    'mdio_empleado' => trim((string) ($row->mdio_empleado ?? '')),
                    'ubicacion' => trim((string) ($row->ubicacion ?? '')),
                    'fech_ini' => $row->fech_ini ? date('Y-m-d', strtotime((string) $row->fech_ini)) : '',
                    'fech_fin' => $row->fech_fin ? date('Y-m-d', strtotime((string) $row->fech_fin)) : '',
                    'enlace' => (string) ($row->enlace ?? ''),
                    'fundamento' => (string) ($row->fundamento ?? ''),
                ];
            }, $rows);

            return $this->ok('Reportes obtenidos correctamente', [
                'reportes' => $reportes,
                'paginacion' => [
                    'pagina_actual' => $pagina,
                    'total_paginas' => $totalPaginas,
                    'total_registros' => $totalRegistros,
                    'items_por_pagina' => self::POR_PAGINA,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('[reporte_publicidad_2026] listar: ' . $e->getMessage());

            return $this->fail('Error al obtener reportes: ' . $e->getMessage());
        }
    }

    /**
     * @return array{status: string, message: string, data?: mixed}
     */
    public function obtener(int $reporteId): array
    {
        if ($reporteId <= 0) {
            return $this->fail('No se especificó un ID de reporte válido');
        }

        try {
            $reporte = DB::selectOne(
                'SELECT cdgo_reporte, ubicacion, fech_ini, fech_fin,
                        COALESCE(enlace, \'\') AS enlace_link, fundamento,
                        img1, img2, img3, img4, img5, img6
                 FROM ' . self::SCHEMA . 'reporte
                 WHERE cdgo_reporte = ? AND estado = 1',
                [$reporteId]
            );

            if (! $reporte) {
                return $this->fail('El reporte solicitado no existe o fue eliminado');
            }

            $fechaIni = $this->descomponerFecha($reporte->fech_ini ?? null);
            $fechaFin = $this->descomponerFecha($reporte->fech_fin ?? null);

            $imagenes = [];
            for ($i = 1; $i <= 6; $i++) {
                $campo = 'img' . $i;
                if (! empty($reporte->$campo)) {
                    $imagenes[] = $reporte->$campo;
                }
            }

            $data = [
                'cdgo_reporte' => (int) $reporte->cdgo_reporte,
                'ubicacion' => (string) ($reporte->ubicacion ?? ''),
                'enlace_link' => (string) ($reporte->enlace_link ?? ''),
                'fundamento' => (string) ($reporte->fundamento ?? ''),
                'periodo_difusion_ini_dia' => $fechaIni['dia'],
                'periodo_difusion_ini_mes' => $fechaIni['mes'],
                'periodo_difusion_ini_anio' => $fechaIni['anio'],
                'periodo_difusion_fin_dia' => $fechaFin['dia'],
                'periodo_difusion_fin_mes' => $fechaFin['mes'],
                'periodo_difusion_fin_anio' => $fechaFin['anio'],
                'imagenes' => $imagenes,
            ];

            return $this->ok('Datos obtenidos correctamente', [
                'reporte' => $data,
                'detalles' => $this->listarDetalles($reporteId),
            ]);
        } catch (Throwable $e) {
            Log::error('[reporte_publicidad_2026] obtener: ' . $e->getMessage());

            return $this->fail('Error al obtener datos.');
        }
    }

    /**
     * @return array{status: string, message: string, data?: mixed}
     */
    public function obtenerParaPdf(int $reporteId): array
    {
        if ($reporteId <= 0) {
            return $this->fail('No se especificó un ID de reporte válido');
        }

        try {
            $reporte = DB::selectOne(
                'SELECT cdgo_reporte, ubicacion, fech_ini, fech_fin,
                        COALESCE(enlace, \'\') AS enlace_link, fundamento,
                        img1, img2, img3, img4, img5, img6
                 FROM ' . self::SCHEMA . 'reporte
                 WHERE cdgo_reporte = ? AND estado = 1',
                [$reporteId]
            );

            if (! $reporte) {
                return $this->fail('El reporte solicitado no existe o fue eliminado');
            }

            $imagenes = [];
            for ($i = 1; $i <= 6; $i++) {
                $campo = 'img' . $i;
                if (! empty($reporte->$campo)) {
                    $path = $this->rutaAdjuntoSegura((string) $reporte->$campo);
                    if ($path !== null) {
                        $imagenes[] = $path;
                    }
                }
            }

            return $this->ok('OK', [
                'reporte' => [
                    'cdgo_reporte' => (int) $reporte->cdgo_reporte,
                    'ubicacion' => (string) ($reporte->ubicacion ?? ''),
                    'fech_ini' => (string) ($reporte->fech_ini ?? ''),
                    'fech_fin' => (string) ($reporte->fech_fin ?? ''),
                    'enlace_link' => (string) ($reporte->enlace_link ?? ''),
                    'fundamento' => (string) ($reporte->fundamento ?? ''),
                ],
                'detalles' => $this->listarDetalles($reporteId),
                'imagenes' => $imagenes,
            ]);
        } catch (Throwable $e) {
            Log::error('[reporte_publicidad_2026] obtenerParaPdf: ' . $e->getMessage());

            return $this->fail('Error al obtener datos del PDF.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $imagenes
     * @return array{status: string, message: string, data?: mixed}
     */
    public function guardar(array $data, int $usuarioId, array $imagenes = []): array
    {
        $mode = (string) ($data['modalMode'] ?? '');
        $cdgoReporte = (int) ($data['cdgo_reporte'] ?? 0);
        $ubicacion = mb_strtoupper(trim((string) ($data['ubicacion'] ?? '')), 'UTF-8');
        $fechIni = trim((string) ($data['fech_ini'] ?? ''));
        $fechFin = trim((string) ($data['fech_fin'] ?? ''));
        $enlace = mb_strtoupper(trim((string) ($data['enlace_link'] ?? '')), 'UTF-8');
        $fundamento = mb_strtoupper(trim((string) ($data['fundamento'] ?? '')), 'UTF-8');
        $detalles = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];

        $errores = [];
        if (! in_array($mode, ['nuevo', 'editar'], true)) {
            $errores[] = 'El modo de operación no es válido';
        }
        if ($mode === 'editar' && $cdgoReporte <= 0) {
            $errores[] = 'El código de reporte no es válido para edición';
        }
        if ($ubicacion === '') {
            $errores[] = 'El campo "Dirección o ubicación de la publicidad" es obligatorio';
        }
        if ($fechIni === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechIni)) {
            $errores[] = 'El campo "Fecha de inicio" del período de difusión es obligatorio';
        }
        if ($fechFin === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechFin)) {
            $errores[] = 'El campo "Fecha de fin" del período de difusión es obligatorio';
        }
        if ($fundamento === '') {
            $errores[] = 'El campo "Fundamento de impostergable necesidad o utilidad pública" es obligatorio';
        }
        if ($detalles === []) {
            $errores[] = 'Debe agregar al menos un detalle de información de publicidad';
        } else {
            foreach ($detalles as $index => $detalle) {
                if (! is_array($detalle)) {
                    continue;
                }
                $n = $index + 1;
                $empresa = trim((string) ($detalle['empresa_elaboro'] ?? ''));
                $tipoMedio = trim((string) ($detalle['tipo_medio_empleado'] ?? ''));
                $cantidad = $detalle['cantidad'] ?? '';
                if ($empresa === '') {
                    $errores[] = "El campo 'Empresa que elaboró' es obligatorio en el detalle #{$n}";
                }
                if ($tipoMedio === '') {
                    $errores[] = "El campo 'Tipo de medio empleado' es obligatorio en el detalle #{$n}";
                }
                if ($cantidad === '' || ! is_numeric($cantidad) || (float) $cantidad <= 0) {
                    $errores[] = "El campo 'Cantidad' es obligatorio y debe ser mayor a 0 en el detalle #{$n}";
                }
            }
        }

        if ($errores !== []) {
            return $this->fail('Por favor complete todos los campos obligatorios: ' . implode(', ', $errores));
        }

        try {
            DB::beginTransaction();

            if ($mode === 'nuevo') {
                $cdgoReporte = (int) DB::table(self::SCHEMA . 'reporte')->insertGetId([
                    'ubicacion' => $ubicacion,
                    'fech_ini' => $fechIni,
                    'fech_fin' => $fechFin,
                    'enlace' => $enlace,
                    'fundamento' => $fundamento,
                    'id_created' => $usuarioId,
                    'created_at' => now(),
                    'estado' => 1,
                ], 'cdgo_reporte');
            } else {
                $updated = DB::table(self::SCHEMA . 'reporte')
                    ->where('cdgo_reporte', $cdgoReporte)
                    ->where('estado', 1)
                    ->update([
                        'ubicacion' => $ubicacion,
                        'fech_ini' => $fechIni,
                        'fech_fin' => $fechFin,
                        'enlace' => $enlace,
                        'fundamento' => $fundamento,
                        'id_update' => $usuarioId,
                        'update_at' => now(),
                    ]);

                if ($updated === 0) {
                    throw new \RuntimeException('El reporte no existe o fue eliminado.');
                }

                DB::table(self::SCHEMA . 'cuadro')
                    ->where('cdgo_reporte', $cdgoReporte)
                    ->where('estado', 1)
                    ->update([
                        'estado' => 0,
                        'id_delete' => $usuarioId,
                        'delete_at' => now(),
                    ]);
            }

            foreach ($detalles as $detalle) {
                if (! is_array($detalle)) {
                    continue;
                }
                $empresa = mb_strtoupper(trim((string) ($detalle['empresa_elaboro'] ?? '')), 'UTF-8');
                $tipoMedio = mb_strtoupper(trim((string) ($detalle['tipo_medio_empleado'] ?? '')), 'UTF-8');
                $cantidad = (float) ($detalle['cantidad'] ?? 0);
                if ($empresa === '' || $tipoMedio === '') {
                    continue;
                }

                DB::table(self::SCHEMA . 'cuadro')->insert([
                    'cdgo_reporte' => $cdgoReporte,
                    'empresa' => $empresa,
                    'tipo_medio_empleado' => $tipoMedio,
                    'cantidad' => $cantidad,
                    'inversion' => 0,
                    'id_created' => $usuarioId,
                    'created_at' => now(),
                    'estado' => 1,
                ]);
            }

            if ($imagenes !== []) {
                $this->guardarImagenes($cdgoReporte, $imagenes, $usuarioId);
            }

            DB::commit();

            return $this->ok('Reporte guardado correctamente', ['cdgo_reporte' => $cdgoReporte]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('[reporte_publicidad_2026] guardar: ' . $e->getMessage());

            return $this->fail('Error al guardar el reporte: ' . $e->getMessage());
        }
    }

    /**
     * @return array{status: string, message: string, data?: mixed}
     */
    public function eliminar(int $reporteId, int $usuarioId): array
    {
        if ($reporteId <= 0) {
            return $this->fail('No se especificó un ID de reporte válido');
        }

        try {
            DB::beginTransaction();

            DB::table(self::SCHEMA . 'reporte')
                ->where('cdgo_reporte', $reporteId)
                ->update([
                    'estado' => 0,
                    'id_delete' => $usuarioId,
                    'delete_at' => now(),
                ]);

            DB::table(self::SCHEMA . 'cuadro')
                ->where('cdgo_reporte', $reporteId)
                ->where('estado', 1)
                ->update([
                    'estado' => 0,
                    'id_delete' => $usuarioId,
                    'delete_at' => now(),
                ]);

            DB::commit();

            return $this->ok('Reporte eliminado correctamente');
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('[reporte_publicidad_2026] eliminar: ' . $e->getMessage());

            return $this->fail('Error al eliminar el reporte.');
        }
    }

    public function rutaAdjuntoSegura(string $archivo): ?string
    {
        $archivo = basename($archivo);
        if ($archivo === '' || $archivo === '.' || $archivo === '..') {
            return null;
        }

        $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        $candidatos = [
            $this->adjuntosPath . DIRECTORY_SEPARATOR . $archivo,
            storage_path('app/mod_ti/reporte_publicidad/adjuntos/' . $archivo),
            base_path('../sigtram_antiguo/Presentacion/mod_ti/reporte_publicidad_2026/adjuntos/' . $archivo),
            base_path('../sigtram_antiguo/Presentacion/mod_ti/reporte_publicidad/adjuntos/' . $archivo),
        ];

        foreach ($candidatos as $path) {
            if (is_file($path)) {
                $real = realpath($path);

                return $real !== false ? $real : $path;
            }
        }

        return null;
    }

    /**
     * @return list<array{cdgo_cuadro: int, cdgo_reporte: int, empresa_elaboro: string, tipo_medio_empleado: string, cantidad: float}>
     */
    private function listarDetalles(int $reporteId): array
    {
        $rows = DB::select(
            'SELECT cdgo_cuadro, cdgo_reporte, empresa AS empresa_elaboro,
                    COALESCE(tipo_medio_empleado, \'\') AS tipo_medio_empleado, cantidad
             FROM ' . self::SCHEMA . 'cuadro
             WHERE cdgo_reporte = ? AND estado = 1
             ORDER BY cdgo_cuadro ASC',
            [$reporteId]
        );

        return array_map(static function ($row) {
            return [
                'cdgo_cuadro' => (int) $row->cdgo_cuadro,
                'cdgo_reporte' => (int) $row->cdgo_reporte,
                'empresa_elaboro' => (string) ($row->empresa_elaboro ?? ''),
                'tipo_medio_empleado' => (string) ($row->tipo_medio_empleado ?? ''),
                'cantidad' => (float) ($row->cantidad ?? 0),
            ];
        }, $rows);
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    private function guardarImagenes(int $reporteId, array $archivos, int $usuarioId): void
    {
        $existentes = DB::selectOne(
            'SELECT img1, img2, img3, img4, img5, img6 FROM ' . self::SCHEMA . 'reporte WHERE cdgo_reporte = ?',
            [$reporteId]
        );

        $campos = [];
        $indice = 0;

        foreach ($archivos as $file) {
            if ($indice >= 6) {
                break;
            }
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $mime = (string) $file->getMimeType();
            if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $ext = $mime === 'image/png' ? 'png' : 'jpg';
            }

            $indice++;
            $nuevoNombre = $reporteId . '_' . $indice . '.' . $ext;

            $campoPrev = 'img' . $indice;
            if ($existentes && ! empty($existentes->$campoPrev)) {
                $prev = $this->adjuntosPath . DIRECTORY_SEPARATOR . $existentes->$campoPrev;
                if (is_file($prev)) {
                    @unlink($prev);
                }
            }

            $file->move($this->adjuntosPath, $nuevoNombre);
            $campos['img' . $indice] = $nuevoNombre;
        }

        if ($campos === []) {
            return;
        }

        $campos['id_update'] = $usuarioId;
        $campos['update_at'] = now();

        DB::table(self::SCHEMA . 'reporte')
            ->where('cdgo_reporte', $reporteId)
            ->update($campos);
    }

    /**
     * @return array{dia: string|int, mes: string|int, anio: string|int}
     */
    private function descomponerFecha(mixed $fecha): array
    {
        if (empty($fecha)) {
            return ['dia' => '', 'mes' => '', 'anio' => ''];
        }
        $ts = strtotime((string) $fecha);
        if ($ts === false) {
            return ['dia' => '', 'mes' => '', 'anio' => ''];
        }

        return [
            'dia' => (int) date('j', $ts),
            'mes' => (int) date('n', $ts),
            'anio' => (int) date('Y', $ts),
        ];
    }

    private function ok(string $message, mixed $data = null): array
    {
        return ['status' => 'success', 'message' => $message, 'data' => $data];
    }

    private function fail(string $message): array
    {
        return ['status' => 'error', 'message' => $message, 'data' => null];
    }
}
