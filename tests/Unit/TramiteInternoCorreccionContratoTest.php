<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\DTOs\Support\ParticipanteUDto;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;

class TramiteInternoCorreccionContratoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_obtener_columnas_bandeja_contiene_alias_canonicos_sin_colisiones(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $repo = new TramiteInternoRepository($db);

        $columnas = $repo->obtenerColumnasBandeja('entrada');

        $this->assertNotEmpty($columnas);
        $this->assertContains('p.cdgo_dtos_ntrnos AS cdgo_dtos_ntrnos', $columnas);
        $this->assertContains('r.cdgo_trmtes_ntrnos AS cdgo_trmtes_ntrnos', $columnas);
        $this->assertContains('p.nu_tram_todo AS nu_tram_todo', $columnas);
        $this->assertContains('p.fe_ingrso AS fe_ingrso', $columnas);
        $this->assertContains('p.de_obser AS de_obser', $columnas);

        // Columnas de referencia DEBEN tener alias terminado en _ref
        $this->assertContains('p_ref.nu_tram_todo AS nu_tram_todo_ref', $columnas);
        $this->assertContains('p_ref.cdgo_dtos_ntrnos AS cdgo_dtos_ntrnos_ref', $columnas);
        $this->assertContains('r_ref.cdgo_trmtes_ntrnos AS cdgo_trmtes_ntrnos_ref', $columnas);
        $this->assertContains('a_ref.de_area AS de_area_ref', $columnas);

        // Verificar que todas las columnas de tablas de referencia tienen alias terminado en _ref
        foreach ($columnas as $col) {
            if (str_starts_with($col, 'p_ref.') || str_starts_with($col, 'r_ref.') || str_starts_with($col, 'a_ref.') || str_starts_with($col, 't_ref.')) {
                $this->assertStringContainsString(' AS ', $col, "La columna {$col} debe tener un alias explícito para no sobreescribir columnas primarias.");
                $this->assertStringEndsWith('_ref', trim($col), "El alias de {$col} debe terminar estrictamente en _ref.");
            }
        }
    }

    public function test_participante_u_dto_soporta_perfil_en_etiqueta_separada(): void
    {
        $dtoConPerfil = new ParticipanteUDto(
            login: '864',
            nombre: 'JUAN PEREZ',
            cargo: 'SUBGERENTE DE TI',
            area: 'GERENCIA DE TI',
            estadoAsignacion: 'Activo',
            perfil: 'PLATAFORMA ATENCION AL CIUDADANO'
        );

        $this->assertEquals('864', $dtoConPerfil->login);
        $this->assertEquals('JUAN PEREZ', $dtoConPerfil->nombre);
        $this->assertEquals('SUBGERENTE DE TI', $dtoConPerfil->cargo);
        $this->assertEquals('GERENCIA DE TI', $dtoConPerfil->area);
        $this->assertEquals('PLATAFORMA ATENCION AL CIUDADANO', $dtoConPerfil->perfil);

        $arr = $dtoConPerfil->toArray();
        $this->assertArrayHasKey('perfil', $arr);
        $this->assertEquals('PLATAFORMA ATENCION AL CIUDADANO', $arr['perfil']);

        $dtoSinPerfil = new ParticipanteUDto(
            login: '865',
            nombre: 'MARIA LOPEZ',
            cargo: 'ESPECIALISTA',
            area: 'GERENCIA GENERAL'
        );

        $this->assertNull($dtoSinPerfil->perfil);
    }

    public function test_listar_bandeja_retorna_ids_canonicos_en_data(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereRaw')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(1);
        $builder->shouldReceive('offset')->with(0)->andReturnSelf();
        $builder->shouldReceive('limit')->with(15)->andReturnSelf();

        $filaDb = (object) [
            'cdgo_dtos_ntrnos' => 5001,
            'cdgo_trmtes_ntrnos' => 7001,
            'cdgo_dtos_ntrnos_ref' => 4001,
            'cdgo_trmtes_ntrnos_ref' => 6001,
            'nu_tram_todo' => '23-2026',
            'fe_ingrso' => '2026-01-30 18:16:00',
            'de_obser' => 'SOLICITUD DE PRUEBA',
            'in_copia' => '0',
            'cdgo_estdo_trmte' => 1,
            'in_esta' => '1',
        ];

        $builder->shouldReceive('get')->andReturn(collect([$filaDb]));

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $resultado = $repo->listarBandeja('entrada', $contexto, [], 1, 15);

        $this->assertArrayHasKey('data', $resultado);
        $this->assertCount(1, $resultado['data']);

        $item = $resultado['data'][0];
        $this->assertEquals(5001, $item['documento_id']);
        $this->assertEquals(7001, $item['movimiento_id']);
        $this->assertEquals(4001, $item['documento_referencia_id']);
        $this->assertEquals(6001, $item['movimiento_referencia_id']);
    }
}
