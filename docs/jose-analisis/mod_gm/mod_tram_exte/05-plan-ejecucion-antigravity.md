# Plan granular ejecutable por Antigravity IDE — Trámite Externo

Este documento es el handoff operativo para implementar `mod_tram_exte` en `codex/back`. Antigravity debe ejecutar **una sola unidad por vez**, actualizar el ODD con evidencia y detenerse cuando encuentre una decisión o intervención manual. La base de datos queda fuera de su autoridad.

## Inicio rápido

1. Abrir `C:/laragon/www/migracion_sigtram/sigtram_v3` y confirmar `codex/back` → `origin/codex/back`.
2. Leer, en este orden: `.agents/AGENTS.md`, este archivo, `odd-task.md`, `README.md`, las specs `01`–`04` y `prompt_migra.md`.
3. Ejecutar únicamente la primera unidad con estado `READY` cuya dependencia esté satisfecha.
4. Actualizar `odd-task.md` con archivos, comandos, resultados y bloqueos.
5. Detenerse. No iniciar automáticamente la siguiente unidad, no hacer commit y no hacer push sin autorización humana.

## Regla P0 — Esquema y datos fuera de alcance

Esta migración adapta la funcionalidad Laravel al esquema existente. **No incluye cambios de esquema, catálogos ni datos.** Antigravity, subagentes, scripts y pruebas automatizadas tienen prohibido conectarse o ejecutar operaciones contra cualquier PostgreSQL configurado en `.env`, `env` u otra fuente.

Está prohibido ejecutar:

- `php artisan migrate`, `migrate:*`, `db:*`, `schema:*`, `db:seed` o Tinker con consultas;
- `psql`, DBeaver, clientes SQL, PDO/DB/Eloquent contra una base real;
- Pest/ PHPUnit Feature que use la conexión PostgreSQL de `.env`;
- DDL, DML, funciones, secuencias, triggers, índices o `SELECT` de inspección sobre la base;
- pruebas que seleccionen usuarios/expedientes activos o creen datos temporales en la base configurada.

Sí se permite:

- inspección estática de código, SQL legacy, modelos y documentación;
- Unit Tests sin conexión, con puertos/repositorios mockeados;
- build frontend, lint, PHPStan y pruebas puramente aisladas;
- diseñar código y pruebas unitarias contra contratos/repositorios mockeados.

### Tratamiento de una necesidad DB inesperada

Si una unidad parece necesitar una secuencia, constraint, índice, tabla, columna, catálogo o corrección de datos:

1. No crear migraciones ni archivos SQL.
2. Registrar en el ODD la necesidad, evidencia estática, impacto y alternativa sin cambio DB.
3. Marcar la unidad `BLOCKED_USER_DB` y detenerse.
4. Esperar una solicitud posterior y explícita del usuario antes de preparar cualquier script manual.

Las reglas globales DB-01/DB-04 de `.agents/AGENTS.md` no autorizan cambios de base dentro de este encargo; sólo aplicarían en una tarea DB separada solicitada por el usuario.

## Límites de alcance

| Permitido | Prohibido |
|---|---|
| Código y documentación de `mod_tram_exte` | Cambios en módulos no relacionados |
| Tests unitarios con dobles/mocks | Ejecutar tests que usen `.env` PostgreSQL |
| Código compatible con el esquema actual | Migraciones Laravel, scripts o ejecución SQL |
| Cambios mínimos y reversibles | Reescritura masiva sin prueba RED |
| Mantener cambios locales existentes | Descartar, resetear o sobrescribir trabajo ajeno |
| Registrar evidencia en ODD | Declarar paridad sin evidencia |

Cambios locales preexistentes en `mod_tram_inter`, `routes/web.php`, `docs/sincronizacion_base_datos.md`, `env` y `docs/gentle-ai/` se consideran ajenos a estas unidades y deben preservarse.

## Puerta obligatoria de higiene, seguridad y código limpio

Cada unidad debe superar esta puerta antes de pasar a `VERIFY` o `DONE`.

### Cero archivos basura

- No crear copias `*.bak`, `*.old`, `*.orig`, `*.tmp`, `*~`, dumps, exports, screenshots temporales, logs o archivos de depuración dentro del repositorio.
- No versionar `.env`, `env`, credenciales, PII, adjuntos reales, `vendor/`, `node_modules/`, caches, coverage, artefactos Vite ni PDFs/ZIP generados.
- No crear variantes como `ControllerNuevo`, `final_v2`, `prueba`, `copia` o vistas duplicadas. Modificar el artefacto canónico o extraer un componente con nombre de dominio.
- No introducir dependencias, assets, documentos o scripts que no tengan consumidor y criterio de aceptación en la unidad activa.
- Los temporales de runtime deben usar storage temporal, nombre impredecible y limpieza garantizada en `finally`/job de expiración.

### Seguridad verificable

- Revisar autenticación, Policy/Gate por recurso, compañía/cargo/área, CSRF, validación, rate limit e idempotencia según la acción.
- Probar negativamente IDOR, XSS, traversal, upload, doble envío y transición inválida cuando apliquen.
- No exponer excepciones, paths, SQL, credenciales, PII ni URLs públicas de adjuntos.
- No copiar una vulnerabilidad legacy para obtener paridad visual o funcional.
- No declarar un control como validado sin prueba o evidencia reproducible.

### Código limpio y arquitectura

- Controladores delgados; negocio en Service; queries complejas en Repository/Contract; entradas en FormRequest; acceso en Policy.
- `declare(strict_types=1)`, tipos explícitos, PHPDoc público obligatorio y `SessionKeys` en código PHP nuevo/modificado.
- Evitar números mágicos, duplicación, métodos muertos, SQL en Blade, `DB::raw()` con variables, correo síncrono y rutas hardcodeadas.
- Corregir la causa raíz; prohibido silenciar PHPStan, crear baseline, usar ignores o debilitar tipos para ocultar errores.
- Mantener el cambio mínimo: no reformatear ni refactorizar archivos fuera de la unidad.

### Revisión del diff

Antes de cerrar la unidad, Antigravity debe:

1. Ejecutar `git status --short` y clasificar cada archivo como preexistente, requerido por la unidad o inesperado.
2. Ejecutar `git diff --check` y revisar `git diff --stat` y el diff completo de sus archivos.
3. Eliminar únicamente artefactos inesperados creados por esa unidad; nunca borrar archivos preexistentes o del usuario.
4. Ejecutar los gates permitidos sin DB: lint, Unit Tests aislados, PHPStan dirigido, build y auditoría cuando correspondan.
5. Registrar en ODD archivos, pruebas, resultados, riesgos y cualquier gate no ejecutado.

La presencia de un archivo inesperado, un secreto, una vulnerabilidad P0/P1 nueva, un error de sintaxis o una conexión DB deja la unidad en `BLOCKED`/`FAIL`; nunca en `DONE`.

## Estados de unidad

| Estado | Significado |
|---|---|
| `READY` | Puede comenzar sin decisión adicional |
| `IN_PROGRESS` | Única unidad actualmente abierta |
| `BLOCKED_USER_DB` | La solución parece requerir DB; fuera de alcance y pendiente de decisión del usuario |
| `BLOCKED_DECISION` | Requiere decisión funcional/arquitectónica |
| `VERIFY` | Implementada; falta evidencia completa |
| `READY_FOR_REVIEW` | Antigravity terminó; pendiente de auditoría independiente usuario/Codex |
| `DONE` | Criterios, pruebas permitidas y evidencia satisfechos |

## Secuencia de ejecución

| Orden | Unidad | Defectos/contrato | Estado inicial | Dependencia |
|---:|---|---|---|---|
| 00 | Congelar alcance y proteger entorno | Gobernanza | `READY` | Ninguna |
| 01 | Sanear dependencias y toolchain | TE-028 | `BLOCKED_DECISION` | Autorización para lockfile |
| 02 | Aislar el harness de pruebas | TE-027 | `READY` después de 00 | Ninguna DB |
| 03 | Contratos de dominio y máquina de estados | TE-004, TE-024, TE-026 | `BLOCKED_DECISION` | Estado 3/26 e `in_esta` |
| 04 | Autorización por recurso | TE-001, TE-043 | `BLOCKED_DECISION` | Unidad 03 |
| 05 | XSS, errores HTTP y salida segura | TE-003, TE-023, TE-030, TE-041 | `BLOCKED_DECISION` | Unidad 02 |
| 06 | Idempotencia, locks y correlativos | TE-005, TE-020 | `BLOCKED_DECISION` | Unidades 03–04 |
| 07 | Custodia y descarga de adjuntos | TE-002, TE-006 | `BLOCKED_DECISION` | Unidad 04 |
| 08 | Capas Service/Repository/Requests | TE-019, TE-021, TE-022, TE-036, TE-037 | `BLOCKED_DECISION` | Unidades 03–07 |
| 09 | Entrada y Recepcionar | Contrato bandejas | `BLOCKED_DECISION` | Unidad 08 |
| 10 | Recibidos: Derivar/Asignar/Enviar/Notificar | TE-018 | `BLOCKED_DECISION` | Unidad 09 |
| 11 | Salida y Devolver | TE-015 | `BLOCKED_DECISION` | Unidad 10 |
| 12 | Finalizados/Archivo/Notificados/Préstamos/Inventario | TE-016, TE-017, TE-019, TE-025, TE-026 | `BLOCKED_DECISION` | Unidad 11 |
| 13 | Redirigir, anexo y hoja de ruta | TE-044 | `BLOCKED_DECISION` | Unidad 08 |
| 14 | Histórico U accesible | HIST-001, TE-011 | `BLOCKED_DECISION` | Unidades 04, 08 |
| 15 | Histórico A/D y descarga consolidada | HIST-001, TE-012, TE-013, TE-014 | `BLOCKED_DECISION` | Unidades 07, 14 |
| 16 | UX, accesibilidad, rutas y assets | TE-029, TE-031, TE-032, TE-033, TE-034, TE-035, TE-045 | `BLOCKED_DECISION` | Flujos funcionales |
| 17 | Contención legacy | TE-007, TE-008, TE-009, TE-010, TE-038, TE-039, TE-040, TE-042 | `BLOCKED_DECISION` | Plan de corte legacy |
| 18 | Certificación RDD | Todos | `BLOCKED_DECISION` | 00–17 terminadas |

## Unidades de trabajo

### AG-00 — Congelar alcance y proteger entorno

**Objetivo:** obtener un baseline recuperable sin modificar producción ni base.

- Confirmar rama/upstream y registrar HEAD en `odd-task.md`.
- Registrar archivos modificados/no versionados sin alterarlos.
- Verificar que TE-001…TE-045 y HIST-001 estén asignados en este plan.
- Incorporar la regla de base manual a cualquier prompt derivado.

**Aceptación:** cero archivos productivos modificados; inventario y HEAD documentados; prohibición DB visible. **Evidencia:** comandos Git de solo lectura y diff documental.

### AG-01 — Sanear dependencias y toolchain

**Objetivo:** cerrar advisories sin actualización masiva.

- Aplicar solamente tras autorización la candidata simulada de 7 upgrades: Dompdf 3.1.6, Guzzle 7.15.2, Promises 2.5.3, PSR-7 2.13.1, Laravel 13.30.0, CommonMark 2.10.3 y Flysystem 3.35.3.
- Reemplazar `nunomaduro/larastan` por `larastan/larastan` en una unidad separable.
- Ejecutar `composer validate`, `composer audit`, PHPStan y build; no ejecutar tests DB.

**Aceptación:** lockfile reproducible, auditoría sin advisories conocidos, build verde y cambios explicados. **Stop:** cualquier salto mayor adicional o más de las 7 actualizaciones acordadas.

### AG-02 — Aislar el harness de pruebas

**Objetivo:** impedir que la suite toque una conexión real por accidente.

- Añadir guard que aborte pruebas DB si el entorno no es `testing` o la conexión no está explícitamente permitida.
- Convertir reglas de negocio posibles a Unit Tests con repositorios/puertos mockeados.
- Marcar Feature DB como excluidas hasta que el usuario provea entorno desechable.
- Eliminar auto-skip y selección de usuarios activos como estrategia de prueba.

**Aceptación:** los tests unitarios corren sin conexión; un intento DB no autorizado falla antes de abrir conexión; no se crea SQLite para simular esquemas PostgreSQL.

### AG-03 — Contratos de dominio y máquina de estados

**Objetivo:** definir, sin persistir aún, estados, vigencia y transiciones legales.

- Crear enums/DTOs y tabla de transición para estados 1, 2, 3, 4, 5, 7, 12, 13, 22–29 y 52.
- Resolver documentalmente estado 3 versus 26 y semántica de `in_esta`.
- Especificar precondición, actor, estado destino y eventos por transición.
- Si parece requerirse normalización/constraint, buscar una alternativa compatible con el esquema actual; si no existe, marcar `BLOCKED_USER_DB` sin crear SQL.

**Aceptación:** Unit Tests happy/error/border sin DB; transición desconocida rechazada; decisión 3/26 aprobada o unidad `BLOCKED_DECISION`.

### AG-04 — Autorización por recurso

**Objetivo:** cerrar IDOR en lecturas, descargas y mutaciones.

- Policy/Gates con compañía, cargo, área, custodia y estado.
- Scopes de Repository que no acepten IDs desnudos fuera del contexto activo.
- FormRequests con `authorize()` real.
- Unit Tests 403/404/409 para otro cargo, compañía, estado y objeto inexistente.

**Aceptación:** ninguna mutación se autoriza por ocultar botones; pruebas negativas sin DB cubren cada acción pública.

### AG-05 — XSS, errores HTTP y salida segura

**Objetivo:** eliminar ejecución DOM y respuestas inseguras.

- Sustituir `x-html` con representación segura; highlighter por nodos o escape probado.
- Cliente HTTP común para 401/403/419/422/409/500 y content type inesperado.
- Mensaje público genérico + correlation ID; detalle únicamente en log.
- `rel="noopener noreferrer"` en aperturas externas.

**Aceptación:** tests con payload HTML, regex inválida y errores HTTP; ninguna respuesta devuelve mensaje interno/stack trace.

### AG-06 — Idempotencia, locks y correlativos

**Objetivo:** una intención produce una sola transición y un correlativo único.

- Guard frontend, `Idempotency-Key` y lock de aplicación.
- Especificar `lockForUpdate` dentro de transacción corta.
- Reutilizar de forma segura el mecanismo de correlativo ya existente; no crear secuencias ni índices.

**Aceptación:** Unit Tests demuestran replay seguro y conflicto de estado sobre un puerto mockeado. **Stop:** si el esquema actual no ofrece un mecanismo atómico, marcar `BLOCKED_USER_DB` sin crear SQL.

### AG-07 — Custodia y descarga de adjuntos

**Objetivo:** adjuntos privados, validados, autorizados y auditables.

- Centralizar en `GestorAdjuntos`; UUID, MIME real, allowlist, tamaño, hash y staging.
- Resolver archivos por ID opaco/relación, nunca por path suministrado.
- Descarga por Laravel después de Policy; contrato de auditoría Fecha/Usuario/IP.
- Si la auditoría necesita una tabla/índice inexistente, marcar `BLOCKED_USER_DB`; no crear SQL.

**Aceptación:** Unit Tests de traversal, MIME falso, extensión doble, IDOR, archivo inexistente y cleanup.

### AG-08 — Extraer arquitectura del módulo

**Objetivo:** controladores delgados y dependencias reemplazables.

- FormRequest → Controller → Service → Contract/Repository.
- Queries de 3+ tablas en Repository; DTOs como frontera.
- SessionKeys para contexto; `strict_types=1` y PHPDoc público.
- Outbox/NotificationCore after-commit; externos mockeados.

**Aceptación:** comportamiento preservado por Unit Tests; sin SQL/files/mail en controladores; no crear abstracciones sin consumidor.

### AG-09 — Entrada y Recepcionar

**Objetivo:** paridad de bandeja Entrada y transición de recepción.

- Filtros, paginación, conteos, selección y acciones conforme a Spec 01.
- Validación/autorización/idempotencia conforme a 03–08.
- Histórico actualizado por contrato, no por acceso DB directo desde UI.

**Aceptación:** escenarios Gherkin de Entrada cubiertos con dobles; evidencia visual cuando la app pueda usar fixtures locales sin DB.

### AG-10 — Recibidos

**Objetivo:** Derivar, Asignar, Enviar, Finalizar y Notificar estado.

- Una acción por endpoint/FormRequest/Service.
- Destinos limitados por contexto activo y estado.
- Notificación desacoplada, reintentos y resultado observable.

**Aceptación:** matriz acción×rol×estado y pruebas negativas; doble clic no duplica transición.

### AG-11 — Salida y Devolver

**Objetivo:** recuperar la transición legacy estado 4 sin replicar sus vulnerabilidades.

- Implementar Devolver/Regresar con motivo obligatorio, Policy y contrato de estado.
- Mantener trazabilidad inmutable; no editar movimientos previos.

**Aceptación:** Unit Tests de actor, estado, motivo, replay y transición inválida; UI solo muestra acción permitida.

### AG-12 — Finalizados, Archivo, Notificados, Préstamos e Inventario

**Objetivo:** cerrar capacidades fragmentadas y custodia física/digital.

- Integrar acciones contextuales sin duplicar queries.
- Unificar Archivar/Inventariar y modelar préstamo/retorno.
- Scope de custodio para prestados; notificación por NotificationCore.
- No crear constraints ni normalizar datos; cualquier necesidad se marca fuera de alcance.

**Aceptación:** matriz de transiciones y permisos completa; sin DML ejecutado por el agente.

### AG-13 — Redirigir, anexos y hoja de ruta

**Objetivo:** preservar historia al cambiar destino y completar acciones auxiliares.

- Redirigir como nuevo movimiento, no sobrescribir el área histórica.
- Anexos por GestorAdjuntos y Policy.
- Hoja de ruta con ruta nombrada y autorización.

**Aceptación:** pruebas de área anterior/nueva, IDOR y archivo; no ajustar datos históricos ni crear SQL.

### AG-14 — Histórico U accesible

**Objetivo:** cumplir la sección U de HIST-001 en toda vista histórica.

- Usuario origen y todos los usuarios destino por movimiento.
- Popover de dos columnas en hover, foco y clic.
- Enter/Espacio abren; Escape cierra; foco retorna al disparador.

**Aceptación:** mismo componente/DTO en bandejas, búsquedas, archivo, reportes y modales; no mostrar solo un destino cuando existan varios.

### AG-15 — Histórico A/D y descarga consolidada

**Objetivo:** completar adjuntos, voucher y descarga total.

- A: archivo privado y trazabilidad Fecha/Usuario/IP.
- D: voucher principal/anexo autorizado por movimiento.
- ZIP con manifiesto y `UNIDO-*.pdf`; reportar no convertibles y limpiar temporales.

**Aceptación:** HIST-001 completo, autorización negativa, auditoría contractual y pruebas de fallos parciales; SQL sólo como paquete manual.

### AG-16 — UX, accesibilidad, rutas y assets

**Objetivo:** paridad visual moderna sin deuda duplicada.

- Dialog accesible, focus trap/restore, Escape y `aria-live`.
- Rutas GET nombradas, URLs con `route()`, Vite y componentes compartidos.
- Resolver `imagen1.jpg`/`imagen2.png`, clon `segi_bandeja`, tutoriales y stub pendiente.
- Paginación servidor; no DataTables jQuery.

**Aceptación:** build verde, teclado completo, responsive, claro/oscuro y capturas comparativas; sin CDN no justificado.

### AG-17 — Contención legacy

**Objetivo:** retirar o aislar rutas legacy peligrosas sin copiar sus defectos.

- Inventario de rutas activas para traversal, upload/RCE, SQLi, CSRF/GET, permisos y XSS.
- Propuesta de bloqueo/retiro y compatibilidad de enlaces residuales.
- Datos/PII fuera de webroot/VCS mediante plan revisado por el usuario.

**Aceptación:** ninguna acción destructiva sobre legacy ni datos; plan de corte, rollback y evidencia estática aprobados.

### AG-18 — Certificación RDD

**Objetivo:** emitir recibo verificable, no una afirmación de confianza.

- Congelar candidato y evaluar Risk, Resilience, Readability y Reliability.
- Reconciliar TE-001…TE-045, HIST-001 y todas las bandejas/botones.
- Ejecutar solo gates sin DB; enumerar como pendientes las verificaciones manuales DB/UI.
- Cualquier necesidad de cambio DB queda fuera de esta entrega y debe permanecer bloqueada.

**Aceptación de Antigravity:** cero P0/P1 declarados abiertos, audit/build/PHPStan/tests autorizados en verde, evidencia visual y recibo RDD candidato. Antigravity termina en `READY_FOR_REVIEW`, nunca se autoaprueba como `DONE`.

## Revisión final independiente — Usuario + Codex

Esta revisión ocurre después de AG-18 y no puede ser sustituida por el reporte de Antigravity.

1. Reconciliar cada unidad AG-00…AG-18 contra su diff, pruebas y evidencia.
2. Confirmar cobertura uno por uno de TE-001…TE-045, HIST-001, bandejas y botones.
3. Auditar seguridad negativa: IDOR, XSS, CSRF, upload, traversal, descarga, concurrencia e información sensible.
4. Revisar arquitectura y código limpio: Controllers, Services, Repositories, Policies, FormRequests, DTOs y duplicación.
5. Revisar higiene del repositorio: ningún archivo basura, secreto, PII, binario o artefacto generado.
6. Confirmar que no existen migraciones, scripts SQL ni cambios de esquema/datos dentro de la entrega.
7. Comparar visualmente legacy ↔ v3, incluido HIST-001, teclado, responsive y claro/oscuro.
8. Ejecutar los gates finales autorizados y emitir nuestro propio recibo RDD.

Sólo el usuario puede aceptar el resultado final. Si encontramos una brecha, se crea una nueva unidad correctiva y el estado vuelve a `IN_PROGRESS`; si todo cumple, el usuario autoriza `DONE`, commit/PR/merge según corresponda.

## Plantilla de reporte por unidad

```markdown
### AG-XX — <nombre>
- Estado: IN_PROGRESS | BLOCKED_USER_DB | BLOCKED_DECISION | VERIFY | READY_FOR_REVIEW | DONE
- Archivos modificados:
- Prueba RED:
- Implementación GREEN:
- Refactor:
- Comandos ejecutados sin DB:
- Resultado:
- SQL entregado y NO ejecutado:
- Riesgos pendientes:
- Próxima unidad habilitada:
```

## Prompt corto para pegar en Antigravity IDE

```text
Trabaja en C:/laragon/www/migracion_sigtram/sigtram_v3 sobre codex/back.
Lee completamente .agents/AGENTS.md y docs/jose-analisis/mod_gm/mod_tram_exte/05-plan-ejecucion-antigravity.md; después lee el ODD y las specs enlazadas.
Ejecuta exactamente una unidad READY, comenzando por AG-00, y actualiza odd-task.md con evidencia.
PROHIBIDO conectarte o ejecutar cualquier comando/consulta contra PostgreSQL o la conexión de .env. No ejecutes migrate, db:*, seed, psql, Tinker DB ni Feature Tests con DB. No crees migraciones ni scripts SQL. Si la implementación parece requerir un cambio DB, documenta la necesidad, marca BLOCKED_USER_DB y detente.
Preserva todos los cambios locales ajenos. No hagas commit ni push sin autorización explícita. No declares paridad sin pruebas. Al terminar AG-18 marca READY_FOR_REVIEW: la aprobación DONE corresponde únicamente al usuario después de la auditoría independiente con Codex.
```

## Próximo paso

Antigravity debe ejecutar solamente **AG-00**. Después debe presentar el diff documental y esperar autorización para AG-01 o AG-02.
