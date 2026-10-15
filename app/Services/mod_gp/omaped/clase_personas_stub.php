<?php

/**
 * Stub mínimo de clase_personas para OMAPED en Laravel.
 * Usa la conexión pg en $GLOBALS['OMAPED_PG'].
 */
class clase_personas
{
    public static function guardar_persona($datos, $id = null, $user_modi = null)
    {
        $cn = $GLOBALS['OMAPED_PG'] ?? null;
        if (!$cn) {
            return false;
        }

        $inTipo = $datos['in_tipo_pers'] ?? '0';
        $noCrto = self::formatearNombreCompleto($datos, $inTipo);
        if ($noCrto === '') {
            return false;
        }

        $idGenerado = self::upsertUsuarioBase($cn, $datos, $noCrto, $id);
        if (!$idGenerado) {
            return false;
        }

        if ($inTipo == '0') {
            return self::gestionarDetalleNatural($cn, $idGenerado, $datos, $user_modi);
        }

        return true;
    }

    private static function formatearNombreCompleto($datos, $tipo)
    {
        if ($tipo == '0') {
            $parts = array_filter([
                $datos['no_usrio'] ?? '',
                $datos['ap_pate'] ?? '',
                $datos['ap_mate'] ?? '',
            ]);

            return mb_strtoupper(implode(' ', $parts), 'UTF-8');
        }

        return mb_strtoupper($datos['rz_soci'] ?? '', 'UTF-8');
    }

    private static function esc($cn, $v)
    {
        return pg_escape_string($cn, (string) $v);
    }

    private static function upsertUsuarioBase($cn, $datos, $noCrto, $id)
    {
        $cmpniaId = 0;
        if (isset($datos['cdgo_cmpnia'])) {
            $raw = $datos['cdgo_cmpnia'];
            $cmpniaId = (is_numeric($raw) && (int) $raw > 0) ? (int) $raw : 0;
        } elseif (function_exists('session')) {
            $cmpniaId = (int) session(\App\Support\SessionKeys::USER_COMPANY, 0);
        }

        $campos = [
            'nu_docu' => $datos['nu_docu'] ?? '',
            'no_crto' => $noCrto,
            'de_dire' => $datos['de_dire'] ?? '',
            'nu_tele' => $datos['nu_tele'] ?? '',
            'co_ubig' => $datos['co_ubig'] ?? '',
            'de_mail' => $datos['de_mail'] ?? '',
            'in_tipo_pers' => $datos['in_tipo_pers'] ?? '0',
            'fe_inic_acti' => $datos['fe_inic_acti'] ?? date('Y-m-d'),
            'nu_ruc' => $datos['nu_ruc'] ?? '',
        ];

        if ($cmpniaId > 0) {
            $campos['cdgo_cmpnia'] = (string) $cmpniaId;
        }

        if ($id) {
            $sets = [];
            foreach ($campos as $c => $v) {
                $sets[] = "$c = '" . self::esc($cn, $v) . "'";
            }
            $sql = 'UPDATE sistema.p_usrios SET ' . implode(', ', $sets) . ' WHERE cdgo_usrios = ' . (int) $id;
            $rs = @pg_query($cn, $sql);

            return $rs ? (int) $id : false;
        }

        $cols = array_keys($campos);
        $vals = [];
        foreach ($campos as $v) {
            $vals[] = "'" . self::esc($cn, $v) . "'";
        }
        $cols[] = 'in_esta';
        $vals[] = "'1'";
        $sql = 'INSERT INTO sistema.p_usrios (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ') RETURNING cdgo_usrios';
        $rs = @pg_query($cn, $sql);
        if (!$rs) {
            return false;
        }
        $row = pg_fetch_assoc($rs);

        return $row ? (int) $row['cdgo_usrios'] : false;
    }

    private static function gestionarDetalleNatural($cn, $id, $datos, $user)
    {
        $id = (int) $id;
        $check = @pg_query($cn, "SELECT 1 FROM sistema.h_usrios_ntral WHERE cdgo_usrios = $id");
        $existe = $check && pg_num_rows($check) > 0;
        $cdgoDoc = isset($datos['cdgo_dcmntos_iden']) && (int) $datos['cdgo_dcmntos_iden'] > 0
            ? (int) $datos['cdgo_dcmntos_iden'] : 1;
        $no = self::esc($cn, $datos['no_usrio'] ?? '');
        $ap = self::esc($cn, $datos['ap_pate'] ?? '');
        $am = self::esc($cn, $datos['ap_mate'] ?? '');
        $userSql = $user ? ', co_usua_modi = ' . (int) $user : '';

        if ($existe) {
            $sql = "UPDATE sistema.h_usrios_ntral SET
                no_usrio=UPPER('$no'), ap_pate=UPPER('$ap'), ap_mate=UPPER('$am'),
                cdgo_dcmntos_iden='$cdgoDoc', ts_usua_modi=NOW() $userSql
                WHERE cdgo_usrios = $id";
        } else {
            $sql = "INSERT INTO sistema.h_usrios_ntral
                (cdgo_usrios, no_usrio, ap_pate, ap_mate, in_esta, cdgo_dcmntos_iden)
                VALUES ($id, UPPER('$no'), UPPER('$ap'), UPPER('$am'), '1', '$cdgoDoc')";
        }

        return (bool) @pg_query($cn, $sql);
    }
}
