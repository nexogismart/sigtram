<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\Services\BdCoreInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class BdCore
 *
 * Implementa el servicio núcleo de bases de datos auxiliares (BdCore).
 * Permite la gestión diferida (Lazy Loading) de conexiones a otras BDs
 * municipales y asegura el carácter de sólo lectura de las consultas externas.
 */
class BdCore implements BdCoreInterface
{
    /**
     * {@inheritdoc}
     */
    public function obtenerConexion(string $nombre): Connection
    {
        // Laravel implementa Lazy Loading por defecto: el socket TCP no se abre
        // hasta que se realice la primera operación contra la conexión.
        /** @var Connection $connection */
        $connection = DB::connection($nombre);

        return $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function consultar(string $conexion, string $query, array $bindings = []): array
    {
        $trimmedQuery = ltrim(strtolower($query));

        // Validación estricta de sólo lectura (SELECT / WITH)
        if (!Str::startsWith($trimmedQuery, ['select', 'with'])) {
            throw new \InvalidArgumentException('Operación no permitida: Solo se permiten consultas de lectura (SELECT) en bases de datos auxiliares.');
        }

        $connection = $this->obtenerConexion($conexion);
        $results = $connection->select($query, $bindings);

        // Sanitización y normalización de resultados a UTF-8
        $sanitizedResults = [];
        foreach ($results as $row) {
            $arrayRow = (array) $row;
            $sanitizedRow = [];
            foreach ($arrayRow as $key => $value) {
                if (is_string($value)) {
                    $sanitizedRow[$key] = $this->sanitizarUtf8($value);
                } else {
                    $sanitizedRow[$key] = $value;
                }
            }
            $sanitizedResults[] = $sanitizedRow;
        }

        return $sanitizedResults;
    }

    /**
     * {@inheritdoc}
     */
    public function sanitizarUtf8(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        // Si la cadena no está bien formada en UTF-8, asumimos codificación de origen Windows-1252 o ISO-8859-1
        if (!mb_check_encoding($string, 'UTF-8')) {
            return mb_convert_encoding($string, 'UTF-8', 'Windows-1252');
        }

        return $string;
    }
}
