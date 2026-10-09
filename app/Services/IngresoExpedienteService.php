<?php

namespace App\Services;

use App\Models\Anio;
use App\Models\Cargo;
use App\Models\CargoTramite;
use App\Models\DocumentoTramiteTupa;
use App\Models\DtoCargoTramite;
use App\Models\DtoRequisito;
use App\Models\DtoTramite;
use App\Models\PUsrrio;
use App\Models\RequisitoTramite;
use App\Models\SeguimientoLog;
use App\Models\TramiteDocumento;
use App\Models\UsrioJuridico;
use App\Models\UsrioNatural;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngresoExpedienteService
{
    /**
     * Registra un expediente completo de forma transaccional.
     */
    public function registrar(array $data, User $user, string $ip, $archivo = null): array
    {
        return DB::transaction(function () use ($data, $user, $ip, $archivo) {
            // 1. Resolver o Crear Solicitante
            $solicitante = $this->resolverSolicitante($data, $user);

            // 2. Generar Número de Expediente
            $numExp = $this->generarNumeroExpediente($data, $user);

            // 3. Obtener Cargo del Operador y Cargo de Destino
            $operatorCargoId = (int) session('sigtram_cargo_id', 0);
            if ($operatorCargoId === 0) {
                // Si no hay cargo en sesión, buscar el primer cargo activo del usuario
                $operatorCargoId = DB::table('sistema.r_crgos_lgin')
                    ->where('cdgo_lgin', $user->cdgo_lgin)
                    ->value('cdgo_crgo') ?? 0;
            }

            $destAreaId = null;
            $destCargoId = null;
            $estadoTrmte = 1; // 1 = Recepcionado / Pendiente
            $inSgmto = false;

            if (!empty($data['observado'])) {
                $areaObservadoId = (int) ($data['cdgo_area_observado'] ?? 0);
                if ($areaObservadoId <= 0) {
                    $areaObservadoId = (int) (Area::where('in_esta', '1')
                        ->where(function ($q) {
                            $q->whereRaw("UPPER(de_area) LIKE '%GESTION DOCUMENTARIA%'")
                                ->orWhereRaw("UPPER(de_area) LIKE '%GESTIÓN DOCUMENTARIA%'");
                        })
                        ->orderBy('cdgo_area', 'asc')
                        ->value('cdgo_area') ?? 0);
                }
                $destAreaId = $areaObservadoId ?: 18;
                $destCargoId = Cargo::where('cdgo_area', $destAreaId)
                    ->where('nu_orde', '1')
                    ->where('in_esta', '1')
                    ->orderBy('cdgo_crgo', 'asc')
                    ->value('cdgo_crgo') ?: $operatorCargoId;
                $estadoTrmte = 12;
            } else {
                if ($data['destino_modo'] === 'servicios' || ($data['destino_modo'] === 'seleccion' && !empty($data['tipo_tram_tupa']))) {
                    $tupaDoc = DocumentoTramiteTupa::findOrFail($data['tipo_tram_tupa']);
                    $destAreaId = $tupaDoc->cdgo_area;
                    $destCargoId = Cargo::where('cdgo_area', $destAreaId)
                        ->where('nu_orde', 1)
                        ->where('in_esta', '1')
                        ->orderBy('cdgo_crgo', 'asc')
                        ->value('cdgo_crgo');
                    $inSgmto = true;
                } elseif ($data['destino_modo'] === 'areas') {
                    $destAreaId = $data['subgerencia'] ?: $data['gerencia'];
                    $destCargoId = Cargo::where('cdgo_area', $destAreaId)
                        ->where('nu_orde', 1)
                        ->where('in_esta', '1')
                        ->orderBy('cdgo_crgo', 'asc')
                        ->value('cdgo_crgo');
                } else {
                    // Seleccion directa u otro - fallback a Mesa de partes (Area 18)
                    $destAreaId = 18;
                    $destCargoId = Cargo::where('cdgo_area', 18)
                        ->where('nu_orde', 1)
                        ->where('in_esta', '1')
                        ->orderBy('cdgo_crgo', 'asc')
                        ->value('cdgo_crgo') ?? $operatorCargoId;
                }
            }

            if (!$destCargoId) {
                throw new \Exception("No se encontró un cargo de destino activo (nu_orde = 1) para el área seleccionada (ID: {$destAreaId}).");
            }

            // 4. Crear Expediente (Cabecera)
            $inSileAdmi = '0';
            if ($data['destino_modo'] === 'servicios' || ($data['destino_modo'] === 'seleccion' && !empty($data['tipo_tram_tupa']))) {
                $inSileAdmi = DocumentoTramiteTupa::where('cdgo_docmnto_trmte', $data['tipo_tram_tupa'])
                    ->value('in_sile_admi') ?? '0';
            }

            $deObse = mb_strtoupper($this->sanitize($data['observaciones'] ?? ''), 'UTF-8');
            if ($inSgmto && !empty($data['tipo_tram_tupa'])) {
                $nombreTupa = DocumentoTramiteTupa::where('cdgo_docmnto_trmte', $data['tipo_tram_tupa'])->value('de_docmnto_trmte');
                if ($nombreTupa) {
                    $deObse = trim(mb_strtoupper($nombreTupa, 'UTF-8') . '; ' . $deObse);
                }
            }

            $expediente = DtoTramite::create([
                'cdgo_usrios' => $solicitante->cdgo_usrios,
                'cdgo_docmnto_trmte' => ($data['destino_modo'] === 'servicios' || $data['destino_modo'] === 'seleccion') ? ($data['tipo_tram_tupa'] ?? null) : null,
                'cdgo_tpo_trmte' => $numExp['cdgo_tpo_trmte'],
                'cdgo_anio' => $numExp['cdgo_anio'],
                'cdgo_area' => $destAreaId,
                'cdgo_mdlo' => 1,
                'cdgo_cmpnia' => $user->cdgo_cmpnia ?? 2,
                'cdgo_usrios_crreo' => null,
                'nu_expe_todo' => $numExp['nu_expe_todo'],
                'nu_expe_trmte' => $numExp['nu_expe_trmte'],
                'nu_docu_trmte' => '',
                'in_docu_trmte' => $numExp['in_docu_trmte'],
                'nu_foli_trmte' => $data['folios'],
                'fe_ingr_trmte' => $data['fecha_ingreso'],
                'fe_limi_trmte' => null,
                'de_obse_trmte' => $deObse,
                'in_sile_admi' => $inSileAdmi,
                'in_encu_tram' => '0',
                'de_obse_todo' => $deObse,
                'co_usua_modi' => $user->cdgo_lgin,
                'co_usua_ingr' => $user->cdgo_lgin,
                'ts_usua_modi' => now(),
            ]);

            $movimiento = TramiteDocumento::create([
                'cdgo_crgo_sa' => 0,
                'cdgo_crgo_en' => $destCargoId,
                'cdgo_dto_trmte' => $expediente->cdgo_dto_trmte,
                'cdgo_estdo_trmte' => $estadoTrmte,
                'fe_rcbdo_trmte' => $data['fecha_ingreso'],
                'de_obse' => $deObse,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $user->cdgo_lgin,
                'nu_foli_anex' => $data['folios'] ?? null,
                'fe_cta_vrtal' => null,
                'nu_anxo' => null,
            ]);

            if ($archivo && method_exists($archivo, 'isValid') && $archivo->isValid()) {
                $ext = strtolower((string) $archivo->getClientOriginalExtension());
                $filename = 'adjunto_' . $movimiento->cdgo_trmte_dcmnto . '_' . time() . '.' . $ext;
                $archivo->storeAs('adjuntos', $filename, 'public');
                $movimiento->update(['de_urls_imag' => $filename]);
            }

            // 6. Asignar Ruta TUPA (Si aplica)
            if ($inSgmto) {
                $rutas = CargoTramite::where('cdgo_docmnto_trmte', $data['tipo_tram_tupa'])
                    ->where('in_esta', '1')
                    ->orderBy('nu_orde', 'asc')
                    ->get();

                foreach ($rutas as $ruta) {
                    DtoCargoTramite::create([
                        'cdgo_dto_trmte' => $expediente->cdgo_dto_trmte,
                        'cdgo_crgo_trmte' => $ruta->cdgo_crgo_trmte,
                        'cdgo_crgo' => $ruta->cdgo_crgo,
                        'fe_dto_crgo_incio' => (int) $ruta->nu_orde === 1 ? $data['fecha_ingreso'] : null,
                        'nu_orde' => $ruta->nu_orde,
                        'in_esta' => (int) $ruta->nu_orde === 1 ? '1' : '0',
                    ]);
                }
            }

            // 7. Registrar Requisitos presentados
            if ($data['destino_modo'] === 'servicios' || ($data['destino_modo'] === 'seleccion' && !empty($data['tipo_tram_tupa']))) {
                $requisitosTupa = RequisitoTramite::where('cdgo_docmnto_trmte', $data['tipo_tram_tupa'])
                    ->where('in_esta', '!=', '0')
                    ->get();

                $marcados = $data['requisitos_marcados'] ?? [];

                foreach ($requisitosTupa as $req) {
                    $presentado = in_array((int) $req->cdgo_rqto_trmte, array_map('intval', $marcados)) ? '1' : '0';
                    DtoRequisito::create([
                        'cdgo_dto_trmte' => $expediente->cdgo_dto_trmte,
                        'cdgo_rqto_trmte' => $req->cdgo_rqto_trmte,
                        'in_pres' => $presentado,
                    ]);
                }
            }

            // 8. Registrar Log/Auditoría
            try {
                SeguimientoLog::create([
                    'cdgo_lgin' => $user->cdgo_lgin,
                    'de_sgto_log' => "Ingreso el expediente Nro {$numExp['nu_expe_todo']}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            } catch (\Exception $e) {
                Log::warning("No se pudo insertar el log legacy en p_sgmto_log: " . $e->getMessage());
            }

            return [
                'id' => $expediente->cdgo_dto_trmte,
                'numero' => $numExp['nu_expe_todo'],
            ];
        });
    }

    /**
     * Busca o crea al ciudadano / solicitante.
     */
    private function resolverSolicitante(array $data, User $user): PUsrrio
    {
        if ($data['tipo_persona'] === 'natural') {
            $nombres = $this->sanitize($data['nombres'] ?? '');
            $apePaterno = $this->sanitize($data['ape_paterno'] ?? '');
            $apeMaterno = $this->sanitize($data['ape_materno'] ?? '');
            $nombresComp = trim($nombres . ' ' . $apePaterno . ' ' . $apeMaterno);
            $direccion = $this->sanitize($data['direccion'] ?? '');
            $telefono = preg_replace('/[^0-9]/', '', $data['telefono'] ?? '');
            $correo = trim(strtolower($data['correo'] ?? ''));

            // Si es tipo_doc = 5 (Sin Documento), siempre creamos uno nuevo como en el PHP antiguo
            if ((int) $data['tipo_doc'] === 5) {
                $usr = PUsrrio::create([
                    'no_crto' => $nombresComp,
                    'no_crto_sstma' => $nombresComp,
                    'de_dire' => mb_strtoupper($direccion, 'UTF-8'),
                    'nu_tele' => $telefono,
                    'co_ubig' => '150101', // Ubigeo por defecto (Lima/Lima/Lima o Puente Piedra si aplica)
                    'de_mail' => $correo,
                    'nu_docu' => null,
                    'de_ocup_rubr' => '',
                    'in_tipo_pers' => '0',
                    'nu_ruc' => '',
                ]);

                UsrioNatural::create([
                    'cdgo_usrios' => $usr->cdgo_usrios,
                    'cdgo_estdo_cvil' => 1,
                    'cdgo_dcmntos_iden' => $data['tipo_doc'],
                    'ap_pate' => mb_strtoupper($apePaterno, 'UTF-8'),
                    'ap_mate' => mb_strtoupper($apeMaterno, 'UTF-8'),
                    'no_usrio' => mb_strtoupper($nombres, 'UTF-8'),
                    'nu_edad' => 0,
                    'in_sexo' => '1',
                    'co_usua_modi' => $user->cdgo_lgin,
                    'ts_usua_modi' => now(),
                ]);

                return $usr;
            }

            // Buscar si ya existe
            $usr = PUsrrio::where('nu_docu', $data['num_doc'])
                ->where('in_tipo_pers', '0')
                ->first();

            if ($usr) {
                // Actualizar
                $usr->update([
                    'no_crto' => $nombresComp,
                    'no_crto_sstma' => $nombresComp,
                    'de_dire' => mb_strtoupper($direccion, 'UTF-8'),
                    'nu_tele' => $telefono,
                    'de_mail' => $correo,
                ]);

                UsrioNatural::where('cdgo_usrios', $usr->cdgo_usrios)->update([
                    'cdgo_dcmntos_iden' => $data['tipo_doc'],
                    'ap_pate' => mb_strtoupper($apePaterno, 'UTF-8'),
                    'ap_mate' => mb_strtoupper($apeMaterno, 'UTF-8'),
                    'no_usrio' => mb_strtoupper($nombres, 'UTF-8'),
                    'co_usua_modi' => $user->cdgo_lgin,
                    'ts_usua_modi' => now(),
                ]);
            } else {
                // Crear nuevo
                $usr = PUsrrio::create([
                    'no_crto' => $nombresComp,
                    'no_crto_sstma' => $nombresComp,
                    'de_dire' => mb_strtoupper($direccion, 'UTF-8'),
                    'nu_tele' => $telefono,
                    'co_ubig' => '150101',
                    'de_mail' => $correo,
                    'nu_docu' => $data['num_doc'],
                    'de_ocup_rubr' => '',
                    'in_tipo_pers' => '0',
                    'nu_ruc' => '',
                ]);

                UsrioNatural::create([
                    'cdgo_usrios' => $usr->cdgo_usrios,
                    'cdgo_estdo_cvil' => 1,
                    'cdgo_dcmntos_iden' => $data['tipo_doc'],
                    'ap_pate' => mb_strtoupper($apePaterno, 'UTF-8'),
                    'ap_mate' => mb_strtoupper($apeMaterno, 'UTF-8'),
                    'no_usrio' => mb_strtoupper($nombres, 'UTF-8'),
                    'nu_edad' => 0,
                    'in_sexo' => '1',
                    'co_usua_modi' => $user->cdgo_lgin,
                    'ts_usua_modi' => now(),
                ]);
            }

            return $usr;
        } else {
            // Persona Jurídica
            $razonSocial = $this->sanitize($data['razon_social'] ?? '');
            $dirJur = $this->sanitize($data['dir_jur'] ?? '');
            $telJur = preg_replace('/[^0-9]/', '', $data['tel_jur'] ?? '');
            $corJur = trim(strtolower($data['cor_jur'] ?? ''));

            $usr = PUsrrio::where('nu_ruc', $data['ruc'])
                ->where('in_tipo_pers', '1')
                ->first();

            if ($usr) {
                // Actualizar
                $usr->update([
                    'no_crto' => $razonSocial,
                    'no_crto_sstma' => $razonSocial,
                    'de_dire' => mb_strtoupper($dirJur, 'UTF-8'),
                    'nu_tele' => $telJur,
                    'de_mail' => $corJur,
                ]);

                UsrioJuridico::where('cdgo_usrios', $usr->cdgo_usrios)->update([
                    'rz_soci' => mb_strtoupper($razonSocial, 'UTF-8'),
                    'co_usua_modi' => $user->cdgo_lgin,
                    'ts_usua_modi' => now(),
                ]);
            } else {
                // Crear nuevo
                $usr = PUsrrio::create([
                    'no_crto' => $razonSocial,
                    'no_crto_sstma' => $razonSocial,
                    'de_dire' => mb_strtoupper($dirJur, 'UTF-8'),
                    'nu_tele' => $telJur,
                    'co_ubig' => '150101',
                    'de_mail' => $corJur,
                    'nu_docu' => null,
                    'de_ocup_rubr' => '',
                    'in_tipo_pers' => '1',
                    'nu_ruc' => $data['ruc'],
                ]);

                UsrioJuridico::create([
                    'cdgo_usrios' => $usr->cdgo_usrios,
                    'rz_soci' => mb_strtoupper($razonSocial, 'UTF-8'),
                    'co_usua_modi' => $user->cdgo_lgin,
                    'ts_usua_modi' => now(),
                    'in_esta' => '1',
                ]);
            }

            return $usr;
        }
    }

    /**
     * Genera el correlativo del expediente recreando la lógica de geneexpedi.class.php
     */
    private function generarNumeroExpediente(array $data, ?User $user = null): array
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.').'.';
        $cmpnia = $user ? ($user->cdgo_cmpnia ?? 2) : 2;

        $letter = 'E';
        $code = (int) ($data['cdgo_tpo_trmte'] ?? 0);

        if (!empty($data['observado'])) {
            $code = (int) ($data['cdgo_tpo_observado'] ?? 0);
            if ($code <= 0) {
                $code = (int) (DB::table($schema . 'a_tpo_trmtes')->where('dc_tpo_trmte', 'O')->value('cdgo_tpo_trmte') ?? 40);
            }
            $letter = 'O';
        } else {
            if ($code <= 0) {
                $letraBuscar = ($data['tipo_tramite'] ?? 'externo') === 'simple' ? 'S' : 'E';
                $code = (int) (DB::table($schema . 'a_tpo_trmtes')->where('dc_tpo_trmte', $letraBuscar)->value('cdgo_tpo_trmte') ?? ($letraBuscar === 'S' ? 17 : 22));
            }
        }

        $dbLetter = DB::table($schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $code)
            ->value('dc_tpo_trmte');

        if ($dbLetter) {
            $letter = trim($dbLetter);
        }

        // 2. Resolver año fiscal
        $currYear = date('Y');
        // Resolver año fiscal usando la compañía del usuario logueado
        $anioRow = Anio::where('cdgo_cmpnia', $cmpnia)
            ->orderBy('de_anio', 'desc')
            ->first();

        $idAnio = null;
        $deAnio = null;
        $expe = 1;

        if (!$anioRow || (int) $anioRow->de_anio < (int) $currYear) {
            // Crear año fiscal nuevo
            $newAnio = Anio::create([
                'de_anio' => $currYear,
                'dc_anio' => substr($currYear, 2, 2),
                'cdgo_cmpnia' => $cmpnia,
            ]);
            $idAnio = $newAnio->cdgo_anio;
            $deAnio = $currYear;
            $expe = 1;
        } else {
            $idAnio = $anioRow->cdgo_anio;
            $deAnio = $anioRow->de_anio;

            // Obtener el correlativo máximo
            $maxExp = DtoTramite::where('cdgo_anio', $idAnio)
                ->where('cdgo_tpo_trmte', $code)
                ->where('cdgo_cmpnia', $cmpnia)
                ->max('nu_expe_trmte');

            $expe = ($maxExp ? (int)$maxExp : 0) + 1;
        }

        // Formato final: E-000123-2026 (rellenar a 6 caracteres como en busca_vauche/legacy voucher si lo requiere, 
        // pero en el PHP antiguo era simplemente $in_tram."-".$expe . "-" . $anio.
        // Vamos a guardar tal como es y para compatibilidad: "E-X-YYYY")
        $nuExpeTodo = "{$letter}-{$expe}-{$deAnio}";

        return [
            'nu_expe_todo' => $nuExpeTodo,
            'nu_expe_trmte' => $expe,
            'cdgo_anio' => $idAnio,
            'cdgo_tpo_trmte' => $code,
            'in_docu_trmte' => $letter,
        ];
    }

    /**
     * Sanitiza cadenas de texto previniendo XSS, tags HTML y espacios múltiples.
     */
    private function sanitize(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
