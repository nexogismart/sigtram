# CI-04 — Migración de consumidores

## Orden

1. `AuthController` y `SecurityCore`.
2. Trámite externo: Controllers, Services, Policies y Repositories.
3. Trámite interno: Controllers, Services, Policies y Repositories.
4. servicios Core compartidos que consumen identidad/contexto.
5. resto de módulos Laravel: migrar usos simples; si dependen de contrato legacy, usar exclusivamente el bridge.

## Reglas

- Inyectar `ContextoFuncionarioServiceInterface` y consumir el DTO; no volver a resolver sesión en cada método.
- Sustituir nombres ambiguos: una variable llamada `$usuarioId` nunca puede contener `cdgo_lgin`; `$loginId` nunca puede contener `cdgo_usrios`.
- Retirar fallbacks y aliases de `ContextoFuncionarioInternoDto`, `CorreoExternoController`, `RedirigirExpedienteController`, `IngresarTramiteService`, `IngresoExpedienteService`, `NotificarPlataformaService` y demás hallazgos de CI-01.
- No cambiar significado de columnas de auditoría: confirmar cada `cdgo_lgin`, `co_usua_*` y `cdgo_usrios` antes de reemplazar.
- No alterar filtros, estados, transacciones, locks, endpoints ni respuestas.
- No hacer reemplazo global ciego. Migrar por caso de uso con prueba dirigida.

## Guardrails de arquitectura

Agregar pruebas estáticas que fallen si reaparecen en código Laravel migrado:

- claves de sesión literales de contexto;
- aliases legacy fuera de `LegacySessionContextBridge`;
- acceso a `DB`/`Auth`/`Request` desde el DTO;
- consultas de contexto en Controllers;
- fallback de identificadores entre login y usuario;
- compañía/cargo/área fijos.

## Aceptación

`rg` sólo puede encontrar aliases legacy dentro del bridge, fixtures explícitos y documentación histórica.
