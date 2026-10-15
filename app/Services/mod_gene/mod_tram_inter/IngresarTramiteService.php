<?php
declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\IngresarTramiteRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Support\SessionKeys;

class IngresarTramiteService
{
    private IngresarTramiteRepositoryInterface $repository;

    public function __construct(IngresarTramiteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Registra el trámite interno de forma transaccional garantizando aislamiento por compañía.
     */
    public function registrarTramite(array $datos, array $archivos = [], int $idReferencia = 0, int $companiaId = 0): array
    {
        if ($companiaId <= 0) {
            throw new AuthorizationException('Sesión sin compañía activa válida o usuario no autorizado.');
        }

        return DB::transaction(function () use ($datos, $archivos, $idReferencia, $companiaId) {
            $cdgoLgin = (int) Auth::id();
            $cdgoCrgoSa = (int) session(SessionKeys::CARGO_ID, 0);

            // Validar emisor: Cargo y Área deben pertenecer a la compañía activa y estar activos
            $cargoEmisor = $this->repository->validarCargoYAreaEmisor($cdgoCrgoSa, $companiaId);
            if (!$cargoEmisor) {
                throw new AuthorizationException('El cargo o área del usuario emisor no pertenece a la compañía activa o se encuentra inactivo.');
            }
            $cdgoAreaSa = (int) $cargoEmisor->cdgo_area;

            // Validar áreas de destino: todas deben pertenecer a la compañía activa y estar activas
            $arrSelAreas = $datos['sel_areas'] ?? [];
            if (!$this->repository->validarAreasDestinoCompania($arrSelAreas, $companiaId)) {
                throw ValidationException::withMessages([
                    'sel_areas' => 'Una o más áreas de destino no pertenecen a la compañía activa o se encuentran inactivas.'
                ]);
            }

            // Validar cargo específico si fue seleccionado
            if (!empty($datos['sel_crgos'])) {
                $cargoEsp = $this->repository->obtenerCargoEspecifico((int) $datos['sel_crgos'], $companiaId);
                if (!$cargoEsp || (int) $cargoEsp->cdgo_area !== (int) $arrSelAreas[0]) {
                    throw ValidationException::withMessages([
                        'sel_crgos' => 'El cargo de destino seleccionado no pertenece al área destino o a la compañía activa.'
                    ]);
                }
            }

            $lginInfo = $this->repository->obtenerUsuarioLogin($cdgoLgin);
            $noCrto = $lginInfo ? (string) $lginInfo->no_crto : '';

            // 1. Validaciones extra o lógicas (fechas, etc)
            $txtFeRcbdo = $datos['txt_fe_rcbdo'];
            if (empty($txtFeRcbdo)) {
                $txtFeRcbdo = date("Y-m-d H:i:s");
            } else {
                $txtFeRcbdo = str_replace('T', ' ', $txtFeRcbdo);
                if (strlen($txtFeRcbdo) === 16) {
                    $txtFeRcbdo .= ':00';
                }
            }
            $datos['txt_fe_rcbdo'] = $txtFeRcbdo;

            // 2. Semilla: usa el N° del formulario (editable como en el antiguo)
            $rawSemilla = trim((string) ($datos['txt_nu_smlla_intrno'] ?? ''));
            $partesSemilla = explode('-', preg_replace('/\s+/', '', $rawSemilla));
            $nuSmllaForm = (int) ltrim((string) $partesSemilla[0], '0');
            if ($nuSmllaForm === 0 && $partesSemilla[0] !== '' && $partesSemilla[0] !== '0') {
                $nuSmllaForm = (int) $partesSemilla[0];
            }
            $anioForm = (int) ($partesSemilla[1] ?? date('Y'));
            if ($anioForm < 2000) {
                $anioForm = (int) date('Y');
            }

            $objAnio = $this->repository->obtenerAnioPorValor($anioForm);
            $cdgoAnio = $objAnio ? $objAnio->cdgo_anio : ($this->repository->obtenerAnioReciente()->cdgo_anio ?? 0);

            $infoTramite = $this->repository->obtenerInfoTipoTramite((int)$datos['sel_cdgo_tpo_trmte']);
            if (!$infoTramite) {
                throw ValidationException::withMessages([
                    'sel_cdgo_tpo_trmte' => 'El tipo de documento seleccionado no es válido.'
                ]);
            }

            // Institutional documents have no personal suffix; personal numbering uses initials,
            // matching the Word draft instead of concatenating the sender's full name.
            $sufijoPersonal = '';
            if ((int) $infoTramite->in_scncal === 1) {
                $partesNombre = preg_split('/\s+/u', trim($noCrto)) ?: [];
                foreach ($partesNombre as $parte) {
                    if ($parte !== '') {
                        $sufijoPersonal .= mb_strtolower(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
                    }
                }
                if ($sufijoPersonal !== '') {
                    $sufijoPersonal = '-'.$sufijoPersonal;
                }
            }

            if ($nuSmllaForm <= 0) {
                throw new \RuntimeException('Debe especificar un N° de Documento válido (ej. 10-2026).');
            }

            $semillaGenerada = $this->prepararSemilla(
                (int)$datos['sel_cdgo_tpo_trmte'],
                (int)$cdgoAnio,
                (int)$cdgoAreaSa,
                (int)$cdgoCrgoSa,
                (int)$infoTramite->in_scncal,
                $cdgoLgin,
                $nuSmllaForm
            );

            // 3. Generar el Título Final (con el número elegido en el formulario)
            $tituloDocumento = $this->generarTituloTramiteInterno(
                $infoTramite->de_tpo_trmte,
                $semillaGenerada['nu_smlla_intrno'],
                (string) $anioForm,
                $sufijoPersonal,
                $cdgoAreaSa
            );
            $numeroTramite = $semillaGenerada['nu_smlla_intrno'] . '-' . $anioForm;

            // 4. Actualizar Referencia Histórica (Si responde a otro documento)
            if ($idReferencia > 0) {
                $this->repository->actualizarHistoricoReferencia($idReferencia, $txtFeRcbdo, '');
            }

            $observacionMayus = mb_strtoupper($datos['txt_de_obser'] ?? '', 'UTF-8');

            // 5. Insertar p_dtos_ntrnos con la compañía activa obligatoria
            $datosInsertar = [
                'cdgo_tpo_trmte'  => (int) $datos['sel_cdgo_tpo_trmte'],
                // Contrato Legacy: número-año, número correlativo y título completo
                // se almacenan en columnas distintas.
                'nu_tram_todo'    => $numeroTramite,
                'nu_tram'         => (int) $semillaGenerada['nu_smlla_intrno'],
                'nu_tram_cmplto'  => $tituloDocumento,
                'fe_ingrso'       => $txtFeRcbdo,
                'de_obser'        => $observacionMayus,
                'in_prrdad_trmte' => $datos['rdn_in_prrdad_trmte'],
                'cdgo_anio'       => (int) $cdgoAnio,
                'cdgo_lgin'       => $cdgoLgin,
                'cdgo_area'       => $cdgoAreaSa,
                'cdgo_cmpnia'     => $companiaId,
                'in_esta'         => 1,
            ];

            $idTramiteNtrno = $this->repository->registrarNuevoTramite($datosInsertar);

            // 6. Guardar Archivos Adjuntos (SEC-11 y SEC-14)
            $adjuntosRutas = [];
            $fechaRuta = date('Y/m/d');

            $archivosValidos = [];
            for ($i = 1; $i <= 4; $i++) {
                $key = "fle_adjunto{$i}";
                if (isset($archivos[$key]) && $archivos[$key]->isValid()) {
                    $archivosValidos[$i] = $archivos[$key];
                }
            }

            if (count($archivosValidos) === 1 && isset($archivosValidos[1])) {
                $file = $archivosValidos[1];
                $ext = $file->getClientOriginalExtension();
                $nuevoNombre = "pri{$idTramiteNtrno}.{$ext}";

                $path = $file->storeAs("documentos/internos/{$fechaRuta}", $nuevoNombre, 'local');
                $adjuntosRutas[] = $path;

            } elseif (count($archivosValidos) > 1) {
                $nuevoNombre = "adjun{$idTramiteNtrno}.zip";
                $tmpZipPath = sys_get_temp_dir() . '/' . $nuevoNombre;
                $zip = new \ZipArchive();

                if ($zip->open($tmpZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                    $zip->addEmptyDir('anexos');

                    foreach ($archivosValidos as $i => $file) {
                        $nombreOriginal = preg_replace('/[^a-zA-Z0-9_\-\. \[\]]/', '_', $file->getClientOriginalName());

                        if ($i === 1) {
                            $zip->addFile($file->getRealPath(), $nombreOriginal);
                        } else {
                            $zip->addFile($file->getRealPath(), "anexos/" . $nombreOriginal);
                        }
                    }
                    $zip->close();

                    $path = "documentos/internos/{$fechaRuta}/{$nuevoNombre}";
                    Storage::disk('local')->put($path, file_get_contents($tmpZipPath));
                    @unlink($tmpZipPath);

                    $adjuntosRutas[] = $path;
                }
            }

            $rutaFinalString = empty($adjuntosRutas) ? '' : implode(',', $adjuntosRutas);
            $this->repository->actualizarRutaAdjunto($idTramiteNtrno, $rutaFinalString);

            if ($idReferencia > 0) {
                $this->repository->actualizarHistoricoReferencia($idReferencia, $txtFeRcbdo, $rutaFinalString);
            }

            // 7. Generar Histórico para áreas de destino
            $cargosDestinoPreload = $this->repository->obtenerCargosDestinoPorAreas($arrSelAreas);

            $datosHistoricoComun = [
                'cdgo_lgin'       => $cdgoLgin,
                'cdgo_crgo_sa'    => $cdgoCrgoSa,
                'de_urls_agrdo'   => $rutaFinalString,
                'fe_rcbdo'        => $txtFeRcbdo,
                'de_obser_ntrnos' => $observacionMayus,
                'sel_crgos'       => $datos['sel_crgos'] ?? null
            ];

            $this->repository->registrarHistoricoDestinos($idTramiteNtrno, $arrSelAreas, $cargosDestinoPreload, $datosHistoricoComun);

            return [
                'success' => true,
                'message' => 'Trámite Interno guardado correctamente',
                'titulo'  => $tituloDocumento,
                'id'      => $idTramiteNtrno
            ];
        });
    }

    /**
     * Reserva la semilla del trámite.
     */
    public function prepararSemilla(int $cdgoTpoTrmte, int $cdgoAnio, int $areaId, int $cargoId, int $inScncal, int $cdgoLgin, int $nuForzado = 0): array
    {
        $nuevaSemilla = $nuForzado > 0
            ? $nuForzado
            : $this->repository->obtenerNuevaSemilla($cdgoTpoTrmte, $cdgoAnio, $areaId, $cargoId, $inScncal);

        $datosInsertarSemilla = [
            'cdgo_tpo_trmte'  => $cdgoTpoTrmte,
            'cdgo_anio'       => $cdgoAnio,
            'cdgo_crgo'       => $cargoId,
            'cdgo_area'       => $areaId,
            'nu_smlla_intrno' => $nuevaSemilla,
        ];

        $this->repository->guardarSemilla($datosInsertarSemilla);

        return $datosInsertarSemilla;
    }

    /**
     * Genera el arreglo de datos para el borrador (Word/PDF) validando la compañía activa.
     */
    public function prepararDatosBorrador(array $datosReq, int $companiaId = 0): array
    {
        if ($companiaId <= 0) {
            throw new AuthorizationException('Sesión sin compañía activa válida o usuario no autorizado.');
        }

        $cdgoCrgoSa = (int) session(SessionKeys::CARGO_ID, 0);
        $cdgoLgin = (int) Auth::id();

        $cargoEmisor = $this->repository->validarCargoYAreaEmisor($cdgoCrgoSa, $companiaId);
        if (!$cargoEmisor) {
            throw new AuthorizationException('El cargo o área del usuario emisor no pertenece a la compañía activa o se encuentra inactivo.');
        }
        $cdgoAreaSa = (int) $cargoEmisor->cdgo_area;

        $infoTramite = $this->repository->obtenerInfoTipoTramite((int)$datosReq['sel_cdgo_tpo_trmte']);
        if (!$infoTramite) {
            throw ValidationException::withMessages([
                'sel_cdgo_tpo_trmte' => 'Tipo de documento no válido.'
            ]);
        }

        $remitenteInfo = $this->repository->obtenerRemitenteDocumento(
            $cdgoCrgoSa,
            $cdgoLgin,
            $cdgoAreaSa,
            (int) $infoTramite->in_scncal,
            $companiaId
        );

        $arrSelAreas = $datosReq['sel_areas'] ?? [];
        if (!$this->repository->validarAreasDestinoCompania($arrSelAreas, $companiaId)) {
            throw ValidationException::withMessages([
                'sel_areas' => 'Una o más áreas de destino no pertenecen a la compañía activa o se encuentran inactivas.'
            ]);
        }

        $cargoDestinoFinal = 'NO DEFINIDO';
        $nombreDestinoFinal = '';
        $ccString = '';
        $destinatariosCopia = [];

        if ($infoTramite->in_cc == 0 && count($arrSelAreas) > 1) {
            $areasDestinoInfo = $this->repository->obtenerMultiplesDestinatarios($arrSelAreas);
            $cargosMultiples = [];
            foreach ($areasDestinoInfo as $dest) {
                $cargosMultiples[] = mb_strtoupper($dest->de_crgo, 'UTF-8');
            }
            $nombreDestinoFinal = implode(' | ', $cargosMultiples);
            $cargoDestinoFinal = '';
        } else {
            $cargoPrincipalInfo = $this->repository->obtenerDestinatarioPrincipal((int)$arrSelAreas[0]);
            if ($cargoPrincipalInfo) {
                $cargoDestinoFinal = $cargoPrincipalInfo->de_crgo;
                $nombreDestinoFinal = ucwords(mb_strtolower($cargoPrincipalInfo->no_crto, 'UTF-8'));
            }

            if (!empty($datosReq['sel_crgos'])) {
                $cargoEspecifico = $this->repository->obtenerCargoEspecifico((int)$datosReq['sel_crgos'], $companiaId);
                if ($cargoEspecifico) {
                    $nombreDestinoFinal = ucwords(mb_strtolower($cargoEspecifico->no_crto, 'UTF-8'));
                    $cargoDestinoFinal = $cargoEspecifico->de_crgo;
                }
            }

            if (count($arrSelAreas) > 1) {
                $areasCopiaIds = array_slice($arrSelAreas, 1);
                $areasCopiaData = $this->repository->obtenerDatosAreas($areasCopiaIds, $companiaId);

                $ccAreasLimpias = [];
                foreach ($areasCopiaIds as $copiaId) {
                    $itemArea = $areasCopiaData->get($copiaId);
                    $destInfo = $this->repository->obtenerDestinatarioPrincipal((int) $copiaId);

                    $nombreFuncionario = '';
                    $cargoArea = '';
                    if ($destInfo && !empty($destInfo->no_crto)) {
                        $nombreFuncionario = ucwords(mb_strtolower($destInfo->no_crto, 'UTF-8'));
                        $cargoArea = mb_strtoupper($destInfo->de_crgo ?? ($itemArea->de_area ?? ''), 'UTF-8');
                    } else {
                        $cargoArea = mb_strtoupper($itemArea->de_area ?? '', 'UTF-8');
                    }

                    $destinatariosCopia[] = [
                        'nombre' => $nombreFuncionario,
                        'cargo'  => $cargoArea,
                    ];

                    if ($itemArea) {
                        $limpio = trim(str_replace('.', '', (string) $itemArea->dc_area));
                        if ($limpio !== '') {
                            $ccAreasLimpias[] = $limpio;
                        }
                    }
                }

                if (!empty($ccAreasLimpias)) {
                    $ccString = 'CC: ' . implode(', ', $ccAreasLimpias);
                }
            }
        }

        $cdgoAnio = $this->repository->obtenerAnioPorValor((int)date('Y'));
        $anio = $cdgoAnio ? $cdgoAnio->de_anio : date('Y');

        $nuSmllaTokens = explode('-', $datosReq['txt_nu_smlla_intrno']);
        $smllaValue = (int) $nuSmllaTokens[0];
        $anioValue = $nuSmllaTokens[1] ?? $anio;

        $noCrto = '';
        if ($infoTramite->in_scncal == 1) {
            $inicialesTitulo = '';
            if ($remitenteInfo && $remitenteInfo->no_crto) {
                $partesNombre = explode(' ', $remitenteInfo->no_crto);
                foreach ($partesNombre as $parte) {
                    if (!empty($parte)) {
                        $inicialesTitulo .= mb_strtolower(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
                    }
                }
                if ($inicialesTitulo) {
                    $noCrto = '-' . $inicialesTitulo;
                }
            }
        }

        $tituloDocumento = $this->generarTituloTramiteInterno(
            $infoTramite->de_tpo_trmte,
            $smllaValue,
            $anioValue,
            $noCrto,
            $cdgoAreaSa
        );

        $anioFscal = $this->repository->obtenerAnioFiscal($cdgoAnio ? (int)$cdgoAnio->cdgo_anio : 0);

        $nombreUsuario = $remitenteInfo ? ucwords(mb_strtolower($remitenteInfo->no_crto, 'UTF-8')) : '';
        $cargoUsuario = $remitenteInfo ? $remitenteInfo->de_crgo : '';
        $areaUsuario = $remitenteInfo ? $remitenteInfo->de_area : '';

        $iniciales = '';
        if ($nombreUsuario) {
            $partesNombre = explode(' ', $nombreUsuario);
            foreach ($partesNombre as $parte) {
                if (!empty($parte)) {
                    $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
                }
            }
        }

        $fechaTxt = $datosReq['txt_fe_rcbdo'] ?? date('Y-m-d');
        $fechaCarbon = \Carbon\Carbon::parse($fechaTxt)->locale('es');
        $fechaFormateada = mb_strtolower($fechaCarbon->translatedFormat('d \d\e F \d\e Y'), 'UTF-8');

        return [
            'cdgo_tpo_trmte'       => $datosReq['sel_cdgo_tpo_trmte'],
            'nu_tram_todo'         => $tituloDocumento,
            'NO_GERENCIA'          => $areaUsuario,
            'NO_ANIO'              => $anioFscal ?? '',
            'NU_TRAMITE'           => $tituloDocumento,
            'NO_USUARIO_DESTINO'   => $nombreDestinoFinal,
            'NO_CARGO_DESTINO'     => $cargoDestinoFinal,
            'NO_USUARIO'           => $nombreUsuario,
            'NO_CARGO'             => $cargoUsuario,
            'NO_ASUNTO'            => mb_strtoupper($datosReq['txt_de_obser'] ?? '', 'UTF-8'),
            'DE_REFERENCIA'        => 'Documento Inicial',
            'FE_TRAMITE'           => $fechaFormateada,
            'CC'                   => $ccString,
            'DESTINATARIOS_COPIA'  => $destinatariosCopia,
            'AREAS_COPIA_NOMBRES'  => array_column($destinatariosCopia, 'cargo'),
            'FIRMA'                => $cdgoCrgoSa . '.jpg',
            'INICIALES'            => $iniciales,
        ];
    }

    /**
     * Formatea el título del documento (Ej: INFORME Nº 00010-2026-SGTI)
     */
    public function generarTituloTramiteInterno(string $deTpoTrmte, int $nuSmllaIntrno, string $anio, string $noCrto, int $areaId = 0): string
    {
        $gerencia = '';
        $subgerencia = '';

        if ($areaId <= 0) {
            $cdgoCrgoSa = (int) session(SessionKeys::CARGO_ID, 0);
            $cargoInfo = $this->repository->obtenerCargoInfo($cdgoCrgoSa);
            $areaId = (int) ($cargoInfo->cdgo_area ?? 0);
        }

        if ($areaId > 0) {
            $jerarquia = $this->repository->obtenerJerarquiaArea($areaId);
            if ($jerarquia) {
                if ($jerarquia->dc_area_prim) {
                    $gerencia = '-' . str_replace('.', '', $jerarquia->dc_area_prim);
                }
                if ($jerarquia->dc_area) {
                    $subgerencia = '-' . str_replace('.', '', $jerarquia->dc_area);
                }
            }
        }

        return trim($deTpoTrmte) . " Nº " . str_pad((string)$nuSmllaIntrno, 4, "0", STR_PAD_LEFT) . "-" . $anio . "-MDPP" . $gerencia . $subgerencia . $noCrto;
    }
}
