<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\DocumentoInternoAsociadoDto;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Servicio especializado en generar y vincular un documento interno real (p_dtos_ntrnos / r_trmtes_ntrnos)
 * cuando una acción de Trámite Externo se ejecuta con la opción "Enviar con trámite interno asociado".
 *
 * Replica fielmente el comportamiento de negocio de los archivos Legacy:
 * - Presentacion/mod_gm/mod_tram_exte/segi_reenviar.php
 * - Presentacion/mod_gm/mod_tram_exte/segi_asignar.php
 * - Presentacion/mod_gm/mod_tram_exte/segi_finaliza.php
 */
class DocumentoInternoDesdeExpedienteExternoService
{
    public function __construct(
        private readonly TramiteInternoRepositoryInterface $tramiteInternoRepo,
        private readonly ?GestorAdjuntosInterface $gestorAdjuntos = null
    ) {}

    /**
     * Genera el documento interno de forma atómica dentro de la transacción activa.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto Contexto del funcionario autenticado
     * @param int $expedienteId Identificador del expediente externo (p_dto_trmtes.cdgo_dto_trmte)
     * @param string $numeroExpediente Número formateado del expediente externo
     * @param int $docTipo Identificador del tipo documental interno (a_tpo_trmtes.cdgo_tpo_trmte)
     * @param string $fecha Fecha del movimiento en formato Y-m-d H:i:s
     * @param string|null $observaciones Observación adicional
     * @param int|null $folios Número de folios del anexo
     * @param UploadedFile|null $adjuntoPdf Archivo PDF del documento interno
     * @return DocumentoInternoAsociadoDto
     */
    public function generarDocumentoInterno(
        ContextoUsuarioAutenticadoDto $contexto,
        int $expedienteId,
        string $numeroExpediente,
        int $docTipo,
        string $fecha,
        ?string $observaciones = null,
        ?int $folios = 1,
        ?UploadedFile $adjuntoPdf = null
    ): DocumentoInternoAsociadoDto {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de autenticación inválido: identificador de login numérico no disponible.');
        }

        if ($contexto->idCompania <= 0 || $contexto->idCargo <= 0 || $contexto->idArea <= 0) {
            throw new InvalidArgumentException('Contexto institucional incompleto: compañía, cargo y área son obligatorios.');
        }

        if ($docTipo <= 0) {
            throw new InvalidArgumentException('Seleccione un tipo de documento interno válido.');
        }

        // 1. Obtener y validar el tipo de documento en el catálogo interno
        $tpoDoc = $this->tramiteInternoRepo->obtenerTipoDocumentoInterno($docTipo);
        if (!$tpoDoc) {
            throw new InvalidArgumentException('El tipo de documento seleccionado no existe o está inactivo.');
        }

        $inScncal = ((int) ($tpoDoc->in_scncal ?? 0)) === 1;

        // 2. Resolver año municipal activo (estricto, sin fallback 1)
        $currentYear = (int) date('Y');
        $anioInfo = $this->tramiteInternoRepo->obtenerAnioPorNumero($currentYear);
        if (!$anioInfo) {
            $anioInfo = $this->tramiteInternoRepo->obtenerUltimoAnio();
        }
        if (!$anioInfo || (int) ($anioInfo->cdgo_anio ?? 0) <= 0) {
            throw new RuntimeException('Año municipal no configurado en el sistema.');
        }
        $cdgoAnio = (int) $anioInfo->cdgo_anio;
        $deAnio = (string) $anioInfo->de_anio;

        // 3. Bloqueo pesimista y reserva del correlativo en a_smlla_intrno (lockForUpdate)
        $nuSemilla = $this->tramiteInternoRepo->obtenerSemillaInternaParaBloqueo(
            $cdgoAnio,
            $docTipo,
            $contexto->idCargo,
            $contexto->idArea,
            $inScncal,
            true
        );

        if ($nuSemilla <= 0) {
            throw new RuntimeException('No se pudo reservar el número del documento interno.');
        }

        // 4. Guardar la semilla reservada
        $this->tramiteInternoRepo->crearSemillaInterna([
            'cdgo_tpo_trmte'  => $docTipo,
            'cdgo_anio'       => $cdgoAnio,
            'cdgo_area'       => $contexto->idArea,
            'cdgo_crgo'       => $inScncal ? $contexto->idCargo : null,
            'nu_smlla_intrno' => $nuSemilla,
            'co_usua_modi'    => $loginId,
            'ts_usua_modi'    => now(),
            'in_esta'         => 1,
        ]);

        // 5. Formatear la numeración canónica
        $numAuto = "{$nuSemilla}-{$deAnio}";
        $numTramTodo = str_pad((string) $nuSemilla, 6, '0', STR_PAD_LEFT) . "-{$deAnio}";
        $nombreTipoDoc = trim((string) ($tpoDoc->de_tpo_trmte ?? 'DOCUMENTO'));
        $tituloDoc = "{$nombreTipoDoc} N° {$numAuto}";

        // 6. Texto de referencia estricto Legacy
        $obsRef = "CON REFERENCIA AL EXPEDIENTE N° {$numeroExpediente}";
        if (!empty($observaciones)) {
            $obsRef .= " - " . trim($observaciones);
        }

        // 7. Procesar archivo si fue provisto
        $rutaAdjunto = null;
        if ($adjuntoPdf !== null && $this->gestorAdjuntos !== null) {
            $dtoAdjunto = $this->gestorAdjuntos->guardar($adjuntoPdf, 'tramites/internos');
            $rutaAdjunto = $dtoAdjunto->rutaRelativa;
        }

        // 8. Insertar en p_dtos_ntrnos relacionándolo con cdgo_dto_trmte y cdgo_cmpnia
        $documentoInternoId = $this->tramiteInternoRepo->crearDocumentoInterno([
            'cdgo_tpo_trmte'  => $docTipo,
            'cdgo_anio'       => $cdgoAnio,
            'cdgo_area'       => $contexto->idArea,
            'cdgo_lgin'       => $loginId,
            'nu_tram_todo'    => $numTramTodo,
            'nu_tram'         => $nuSemilla,
            'de_obser'        => $obsRef,
            'de_urls_adjnto'  => $rutaAdjunto,
            'fe_ingrso'       => $fecha,
            'in_esta'         => '1',
            'cdgo_cmpnia'     => $contexto->idCompania,
            'cdgo_dto_trmte'  => $expedienteId,
            'in_envo_fsco'    => 0,
            'nu_tram_cmplto'  => $tituloDoc,
            'in_prrdad_trmte' => 1,
        ]);

        // 9. Insertar movimiento inicial en r_trmtes_ntrnos
        $movimientoInternoId = $this->tramiteInternoRepo->crearMovimientoInterno([
            'cdgo_dtos_ntrnos' => $documentoInternoId,
            'cdgo_estdo_trmte' => 1, // PENDIENTE inicial
            'cdgo_lgin'        => $loginId,
            'cdgo_crgo_sa'     => $contexto->idCargo,
            'cdgo_crgo_en'     => $contexto->idCargo,
            'de_urls_agrdo'    => $rutaAdjunto,
            'fe_rcbdo'         => $fecha,
            'de_obser_ntrnos'  => $obsRef,
            'in_copia'         => '1',
            'in_esta'          => '1',
        ]);

        return new DocumentoInternoAsociadoDto(
            documentoInternoId: $documentoInternoId,
            movimientoInternoId: $movimientoInternoId,
            numeroFormateado: $tituloDoc,
            tipoDocumentalId: $docTipo,
            tipoDocumentalNombre: $nombreTipoDoc,
            rutaAdjunto: $rutaAdjunto
        );
    }
}
