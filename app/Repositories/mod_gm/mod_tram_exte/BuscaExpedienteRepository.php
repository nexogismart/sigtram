<?php

declare(strict_types=1);

namespace App\Repositories\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;


use App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use RuntimeException;

class BuscaExpedienteRepository implements BuscaExpedienteRepositoryInterface
{
    private string $schema;
    private CorrelativoExpedienteLock $correlativoLock;

    public function __construct(
        private readonly DatabaseManager $db,
        ?CorrelativoExpedienteLock $correlativoLock = null
    ) {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $this->correlativoLock = $correlativoLock ?? new CorrelativoExpedienteLock($this->db);
    }

    /**
     * {@inheritdoc}
     */
    public function existeExpediente(int $idTram, int $companiaId): bool
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return false;
        }

        return $this->db->table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_cmpnia', $companiaId)
            ->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function buscarExpediente(int $idTram, int $companiaId): ?object
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return null;
        }

        return $this->db->table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_cmpnia', $companiaId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerFichaExpediente(int $idTram, int $companiaId): ?array
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return null;
        }

        $tramite = $this->db->table($this->schema . 'p_dto_trmtes as p_dto_trmtes')
            ->join($this->schema . 'p_usrios as p_usrios', 'p_dto_trmtes.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($this->schema . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($this->schema . 'a_areas as a_areas', 'p_dto_trmtes.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($this->schema . 'a_anios as a_anios', 'p_dto_trmtes.cdgo_anio', '=', 'a_anios.cdgo_anio')
            ->leftJoin($this->schema . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p_dto_trmtes.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->leftJoin($this->schema . 'h_usrios_crreo as h_usrios_crreo', 'p_dto_trmtes.cdgo_usrios_crreo', '=', 'h_usrios_crreo.cdgo_usrios_crreo')
            ->select(
                'p_dto_trmtes.cdgo_dto_trmte',
                'a_tpo_trmtes.cdgo_tpo_trmte',
                'a_tpo_trmtes.de_tpo_trmte',
                'p_dto_trmtes.nu_docu_trmte',
                'p_dto_trmtes.cdgo_usrios',
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.nu_foli_trmte',
                'p_dto_trmtes.fe_ingr_trmte',
                'p_dto_trmtes.fe_limi_trmte',
                'p_dto_trmtes.cdgo_docmnto_trmte',
                'p_dto_trmtes.in_sile_admi',
                'p_dto_trmtes.de_obse_trmte',
                'p_dto_trmtes.cdgo_area',
                'a_docmntos_trmtes.nu_tiem_sile',
                'p_usrios.de_mail',
                'p_usrios.in_tipo_pers',
                'h_usrios_crreo.de_mail as de_usrios_mail'
            )
            ->where('p_dto_trmtes.cdgo_dto_trmte', $idTram)
            ->where('p_dto_trmtes.cdgo_cmpnia', $companiaId)
            ->first();

        if (!$tramite) {
            return null;
        }

        $persona = $this->obtenerPersona((int) $tramite->cdgo_usrios, (string) ($tramite->in_tipo_pers ?? ''));

        $servicio = null;
        $tipoTramiteServ = null;
        if (!empty($tramite->cdgo_docmnto_trmte)) {
            $servRow = $this->db->table($this->schema . 'a_docmntos_trmtes as a_docmntos_trmtes')
                ->join($this->schema . 'a_srvcio_areas as a_srvcio_areas', 'a_docmntos_trmtes.cdgo_srvcio_area', '=', 'a_srvcio_areas.cdgo_srvcio_area')
                ->select('a_srvcio_areas.de_srvcio', 'a_docmntos_trmtes.de_docmnto_trmte')
                ->where('a_docmntos_trmtes.cdgo_docmnto_trmte', (int) $tramite->cdgo_docmnto_trmte)
                ->first();

            if ($servRow) {
                $servicio = $servRow->de_srvcio;
                $tipoTramiteServ = $servRow->de_docmnto_trmte;
            }
        }

        $requisitos = $this->db->table($this->schema . 'r_dtos_rqtos as r_dtos_rqtos')
            ->join($this->schema . 'h_rqtos_trmtes as h_rqtos_trmtes', 'r_dtos_rqtos.cdgo_rqto_trmte', '=', 'h_rqtos_trmtes.cdgo_rqto_trmte')
            ->select(
                'r_dtos_rqtos.in_pres',
                'h_rqtos_trmtes.dc_rqto_trmte',
                'r_dtos_rqtos.cdgo_rqto_trmte',
                'h_rqtos_trmtes.de_rqto_trmte'
            )
            ->where('r_dtos_rqtos.cdgo_dto_trmte', $idTram)
            ->get();

        $cdgoEstado = $this->db->table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('in_esta', '1')
            ->value('cdgo_estdo_trmte');

        $cdgoTpo = (string) ($tramite->cdgo_tpo_trmte ?? '');
        if ($cdgoTpo === '17') {
            $tituloPrefijo = 'Trámite Simple Nº: ';
        } elseif ($cdgoTpo === '22') {
            $tituloPrefijo = 'Expediente Nº: ';
        } else {
            $tituloPrefijo = 'N° de Tramite: ';
        }

        $numeroExpediente = $this->formatNumeroExpediente((string) ($tramite->nu_expe_todo ?? ''));

        $fechaIngreso = '';
        if ($tramite->fe_ingr_trmte) {
            $fechaIngreso = Carbon::parse($tramite->fe_ingr_trmte)
                ->timezone('America/Lima')
                ->format('d/m/Y H:i');
        }

        $correo = $tramite->de_usrios_mail
            ? strtolower((string) $tramite->de_usrios_mail)
            : (string) ($tramite->de_mail ?? '');

        return [
            'tramite' => $tramite,
            'persona' => $persona,
            'servicio' => $servicio,
            'tipoTramiteServ' => $tipoTramiteServ,
            'requisitos' => $requisitos,
            'cdgoEstado' => (string) ($cdgoEstado ?? ''),
            'tituloPrefijo' => $tituloPrefijo,
            'numeroExpediente' => $numeroExpediente,
            'fechaIngreso' => $fechaIngreso,
            'correo' => $correo,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerPersona(int $idUsua, string $tipoPers = ''): ?object
    {
        if ($tipoPers === '') {
            $tipoPers = (string) $this->db->table($this->schema . 'p_usrios')
                ->where('cdgo_usrios', $idUsua)
                ->value('in_tipo_pers');
        }

        if ($tipoPers === '0') {
            $row = $this->db->table($this->schema . 'h_usrios_ntral as h')
                ->join($this->schema . 'a_dcmntos_iden as d', 'h.cdgo_dcmntos_iden', '=', 'd.cdgo_dcmntos_iden')
                ->join($this->schema . 'p_usrios as u', 'h.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select(
                    'd.dc_dcmntos_iden as tipo_doc',
                    'u.nu_docu',
                    'h.no_usrio as nombres',
                    'h.ap_pate',
                    'h.ap_mate',
                    'u.de_dire',
                    'u.nu_tele'
                )
                ->where('u.cdgo_usrios', $idUsua)
                ->first();

            return (object) [
                'tipo' => '0',
                'data' => $row,
            ];
        }

        if ($tipoPers === '1') {
            $row = $this->db->table($this->schema . 'h_usrios_jrid as h')
                ->join($this->schema . 'p_usrios as u', 'h.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select(
                    'u.nu_docu as ruc',
                    'h.rz_soci',
                    'u.de_dire',
                    'u.nu_tele'
                )
                ->where('u.cdgo_usrios', $idUsua)
                ->first();

            return (object) [
                'tipo' => '1',
                'data' => $row,
            ];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerExpedienteParaActualizar(int $idTram, int $companiaId, bool $lock = false): ?object
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return null;
        }

        $query = $this->db->table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_cmpnia', $companiaId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function actualizarDatosExpediente(int $idTram, int $companiaId, string $deObse, string $nuevoTodo, array $requisitos): void
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new InvalidArgumentException('Identificador de expediente y compañía deben ser positivos.');
        }

        $affected = $this->db->table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_cmpnia', $companiaId)
            ->update([
                'de_obse_trmte' => $deObse,
                'de_obse_todo' => $nuevoTodo,
            ]);

        if ($affected === 0) {
            throw new RuntimeException("No se pudo actualizar el expediente {$idTram}: no existe o pertenece a otra compañía.");
        }

        foreach ($requisitos as $nuRequ => $elRequ) {
            $this->db->table($this->schema . 'r_dtos_rqtos')
                ->where('cdgo_dto_trmte', $idTram)
                ->where('cdgo_rqto_trmte', (int) $nuRequ)
                ->update(['in_pres' => (string) $elRequ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientoParaAnexo(int $idTram, int $companiaId, bool $lock = false): ?object
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return null;
        }

        $query = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('r.cdgo_dto_trmte', $idTram)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('r.in_esta', '1');

        if ($lock) {
            $query->lockForUpdate();
        }

        $cargo = $query->select('r.cdgo_crgo_sa', 'r.cdgo_crgo_en', 'r.cdgo_trmte_dcmnto')
            ->first();

        if (!$cargo) {
            $fallbackQuery = $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
                ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
                ->where('r.cdgo_dto_trmte', $idTram)
                ->where('p.cdgo_cmpnia', $companiaId)
                ->orderByDesc('r.cdgo_trmte_dcmnto');

            if ($lock) {
                $fallbackQuery->lockForUpdate();
            }

            $cargo = $fallbackQuery->select('r.cdgo_crgo_sa', 'r.cdgo_crgo_en', 'r.cdgo_trmte_dcmnto')
                ->first();
        }

        return $cargo;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientosActivosParaBloqueo(int $idTram, int $companiaId): array
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return [];
        }

        return $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->where('r.cdgo_dto_trmte', $idTram)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('r.in_esta', '1')
            ->select('r.cdgo_trmte_dcmnto', 'r.cdgo_crgo_sa', 'r.cdgo_crgo_en', 'r.cdgo_estdo_trmte')
            ->lockForUpdate()
            ->get()
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function desactivarMovimientosActivos(
        int $idTram,
        int $companiaId,
        ?int $expectedCount = null,
        array $movimientoIds = []
    ): int {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new InvalidArgumentException('Identificador de expediente y compañía deben ser positivos.');
        }

        if ($expectedCount === 0) {
            throw new ConflictoConcurrenteTramiteException('Conflicto de concurrencia: no se encontraron movimientos activos para desactivar.');
        }

        $query = $this->db->table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('in_esta', '1')
            ->whereIn('cdgo_dto_trmte', function ($sub) use ($idTram, $companiaId) {
                $sub->select('cdgo_dto_trmte')
                    ->from($this->schema . 'p_dto_trmtes')
                    ->where('cdgo_dto_trmte', $idTram)
                    ->where('cdgo_cmpnia', $companiaId);
            });

        if (!empty($movimientoIds)) {
            $query->whereIn('cdgo_trmte_dcmnto', $movimientoIds);
        }

        $affected = $query->update(['in_esta' => '0']);

        if ($affected === 0) {
            throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia o expediente no encontrado: 0 movimientos activos actualizados para el trámite {$idTram}.");
        }

        if ($expectedCount !== null && $affected !== $expectedCount) {
            throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia: se esperaban {$expectedCount} movimientos activos afectados pero se actualizaron {$affected}.");
        }

        return $affected;
    }

    /**
     * {@inheritdoc}
     */
    public function insertarMovimiento(array $datos): int
    {
        return (int) $this->db->table($this->schema . 'r_trmtes_dcmntos')->insertGetId($datos, 'cdgo_trmte_dcmnto');
    }

    /**
     * {@inheritdoc}
     */
    public function actualizarAdjuntoMovimiento(int $movId, string $filename): void
    {
        $this->db->table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_trmte_dcmnto', $movId)
            ->update(['de_urls_imag' => $filename]);
    }

    /**
     * {@inheritdoc}
     */
    public function actualizarObservacionesExpediente(int $idTram, int $companiaId, string $nuevoTodo, string $deObse): void
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new InvalidArgumentException('Identificador de expediente y compañía deben ser positivos.');
        }

        $affected = $this->db->table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_cmpnia', $companiaId)
            ->update([
                'de_obse_todo' => $nuevoTodo,
                'de_obse_trmte' => $deObse,
            ]);

        if ($affected === 0) {
            throw new RuntimeException("No se pudo actualizar el expediente {$idTram}: no encontrado o pertenece a otra compañía.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registrarSeguimiento(int $idUsua, string $deSgtoLog, string $deIp): void
    {
        $this->db->table($this->schema . 'p_sgmto_log')->insert([
            'cdgo_lgin' => $idUsua,
            'de_sgto_log' => $deSgtoLog,
            'fe_sgto_log' => now(),
            'de_ip' => $deIp,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function listarVouchers(int $idTram, int $companiaId): array
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return [
                'expedienteExiste' => false,
                'anexos' => collect([]),
            ];
        }

        $expedienteExists = $this->db->table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_cmpnia', $companiaId)
            ->exists();

        if (!$expedienteExists) {
            return [
                'expedienteExiste' => false,
                'anexos' => collect([]),
            ];
        }

        $anexos = $this->db->table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTram)
            ->where('cdgo_estdo_trmte', 25)
            ->select('cdgo_trmte_dcmnto', 'fe_rcbdo_trmte')
            ->orderBy('cdgo_trmte_dcmnto')
            ->get();

        return [
            'expedienteExiste' => true,
            'anexos' => $anexos,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerVoucherAnexo(int $idMov, int $companiaId): ?object
    {
        if ($idMov <= 0 || $companiaId <= 0) {
            return null;
        }

        return $this->db->table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($this->schema . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->leftJoin($this->schema . 'p_crgos as c', 'r.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->leftJoin($this->schema . 'a_docmntos_trmtes as d', 'p.cdgo_docmnto_trmte', '=', 'd.cdgo_docmnto_trmte')
            ->select(
                'r.cdgo_trmte_dcmnto',
                'r.fe_rcbdo_trmte',
                'r.nu_foli_anex',
                'r.de_obse',
                'r.de_urls_imag',
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.cdgo_docmnto_trmte',
                'p.de_obse_trmte',
                'u.nu_docu',
                'u.no_crto',
                'u.de_dire',
                'u.nu_tele',
                'a.de_area',
                'd.de_docmnto_trmte'
            )
            ->where('r.cdgo_trmte_dcmnto', $idMov)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDatosGeneracionExpediente(int $idSubarea): array
    {
        $idArea = (int) ($this->db->table($this->schema . 'a_areas')->where('cdgo_area', $idSubarea)->value('cdgo_area_prim') ?? 0);
        if ($idArea <= 0) {
            $idArea = $idSubarea;
        }

        $todasAreas = $this->db->table($this->schema . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_area', '<>', 0)
            ->orderBy('de_area')
            ->get(['cdgo_area', 'de_area', 'dc_area', 'cdgo_area_prim']);

        $gerencias = $todasAreas
            ->filter(fn ($a) => (int) ($a->cdgo_area_prim ?? 0) === 0)
            ->values();

        if ($gerencias->isEmpty()) {
            $gerencias = $todasAreas;
        }

        $subgerencias = $todasAreas
            ->filter(fn ($a) => (int) ($a->cdgo_area_prim ?? 0) === $idArea)
            ->values();

        return [
            'idArea' => $idArea,
            'idSubarea' => $idSubarea,
            'gerencias' => $gerencias,
            'subgerencias' => $subgerencias,
            'todasAreas' => $todasAreas,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargoOrdenUnoArea(int $areaId): ?int
    {
        $cargoId = $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_area', $areaId)
            ->where('nu_orde', '1')
            ->where('in_esta', '1')
            ->orderBy('cdgo_crgo')
            ->value('cdgo_crgo');

        return $cargoId !== null ? (int) $cargoId : null;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerExpedienteVirtualConMovimiento(int $idTramVirtual, int $companiaId, bool $lock = false): array
    {
        if ($idTramVirtual <= 0 || $companiaId <= 0) {
            return [
                'virtual' => null,
                'movVirtual' => null,
            ];
        }

        $queryVirtual = $this->db->table($this->schema . 'p_dto_trmtes')
            ->where('cdgo_dto_trmte', $idTramVirtual)
            ->where('cdgo_cmpnia', $companiaId);

        if ($lock) {
            $queryVirtual->lockForUpdate();
        }

        $virtual = $queryVirtual->first();

        $queryMov = $this->db->table($this->schema . 'r_trmtes_dcmntos')
            ->where('cdgo_dto_trmte', $idTramVirtual)
            ->where('in_esta', '1');

        if ($lock) {
            $queryMov->lockForUpdate();
        }

        $movVirtual = $queryMov->first();

        return [
            'virtual' => $virtual,
            'movVirtual' => $movVirtual,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function resolverTipoTramiteActivoPorCodigo(string $codigo): object
    {
        $code = strtoupper(trim($codigo));
        if (!in_array($code, ['E', 'S'], true)) {
            throw new \InvalidArgumentException("Código de tipo de trámite inválido: '{$codigo}'. Solo se admite E o S.");
        }

        $registros = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('dc_tpo_trmte', $code)
            ->where('in_esta', '1')
            ->select(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta'])
            ->get();

        if ($registros->isEmpty()) {
            throw new \RuntimeException("No existe un tipo de trámite activo en el catálogo para el código '{$code}'.");
        }

        if ($registros->count() > 1) {
            throw new \RuntimeException("Se encontró más de un tipo de trámite activo en el catálogo para el código '{$code}'.");
        }

        $tipo = $registros->first();
        if ((int) $tipo->cdgo_tpo_trmte <= 0) {
            throw new \UnexpectedValueException("El ID del tipo de trámite para '{$code}' no es un entero positivo.");
        }

        return $tipo;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerPrefijoTipoTramite(int $tpoTrmtes): string
    {
        $prefijo = $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $tpoTrmtes)
            ->where('in_esta', '1')
            ->value('dc_tpo_trmte');

        if ($prefijo === null || trim((string) $prefijo) === '') {
            throw new \RuntimeException("No se encontró prefijo activo en catálogo para el tipo de trámite {$tpoTrmtes}.");
        }

        return trim((string) $prefijo);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDescripcionTipoTramite(int $tpoTrmtes): string
    {
        return (string) ($this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $tpoTrmtes)
            ->value('de_tpo_trmte') ?? '');
    }

    /**
     * Retorna la clave determinística para serialización PostgreSQL.
     */
    public function obtenerClaveSerializacion(int $currYear, int $tpoTrmtes = 0): int
    {
        return $this->correlativoLock->obtenerClaveSerializacion($currYear, $tpoTrmtes);
    }

    /**
     * Retorna los parámetros vinculados para el advisory lock [cmpnia, clave].
     *
     * @return array{0: int, 1: int}
     */
    public function obtenerParametrosLock(int $cmpnia, int $currYear, int $tpoTrmtes = 0): array
    {
        return $this->correlativoLock->obtenerParametrosLock($cmpnia, $currYear, $tpoTrmtes);
    }

    /**
     * Aplica advisory lock transaccional determinístico si la conexión es PostgreSQL.
     */
    public function serializarCorrelativoPostgres(int $cmpnia, int $currYear, int $tpoTrmtes = 0): void
    {
        $this->correlativoLock->serializarCorrelativoPostgres($cmpnia, $currYear, $tpoTrmtes);
    }

    /**
     * {@inheritdoc}
     */
    public function resolverCorrelativoAnio(int $cmpnia, int $tpoTrmtes, int $currYear): array
    {
        return $this->correlativoLock->resolver($cmpnia, $tpoTrmtes, $currYear);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCodigoCorreoUsuario(int $idUsuaSol): ?int
    {
        $cdgo = $this->db->table($this->schema . 'h_usrios_crreo')
            ->where('cdgo_usrios', $idUsuaSol)
            ->value('cdgo_usrios_crreo');

        return $cdgo !== null ? (int) $cdgo : null;
    }

    /**
     * {@inheritdoc}
     */
    public function insertarExpediente(array $datos): int
    {
        return (int) $this->db->table($this->schema . 'p_dto_trmtes')->insertGetId($datos, 'cdgo_dto_trmte');
    }

    /**
     * {@inheritdoc}
     */
    public function copiarRequisitosVirtual(int $idTramVirtual, int $idNuevo): void
    {
        $reqs = $this->db->table($this->schema . 'r_dtos_rqtos')
            ->where('cdgo_dto_trmte', $idTramVirtual)
            ->get(['cdgo_rqto_trmte', 'in_pres']);

        foreach ($reqs as $req) {
            $this->db->table($this->schema . 'r_dtos_rqtos')->insert([
                'cdgo_dto_trmte' => $idNuevo,
                'cdgo_rqto_trmte' => $req->cdgo_rqto_trmte,
                'in_pres' => '1',
            ]);
        }
    }

    private function formatNumeroExpediente(string $expe): string
    {
        $parts = explode('-', $expe);
        if (count($parts) < 3) {
            return $expe;
        }

        return $parts[0] . '-' . str_pad($parts[1], 5, '0', STR_PAD_LEFT) . '-' . $parts[2];
    }
}
