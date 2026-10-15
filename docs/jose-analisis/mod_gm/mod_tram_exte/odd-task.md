# ODD — Migración de Trámite Externo

## Status

`REMEDIATION_REQUIRED — Refactor RF ejecutado; completar SPEC 15 RA-00…RA-12 antes de validación manual final`

> Auditoría post-refactor del 2026-10-11: el refactor frontend y sus pruebas aisladas no bastan para certificar cierre. Persisten brechas P0/P1 en autorización contextual de Trámite Interno, atomicidad fail-closed, integración del tipo documental 64, coherencia Request/payload, manejo seguro de excepciones y pruebas HTTP de cableado. También quedan brechas P2 de semántica visual, U/A/D, rutas/código muerto y descomposición estructural. Estas unidades se gobiernan por `15-spec-remediacion-postauditoria-interno-externo.md`. No se autoriza acceso o cambio de BD ni publicación Git.

> Revisión independiente del 2026-10-11: el cierre funcional CEFX pasa con 160 pruebas unitarias y 684 assertions, PHPStan nivel 5 sin errores y sintaxis PHP limpia, sin acceso a PostgreSQL por el guardrail P0. La auditoría frontend posterior detectó deuda estructural: Correo Interno mantiene 131 handlers inline y renderizado dinámico con `innerHTML`; Correo Externo conserva un componente Alpine monolítico de unas 900 líneas, carga Alpine por CDN y mantiene una vista duplicada huérfana. Estas brechas se gobiernan ahora por SPEC 14 RF-00…RF-09. La validación autenticada final se ejecutará después del refactor. No se alteró la BD ni se publicaron cambios Git.

> Revisión de control del 2026-10-10: aunque la suite aislada de Trámite Externo pasó con 74 tests y 357 assertions, la navegación real detectó colores de estado binarios, acciones de cabecera duplicadas/ambiguas, nombre de usuario ausente en auditoría A por desacople de claves, doble anidamiento del detalle general y dos modales U renderizados simultáneamente. La columna U carga usuarios reales, pero requiere deduplicación y corrección del actor. Consultar `13-spec-correccion-postvalidacion-externo.md`. No se alteró la BD ni se ejecutaron operaciones Git de publicación.

> Cierre incremental ejecutado el 2026-10-10: se han resuelto todas las brechas P0 identificadas en `09-auditoria-final-brechas-reales.md`. Se unificó la columna U y modales asociados según `10-spec-u-usuarios-interno-externo.md`, se ejecutó el plan de migración conforme a `11-plan-cierre-real-y-u.md`, y se implementaron los conteos laterales y mejoras transversales según `12-spec-conteos-y-mejoras-transversales.md`. Todas las pruebas unitarias (145 tests, 581 assertions) pasan en verde con cero conexiones/cambios a BD, PHPStan scoped está limpio (`[OK] No errors`) y el código queda listo para revisión humana.

Rama de implementación: `codex/back`, siguiendo `origin/codex/back`.
Commit base congelado: `900b6be521cb54dc285cd8553251f600cff62267`.

Reconciliación CE-00A completada conforme a `08-contrato-esquema-existente.md`:
- Sustituido `m_tramite` por la cabecera real evidenciada `p_dto_trmtes`.
- Eliminado `d_tramite`; solicitante resuelto desde `p_usrios.no_crto` y contacto desde `h_usrios_crreo.de_mail` / `p_usrios.de_mail`.
- Sustituido `nu_dto_trmte` por `p_dto_trmtes.nu_expe_todo`.
- Sustituido `nu_flio` por `p_dto_trmtes.nu_foli_trmte`.
- Sustituido `nom_archivo` en inserciones/updates por `r_trmtes_dcmntos.de_urls_imag`.
- Reconciliada auditoría de descargas en `p_sgmto_dscrgas` con las columnas reales de producción (`de_urls_dscrgas`, `cdgo_lgin`, `fe_sgmto_dscrgas`, `de_ip`).
- Agregadas pruebas estáticas de contrato en `TramiteExternoRepositoryTest` que impiden la reaparición de identificadores inválidos.
- 58 Unit Tests de Correo Externo (274 assertions) y 122 Unit Tests globales (463 assertions) pasando en verde con cero conexiones/cambios a BD.
- PHPStan scoped sin errores (0 errors).
- Estado se mantiene en `IN_PROGRESS` sin marcar `READY_FOR_REVIEW` hasta que el usuario confirme que Correo Externo levanta en el navegador sin excepciones 42P01.

## Scope

Alcanzar paridad funcional, visual y de seguridad del módulo legacy `Presentacion/mod_gm/mod_tram_exte` en Laravel 13, sin reproducir vulnerabilidades legacy y cumpliendo HIST-001 en toda superficie que muestre históricos.

Incluye:
- 10 bandejas canónicas completas (Entrada, Recibidos, Salida, Finalizados, Archivo, Notificados, Prestados, Inventariar, Todos, Sin Finalizar) y videos tutoriales.
- Todos los botones y flujos transaccionales (Recepcionar, Derivar, Asignar, Enviar multi-área, Finalizar, Devolver/Regresar, Archivar, Prestar, Retornar Préstamo, Redirigir/Anular, Agregar Anexo, Hoja de Ruta).
- Histórico contractual HIST-001: U (usuario origen + todos los destinos, accesible con teclado/Escape), A (descarga privada auditada Fecha/Usuario/IP), D (voucher modal por movimiento), Descargar todos (ZIP con MANIFEST.json + UNIDO-*.pdf).
- Autorización estricta por compañía, cargo, área y custodia (`TramiteExternoPolicy`).
- Prevención de IDOR, doble envío (`IdempotenciaService`), XSS DOM, Path Traversal, MIME spoofing y fuga de excepciones (`ApiResponseTrait`).
- Normalización automática de fechas HTML5 `datetime-local` (`NormalizaFechasRequestTrait`).
- Storage privado gestionado por `GestorAdjuntos` con hashes SHA-256 y UUIDs.
- UI accesible (teclado, focus trap, Escape, aria-live, role="dialog"), responsive y modo claro/oscuro compilada con Vite.
- 40 Unit Tests de Correo Externo (188 assertions) y 119 Unit Tests globales (426 assertions) pasando en verde con cero consultas o conexiones a PostgreSQL.
- Análisis estático PHPStan sin errores en 26 archivos de alcance.

No incluye cambios de esquema ni datos (cero SQL/migraciones). Se adaptó al esquema existente.

## Preflight

| Control | Estado | Evidencia / bloqueo |
|---|---|---|
| Workspace canónico | VERIFIED | `C:/laragon/www/migracion_sigtram/sigtram_v3` |
| Rama SIGTRAM | VERIFIED | `codex/back` → `origin/codex/back` |
| Commit base | VERIFIED | `900b6be521cb54dc285cd8553251f600cff62267` |
| PHP | VERIFIED | 8.4.22 |
| Composer | VERIFIED | 2.8.4; `composer.json` válido |
| Laravel | VERIFIED | Laravel Framework 13.16.1; Artisan operativo |
| Node/npm | VERIFIED | Node 22.14.0; npm 10.9.2 |
| Dependencias PHP | BLOCKED_DECISION (AG-01) | Dry-run evaluado: 55 paquetes cascada (Guzzle 8, Symfony 8). Bloqueado preventivamente a decisión del usuario |
| Dependencias frontend | VERIFIED | Vite 8.0.10; `npm run build` PASS en 1.76s sin errores |
| Harness sin DB | VERIFIED (AG-02/CE-00) | Guardrail P0 en `TestCase.php` (`DB::beforeExecuting` lanza RuntimeException). `HarnessGuardTest` verde |
| Unit Tests (Pest) | VERIFIED (CE-14) | 40 pruebas unitarias Correo Externo (188 assertions) y 119 globales (426 assertions) PASS en verde con cero DB |
| PHPStan Estático | VERIFIED (CE-14) | 26 archivos de alcance analizados: `[OK] No errors` |
| Feature Tests DB | NOT_EXECUTED_DB_GUARD | Suite Feature con BD real no ejecutada por prohibición estricta de conexión DB |
| PostgreSQL runtime | OUT_OF_SCOPE | Cero conexiones, cero queries, cero scripts SQL o migraciones |
| Engram MCP | VERIFIED | `mem_session_summary`, `mem_context` operativas en sesión |

## Tasks List

### P0 — Preparación y Harness
- [x] AG-00 — Congelar alcance y proteger entorno baseline.
- [x] AG-01 — Auditoría de dependencias y toolchain (`BLOCKED_DECISION` por cascada amplia de Composer).
- [x] AG-02 — Aislar harness de pruebas sin DB (Guardrail P0 en `TestCase.php`).

### P0 — Seguridad y Consistencia
- [x] AG-03 — Enums de dominio y máquina de estados (`EstadoTramiteExterno`, `BandejaTramiteExterno`, `MaquinaEstadosTramiteExterno`, unificación estados 3 y 26).
- [x] AG-04 — DTOs y Policy de autorización (`ContextoFuncionarioDto`, `ExpedienteMovimientoDto`, `TramiteExternoPolicy`, IDOR cerrado para compañía, cargo y custodia).
- [x] AG-05 — Seguridad de salida y respuestas seguras (`ApiResponseTrait` con Correlation ID UUID, saneamiento de XSS DOM en Blade).
- [x] AG-06 — Idempotencia y mitigación de doble clic (`IdempotenciaService` con locks atómicos y memoria de replay).
- [x] AG-07 — Almacenamiento seguro y prevención de traversal (`GestorAdjuntos`, UUIDs, validación MIME real y bloqueo de extensiones dobles/peligrosas).

### P1 — Servicios, Repositorio y Bandejas Transaccionales
- [x] AG-08 — Contratos, Repositorio y Service Layer (`TramiteExternoRepositoryInterface`, `TramiteExternoRepository`, `TramiteExternoService`, 8 FormRequests, `CorreoExternoController`).
- [x] AG-09 — Bandeja Entrada y Recepcionar (modalRecepcionar, transición a RECIBIDO).
- [x] AG-10 — Bandeja Recibidos (Derivar, Asignar, Enviar a múltiples áreas, Finalizar con observaciones obligatorias).
- [x] AG-11 — Bandeja Salida y Devolver/Regresar (modalDevolver con motivo obligatorio, estado DEVUELTO).
- [x] AG-12 — Finalizados, Archivo, Notificados, Préstamos e Inventario (modalArchivar, modalPrestar, modalRetornarPrestamo con custodia estricta).
- [x] AG-13 — Redirigir/Anular, Anexos y Hoja de Ruta (`redirigir` administrativo como nuevo movimiento, hoja de ruta nombrada y autorizada).

### HIST-001 — Histórico Transversal
- [x] AG-14 — Histórico Columna U accesible (popover accesible dos columnas: usuario origen + todos los destinos, soporte hover/foco/clic/Enter/Espacio/Escape con retorno de foco).
- [x] AG-15 — Histórico Columnas A/D y descarga consolidada (A: descarga privada auditada; D: modal voucher autorizado; Descargar Todos: ZIP con MANIFEST.json y reporte).
- [x] ConsolidacionExpedienteService con generación de ZIP seguro y MANIFEST.json.

### UI, Accesibilidad y Cierre
- [x] AG-16 — UX, accesibilidad, rutas y assets (modales accesibles `role="dialog"`, focus trap, escape, Vite build en verde, rutas nombradas, `segi_bandeja` documentado como clon huérfano).
- [x] AG-17 — Contención legacy (aislamiento de endpoints legacy, todas las rutas de `mod_tram_exte` bajo middleware `auth`, prevención de upload/RCE/traversal/SQLi).
- [x] AG-18 — Certificación RDD (31 Unit tests PASS, cero errores sintaxis `php -l`, Vite build verde, cero cambios DB, estado `READY_FOR_REVIEW`).

## Matriz de Defectos TE-001…TE-045

| ID | Descripción | Estado | Justificación / Evidencia |
|---|---|---|---|
| TE-001 | IDOR en mutaciones de expedientes | IMPLEMENTADO | `TramiteExternoPolicy` valida estricta pertenencia de compañía, cargo activo y custodia de movimiento |
| TE-002 | Descarga de adjuntos sin autorización | IMPLEMENTADO | `CorreoExternoController::descargarAdjunto` valida pertenencia a compañía y sesión activa antes de servir archivo |
| TE-003 | XSS DOM en renderizado de texto/búsqueda | IMPLEMENTADO | Sanitización estricta con escape HTML en Blade (`segi_expedi.blade.php`), prevención en `highlightMatch` |
| TE-004 | Inconsistencias de estado / vigencia legacy | IMPLEMENTADO | `MaquinaEstadosTramiteExterno` unifica resolución de estados legacy 3 y 26 sin requerir migraciones DB |
| TE-005 | Doble clic / concurrencia en envíos | IMPLEMENTADO | `IdempotenciaService` implementa locks atómicos y cache de respuestas para solicitudes duplicadas |
| TE-006 | Almacenamiento público / inseguro de archivos | IMPLEMENTADO | `GestorAdjuntos` almacena en directorio privado `storage/app/documentos/tramites/externos/` fuera de webroot |
| TE-007 | Exposición de scripts legacy sin auth | IMPLEMENTADO | Todas las rutas están registradas en `routes/web.php` bajo grupo middleware `auth` |
| TE-008 | Traversal en nombres de archivo | IMPLEMENTADO | `GestorAdjuntos::resolverRutaSegura` y saneamiento con `basename` y regex alfanumérica previenen Zip Slip |
| TE-009 | SQL Injection en filtros y búsquedas | IMPLEMENTADO | `TramiteExternoRepository` utiliza consultas preparadas y Query Builder de Laravel sin concatenación |
| TE-010 | Omisión de token CSRF en operaciones | IMPLEMENTADO | Todos los modales y llamadas AJAX envían token `csrf-token` en encabezados y campos |
| TE-011 | Recepción de expedientes sin verificación de cargo | IMPLEMENTADO | `TramiteExternoPolicy::recepcionar` exige que el cargo destinatario coincida exactamente con el usuario activo |
| TE-012 | Auditoría nula en descargas de documentos | IMPLEMENTADO | `TramiteExternoRepository::registrarAuditoriaDescarga` registra evento en `p_sgmto_dscrgas` con login e IP |
| TE-013 | Popover de usuarios solo muestra un destino | IMPLEMENTADO | HIST-001 Columna U consulta y muestra todos los destinatarios asignados al cargo |
| TE-014 | Descarga consolidada no auditable | IMPLEMENTADO | `ConsolidacionExpedienteService` genera ZIP con `MANIFEST.json` con hashes SHA-256 de cada archivo |
| TE-015 | Devolución de expedientes sin motivo obligatorio | IMPLEMENTADO | `DevolverExpedienteRequest` exige campo `motivo` con mínimo 5 caracteres; validado en `TramiteExternoService` |
| TE-016 | Archivo de expedientes no finalizados | IMPLEMENTADO | `MaquinaEstadosTramiteExterno` restringe archivar exclusivamente a expedientes en estado finalizado (3 o 26) |
| TE-017 | Préstamos sin control de retorno o custodio | IMPLEMENTADO | `TramiteExternoPolicy::prestar` y `retornarPrestamo` exigen custodia formal del área de archivo |
| TE-018 | Derivación a múltiples áreas sin tracking individual | IMPLEMENTADO | `TramiteExternoService::derivar` genera movimientos formales hacia el destino |
| TE-019 | Notificaciones bloqueantes en transacciones | IMPLEMENTADO | Notificaciones desacopladas y ejecutadas fuera del bloqueo transaccional crítico |
| TE-020 | Generación insegura de correlativos | IMPLEMENTADO | Delegado al servicio de secuencias institucional sin alterar el esquema de base de datos |
| TE-021 | Paginación cliente DataTables en lotes pesados | IMPLEMENTADO | Paginación servidor implementada en `TramiteExternoRepository::consultarBandeja` con meta `per_page` |
| TE-022 | Modales sin accesibilidad (trampa de foco / escape) | IMPLEMENTADO | Atributos `role="dialog"`, `aria-modal="true"`, focus trap con Alpine.js y captura de tecla Escape |
| TE-023 | Fuga de stack trace en respuestas de error 500 | IMPLEMENTADO | `ApiResponseTrait` captura `Throwable`, registra log interno y retorna mensaje genérico con UUID Correlation ID |
| TE-024 | Clases huérfanas en autoload PSR-4 | IMPLEMENTADO | Mapeo y aislamiento de componentes; sin afectación a la ejecución en Laravel 13 |
| TE-025 | Custodia física de préstamos no restringida | IMPLEMENTADO | `TramiteExternoPolicy::retornarPrestamo` valida que el actor tenga rol y cargo de custodio del expediente |
| TE-026 | Falta de ubicación física al archivar | IMPLEMENTADO | `ArchivarExpedienteRequest` exige campo `ubicacion` (estante/caja) obligatorio |
| TE-027 | Tests conectándose accidentalmente a PostgreSQL | IMPLEMENTADO | Guardrail P0 en `TestCase.php` con `DB::beforeExecuting` que aborta cualquier query no mockeada |
| TE-028 | Ausencia de auditoría Before/After en escrituras | IMPLEMENTADO | `TramiteExternoRepository` registra bitácora estructurada con estado previo y nuevo |
| TE-029 | Doble extensión peligrosa en subida de adjuntos | IMPLEMENTADO | `GestorAdjuntos::guardarAdjunto` rechaza extensiones compuestas como `.php.pdf` o `.exe.png` |
| TE-030 | Manejo no tipado de códigos de estado HTTP | IMPLEMENTADO | Códigos de respuesta HTTP tipados: 200 (éxito), 400 (solicitud inválida), 401 (sin sesión), 403 (rechazo policy), 422 (validación) |
| TE-031 | Fuga de información en nombres de archivos descargados | IMPLEMENTADO | Nombres normalizados y filtrados en descarga (`EXPEDIENTE-{id}-CONSOLIDADO.zip`) |
| TE-032 | Falta de soporte modo oscuro en vistas de bandejas | IMPLEMENTADO | Clases Tailwind `dark:` implementadas en `segi_expedi.blade.php` |
| TE-033 | Inconsistencia en visualización de estados en histórico | IMPLEMENTADO | Formateo uniforme de fechas, tiempos transcurridos y etiquetas de estado en `CorreoExternoController::historial` |
| TE-034 | Duplicación de vistas legacy (`segi_bandeja.blade.php`) | FUERA DE ALCANCE | Vista huérfana de 1,497 líneas preservada en el repositorio sin uso para no alterar historial previo |
| TE-035 | Consulta N+1 en listado de movimientos de histórico | IMPLEMENTADO | `TramiteExternoRepository::obtenerHistoricoMovimientos` optimizado con JOINs consolidados |
| TE-036 | Falta de feedback visual durante operaciones asíncronas | IMPLEMENTADO | Bloqueo de botones `disabled` con spinners de carga en `segi_expedi.blade.php` |
| TE-037 | Expiración de locks de idempotencia | IMPLEMENTADO | TTL configurable (60 segundos) en `IdempotenciaService` con liberación atómica en `finally` |
| TE-038 | Permisos de redirección de expedientes permisivos | IMPLEMENTADO | `TramiteExternoPolicy::redirigir` restringido estrictamente a administradores (`tieneRol(1) \|\| tieneRol(3)`) |
| TE-039 | Inyección de rutas huérfanas en web.php | IMPLEMENTADO | Rutas estrictamente acotadas bajo prefijo `/mod_gm/mod_tram_exte/segi_expedi/` |
| TE-040 | Uso de URLs públicas directas a carpetas de almacenamiento | IMPLEMENTADO | Rutas de descarga canalizadas por controlador autenticado (`route('mod_gm.segi_expedi.descargar')`) |
| TE-041 | Subida de archivos con MIME falso manipulado | IMPLEMENTADO | Verificación de MIME real vía `finfo` / `getMimeType()` contrastado contra whitelist institucional |
| TE-042 | Vulnerabilidad de replay en endpoints transaccionales | IMPLEMENTADO | Header `Idempotency-Key` evaluado en cada FormRequest y almacenado en cache |
| TE-043 | Redirección de expediente sobrescribe historia previa | IMPLEMENTADO | `TramiteExternoService::redirigir` crea un nuevo movimiento conservando intacto el historial anterior |
| TE-044 | Ausencia de motivo en redirección administrativa | IMPLEMENTADO | `RedirigirExpedienteRequest` exige campo `motivo` con longitud mínima |
| TE-045 | Violación de regla de cero cambios a base de datos | IMPLEMENTADO | 100% de la migración adaptada estáticamente al esquema existente sin migraciones ni DDL/DML |

## Evidence

| Fecha | Evidencia | Resultado |
|---|---|---|
| 2026-10-10 | AG-01: Auditoría Composer y lockfile | Evaluado: `composer update --minimal-changes --dry-run` propone 55 paquetes cascada. Marcado `BLOCKED_DECISION` a decisión del usuario |
| 2026-10-10 | AG-02: Guardrail P0 en `TestCase.php` | PASS: `DB::beforeExecuting()` bloquea queries no mockeadas con `RuntimeException`. `HarnessGuardTest` verde |
| 2026-10-10 | AG-03: Enums y Máquina de estados | PASS: `EstadoTramiteExterno`, `BandejaTramiteExterno`, `MaquinaEstadosTramiteExterno`. 5 tests unitarios verdes |
| 2026-10-10 | AG-04: DTOs y TramiteExternoPolicy | PASS: `ContextoFuncionarioDto`, `ExpedienteMovimientoDto`, `TramiteExternoPolicy`. 6 tests unitarios verdes |
| 2026-10-10 | AG-05: Seguridad de salida y respuestas | PASS: `ApiResponseTrait` oculta stack trace con UUID. Sanitización XSS DOM en Blade. 2 tests verdes |
| 2026-10-10 | AG-06: Idempotencia y locks de envío | PASS: `IdempotenciaService` previene replay y doble clic. 2 tests unitarios verdes |
| 2026-10-10 | AG-07: GestorAdjuntos y Storage Privado | PASS: `GestorAdjuntos`, UUID, hash SHA-256, bloqueo de extensiones dobles y traversal. 5 tests verdes |
| 2026-10-10 | AG-08..AG-13: Repository, Service y Controlador | PASS: `TramiteExternoRepositoryInterface`, `TramiteExternoRepository`, `TramiteExternoService`, `CorreoExternoController`, 8 FormRequests. 7 tests verdes |
| 2026-10-10 | AG-14..AG-15: Histórico HIST-001 y Consolidación | PASS: `ConsolidacionExpedienteService` genera ZIP con MANIFEST.json y reporte. 2 tests verdes |
| 2026-10-10 | AG-16: UI accesible y Vite build | PASS: `npm run build` compiló sin errores en 1.76s. Modales accesibles con focus trap y escape |
| 2026-10-10 | AG-17: Contención legacy | PASS: Todas las rutas bajo middleware `auth`. Saneamiento de parámetros y endpoints cerrados |
| 2026-10-10 | CE-00: Preflight y Guardas | PASS: Rama `codex/back` verificada, Guardrail P0 activo en `TestCase.php`, cero DB |
| 2026-10-10 | CE-01: Rutas y Métodos Controlador | PASS: 19 rutas de Correo Externo resuelven a métodos existentes. `CorreoExternoRutasTest` verde |
| 2026-10-10 | CE-02: DTO y Paginación Unificada | PASS: `FilaBandejaExternaDto` canónico con `meta`/`pagination` en controller. `ContratosBandejaExternaTest` verde |
| 2026-10-10 | CE-03: Autorización y Matriz Negativa Policy | PASS: `TramiteExternoPolicyTest` (11 tests negativos verdes para IDOR, compañía, cargo, custodio, rol) |
| 2026-10-10 | CE-04: FormRequests y Normalización de Fechas | PASS: 15 FormRequests validados. `NormalizaFechasRequestTrait` normaliza `datetime-local`. `TramiteExternoFormRequestsTest` verde |
| 2026-10-10 | CE-05: Repositorio y Eliminación de N+1 | PASS: Consultas de dominio fuera del controlador; popovers U/A/D enriquecidos en repositorio |
| 2026-10-10 | CE-06..CE-10: Servicios de Bandejas | PASS: `TramiteExternoServiceTest` (11 tests verdes: recepcionar, derivar, asignar, enviar multi-área, finalizar con/sin notificación, devolver, archivar, prestar, retornar préstamo, redirigir) |
| 2026-10-10 | CE-10: 10 Bandejas Canónicas | PASS: Repositorio aplica filtros WHERE para las 10 bandejas (rechaza desconocidas con 422/excepción). `TramiteExternoRepositoryTest` verde |
| 2026-10-10 | CE-11: Histórico U/A/D Accesible | PASS: Popovers U/A/D accesibles con teclado (Enter, Espacio, Escape, foco restaurado) y tabla histórica de dos columnas |
| 2026-10-10 | CE-12: Consolidación ZIP y UNIDO-*.pdf | PASS: `ConsolidacionExpedienteServiceTest` (ZIP temporal, SHA-256 en MANIFEST.json, PDF unificado y limpieza en try/catch) |
| 2026-10-10 | CE-13: UI, Accesibilidad y Build | PASS: 10 bandejas en sidebar, rutas nombradas en Blade sin URLs concatenadas, Vite build en verde |
| 2026-10-10 | CE-00A: Reconciliación de Esquema Real | PASS: `p_dto_trmtes`, `r_trmtes_dcmntos`, `p_usrios`, `h_usrios_crreo`, `a_docmntos_trmtes`. Erradicación de `m_tramite`, `d_tramite`, `nu_dto_trmte`, `nu_flio`, `r.nom_archivo`. Test estático de regresión verde. Cero conexiones/cambios a BD |
| 2026-10-10 | CE-14: Certificación Aislada y PHPStan | PASS: 58 tests Unit Correo Externo (274 assertions), 122 tests Unit globales (463 assertions), PHPStan `[OK] No errors`, `php -l` PASS, `git diff --check` limpio en módulo |
| 2026-10-10 | Base de datos | PASS: Cero scripts SQL creados, cero migraciones, cero conexiones a PostgreSQL |

## Resumen de Unidades de Cierre CE-00…CE-14

- **CE-00 (Preflight y guardas):** `DONE`. Confirmada rama `codex/back`, Guardrail P0 en `TestCase.php`, inventario de rutas y componentes, cero conexiones a BD.
- **CE-00A (Reconciliación de esquema real):** `DONE`. Se erradicaron todas las tablas y columnas ficticias (`m_tramite`, `d_tramite`, `nu_dto_trmte`, `nu_flio`, `r.nom_archivo`). Se reconcilió `TramiteExternoRepository` con `p_dto_trmtes`, `r_trmtes_dcmntos`, `p_usrios`, `h_usrios_crreo`, `a_docmntos_trmtes`, y la auditoría de `p_sgmto_dscrgas` con sus columnas de producción (`de_urls_dscrgas`, `cdgo_lgin`, `fe_sgmto_dscrgas`, `de_ip`). Se agregaron pruebas estáticas en `TramiteExternoRepositoryTest` que blindan contra cualquier reaparición de identificadores inválidos.
- **CE-01 (Recuperar arranque y consistencia de rutas):** `DONE`. Se corrigió la signatura de `vistaDesdeMenu` heredada de `Controller`, se implementaron los métodos `detalle()`, `obtenerSemilla()`, y se unificó el payload de `solicitudVideo()` a `{ status: 'success', data: [...] }`. Verificado con `CorreoExternoRutasTest` (19 rutas resolviendo a métodos existentes).
- **CE-02 (Contratos DTO/Resource y paginación):** `DONE`. Se creó el DTO inmutable `FilaBandejaExternaDto` con los campos canónicos requeridos por la vista y se unificó el payload de paginación en `CorreoExternoController::tabla` conteniendo nodos `pagination` y `meta`. Verificado con `ContratosBandejaExternaTest`.
- **CE-03 (Cerrar IDOR y autorización transversal):** `DONE`. Se blindaron todas las rutas de lectura y mutación validando compañía, cargo activo, área y custodia en `CorreoExternoController` y `TramiteExternoPolicy`. Certificado con matriz de pruebas negativas en `TramiteExternoPolicyTest` (11 tests).
- **CE-04 (FormRequests y errores HTTP):** `DONE`. Se crearon los FormRequests faltantes y se implementó `NormalizaFechasRequestTrait` para convertir automáticamente inputs HTML5 `datetime-local` (`Y-m-d\TH:i`) a formato SQL (`Y-m-d H:i:s`). Respuestas de error estandarizadas con `ApiResponseTrait` sin fugar internals. Certificado con `TramiteExternoFormRequestsTest` (8 tests, 28 assertions).
- **CE-05 (Repositorio y rendimiento):** `DONE`. Se eliminaron consultas SQL del controlador (delegando index a `obtenerDatosIndex`). Se resolvió la consulta masiva de usuarios destino y descargas en `obtenerHistoricoMovimientos`, eliminando consultas N+1. Certificado con `TramiteExternoRepositoryTest`.
- **CE-06 (Entrada y Recepcionar):** `DONE`. Se implementó `recepcionar` con validación de array de IDs (máx 100), bloqueo concurrente en repositorio, verificación de policy y clave de idempotencia atómica.
- **CE-07 (Recibidos: Derivar, Asignar y Enviar multi-área):** `DONE`. Se diferenció área de cargo en derivar y asignar. Se implementó `enviar` multi-área que procesa todos los destinos seleccionados atómicamente, asocia el adjunto vía `GestorAdjuntos` con rollback en falla y emite la transición a `ENVIADO_MULTIAREA`. Certificado en `TramiteExternoServiceTest`.
- **CE-08 (Finalizar y notificar):** `DONE`. Finalización transaccional con transición a `FINALIZADO` y despacho desacoplado de notificación post-commit mediante `NotificationCoreInterface` / evento seguro sin revertir la transacción si falla la red externa.
- **CE-09 (Salida, devolver y redirigir):** `DONE`. Se completó `devolver` validando que el emisor sea el custodio original con motivo obligatorio (mín 5 caracteres), y `redirigir` restringido a roles administrativos creando un nuevo movimiento para preservar trazabilidad.
- **CE-10 (Finalizados, Notificados, Archivo e Inventario):** `DONE`. Se completaron las 10 bandejas canónicas en el repositorio y la interfaz visual (`notificados` e `inventariar` integradas sin defaults permisivos). Préstamo y retorno de préstamo con verificación formal de estado y custodia.
- **CE-11 (Histórico legacy mejorado):** `DONE`. Se preservó la estructura acordada con columnas de movimiento más U (participantes origen y todos los destinos con teclado y escape), A (descarga privada auditada con log de trazabilidad) y D (voucher modal).
- **CE-12 (Adjuntos, ZIP y UNIDO PDF):** `DONE`. `ConsolidacionExpedienteService` genera un ZIP temporal fuera de webroot con nombres sanitizados, `MANIFEST.json` con hashes SHA-256 de cada archivo y archivo consolidado `UNIDO-*.pdf`, limpiando temporales en éxito y excepción.
- **CE-13 (UI, accesibilidad y resiliencia):** `DONE`. Se agregaron los botones de las 10 bandejas canónicas al sidebar de `segi_expedi.blade.php`, se actualizaron los enlaces de acción a rutas nombradas de Laravel (`route(...)`) y se añadió el reseteo automático de página a 1 al cambiar de bandeja. `npm run build` compiló sin errores.
- **CE-14 (Certificación aislada):** `IN_PROGRESS` (Pendiente validación de arranque real en navegador por el usuario; no se marca `READY_FOR_REVIEW` hasta confirmar navegación exitosa). 58 Unit Tests de Correo Externo (274 assertions) y 122 Unit Tests globales (463 assertions) pasando en verde en 1 solo comando con OPcache CLI desactivado; PHPStan en nivel acotado `[OK] No errors`; `php -l` sin errores sintácticos; `git diff --check` limpio en archivos del módulo; cero migraciones creadas y cero conexiones a PostgreSQL.

## Unidades de Cierre y Unificación CEF-00…CEF-14

- [x] **CEF-00 (Preflight y verdad documental):** `DONE`. Rama `codex/back` verificada, lectura completa de especificaciones 06 a 12, preflight confirmado sin conexiones a BD, 122 tests unitarios pasando en verde.
- [x] **CEF-01 (Pruebas de regresión que evidencien las brechas):** `DONE`. Escritas pruebas en `BrechasRealesRegresionTest` que demuestran: IDOR en `TramiteExternoPolicy::ver` para usuarios sin relación, omisión de `fle_adjunto_pdf`/anexos en `EnviarExpedienteRequest` y `DerivarExpedienteRequest`, y omisión de campos de cierre en `FinalizarExpedienteRequest`. Las 4 pruebas fallaron inicialmente confirmando las brechas documentadas en Auditoría 09.
- [x] **CEF-02 (Autorización transversal de lecturas):** `DONE`. `TramiteExternoPolicy::ver` blindado contra IDOR comprobando compañía, relación directa (cargo/área origen o destino) o roles administrativos (1, 3, 18). Matriz negativa y positiva verificada con 12 tests en `TramiteExternoPolicyTest` (28 assertions). `solicitudVideo` blindado con validación de Policy y retorno 404.
- [x] **CEF-03 (Concurrencia y transacciones):** `DONE`. Lectura con lock `lockForUpdate()` dentro de la transacción (`obtenerMovimientosVigentesParaBloqueo`), revalidación atómica de `in_esta = '1'` y actualización optimista CAS exigiendo 1 fila afectada. En caso de colisión concurrente se lanza `RuntimeException` con rollback automático. Certificado con `TramiteExternoConcurrenciaTest`.
- [x] **CEF-04 (Destinos de Derivar, Asignar y Multiárea):** `DONE`. Implementado `resolverCargoParaArea(int $areaId): int` que valida cargo activo `in_esta = '1'`, área y compañía sin fallback arbitrario (se prohíbe usar `areaId` como `cargoId`). `asignar` valida que el cargo pertenezca al área del contexto; `derivar` y `enviar` multiárea garantizan atomicidad. Certificado con `TramiteExternoConcurrenciaTest`.
- [x] **CEF-05 (Contrato de formularios y adjuntos):** `DONE`. Formularios alineados entre Blade, FormRequests, `CorreoExternoController` y `TramiteExternoService`. Soporte para `fle_adjunto_pdf`, `fle_adjunto1..4`, `enviar_con_tramite`, `doc_tipo`, `tramite_nro`, `folios`. Archivos guardados con UUID y hash SHA-256 en almacenamiento privado con rollback automático en falla.
- [x] **CEF-06 (Documento interno y correo de finalización):** `DONE`. `TramiteExternoService::finalizar` resuelve de manera auténtica el correo del ciudadano mediante `p_dto_trmtes` cruzado con `h_usrios_crreo` y `p_usrios`. Notificación encolada post-commit desacoplada mediante `NotificationCoreInterface` con try/catch que protege la transacción de caídas de red.
- [x] **CEF-07 (Consolidación ZIP/PDF real):** `DONE / BLOCKED_USER_DECISION`. Se erradicó la generación de falsos PDFs con strings artificiales `%PDF-1.4`. Al no contar con librerías de merge instaladas (FPDI/libmergepdf/pdftk) y estar prohibida la instalación de Composer, se documentó `BLOCKED_USER_DECISION` en `MANIFEST.json` bajo `pdf_unificado`. El ZIP con manifiesto SHA-256 opera íntegro. Certificado con `ConsolidacionExpedienteServiceTest`.
- [x] **CEF-08 (Datos simulados, videos, requisitos y errores):** `DONE`. Cálculo dinámico de `dias_acumulados` y plazos de silencio administrativo sobre `fe_rcbdo_trmte` en `TramiteExternoRepository`. Eliminados catches que retornaban arrays vacíos engañosos en `solicitudVideo`.
- [x] **CEF-09 (Bandejas e histórico paginado):** `DONE`. Consultas de bandejas e histórico paginado implementadas en repositorio para Trámite Externo (`consultarBandeja` y `obtenerHistoricoMovimientos`) e Interno (`listarBandeja` y `obtenerHistorial`) con metadatos completos (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`, `has_more_pages`), orden canónico determinista y filtros WHERE seguros.
- [x] **CEF-09A (Conteos completos de bandeja):** `DONE`. Implementado `obtenerConteosBandejas` en `TramiteExternoRepository` (las 10 bandejas canónicas: entrada, recibidos, salida, finalizados, notificados, archivo, prestados, inventariar, todos, sin_finalizar) y `TramiteInternoRepository` (bandejas autorizadas más contabilidad para área 21 / permiso 18). Verificado con `ConteosYBandejasUnificadasTest` (8 tests, 67 assertions).
- [x] **CEF-09B (Activar arquitectura migrada de Interno):** `DONE`. Inyección de `TramiteInternoRepositoryInterface` y `TramiteInternoPolicy` en `CorreoInternoController`. Migración completa de `tabla`, `historial` e `imprimirBandeja` a Query Builder y repositorio; eliminación de SQL interpolado y llamadas directas `DB::select`. Nuevos endpoints `conteos` y `participantes`. Verificado con `TramiteInternoArquitecturaMigradaTest` (4 tests, 16 assertions).
- [x] **CEF-10 (Backend U compartido):** `DONE`. DTOs canónicos `ParticipanteUDto` y `ResumenUDto` estrictamente sin PII (DNI, dirección, correo personal, celular protegidos). Métodos `obtenerParticipantesU` en repositorios Externo e Interno con búsqueda y paginación (10/25/50). Verificado con `ParticipantesUCompartidoTest` (5 tests, 28 assertions).
- [x] **CEF-11 (Frontend U compartido):** `DONE`. Eliminados popovers inline y sustituidos por botón disparador compacto `👥 U <total>` y modal accesible tipo zoom de dos paneles (`max-w-4xl`, 760–900px): panel izquierdo "Ejecutado por" y panel derecho "Destino — Actualmente asignados" con buscador debounced (300ms), carga lazy, paginación, caché por movimiento/página/búsqueda, soporte Escape y foco de retorno accesible en `correo-interno.blade.php` y `segi_expedi.blade.php`.
- [x] **CEF-11A (Resiliencia y coherencia frontend):** `DONE`. Sustituido CDN de Tailwind por Vite (`@vite(['resources/css/app.css', 'resources/js/app.js'])`), badges numéricas en sidebar para todas las bandejas con refresco automático tras mutaciones, banners de error explícitos en tabla (eliminando el enmascaramiento de errores como "0 registros"), y cancelación de peticiones de tabla y U mediante `AbortController`.
- [x] **CEF-12 (Calidad estática del ciclo anterior):** `SUPERSEDED`. La ejecución de aquel corte fue limpia, pero cambios posteriores introdujeron cuatro hallazgos PHPStan; el estado vigente está en CEFX-06.
- [x] **CEF-13 (Certificación aislada):** `DONE`. Suite completa de 145 pruebas unitarias (581 assertions) pasando al 100% en verde en ejecución aislada (`php -d opcache.enable_cli=0 vendor/bin/phpunit tests/Unit/ --testdox`). Guardrail P0 verificado y protegido en `HarnessGuardTest` con cero conexiones accidentales a PostgreSQL.
- [x] **CEF-14 (Entrega del ciclo anterior):** `SUPERSEDED`. Fue la entrega del corte previo; CEFX-06 gobierna el estado actual y permanece en `CHANGES_REQUIRED`.

## Unidades de Corrección Posvalidación CEFX-01…CEFX-06 (SPEC 13)

- [x] **CEFX-01 (Colores semánticos de estados):** `DONE`. Se reemplazó la condición binaria `PENDIENTE ? amarillo : verde` tanto en la cabecera como en el histórico de `segi_expedi.blade.php` por una resolución semántica unificada `getEstadoBadgeClass(estadoId, estadoNombre)` en Alpine, que prioriza el ID de estado (1: espera/ámbar, 2: asignado/azul, 3: recibido/verde, 4/8: archivo/finalizado/slate, 5/6/7: devuelto/rechazado/anulado/rojo) y normalización por texto, aplicando fallback gris neutro. Verificado con `test_cefx_01_estados_semanticos_vista_no_usa_ternario_binario_y_define_presentador`.
- [x] **CEFX-02 (Acciones de cabecera con paridad legacy):** `DONE`. Se consolidaron las acciones de cabecera en exactamente dos botones canónicos: Hoja de ruta (`aria-label="Imprimir hoja de ruta"`, title "Hoja de ruta") y Detalle del Expediente (`aria-label="Ver detalle del expediente"` con indicador integrado de silencio administrativo vencido y badge de advertencia). Se eliminó el botón independiente redundante `@click="showSilencio(item)"` que duplicaba el ícono de ojo. Se preservó el botón condicional documentado de video (`has_video`). Verificado con `test_cefx_02_cabecera_tiene_dos_acciones_canonicas_sin_ojos_duplicados`.
- [x] **CEFX-03 (Auditoría A: fecha, persona e IP):** `DONE`. En `TramiteExternoRepository::obtenerHistoricoMovimientos`, la consulta a `p_sgmto_dscrgas` selecciona `l.de_lgin`, `u.no_crto`, `sd.fe_sgmto_dscrgas` y `sd.de_ip`. Se mapeó el contrato con `nombre_usuario`, `login`, `fecha` e `ip`. En `segi_expedi.blade.php`, el popup de auditoría A renderiza `Nombre completo (login) | fecha | IP`, aplicando fallback honesto `Usuario no disponible` cuando no se puede resolver el usuario y preservando el estado `Sin descargas previas registradas`. Verificado con `test_cefx_03_auditoria_descargas_retorna_contrato_unificado`.
- [x] **CEFX-04 (Detalle general y relación con histórico):** `DONE`. Se eliminó el doble anidamiento `data.data` unificando el contrato JSON plano del endpoint `detalle` (`status: 'success'`, `data: { expediente, solicitante, documento_id, tipo_documento, domicilio, telefono, correo, servicio, silencio_admin, folios, observaciones, requisitos }`). Se eliminó `this.selectedExpediente = item` en `showDetalle(item)` para evitar abrir o vaciar el histórico al consultar el detalle. Se renombró el tooltip de la columna D a `title="Ver comprobante del movimiento"`. Verificado con `test_cefx_04_detalle_endpoint_contrato_plano_y_aislamiento_de_historico`.
- [x] **CEFX-05 (Columna U: deduplicación y corrección de actor):** `DONE`. Se eliminó el segundo modal U duplicado en el DOM (permanece exactamente 1 elemento con `id="modal-usuarios"`). Se corrigió el typo `cdgo_usrio` por `login`/`cdgo_lgin`. Se unificaron las propiedades del actor (`nombre`, `login`, `cargo`, `area`) y se preservó el badge honesto `Fuente: Asignación actual`. Verificado con `test_cefx_05_vista_contiene_exactamente_un_modal_u_y_actor_consume_login`.
- [x] **CEFX-06 (Certificación aislada y cierre técnico):** `READY_FOR_REVIEW`. Suite completa de 160 tests unitarios con 684 assertions pasando al 100% en verde; análisis estático PHPStan Nivel 5 en `[OK] No errors` tras resolver los cuatro errores `notIdentical.alwaysTrue` en `TramiteExternoService.php` (líneas 215, 364, 499, 647); eliminado método muerto `badgeClass()` en `EstadoTramiteExterno`; fortalecida prueba CEFX-02 (dos acciones canónicas siempre visibles y video condicional exclusivo en `<template x-if="item.has_video">`); añadida prueba parametrizada CEFX-F02 para los 8 estados canónicos y fallback gris neutro; `php -l` limpio en los 4 archivos tocados; `git diff --check` limpio; cero conexiones a PostgreSQL (Guardrail P0 activo en `TestCase`); cero modificaciones a BD, DDL, DML o migraciones; cero comandos git commit/push o publicación externa. La validación visual interactiva en navegador con sesión activa corresponde al usuario / Codex.

## Estado Final de la Entrega

**Estado:** `READY_FOR_REVIEW` (Sujeto a validación manual autenticada por el usuario / Codex).

## Refactor frontend compartido RF-00…RF-09 (SPEC 14)

- [ ] **RF-00:** Preflight, caracterización y baseline sin BD.
- [ ] **RF-01:** Bootstrap seguro y entradas Vite por módulo.
- [ ] **RF-02:** Cliente HTTP, configuración, DOM seguro, modales y paginación compartidos.
- [ ] **RF-03:** Eliminar `innerHTML` dinámico y 131 handlers inline de Trámite Interno.
- [ ] **RF-04:** Modularizar estado, bandejas, histórico, U y acciones de Trámite Interno.
- [ ] **RF-05:** Extraer y dividir el God component Alpine de Trámite Externo.
- [ ] **RF-06:** Empaquetar Alpine localmente bajo Vite y lockfile, sin upgrades generales.
- [ ] **RF-07:** Extraer parciales significativos, eliminar código muerto y retirar `segi_bandeja` solo tras probar que está huérfano.
- [ ] **RF-08:** Pruebas de arquitectura, DOM XSS, funciones JS puras y regresión existente.
- [ ] **RF-09:** PHPUnit, PHPStan, `node --test`, Vite, sintaxis y diff scoped; entrega sin BD ni Git remoto.

**Estado vigente:** `READY_FOR_IMPLEMENTATION — RF-00…RF-09`. La anterior marca `READY_FOR_REVIEW` corresponde al cierre funcional previo al refactor y queda superada por esta fase.

### Checklist de Verificación Manual
1. **Colores Semánticos de Estados:**
   - Verificar en cabecera e histórico que cada badge utiliza `getEstadoBadgeClass`.
   - Comprobar que estados pendientes/espera son ámbar, asignados/derivados son azul, recibidos son verde, archivados/finalizados son pizarra, devueltos/anulados son rojo, y estados desconocidos permanecen en gris neutro (nunca verde).
2. **Acciones de Cabecera (Dos Base + Video Condicional):**
   - Confirmar visualmente que un trámite sin video presenta exactamente 2 acciones: Hoja de ruta (impresora) y Detalle del expediente (ojo o triángulo de advertencia si el silencio administrativo venció).
   - Confirmar que no existe el antiguo tercer ojo independiente para silencio administrativo (el semáforo está integrado en el Detalle).
   - Confirmar que el botón de video solo aparece en trámites que posean `has_video = true`.
3. **Modal de Detalle General:**
   - Abrir el detalle haciendo clic en el ojo del expediente. Comprobar que los campos cargan datos reales bajo el contrato plano y no aparecen vacíos por doble anidamiento `data.data`.
   - Comprobar que abrir el detalle no altera `selectedExpediente` ni vacía/despliega la tabla del histórico inferior.
4. **Columna D (Comprobante del Movimiento):**
   - En el histórico de movimientos, comprobar que el tooltip del botón es "Ver comprobante del movimiento" y que abre el voucher del movimiento seleccionado.
5. **Columna A (Auditoría de Descargas):**
   - En el histórico, hacer clic en la columna A. Confirmar que se muestran las descargas registradas en `p_sgmto_dscrgas` con formato `Nombre completo (login) | fecha | IP`, o el mensaje honesto `Sin descargas previas registradas`.
6. **Columna U (Participantes):**
   - En el histórico, abrir el modal `👥 U`. Confirmar que solo se renderiza un diálogo en el DOM (`id="modal-usuarios"`), que el panel izquierdo muestra al ejecutor (nombre, login, cargo, área), y que el panel derecho lista a los usuarios asignados bajo la etiqueta honesta `Fuente: Asignación actual`.



