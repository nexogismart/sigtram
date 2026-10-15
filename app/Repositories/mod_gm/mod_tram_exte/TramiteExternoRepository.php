<?php

declare(strict_types=1);

namespace App\Repositories\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;


use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\DTOs\Support\ParticipanteUDto;
use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Enums\mod_gm\EstadoTramiteExterno;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

/**
 * Repositorio de base de datos para Trámite Externo.
 * Implementa consultas desacopladas con Query Builder, bindings seguros
 * y filtrado estricto de vigencia in_esta = '1'.
 */
class TramiteExternoRepository implements TramiteExternoRepositoryInterface
{
    private string $schema;

    public function __construct(
        private readonly DatabaseManager $db
    ) {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientoActual(int $expedienteId): ?ExpedienteMovimientoDto
    {
        $fila = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->where('r.cdgo_dto_trmte', $expedienteId)
            ->where('r.in_esta', '1')
            ->select([
                'r.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'r.cdgo_trmte_dcmnto',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.fe_rcbdo_trmte',
                'r.de_obse',
            ])
            ->orderBy('r.cdgo_trmte_dcmnto', 'desc')
            ->first();

        if (!$fila) {
            return null;
        }

        // Obtener áreas asociadas a los cargos
        $areaSa = (int) ($this->db->table($this->schema . 'p_crgos')->where('cdgo_crgo', $fila->cdgo_crgo_sa)->value('cdgo_area') ?? 0);
        $areaEn = (int) ($this->db->table($this->schema . 'p_crgos')->where('cdgo_crgo', $fila->cdgo_crgo_en)->value('cdgo_area') ?? 0);

        return new ExpedienteMovimientoDto(
            expedienteId: (int) $fila->cdgo_dto_trmte,
            numeroExpediente: (string) ($fila->nu_expe_todo ?? ''),
            movimientoId: (int) $fila->cdgo_trmte_dcmnto,
            companiaId: (int) ($fila->cdgo_cmpnia ?? 0),
            cargoSalidaId: (int) $fila->cdgo_crgo_sa,
            cargoEntradaId: (int) $fila->cdgo_crgo_en,
            areaSalidaId: $areaSa,
            areaEntradaId: $areaEn,
            estadoId: (int) $fila->cdgo_estdo_trmte,
            inEsta: (string) $fila->in_esta,
            fechaMovimiento: $fila->fe_rcbdo_trmte ? (string) $fila->fe_rcbdo_trmte : null,
            observacion: $fila->de_obse ? (string) $fila->de_obse : null
        );
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientosActivosPorExpediente(int $expedienteId, int $companiaId): array
    {
        return $this->obtenerMovimientosDelExpediente($expedienteId, $companiaId, true);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientosHistoricosPorExpediente(int $expedienteId, int $companiaId): array
    {
        return $this->obtenerMovimientosDelExpediente($expedienteId, $companiaId, false);
    }

    /**
     * @return array<ExpedienteMovimientoDto>
     */
    private function obtenerMovimientosDelExpediente(
        int $expedienteId,
        int $companiaId,
        bool $soloActivos
    ): array {
        $query = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->where('r.cdgo_dto_trmte', $expedienteId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select([
                'r.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'r.cdgo_trmte_dcmnto',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.fe_rcbdo_trmte',
                'r.de_obse',
            ])
            ->orderBy('r.cdgo_trmte_dcmnto', 'asc');

        if ($soloActivos) {
            $query->where('r.in_esta', '1');
        }

        $filas = $query->get();

        if ($filas->isEmpty()) {
            return [];
        }

        // Obtener áreas de cargos en una sola consulta para evitar N+1
        $cargoIds = $filas->pluck('cdgo_crgo_sa')
            ->merge($filas->pluck('cdgo_crgo_en'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $areasPorCargo = [];
        if (!empty($cargoIds)) {
            $areasPorCargo = $this->db->table($this->schema . 'p_crgos')
                ->whereIn('cdgo_crgo', $cargoIds)
                ->pluck('cdgo_area', 'cdgo_crgo')
                ->all();
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $areaSa = (int) ($areasPorCargo[$fila->cdgo_crgo_sa] ?? 0);
            $areaEn = (int) ($areasPorCargo[$fila->cdgo_crgo_en] ?? 0);

            $resultado[] = new ExpedienteMovimientoDto(
                expedienteId: (int) $fila->cdgo_dto_trmte,
                numeroExpediente: (string) ($fila->nu_expe_todo ?? ''),
                movimientoId: (int) $fila->cdgo_trmte_dcmnto,
                companiaId: (int) ($fila->cdgo_cmpnia ?? 0),
                cargoSalidaId: (int) $fila->cdgo_crgo_sa,
                cargoEntradaId: (int) $fila->cdgo_crgo_en,
                areaSalidaId: $areaSa,
                areaEntradaId: $areaEn,
                estadoId: (int) $fila->cdgo_estdo_trmte,
                inEsta: (string) $fila->in_esta,
                fechaMovimiento: $fila->fe_rcbdo_trmte ? (string) $fila->fe_rcbdo_trmte : null,
                observacion: $fila->de_obse ? (string) $fila->de_obse : null
            );
        }

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientosPorMovimientoIds(array $movimientoIds, int $companiaId): array
    {
        if (empty($movimientoIds)) {
            return [];
        }

        $filas = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->whereIn('r.cdgo_trmte_dcmnto', $movimientoIds)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('r.in_esta', '1')
            ->select([
                'r.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'r.cdgo_trmte_dcmnto',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.fe_rcbdo_trmte',
                'r.de_obse',
            ])
            ->orderBy('r.cdgo_trmte_dcmnto', 'asc')
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        // Obtener áreas de cargos en una sola consulta para evitar N+1
        $cargoIds = $filas->pluck('cdgo_crgo_sa')
            ->merge($filas->pluck('cdgo_crgo_en'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $areasPorCargo = [];
        if (!empty($cargoIds)) {
            $areasPorCargo = $this->db->table($this->schema . 'p_crgos')
                ->whereIn('cdgo_crgo', $cargoIds)
                ->pluck('cdgo_area', 'cdgo_crgo')
                ->all();
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $areaSa = (int) ($areasPorCargo[$fila->cdgo_crgo_sa] ?? 0);
            $areaEn = (int) ($areasPorCargo[$fila->cdgo_crgo_en] ?? 0);

            $resultado[] = new ExpedienteMovimientoDto(
                expedienteId: (int) $fila->cdgo_dto_trmte,
                numeroExpediente: (string) ($fila->nu_expe_todo ?? ''),
                movimientoId: (int) $fila->cdgo_trmte_dcmnto,
                companiaId: (int) ($fila->cdgo_cmpnia ?? 0),
                cargoSalidaId: (int) $fila->cdgo_crgo_sa,
                cargoEntradaId: (int) $fila->cdgo_crgo_en,
                areaSalidaId: $areaSa,
                areaEntradaId: $areaEn,
                estadoId: (int) $fila->cdgo_estdo_trmte,
                inEsta: (string) $fila->in_esta,
                fechaMovimiento: $fila->fe_rcbdo_trmte ? (string) $fila->fe_rcbdo_trmte : null,
                observacion: $fila->de_obse ? (string) $fila->de_obse : null
            );
        }

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientosVigentesParaBloqueo(array $movimientoIds, int $companiaId): array
    {
        if (empty($movimientoIds)) {
            return [];
        }

        // Buscar estrictamente por r.cdgo_trmte_dcmnto con bloqueo pesimista (lockForUpdate)
        $filas = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->whereIn('r.cdgo_trmte_dcmnto', $movimientoIds)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('r.in_esta', '1')
            ->select([
                'r.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'r.cdgo_trmte_dcmnto',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.fe_rcbdo_trmte',
                'r.de_obse',
            ])
            ->orderBy('r.cdgo_trmte_dcmnto', 'asc')
            ->lockForUpdate()
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $cargoIds = $filas->pluck('cdgo_crgo_sa')
            ->merge($filas->pluck('cdgo_crgo_en'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $areasPorCargo = [];
        if (!empty($cargoIds)) {
            $areasPorCargo = $this->db->table($this->schema . 'p_crgos')
                ->whereIn('cdgo_crgo', $cargoIds)
                ->pluck('cdgo_area', 'cdgo_crgo')
                ->all();
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $areaSa = (int) ($areasPorCargo[$fila->cdgo_crgo_sa] ?? 0);
            $areaEn = (int) ($areasPorCargo[$fila->cdgo_crgo_en] ?? 0);

            $resultado[] = new ExpedienteMovimientoDto(
                expedienteId: (int) $fila->cdgo_dto_trmte,
                numeroExpediente: (string) ($fila->nu_expe_todo ?? ''),
                movimientoId: (int) $fila->cdgo_trmte_dcmnto,
                companiaId: (int) ($fila->cdgo_cmpnia ?? 0),
                cargoSalidaId: (int) $fila->cdgo_crgo_sa,
                cargoEntradaId: (int) $fila->cdgo_crgo_en,
                areaSalidaId: $areaSa,
                areaEntradaId: $areaEn,
                estadoId: (int) $fila->cdgo_estdo_trmte,
                inEsta: (string) $fila->in_esta,
                fechaMovimiento: $fila->fe_rcbdo_trmte ? (string) $fila->fe_rcbdo_trmte : null,
                observacion: $fila->de_obse ? (string) $fila->de_obse : null
            );
        }

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientosPorIds(array $expedienteIds, int $companiaId): array
    {
        if (empty($expedienteIds)) {
            return [];
        }

        // Buscar estrictamente por movimiento_id (identidad canónica de la fila de bandeja)
        return $this->obtenerMovimientosPorMovimientoIds($expedienteIds, $companiaId);
    }

    /**
     * {@inheritdoc}
     */
    public function construirQueryBandejaBase(
        ContextoUsuarioAutenticadoDto $contexto,
        string $bandeja,
        array $filtros
    ): Builder {
        $query = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->leftJoin($this->schema . 'a_tpo_trmtes as tt', 'tt.cdgo_tpo_trmte', '=', 'p.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'p_usrios as u', 'u.cdgo_usrios', '=', 'p.cdgo_usrios')
            ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'e.cdgo_estdo_trmte', '=', 'r.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'a_docmntos_trmtes as dt', 'dt.cdgo_docmnto_trmte', '=', 'p.cdgo_docmnto_trmte')
            ->leftJoin($this->schema . 'p_crgos as c', 'c.cdgo_crgo', '=', 'r.cdgo_crgo_en')
            ->leftJoin($this->schema . 'a_areas as a', 'a.cdgo_area', '=', 'c.cdgo_area')
            ->where('p.cdgo_cmpnia', $contexto->idCompania);

        $this->aplicarFiltroBandeja($query, $bandeja, $contexto);
        $this->aplicarCriteriosBusqueda($query, $filtros);

        $query->orderBy('r.cdgo_trmte_dcmnto', 'desc');

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function consultarBandeja(
        ContextoUsuarioAutenticadoDto $contexto,
        string $bandeja,
        array $filtros,
        int $pagina = 1,
        int $porPagina = 20
    ): array {
        $query = $this->construirQueryBandejaBase($contexto, $bandeja, $filtros);

        $total = $query->count();
        $offset = max(0, ($pagina - 1) * max(1, $porPagina));

        $queryBuilder = $query->select([
            'r.cdgo_trmte_dcmnto',
            'r.cdgo_dto_trmte',
            'p.nu_expe_todo',
            'p.fe_ingr_trmte',
            'p.cdgo_tpo_trmte',
            'p.in_docu_trmte',
            'tt.dc_tpo_trmte',
            'tt.de_tpo_trmte',
            'r.cdgo_estdo_trmte',
            'e.dc_estdo_trmte',
            'r.fe_rcbdo_trmte',
            'r.de_obse as de_obse_trmte',
            'p.de_obse_trmte as asunto_expediente',
            'u.no_crto',
            'u.nu_docu',
            'a.de_area',
            'a.dc_area',
            'c.de_crgo',
            'dt.nu_tiem_sile',
            'p.nu_tiem_sile_adcnal',
            'p.nu_foli_trmte',
            'p.cdgo_usrios as cdgo_usrios_trmte',
        ]);

        if ($porPagina > 0) {
            $queryBuilder->offset($offset)->limit($porPagina);
            $lastPage = (int) ceil($total / max(1, $porPagina));
        } else {
            $lastPage = 1;
        }

        $filas = $queryBuilder->get();

        $data = $filas->map(function ($row) use ($contexto) {
            $requiredProps = [
                'cdgo_dto_trmte',
                'cdgo_trmte_dcmnto',
                'cdgo_tpo_trmte',
                'in_docu_trmte',
                'dc_tpo_trmte',
                'de_tpo_trmte',
            ];
            foreach ($requiredProps as $prop) {
                if (!property_exists($row, $prop)) {
                    \Illuminate\Support\Facades\Log::error("Fila de bandeja externa carece de propiedad obligatoria garantizada por SELECT: {$prop}", [
                        'fila' => (array) $row,
                    ]);
                    throw new \UnexpectedValueException("La fila de la bandeja externa carece de la propiedad '{$prop}' garantizada por la consulta.");
                }
            }

            $documentoId = (int) $row->cdgo_dto_trmte;
            $movimientoId = (int) $row->cdgo_trmte_dcmnto;

            if ($documentoId <= 0 || $movimientoId <= 0) {
                \Illuminate\Support\Facades\Log::error("Fila de bandeja externa con identificadores inválidos o no positivos", [
                    'cdgo_dto_trmte' => $row->cdgo_dto_trmte,
                    'cdgo_trmte_dcmnto' => $row->cdgo_trmte_dcmnto,
                ]);
                throw new \UnexpectedValueException("Fila de bandeja externa con identificador de documento ({$documentoId}) o movimiento ({$movimientoId}) inválido.");
            }

            $estadoEnum = EstadoTramiteExterno::tryFrom((int) ($row->cdgo_estdo_trmte ?? 0));
            $nombreEstado = (string) ($row->dc_estdo_trmte ?? $estadoEnum?->label() ?? 'DESCONOCIDO');

            $cdgoTpoTrmte = (int) $row->cdgo_tpo_trmte;
            if ($cdgoTpoTrmte <= 0) {
                \Illuminate\Support\Facades\Log::error("Fila de bandeja externa con cdgo_tpo_trmte no positivo: {$row->cdgo_tpo_trmte}", [
                    'fila' => (array) $row,
                ]);
                throw new \UnexpectedValueException("Fila de bandeja externa con cdgo_tpo_trmte inválido.");
            }

            $dcTpo = trim((string) $row->dc_tpo_trmte);
            $deTpo = trim((string) $row->de_tpo_trmte);

            if ($dcTpo === '') {
                \Illuminate\Support\Facades\Log::error("Fila de bandeja externa carece de dc_tpo_trmte en catálogo para tipo {$cdgoTpoTrmte}", [
                    'fila' => (array) $row,
                ]);
                throw new \UnexpectedValueException("Fila de bandeja externa carece de correspondencia en catálogo de tipos de trámite.");
            }

            $codigoCorto = $dcTpo;
            $descripcion = $deTpo !== '' ? $deTpo : $dcTpo;
            $inDocu = trim((string) ($row->in_docu_trmte ?? ''));

            $cdgoUsriosTrmte = (int) ($row->cdgo_usrios_trmte ?? 0);
            $indicadorOrigen = ($cdgoUsriosTrmte > 0 && $cdgoUsriosTrmte === $contexto->idUsuario) ? 'V' : 'L';

            return [
                'cdgo_trmte_dcmnto'    => $movimientoId,
                'cdgo_dto_trmte'      => $documentoId,
                'documento_id'        => $documentoId,
                'movimiento_id'       => $movimientoId,
                'orig_doc'            => $codigoCorto,
                'indicador_origen'    => $indicadorOrigen,
                'origen_flag'         => $indicadorOrigen,
                'cdgo_usrios_trmte'   => $cdgoUsriosTrmte,
                'cdgo_tpo_trmte'      => $cdgoTpoTrmte,
                'dc_tpo_trmte'        => $dcTpo,
                'de_tpo_trmte'        => $descripcion,
                'in_docu_trmte'       => $inDocu !== '' ? $inDocu : null,
                'nu_expe_todo'        => (string) ($row->nu_expe_todo ?? (string) $documentoId),
                'tramite_formateado'  => (string) ($row->nu_expe_todo ?? (string) $documentoId),
                'cdgo_estdo_trmte'    => (int) ($row->cdgo_estdo_trmte ?? 0),
                'dc_estdo_trmte'      => $nombreEstado,
                'fe_rcbdo_trmte'      => !empty($row->fe_rcbdo_trmte) ? date('d/m/Y H:i', strtotime((string) $row->fe_rcbdo_trmte)) : '—',
                'fe_ingr_trmte'       => !empty($row->fe_ingr_trmte) ? date('d/m/Y H:i', strtotime((string) $row->fe_ingr_trmte)) : '—',
                'de_obse_trmte'       => (string) ($row->de_obse_trmte ?? '—'),
                'asunto_expediente'   => (string) ($row->asunto_expediente ?? '—'),
                'no_crto'             => (string) ($row->no_crto ?? 'Ciudadano'),
                'nu_docu'             => (string) ($row->nu_docu ?? '—'),
                'de_area'             => (string) ($row->de_area ?? $row->dc_area ?? '—'),
                'dc_area'             => (string) ($row->dc_area ?? '—'),
                'de_crgo'             => (string) ($row->de_crgo ?? '—'),
                'de_crgo_en'          => (string) ($row->de_crgo ?? '—'),
                'nu_tiem_sile'        => (int) ($row->nu_tiem_sile ?? 0),
                'nu_tiem_sile_adcnal' => (int) ($row->nu_tiem_sile_adcnal ?? 0),
                'dias_acumulados'     => !empty($row->fe_rcbdo_trmte) ? max(0, (int) (new \DateTime((string) $row->fe_rcbdo_trmte))->diff(new \DateTime())->days) : 0,
                'has_video'           => false,
            ];
        })->all();

        return [
            'data'       => $data,
            'total'      => $total,
            'last_page'  => max(1, $lastPage),
            'page'       => $pagina,
            'per_page'   => $porPagina,
            'pagination' => [
                'current_page' => $pagina,
                'per_page'     => $porPagina,
                'total'        => $total,
                'last_page'    => max(1, $lastPage),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerHistoricoMovimientos(int $expedienteId): array
    {
        $movimientos = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->leftJoin($this->schema . 'p_crgos as c_sa', 'c_sa.cdgo_crgo', '=', 'r.cdgo_crgo_sa')
            ->leftJoin($this->schema . 'p_crgos as c_en', 'c_en.cdgo_crgo', '=', 'r.cdgo_crgo_en')
            ->leftJoin($this->schema . 'a_areas as a_sa', 'a_sa.cdgo_area', '=', 'c_sa.cdgo_area')
            ->leftJoin($this->schema . 'a_areas as a_en', 'a_en.cdgo_area', '=', 'c_en.cdgo_area')
            ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'e.cdgo_estdo_trmte', '=', 'r.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'p_lgin as l', 'l.cdgo_lgin', '=', 'r.cdgo_lgin')
            ->leftJoin($this->schema . 'p_usrios as u', 'u.cdgo_usrios', '=', 'l.cdgo_usrios')
            ->leftJoin($this->schema . 'p_prfles as prf', 'prf.cdgo_prfle', '=', 'l.cdgo_prfle')
            ->where('r.cdgo_dto_trmte', $expedienteId)
            ->select([
                'r.cdgo_trmte_dcmnto',
                'r.cdgo_dto_trmte',
                'r.fe_rcbdo_trmte',
                'r.cdgo_estdo_trmte',
                'e.dc_estdo_trmte',
                'r.de_obse',
                'r.de_urls_imag as nom_archivo',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'c_sa.de_crgo as cargo_salida',
                'c_en.de_crgo as cargo_entrada',
                'a_sa.de_area as area_salida',
                'a_en.de_area as area_entrada',
                'r.cdgo_lgin',
                'l.de_lgin as login_actor',
                'u.no_crto as usuario_actor',
                'l.cdgo_prfle as perfil_id_actor',
                'prf.de_prfle as perfil_actor',
                'r.in_esta',
            ])
            ->orderByDesc('r.cdgo_trmte_dcmnto')
            ->get();

        if ($movimientos->isEmpty()) {
            return [];
        }

        // Consultas masivas para eliminar N+1 (HIST-001)
        $cargosEntrada = $movimientos->pluck('cdgo_crgo_en')->filter()->unique()->all();
        $movimientoIds = $movimientos->pluck('cdgo_trmte_dcmnto')->filter()->unique()->all();

        // 1. Destinatarios asignados agrupados por cargo
        $destinatariosPorCargo = [];
        if (!empty($cargosEntrada)) {
            $destinatarios = $this->db->table($this->schema . 'r_crgos_lgin as rl')
                ->join($this->schema . 'p_lgin as l', 'rl.cdgo_lgin', '=', 'l.cdgo_lgin')
                ->join($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->whereIn('rl.cdgo_crgo', $cargosEntrada)
                ->where('rl.in_esta', '1')
                ->where('l.in_esta', '1')
                ->select(['u.no_crto as nombre', 'rl.cdgo_lgin', 'rl.cdgo_crgo'])
                ->get();

            foreach ($destinatarios as $d) {
                $destinatariosPorCargo[$d->cdgo_crgo][] = [
                    'nombre'    => (string) $d->nombre,
                    'cdgo_lgin' => (string) $d->cdgo_lgin,
                ];
            }
        }

        // 2. Conteo agregado de descargas auditadas agrupadas por archivo (p_sgmto_dscrgas)
        $descargasCountPorArchivo = [];
        $archivosAdjuntos = $movimientos->pluck('nom_archivo')->filter()->unique()->all();
        if (!empty($archivosAdjuntos)) {
            try {
                $conteos = $this->db->table($this->schema . 'p_sgmto_dscrgas')
                    ->whereIn('de_urls_dscrgas', $archivosAdjuntos)
                    ->groupBy('de_urls_dscrgas')
                    ->selectRaw('de_urls_dscrgas, count(*) as total')
                    ->pluck('total', 'de_urls_dscrgas')
                    ->all();
                foreach ($conteos as $arch => $cnt) {
                    $descargasCountPorArchivo[$arch] = (int) $cnt;
                }
            } catch (\Throwable) {
                $descargasCountPorArchivo = [];
            }
        }

        $resultado = [];
        $totalMovs = count($movimientos);

        foreach ($movimientos as $index => $mov) {
            $m = (array) $mov;
            $siguienteFecha = ($index + 1 < $totalMovs) ? ($movimientos[$index + 1]->fe_rcbdo_trmte ?? null) : null;
            $m['dias'] = $this->calcularTiempoTranscurrido($m['fe_rcbdo_trmte'] ?? null, $siguienteFecha);
            $m['fe_entrada'] = !empty($m['fe_rcbdo_trmte']) ? date('d/m/Y H:i', strtotime((string) $m['fe_rcbdo_trmte'])) : '—';
            $m['observaciones'] = $m['de_obse'] ?? '—';
            $m['area'] = $m['area_entrada'] ?? '—';
            // Resolución canónica del actor histórico (FASE 1)
            $loginId = isset($m['cdgo_lgin']) && $m['cdgo_lgin'] !== '' ? (int) $m['cdgo_lgin'] : null;
            $loginName = !empty($m['login_actor']) ? (string) $m['login_actor'] : null;
            $nombreActor = !empty($m['usuario_actor'])
                ? (string) $m['usuario_actor']
                : ($loginName ?? 'Usuario no identificado');

            $perfilId = isset($m['perfil_id_actor']) ? (int) $m['perfil_id_actor'] : null;
            $perfilNombre = !empty($m['perfil_actor']) ? (string) $m['perfil_actor'] : 'Perfil no registrado';

            $cargoIdSa = isset($m['cdgo_crgo_sa']) ? (int) $m['cdgo_crgo_sa'] : 0;
            $rawCargoSa = isset($m['cargo_salida']) ? trim((string) $m['cargo_salida']) : '';
            $cargoHistorico = ($cargoIdSa > 0 && $rawCargoSa !== '' && $rawCargoSa !== '.')
                ? $rawCargoSa
                : null;

            $rawAreaSa = isset($m['area_salida']) ? trim((string) $m['area_salida']) : '';
            $areaHistorica = ($rawAreaSa !== '' && $rawAreaSa !== '.')
                ? $rawAreaSa
                : 'Área histórica no registrada';

            $m['usuario'] = $nombreActor;
            $m['area_origen'] = $areaHistorica;
            $m['cargo_salida'] = $cargoHistorico;

            $estadoEnum = EstadoTramiteExterno::tryFrom((int) ($m['cdgo_estdo_trmte'] ?? 0));
            $m['estado'] = (string) ($m['dc_estdo_trmte'] ?? $estadoEnum?->label() ?? '—');

            $m['actor'] = [
                'login_id'  => $loginId,
                'login'     => $loginName ?? ($loginId ? (string) $loginId : '—'),
                'nombre'    => $nombreActor,
                'perfil_id' => $perfilId,
                'perfil'    => $perfilNombre,
                'cargo_id'  => $cargoIdSa,
                'cargo'     => $cargoHistorico,
                'area'      => $areaHistorica,
            ];

            $m['destino'] = [
                'cargo_id' => (int) ($m['cdgo_crgo_en'] ?? 0),
                'cargo'    => (string) ($m['cargo_entrada'] ?? '—'),
                'area_id'  => (int) ($m['cdgo_area_en'] ?? 0),
                'area'     => (string) ($m['area_entrada'] ?? '—'),
            ];

            $m['usuarios_destino'] = $destinatariosPorCargo[$m['cdgo_crgo_en'] ?? 0] ?? [];
            $m['usuarios_resumen'] = [
                'total'   => count($destinatariosPorCargo[$m['cdgo_crgo_en'] ?? 0] ?? []),
                'preview' => array_slice($destinatariosPorCargo[$m['cdgo_crgo_en'] ?? 0] ?? [], 0, 3),
                'fuente'  => 'asignacion_actual',
            ];
            $nomArch = !empty($m['nom_archivo']) ? (string) $m['nom_archivo'] : null;
            $totalDescargas = $nomArch ? (int) ($descargasCountPorArchivo[$nomArch] ?? 0) : 0;
            $m['descargas_total'] = $totalDescargas;
            $m['tiene_descargas'] = $totalDescargas > 0;
            $m['descargas_historial'] = [];

            $resultado[] = $m;
        }

        return $resultado;
    }

    /**
     * {@inheritdoc}
     */
    public function registrarAuditoriaDescarga(int $movimientoId, string $login, string $ip): void
    {
        try {
            $archivo = (string) ($this->db->table($this->schema . 'r_trmtes_dcmntos')
                ->where('cdgo_trmte_dcmnto', $movimientoId)
                ->value('de_urls_imag') ?? '');

            $loginRow = $this->db->table($this->schema . 'p_lgin')
                ->where('de_lgin', $login)
                ->value('cdgo_lgin');
            $loginId = is_numeric($loginRow) ? (int) $loginRow : (is_numeric($login) ? (int) $login : 0);

            $this->db->table($this->schema . 'p_sgmto_dscrgas')->insert([
                'cdgo_lgin'        => $loginId,
                'de_urls_dscrgas'  => $archivo,
                'fe_sgmto_dscrgas' => date('Y-m-d H:i:s'),
                'de_ip'            => $ip,
            ]);
        } catch (\Throwable) {
            // Guardrail: no interrumpir descarga si la tabla de auditoría no está disponible
        }
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDetalleExpediente(int $expedienteId, int $companiaId): ?array
    {
        $tramite = $this->db->table($this->schema . 'p_dto_trmtes as p')
            ->leftJoin($this->schema . 'p_usrios as u', 'u.cdgo_usrios', '=', 'p.cdgo_usrios')
            ->leftJoin($this->schema . 'h_usrios_crreo as h', 'h.cdgo_usrios_crreo', '=', 'p.cdgo_usrios_crreo')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 't.cdgo_tpo_trmte', '=', 'p.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'a_docmntos_trmtes as dt', 'dt.cdgo_docmnto_trmte', '=', 'p.cdgo_docmnto_trmte')
            ->where('p.cdgo_dto_trmte', $expedienteId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.nu_foli_trmte',
                'p.de_obse_todo',
                'p.de_obse_trmte',
                'u.no_crto',
                'u.nu_docu',
                'u.de_dire',
                'u.nu_tele',
                'u.de_mail',
                'h.de_mail as de_usrios_mail',
                't.de_tpo_trmte',
                'dt.de_docmnto_trmte',
                'dt.nu_tiem_sile',
            ])
            ->first();

        if (!$tramite) {
            return null;
        }

        $movimientoDto = $this->obtenerMovimientoActual($expedienteId);

        $correo = !empty($tramite->de_usrios_mail)
            ? (string) $tramite->de_usrios_mail
            : (string) ($tramite->de_mail ?? '');

        $tipoDoc = !empty($tramite->de_tpo_trmte)
            ? (string) $tramite->de_tpo_trmte
            : 'Trámite Externo';

        $servicio = !empty($tramite->de_docmnto_trmte)
            ? (string) $tramite->de_docmnto_trmte
            : (!empty($tramite->de_obse_todo) ? (string) $tramite->de_obse_todo : 'Trámite Municipal');

        $solicitante = !empty($tramite->no_crto) ? (string) $tramite->no_crto : 'Ciudadano';

        $flat = [
            'expediente'     => (string) ($tramite->nu_expe_todo ?? (string) $expedienteId),
            'solicitante'    => $solicitante,
            'documento_id'   => (string) ($tramite->nu_docu ?? ''),
            'tipo_documento'  => $tipoDoc,
            'domicilio'      => (string) ($tramite->de_dire ?? ''),
            'telefono'       => (string) ($tramite->nu_tele ?? ''),
            'correo'         => $correo,
            'servicio'       => $servicio,
            'silencio_admin' => ((int) ($tramite->nu_tiem_sile ?? 0)) > 0 ? "{$tramite->nu_tiem_sile} días" : 'No aplica',
            'folios'         => (int) ($tramite->nu_foli_trmte ?? 1),
            'observaciones'  => (string) ($movimientoDto !== null ? ($movimientoDto->observacion ?? $tramite->de_obse_trmte ?? '') : ($tramite->de_obse_trmte ?? '')),
            'requisitos'     => [],
        ];

        return array_merge($flat, [
            'movimiento_dto' => $movimientoDto,
            'data'           => $flat,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerSemillaInterno(int $cdgoTpoTrmte, int $cargoId, int $areaId, int $companiaId): array
    {
        $currentYear = date('Y');
        $anioInfo = $this->db->table($this->schema . 'a_anios')
            ->where('de_anio', $currentYear)
            ->where('cdgo_cmpnia', $companiaId)
            ->first();
        $cdgoAnio = $anioInfo ? (int) $anioInfo->cdgo_anio : null;

        $tpoTrmte = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $cdgoTpoTrmte)
            ->first();

        $inScncal = (int) ($tpoTrmte->in_scncal ?? 0);

        $query = $this->db->table($this->schema . 'a_smlla_intrno')
            ->where('cdgo_tpo_trmte', $cdgoTpoTrmte)
            ->where('cdgo_anio', $cdgoAnio);

        if ($inScncal === 1) {
            $query->where('cdgo_crgo', $cargoId);
        } else {
            $query->where('cdgo_area', $areaId);
        }

        $maxSemilla = $query->max('nu_smlla_intrno');
        $nuSemilla = $maxSemilla ? ((int) $maxSemilla) + 1 : 1;

        return [
            'nu_smlla'        => $nuSemilla,
            'de_anio'         => $currentYear,
            'cdgo_anio'       => $cdgoAnio,
            'nu_smlla_intrno' => "{$nuSemilla}-{$currentYear}",
            'in_scncal'       => $inScncal,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerSolicitudesVideo(int $expedienteId, int $companiaId): array
    {
        try {
            $solicitudes = $this->db->table($this->schema . 'p_solicitudes_video as sv')
                ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'sv.cdgo_dto_trmte')
                ->where('sv.cdgo_dto_trmte', $expedienteId)
                ->where('p.cdgo_cmpnia', $companiaId)
                ->select([
                    'sv.cdgo_solicitud_video',
                    'sv.de_nombre_usuario',
                    'sv.de_cargo',
                    'sv.de_institucion_uo',
                    'sv.tipo_documento',
                    'sv.nu_documento',
                    'sv.de_correo_solicitante',
                    'sv.fe_solicitud',
                    'sv.fe_inicio_grabacion',
                    'sv.fe_fin_grabacion',
                    'sv.fe_entrega',
                ])
                ->get();

            return $solicitudes->toArray();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error al consultar solicitudes de video para expediente {$expedienteId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDatosIndex(ContextoUsuarioAutenticadoDto $contexto): array
    {
        // Jerarquía de Áreas
        $allAreas = $this->db->table($this->schema . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $contexto->idCompania)
            ->orderBy('de_area', 'asc')
            ->get();

        $areasList = [];
        $gerencias = $allAreas->whereNull('cdgo_area_prim');
        foreach ($gerencias as $ger) {
            $areasList[] = [
                'id' => $ger->cdgo_area,
                'nombre' => $ger->de_area,
                'level' => 0,
                'cdgo_area_prim' => null,
            ];
            $subs = $allAreas->where('cdgo_area_prim', $ger->cdgo_area);
            foreach ($subs as $sub) {
                $areasList[] = [
                    'id' => $sub->cdgo_area,
                    'nombre' => '↪ ' . $sub->de_area,
                    'level' => 1,
                    'cdgo_area_prim' => $ger->cdgo_area,
                ];
                $subSubs = $allAreas->where('cdgo_area_prim', $sub->cdgo_area);
                foreach ($subSubs as $ss) {
                    $areasList[] = [
                        'id' => $ss->cdgo_area,
                        'nombre' => '↪↪ ' . $ss->de_area,
                        'level' => 2,
                        'cdgo_area_prim' => $sub->cdgo_area,
                    ];
                }
            }
        }

        // Cargos del área
        $cargosList = $this->db->table($this->schema . 'p_crgos as c')
            ->join($this->schema . 'r_crgos_lgin as rl', 'c.cdgo_crgo', '=', 'rl.cdgo_crgo')
            ->join($this->schema . 'p_lgin as l', 'rl.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->join($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select('c.cdgo_crgo', 'c.de_crgo', 'u.no_crto')
            ->where('c.cdgo_area', $contexto->idArea)
            ->where('c.cdgo_crgo', '<>', $contexto->idCargo)
            ->where('c.in_esta', '1')
            ->where('u.cdgo_usrios', '<>', 8)
            ->orderBy('u.no_crto', 'asc')
            ->get();

        // Tipos de documentos permitidos en el área
        $tiposDocumento = $this->db->table($this->schema . 'r_tpo_trmte_areas as r')
            ->join($this->schema . 'a_tpo_trmtes as t', 'r.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->select('t.cdgo_tpo_trmte', 't.de_tpo_trmte')
            ->where('r.in_esta', '1')
            ->where('r.cdgo_area', $contexto->idArea)
            ->orderBy('t.de_tpo_trmte', 'asc')
            ->get();

        // Contadores iniciales
        $countEntrada = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('r.cdgo_crgo_en', $contexto->idCargo)
            ->where('r.cdgo_crgo_sa', '<>', $contexto->idCargo)
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', [5, 12, 29, 3, 26, 27, 28])
            ->where('p.cdgo_cmpnia', $contexto->idCompania)
            ->count();

        $countRecibidos = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('r.cdgo_crgo_en', $contexto->idCargo)
            ->where('r.cdgo_crgo_sa', $contexto->idCargo)
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', [3, 26, 27])
            ->where('p.cdgo_cmpnia', $contexto->idCompania)
            ->count();

        return [
            'areasList'      => $areasList,
            'cargosList'     => $cargosList,
            'tiposDocumento' => $tiposDocumento,
            'countEntrada'   => $countEntrada,
            'countRecibidos' => $countRecibidos,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientoPorMovimientoId(int $movimientoId, int $companiaId): ?ExpedienteMovimientoDto
    {
        $fila = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->where('r.cdgo_trmte_dcmnto', $movimientoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select([
                'r.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'r.cdgo_trmte_dcmnto',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.fe_rcbdo_trmte',
                'r.de_obse',
            ])
            ->first();

        if (!$fila) {
            return null;
        }

        $areaSa = (int) ($this->db->table($this->schema . 'p_crgos')->where('cdgo_crgo', $fila->cdgo_crgo_sa)->value('cdgo_area') ?? 0);
        $areaEn = (int) ($this->db->table($this->schema . 'p_crgos')->where('cdgo_crgo', $fila->cdgo_crgo_en)->value('cdgo_area') ?? 0);

        return new ExpedienteMovimientoDto(
            expedienteId: (int) $fila->cdgo_dto_trmte,
            numeroExpediente: (string) ($fila->nu_expe_todo ?? ''),
            movimientoId: (int) $fila->cdgo_trmte_dcmnto,
            companiaId: (int) ($fila->cdgo_cmpnia ?? 0),
            cargoSalidaId: (int) $fila->cdgo_crgo_sa,
            cargoEntradaId: (int) $fila->cdgo_crgo_en,
            areaSalidaId: $areaSa,
            areaEntradaId: $areaEn,
            estadoId: (int) $fila->cdgo_estdo_trmte,
            inEsta: (string) $fila->in_esta,
            fechaMovimiento: $fila->fe_rcbdo_trmte ? (string) $fila->fe_rcbdo_trmte : null,
            observacion: $fila->de_obse ? (string) $fila->de_obse : null
        );
    }

    /**
     * Aplica la cláusula WHERE correspondiente a cada una de las 10 bandejas canónicas.
     */
    public function aplicarFiltroBandeja(Builder $query, string $bandeja, ContextoUsuarioAutenticadoDto $contexto): void
    {
        switch ($bandeja) {
            case BandejaTramiteExterno::ENTRADA->value:
                $query->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', '!=', $contexto->idCargo)
                    ->where('r.in_esta', '1')
                    ->whereNotIn('r.cdgo_estdo_trmte', [5, 12, 29, 3, 26, 27, 28]);
                break;

            case BandejaTramiteExterno::RECIBIDOS->value:
                $query->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->where('r.in_esta', '1')
                    ->whereNotIn('r.cdgo_estdo_trmte', [3, 26, 27]);
                break;

            case BandejaTramiteExterno::SALIDA->value:
                $query->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->where('r.cdgo_crgo_en', '!=', $contexto->idCargo)
                    ->where('r.in_esta', '1');
                break;

            case 'finalizados_notificados':
            case BandejaTramiteExterno::FINALIZADOS_NOTIFICADOS->value:
            case BandejaTramiteExterno::FINALIZADOS->value:
                $query->where('r.in_esta', '1')
                    ->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->whereIn('r.cdgo_estdo_trmte', [EstadoTramiteExterno::FINALIZADO->value, EstadoTramiteExterno::NOTIFICADO->value]);
                break;

            case BandejaTramiteExterno::ARCHIVO->value:
                $query->where('r.in_esta', '1')
                    ->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->where('r.cdgo_estdo_trmte', EstadoTramiteExterno::ARCHIVADO->value);
                break;

            case BandejaTramiteExterno::NOTIFICADOS->value:
                $query->where('r.in_esta', '1')
                    ->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_estdo_trmte', EstadoTramiteExterno::NOTIFICADO->value);
                break;

            case BandejaTramiteExterno::PRESTADOS->value:
                $query->where('r.in_esta', '1')
                    ->where('r.cdgo_estdo_trmte', EstadoTramiteExterno::PRESTADO->value);
                break;

            case BandejaTramiteExterno::INVENTARIAR->value:
                $query->where('r.in_esta', '1')
                    ->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->whereIn('r.cdgo_estdo_trmte', [
                        EstadoTramiteExterno::ARCHIVADO->value,
                        EstadoTramiteExterno::FINALIZADO->value,
                    ]);
                break;

            case BandejaTramiteExterno::SIN_FINALIZAR->value:
                $query->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->where('r.cdgo_crgo_en', '!=', $contexto->idCargo)
                    ->where('r.in_esta', '1')
                    ->whereNotIn('r.cdgo_estdo_trmte', [EstadoTramiteExterno::FINALIZADO->value, EstadoTramiteExterno::NOTIFICADO->value]);
                break;

            case BandejaTramiteExterno::TODOS->value:
                $query->where('r.in_esta', '1')
                    ->where('c.cdgo_area', $contexto->idArea);
                break;

            default:
                throw new \InvalidArgumentException("Bandeja desconocida o no permitida: {$bandeja}");
        }
    }

    /**
     * Aplica filtros adicionales de búsqueda segura sin concatenación SQL directa.
     *
     * @param Builder $query
     * @param array<string, mixed> $filtros
     */
    public function aplicarCriteriosBusqueda(Builder $query, array $filtros): void
    {
        if (!empty($filtros['descripcion'])) {
            $criterio = (string) ($filtros['criterio'] ?? 'expe_todo');
            $termino = '%' . trim((string) $filtros['descripcion']) . '%';

            switch ($criterio) {
                case '1':
                case 'expe_todo':
                    $query->where('p.nu_expe_todo', 'like', $termino);
                    break;

                case 'expe_varios':
                    $raw = (string) $filtros['descripcion'];
                    $tokens = preg_split('/[,\s]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    if (!empty($tokens)) {
                        $query->where(function ($q) use ($tokens) {
                            foreach ($tokens as $token) {
                                $q->orWhere('p.nu_expe_todo', 'like', '%' . trim($token) . '%');
                            }
                        });
                    }
                    break;

                case '2':
                case 'solicitante':
                    $query->where('u.no_crto', 'like', $termino);
                    break;

                case '3':
                case 'observaciones':
                    $query->where(function ($q) use ($termino) {
                        $q->where('r.de_obse', 'like', $termino)
                            ->orWhere('p.de_obse_trmte', 'like', $termino);
                    });
                    break;

                default:
                    throw new \InvalidArgumentException("Criterio de búsqueda desconocido: {$criterio}");
            }
        }

        if (!empty($filtros['expediente'])) {
            $query->where('p.nu_expe_todo', 'like', '%' . trim((string) $filtros['expediente']) . '%');
        }

        if (!empty($filtros['persona'])) {
            $query->where('u.no_crto', 'like', '%' . trim((string) $filtros['persona']) . '%');
        }

        if (!empty($filtros['observacion'])) {
            $obsTermino = '%' . trim((string) $filtros['observacion']) . '%';
            $query->where(function ($q) use ($obsTermino) {
                $q->where('r.de_obse', 'like', $obsTermino)
                    ->orWhere('p.de_obse_trmte', 'like', $obsTermino);
            });
        }

        if (!empty($filtros['dni_ruc'])) {
            $query->where('u.nu_docu', trim((string) $filtros['dni_ruc']));
        }

        if (isset($filtros['folios']) && is_numeric($filtros['folios'])) {
            $query->where('p.nu_foli_trmte', (int) $filtros['folios']);
        }

        if (!empty($filtros['area_id'])) {
            $query->where('c.cdgo_area', (int) $filtros['area_id']);
        }

        if (!empty($filtros['condicion'])) {
            $query->where('r.in_esta', (string) $filtros['condicion']);
        }

        if (!empty($filtros['estado'])) {
            $query->where('r.cdgo_estdo_trmte', (int) $filtros['estado']);
        }

        if (!empty($filtros['desde'])) {
            $query->whereDate('r.fe_rcbdo_trmte', '>=', (string) $filtros['desde']);
        }

        if (!empty($filtros['hasta'])) {
            $query->whereDate('r.fe_rcbdo_trmte', '<=', (string) $filtros['hasta']);
        }
    }

    /**
     * Calcula el tiempo transcurrido en formato amigable para histórico.
     */
    private function calcularTiempoTranscurrido(?string $fechaInicio, ?string $fechaFin): string
    {
        if (empty($fechaInicio)) {
            return '—';
        }

        $inicio = \Carbon\Carbon::parse($fechaInicio);
        $fin = $fechaFin ? \Carbon\Carbon::parse($fechaFin) : \Carbon\Carbon::now();
        $dias = (int) $inicio->diffInDays($fin);
        $horas = (int) $inicio->diffInHours($fin) % 24;

        if ($dias > 0) {
            return "{$dias}d {$horas}h";
        }

        return "{$horas}h";
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerConteosBandejas(ContextoUsuarioAutenticadoDto $contexto): array
    {
        $bandejas = [
            BandejaTramiteExterno::TODOS->value,
            BandejaTramiteExterno::ENTRADA->value,
            BandejaTramiteExterno::RECIBIDOS->value,
            BandejaTramiteExterno::SALIDA->value,
            BandejaTramiteExterno::FINALIZADOS->value,
            BandejaTramiteExterno::ARCHIVO->value,
            BandejaTramiteExterno::SIN_FINALIZAR->value,
        ];

        $conteos = [];
        foreach ($bandejas as $bandeja) {
            $query = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
                ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
                ->leftJoin($this->schema . 'p_crgos as c', 'c.cdgo_crgo', '=', 'r.cdgo_crgo_en')
                ->where('p.cdgo_cmpnia', $contexto->idCompania);

            $this->aplicarFiltroBandeja($query, $bandeja, $contexto);
            $conteos[$bandeja] = (int) $query->count();
        }

        $conteos['finalizados_notificados'] = $conteos[BandejaTramiteExterno::FINALIZADOS->value];

        return $conteos;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerParticipantesU(
        int $movimientoId,
        ContextoUsuarioAutenticadoDto $contexto,
        int $page = 1,
        int $perPage = 25,
        ?string $search = null
    ): array {
        $mov = $this->obtenerMovimientoPorMovimientoId($movimientoId, $contexto->idCompania);
        if (!$mov) {
            return [
                'status'  => 'error',
                'message' => 'Movimiento no encontrado o sin autorización.',
                'code'    => 404,
            ];
        }

        // Obtener actor ejecutor histórico en campo separado (FASE 1)
        $origenEjecutor = null;
        try {
            $ejecutorFila = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
                ->leftJoin($this->schema . 'p_lgin as l', 'l.cdgo_lgin', '=', 'r.cdgo_lgin')
                ->leftJoin($this->schema . 'p_usrios as u', 'u.cdgo_usrios', '=', 'l.cdgo_usrios')
                ->leftJoin($this->schema . 'p_prfles as prf', 'prf.cdgo_prfle', '=', 'l.cdgo_prfle')
                ->leftJoin($this->schema . 'p_crgos as c_sa', 'c_sa.cdgo_crgo', '=', 'r.cdgo_crgo_sa')
                ->leftJoin($this->schema . 'a_areas as a_sa', 'a_sa.cdgo_area', '=', 'c_sa.cdgo_area')
                ->where('r.cdgo_trmte_dcmnto', $movimientoId)
                ->select([
                    'r.cdgo_lgin',
                    'l.de_lgin as login_actor',
                    'u.no_crto as usuario_actor',
                    'l.cdgo_prfle as perfil_id_actor',
                    'prf.de_prfle as perfil_actor',
                    'c_sa.cdgo_crgo as cargo_id_actor',
                    'c_sa.de_crgo as cargo_salida',
                    'a_sa.de_area as area_salida',
                ])
                ->first();

            if ($ejecutorFila) {
                $rawCargoSa = trim((string) ($ejecutorFila->cargo_salida ?? ''));
                $cargoHistorico = ($rawCargoSa !== '' && $rawCargoSa !== '.') ? $rawCargoSa : '—';
                $rawAreaSa = trim((string) ($ejecutorFila->area_salida ?? ''));
                $areaHistorica = ($rawAreaSa !== '' && $rawAreaSa !== '.') ? $rawAreaSa : '—';
                $loginId = is_numeric($ejecutorFila->cdgo_lgin) ? (int) $ejecutorFila->cdgo_lgin : null;
                $loginName = !empty($ejecutorFila->login_actor) ? (string) $ejecutorFila->login_actor : ($loginId ? (string) $loginId : '—');
                $nombreActor = !empty($ejecutorFila->usuario_actor) ? (string) $ejecutorFila->usuario_actor : $loginName;

                $origenEjecutor = [
                    'login_id'  => $loginId,
                    'login'     => $loginName,
                    'nombre'    => $nombreActor,
                    'perfil_id' => is_numeric($ejecutorFila->perfil_id_actor) ? (int) $ejecutorFila->perfil_id_actor : null,
                    'perfil'    => !empty($ejecutorFila->perfil_actor) ? (string) $ejecutorFila->perfil_actor : 'Perfil no registrado',
                    'cargo_id'  => is_numeric($ejecutorFila->cargo_id_actor) ? (int) $ejecutorFila->cargo_id_actor : 0,
                    'cargo'     => $cargoHistorico,
                    'area'      => $areaHistorica,
                ];
            }
        } catch (\Throwable) {
            $origenEjecutor = null;
        }

        $cargoDestinoId = $mov->cargoEntradaId;

        $query = $this->db->table($this->schema . 'r_crgos_lgin as rl')
            ->join($this->schema . 'p_lgin as l', 'rl.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->join($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->join($this->schema . 'p_crgos as c', 'rl.cdgo_crgo', '=', 'c.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($this->schema . 'p_prfles as prf', 'l.cdgo_prfle', '=', 'prf.cdgo_prfle')
            ->where('rl.cdgo_crgo', $cargoDestinoId)
            ->where('rl.in_esta', '1')
            ->where('l.in_esta', '1');

        if (!empty($search)) {
            $term = '%' . strtoupper(trim($search)) . '%';
            $searchRaw = trim($search);
            $query->where(function (Builder $q) use ($term, $searchRaw) {
                $q->whereRaw('UPPER(u.no_crto) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(l.de_lgin) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(c.de_crgo) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(a.de_area) LIKE ?', [$term])
                    ->orWhereRaw('CAST(l.cdgo_lgin AS TEXT) LIKE ?', [$term]);
                if (is_numeric($searchRaw)) {
                    $q->orWhere('l.cdgo_lgin', (int) $searchRaw);
                }
            });
        }

        $total = (int) (clone $query)->distinct('l.cdgo_lgin')->count('l.cdgo_lgin');

        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 25;
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $filas = $query->select([
            'l.cdgo_lgin',
            'l.de_lgin',
            'u.no_crto',
            'c.cdgo_crgo',
            'c.de_crgo',
            'a.de_area',
            'prf.de_prfle',
        ])
            ->distinct()
            ->orderBy('u.no_crto', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $participantes = [];
        foreach ($filas as $fila) {
            $cargoDesc = trim((string) ($fila->de_crgo ?? ''));
            $cargoNorm = ($cargoDesc !== '' && $cargoDesc !== '.') ? $cargoDesc : '—';
            $loginNom = !empty($fila->de_lgin) ? (string) $fila->de_lgin : (string) $fila->cdgo_lgin;
            $loginId = is_numeric($fila->cdgo_lgin) ? (int) $fila->cdgo_lgin : null;

            $dto = new ParticipanteUDto(
                login: $loginNom,
                nombre: (string) ($fila->no_crto ?? $loginNom),
                cargo: $cargoNorm,
                area: (string) ($fila->de_area ?? '—'),
                estadoAsignacion: 'Activo',
                perfil: !empty($fila->de_prfle) ? (string) $fila->de_prfle : null,
                loginId: $loginId,
            );
            $participantes[] = $dto->toArray();
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'status'          => 'success',
            'data'            => $participantes,
            'origen_ejecutor' => $origenEjecutor,
            'pagination'      => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
            'meta'            => [
                'current_page'  => $page,
                'per_page'      => $perPage,
                'total'         => $total,
                'last_page'     => $lastPage,
                'fuente'        => 'asignacion_actual',
                'consultado_en' => Carbon::now()->toIso8601String(),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerAuditoriaDescargasPaginada(
        int $movimientoId,
        int $companiaId,
        int $page = 1,
        int $perPage = 10
    ): array {
        $mov = $this->obtenerMovimientoPorMovimientoId($movimientoId, $companiaId);
        if (!$mov) {
            return [
                'status'  => 'error',
                'message' => 'Movimiento no encontrado o sin autorización.',
                'code'    => 404,
            ];
        }

        // Obtener el nombre del archivo adjunto para el movimiento
        $archivoFila = $this->db->table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->select('de_urls_imag')
            ->first();

        $nomArchivo = !empty($archivoFila->de_urls_imag) ? trim((string) $archivoFila->de_urls_imag) : null;
        if (empty($nomArchivo)) {
            return [
                'status'     => 'success',
                'data'       => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page'     => $perPage,
                    'total'        => 0,
                    'last_page'    => 1,
                ],
                'archivo'    => null,
            ];
        }

        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $query = $this->db->table($this->schema . 'p_sgmto_dscrgas as sd')
            ->leftJoin($this->schema . 'p_lgin as l', 'l.cdgo_lgin', '=', 'sd.cdgo_lgin')
            ->leftJoin($this->schema . 'p_usrios as u', 'u.cdgo_usrios', '=', 'l.cdgo_usrios')
            ->where('sd.de_urls_dscrgas', $nomArchivo);

        $total = (int) (clone $query)->count();

        $filas = $query->select([
            'sd.cdgo_sgmto_dscrgas',
            'sd.fe_sgmto_dscrgas',
            'sd.de_ip',
            'sd.cdgo_lgin as id_login',
            'l.de_lgin as login_nom',
            'u.no_crto as nombre_usuario',
            'sd.de_urls_dscrgas',
        ])
            ->orderBy('sd.fe_sgmto_dscrgas', 'desc')
            ->orderBy('sd.cdgo_sgmto_dscrgas', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = [];
        foreach ($filas as $f) {
            $fechaVal = $f->fe_sgmto_dscrgas ? date('d/m/Y H:i', strtotime((string) $f->fe_sgmto_dscrgas)) : '—';
            $ipVal = (string) ($f->de_ip ?? '—');
            $nombreVal = !empty($f->nombre_usuario) ? (string) $f->nombre_usuario : null;
            $loginVal = !empty($f->login_nom) ? (string) $f->login_nom : null;
            $loginId = !empty($f->id_login) ? (int) $f->id_login : null;

            $items[] = [
                'nombre_usuario' => $nombreVal ?? 'Usuario no disponible',
                'login'          => $loginVal ?? ($loginId ? (string) $loginId : '—'),
                'login_id'       => $loginId,
                'fecha'          => $fechaVal,
                'ip'             => $ipVal,
                'nom_archivo'    => (string) $f->de_urls_dscrgas,
                'fe_dscrga'      => $fechaVal,
                'ip_dscrga'      => $ipVal,
                'no_crto'        => $nombreVal ?? ($loginVal ?? 'Usuario no disponible'),
                'cdgo_lgin'      => $loginVal ?? ($loginId ? (string) $loginId : ''),
            ];
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'status'     => 'success',
            'data'       => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
            'archivo'    => $nomArchivo,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerAdjuntoAutorizadoPorMovimiento(int $movimientoId, int $companiaId): ?array
    {
        if ($movimientoId <= 0 || $companiaId <= 0) {
            return null;
        }

        $movFila = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')
            ->where('r.cdgo_trmte_dcmnto', $movimientoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select('r.de_urls_imag')
            ->first();

        return $movFila ? (array) $movFila : null;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDatosHojaRuta(int $expedienteId, int $companiaId): ?array
    {
        if ($expedienteId <= 0 || $companiaId <= 0) {
            return null;
        }

        $expediente = $this->db->table($this->schema . 'p_dto_trmtes as p')
            ->leftJoin($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select('p.nu_expe_todo', 'p.fe_ingr_trmte', 'u.no_crto')
            ->where('p.cdgo_dto_trmte', $expedienteId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->first();

        if (!$expediente) {
            return null;
        }

        $historial = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->leftJoin($this->schema . 'p_crgos as p_crgos_en', 'r.cdgo_crgo_en', '=', 'p_crgos_en.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a_areas_en', 'p_crgos_en.cdgo_area', '=', 'a_areas_en.cdgo_area')
            ->leftJoin($this->schema . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select(
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte as fe_entrada',
                'a_areas_en.de_area as area',
                'a_estdos_trmtes.dc_estdo_trmte as estado',
                'u.no_crto as usuario'
            )
            ->where('r.cdgo_dto_trmte', $expedienteId)
            ->orderBy('r.cdgo_trmte_dcmnto', 'asc')
            ->get();

        return [
            'expediente' => $expediente,
            'historial' => $historial,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function resolverDatosContexto(?int $cdgoLgin, int $cargoId, int $areaId): array
    {
        $resCargoId = $cargoId;
        if ($resCargoId <= 0 && $cdgoLgin !== null && $cdgoLgin > 0) {
            $resCargoId = (int) ($this->db->table($this->schema . 'r_crgos_lgin')
                ->where('cdgo_lgin', $cdgoLgin)
                ->where('in_esta', '1')
                ->value('cdgo_crgo') ?? 0);
        }

        $resAreaId = $areaId;
        if ($resAreaId <= 0 && $resCargoId > 0) {
            $resAreaId = (int) ($this->db->table($this->schema . 'p_crgos')
                ->where('cdgo_crgo', $resCargoId)
                ->value('cdgo_area') ?? 0);
        }

        $compFromArea = null;
        if ($resAreaId > 0) {
            $compFromArea = $this->db->table($this->schema . 'a_areas')
                ->where('cdgo_area', $resAreaId)
                ->value('cdgo_cmpnia');
        }

        return [
            'cargo_id' => $resCargoId,
            'area_id' => $resAreaId,
            'compania_id' => $compFromArea !== null ? (int) $compFromArea : 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function desactivarMovimientoExternoEsperado(int $movimientoId): int
    {
        return $this->db->table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->where('in_esta', '1')
            ->update(['in_esta' => '0']);
    }

    /**
     * {@inheritdoc}
     */
    public function crearMovimientoExterno(array $datos): int
    {
        return (int) $this->db->table($this->schema . 'r_trmtes_dcmntos')->insertGetId($datos, 'cdgo_trmte_dcmnto');
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargoPrincipalDeArea(int $areaId): ?object
    {
        return $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_area', $areaId)
            ->where('in_esta', '1')
            ->orderBy('cdgo_crgo', 'asc')
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function validarCargoEnArea(int $cargoId, int $areaId): bool
    {
        return $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->where('cdgo_area', $areaId)
            ->where('in_esta', '1')
            ->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerContactoNotificacionExpediente(int $expedienteId, int $companiaId): ?array
    {
        if ($expedienteId <= 0 || $companiaId <= 0) {
            return null;
        }

        $contacto = $this->db->table($this->schema . 'p_dto_trmtes as p')
            ->leftJoin($this->schema . 'h_usrios_crreo as h', 'h.cdgo_usrios_crreo', '=', 'p.cdgo_usrios_crreo')
            ->leftJoin($this->schema . 'p_usrios as u', 'u.cdgo_usrios', '=', 'p.cdgo_usrios')
            ->where('p.cdgo_dto_trmte', $expedienteId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select([
                'p.nu_expe_todo',
                'u.no_crto',
                'u.nu_tele',
                'u.de_mail as de_mail_titular',
                'h.de_mail as de_mail_correo',
                'h.de_mail as correo_h',
                'u.de_mail as correo_u',
            ])
            ->first();

        if (!$contacto) {
            return null;
        }

        return (array) $contacto;
    }

    /**
     * {@inheritdoc}
     */
    public function registrarSeguimientoExterno(int $cdgoLgin, string $deSgtoLog, string $ip): void
    {
        $this->db->table($this->schema . 'p_sgmto_log')->insert([
            'cdgo_lgin' => $cdgoLgin,
            'de_sgto_log' => $deSgtoLog,
            'fe_sgto_log' => now(),
            'de_ip' => $ip,
        ]);
    }

    // =========================================================================
    // IMPLEMENTACIÓN DE MÉTODOS EXTENDIDOS PARA DESACOPLAR CONTROLADORES
    // =========================================================================

    // --- 1. Adjuntar Trámite ---

    public function obtenerMovimientosParaAdjuntar(array $filtros, int $pagina, int $porPagina, int $companiaId): array
    {
        $s = $this->schema;
        $query = $this->db->table($s . 'p_dto_trmtes as p_dto_trmtes')
            ->join($s . 'p_lgin as p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')
            ->join($s . 'r_trmtes_dcmntos as r_trmtes_dcmntos', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->join($s . 'p_crgos as p_crgos', 'r_trmtes_dcmntos.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'p_usrios as p_usrios_1', 'p_lgin.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->select(
                'p_dto_trmtes.cdgo_dto_trmte',
                'p_dto_trmtes.nu_expe_todo',
                'p_usrios.no_crto',
                'r_trmtes_dcmntos.fe_rcbdo_trmte',
                'a_areas.dc_area',
                'a_areas.de_area',
                'p_dto_trmtes.de_obse_trmte',
                'r_trmtes_dcmntos.cdgo_trmte_dcmnto',
                'r_trmtes_dcmntos.de_urls_imag',
                'a_estdos_trmtes.de_estdo_trmte'
            )
            ->where('r_trmtes_dcmntos.cdgo_crgo_sa', '0')
            ->whereIn('r_trmtes_dcmntos.cdgo_estdo_trmte', ['25', '5', '1'])
            ->where('r_trmtes_dcmntos.in_esta', '1');

        if ($companiaId > 0) {
            $query->where('p_dto_trmtes.cdgo_cmpnia', $companiaId);
        }

        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $d1 = (string) ($filtros['d1'] ?? 'p_dto_trmtes.nu_expe_todo');
        $allowed = [
            'p_dto_trmtes.nu_expe_todo',
            'a_areas.de_area',
            'p_usrios.no_crto',
            'a_tpo_trmtes.dc_tpo_trmte',
            'p_dto_trmtes.de_obse_todo',
            'p_usrios_1.no_crto',
        ];

        if ($criterio !== '' && in_array($d1, $allowed, true)) {
            $query->where($d1, 'ILIKE', '%' . $criterio . '%');
        }

        $fecha = trim((string) ($filtros['fecha'] ?? ''));
        if ($fecha !== '') {
            try {
                $f = \Carbon\Carbon::createFromFormat('d/m/Y', $fecha)->format('Y-m-d');
                $query->whereBetween('p_dto_trmtes.fe_ingr_trmte', [$f . ' 00:00:00', $f . ' 23:59:59']);
            } catch (\Throwable) {}
        }

        $total = (clone $query)->count();
        $numPags = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $numPags);
        $offset = ($pagina - 1) * $porPagina;

        $rows = $query
            ->orderByDesc('p_dto_trmtes.cdgo_dto_trmte')
            ->offset($offset)
            ->limit($porPagina)
            ->get();

        return [
            'rows' => $rows,
            'total' => $total,
            'pagina' => $pagina,
            'numPags' => $numPags,
        ];
    }

    public function obtenerDetalleMovimientoAdjuntar(int $movimientoId): ?array
    {
        $s = $this->schema;

        $movimientoBase = $this->db->table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->first();

        if (! $movimientoBase) {
            return null;
        }

        $cdgoDto = $movimientoBase->cdgo_dto_trmte;

        $expediente = $this->db->table($s . 'p_dto_trmtes as p_dto_trmtes')
            ->leftJoin($s . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->select(
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_dto_trmtes.de_obse_todo',
                'p_dto_trmtes.de_obse_trmte',
                'p_usrios.no_crto',
                'p_usrios.nu_docu',
                'a_tpo_trmtes.de_tpo_trmte'
            )
            ->where('p_dto_trmtes.cdgo_dto_trmte', $cdgoDto)
            ->first();

        $historial = $this->db->table($s . 'r_trmtes_dcmntos as r')
            ->leftJoin($s . 'p_crgos as p_crgos_en', 'r.cdgo_crgo_en', '=', 'p_crgos_en.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_areas_en', 'p_crgos_en.cdgo_area', '=', 'a_areas_en.cdgo_area')
            ->leftJoin($s . 'p_crgos as p_crgos_sa', 'r.cdgo_crgo_sa', '=', 'p_crgos_sa.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_areas_sa', 'p_crgos_sa.cdgo_area', '=', 'a_areas_sa.cdgo_area')
            ->leftJoin($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->select(
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte as fe_trmte_dcmnto',
                'a_areas_sa.de_area as area_origen',
                'a_areas_en.de_area as area_destino',
                'a_estdos_trmtes.dc_estdo_trmte as estado',
                'r.de_obse as observacion',
                'r.de_urls_imag'
            )
            ->where('r.cdgo_dto_trmte', $cdgoDto)
            ->orderByDesc('r.cdgo_trmte_dcmnto')
            ->get();

        return [
            'expediente' => $expediente,
            'historial' => $historial,
        ];
    }

    public function actualizarAdjuntoMovimiento(int $movimientoId, string $filename, int $loginId, string $ip): void
    {
        $s = $this->schema;

        $this->db->table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->update(['de_urls_imag' => $filename]);

        $idTram = (int) $this->db->table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movimientoId)
            ->value('cdgo_dto_trmte');

        try {
            $this->db->table($s . 'p_sgmto_log')->insert([
                'cdgo_lgin' => $loginId,
                'de_sgto_log' => "Se asigno el expediente {$idTram}",
                'fe_sgto_log' => now(),
                'de_ip' => $ip,
            ]);
        } catch (\Throwable) {}
    }

    // --- 2. Archivo Expediente ---

    public function verificarPermisoArchivo(int $perfilId, int $cargoId): bool
    {
        return $this->db->table($this->schema . 'r_prmsos')
            ->where('cdgo_prfle', $perfilId)
            ->where('cdgo_rle', 11)
            ->where(function ($query) use ($cargoId) {
                $query->whereNull('cdgo_crgo')->orWhere('cdgo_crgo', $cargoId);
            })
            ->exists();
    }

    private function baseQueryArchivo(): Builder
    {
        $s = $this->schema;
        return $this->db->table($s . 'r_trmtes_dcmntos as r')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->join($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->leftJoin($s . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin($s . 'p_lgin as l', 'p.co_usua_ingr', '=', 'l.cdgo_lgin')
            ->leftJoin($s . 'p_usrios as usuario_lgin', 'l.cdgo_usrios', '=', 'usuario_lgin.cdgo_usrios')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.nu_expe_trmte',
                'p.nu_foli_trmte',
                'p.fe_ingr_trmte',
                'p.de_obse_trmte',
                'p.de_obse_todo',
                'u.no_crto',
                'u.nu_docu',
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'r.cdgo_estdo_trmte',
                'r.de_obse as movimiento_observacion',
                'r.de_urls_imag',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                'a_en.dc_area',
                'a_en.de_area',
                'usuario_lgin.no_crto as usuario_plataforma',
            ])
            ->where('r.in_esta', '1');
    }

    private function aplicarFiltrosArchivo(Builder $query, array $filtros, ?int $cargoId, int $companiaId): void
    {
        $bandeja = (string) ($filtros['bandeja'] ?? 'finalizados');
        match ($bandeja) {
            'finalizados' => $query->where('r.cdgo_crgo_en', $cargoId)->where('r.cdgo_crgo_sa', $cargoId)->whereIn('r.cdgo_estdo_trmte', ['3', '26']),
            'archivados' => $query->where('r.cdgo_crgo_en', $cargoId)->where('r.cdgo_crgo_sa', $cargoId)->where('r.cdgo_estdo_trmte', '27'),
            'prestados' => $query->where('r.cdgo_estdo_trmte', '28'),
            default => null,
        };

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $campo = (string) ($filtros['campo'] ?? 'expediente');
        if ($criterio !== '') {
            if ($campo === 'expediente_varios') {
                $numbers = collect(explode(',', $criterio))->map(fn ($part) => trim($part))->filter()->all();
                if ($numbers) {
                    $query->whereIn('p.nu_expe_trmte', $numbers);
                }
            } else {
                $column = match ($campo) {
                    'nombre' => 'u.no_crto',
                    'estado' => 'e.dc_estdo_trmte',
                    'observaciones' => 'p.de_obse_trmte',
                    default => 'p.nu_expe_todo',
                };
                $query->where($column, 'ILIKE', '%' . mb_strtoupper($criterio, 'UTF-8') . '%');
            }
        }

        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;
        if ($desde) {
            $query->where('r.fe_rcbdo_trmte', '>=', $desde . ' 00:00:00');
        }
        if ($hasta) {
            $query->where('r.fe_rcbdo_trmte', '<=', $hasta . ' 23:59:59');
        }
    }

    public function obtenerTablaArchivos(array $filtros, int $page, int $perPage, ?int $cargoId, int $companiaId): array
    {
        $query = $this->baseQueryArchivo();
        $this->aplicarFiltrosArchivo($query, $filtros, $cargoId, $companiaId);

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $items = $query->orderByDesc('r.fe_rcbdo_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
        ];
    }

    public function obtenerConteosArchivos(?int $cargoId, int $companiaId): array
    {
        $base = $this->baseQueryArchivo();
        if ($companiaId > 0) {
            $base->where('p.cdgo_cmpnia', $companiaId);
        }

        $finalizados = (clone $base)->where('r.cdgo_crgo_en', $cargoId)->where('r.cdgo_crgo_sa', $cargoId)->whereIn('r.cdgo_estdo_trmte', ['3', '26'])->count();
        $archivados = (clone $base)->where('r.cdgo_crgo_en', $cargoId)->where('r.cdgo_crgo_sa', $cargoId)->where('r.cdgo_estdo_trmte', '27')->count();
        $prestados = (clone $base)->where('r.cdgo_estdo_trmte', '28')->count();

        return [
            'finalizados' => $finalizados,
            'archivados' => $archivados,
            'prestados' => $prestados,
        ];
    }

    public function archivarExpedientes(array $ids, int $cargoId, int $userId, string $fecha, string $observaciones, string $ip, int $companiaId): int
    {
        return (int) $this->db->transaction(function () use ($ids, $cargoId, $userId, $fecha, $observaciones, $ip, $companiaId) {
            $s = $this->schema;
            $validQuery = $this->baseQueryArchivo()
                ->whereIn('p.cdgo_dto_trmte', $ids)
                ->where(function (Builder $query) use ($cargoId) {
                    $query->where('r.cdgo_estdo_trmte', '28')
                        ->orWhere(function (Builder $subQuery) use ($cargoId) {
                            $subQuery->whereIn('r.cdgo_estdo_trmte', ['3', '26'])
                                ->where('r.cdgo_crgo_en', $cargoId)
                                ->where('r.cdgo_crgo_sa', $cargoId);
                        });
                });
            if ($companiaId > 0) {
                $validQuery->where('p.cdgo_cmpnia', $companiaId);
            }

            $validIds = $validQuery->pluck('p.cdgo_dto_trmte')->map(fn ($id) => (int) $id)->unique()->values();

            foreach ($validIds as $id) {
                $trackingId = $this->db->table($s . 'r_dtos_crgos_trmtes')
                    ->where('cdgo_dto_trmte', $id)
                    ->where('in_esta', '1')
                    ->orderByDesc('cdgo_dto_crgo_trmte')
                    ->value('cdgo_dto_crgo_trmte');
                if ($trackingId) {
                    $this->db->table($s . 'r_dtos_crgos_trmtes')->where('cdgo_dto_crgo_trmte', $trackingId)->update(['fe_dto_crgo_fin' => now()]);
                }

                $this->db->table($s . 'r_trmtes_dcmntos')->where('cdgo_dto_trmte', $id)->update(['in_esta' => '0']);
                $this->db->table($s . 'r_trmtes_dcmntos')->insert([
                    'cdgo_crgo_sa' => $cargoId,
                    'cdgo_crgo_en' => $cargoId,
                    'cdgo_dto_trmte' => $id,
                    'cdgo_estdo_trmte' => '27',
                    'fe_rcbdo_trmte' => $fecha,
                    'de_obse' => $observaciones,
                    'in_esta' => '1',
                    'de_urls_imag' => '',
                    'cdgo_lgin' => $userId,
                    'nu_foli_anex' => null,
                ]);

                $currentObse = (string) ($this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->value('de_obse_todo') ?? '');
                $this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->update([
                    'de_obse_todo' => $currentObse !== '' ? $currentObse . ' - ' . $observaciones : $observaciones,
                    'co_usua_modi' => $userId,
                    'ts_usua_modi' => now(),
                ]);

                $this->db->table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => "Se archivo el expediente {$id}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            }

            return $validIds->count();
        });
    }

    public function notificarExpedientesArchivo(array $ids, int $cargoId, int $userId, string $fecha, string $observaciones, string $adjunto, string $ip, int $companiaId): array
    {
        return $this->db->transaction(function () use ($ids, $cargoId, $userId, $fecha, $observaciones, $adjunto, $ip, $companiaId) {
            $s = $this->schema;
            $validQuery = $this->baseQueryArchivo()
                ->whereIn('p.cdgo_dto_trmte', $ids)
                ->whereIn('r.cdgo_estdo_trmte', ['3', '26'])
                ->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId);
            if ($companiaId > 0) {
                $validQuery->where('p.cdgo_cmpnia', $companiaId);
            }

            $validIds = $validQuery->pluck('p.cdgo_dto_trmte')->map(fn ($id) => (int) $id)->unique()->values();

            foreach ($validIds as $id) {
                $trackingId = $this->db->table($s . 'r_dtos_crgos_trmtes')
                    ->where('cdgo_dto_trmte', $id)
                    ->where('in_esta', '1')
                    ->orderByDesc('cdgo_dto_crgo_trmte')
                    ->value('cdgo_dto_crgo_trmte');
                if ($trackingId) {
                    $this->db->table($s . 'r_dtos_crgos_trmtes')->where('cdgo_dto_crgo_trmte', $trackingId)->update(['fe_dto_crgo_fin' => now()]);
                }

                $this->db->table($s . 'r_trmtes_dcmntos')->where('cdgo_dto_trmte', $id)->update(['in_esta' => '0']);
                $this->db->table($s . 'r_trmtes_dcmntos')->insert([
                    'cdgo_crgo_sa' => $cargoId,
                    'cdgo_crgo_en' => $cargoId,
                    'cdgo_dto_trmte' => $id,
                    'cdgo_estdo_trmte' => '26',
                    'fe_rcbdo_trmte' => $fecha,
                    'de_obse' => $observaciones,
                    'in_esta' => '1',
                    'de_urls_imag' => $adjunto,
                    'cdgo_lgin' => $userId,
                    'nu_foli_anex' => null,
                ]);

                $currentObse = (string) ($this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->value('de_obse_todo') ?? '');
                $this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->update([
                    'de_obse_todo' => $currentObse !== '' ? $currentObse . ' - ' . $observaciones : $observaciones,
                    'co_usua_modi' => $userId,
                    'ts_usua_modi' => now(),
                ]);

                $this->db->table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => "Se notifico el expediente {$id}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            }

            return $validIds->all();
        });
    }

    public function prestarExpedientes(array $ids, int $cargoId, int $userId, string $fecha, string $observaciones, string $ip, int $companiaId): int
    {
        return (int) $this->db->transaction(function () use ($ids, $cargoId, $userId, $fecha, $observaciones, $ip, $companiaId) {
            $s = $this->schema;
            $validQuery = $this->baseQueryArchivo()
                ->whereIn('p.cdgo_dto_trmte', $ids)
                ->where('r.cdgo_estdo_trmte', '27')
                ->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId);
            if ($companiaId > 0) {
                $validQuery->where('p.cdgo_cmpnia', $companiaId);
            }

            $validIds = $validQuery->pluck('p.cdgo_dto_trmte')->map(fn ($id) => (int) $id)->unique()->values();

            foreach ($validIds as $id) {
                $trackingId = $this->db->table($s . 'r_dtos_crgos_trmtes')
                    ->where('cdgo_dto_trmte', $id)
                    ->where('in_esta', '1')
                    ->orderByDesc('cdgo_dto_crgo_trmte')
                    ->value('cdgo_dto_crgo_trmte');
                if ($trackingId) {
                    $this->db->table($s . 'r_dtos_crgos_trmtes')->where('cdgo_dto_crgo_trmte', $trackingId)->update(['fe_dto_crgo_fin' => now()]);
                }

                $this->db->table($s . 'r_trmtes_dcmntos')->where('cdgo_dto_trmte', $id)->update(['in_esta' => '0']);
                $this->db->table($s . 'r_trmtes_dcmntos')->insert([
                    'cdgo_crgo_sa' => $cargoId,
                    'cdgo_crgo_en' => $cargoId,
                    'cdgo_dto_trmte' => $id,
                    'cdgo_estdo_trmte' => '28',
                    'fe_rcbdo_trmte' => $fecha,
                    'de_obse' => $observaciones,
                    'in_esta' => '1',
                    'de_urls_imag' => '',
                    'cdgo_lgin' => $userId,
                    'nu_foli_anex' => null,
                ]);

                $currentObse = (string) ($this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->value('de_obse_todo') ?? '');
                $this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $id)->update([
                    'de_obse_todo' => $currentObse !== '' ? $currentObse . ' - ' . $observaciones : $observaciones,
                    'co_usua_modi' => $userId,
                    'ts_usua_modi' => now(),
                ]);

                $this->db->table($s . 'r_mtriz_archvdor_trmtes')->where('cdgo_dto_trmte', $id)->where('in_esta', '1')->update(['in_esta' => '0']);

                $this->db->table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => "Se presto el expediente {$id}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            }

            return $validIds->count();
        });
    }

    public function obtenerMatrizArchivador(): array
    {
        $s = $this->schema;
        return $this->db->table($s . 'a_mtriz_archvdor as m')
            ->leftJoin($s . 'r_mtriz_archvdor_trmtes as rm', function ($join) {
                $join->on('m.cdgo_mtriz_archvdor', '=', 'rm.cdgo_mtriz_archvdor')
                    ->where('rm.in_esta', '1');
            })
            ->leftJoin($s . 'p_dto_trmtes as p', 'rm.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('m.in_esta', '1')
            ->select([
                'm.cdgo_mtriz_archvdor',
                'm.nu_psllo',
                'm.nu_fla',
                'm.nu_clmna',
                'm.no_mtriz_almcen',
                'm.nu_mxmo',
                $this->db->raw('count(rm.cdgo_mtriz_archvdor_trmtes) as ocupados'),
                $this->db->raw("string_agg(p.nu_expe_todo::text, ', ' order by p.nu_expe_todo) as expedientes"),
            ])
            ->groupBy('m.cdgo_mtriz_archvdor', 'm.nu_psllo', 'm.nu_fla', 'm.nu_clmna', 'm.no_mtriz_almcen', 'm.nu_mxmo')
            ->orderBy('m.nu_psllo')
            ->orderBy('m.nu_fla')
            ->orderBy('m.nu_clmna')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->cdgo_mtriz_archvdor,
                'pasillo' => (int) $row->nu_psllo,
                'fila' => (int) $row->nu_fla,
                'columna' => (int) $row->nu_clmna,
                'nombre' => $row->no_mtriz_almcen,
                'maximo' => (int) $row->nu_mxmo,
                'ocupados' => (int) $row->ocupados,
                'expedientes' => $row->expedientes ?: '',
            ])
            ->all();
    }

    public function obtenerExpedientesArchivoParaImprimir(array $filtros, ?int $cargoId, int $companiaId, int $limit = 1000): array
    {
        $query = $this->baseQueryArchivo();
        $this->aplicarFiltrosArchivo($query, $filtros, $cargoId, $companiaId);

        return $query->orderByDesc('r.fe_rcbdo_trmte')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function obtenerDetalleCompletoArchivo(int $id): ?array
    {
        $s = $this->schema;
        $row = $this->baseQueryArchivo()->where('p.cdgo_dto_trmte', $id)->first();
        if (!$row) {
            return null;
        }

        $historial = $this->db->table($s . 'r_trmtes_dcmntos as r')
            ->leftJoin($s . 'p_crgos as c_sa', 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_sa', 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->leftJoin($s . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('r.cdgo_dto_trmte', $id)
            ->select([
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                'a_sa.de_area as area_origen',
                'a_en.de_area as area_destino',
                'r.de_obse',
                'r.in_esta',
            ])
            ->orderByDesc('r.cdgo_trmte_dcmnto')
            ->get()
            ->all();

        return [
            'row' => $row,
            'historial' => $historial,
        ];
    }

    // --- 3. Lista Expedi Todo ---

    private function construirQueryExpedientesTodos(array $filtros, int $companiaId): Builder
    {
        $s = $this->schema;
        $query = $this->db->table($s . 'p_dto_trmtes as p')
            ->join($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->leftJoin($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_crgos as p_crgos', 'r.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_areas.de_area',
                'p.nu_foli_trmte',
                'a_estdos_trmtes.de_estdo_trmte',
                'p.in_sile_admi',
                'p.fe_ingr_trmte',
                'r.fe_rcbdo_trmte',
            ])
            ->where('r.in_esta', '1');

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        $modo = (string) ($filtros['modo'] ?? 'generico');
        $palabra = trim((string) ($filtros['palabra'] ?? ''));
        $fecha = $filtros['fecha'] ?? null;
        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;

        if ($modo === 'fechas') {
            $this->aplicarRangoExpedientesTodos($query, $desde, $hasta, false);
        } else {
            $this->aplicarCampoExpedientesTodos($query, $filtros['campo'] ?? 'p_dto_trmtes.nu_expe_todo', $palabra);
            if ($fecha) {
                $this->aplicarRangoExpedientesTodos($query, $fecha, $fecha, true);
            } elseif ($palabra === '') {
                $query->where('p.fe_ingr_trmte', '>=', \Carbon\Carbon::now('America/Lima')->subDays(60)->format('Y-m-d'));
            }
        }

        return $query;
    }

    private function aplicarRangoExpedientesTodos(Builder $query, $desde, $hasta, bool $incluirDiaSiguiente): void
    {
        $d = $this->normalizarFechaHelper($desde);
        $h = $this->normalizarFechaHelper($hasta);
        if (! $d || ! $h) {
            return;
        }

        if ($incluirDiaSiguiente) {
            $query->whereRaw(
                "p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= (CAST(? AS date) + 1) + interval '23 hours 59 minutes 59 seconds'",
                [$d . ' 00:00:00', $h]
            );
            return;
        }

        $query->whereRaw('p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= ?', [$d . ' 00:00:00', $h . ' 23:59:59']);
    }

    private function aplicarCampoExpedientesTodos(Builder $query, $campo, $valor): void
    {
        $allowed = [
            'p_dto_trmtes.nu_expe_todo',
            'p_usrios.nu_tele',
            'p_usrios.nu_docu',
            'p_usrios.no_crto',
            'a_areas.de_area',
            'a_tpo_trmtes.de_tpo_trmte',
            'a_estdos_trmtes.dc_estdo_trmte',
            'p_dto_trmtes.de_obse_todo',
        ];
        $campo = (string) $campo;
        if (! in_array($campo, $allowed, true)) {
            $campo = 'p_dto_trmtes.nu_expe_todo';
        }
        $valor = trim((string) $valor);
        if ($valor === '') {
            return;
        }

        $col = match ($campo) {
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.nu_tele' => 'p_usrios.nu_tele',
            'p_usrios.nu_docu' => 'p_usrios.nu_docu',
            'p_usrios.no_crto' => 'p_usrios.no_crto',
            'a_areas.de_area' => 'a_areas.de_area',
            'a_tpo_trmtes.de_tpo_trmte' => 'a_tpo_trmtes.de_tpo_trmte',
            'a_estdos_trmtes.dc_estdo_trmte' => 'a_estdos_trmtes.dc_estdo_trmte',
            'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
        };
        $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $valor . '%']);
    }

    public function buscarExpedientesTodos(array $filtros, int $page, int $perPage, int $companiaId): array
    {
        $query = $this->construirQueryExpedientesTodos($filtros, $companiaId);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $rows = $query->orderByDesc('p.cdgo_dto_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'rows' => $rows,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    public function obtenerExpedientesTodosParaImprimir(array $filtros, int $companiaId, int $limit = 2000): array
    {
        return $this->construirQueryExpedientesTodos($filtros, $companiaId)
            ->orderBy('p.cdgo_dto_trmte')
            ->limit($limit)
            ->get()
            ->all();
    }

    // --- 4. Lista Repor Area ---

    public function buscarReporteArea(array $filtros, int $cargoId, string $bandeja, int $page, int $perPage, int $companiaId): array
    {
        $s = $this->schema;
        $query = $this->db->table($s . 'r_trmtes_dcmntos as r')
            ->leftJoin($s . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($s . 'h_usrios_ntral as h_usrios_ntral', 'l.cdgo_usrios', '=', 'h_usrios_ntral.cdgo_usrios')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.nu_foli_trmte',
                'r.fe_rcbdo_trmte',
                'p.in_sile_admi',
                'p_usrios.no_crto',
                'p.de_obse_trmte',
                $this->db->raw('COALESCE(h_usrios_ntral.no_usrio, l.de_lgin) as usuario'),
                'a_docmntos_trmtes.de_docmnto_trmte',
            ]);

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        if ($bandeja === '2') {
            $query->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', '<>', $cargoId);
        } elseif ($bandeja === '3') {
            $query->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', $cargoId)
                ->where('a_estdos_trmtes.cdgo_estdo_trmte', '3');
        } else {
            $query->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', $cargoId)
                ->where('a_estdos_trmtes.cdgo_estdo_trmte', '2');
        }

        $modo = (string) ($filtros['modo'] ?? 'generico');
        if ($modo === 'fechas') {
            $d = $this->normalizarFechaHelper($filtros['desde'] ?? null);
            $h = $this->normalizarFechaHelper($filtros['hasta'] ?? null);
            if ($d && $h) {
                $query->whereRaw('r.fe_rcbdo_trmte >= ? AND r.fe_rcbdo_trmte <= (CAST(? AS date) + 1)', [$d, $h]);
            }
        } else {
            $palabra = trim((string) ($filtros['palabra'] ?? ''));
            $campo = (string) ($filtros['campo'] ?? 'p_dto_trmtes.nu_expe_todo');
            $allowed = [
                'p_dto_trmtes.nu_expe_todo',
                'p_usrios.nu_docu',
                'p_usrios.no_crto',
                'a_docmntos_trmtes.de_docmnto_trmte',
                'h_usrios_ntral.no_usrio',
                'p_dto_trmtes.de_obse_todo',
            ];
            if ($palabra !== '') {
                if (! in_array($campo, $allowed, true)) {
                    $campo = 'p_dto_trmtes.nu_expe_todo';
                }
                $col = match ($campo) {
                    'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
                    'p_usrios.nu_docu' => 'p_usrios.nu_docu',
                    'p_usrios.no_crto' => 'p_usrios.no_crto',
                    'a_docmntos_trmtes.de_docmnto_trmte' => 'a_docmntos_trmtes.de_docmnto_trmte',
                    'h_usrios_ntral.no_usrio' => 'h_usrios_ntral.no_usrio',
                    'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
                };
                $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $palabra . '%']);
            }
            $f = $this->normalizarFechaHelper($filtros['fecha'] ?? null);
            if ($f) {
                $query->whereRaw('r.fe_rcbdo_trmte >= ? AND r.fe_rcbdo_trmte <= (CAST(? AS date) + 1)', [$f, $f]);
            }
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $rows = $query->orderByDesc('r.fe_rcbdo_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'rows' => $rows,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    // --- 5. Lista Reporte ---

    private function queryBaseListaReporte(array $filtros, int $companiaId): Builder
    {
        $s = $this->schema;
        $query = $this->db->table($s . 'p_dto_trmtes as p')
            ->join($s . 'p_lgin as l', 'p.co_usua_ingr', '=', 'l.cdgo_lgin')
            ->join($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_crgos as p_crgos', 'r.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($s . 'p_usrios as p_usrios_1', 'l.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->leftJoin($s . 'h_usrios_ntral as h_usrios_ntral', 'p_usrios_1.cdgo_usrios', '=', 'h_usrios_ntral.cdgo_usrios')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_areas.dc_area',
                'p.de_obse_trmte',
                'p.in_sile_admi',
                'p.fe_ingr_trmte',
                'r.fe_rcbdo_trmte',
                'p_usrios.no_crto',
                'a_docmntos_trmtes.de_docmnto_trmte',
                'p.nu_foli_trmte',
                $this->db->raw('COALESCE(h_usrios_ntral.no_usrio, p_usrios_1.no_crto, l.de_lgin) as usuario'),
                'r.cdgo_estdo_trmte',
                'a_estdos_trmtes.de_estdo_trmte',
                'a_estdos_trmtes.dc_estdo_trmte',
            ])
            ->where('r.in_esta', '1');

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        $simple = filter_var($filtros['simple'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $simple) {
            $query->where('p.cdgo_tpo_trmte', '<>', '17');
        }

        $modo = (string) ($filtros['modo'] ?? 'generico');
        if ($modo === 'fechas') {
            $this->aplicarRangoListaReporte($query, $filtros['desde'] ?? null, $filtros['hasta'] ?? null);
        } elseif ($modo === 'avanzado') {
            $this->aplicarCampoListaReporte($query, $filtros['campo1'] ?? 'a_areas.de_area', $filtros['palabra1'] ?? '', false);
            $this->aplicarRangoListaReporte($query, $filtros['desde'] ?? null, $filtros['hasta'] ?? null);
            $palabra2 = trim((string) ($filtros['palabra2'] ?? ''));
            if ($palabra2 !== '') {
                $this->aplicarCampoListaReporte(
                    $query,
                    $filtros['campo2'] ?? 'a_estdos_trmtes.dc_estdo_trmte',
                    $palabra2,
                    filter_var($filtros['excluir'] ?? false, FILTER_VALIDATE_BOOLEAN)
                );
            }
        } else {
            $palabra = trim((string) ($filtros['palabra'] ?? ''));
            $fecha = $filtros['fecha'] ?? null;
            $this->aplicarCampoListaReporte($query, $filtros['campo'] ?? 'p_dto_trmtes.nu_expe_todo', $palabra, false, true);
            if ($fecha) {
                $this->aplicarRangoListaReporte($query, $fecha, $fecha);
            } elseif ($palabra === '') {
                $query->where('p.fe_ingr_trmte', '>=', \Carbon\Carbon::now('America/Lima')->subDays(60)->format('Y-m-d'));
            }
        }

        return $query;
    }

    private function aplicarRangoListaReporte(Builder $query, $desde, $hasta): void
    {
        $d = $this->normalizarFechaHelper($desde);
        $h = $this->normalizarFechaHelper($hasta);
        if ($d && $h) {
            $query->whereRaw('p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= (CAST(? AS date) + 1)', [$d, $h]);
        } elseif ($d) {
            $query->where('p.fe_ingr_trmte', '>=', $d);
        } elseif ($h) {
            $query->whereRaw('p.fe_ingr_trmte <= (CAST(? AS date) + 1)', [$h]);
        }
    }

    private function aplicarCampoListaReporte(Builder $query, $campo, $valor, bool $excluir, bool $exactoExpediente = false): void
    {
        $allowed = [
            'p_dto_trmtes.nu_expe_todo',
            'p_usrios.nu_docu',
            'p_usrios.no_crto',
            'a_areas.de_area',
            'a_tpo_trmtes.de_tpo_trmte',
            'p_dto_trmtes.nu_docu_trmte',
            'a_estdos_trmtes.dc_estdo_trmte',
            'p_usrios_1.no_crto',
            'p_dto_trmtes.de_obse_todo',
        ];
        $campo = (string) $campo;
        if (! in_array($campo, $allowed, true)) {
            $campo = 'p_dto_trmtes.nu_expe_todo';
        }
        $col = match ($campo) {
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.nu_docu' => 'p_usrios.nu_docu',
            'p_usrios.no_crto' => 'p_usrios.no_crto',
            'a_areas.de_area' => 'a_areas.de_area',
            'a_tpo_trmtes.de_tpo_trmte' => 'a_tpo_trmtes.de_tpo_trmte',
            'p_dto_trmtes.nu_docu_trmte' => 'p.nu_docu_trmte',
            'a_estdos_trmtes.dc_estdo_trmte' => 'a_estdos_trmtes.dc_estdo_trmte',
            'p_usrios_1.no_crto' => 'p_usrios_1.no_crto',
            'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
        };
        $valor = trim((string) $valor);
        if ($valor === '') {
            return;
        }

        if ($exactoExpediente && $campo === 'p_dto_trmtes.nu_expe_todo') {
            $query->where($col, '=', $valor);
            return;
        }

        if ($excluir) {
            $query->whereRaw("UPPER({$col}) NOT LIKE UPPER(?)", ['%' . $valor . '%']);
        } else {
            $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $valor . '%']);
        }
    }

    public function buscarReporteExpedientesIngresados(array $filtros, int $page, int $perPage, int $companiaId): array
    {
        $query = $this->queryBaseListaReporte($filtros, $companiaId);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $rows = $query->orderByDesc('p.cdgo_dto_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'rows' => $rows,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    public function obtenerReporteExpedientesIngresadosParaImprimir(array $filtros, int $companiaId, int $limit = 2000): array
    {
        return $this->queryBaseListaReporte($filtros, $companiaId)
            ->orderByDesc('p.cdgo_dto_trmte')
            ->limit($limit)
            ->get()
            ->all();
    }

    // --- 6. Notificar Plataforma ---

    public function obtenerTablaNotificarPlataforma(array $filtros, int $page, int $perPage, int $companiaId): array
    {
        $s = $this->schema;
        $query = $this->db->table($s . 'r_trmtes_dcmntos as r')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->leftJoin($s . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->leftJoin($s . 'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($s . 'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('r.in_esta', '1')
            ->whereIn('r.cdgo_estdo_trmte', [3, 26])
            ->where('p.cdgo_cmpnia', $companiaId);

        $modo = (string) ($filtros['modo'] ?? 'generico');
        $criterio = trim((string) ($filtros['criterio'] ?? ''));
        $campo = (string) ($filtros['campo'] ?? 'p.nu_expe_todo');
        $desde = trim((string) ($filtros['desde'] ?? ''));
        $hasta = trim((string) ($filtros['hasta'] ?? ''));

        $campos = [
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.no_crto' => 'u.no_crto',
            'u.no_crto' => 'u.no_crto',
            'a_tpo_trmtes.de_tpo_trmte' => 't.de_tpo_trmte',
            't.de_tpo_trmte' => 't.de_tpo_trmte',
        ];

        if ($modo === 'fechas') {
            if ($desde !== '') {
                try {
                    $query->where('p.fe_ingr_trmte', '>=', \Carbon\Carbon::createFromFormat('Y-m-d', $desde)->startOfDay());
                } catch (\Throwable) {}
            }
            if ($hasta !== '') {
                try {
                    $query->where('p.fe_ingr_trmte', '<=', \Carbon\Carbon::createFromFormat('Y-m-d', $hasta)->endOfDay());
                } catch (\Throwable) {}
            }
        } elseif ($criterio !== '' && isset($campos[$campo])) {
            $col = $campos[$campo];
            $query->whereRaw("UPPER({$col}::text) LIKE UPPER(?)", [$criterio . '%']);
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $items = $query
            ->select(
                'r.cdgo_trmte_dcmnto',
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.in_sile_admi',
                'p.nu_foli_trmte',
                'p.fe_ingr_trmte',
                'u.no_crto',
                't.de_tpo_trmte',
                'a.dc_area',
                'a.de_area',
                'e.dc_estdo_trmte',
                'e.cdgo_estdo_trmte',
                'r.de_urls_imag'
            )
            ->orderByDesc('p.cdgo_dto_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    public function notificarExpedientePlataforma(int $cdgoDtoTrmte, string $fecha, ?string $observaciones, ?string $urlImag, ?int $cargoId, int $userId, string $ip): void
    {
        $s = $this->schema;
        $this->db->transaction(function () use ($cdgoDtoTrmte, $fecha, $observaciones, $urlImag, $cargoId, $userId, $ip, $s) {
            $this->db->table($s . 'p_dto_trmtes')
                ->where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->lockForUpdate()
                ->first();

            $movActivo = $this->db->table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->where('in_esta', '1')
                ->first();

            if ($movActivo && (int) $movActivo->cdgo_estdo_trmte === 26) {
                throw new \Exception('El expediente ya ha sido notificado anteriormente.');
            }

            $effectiveCargo = ($cargoId && $cargoId > 0) ? $cargoId : (int) ($movActivo->cdgo_crgo_en ?? 0);

            $this->db->table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->update(['in_esta' => '0']);

            $this->db->table($s . 'r_trmtes_dcmntos')->insert([
                'cdgo_dto_trmte' => $cdgoDtoTrmte,
                'cdgo_estdo_trmte' => '26',
                'in_esta' => '1',
                'de_obse' => $observaciones ?: '',
                'de_urls_imag' => $urlImag ?: '',
                'fe_rcbdo_trmte' => $fecha,
                'cdgo_crgo_en' => $effectiveCargo,
                'cdgo_crgo_sa' => $effectiveCargo,
                'cdgo_lgin' => $userId,
            ]);

            $ultimoCargoTrmte = $this->db->table($s . 'r_dtos_crgos_trmtes')
                ->where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->where('in_esta', '1')
                ->orderBy('cdgo_dto_crgo_trmte', 'desc')
                ->first();

            if ($ultimoCargoTrmte) {
                $this->db->table($s . 'r_dtos_crgos_trmtes')
                    ->where('cdgo_dto_crgo_trmte', $ultimoCargoTrmte->cdgo_dto_crgo_trmte)
                    ->update(['fe_dto_crgo_fin' => now()]);
            }

            try {
                $this->db->table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => "Se notifico el expediente ID {$cdgoDtoTrmte}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            } catch (\Throwable) {}
        });
    }

    // --- 7. Redirigir Expediente ---

    public function obtenerGerenciasParaRedirigir(int $companiaId): array
    {
        $s = $this->schema;
        try {
            return $this->db->table($s . 'a_areas')
                ->whereNull('cdgo_area_prim')
                ->where('in_esta', '1')
                ->where('cdgo_cmpnia', $companiaId)
                ->orderBy('de_area')
                ->get(['cdgo_area', 'de_area', 'in_part'])
                ->all();
        } catch (\Throwable) {
            $gerencias = $this->db->table($s . 'a_areas')
                ->whereNull('cdgo_area_prim')
                ->where('in_esta', '1')
                ->where('cdgo_cmpnia', $companiaId)
                ->orderBy('de_area')
                ->get(['cdgo_area', 'de_area']);
            $gerencias->each(function ($g) {
                $g->in_part = '0';
            });
            return $gerencias->all();
        }
    }

    public function obtenerSubgerenciasParaRedirigir(int $gerenciaId, int $companiaId): array
    {
        $s = $this->schema;
        return $this->db->table($s . 'a_areas')
            ->where('cdgo_area_prim', $gerenciaId)
            ->where('in_esta', '1')
            ->where('cdgo_cmpnia', $companiaId)
            ->orderBy('de_area')
            ->get(['cdgo_area', 'de_area'])
            ->all();
    }

    private function queryReenviarRedirigir(array $filtros): Builder
    {
        $s = $this->schema;
        $query = $this->db->table($s . 'r_trmtes_dcmntos as r_trmtes_dcmntos')
            ->join($s . 'p_crgos as p_crgos', 'r_trmtes_dcmntos.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($s . 'p_dto_trmtes as p_dto_trmtes', 'r_trmtes_dcmntos.cdgo_dto_trmte', '=', 'p_dto_trmtes.cdgo_dto_trmte')
            ->join($s . 'p_lgin as p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')
            ->join($s . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->leftJoin($s . 'p_usrios as p_usrios_1', 'p_lgin.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p_dto_trmtes.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->select(
                'p_dto_trmtes.cdgo_dto_trmte',
                'p_dto_trmtes.nu_expe_todo',
                'p_usrios.no_crto',
                'r_trmtes_dcmntos.fe_rcbdo_trmte',
                'a_areas.dc_area',
                'p_dto_trmtes.de_obse_trmte',
                'p_dto_trmtes.in_encu_tram',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_dto_trmtes.cdgo_docmnto_trmte',
                'r_trmtes_dcmntos.in_esta',
                'r_trmtes_dcmntos.cdgo_crgo_en',
                'a_estdos_trmtes.de_estdo_trmte',
                'p_lgin.de_lgin',
                'r_trmtes_dcmntos.cdgo_trmte_dcmnto',
                'a_docmntos_trmtes.nu_tiem_sile'
            )
            ->where('r_trmtes_dcmntos.cdgo_crgo_sa', '0')
            ->where('r_trmtes_dcmntos.in_esta', '1')
            ->whereNotIn('a_estdos_trmtes.cdgo_estdo_trmte', [29]);

        $palabra = trim((string) ($filtros['palabra'] ?? ''));
        $campo = (string) ($filtros['buscar_por'] ?? 'p_dto_trmtes.nu_expe_todo');
        $allowed = [
            'p_dto_trmtes.nu_expe_todo',
            'a_areas.de_area',
            'p_usrios.no_crto',
            'a_tpo_trmtes.dc_tpo_trmte',
            'p_dto_trmtes.de_obse_todo',
            'p_usrios_1.no_crto',
        ];
        if (! in_array($campo, $allowed, true)) {
            $campo = 'p_dto_trmtes.nu_expe_todo';
        }

        if ($palabra !== '') {
            $query->whereRaw("UPPER({$campo}) LIKE UPPER(?)", ['%' . $palabra . '%']);
        }

        $fecha = $this->normalizarFechaHelper($filtros['fecha'] ?? null);
        if ($fecha !== null) {
            $query->whereRaw('p_dto_trmtes.fe_ingr_trmte BETWEEN ? AND (CAST(? AS date) + 1)', [$fecha, $fecha]);
        }

        return $query;
    }

    public function buscarExpedientesParaRedirigir(array $filtros, int $page, int $perPage, int $companiaId): array
    {
        $query = $this->queryReenviarRedirigir($filtros);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $rows = $query->orderByDesc('r_trmtes_dcmntos.fe_rcbdo_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'rows' => $rows,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    public function derivarExpedientesRedirigir(array $ids, int $areaDestino, string $observacion, int $userId, string $ip, int $companiaId): void
    {
        $s = $this->schema;
        $cargoDestino = $this->db->table($s . 'p_crgos')
            ->where('cdgo_area', $areaDestino)
            ->where('nu_orde', 1)
            ->orderByDesc('cdgo_crgo')
            ->first(['cdgo_crgo']);

        if (! $cargoDestino) {
            throw new \RuntimeException('No se encontró un cargo válido para el área destino.');
        }

        $this->db->transaction(function () use ($ids, $cargoDestino, $areaDestino, $observacion, $userId, $ip, $s) {
            foreach ($ids as $idTram) {
                $idTram = (int) $idTram;

                $rowSlnc = $this->db->table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->first();
                if ($rowSlnc && (string) $rowSlnc->in_esta === '1' && $rowSlnc->fe_inic) {
                    $dias = (int) round((\Carbon\Carbon::today()->getTimestamp() - \Carbon\Carbon::parse($rowSlnc->fe_inic)->startOfDay()->getTimestamp()) / 86400);
                    $total = max(0, $dias) + (int) ($rowSlnc->nu_dias ?? 0);
                    $this->db->table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->update(['nu_dias' => $total, 'in_esta' => '2']);
                }

                $this->db->table($s . 'r_trmtes_dcmntos')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->update(['in_esta' => '0']);

                $this->db->table($s . 'p_dto_trmtes')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->update(['cdgo_area' => $areaDestino]);

                $this->db->table($s . 'r_trmtes_dcmntos')->insert([
                    'cdgo_crgo_sa' => 0,
                    'cdgo_crgo_en' => $cargoDestino->cdgo_crgo,
                    'cdgo_dto_trmte' => $idTram,
                    'cdgo_estdo_trmte' => 13,
                    'fe_rcbdo_trmte' => now(),
                    'de_obse' => mb_strtoupper($observacion, 'UTF-8'),
                    'in_esta' => '1',
                    'de_urls_imag' => '',
                    'cdgo_lgin' => $userId,
                    'nu_foli_anex' => null,
                ]);

                $obseTodo = (string) ($this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $idTram)->value('de_obse_todo') ?? '');
                $this->db->table($s . 'p_dto_trmtes')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->update([
                        'de_obse_todo' => mb_strtoupper(trim($obseTodo . ' - ' . $observacion), 'UTF-8'),
                    ]);

                try {
                    $this->db->table($s . 'p_sgmto_log')->insert([
                        'cdgo_lgin' => $userId,
                        'de_sgto_log' => "Se reedirigio el expediente {$idTram}",
                        'fe_sgto_log' => now(),
                        'de_ip' => $ip,
                    ]);
                } catch (\Throwable) {}
            }
        });
    }

    public function anularExpedienteRedirigir(int $id, string $motivo, int $userId, string $ip, int $companiaId): void
    {
        $s = $this->schema;
        $this->db->transaction(function () use ($id, $motivo, $userId, $ip, $s) {
            $mov = $this->db->table($s . 'r_trmtes_dcmntos')->where('cdgo_trmte_dcmnto', $id)->first();
            if (! $mov) {
                throw new \RuntimeException('Movimiento no encontrado.');
            }

            $expediente = (string) ($this->db->table($s . 'p_dto_trmtes')->where('cdgo_dto_trmte', $mov->cdgo_dto_trmte)->value('nu_expe_todo') ?? '');
            $deObse = mb_strtoupper($motivo . ', SE ANULA CON FECHA ' . now()->format('d/m/Y H:i:s'), 'UTF-8');

            $this->db->table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_trmte_dcmnto', $id)
                ->update([
                    'cdgo_estdo_trmte' => 29,
                    'de_obse' => $deObse,
                ]);

            try {
                $this->db->table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => "Se anulo el expediente {$expediente} con cdgo_trmte_dcmnto = {$id}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            } catch (\Throwable) {}
        });
    }

    public function obtenerExpedientesParaRedirigirImprimir(array $filtros, int $companiaId): array
    {
        return $this->queryReenviarRedirigir($filtros)
            ->addSelect(
                'p_dto_trmtes.nu_foli_trmte',
                'p_dto_trmtes.cdgo_tpo_trmte',
                'a_areas.de_area',
                'a_areas.cdgo_area'
            )
            ->orderBy('a_areas.de_area')
            ->orderByDesc('p_dto_trmtes.cdgo_dto_trmte')
            ->get()
            ->all();
    }

    public function obtenerDetalleExpedienteRedirigir(int $id): ?array
    {
        $s = $this->schema;
        $dtoTrmte = $this->db->table($s . 'p_dto_trmtes as p')
            ->leftJoin($s . 'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($s . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select(
                'p.nu_expe_todo',
                'p.fe_ingr_trmte',
                'p.de_obse_todo',
                'p.nu_foli_trmte',
                't.de_tpo_trmte',
                'u.no_crto',
                'u.nu_docu',
                'u.nu_ruc',
                'u.de_dire',
                'u.nu_tele',
                'u.de_mail'
            )
            ->where('p.cdgo_dto_trmte', $id)
            ->first();

        if (! $dtoTrmte) {
            return null;
        }

        return [
            'expediente' => $dtoTrmte->nu_expe_todo,
            'fecha' => $dtoTrmte->fe_ingr_trmte ? \Carbon\Carbon::parse($dtoTrmte->fe_ingr_trmte)->format('d/m/Y H:i') : '',
            'solicitante' => $dtoTrmte->no_crto,
            'documento_identidad' => ($dtoTrmte->nu_ruc ?: $dtoTrmte->nu_docu) ?: 'No especificado',
            'domicilio' => $dtoTrmte->de_dire ?: 'No especificado',
            'telefono' => $dtoTrmte->nu_tele ?: 'No especificado',
            'correo' => $dtoTrmte->de_mail ?: 'No especificado',
            'folios' => $dtoTrmte->nu_foli_trmte ?: '0',
            'tipo_doc' => $dtoTrmte->de_tpo_trmte,
            'observacion' => $dtoTrmte->de_obse_todo ?: 'Ninguna',
        ];
    }

    // --- 8. Reporte Expediente ---

    public function verificarPermisoReporte(int $perfilId, array $roleIds): bool
    {
        return $this->db->table($this->schema . 'r_prmsos')
            ->where('cdgo_prfle', $perfilId)
            ->whereIn('cdgo_rle', $roleIds)
            ->exists();
    }

    public function obtenerAreaPorCargo(int $cargoId): int
    {
        return (int) ($this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('cdgo_area') ?? 0);
    }

    private function baseSearchQueryReporte(): Builder
    {
        $s = $this->schema;
        return $this->db->table($s . 'p_dto_trmtes as p')
            ->join($s . 'p_lgin as l', 'p.co_usua_ingr', '=', 'l.cdgo_lgin')
            ->join($s . 'p_usrios as solicitante', 'p.cdgo_usrios', '=', 'solicitante.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->join($s . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->join($s . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin($s . 'p_crgos as c_sa', 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_sa', 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->leftJoin($s . 'a_docmntos_trmtes as doc', 'p.cdgo_docmnto_trmte', '=', 'doc.cdgo_docmnto_trmte')
            ->leftJoin($s . 'a_tpo_trmtes as tipo', 'p.cdgo_tpo_trmte', '=', 'tipo.cdgo_tpo_trmte')
            ->leftJoin($s . 'p_usrios as usuario_lgin', 'l.cdgo_usrios', '=', 'usuario_lgin.cdgo_usrios')
            ->leftJoin($s . 'h_usrios_ntral as hn', 'usuario_lgin.cdgo_usrios', '=', 'hn.cdgo_usrios')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.nu_expe_trmte',
                'p.nu_docu_trmte',
                'p.cdgo_tpo_trmte',
                'p.in_sile_admi',
                'p.fe_ingr_trmte',
                'p.nu_foli_trmte',
                'p.de_obse_trmte',
                'p.nu_tiem_sile_adcnal',
                'solicitante.no_crto',
                'solicitante.nu_docu',
                'tipo.de_tpo_trmte',
                'doc.de_docmnto_trmte',
                'doc.nu_tiem_sile',
                'a_en.cdgo_area as cdgo_area_actual',
                'a_en.dc_area',
                'a_en.de_area',
                'a_sa.cdgo_area as cdgo_area_origen',
                'c_en.cdgo_crgo as cdgo_crgo_actual',
                'c_sa.cdgo_crgo as cdgo_crgo_origen',
                'r.cdgo_trmte_dcmnto',
                'r.cdgo_estdo_trmte',
                'r.fe_rcbdo_trmte',
                'r.de_obse as movimiento_observacion',
                'r.de_urls_imag',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                $this->db->raw('COALESCE(hn.no_usrio, usuario_lgin.no_crto, l.de_lgin) as usuario_plataforma'),
            ])
            ->where('r.in_esta', '1');
    }

    private function aplicarFiltrosReporteGeneral(Builder $query, array $filtros, int $companiaId): void
    {
        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        if (! filter_var($filtros['simple'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('p.cdgo_tpo_trmte', '<>', '17');
        }

        $mode = $filtros['modo'] ?? 'generico';
        if ($mode === 'fechas') {
            if (! empty($filtros['desde']) && ! empty($filtros['hasta'])) {
                $query->whereBetween('p.fe_ingr_trmte', [$filtros['desde'] . ' 00:00:00', $filtros['hasta'] . ' 23:59:59']);
            }
            return;
        }

        if ($mode === 'avanzado') {
            $this->aplicarCampoReporteGeneral($query, $filtros['campo1'] ?? 'area', $filtros['palabra1'] ?? '');
            if (! empty($filtros['desde']) && ! empty($filtros['hasta'])) {
                $query->whereBetween('p.fe_ingr_trmte', [$filtros['desde'] . ' 00:00:00', $filtros['hasta'] . ' 23:59:59']);
            }
            $this->aplicarCampoReporteGeneral($query, $filtros['campo2'] ?? 'estado', $filtros['palabra2'] ?? '', filter_var($filtros['excluir'] ?? false, FILTER_VALIDATE_BOOLEAN));
            return;
        }

        $this->aplicarCampoReporteGeneral($query, $filtros['campo'] ?? 'expediente', $filtros['palabra'] ?? '');

        if (! empty($filtros['fecha'])) {
            $query->whereBetween('p.fe_ingr_trmte', [$filtros['fecha'] . ' 00:00:00', $filtros['fecha'] . ' 23:59:59']);
        } elseif (empty($filtros['palabra'])) {
            $query->where('p.fe_ingr_trmte', '>=', \Carbon\Carbon::now('America/Lima')->subDays(60)->startOfDay()->format('Y-m-d H:i:s'));
        }
    }

    private function aplicarCampoReporteGeneral(Builder $query, string $field, ?string $value, bool $exclude = false): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $column = match ($field) {
            'identificacion' => 'solicitante.nu_docu',
            'nombre' => 'solicitante.no_crto',
            'area' => 'a_en.de_area',
            'tipo_documento' => 'tipo.de_tpo_trmte',
            'nro_documento' => 'p.nu_docu_trmte',
            'estado' => 'e.dc_estdo_trmte',
            'usuario' => 'usuario_lgin.no_crto',
            'observaciones' => 'p.de_obse_trmte',
            'expediente_varios' => 'p.nu_expe_trmte',
            default => 'p.nu_expe_todo',
        };

        if ($field === 'expediente') {
            $query->where($column, '=', $value);
            return;
        }

        if ($field === 'expediente_varios') {
            $numbers = collect(explode(',', $value))->map(fn ($part) => trim($part))->filter(fn ($part) => $part !== '')->values();
            if ($numbers->isNotEmpty()) {
                $query->whereIn($column, $numbers->all());
            }
            return;
        }

        $op = $exclude ? 'NOT ILIKE' : 'ILIKE';
        $query->where($column, $op, '%' . mb_strtoupper($value) . '%');
    }

    public function buscarReporteGeneral(array $filtros, int $page, int $perPage, int $companiaId): array
    {
        $query = $this->baseSearchQueryReporte();
        $this->aplicarFiltrosReporteGeneral($query, $filtros, $companiaId);

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $items = $query->orderByDesc('p.cdgo_dto_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
        ];
    }

    public function obtenerReporteGeneralResumenArea(string $desde, string $hasta, int $companiaId): array
    {
        $s = $this->schema;
        $from = $desde . ' 00:01';
        $to = $hasta . ' 23:59';

        $areas = $this->db->table($s . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_area', '<>', 0)
            ->orderBy('de_area')
            ->get(['cdgo_area', 'de_area']);

        $porDestino = collect($this->db->select("
            SELECT
                a_en.cdgo_area AS cdgo_area,
                SUM(CASE WHEN (a_sa.cdgo_area IS NULL OR a_sa.cdgo_area <> a_en.cdgo_area) AND r.cdgo_estdo_trmte IN ('5') THEN 1 ELSE 0 END) AS digitalizar,
                SUM(CASE WHEN (a_sa.cdgo_area IS NULL OR a_sa.cdgo_area <> a_en.cdgo_area) AND r.cdgo_estdo_trmte NOT IN ('5','12','29','27') THEN 1 ELSE 0 END) AS por_recibir,
                SUM(CASE WHEN a_sa.cdgo_area = a_en.cdgo_area AND r.cdgo_estdo_trmte NOT IN ('3','26','27') THEN 1 ELSE 0 END) AS recibidos,
                SUM(CASE WHEN a_sa.cdgo_area = a_en.cdgo_area AND r.cdgo_estdo_trmte IN ('3') THEN 1 ELSE 0 END) AS finalizados
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            INNER JOIN {$s}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
            LEFT JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$s}a_areas a_sa ON c_sa.cdgo_area = a_sa.cdgo_area
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE r.in_esta = '1'
              AND p.fe_ingr_trmte >= ?
              AND p.fe_ingr_trmte <= ?
            GROUP BY a_en.cdgo_area
        ", [$from, $to]))->keyBy('cdgo_area');

        $porOrigen = collect($this->db->select("
            SELECT
                a_sa.cdgo_area AS cdgo_area,
                COUNT(*) AS derivados
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            INNER JOIN {$s}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
            LEFT JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$s}a_areas a_sa ON c_sa.cdgo_area = a_sa.cdgo_area
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE r.in_esta = '1'
              AND a_sa.cdgo_area IS NOT NULL
              AND a_en.cdgo_area <> a_sa.cdgo_area
              AND p.fe_ingr_trmte >= ?
              AND p.fe_ingr_trmte <= ?
            GROUP BY a_sa.cdgo_area
        ", [$from, $to]))->keyBy('cdgo_area');

        $rows = [];
        foreach ($areas as $area) {
            $id = (int) $area->cdgo_area;
            $dest = $porDestino->get($id);
            $orig = $porOrigen->get($id);
            $digitalizar = (int) ($dest->digitalizar ?? 0);
            $porRecibir = (int) ($dest->por_recibir ?? 0);
            $recibidos = (int) ($dest->recibidos ?? 0);
            $finalizados = (int) ($dest->finalizados ?? 0);
            $derivados = (int) ($orig->derivados ?? 0);
            $total = $digitalizar + $porRecibir + $recibidos + $derivados + $finalizados;
            $porcentaje = $total > 0 ? round((($derivados + $finalizados) / $total) * 100, 2) : 0.0;

            $row = (object) [
                'cdgo_area' => $id,
                'cdgo_crgo' => null,
                'de_area' => $area->de_area,
                'id' => $id,
                'nombre' => $area->de_area,
                'usuarios' => '',
                'digitalizar' => $digitalizar,
                'por_recibir' => $porRecibir,
                'recibidos' => $recibidos,
                'derivados' => $derivados,
                'finalizados' => $finalizados,
                'criticos' => 0,
                'sin_finalizar' => 0,
                'total_doc' => $total,
                'porcentaje' => $porcentaje,
            ];
            $rows[] = $row;
        }

        usort($rows, function ($a, $b) {
            if ($b->porcentaje !== $a->porcentaje) {
                return $b->porcentaje <=> $a->porcentaje;
            }
            return $b->total_doc <=> $a->total_doc;
        });

        return $rows;
    }

    public function obtenerReporteGeneralResumenCargo(string $desde, string $hasta, int $areaId, int $companiaId): array
    {
        $s = $this->schema;
        $from = $desde . ' 00:00:00';
        $to = $hasta . ' 23:59:59';

        $sql = "
            SELECT
                c.cdgo_crgo,
                c.de_crgo AS de_area,
                STRING_AGG(DISTINCT u.no_crto, ' - ') AS usuarios,
                0 AS digitalizar,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND (c_sa.cdgo_crgo IS NULL OR c_sa.cdgo_crgo != c.cdgo_crgo) AND r.cdgo_estdo_trmte NOT IN ('5','12','29','27') THEN 1 ELSE 0 END), 0) AS por_recibir,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_sa.cdgo_crgo = c.cdgo_crgo AND c_en.cdgo_crgo = c.cdgo_crgo AND r.cdgo_estdo_trmte NOT IN ('3','26','27') THEN 1 ELSE 0 END), 0) AS recibidos,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_sa.cdgo_crgo = c.cdgo_crgo AND c_en.cdgo_crgo != c.cdgo_crgo THEN 1 ELSE 0 END), 0) AS derivados,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_sa.cdgo_crgo = c.cdgo_crgo AND c_en.cdgo_crgo = c.cdgo_crgo AND r.cdgo_estdo_trmte IN ('3') THEN 1 ELSE 0 END), 0) AS finalizados,
                0 AS criticos,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_en.cdgo_crgo = c.cdgo_crgo AND r.cdgo_estdo_trmte != '3' THEN 1 ELSE 0 END), 0) AS sin_finalizar
            FROM {$s}p_crgos c
            LEFT JOIN {$s}r_crgos_lgin rl ON c.cdgo_crgo = rl.cdgo_crgo AND rl.in_esta = '1'
            LEFT JOIN {$s}p_lgin l ON rl.cdgo_lgin = l.cdgo_lgin
            LEFT JOIN {$s}p_usrios u ON l.cdgo_usrios = u.cdgo_usrios
            LEFT JOIN {$s}r_trmtes_dcmntos r ON r.cdgo_crgo_en = c.cdgo_crgo AND r.in_esta = '1'
            LEFT JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            LEFT JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte AND p.fe_ingr_trmte BETWEEN ? AND ?
            WHERE c.in_esta = '1'
              AND (? = 0 OR c.cdgo_area = ?)
            GROUP BY c.cdgo_crgo, c.de_crgo
        ";

        $rows = $this->db->select($sql, [$from, $to, $areaId, $areaId]);
        foreach ($rows as $row) {
            $row->id = (int) $row->cdgo_crgo;
            $row->nombre = (string) $row->de_area;
            $row->usuarios = (string) ($row->usuarios ?? '');
            $row->digitalizar = (int) $row->digitalizar;
            $row->por_recibir = (int) $row->por_recibir;
            $row->recibidos = (int) $row->recibidos;
            $row->derivados = (int) $row->derivados;
            $row->finalizados = (int) $row->finalizados;
            $row->criticos = (int) $row->criticos;
            $row->sin_finalizar = (int) $row->sin_finalizar;
            $row->total_doc = $row->digitalizar + $row->por_recibir + $row->recibidos + $row->derivados + $row->finalizados;
            $row->porcentaje = $row->total_doc > 0
                ? round((($row->derivados + $row->finalizados) / $row->total_doc) * 100, 2)
                : 0.0;
        }

        $rows = array_values(array_filter($rows, fn ($row) => $row->total_doc > 0 || $row->sin_finalizar > 0));

        usort($rows, function ($a, $b) {
            if ($b->porcentaje !== $a->porcentaje) {
                return $b->porcentaje <=> $a->porcentaje;
            }
            return $b->total_doc <=> $a->total_doc;
        });

        return $rows;
    }

    public function obtenerReporteGeneralDetalleBandeja(string $tipo, int $id, string $categoria, string $desde, string $hasta, int $companiaId): array
    {
        $s = $this->schema;
        $from = $desde . ' 00:01';
        $to = $hasta . ' 23:59';

        if ($tipo === 'area') {
            $query = $this->db->table($s . 'r_trmtes_dcmntos as r')
                ->join($s . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
                ->join($s . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
                ->leftJoin($s . 'p_crgos as c_sa', 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
                ->leftJoin($s . 'a_areas as a_sa', 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
                ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
                ->join($s . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select([
                    'p.cdgo_dto_trmte',
                    'p.nu_expe_todo',
                    'u.no_crto',
                    'p.fe_ingr_trmte',
                    'p.de_obse_trmte',
                    'r.fe_rcbdo_trmte',
                    'r.cdgo_trmte_dcmnto',
                ])
                ->where('r.in_esta', '1')
                ->where('p.fe_ingr_trmte', '>=', $from)
                ->where('p.fe_ingr_trmte', '<=', $to);

            match ($categoria) {
                'digitalizar' => $query->where(fn ($q) => $q->whereNull('a_sa.cdgo_area')->orWhere('a_sa.cdgo_area', '<>', $id))->where('a_en.cdgo_area', $id)->whereIn('r.cdgo_estdo_trmte', ['5']),
                'por_recibir' => $query->where(fn ($q) => $q->whereNull('a_sa.cdgo_area')->orWhere('a_sa.cdgo_area', '<>', $id))->where('a_en.cdgo_area', $id)->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']),
                'recibidos' => $query->where('a_sa.cdgo_area', $id)->where('a_en.cdgo_area', $id)->whereNotIn('r.cdgo_estdo_trmte', ['3', '26', '27']),
                'derivados' => $query->where('a_sa.cdgo_area', $id)->where('a_en.cdgo_area', '<>', $id),
                'finalizados' => $query->where('a_sa.cdgo_area', $id)->where('a_en.cdgo_area', $id)->whereIn('r.cdgo_estdo_trmte', ['3']),
                default => $query->whereRaw('1 = 0'),
            };

            $rows = $query->orderByDesc('p.cdgo_dto_trmte')->get();
            $adjuntos = $this->primerosAdjuntosReporte($rows->pluck('cdgo_dto_trmte')->all());

            return $rows->map(function ($row) use ($adjuntos) {
                $nombre = (string) ($row->no_crto ?? '');
                $obs = (string) ($row->de_obse_trmte ?? '');
                return [
                    'id' => (int) $row->cdgo_dto_trmte,
                    'expediente' => $row->nu_expe_todo,
                    'solicitante' => $nombre,
                    'solicitante_corto' => mb_strlen($nombre, 'UTF-8') > 35 ? mb_substr($nombre, 0, 35, 'UTF-8') : $nombre,
                    'fecha' => $row->fe_ingr_trmte ? \Carbon\Carbon::parse($row->fe_ingr_trmte)->format('d/m/Y H:i') : '',
                    'fecha_ultima' => $row->fe_rcbdo_trmte ? \Carbon\Carbon::parse($row->fe_rcbdo_trmte)->format('d/m/Y H:i') : '',
                    'descripcion' => $obs,
                    'adjunto' => $adjuntos[(int) $row->cdgo_dto_trmte] ?? '',
                ];
            })->all();
        }

        $query = $this->baseSearchQueryReporte()
            ->whereBetween('p.fe_ingr_trmte', [$desde . ' 00:01:00', $hasta . ' 23:59:00']);

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        match ($categoria) {
            'por_recibir' => $query->where('c_en.cdgo_crgo', $id)->where(fn ($q) => $q->where('c_sa.cdgo_crgo', '<>', $id)->orWhereNull('c_sa.cdgo_crgo'))->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']),
            'recibidos' => $query->where('c_en.cdgo_crgo', $id)->where('c_sa.cdgo_crgo', $id)->whereNotIn('r.cdgo_estdo_trmte', ['3', '26', '27']),
            'derivados' => $query->where('c_sa.cdgo_crgo', $id)->where('c_en.cdgo_crgo', '<>', $id),
            'finalizados' => $query->where('c_en.cdgo_crgo', $id)->where('c_sa.cdgo_crgo', $id)->whereIn('r.cdgo_estdo_trmte', ['3']),
            'sin_finalizar' => $query->where('c_en.cdgo_crgo', $id)->where('r.cdgo_estdo_trmte', '<>', '3'),
            default => null,
        };

        return $query->orderByDesc('p.cdgo_dto_trmte')->limit(2000)->get()->all();
    }

    private function primerosAdjuntosReporte(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $s = $this->schema;
        $rows = $this->db->select("
            SELECT DISTINCT ON (cdgo_dto_trmte) cdgo_dto_trmte, de_urls_imag
            FROM {$s}r_trmtes_dcmntos
            WHERE cdgo_dto_trmte IN (" . implode(',', $ids) . ")
              AND de_urls_imag IS NOT NULL
              AND de_urls_imag <> ''
            ORDER BY cdgo_dto_trmte, cdgo_trmte_dcmnto ASC
        ");

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->cdgo_dto_trmte] = (string) $row->de_urls_imag;
        }

        return $out;
    }

    public function obtenerDetalleReporteGeneral(int $id, int $companiaId): ?array
    {
        $row = $this->baseSearchQueryReporte()->where('p.cdgo_dto_trmte', $id)->first();
        return $row ? (array) $row : null;
    }

    public function obtenerReporteGeneralParaImprimir(array $filtros, int $companiaId, ?array $selectedIds = null, int $limit = 1000): array
    {
        $query = $this->baseSearchQueryReporte();
        $this->aplicarFiltrosReporteGeneral($query, $filtros, $companiaId);
        if ($selectedIds !== null && count($selectedIds) > 0) {
            $query->whereIn('p.cdgo_dto_trmte', $selectedIds);
        }

        return $query->orderByDesc('p.cdgo_dto_trmte')
            ->limit($limit)
            ->get()
            ->all();
    }

    // --- 9. Segi Ver Expedi ---

    public function obtenerEstadosDelAreaSeguimiento(int $cargoId): array
    {
        try {
            $s = $this->schema;
            if ($cargoId <= 0) {
                return [];
            }

            $areaId = (int) $this->db->table($s . 'p_crgos')
                ->where('cdgo_crgo', $cargoId)
                ->value('cdgo_area');

            if ($areaId <= 0) {
                return [];
            }

            return $this->db->table($s . 'r_stad_aras as r')
                ->join($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
                ->where('r.cdgo_area', $areaId)
                ->orderBy('e.dc_estdo_trmte')
                ->get(['e.cdgo_estdo_trmte', 'e.dc_estdo_trmte'])
                ->map(fn ($row) => [
                    'id' => (string) $row->cdgo_estdo_trmte,
                    'nombre' => (string) $row->dc_estdo_trmte,
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function obtenerDiasFestivos(): array
    {
        $cache = [];
        try {
            $rows = $this->db->table($this->schema . 'a_dias_fstvos')
                ->where('in_esta', '1')
                ->get(['fe_dias_fstvos']);
            foreach ($rows as $row) {
                if ($row->fe_dias_fstvos) {
                    $cache[\Carbon\Carbon::parse($row->fe_dias_fstvos)->toDateString()] = true;
                }
            }
        } catch (\Throwable) {}

        return $cache;
    }

    public function buscarSeguimientoExpedientes(array $filtros, int $cargoId, int $page, int $perPage, int $companiaId): array
    {
        $s = $this->schema;
        $query = $this->db->table($s . 'p_dto_trmtes as p')
            ->join($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p_usrios.no_crto',
                'r.fe_rcbdo_trmte',
                'a_estdos_trmtes.dc_estdo_trmte',
                'p.de_obse_trmte',
                'p.in_encu_tram',
                'p.fe_ingr_trmte',
                'p.cdgo_docmnto_trmte',
                'r.cdgo_trmte_dcmnto',
                'a_estdos_trmtes.cdgo_estdo_trmte',
                'a_docmntos_trmtes.nu_tiem_sile',
            ])
            ->where('r.cdgo_crgo_en', $cargoId)
            ->where('r.cdgo_crgo_sa', $cargoId)
            ->where('r.in_esta', '1')
            ->where('r.cdgo_estdo_trmte', '<>', '3');

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        $palabra = trim((string) ($filtros['palabra'] ?? ''));
        if ($palabra !== '') {
            $campo = (string) ($filtros['campo'] ?? 'p_dto_trmtes.nu_expe_todo');
            $allowed = [
                'p_dto_trmtes.nu_expe_todo',
                'p_usrios.no_crto',
                'a_tpo_trmtes.dc_tpo_trmte',
                'p_dto_trmtes.de_obse_todo',
            ];
            if (! in_array($campo, $allowed, true)) {
                $campo = 'p_dto_trmtes.nu_expe_todo';
            }
            $col = match ($campo) {
                'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
                'p_usrios.no_crto' => 'p_usrios.no_crto',
                'a_tpo_trmtes.dc_tpo_trmte' => 'a_tpo_trmtes.dc_tpo_trmte',
                'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
            };
            $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $palabra . '%']);
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $rows = $query->orderByDesc('p.cdgo_dto_trmte')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'rows' => $rows,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    public function obtenerHistoricoSeguimiento(int $expedienteId, int $companiaId): array
    {
        $s = $this->schema;
        $expediente = (string) $this->db->table($s . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $expedienteId)
            ->value('nu_expe_todo');

        $query = $this->db->table($s . 'r_trmtes_dcmntos as r')
            ->join($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->join($s . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($s . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($s . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select([
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                'e.cdgo_estdo_trmte',
                'r.de_obse',
                'a.de_area',
                'r.de_urls_imag',
                'p.fe_ingr_trmte',
                'r.in_esta',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'p.in_docu_trmte',
                $this->db->raw('COALESCE(u.no_crto, l.de_lgin) as usuario'),
            ])
            ->where('p.cdgo_dto_trmte', $expedienteId)
            ->orderByDesc('r.cdgo_trmte_dcmnto');

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        return [
            'expediente' => $expediente,
            'rows' => $query->get()->all(),
        ];
    }

    public function cambiarEstadoSeguimiento(int $idTram, int $doc, string $esta, string $estAct, string $observacion, int $cargoId, int $userId, string $ip, int $companiaId): void
    {
        $s = $this->schema;
        $estadoActual = (string) $this->db->table($s . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $doc)
            ->value('cdgo_estdo_trmte');

        if ($estadoActual === '' || $estadoActual === $esta) {
            throw new \RuntimeException('El estado ya no coincide con el movimiento actual.');
        }

        $this->db->transaction(function () use ($idTram, $esta, $estAct, $observacion, $cargoId, $userId, $ip, $s) {
            if (in_array($esta, ['5', '17'], true)) {
                $row = $this->db->table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->first();
                if ($row) {
                    $this->db->table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->update(['fe_inic' => now(), 'in_esta' => '1']);
                } else {
                    $this->db->table($s . 'h_slncio')->insert(['cdgo_dto_trmte' => $idTram, 'nu_dias' => null, 'fe_inic' => now(), 'in_esta' => '1']);
                }
            }
            if ($esta === '7' || in_array($estAct, ['5', '17'], true)) {
                $row = $this->db->table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->first();
                if ($row && (string) $row->in_esta === '1' && $row->fe_inic) {
                    $dias = (int) round((\Carbon\Carbon::today()->getTimestamp() - \Carbon\Carbon::parse($row->fe_inic)->startOfDay()->getTimestamp()) / 86400);
                    $total = max(0, $dias) + (int) ($row->nu_dias ?? 0);
                    $this->db->table($s . 'h_slncio')->where('cdgo_dto_trmte', $idTram)->update(['nu_dias' => $total, 'in_esta' => '2']);
                }
            }

            $this->db->table($s . 'r_trmtes_dcmntos')
                ->where('cdgo_dto_trmte', $idTram)
                ->update(['in_esta' => '0']);

            $inEsta = $esta === '3' ? '0' : '1';
            $this->db->table($s . 'r_trmtes_dcmntos')->insert([
                'cdgo_crgo_sa' => $cargoId,
                'cdgo_crgo_en' => $cargoId,
                'cdgo_dto_trmte' => $idTram,
                'cdgo_estdo_trmte' => $esta,
                'fe_rcbdo_trmte' => now(),
                'de_obse' => mb_strtoupper($observacion, 'UTF-8'),
                'in_esta' => $inEsta,
                'de_urls_imag' => '',
                'cdgo_lgin' => $userId,
                'nu_foli_anex' => null,
            ]);

            try {
                $this->db->table($s . 'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => mb_strtoupper($observacion, 'UTF-8'),
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            } catch (\Throwable) {}
        });
    }

    // --- 10. V2 Lista Reporte Area Cargo Externo ---

    public function obtenerV2EvolucionExterno(int $areaId, string $desde, string $hasta): array
    {
        $s = $this->schema;
        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        if ($areaId <= 0) {
            return [
                'labels' => [],
                'finalizados' => [],
                'pendientes' => [],
                'total_fin' => 0,
                'avg_fin' => 0,
                'trend_pct' => 0,
                'avg_time' => 1.5,
            ];
        }

        $sqlEvo = "
            SELECT
                EXTRACT(YEAR FROM p.fe_ingr_trmte)::int  AS anio,
                EXTRACT(MONTH FROM p.fe_ingr_trmte)::int AS mes,
                SUM(CASE WHEN r.cdgo_estdo_trmte = '3' THEN 1 ELSE 0 END) AS finalizados,
                SUM(CASE WHEN r.cdgo_estdo_trmte IN ('1','2') THEN 1 ELSE 0 END) AS pendientes
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE c.cdgo_area = ?
              AND r.in_esta = '1'
              AND p.fe_ingr_trmte >= ?
              AND p.fe_ingr_trmte <= ?
            GROUP BY anio, mes
            ORDER BY anio ASC, mes ASC
            LIMIT 19
        ";

        $resEvo = $this->db->select($sqlEvo, [$areaId, $desde, $hasta]);

        $labels = [];
        $finalizados = [];
        $pendientes = [];
        $total_fin = 0;

        foreach ($resEvo as $row) {
            $idx = (int) $row->mes - 1;
            $labels[] = $meses[$idx] . ' ' . substr((string) $row->anio, 2);
            $finalizados[] = (int) $row->finalizados;
            $pendientes[] = (int) $row->pendientes;
            $total_fin += (int) $row->finalizados;
        }

        $avg_fin = count($finalizados) > 0 ? round($total_fin / count($finalizados), 1) : 0;
        $last_val = end($finalizados);
        $prev_val = prev($finalizados);
        $trend_pct = 0;
        if ($prev_val !== false && $prev_val > 0) {
            $trend_pct = round((($last_val - $prev_val) / $prev_val) * 100);
        }

        $sqlTime = "
            SELECT AVG(EXTRACT(DAY FROM (r.fe_rcbdo_trmte - p.fe_ingr_trmte))) as avg_time
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE c.cdgo_area = ?
              AND r.cdgo_estdo_trmte = '3'
              AND r.in_esta = '1'
              AND r.fe_rcbdo_trmte IS NOT NULL
              AND p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= ?
        ";
        $resTime = $this->db->selectOne($sqlTime, [$areaId, $desde, $hasta]);
        $avg_time = $resTime && $resTime->avg_time !== null ? round((float) $resTime->avg_time, 1) : 1.5;

        return [
            'labels' => $labels,
            'finalizados' => $finalizados,
            'pendientes' => $pendientes,
            'total_fin' => $total_fin,
            'avg_fin' => $avg_fin,
            'trend_pct' => $trend_pct,
            'avg_time' => $avg_time,
        ];
    }

    public function obtenerCargosAreaExterno(int $areaId): array
    {
        if ($areaId <= 0) {
            return [];
        }

        return $this->db->table($this->schema . 'p_crgos')
            ->where('in_esta', '1')
            ->where('cdgo_area', $areaId)
            ->orderBy('de_crgo')
            ->get(['cdgo_crgo', 'de_crgo'])
            ->all();
    }

    public function contarReporteCargoExterno(int $cargoId, string $desde, string $hasta, string $tipo): int
    {
        $s = $this->schema;
        $q = $this->db->table("{$s}r_trmtes_dcmntos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dto_trmtes as p", 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingr_trmte', [$desde, $hasta]);

        return match ($tipo) {
            'entrada' => $q->where('c_en.cdgo_crgo', $cargoId)->where(fn ($sub) => $sub->whereNull('c_sa.cdgo_crgo')->orWhere('c_sa.cdgo_crgo', '!=', $cargoId))->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27'])->count(),
            'recibidos' => $q->where('c_en.cdgo_crgo', $cargoId)->where('c_sa.cdgo_crgo', $cargoId)->whereNotIn('r.cdgo_estdo_trmte', ['3', '26', '27'])->count(),
            'salida' => $q->where('c_sa.cdgo_crgo', $cargoId)->where('c_en.cdgo_crgo', '!=', $cargoId)->count(),
            'finalizado' => $q->where('c_en.cdgo_crgo', $cargoId)->where('c_sa.cdgo_crgo', $cargoId)->whereIn('r.cdgo_estdo_trmte', ['3'])->count(),
            default => 0,
        };
    }

    public function obtenerTramitesReporteCargoDetalleExterno(int $cargoId, string $desde, string $hasta, string $tipo, int $page, int $porPagina): array
    {
        return [];
    }

    public function obtenerMetricasCargosV2(array $cargos, string $desde, string $hasta): array
    {
        $s = $this->schema;
        $rows_data = [];
        $sum_entregado = 0;
        $sum_recibido = 0;
        $sum_salida = 0;
        $sum_finalizado = 0;
        $sum_total_doc = 0;
        $sum_total_ges = 0;

        foreach ($cargos as $cargo) {
            $cdgo = (int) $cargo->cdgo_crgo;

            $con_entrada = $this->contarReporteCargoExterno($cdgo, $desde, $hasta, 'entrada');
            $con_recibido = $this->contarReporteCargoExterno($cdgo, $desde, $hasta, 'recibidos');
            $con_salida = $this->contarReporteCargoExterno($cdgo, $desde, $hasta, 'salida');
            $con_finalizado = $this->contarReporteCargoExterno($cdgo, $desde, $hasta, 'finalizado');

            $total = $con_entrada + $con_recibido + $con_salida + $con_finalizado;
            $gestionados = $con_salida + $con_finalizado;
            $pendientes = $con_entrada + $con_recibido;

            $porcentaje = $total > 0 ? ($gestionados / $total) * 100 : 100;

            if ($pendientes == 0) {
                $porcentaje = 100;
                $status_text = 'EXCELENTE';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 80) {
                $status_text = 'AL DÍA';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 50) {
                $status_text = 'REVISAR';
                $status_color = 'var(--warning)';
            } else {
                $status_text = 'CRÍTICO';
                $status_color = 'var(--danger)';
            }

            $sum_entregado += $con_entrada;
            $sum_recibido += $con_recibido;
            $sum_salida += $con_salida;
            $sum_finalizado += $con_finalizado;
            $sum_total_doc += $total;
            $sum_total_ges += $gestionados;

            $usuarios = $this->db->table("{$s}r_crgos_lgin as rcl")
                ->join("{$s}p_lgin as l", 'rcl.cdgo_lgin', '=', 'l.cdgo_lgin')
                ->join("{$s}p_usrios as u", 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->where('rcl.cdgo_crgo', $cdgo)
                ->where('l.in_esta', '1')
                ->orderBy('u.no_crto')
                ->pluck('u.no_crto')
                ->map(fn ($n) => (string) $n)
                ->all();

            $iniciales = array_map(function ($nombre) {
                $parts = preg_split('/\s+/', trim((string) $nombre)) ?: [];
                $ini = '';
                foreach ($parts as $p) {
                    if ($p !== '') {
                        $ini .= mb_substr($p, 0, 1);
                    }
                }
                return mb_strtoupper($ini);
            }, $usuarios);

            $rows_data[] = [
                'id' => $cdgo,
                'cargo' => (string) $cargo->de_crgo,
                'usuarios' => $usuarios,
                'iniciales' => $iniciales,
                'entrada' => $con_entrada,
                'recibido' => $con_recibido,
                'salida' => $con_salida,
                'finalizado' => $con_finalizado,
                'total' => $total,
                'gestionados' => $gestionados,
                'porcentaje' => round($porcentaje, 1),
                'status_text' => $status_text,
                'status_color' => $status_color,
            ];
        }

        usort($rows_data, function ($a, $b) {
            $aTiene = $a['total'] > 0;
            $bTiene = $b['total'] > 0;
            if ($aTiene !== $bTiene) {
                return $bTiene <=> $aTiene;
            }
            $pctA = $a['total'] > 0 ? ($a['gestionados'] / $a['total']) * 100 : 0;
            $pctB = $b['total'] > 0 ? ($b['gestionados'] / $b['total']) * 100 : 0;
            return $pctB <=> $pctA;
        });

        $sum_gestionado_global = $sum_salida + $sum_finalizado;
        $pct_trabajo = $sum_total_doc > 0 ? round(($sum_gestionado_global / $sum_total_doc) * 100, 1) : 0;

        if ($pct_trabajo >= 80) {
            $pct_color = '#10b981';
            $pct_bg = 'rgba(16,185,129,0.1)';
        } elseif ($pct_trabajo >= 50) {
            $pct_color = '#f59e0b';
            $pct_bg = 'rgba(245,158,11,0.1)';
        } else {
            $pct_color = '#ef4444';
            $pct_bg = 'rgba(239,68,68,0.1)';
        }

        $totales = [
            'sum_entregado' => $sum_entregado,
            'sum_recibido' => $sum_recibido,
            'sum_salida' => $sum_salida,
            'sum_finalizado' => $sum_finalizado,
            'sum_total_doc' => $sum_total_doc,
            'sum_total_ges' => $sum_total_ges,
            'pct_trabajo' => $pct_trabajo,
            'pct_color' => $pct_color,
            'pct_bg' => $pct_bg,
        ];

        return [
            'rows_data' => $rows_data,
            'totales' => $totales,
        ];
    }

    public function obtenerDetalleBandejaCargoV2(int $cargoId, int $tipoBandeja, string $desde, string $hasta): array
    {
        $s = $this->schema;
        $baseQuery = $this->db->table("{$s}r_trmtes_dcmntos as r")
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'u.no_crto',
                'r.fe_rcbdo_trmte',
                'p.fe_ingr_trmte',
            ])
            ->join("{$s}p_dto_trmtes as p", 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_usrios as u", 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->join("{$s}a_estdos_trmtes as e", 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingr_trmte', [$desde, $hasta]);

        if ($tipoBandeja === 1) {
            $baseQuery->where(function ($q) use ($cargoId) {
                $q->where(function ($q2) use ($cargoId) {
                    $q2->where('r.cdgo_crgo_en', $cargoId)
                        ->where('r.cdgo_crgo_sa', '!=', $cargoId)
                        ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29']);
                })->orWhere(function ($q2) use ($cargoId) {
                    $q2->where('r.cdgo_crgo_en', $cargoId)
                        ->whereIn('r.cdgo_estdo_trmte', ['25', '51']);
                });
            });
        } elseif ($tipoBandeja === 2) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['3']);
        } elseif ($tipoBandeja === 3) {
            $baseQuery->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', '!=', $cargoId);
        } elseif ($tipoBandeja === 4) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId)
                ->whereIn('r.cdgo_estdo_trmte', ['3']);
        }

        $tramites = $baseQuery->orderByDesc('p.fe_ingr_trmte')->get();
        $ids = $tramites->pluck('cdgo_dto_trmte')->filter()->unique()->all();

        $adjuntos = [];
        if (! empty($ids)) {
            $rowsAdj = $this->db->table("{$s}r_trmtes_dcmntos")
                ->select('cdgo_dto_trmte', 'de_urls_imag', 'cdgo_trmte_dcmnto')
                ->whereIn('cdgo_dto_trmte', $ids)
                ->whereNotNull('de_urls_imag')
                ->where('de_urls_imag', '!=', '')
                ->orderBy('cdgo_trmte_dcmnto')
                ->get();
            foreach ($rowsAdj as $radj) {
                $idt = (int) $radj->cdgo_dto_trmte;
                if (! isset($adjuntos[$idt])) {
                    $adjuntos[$idt] = (string) $radj->de_urls_imag;
                }
            }
        }

        $rutas = [];
        if (! empty($ids)) {
            $rowsRutas = $this->db->table("{$s}r_trmtes_dcmntos as r")
                ->join("{$s}p_crgos as c", 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
                ->join("{$s}a_areas as a", 'c.cdgo_area', '=', 'a.cdgo_area')
                ->whereIn('r.cdgo_dto_trmte', $ids)
                ->orderBy('r.cdgo_dto_trmte')
                ->orderBy('r.cdgo_trmte_dcmnto')
                ->get(['r.cdgo_dto_trmte', 'a.dc_area']);
            foreach ($rowsRutas as $rr) {
                $idt = (int) $rr->cdgo_dto_trmte;
                $area = (string) ($rr->dc_area ?? '');
                if ($area === '') continue;
                if (! isset($rutas[$idt])) $rutas[$idt] = [];
                $last = empty($rutas[$idt]) ? '' : end($rutas[$idt]);
                if ($area !== $last) $rutas[$idt][] = $area;
            }
        }

        return [
            'tramites' => $tramites,
            'adjuntos' => $adjuntos,
            'rutas' => $rutas,
        ];
    }

    public function obtenerHistoricoMovimientosV2(int $expedienteId, int $companiaId): array
    {
        $s = $this->schema;
        $expediente = (string) $this->db->table($s . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $expedienteId)
            ->value('nu_expe_todo');

        $query = $this->db->table($s . 'r_trmtes_dcmntos as r')
            ->join($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->join($s . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($s . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($s . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select([
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                'e.cdgo_estdo_trmte',
                'r.de_obse',
                'a.de_area',
                'r.de_urls_imag',
                'p.fe_ingr_trmte',
                'r.in_esta',
                $this->db->raw('COALESCE(u.no_crto, l.de_lgin) as usuario'),
            ])
            ->where('p.cdgo_dto_trmte', $expedienteId)
            ->orderByDesc('r.cdgo_trmte_dcmnto');

        if ($companiaId > 0) {
            $query->where('p.cdgo_cmpnia', $companiaId);
        }

        return [
            'expediente' => $expediente,
            'rows' => $query->get()->all(),
        ];
    }

    private function normalizarFechaHelper($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d');
            }

            return \Carbon\Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}