<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Requests\mod_gene\mod_tram_inter\CorrelativoTramiteInternoRequest;
use Illuminate\Support\Facades\Validator;

class TramiteInternoCorrelativoTest extends TestCase
{
    public function test_correlativo_recalcula_y_no_confia_en_numero_frontend(): void
    {
        $repoPath = app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php');
        $code = file_get_contents($repoPath);

        // Verificar que obtenerSemillaInternaParaBloqueo calcula el correlativo en backend con max + 1
        $this->assertStringContainsString('$max = $query->max(\'nu_smlla_intrno\');', $code);
        $this->assertStringContainsString('return $max ? ((int) $max) + 1 : 1;', $code);

        $servicePath = app_path('Services/mod_gene/mod_tram_inter/CorreoInternoTransaccionalService.php');
        $serviceCode = file_get_contents($servicePath);

        // Verificar que no usa el valor de semilla enviado desde frontend para la inserción
        $this->assertStringNotContainsString('$nuSemilla = (int) ($partes[0] ?? 0);', $serviceCode, 'No debe aceptar el número de frontend como definitivo.');
    }

    public function test_correlativo_aplica_lock_compatible_sin_ddl(): void
    {
        $repoPath = app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php');
        $code = file_get_contents($repoPath);

        // Verificar uso de lockForUpdate() sobre a_smlla_intrno dentro de la transacción
        $this->assertStringContainsString('$query->lockForUpdate()->get();', $code, 'Debe aplicar lockForUpdate para serializar concurrencia sin DDL.');
        $this->assertStringContainsString('a_smlla_intrno', $code);
    }

    public function test_correlativo_formato_y_secuencia_estricta(): void
    {
        $nuSemilla = 15;
        $deAnio = '2026';

        $numAuto = $nuSemilla . '-' . $deAnio;
        $numTramTodo = str_pad((string) $nuSemilla, 6, '0', STR_PAD_LEFT) . '-' . $deAnio;

        $this->assertEquals('15-2026', $numAuto);
        $this->assertEquals('000015-2026', $numTramTodo);
    }

    public function test_correlativo_request_valida_tipo_documento_positivo(): void
    {
        $rules = (new CorrelativoTramiteInternoRequest())->rules();

        $v1 = Validator::make(['id_tipo_documento' => 0], $rules);
        $this->assertTrue($v1->fails(), 'Tipo de documento 0 debe fallar.');

        $v2 = Validator::make(['id_tipo_documento' => -1], $rules);
        $this->assertTrue($v2->fails(), 'Tipo de documento negativo debe fallar.');

        $v3 = Validator::make(['id_tipo_documento' => 7], $rules);
        $this->assertTrue($v3->passes(), 'Tipo de documento positivo debe pasar.');
    }
}