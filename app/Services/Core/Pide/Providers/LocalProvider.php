<?php

declare(strict_types=1);

namespace App\Services\Core\Pide\Providers;

use App\Contracts\Services\PideProviderInterface;
use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;
use App\Models\PUsrrio;

class LocalProvider implements PideProviderInterface
{
    private ?string $lastError = null;

    public function buscarDni(string $dni): ?PersonaDto
    {
        $this->lastError = null;

        // Buscamos el usuario en p_usrios que esté activo ('1')
        $usuario = PUsrrio::where('nu_docu', $dni)
            ->where('in_esta', '1')
            ->orderBy('cdgo_usrios', 'desc')
            ->first();

        if (!$usuario) {
            return null;
        }

        // Cargamos la relación de usuario natural (h_usrios_ntral)
        $natural = $usuario->natural;
        if (!$natural) {
            return null;
        }

        return new PersonaDto(
            dni: $usuario->nu_docu,
            nombres: $natural->no_usrio ?? '',
            apellidoPaterno: $natural->ap_pate ?? '',
            apellidoMaterno: $natural->ap_mate ?? '',
            direccion: $usuario->de_dire,
            foto: $usuario->de_foto,
            fuente: $this->obtenerNombreFuente(),
            cdgoUsrios: $usuario->cdgo_usrios
        );
    }

    public function buscarRuc(string $ruc, bool $full = false): ?EmpresaDto
    {
        $this->lastError = null;

        // Buscamos en p_usrios por el número de documento RUC
        $usuario = PUsrrio::where('nu_docu', $ruc)
            ->where('in_esta', '1')
            ->orderBy('cdgo_usrios', 'desc')
            ->first();

        if (!$usuario) {
            return null;
        }

        // Cargamos la relación de persona jurídica (h_usrios_jrid)
        $juridico = $usuario->juridico;
        if (!$juridico) {
            return null;
        }

        return new EmpresaDto(
            ruc: $usuario->nu_docu,
            razonSocial: $juridico->rz_soci ?? '',
            direccion: $usuario->de_dire,
            estado: 'ACTIVO',
            condicion: 'HABIDO',
            fuente: $this->obtenerNombreFuente(),
            cdgoUsrios: $usuario->cdgo_usrios
        );
    }

    public function obtenerNombreFuente(): string
    {
        return 'SIGTRAM';
    }

    public function obtenerUltimoError(): ?string
    {
        return $this->lastError;
    }
}
