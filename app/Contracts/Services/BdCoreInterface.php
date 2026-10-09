<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Database\Connection;

/**
 * Interface BdCoreInterface
 *
 * Contrato para el servicio central de bases de datos auxiliares (BdCore).
 * Gestiona conexiones secundarias mediante Lazy Loading y provee métodos
 * de consulta segura de sólo lectura con sanitización UTF-8.
 */
interface BdCoreInterface
{
    /**
     * Obtiene una conexión auxiliar por su nombre (Lazy Loading).
     *
     * @param string $nombre Nombre de la conexión (ej. 'predio', 'reniec', 'satmunxp', 'siga', 'camara_seg', 'asterisk').
     * @return Connection Conexión a la base de datos auxiliar.
     */
    public function obtenerConexion(string $nombre): Connection;

    /**
     * Ejecuta una consulta SELECT sobre una conexión externa, devolviendo los datos sanitizados en UTF-8.
     * Garantiza que sólo se ejecuten consultas de lectura.
     *
     * @param string $conexion Nombre de la conexión auxiliar.
     * @param string $query Consulta SQL (Sólo lectura SELECT).
     * @param array $bindings Enlace de parámetros PDO.
     * @return array Conjunto de registros sanitizados.
     */
    public function consultar(string $conexion, string $query, array $bindings = []): array;

    /**
     * Sanitiza una cadena convirtiendo codificaciones legacy (ej. Windows-1252, ISO-8859-1) a UTF-8.
     *
     * @param string|null $string Cadena a sanitizar.
     * @return string Cadena convertida a UTF-8.
     */
    public function sanitizarUtf8(?string $string): string;
}
