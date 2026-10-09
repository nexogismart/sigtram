<?php

declare(strict_types=1);

namespace App\Services\Core\Pide\Providers;

use App\Contracts\Services\PideProviderInterface;
use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;
use Illuminate\Support\Facades\Http;
use Throwable;

class SunarpProvider implements PideProviderInterface
{
    private ?string $lastError = null;

    /**
     * Consulta bienes (vehículos/predios) de un ciudadano por DNI en SUNARP (Demo).
     */
    public function buscarDni(string $dni): ?PersonaDto
    {
        $this->lastError = null;
        $timeout = config('pide.timeout', 3);
        $credentials = config('pide.credentials_pool', []);

        // Demo fallback data si no hay conexión real
        $demoBienes = [
            [
                'partida' => '11002233',
                'registro' => 'PREDIOS',
                'zona' => 'IX - SEDE LIMA',
                'descripcion' => 'DEPARTAMENTO 402, AV. PUENTE PIEDRA 1234'
            ],
            [
                'partida' => '55443322',
                'registro' => 'PROPIEDAD VEHICULAR',
                'zona' => 'IX - SEDE LIMA',
                'descripcion' => 'VEHICULO TOYOTA YARIS - PLACA ABC-123'
            ]
        ];

        // Simulamos la consulta a la PIDE SUNARP
        foreach ($credentials as $creds) {
            try {
                $response = Http::timeout($timeout)
                    ->get("https://ws2.pide.gob.pe/Rest/SUNARP/Titularidad", [
                        'tipoDocumento' => 'DNI',
                        'numeroDocumento' => $dni,
                        'nuDniUsuario' => $creds['usuario'],
                        'nuRucUsuario' => config('pide.ruc_institucional'),
                        'password' => $creds['clave'],
                        'out' => 'json'
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $bienes = $data['titularidadResponse']['listaBienes'] ?? $demoBienes;
                    return new PersonaDto(
                        dni: $dni,
                        nombres: $data['nombres'] ?? 'CIUDADANO',
                        apellidoPaterno: $data['apellidoPaterno'] ?? 'TEST',
                        apellidoMaterno: $data['apellidoMaterno'] ?? 'SUNARP',
                        fuente: $this->obtenerNombreFuente(),
                        fuenteDetalle: $creds['usuario'],
                        bienes: $bienes
                    );
                }
            } catch (Throwable $e) {
                $this->lastError = 'SERVICE_CONNECTION_ERROR';
            }
        }

        // Si falló la llamada real, para fines de la demo retornamos el DTO estructurado con datos mock
        return new PersonaDto(
            dni: $dni,
            nombres: 'CIUDADANO',
            apellidoPaterno: 'DEMO',
            apellidoMaterno: 'SUNARP',
            fuente: $this->obtenerNombreFuente(),
            fuenteDetalle: 'MOCK_DEMO',
            bienes: $demoBienes
        );
    }

    /**
     * Consulta representantes legales de una empresa por RUC en SUNARP (Demo).
     */
    public function buscarRuc(string $ruc, bool $full = false): ?EmpresaDto
    {
        $this->lastError = null;
        $timeout = config('pide.timeout', 3);
        $credentials = config('pide.credentials_pool', []);

        $demoRepresentantes = [
            [
                'dni' => '10203040',
                'nombre' => 'JUAN PEREZ FLORES',
                'cargo' => 'GERENTE GENERAL',
                'partida' => '11223344',
                'firma_autorizada' => 'S'
            ],
            [
                'dni' => '50607080',
                'nombre' => 'MARIA SOTO RUIZ',
                'cargo' => 'APODERADO',
                'partida' => '11223344',
                'firma_autorizada' => 'S'
            ]
        ];

        foreach ($credentials as $creds) {
            try {
                $response = Http::timeout($timeout)
                    ->get("https://ws2.pide.gob.pe/Rest/SUNARP/Representantes", [
                        'ruc' => $ruc,
                        'nuDniUsuario' => $creds['usuario'],
                        'nuRucUsuario' => config('pide.ruc_institucional'),
                        'password' => $creds['clave'],
                        'out' => 'json'
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $representantes = $data['representantesResponse']['listaRepresentantes'] ?? $demoRepresentantes;
                    return new EmpresaDto(
                        ruc: $ruc,
                        razonSocial: $data['razonSocial'] ?? 'EMPRESA SUNARP SAC',
                        fuente: $this->obtenerNombreFuente(),
                        fuenteDetalle: $creds['usuario'],
                        representantesLegales: $representantes
                    );
                }
            } catch (Throwable $e) {
                $this->lastError = 'SERVICE_CONNECTION_ERROR';
            }
        }

        // Si falló la llamada real, para fines de la demo retornamos el DTO estructurado con datos mock
        return new EmpresaDto(
            ruc: $ruc,
            razonSocial: 'EMPRESA DEMO SUNARP SAC',
            fuente: $this->obtenerNombreFuente(),
            fuenteDetalle: 'MOCK_DEMO',
            representantesLegales: $demoRepresentantes
        );
    }

    public function obtenerNombreFuente(): string
    {
        return 'SUNARP';
    }

    public function obtenerUltimoError(): ?string
    {
        return $this->lastError;
    }
}
