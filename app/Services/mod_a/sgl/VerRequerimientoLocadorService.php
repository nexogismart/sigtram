<?php

namespace App\Services\mod_a\sgl;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pago a proveedores — Locador
 * (migrado desde ver_requerimiento_locador.php + lista_pago_proveedores.php?intGrnte=2).
 */
class VerRequerimientoLocadorService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private readonly string $schemaSistema;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function areaUsuario(): int
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
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
            'tiposPlantilla' => $this->tiposDocumento([63]),
            'semilla' => '1-' . date('Y'),
        ];
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
            $items = array_map(function ($row) use (&$n) {
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
                'items' => $items,
                'total' => $total,
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'paginas' => max(1, (int) ceil($total / $porPagina)),
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_locador] listar: ' . $e->getMessage());

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

            return $this->ok([
                'id' => (int) $cab->cdgo_pgo_prvdres,
                'asunto' => (string) ($cab->de_pgo_prvdres ?? ''),
                'estado' => (int) ($cab->in_esta_prvdres ?? 0),
                'cdgo_dtos_ntrnos' => (int) ($cab->cdgo_dtos_ntrnos ?? 0),
                'items' => $this->itemsDeRequerimiento($id, $areaId),
            ]);
        } catch (Throwable $e) {
            Log::error('[ver_requerimiento_locador] detalle: ' . $e->getMessage());

            return $this->fail('No se pudo cargar el requerimiento.');
        }
    }

    public function guardarPlantilla(int $id, ?UploadedFile $archivo): array
    {
        $areaId = $this->areaUsuario();
        $userId = (int) (Auth::user()?->cdgo_lgin ?? 0);
        $cab = $this->cabecera($id, $areaId);
        if ($cab === null) {
            return $this->fail('No se encontró el requerimiento.');
        }
        if (! $archivo instanceof UploadedFile) {
            return $this->fail('Debe adjuntar el archivo Word de la plantilla.');
        }

        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if (! in_array($ext, ['doc', 'docx'], true)) {
            return $this->fail('La plantilla debe ser un archivo Word (.doc o .docx).');
        }

        $nombre = 'tdr' . $userId . '.' . $ext;
        $dirs = [
            storage_path('app/mod_gene/mod_tram_inter/adjunto/plantilla_generada'),
            storage_path('app/private/mod_gene/mod_tram_inter/adjunto/plantilla_generada'),
        ];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        $destino = $dirs[0] . DIRECTORY_SEPARATOR . $nombre;
        if (is_file($destino)) {
            @unlink($destino);
        }
        $archivo->move($dirs[0], $nombre);
        if (is_file($destino) && $dirs[1] !== $dirs[0]) {
            @copy($destino, $dirs[1] . DIRECTORY_SEPARATOR . $nombre);
        }

        $legacyRoot = rtrim((string) config('filesystems.legacy_sigtram_root', base_path('../sigtram_antiguo')), '/\\');
        $legacyDir = $legacyRoot . '/Presentacion/mod_gene/mod_tram_inter/adjunto/plantilla_generada';
        if (is_dir($legacyDir) || @mkdir($legacyDir, 0775, true)) {
            @copy($destino, $legacyDir . DIRECTORY_SEPARATOR . $nombre);
        }

        DB::table(self::SCHEMA_LOGISTICA . 'p_pgo_prvdres')
            ->where('cdgo_pgo_prvdres', $id)
            ->where('cdgo_area', $areaId)
            ->update(['in_esta' => 11]);

        return $this->ok(['id' => $id], 'Se agregó el archivo correctamente.');
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

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::select(
            "SELECT DISTINCT t.cdgo_tpo_trmte AS id, t.de_tpo_trmte AS nombre
             FROM {$this->schemaSistema}r_tpo_trmte_areas r
             INNER JOIN {$this->schemaSistema}a_tpo_trmtes t ON r.cdgo_tpo_trmte = t.cdgo_tpo_trmte
             WHERE r.in_esta = '1' AND t.cdgo_tpo_trmte IN ({$placeholders})
             ORDER BY t.de_tpo_trmte ASC",
            $ids
        );

        return array_map(fn ($r) => [
            'id' => (int) $r->id,
            'nombre' => (string) $r->nombre,
        ], $rows);
    }

    private function cabecera(int $id, int $areaId): ?object
    {
        return DB::selectOne(
            'SELECT p.cdgo_pgo_prvdres, p.de_pgo_prvdres, p.in_esta AS in_esta_prvdres, p.cdgo_dtos_ntrnos
             FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p
             WHERE p.in_esta NOT IN (0) AND p.cdgo_pgo_prvdres = ? AND p.cdgo_area = ?',
            [$id, $areaId]
        );
    }

    private function itemsDeRequerimiento(int $id, int $areaId): array
    {
        $rows = DB::select(
            'SELECT h.cdgo_deta_pgo_prvdres, h.nu_cntdad_prvdres, h.de_deta_pgo_prvdres, h.nu_mnto,
                    h.nu_sga, h.de_urls_pgo, h.de_urls_anxos, h.in_esta AS in_esta_pgo
             FROM ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres h
             INNER JOIN ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres p ON h.cdgo_pgo_prvdres = p.cdgo_pgo_prvdres
             WHERE h.in_esta NOT IN (0) AND p.cdgo_pgo_prvdres = ? AND p.cdgo_area = ?
             ORDER BY h.cdgo_deta_pgo_prvdres ASC',
            [$id, $areaId]
        );

        $n = 0;
        $out = [];
        foreach ($rows as $row) {
            $n++;
            $pendMemo = DB::table(self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos')
                ->where('cdgo_deta_pgo_prvdres', (int) $row->cdgo_deta_pgo_prvdres)
                ->where('in_esta', 4)
                ->whereNull('cdgo_dtos_ntrnos_cnfrmdad')
                ->exists();

            $out[] = [
                'n' => $n,
                'id' => (int) $row->cdgo_deta_pgo_prvdres,
                'cantidad' => $row->nu_cntdad_prvdres !== null ? (int) $row->nu_cntdad_prvdres : null,
                'descripcion' => (string) ($row->de_deta_pgo_prvdres ?? ''),
                'monto' => $row->nu_mnto !== null ? (float) $row->nu_mnto : null,
                'siga' => trim((string) ($row->nu_sga ?? '')),
                'url_tdr' => $this->urlOpcional($row->de_urls_pgo ?? null),
                'url_ane' => $this->urlOpcional($row->de_urls_anxos ?? null),
                'in_esta' => (int) ($row->in_esta_pgo ?? 1),
                'icono' => $pendMemo ? 'alerta' : 'ver',
                'titulo_icono' => $pendMemo ? 'Esperando Conformidad' : 'Solicitar Conformidad',
            ];
        }

        return $out;
    }

    private function detalleVacio(): array
    {
        return [
            'id' => 0,
            'asunto' => '',
            'estado' => 0,
            'cdgo_dtos_ntrnos' => 0,
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

    private function ok(mixed $data, string $message = ''): array
    {
        return ['success' => true, 'data' => $data, 'message' => $message];
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'error' => $message, 'message' => $message];
    }
}
