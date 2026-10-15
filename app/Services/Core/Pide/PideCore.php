<?php

declare(strict_types=1);

namespace App\Services\Core\Pide;

use App\Contracts\Services\PideCoreInterface;
use App\Contracts\Services\PideProviderInterface;
use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;
use App\Services\Core\Pide\Providers\LocalProvider;
use App\Services\Core\Pide\Providers\PideProvider;
use App\Services\Core\Pide\Providers\DecolectaProvider;
use App\Services\Core\Pide\Providers\SunarpProvider;
use App\Models\PUsrrio;
use App\Models\UsrioNatural;
use App\Models\UsrioJuridico;
use App\Support\SessionKeys;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Throwable;

class PideCore implements PideCoreInterface
{
    protected PideProvider $pideProvider;
    protected DecolectaProvider $decolectaProvider;
    protected LocalProvider $localProvider;
    protected SunarpProvider $sunarpProvider;
    protected ?string $warning = null;

    public function __construct(
        PideProvider $pideProvider,
        DecolectaProvider $decolectaProvider,
        LocalProvider $localProvider,
        SunarpProvider $sunarpProvider
    ) {
        $this->pideProvider = $pideProvider;
        $this->decolectaProvider = $decolectaProvider;
        $this->localProvider = $localProvider;
        $this->sunarpProvider = $sunarpProvider;
    }

    public function consultarDetalle(string $documento, string $tipo = 'DNI'): ?object
    {
        $this->warning = null;
        $this->verificarRateLimit();

        // 1. Flujo en cascada: PIDE Oficial -> Local -> Decolecta
        $resultado = $this->ejecutarCascada($documento, $tipo);

        // 2. Si se encontró, se complementa opcionalmente con SUNARP
        if ($resultado && config('pide.usar_sunarp')) {
            $resultado = $this->complementarConSunarp($resultado, $documento, $tipo);
        }

        // Registro de auditoría informativa
        if ($resultado) {
            $this->registrarAuditoria($documento, $tipo, $resultado->fuente, true, $resultado->fuenteDetalle);
        } else {
            $this->registrarAuditoria($documento, $tipo, 'NO_ENCONTRADO', false);
        }

        return $resultado;
    }

    public function buscarYRegistrarParaTramite(string $documento, string $tipo = 'DNI', ?array $forceProviders = null): ?object
    {
        $this->warning = null;
        $this->verificarRateLimit();

        // 1. Ejecutar cascada de consulta
        $resultado = $this->ejecutarCascada($documento, $tipo, $forceProviders);

        if (!$resultado) {
            $this->registrarAuditoria($documento, $tipo, 'FALLIDO', false);
            return null;
        }

        // 2. Realizamos la lógica de persistencia / discrepancia
        try {
            return DB::transaction(function () use ($resultado, $tipo) {
                if ($tipo === 'DNI') {
                    return $this->procesarPersistenciaPersona($resultado);
                } else {
                    return $this->procesarPersistenciaEmpresa($resultado);
                }
            });
        } catch (Throwable $e) {
            $this->warning = 'PERSISTENCE_ERROR';
            $this->registrarAuditoria($documento, $tipo, $resultado->fuente, false, $resultado->fuenteDetalle);
            return null;
        }
    }

    public function obtenerUltimoAviso(): ?string
    {
        return $this->warning;
    }

    public function obtenerMensajeError(string $tipo = 'DNI'): string
    {
        $hasActive = config('pide.usar_oficial') || config('pide.usar_decolecta');

        if (!$hasActive && !config('pide.usar_local')) {
            return "Sin servicio por mantenimiento técnico de la plataforma.";
        }

        if ($this->warning === 'EXTERNAL_SERVICE_UNAVAILABLE') {
            return "Las fuentes externas no responden en este momento y el número de documento solicitado no se encuentra localmente. Por favor, reintente en unos minutos.";
        }

        if (!$hasActive) {
            return "Sin servicio por mantenimiento de conexión externa.";
        }

        return ($tipo === 'RUC')
            ? "El RUC solicitado no se encuentra registrado en SUNAT ni en nuestra base de datos."
            : "El número de DNI ingresado no existe en los registros oficiales ni locales.";
    }

    /**
     * Ejecuta el orden de cascada prioritario: PIDE -> Local -> Decolecta.
     */
    protected function ejecutarCascada(string $documento, string $tipo, ?array $forceProviders = null): ?object
    {
        $anyExternalAttempted = false;
        $anyExternalFailed = false;

        // 1. Primer Paso: PIDE Oficial (Si está activo y no se filtra)
        if (config('pide.usar_oficial') && ($forceProviders === null || $this->contieneProveedor('Pide', $forceProviders))) {
            $anyExternalAttempted = true;
            try {
                $res = ($tipo === 'RUC')
                    ? $this->pideProvider->buscarRuc($documento)
                    : $this->pideProvider->buscarDni($documento);

                if ($res) {
                    return $res;
                }

                if ($this->pideProvider->obtenerUltimoError()) {
                    $anyExternalFailed = true;
                }
            } catch (Throwable $e) {
                $anyExternalFailed = true;
            }
        }

        // 2. Segundo Paso: Base de Datos Local (como Contingencia / Ahorro de costos)
        if (config('pide.usar_local') && ($forceProviders === null || $this->contieneProveedor('Local', $forceProviders))) {
            $res = ($tipo === 'RUC')
                ? $this->localProvider->buscarRuc($documento)
                : $this->localProvider->buscarDni($documento);

            if ($res) {
                return $res;
            }
        }

        // 3. Tercer Paso: Decolecta (Si está activo y no se filtra)
        if (config('pide.usar_decolecta') && ($forceProviders === null || $this->contieneProveedor('Decolecta', $forceProviders))) {
            $anyExternalAttempted = true;
            try {
                $res = ($tipo === 'RUC')
                    ? $this->decolectaProvider->buscarRuc($documento)
                    : $this->decolectaProvider->buscarDni($documento);

                if ($res) {
                    return $res;
                }

                if ($this->decolectaProvider->obtenerUltimoError()) {
                    $anyExternalFailed = true;
                }
            } catch (Throwable $e) {
                $anyExternalFailed = true;
            }
        }

        // Si intentamos consultar externamente y falló de red, activamos la contingencia
        if ($anyExternalAttempted && $anyExternalFailed) {
            $this->warning = 'EXTERNAL_SERVICE_UNAVAILABLE';
        }

        return null;
    }

    /**
     * Helper para verificar si un proveedor está en la lista de forzados.
     */
    protected function contieneProveedor(string $nombre, array $forceProviders): bool
    {
        foreach ($forceProviders as $f) {
            if (stripos($nombre, $f) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Procesa la inserción/actualización de Persona Natural detectando discrepancias.
     */
    protected function procesarPersistenciaPersona(PersonaDto $dto): PersonaDto
    {
        // Buscamos si existe localmente
        $usuario = PUsrrio::where('nu_docu', $dto->dni)
            ->where('in_esta', '1')
            ->orderBy('cdgo_usrios', 'desc')
            ->first();

        if (!$usuario) {
            // Caso A: Totalmente nuevo -> Inserción limpia en bloque transaccional
            $usuario = PUsrrio::create([
                'nu_docu' => $dto->dni,
                'de_dire' => $dto->direccion,
                'de_foto' => $dto->foto,
                'in_tipo_pers' => 'N',
                'in_esta' => '1',
                'no_crto_sstma' => $dto->nombreCompleto,
            ]);

            UsrioNatural::create([
                'cdgo_usrios' => $usuario->cdgo_usrios,
                'no_usrio' => $dto->nombres,
                'ap_pate' => $dto->apellidoPaterno,
                'ap_mate' => $dto->apellidoMaterno,
                'cdgo_dcmntos_iden' => 1,
                'cdgo_estdo_cvil' => 1,
                'ts_usua_modi' => now(),
            ]);

            $this->registrarAuditoria($dto->dni, 'DNI', $dto->fuente, true, $dto->fuenteDetalle);

            return new PersonaDto(
                dni: $dto->dni,
                nombres: $dto->nombres,
                apellidoPaterno: $dto->apellidoPaterno,
                apellidoMaterno: $dto->apellidoMaterno,
                direccion: $dto->direccion,
                foto: $dto->foto,
                fuente: $dto->fuente,
                fuenteDetalle: $dto->fuenteDetalle,
                cdgoUsrios: $usuario->cdgo_usrios
            );
        }

        // Caso B: El ciudadano ya existe. Comparamos discrepancias con RENIEC
        $natural = $usuario->natural;

        $localData = [
            'nombres' => $natural->no_usrio ?? '',
            'apellido_paterno' => $natural->ap_pate ?? '',
            'apellido_materno' => $natural->ap_mate ?? '',
            'direccion' => $usuario->de_dire ?? '',
        ];

        $differences = (
            strcasecmp(trim($natural->no_usrio ?? ''), trim($dto->nombres)) !== 0 ||
            strcasecmp(trim($natural->ap_pate ?? ''), trim($dto->apellidoPaterno)) !== 0 ||
            strcasecmp(trim($natural->ap_mate ?? ''), trim($dto->apellidoMaterno)) !== 0 ||
            ($dto->direccion !== null && strcasecmp(trim($usuario->de_dire ?? ''), trim($dto->direccion)) !== 0)
        );

        if ($differences && $dto->fuente !== 'SIGTRAM') {
            // Existe discrepancia: Retornamos el DTO marcando la advertencia para que el operador decida
            return new PersonaDto(
                dni: $dto->dni,
                nombres: $dto->nombres,
                apellidoPaterno: $dto->apellidoPaterno,
                apellidoMaterno: $dto->apellidoMaterno,
                direccion: $dto->direccion,
                foto: $dto->foto,
                fuente: $dto->fuente,
                fuenteDetalle: $dto->fuenteDetalle,
                cdgoUsrios: $usuario->cdgo_usrios,
                tieneDiscrepancia: true,
                datosLocales: $localData
            );
        }

        // Si son iguales, solo refrescamos la fecha de última modificación
        if ($natural) {
            $natural->update(['ts_usua_modi' => now()]);
        }

        $this->registrarAuditoria($dto->dni, 'DNI', $dto->fuente, true, $dto->fuenteDetalle);

        return new PersonaDto(
            dni: $dto->dni,
            nombres: $dto->nombres,
            apellidoPaterno: $dto->apellidoPaterno,
            apellidoMaterno: $dto->apellidoMaterno,
            direccion: $usuario->de_dire,
            foto: $usuario->de_foto,
            fuente: $dto->fuente,
            fuenteDetalle: $dto->fuenteDetalle,
            cdgoUsrios: $usuario->cdgo_usrios
        );
    }

    /**
     * Procesa la inserción/actualización de Persona Jurídica detectando discrepancias.
     */
    protected function procesarPersistenciaEmpresa(EmpresaDto $dto): EmpresaDto
    {
        $usuario = PUsrrio::where('nu_docu', $dto->ruc)
            ->where('in_esta', '1')
            ->orderBy('cdgo_usrios', 'desc')
            ->first();

        if (!$usuario) {
            // Inserción limpia de nueva empresa
            $usuario = PUsrrio::create([
                'nu_docu' => $dto->ruc,
                'de_dire' => $dto->direccion,
                'in_tipo_pers' => 'J',
                'in_esta' => '1',
                'no_crto_sstma' => $dto->razonSocial,
            ]);

            UsrioJuridico::create([
                'cdgo_usrios' => $usuario->cdgo_usrios,
                'rz_soci' => $dto->razonSocial,
                'ts_usua_modi' => now(),
                'in_esta' => '1',
            ]);

            $this->registrarAuditoria($dto->ruc, 'RUC', $dto->fuente, true, $dto->fuenteDetalle);

            return new EmpresaDto(
                ruc: $dto->ruc,
                razonSocial: $dto->razonSocial,
                direccion: $dto->direccion,
                estado: $dto->estado,
                condicion: $dto->condicion,
                fuente: $dto->fuente,
                fuenteDetalle: $dto->fuenteDetalle,
                cdgoUsrios: $usuario->cdgo_usrios
            );
        }

        $juridico = $usuario->juridico;

        $localData = [
            'razon_social' => $juridico->rz_soci ?? '',
            'direccion' => $usuario->de_dire ?? '',
        ];

        $differences = (
            strcasecmp(trim($juridico->rz_soci ?? ''), trim($dto->razonSocial)) !== 0 ||
            ($dto->direccion !== null && strcasecmp(trim($usuario->de_dire ?? ''), trim($dto->direccion)) !== 0)
        );

        if ($differences && $dto->fuente !== 'SIGTRAM') {
            return new EmpresaDto(
                ruc: $dto->ruc,
                razonSocial: $dto->razonSocial,
                direccion: $dto->direccion,
                estado: $dto->estado,
                condicion: $dto->condicion,
                fuente: $dto->fuente,
                fuenteDetalle: $dto->fuenteDetalle,
                cdgoUsrios: $usuario->cdgo_usrios,
                tieneDiscrepancia: true,
                datosLocales: $localData
            );
        }

        if ($juridico) {
            $juridico->update(['ts_usua_modi' => now()]);
        }

        $this->registrarAuditoria($dto->ruc, 'RUC', $dto->fuente, true, $dto->fuenteDetalle);

        return new EmpresaDto(
            ruc: $dto->ruc,
            razonSocial: $dto->razonSocial,
            direccion: $usuario->de_dire,
            estado: $dto->estado,
            condicion: $dto->condicion,
            fuente: $dto->fuente,
            fuenteDetalle: $dto->fuenteDetalle,
            cdgoUsrios: $usuario->cdgo_usrios
        );
    }

    /**
     * Controla el Rate Limit de consultas del usuario.
     */
    protected function verificarRateLimit(): void
    {
        $loginId = (int) (Auth::id() ?? session(SessionKeys::LOGIN_ID, 0));
        $key = $loginId > 0 ? "pide:{$loginId}" : "pide-ip:" . Request::ip();

        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw new \RuntimeException('Excede el límite de seguridad de consultas (máximo 30 por minuto).');
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Guarda la auditoría física en sistema.h_pide_consltas.
     */
    protected function registrarAuditoria(string $numero, string $tipo, string $fuente, bool $exito, ?string $fuenteDetalle = null): void
    {
        try {
            $loginId = (int) (Auth::id() ?? session(SessionKeys::LOGIN_ID, 0));
            $ip = Request::ip() ?? '127.0.0.1';
            $userAgent = Request::userAgent() ?? 'Desconocido';

            DB::table('sistema.h_pide_consltas')->insert([
                'nu_documento' => $numero,
                'tp_documento' => $tipo,
                'cdgo_lgin' => $loginId > 0 ? $loginId : null,
                'ip_terminal' => $ip,
                'fuente' => $fuente,
                'pide_user_used' => $fuenteDetalle,
                'txt_dispositivo' => $userAgent,
                'fcha_cnsulta' => now(),
                'exito' => $exito
            ]);
        } catch (Throwable $e) {
            // Fallo silencioso en logs para no interrumpir el flujo principal de atención
            report($e);
        }
    }

    /**
     * Enriquece el DTO de identidad con datos registrales de SUNARP (Demo).
     */
    protected function complementarConSunarp(object $dto, string $documento, string $tipo): object
    {
        try {
            if ($tipo === 'RUC' && $dto instanceof EmpresaDto) {
                $sunarpData = $this->sunarpProvider->buscarRuc($documento);
                if ($sunarpData && !empty($sunarpData->representantesLegales)) {
                    return $dto->withRepresentantesLegales($sunarpData->representantesLegales);
                }
            } elseif ($tipo === 'DNI' && $dto instanceof PersonaDto) {
                $sunarpData = $this->sunarpProvider->buscarDni($documento);
                if ($sunarpData && !empty($sunarpData->bienes)) {
                    return $dto->withBienes($sunarpData->bienes);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $dto;
    }
}
