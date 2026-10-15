<?php

namespace App\Services\mod_a\sgl;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\SessionKeys;
use Throwable;

/**
 * Fichas de selección SE@CE
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/index.php
 * y mante_fichaseleccion.php).
 */
class FichaSeleccionSeaceService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private const CAMPOS = [
        'nomenclatura' => 'p_fcha_slccon.no_nmncltra',
        'contratacion' => 'a_tpo_objto.de_tpo_objto',
        'descripcion' => 'p_fcha_slccon.no_dscrpcon_objto',
        'valor' => 'p_fcha_slccon.nu_vlor_rfrncal',
        'seace' => 'a_vrson_seace.de_vrson_seace',
    ];

    private readonly string $schemaSistema;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function catalogos(): array
    {
        $l = self::SCHEMA_LOGISTICA;

        return [
            'tiposSeleccion' => DB::table($l . 'a_tpo_slccon')
                ->where('in_esta', '1')
                ->orderBy('de_tpo_slccon')
                ->get(['cdgo_tpo_slccon as id', 'de_tpo_slccon as nombre'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'nombre' => (string) $r->nombre])
                ->all(),
            'versionesSeace' => DB::table($l . 'a_vrson_seace')
                ->where('in_esta', '1')
                ->orderBy('de_vrson_seace')
                ->get(['cdgo_vrson_seace as id', 'de_vrson_seace as nombre'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'nombre' => (string) $r->nombre])
                ->all(),
            'tiposObjeto' => DB::table($l . 'a_tpo_objto')
                ->where('in_esta', '1')
                ->orderBy('de_tpo_objto')
                ->get(['cdgo_tpo_objto as id', 'de_tpo_objto as nombre'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'nombre' => (string) $r->nombre])
                ->all(),
        ];
    }

    public function listar(array $filtros): array
    {
        $campo = (string) ($filtros['campo'] ?? '');
        $palabra = trim((string) ($filtros['palabra'] ?? ''));
        $desde = $this->normalizarFecha($filtros['desde'] ?? '');
        $hasta = $this->normalizarFecha($filtros['hasta'] ?? '');
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = (int) ($filtros['porPagina'] ?? 10);
        $porPagina = ($porPagina > 0 && $porPagina <= 100) ? $porPagina : 10;

        $query = $this->queryBase();

        if ($palabra !== '' && isset(self::CAMPOS[$campo])) {
            $col = self::CAMPOS[$campo];
            $query->whereRaw("UPPER(CAST({$col} AS TEXT)) LIKE UPPER(?)", ['%' . $palabra . '%']);
        }

        if ($desde && $hasta) {
            $query->whereRaw('DATE(p_fcha_slccon.fe_fcha_slccon) BETWEEN ? AND ?', [$desde, $hasta]);
        } elseif ($desde) {
            $query->whereRaw('DATE(p_fcha_slccon.fe_fcha_slccon) >= ?', [$desde]);
        } elseif ($hasta) {
            $query->whereRaw('DATE(p_fcha_slccon.fe_fcha_slccon) <= ?', [$hasta]);
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $lastPage);

        $rows = $query
            ->orderByDesc('p_fcha_slccon.fe_fcha_slccon')
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get();

        return [
            'status' => 'success',
            'data' => $rows->map(fn ($row) => $this->mapLista($row))->all(),
            'current_page' => $pagina,
            'last_page' => $lastPage,
            'total' => $total,
        ];
    }

    public function detalle(int $id): array
    {
        $ficha = [
            'id' => 0,
            'nomenclatura' => '',
            'fecha' => '',
            'tipo_seleccion' => 0,
            'version_seace' => 0,
            'identificador' => '',
            'convocatoria' => '',
            'tipo_objeto' => 0,
            'valor' => '',
            'descripcion' => '',
            'publicacion' => '',
        ];

        if ($id > 0) {
            $row = $this->queryBase()
                ->where('p_fcha_slccon.cdgo_fcha_slccon', $id)
                ->first();
            if (! $row) {
                return ['status' => 'error', 'message' => 'No se encontró la ficha.'];
            }
            $ficha = $this->mapDetalle($row);
        }

        return [
            'status' => 'success',
            'ficha' => $ficha,
            'cronograma' => $id > 0 ? $this->cronograma($id) : [],
            'participantes' => $id > 0 ? $this->expedientes($id, '1') : [],
            'apelaciones' => $id > 0 ? $this->expedientes($id, '2') : [],
        ];
    }

    public function guardar(array $datos): array
    {
        $id = (int) ($datos['id'] ?? 0);
        $nomenclatura = trim((string) ($datos['nomenclatura'] ?? ''));
        $fecha = $this->parseDateTime($datos['fecha'] ?? '');
        $tipoSeleccion = (int) ($datos['tipo_seleccion'] ?? 0);
        $versionSeace = (int) ($datos['version_seace'] ?? 0);
        $identificador = trim((string) ($datos['identificador'] ?? ''));
        $convocatoria = trim((string) ($datos['convocatoria'] ?? ''));
        $tipoObjeto = (int) ($datos['tipo_objeto'] ?? 0);
        $valor = trim((string) ($datos['valor'] ?? ''));
        $descripcion = mb_strtoupper(trim((string) ($datos['descripcion'] ?? '')), 'UTF-8');
        $publicacion = $this->parseDateTime($datos['publicacion'] ?? '');

        if ($nomenclatura === '') {
            return ['status' => 'error', 'message' => 'Debe ingresar la nomenclatura.'];
        }
        if ($fecha === null) {
            return ['status' => 'error', 'message' => 'Debe ingresar la fecha de la selección.'];
        }
        if ($tipoSeleccion <= 0) {
            return ['status' => 'error', 'message' => 'Debe seleccionar el tipo de selección.'];
        }
        if ($versionSeace <= 0) {
            return ['status' => 'error', 'message' => 'Debe seleccionar la versión del SE@CE.'];
        }
        if ($identificador === '') {
            return ['status' => 'error', 'message' => 'Debe ingresar el identificador de la convocatoria.'];
        }
        if ($tipoObjeto <= 0) {
            return ['status' => 'error', 'message' => 'Debe ingresar el tipo de objeto.'];
        }
        if ($descripcion === '') {
            return ['status' => 'error', 'message' => 'Debe ingresar la descripción del objeto.'];
        }
        if ($publicacion === null) {
            return ['status' => 'error', 'message' => 'Debe ingresar la fecha de la publicación.'];
        }

        $l = self::SCHEMA_LOGISTICA;
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
        $company = $this->compania();

        $payload = [
            'cdgo_tpo_slccon' => $tipoSeleccion,
            'cdgo_tpo_objto' => $tipoObjeto,
            'cdgo_vrson_seace' => $versionSeace,
            'nu_idntfcar_cnvctra' => $identificador,
            'no_nmncltra' => $nomenclatura,
            'fe_fcha_slccon' => $fecha,
            'fe_fcha_pblccon' => $publicacion,
            'nu_cnvctria' => $convocatoria,
            'no_dscrpcon_objto' => $descripcion,
            'nu_vlor_rfrncal' => $valor === '' ? null : $valor,
            'ts_usua_modi' => now(),
            'co_usua_modi' => $userId,
        ];

        try {
            DB::beginTransaction();

            if ($id <= 0) {
                $payload['cdgo_cmpnia'] = $company;
                $payload['in_esta'] = '1';
                $id = (int) DB::table($l . 'p_fcha_slccon')->insertGetId($payload, 'cdgo_fcha_slccon');
            } else {
                DB::table($l . 'p_fcha_slccon')
                    ->where('cdgo_fcha_slccon', $id)
                    ->update($payload);
            }

            $this->generarCronogramaSiFalta($id, $tipoSeleccion, $fecha);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('[ficha_seleccion_seace] guardar: ' . $e->getMessage());

            return ['status' => 'error', 'message' => 'No se pudo guardar la ficha.'];
        }

        return [
            'status' => 'success',
            'message' => 'Ingresado correctamente.',
            'id' => $id,
        ];
    }

    public function actualizarFechaCronograma(int $cronogramaId, string $campo, string $fecha): array
    {
        if (! in_array($campo, ['fe_incio', 'fe_fin'], true)) {
            return ['status' => 'error', 'message' => 'Campo no permitido.'];
        }

        $valor = $this->normalizarFecha($fecha);
        if (! $valor) {
            return ['status' => 'error', 'message' => 'Fecha inválida.'];
        }

        $l = self::SCHEMA_LOGISTICA;
        $updated = DB::table($l . 'r_fcha_crngrma')
            ->where('cdgo_fcha_crngrma', $cronogramaId)
            ->where('in_esta', '1')
            ->update([$campo => $valor]);

        if ($updated === 0) {
            return ['status' => 'error', 'message' => 'No se encontró la etapa.'];
        }

        $row = DB::table($l . 'r_fcha_crngrma')
            ->where('cdgo_fcha_crngrma', $cronogramaId)
            ->first([$campo]);

        return [
            'status' => 'success',
            'fecha' => $row && $row->{$campo} ? Carbon::parse($row->{$campo})->format('d/m/Y') : '',
        ];
    }

    private function generarCronogramaSiFalta(int $fichaId, int $tipoSeleccion, string $fechaSeleccion): void
    {
        $l = self::SCHEMA_LOGISTICA;
        $existe = (int) DB::table($l . 'r_fcha_crngrma')
            ->where('cdgo_fcha_slccon', $fichaId)
            ->count();
        if ($existe > 0) {
            return;
        }

        $items = DB::table($l . 'h_items_crngrma')
            ->where('cdgo_tpo_slccon', $tipoSeleccion)
            ->orderBy('nu_orden')
            ->get();

        $cursor = Carbon::parse($fechaSeleccion)->toDateString();
        $primero = true;
        foreach ($items as $item) {
            $itemId = (int) $item->cdgo_items_crngrma;
            if ($primero) {
                DB::table($l . 'p_fcha_slccon')
                    ->where('cdgo_fcha_slccon', $fichaId)
                    ->update(['cdgo_items_crngrma' => $itemId]);
                $primero = false;
            }

            $dias = (int) ($item->nu_dias_espra ?? 0);
            $fin = Carbon::parse($cursor)->addDays($dias)->toDateString();

            DB::table($l . 'r_fcha_crngrma')->insert([
                'cdgo_items_crngrma' => $itemId,
                'cdgo_fcha_slccon' => $fichaId,
                'fe_incio' => $cursor,
                'fe_fin' => $fin,
                'ho_incio' => null,
                'ho_fin' => null,
                'de_lgar_crngrma' => (string) ($item->de_lgar_crngrma ?? ''),
                'in_esta' => '1',
            ]);

            $cursor = $fin;
        }
    }

    private function queryBase()
    {
        $l = self::SCHEMA_LOGISTICA;
        $company = $this->compania();

        $query = DB::table($l . 'p_fcha_slccon')
            ->join($l . 'a_tpo_objto', 'p_fcha_slccon.cdgo_tpo_objto', '=', 'a_tpo_objto.cdgo_tpo_objto')
            ->join($l . 'a_vrson_seace', 'p_fcha_slccon.cdgo_vrson_seace', '=', 'a_vrson_seace.cdgo_vrson_seace')
            ->join($l . 'a_tpo_slccon', 'p_fcha_slccon.cdgo_tpo_slccon', '=', 'a_tpo_slccon.cdgo_tpo_slccon')
            ->join($l . 'h_items_crngrma', 'p_fcha_slccon.cdgo_items_crngrma', '=', 'h_items_crngrma.cdgo_items_crngrma')
            ->whereNotIn('p_fcha_slccon.in_esta', ['0'])
            ->select([
                'p_fcha_slccon.cdgo_fcha_slccon',
                'p_fcha_slccon.fe_fcha_slccon',
                'p_fcha_slccon.fe_fcha_pblccon',
                'p_fcha_slccon.no_nmncltra',
                'a_tpo_objto.cdgo_tpo_objto',
                'a_tpo_objto.de_tpo_objto',
                'p_fcha_slccon.no_dscrpcon_objto',
                'p_fcha_slccon.nu_vlor_rfrncal',
                'p_fcha_slccon.nu_idntfcar_cnvctra',
                'p_fcha_slccon.nu_cnvctria',
                'a_vrson_seace.cdgo_vrson_seace',
                'a_vrson_seace.de_vrson_seace',
                'a_tpo_slccon.cdgo_tpo_slccon',
                'a_tpo_slccon.de_tpo_slccon',
            ]);

        if ($company > 0) {
            $query->where('p_fcha_slccon.cdgo_cmpnia', $company);
        }

        return $query;
    }

    private function cronograma(int $fichaId): array
    {
        $l = self::SCHEMA_LOGISTICA;

        return DB::table($l . 'r_fcha_crngrma as r')
            ->join($l . 'h_items_crngrma as h', 'r.cdgo_items_crngrma', '=', 'h.cdgo_items_crngrma')
            ->where('r.in_esta', '1')
            ->where('r.cdgo_fcha_slccon', $fichaId)
            ->orderBy('r.cdgo_fcha_crngrma')
            ->get([
                'r.cdgo_fcha_crngrma',
                'h.no_items_crngrma',
                'r.fe_incio',
                'r.fe_fin',
                'r.de_lgar_crngrma',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->cdgo_fcha_crngrma,
                'etapa' => (string) $row->no_items_crngrma,
                'lugar' => trim((string) ($row->de_lgar_crngrma ?? '')),
                'inicio' => $row->fe_incio ? Carbon::parse($row->fe_incio)->format('d/m/Y') : '',
                'fin' => $row->fe_fin ? Carbon::parse($row->fe_fin)->format('d/m/Y') : '',
            ])
            ->all();
    }

    private function expedientes(int $fichaId, string $tipo): array
    {
        $l = self::SCHEMA_LOGISTICA;
        $s = $this->schemaSistema;

        return DB::table($s . 'p_dto_trmtes as p')
            ->join($l . 'r_fcha_trmtes as r', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->where('r.in_esta', '1')
            ->where('r.in_tpo_fcha_trmtes', $tipo)
            ->where('r.cdgo_fcha_slccon', $fichaId)
            ->orderByDesc('p.fe_ingr_trmte')
            ->get([
                'p.nu_expe_todo',
                'p.fe_ingr_trmte',
                'p.cdgo_dto_trmte',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->cdgo_dto_trmte,
                'expediente' => (string) $row->nu_expe_todo,
                'fecha' => $row->fe_ingr_trmte ? Carbon::parse($row->fe_ingr_trmte)->format('d/m/Y H:i:s') : '',
            ])
            ->all();
    }

    private function mapLista(object $row): array
    {
        $desc = (string) ($row->no_dscrpcon_objto ?? '');

        return [
            'id' => (int) $row->cdgo_fcha_slccon,
            'fecha' => $row->fe_fcha_slccon ? Carbon::parse($row->fe_fcha_slccon)->format('d/m/Y H:i:s') : '',
            'nomenclatura' => (string) $row->no_nmncltra,
            'contratacion' => (string) $row->de_tpo_objto,
            'descripcion' => $desc,
            'descripcion_corta' => mb_strlen($desc, 'UTF-8') > 50 ? mb_substr($desc, 0, 50, 'UTF-8') . '…' : $desc,
            'valor' => number_format((float) $row->nu_vlor_rfrncal, 2, '.', ','),
            'moneda' => 'SOLES',
            'seace' => (string) $row->de_vrson_seace,
        ];
    }

    private function mapDetalle(object $row): array
    {
        return [
            'id' => (int) $row->cdgo_fcha_slccon,
            'nomenclatura' => (string) $row->no_nmncltra,
            'fecha' => $this->toLocalInput($row->fe_fcha_slccon),
            'tipo_seleccion' => (int) $row->cdgo_tpo_slccon,
            'version_seace' => (int) $row->cdgo_vrson_seace,
            'identificador' => (string) ($row->nu_idntfcar_cnvctra ?? ''),
            'convocatoria' => (string) ($row->nu_cnvctria ?? ''),
            'tipo_objeto' => (int) $row->cdgo_tpo_objto,
            'valor' => $row->nu_vlor_rfrncal !== null ? (string) $row->nu_vlor_rfrncal : '',
            'descripcion' => (string) ($row->no_dscrpcon_objto ?? ''),
            'publicacion' => $this->toLocalInput($row->fe_fcha_pblccon),
        ];
    }

    private function toLocalInput($fecha): string
    {
        if (! $fecha) {
            return '';
        }

        return Carbon::parse($fecha)->format('Y-m-d\TH:i');
    }

    private function parseDateTime($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $valor)) {
                return Carbon::createFromFormat('Y-m-d\TH:i', substr($valor, 0, 16))->format('Y-m-d H:i:s');
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/', $valor)) {
                return Carbon::createFromFormat('d/m/Y H:i:s', $valor)->format('Y-m-d H:i:s');
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
                return Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d H:i:s');
            }

            return Carbon::parse($valor)->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function normalizarFecha($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
                return Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d');
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
                return substr($valor, 0, 10);
            }

            return Carbon::parse($valor)->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function compania(): int
    {
        return (int) session(SessionKeys::USER_COMPANY, 0);
    }
}
