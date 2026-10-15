# CI-03 — Sesión canónica y bridge legacy

## Trabajo

1. Rediseñar `SessionKeys` con constantes inequívocas, de acuerdo con CI-01. Como mínimo deben distinguir:
   - `LOGIN_ID` de `USUARIO_ID`;
   - `LOGIN_NOMBRE`;
   - `PERFIL_ID`;
   - `COMPANIA_ID`/`COMPANIA_NOMBRE`;
   - `CARGO_ID`/`CARGO_NOMBRE`;
   - `AREA_ID`/`AREA_NOMBRE`.
2. No dejar constantes semánticamente falsas como `USER_ID` si en realidad contienen `cdgo_lgin`, ni comentarios como “datos de empresa” si contienen un entero.
3. Centralizar escritura, invalidación y cambio de contexto. `AuthController` orquesta mediante el servicio; no escribe simultáneamente claves canónicas y aliases.
4. Crear `LegacySessionContextBridge` sólo si módulos no migrados todavía requieren `usIds`, `sigtram_cargo_id`, `sigtram_area_id` o `$_SESSION`. El bridge:
   - recibe el DTO canónico;
   - exporta aliases únicamente al entrar en una frontera legacy identificada;
   - nunca se usa como fuente de autorización;
   - queda documentado para eliminación posterior.
5. Al desplegar nuevas claves, invalidar de forma controlada sesiones antiguas y forzar nueva selección; no aceptar dos esquemas indefinidamente.

## Prohibido

- `session('literal')`, `$request->session()->get('literal')` y escrituras duplicadas fuera del bridge;
- cadenas `?? session('usIds')`, `?? $user->id` o inferencias por coincidencia de nombres;
- aliases genéricos `id`, `user_id`, `company_id` para columnas SIGTRAM confirmadas.

## Aceptación

- Una sola fuente canónica de sesión.
- Cero dual-write en autenticación.
- Compatibilidad legacy aislada y testeada.
