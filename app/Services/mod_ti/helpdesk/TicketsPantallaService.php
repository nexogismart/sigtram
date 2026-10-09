<?php

namespace App\Services\mod_ti\helpdesk;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Datos para la pantalla TV de tickets (Kanban en vivo)
 * (migrado desde sigtram_antiguo/.../helpdesk/get_tickets.php).
 */
class TicketsPantallaService
{
    private const AREA_OTI = 103;

    private const CARGO_EXCLUIDO = 496;

    private readonly string $schemaSistema;

    private readonly string $schemaTi;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $this->schemaTi = 'ti.';
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerDatos(): array
    {
        $emptyStats = [
            'situacion_1' => 0,
            'situacion_2' => 0,
            'situacion_3' => 0,
            'situacion_4' => 0,
            'situacion_5' => 0,
            'finalizados_hoy' => 0,
            'total_disponibles' => 0,
            'total_ocupados' => 0,
        ];

        try {
            $today = date('Y-m-d');
            $ticketsBySituation = [];
            $staffOcupados = [];

            for ($situacion = 1; $situacion <= 5; $situacion++) {
                $rows = DB::select($this->sqlTicketsPorSituacion($situacion), [$today]);
                $ticketsBySituation[$situacion] = [];

                foreach ($rows as $row) {
                    $staffId = (int) ($row->id_staff_asignado ?? 0);
                    if (($situacion === 2 || $situacion === 3) && $staffId > 0) {
                        $staffOcupados[$staffId] = ($staffOcupados[$staffId] ?? 0) + 1;
                    }

                    $ticketsBySituation[$situacion][] = [
                        'id' => 'T-' . str_pad((string) $row->id, 3, '0', STR_PAD_LEFT),
                        'asunto' => ! empty($row->asunto) ? (string) $row->asunto : 'Sin asunto',
                        'detalle' => ! empty($row->tipo_incidencia) ? (string) $row->tipo_incidencia : 'Sin especificar',
                        'fecha' => $row->fecha ? date('H:i', strtotime((string) $row->fecha)) : '',
                        'solicitante' => ! empty($row->solicitante) ? (string) $row->solicitante : 'Desconocido',
                        'staff' => ! empty($row->staff_asignado) ? (string) $row->staff_asignado : 'Sin asignar',
                        'prioridad' => ! empty($row->prioridad) ? (string) $row->prioridad : 'Normal',
                        'situacion' => $situacion,
                    ];
                }
            }

            $staffResueltos = [];
            foreach (DB::select($this->sqlResueltosPorPersona(), [$today, $today]) as $row) {
                $staffResueltos[(int) $row->id_staff] = (int) $row->tickets_resueltos;
            }

            $staffData = [];
            foreach (DB::select($this->sqlStaff()) as $row) {
                $staffId = (int) $row->id_login;
                $asignados = $staffOcupados[$staffId] ?? 0;
                $resueltos = $staffResueltos[$staffId] ?? 0;

                $staffData[] = [
                    'id' => $staffId,
                    'name' => ! empty($row->nombre_completo) ? (string) $row->nombre_completo : 'Sin nombre',
                    'area' => 'OTI',
                    'status' => $asignados > 0 ? 'busy' : 'available',
                    'cases' => $asignados,
                    'resolved' => $resueltos,
                ];
            }

            $totalDisponibles = 0;
            $totalOcupados = 0;
            foreach ($staffData as $member) {
                if ($member['status'] === 'available') {
                    $totalDisponibles++;
                } elseif ($member['status'] === 'busy') {
                    $totalOcupados++;
                }
            }

            $stats = [
                'situacion_1' => count($ticketsBySituation[1]),
                'situacion_2' => count($ticketsBySituation[2]),
                'situacion_3' => count($ticketsBySituation[3]),
                'situacion_4' => count($ticketsBySituation[4]),
                'situacion_5' => count($ticketsBySituation[5]),
                'finalizados_hoy' => count($ticketsBySituation[4]) + count($ticketsBySituation[5]),
                'total_disponibles' => $totalDisponibles,
                'total_ocupados' => $totalOcupados,
            ];

            return [
                'success' => true,
                'situation1' => $ticketsBySituation[1],
                'situation2' => $ticketsBySituation[2],
                'situation3' => $ticketsBySituation[3],
                'situation4' => $ticketsBySituation[4],
                'situation5' => $ticketsBySituation[5],
                'staff' => $staffData,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            Log::error('[helpdesk.tickets] obtenerDatos: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage(),
                'situation1' => [],
                'situation2' => [],
                'situation3' => [],
                'situation4' => [],
                'situation5' => [],
                'staff' => [],
                'stats' => $emptyStats,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }
    }

    private function sqlTicketsPorSituacion(int $situacion): string
    {
        $sis = $this->schemaSistema;
        $ti = $this->schemaTi;

        $select = "
            SELECT DISTINCT
                ts.id,
                ts.asunto,
                i.detalle AS tipo_incidencia,
                ts.fecha,
                pu_solicitante.no_crto AS solicitante,
                COALESCE(pu_asignado.no_crto, 'Sin asignar') AS staff_asignado,
                COALESCE(pl_asignado.cdgo_lgin, 0) AS id_staff_asignado,
                b.situacion,
                COALESCE(p.nombre, 'Normal') AS prioridad
            FROM {$ti}tckt_slctd ts
            INNER JOIN {$ti}bndja b ON ts.id = b.idticket
            INNER JOIN {$ti}incdncia i ON i.id = ts.incidencia
            INNER JOIN {$sis}p_lgin pl_solicitante ON ts.idlogin_or = pl_solicitante.cdgo_lgin
            INNER JOIN {$sis}p_usrios pu_solicitante ON pu_solicitante.cdgo_usrios = pl_solicitante.cdgo_usrios
            LEFT JOIN {$sis}p_lgin pl_asignado ON b.idlogin_des = pl_asignado.cdgo_lgin
            LEFT JOIN {$sis}p_usrios pu_asignado ON pu_asignado.cdgo_usrios = pl_asignado.cdgo_usrios
            LEFT JOIN {$ti}prrdd p ON ts.id_prioridad = p.id
        ";

        if ($situacion === 5) {
            return $select . "
                WHERE b.situacion = 5
                  AND b.estado = 1
                  AND DATE(b.fecha) = ?
                  AND EXISTS (
                      SELECT 1
                      FROM {$ti}bndja b4
                      WHERE b4.idticket = ts.id
                        AND b4.situacion = 4
                        AND DATE(b4.fecha) = DATE(b.fecha)
                  )
                ORDER BY ts.fecha ASC
                LIMIT 20
            ";
        }

        return $select . "
            WHERE b.situacion = {$situacion}
              AND b.estado = 1
              AND DATE(b.fecha) = ?
            ORDER BY ts.fecha ASC
            LIMIT 20
        ";
    }

    private function sqlResueltosPorPersona(): string
    {
        $sis = $this->schemaSistema;
        $ti = $this->schemaTi;
        $area = self::AREA_OTI;
        $cargo = self::CARGO_EXCLUIDO;

        return "
            SELECT id_staff, COUNT(DISTINCT idticket) AS tickets_resueltos
            FROM (
                SELECT b.idlogin_des AS id_staff, b.idticket
                FROM {$ti}bndja b
                INNER JOIN {$sis}p_lgin pl ON b.idlogin_des = pl.cdgo_lgin
                INNER JOIN {$sis}r_crgos_lgin rcl ON pl.cdgo_lgin = rcl.cdgo_lgin
                INNER JOIN {$sis}p_crgos pc ON rcl.cdgo_crgo = pc.cdgo_crgo
                WHERE b.situacion = 4
                  AND b.estado = 1
                  AND DATE(b.fecha) = ?
                  AND pc.cdgo_area = {$area}
                  AND pc.cdgo_crgo != {$cargo}
                  AND pl.in_esta = '1'

                UNION

                SELECT b4.idlogin_des AS id_staff, b5.idticket
                FROM {$ti}bndja b5
                INNER JOIN {$ti}bndja b4 ON b4.idticket = b5.idticket
                    AND b4.situacion = 4
                    AND DATE(b4.fecha) = DATE(b5.fecha)
                INNER JOIN {$sis}p_lgin pl ON b4.idlogin_des = pl.cdgo_lgin
                INNER JOIN {$sis}r_crgos_lgin rcl ON pl.cdgo_lgin = rcl.cdgo_lgin
                INNER JOIN {$sis}p_crgos pc ON rcl.cdgo_crgo = pc.cdgo_crgo
                WHERE b5.situacion = 5
                  AND b5.estado = 1
                  AND DATE(b5.fecha) = ?
                  AND pc.cdgo_area = {$area}
                  AND pc.cdgo_crgo != {$cargo}
                  AND pl.in_esta = '1'
            ) resueltos
            GROUP BY id_staff
        ";
    }

    private function sqlStaff(): string
    {
        $sis = $this->schemaSistema;
        $area = self::AREA_OTI;
        $cargo = self::CARGO_EXCLUIDO;

        return "
            SELECT p_lgin.cdgo_lgin AS id_login,
                   MAX(p_usrios.no_crto) AS nombre_completo
            FROM {$sis}p_lgin
            INNER JOIN {$sis}r_crgos_lgin ON p_lgin.cdgo_lgin = r_crgos_lgin.cdgo_lgin
            INNER JOIN {$sis}p_crgos ON r_crgos_lgin.cdgo_crgo = p_crgos.cdgo_crgo
            INNER JOIN {$sis}a_areas ON p_crgos.cdgo_area = a_areas.cdgo_area
            INNER JOIN {$sis}p_usrios ON p_lgin.cdgo_usrios = p_usrios.cdgo_usrios
            WHERE p_crgos.cdgo_area = {$area}
              AND p_crgos.cdgo_crgo != {$cargo}
              AND p_lgin.in_esta = '1'
            GROUP BY p_lgin.cdgo_lgin
            ORDER BY nombre_completo ASC
        ";
    }
}
