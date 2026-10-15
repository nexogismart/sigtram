# SPEC 16-04 — Checklist de ejecución y prompt para Antigravity

## 1. Preflight obligatorio

Antes de editar:

```powershell
git branch --show-current
git status --short
php artisan route:list
php artisan test --testsuite=Unit
npm test
npm run build
git diff --check
```

Registrar resultados en la respuesta final, no en archivos temporales. Si el baseline ya falla, identificar fallos preexistentes y no enmascararlos.

## 2. Puerta por fase

Después de cada REPO-01…REPO-05:

```powershell
php artisan test --testsuite=Unit
git diff --check
```

Ejecutar además pruebas dirigidas de los archivos tocados. No avanzar con rojo.

## 3. Verificación arquitectónica final

Las siguientes búsquedas deben quedar sin acceso a tablas en Controllers:

```powershell
rg -n "DB::|->table\(|ConnectionInterface|Query\\Builder" app/Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php app/Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php app/Http/Controllers/mod_gm/mod_tram_exte/IngresoExpedienteController.php app/Http/Controllers/mod_gm/mod_tram_exte/BuscaExpediController.php
```

En Services, sólo se admite el límite transaccional, no operaciones de tabla:

```powershell
rg -n "DB::table|->table\(|->select\(|->insert\(|->update\(|->delete\(" app/Services/mod_gene/CorreoInternoTransaccionalService.php app/Services/mod_gm/mod_tram_exte/TramiteExternoService.php app/Services/mod_gm/mod_tram_exte/BuscaExpedienteService.php
```

Validar contratos/bindings:

```powershell
rg -n "IngresoExpedienteRepositoryInterface|BuscaExpedienteRepositoryInterface" app/Providers/AppServiceProvider.php app/Contracts/Repositories/mod_gm app/Repositories/mod_gm
```

## 4. Baseline conocido al redactar la SPEC

- rama: `codex/back`;
- árbol de trabajo con numerosos cambios previos que deben preservarse;
- último baseline observado: `439 passed`, `2476 assertions` en Unit;
- JavaScript: `28 passed`;
- build Vite aprobado con dos avisos de imágenes resueltas en runtime;
- `route:list`: 366 rutas.

Antigravity debe volver a medir estos valores. No debe copiar estos números como si fueran la ejecución actual ni considerar regresión un aumento de pruebas/rutas justificado.

## 5. Evidencia final

Entregar:

1. fase REPO-00…REPO-06 con `DONE`, `BLOCKED` o `NOT_APPLICABLE`;
2. archivos creados/modificados y responsabilidad;
3. tabla endpoint → Controller → Service → Repository;
4. métodos Repository añadidos con tablas tocadas;
5. comandos y resultado exacto;
6. accesos DB residuales con justificación, si alguno;
7. confirmación de cero cambios DB, cero conexión PostgreSQL y cero operaciones Git remotas;
8. riesgos/manual pendiente;
9. siguiente SPEC: almacenamiento dual legacy/nuevo y QR, expresamente no implementada aquí.

## 6. Prompt único para copiar y pegar en Antigravity

```text
ACTÚA COMO LEAD LARAVEL ARCHITECT Y EJECUTOR FORENSE DE REFACTOR SEGURO.

Proyecto: C:\laragon\www\migracion_sigtram\sigtram_v3
Rama obligatoria: codex/back

IMPLEMENTA COMPLETAMENTE, EN ORDEN Y SIN IMPROVISAR, LAS SPECS:
1. docs/jose-analisis/arquitectura/16-00-spec-maestro-convergencia-repositories.md
2. docs/jose-analisis/arquitectura/16-01-plan-controladores-correo.md
3. docs/jose-analisis/arquitectura/16-02-plan-ingreso-y-busqueda-expedientes.md
4. docs/jose-analisis/arquitectura/16-03-plan-persistencia-services.md
5. docs/jose-analisis/arquitectura/16-04-checklist-ejecucion-y-prompt-antigravity.md

Lee primero completos esos cinco archivos y .agents/AGENTS.md. Esas specs son la fuente de verdad de esta ejecución; no vuelvas a diseñar la arquitectura ni cambies nombres definidos. Inspecciona la implementación actual de cada consulta antes de moverla y conserva exactamente joins, aliases, filtros, in_esta, scopes, orden, estados, locks, filas afectadas, payloads y respuestas. Primero escribe/ajusta la prueba de caracterización, luego mueve la consulta, luego ejecuta la puerta de pruebas de esa fase.

LÍMITES ABSOLUTOS:
- preserva todo cambio previo del árbol sucio;
- no reset/checkout/restore/clean ni reformateo masivo;
- no commit/push/pull/merge/rebase/PR;
- no conexión a PostgreSQL, SQL manual, migrate, seed o tinker;
- no cambios de esquema, rutas, nombres de rutas, frontend, contratos HTTP o comportamiento funcional;
- no implementes todavía almacenamiento nuevo/legacy, descarga dual ni QR público;
- no inventes tablas, columnas, estados, DTOs genéricos o métodos Repository genéricos;
- Controller no accede a DB; Service orquesta y abre transacción; Repository es el único que conoce tablas/queries/escrituras;
- si faltan tokens o aparece un bloqueo real, termina la fase actual en verde y reporta el siguiente ID; nunca dejes un caso de uso a medias.

Empieza por REPO-00. Ejecuta REPO-01 a REPO-06 secuencialmente. No declares terminado por una búsqueda de texto: exige pruebas conductuales con mocks, bindings resolubles, suite Unit, npm test, npm run build y git diff --check. Al final entrega exactamente la evidencia pedida por la SPEC 16-04, incluyendo cualquier acceso DB residual y toda validación manual pendiente. No afirmes producción ni 100% si no ejecutaste pruebas autenticadas.
```

## 7. Uso recomendado del prompt

No pegar nuevamente todos los prompts históricos. El prompt anterior y estas cinco specs bastan. Los prompts viejos contienen decisiones ya superadas, aumentan contexto y pueden reintroducir nombres inconsistentes.

Si Antigravity se queda sin contexto, iniciar una nueva sesión con el mismo prompt cambiando únicamente:

```text
REANUDA DESDE REPO-XX. Verifica primero el estado y las pruebas dejadas por la sesión anterior; no repitas fases DONE.
```

