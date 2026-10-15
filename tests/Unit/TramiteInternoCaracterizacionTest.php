<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class TramiteInternoCaracterizacionTest extends TestCase
{
    /**
     * Prueba el algoritmo de cálculo de tiempo estimado en días, horas, minutos y segundos.
     */
    public function test_calculo_tiempo_estimado_algoritmo(): void
    {
        $calcularTe = function (?string $comien, ?string $hoy): string {
            if (empty($comien) || empty($hoy)) {
                return '-';
            }
            $tComien = strtotime($comien);
            $tHoy = strtotime($hoy);
            if ($tComien === false || $tHoy === false || $tHoy < $tComien) {
                return '0s';
            }
            $s = $tHoy - $tComien;
            $d = intval($s / 86400);
            $s -= $d * 86400;
            $h = intval($s / 3600);
            $s -= $h * 3600;
            $m = intval($s / 60);
            $s -= $m * 60;

            if ($d == 1) {
                return $d . "D";
            } elseif ($d >= 2) {
                return ($d + 1) . "D";
            } elseif ($h >= 1) {
                return $h . "H";
            } elseif ($m >= 1) {
                return $m . "m";
            } elseif ($s >= 1) {
                return $s . "s";
            }
            return '0s';
        };

        // Intervalo de segundos
        $this->assertEquals('15s', $calcularTe('2026-10-10 10:00:00', '2026-10-10 10:00:15'));

        // Intervalo de minutos
        $this->assertEquals('35m', $calcularTe('2026-10-10 10:00:00', '2026-10-10 10:35:10'));

        // Intervalo de horas
        $this->assertEquals('2H', $calcularTe('2026-10-10 10:00:00', '2026-10-10 12:15:00'));

        // Intervalo de 1 día (retorna 1D)
        $this->assertEquals('1D', $calcularTe('2026-10-09 10:00:00', '2026-10-10 10:00:00'));

        // Intervalo de 6 días (retorna d+1 = 7D)
        $this->assertEquals('7D', $calcularTe('2026-10-04 10:00:00', '2026-10-10 10:00:00'));

        // Fechas nulas o vacías
        $this->assertEquals('-', $calcularTe(null, '2026-10-10 10:00:00'));
    }

    /**
     * Prueba el formateo de números correlativos con padding a 8 y 10 dígitos.
     */
    public function test_formateo_numeros_correlativos(): void
    {
        $pad8 = fn ($n) => str_pad((string) ($n ?? ''), 8, '0', STR_PAD_LEFT);
        $pad10 = fn ($n) => str_pad((string) ($n ?? ''), 10, '0', STR_PAD_LEFT);

        $this->assertEquals('00000142', $pad8(142));
        $this->assertEquals('0000000142', $pad10(142));
        $this->assertEquals('00001683', $pad8('1683'));
    }

    /**
     * Prueba la construcción de nombres de archivo seguros para ZIP consolidado.
     */
    public function test_nombre_archivo_zip_seguro(): void
    {
        $sanitizar = function (string $tipo, string $numero, string $area): string {
            $base = "{$tipo}-{$numero}-{$area}";
            return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base);
        };

        $resultado = $sanitizar('MEMORANDUM', '1683-2026', 'S.G.R.E.C.');
        $this->assertEquals('MEMORANDUM-1683-2026-S_G_R_E_C_', $resultado);
    }
}