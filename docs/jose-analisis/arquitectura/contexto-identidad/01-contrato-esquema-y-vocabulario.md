# CI-01 — Contrato real de esquema y vocabulario

## Trabajo

1. Usar el MCP de sólo lectura **SIGTRAM lectura** para consultar `information_schema` y `SELECT` acotados. Si no está disponible, detener esta fase y reportar `BLOCKED`; no inventar columnas.
2. Confirmar PK, tipos, estados y relaciones de:
   - `p_lgin`, `p_usrios`;
   - `r_cmpnias_lgin`, `a_cmpnias`;
   - `r_crgos_lgin`, `p_crgos`, `a_areas`;
   - tablas reales de perfil/permisos que use `SecurityCore`.
3. Confirmar particularmente dónde vive `cdgo_cmpnia` para usuario, área y cargo; no asumir que todas las tablas lo contienen.
4. Inventariar con `rg` todos los nombres usados para login, usuario, compañía, cargo, área, perfil, roles y permisos en:
   - `app/Support/SessionKeys.php`;
   - `AuthController`, `SecurityCore`, DTOs;
   - Controllers/Services/Repositories de trámite interno y externo;
   - pruebas relacionadas.
5. Clasificar cada hallazgo como: columna BD, propiedad de modelo, propiedad DTO, clave de sesión canónica, alias legacy, campo Request o fallback inválido.
6. Guardar el contrato confirmado en `docs/jose-analisis/arquitectura/contexto-identidad/contrato-confirmado.md`, incluyendo consultas ejecutadas y sin datos personales.

## Aceptación

- Cero nombres inferidos.
- Diferencia explícita entre `loginId` y `usuarioId`.
- Matriz de aliases con destino: conservar, migrar o aislar en bridge.
- Cero escrituras en PostgreSQL y cero cambios funcionales en esta fase.
