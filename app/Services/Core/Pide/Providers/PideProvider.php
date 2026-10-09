<?php

declare(strict_types=1);

namespace App\Services\Core\Pide\Providers;

use App\Contracts\Services\PideProviderInterface;
use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;
use Illuminate\Support\Facades\Http;
use SoapClient;
use SoapFault;
use Throwable;

class PideProvider implements PideProviderInterface
{
    private ?string $lastError = null;

    public function buscarDni(string $dni): ?PersonaDto
    {
        $this->lastError = null;
        $timeout = config('pide.timeout', 3);
        $credentials = config('pide.credentials_pool', []);

        $attempts = 0;
        foreach ($credentials as $creds) {
            if ($attempts >= 3) {
                break;
            }
            $attempts++;

            try {
                $response = Http::timeout($timeout)
                    ->get("https://ws2.pide.gob.pe/Rest/RENIEC/Consultar", [
                        'nuDniConsulta' => $dni,
                        'nuDniUsuario' => $creds['usuario'],
                        'nuRucUsuario' => config('pide.ruc_institucional'),
                        'password' => $creds['clave'],
                        'out' => 'json'
                    ]);

                if ($response->failed()) {
                    $this->lastError = 'SERVICE_CONNECTION_TIMEOUT';
                    break; // Error de conexión: abortamos todo PIDE
                }

                $res = $response->json();
                
                // En Laravel Http ->json() puede retornar array
                $return = $res['consultarResponse']['return'] ?? null;

                if ($return) {
                    $code = $return['coResultado'] ?? '';

                    // 0000: Éxito
                    if ($code === '0000' && isset($return['datosPersona'])) {
                        $p = $return['datosPersona'];
                        return new PersonaDto(
                            dni: $dni,
                            nombres: $p['prenombres'] ?? '',
                            apellidoPaterno: $p['apPrimer'] ?? '',
                            apellidoMaterno: $p['apSegundo'] ?? '',
                            direccion: $p['direccion'] ?? null,
                            foto: $p['foto'] ?? null,
                            fuente: $this->obtenerNombreFuente(),
                            fuenteDetalle: $creds['usuario']
                        );
                    }

                    // 0001 / 1000 / 1001: No encontrado
                    if (in_array($code, ['0001', '1000', '1001'])) {
                        $this->lastError = null; // No es un error de sistema, no existe
                        return null;
                    }
                }
            } catch (Throwable $e) {
                $this->lastError = 'EXTERNAL_ERROR';
                break;
            }
        }

        $this->lastError = 'EXTERNAL_ERROR';
        return null;
    }

    public function buscarRuc(string $ruc, bool $full = false): ?EmpresaDto
    {
        $this->lastError = null;
        $url = "https://ws3.pide.gob.pe/services/SunatConsultaRuc?wsdl";
        $credentials = config('pide.credentials_pool', []);

        foreach ($credentials as $creds) {
            try {
                if (!class_exists('SoapClient')) {
                    $this->lastError = 'SOAP_EXTENSION_MISSING';
                    return null;
                }

                $client = new SoapClient($url, [
                    'connection_timeout' => config('pide.timeout', 3),
                    'exceptions' => true
                ]);

                $params = ['numruc' => $ruc];
                $principales = $client->getDatosPrincipales($params);
                $domicilio = $client->getDomicilioLegal($params);

                // SoapClient retorna objetos stdClass
                if ($principales && isset($principales->ddp_nombre)) {
                    $direccion = is_string($domicilio) ? $domicilio : ($domicilio->ddp_nombre ?? null);
                    return new EmpresaDto(
                        ruc: $ruc,
                        razonSocial: $principales->ddp_nombre,
                        direccion: $direccion,
                        estado: 'ACTIVO',
                        condicion: 'HABIDO',
                        fuente: $this->obtenerNombreFuente(),
                        fuenteDetalle: $creds['usuario']
                    );
                }
            } catch (SoapFault $e) {
                if ($e->faultcode === 'HTTP' || strpos($e->getMessage(), 'Could not connect') !== false) {
                    $this->lastError = 'SERVICE_CONNECTION_TIMEOUT';
                    break;
                }
                continue; // Probamos siguiente credencial
            } catch (Throwable $e) {
                $this->lastError = 'EXTERNAL_ERROR';
                break;
            }
        }

        $this->lastError = 'EXTERNAL_ERROR';
        return null;
    }

    public function obtenerNombreFuente(): string
    {
        return 'PIDE';
    }

    public function obtenerUltimoError(): ?string
    {
        return $this->lastError;
    }
}
