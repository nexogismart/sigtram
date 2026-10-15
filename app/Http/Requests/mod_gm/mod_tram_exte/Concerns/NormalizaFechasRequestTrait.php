<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte\Concerns;

/**
 * Trait para normalizar entradas de fecha desde inputs HTML5 datetime-local (Y-m-d\TH:i)
 * al formato canónico de almacenamiento y validación de base de datos (Y-m-d H:i:s).
 * Resuelve CE-P1-002.
 */
trait NormalizaFechasRequestTrait
{
    /**
     * Normaliza los campos de fecha especificados.
     *
     * @param array<string> $campos
     * @return void
     */
    protected function normalizarCamposFecha(array $campos): void
    {
        $updates = [];
        foreach ($campos as $campo) {
            $val = $this->input($campo);
            if (is_string($val)) {
                $val = trim($val);
                if ($val === '') {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $val)) {
                    $updates[$campo] = str_replace('T', ' ', $val) . ':00';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $val)) {
                    $updates[$campo] = str_replace('T', ' ', $val);
                }
            }
        }

        if (!empty($updates)) {
            $this->merge($updates);
        }
    }
}
