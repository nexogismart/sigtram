<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

class IngresoExpedienteService
{
    public function __construct(
        private readonly IngresoExpedienteRepositoryInterface $repository,
        private readonly DatabaseManager $db
    ) {}

    /**
     * Registra un expediente completo de forma transaccional.
     *
     * @param array<string, mixed> $data
     * @param mixed $archivo
     * @return array{id: int, numero: string}
     */
    public function registrar(array $data, User $user, int $companiaId, string $ip, $archivo = null): array
    {
        if ($companiaId <= 0) {
            throw new OperacionTramiteNoAutorizadaException('No existe una compañía activa válida en la sesión del usuario.');
        }

        return $this->db->transaction(function () use ($data, $user, $ip, $archivo, $companiaId) {
            $loginId = (int) $user->cdgo_lgin;

            // 1. Resolver o Crear Solicitante
            $cdgoUsrios = $this->repository->resolverSolicitante($data, $loginId, $companiaId);

            // 2. Generar Número de Expediente con lock
            $numExp = $this->repository->generarNumeroExpediente($data, $companiaId);

            // 3. Obtener Cargo del Operador y Cargo de Destino
            $operatorCargoId = (int) session(\App\Support\SessionKeys::CARGO_ID, 0);
            if ($operatorCargoId === 0) {
                $operatorCargoId = $this->repository->obtenerPrimerCargoUsuario($loginId);
            }

            $destAreaId = null;
            $destCargoId = null;
            $estadoTrmte = 1; // 1 = Recepcionado / Pendiente
            $inSgmto = false;

            if (!empty($data['observado'])) {
                $areaObservadoId = (int) ($data['cdgo_area_observado'] ?? 0);
                if ($areaObservadoId <= 0) {
                    $areaObservadoId = $this->repository->resolverAreaObservado();
                }
                $destAreaId = $areaObservadoId ?: 18;
                $destCargoId = $this->repository->obtenerPrimerCargoArea($destAreaId) ?: $operatorCargoId;
                $estadoTrmte = 12;
            } else {
                $destinoModo = (string) ($data['destino_modo'] ?? '');
                $tipoTramTupa = (int) ($data['tipo_tram_tupa'] ?? 0);

                if ($destinoModo === 'servicios' || ($destinoModo === 'seleccion' && $tipoTramTupa > 0)) {
                    $tupaDoc = $this->repository->obtenerDatosTupa($tipoTramTupa);
                    if (!$tupaDoc) {
                        throw new RuntimeException("No se encontró el trámite TUPA ID: {$tipoTramTupa}");
                    }
                    $destAreaId = (int) ($tupaDoc->cdgo_area ?? 18);
                    $destCargoId = $this->repository->obtenerPrimerCargoArea($destAreaId);
                    $inSgmto = true;
                } elseif ($destinoModo === 'areas') {
                    $destAreaId = (int) ($data['subgerencia'] ?: $data['gerencia']);
                    $destCargoId = $this->repository->obtenerPrimerCargoArea($destAreaId);
                } else {
                    $destAreaId = 18;
                    $destCargoId = $this->repository->obtenerPrimerCargoArea(18) ?? $operatorCargoId;
                }
            }

            if (!$destCargoId) {
                throw new RuntimeException("No se encontró un cargo de destino activo (nu_orde = 1) para el área seleccionada (ID: {$destAreaId}).");
            }

            // 4. Determinar silencio administrativo y observaciones
            $inSileAdmi = '0';
            $tipoTramTupa = (int) ($data['tipo_tram_tupa'] ?? 0);
            $destinoModo = (string) ($data['destino_modo'] ?? '');

            if ($destinoModo === 'servicios' || ($destinoModo === 'seleccion' && $tipoTramTupa > 0)) {
                $tupaInfo = $this->repository->obtenerDatosTupa($tipoTramTupa);
                if ($tupaInfo && isset($tupaInfo->in_sile_admi)) {
                    $inSileAdmi = (string) $tupaInfo->in_sile_admi;
                }
            }

            $deObse = mb_strtoupper($this->sanitize($data['observaciones'] ?? ''), 'UTF-8');
            if ($inSgmto && $tipoTramTupa > 0) {
                $tupaDoc = $this->repository->obtenerDatosTupa($tipoTramTupa);
                if ($tupaDoc && !empty($tupaDoc->de_docmnto_trmte)) {
                    $deObse = trim(mb_strtoupper((string) $tupaDoc->de_docmnto_trmte, 'UTF-8') . '; ' . $deObse);
                }
            }

            // 5. Crear Expediente (Cabecera)
            $expedienteId = $this->repository->crearExpedienteCabecera([
                'cdgo_usrios' => $cdgoUsrios,
                'cdgo_docmnto_trmte' => ($destinoModo === 'servicios' || $destinoModo === 'seleccion') ? ($tipoTramTupa ?: null) : null,
                'cdgo_tpo_trmte' => $numExp['cdgo_tpo_trmte'],
                'cdgo_anio' => $numExp['cdgo_anio'],
                'cdgo_area' => $destAreaId,
                'cdgo_mdlo' => 1,
                'cdgo_cmpnia' => $companiaId,
                'cdgo_usrios_crreo' => null,
                'nu_expe_todo' => $numExp['nu_expe_todo'],
                'nu_expe_trmte' => $numExp['nu_expe_trmte'],
                'nu_docu_trmte' => '',
                'in_docu_trmte' => $numExp['in_docu_trmte'],
                'nu_foli_trmte' => (int) ($data['folios'] ?? 0),
                'fe_ingr_trmte' => $data['fecha_ingreso'],
                'fe_limi_trmte' => null,
                'de_obse_trmte' => $deObse,
                'in_sile_admi' => $inSileAdmi,
                'in_encu_tram' => '0',
                'de_obse_todo' => $deObse,
                'co_usua_modi' => $loginId,
                'co_usua_ingr' => $loginId,
                'ts_usua_modi' => now(),
            ]);

            // 6. Crear Movimiento Inicial
            $movimientoId = $this->repository->crearMovimientoInicial([
                'cdgo_crgo_sa' => 0,
                'cdgo_crgo_en' => $destCargoId,
                'cdgo_dto_trmte' => $expedienteId,
                'cdgo_estdo_trmte' => $estadoTrmte,
                'fe_rcbdo_trmte' => $data['fecha_ingreso'],
                'de_obse' => $deObse,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $loginId,
                'nu_foli_anex' => $data['folios'] ?? null,
                'fe_cta_vrtal' => null,
                'nu_anxo' => null,
            ]);

            // 7. Guardar Adjunto si existe
            if ($archivo instanceof \Illuminate\Http\UploadedFile && $archivo->isValid()) {
                $ext = strtolower($archivo->getClientOriginalExtension());
                $filename = 'adjunto_' . $movimientoId . '_' . time() . '.' . $ext;
                $archivo->storeAs('adjuntos', $filename, 'public');
                $this->repository->actualizarAdjuntoMovimiento($movimientoId, $filename);
            }

            // 8. Asignar Rutas TUPA
            if ($inSgmto && $tipoTramTupa > 0) {
                $this->repository->asignarRutasTupa($expedienteId, $tipoTramTupa, (string) $data['fecha_ingreso']);
            }

            // 9. Registrar Requisitos TUPA
            if ($destinoModo === 'servicios' || ($destinoModo === 'seleccion' && $tipoTramTupa > 0)) {
                $marcados = (array) ($data['requisitos_marcados'] ?? []);
                $this->repository->registrarRequisitosTupa($expedienteId, $tipoTramTupa, $marcados);
            }

            // 10. Auditoría Fail-Closed (R17-P1-02)
            $this->repository->registrarSeguimiento(
                $loginId,
                "Ingreso el expediente Nro {$numExp['nu_expe_todo']}",
                $ip
            );

            return [
                'id' => $expedienteId,
                'numero' => $numExp['nu_expe_todo'],
            ];
        });
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
        $value = (string) preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
