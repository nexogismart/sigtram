<?php

use App\Services\mod_ti\DispositivoSegService;

if (!function_exists('ds_credenciales_camara_por_ip')) {
    /**
     * @param mixed $conexion Ignorado (compatibilidad con legado)
     * @return array{usuario:string,password:string}
     */
    function ds_credenciales_camara_por_ip($ip, $conexion = null)
    {
        return app(DispositivoSegService::class)->credencialesPorIp((string) $ip);
    }
}
