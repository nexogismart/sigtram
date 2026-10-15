<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface;
use App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

class BuscaExpedienteService
{
    public function __construct(
        private readonly BuscaExpedienteRepositoryInterface $repository,
        private readonly DatabaseManager $db
    ) {}

    /**
     * Obtiene la ficha completa del expediente para show, detallePopup o imprimir.
     */
    public function obtenerFicha(int $idTram, int $companiaId): ?array
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return null;
        }

        return $this->repository->obtenerFichaExpediente($idTram, $companiaId);
    }

    /**
     * Modifica las observaciones y presentación de requisitos del expediente dentro de una transacción.
     *
     * @param array<int, string> $requisitos
     */
    public function actualizarExpediente(int $idTram, int $companiaId, string $deObse, array $requisitos): void
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new InvalidArgumentException('Identificador de expediente y compañía deben ser positivos.');
        }

        $this->db->transaction(function () use ($idTram, $companiaId, $deObse, $requisitos) {
            $virtual = $this->repository->obtenerExpedienteParaActualizar($idTram, $companiaId, true);
            if (!$virtual) {
                throw new RuntimeException('Expediente no encontrado.');
            }

            $deObseUpper = mb_strtoupper(trim($deObse), 'UTF-8');
            $obseTodo = (string) ($virtual->de_obse_todo ?? '');
            $nuevoTodo = mb_strtoupper(trim($obseTodo . ' - ' . $deObseUpper), 'UTF-8');

            $this->repository->actualizarDatosExpediente($idTram, $companiaId, $deObseUpper, $nuevoTodo, $requisitos);
        });
    }

    /**
     * Carga los parámetros y mensajes para el formulario de anexo / reapertura con validación de compañía.
     */
    public function obtenerDatosAnexo(int $idTram, int $tipo, int $companiaId): array
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new RuntimeException('Expediente no encontrado.');
        }

        if (!$this->repository->existeExpediente($idTram, $companiaId)) {
            throw new RuntimeException('Expediente no encontrado.');
        }

        $ahora = Carbon::now('America/Lima');
        $fechaMostrar = $ahora->format('d/m/Y H:i:s');
        $fechaObs = $ahora->format('d/m/Y');

        if ($tipo === 1) {
            $titulo = 'ANEXAR FOLIOS';
            $mensaje = 'Anexo.- Con fecha ' . $fechaObs;
        } else {
            $titulo = 'REAPERTURA';
            $mensaje = 'Reapertura.- Con fecha ' . $fechaObs;
        }

        return [
            'idTram' => $idTram,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'fechaMostrar' => $fechaMostrar,
        ];
    }

    /**
     * Registra un anexo o reapertura en el expediente dentro de una transacción con aislamiento de compañía y bloqueo.
     */
    public function agregarAnexo(
        int $idTram,
        int $companiaId,
        int $tipo,
        string $deObse,
        int $nuFoli,
        int $idUsua,
        ?UploadedFile $archivo,
        string $ip
    ): int {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new InvalidArgumentException('Identificador de expediente y compañía deben ser positivos.');
        }

        return $this->db->transaction(function () use ($idTram, $companiaId, $tipo, $deObse, $nuFoli, $idUsua, $archivo, $ip) {
            $movimientos = $this->repository->obtenerMovimientosActivosParaBloqueo($idTram, $companiaId);
            $lockedCount = count($movimientos);
            if ($lockedCount === 0) {
                throw new ConflictoConcurrenteTramiteException('Conflicto de concurrencia: no se encontraron movimientos activos para desactivar.');
            }

            $cargo = $movimientos[0];
            $idEsta = $tipo === 2 ? '51' : '25'; // 51=reingreso, 25=anexo
            $deObseUpper = mb_strtoupper(trim($deObse), 'UTF-8');

            $movimientoIds = array_map(fn($m) => (int) $m->cdgo_trmte_dcmnto, $movimientos);
            $this->repository->desactivarMovimientosActivos($idTram, $companiaId, $lockedCount, $movimientoIds);

            $movId = $this->repository->insertarMovimiento([
                'cdgo_crgo_sa' => $cargo->cdgo_crgo_sa,
                'cdgo_crgo_en' => $cargo->cdgo_crgo_en,
                'cdgo_dto_trmte' => $idTram,
                'cdgo_estdo_trmte' => $idEsta,
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $deObseUpper,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => $nuFoli,
            ]);

            if ($archivo && $archivo->isValid()) {
                $extension = strtolower((string) $archivo->getClientOriginalExtension());
                $filename = 'adjunto_' . $movId . '_' . time() . ($extension !== '' ? '.' . $extension : '');
                $archivo->storeAs('adjuntos', $filename, 'public');
                $this->repository->actualizarAdjuntoMovimiento($movId, $filename);
            }

            $virtual = $this->repository->buscarExpediente($idTram, $companiaId);
            $obseTodo = (string) ($virtual->de_obse_todo ?? '');
            $nuevoTodo = mb_strtoupper(trim($obseTodo . ' - ' . $deObseUpper), 'UTF-8');

            $this->repository->actualizarObservacionesExpediente($idTram, $companiaId, $nuevoTodo, $deObseUpper);
            $this->repository->registrarSeguimiento($idUsua, "Se anexo al expediente {$idTram}", $ip);

            return $movId;
        });
    }

    /**
     * Lista los vouchers y anexos del expediente asegurando compañía activa.
     */
    public function listarVouchers(int $idTram, string $fecha, int $companiaId): array
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new RuntimeException('Expediente no encontrado.');
        }

        $res = $this->repository->listarVouchers($idTram, $companiaId);
        if (!$res['expedienteExiste']) {
            throw new RuntimeException('Expediente no encontrado.');
        }

        $fechaDia = $fecha !== '' ? substr($fecha, 0, 10) : '';
        $fechaHora = strlen($fecha) >= 16 ? substr($fecha, 11, 5) : '';

        $anexosFmt = $res['anexos']->map(function ($row) {
            $fe = $row->fe_rcbdo_trmte
                ? Carbon::parse($row->fe_rcbdo_trmte)->timezone('America/Lima')
                : null;

            return (object) [
                'id' => (int) $row->cdgo_trmte_dcmnto,
                'fecha' => $fe ? $fe->format('d/m/Y') : '',
                'hora' => $fe ? $fe->format('H:i') : '',
                'url' => route('tramites.voucher_anexo', ['id_mov' => $row->cdgo_trmte_dcmnto]),
            ];
        });

        return [
            'idTram' => $idTram,
            'fechaDia' => $fechaDia,
            'fechaHora' => $fechaHora,
            'anexos' => $anexosFmt,
            'tieneAnexos' => $res['anexos']->isNotEmpty(),
        ];
    }

    /**
     * Obtiene y formatea los datos para el voucher del anexo asegurando compañía activa.
     */
    public function obtenerVoucherAnexo(int $idMov, int $companiaId): ?array
    {
        if ($idMov <= 0 || $companiaId <= 0) {
            return null;
        }

        $row = $this->repository->obtenerVoucherAnexo($idMov, $companiaId);
        if (!$row) {
            return null;
        }

        $fecha = '';
        $hora = '';
        if ($row->fe_rcbdo_trmte) {
            $fe = Carbon::parse($row->fe_rcbdo_trmte)->timezone('America/Lima');
            $fecha = $fe->format('d/m/Y');
            $hora = $fe->format('H:i');
        }

        return [
            'row' => $row,
            'numeroExpediente' => $this->formatNumeroExpediente((string) ($row->nu_expe_todo ?? '')),
            'fecha' => $fecha,
            'hora' => $hora,
        ];
    }

    /**
     * Registra una cita virtual invirtiendo los cargos del expediente con aislamiento de compañía.
     */
    public function registrarCitaVirtual(
        int $idTram,
        int $companiaId,
        string $fecha,
        string $hora,
        string $deObse,
        int $idUsua
    ): void {
        if ($idTram <= 0 || $companiaId <= 0) {
            throw new InvalidArgumentException('Identificador de expediente y compañía deben ser positivos.');
        }

        try {
            $feCita = Carbon::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora, 'America/Lima');
        } catch (\Throwable) {
            try {
                $feCita = Carbon::parse($fecha . ' ' . $hora, 'America/Lima');
            } catch (\Throwable) {
                throw new InvalidArgumentException('Fecha u hora inválida.');
            }
        }

        $enlace = 'https://meet.jit.si/CitasVirtualesMDB' . $idTram;
        $fechaPeru = $feCita->format('d/m/Y');
        $obs = mb_strtoupper(
            $deObse . ' - SE CREA CITA VIRTUAL PARA EL DIA ' . $fechaPeru
            . ' A LAS ' . $hora
            . ' EL SIGUIENTE ENLACE <a href="' . $enlace . '" target="_blank">Entrar a reunión</a>',
            'UTF-8'
        );

        $this->db->transaction(function () use ($idTram, $companiaId, $idUsua, $obs, $feCita) {
            $movimientos = $this->repository->obtenerMovimientosActivosParaBloqueo($idTram, $companiaId);
            $lockedCount = count($movimientos);
            if ($lockedCount === 0) {
                throw new ConflictoConcurrenteTramiteException('Conflicto de concurrencia: no se encontraron movimientos activos para desactivar.');
            }

            $cargo = $movimientos[0];
            $crgoSa = $cargo->cdgo_crgo_en;
            $crgoEn = $cargo->cdgo_crgo_sa;

            $movimientoIds = array_map(fn($m) => (int) $m->cdgo_trmte_dcmnto, $movimientos);
            $this->repository->desactivarMovimientosActivos($idTram, $companiaId, $lockedCount, $movimientoIds);

            $this->repository->insertarMovimiento([
                'cdgo_crgo_sa' => $crgoSa,
                'cdgo_crgo_en' => $crgoEn,
                'cdgo_dto_trmte' => $idTram,
                'cdgo_estdo_trmte' => '1',
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $obs,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => null,
                'fe_cta_vrtal' => $feCita->format('Y-m-d H:i:s'),
            ]);
        });
    }

    /**
     * Carga datos para el formulario de generación desde virtual con aislamiento de compañía.
     */
    public function obtenerDatosGeneracionExpediente(int $idTram, int $companiaId): ?array
    {
        if ($idTram <= 0 || $companiaId <= 0) {
            return null;
        }

        $ficha = $this->repository->obtenerFichaExpediente($idTram, $companiaId);
        if (!$ficha) {
            return null;
        }

        $idSubarea = (int) ($ficha['tramite']->cdgo_area ?? 0);
        $datosAreas = $this->repository->obtenerDatosGeneracionExpediente($idSubarea);

        return array_merge($ficha, $datosAreas);
    }

    /**
     * Resuelve el tipo de trámite activo para el flujo desde virtual.
     */
    public function resolverTipoTramiteActivo(string $codigo): object
    {
        return $this->repository->resolverTipoTramiteActivoPorCodigo($codigo);
    }

    /**
     * Genera un expediente oficial desde un expediente virtual observado bajo transacción y locking.
     */
    public function generarExpedienteDesdeObservado(
        int $idTramVirtual,
        int $tpoTrmtes,
        int $folios,
        string $deObse,
        int $idUsuaSol,
        int $destAreaId,
        int $idUsua,
        int $cmpnia,
        string $ip
    ): int {
        if ($idTramVirtual <= 0 || $cmpnia <= 0) {
            throw new InvalidArgumentException('Identificador de expediente virtual y compañía deben ser positivos.');
        }

        $destCargoId = $this->repository->obtenerCargoOrdenUnoArea($destAreaId);
        if (!$destCargoId) {
            throw new RuntimeException('No hay cargo activo (orden 1) en el área destino.');
        }

        return $this->db->transaction(function () use (
            $idTramVirtual,
            $tpoTrmtes,
            $folios,
            $deObse,
            $idUsuaSol,
            $destAreaId,
            $destCargoId,
            $idUsua,
            $cmpnia,
            $ip
        ) {
            $virtualData = $this->repository->obtenerExpedienteVirtualConMovimiento($idTramVirtual, $cmpnia, true);
            $virtual = $virtualData['virtual'];
            if (!$virtual) {
                throw new RuntimeException('Expediente virtual no encontrado.');
            }

            $urlsImag = $virtualData['movVirtual']->de_urls_imag ?? '';
            $letter = $this->repository->obtenerPrefijoTipoTramite($tpoTrmtes);
            $correlativo = $this->repository->resolverCorrelativoAnio($cmpnia, $tpoTrmtes, (int) date('Y'));

            $idAnio = $correlativo['idAnio'];
            $deAnio = $correlativo['deAnio'];
            $nuExpe = $correlativo['nuExpe'];

            $nuExpeTodo = $letter . '-' . $nuExpe . '-' . $deAnio;
            $tiSile = !empty($virtual->cdgo_docmnto_trmte) ? '1' : '0';
            $obsNueva = mb_strtoupper(
                trim($deObse . ' - GENERADO DESDE EL TRÁMITE VIRTUAL ' . ($virtual->nu_expe_todo ?? '')),
                'UTF-8'
            );

            $cdgoCorreo = $virtual->cdgo_usrios_crreo ?? null;
            if (empty($cdgoCorreo)) {
                $cdgoCorreo = $this->repository->obtenerCodigoCorreoUsuario($idUsuaSol);
            }

            $idNuevo = $this->repository->insertarExpediente([
                'cdgo_usrios' => $idUsuaSol,
                'cdgo_docmnto_trmte' => $virtual->cdgo_docmnto_trmte ?: null,
                'cdgo_tpo_trmte' => $tpoTrmtes,
                'cdgo_anio' => $idAnio,
                'cdgo_area' => $destAreaId,
                'cdgo_mdlo' => 1,
                'cdgo_cmpnia' => $cmpnia,
                'cdgo_usrios_crreo' => $cdgoCorreo ?: null,
                'nu_expe_todo' => $nuExpeTodo,
                'nu_expe_trmte' => $nuExpe,
                'nu_docu_trmte' => '',
                'in_docu_trmte' => $letter,
                'nu_foli_trmte' => $folios,
                'fe_ingr_trmte' => now(),
                'fe_limi_trmte' => null,
                'de_obse_trmte' => $obsNueva,
                'in_sile_admi' => $tiSile,
                'in_encu_tram' => '0',
                'de_obse_todo' => $obsNueva,
                'co_usua_modi' => $idUsua,
                'co_usua_ingr' => $idUsua,
                'ts_usua_modi' => now(),
            ]);

            $this->repository->insertarMovimiento([
                'cdgo_crgo_sa' => 0,
                'cdgo_crgo_en' => $destCargoId,
                'cdgo_dto_trmte' => $idNuevo,
                'cdgo_estdo_trmte' => '1',
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $obsNueva,
                'in_esta' => '1',
                'de_urls_imag' => $urlsImag ?: '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => null,
            ]);

            $this->repository->copiarRequisitosVirtual($idTramVirtual, $idNuevo);

            // Finalizar trámite virtual (estado 3)
            $cargoFin = $this->repository->obtenerCargoOrdenUnoArea(18) ?? $destCargoId;
            $deTpo = $this->repository->obtenerDescripcionTipoTramite($tpoTrmtes);

            $obsFin = mb_strtoupper(
                'SE FINALIZA TRÁMITE CON LA GENERACION DEL TRAMITE ' . $deTpo . ' N° ' . $nuExpeTodo,
                'UTF-8'
            );

            $movsVirtuales = $this->repository->obtenerMovimientosActivosParaBloqueo($idTramVirtual, $cmpnia);
            $lockedVirtCount = count($movsVirtuales);
            $virtIds = array_map(fn($m) => (int) $m->cdgo_trmte_dcmnto, $movsVirtuales);

            $this->repository->desactivarMovimientosActivos(
                $idTramVirtual,
                $cmpnia,
                $lockedVirtCount > 0 ? $lockedVirtCount : null,
                $virtIds
            );

            $this->repository->insertarMovimiento([
                'cdgo_crgo_sa' => $cargoFin,
                'cdgo_crgo_en' => $cargoFin,
                'cdgo_dto_trmte' => $idTramVirtual,
                'cdgo_estdo_trmte' => '3',
                'fe_rcbdo_trmte' => now(),
                'de_obse' => $obsFin,
                'in_esta' => '1',
                'de_urls_imag' => '',
                'cdgo_lgin' => $idUsua,
                'nu_foli_anex' => null,
            ]);

            $this->repository->registrarSeguimiento(
                $idUsua,
                "Ingreso el expediente Nro{$idNuevo} desde virtual {$idTramVirtual}",
                $ip
            );

            return $idNuevo;
        });
    }

    private function formatNumeroExpediente(string $expe): string
    {
        $parts = explode('-', $expe);
        if (count($parts) < 3) {
            return $expe;
        }

        return $parts[0] . '-' . str_pad($parts[1], 5, '0', STR_PAD_LEFT) . '-' . $parts[2];
    }
}
