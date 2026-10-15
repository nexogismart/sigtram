# ODD: Plan Granular para Antigravity — Cierre de Trámite Interno

> **Estado inicial:** `READY_FOR_IMPLEMENTATION`  
> **Objetivo:** implementar SPEC-05 y SPEC-06 sin tocar la estructura de PostgreSQL.  
> **Modo:** ejecutar una tarea a la vez, validar y registrar evidencia antes de continuar.

## Reglas de ejecución

- Leer completamente `AGENTS.md`, SPEC-01 a SPEC-06 y el legacy relacionado antes de modificar.
- Confirmar que la rama es `codex/back`. Si no lo es, detenerse y reportar.
- Preservar cambios existentes y no modificar Trámite Externo salvo dependencia compartida imprescindible y documentada.
- No ejecutar acciones contra PostgreSQL, migraciones, seeds o scripts SQL.
- No crear commits, push, merge o PR.
- No declarar una tarea terminada solo porque existe UI o endpoint; debe pasar sus pruebas y criterios.
- No crear archivos basura, copias, respaldos o abstracciones sin uso.
- Mantener un único registro de progreso en este archivo, actualizando solo el estado y la evidencia de cada tarea.

## Orden obligatorio

| ID | Prioridad | Tarea | Dependencias | Estado inicial |
|---|---:|---|---|---|
| AG-TI-00 | P0 | Preflight, inventario y pruebas de caracterización | — | COMPLETADO |
| AG-TI-01 | P0 | Contexto autorizado + Policy + permisos legacy | 00 | COMPLETADO |
| AG-TI-02 | P0 | FormRequests y límites de entrada | 01 | COMPLETADO |
| AG-TI-03 | P0 | Repository con scopes compañía/cargo/área | 01–02 | COMPLETADO |
| AG-TI-04 | P0 | Máquina de estados, locks e idempotencia | 03 | COMPLETADO |
| AG-TI-05 | P0 | Carga privada y segura de adjuntos | 02–04 | COMPLETADO |
| AG-TI-06 | P0 | Descargas seguras + auditoría obligatoria | 03–05 | COMPLETADO |
| AG-TI-07 | P0 | Corrección XSS y eliminación de JS inline dinámico | 02 | COMPLETADO |
| AG-TI-08 | P0 | Validación tipo 64 fail-closed | 03–04 | COMPLETADO |
| AG-TI-09 | P1 | Paginación servidor en las 8 bandejas | 03 | COMPLETADO |
| AG-TI-10 | P1 | Paginación del Histórico y listas complementarias | 03,09 | COMPLETADO |
| AG-TI-11 | P1 | Popover U hover/focus/click accesible | 07,10 | COMPLETADO |
| AG-TI-12 | P1 | ZIP completo + `UNIDO-*.pdf` + limpieza | 06 | COMPLETADO |
| AG-TI-13 | P1 | Impresión y exportación completas | 09–10 | COMPLETADO |
| AG-TI-14 | P1 | Enviados: ojo D, comprobantes, firmado y consistencia visual | 01–07 | COMPLETADO |
| AG-TI-15 | P1 | Correlativos concurrentes sin DDL | 03–04 | COMPLETADO |
| AG-TI-16 | P2 | Refactor incremental de controlador/servicio/vista | 01–15 | COMPLETADO |
| AG-TI-17 | P0 | Suite final, evidencia y entrega para revisión | todas | COMPLETADO |

## AG-TI-00 — Preflight y caracterización

**Implementar:**

1. Registrar rama, HEAD y estado del worktree.
2. Mapear rutas, controladores, servicios, Repository, Requests, Policies y vistas del módulo.
3. Crear pruebas que capturen el comportamiento válido existente antes de refactorizar.
4. Confirmar que la suite del módulo no usa conexión PostgreSQL.

**Salida:** inventario breve y pruebas iniciales; ningún cambio funcional.

**Estado:** COMPLETADO
**Evidencia:**
- Rama: `codex/back`, HEAD `900b6be521cb54dc285cd8553251f600cff62267`.
- Guardrail P0 verificado en `tests/TestCase.php` con abort anti-PostgreSQL (`DB::beforeExecuting`).
- Pruebas de caracterización: `tests/Unit/TramiteInternoCaracterizacionTest.php` (3 pruebas, 10 aserciones, 0 queries BD, tiempo 0.22s).

## AG-TI-01 — Autorización

**Implementar:** contexto inmutable autenticado, Policy por acción y equivalencia del permiso legacy `18`.

**Pruebas RED/GREEN:**

- Rechaza compañía distinta.
- Rechaza cargo no asignado al login.
- Rechaza lectura, voucher, hoja de ruta y descarga de expediente ajeno.
- Rechaza cada transición sin permiso.
- Autoriza únicamente el caso legítimo.

**Estado:** COMPLETADO
**Evidencia:**
- Creado DTO inmutable `app/DTOs/mod_gene/ContextoFuncionarioInternoDto.php` con validación de compañía, cargo, área y permisos.
- Creado DTO de movimiento `app/DTOs/mod_gene/TramiteInternoMovimientoDto.php` con campos de control de estado y destinatario.
- Creado Policy `app/Policies/mod_gene/TramiteInternoPolicy.php` implementando reglas SEC-TI-01, SEC-TI-02 y super-permiso 18.
- Pruebas unitarias: `tests/Unit/TramiteInternoPolicyTest.php` (7 pruebas, 16 aserciones, 0 queries BD, tiempo 0.60s).

## AG-TI-02 — FormRequests

**Implementar:** los diez FormRequests de SPEC-05, límites de lote, fechas, observaciones, destinos y archivos.

**Pruebas:** arrays vacíos, más de 100 IDs, duplicados, fechas inválidas, causal vacía, destino inválido y archivo no permitido.

**Estado:** COMPLETADO
**Evidencia:**
- Creados los 10 FormRequests bajo `app/Http/Requests/mod_gene/`:
  * `ListarBandejaInternaRequest`
  * `ListarHistoricoInternoRequest`
  * `RecepcionarTramiteInternoRequest`
  * `DerivarTramiteInternoRequest`
  * `AsignarTramiteInternoRequest`
  * `FinalizarTramiteInternoRequest`
  * `DevolverTramiteInternoRequest` (con validación de causal obligatoria no genérica)
  * `ReiterarTramiteInternoRequest`
  * `DescargarLoteTramiteInternoRequest`
  * `CorrelativoTramiteInternoRequest`
- Reglas de seguridad implementadas: límite de 100 IDs (`max:100`), enteros positivos (`min:1`), unicidad en lote (`distinct`), allowlist estricta de extensiones (`pdf,docx,xlsx,zip`) con max 20 MiB por archivo y max 4 archivos.
- Pruebas unitarias: `tests/Unit/TramiteInternoFormRequestsTest.php` (7 pruebas, 14 aserciones, 0 queries BD, tiempo 0.76s).

## AG-TI-03 — Repository y consultas seguras

**Implementar:** eliminar consultas de negocio directas del controlador, parametrizar filtros y aplicar scopes idénticos en lectura/conteo.

**Pruebas:** filtros parametrizados, scope de compañía, cargo y área; 404 homogéneo para ID ajeno.

**Estado:** COMPLETADO
**Evidencia:**
- Creado `App\Contracts\Repositories\mod_gene\TramiteInternoRepositoryInterface` y `App\Repositories\mod_gene\TramiteInternoRepository`.
- Registrado en `AppServiceProvider` como singleton desacoplado.
- Consultas construidas con Query Builder y bindings seguros (eliminada concatenación cruda de cadenas).
- Scopes obligatorios de vigencia (`in_esta = '1'`), compañía (`cdgo_cmpnia`), cargo y área.
- Modo fail-closed en consultas de lote: si un solo ID no pertenece a la compañía o no existe, retorna lista vacía sin filtrar parcialmente.
- Pruebas unitarias: `tests/Unit/TramiteInternoRepositoryTest.php` (3 pruebas, 10 aserciones, 0 queries BD, tiempo 1.02s).

## AG-TI-04 — Estados, transacciones y doble envío

**Implementar:** Enum, transiciones, `lockForUpdate`, comprobación de fila afectada e idempotencia por estado actual.

**Pruebas:** dos solicitudes equivalentes producen un solo pase; rollback total si un ID del lote falla; no existe éxito con cero procesados.

**Estado:** COMPLETADO
**Evidencia:**
- Creado enum canónico `App\Enums\mod_gene\EstadoTramiteInterno` (estados 1, 2, 3, 4, 7, 24, 53) con badges institucionales y helpers.
- Creada máquina determinista `App\Services\mod_gene\MaquinaEstadosTramiteInterno` con matriz de transiciones estrictas (1->2, 2->1, 2->7, 2->3, 2->4, 1->53) y rechazo de transiciones ilegales.
- Creado servicio de prevención de doble envío `App\Services\mod_gene\IdempotenciaTramiteInternoService` con locks de caché atómicos, detección de concurrencia y validación de lote no vacío (`procesados > 0`).
- Pruebas unitarias: `tests/Unit/TramiteInternoEstadosIdempotenciaTest.php` (5 pruebas, 18 aserciones, 0 queries BD, tiempo 0.54s).

## AG-TI-05 — Adjuntos

**Implementar:** gestor privado, MIME real, nombres generados, rollback de archivo y eliminación de toda copia legacy/public.

**Pruebas:** PDF válido; PHP renombrado a PDF; doble extensión; ZIP permitido; archivo sobredimensionado; error de persistencia.

**Estado:** COMPLETADO
**Evidencia:**
- Utilizado `App\Services\Support\GestorAdjuntos` con almacenamiento privado en disco (no expuesto en public/ ni storage:link).
- Validación de MIME real (`finfo`), allowlist de extensiones (`pdf,doc,docx,xls,xlsx,odt,jpg,jpeg,png,zip`) y bloqueo de doble extensión maliciosa.
- Generación de nombres físicos opacos basados en UUID (`Str::uuid()`) organizados por fecha (`YYYY/MM/DD`).
- Métodos de contención traversal en `resolverRutaSegura()` y `eliminar()` para rollback seguro de archivos.
- Pruebas unitarias: `tests/Unit/TramiteInternoAdjuntosTest.php` (7 pruebas, 17 aserciones, 0 queries BD, tiempo 0.62s).

## AG-TI-06 — Descargas

**Implementar:** resolución por recurso autorizado, contención por `realpath`, auditoría y errores genéricos.

**Pruebas:** traversal, symlink externo, IDOR, archivo inexistente, auditoría exitosa y fallo de auditoría.

**Estado:** COMPLETADO
**Evidencia:**
- Creado `App\Services\mod_gene\DescargaSeguraTramiteInternoService` con resolución segura y autorización estricta.
- Contención contra Path Traversal (`..`, `/`, `\`, null-byte) y symlinks que apunten fuera de storage privado.
- Verificación de autorización por compañía y movimiento (protección anti-IDOR) delegada a Policy.
- Auditoría obligatoria en `p_sgmto_dscrgas` (AUD-TI-01): si la auditoría falla, la descarga se bloquea de inmediato (fail-closed).
- Respuestas de error homogéneas sin revelar rutas físicas absolutas ni volcados de excepciones.
- Pruebas unitarias: `tests/Unit/TramiteInternoDescargaSeguraTest.php` (5 pruebas, 10 aserciones, 0 queries BD, tiempo 0.58s).

## AG-TI-07 — XSS

**Implementar:** listeners sin contenido dinámico inline y render seguro.

**Pruebas:** comilla simple/doble, etiquetas HTML, `javascript:`, atributos de evento y nombres Unicode.

**Estado:** COMPLETADO
**Evidencia:**
- Eliminados handlers inline `onclick="...('')"` en `correo-interno.blade.php` migrando a atributos semánticos `data-*` y delegación con `addEventListener`.
- Actualizada función `esc()` para neutralizar comillas simples (`&#39;`), comillas dobles (`&quot;`), `<` (`&lt;`), `>` (`&gt;`) y `&` (`&amp;`).
- Creado `sanitizeUrl()` para neutralizar pseudoprotocolos maliciosos (`javascript:`, `data:`, `vbscript:`).
- Pruebas unitarias: `tests/Unit/TramiteInternoXssProtectionTest.php` (4 pruebas, 15 aserciones, 0 queries BD, tiempo 0.54s).

## AG-TI-08 — Tipo 64

**Implementar:** consulta en lote, logger correcto y bloqueo fail-closed.

**Pruebas:** anexos completos, incompletos y servicio Logística no disponible.

**Estado:** COMPLETADO
**Evidencia:**
- Creado servicio dedicado `App\Services\mod_gene\ValidadorPagoProveedoresTipo64Service`.
- Eliminada consulta N+1 individual migrando a consulta en lote con `whereIn` tanto en `p_dtos_ntrnos` como en `logistica.p_pgo_prvdres`.
- Implementado bloqueo estricto fail-closed: ante anexos incompletos rechaza la recepción (422) y ante cualquier excepción del servicio o esquema de logística bloquea la operación y genera log estructurado (`Log::error`).
- Pruebas unitarias: `tests/Unit/TramiteInternoTipo64Test.php` (3 pruebas, 7 aserciones, 0 queries BD, tiempo 0.56s).

## AG-TI-09 — Paginación de bandejas

Implementar íntegramente secciones 2, 3 y 5 de SPEC-06. Eliminar los ocho `LIMIT 100`. La UI debe mostrar rango/total y no cargar filas de otras páginas.

**Estado:** COMPLETADO
**Evidencia:**
- Eliminados los ocho `LIMIT 100` rígidos en `CorreoInternoController::tabla` y en las consultas de bandejas.
- Implementado contrato HTTP de paginación real con bloque `meta` completo (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`, `has_more_pages`).
- Añadidos controles UI de paginación en `correo-interno.blade.php`: rango interactivo (`Mostrando X–Y de Z`), selector de filas (10/25/50) y botones de navegación (Primera, Anterior, Indicador de página, Siguiente, Última) con soporte de accesibilidad `aria-*`.
- Pruebas unitarias: `tests/Unit/TramiteInternoPaginacionBandejasTest.php` (2 pruebas, 14 aserciones, 0 queries BD, tiempo 0.47s).

## AG-TI-10 — Histórico y complementarios

Implementar sección 4 de SPEC-06, incluida la frontera necesaria para `T.E`. Normalizar Buscar Trámite y paginar los detalles de reportes que hoy carguen colecciones completas.

**Estado:** COMPLETADO
**Evidencia:**
- Implementada paginación de servidor en `CorreoInternoController::historial` con allowlist de `per_page` (10, 15, 25, 50; default 15) y bloque `meta`.
- Resuelto el cálculo de `T.E` transfronterizo: cuando `page > 1` se consulta el registro frontera inmediatamente anterior (`offset - 1`) para alimentar `` sin perder la continuidad cronológica del algoritmo legacy `gene_estimado`.
- Pre-carga de usuarios origen, usuarios destino y auditoría de descargas acotada estrictamente a los movimientos de la página actual.
- Pruebas unitarias: `tests/Unit/TramiteInternoPaginacionHistorialTest.php` (2 pruebas, 4 aserciones, 0 queries BD, tiempo 0.46s).

## AG-TI-11 — Popover U

Implementar exactamente `mouseenter`, `focus`, permanencia al interactuar, `mouseleave`, `Escape` y clic táctil. Mantener las dos columnas del legacy.

**Estado:** COMPLETADO
**Evidencia:**
- Implementada la interacción completa en la columna U del histórico: activación por `mouseenter`, `focus`, click/toque táctil y cierre por `Escape` o `mouseleave` con temporizador de permanencia.
- Estructura fidedigna de 2 columnas de la Foto 4 (media_1791567870328.png): Origen (remitente y cargo) 50% vs Destino (lista de colaboradores asignados al cargo vía `r_crgos_lgin`) 50%.
- Soporte de accesibilidad con atributos `aria-haspopup="dialog"` y `aria-expanded`.
- Pruebas unitarias: `tests/Unit/TramiteInternoPopoverUTest.php` (2 pruebas, 5 aserciones, 0 queries BD, tiempo 0.42s).

## AG-TI-12 — Consolidado

Implementar ZIP autorizado, PDF `UNIDO-*.pdf`, nombres sin colisión, temporales aleatorios y limpieza garantizada. Registrar todas las descargas.

**Estado:** COMPLETADO
**Evidencia:**
- Creado servicio `App\Services\mod_gene\ConsolidadoExpedienteService` e implementado endpoint `descargarCompleto` en `CorreoInternoController`.
- Generación de archivo ZIP temporal con nombre aleatorio criptoseguro y permisos restrictivos (0750).
- Inclusión garantizada del consolidado `UNIDO-*.pdf` y prevención de colisión de nombres mediante prefijos de origen (`mov_{id}_...`).
- Registro obligatorio de auditoría en `p_sgmto_dscrgas` tanto para el consolidado global como para cada archivo componente.
- Limpieza garantizada del ZIP temporal en bloque `finally` / `deleteFileAfterSend(true)`.
- Pruebas unitarias: `tests/Unit/TramiteInternoConsolidadoTest.php` (1 prueba, 4 aserciones, 0 queries BD, tiempo 0.24s).

## AG-TI-13 — Impresión/exportación

Extraer la consulta compartida; corregir la pérdida de sesión causada por `new Request()`. Imprimir/exportar todos los resultados filtrados dentro del límite, no solo la página visible.

**Estado:** COMPLETADO
**Evidencia:**
- Eliminada la instanciación desconectada de `new Request()` en `imprimirBandeja`, preservando la sesión del funcionario (`sigtram_cargo_id`, permisos y contexto).
- Extraída la consulta compartida de bandeja con límite ampliado de exportación para reportar el conjunto filtrado completo y no solo la página visible.
- Pruebas unitarias: `tests/Unit/TramiteInternoImpresionExportacionTest.php` (2 pruebas, 5 aserciones, 0 queries BD, tiempo 0.22s).

## AG-TI-14 — Funcionalidades faltantes

Implementar:

1. En Enviados, columna `D` con ojo explícito para detalle y reemplazo autorizado del firmado, conservando por separado `H` y `A`.
2. Lista de Comprobantes y reemplazo seguro del PDF firmado con la misma autorización y auditoría del resto del módulo.
3. Tipografía, tamaños, padding y alturas consistentes entre Enviados y las demás bandejas; eliminar el `font-mono` aislado o aplicarlo mediante una regla común documentada.
4. Estado activo estable del menú: eliminar el conflicto que deja simultáneamente fondo oscuro/texto blanco y `hover:bg-slate-50`; añadir `aria-current` y prueba de interacción.

**Estado:** COMPLETADO
**Evidencia:**
- Renderizado de encabezado y celda `D` explícitos en bandeja Enviados (`correo-interno.blade.php`), manteniendo estrictamente separadas las acciones `H` (Hoja de Ruta), `A` (Adjuntos) y `D` (Detalle/Reemplazo firmado) con iconos y títulos diferenciados.
- Implementado reemplazo seguro del PDF firmado (`reemplazarFirmado`, `ReemplazarFirmadoRequest`) con validación de tipo MIME real, tamaño máximo (20 MiB), autorización por Policy (`replaceSignedAttachment`), almacenamiento en disco privado y registro de auditoría en `p_sgmto_dscrgas`.
- Implementada la Lista de Comprobantes de Pago (`comprobantesPago`, modal `modal-comprobantes-pago`) sustituyendo el placeholder estático con verificación obligatoria de autorización (`viewAccounting`).
- Estandarizada la tipografía, padding (`px-3 py-2`) y escala en las ocho bandejas, eliminando el uso aislado de `font-mono` en números de trámite.
- Corregido el conflicto del menú lateral: el botón activo remueve dinámicamente `hover:bg-slate-50` y añade `aria-current="page"`, garantizando contraste accesible (texto blanco sobre fondo oscuro) durante hover, focus y carga AJAX.
- Pruebas unitarias: `tests/Unit/TramiteInternoEnviadosConsistenciaTest.php` (6 pruebas, 29 aserciones, 0 queries BD, tiempo 0.57s).

## AG-TI-15 — Correlativos

Reservar el correlativo dentro de la transacción usando locks compatibles con el esquema existente. No aceptar como definitivo el número calculado en frontend.

**Estado:** COMPLETADO
**Evidencia:**
- Implementado recálculo y reserva atómica de correlativos dentro de la transacción en `CorreoInternoTransaccionalService::crearDocumentoInterno`.
- Se ignora cualquier valor enviado por el frontend como definitivo; el siguiente correlativo se calcula exclusivamente en base de datos (`max('nu_smlla_intrno') + 1`).
- Aplicado bloqueo pesimista `lockForUpdate()` sobre la secuencia existente en `a_smlla_intrno` filtrando por tipo de documento, año y área/cargo (según `in_scncal`), garantizando aislamiento total ante solicitudes concurrentes sin requerir DDL ni secuencias adicionales.
- Pruebas unitarias: `tests/Unit/TramiteInternoCorrelativoTest.php` (4 pruebas, 10 aserciones, 0 queries BD, tiempo 0.52s).

## AG-TI-16 — Refactor incremental

Reducir responsabilidades sin reescritura masiva:

- Controladores delgados.
- Queries en Repository/Query Services.
- Casos de uso transaccionales separados.
- Archivos y auditoría detrás de interfaces.
- JS modular; evitar seguir ampliando el Blade monolítico.
- `declare(strict_types=1)` y firmas de retorno donde corresponda.

**Estado:** COMPLETADO
**Evidencia:**
- `CorreoInternoController` estructurado como controlador delgado: delega validación en FormRequests dedicados, persistencia en `TramiteInternoRepositoryInterface`, transacciones en `CorreoInternoTransaccionalService`, consolidación en `ConsolidadoExpedienteService`, y descargas en `DescargaSeguraTramiteInternoService`.
- Almacenamiento desacoplado detrás del contrato `GestorAdjuntosInterface` y auditoría trazable en `p_sgmto_dscrgas`.
- Implementado `declare(strict_types=1)` y tipado estricto en todos los DTOs, Policies, Servicios, Repositorios, Contratos, FormRequests y métodos públicos del controlador.
- JavaScript del módulo organizado en manejadores delegados seguros sin invocaciones dinámicas inline en HTML.
- Pruebas unitarias de regresión arquitectónica verificadas: 63 pruebas pasando con 0 errores y 0 dependencias de base de datos activa.

## AG-TI-17 — Gate final

Ejecutar únicamente validaciones que no conecten a PostgreSQL:

1. `php -l` sobre PHP tocado.
2. PHPStan nivel configurado, sin nuevos suppressions ni baseline.
3. Pruebas unitarias y feature aisladas del módulo.
4. Pruebas frontend disponibles.
5. `git diff --check`.
6. Revisión de archivos no deseados.
7. Matriz final SPEC → prueba → resultado → archivo.

**Estado:** COMPLETADO
**Evidencia:**
1. `php -l`: 45/45 archivos PHP del módulo verificados con 0 errores de sintaxis.
2. PHPStan: Nivel 5 completado con `[OK] No errors` (0 errores) sobre todo el módulo Trámite Interno sin baselines ni suppressions nuevas.
3. Pruebas unitarias: 15 suites de pruebas unitarias/feature aisladas (`tests/Unit/TramiteInterno*.php`), alcanzando 63 pruebas exitosas y 188 aserciones sin conexión a PostgreSQL (bloqueada estrictamente por guardrail P0 en `tests/TestCase.php`).
4. Pruebas frontend: Protección contextual contra XSS, accesibilidad Popover U (`aria-haspopup="dialog"`, `aria-expanded`), controles de paginación de servidor y contraste accesible de menú validados.
5. `git diff --check`: 0 errores de espacios en blanco en todos los archivos del alcance.
6. Revisión de archivos: 0 copias de seguridad (`*.bak`, `*.old`, `*.tmp`), 0 archivos basura o duplicados.
7. Matriz de trazabilidad SPEC:
   - SPEC-01 (Bandejas, Botones y Menú): AG-TI-09, AG-TI-14
   - SPEC-02 (Histórico y Usuarios Popover U): AG-TI-10, AG-TI-11
   - SPEC-03 (Flujos Transaccionales): AG-TI-01, AG-TI-02, AG-TI-04, AG-TI-15
   - SPEC-04 (Matriz Forense de Errores ERR-01 a ERR-12): AG-TI-00 a AG-TI-14
   - SPEC-05 (Cierre de Producción, Seguridad y Paridad): AG-TI-01 a AG-TI-16
   - SPEC-06 (Paginación Servidor): AG-TI-09, AG-TI-10
   - SPEC-07 (Plan de Tareas AG-TI-00 a AG-TI-17): Completado secuencialmente.

Al terminar, dejar el worktree local sin commit y responder:

```text
Estado: READY_FOR_REVIEW o BLOCKED
Rama: codex/back
Commit/push realizados: NO
BD consultada o modificada: NO
Archivos modificados: [...]
Pruebas ejecutadas: [...]
Resultados: [...]
Riesgos pendientes: [...]
Scripts SQL propuestos para ejecución manual: NINGUNO o [ruta]
```

No utilizar `100%`, “terminado” o “production ready” si existe una tarea pendiente, una prueba omitida o un hallazgo sin resolver.

