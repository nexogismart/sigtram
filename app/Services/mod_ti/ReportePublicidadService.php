<?php

namespace App\Services\mod_ti;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gestión de reportes de publicidad estatal
 * (migrado desde sigtram_antiguo/Presentacion/mod_ti/reporte_publicidad/).
 */
class ReportePublicidadService
{
    private const SCHEMA = 'reporte.';

    private readonly string $adjuntosPath;

    public function __construct()
    {
        $this->adjuntosPath = storage_path('app/mod_ti/reporte_publicidad/adjuntos');
        if (! is_dir($this->adjuntosPath)) {
            @mkdir($this->adjuntosPath, 0775, true);
        }
    }

    public function adjuntosPath(): string
    {
        return $this->adjuntosPath;
    }

    /**
     * @return array{status: string, message: string, data?: mixed}
     */
    public function listar(): array
    {
        try {
            $rows = DB::select(
                'SELECT cdgo_reporte, mdio_empleado, ubicacion, fech_ini, fech_fin, enlace, fundamento, estado
                 FROM ' . self::SCHEMA . 'reporte
                 WHERE estado = 1
                 ORDER BY cdgo_reporte DESC'
            );

            $reportes = array_map(static function ($row) {
                return [
                    'cdgo_reporte' => (int) $row->cdgo_reporte,
                    'mdio_empleado' => (string) ($row->mdio_empleado ?? ''),
                    'ubicacion' => (string) ($row->ubicacion ?? ''),
                    'fech_ini' => $row->fech_ini ? date('Y-m-d', strtotime((string) $row->fech_ini)) : '',
                    'fech_fin' => $row->fech_fin ? date('Y-m-d', strtotime((string) $row->fech_fin)) : '',
                    'enlace' => (string) ($row->enlace ?? ''),
                    'fundamento' => (string) ($row->fundamento ?? ''),
                    'estado' => (int) ($row->estado ?? 0),
                ];
            }, $rows);

            return $this->ok('Reportes obtenidos correctamente', $reportes);
        } catch (Throwable $e) {
            Log::error('[reporte_publicidad] listar: ' . $e->getMessage());

            return $this->fail('Error al obtener reportes.');
        }
    }

    /**
     * @return array{status: string, message: string, data?: mixed}
     */
    public function obtenerCuadros(int $reporteId): array
    {
        if ($reporteId <= 0) {
            return $this->fail('No se especificó un ID de reporte válido');
        }

        try {
            $reporte = DB::selectOne(
                'SELECT enlace, fundamento
                 FROM ' . self::SCHEMA . 'reporte
                 WHERE cdgo_reporte = ? AND estado = 1',
                [$reporteId]
            );

            if (! $reporte) {
                return $this->fail('El reporte solicitado no existe o fue eliminado');
            }

            $cuadros = $this->listarCuadros($reporteId);

            return $this->ok('Datos obtenidos correctamente', [
                'reporte' => [
                    'enlace' => (string) ($reporte->enlace ?? ''),
                    'fundamento' => (string) ($reporte->fundamento ?? ''),
                ],
                'cuadros' => $cuadros,
            ]);
        } catch (Throwable $e) {
            Log::error('[reporte_publicidad] obtenerCuadros: ' . $e->getMessage());

            return $this->fail('Error al obtener datos.');
        }
    }

    /**
     * @return array{status: string, message: string, data?: mixed}
     */
    public function obtenerParaEditar(int $reporteId): array
    {
        if ($reporteId <= 0) {
            return $this->fail('No se especificó un ID de reporte válido');
        }

        try {
            $reporte = DB::selectOne(
                'SELECT cdgo_reporte, mdio_empleado, ubicacion, fech_ini, fech_fin,
                        enlace, fundamento, img1, img2, img3, img4, img5, img6
                 FROM ' . self::SCHEMA . 'reporte
                 WHERE cdgo_reporte = ? AND estado = 1',
                [$reporteId]
            );

            if (! $reporte) {
                return $this->fail('El reporte solicitado no existe o fue eliminado');
            }

            $data = [
                'cdgo_reporte' => (int) $reporte->cdgo_reporte,
                'mdio_empleado' => (string) ($reporte->mdio_empleado ?? ''),
                'ubicacion' => (string) ($reporte->ubicacion ?? ''),
                'fech_ini' => $reporte->fech_ini ? date('Y-m-d', strtotime((string) $reporte->fech_ini)) : '',
                'fech_fin' => $reporte->fech_fin ? date('Y-m-d', strtotime((string) $reporte->fech_fin)) : '',
                'enlace' => (string) ($reporte->enlace ?? ''),
                'fundamento' => (string) ($reporte->fundamento ?? ''),
                'imagenes' => [
                    $reporte->img1 ?: null,
                    $reporte->img2 ?: null,
                    $reporte->img3 ?: null,
                    $reporte->img4 ?: null,
                    $reporte->img5 ?: null,
                    $reporte->img6 ?: null,
                ],
            ];

            return $this->ok('Datos obtenidos correctamente', [
                'reporte' => $data,
                'cuadros' => $this->listarCuadros($reporteId),
            ]);
        } catch (Throwable $e) {
            Log::error('[reporte_publicidad] obtenerParaEditar: ' . $e->getMessage());

            return $this->fail('Error al obtener datos para edición.');
        }
    }

    /**
     * Datos completos para PDF.
     *
     * @return array{status: string, message: string, data?: mixed}
     */
    public function obtenerParaPdf(int $reporteId): array
    {
        if ($reporteId <= 0) {
            return $this->fail('No se especificó un ID de reporte válido');
        }

        try {
            $reporte = DB::selectOne(
                'SELECT cdgo_reporte, mdio_empleado, ubicacion, fech_ini, fech_fin,
                        enlace, fundamento, created_at,
                        img1, img2, img3, img4, img5, img6
                 FROM ' . self::SCHEMA . 'reporte
                 WHERE cdgo_reporte = ? AND estado = 1',
                [$reporteId]
            );

            if (! $reporte) {
                return $this->fail('El reporte solicitado no existe o fue eliminado');
            }

            $imagenes = array_values(array_filter([
                $reporte->img1,
                $reporte->img2,
                $reporte->img3,
                $reporte->img4,
                $reporte->img5,
                $reporte->img6,
            ]));

            $imagenesPaths = [];
            foreach ($imagenes as $img) {
                $path = $this->rutaAdjuntoSegura((string) $img);
                if ($path !== null) {
                    $imagenesPaths[] = $path;
                }
            }

            return $this->ok('OK', [
                'reporte' => [
                    'cdgo_reporte' => (int) $reporte->cdgo_reporte,
                    'mdio_empleado' => (string) ($reporte->mdio_empleado ?? ''),
                    'ubicacion' => (string) ($reporte->ubicacion ?? ''),
                    'fech_ini' => (string) ($reporte->fech_ini ?? ''),
                    'fech_fin' => (string) ($reporte->fech_fin ?? ''),
                    'enlace' => (string) ($reporte->enlace ?? ''),
                    'fundamento' => (string) ($reporte->fundamento ?? ''),
                ],
                'cuadros' => $this->listarCuadros($reporteId),
                'imagenes' => $imagenesPaths,
            ]);
        } catch (Throwable $e) {
            Log::error('[reporte_publicidad] obtenerParaPdf: ' . $e->getMessage());

            return $this->fail('Error al obtener datos del PDF.');
        }
    }

    /**
     * @param  array{modalMode: string, cdgo_reporte?: int, mdio_empleado: string, ubicacion: string, fech_ini: string, fech_fin: string, enlace?: string, fundamento?: string, cuadros?: array}  $data
     * @param  list<UploadedFile>  $imagenes
     * @return array{status: string, message: string, data?: mixed}
     */
    public function guardar(array $data, int $usuarioId, array $imagenes = []): array
    {
        $mode = (string) ($data['modalMode'] ?? '');
        $cdgoReporte = (int) ($data['cdgo_reporte'] ?? 0);
        $mdioEmpleado = mb_strtoupper(trim((string) ($data['mdio_empleado'] ?? '')), 'UTF-8');
        $ubicacion = mb_strtoupper(trim((string) ($data['ubicacion'] ?? '')), 'UTF-8');
        $fechIni = trim((string) ($data['fech_ini'] ?? ''));
        $fechFin = trim((string) ($data['fech_fin'] ?? ''));
        $enlace = trim((string) ($data['enlace'] ?? ''));
        $fundamento = mb_strtoupper(trim((string) ($data['fundamento'] ?? '')), 'UTF-8');
        $cuadros = is_array($data['cuadros'] ?? null) ? $data['cuadros'] : [];

        if ($mdioEmpleado === '' || $ubicacion === '' || $fechIni === '' || $fechFin === '') {
            return $this->fail('Todos los campos marcados como requeridos deben ser completados');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechIni) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechFin)) {
            return $this->fail('Fechas inválidas.');
        }

        try {
            DB::beginTransaction();

            if ($mode === 'nuevo') {
                $cdgoReporte = (int) DB::table(self::SCHEMA . 'reporte')->insertGetId([
                    'mdio_empleado' => $mdioEmpleado,
                    'ubicacion' => $ubicacion,
                    'fech_ini' => $fechIni,
                    'fech_fin' => $fechFin,
                    'enlace' => $enlace,
                    'fundamento' => $fundamento,
                    'id_created' => $usuarioId,
                    'created_at' => now(),
                    'estado' => 1,
                ], 'cdgo_reporte');
            } elseif ($mode === 'editar') {
                if ($cdgoReporte <= 0) {
                    throw new \RuntimeException('ID de reporte inválido para edición.');
                }

                $updated = DB::table(self::SCHEMA . 'reporte')
                    ->where('cdgo_reporte', $cdgoReporte)
                    ->where('estado', 1)
                    ->update([
                        'mdio_empleado' => $mdioEmpleado,
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
            } else {
                throw new \RuntimeException('Modo de guardado no válido.');
            }

            foreach ($cuadros as $cuadro) {
                if (! is_array($cuadro)) {
                    continue;
                }
                $empresa = trim((string) ($cuadro['empresa'] ?? ''));
                $medida = trim((string) ($cuadro['medida'] ?? ''));
                $cantidad = (float) ($cuadro['cantidad'] ?? 0);
                $inversion = (float) ($cuadro['inversion'] ?? 0);

                if ($empresa === '' || $medida === '') {
                    continue;
                }

                DB::table(self::SCHEMA . 'cuadro')->insert([
                    'cdgo_reporte' => $cdgoReporte,
                    'empresa' => $empresa,
                    'medida' => $medida,
                    'cantidad' => $cantidad,
                    'inversion' => $inversion,
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
            Log::error('[reporte_publicidad] guardar: ' . $e->getMessage());

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
            Log::error('[reporte_publicidad] eliminar: ' . $e->getMessage());

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

        $path = $this->adjuntosPath . DIRECTORY_SEPARATOR . $archivo;
        if (! is_file($path)) {
            // Fallback al adjunto legacy
            $legacy = base_path('../sigtram_antiguo/Presentacion/mod_ti/reporte_publicidad/adjuntos/' . $archivo);
            if (is_file($legacy)) {
                $path = $legacy;
            } else {
                return null;
            }
        }

        $real = realpath($path);
        if ($real === false) {
            return null;
        }

        return $real;
    }

    /**
     * @return list<array{cdgo_cuadro: int, cdgo_reporte: int, empresa: string, medida: string, cantidad: float, inversion: float}>
     */
    private function listarCuadros(int $reporteId): array
    {
        $rows = DB::select(
            'SELECT cdgo_cuadro, cdgo_reporte, empresa, medida, cantidad, inversion
             FROM ' . self::SCHEMA . 'cuadro
             WHERE cdgo_reporte = ? AND estado = 1
             ORDER BY cdgo_cuadro ASC',
            [$reporteId]
        );

        return array_map(static function ($row) {
            return [
                'cdgo_cuadro' => (int) $row->cdgo_cuadro,
                'cdgo_reporte' => (int) $row->cdgo_reporte,
                'empresa' => (string) ($row->empresa ?? ''),
                'medida' => (string) ($row->medida ?? ''),
                'cantidad' => (float) ($row->cantidad ?? 0),
                'inversion' => (float) ($row->inversion ?? 0),
            ];
        }, $rows);
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    private function guardarImagenes(int $reporteId, array $archivos, int $usuarioId): void
    {
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
            $file->move($this->adjuntosPath, $nuevoNombre);
            $campos['img' . $indice] = $nuevoNombre;
        }

        if ($campos === []) {
            return;
        }

        DB::table(self::SCHEMA . 'reporte')
            ->where('cdgo_reporte', $reporteId)
            ->where('estado', 1)
            ->update([
                'img1' => null,
                'img2' => null,
                'img3' => null,
                'img4' => null,
                'img5' => null,
                'img6' => null,
                'id_update' => $usuarioId,
                'update_at' => now(),
            ]);

        DB::table(self::SCHEMA . 'reporte')
            ->where('cdgo_reporte', $reporteId)
            ->update($campos);
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
