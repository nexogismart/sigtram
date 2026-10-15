<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Policies\mod_gm\TramiteExternoPolicy;
use Tests\TestCase;

class TramiteExternoPolicyTest extends TestCase
{
    private TramiteExternoPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TramiteExternoPolicy();
    }

    public function test_recepcionar_autorizado_para_cargo_destino_y_estado_pendiente(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 105, 20);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->assertTrue($this->policy->recepcionar($contexto, $movimiento));
    }

    public function test_recepcionar_rechazado_por_idor_compania_distinta(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 105, 20);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-001', 50, 999, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->assertFalse($this->policy->recepcionar($contexto, $movimiento));
    }

    public function test_recepcionar_rechazado_para_cargo_no_destinatario(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 999, 20);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->assertFalse($this->policy->recepcionar($contexto, $movimiento));
    }

    public function test_derivar_autorizado_cuando_estado_es_recibido(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 105, 20);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->assertTrue($this->policy->derivar($contexto, $movimiento));
    }

    public function test_derivar_rechazado_si_expediente_esta_pendiente_sin_recepcionar(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 105, 20);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->assertFalse($this->policy->derivar($contexto, $movimiento));
    }

    public function test_devolver_solo_permitido_para_cargo_emisor_original(): void
    {
        $contextoEmisor = $this->crearContexto('jperez', 10, 2, 80, 15);
        $contextoAjeno = $this->crearContexto('mgarcia', 11, 2, 999, 15);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->assertTrue($this->policy->devolver($contextoEmisor, $movimiento));
        $this->assertFalse($this->policy->devolver($contextoAjeno, $movimiento));
    }

    public function test_retornar_prestamo_autorizado_solo_si_estado_es_prestado_y_misma_compania(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 80, 15);
        $movPrestado = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 80, 15, 15, EstadoTramiteExterno::PRESTADO->value);
        $movArchivado = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 80, 15, 15, EstadoTramiteExterno::ARCHIVADO->value);
        $movOtraCia = new ExpedienteMovimientoDto(1, '2026-001', 50, 999, 80, 80, 15, 15, EstadoTramiteExterno::PRESTADO->value);

        $this->assertTrue($this->policy->retornarPrestamo($contexto, $movPrestado));
        $this->assertFalse($this->policy->retornarPrestamo($contexto, $movArchivado));
        $this->assertFalse($this->policy->retornarPrestamo($contexto, $movOtraCia));
    }

    public function test_prestar_autorizado_solo_si_estado_es_archivado(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 80, 15);
        $movArchivado = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 80, 15, 15, EstadoTramiteExterno::ARCHIVADO->value);
        $movRecibido = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 80, 15, 15, EstadoTramiteExterno::RECIBIDO->value);

        $this->assertTrue($this->policy->prestar($contexto, $movArchivado));
        $this->assertFalse($this->policy->prestar($contexto, $movRecibido));
    }

    public function test_archivar_rechazado_para_expedientes_no_finalizados(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 2, 80, 15);
        $movFinalizado = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 80, 15, 15, EstadoTramiteExterno::FINALIZADO->value);
        $movPendiente = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 80, 15, 15, EstadoTramiteExterno::PENDIENTE->value);

        $this->assertTrue($this->policy->archivar($contexto, $movFinalizado));
        $this->assertFalse($this->policy->archivar($contexto, $movPendiente));
    }

    public function test_descargar_adjunto_autorizado_por_area_cargo_o_rol_admin(): void
    {
        // Funcionario del área destino
        $contextoArea = $this->crearContexto('jperez', 10, 2, 500, 20);
        $mov = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::RECIBIDO->value);
        $this->assertTrue($this->policy->descargarAdjunto($contextoArea, $mov));

        // Funcionario ajeno sin rol
        $contextoAjeno = $this->crearContexto('mgarcia', 11, 2, 999, 999, [5]);
        $this->assertFalse($this->policy->descargarAdjunto($contextoAjeno, $mov));

        // Funcionario ajeno pero con rol administrador (rol 1)
        $contextoAdmin = $this->crearContexto('admin', 1, 2, 999, 999, [1]);
        $this->assertTrue($this->policy->descargarAdjunto($contextoAdmin, $mov));
    }

    public function test_redirigir_rechazado_para_usuarios_sin_rol_administrativo(): void
    {
        $contextoComun = $this->crearContexto('jperez', 10, 2, 105, 20, [4]);
        $contextoAdmin = $this->crearContexto('admin', 1, 2, 105, 20, [1]);
        $mov = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->assertFalse($this->policy->redirigir($contextoComun, $mov));
        $this->assertTrue($this->policy->redirigir($contextoAdmin, $mov));
    }

    public function test_ver_matriz_autorizacion_y_negativa(): void
    {
        $mov = new ExpedienteMovimientoDto(1, '2026-001', 50, 2, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        // Otra compañía: debe rechazar
        $ctxOtraCia = $this->crearContexto('ext', 99, 999, 105, 20);
        $this->assertFalse($this->policy->ver($ctxOtraCia, $mov));

        // Misma compañía pero cargo y área totalmente ajenos sin rol admin: debe rechazar
        $ctxAjeno = $this->crearContexto('ajeno', 50, 2, 777, 888, [2]);
        $this->assertFalse($this->policy->ver($ctxAjeno, $mov));

        // Coincide cargo entrada: autorizado
        $ctxCargoEntrada = $this->crearContexto('dest', 51, 2, 105, 999, [2]);
        $this->assertTrue($this->policy->ver($ctxCargoEntrada, $mov));

        // Coincide cargo salida: autorizado
        $ctxCargoSalida = $this->crearContexto('orig', 52, 2, 80, 999, [2]);
        $this->assertTrue($this->policy->ver($ctxCargoSalida, $mov));

        // Coincide área entrada: autorizado
        $ctxAreaEntrada = $this->crearContexto('area_dest', 53, 2, 999, 20, [2]);
        $this->assertTrue($this->policy->ver($ctxAreaEntrada, $mov));

        // Coincide área salida: autorizado
        $ctxAreaSalida = $this->crearContexto('area_orig', 54, 2, 999, 15, [2]);
        $this->assertTrue($this->policy->ver($ctxAreaSalida, $mov));

        // Rol admin 1: autorizado
        $ctxAdmin1 = $this->crearContexto('admin1', 1, 2, 999, 999, [1]);
        $this->assertTrue($this->policy->ver($ctxAdmin1, $mov));

        // Rol admin 3: autorizado
        $ctxAdmin3 = $this->crearContexto('admin3', 3, 2, 999, 999, [3]);
        $this->assertTrue($this->policy->ver($ctxAdmin3, $mov));

        // Superpermiso 18: autorizado
        $ctxPerm18 = $this->crearContexto('perm18', 18, 2, 999, 999, [18]);
        $this->assertTrue($this->policy->ver($ctxPerm18, $mov));
    }

    /**
     * FASE 1: verHistorico autoriza movimientos históricos (in_esta = '0') mediante sus ramas activas
     * o relación directa, pero rechaza compañía distinta y usuarios sin autorización.
     */
    public function test_ver_historico_autoriza_inactivos_y_previene_idor(): void
    {
        $movHistorico = new ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 50,
            companiaId: 2,
            cargoSalidaId: 80,
            cargoEntradaId: 90,
            areaSalidaId: 15,
            areaEntradaId: 18,
            estadoId: 1,
            inEsta: '0' // Histórico inactivo
        );

        $ramaActivaAutorizada = [
            new ExpedienteMovimientoDto(
                expedienteId: 10,
                numeroExpediente: '2026-00010',
                movimientoId: 55,
                companiaId: 2,
                cargoSalidaId: 90,
                cargoEntradaId: 105,
                areaSalidaId: 18,
                areaEntradaId: 20,
                estadoId: 1,
                inEsta: '1' // Activo
            ),
        ];

        // 1. Usuario con cargo activo en la rama activa: autorizado
        $ctxActivo = $this->crearContexto('jperez', 10, 2, 105, 20);
        $this->assertTrue($this->policy->verHistorico($ctxActivo, $movHistorico, $ramaActivaAutorizada));

        // 2. Usuario con cargo participante del movimiento histórico: autorizado
        $ctxHistorico = $this->crearContexto('antiguo', 11, 2, 80, 15);
        $this->assertTrue($this->policy->verHistorico($ctxHistorico, $movHistorico, []));

        // 3. Usuario administrador (rol 1, 3 o 18): autorizado
        $ctxAdmin = $this->crearContexto('admin', 1, 2, 999, 999, [1]);
        $this->assertTrue($this->policy->verHistorico($ctxAdmin, $movHistorico, []));

        // 4. Usuario de otra compañía: RECHAZADO (IDOR)
        $ctxOtraCia = $this->crearContexto('ajeno', 50, 999, 105, 20);
        $this->assertFalse($this->policy->verHistorico($ctxOtraCia, $movHistorico, $ramaActivaAutorizada));

        // 5. Usuario sin relación con ramas activas ni con el movimiento histórico: RECHAZADO
        $ctxSinRelacion = $this->crearContexto('sin_acceso', 60, 2, 777, 888);
        $this->assertFalse($this->policy->verHistorico($ctxSinRelacion, $movHistorico, $ramaActivaAutorizada));

        // 6. ver() estricto sigue rechazando movimientos inactivos
        $this->assertFalse($this->policy->ver($ctxActivo, $movHistorico));

        // 7. Policies de mutación siguen exigiendo sus condiciones estrictas
        $this->assertFalse($this->policy->recepcionar($ctxActivo, $movHistorico));
        $this->assertFalse($this->policy->derivar($ctxActivo, $movHistorico));
    }

    public function test_historial_de_expediente_cerrado_se_autoriza_por_relacion_historica_sin_habilitar_mutaciones(): void
    {
        $movimientoCerrado = new ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 50,
            companiaId: 2,
            cargoSalidaId: 80,
            cargoEntradaId: 90,
            areaSalidaId: 15,
            areaEntradaId: 18,
            estadoId: EstadoTramiteExterno::FINALIZADO->value,
            inEsta: '0'
        );

        $contextoRelacionado = $this->crearContexto('historico', 11, 2, 80, 15, [2]);
        $contextoAjeno = $this->crearContexto('ajeno', 12, 2, 777, 888, [2]);
        $contextoOtraCompania = $this->crearContexto('otra', 13, 9, 80, 15, [1]);

        $this->assertTrue($this->policy->verHistorialExpediente($contextoRelacionado, [$movimientoCerrado]));
        $this->assertFalse($this->policy->verHistorialExpediente($contextoAjeno, [$movimientoCerrado]));
        $this->assertFalse($this->policy->verHistorialExpediente($contextoOtraCompania, [$movimientoCerrado]));
        $this->assertFalse($this->policy->recepcionar($contextoRelacionado, $movimientoCerrado));
    }
}
