<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface;
use App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Exceptions\ContextoInvalidoException;
use App\Support\SessionKeys;
use Illuminate\Support\Facades\Auth;

/**
 * Servicio centralizado para orquestar la resolución, validación y persistencia
 * del contexto institucional (identidad, compañía, cargo y área) de usuarios.
 *
 * REGLA: En SIGTRAM3 existe una sola clave canónica por concepto mediante SessionKeys.
 * Cero dual-writes, cero aliases legacy y cero bridges generales.
 */
class ContextoUsuarioAutenticadoService implements ContextoUsuarioAutenticadoServiceInterface
{
    public function __construct(
        protected ContextoUsuarioAutenticadoRepositoryInterface $repository
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerContextoActivo(?int $idLogin = null, ?int $idCompania = null, ?int $idCargo = null): ?ContextoUsuarioAutenticadoDto
    {
        $user = Auth::user();
        if ($idLogin === null) {
            $rawId = Auth::id();
            if (is_numeric($rawId) && (int) $rawId > 0) {
                $idLogin = (int) $rawId;
            } elseif ($user) {
                if (isset($user->cdgo_lgin) && (int) $user->cdgo_lgin > 0) {
                    $idLogin = (int) $user->cdgo_lgin;
                } elseif (isset($user->cdgo_usrios) && (int) $user->cdgo_usrios > 0) {
                    $idLogin = (int) $user->cdgo_usrios;
                } elseif (isset($user->id) && is_numeric($user->id) && (int) $user->id > 0) {
                    $idLogin = (int) $user->id;
                }
            }
            if ($idLogin === null) {
                $idLogin = (int) session(SessionKeys::LOGIN_ID, 0);
            }
        }
        if ($idLogin <= 0) {
            return null;
        }

        $idCompania = $idCompania ?? (int) session(SessionKeys::COMPANIA_ID, 0);
        if ($idCompania <= 0) {
            return null;
        }

        $idCargo = $idCargo ?? (int) session(SessionKeys::CARGO_ID, 0);
        if ($idCargo <= 0) {
            return null;
        }

        $idArea = (int) session(SessionKeys::AREA_ID, 0);
        if ($idArea > 0) {
            $nombreLogin = (string) ($user->de_lgin ?? $user->cdgo_lgin ?? session(SessionKeys::LOGIN_NOMBRE, ''));
            $idUsuario = (int) ($user->cdgo_usrios ?? session(SessionKeys::USUARIO_ID, 0));
            $idPerfil = (int) ($user->cdgo_prfle ?? session(SessionKeys::PERFIL_ID, 1));
            $nombreCompania = (string) session(SessionKeys::COMPANIA_NOMBRE, '');
            $nombreCargo = (string) session(SessionKeys::CARGO_NOMBRE, '');
            $nombreArea = (string) session(SessionKeys::AREA_NOMBRE, '');
            $cargosAsignados = (array) session('sigtram_cargos_asignados', [$idCargo]);
            $permisos = (array) session('sigtram_permisos', []);

            return new ContextoUsuarioAutenticadoDto(
                idLogin: $idLogin,
                idUsuario: $idUsuario,
                nombreLogin: $nombreLogin,
                idPerfil: $idPerfil,
                idCompania: $idCompania,
                idCargo: $idCargo,
                idArea: $idArea,
                cargosAsignados: $cargosAsignados,
                permisos: $permisos,
                roles: [$idPerfil],
                esContabilidad: ($idArea === 21),
                nombreCompania: $nombreCompania,
                nombreCargo: $nombreCargo,
                nombreArea: $nombreArea
            );
        }

        return $this->repository->validarYObtenerContexto($idLogin, $idCompania, $idCargo);
    }

    /**
     * {@inheritdoc}
     */
    public function resolverContextoObligatorio(?int $idLogin = null, ?int $idCompania = null, ?int $idCargo = null): ContextoUsuarioAutenticadoDto
    {
        $contexto = $this->obtenerContextoActivo($idLogin, $idCompania, $idCargo);
        if ($contexto === null) {
            $user = Auth::user();
            if (!$user && ($idLogin === null)) {
                throw ContextoInvalidoException::sinAutenticacion();
            }
            $comp = $idCompania ?? (int) session(SessionKeys::COMPANIA_ID, 0);
            if ($comp <= 0) {
                throw ContextoInvalidoException::companiaInvalida($comp);
            }
            $crgo = $idCargo ?? (int) session(SessionKeys::CARGO_ID, 0);
            if ($crgo <= 0) {
                throw ContextoInvalidoException::cargoInvalido($crgo);
            }
            throw ContextoInvalidoException::contextoIncoherente("No existe una asignación activa y válida para login {$idLogin}, compañía {$comp} y cargo {$crgo}.");
        }

        return $contexto;
    }

    /**
     * {@inheritdoc}
     */
    public function establecerContexto(int $idCompania, int $idCargo): ContextoUsuarioAutenticadoDto
    {
        $idLogin = (int) Auth::id();
        if ($idLogin <= 0) {
            throw ContextoInvalidoException::sinAutenticacion();
        }

        if ($idCompania <= 0) {
            throw ContextoInvalidoException::companiaInvalida($idCompania);
        }

        if ($idCargo <= 0) {
            throw ContextoInvalidoException::cargoInvalido($idCargo);
        }

        $dto = $this->repository->validarYObtenerContexto($idLogin, $idCompania, $idCargo);
        if ($dto === null) {
            throw ContextoInvalidoException::contextoIncoherente(
                "La combinación de compañía {$idCompania} y cargo {$idCargo} no está autorizada para el login {$idLogin}."
            );
        }

        // Persistir exclusivamente en claves de sesión canónicas únicas
        session()->put(SessionKeys::LOGIN_ID, $dto->idLogin);
        session()->put(SessionKeys::USUARIO_ID, $dto->idUsuario);
        session()->put(SessionKeys::LOGIN_NOMBRE, $dto->nombreLogin);
        session()->put(SessionKeys::PERFIL_ID, $dto->idPerfil);
        session()->put(SessionKeys::COMPANIA_ID, $dto->idCompania);
        session()->put(SessionKeys::COMPANIA_NOMBRE, $dto->nombreCompania);
        session()->put(SessionKeys::CARGO_ID, $dto->idCargo);
        session()->put(SessionKeys::CARGO_NOMBRE, $dto->nombreCargo);
        session()->put(SessionKeys::AREA_ID, $dto->idArea);
        session()->put(SessionKeys::AREA_NOMBRE, $dto->nombreArea);

        return $dto;
    }

    /**
     * {@inheritdoc}
     */
    public function limpiarContexto(): void
    {
        session()->forget([
            SessionKeys::LOGIN_ID,
            SessionKeys::USUARIO_ID,
            SessionKeys::LOGIN_NOMBRE,
            SessionKeys::PERFIL_ID,
            SessionKeys::COMPANIA_ID,
            SessionKeys::COMPANIA_NOMBRE,
            SessionKeys::CARGO_ID,
            SessionKeys::CARGO_NOMBRE,
            SessionKeys::AREA_ID,
            SessionKeys::AREA_NOMBRE,
        ]);
    }
}
