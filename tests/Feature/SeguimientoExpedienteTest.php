<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeguimientoExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private ?User $user = null;
    private string $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        
        // Obtener un usuario activo de la base de datos para autenticación
        $this->user = User::where('in_esta', '1')->first();
    }

    /**
     * Test de acceso a la vista principal
     */
    public function test_acceso_vista_seguimiento_expedientes(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No hay usuarios activos en la BD para probar.');
        }

        // Simular rol 3 en r_prmsos para el perfil del usuario
        DB::table($this->schema . 'r_prmsos')
            ->updateOrInsert(
                ['cdgo_prfle' => $this->user->cdgo_prfle, 'cdgo_rle' => 3],
                []
            );

        // Obtener un cargo activo del usuario
        $cargoId = DB::table($this->schema . 'r_crgos_lgin')
            ->where('cdgo_lgin', $this->user->cdgo_lgin)
            ->value('cdgo_crgo');

        if (!$cargoId) {
            $this->markTestSkipped('El usuario no tiene un cargo asociado en r_crgos_lgin.');
        }

        $response = $this->actingAs($this->user)
            ->withSession(['sigtram_cargo_id' => $cargoId])
            ->get('/mod_gm/mod_tram_exte/segi_expedi');

        $response->assertStatus(200);
        $response->assertViewIs('mod_gm.mod_tram_exte.segi_expedi');
    }

    /**
     * Test de consulta y filtrado de la bandeja
     */
    public function test_consultar_tabla_bandeja(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No hay usuarios activos en la BD para probar.');
        }

        DB::table($this->schema . 'r_prmsos')
            ->updateOrInsert(
                ['cdgo_prfle' => $this->user->cdgo_prfle, 'cdgo_rle' => 3],
                []
            );

        $cargoId = DB::table($this->schema . 'r_crgos_lgin')
            ->where('cdgo_lgin', $this->user->cdgo_lgin)
            ->value('cdgo_crgo');

        if (!$cargoId) {
            $this->markTestSkipped('El usuario no tiene un cargo asociado.');
        }

        $response = $this->actingAs($this->user)
            ->withSession(['sigtram_cargo_id' => $cargoId])
            ->postJson(route('mod_gm.segi_expedi.tabla'), [
                'bandeja' => 'entrada',
                'criterio' => 'expe_todo',
                'descripcion' => ''
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success'
        ]);
        $response->assertJsonStructure([
            'status',
            'data',
            'cargo_id',
            'area_id'
        ]);
    }

    /**
     * Test de recepción de expediente
     */
    public function test_recepcionar_expediente(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No hay usuarios activos en la BD para probar.');
        }

        DB::table($this->schema . 'r_prmsos')
            ->updateOrInsert(
                ['cdgo_prfle' => $this->user->cdgo_prfle, 'cdgo_rle' => 3],
                []
            );

        $cargoId = DB::table($this->schema . 'r_crgos_lgin')
            ->where('cdgo_lgin', $this->user->cdgo_lgin)
            ->value('cdgo_crgo');

        if (!$cargoId) {
            $this->markTestSkipped('El usuario no tiene un cargo asociado.');
        }

        // Buscar un expediente pendiente de recepción en r_trmtes_dcmntos
        $pendiente = DB::table($this->schema . 'r_trmtes_dcmntos as r')
            ->join($this->schema . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->select('r.cdgo_dto_trmte')
            ->where('r.cdgo_crgo_en', $cargoId)
            ->where('r.cdgo_crgo_sa', '<>', $cargoId)
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['5','12','29'])
            ->first();

        if (!$pendiente) {
            // Si no hay ninguno, creamos un registro temporal para probar la transacción
            $dtoId = DB::table($this->schema . 'p_dto_trmtes')->insertGetId([
                'nu_expe_todo' => 'EXT-TEST-2026',
                'fe_ingr_trmte' => now(),
                'cdgo_usrios' => 1,
                'cdgo_cmpnia' => 2,
                'cdgo_area' => 1,
                'co_usua_ingr' => $this->user->cdgo_lgin,
                'in_esta' => '1'
            ], 'cdgo_dto_trmte');

            DB::table($this->schema . 'r_trmtes_dcmntos')->insert([
                'cdgo_crgo_sa' => 9999, // Otro cargo
                'cdgo_crgo_en' => $cargoId,
                'cdgo_dto_trmte' => $dtoId,
                'cdgo_estdo_trmte' => 1, // Pendiente
                'fe_rcbdo_trmte' => now(),
                'in_esta' => '1'
            ]);

            $pendienteId = $dtoId;
        } else {
            $pendienteId = $pendiente->cdgo_dto_trmte;
        }

        $response = $this->actingAs($this->user)
            ->withSession(['sigtram_cargo_id' => $cargoId])
            ->postJson(route('mod_gm.segi_expedi.recepcionar'), [
                'ids' => [$pendienteId],
                'observaciones' => 'TEST RECEPCION'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Los expedientes fueron recepcionados correctamente.'
        ]);

        // Verificar cambio en la base de datos
        $this->assertDatabaseHas($this->schema . 'r_trmtes_dcmntos', [
            'cdgo_dto_trmte' => $pendienteId,
            'cdgo_crgo_en' => $cargoId,
            'cdgo_crgo_sa' => $cargoId,
            'cdgo_estdo_trmte' => 2, // Recibido
            'in_esta' => '1'
        ]);
    }
}
