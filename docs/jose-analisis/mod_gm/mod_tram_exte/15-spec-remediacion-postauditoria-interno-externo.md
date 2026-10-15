# SPEC 15 — Remediación postauditoría de Trámite Interno y Externo

## Estado

`READY_FOR_IMPLEMENTATION — RA-00…RA-12 — NO APTO PARA PRODUCCIÓN`

Esta especificación convierte los hallazgos posteriores al refactor RF-00…RF-09 en unidades pequeñas, verificables y ordenadas. No reemplaza las especificaciones funcionales anteriores: las complementa y prevalece únicamente cuando endurece seguridad, contratos, mantenibilidad o evidencia de cierre.

## 1. Objetivo

Cerrar las brechas reales que aún impiden certificar Trámite Interno y Trámite Externo como funcionales, seguros, escalables y mantenibles:

- autorización contextual y aislamiento por compañía, área y cargo;
- transiciones atómicas y fail-closed;
- validación HTTP coherente con el payload real;
- eliminación de fuga de errores internos;
- integración efectiva de la validación especial del tipo documental 64;
- cobertura HTTP/servicio sin conexiones reales a PostgreSQL;
- colores semánticos y contrato U/A/D coherente;
- reducción progresiva de controladores, servicios y JavaScript sobredimensionados;
- eliminación de rutas, acciones y HTML dinámico inseguros o muertos.

## 2. Fuentes de verdad y precedencia

1. Reglas funcionales y paridad legacy: specs 01–13 de cada módulo.
2. Arquitectura frontend ya implantada: SPEC 14.
3. Remediaciones posteriores a la auditoría: esta SPEC 15.
4. El esquema existente se consume tal como está. No se inventan tablas, columnas, estados, relaciones ni permisos.
5. Ante conflicto, prevalece seguridad fail-closed sin alterar el resultado de negocio legítimo.

## 3. Guardrails absolutos

1. Trabajar exclusivamente en la rama local `codex/back`; no cambiar de rama.
2. Prohibido ejecutar `commit`, `push`, `pull`, `merge`, `rebase`, abrir PR o publicar artefactos.
3. Prohibido conectarse a PostgreSQL, ejecutar SQL, `migrate`, `db:seed`, `tinker`, DDL o DML.
4. Prohibido crear o modificar migraciones, seeders, dumps o scripts SQL.
5. No activar `ALLOW_DANGEROUS_DB_TESTS` ni neutralizar el guardrail de `tests/TestCase.php`.
6. Las pruebas nuevas deben usar mocks, fakes, contratos o dobles en memoria y fallar antes de cualquier conexión real.
7. No descartar, sobrescribir ni reformatear cambios ajenos del árbol de trabajo.
8. No inventar columnas ni adaptar el código a un esquema supuesto. Si falta evidencia, detener esa unidad y documentar el bloqueo.
9. No crear prompts, copias, respaldos, archivos `tmp`, reportes duplicados ni artefactos de build versionados.
10. No ocultar defectos con `@phpstan-ignore`, baselines, tests omitidos o excepciones capturadas sin registro.
11. No agregar dependencias salvo necesidad demostrable y aprobación expresa. Usar primero Laravel, PHP y módulos ya instalados.
12. No declarar “100%”, “producción” ni “paridad total” sin validación manual autenticada y pruebas dinámicas autorizadas.

## 4. Hallazgos que gobiernan esta fase

| ID | Severidad | Hallazgo |
|---|---|---|
| RA-H01 | P0 | Varias operaciones internas reciben `Request` genérico y no demuestran FormRequest + Policy por operación. |
| RA-H02 | P0 | El servicio transaccional interno puede insertar movimientos sin probar previamente pertenencia, estado permitido y afectación exacta de filas. |
| RA-H03 | P0 | Histórico, detalle, hoja de ruta, voucher y descarga por lote internos requieren alcance contextual explícito para evitar IDOR. |
| RA-H04 | P0 | La validación especial para tipo documental 64 existe como servicio, pero el flujo HTTP contiene una contingencia fail-open. |
| RA-H05 | P0 | Requests y frontend no usan de forma inequívoca el mismo contrato para `observacion/observaciones` y adjuntos. |
| RA-H06 | P1 | Respuestas internas y externas pueden devolver `$e->getMessage()` al cliente. |
| RA-H07 | P1 | Predomina cobertura estática/unitaria; falta comprobar el cableado HTTP real con dobles y sin BD. |
| RA-H08 | P1 | La autorización de exportación externa contiene IDs de usuario estáticos cuya fuente de autoridad no está justificada. |
| RA-H09 | P2 | Interno no aplica todavía una semántica visual única de estados en cabecera e histórico. |
| RA-H10 | P2 | Persisten clases y módulos extensos con varias responsabilidades. |
| RA-H11 | P2 | Quedan rutas construidas manualmente, acciones posiblemente muertas y superficies Blade secundarias con JS inline/CDN/sinks HTML. |
| RA-H12 | P2 | La columna U está cableada, pero necesita pruebas de integración que garanticen múltiples destinatarios, deduplicación, foco y datos vacíos. |

## 5. Orden obligatorio de ejecución

```text
RA-00
  ├─ RA-01 → RA-02 → RA-03 → RA-04 → RA-05
  ├─ RA-06 → RA-07
  └─ RA-08 → RA-09 → RA-10
                         ↓
                       RA-11
                         ↓
                       RA-12
```

Las unidades P0 se completan antes de cualquier reorganización amplia. Cada unidad debe dejar pruebas verdes antes de iniciar la siguiente.

## 6. Tareas granulares

### RA-00 — Preflight, alcance y caracterización

- Confirmar `codex/back`, registrar `git status --short` y preservar el árbol sucio.
- Leer SPEC 15, SPEC 14 y los contratos funcionales de ambos módulos.
- Inventariar rutas, métodos HTTP, Request, Policy, Service y Repository de cada acción interna y externa.
- Ejecutar baseline permitido: sintaxis PHP/JS, `tests/Unit`, pruebas JS, PHPStan scoped, build y `git diff --check`.
- Confirmar que el guardrail de pruebas bloquea cualquier consulta real a BD.
- Registrar el baseline en el informe final, no en un archivo adicional.

**Aceptación:** matriz en la respuesta final con acción → ruta → Request → Policy → Service → prueba; cero conexión a BD.

### RA-01 — Contrato HTTP canónico de Trámite Interno

- Sustituir `Request` genérico por el FormRequest específico en recepcionar, asignar, derivar, finalizar, devolver, reiterar, descarga por lote y correlativo.
- Comparar los nombres realmente enviados por `resources/js/tramite-interno/*` con las reglas de cada FormRequest.
- Elegir un contrato canónico para observaciones y adjuntos; actualizar ambos extremos en la misma unidad.
- Si se requiere compatibilidad temporal, normalizar aliases solo en `prepareForValidation()` y exponer después una única clave interna.
- Aplicar límites, tipos, allowlist MIME/extensión, tamaños y cardinalidad de archivos conforme a las specs previas.
- No confiar en IDs de usuario, cargo, área o compañía enviados por el cliente.

**Pruebas mínimas:** 422 por payload incompleto, arrays mal formados, IDs inválidos, MIME/tamaño prohibido; aceptación del payload real del frontend; prueba de que los aliases no generan dos contratos internos.

**Aceptación:** ningún método transaccional interno usa Request genérico ni validación ad hoc duplicada.

### RA-02 — Autorización contextual e IDOR en lecturas internas

- Centralizar la obtención de compañía, área, cargo y usuario desde sesión/autenticación.
- Autorizar `historial`, `detalleDocumento`, `hojaRuta`, `voucher`, adjuntos y `descargarLote` mediante Policy o guardia de dominio equivalente comprobable.
- Hacer que Repository/Service reciban el contexto autorizado y filtren por compañía y pertenencia; no consultar primero un registro global para autorizar después.
- Para lote, exigir que el total autorizado coincida con el total solicitado; una discrepancia aborta todo el lote.
- Responder 404 cuando corresponda evitar enumeración y 403 cuando la existencia ya sea legítimamente conocida.
- Mantener U/A/D y descargas privadas sin ampliar permisos.

**Pruebas mínimas:** otro cargo, otra área, otra compañía, ID inexistente, mezcla de IDs válidos/no autorizados y usuario sin permiso.

**Aceptación:** ningún endpoint de lectura/descarga interno puede resolver un documento únicamente por ID controlado por el cliente.

### RA-03 — Mutaciones internas atómicas y fail-closed

- Hacer que `CorreoInternoTransaccionalService` cargue todos los movimientos solicitados dentro de una transacción y con bloqueo pesimista mediante Repository.
- Verificar antes de mutar: compañía, dueño/cargo activo, estado origen, transición admitida, permiso y número exacto de elementos.
- La actualización del movimiento origen debe afectar exactamente las filas esperadas antes de insertar el nuevo movimiento.
- Ante cero filas, lote parcial, carrera, transición repetida o cambio de estado concurrente: rollback y conflicto seguro; nunca insertar un movimiento huérfano.
- Preservar o fortalecer la idempotencia existente con una clave de operación estable; no usar solo el mensaje de error como control.
- Evitar N+1: resolver lotes y catálogos con consultas agrupadas en el Repository.

**Pruebas mínimas:** happy path, estado inválido, segunda ejecución, update con cero afectados, lote parcialmente ajeno, excepción a mitad de operación y concurrencia simulada.

**Aceptación:** cada mutación satisface `authorize → validate state → lock → mutate exact rows → audit/insert → commit`.

### RA-04 — Integración real del tipo documental 64

- Inyectar y utilizar `ValidadorPagoProveedoresTipo64Service` en el flujo HTTP correspondiente.
- Eliminar la contingencia que permite continuar cuando la validación lanza una excepción inesperada.
- Distinguir resultado de negocio inválido de fallo técnico; ambos deben impedir la mutación.
- Mantener el mensaje funcional seguro sin revelar SQL, rutas ni stack trace.

**Pruebas mínimas:** tipo distinto de 64, tipo 64 válido, inválido y servicio indisponible/excepción.

**Aceptación:** no existe ninguna ruta fail-open para el tipo 64 y la prueba atraviesa Controller → Service usando dobles.

### RA-05 — Policies y autorización de todas las escrituras

- Comprobar que cada acción interna y externa invoca una Policy/guardia antes del Service.
- La Policy decide capacidad general; el Service vuelve a comprobar pertenencia/estado bajo lock para evitar TOCTOU.
- No aceptar compañía/cargo/usuario efectivos desde el request.
- Conservar las reglas de negocio legacy válidas y los enums como identificadores de dominio, sin usarlos como catálogo editable.

**Pruebas mínimas:** usuario no autenticado, rol sin capacidad, compañía/cargo ajeno y revocación/cambio de contexto entre petición y mutación simulada.

**Aceptación:** matriz completa de endpoints mutables, sin huecos de autorización.

### RA-06 — Errores seguros y observabilidad

- Retirar de respuestas JSON/HTML todo uso de `$e->getMessage()` para excepciones técnicas.
- Mapear excepciones de dominio conocidas a 400/403/404/409/422 con mensajes estables y no sensibles.
- Para errores inesperados, devolver 500 genérico con un identificador de correlación y registrar contexto sanitizado server-side.
- No registrar contenido de adjuntos, credenciales, cookies, tokens ni PII innecesaria.
- Unificar la estrategia entre Interno y Externo sin crear un helper global excesivo.

**Pruebas mínimas:** excepción con SQL/path/token señuelo que no aparece en respuesta; códigos correctos por excepción de dominio; correlación presente en log/respuesta segura.

**Aceptación:** búsqueda estática y pruebas confirman cero filtración de mensajes técnicos al cliente.

### RA-07 — Pruebas HTTP de cableado sin PostgreSQL

- Crear pruebas Feature/HTTP aisladas para endpoints críticos de ambos módulos usando mocks de contratos y servicios.
- Verificar middleware, autenticación, FormRequest, Policy, normalización del payload, status, estructura JSON y que el Service recibe DTO/contexto esperado.
- No probar consultas reales ni desactivar el guardrail. Si una ruta no puede aislarse, corregir la dependencia rígida mediante interfaces/inyección.
- Mantener arrays planos bajo `data`; prohibido reintroducir `data.data`.

**Cobertura mínima:** una prueba positiva y las negativas 401/403/409/422/500 seguro por familia crítica; detalle, histórico, U, descarga y cada mutación.

**Aceptación:** las pruebas demuestran el cableado runtime, no solo presencia de strings o métodos.

### RA-08 — Semántica visual única de estados

- Extraer o reutilizar un presentador puro único para colores de estado en Interno y Externo.
- Priorizar ID canónico; utilizar nombre normalizado únicamente como fallback; desconocido siempre gris neutro.
- Aplicar la misma clase en tabla cabecera, histórico, modal o partial donde el estado se represente como badge.
- Mantener contraste AA, texto visible y significado no dependiente solo del color.

**Mapa mínimo:** espera/pendiente ámbar; asignado/derivado azul; recibido verde; archivado/finalizado pizarra; devuelto/rechazado/anulado rojo; desconocido gris.

**Pruebas mínimas:** tabla parametrizada de estados, fallback desconocido, igualdad entre cabecera e histórico y ausencia de ternarios binarios dispersos.

**Aceptación:** una sola resolución semántica comprobable para ambos módulos.

### RA-09 — Contrato U/A/D y múltiples destinatarios

- Mantener exactamente una instancia accesible del visor U por pantalla.
- U debe mostrar origen y todos los destinos disponibles, deduplicados por clave estable, sin truncar la lista ni asumir un único receptor.
- Para listas extensas, usar contenedor con alto máximo, scroll, contador, búsqueda local opcional y encabezados fijos; no cargar usuarios ajenos al movimiento.
- Soportar hover como mejora, pero también clic, Enter, Espacio, Escape, foco inicial y retorno de foco.
- Mantener estados honestos: cargando, vacío, error seguro y “fuente asignación actual” cuando no exista histórico nominal.
- A solo muestra persona, fecha e IP cuando existe registro real; si no existe, conservar “Sin descargas previas registradas” sin inventar auditoría ni tocar BD.
- D abre exclusivamente el comprobante del movimiento autorizado.

**Pruebas mínimas:** cero, uno y varios destinos; duplicados; nombres largos/Unicode/XSS; teclado; A vacío/con datos; D autorizado/no autorizado.

**Aceptación:** contrato idéntico y estable en Interno y Externo, sin datos falsos ni consultas adicionales por fila.

### RA-10 — Rutas, acciones muertas y superficies Blade restantes

- Inventariar todas las vistas internas y externas del módulo, no solo las dos vistas canónicas.
- Sustituir URLs hardcodeadas por rutas generadas/configuración segura de bootstrap.
- Identificar acciones como `openHojaRuta`, `imprimirBandeja`, `exportarExcel`, `verAdjunto`, `descargarConsolidado` y similares; conservarlas si tienen consumidor probado o eliminarlas si son realmente huérfanas.
- Eliminar handlers inline, CDN ejecutables y sinks `innerHTML`/`x-html` inseguros restantes dentro de ambos módulos.
- En respuestas HTML legacy inevitables, migrar a datos estructurados; no sanitizar con expresiones regulares caseras.
- No borrar una vista o método sin evidencia de rutas, referencias y pruebas.

**Pruebas mínimas:** reglas arquitectónicas sobre todas las vistas de ambos directorios; payloads XSS; prueba de rutas configuradas; prueba de ausencia de referencias antes de retirar código.

**Aceptación:** inventario cerrado, cero rutas manuales evitables, cero eventos inline y cero sinks prohibidos con datos no confiables en el alcance completo.

### RA-11 — Descomposición backend/frontend por responsabilidad

- Ejecutar después de RA-01…RA-10 para no ocultar defectos funcionales mediante movimientos de código.
- Dividir controladores en orquestadores delgados; extraer casos de uso cohesionados, no helpers genéricos.
- Separar consultas, transiciones, archivos, consolidación, participantes, descargas y presentadores cuando hoy convivan en una clase extensa.
- Dividir módulos JS de acciones por flujo: recepción; enrutamiento/asignación; cierre/devolución; documentos/descargas; comprobantes.
- Mantener API pública, rutas y contratos salvo correcciones explícitas de RA-01/RA-06.
- Usar como guía, no como truco: métodos de controlador de unas 30–40 líneas y módulos nuevos por debajo de unas 400 líneas. La cohesión y las pruebas prevalecen sobre el conteo.
- Prohibido “refactor” que solo mueva el mismo God object a otro archivo.

**Pruebas mínimas:** regresión existente completa; tests por caso de uso; reglas de dependencia Controller → Service → Repository; ausencia de DB/Filesystem/Mail directo en Controller y Blade.

**Aceptación:** responsabilidades identificables, dependencias inyectables y ninguna regresión funcional conocida.

### RA-12 — Certificación estática, entrega y validación manual pendiente

- Ejecutar sintaxis de todos los PHP/JS tocados.
- Ejecutar `php artisan test tests/Unit` con guardrail activo.
- Ejecutar las pruebas Feature aisladas que no requieran DB.
- Ejecutar `node --test tests/js/*.test.js` o el patrón real existente.
- Ejecutar PHPStan scoped de ambos módulos con memoria suficiente, sin baseline.
- Ejecutar `npm run build` y `git diff --check`.
- Buscar fugas de excepciones, Request genérico en mutaciones, handlers inline, sinks inseguros, CDN, URLs hardcodeadas y cambios DB.
- Entregar matriz RA-00…RA-12 con `DONE`, `BLOCKED` o `NOT_APPLICABLE`, evidencia y archivos.
- Entregar checklist manual autenticado para usuario/Codex: roles, cargos, áreas, compañías, estados, U/A/D, descargas, ZIP/PDF/voucher y comparación legacy.

**Aceptación:** `READY_FOR_MANUAL_REVIEW` solo si toda evidencia automatizable está verde. Cualquier prueba dinámica no ejecutada permanece declarada como pendiente.

## 7. Regla específica para IDs estáticos de exportación

Los IDs de usuario estáticos no pueden ser la única autorización. Durante RA-05/RA-10:

1. Buscar primero una capacidad, rol, permiso o señal de sesión ya existente y autoritativa.
2. Si existe, usar Policy basada en esa fuente.
3. Si no existe evidencia suficiente, no inventar tabla/columna ni ampliar acceso. Encapsular temporalmente la compatibilidad en una regla nombrada y probada, registrar la deuda como `BLOCKED_AUTHORITY_SOURCE` y conservar el comportamiento más restrictivo.

## 8. Definition of Done

- [ ] Todos los endpoints críticos tienen Request, autorización y Service explícitos.
- [ ] Ninguna lectura o descarga acepta un ID sin alcance contextual.
- [ ] Todas las mutaciones son atómicas, bloqueadas, idempotentes y fail-closed.
- [ ] El tipo 64 nunca continúa ante fallo de validación.
- [ ] Cero mensajes técnicos/SQL/path/stack trace expuestos al cliente.
- [ ] Las pruebas HTTP verifican cableado real sin PostgreSQL.
- [ ] Estados y U/A/D son coherentes, accesibles y seguros en ambos módulos.
- [ ] No hay rutas manuales evitables, handlers inline, CDN o sinks DOM inseguros en el alcance inventariado.
- [ ] Controladores, servicios, repositorios y módulos JS tienen responsabilidades cohesionadas.
- [ ] Tests, PHPStan, build, sintaxis y diff check pasan.
- [ ] Cero cambios o conexiones de BD y cero acciones Git remotas.
- [ ] La validación manual autenticada queda ejecutada por el usuario/Codex antes de afirmar cierre productivo.

## 9. Evidencia final obligatoria de Antigravity

La respuesta final debe contener:

1. rama y estado inicial/final;
2. tabla RA-00…RA-12 con resultado y evidencia;
3. archivos modificados, creados y eliminados con justificación;
4. comandos de verificación y salida resumida exacta;
5. pruebas no ejecutadas y motivo;
6. confirmación explícita de cero conexión/cambio de BD;
7. confirmación explícita de cero commit/push/merge;
8. riesgos residuales y checklist manual.

