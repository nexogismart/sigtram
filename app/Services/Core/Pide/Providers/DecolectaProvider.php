<?php

declare(strict_types=1);

namespace App\Services\Core\Pide\Providers;

use App\Contracts\Services\PideProviderInterface;
use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;
use Illuminate\Support\Facades\Http;
use Throwable;

class DecolectaProvider implements PideProviderInterface
{
    private string $baseUrl = 'https://api.decolecta.com/v1';
    private ?string $lastError = null;

    public function buscarDni(string $dni): ?PersonaDto
    {
        $this->lastError = null;
        $tokens = config('pide.decolecta_tokens', []);

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($token)
                    ->timeout(config('pide.timeout', 3))
                    ->get("{$this->baseUrl}/reniec/dni", [
                        'numero' => $dni
                    ]);

                // 429 (Cuota excedida) o 401 (No autorizado): rotamos al siguiente token
                if ($response->status() === 429 || $response->status() === 401) {
                    continue;
                }

                if ($response->failed()) {
                    $this->lastError = 'EXTERNAL_ERROR';
                    continue;
                }

                $res = $response->json();
                $nombres = $res['nombres'] ?? $res['first_name'] ?? null;
                $paterno = $res['apellido_paterno'] ?? $res['first_last_name'] ?? null;
                $materno = $res['apellido_materno'] ?? $res['second_last_name'] ?? null;

                if ($nombres) {
                    return new PersonaDto(
                        dni: $dni,
                        nombres: $nombres,
                        apellidoPaterno: $paterno ?? '',
                        apellidoMaterno: $materno ?? '',
                        direccion: $res['direccion'] ?? null,
                        fuente: $this->obtenerNombreFuente(),
                        fuenteDetalle: substr($token, 0, 8) . '...'
                    );
                }
            } catch (Throwable $e) {
                $this->lastError = 'EXTERNAL_ERROR';
                continue;
            }
        }

        $this->lastError = 'EXTERNAL_ERROR';
        return null;
    }

    public function buscarRuc(string $ruc, bool $full = false): ?EmpresaDto
    {
        $this->lastError = null;
        $tokens = config('pide.decolecta_tokens', []);

        foreach ($tokens as $token) {
            try {
                $endpoint = $full ? 'sunat/ruc/full' : 'sunat/ruc';
                $response = Http::withToken($token)
                    ->timeout(config('pide.timeout', 3))
                    ->get("{$this->baseUrl}/{$endpoint}", [
                        'numero' => $ruc
                    ]);

                if ($response->status() === 429 || $response->status() === 401) {
                    continue;
                }

                if ($response->failed()) {
                    $this->lastError = 'EXTERNAL_ERROR';
                    continue;
                }

                $res = $response->json();
                $razonSocial = $res['razon_social'] ?? $res['full_name'] ?? null;

                if ($razonSocial) {
                    return new EmpresaDto(
                        ruc: $ruc,
                        razonSocial: $razonSocial,
                        direccion: $res['direccion'] ?? null,
                        estado: $res['estado'] ?? null,
                        condicion: $res['condicion'] ?? null,
                        fuente: $this->obtenerNombreFuente(),
                        fuenteDetalle: substr($token, 0, 8) . '...'
                    );
                }
            } catch (Throwable $e) {
                $this->lastError = 'EXTERNAL_ERROR';
                continue;
            }
        }

        $this->lastError = 'EXTERNAL_ERROR';
        return null;
    }

    public function obtenerNombreFuente(): string
    {
        return 'API DECOLECTA';
    }

    public function obtenerUltimoError(): ?string
    {
        return $this->lastError;
    }
}
