<?php
declare(strict_types=1);

namespace App\Services\mod_gene;

use App\Contracts\Repositories\mod_gene\IngresarTramiteRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IngresarTramiteService
{
    private IngresarTramiteRepositoryInterface $repository;

    public function __construct(IngresarTramiteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Registra el trámite interno de forma transaccional
     */
    public function registrarTramite(array $datos, array $archivos = [], int $idReferencia = 0): array
    {
        return DB::transaction(function () use ($datos, $archivos, $idReferencia) {
            $cdgoLgin = (int) Auth::id();
            $cdgoCrgoSa = (int) session('sigtram_cargo_id', 0);
            
            $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
            
            $cargoInfo = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cdgoCrgoSa)->first();
            $cdgoAreaSa = (int) ($cargoInfo->cdgo_area ?? 0);

            $lginInfo = DB::table($schema . 'p_lgin as l')
                ->join($schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select('u.no_crto')
                ->where('l.cdgo_lgin', $cdgoLgin)
                ->first();
            $noCrto = $lginInfo ? $lginInfo->no_crto : '';

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
            $nuSmllaForm = (int) ltrim((string) ($partesSemilla[0] ?? '0'), '0');
            if ($nuSmllaForm === 0 && ($partesSemilla[0] ?? '') !== '' && ($partesSemilla[0] ?? '') !== '0') {
                $nuSmllaForm = (int) $partesSemilla[0];
            }
            $anioForm = (int) ($partesSemilla[1] ?? date('Y'));
            if ($anioForm < 2000) {
                $anioForm = (int) date('Y');
            }

            $objAnio = $this->repository->obtenerAnioPorValor($anioForm);
            $cdgoAnio = $objAnio ? $objAnio->cdgo_anio : ($this->repository->obtenerAnioReciente()->cdgo_anio ?? 0);

            $infoTramite = $this->repository->obtenerInfoTipoTramite((int)$datos['sel_cdgo_tpo_trmte']);

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
                $noCrto
            );

            // 4. Actualizar Referencia Histórica (Si responde a otro documento)
            if ($idReferencia > 0) {
                // RF-08 Parcial: Actualmente modifica directo.
                // En el futuro, aquí se validará si hay pasos posteriores para enviar a Staging.
                $this->repository->actualizarHistoricoReferencia($idReferencia, $txtFeRcbdo, ''); // La ruta de adjunto la actualizaremos luego
            }

            // 5. Determinar destinatarios (CC o múltiple)
            $arrSelAreas = $datos['sel_areas'];
            $ccAreas = [];
            
            // Si NO tiene copia, unimos todos en cargo
            if ($infoTramite->in_cc == 0 && count($arrSelAreas) > 1) {
                $areasDestinoInfo = $this->repository->obtenerMultiplesDestinatarios($arrSelAreas);
                $cargosMultiples = [];
                foreach ($areasDestinoInfo as $dest) {
                    $cargosMultiples[] = $dest->de_crgo;
                }
                $cargoDestinoFinal = implode(' | ', $cargosMultiples);
                // Cuando no hay CC, registramos el documento solo al primer área pero en el texto salen todas
            } else {
                // Si tiene copia, se envían al array normal
                $cargoPrincipalInfo = $this->repository->obtenerDestinatarioPrincipal((int)$arrSelAreas[0]);
                $cargoDestinoFinal = $cargoPrincipalInfo ? $cargoPrincipalInfo->de_crgo : 'NO DEFINIDO';

                if (count($arrSelAreas) > 1) {
                    $nombresAreas = $this->repository->obtenerNombresAreas(array_slice($arrSelAreas, 1));
                    $ccAreas = $nombresAreas;
                }
            }

            $observacionMayus = mb_strtoupper($datos['txt_de_obser'] ?? '', 'UTF-8');

            // 6. Insertar p_dtos_ntrnos
            $datosInsertar = [
                'cdgo_tpo_trmte'  => $datos['sel_cdgo_tpo_trmte'],
                'nu_tram_todo'    => $tituloDocumento,
                'fe_ingrso'       => $txtFeRcbdo,
                'de_obser'        => $observacionMayus,
                'in_prrdad_trmte' => $datos['rdn_in_prrdad_trmte'],
                'cdgo_anio'       => $cdgoAnio,
                'cdgo_lgin'       => $cdgoLgin,
                'cdgo_area'       => $cdgoAreaSa,
                'in_esta'         => 1,
            ];

            $idTramiteNtrno = $this->repository->registrarNuevoTramite($datosInsertar);

            // 7. Guardar Archivos Adjuntos (SEC-11 y SEC-14)
            // Se guardan de forma privada por YYYY/MM/DD
            $adjuntosRutas = [];
            $fechaRuta = date('Y/m/d');
            
            // Recopilar archivos válidos
            $archivosValidos = [];
            for ($i = 1; $i <= 4; $i++) {
                $key = "fle_adjunto{$i}";
                if (isset($archivos[$key]) && $archivos[$key]->isValid()) {
                    $archivosValidos[$i] = $archivos[$key];
                }
            }
            
            // Lógica exacta del Legacy según evidencia visual
            if (count($archivosValidos) === 1 && isset($archivosValidos[1])) {
                // Si SOLO hay principal, se guarda como pri[ID].[ext]
                $file = $archivosValidos[1];
                $ext = $file->getClientOriginalExtension();
                $nuevoNombre = "pri{$idTramiteNtrno}.{$ext}";
                
                $path = $file->storeAs("documentos/internos/{$fechaRuta}", $nuevoNombre, 'local');
                $adjuntosRutas[] = $path;
                
            } elseif (count($archivosValidos) > 1) {
                // Si hay anexos, se crea adjun[ID].zip
                $nuevoNombre = "adjun{$idTramiteNtrno}.zip";
                $tmpZipPath = sys_get_temp_dir() . '/' . $nuevoNombre;
                $zip = new \ZipArchive();
                
                if ($zip->open($tmpZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                    // Crear carpeta anexos en el zip
                    $zip->addEmptyDir('anexos');
                    
                    foreach ($archivosValidos as $i => $file) {
                        // Respetar el nombre original
                        $nombreOriginal = preg_replace('/[^a-zA-Z0-9_\-\. \[\]]/', '_', $file->getClientOriginalName());
                        
                        if ($i === 1) {
                            // Principal va en la raíz del ZIP
                            $zip->addFile($file->getRealPath(), $nombreOriginal);
                        } else {
                            // Anexos van dentro de la carpeta "anexos/"
                            $zip->addFile($file->getRealPath(), "anexos/" . $nombreOriginal);
                        }
                    }
                    $zip->close();
                    
                    $path = "documentos/internos/{$fechaRuta}/{$nuevoNombre}";
                    \Illuminate\Support\Facades\Storage::disk('local')->put($path, file_get_contents($tmpZipPath));
                    @unlink($tmpZipPath);
                    
                    $adjuntosRutas[] = $path;
                }
            }

            $rutaFinalString = empty($adjuntosRutas) ? '' : implode(',', $adjuntosRutas);
            $this->repository->actualizarRutaAdjunto($idTramiteNtrno, $rutaFinalString);

            // También actualizamos el histórico original si hubo adjunto de referencia
            if ($idReferencia > 0) {
                $this->repository->actualizarHistoricoReferencia($idReferencia, $txtFeRcbdo, $rutaFinalString);
            }

            // 8. Generar Histórico para áreas de destino
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
     * Si $nuForzado > 0 (número editable del formulario), se usa ese valor como en el antiguo.
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
     * Genera el arreglo de datos para el borrador (Word/PDF)
     */
    public function prepararDatosBorrador(array $datosReq): array
    {
        $cdgoCrgoSa = (int) session('sigtram_cargo_id', 0);
        $cdgoLgin = (int) Auth::id();

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        
        $cargoInfo = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cdgoCrgoSa)->first();
        $cdgoAreaSa = (int) ($cargoInfo->cdgo_area ?? 0);

        $infoTramite = $this->repository->obtenerInfoTipoTramite((int)$datosReq['sel_cdgo_tpo_trmte']);
        
        // Obtener la información del remitente correcto (Usuario actual o Jefe de Área)
        if ($infoTramite->in_scncal == 1) {
            // Documento Personal -> Usuario logueado
            $remitenteInfo = DB::table($schema . 'p_crgos as c')
                ->join($schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
                ->join($schema . 'r_crgos_lgin as r', 'c.cdgo_crgo', '=', 'r.cdgo_crgo')
                ->join($schema . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
                ->join($schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select('u.no_crto', 'c.de_crgo', 'a.de_area')
                ->where('c.cdgo_crgo', $cdgoCrgoSa)
                ->where('l.cdgo_lgin', $cdgoLgin)
                ->first();
        } else {
            // Documento de Área -> Jefe del Área
            $remitenteInfo = DB::table($schema . 'p_crgos as c')
                ->join($schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
                ->join($schema . 'r_crgos_lgin as r', 'c.cdgo_crgo', '=', 'r.cdgo_crgo')
                ->join($schema . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
                ->join($schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                ->select('u.no_crto', 'c.de_crgo', 'a.de_area')
                ->where('c.cdgo_area', $cdgoAreaSa)
                ->where('r.in_crgo_prmro', '1') // 1 indica que es el cargo principal/jefe
                ->first();
        }
        $arrSelAreas = $datosReq['sel_areas'];
        
        $cargoDestinoFinal = 'NO DEFINIDO';
        $nombreDestinoFinal = '';
        $ccString = '';

        if ($infoTramite->in_cc == 0 && count($arrSelAreas) > 1) {
            $areasDestinoInfo = $this->repository->obtenerMultiplesDestinatarios($arrSelAreas);
            $cargosMultiples = [];
            foreach ($areasDestinoInfo as $dest) {
                $cargosMultiples[] = mb_strtoupper($dest->de_crgo, 'UTF-8');
            }
            // En legacy, cuando son multiples destinos y no tiene CC, NO_USUARIO_DESTINO recibe los cargos y NO_CARGO_DESTINO se queda vacio
            $nombreDestinoFinal = implode(' | ', $cargosMultiples);
            $cargoDestinoFinal = '';
        } else {
            // Destino principal (primer área seleccionada)
            $cargoPrincipalInfo = $this->repository->obtenerDestinatarioPrincipal((int)$arrSelAreas[0]);
            if ($cargoPrincipalInfo) {
                $cargoDestinoFinal = $cargoPrincipalInfo->de_crgo;
                $nombreDestinoFinal = ucwords(mb_strtolower($cargoPrincipalInfo->no_crto, 'UTF-8'));
            }

            // Si se seleccionó un cargo específico desde el dropdown (aplica al destino principal)
            if (!empty($datosReq['sel_crgos'])) {
                $cargoEspecifico = DB::table($schema . 'p_crgos as c')
                    ->join($schema . 'r_crgos_lgin as r', 'c.cdgo_crgo', '=', 'r.cdgo_crgo')
                    ->join($schema . 'p_lgin as l', 'r.cdgo_lgin', '=', 'l.cdgo_lgin')
                    ->join($schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
                    ->select('u.no_crto', 'c.de_crgo')
                    ->where('c.cdgo_crgo', (int)$datosReq['sel_crgos'])
                    ->first();
                if ($cargoEspecifico) {
                    $nombreDestinoFinal = ucwords(mb_strtolower($cargoEspecifico->no_crto, 'UTF-8'));
                    $cargoDestinoFinal = $cargoEspecifico->de_crgo;
                }
            }

            // Áreas adicionales son Copia (CC)
            if (count($arrSelAreas) > 1) {
                $areasCopiaIds = array_slice($arrSelAreas, 1);
                $areasCopiaData = DB::table($schema . 'a_areas')
                    ->whereIn('cdgo_area', $areasCopiaIds)
                    ->select('cdgo_area', 'de_area', 'dc_area')
                    ->get()
                    ->keyBy('cdgo_area');

                $destinatariosCopia = [];
                $ccAreasLimpias = [];
                foreach ($areasCopiaIds as $copiaId) {
                    $itemArea = $areasCopiaData[$copiaId] ?? null;
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
                    // Como en el legacy gene_docu.php: CC: GAT, GGRD
                    $ccString = 'CC: ' . implode(', ', $ccAreasLimpias);
                }
            }
        }

        $cdgoAnio = $this->repository->obtenerAnioPorValor((int)date('Y'));
        $anio = $cdgoAnio ? $cdgoAnio->de_anio : date('Y');
        $noCrto = session('USER_NOCRTO', '');

        $nuSmllaTokens = explode('-', $datosReq['txt_nu_smlla_intrno']);
        $smllaValue = (int)($nuSmllaTokens[0] ?? 0);
        $anioValue = $nuSmllaTokens[1] ?? $anio;

        $noCrto = '';
        if ($infoTramite->in_scncal == 1) {
            // Obtener solo iniciales para el título, legacy usaba mos_inici
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
            $noCrto
        );

        $anioFscal = DB::table($schema . 'a_anios')
            ->where('cdgo_anio', $cdgoAnio ? $cdgoAnio->cdgo_anio : 0)
            ->value('no_anio_fscal');

        $nombreUsuario = $remitenteInfo ? ucwords(mb_strtolower($remitenteInfo->no_crto, 'UTF-8')) : '';
        $cargoUsuario = $remitenteInfo ? $remitenteInfo->de_crgo : '';
        $areaUsuario = $remitenteInfo ? $remitenteInfo->de_area : '';
        
        // Iniciales para el pie de página
        $iniciales = '';
        if ($nombreUsuario) {
            $partesNombre = explode(' ', $nombreUsuario);
            foreach ($partesNombre as $parte) {
                if (!empty($parte)) {
                    $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
                }
            }
        }

        // Formatear la fecha usando Carbon (Laravel)
        $fechaTxt = $datosReq['txt_fe_rcbdo'] ?? date('Y-m-d');
        $fechaCarbon = \Carbon\Carbon::parse($fechaTxt)->locale('es');
        $fechaFormateada = mb_strtolower($fechaCarbon->translatedFormat('d \d\e F \d\e Y'), 'UTF-8');

        return [
            'cdgo_tpo_trmte'       => $datosReq['sel_cdgo_tpo_trmte'],
            'nu_tram_todo'         => $tituloDocumento,
            
            // Variables para el Word (OpenTBS)
            'NO_GERENCIA'          => $areaUsuario,
            'NO_ANIO'              => $anioFscal ?? '',
            'NU_TRAMITE'           => $tituloDocumento,
            'NO_USUARIO_DESTINO'   => $nombreDestinoFinal,
            'NO_CARGO_DESTINO'     => $cargoDestinoFinal,
            'NO_USUARIO'           => $nombreUsuario,
            'NO_CARGO'             => $cargoUsuario,
            'NO_ASUNTO'            => mb_strtoupper($datosReq['txt_de_obser'] ?? '', 'UTF-8'),
            'DE_REFERENCIA'        => 'Documento Inicial', // Se puede actualizar luego si referencian a otro
            'FE_TRAMITE'           => $fechaFormateada,
            'CC'                   => $ccString,
            'DESTINATARIOS_COPIA'  => $destinatariosCopia ?? [],
            'AREAS_COPIA_NOMBRES'  => array_column($destinatariosCopia ?? [], 'cargo'),
            'FIRMA'                => $cdgoCrgoSa . '.jpg',
            'INICIALES'            => $iniciales,
        ];
    }

    /**
     * Formatea el título del documento (Ej: INFORME Nº 00010-2026-SGTI)
     */
    public function generarTituloTramiteInterno(string $deTpoTrmte, int $nuSmllaIntrno, string $anio, string $noCrto): string
    {
        $gerencia = '';
        $subgerencia = '';
        
        $cdgoCrgoSa = (int) session('sigtram_cargo_id', 0);
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $cargoInfo = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cdgoCrgoSa)->first();
        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);
        
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
