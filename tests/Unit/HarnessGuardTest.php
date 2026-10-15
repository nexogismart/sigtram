<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HarnessGuardTest extends TestCase
{
    /**
     * Verifica que cualquier intento de ejecutar una consulta DB real sin autorización
     * sea interceptado y abortado por el Guardrail P0 antes de conectar.
     */
    public function test_guardrail_p0_bloquea_consultas_db_accidentales(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GUARDRAIL P0 ACTIVADO');

        DB::select('SELECT 1');
    }
}
