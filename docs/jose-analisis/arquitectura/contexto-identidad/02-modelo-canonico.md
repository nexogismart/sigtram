# CI-02 — DTO, Repository y servicio canónicos

## Destino arquitectónico

Crear o consolidar, ajustando namespaces a la organización vigente:

- `app/DTOs/Core/ContextoFuncionarioDto.php`;
- `app/Contracts/Repositories/mod_sistema/ContextoFuncionarioRepositoryInterface.php`;
- `app/Repositories/mod_sistema/ContextoFuncionarioRepository.php`;
- `app/Contracts/Services/ContextoFuncionarioServiceInterface.php`;
- `app/Services/Core/ContextoFuncionarioService.php`;
- excepción de dominio específica si es necesaria;
- bindings en `AppServiceProvider`.

## Reglas

1. El DTO sólo contiene valores tipados: `loginId`, `usuarioId`, `loginNombre`, `perfilId`, `companiaId`, `cargoId`, `areaId` y permisos/roles únicamente si el esquema confirma que pertenecen al contexto. No usa `Auth`, `Request`, `session()`, `DB` ni Repository.
2. El Repository conoce tablas y valida en una consulta coherente que:
   - login y usuario existen y están activos;
   - compañía está activa y asignada al login;
   - cargo está activo y asignado al login;
   - cargo/área pertenecen a la compañía activa;
   - área está activa.
3. El Service lee usuario autenticado y selección canónica de sesión, consulta el Repository y devuelve el DTO o falla cerrado. No reconstruye silenciosamente compañía/cargo/área faltantes.
4. Eliminar la duplicación entre `ContextoFuncionarioDto` externo y `ContextoFuncionarioInternoDto`; dejar wrappers temporales sólo si una fase intermedia los necesita y retirarlos en CI-04.
5. Prohibidos `mixed` y normalizadores tolerantes para datos internos ya tipados. La normalización de input sólo ocurre en la frontera y acepta exclusivamente formas confirmadas.

## Aceptación

- DTO sin dependencias Laravel/BD.
- Controller sin consultas de contexto.
- Service sin nombres de tablas.
- Repository cubierto con pruebas de contrato y Service con happy/error/border.
