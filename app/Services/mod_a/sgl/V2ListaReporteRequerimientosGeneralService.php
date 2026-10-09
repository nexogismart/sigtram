<?php

namespace App\Services\mod_a\sgl;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reporte general de requerimientos de servicios (pago a proveedores)
 * migrado desde sigtram_antiguo/.../pago_proveedores/v2_lista_reporte_requerimientos_general.php.
 *
 * Los fragmentos SQL dinámicos (hitos, EXISTS del circuito, rango de fechas) se concatenan igual
 * que en el legacy: no reciben entrada de usuario sin normalizar — las fechas se generan con Carbon
 * en formato Y-m-d H:i:s, los ids se castean a int y la clave de hito se valida contra HITO_ORDEN.
 */
class V2ListaReporteRequerimientosGeneralService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    public const LIMITE_DETALLE = 15;

    public const TZ = 'America/Lima';

    /** Orden del circuito; las etapas posteriores acumulan a las anteriores. */
    public const HITO_ORDEN = ['llega_ol', 'ol_pres', 'pres_ol', 'os_det', 'conf_mov', 'mem', 'finanzas', 'fin'];

    public const HITO_LABELS = [
        'llega_ol' => 'Llegada a Logística',
        'ol_pres' => 'Logística envía a Presupuesto',
        'pres_ol' => 'Presupuesto devuelve a Logística',
        'os_det' => 'O/S en detalle',
        'conf_mov' => 'Conformidad de pago',
        'mem' => 'Memorándum a devengar',
        'finanzas' => 'Finanzas — giro',
        'fin' => 'Finalizado',
    ];

    public const TIPO_NOMBRE = [
        0 => 'todos',
        1 => 'por_iniciar',
        2 => 'en_tramite',
        3 => 'en_proceso',
        4 => 'finalizado',
    ];

    private readonly string $schemaSistema;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    // ── Helpers de negocio (portados 1:1 del legacy) ──────────────────────

    /**
     * Etiqueta legible de p_pgo_prvdres.in_esta (flujo gestión de proveedores / logística).
     */
    public static function estLabel(mixed $inEsta): string
    {
        $n = (int) $inEsta;

        $labels = [
            1 => 'Creado / por iniciar',
            3 => 'Pendiente orden de servicio (O/S)',
            4 => 'Conformidad locador',
            5 => 'Memorándum a devengar',
            6 => 'Finanzas — giro de pago',
            7 => 'Finalizado',
            8 => 'Finalizado',
            9 => 'Solicitud de CPP',
            10 => 'Ítems / anexos (revisión)',
            11 => 'Orden de servicio (O/S)',
            12 => 'Presupuesto — seguimiento',
            13 => 'Habilitación / en proceso',
        ];

        return $labels[$n] ?? ('Estado código ' . $n);
    }

    /**
     * Estados de trámite interno considerados «finalizado» en el histórico (a_estdos_trmtes del flujo interno).
     */
    public static function ntrEsFinal(mixed $cdgoEstdoTrmte): bool
    {
        if ($cdgoEstdoTrmte === null || $cdgoEstdoTrmte === '') {
            return false;
        }

        return in_array((string) $cdgoEstdoTrmte, ['3', '24'], true);
    }

    /**
     * Igual que ntrEsFinal, pero admite códigos no listados si la descripción del catálogo indica finalización
     * (evita casos donde el histórico dice «FINALIZADO» y p_pgo_prvdres sigue en 1).
     */
    public static function ntrMuestraComoFinalizado(mixed $cdgoEstdoTrmte, mixed $dcEstdoTrmte = null): bool
    {
        if (self::ntrEsFinal($cdgoEstdoTrmte)) {
            return true;
        }

        $dcu = mb_strtoupper(trim((string) $dcEstdoTrmte));
        if ($dcu === '') {
            return false;
        }

        return str_contains($dcu, 'FINALIZADO') || str_contains($dcu, 'FINAL.');
    }

    /**
     * Texto de la columna «Estado req.». Estado 3 = pendiente de registrar la O/S en el sistema,
     * no falta de anexos. Si ya hay archivo de O/S en detalle pero sigue en 3, se muestra otra leyenda.
     *
     * @return array{label: string, cod_line: string, title: string, badge_variant: string}
     */
    public static function estReportDisplay(
        mixed $inEsta,
        mixed $tieneOsEnDetalle,
        mixed $ultCdgoEstdoTrmte = null,
        mixed $ultDcEstdoTrmte = null
    ): array {
        $n = (int) $inEsta;
        $base = self::estLabel($n);
        $tieneOs = ($tieneOsEnDetalle === true || $tieneOsEnDetalle === 't' || $tieneOsEnDetalle === 1 || $tieneOsEnDetalle === '1');

        if ($n === 3 && ! $tieneOs) {
            return [
                'label' => 'Falta registrar la O/S',
                'cod_line' => 'cod. 3',
                'title' => 'Estado 3: aún no se ha registrado la orden de servicio en el detalle del requerimiento. No significa que falten anexos; en este código el retraso es la O/S pendiente de carga por logística.',
                'badge_variant' => 'process',
            ];
        }

        if ($n === 3 && $tieneOs) {
            $lbl = 'O/S ya registrada — avance formal pendiente';

            return [
                'label' => $lbl,
                'cod_line' => 'cod. 3',
                'title' => $lbl . '. La orden de servicio ya figura en el detalle; el código 3 puede mantenerse hasta que el trámite avance (p. ej. al abrir el histórico). No indica falta de anexos.',
                'badge_variant' => 'warn',
            ];
        }

        if ($n === 1 && self::ntrMuestraComoFinalizado($ultCdgoEstdoTrmte, $ultDcEstdoTrmte)) {
            $dc = trim((string) ($ultDcEstdoTrmte ?? ''));

            return [
                'label' => 'Finalizado en trámite interno',
                'cod_line' => 'cod. 1 · trámite cerrado',
                'title' => 'En gestión de proveedores el requerimiento sigue con in_esta=1, pero el último movimiento del trámite interno está finalizado'
                    . ($dc !== '' ? ' (' . $dc . ')' : '')
                    . '. Es habitual en devoluciones o cierres en sede; el histórico (ojito) muestra el detalle. En las tarjetas del reporte este caso cuenta en «Finalizados», no en «Por iniciar».',
                'badge_variant' => 'warn',
            ];
        }

        $variant = 'default';
        if (in_array($n, [7, 8], true)) {
            $variant = 'success';
        } elseif (in_array($n, [4, 5, 13], true)) {
            $variant = 'process';
        } elseif ($n === 6) {
            $variant = 'finance';
        } elseif ($n === 1) {
            $variant = 'muted';
        }

        return [
            'label' => $base,
            'cod_line' => 'cod. ' . $n,
            'title' => 'in_esta = ' . $n . ': ' . $base,
            'badge_variant' => $variant,
        ];
    }

    // ── Rango de fechas ───────────────────────────────────────────────────

    /**
     * Límite superior real del rango: en PostgreSQL 'YYYY-MM-DD' es medianoche y excluiría todo lo
     * registrado ese día. Desde 00:00:00; hasta 23:59:59 (o la hora actual si el final es hoy).
     *
     * @return array{fi: string, ff: string}
     */
    public function rangoDatetime(string $fiYmd, string $ffYmd): array
    {
        $hoy = Carbon::now(self::TZ);

        $fi = $this->normalizarFecha($fiYmd) ?? $hoy->copy()->startOfMonth()->format('Y-m-d');
        $ff = $this->normalizarFecha($ffYmd) ?? $hoy->format('Y-m-d');

        if ($fi > $ff) {
            [$fi, $ff] = [$ff, $fi];
        }

        $ffDt = $ff === $hoy->format('Y-m-d')
            ? $hoy->format('Y-m-d H:i:s')
            : $ff . ' 23:59:59';

        return ['fi' => $fi . ' 00:00:00', 'ff' => $ffDt];
    }

    private function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
            return $valor;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m) === 1) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return null;
    }

    // ── Fragmentos SQL compartidos ────────────────────────────────────────

    /**
     * Construye los fragmentos SQL del reporte con la misma lógica del legacy.
     *
     * @return array<string, mixed>
     */
    private function fragmentos(string $fiDt, string $ffDt): array
    {
        $s = $this->schemaSistema;
        $l = self::SCHEMA_LOGISTICA;

        $whereFecha = " AND p_pgo_prvdres.fe_pgo_prvdres >= '{$fiDt}'
                        AND p_pgo_prvdres.fe_pgo_prvdres <= '{$ffDt}'";

        /* Mismo filtro cuando la tabla va con alias pp (PostgreSQL no permite p_pgo_prvdres tras AS pp). */
        $whereFechaPp = " AND pp.fe_pgo_prvdres >= '{$fiDt}'
                          AND pp.fe_pgo_prvdres <= '{$ffDt}'";

        /*
         * Solo requerimientos con al menos un ítem activo en logistica.h_deta_pgo_prvdres que tenga
         * cargados anexos (de_urls_anxos) y documento adicional / SERVIR (de_urls_adicional).
         */
        $whereReqAdjuntos = "
            AND EXISTS (
                SELECT 1 FROM {$l}h_deta_pgo_prvdres hdp_clip
                WHERE hdp_clip.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres
                  AND hdp_clip.in_esta NOT IN (0, 2)
                  AND NULLIF(BTRIM(COALESCE(hdp_clip.de_urls_anxos, '')), '') IS NOT NULL
                  AND NULLIF(BTRIM(COALESCE(hdp_clip.de_urls_adicional, '')), '') IS NOT NULL
            )";

        /*
         * Último movimiento del trámite interno (asignado, ruta, F. recepción / días y estado).
         * La partición principal sigue siendo p_pgo_prvdres.in_esta; excepción: si in_esta=1 pero el
         * último movimiento interno está finalizado, cuenta como «finalizado».
         */
        $sqlJoinNtrUlt = "
            LEFT JOIN LATERAL (
                SELECT r2.cdgo_estdo_trmte,
                       e.dc_estdo_trmte,
                       r2.cdgo_crgo_en AS ult_cdgo_crgo_en,
                       r2.fe_rcbdo     AS ult_fe_rcbdo
                FROM {$s}r_trmtes_ntrnos r2
                INNER JOIN {$s}a_estdos_trmtes e ON e.cdgo_estdo_trmte = r2.cdgo_estdo_trmte
                WHERE r2.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                ORDER BY r2.cdgo_trmtes_ntrnos DESC
                LIMIT 1
            ) AS ult_ntr ON (true)";

        $condNtrUltimoFinal = "(
            ult_ntr.cdgo_estdo_trmte IS NOT NULL
            AND (
                ult_ntr.cdgo_estdo_trmte IN ('3','24')
                OR UPPER(COALESCE(ult_ntr.dc_estdo_trmte, '')) LIKE '%FINALIZADO%'
                OR UPPER(COALESCE(ult_ntr.dc_estdo_trmte, '')) LIKE '%FINAL.%'
            )
        )";

        /* Siglas del catálogo de áreas; ajustar los ILIKE si cambian (O.L., O.G.P.P.I., O.G.A.F., O.F.). */
        $axOl = "(COALESCE(ax.dc_area,'') ILIKE '%O.L%' OR COALESCE(ax.de_area,'') ILIKE '%logística%' OR COALESCE(ax.de_area,'') ILIKE '%logistica%')";
        $asaOl = "(COALESCE(asa.dc_area,'') ILIKE '%O.L%' OR COALESCE(asa.de_area,'') ILIKE '%logística%' OR COALESCE(asa.de_area,'') ILIKE '%logistica%')";
        $aenOl = "(COALESCE(aen.dc_area,'') ILIKE '%O.L%' OR COALESCE(aen.de_area,'') ILIKE '%logística%' OR COALESCE(aen.de_area,'') ILIKE '%logistica%')";
        $asaOgppi = "(COALESCE(asa.dc_area,'') ILIKE '%G.P.P.I%' OR COALESCE(asa.de_area,'') ILIKE '%planeamiento%' OR COALESCE(asa.de_area,'') ILIKE '%presupuesto%')";
        $aenOgppi = "(COALESCE(aen.dc_area,'') ILIKE '%G.P.P.I%' OR COALESCE(aen.de_area,'') ILIKE '%planeamiento%' OR COALESCE(aen.de_area,'') ILIKE '%presupuesto%')";
        $aenOgaf = "(COALESCE(aen.dc_area,'') ILIKE '%G.A.F%' OR COALESCE(aen.de_area,'') ILIKE '%administraci%finanz%')";
        $axOf = "((COALESCE(ax.dc_area,'') ILIKE '%O.F%' AND COALESCE(ax.dc_area,'') NOT ILIKE '%G.A.F%') OR COALESCE(ax.de_area,'') ILIKE '%oficina%finanzas%')";

        $existsLlegaOl = "EXISTS (
            SELECT 1 FROM {$s}r_trmtes_ntrnos rx
            INNER JOIN {$s}p_crgos cx ON rx.cdgo_crgo_en = cx.cdgo_crgo
            INNER JOIN {$s}a_areas ax ON cx.cdgo_area = ax.cdgo_area
            WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
              AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
              AND ({$axOl})
        )";

        $existsOlEnviaPres = "EXISTS (
            SELECT 1 FROM {$s}r_trmtes_ntrnos rx
            INNER JOIN {$s}p_crgos csa ON rx.cdgo_crgo_sa = csa.cdgo_crgo
            INNER JOIN {$s}a_areas asa ON csa.cdgo_area = asa.cdgo_area
            INNER JOIN {$s}p_crgos cen ON rx.cdgo_crgo_en = cen.cdgo_crgo
            INNER JOIN {$s}a_areas aen ON cen.cdgo_area = aen.cdgo_area
            WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
              AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
              AND ({$asaOl})
              AND ({$aenOgppi})
        )";

        $existsPresDevuelveOl = "EXISTS (
            SELECT 1 FROM {$s}r_trmtes_ntrnos rx
            INNER JOIN {$s}p_crgos csa ON rx.cdgo_crgo_sa = csa.cdgo_crgo
            INNER JOIN {$s}a_areas asa ON csa.cdgo_area = asa.cdgo_area
            INNER JOIN {$s}p_crgos cen ON rx.cdgo_crgo_en = cen.cdgo_crgo
            INNER JOIN {$s}a_areas aen ON cen.cdgo_area = aen.cdgo_area
            WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
              AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
              AND ({$asaOgppi})
              AND ({$aenOl})
        )";

        $existsConfSolicOgaf = "EXISTS (
            SELECT 1 FROM {$s}r_trmtes_ntrnos rx
            INNER JOIN {$s}p_crgos csa ON rx.cdgo_crgo_sa = csa.cdgo_crgo
            INNER JOIN {$s}p_crgos cen ON rx.cdgo_crgo_en = cen.cdgo_crgo
            INNER JOIN {$s}a_areas aen ON cen.cdgo_area = aen.cdgo_area
            WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
              AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
              AND csa.cdgo_area = p_pgo_prvdres.cdgo_area
              AND ({$aenOgaf})
        )";

        $existsLlegaOf = "EXISTS (
            SELECT 1 FROM {$s}r_trmtes_ntrnos rx
            INNER JOIN {$s}p_crgos cx ON rx.cdgo_crgo_en = cx.cdgo_crgo
            INNER JOIN {$s}a_areas ax ON cx.cdgo_area = ax.cdgo_area
            WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
              AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
              AND ({$axOf})
        )";

        /*
         * Reporte por áreas: hitos como etapas excluyentes (un req. cuenta en una sola tarjeta).
         * Las primeras etapas excluyen «finalizado»; de lo contrario la suma de hitos supera el total.
         */
        $existsOsDetalle = "EXISTS (
            SELECT 1 FROM {$l}h_deta_pgo_prvdres hd_os
            WHERE hd_os.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres
              AND hd_os.in_esta NOT IN (0,2)
              AND NULLIF(BTRIM(COALESCE(hd_os.de_urls_os, '')), '') IS NOT NULL
        )";

        $hitoFin = "(p_pgo_prvdres.in_esta IN (7,8) OR (p_pgo_prvdres.in_esta = 1 AND ({$condNtrUltimoFinal})))";
        $hitoFinanzas = "(p_pgo_prvdres.in_esta IN (6,7,8) OR ({$existsLlegaOf}))";
        $hitoMemRango = 'p_pgo_prvdres.in_esta IN (5,6,7,8)';

        $sqlHito = [
            'llega_ol' => "({$existsLlegaOl}) AND NOT ({$existsOlEnviaPres}) AND NOT ({$hitoFin})",
            'ol_pres' => "({$existsOlEnviaPres}) AND NOT ({$existsPresDevuelveOl}) AND NOT ({$hitoFin})",
            'pres_ol' => "({$existsPresDevuelveOl}) AND NOT ({$existsOsDetalle}) AND NOT ({$hitoFin})",
            'os_det' => "({$existsOsDetalle}) AND NOT ({$existsConfSolicOgaf}) AND NOT ({$hitoFin})",
            'conf_mov' => "({$existsConfSolicOgaf}) AND NOT ({$hitoMemRango}) AND NOT ({$hitoFin})",
            'mem' => "({$hitoMemRango}) AND NOT ({$hitoFinanzas}) AND NOT ({$hitoFin})",
            'finanzas' => "({$hitoFinanzas}) AND NOT ({$hitoFin})",
            'fin' => "({$hitoFin})",
        ];

        /* Acumulativo para píldoras y modal: req. en este hito o en cualquier etapa posterior. */
        $sqlHitoCum = [];
        $n = count(self::HITO_ORDEN);
        for ($i = 0; $i < $n; $i++) {
            $partes = [];
            for ($j = $i; $j < $n; $j++) {
                $partes[] = '(' . $sqlHito[self::HITO_ORDEN[$j]] . ')';
            }
            $sqlHitoCum[self::HITO_ORDEN[$i]] = implode(' OR ', $partes);
        }

        /* Texto «usuario · fecha» del paso asociado al hito. */
        $nomFeRx = "NULLIF(TRIM(BOTH ' ·' FROM (COALESCE(TRIM(pu.no_crto), '') || CASE WHEN rx.fe_rcbdo IS NULL THEN '' ELSE ' · ' || to_char(rx.fe_rcbdo::date, 'DD/MM/YYYY') END)), '')";

        $sqlOpHito = [
            'llega_ol' => "(SELECT {$nomFeRx}
                FROM {$s}r_trmtes_ntrnos rx
                INNER JOIN {$s}p_crgos cx ON rx.cdgo_crgo_en = cx.cdgo_crgo
                INNER JOIN {$s}a_areas ax ON cx.cdgo_area = ax.cdgo_area
                INNER JOIN {$s}p_lgin pl ON rx.cdgo_lgin = pl.cdgo_lgin
                INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                  AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
                  AND ({$axOl})
                ORDER BY rx.cdgo_trmtes_ntrnos ASC
                LIMIT 1)",
            'ol_pres' => "(SELECT {$nomFeRx}
                FROM {$s}r_trmtes_ntrnos rx
                INNER JOIN {$s}p_crgos csa ON rx.cdgo_crgo_sa = csa.cdgo_crgo
                INNER JOIN {$s}a_areas asa ON csa.cdgo_area = asa.cdgo_area
                INNER JOIN {$s}p_crgos cen ON rx.cdgo_crgo_en = cen.cdgo_crgo
                INNER JOIN {$s}a_areas aen ON cen.cdgo_area = aen.cdgo_area
                INNER JOIN {$s}p_lgin pl ON rx.cdgo_lgin = pl.cdgo_lgin
                INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                  AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
                  AND ({$asaOl})
                  AND ({$aenOgppi})
                ORDER BY rx.cdgo_trmtes_ntrnos ASC
                LIMIT 1)",
            'pres_ol' => "(SELECT {$nomFeRx}
                FROM {$s}r_trmtes_ntrnos rx
                INNER JOIN {$s}p_crgos csa ON rx.cdgo_crgo_sa = csa.cdgo_crgo
                INNER JOIN {$s}a_areas asa ON csa.cdgo_area = asa.cdgo_area
                INNER JOIN {$s}p_crgos cen ON rx.cdgo_crgo_en = cen.cdgo_crgo
                INNER JOIN {$s}a_areas aen ON cen.cdgo_area = aen.cdgo_area
                INNER JOIN {$s}p_lgin pl ON rx.cdgo_lgin = pl.cdgo_lgin
                INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                  AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
                  AND ({$asaOgppi})
                  AND ({$aenOl})
                ORDER BY rx.cdgo_trmtes_ntrnos ASC
                LIMIT 1)",
            'os_det' => "(SELECT NULLIF(TRIM(BOTH ' ·' FROM (COALESCE(TRIM(pu.no_crto), '') || CASE WHEN COALESCE(hd.fe_orden_srvco, hd.ts_usua_modi) IS NULL THEN '' ELSE ' · ' || to_char(COALESCE(hd.fe_orden_srvco, hd.ts_usua_modi)::date, 'DD/MM/YYYY') END)), '')
                FROM {$l}h_deta_pgo_prvdres hd
                LEFT JOIN {$s}p_usrios pu ON hd.cdgo_usrios = pu.cdgo_usrios
                WHERE hd.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres
                  AND hd.in_esta NOT IN (0,2)
                  AND NULLIF(BTRIM(COALESCE(hd.de_urls_os, '')), '') IS NOT NULL
                ORDER BY hd.cdgo_deta_pgo_prvdres ASC
                LIMIT 1)",
            'conf_mov' => "(SELECT {$nomFeRx}
                FROM {$s}r_trmtes_ntrnos rx
                INNER JOIN {$s}p_crgos csa ON rx.cdgo_crgo_sa = csa.cdgo_crgo
                INNER JOIN {$s}p_crgos cen ON rx.cdgo_crgo_en = cen.cdgo_crgo
                INNER JOIN {$s}a_areas aen ON cen.cdgo_area = aen.cdgo_area
                INNER JOIN {$s}p_lgin pl ON rx.cdgo_lgin = pl.cdgo_lgin
                INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                  AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
                  AND csa.cdgo_area = p_pgo_prvdres.cdgo_area
                  AND ({$aenOgaf})
                ORDER BY rx.cdgo_trmtes_ntrnos ASC
                LIMIT 1)",
            'mem' => "(SELECT {$nomFeRx}
                FROM {$s}r_trmtes_ntrnos rx
                INNER JOIN {$s}p_lgin pl ON rx.cdgo_lgin = pl.cdgo_lgin
                INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                  AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
                ORDER BY rx.cdgo_trmtes_ntrnos DESC
                LIMIT 1)",
            'finanzas' => "(SELECT {$nomFeRx}
                FROM {$s}r_trmtes_ntrnos rx
                INNER JOIN {$s}p_crgos cx ON rx.cdgo_crgo_en = cx.cdgo_crgo
                INNER JOIN {$s}a_areas ax ON cx.cdgo_area = ax.cdgo_area
                INNER JOIN {$s}p_lgin pl ON rx.cdgo_lgin = pl.cdgo_lgin
                INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                  AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
                  AND ({$axOf})
                ORDER BY rx.cdgo_trmtes_ntrnos ASC
                LIMIT 1)",
            'fin' => "(SELECT {$nomFeRx}
                FROM {$s}r_trmtes_ntrnos rx
                INNER JOIN {$s}p_lgin pl ON rx.cdgo_lgin = pl.cdgo_lgin
                INNER JOIN {$s}p_usrios pu ON pu.cdgo_usrios = pl.cdgo_usrios
                WHERE rx.cdgo_dtos_ntrnos = p_pgo_prvdres.cdgo_dtos_ntrnos
                  AND p_pgo_prvdres.cdgo_dtos_ntrnos IS NOT NULL
                ORDER BY rx.cdgo_trmtes_ntrnos DESC
                LIMIT 1)",
        ];

        return [
            'whereFecha' => $whereFecha,
            'whereFechaPp' => $whereFechaPp,
            'whereReqAdjuntos' => $whereReqAdjuntos,
            'sqlJoinNtrUlt' => $sqlJoinNtrUlt,
            'condNtrUltimoFinal' => $condNtrUltimoFinal,
            'sqlHito' => $sqlHito,
            'sqlHitoCum' => $sqlHitoCum,
            'sqlOpHito' => $sqlOpHito,
        ];
    }

    /**
     * Filtro de bandeja (partición por in_esta), idéntico al legacy.
     */
    private function whereTipo(int $tipo, string $condNtrUltimoFinal): string
    {
        return match ($tipo) {
            1 => " AND p_pgo_prvdres.in_esta = 1 AND NOT ({$condNtrUltimoFinal})",
            2 => ' AND p_pgo_prvdres.in_esta NOT IN (1,4,5,7,8,13)',
            3 => ' AND p_pgo_prvdres.in_esta IN (4,5,13)',
            4 => " AND (p_pgo_prvdres.in_esta IN (7,8) OR (p_pgo_prvdres.in_esta = 1 AND ({$condNtrUltimoFinal})))",
            default => '',
        };
    }

    // ── Estadísticas por área ─────────────────────────────────────────────

    /**
     * Tarjetas por área (partición + hitos) y totales globales del período.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function statsPorArea(string $fiYmd, string $ffYmd): array
    {
        try {
            $rango = $this->rangoDatetime($fiYmd, $ffYmd);
            $f = $this->fragmentos($rango['fi'], $rango['ff']);
            $s = $this->schemaSistema;
            $l = self::SCHEMA_LOGISTICA;
            $hito = $f['sqlHito'];

            $locadores = $this->locadoresPorArea($f['whereFechaPp']);

            $sqlAreas = "SELECT COALESCE(a_areas.cdgo_area,-1)       AS cdgo_area,
                                COALESCE(a_areas.de_area,'Sin área') AS de_area,
                                COUNT(DISTINCT p_pgo_prvdres.cdgo_pgo_prvdres) AS total,
                                SUM(CASE WHEN p_pgo_prvdres.in_esta = 1 AND NOT ({$f['condNtrUltimoFinal']}) THEN 1 ELSE 0 END) AS por_iniciar,
                                SUM(CASE WHEN p_pgo_prvdres.in_esta NOT IN (1,4,5,7,8,13) THEN 1 ELSE 0 END) AS en_tramite,
                                SUM(CASE WHEN p_pgo_prvdres.in_esta IN (4,5,13) THEN 1 ELSE 0 END) AS en_proceso,
                                SUM(CASE WHEN p_pgo_prvdres.in_esta IN (7,8) OR (p_pgo_prvdres.in_esta = 1 AND ({$f['condNtrUltimoFinal']})) THEN 1 ELSE 0 END) AS finalizados,
                                SUM(CASE WHEN {$hito['llega_ol']} THEN 1 ELSE 0 END) AS n_m_llega_ol,
                                SUM(CASE WHEN {$hito['ol_pres']}  THEN 1 ELSE 0 END) AS n_m_ol_pres,
                                SUM(CASE WHEN {$hito['pres_ol']}  THEN 1 ELSE 0 END) AS n_m_pres_ol,
                                SUM(CASE WHEN {$hito['os_det']}   THEN 1 ELSE 0 END) AS n_m_os,
                                SUM(CASE WHEN {$hito['conf_mov']} THEN 1 ELSE 0 END) AS n_m_conf_mov,
                                SUM(CASE WHEN {$hito['mem']}      THEN 1 ELSE 0 END) AS n_m_mem,
                                SUM(CASE WHEN {$hito['finanzas']} THEN 1 ELSE 0 END) AS n_m_finanzas,
                                SUM(CASE WHEN {$hito['fin']}      THEN 1 ELSE 0 END) AS n_m_fin
                         FROM {$l}p_pgo_prvdres
                         LEFT JOIN {$s}a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
                         {$f['sqlJoinNtrUlt']}
                         WHERE p_pgo_prvdres.in_esta NOT IN (0,2) {$f['whereFecha']} {$f['whereReqAdjuntos']}
                         GROUP BY a_areas.cdgo_area, a_areas.de_area
                         ORDER BY COUNT(DISTINCT p_pgo_prvdres.cdgo_pgo_prvdres) DESC";

            $filas = [];
            $sumTotal = 0;
            $sumPorIniciar = 0;
            $sumEnTramite = 0;
            $sumEnProceso = 0;
            $sumFinalizado = 0;

            foreach (DB::select($sqlAreas) as $ra) {
                $total = (int) $ra->total;
                $porIniciar = (int) $ra->por_iniciar;
                $enTramite = (int) $ra->en_tramite;
                $enProceso = (int) $ra->en_proceso;
                $finalizados = (int) $ra->finalizados;
                $gestionados = $enTramite + $enProceso + $finalizados;

                if ($porIniciar === 0) {
                    $porcentaje = 100.0;
                    $statusText = 'EXCELENTE';
                } else {
                    $porcentaje = $total > 0 ? ($gestionados / $total) * 100 : 0.0;
                    if ($porcentaje >= 80) {
                        $statusText = 'AL DÍA';
                    } elseif ($porcentaje >= 50) {
                        $statusText = 'REVISAR';
                    } else {
                        $statusText = 'CRÍTICO';
                    }
                }

                $sumTotal += $total;
                $sumPorIniciar += $porIniciar;
                $sumEnTramite += $enTramite;
                $sumEnProceso += $enProceso;
                $sumFinalizado += $finalizados;

                if ($porIniciar + $enTramite + $enProceso + $finalizados !== $total) {
                    Log::warning('[v2_lista_reporte_requerimientos_general] partición no cuadra con total en área ' . ($ra->de_area ?? ''));
                }

                $cdgoArea = (int) $ra->cdgo_area;

                $filas[] = [
                    'id' => $cdgoArea,
                    'area' => (string) $ra->de_area,
                    'locadores' => $locadores[$cdgoArea] ?? [],
                    'total' => $total,
                    'por_iniciar' => $porIniciar,
                    'en_tramite' => $enTramite,
                    'en_proceso' => $enProceso,
                    'finalizados' => $finalizados,
                    'gestionados' => $gestionados,
                    'pendientes' => $porIniciar,
                    'porcentaje' => round($porcentaje, 1),
                    'status_text' => $statusText,
                    'milestones' => $this->milestones($ra, $total),
                ];
            }

            usort($filas, function (array $a, array $b): int {
                $at = $a['total'] > 0;
                $bt = $b['total'] > 0;
                if ($at !== $bt) {
                    return $bt <=> $at;
                }
                $pa = $a['total'] > 0 ? $a['gestionados'] / $a['total'] : 0;
                $pb = $b['total'] > 0 ? $b['gestionados'] / $b['total'] : 0;

                return $pb <=> $pa;
            });

            $gestionadoGlobal = $sumEnTramite + $sumEnProceso + $sumFinalizado;
            $pctTrabajo = $sumTotal > 0 ? round(($gestionadoGlobal / $sumTotal) * 100, 1) : 0.0;

            return $this->ok([
                'filas' => $filas,
                'totales' => [
                    'total' => $sumTotal,
                    'por_iniciar' => $sumPorIniciar,
                    'en_tramite' => $sumEnTramite,
                    'en_proceso' => $sumEnProceso,
                    'finalizados' => $sumFinalizado,
                    'gestionados' => $gestionadoGlobal,
                    'pct_trabajo' => $pctTrabajo,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('[v2_lista_reporte_requerimientos_general] statsPorArea: ' . $e->getMessage());

            return $this->fail('No se pudieron calcular las estadísticas por área.');
        }
    }

    /**
     * Píldoras del reporte por áreas: n y % acumulativos (cuántos llegaron al menos a este hito)
     * y n_here (cuántos están exactamente en ese hito).
     *
     * @return list<array<string, mixed>>
     */
    private function milestones(object $ra, int $total): array
    {
        $here = [
            (int) ($ra->n_m_llega_ol ?? 0),
            (int) ($ra->n_m_ol_pres ?? 0),
            (int) ($ra->n_m_pres_ol ?? 0),
            (int) ($ra->n_m_os ?? 0),
            (int) ($ra->n_m_conf_mov ?? 0),
            (int) ($ra->n_m_mem ?? 0),
            (int) ($ra->n_m_finanzas ?? 0),
            (int) ($ra->n_m_fin ?? 0),
        ];

        $cum = [];
        $sufijo = 0;
        for ($i = count($here) - 1; $i >= 0; $i--) {
            $sufijo += $here[$i];
            $cum[$i] = $sufijo;
        }

        $deAreaSol = (string) ($ra->de_area ?? 'Área solicitante');
        $lineas = [
            'llega_ol' => 'O.L. · recepción del trámite',
            'ol_pres' => 'O.L. → O.G.P.P.I.',
            'pres_ol' => 'O.G.P.P.I. → O.L.',
            'os_det' => 'O.L. · gestión proveedores',
            'conf_mov' => $deAreaSol . ' → O.G.A.F.',
            'mem' => 'O.L.',
            'finanzas' => 'O.F.',
            'fin' => 'Cierre',
        ];

        $out = [];
        foreach (self::HITO_ORDEN as $i => $key) {
            $out[] = [
                'ml_key' => $key,
                'label' => self::HITO_LABELS[$key],
                'area_line' => $lineas[$key],
                'n' => $cum[$i],
                'n_here' => $here[$i],
                'pct' => $total > 0 ? (int) round(($cum[$i] / $total) * 100) : 0,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, list<string>>
     */
    private function locadoresPorArea(string $whereFechaPp): array
    {
        $s = $this->schemaSistema;
        $l = self::SCHEMA_LOGISTICA;

        $rows = DB::select(
            "SELECT pp.cdgo_area, pu.no_crto
             FROM {$l}p_pgo_prvdres pp
             INNER JOIN {$l}h_deta_pgo_prvdres hdp
                     ON hdp.cdgo_pgo_prvdres = pp.cdgo_pgo_prvdres AND hdp.in_esta NOT IN (0, 2)
                     AND NULLIF(BTRIM(COALESCE(hdp.de_urls_anxos, '')), '') IS NOT NULL
                     AND NULLIF(BTRIM(COALESCE(hdp.de_urls_adicional, '')), '') IS NOT NULL
             INNER JOIN {$s}p_usrios pu ON hdp.cdgo_usrios = pu.cdgo_usrios
             WHERE pp.in_esta NOT IN (0,2) {$whereFechaPp}
             GROUP BY pp.cdgo_area, pu.no_crto
             ORDER BY pp.cdgo_area, pu.no_crto"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->cdgo_area][] = (string) $r->no_crto;
        }

        return $out;
    }

    // ── Detalle (AJAX / listado completo) ─────────────────────────────────

    /**
     * Detalle paginado de requerimientos.
     *
     * @param  int  $tipo  0=todos 1=por iniciar 2=en trámite 3=en proceso 4=finalizado
     * @param  string|null  $ml  clave de hito (filtro acumulativo del reporte por áreas)
     * @return array{success: bool, data?: array, error?: string}
     */
    public function detalle(int $areaId, int $tipo, int $offset, ?string $ml, string $fi, string $ff): array
    {
        return $this->consultaDetalle($areaId, $tipo, $offset, $ml, $fi, $ff, self::LIMITE_DETALLE);
    }

    /**
     * Listado completo del período (vista «Reportes»), sin paginación.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function listadoGeneral(string $fi, string $ff): array
    {
        return $this->consultaDetalle(-1, 0, 0, null, $fi, $ff, null);
    }

    /**
     * @return array{success: bool, data?: array, error?: string}
     */
    private function consultaDetalle(int $areaId, int $tipo, int $offset, ?string $ml, string $fi, string $ff, ?int $limit): array
    {
        $mlKey = $this->normalizarHito($ml);
        if ($ml !== null && trim($ml) !== '' && $mlKey === null) {
            return $this->fail('Hito no reconocido.');
        }

        try {
            $rango = $this->rangoDatetime($fi, $ff);
            $f = $this->fragmentos($rango['fi'], $rango['ff']);
            $s = $this->schemaSistema;
            $l = self::SCHEMA_LOGISTICA;

            $whereTipo = $mlKey !== null
                ? ' AND (' . $f['sqlHitoCum'][$mlKey] . ')'
                : $this->whereTipo($tipo, $f['condNtrUltimoFinal']);

            $whereArea = $areaId >= 0 ? ' AND p_pgo_prvdres.cdgo_area = ' . $areaId : '';
            $offset = max(0, $offset);

            $whereBase = "WHERE p_pgo_prvdres.in_esta NOT IN (0,2) {$whereArea} {$whereTipo} {$f['whereFecha']} {$f['whereReqAdjuntos']}";

            $total = (int) (DB::selectOne(
                "SELECT COUNT(*) AS n
                 FROM {$l}p_pgo_prvdres
                 {$f['sqlJoinNtrUlt']}
                 {$whereBase}"
            )?->n ?? 0);

            $extraHito = $mlKey !== null
                ? ', (' . $f['sqlOpHito'][$mlKey] . ') AS rep_area_ml_oper_txt'
                : '';

            $paginado = $limit !== null ? " LIMIT {$limit} OFFSET {$offset}" : '';

            $rows = DB::select(
                "SELECT
                    p_pgo_prvdres.cdgo_pgo_prvdres,
                    p_pgo_prvdres.in_esta AS in_esta_prvdres,
                    p_pgo_prvdres.cdgo_dtos_ntrnos,
                    p_pgo_prvdres.fe_pgo_prvdres,
                    p_pgo_prvdres.de_pgo_prvdres,
                    COALESCE(p_dtos_ntrnos.nu_tram_todo, 'REQ-'||p_pgo_prvdres.cdgo_pgo_prvdres::text) AS nu_expe,
                    COALESCE(a_areas.de_area,'Sin área') AS de_area,
                    COALESCE(a_areas.dc_area,'') AS dc_area_req,
                    (SELECT string_agg(x.no_crto, ', ' ORDER BY x.no_crto)
                     FROM (
                         SELECT DISTINCT pu.no_crto
                         FROM {$l}h_deta_pgo_prvdres hdp
                         INNER JOIN {$s}p_usrios pu ON hdp.cdgo_usrios = pu.cdgo_usrios
                         WHERE hdp.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres
                           AND hdp.in_esta NOT IN (0, 2) AND hdp.cdgo_usrios IS NOT NULL
                           AND NULLIF(BTRIM(COALESCE(hdp.de_urls_anxos, '')), '') IS NOT NULL
                           AND NULLIF(BTRIM(COALESCE(hdp.de_urls_adicional, '')), '') IS NOT NULL
                     ) x
                    ) AS no_crto_titular,
                    EXISTS (
                        SELECT 1 FROM {$l}h_deta_pgo_prvdres hos
                        WHERE hos.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres
                          AND hos.in_esta NOT IN (0, 2)
                          AND NULLIF(BTRIM(COALESCE(hos.de_urls_os, '')), '') IS NOT NULL
                    ) AS tiene_os_detalle,
                    (SELECT string_agg(pu.no_crto, ', ' ORDER BY pu.no_crto)
                     FROM {$s}r_crgos_lgin rcl
                     INNER JOIN {$s}p_lgin pl ON rcl.cdgo_lgin = pl.cdgo_lgin AND pl.in_esta = '1'
                     INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                     WHERE rcl.cdgo_crgo = ult_ntr.ult_cdgo_crgo_en) AS no_crto_asignado,
                    ult_ntr.cdgo_estdo_trmte AS ult_cdgo_estdo_trmte,
                    ult_ntr.dc_estdo_trmte   AS ult_dc_estdo_trmte,
                    ult_ntr.ult_fe_rcbdo
                    {$extraHito}
                FROM {$l}p_pgo_prvdres
                LEFT JOIN {$s}p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
                LEFT JOIN {$s}a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
                {$f['sqlJoinNtrUlt']}
                {$whereBase}
                ORDER BY p_pgo_prvdres.fe_pgo_prvdres DESC{$paginado}"
            );

            return $this->ok([
                'rows' => $this->prepararFilas($rows),
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit ?? $total,
                'area_id' => $areaId,
                'tipo' => $tipo,
                'ml' => $mlKey,
                'ml_label' => $mlKey !== null ? self::HITO_LABELS[$mlKey] : null,
                'ruta_por_dto' => $this->rutaPorDto($rows),
            ]);
        } catch (Throwable $e) {
            Log::error('[v2_lista_reporte_requerimientos_general] detalle: ' . $e->getMessage());

            return $this->fail('No se pudo cargar el detalle. Revise la conexión o consulte al administrador.');
        }
    }

    /**
     * Añade a cada fila los datos derivados que el legacy calculaba dentro del bucle de impresión.
     *
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function prepararFilas(array $rows): array
    {
        $hoy = Carbon::now(self::TZ)->startOfDay();

        foreach ($rows as $r) {
            $r->estado_display = self::estReportDisplay(
                $r->in_esta_prvdres ?? 0,
                $r->tiene_os_detalle ?? false,
                $r->ult_cdgo_estdo_trmte ?? null,
                $r->ult_dc_estdo_trmte ?? null
            );

            $ultFe = $r->ult_fe_rcbdo ?? null;
            $r->dias_area = null;
            if ($ultFe !== null && $ultFe !== '') {
                try {
                    $r->dias_area = (int) Carbon::parse(substr((string) $ultFe, 0, 10))->startOfDay()->diffInDays($hoy, true);
                } catch (Throwable) {
                    $r->dias_area = 0;
                }
            }

            $r->fe_registro = $r->fe_pgo_prvdres ? Carbon::parse($r->fe_pgo_prvdres)->format('d/m/Y') : '';
            $r->fe_registro_ymd = $r->fe_pgo_prvdres ? Carbon::parse($r->fe_pgo_prvdres)->format('Y-m-d') : '';
            $r->dto_int = ($r->cdgo_dtos_ntrnos !== null && $r->cdgo_dtos_ntrnos !== '') ? (int) $r->cdgo_dtos_ntrnos : 0;
        }

        return $rows;
    }

    /**
     * Ruta de áreas recorridas por cada trámite interno (una sola consulta para todas las filas).
     *
     * @param  list<object>  $rows
     * @return array<int, array{ruta_labels: list<string>, area_actual: string, cargo_actual: string}>
     */
    private function rutaPorDto(array $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if (! empty($r->cdgo_dtos_ntrnos)) {
                $ids[(int) $r->cdgo_dtos_ntrnos] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $s = $this->schemaSistema;
        $lista = array_keys($ids);
        $placeholders = implode(',', array_fill(0, count($lista), '?'));

        $movs = DB::select(
            "SELECT r.cdgo_dtos_ntrnos, r.cdgo_trmtes_ntrnos, a.dc_area, c.de_crgo
             FROM {$s}r_trmtes_ntrnos r
             INNER JOIN {$s}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
             INNER JOIN {$s}a_areas a ON c.cdgo_area = a.cdgo_area
             WHERE r.cdgo_dtos_ntrnos IN ({$placeholders})
             ORDER BY r.cdgo_dtos_ntrnos ASC, r.cdgo_trmtes_ntrnos ASC",
            $lista
        );

        $porDto = [];
        foreach ($movs as $m) {
            $porDto[(int) $m->cdgo_dtos_ntrnos][] = $m;
        }

        $out = [];
        foreach ($porDto as $did => $lst) {
            $labels = [];
            $ultimaArea = '';
            foreach ($lst as $m) {
                $ca = trim((string) $m->dc_area);
                if ($ca !== '' && $ca !== $ultimaArea) {
                    $labels[] = $ca;
                    $ultimaArea = $ca;
                }
            }
            $ultimo = end($lst);

            $out[$did] = [
                'ruta_labels' => $labels,
                'area_actual' => $labels === [] ? '—' : (string) end($labels),
                'cargo_actual' => $ultimo ? (trim((string) $ultimo->de_crgo) ?: '—') : '—',
            ];
        }

        return $out;
    }

    private function normalizarHito(?string $ml): ?string
    {
        $ml = preg_replace('/[^a-z_]/', '', mb_strtolower(trim((string) $ml)));
        if ($ml === null || $ml === '') {
            return null;
        }

        return in_array($ml, self::HITO_ORDEN, true) ? $ml : null;
    }

    // ── Exportaciones (CSV UTF-8 con BOM: no hay PhpSpreadsheet en composer) ──

    /**
     * Listado completo del detalle con los mismos filtros que la tabla (sin paginar).
     *
     * @return array{success: bool, data?: array{filename: string, contenido: string}, error?: string}
     */
    public function exportDetalleCsv(int $areaId, int $tipo, string $fi, string $ff): array
    {
        $res = $this->consultaDetalle($areaId, $tipo, 0, null, $fi, $ff, null);
        if (empty($res['success'])) {
            return $res;
        }

        $rutas = $res['data']['ruta_por_dto'];
        $filas = [[
            'N° Exp.', 'Asunto', 'Estado req.', 'Locador', 'Área solicitante', 'Ruta (áreas)',
            'Área actual', 'Cargo destino', 'Asignado', 'Días', 'F. registro', 'Enlace historial / requerimiento',
        ]];

        foreach ($res['data']['rows'] as $r) {
            $filas[] = $this->filaExport($r, $rutas);
        }

        $tipoNombre = self::TIPO_NOMBRE[$tipo] ?? ('tipo' . $tipo);
        $sufijo = $areaId < 0 ? 'todas_areas' : ('area' . $areaId);

        return $this->ok([
            'filename' => 'detalle_req_' . $sufijo . '_' . $tipoNombre . '_' . Carbon::now(self::TZ)->format('Ymd_His') . '.csv',
            'contenido' => $this->csv($filas),
        ]);
    }

    /**
     * Reporte por áreas: bloque resumen (píldoras) + bloque de requerimientos con su hito actual.
     *
     * @return array{success: bool, data?: array{filename: string, contenido: string}, error?: string}
     */
    public function exportAreasCsv(string $fi, string $ff): array
    {
        $stats = $this->statsPorArea($fi, $ff);
        if (empty($stats['success'])) {
            return $stats;
        }

        $filas = [
            ['PERÍODO', $fi . ' — ' . $ff],
            [],
            ['RESUMEN POR ÁREAS'],
        ];
        $filas[] = [
            'Área solicitante', 'Cód. área', 'Total req.', 'Estado tarjeta',
            'Hito', 'Detalle ruta (hito)', 'N avance (acum.)', 'N ahora', '% avance',
        ];
        foreach ($stats['data']['filas'] as $fila) {
            foreach ($fila['milestones'] as $ms) {
                $filas[] = [
                    $fila['area'],
                    $fila['id'],
                    $fila['total'],
                    $fila['status_text'],
                    $ms['label'],
                    (string) ($ms['area_line'] ?? ''),
                    $ms['n'],
                    $ms['n_here'],
                    min(100, (int) $ms['pct']) . '%',
                ];
            }
        }

        $filas[] = [];
        $filas[] = ['REQUERIMIENTOS DEL PERÍODO'];

        $detalle = $this->requerimientosConHito($fi, $ff);
        if (! empty($detalle['success'])) {
            $rutas = $detalle['data']['ruta_por_dto'];
            $filas[] = [
                'N° Exp.', 'Asunto', 'Estado req.', 'Hito (reporte por áreas)', 'Quién / cuándo (hito)',
                'Locador', 'Área solicitante', 'Ruta (áreas)', 'Área actual', 'Cargo destino',
                'Asignado', 'Días', 'F. registro', 'Enlace',
            ];
            foreach ($detalle['data']['rows'] as $r) {
                $base = $this->filaExport($r, $rutas);
                array_splice($base, 3, 0, [
                    (string) ($r->de_hito_rep_area ?? 'Sin clasificar en hitos'),
                    (string) ($r->rep_area_ml_oper_txt ?? ''),
                ]);
                $filas[] = $base;
            }
        }

        return $this->ok([
            'filename' => 'reporte_areas_req_' . Carbon::now(self::TZ)->format('Ymd_His') . '.csv',
            'contenido' => $this->csv($filas),
        ]);
    }

    /**
     * Todos los requerimientos del período con el hito excluyente en que se encuentran.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    private function requerimientosConHito(string $fi, string $ff): array
    {
        try {
            $rango = $this->rangoDatetime($fi, $ff);
            $f = $this->fragmentos($rango['fi'], $rango['ff']);
            $s = $this->schemaSistema;
            $l = self::SCHEMA_LOGISTICA;

            /* Del hito más avanzado al más temprano: el primer CASE que coincide es la etapa actual. */
            $orden = array_reverse(self::HITO_ORDEN);
            $caseHito = 'CASE ';
            $caseOper = 'CASE ';
            foreach ($orden as $k) {
                $lbl = str_replace("'", "''", self::HITO_LABELS[$k]);
                $caseHito .= 'WHEN (' . $f['sqlHito'][$k] . ") THEN '" . $lbl . "' ";
                $caseOper .= 'WHEN (' . $f['sqlHito'][$k] . ') THEN (' . $f['sqlOpHito'][$k] . ') ';
            }
            $caseHito .= "ELSE 'Sin clasificar en hitos' END";
            $caseOper .= 'ELSE NULL END';

            $rows = DB::select(
                "SELECT
                    p_pgo_prvdres.cdgo_pgo_prvdres,
                    p_pgo_prvdres.in_esta AS in_esta_prvdres,
                    p_pgo_prvdres.cdgo_dtos_ntrnos,
                    p_pgo_prvdres.fe_pgo_prvdres,
                    p_pgo_prvdres.de_pgo_prvdres,
                    COALESCE(p_dtos_ntrnos.nu_tram_todo, 'REQ-'||p_pgo_prvdres.cdgo_pgo_prvdres::text) AS nu_expe,
                    COALESCE(a_areas.de_area,'Sin área') AS de_area,
                    COALESCE(a_areas.dc_area,'') AS dc_area_req,
                    ({$caseHito}) AS de_hito_rep_area,
                    ({$caseOper}) AS rep_area_ml_oper_txt,
                    (SELECT string_agg(x.no_crto, ', ' ORDER BY x.no_crto)
                     FROM (
                         SELECT DISTINCT pu.no_crto
                         FROM {$l}h_deta_pgo_prvdres hdp
                         INNER JOIN {$s}p_usrios pu ON hdp.cdgo_usrios = pu.cdgo_usrios
                         WHERE hdp.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres
                           AND hdp.in_esta NOT IN (0, 2) AND hdp.cdgo_usrios IS NOT NULL
                           AND NULLIF(BTRIM(COALESCE(hdp.de_urls_anxos, '')), '') IS NOT NULL
                           AND NULLIF(BTRIM(COALESCE(hdp.de_urls_adicional, '')), '') IS NOT NULL
                     ) x
                    ) AS no_crto_titular,
                    EXISTS (
                        SELECT 1 FROM {$l}h_deta_pgo_prvdres hos
                        WHERE hos.cdgo_pgo_prvdres = p_pgo_prvdres.cdgo_pgo_prvdres
                          AND hos.in_esta NOT IN (0, 2)
                          AND NULLIF(BTRIM(COALESCE(hos.de_urls_os, '')), '') IS NOT NULL
                    ) AS tiene_os_detalle,
                    (SELECT string_agg(pu.no_crto, ', ' ORDER BY pu.no_crto)
                     FROM {$s}r_crgos_lgin rcl
                     INNER JOIN {$s}p_lgin pl ON rcl.cdgo_lgin = pl.cdgo_lgin AND pl.in_esta = '1'
                     INNER JOIN {$s}p_usrios pu ON pl.cdgo_usrios = pu.cdgo_usrios
                     WHERE rcl.cdgo_crgo = ult_ntr.ult_cdgo_crgo_en) AS no_crto_asignado,
                    ult_ntr.cdgo_estdo_trmte AS ult_cdgo_estdo_trmte,
                    ult_ntr.dc_estdo_trmte   AS ult_dc_estdo_trmte,
                    ult_ntr.ult_fe_rcbdo
                FROM {$l}p_pgo_prvdres
                LEFT JOIN {$s}p_dtos_ntrnos ON p_pgo_prvdres.cdgo_dtos_ntrnos = p_dtos_ntrnos.cdgo_dtos_ntrnos
                LEFT JOIN {$s}a_areas ON p_pgo_prvdres.cdgo_area = a_areas.cdgo_area
                {$f['sqlJoinNtrUlt']}
                WHERE p_pgo_prvdres.in_esta NOT IN (0,2) {$f['whereFecha']} {$f['whereReqAdjuntos']}
                ORDER BY COALESCE(a_areas.de_area,'') ASC, p_pgo_prvdres.fe_pgo_prvdres DESC"
            );

            return $this->ok([
                'rows' => $this->prepararFilas($rows),
                'ruta_por_dto' => $this->rutaPorDto($rows),
            ]);
        } catch (Throwable $e) {
            Log::error('[v2_lista_reporte_requerimientos_general] requerimientosConHito: ' . $e->getMessage());

            return $this->fail('No se pudo generar la exportación del reporte por áreas.');
        }
    }

    /**
     * @param  array<int, array{ruta_labels: list<string>, area_actual: string, cargo_actual: string}>  $rutas
     * @return list<string|int>
     */
    private function filaExport(object $r, array $rutas): array
    {
        $dto = (int) ($r->dto_int ?? 0);
        $info = $rutas[$dto] ?? null;

        $areaActual = $info['area_actual'] ?? '—';
        $cargoDestino = $info['cargo_actual'] ?? '—';
        $rutaLabels = $info['ruta_labels'] ?? [];

        $areaSol = trim((string) $r->dc_area_req) !== '' ? (string) $r->dc_area_req : (string) ($r->de_area ?? '');

        if ($dto <= 0) {
            $ruta = trim((string) $r->dc_area_req) !== '' ? (string) $r->dc_area_req : 'Sin trámite interno aún';
        } else {
            $ruta = $rutaLabels !== [] ? implode(' > ', $rutaLabels) : '—';
        }

        $estado = $r->estado_display;
        $locador = trim((string) ($r->no_crto_titular ?? '')) ?: 'Sin asignar';
        $asignado = trim((string) ($r->no_crto_asignado ?? '')) ?: 'Sin asignar';

        if ($dto > 0) {
            $enlace = url('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago/seguimiento_tramite')
                . '?nu_expe=' . rawurlencode((string) $dto);
        } else {
            $enlace = url('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago')
                . '?intCdgoPgoPrvdres=' . rawurlencode((string) ($r->cdgo_pgo_prvdres ?? ''));
        }

        return [
            (string) $r->nu_expe,
            (string) ($r->de_pgo_prvdres ?? ''),
            $estado['label'] . ' (' . $estado['cod_line'] . ')',
            $locador,
            $areaSol,
            $ruta,
            $areaActual,
            $cargoDestino,
            $asignado,
            $r->dias_area === null ? '' : (int) $r->dias_area,
            (string) $r->fe_registro,
            $enlace,
        ];
    }

    /**
     * CSV UTF-8 con BOM y separador «;» para que Excel lo abra correctamente en configuración local.
     *
     * @param  list<list<string|int>>  $filas
     */
    private function csv(array $filas): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($filas as $fila) {
            fputcsv($handle, $fila, ';', '"', '\\');
        }
        rewind($handle);
        $contenido = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $contenido;
    }

    private function ok(array $data): array
    {
        return ['success' => true, 'data' => $data];
    }

    private function fail(string $error): array
    {
        return ['success' => false, 'error' => $error];
    }
}
