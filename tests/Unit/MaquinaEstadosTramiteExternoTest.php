<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Services\mod_gm\mod_tram_exte\MaquinaEstadosTramiteExterno;
use InvalidArgumentException;
use Tests\TestCase;

class MaquinaEstadosTramiteExternoTest extends TestCase
{
    private MaquinaEstadosTramiteExterno $maquina;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maquina = new MaquinaEstadosTramiteExterno();
    }

    public function test_transicion_valida_de_pendiente_a_recibido(): void
    {
        $this->assertTrue($this->maquina->esTransicionValida(EstadoTramiteExterno::PENDIENTE, 'recepcionar'));
        $destino = $this->maquina->resolverEstadoDestino(EstadoTramiteExterno::PENDIENTE, 'recepcionar');
        $this->assertSame(EstadoTramiteExterno::RECIBIDO, $destino);
    }

    public function test_transicion_valida_de_recibido_a_derivar(): void
    {
        $this->assertTrue($this->maquina->esTransicionValida(EstadoTramiteExterno::RECIBIDO, 'derivar'));
        $destino = $this->maquina->resolverEstadoDestino(EstadoTramiteExterno::RECIBIDO, 'derivar');
        $this->assertSame(EstadoTramiteExterno::PENDIENTE, $destino);
    }

    public function test_transicion_invalida_lanza_excepcion(): void
    {
        $this->assertFalse($this->maquina->esTransicionValida(EstadoTramiteExterno::PENDIENTE, 'finalizar'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transición inválida');
        $this->maquina->resolverEstadoDestino(EstadoTramiteExterno::PENDIENTE, 'finalizar');
    }

    public function test_resolucion_unificada_de_estados_finalizados_3_y_26(): void
    {
        $this->assertTrue(MaquinaEstadosTramiteExterno::esEstadoFinalizado(3));
        $this->assertTrue(MaquinaEstadosTramiteExterno::esEstadoFinalizado(26));
        $this->assertFalse(MaquinaEstadosTramiteExterno::esEstadoFinalizado(1));
        $this->assertFalse(MaquinaEstadosTramiteExterno::esEstadoFinalizado(2));
    }

    public function test_admisibilidad_en_bandeja_de_entrada(): void
    {
        $this->assertTrue(MaquinaEstadosTramiteExterno::admisibleEnEntrada(EstadoTramiteExterno::PENDIENTE->value));
        $this->assertTrue(MaquinaEstadosTramiteExterno::admisibleEnEntrada(EstadoTramiteExterno::REINGRESO->value));
        $this->assertFalse(MaquinaEstadosTramiteExterno::admisibleEnEntrada(EstadoTramiteExterno::EXCLUIDO_5->value));
        $this->assertFalse(MaquinaEstadosTramiteExterno::admisibleEnEntrada(EstadoTramiteExterno::FINALIZADO->value));
    }
}
