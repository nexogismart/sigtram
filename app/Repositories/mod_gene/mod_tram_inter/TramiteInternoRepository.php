<?php

declare(strict_types=1);

namespace App\Repositories\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\DTOs\Support\ParticipanteUDto;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException;
use InvalidArgumentException;

class TramiteInternoRepository implements TramiteInternoRepositoryInterface
{
    private string $schema;

    public function __construct(
        private readonly DatabaseManager $db
    ) {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function obtenerMovimientoPorId(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto
    {
        $fila = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->where('r.cdgo_trmtes_ntrnos', $movimientoId)
            ->where('r.in_esta', '1')
            ->where('p.in_esta', '1')
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'p.cdgo_area as area_sa',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.in_copia',
            ])
            ->first();

        if (!$fila) {
            return null;
        }

        $rowCmpnia = isset($fila->cdgo_cmpnia) && is_numeric($fila->cdgo_cmpnia) ? (int) $fila->cdgo_cmpnia : 0;
        if ($rowCmpnia <= 0 || $rowCmpnia !== $companiaId) {
            // IDOR Protection: pertenece a otra compania o compania invalida
            return null;
        }

        $areaEn = (int) ($this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $fila->cdgo_crgo_en)
            ->value('cdgo_area') ?? 0);

        return new TramiteInternoMovimientoDto(
            id: (int) $fila->cdgo_dtos_ntrnos,
            movimientoId: (int) $fila->cdgo_trmtes_ntrnos,
            numero: (string) ($fila->nu_tram_todo ?? ''),
            companiaId: $rowCmpnia,
            cargoSalidaId: (int) $fila->cdgo_crgo_sa,
            cargoEntradaId: (int) $fila->cdgo_crgo_en,
            areaSalidaId: (int) ($fila->area_sa ?? 0),
            areaEntradaId: $areaEn,
            estadoId: (int) $fila->cdgo_estdo_trmte,
            inEsta: (string) $fila->in_esta,
            inCopia: (string) ($fila->in_copia ?? '0')
        );
    }

    public function obtenerMovimientoHistoricoPorId(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto
    {
        $fila = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->where('r.cdgo_trmtes_ntrnos', $movimientoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'p.cdgo_area as area_sa',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.in_copia',
            ])
            ->first();

        if (!$fila) {
            return null;
        }

        $rowCmpnia = (int) ($fila->cdgo_cmpnia ?? 0);
        if ($rowCmpnia !== $companiaId || $rowCmpnia <= 0) {
            return null;
        }

        $areaEn = (int) ($this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $fila->cdgo_crgo_en)
            ->value('cdgo_area') ?? 0);

        return new TramiteInternoMovimientoDto(
            id: (int) $fila->cdgo_dtos_ntrnos,
            movimientoId: (int) $fila->cdgo_trmtes_ntrnos,
            numero: (string) ($fila->nu_tram_todo ?? ''),
            companiaId: $rowCmpnia,
            cargoSalidaId: (int) $fila->cdgo_crgo_sa,
            cargoEntradaId: (int) $fila->cdgo_crgo_en,
            areaSalidaId: (int) ($fila->area_sa ?? 0),
            areaEntradaId: $areaEn,
            estadoId: (int) $fila->cdgo_estdo_trmte,
            inEsta: (string) $fila->in_esta,
            inCopia: (string) ($fila->in_copia ?? '0')
        );
    }

    public function obtenerMovimientoHistoricoAutorizado(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto
    {
        return $this->obtenerMovimientoHistoricoPorId($movimientoId, $companiaId);
    }

    public function obtenerDocumentoHistoricoAutorizado(int $documentoId, int $companiaId): ?object
    {
        if ($documentoId <= 0 || $companiaId <= 0) {
            return null;
        }

        return $this->db->table($this->schema . 'p_dtos_ntrnos as p')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'a_areas as a', 'p.cdgo_area', '=', 'a.cdgo_area')
            ->where('p.cdgo_dtos_ntrnos', $documentoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select([
                'p.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.fe_ingrso',
                'p.de_obser',
                'p.de_urls_adjnto',
                'p.cdgo_cmpnia',
                'p.cdgo_area',
                't.de_tpo_trmte',
                't.dc_tpo_trmte',
                'a.de_area',
                'a.dc_area',
            ])
            ->first();
    }

    public function obtenerMovimientosPorIds(array $movimientoIds, int $companiaId): array
    {
        if (empty($movimientoIds)) {
            return [];
        }

        $filas = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->whereIn('r.cdgo_trmtes_ntrnos', $movimientoIds)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('r.in_esta', '1')
            ->where('p.in_esta', '1')
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'p.cdgo_area as area_sa',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.in_copia',
            ])
            ->get();

        // Fail-closed: si la cantidad recuperada no coincide con los IDs solicitados,
        // significa que al menos uno no existe o pertenece a otra compania
        if ($filas->count() !== count($movimientoIds)) {
            return [];
        }

        $dtos = [];
        foreach ($filas as $fila) {
            $areaEn = (int) ($this->db->table($this->schema . 'p_crgos')
                ->where('cdgo_crgo', $fila->cdgo_crgo_en)
                ->value('cdgo_area') ?? 0);

            $dtos[] = new TramiteInternoMovimientoDto(
                id: (int) $fila->cdgo_dtos_ntrnos,
                movimientoId: (int) $fila->cdgo_trmtes_ntrnos,
                numero: (string) ($fila->nu_tram_todo ?? ''),
                companiaId: (int) ($fila->cdgo_cmpnia ?? 0),
                cargoSalidaId: (int) $fila->cdgo_crgo_sa,
                cargoEntradaId: (int) $fila->cdgo_crgo_en,
                areaSalidaId: (int) ($fila->area_sa ?? 0),
                areaEntradaId: $areaEn,
                estadoId: (int) $fila->cdgo_estdo_trmte,
                inEsta: (string) $fila->in_esta,
                inCopia: (string) ($fila->in_copia ?? '0')
            );
        }

        return $dtos;
    }

    public function listarBandeja(
        string $bandeja,
        ContextoUsuarioAutenticadoDto $contexto,
        array $filtros = [],
        int $page = 1,
        int $perPage = 25
    ): array {
        $builder = $this->construirQueryBandeja($bandeja, $contexto, $filtros);

        $total = (clone $builder)->count();
        $offset = ($page - 1) * $perPage;

        $items = $builder
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? $offset + 1 : null;
        $to = $total > 0 ? min($offset + $perPage, $total) : null;

        $data = $items->map(function ($item) {
            $arr = (array) $item;
            $arr['documento_id'] = (int) ($arr['cdgo_dtos_ntrnos'] ?? 0);
            $arr['movimiento_id'] = (int) ($arr['cdgo_trmtes_ntrnos'] ?? 0);
            $arr['documento_referencia_id'] = !empty($arr['cdgo_dtos_ntrnos_ref']) ? (int) $arr['cdgo_dtos_ntrnos_ref'] : null;
            $arr['movimiento_referencia_id'] = !empty($arr['cdgo_trmtes_ntrnos_ref']) ? (int) $arr['cdgo_trmtes_ntrnos_ref'] : null;
            return $arr;
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'has_more_pages' => $page < $lastPage,
            ],
        ];
    }

    public function contarBandeja(
        string $bandeja,
        ContextoUsuarioAutenticadoDto $contexto,
        array $filtros = []
    ): int {
        return $this->construirQueryBandeja($bandeja, $contexto, $filtros)->count();
    }

    public function obtenerHistorial(
        int $movimientoId,
        ContextoUsuarioAutenticadoDto $contexto,
        int $page = 1,
        int $perPage = 50,
        ?int $documentoId = null
    ): array {
        $cdgoDtosNtrnos = $documentoId;

        if ($cdgoDtosNtrnos === null || $cdgoDtosNtrnos <= 0) {
            // Encontrar el cdgo_dtos_ntrnos base (sin filtrar vigencia del movimiento)
            $doc = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
                ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
                ->where('r.cdgo_trmtes_ntrnos', $movimientoId)
                ->where('p.cdgo_cmpnia', $contexto->idCompania)
                ->select('r.cdgo_dtos_ntrnos')
                ->first();
            $cdgoDtosNtrnos = $doc ? (int) $doc->cdgo_dtos_ntrnos : 0;
        } else {
            // Validar que el documento pertenece a la compañía
            $docExiste = $this->db->table($this->schema . 'p_dtos_ntrnos as p')
                ->where('p.cdgo_dtos_ntrnos', $cdgoDtosNtrnos)
                ->where('p.cdgo_cmpnia', $contexto->idCompania)
                ->exists();
            if (!$docExiste) {
                $cdgoDtosNtrnos = 0;
            } elseif ($movimientoId > 0) {
                // FASE 3: Si se reciben ambos, validar que el movimiento pertenece al documento
                $movPertenece = $this->db->table($this->schema . 'r_trmtes_ntrnos')
                    ->where('cdgo_trmtes_ntrnos', $movimientoId)
                    ->where('cdgo_dtos_ntrnos', $cdgoDtosNtrnos)
                    ->exists();
                if (!$movPertenece) {
                    $cdgoDtosNtrnos = 0;
                }
            }
        }

        if ($cdgoDtosNtrnos <= 0) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                    'has_more_pages' => false,
                ],
            ];
        }

        $builder = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->leftJoin($this->schema . 'p_crgos as c_sa', 'c_sa.cdgo_crgo', '=', 'r.cdgo_crgo_sa')
            ->leftJoin($this->schema . 'p_crgos as c_en', 'c_en.cdgo_crgo', '=', 'r.cdgo_crgo_en')
            ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'e.cdgo_estdo_trmte', '=', 'r.cdgo_estdo_trmte')
            ->where('r.cdgo_dtos_ntrnos', $cdgoDtosNtrnos)
            ->orderByDesc('r.cdgo_trmtes_ntrnos')
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'r.fe_rcbdo',
                'r.de_obser_ntrnos',
                'r.de_obser_ntrnos as de_obser',
                'r.de_urls_agrdo',
                'r.in_esta',
                'r.in_copia',
                'r.cdgo_lgin',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'r.cdgo_estdo_trmte',
                'r.cdgo_dtos_ntrnos_rfrncia',
                'p.nu_tram_todo',
                'p.fe_ingrso',
                'p.de_obser as de_obser_documento',
                'c_sa.de_crgo as de_crgo_sa',
                'c_sa.de_crgo as cargo_salida',
                'c_en.de_crgo as de_crgo_en',
                'c_en.de_crgo as cargo_entrada',
                'e.dc_estdo_trmte',
                'e.dc_estdo_trmte as estado_descripcion',
            ]);

        $total = $builder->count();
        $offset = ($page - 1) * $perPage;

        $fronteraFepasa = null;
        if ($page > 1) {
            $fronteraFepasa = $this->db->table($this->schema . 'r_trmtes_ntrnos')
                ->where('cdgo_dtos_ntrnos', $cdgoDtosNtrnos)
                ->orderByDesc('cdgo_trmtes_ntrnos')
                ->offset($offset - 1)
                ->limit(1)
                ->value('fe_rcbdo');
        }

        $movimientos = $builder->offset($offset)->limit($perPage)->get()->all();

        $cargoIds = array_values(array_unique(array_filter(array_merge(
            array_map(static fn (object $movimiento): int => (int) ($movimiento->cdgo_crgo_sa ?? 0), $movimientos),
            array_map(static fn (object $movimiento): int => (int) ($movimiento->cdgo_crgo_en ?? 0), $movimientos)
        ))));
        $areasPorCargo = [];
        if ($cargoIds !== []) {
            $cargos = $this->db->table($this->schema . 'p_crgos as c')
                ->leftJoin($this->schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
                ->whereIn('c.cdgo_crgo', $cargoIds)
                ->select(['c.cdgo_crgo', 'a.de_area', 'a.dc_area'])
                ->get();
            foreach ($cargos as $cargo) {
                $areasPorCargo[(int) $cargo->cdgo_crgo] = $cargo;
            }
        }

        $loginIds = array_values(array_unique(array_filter(array_map(
            static fn (object $movimiento): int => (int) ($movimiento->cdgo_lgin ?? 0),
            $movimientos
        ))));
        $usuariosOrigen = [];
        if ($loginIds !== []) {
            $usuarios = $this->db->table($this->schema . 'p_lgin as l')
                ->join($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->leftJoin($this->schema . 'p_prfles as prf', 'l.cdgo_prfle', '=', 'prf.cdgo_prfle')
                ->whereIn('l.cdgo_lgin', $loginIds)
                ->select(['l.cdgo_lgin', 'u.no_crto', 'l.cdgo_prfle', 'prf.de_prfle'])
                ->get();

            foreach ($usuarios as $usuario) {
                $usuariosOrigen[(int) $usuario->cdgo_lgin] = [
                    'login' => (string) $usuario->cdgo_lgin,
                    'nombre' => strtoupper((string) $usuario->no_crto),
                    'perfil' => $usuario->de_prfle ? strtoupper((string) $usuario->de_prfle) : null,
                    'perfil_id' => (int) $usuario->cdgo_prfle,
                ];
            }
        }

        $cargosDestinoIds = array_values(array_unique(array_filter(array_map(
            static fn (object $movimiento): int => (int) ($movimiento->cdgo_crgo_en ?? 0),
            $movimientos
        ))));
        $usuariosDestino = [];
        if ($cargosDestinoIds !== []) {
            $destinatarios = $this->db->table($this->schema . 'r_crgos_lgin as rcl')
                ->join($this->schema . 'p_lgin as pl', 'rcl.cdgo_lgin', '=', 'pl.cdgo_lgin')
                ->join($this->schema . 'p_usrios as pu', 'pl.cdgo_usrios', '=', 'pu.cdgo_usrios')
                ->join($this->schema . 'p_crgos as pc', 'rcl.cdgo_crgo', '=', 'pc.cdgo_crgo')
                ->whereIn('rcl.cdgo_crgo', $cargosDestinoIds)
                ->where('pl.in_esta', '1')
                ->where('pc.in_esta', '1')
                ->where('rcl.in_esta', '1')
                ->where('pu.cdgo_usrios', '!=', 8)
                ->select(['rcl.cdgo_crgo', 'pu.no_crto', 'pc.de_crgo'])
                ->orderBy('pu.no_crto')
                ->get();

            foreach ($destinatarios as $destinatario) {
                $usuariosDestino[(int) $destinatario->cdgo_crgo][] = [
                    'no_crto' => strtoupper((string) $destinatario->no_crto),
                    'de_crgo' => (string) $destinatario->de_crgo,
                ];
            }
        }

        $adjuntos = array_values(array_unique(array_filter(array_map(
            static fn (object $movimiento): string => trim((string) ($movimiento->de_urls_agrdo ?? '')),
            $movimientos
        ))));
        $conteosDescarga = [];
        if ($adjuntos !== []) {
            $conteosDescarga = $this->db->table($this->schema . 'p_sgmto_dscrgas')
                ->whereIn('de_urls_dscrgas', $adjuntos)
                ->selectRaw('de_urls_dscrgas, count(*) as total')
                ->groupBy('de_urls_dscrgas')
                ->pluck('total', 'de_urls_dscrgas')
                ->toArray();
        }

        $hoy = now()->format('Y-m-d H:i:s');
        $fepasa = $fronteraFepasa;
        $items = [];
        foreach ($movimientos as $movimiento) {
            $inEsta = (string) ($movimiento->in_esta ?? '');
            $estado = (string) ($movimiento->dc_estdo_trmte ?? $movimiento->estado_descripcion ?? '');
            $fechaRecepcion = $movimiento->fe_rcbdo ?? null;
            $fechaIngreso = $movimiento->fe_ingrso ?? null;
            if ($inEsta === '0') {
                $tiempoEstimado = $this->calcularTiempoEstimado($fechaRecepcion, $fepasa ?? $hoy);
            } elseif ($inEsta === '1' && in_array($estado, ['FINALIZADO', 'FINAL. COPIA'], true)) {
                $tiempoEstimado = $this->calcularTiempoEstimado($fechaIngreso, $fechaRecepcion);
            } else {
                $tiempoEstimado = $this->calcularTiempoEstimado($fechaRecepcion, $hoy);
            }
            $fepasa = $fechaRecepcion;

            $loginId = (int) ($movimiento->cdgo_lgin ?? 0);
            $cargoSalidaId = (int) ($movimiento->cdgo_crgo_sa ?? 0);
            $cargoEntradaId = (int) ($movimiento->cdgo_crgo_en ?? 0);
            $areaSalida = $areasPorCargo[$cargoSalidaId] ?? null;
            $areaEntrada = $areasPorCargo[$cargoEntradaId] ?? null;
            $origen = $usuariosOrigen[$loginId] ?? null;
            $nombreOrigen = $origen['nombre'] ?? '-';
            $cargoOrigenRegistrado = $movimiento->de_crgo_sa ?? $movimiento->cargo_salida ?? null;
            $cargoDestinoRegistrado = $movimiento->de_crgo_en ?? $movimiento->cargo_entrada ?? null;
            $cargoOrigen = !empty($cargoOrigenRegistrado)
                ? (string) $cargoOrigenRegistrado
                : ($cargoSalidaId > 0 ? "Cargo ID #{$cargoSalidaId}" : 'Cargo no registrado en el movimiento');
            $areaOrigen = !empty($areaSalida->de_area)
                ? (string) $areaSalida->de_area
                : (!empty($areaSalida->dc_area) ? (string) $areaSalida->dc_area : 'Área no registrada');

            if ($cargoEntradaId > 0 && $cargoEntradaId === $cargoSalidaId) {
                $destinos = [[
                    'no_crto' => $nombreOrigen,
                    'de_crgo' => (string) ($cargoOrigenRegistrado ?? ''),
                ]];
            } else {
                $destinos = $usuariosDestino[$cargoEntradaId] ?? [[
                    'no_crto' => 'SIN USUARIOS ACTIVOS',
                    'de_crgo' => (string) ($cargoDestinoRegistrado ?? ''),
                ]];
            }

            $adjunto = trim((string) ($movimiento->de_urls_agrdo ?? ''));
            $totalDescargas = (int) ($conteosDescarga[$adjunto] ?? 0);
            $movimiento->movimiento_id = (int) $movimiento->cdgo_trmtes_ntrnos;
            $movimiento->documento_id = $cdgoDtosNtrnos;
            $movimiento->fe_rcbdo_fmt = $fechaRecepcion ? Carbon::parse($fechaRecepcion)->format('d/m/Y H:i') : '-';
            $movimiento->tiempo_estimado = $tiempoEstimado;
            $movimiento->dc_area = (string) ($areaSalida->dc_area ?? '');
            $movimiento->de_area = $areaOrigen;
            $movimiento->de_crgo_sa = $cargoOrigen;
            $movimiento->dc_estdo_trmte = $estado;
            $movimiento->dc_area_1 = (string) ($areaEntrada->dc_area ?? '');
            $movimiento->de_area_1 = (string) ($areaEntrada->de_area ?? '');
            $movimiento->usuario_origen = $nombreOrigen;
            $movimiento->perfil_origen = $origen['perfil'] ?? null;
            $movimiento->actor = [
                    'login' => (string) ($loginId ?: '-'),
                    'nombre' => $nombreOrigen,
                    'cargo' => $cargoOrigen,
                    'area' => $areaOrigen,
                    'perfil' => $origen['perfil'] ?? null,
            ];
            $movimiento->usuarios_destino = $destinos;
            $movimiento->tiene_adjunto = $adjunto !== '';
            $movimiento->de_urls_agrdo = $adjunto;
            $movimiento->descargas_total = $totalDescargas;
            $movimiento->tiene_descargas = $totalDescargas > 0;
            $movimiento->auditoria_descargas = [];
            $items[] = $movimiento;
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
                'has_more_pages' => $page < $lastPage,
            ],
        ];
    }

    private function calcularTiempoEstimado(?string $inicio, ?string $fin): string
    {
        if (empty($inicio) || empty($fin)) {
            return '-';
        }

        $inicioTimestamp = strtotime($inicio);
        $finTimestamp = strtotime($fin);
        if ($inicioTimestamp === false || $finTimestamp === false || $finTimestamp < $inicioTimestamp) {
            return '0s';
        }

        $segundos = $finTimestamp - $inicioTimestamp;
        $dias = intdiv($segundos, 86400);
        $segundos -= $dias * 86400;
        $horas = intdiv($segundos, 3600);
        $segundos -= $horas * 3600;
        $minutos = intdiv($segundos, 60);
        $segundos -= $minutos * 60;

        return match (true) {
            $dias === 1 => '1D',
            $dias >= 2 => ($dias + 1) . 'D',
            $horas >= 1 => $horas . 'H',
            $minutos >= 1 => $minutos . 'm',
            $segundos >= 1 => $segundos . 's',
            default => '0s',
        };
    }

    public function obtenerDetalleExpediente(int $movimientoId, ContextoUsuarioAutenticadoDto $contexto): ?array
    {
        $fila = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 't.cdgo_tpo_trmte', '=', 'p.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'a_areas as a', 'a.cdgo_area', '=', 'p.cdgo_area')
            ->where('r.cdgo_trmtes_ntrnos', $movimientoId)
            ->where('p.cdgo_cmpnia', $contexto->idCompania)
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'p.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                't.dc_tpo_trmte as tipo_documento',
                'a.dc_area as area_origen',
                'p.fe_ingrso',
                'p.de_obser',
                'p.de_urls_adjnto',
                'r.cdgo_estdo_trmte',
                'r.fe_rcbdo',
            ])
            ->first();

        return $fila ? (array) $fila : null;
    }

    public function obtenerColumnasBandeja(string $bandeja): array
    {
        $columnas = [
            'r.cdgo_trmtes_ntrnos AS cdgo_trmtes_ntrnos',
            'p.cdgo_dtos_ntrnos AS cdgo_dtos_ntrnos',
            'p.nu_tram_todo AS nu_tram_todo',
            'p.cdgo_tpo_trmte AS cdgo_tpo_trmte',
            't.dc_tpo_trmte AS dc_tpo_trmte',
            't.de_tpo_trmte AS de_tpo_trmte',
            'a.cdgo_area AS cdgo_area',
            'a.dc_area AS dc_area',
            'a.de_area AS de_area',
            'p.fe_ingrso AS fe_ingrso',
            'r.fe_rcbdo AS fe_rcbdo',
            'p.de_obser AS de_obser',
            'r.de_obser_ntrnos AS de_obser_ntrnos',
            'p.de_urls_adjnto AS de_urls_adjnto',
            'r.de_urls_agrdo AS de_urls_agrdo',
            'r.cdgo_estdo_trmte AS cdgo_estdo_trmte',
            'p.in_prrdad_trmte AS in_prrdad_trmte',
            'p.in_envo_fsco AS in_envo_fsco',
            'r.in_copia AS in_copia',
            'r.in_esta AS in_esta',
            'r.cdgo_crgo_sa AS cdgo_crgo_sa',
            'r.cdgo_crgo_en AS cdgo_crgo_en',
            'p.cdgo_dto_trmte AS cdgo_dto_trmte',
            'p.cdgo_dtos_ntrnos_pri AS cdgo_dtos_ntrnos_pri',
            'p.nu_tram_todo_rfncia AS nu_tram_todo_rfncia',
            'r.nu_tram_todo_rfncia AS nu_tram_todo_rfncia_trmtes',
        ];

        switch ($bandeja) {
            case 'entrada':
                $columnas = array_merge($columnas, [
                    'r_ref.cdgo_trmtes_ntrnos AS cdgo_trmtes_ntrnos_ref',
                    'p_ref.cdgo_dtos_ntrnos AS cdgo_dtos_ntrnos_ref',
                    'p_ref.nu_tram_todo AS nu_tram_todo_ref',
                    't_ref.dc_tpo_trmte AS dc_tpo_trmte_ref',
                    't_ref.de_tpo_trmte AS de_tpo_trmte_ref',
                    'a_ref.cdgo_area AS cdgo_area_ref',
                    'a_ref.dc_area AS dc_area_ref',
                    'a_ref.de_area AS de_area_ref',
                    'p_ref.fe_ingrso AS fe_ingrso_ref',
                    'p_ref.de_urls_adjnto AS de_urls_adjnto_ref',
                ]);
                break;

            case 'enviados':
                $columnas = array_merge($columnas, [
                    'u.no_crto AS no_crto',
                    'p.cdgo_lgin AS cdgo_lgin',
                ]);
                break;

            case 'todos':
                $columnas = array_merge($columnas, [
                    'pc_en.de_crgo AS de_crgo',
                    'pc_en.dc_crgo AS dc_crgo',
                    'e.dc_estdo_trmte AS dc_estdo_trmte',
                    'e.de_estdo_trmte AS de_estdo_trmte',
                ]);
                break;

            case 'sin_finalizar':
            case 'contabilidad':
                $columnas = array_merge($columnas, [
                    'e.dc_estdo_trmte AS dc_estdo_trmte',
                    'e.de_estdo_trmte AS de_estdo_trmte',
                ]);
                break;

            default:
                break;
        }

        return $columnas;
    }

    private function construirQueryBandeja(
        string $bandeja,
        ContextoUsuarioAutenticadoDto $contexto,
        array $filtros
    ): Builder {
        $builder = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->leftJoin($this->schema . 'a_areas as a', 'a.cdgo_area', '=', 'p.cdgo_area')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 't.cdgo_tpo_trmte', '=', 'p.cdgo_tpo_trmte')
            ->where('p.cdgo_cmpnia', $contexto->idCompania)
            ->where('r.in_esta', '1')
            ->where('p.in_esta', '1');

        switch ($bandeja) {
            case 'enviados':
                $builder->join($this->schema . 'p_lgin as l', 'p.cdgo_lgin', '=', 'l.cdgo_lgin')
                    ->join($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                    ->where('p.cdgo_area', $contexto->idArea)
                    ->where('r.cdgo_estdo_trmte', 1)
                    ->orderBy('p.cdgo_dtos_ntrnos', 'desc');
                break;

            case 'todos':
                $builder->join($this->schema . 'p_crgos as pc_en', 'pc_en.cdgo_crgo', '=', 'r.cdgo_crgo_en')
                    ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
                    ->where('pc_en.cdgo_area', $contexto->idArea)
                    ->orderBy('r.fe_rcbdo', 'desc')
                    ->orderBy('r.cdgo_trmtes_ntrnos', 'desc');
                break;

            case 'recibidos':
                $builder->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->whereNotIn('r.cdgo_estdo_trmte', [1, 3, 24])
                    ->orderBy('r.cdgo_trmtes_ntrnos', 'desc');
                break;

            case 'salida':
                $builder->where('r.cdgo_crgo_en', '!=', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->orderBy('r.fe_rcbdo', 'desc')
                    ->orderBy('r.cdgo_trmtes_ntrnos', 'desc');
                break;

            case 'finalizados':
                $builder->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->whereIn('r.cdgo_estdo_trmte', [3, 24])
                    ->orderBy('r.fe_rcbdo', 'desc')
                    ->orderBy('r.cdgo_trmtes_ntrnos', 'desc');
                break;

            case 'sin_finalizar':
                $builder->leftJoin($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
                    ->where('r.cdgo_crgo_en', '!=', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', $contexto->idCargo)
                    ->whereNotIn('r.cdgo_estdo_trmte', [3, 24])
                    ->orderBy('r.fe_rcbdo', 'desc')
                    ->orderBy('r.cdgo_trmtes_ntrnos', 'desc');
                break;

            case 'contabilidad':
                $builder->leftJoin($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
                    ->where(function (Builder $q) {
                        $q->where(function (Builder $sub) {
                            $sub->where('p.cdgo_tpo_trmte', 7)
                                ->where('r.cdgo_crgo_en', 21)
                                ->where('r.cdgo_crgo_sa', 20);
                        })->orWhere('p.cdgo_tpo_trmte', 15);
                    })
                    ->orderBy('r.fe_rcbdo', 'desc')
                    ->orderBy('r.cdgo_trmtes_ntrnos', 'desc');
                break;

            default: // 'entrada'
                $builder->leftJoin($this->schema . 'r_trmtes_ntrnos as r_ref', 'r.cdgo_dtos_ntrnos_rfrncia', '=', 'r_ref.cdgo_trmtes_ntrnos')
                    ->leftJoin($this->schema . 'p_dtos_ntrnos as p_ref', 'r_ref.cdgo_dtos_ntrnos', '=', 'p_ref.cdgo_dtos_ntrnos')
                    ->leftJoin($this->schema . 'a_areas as a_ref', 'p_ref.cdgo_area', '=', 'a_ref.cdgo_area')
                    ->leftJoin($this->schema . 'a_tpo_trmtes as t_ref', 'p_ref.cdgo_tpo_trmte', '=', 't_ref.cdgo_tpo_trmte')
                    ->where('r.cdgo_crgo_en', $contexto->idCargo)
                    ->where('r.cdgo_crgo_sa', '!=', $contexto->idCargo)
                    ->orderBy('r.fe_rcbdo', 'desc')
                    ->orderBy('r.cdgo_trmtes_ntrnos', 'desc');
                break;
        }

        // Filtros parametrizados
        if (!empty($filtros['desde']) && !empty($filtros['hasta'])) {
            $campoFecha = ($bandeja === 'sin_finalizar') ? 'r.fe_rcbdo' : 'p.fe_ingrso';
            $builder->whereBetween($campoFecha, [
                $filtros['desde'] . ' 00:00:00',
                $filtros['hasta'] . ' 23:59:59',
            ]);
        }

        if (!empty($filtros['descripcion'])) {
            $busqueda = '%' . strtoupper(trim((string) $filtros['descripcion'])) . '%';
            $descExact = trim((string) $filtros['descripcion']);
            $criterio = $filtros['criterio'] ?? 'todos';

            switch ($criterio) {
                case 'tramite':
                    if ($bandeja === 'entrada') {
                        $builder->where(function (Builder $q) use ($descExact) {
                            $q->where('p.nu_tram_todo', $descExact)
                              ->orWhere('p_ref.nu_tram_todo', $descExact);
                        });
                    } else {
                        $builder->where('p.nu_tram_todo', $descExact);
                    }
                    break;
                case 'envio':
                    if ($bandeja === 'entrada') {
                        $builder->where('p_ref.nu_tram_todo', $descExact);
                    } else {
                        $builder->where('p.nu_tram_todo', $descExact);
                    }
                    break;
                case 'referencia':
                    $builder->where('p.nu_tram_todo', $descExact);
                    break;
                case 'area':
                    $builder->whereRaw('UPPER(a.de_area) LIKE ?', [$busqueda]);
                    break;
                case 'tipo':
                    $builder->whereRaw('UPPER(t.de_tpo_trmte) LIKE ?', [$busqueda]);
                    break;
                case 'observaciones':
                    $builder->whereRaw('UPPER(p.de_obser) LIKE ?', [$busqueda]);
                    break;
                case 'usuario':
                    if ($bandeja === 'enviados') {
                        $builder->whereRaw('UPPER(u.no_crto) LIKE ?', [$busqueda]);
                    }
                    break;
                case 'cargo':
                    if ($bandeja === 'todos') {
                        $builder->whereRaw('UPPER(pc_en.de_crgo) LIKE ?', [$busqueda]);
                    }
                    break;
                default:
                    $builder->where(function (Builder $q) use ($busqueda) {
                        $q->whereRaw('UPPER(p.nu_tram_todo) LIKE ?', [$busqueda])
                          ->orWhereRaw('UPPER(p.de_obser) LIKE ?', [$busqueda]);
                    });
                    break;
            }
        }

        if (!empty($filtros['faltantes'])) {
            $builder->where('p.cdgo_tpo_trmte', 64)
                ->whereExists(function ($q) {
                    $q->select($this->db->raw(1))
                        ->from('logistica.p_pgo_prvdres as ppp')
                        ->join('logistica.h_deta_pgo_prvdres as hdp', 'ppp.cdgo_pgo_prvdres', '=', 'hdp.cdgo_pgo_prvdres')
                        ->whereColumn('ppp.cdgo_dtos_ntrnos', 'p.cdgo_dtos_ntrnos')
                        ->where('ppp.in_esta', '!=', 0)
                        ->where('hdp.in_esta', '!=', 0)
                        ->where(function (Builder $sub) {
                            $sub->whereRaw("COALESCE(TRIM(hdp.de_urls_anxos), '') = ''")
                                ->orWhereRaw("COALESCE(TRIM(hdp.de_urls_adicional), '') = ''");
                        });
                });
        }

        $builder->select($this->obtenerColumnasBandeja($bandeja));

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerConteosBandejas(ContextoUsuarioAutenticadoDto $contexto): array
    {
        $bandejas = ['entrada', 'enviados', 'recibidos', 'salida', 'finalizados', 'sin_finalizar', 'todos'];
        if ($contexto->idArea === 21 || in_array(18, $contexto->permisos, true)) {
            $bandejas[] = 'contabilidad';
        }

        $conteos = [];
        foreach ($bandejas as $b) {
            $conteos[$b] = $this->contarBandeja($b, $contexto);
        }

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
        $mov = $this->obtenerMovimientoHistoricoPorId($movimientoId, $contexto->idCompania);
        if (!$mov) {
            return [
                'status'  => 'error',
                'message' => 'Movimiento no encontrado o sin autorización.',
                'code'    => 404,
            ];
        }

        $cargoDestinoId = $mov->cargoEntradaId;

        // Si cargo salida es igual a cargo entrada, se consultan TODOS los usuarios asignados al destino
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
            'status'     => 'success',
            'data'       => $participantes,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
            'meta'       => [
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
        $mov = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->where('r.cdgo_trmtes_ntrnos', $movimientoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select(['r.de_urls_agrdo', 'p.de_urls_adjnto'])
            ->first();

        if (!$mov) {
            return [
                'status'     => 'error',
                'message'    => 'Movimiento no encontrado o sin autorización.',
                'code'       => 404,
                'data'       => [],
                'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
            ];
        }

        $nomArchivo = trim((string) ($mov->de_urls_agrdo ?: $mov->de_urls_adjnto));
        if (empty($nomArchivo)) {
            return [
                'status'     => 'success',
                'data'       => [],
                'pagination' => [
                    'current_page' => $page,
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
     * Recupera movimientos vigentes bajo lock pesimista agrupado para operaciones transaccionales por lote.
     * Normaliza y deduplica IDs, aplica scope por compania del contexto, previene N+1 y retorna DTOs completos.
     *
     * @param array<int> $ids
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @return array<TramiteInternoMovimientoDto>
     */
    public function obtenerMovimientosVigentesParaOperacion(
        array $ids,
        ContextoUsuarioAutenticadoDto $contexto
    ): array {
        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($idsNormalizados)) {
            return [];
        }

        $filas = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->leftJoin($this->schema . 'p_crgos as pc', 'pc.cdgo_crgo', '=', 'r.cdgo_crgo_en')
            ->whereIn('p.cdgo_dtos_ntrnos', $idsNormalizados)
            ->where('p.cdgo_cmpnia', $contexto->idCompania)
            ->where('r.in_esta', '1')
            ->where('p.in_esta', '1')
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'p.cdgo_area as area_sa',
                'pc.cdgo_area as area_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.in_copia',
            ])
            ->orderBy('r.cdgo_trmtes_ntrnos', 'desc')
            ->lockForUpdate()
            ->get();

        $agrupadosPorDoc = [];
        foreach ($filas as $fila) {
            $docId = (int) $fila->cdgo_dtos_ntrnos;
            $agrupadosPorDoc[$docId][] = $fila;
        }

        $porDocumento = [];
        foreach ($agrupadosPorDoc as $docId => $movsDoc) {
            $seleccionado = null;
            if (count($movsDoc) === 1) {
                $seleccionado = $movsDoc[0];
            } else {
                // Multi-rama/multi-área: resolver el movimiento que corresponde al cargo/área del funcionario
                $coincidencias = [];
                foreach ($movsDoc as $m) {
                    $cargoEn = (int) $m->cdgo_crgo_en;
                    $cargoSa = (int) $m->cdgo_crgo_sa;
                    $areaEn = (int) ($m->area_en ?? 0);
                    if (
                        $cargoEn === $contexto->idCargo ||
                        in_array($cargoEn, $contexto->cargosAsignados, true) ||
                        $cargoSa === $contexto->idCargo ||
                        $areaEn === $contexto->idArea
                    ) {
                        $coincidencias[] = $m;
                    }
                }

                if (count($coincidencias) === 1) {
                    $seleccionado = $coincidencias[0];
                } elseif (count($coincidencias) > 1) {
                    throw new ConflictoConcurrenteTramiteException("Estado ambiguo detectado: múltiples movimientos vigentes para el expediente {$docId}.");
                } elseif ($contexto->tienePermiso(18)) {
                    if (count($movsDoc) === 1) {
                        $seleccionado = $movsDoc[0];
                    } else {
                        throw new ConflictoConcurrenteTramiteException("Estado ambiguo detectado: múltiples movimientos vigentes para el expediente {$docId}.");
                    }
                }
            }

            if (!$seleccionado) {
                continue;
            }

            $porDocumento[$docId] = new TramiteInternoMovimientoDto(
                id: $docId,
                movimientoId: (int) $seleccionado->cdgo_trmtes_ntrnos,
                numero: (string) ($seleccionado->nu_tram_todo ?? ''),
                companiaId: (int) ($seleccionado->cdgo_cmpnia ?? 0),
                cargoSalidaId: (int) $seleccionado->cdgo_crgo_sa,
                cargoEntradaId: (int) $seleccionado->cdgo_crgo_en,
                areaSalidaId: (int) ($seleccionado->area_sa ?? 0),
                areaEntradaId: (int) ($seleccionado->area_en ?? 0),
                estadoId: (int) $seleccionado->cdgo_estdo_trmte,
                inEsta: (string) $seleccionado->in_esta,
                inCopia: (string) ($seleccionado->in_copia ?? '0')
            );
        }

        return array_values($porDocumento);
    }

    public function obtenerDocumentosVigentesParaOperacion(
        array $documentoIds,
        ContextoUsuarioAutenticadoDto $contexto
    ): array {
        return $this->obtenerMovimientosVigentesParaOperacion($documentoIds, $contexto);
    }

    public function obtenerMovimientoVigentePorId(int $movimientoId, int $companiaId): ?TramiteInternoMovimientoDto
    {
        return $this->obtenerMovimientoPorId($movimientoId, $companiaId);
    }

    public function obtenerExpedienteAutorizadoParaConsolidado(
        int $documentoId,
        ContextoUsuarioAutenticadoDto $contexto
    ): ?TramiteInternoMovimientoDto {
        if ($documentoId <= 0) {
            return null;
        }

        // Buscar estrictamente por p.cdgo_dtos_ntrnos y p.cdgo_cmpnia
        $doc = $this->db->table($this->schema . 'p_dtos_ntrnos as p')
            ->where('p.cdgo_dtos_ntrnos', $documentoId)
            ->where('p.cdgo_cmpnia', $contexto->idCompania)
            ->where('p.in_esta', '1')
            ->select(['p.cdgo_dtos_ntrnos', 'p.nu_tram_todo', 'p.cdgo_cmpnia', 'p.cdgo_area'])
            ->first();

        if (!$doc) {
            return null;
        }

        $filas = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->leftJoin($this->schema . 'p_crgos as pc', 'pc.cdgo_crgo', '=', 'r.cdgo_crgo_en')
            ->where('r.cdgo_dtos_ntrnos', $documentoId)
            ->where('r.in_esta', '1')
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'pc.cdgo_area as area_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.in_copia',
            ])
            ->orderBy('r.cdgo_trmtes_ntrnos', 'desc')
            ->get();

        if ($filas->isEmpty()) {
            return null;
        }

        $seleccionado = null;
        if ($filas->count() === 1) {
            $seleccionado = $filas->first();
        } else {
            $coincidencias = [];
            foreach ($filas as $fila) {
                $cargoEn = (int) $fila->cdgo_crgo_en;
                $cargoSa = (int) $fila->cdgo_crgo_sa;
                $areaEn = (int) ($fila->area_en ?? 0);

                if (
                    $cargoEn === $contexto->idCargo ||
                    in_array($cargoEn, $contexto->cargosAsignados, true) ||
                    $cargoSa === $contexto->idCargo ||
                    $areaEn === $contexto->idArea
                ) {
                    $coincidencias[] = $fila;
                }
            }

            if (count($coincidencias) === 1) {
                $seleccionado = $coincidencias[0];
            } elseif (count($coincidencias) > 1) {
                throw new ConflictoConcurrenteTramiteException("Estado ambiguo detectado: múltiples movimientos vigentes para el expediente {$documentoId}.");
            } elseif ($contexto->tienePermiso(18)) {
                throw new ConflictoConcurrenteTramiteException("Estado ambiguo detectado: múltiples movimientos vigentes para el expediente {$documentoId}.");
            }
        }

        if (!$seleccionado) {
            return null;
        }

        return new TramiteInternoMovimientoDto(
            id: (int) $doc->cdgo_dtos_ntrnos,
            movimientoId: (int) $seleccionado->cdgo_trmtes_ntrnos,
            numero: (string) ($doc->nu_tram_todo ?? ''),
            companiaId: (int) $doc->cdgo_cmpnia,
            cargoSalidaId: (int) $seleccionado->cdgo_crgo_sa,
            cargoEntradaId: (int) $seleccionado->cdgo_crgo_en,
            areaSalidaId: (int) ($doc->cdgo_area ?? 0),
            areaEntradaId: (int) ($seleccionado->area_en ?? 0),
            estadoId: (int) $seleccionado->cdgo_estdo_trmte,
            inEsta: (string) $seleccionado->in_esta,
            inCopia: (string) ($seleccionado->in_copia ?? '0')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargoPorId(int $cargoId): ?object
    {
        if ($cargoId <= 0) {
            return null;
        }

        return $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerUltimoMovimientoPorDocumento(int $documentoId, int $companiaId): ?TramiteInternoMovimientoDto
    {
        if ($documentoId <= 0 || $companiaId <= 0) {
            return null;
        }

        $movRecord = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->where('r.cdgo_dtos_ntrnos', $documentoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.cdgo_cmpnia',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'p.cdgo_area as area_sa',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.in_copia',
            ])
            ->orderBy('r.cdgo_trmtes_ntrnos', 'desc')
            ->first();

        if (!$movRecord) {
            return null;
        }

        $areaEn = (int) ($this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $movRecord->cdgo_crgo_en)
            ->value('cdgo_area') ?? 0);

        return new TramiteInternoMovimientoDto(
            id: (int) $movRecord->cdgo_dtos_ntrnos,
            movimientoId: (int) $movRecord->cdgo_trmtes_ntrnos,
            numero: (string) ($movRecord->nu_tram_todo ?? ''),
            companiaId: (int) $movRecord->cdgo_cmpnia,
            cargoSalidaId: (int) $movRecord->cdgo_crgo_sa,
            cargoEntradaId: (int) $movRecord->cdgo_crgo_en,
            areaSalidaId: (int) ($movRecord->area_sa ?? 0),
            areaEntradaId: $areaEn,
            estadoId: (int) $movRecord->cdgo_estdo_trmte,
            inEsta: (string) $movRecord->in_esta,
            inCopia: (string) ($movRecord->in_copia ?? '0')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerVoucherMovimientoInterno(int $movimientoId, ContextoUsuarioAutenticadoDto $contexto): ?object
    {
        if ($movimientoId <= 0 || $contexto->idCompania <= 0) {
            return null;
        }

        // Buscar primero por movimiento exacto (r.cdgo_trmtes_ntrnos) con scope de compañía
        $mov = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 't.cdgo_tpo_trmte', '=', 'p.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'p_crgos as sa', 'sa.cdgo_crgo', '=', 'r.cdgo_crgo_sa')
            ->leftJoin($this->schema . 'p_crgos as en', 'en.cdgo_crgo', '=', 'r.cdgo_crgo_en')
            ->where('r.cdgo_trmtes_ntrnos', $movimientoId)
            ->where('p.cdgo_cmpnia', $contexto->idCompania)
            ->select([
                'r.cdgo_trmtes_ntrnos',
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.cdgo_cmpnia',
                't.de_tpo_trmte',
                'r.fe_rcbdo',
                'r.de_obser_ntrnos',
                'p.de_obser as de_obser_documento',
                'r.de_urls_agrdo',
                'p.de_urls_adjnto',
                'r.cdgo_crgo_sa',
                'r.cdgo_crgo_en',
                'sa.cdgo_area as area_sa',
                'en.cdgo_area as area_en',
                'r.cdgo_estdo_trmte',
                'r.in_esta',
                'r.in_copia',
            ])
            ->first();

        // Si no se encontró por movimiento_id, buscar por documento_id (último movimiento histórico del trámite)
        if (!$mov) {
            $mov = $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
                ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
                ->leftJoin($this->schema . 'a_tpo_trmtes as t', 't.cdgo_tpo_trmte', '=', 'p.cdgo_tpo_trmte')
                ->leftJoin($this->schema . 'p_crgos as sa', 'sa.cdgo_crgo', '=', 'r.cdgo_crgo_sa')
                ->leftJoin($this->schema . 'p_crgos as en', 'en.cdgo_crgo', '=', 'r.cdgo_crgo_en')
                ->where('r.cdgo_dtos_ntrnos', $movimientoId)
                ->where('p.cdgo_cmpnia', $contexto->idCompania)
                ->select([
                    'r.cdgo_trmtes_ntrnos',
                    'r.cdgo_dtos_ntrnos',
                    'p.nu_tram_todo',
                    'p.cdgo_cmpnia',
                    't.de_tpo_trmte',
                    'r.fe_rcbdo',
                    'r.de_obser_ntrnos',
                    'p.de_obser as de_obser_documento',
                    'r.de_urls_agrdo',
                    'p.de_urls_adjnto',
                    'r.cdgo_crgo_sa',
                    'r.cdgo_crgo_en',
                    'sa.cdgo_area as area_sa',
                    'en.cdgo_area as area_en',
                    'r.cdgo_estdo_trmte',
                    'r.in_esta',
                    'r.in_copia',
                ])
                ->orderBy('r.cdgo_trmtes_ntrnos', 'desc')
                ->first();
        }

        return $mov;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerComprobantesPago(array $criterios, ContextoUsuarioAutenticadoDto $contexto): array
    {
        return $this->db->table('caja.p_cmprbntes_pgo as c')
            ->leftJoin($this->schema . 'p_dtos_ntrnos as p', 'c.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->leftJoin($this->schema . 'a_areas as a', 'p.cdgo_area', '=', 'a.cdgo_area')
            ->select(
                'c.cdgo_cmprbnte_pgo',
                'c.nu_cmprbnte_pgo',
                'c.fe_cmprbnte_pgo',
                'c.nu_imprte',
                'c.no_bnfcrio',
                'p.nu_tram_todo',
                'a.de_area',
                'c.in_esta'
            )
            ->where('c.in_esta', '1')
            ->orderBy('c.fe_cmprbnte_pgo', 'desc')
            ->limit(50)
            ->get()
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDocumentoParaHojaRuta(int $documentoId, ContextoUsuarioAutenticadoDto $contexto): ?object
    {
        if ($documentoId <= 0 || $contexto->idCompania <= 0) {
            return null;
        }

        $tramite = $this->db->table($this->schema . 'p_dtos_ntrnos')
            ->where('cdgo_dtos_ntrnos', $documentoId)
            ->where('cdgo_cmpnia', $contexto->idCompania)
            ->where('in_esta', '1')
            ->first();

        if (!$tramite) {
            return null;
        }

        $esAutorizado = $contexto->tienePermiso(18)
            || ((int) $tramite->cdgo_area === $contexto->idArea)
            || $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
                ->leftJoin($this->schema . 'p_crgos as c_sa', 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
                ->leftJoin($this->schema . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
                ->where('r.cdgo_dtos_ntrnos', $documentoId)
                ->where('r.in_esta', '1')
                ->where(function ($q) use ($contexto) {
                    $q->where('r.cdgo_crgo_sa', $contexto->idCargo)
                        ->orWhere('r.cdgo_crgo_en', $contexto->idCargo)
                        ->orWhereIn('r.cdgo_crgo_en', $contexto->cargosAsignados)
                        ->orWhere('c_sa.cdgo_area', $contexto->idArea)
                        ->orWhere('c_en.cdgo_area', $contexto->idArea);
                })
                ->exists();

        $tramite->es_autorizado = (bool) $esAutorizado;

        return $tramite;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCabeceraImpresion(ContextoUsuarioAutenticadoDto $contexto): array
    {
        $cargoInfo = $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $contexto->idCargo)
            ->first();

        $areaId = (int) ($cargoInfo->cdgo_area ?? $contexto->idArea);
        $areaNombre = $this->db->table($this->schema . 'a_areas')
            ->where('cdgo_area', $areaId)
            ->value('de_area') ?? 'MUNICIPALIDAD DE PUENTE PIEDRA';

        return [
            'cargo' => $cargoInfo,
            'cargo_nombre' => (string) ($cargoInfo->de_crgo ?? ''),
            'area_nombre' => (string) $areaNombre,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargosDeArea(int $areaId, int $cargoIdExcluir = 0): array
    {
        return $this->db->table($this->schema . 'r_crgos_lgin as rcl')
            ->join($this->schema . 'p_lgin as pl', 'rcl.cdgo_lgin', '=', 'pl.cdgo_lgin')
            ->join($this->schema . 'p_crgos as pc', 'rcl.cdgo_crgo', '=', 'pc.cdgo_crgo')
            ->join($this->schema . 'p_usrios as pu', 'pl.cdgo_usrios', '=', 'pu.cdgo_usrios')
            ->where('pl.in_esta', '1')
            ->where('pc.in_esta', '1')
            ->where('rcl.in_esta', '1')
            ->where('pc.cdgo_area', $areaId)
            ->when($cargoIdExcluir > 0, fn($q) => $q->where('pc.cdgo_crgo', '!=', $cargoIdExcluir))
            ->where('pu.cdgo_usrios', '!=', 8)
            ->select(
                'pc.cdgo_crgo',
                'pc.de_crgo',
                'pu.no_crto',
                'pu.cdgo_usrios'
            )
            ->orderBy('pu.no_crto', 'asc')
            ->get()
            ->map(function ($c) {
                return [
                    'cdgo_crgo' => (int) $c->cdgo_crgo,
                    'de_crgo' => (string) $c->de_crgo,
                    'no_crto' => (string) $c->no_crto,
                    'texto' => $c->de_crgo . ' — ' . $c->no_crto,
                ];
            })
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerAreasDestino(): array
    {
        return $this->db->table($this->schema . 'a_areas')
            ->where('in_esta', '1')
            ->select('cdgo_area', 'de_area', 'dc_area')
            ->orderBy('cdgo_tpo_area', 'asc')
            ->orderBy('de_area', 'asc')
            ->get()
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerTiposDocumentoInterno(int $areaId): array
    {
        return $this->db->table($this->schema . 'a_tpo_trmtes as t')
            ->join($this->schema . 'r_tpo_trmte_areas as r', 't.cdgo_tpo_trmte', '=', 'r.cdgo_tpo_trmte')
            ->where('t.in_esta', '1')
            ->where('r.in_esta', '1')
            ->where('r.cdgo_area', $areaId)
            ->select(
                't.cdgo_tpo_trmte',
                't.de_tpo_trmte',
                't.dc_tpo_trmte',
                't.abrvtra_tpo_trmte',
                't.in_scncal',
                't.in_frma_dgtal'
            )
            ->distinct()
            ->orderBy('t.de_tpo_trmte', 'asc')
            ->get()
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerAnioPorNumero(int $anio): ?object
    {
        return $this->db->table($this->schema . 'a_anios')
            ->where('de_anio', $anio)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerUltimoAnio(): ?object
    {
        return $this->db->table($this->schema . 'a_anios')
            ->orderBy('de_anio', 'desc')
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerTipoDocumentoInterno(int $tipoDocId): ?object
    {
        return $this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $tipoDocId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerSemillaInternaParaBloqueo(int $cdgoAnio, int $cdgoTpoTrmte, ?int $cargoId, ?int $areaId, bool $inScncal, bool $lock = false): int
    {
        $query = $this->db->table($this->schema . 'a_smlla_intrno')
            ->where('cdgo_tpo_trmte', $cdgoTpoTrmte)
            ->where('cdgo_anio', $cdgoAnio);

        if ($inScncal) {
            $query->where('cdgo_crgo', $cargoId);
        } else {
            $query->where('cdgo_area', $areaId);
        }

        if ($lock) {
            $query->lockForUpdate()->get();
        }

        $max = $query->max('nu_smlla_intrno');
        return $max ? ((int) $max) + 1 : 1;
    }

    /**
     * {@inheritdoc}
     */
    public function crearSemillaInterna(array $datos): void
    {
        $this->db->table($this->schema . 'a_smlla_intrno')->insert($datos);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerReferenciaDocumentoInterno(int $refDocId): ?object
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos as p')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'a_areas as a', 'p.cdgo_area', '=', 'a.cdgo_area')
            ->where('p.cdgo_dtos_ntrnos', $refDocId)
            ->select('p.nu_tram_todo', 't.de_tpo_trmte', 'a.de_area')
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function crearDocumentoInterno(array $datos): int
    {
        return (int) $this->db->table($this->schema . 'p_dtos_ntrnos')->insertGetId($datos, 'cdgo_dtos_ntrnos');
    }

    /**
     * {@inheritdoc}
     */
    public function crearMovimientoInterno(array $datos): int
    {
        return (int) $this->db->table($this->schema . 'r_trmtes_ntrnos')->insertGetId($datos, 'cdgo_trmtes_ntrnos');
    }

    /**
     * {@inheritdoc}
     */
    public function desactivarMovimientoInternoEsperado(int $movimientoId): int
    {
        return $this->db->table($this->schema . 'r_trmtes_ntrnos')
            ->where('cdgo_trmtes_ntrnos', $movimientoId)
            ->where('in_esta', '1')
            ->update(['in_esta' => '0']);
    }

    /**
     * {@inheritdoc}
     */
    public function registrarSeguimientoInterno(int $cdgoLgin, string $deSgtoLog, string $ip): void
    {
        try {
            $this->db->table($this->schema . 'p_sgmto_log')->insert([
                'cdgo_lgin' => $cdgoLgin,
                'de_sgto_log' => $deSgtoLog,
                'fe_sgto_log' => now(),
                'de_ip' => $ip,
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargoDestino(int $cargoId, bool $lockForUpdate = false): ?object
    {
        $query = $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->where('in_esta', '1');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerArea(int $areaId, bool $lockForUpdate = false): ?object
    {
        $query = $this->db->table($this->schema . 'a_areas')
            ->where('cdgo_area', $areaId)
            ->where('in_esta', '1');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargoPrincipalDeArea(int $areaId, bool $lockForUpdate = false): ?object
    {
        $query = $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_area', $areaId)
            ->where('in_esta', '1')
            ->orderByRaw("CASE WHEN nu_orde = '1' THEN 0 ELSE 1 END")
            ->orderBy('cdgo_crgo', 'desc');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerDetalleTramiteParaHojaRuta(int $tramiteId): ?object
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos as p')
            ->leftJoin($this->schema . 'a_tpo_trmtes as t', 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->leftJoin($this->schema . 'a_areas as a', 'p.cdgo_area', '=', 'a.cdgo_area')
            ->where('p.cdgo_dtos_ntrnos', $tramiteId)
            ->select(
                'p.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.fe_ingrso',
                'p.de_obser',
                'p.nu_tram_cmplto',
                't.de_tpo_trmte',
                't.dc_tpo_trmte',
                'a.de_area',
                'a.dc_area'
            )
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMovimientosHojaRuta(int $tramiteId): array
    {
        return $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->leftJoin($this->schema . 'p_crgos as sa', 'r.cdgo_crgo_sa', '=', 'sa.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as asa', 'sa.cdgo_area', '=', 'asa.cdgo_area')
            ->leftJoin($this->schema . 'p_crgos as en', 'r.cdgo_crgo_en', '=', 'en.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as aen', 'en.cdgo_area', '=', 'aen.cdgo_area')
            ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->leftJoin($this->schema . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->leftJoin($this->schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('r.cdgo_dtos_ntrnos', $tramiteId)
            ->select(
                'r.cdgo_trmtes_ntrnos',
                'r.fe_rcbdo',
                'r.de_obser_ntrnos',
                'r.de_urls_agrdo',
                'asa.de_area as area_origen',
                'asa.dc_area as area_origen_sigla',
                'aen.de_area as area_destino',
                'aen.dc_area as area_destino_sigla',
                'e.dc_estdo_trmte as estado',
                'u.no_crto as usuario_nombre'
            )
            ->orderBy('r.cdgo_trmtes_ntrnos', 'asc')
            ->get()
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerSiguienteSecuenciaInterna(): int
    {
        try {
            $seqRow = $this->db->selectOne("SELECT nextval('{$this->schema}r_trmtes_ntrnos_cdgo_trmtes_ntrnos_seq') AS next_val");
            if ($seqRow && isset($seqRow->next_val) && (int) $seqRow->next_val > 0) {
                return (int) $seqRow->next_val;
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("No fue posible reservar un correlativo de secuencia para adjuntos: " . $e->getMessage(), 0, $e);
        }

        throw new \RuntimeException("No fue posible reservar un correlativo de secuencia para adjuntos.");
    }

    public function obtenerDocumentoParaConsolidado(int $documentoId, int $companiaId): ?object
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos as p')
            ->where('p.cdgo_dtos_ntrnos', $documentoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('p.in_esta', '1')
            ->select(['p.cdgo_dtos_ntrnos', 'p.nu_tram_todo', 'p.de_urls_adjnto'])
            ->first();
    }

    public function obtenerMovimientosParaConsolidado(int $documentoId): array
    {
        return $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->where('r.cdgo_dtos_ntrnos', $documentoId)
            ->whereNotNull('r.de_urls_agrdo')
            ->where('r.de_urls_agrdo', '!=', '')
            ->select(['r.cdgo_trmtes_ntrnos', 'r.de_urls_agrdo'])
            ->get()
            ->all();
    }

    public function obtenerDocumentosAutorizadosLote(array $ids, int $companiaId): array
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos as p')
            ->whereIn('p.cdgo_dtos_ntrnos', $ids)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('p.in_esta', '1')
            ->pluck('p.cdgo_dtos_ntrnos')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    public function obtenerMovimientosLote(array $ids, int $companiaId): array
    {
        return $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->join($this->schema . 'p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')
            ->whereIn('r.cdgo_dtos_ntrnos', $ids)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->where('r.in_esta', '1')
            ->whereNotNull('r.de_urls_agrdo')
            ->where('r.de_urls_agrdo', '!=', '')
            ->select(['r.cdgo_dtos_ntrnos', 'r.cdgo_trmtes_ntrnos', 'r.de_urls_agrdo'])
            ->get()
            ->all();
    }

    public function obtenerDocumentoParaDescarga(int $documentoId, int $companiaId): ?object
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos')
            ->where('cdgo_dtos_ntrnos', $documentoId)
            ->where('cdgo_cmpnia', $companiaId)
            ->where('in_esta', '1')
            ->select(['de_urls_adjnto'])
            ->first();
    }

    public function obtenerMovimientoParaDescarga(int $movimientoId, int $companiaId): ?object
    {
        return $this->db->table($this->schema . 'r_trmtes_ntrnos as r')
            ->leftJoin($this->schema . 'p_dtos_ntrnos as p', 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('r.cdgo_trmtes_ntrnos', $movimientoId)
            ->where('p.cdgo_cmpnia', $companiaId)
            ->select(['r.de_urls_agrdo', 'p.de_urls_adjnto'])
            ->first();
    }

    public function actualizarDocumentoFirmado(int $cdgoDtoNtrno, int $companiaId, array $datos): int
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos')
            ->where('cdgo_dtos_ntrnos', $cdgoDtoNtrno)
            ->where('cdgo_cmpnia', $companiaId)
            ->where('in_esta', '1')
            ->update($datos);
    }

    public function registrarAuditoriaDescargaInterna(array $datos): void
    {
        $this->db->table($this->schema . 'p_sgmto_dscrgas')->insert($datos);
    }

    public function obtenerTramitesTipo64(array $ids): array
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos')
            ->whereIn('cdgo_dtos_ntrnos', $ids)
            ->where('cdgo_tpo_trmte', 64)
            ->where('in_esta', '1')
            ->select(['cdgo_dtos_ntrnos', 'nu_tram_todo'])
            ->get()
            ->all();
    }

    public function obtenerPagosProveedoresIncompletos(array $idsTipo64): array
    {
        return $this->db->table('logistica.p_pgo_prvdres as ppp')
            ->join('logistica.h_deta_pgo_prvdres as hdp', 'ppp.cdgo_pgo_prvdres', '=', 'hdp.cdgo_pgo_prvdres')
            ->whereIn('ppp.cdgo_dtos_ntrnos', $idsTipo64)
            ->where('ppp.in_esta', '!=', 0)
            ->where('hdp.in_esta', '!=', 0)
            ->where(function ($q) {
                $q->whereNull('hdp.de_urls_anxos')
                    ->orWhereRaw("TRIM(hdp.de_urls_anxos) = ''")
                    ->orWhereNull('hdp.de_urls_adicional')
                    ->orWhereRaw("TRIM(hdp.de_urls_adicional) = ''");
            })
            ->select(['ppp.cdgo_dtos_ntrnos'])
            ->distinct()
            ->pluck('cdgo_dtos_ntrnos')
            ->toArray();
    }

    public function obtenerCargoInfo(int $cargoId): ?object
    {
        return $this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();
    }

    public function obtenerAreasCatalogo(bool $isAdmin, int $areaUsuario): array
    {
        $query = $this->db->table($this->schema . 'a_areas')
            ->select('cdgo_area', 'de_area', 'dc_area')
            ->where('in_esta', '1');
        if (!$isAdmin) {
            $query->where('cdgo_area', $areaUsuario);
        }
        return $query->orderBy('de_area')->get()->all();
    }

    public function obtenerDestinoAreasCatalogo(): array
    {
        return $this->db->table($this->schema . 'a_areas')
            ->select('cdgo_area', 'de_area', 'dc_area')
            ->where('in_esta', '1')
            ->orderBy('de_area')
            ->get()
            ->all();
    }

    public function obtenerTiposPorArea(?int $areaId): array
    {
        return $this->db->table($this->schema . 'a_tpo_trmtes as t')
            ->join($this->schema . 'r_tpo_trmte_areas as r', 't.cdgo_tpo_trmte', '=', 'r.cdgo_tpo_trmte')
            ->select('t.cdgo_tpo_trmte', 't.de_tpo_trmte', 't.dc_tpo_trmte')
            ->where('t.in_esta', '1')
            ->where('r.in_esta', '1')
            ->when($areaId && $areaId > 0, fn($q) => $q->where('r.cdgo_area', $areaId))
            ->distinct()
            ->orderBy('t.de_tpo_trmte')
            ->get()
            ->all();
    }

    public function buscarTramitesInternos(array $filtros, int $perPage = 36, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->db->table($this->schema . 'r_trmtes_ntrnos as r_trmtes_ntrnos')
            ->join($this->schema . 'a_estdos_trmtes as a_estdos_trmtes', 'r_trmtes_ntrnos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($this->schema . 'p_dtos_ntrnos', 'r_trmtes_ntrnos.cdgo_dtos_ntrnos', '=', 'p_dtos_ntrnos.cdgo_dtos_ntrnos')
            ->join($this->schema . 'a_tpo_trmtes', 'p_dtos_ntrnos.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($this->schema . 'a_areas as origen', 'p_dtos_ntrnos.cdgo_area', '=', 'origen.cdgo_area')
            ->leftJoin($this->schema . 'p_crgos as crgo_en', 'r_trmtes_ntrnos.cdgo_crgo_en', '=', 'crgo_en.cdgo_crgo')
            ->leftJoin($this->schema . 'p_crgos as crgo_sa', 'r_trmtes_ntrnos.cdgo_crgo_sa', '=', 'crgo_sa.cdgo_crgo')
            ->leftJoin($this->schema . 'a_areas as destino', 'crgo_en.cdgo_area', '=', 'destino.cdgo_area')
            ->select(
                'p_dtos_ntrnos.cdgo_dtos_ntrnos',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_tpo_trmtes.dc_tpo_trmte',
                'p_dtos_ntrnos.nu_tram_todo',
                'p_dtos_ntrnos.fe_ingrso',
                'p_dtos_ntrnos.de_obser',
                'p_dtos_ntrnos.de_urls_adjnto',
                'origen.de_area as area_origen',
                'origen.dc_area as dc_area_origen',
                'destino.de_area as area_destino',
                'destino.dc_area as dc_area_destino',
                'p_dtos_ntrnos.cdgo_dto_trmte',
                'p_dtos_ntrnos.cdgo_dtos_ntrnos_pri'
            )
            ->where('p_dtos_ntrnos.in_esta', '1');

        $cdgoArea = (int) ($filtros['cdgo_area'] ?? 0);
        if ($cdgoArea > 0) {
            $query->where(function ($q) use ($cdgoArea) {
                $q->where('crgo_en.cdgo_area', $cdgoArea)
                    ->orWhere('crgo_sa.cdgo_area', $cdgoArea);
            });
        }

        if (!empty($filtros['sel_cdgo_area_en'])) {
            $query->where('crgo_en.cdgo_area', (int) $filtros['sel_cdgo_area_en']);
        }

        if (!empty($filtros['sel_cdgo_tpo_trmte']) && (int) $filtros['sel_cdgo_tpo_trmte'] > 0) {
            $query->where('p_dtos_ntrnos.cdgo_tpo_trmte', (int) $filtros['sel_cdgo_tpo_trmte']);
        }

        if (!empty($filtros['txt_nu_tram_todo'])) {
            $query->where('p_dtos_ntrnos.nu_tram_todo', trim((string) $filtros['txt_nu_tram_todo']));
        }

        if (!empty($filtros['T1']) && !empty($filtros['T2'])) {
            $query->where('p_dtos_ntrnos.fe_ingrso', '>=', $filtros['T1'] . ' 00:00:00')
                ->where('p_dtos_ntrnos.fe_ingrso', '<=', $filtros['T2'] . ' 23:59:59');
        }

        $query->orderBy('p_dtos_ntrnos.fe_ingrso', 'desc');

        $groupCols = [
            'p_dtos_ntrnos.cdgo_dtos_ntrnos',
            'a_tpo_trmtes.de_tpo_trmte',
            'a_tpo_trmtes.dc_tpo_trmte',
            'p_dtos_ntrnos.nu_tram_todo',
            'p_dtos_ntrnos.fe_ingrso',
            'p_dtos_ntrnos.de_obser',
            'p_dtos_ntrnos.de_urls_adjnto',
            'origen.de_area',
            'origen.dc_area',
            'destino.de_area',
            'destino.dc_area',
            'p_dtos_ntrnos.cdgo_dto_trmte',
            'p_dtos_ntrnos.cdgo_dtos_ntrnos_pri',
        ];

        return $query->groupBy($groupCols)->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function obtenerDetalleTramite(int $id): ?object
    {
        return $this->db->table($this->schema . 'p_dtos_ntrnos as p_dtos_ntrnos')
            ->join($this->schema . 'a_tpo_trmtes as a_tpo_trmtes', 'p_dtos_ntrnos.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($this->schema . 'a_areas as origen', 'p_dtos_ntrnos.cdgo_area', '=', 'origen.cdgo_area')
            ->leftJoin($this->schema . 'r_trmtes_ntrnos as r', 'r.cdgo_dtos_ntrnos', '=', 'p_dtos_ntrnos.cdgo_dtos_ntrnos')
            ->leftJoin($this->schema . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->where('p_dtos_ntrnos.cdgo_dtos_ntrnos', $id)
            ->select(
                'p_dtos_ntrnos.cdgo_dtos_ntrnos',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_tpo_trmtes.dc_tpo_trmte',
                'p_dtos_ntrnos.nu_tram_todo',
                'p_dtos_ntrnos.fe_ingrso',
                'origen.de_area as area_origen',
                'origen.dc_area as dc_area_origen',
                'p_dtos_ntrnos.de_obser',
                'p_dtos_ntrnos.de_urls_adjnto',
                'e.dc_estdo_trmte as estado'
            )
            ->orderBy('r.cdgo_trmtes_ntrnos', 'desc')
            ->first();
    }

    public function obtenerHistorialMovimientos(int $id): array
    {
        $sql = "SELECT r_trmtes_ntrnos.cdgo_trmtes_ntrnos, r_trmtes_ntrnos.fe_rcbdo, r_trmtes_ntrnos.de_obser_ntrnos,
                       a_areas.de_area, a_areas_1.de_area AS de_area_1, r_trmtes_ntrnos.de_urls_agrdo,
                       a_areas.dc_area, a_areas_1.dc_area AS dc_area_1, a_estdos_trmtes.dc_estdo_trmte,
                       p_dtos_ntrnos.nu_tram_todo
                FROM {$this->schema}p_crgos p_crgos
                INNER JOIN {$this->schema}a_areas a_areas ON (p_crgos.cdgo_area = a_areas.cdgo_area)
                INNER JOIN {$this->schema}r_trmtes_ntrnos r_trmtes_ntrnos ON (r_trmtes_ntrnos.cdgo_crgo_sa = p_crgos.cdgo_crgo)
                INNER JOIN {$this->schema}p_dtos_ntrnos p_dtos_ntrnos ON (r_trmtes_ntrnos.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos)
                INNER JOIN {$this->schema}a_estdos_trmtes a_estdos_trmtes ON (r_trmtes_ntrnos.cdgo_estdo_trmte = a_estdos_trmtes.cdgo_estdo_trmte)
                INNER JOIN {$this->schema}p_crgos p_crgos_1 ON (r_trmtes_ntrnos.cdgo_crgo_en = p_crgos_1.cdgo_crgo)
                INNER JOIN {$this->schema}a_areas a_areas_1 ON (p_crgos_1.cdgo_area = a_areas_1.cdgo_area)
                WHERE p_dtos_ntrnos.cdgo_dtos_ntrnos = ?
                ORDER BY r_trmtes_ntrnos.cdgo_trmtes_ntrnos DESC";

        return $this->db->select($sql, [$id]);
    }

    public function obtenerAniosCatalogo(): array
    {
        return $this->db->table($this->schema . 'a_anios')
            ->select('cdgo_anio', 'de_anio')
            ->orderByDesc('de_anio')
            ->get()
            ->all();
    }

    public function obtenerTiposSecuencialCatalogo(): array
    {
        return $this->db->table($this->schema . 'a_tpo_trmtes as t')
            ->select('t.cdgo_tpo_trmte', 't.de_tpo_trmte', 't.dc_tpo_trmte')
            ->where('t.in_esta', '1')
            ->where('t.in_scncal', 0)
            ->orderBy('t.de_tpo_trmte')
            ->get()
            ->all();
    }

    public function obtenerInScncalTipo(int $cdgoTpoTrmte): int
    {
        return (int) ($this->db->table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $cdgoTpoTrmte)
            ->value('in_scncal') ?? 0);
    }

    public function obtenerListaSecuenciales(int $cdgoAnio, int $cdgoTpoTrmte, int $areaId, int $cargoId, int $inScncal): array
    {
        $query = $this->db->table($this->schema . 'r_scncal_tpo_trmtes_area as r')
            ->join($this->schema . 'a_tpo_trmtes as t', 'r.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->join($this->schema . 'a_anios as a', 'r.cdgo_anio', '=', 'a.cdgo_anio')
            ->leftJoin($this->schema . 'a_areas as ar', 'r.cdgo_area', '=', 'ar.cdgo_area')
            ->leftJoin($this->schema . 'p_crgos as c', 'r.cdgo_crgo', '=', 'c.cdgo_crgo')
            ->select([
                'r.cdgo_scncal_tpo_trmtes_area',
                'r.nu_smlla_intrno',
                't.de_tpo_trmte',
                'a.de_anio',
                'ar.de_area',
                'c.de_crgo',
            ])
            ->where('r.cdgo_anio', $cdgoAnio)
            ->where('r.cdgo_tpo_trmte', $cdgoTpoTrmte);

        if ($inScncal === 1) {
            $query->where('r.cdgo_crgo', $cargoId);
        } else {
            $query->where('r.cdgo_area', $areaId);
        }

        return $query->orderBy('r.nu_smlla_intrno', 'asc')->get()->all();
    }

    public function obtenerMaxSecuencial(int $cdgoAnio, int $cdgoTpoTrmte, int $areaId, int $cargoId, int $inScncal): int
    {
        $maxQuery = $this->db->table($this->schema . 'r_scncal_tpo_trmtes_area')
            ->where('cdgo_anio', $cdgoAnio)
            ->where('cdgo_tpo_trmte', $cdgoTpoTrmte);

        if ($inScncal === 1) {
            $maxQuery->where('cdgo_crgo', $cargoId);
        } else {
            $maxQuery->where('cdgo_area', $areaId);
        }

        return (int) ($maxQuery->max('nu_smlla_intrno') ?? 0);
    }

    public function insertarSecuencial(array $datos): void
    {
        $this->db->table($this->schema . 'r_scncal_tpo_trmtes_area')->insert($datos);
    }

    public function obtenerCargosPorArea(int $areaId): array
    {
        return $this->db->table($this->schema . 'p_crgos')
            ->select('cdgo_crgo', 'de_crgo')
            ->where('cdgo_area', $areaId)
            ->where('in_esta', '1')
            ->orderBy('de_crgo')
            ->get()
            ->all();
    }

    public function obtenerAreaUsuarioPorCargo(int $cargoId): int
    {
        return (int) ($this->db->table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('cdgo_area') ?? 0);
    }

    public function contarReporteEntrada(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null, ?int $cargoSa = null): int
    {
        $q = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('r.cdgo_crgo_en', $cargoId)
            ->where('r.cdgo_estdo_trmte', 1)
            ->whereBetween('r.fe_rcbdo', [$desde, $hasta]);
        if ($tipoTramite && $tipoTramite > 0) {
            $q->where('p.cdgo_tpo_trmte', $tipoTramite);
        }
        if ($cargoSa && $cargoSa > 0) {
            $q->where('r.cdgo_crgo_sa', $cargoSa);
        }
        return (int) $q->count();
    }

    public function contarReporteRecibidos(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null, ?int $cargoSa = null): int
    {
        $q = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('r.cdgo_crgo_en', $cargoId)
            ->whereIn('r.cdgo_estdo_trmte', [2, 3])
            ->whereBetween('r.fe_rcbdo', [$desde, $hasta]);
        if ($tipoTramite && $tipoTramite > 0) {
            $q->where('p.cdgo_tpo_trmte', $tipoTramite);
        }
        if ($cargoSa && $cargoSa > 0) {
            $q->where('r.cdgo_crgo_sa', $cargoSa);
        }
        return (int) $q->count();
    }

    public function contarReporteSalida(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null): int
    {
        $q = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('r.cdgo_crgo_sa', $cargoId)
            ->whereBetween('r.fe_rcbdo', [$desde, $hasta]);
        if ($tipoTramite && $tipoTramite > 0) {
            $q->where('p.cdgo_tpo_trmte', $tipoTramite);
        }
        return (int) $q->count();
    }

    public function contarReporteFinalizado(int $cargoId, string $desde, string $hasta, ?int $tipoTramite = null, ?int $cargoSa = null): int
    {
        $q = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('r.cdgo_crgo_en', $cargoId)
            ->where('r.cdgo_estdo_trmte', 4)
            ->whereBetween('r.fe_rcbdo', [$desde, $hasta]);
        if ($tipoTramite && $tipoTramite > 0) {
            $q->where('p.cdgo_tpo_trmte', $tipoTramite);
        }
        if ($cargoSa && $cargoSa > 0) {
            $q->where('r.cdgo_crgo_sa', $cargoSa);
        }
        return (int) $q->count();
    }

    public function obtenerUsuariosDeCargo(int $cargoId): array
    {
        return $this->db->table("{$this->schema}r_crgos_lgin as rcl")
            ->join("{$this->schema}p_lgin as l", 'rcl.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->join("{$this->schema}p_usrios as u", 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('rcl.cdgo_crgo', $cargoId)
            ->select('l.cdgo_lgin', 'u.no_crto', 'l.de_lgin')
            ->get()
            ->all();
    }

    public function obtenerDetalleReporteCargoInterno(array $filtros, int $perPage, int $page): array
    {
        $baseQuery = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->join("{$this->schema}a_tpo_trmtes as t", 'p.cdgo_tpo_trmte', '=', 't.cdgo_tpo_trmte')
            ->join("{$this->schema}a_estdos_trmtes as e", 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$this->schema}a_areas as a_sa", 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->leftJoin("{$this->schema}a_areas as a_en", 'c_en.cdgo_area', '=', 'a_en.cdgo_area');

        $tipo = $filtros['tipo'] ?? 'entrada';
        $cargoId = (int) ($filtros['cargo_id'] ?? 0);
        $fechaDesde = $filtros['fecha_desde'] ?? date('Y-m-d');
        $fechaHasta = $filtros['fecha_hasta'] ?? date('Y-m-d');

        if ($tipo === 'salida') {
            $baseQuery->where('r.cdgo_crgo_sa', $cargoId);
        } else {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId);
            if ($tipo === 'entrada') {
                $baseQuery->where('r.cdgo_estdo_trmte', 1);
            } elseif ($tipo === 'recibidos') {
                $baseQuery->whereIn('r.cdgo_estdo_trmte', [2, 3]);
            } elseif ($tipo === 'finalizado') {
                $baseQuery->where('r.cdgo_estdo_trmte', 4);
            }
        }

        $baseQuery->whereBetween('r.fe_rcbdo', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59']);

        if (!empty($filtros['cdgo_tpo_trmte'])) {
            $baseQuery->where('p.cdgo_tpo_trmte', (int) $filtros['cdgo_tpo_trmte']);
        }
        if (!empty($filtros['cdgo_crgo_sa'])) {
            $baseQuery->where('r.cdgo_crgo_sa', (int) $filtros['cdgo_crgo_sa']);
        }

        $total = $baseQuery->count();
        $rows = $baseQuery->select([
            'r.cdgo_trmtes_ntrnos',
            'p.cdgo_dtos_ntrnos',
            'p.nu_tram_todo',
            't.de_tpo_trmte',
            'r.fe_rcbdo',
            'e.de_estdo_trmte',
            'e.dc_estdo_trmte',
            'c_sa.de_crgo as cargo_origen',
            'a_sa.de_area as area_origen',
            'c_en.de_crgo as cargo_destino',
            'a_en.de_area as area_destino',
            'r.de_obser_ntrnos',
            'r.de_urls_agrdo',
        ])
        ->orderByDesc('r.fe_rcbdo')
        ->forPage($page, $perPage)
        ->get()
        ->all();

        return [
            'total' => $total,
            'data' => $rows,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function obtenerEvolucionReporteInterno(array $cargosIds, string $desde, string $hasta): array
    {
        if (empty($cargosIds)) {
            return ['evolucion' => [], 'tiempos' => null];
        }

        $cargosStr = implode(',', array_map('intval', $cargosIds));
        $sqlEvo = "SELECT date_trunc('day', r.fe_rcbdo) as dia, count(*) as total
                   FROM {$this->schema}r_trmtes_ntrnos r
                   WHERE r.cdgo_crgo_en IN ({$cargosStr})
                     AND r.fe_rcbdo BETWEEN ? AND ?
                   GROUP BY dia ORDER BY dia";

        $resEvo = $this->db->select($sqlEvo, [$desde . ' 00:00:00', $hasta . ' 23:59:59']);

        $sqlTime = "SELECT avg(EXTRACT(EPOCH FROM (r.fe_rcbdo - p.fe_ingrso))/3600) as avg_horas
                    FROM {$this->schema}r_trmtes_ntrnos r
                    JOIN {$this->schema}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
                    WHERE r.cdgo_crgo_en IN ({$cargosStr})
                      AND r.fe_rcbdo BETWEEN ? AND ?";

        $resTime = $this->db->selectOne($sqlTime, [$desde . ' 00:00:00', $hasta . ' 23:59:59']);

        return [
            'evolucion' => $resEvo,
            'tiempos' => $resTime,
        ];
    }

    public function obtenerReporteTramitesPorAreas(string $desde, string $hasta): array
    {
        $q1Sql = "SELECT a.cdgo_area, a.de_area, count(r.cdgo_trmtes_ntrnos) as total_enviados
                  FROM {$this->schema}a_areas a
                  LEFT JOIN {$this->schema}p_crgos c ON a.cdgo_area = c.cdgo_area
                  LEFT JOIN {$this->schema}r_trmtes_ntrnos r ON c.cdgo_crgo = r.cdgo_crgo_sa
                     AND r.fe_rcbdo BETWEEN ? AND ?
                  WHERE a.in_esta = '1'
                  GROUP BY a.cdgo_area, a.de_area
                  ORDER BY a.de_area";

        $q2Sql = "SELECT a.cdgo_area, a.de_area, count(r.cdgo_trmtes_ntrnos) as total_recibidos
                  FROM {$this->schema}a_areas a
                  LEFT JOIN {$this->schema}p_crgos c ON a.cdgo_area = c.cdgo_area
                  LEFT JOIN {$this->schema}r_trmtes_ntrnos r ON c.cdgo_crgo = r.cdgo_crgo_en
                     AND r.fe_rcbdo BETWEEN ? AND ?
                  WHERE a.in_esta = '1'
                  GROUP BY a.cdgo_area, a.de_area
                  ORDER BY a.de_area";

        $desdeSql = $desde . ' 00:00:00';
        $hastaSql = $hasta . ' 23:59:59';

        $q1Results = collect($this->db->select($q1Sql, [$desdeSql, $hastaSql]))->keyBy('cdgo_area');
        $q2Results = collect($this->db->select($q2Sql, [$desdeSql, $hastaSql]))->keyBy('cdgo_area');

        return [
            'enviados' => $q1Results,
            'recibidos' => $q2Results,
        ];
    }

    public function contarReporteCargoEntrada(int $cdgo, string $desde, string $hasta): int
    {
        return (int) $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where(function ($q) use ($cdgo) {
                $q->whereNull('c_sa.cdgo_crgo')
                    ->orWhere('c_sa.cdgo_crgo', '!=', $cdgo);
            })
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27'])
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    public function contarReporteCargoRecibidos(int $cdgo, string $desde, string $hasta): int
    {
        return (int) $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['1', '3', '24'])
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    public function contarReporteCargoSalida(int $cdgo, string $desde, string $hasta): int
    {
        return (int) $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('c_en.cdgo_crgo', '!=', $cdgo)
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    public function contarReporteCargoFinalizado(int $cdgo, string $desde, string $hasta): int
    {
        return (int) $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->join("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('r.in_esta', '1')
            ->whereIn('r.cdgo_estdo_trmte', ['3', '24'])
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    public function obtenerTramitesReporteCargoDetalle(int $cargoId, int $tipoBandeja, string $desde, string $hasta): array
    {
        $baseQuery = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->select([
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.fe_ingrso',
                'r.fe_rcbdo',
                'p.de_obser',
                'p.de_urls_adjnto',
                'te.de_tpo_trmte',
                'te.dc_tpo_trmte',
                'a_inco.de_area as de_area_inco',
                'a_inco.dc_area as dc_area_inco',
            ])
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->join("{$this->schema}p_tpo_trmte as te", 'p.cdgo_tpo_trmte', '=', 'te.cdgo_tpo_trmte')
            ->join("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$this->schema}a_areas as a_inco", 'p.cdgo_area', '=', 'a_inco.cdgo_area')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingrso', [$desde, $hasta]);

        if ($tipoBandeja === 1) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where(function ($q) use ($cargoId) {
                    $q->whereNull('r.cdgo_crgo_sa')->orWhere('r.cdgo_crgo_sa', '!=', $cargoId);
                })
                ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']);
        } elseif ($tipoBandeja === 2) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['1', '3', '24']);
        } elseif ($tipoBandeja === 3) {
            $baseQuery->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', '!=', $cargoId);
        } elseif ($tipoBandeja === 4) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId)
                ->whereIn('r.cdgo_estdo_trmte', ['3', '24']);
        }

        return $baseQuery
            ->orderBy('te.dc_tpo_trmte')
            ->orderBy('p.fe_ingrso')
            ->get()
            ->all();
    }

    public function obtenerReporteTramitesPorAreasDetallado(string $fechaDesde, string $fechaHasta): array
    {
        $areas = $this->db->table($this->schema . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_area', '!=', 0)
            ->orderBy('de_area')
            ->get(['cdgo_area', 'de_area'])
            ->keyBy('cdgo_area');

        $q1Sql = "
            SELECT
                c_en.cdgo_area,
                SUM(CASE WHEN (a_sa.cdgo_area IS NULL OR a_sa.cdgo_area != c_en.cdgo_area)
                          AND r.cdgo_estdo_trmte NOT IN ('5','12','29','27') THEN 1 ELSE 0 END) AS por_recibir,
                SUM(CASE WHEN a_sa.cdgo_area = c_en.cdgo_area
                          AND r.cdgo_estdo_trmte NOT IN ('1','3','24') THEN 1 ELSE 0 END) AS recibidos,
                SUM(CASE WHEN a_sa.cdgo_area = c_en.cdgo_area
                          AND r.cdgo_estdo_trmte IN ('3','24') THEN 1 ELSE 0 END) AS finalizados
            FROM {$this->schema}r_trmtes_ntrnos r
            INNER JOIN {$this->schema}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            LEFT JOIN {$this->schema}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$this->schema}a_areas a_sa ON c_sa.cdgo_area = a_sa.cdgo_area
            INNER JOIN {$this->schema}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE r.in_esta = '1'
              AND p.fe_ingrso >= ? AND p.fe_ingrso <= ?
            GROUP BY c_en.cdgo_area
        ";

        $q1Results = collect($this->db->select($q1Sql, [$fechaDesde, $fechaHasta]))->keyBy('cdgo_area');

        $q2Sql = "
            SELECT
                c_sa.cdgo_area,
                COUNT(*) AS derivados
            FROM {$this->schema}r_trmtes_ntrnos r
            INNER JOIN {$this->schema}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            INNER JOIN {$this->schema}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
            INNER JOIN {$this->schema}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            INNER JOIN {$this->schema}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE r.in_esta = '1'
              AND a_en.cdgo_area != c_sa.cdgo_area
              AND p.fe_ingrso >= ? AND p.fe_ingrso <= ?
            GROUP BY c_sa.cdgo_area
        ";

        $q2Results = collect($this->db->select($q2Sql, [$fechaDesde, $fechaHasta]))->keyBy('cdgo_area');

        return [
            'areas' => $areas,
            'q1' => $q1Results,
            'q2' => $q2Results,
        ];
    }

    public function obtenerTramitesReporteAreaDetalle(int $areaId, int $tipo, string $fechaDesde, string $fechaHasta): array
    {
        $baseQuery = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->select([
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.fe_ingrso',
                'r.fe_rcbdo',
                'p.de_obser',
                'p.de_urls_adjnto',
                'te.de_tpo_trmte',
                'te.dc_tpo_trmte',
                'a_inco.de_area as de_area_inco',
                'a_inco.dc_area as dc_area_inco',
            ])
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->join("{$this->schema}a_tpo_trmtes as te", 'p.cdgo_tpo_trmte', '=', 'te.cdgo_tpo_trmte')
            ->join("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->join("{$this->schema}a_areas as a_en", 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin("{$this->schema}a_areas as a_sa", 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->join("{$this->schema}a_areas as a_inco", 'p.cdgo_area', '=', 'a_inco.cdgo_area')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingrso', [$fechaDesde, $fechaHasta]);

        if ($tipo === 1) {
            $baseQuery->where('a_en.cdgo_area', $areaId)
                ->where(function ($q) use ($areaId) {
                    $q->whereNull('a_sa.cdgo_area')->orWhere('a_sa.cdgo_area', '!=', $areaId);
                })
                ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']);
        } elseif ($tipo === 2) {
            $baseQuery->where('a_en.cdgo_area', $areaId)
                ->where('a_sa.cdgo_area', $areaId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['1', '3', '24']);
        } elseif ($tipo === 3) {
            $baseQuery->where('a_sa.cdgo_area', $areaId)
                ->where('a_en.cdgo_area', '!=', $areaId);
        } elseif ($tipo === 4) {
            $baseQuery->where('a_en.cdgo_area', $areaId)
                ->where('a_sa.cdgo_area', $areaId)
                ->whereIn('r.cdgo_estdo_trmte', ['3', '24']);
        }

        return $baseQuery
            ->orderBy('te.dc_tpo_trmte')
            ->orderBy('p.fe_ingrso')
            ->get()
            ->all();
    }

    public function obtenerV2Evolucion(int $areaId, string $desde, string $hasta): array
    {
        $sqlEvo = "
            SELECT
                EXTRACT(YEAR FROM p.fe_ingrso)::int  AS anio,
                EXTRACT(MONTH FROM p.fe_ingrso)::int AS mes,
                SUM(CASE WHEN r.cdgo_estdo_trmte IN ('3','24') THEN 1 ELSE 0 END) AS finalizados,
                SUM(CASE WHEN r.cdgo_estdo_trmte IN ('1','2') THEN 1 ELSE 0 END) AS pendientes
            FROM {$this->schema}r_trmtes_ntrnos r
            INNER JOIN {$this->schema}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$this->schema}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE c.cdgo_area = ?
              AND r.in_esta = '1'
              AND p.fe_ingrso >= ?
              AND p.fe_ingrso <= ?
            GROUP BY anio, mes
            ORDER BY anio ASC, mes ASC
            LIMIT 19
        ";

        return $this->db->select($sqlEvo, [$areaId, $desde, $hasta]);
    }

    public function obtenerV2AvgTiempo(int $areaId, string $desde, string $hasta): ?float
    {
        $sqlTime = "
            SELECT AVG(EXTRACT(DAY FROM (r.fe_rcbdo - p.fe_ingrso))) as avg_time
            FROM {$this->schema}r_trmtes_ntrnos r
            INNER JOIN {$this->schema}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$this->schema}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE c.cdgo_area = ?
              AND r.cdgo_estdo_trmte IN ('3','24')
              AND r.in_esta = '1'
              AND r.fe_rcbdo IS NOT NULL
              AND p.fe_ingrso >= ? AND p.fe_ingrso <= ?
        ";
        $resTime = $this->db->selectOne($sqlTime, [$areaId, $desde, $hasta]);
        return $resTime && $resTime->avg_time !== null ? (float) $resTime->avg_time : null;
    }

    public function obtenerV2TramitesDetalle(int $cargoId, int $tipoBandeja, string $desde, string $hasta): array
    {
        $baseQuery = $this->db->table("{$this->schema}r_trmtes_ntrnos as r")
            ->select([
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.fe_ingrso',
                'r.fe_rcbdo',
                'p.de_obser',
                'p.de_urls_adjnto',
                'te.de_tpo_trmte',
                'te.dc_tpo_trmte',
                'a_inco.de_area as de_area_inco',
                'a_inco.dc_area as dc_area_inco',
            ])
            ->join("{$this->schema}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->join("{$this->schema}p_tpo_trmte as te", 'p.cdgo_tpo_trmte', '=', 'te.cdgo_tpo_trmte')
            ->join("{$this->schema}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$this->schema}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$this->schema}a_areas as a_inco", 'p.cdgo_area', '=', 'a_inco.cdgo_area')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingrso', [$desde, $hasta]);

        if ($tipoBandeja === 1) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where(function ($q) use ($cargoId) {
                    $q->whereNull('r.cdgo_crgo_sa')->orWhere('r.cdgo_crgo_sa', '!=', $cargoId);
                })
                ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']);
        } elseif ($tipoBandeja === 2) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['1', '3', '24']);
        } elseif ($tipoBandeja === 3) {
            $baseQuery->where('r.cdgo_crgo_sa', $cargoId)
                ->where('r.cdgo_crgo_en', '!=', $cargoId);
        } elseif ($tipoBandeja === 4) {
            $baseQuery->where('r.cdgo_crgo_en', $cargoId)
                ->where('r.cdgo_crgo_sa', $cargoId)
                ->whereIn('r.cdgo_estdo_trmte', ['3', '24']);
        }

        return $baseQuery->orderBy('p.fe_ingrso', 'desc')->get()->all();
    }

    public function obtenerRutaAreasPorDocumento(int $documentoId): array
    {
        return $this->db->table("{$this->schema}r_trmtes_ntrnos as r2")
            ->join("{$this->schema}p_crgos as c", 'r2.cdgo_crgo_en', '=', 'c.cdgo_crgo')
            ->join("{$this->schema}a_areas as a", 'c.cdgo_area', '=', 'a.cdgo_area')
            ->where('r2.cdgo_dtos_ntrnos', $documentoId)
            ->orderBy('r2.cdgo_trmtes_ntrnos')
            ->pluck('a.dc_area')
            ->all();
    }
}

