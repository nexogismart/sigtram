<?php

namespace App\Helpers;

class GduHelper
{
    public static function fechaPeru(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }

        return substr($fecha, 8, 2) . '/' . substr($fecha, 5, 2) . '/' . substr($fecha, 0, 4);
    }

    public static function fechaEeuu(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            return substr($fecha, 6, 4) . '-' . substr($fecha, 3, 2) . '-' . substr($fecha, 0, 2);
        }

        return $fecha;
    }

    public static function cerosIzquierda(string $valor, int $longitud): string
    {
        $partes = explode('.', $valor);
        $diferencia = $longitud - strlen($partes[0]);
        $ceros = str_repeat('0', max(0, $diferencia));

        return $ceros . $valor;
    }

    public static function formatoExpediente(?string $nuExpeTodo): string
    {
        if (empty($nuExpeTodo)) {
            return '';
        }

        $partes = explode('-', $nuExpeTodo);
        if (count($partes) < 3) {
            return $nuExpeTodo;
        }

        $sufijo = substr($partes[2], 2, 10);

        return $partes[0] . '-' . self::cerosIzquierda($partes[1] . '-' . $sufijo, 8);
    }

    public static function truncar(?string $texto, int $longitud): string
    {
        $texto = (string) $texto;

        if (strlen($texto) <= $longitud) {
            return $texto;
        }

        return substr($texto, 0, $longitud);
    }
}
