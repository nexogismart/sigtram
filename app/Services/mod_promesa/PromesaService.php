<?php

namespace App\Services\mod_promesa;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lógica de negocio del módulo "Audiencias Públicas" (Promesas/Compromisos).
 *
 * Replica el comportamiento del módulo antiguo Presentacion/mod_promesa, usando
 * las siguientes tablas de PostgreSQL (conexión por defecto de la aplicación):
 *
 *  - externo.datos_zona            (reuniones / lugares de audiencia)
 *  - externo.datos_zona_semaforo   (compromisos / promesas)
 *  - externo.datos_zona_seguimiento(actividades de seguimiento de cada promesa)
 *  - externo.datos_zona_usuario    (responsables/personal asignado por área)
 *  - sistema.a_areas               (catálogo de áreas/unidades orgánicas)
 *  - sistema.p_usrios              (usuarios del sistema, solo para notificación API)
 *
 * Todos los métodos públicos devuelven un arreglo con la forma:
 *  ['success' => bool, 'error' => string (si success=false), 'data' => mixed (si aplica)]
 */
class PromesaService
{
    private const TBL_ZONA = 'externo.datos_zona';

    private const TBL_SEMAFORO = 'externo.datos_zona_semaforo';

    private const TBL_SEGUIMIENTO = 'externo.datos_zona_seguimiento';

    private const TBL_ZONA_USUARIO = 'externo.datos_zona_usuario';

    private const TBL_AREAS = 'sistema.a_areas';

    private const TBL_USUARIOS = 'sistema.p_usrios';

    private const TZ = 'America/Lima';

    private const API_NOTIFICACION = 'https://denuncias.munipuentepiedra.gob.pe/api/notificacion-evento-administrado';

    /**
     * Áreas que en el sistema antiguo solo tenían acceso de lectura filtrada
     * (accessUsr = 0). Cualquier otra área tiene acceso pleno (accessUsr = 1)
     * y puede crear/editar/eliminar reuniones y promesas.
     */
    private const AREAS_LECTURA = [
        1, 2, 3, 4, 5, 7, 8, 9, 10, 15, 17, 33, 36, 37, 38, 40, 42, 60, 64, 65,
        74, 80, 82, 87, 89, 91, 94, 95, 96, 97, 98, 99, 100, 101, 105, 106,
    ];

    /**
     * Campos permitidos para el filtro de "palabra" en listarPromesas().
     * Clave = alias público, Valor = columna real calificada con su tabla.
     */
    private const CAMPOS_BUSQUEDA_PROMESA = [
        'sem_nom_dirigente' => 'semaforo.sem_nom_dirigente',
        'sem_desc_pedido' => 'semaforo.sem_desc_pedido',
        'de_area' => 'area.de_area',
    ];

    // ── 1. Acceso ──────────────────────────────────────────────────────────

    /**
     * Determina si un área tiene acceso pleno (CRUD) o solo lectura filtrada
     * por su propia área, replicando el arreglo "$asignados" del módulo antiguo.
     */
    public function esAccesoPleno(int $areaId): bool
    {
        return ! in_array($areaId, self::AREAS_LECTURA, true);
    }

    // ── 2. Lugares / Reuniones ───────────────────────────────────────────

    /**
     * Lista los lugares/reuniones activos (zona_estado = '1'), con búsqueda
     * opcional por nombre, filtro opcional por rango de fechas y el resumen
     * de avance de promesas de cada lugar (total / finalizado / porcentaje).
     *
     * @param  string  $fechaDesde  Formato dd/mm/yyyy o yyyy-mm-dd.
     * @param  string  $fechaHasta  Formato dd/mm/yyyy o yyyy-mm-dd.
     */
    public function listarLugares(
        string $palabra = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        int $pagina = 1,
        int $porPagina = 8
    ): array {
        try {
            $pagina = max(1, $pagina);
            $porPagina = max(1, $porPagina);

            $query = DB::table(self::TBL_ZONA)->where('zona_estado', '1');

            $palabra = trim($palabra);
            if ($palabra !== '') {
                $query->where('zona_nombre', 'ILIKE', '%'.$palabra.'%');
            }

            $desde = $this->fechaEeuu($fechaDesde);
            $hasta = $this->fechaEeuu($fechaHasta);
            if ($desde !== null && $hasta !== null) {
                $query->whereRaw('fecha_rnn >= ?::timestamp', [$desde.' 00:00:00']);
                $query->whereRaw('fecha_rnn <= ?::timestamp', [$hasta.' 23:59:59']);
            }

            $total = (clone $query)->count();

            $rows = (clone $query)
                ->orderByDesc('cdgo_datos_zona')
                ->limit($porPagina)
                ->offset(($pagina - 1) * $porPagina)
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $progreso = $this->progresoLugar((int) $row->cdgo_datos_zona);
                $longLat = trim((string) ($row->zona_long_lat ?? ''));

                $items[] = [
                    'id' => (int) $row->cdgo_datos_zona,
                    'zona_nombre' => $row->zona_nombre,
                    'zona_long_lat' => $row->zona_long_lat,
                    'fecha_rnn' => $this->fechaPeru($row->fecha_rnn),
                    'hora_rnn' => $this->formatearHora($row->hora_rnn),
                    'total' => $progreso['total'],
                    'finalizado' => $progreso['finalizado'],
                    'porcentaje' => $progreso['porcentaje'],
                    'maps_url' => $longLat !== '' ? ('https://www.google.com/maps/@'.$longLat.',18z?entry=ttu') : null,
                ];
            }

            return $this->ok([
                'items' => $items,
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total_paginas' => max(1, (int) ceil($total / $porPagina)),
            ]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] listarLugares: '.$e->getMessage());

            return $this->fail('No se pudo listar los lugares.');
        }
    }

    /**
     * Detalle del avance de promesas de una reunión, agrupado por área
     * (equivalente a ajax_detalles.php del módulo antiguo).
     */
    public function detallePorcentajeAreas(int $idReunion): array
    {
        try {
            $areas = DB::table(self::TBL_SEMAFORO)
                ->select('sem_cdgo_uni_organica')
                ->distinct()
                ->where('cdgo_datos_zona', $idReunion)
                ->whereRaw("sem_estado = B'1'")
                ->orderBy('sem_cdgo_uni_organica')
                ->get();

            $items = [];
            foreach ($areas as $area) {
                $areaId = (int) $area->sem_cdgo_uni_organica;

                $rows = $this->promesaQuery()
                    ->where('semaforo.cdgo_datos_zona', $idReunion)
                    ->where('semaforo.sem_cdgo_uni_organica', $areaId)
                    ->select('area.dc_area', 'area.de_area', 'semaforo.sem_fec_cumplimiento')
                    ->get();

                $total = $rows->count();
                $finalizados = $rows->filter(fn ($r) => ! empty($r->sem_fec_cumplimiento))->count();
                $porcentaje = $finalizados !== 0 ? round($finalizados / $total * 100, 1) : 0.0;

                $items[] = [
                    'area_id' => $areaId,
                    'dc_area' => $rows->first()->dc_area ?? '',
                    'de_area' => $rows->first()->de_area ?? '',
                    'total' => $total,
                    'finalizados' => $finalizados,
                    'porcentaje' => $porcentaje,
                ];
            }

            return $this->ok($items);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] detallePorcentajeAreas: '.$e->getMessage());

            return $this->fail('No se pudo obtener el detalle por áreas.');
        }
    }

    /**
     * Obtiene un lugar/reunión por su id (solo si está activo).
     */
    public function obtenerLugar(int $id): array
    {
        try {
            $row = DB::table(self::TBL_ZONA)
                ->where('cdgo_datos_zona', $id)
                ->where('zona_estado', '1')
                ->first();

            if (! $row) {
                return $this->fail('Lugar no encontrado.');
            }

            return $this->ok([
                'id' => (int) $row->cdgo_datos_zona,
                'zona_nombre' => $row->zona_nombre,
                'zona_long_lat' => $row->zona_long_lat,
                'zona_documento' => $row->zona_documento,
                'fecha_rnn' => $row->fecha_rnn ? Carbon::parse($row->fecha_rnn)->format('Y-m-d') : null,
                'hora_rnn' => $this->formatearHora($row->hora_rnn),
            ]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] obtenerLugar: '.$e->getMessage());

            return $this->fail('No se pudo obtener el lugar.');
        }
    }

    /**
     * Crea un nuevo lugar/reunión.
     *
     * @param  array{zona_nombre:string, zona_long_lat?:string, fecha_rnn:string, hora_rnn?:string}  $datos
     */
    public function guardarLugar(array $datos, int $usuarioId): array
    {
        try {
            $nombre = trim((string) ($datos['zona_nombre'] ?? ''));
            if ($nombre === '') {
                return $this->fail('El nombre/ubicación es obligatorio.');
            }

            $fecha = $this->fechaEeuu((string) ($datos['fecha_rnn'] ?? ''));
            if ($fecha === null) {
                return $this->fail('La fecha de la reunión es obligatoria y debe ser válida.');
            }

            $hora = trim((string) ($datos['hora_rnn'] ?? '')) ?: '00:00';

            $id = DB::table(self::TBL_ZONA)->insertGetId([
                'zona_nombre' => $nombre,
                'zona_long_lat' => trim((string) ($datos['zona_long_lat'] ?? '')),
                'zona_documento' => 'firmado.jpg',
                'zona_estado' => '1',
                'zona_cdgo_usuario_ins' => $usuarioId,
                'zona_fec_ins' => DB::raw('NOW()'),
                'fecha_rnn' => $fecha,
                'hora_rnn' => $hora,
            ], 'cdgo_datos_zona');

            return $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] guardarLugar: '.$e->getMessage());

            return $this->fail('No se pudo guardar el lugar.');
        }
    }

    /**
     * Actualiza un lugar/reunión existente. Permite adjuntar opcionalmente
     * un PDF, el cual se guarda en storage/app/promesa/adjuntos/{id}.pdf.
     *
     * @param  array{zona_nombre:string, zona_long_lat?:string, fecha_rnn:string, hora_rnn?:string}  $datos
     */
    public function actualizarLugar(int $id, array $datos, int $usuarioId, ?UploadedFile $pdf = null): array
    {
        try {
            $existe = DB::table(self::TBL_ZONA)->where('cdgo_datos_zona', $id)->exists();
            if (! $existe) {
                return $this->fail('Lugar no encontrado.');
            }

            $nombre = trim((string) ($datos['zona_nombre'] ?? ''));
            if ($nombre === '') {
                return $this->fail('El nombre/ubicación es obligatorio.');
            }

            $fecha = $this->fechaEeuu((string) ($datos['fecha_rnn'] ?? ''));
            if ($fecha === null) {
                return $this->fail('La fecha de la reunión es obligatoria y debe ser válida.');
            }

            $hora = trim((string) ($datos['hora_rnn'] ?? '')) ?: '00:00';

            $update = [
                'zona_nombre' => $nombre,
                'fecha_rnn' => $fecha,
                'hora_rnn' => $hora,
                'zona_long_lat' => trim((string) ($datos['zona_long_lat'] ?? '')),
                'zona_cdgo_usuario_upd' => $usuarioId,
                'zona_fec_upd' => DB::raw('NOW()'),
            ];

            if ($pdf !== null) {
                if (strtolower($pdf->getClientOriginalExtension()) !== 'pdf') {
                    return $this->fail('El archivo adjunto debe ser un PDF.');
                }
                $pdf->storeAs('promesa/adjuntos', $id.'.pdf');
                $update['zona_documento'] = $id.'.pdf';
            }

            DB::table(self::TBL_ZONA)->where('cdgo_datos_zona', $id)->update($update);

            return $this->ok(['id' => $id]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] actualizarLugar: '.$e->getMessage());

            return $this->fail('No se pudo actualizar el lugar.');
        }
    }

    /**
     * Elimina (lógicamente) un lugar/reunión: zona_estado = '0'.
     */
    public function eliminarLugar(int $id, int $usuarioId): array
    {
        try {
            $afectados = DB::table(self::TBL_ZONA)
                ->where('cdgo_datos_zona', $id)
                ->where('zona_estado', '1')
                ->update([
                    'zona_estado' => '0',
                    'zona_cdgo_usuario_del' => $usuarioId,
                    'zona_fec_del' => DB::raw('NOW()'),
                ]);

            if ($afectados === 0) {
                return $this->fail('Lugar no encontrado o ya eliminado.');
            }

            return $this->ok();
        } catch (Throwable $e) {
            Log::error('[mod_promesa] eliminarLugar: '.$e->getMessage());

            return $this->fail('No se pudo eliminar el lugar.');
        }
    }

    // ── 3. Promesas / Compromisos ────────────────────────────────────────

    /**
     * Lista las promesas/compromisos de una reunión, con filtro de área
     * (cuando no hay acceso pleno) y búsqueda opcional por campo permitido.
     */
    public function listarPromesas(
        int $idReunion,
        int $areaId,
        bool $accesoPleno,
        string $criterioCampo = '',
        string $palabra = '',
        int $pagina = 1,
        int $porPagina = 8
    ): array {
        try {
            $pagina = max(1, $pagina);
            $porPagina = max(1, $porPagina);

            $query = $this->promesaQuery()->where('semaforo.cdgo_datos_zona', $idReunion);

            if (! $accesoPleno) {
                $query->where('semaforo.sem_cdgo_uni_organica', $areaId);
            }

            $palabra = trim($palabra);
            if ($palabra !== '' && isset(self::CAMPOS_BUSQUEDA_PROMESA[$criterioCampo])) {
                $query->where(self::CAMPOS_BUSQUEDA_PROMESA[$criterioCampo], 'ILIKE', '%'.$palabra.'%');
            }

            $total = (clone $query)->count();

            $rows = (clone $query)
                ->select(
                    'semaforo.cdgo_semaforo',
                    'semaforo.cdgo_datos_zona',
                    'semaforo.sem_nom_dirigente',
                    'semaforo.sem_desc_pedido',
                    'area.dc_area',
                    'area.de_area',
                    'usr.no_usrios',
                    'semaforo.sem_fec_compromiso',
                    'semaforo.sem_fec_cumplimiento'
                )
                ->orderByDesc('semaforo.cdgo_semaforo')
                ->limit($porPagina)
                ->offset(($pagina - 1) * $porPagina)
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id_promesa' => (int) $row->cdgo_semaforo,
                    'id_reunion' => (int) $row->cdgo_datos_zona,
                    'dirigente' => $row->sem_nom_dirigente,
                    'pedido' => $row->sem_desc_pedido,
                    'dc_area' => $row->dc_area,
                    'de_area' => $row->de_area,
                    'no_usrios' => $row->no_usrios,
                    'fec_compromiso' => $this->fechaPeru($row->sem_fec_compromiso),
                    'fec_cumplimiento' => $row->sem_fec_cumplimiento ? $this->fechaPeru($row->sem_fec_cumplimiento) : null,
                    'cumplido' => $row->sem_fec_cumplimiento !== null,
                ];
            }

            return $this->ok([
                'items' => $items,
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total_paginas' => max(1, (int) ceil($total / $porPagina)),
            ]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] listarPromesas: '.$e->getMessage());

            return $this->fail('No se pudo listar los compromisos.');
        }
    }

    /**
     * Obtiene el detalle de una promesa (para edición / detalle).
     */
    public function obtenerPromesa(int $id): array
    {
        try {
            $row = $this->promesaQuery()
                ->where('semaforo.cdgo_semaforo', $id)
                ->select('semaforo.*', 'area.dc_area', 'area.de_area', 'usr.no_usrios')
                ->first();

            if (! $row) {
                return $this->fail('Compromiso no encontrado.');
            }

            return $this->ok([
                'id_promesa' => (int) $row->cdgo_semaforo,
                'id_reunion' => (int) $row->cdgo_datos_zona,
                'dni_dirigente' => $row->sem_doc_dirigente,
                'dirigente' => $row->sem_nom_dirigente,
                'telefono' => $row->sem_contact_dirigente,
                'pedido' => $row->sem_desc_pedido,
                'area_id' => (int) $row->sem_cdgo_uni_organica,
                'dc_area' => $row->dc_area,
                'de_area' => $row->de_area,
                'responsable_id' => $row->sem_cdgo_responsable !== null ? (int) $row->sem_cdgo_responsable : null,
                'responsable_nombre' => $row->sem_nom_responsable,
                'compromiso' => $row->sem_desc_compromiso,
                'avance' => $row->sem_porcentaje_avance,
                'comentario' => $row->sem_comentario_uni_org,
                'fec_compromiso' => $row->sem_fec_compromiso ? Carbon::parse($row->sem_fec_compromiso)->format('Y-m-d') : null,
                'fec_cumplimiento' => $row->sem_fec_cumplimiento ? Carbon::parse($row->sem_fec_cumplimiento)->format('Y-m-d') : null,
                'cumplido' => $row->sem_fec_cumplimiento !== null,
            ]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] obtenerPromesa: '.$e->getMessage());

            return $this->fail('No se pudo obtener el compromiso.');
        }
    }

    /**
     * Crea una nueva promesa/compromiso. Al finalizar intenta notificar al
     * administrado vía la API externa (sin bloquear el guardado si falla).
     *
     * @param  array{id_reunion:int, dni_dirigente?:string, dirigente:string, telefono?:string,
     *               pedido:string, area_id:int, responsable_id?:int, responsable_nombre?:string,
     *               compromiso?:string, avance?:string, comentario?:string, fecha_cumplimiento?:string}  $datos
     */
    public function guardarPromesa(array $datos, int $usuarioId): array
    {
        try {
            $idReunion = (int) ($datos['id_reunion'] ?? 0);
            if ($idReunion <= 0) {
                return $this->fail('La reunión es obligatoria.');
            }

            $dirigente = trim((string) ($datos['dirigente'] ?? ''));
            if ($dirigente === '') {
                return $this->fail('El nombre del dirigente es obligatorio.');
            }

            $pedido = trim((string) ($datos['pedido'] ?? ''));
            if ($pedido === '') {
                return $this->fail('La descripción del pedido es obligatoria.');
            }

            $areaId = (int) ($datos['area_id'] ?? 0);
            if ($areaId <= 0) {
                return $this->fail('La unidad orgánica es obligatoria.');
            }

            $responsableId = ($datos['responsable_id'] ?? '') !== '' ? (int) $datos['responsable_id'] : null;
            $responsableNombre = $this->resolverNombreResponsable($responsableId)
                ?? trim((string) ($datos['responsable_nombre'] ?? ''));

            $fechaCumplimiento = $this->fechaEeuu((string) ($datos['fecha_cumplimiento'] ?? ''));

            $registroBase = [
                'cdgo_datos_zona' => $idReunion,
                'sem_fecha' => DB::raw('NOW()'),
                'sem_doc_dirigente' => trim((string) ($datos['dni_dirigente'] ?? '')),
                'sem_nom_dirigente' => $dirigente,
                'sem_contact_dirigente' => trim((string) ($datos['telefono'] ?? '')),
                'sem_desc_pedido' => $pedido,
                'sem_cdgo_uni_organica' => $areaId,
                'sem_nom_responsable' => $responsableNombre,
                'sem_desc_compromiso' => trim((string) ($datos['compromiso'] ?? '')),
                'sem_porcentaje_avance' => trim((string) ($datos['avance'] ?? '0')),
                'sem_fec_compromiso' => DB::raw('NOW()'),
                'sem_comentario_uni_org' => trim((string) ($datos['comentario'] ?? '')),
                'sem_estado' => DB::raw("B'1'"),
                'sem_cdgo_usuario_ins' => $usuarioId,
                'sem_fec_ins' => DB::raw('NOW()'),
                'sem_cdgo_responsable' => $responsableId,
            ];

            if ($fechaCumplimiento !== null) {
                $registroBase['sem_fec_cumplimiento'] = $fechaCumplimiento;
            }

            $cdgo = DB::transaction(function () use ($registroBase) {
                $siguiente = (int) (DB::table(self::TBL_SEMAFORO)->max('cdgo_semaforo')) + 1;
                DB::table(self::TBL_SEMAFORO)->insert(['cdgo_semaforo' => $siguiente] + $registroBase);

                return $siguiente;
            });

            $this->notificarAdministrado($cdgo);

            return $this->ok(['id' => $cdgo]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] guardarPromesa: '.$e->getMessage());

            return $this->fail('No se pudo guardar el compromiso.');
        }
    }

    /**
     * Actualiza una promesa/compromiso existente.
     *
     * @param  array{dni_dirigente?:string, dirigente:string, telefono?:string, pedido:string,
     *               area_id:int, responsable_id?:int, responsable_nombre?:string, compromiso?:string,
     *               avance?:string, comentario?:string, fecha_cumplimiento?:string}  $datos
     */
    public function actualizarPromesa(int $id, array $datos, int $usuarioId): array
    {
        try {
            $existe = DB::table(self::TBL_SEMAFORO)->where('cdgo_semaforo', $id)->exists();
            if (! $existe) {
                return $this->fail('Compromiso no encontrado.');
            }

            $dirigente = trim((string) ($datos['dirigente'] ?? ''));
            if ($dirigente === '') {
                return $this->fail('El nombre del dirigente es obligatorio.');
            }

            $pedido = trim((string) ($datos['pedido'] ?? ''));
            if ($pedido === '') {
                return $this->fail('La descripción del pedido es obligatoria.');
            }

            $areaId = (int) ($datos['area_id'] ?? 0);
            if ($areaId <= 0) {
                return $this->fail('La unidad orgánica es obligatoria.');
            }

            $responsableId = ($datos['responsable_id'] ?? '') !== '' ? (int) $datos['responsable_id'] : null;
            $responsableNombre = $this->resolverNombreResponsable($responsableId)
                ?? trim((string) ($datos['responsable_nombre'] ?? ''));

            $fechaCumplimiento = $this->fechaEeuu((string) ($datos['fecha_cumplimiento'] ?? ''));

            $update = [
                'sem_nom_dirigente' => $dirigente,
                'sem_contact_dirigente' => trim((string) ($datos['telefono'] ?? '')),
                'sem_desc_pedido' => $pedido,
                'sem_cdgo_uni_organica' => $areaId,
                'sem_nom_responsable' => $responsableNombre,
                'sem_cdgo_responsable' => $responsableId,
                'sem_desc_compromiso' => trim((string) ($datos['compromiso'] ?? '')),
                'sem_porcentaje_avance' => trim((string) ($datos['avance'] ?? '0')),
                'sem_fec_upd_informacion' => DB::raw('NOW()'),
                'sem_comentario_uni_org' => trim((string) ($datos['comentario'] ?? '')),
                'sem_cdgo_usuario_upd' => $usuarioId,
                'sem_fec_upd' => DB::raw('NOW()'),
            ];

            if (array_key_exists('dni_dirigente', $datos)) {
                $update['sem_doc_dirigente'] = trim((string) $datos['dni_dirigente']);
            }

            if ($fechaCumplimiento !== null) {
                $update['sem_fec_cumplimiento'] = $fechaCumplimiento;
            }

            DB::table(self::TBL_SEMAFORO)->where('cdgo_semaforo', $id)->update($update);

            return $this->ok(['id' => $id]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] actualizarPromesa: '.$e->getMessage());

            return $this->fail('No se pudo actualizar el compromiso.');
        }
    }

    /**
     * Elimina físicamente una promesa/compromiso (igual que el módulo antiguo).
     */
    public function eliminarPromesa(int $id): array
    {
        try {
            $afectados = DB::table(self::TBL_SEMAFORO)->where('cdgo_semaforo', $id)->delete();

            if ($afectados === 0) {
                return $this->fail('Compromiso no encontrado.');
            }

            return $this->ok();
        } catch (Throwable $e) {
            Log::error('[mod_promesa] eliminarPromesa: '.$e->getMessage());

            return $this->fail('No se pudo eliminar el compromiso.');
        }
    }

    // ── 4. Seguimiento / Actividades ─────────────────────────────────────

    /**
     * Lista las actividades de seguimiento de una promesa, calculando el
     * estado de cada una (EN EJECUCION / ATRAZADO / FINALIZADO / EXTENDIDO)
     * en base a la fecha actual en America/Lima.
     */
    public function listarSeguimiento(int $idPromesa): array
    {
        try {
            $rows = DB::table(self::TBL_SEGUIMIENTO)
                ->where('cdgo_semaforo', $idPromesa)
                ->orderBy('fecha_inicio')
                ->get();

            $hoy = Carbon::now(self::TZ)->startOfDay();

            $items = [];
            foreach ($rows as $row) {
                $fechaInicio = $row->fecha_inicio ? Carbon::parse($row->fecha_inicio)->startOfDay() : null;
                $fechaFinal = $row->fecha_final ? Carbon::parse($row->fecha_final)->startOfDay() : null;
                $fechaTerminado = $row->fecha_terminado ? Carbon::parse($row->fecha_terminado) : null;
                $terminado = $fechaTerminado !== null;
                $extendida = $row->cdgo_aplazo !== null;

                if (! $extendida) {
                    if (! $terminado) {
                        $estado = ($fechaFinal !== null && $hoy->lte($fechaFinal)) ? 'EN EJECUCION' : 'ATRAZADO';
                    } else {
                        $estado = 'FINALIZADO';
                    }
                } else {
                    $estado = $terminado ? 'FINALIZADO' : 'EXTENDIDO';
                }

                $puedeEliminar = ! $terminado && $fechaInicio !== null && $fechaInicio->gte($hoy);
                $puedeExtender = ! $terminado && $fechaInicio !== null && $fechaInicio->lt($hoy);
                $puedeFinalizar = ! $terminado;

                $items[] = [
                    'id_actividad' => (int) $row->cdgo_sgmnto,
                    'id_promesa' => (int) $row->cdgo_semaforo,
                    'actividad' => $row->no_actividad,
                    'fecha_inicio' => $fechaInicio?->format('d/m/Y'),
                    'fecha_final' => $fechaFinal?->format('d/m/Y'),
                    'fecha_terminado' => $fechaTerminado?->format('d/m/Y'),
                    'cdgo_aplazo' => $row->cdgo_aplazo !== null ? (int) $row->cdgo_aplazo : null,
                    'estado' => $estado,
                    'puede_eliminar' => $puedeEliminar,
                    'puede_extender' => $puedeExtender,
                    'puede_finalizar' => $puedeFinalizar,
                ];
            }

            return $this->ok($items);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] listarSeguimiento: '.$e->getMessage());

            return $this->fail('No se pudo listar el seguimiento.');
        }
    }

    /**
     * Registra una nueva actividad de seguimiento para una promesa.
     */
    public function guardarActividad(int $idPromesa, string $actividad, string $fechaInicio, string $fechaFinal, int $usuarioId): array
    {
        try {
            $actividad = trim($actividad);
            if ($actividad === '') {
                return $this->fail('La descripción de la actividad es obligatoria.');
            }

            $inicio = $this->fechaEeuu($fechaInicio);
            $final = $this->fechaEeuu($fechaFinal);
            if ($inicio === null || $final === null) {
                return $this->fail('Las fechas de inicio y fin son obligatorias y deben ser válidas.');
            }

            $cdgo = DB::transaction(function () use ($idPromesa, $actividad, $inicio, $final, $usuarioId) {
                $siguiente = (int) (DB::table(self::TBL_SEGUIMIENTO)->max('cdgo_sgmnto')) + 1;

                DB::table(self::TBL_SEGUIMIENTO)->insert([
                    'cdgo_sgmnto' => $siguiente,
                    'cdgo_semaforo' => $idPromesa,
                    'no_actividad' => $actividad,
                    'fecha_inicio' => $inicio,
                    'fecha_final' => $final,
                    'cdgo_persona' => $usuarioId,
                    'cdgo_aplazo' => null,
                    'in_esta' => '0',
                    'fecha_terminado' => null,
                ]);

                return $siguiente;
            });

            return $this->ok(['id' => $cdgo]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] guardarActividad: '.$e->getMessage());

            return $this->fail('No se pudo guardar la actividad.');
        }
    }

    /**
     * Extiende una actividad: marca la actividad anterior como terminada
     * (apuntando con cdgo_aplazo a la nueva) e inserta la nueva actividad.
     */
    public function extenderActividad(
        int $idActividadAnterior,
        int $idPromesa,
        string $actividad,
        string $fechaInicio,
        string $fechaFinal,
        int $usuarioId
    ): array {
        try {
            $actividad = trim($actividad);
            if ($actividad === '') {
                return $this->fail('La descripción de la actividad es obligatoria.');
            }

            $inicio = $this->fechaEeuu($fechaInicio);
            $final = $this->fechaEeuu($fechaFinal);
            if ($inicio === null || $final === null) {
                return $this->fail('Las fechas de inicio y fin son obligatorias y deben ser válidas.');
            }

            $existe = DB::table(self::TBL_SEGUIMIENTO)->where('cdgo_sgmnto', $idActividadAnterior)->exists();
            if (! $existe) {
                return $this->fail('La actividad a extender no existe.');
            }

            $cdgo = DB::transaction(function () use ($idActividadAnterior, $idPromesa, $actividad, $inicio, $final, $usuarioId) {
                $siguiente = (int) (DB::table(self::TBL_SEGUIMIENTO)->max('cdgo_sgmnto')) + 1;

                DB::table(self::TBL_SEGUIMIENTO)
                    ->where('cdgo_sgmnto', $idActividadAnterior)
                    ->update([
                        'fecha_terminado' => DB::raw('NOW()'),
                        'cdgo_aplazo' => $siguiente,
                    ]);

                DB::table(self::TBL_SEGUIMIENTO)->insert([
                    'cdgo_sgmnto' => $siguiente,
                    'cdgo_semaforo' => $idPromesa,
                    'no_actividad' => $actividad,
                    'fecha_inicio' => $inicio,
                    'fecha_final' => $final,
                    'cdgo_persona' => $usuarioId,
                    'cdgo_aplazo' => null,
                    'in_esta' => '0',
                    'fecha_terminado' => null,
                ]);

                return $siguiente;
            });

            return $this->ok(['id' => $cdgo]);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] extenderActividad: '.$e->getMessage());

            return $this->fail('No se pudo extender la actividad.');
        }
    }

    /**
     * Marca una actividad de seguimiento como finalizada (fecha_terminado = NOW()).
     */
    public function finalizarActividad(int $idActividad): array
    {
        try {
            $afectados = DB::table(self::TBL_SEGUIMIENTO)
                ->where('cdgo_sgmnto', $idActividad)
                ->update(['fecha_terminado' => DB::raw('NOW()')]);

            if ($afectados === 0) {
                return $this->fail('Actividad no encontrada.');
            }

            return $this->ok();
        } catch (Throwable $e) {
            Log::error('[mod_promesa] finalizarActividad: '.$e->getMessage());

            return $this->fail('No se pudo finalizar la actividad.');
        }
    }

    /**
     * Elimina físicamente una actividad de seguimiento.
     */
    public function eliminarActividad(int $idActividad): array
    {
        try {
            $afectados = DB::table(self::TBL_SEGUIMIENTO)->where('cdgo_sgmnto', $idActividad)->delete();

            if ($afectados === 0) {
                return $this->fail('Actividad no encontrada.');
            }

            return $this->ok();
        } catch (Throwable $e) {
            Log::error('[mod_promesa] eliminarActividad: '.$e->getMessage());

            return $this->fail('No se pudo eliminar la actividad.');
        }
    }

    /**
     * Finaliza el seguimiento completo de una promesa (marca sem_fec_cumplimiento = NOW()).
     */
    public function finalizarSeguimientoPromesa(int $idPromesa, int $usuarioId): array
    {
        try {
            $afectados = DB::table(self::TBL_SEMAFORO)
                ->where('cdgo_semaforo', $idPromesa)
                ->update([
                    'sem_fec_cumplimiento' => DB::raw('NOW()'),
                    'sem_fec_upd' => DB::raw('NOW()'),
                    'sem_cdgo_usuario_upd' => $usuarioId,
                ]);

            if ($afectados === 0) {
                return $this->fail('Compromiso no encontrado.');
            }

            return $this->ok();
        } catch (Throwable $e) {
            Log::error('[mod_promesa] finalizarSeguimientoPromesa: '.$e->getMessage());

            return $this->fail('No se pudo finalizar el seguimiento.');
        }
    }

    // ── 5. Catálogos ──────────────────────────────────────────────────────

    /**
     * Lista las áreas/unidades orgánicas activas.
     */
    public function catalogosAreas(): array
    {
        try {
            $rows = DB::table(self::TBL_AREAS)
                ->where('in_esta', '1')
                ->orderBy('de_area')
                ->select('cdgo_area', 'de_area')
                ->get();

            return $this->ok($rows->map(fn ($r) => [
                'cdgo_area' => (int) $r->cdgo_area,
                'de_area' => $r->de_area,
            ])->all());
        } catch (Throwable $e) {
            Log::error('[mod_promesa] catalogosAreas: '.$e->getMessage());

            return $this->fail('No se pudo listar las áreas.');
        }
    }

    /**
     * Lista el personal activo asignado a un área (para el combo "Asignado").
     */
    public function personalPorArea(int $areaId): array
    {
        try {
            $rows = DB::table(self::TBL_ZONA_USUARIO)
                ->where('in_esta', '1')
                ->where('cdgo_area', $areaId)
                ->orderBy('no_usrios')
                ->select('cdgo_usrios', 'no_usrios')
                ->get();

            return $this->ok($rows->map(fn ($r) => [
                'cdgo_usrios' => (int) $r->cdgo_usrios,
                'no_usrios' => $r->no_usrios,
            ])->all());
        } catch (Throwable $e) {
            Log::error('[mod_promesa] personalPorArea: '.$e->getMessage());

            return $this->fail('No se pudo listar el personal.');
        }
    }

    /**
     * Lista promesas puntuales para la vista de impresión (sin exigir join
     * con datos_zona_usuario, ya que la impresión no depende de responsables activos).
     *
     * @param  array<int, int|string>  $ids  Códigos de cdgo_semaforo a imprimir.
     */
    public function listarPromesasImpresion(array $ids): array
    {
        try {
            $ids = array_values(array_unique(array_map(
                'intval',
                array_filter($ids, fn ($v) => is_numeric($v))
            )));

            if ($ids === []) {
                return $this->ok([]);
            }

            $rows = DB::table(self::TBL_SEMAFORO.' as semaforo')
                ->join(self::TBL_AREAS.' as area', 'semaforo.sem_cdgo_uni_organica', '=', 'area.cdgo_area')
                ->whereIn('semaforo.cdgo_semaforo', $ids)
                ->orderByDesc('semaforo.cdgo_semaforo')
                ->select(
                    'semaforo.cdgo_semaforo',
                    'semaforo.cdgo_datos_zona',
                    'semaforo.sem_nom_dirigente',
                    'semaforo.sem_desc_pedido',
                    'semaforo.sem_desc_compromiso',
                    'semaforo.sem_comentario_uni_org',
                    'semaforo.sem_fec_compromiso',
                    'semaforo.sem_fec_cumplimiento',
                    'area.dc_area',
                    'area.de_area'
                )
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id_promesa' => (int) $row->cdgo_semaforo,
                    'id_reunion' => (int) $row->cdgo_datos_zona,
                    'dirigente' => $row->sem_nom_dirigente,
                    'pedido' => $row->sem_desc_pedido,
                    'compromiso' => $row->sem_desc_compromiso,
                    'comentario' => $row->sem_comentario_uni_org,
                    'dc_area' => $row->dc_area,
                    'de_area' => $row->de_area,
                    'fec_compromiso' => $this->fechaPeru($row->sem_fec_compromiso),
                    'fec_cumplimiento' => $row->sem_fec_cumplimiento ? $this->fechaPeru($row->sem_fec_cumplimiento) : null,
                ];
            }

            return $this->ok($items);
        } catch (Throwable $e) {
            Log::error('[mod_promesa] listarPromesasImpresion: '.$e->getMessage());

            return $this->fail('No se pudo generar la impresión.');
        }
    }

    // ── Consultas internas ───────────────────────────────────────────────

    /**
     * Query base equivalente a "listar_promesa" del módulo antiguo:
     * une datos_zona_semaforo con sistema.a_areas y externo.datos_zona_usuario,
     * exigiendo que el área tenga al menos un usuario/responsable activo.
     */
    private function promesaQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::TBL_SEMAFORO.' as semaforo')
            ->join(self::TBL_AREAS.' as area', 'semaforo.sem_cdgo_uni_organica', '=', 'area.cdgo_area')
            ->join(self::TBL_ZONA_USUARIO.' as usr', 'semaforo.sem_cdgo_uni_organica', '=', 'usr.cdgo_area')
            ->where('usr.in_esta', '1');
    }

    /**
     * Calcula el avance (total / finalizado / porcentaje) de las promesas de
     * un lugar, replicando exactamente la lógica del módulo antiguo:
     *   a = total de promesas del lugar.
     *   pendientesEfectivos = por cada promesa sin sem_fec_cumplimiento, se
     *     suma (1 - cantidad de seguimientos activos in_esta='1'); es decir,
     *     una promesa pendiente sin seguimiento activo cuenta como pendiente (+1),
     *     y con seguimiento(s) activo(s) deja de contar (o incluso resta).
     *   finalizado = a - pendientesEfectivos.
     *   porcentaje = finalizado / a * 100 (redondeado a 1 decimal), 0 si finalizado = 0.
     *
     * @return array{total:int, finalizado:int, porcentaje:float}
     */
    private function progresoLugar(int $idReunion): array
    {
        $total = $this->promesaQuery()
            ->where('semaforo.cdgo_datos_zona', $idReunion)
            ->count();

        if ($total === 0) {
            return ['total' => 0, 'finalizado' => 0, 'porcentaje' => 0.0];
        }

        $idsPendientes = $this->promesaQuery()
            ->where('semaforo.cdgo_datos_zona', $idReunion)
            ->whereNull('semaforo.sem_fec_cumplimiento')
            ->pluck('semaforo.cdgo_semaforo')
            ->all();

        $pendientesEfectivos = 0;
        if ($idsPendientes !== []) {
            $activosPorPromesa = DB::table(self::TBL_SEGUIMIENTO)
                ->where('in_esta', '1')
                ->whereIn('cdgo_semaforo', $idsPendientes)
                ->select('cdgo_semaforo', DB::raw('count(*) as total'))
                ->groupBy('cdgo_semaforo')
                ->pluck('total', 'cdgo_semaforo');

            foreach ($idsPendientes as $idPendiente) {
                $activos = (int) ($activosPorPromesa[$idPendiente] ?? 0);
                $pendientesEfectivos += 1 - $activos;
            }
        }

        $finalizado = $total - $pendientesEfectivos;
        $porcentaje = $finalizado !== 0 ? round($finalizado / $total * 100, 1) : 0.0;

        return ['total' => $total, 'finalizado' => $finalizado, 'porcentaje' => $porcentaje];
    }

    /**
     * Resuelve el nombre de un responsable a partir de su id en
     * externo.datos_zona_usuario (usado al guardar/actualizar promesas).
     */
    private function resolverNombreResponsable(?int $responsableId): ?string
    {
        if ($responsableId === null || $responsableId <= 0) {
            return null;
        }

        $row = DB::table(self::TBL_ZONA_USUARIO)
            ->where('cdgo_usrios', $responsableId)
            ->where('in_esta', '1')
            ->first();

        return $row?->no_usrios;
    }

    /**
     * Notifica al administrado sobre el registro de su promesa/compromiso
     * mediante la API externa de la Municipalidad. No lanza excepciones:
     * cualquier error de red/HTTP queda registrado en el log y se ignora.
     */
    private function notificarAdministrado(int $cdgoSemaforo): void
    {
        try {
            $row = DB::table(self::TBL_SEMAFORO.' as dzs')
                ->join(self::TBL_ZONA_USUARIO.' as dzu', 'dzu.cdgo_area', '=', 'dzs.sem_cdgo_uni_organica')
                ->join(self::TBL_USUARIOS.' as pu', 'pu.cdgo_usrios', '=', 'dzu.cdgo_usrios')
                ->where('dzs.cdgo_semaforo', $cdgoSemaforo)
                ->select('pu.nu_tele', 'dzu.no_usrios', 'dzs.sem_desc_pedido')
                ->first();

            if (! $row || empty($row->nu_tele)) {
                return;
            }

            Http::timeout(5)->get(self::API_NOTIFICACION, [
                'numeroCelular' => '51'.$row->nu_tele,
                'administrado' => (string) $row->no_usrios,
                'asunto' => (string) $row->sem_desc_pedido,
            ]);
        } catch (Throwable $e) {
            Log::warning('[mod_promesa] notificarAdministrado: '.$e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Construye una respuesta exitosa. Si $data es null no se incluye la clave.
     */
    private function ok(mixed $data = null): array
    {
        return $data === null ? ['success' => true] : ['success' => true, 'data' => $data];
    }

    /**
     * Construye una respuesta de error.
     */
    private function fail(string $error): array
    {
        return ['success' => false, 'error' => $error];
    }

    /**
     * Convierte una fecha/timestamp de BD (o cualquier formato parseable)
     * al formato peruano d/m/Y. Devuelve null si no se puede interpretar.
     */
    private function fechaPeru(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Convierte una fecha en formato dd/mm/yyyy o yyyy-mm-dd (con o sin hora)
     * al formato yyyy-mm-dd esperado por PostgreSQL. Devuelve null si la
     * cadena está vacía o no coincide con ninguno de los formatos soportados.
     */
    private function fechaEeuu(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $fecha, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    /**
     * Recorta una hora "HH:MM:SS" (o similar) a "HH:MM".
     */
    private function formatearHora(?string $hora): string
    {
        $hora = trim((string) $hora);

        return $hora !== '' ? substr($hora, 0, 5) : '';
    }
}
