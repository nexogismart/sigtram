# SDD de cierre — Correo Externo

## Estado

`APPROVED_FOR_IMPLEMENTATION — CHANGES_REQUIRED`

Esta especificación reemplaza cualquier declaración previa de paridad o cierre que contradiga la auditoría independiente del 10 de octubre de 2026. La implementación actual es una base de trabajo; no debe reescribirse completa ni considerarse terminada.

## Objetivo

Completar la migración de Correo Externo sobre Laravel, conservando el comportamiento útil del legacy y mejorándolo en seguridad, rendimiento, accesibilidad, mantenibilidad y trazabilidad, sin modificar esquema, catálogos ni datos de PostgreSQL.

## Restricciones P0

1. Trabajar únicamente en la rama local `codex/back`.
2. No ejecutar conexiones, consultas, comandos, seeders ni pruebas contra ninguna base de datos.
3. No crear ni ejecutar migraciones, DDL, DML, funciones, triggers, índices o scripts de corrección de datos.
4. No ejecutar `commit`, `push`, `merge`, `rebase`, `reset`, `clean` ni modificar remotos.
5. No instalar ni actualizar dependencias sin autorización expresa.
6. No borrar ni sobrescribir cambios locales ajenos.
7. Si una solución necesita alterar BD o dependencias, marcar `BLOCKED_USER_DECISION`, documentar la necesidad y continuar con las demás tareas independientes.
8. Los IDs del enum `EstadoTramiteExterno` son el contrato histórico confirmado por el usuario. Los nombres visibles deben provenir del catálogo cuando estén disponibles; `label()` será solo fallback técnico.
9. Ninguna tarea puede marcarse `DONE` únicamente por compilar. Debe aportar prueba automatizada o evidencia estática reproducible.
10. El estado final de Antigravity será como máximo `READY_FOR_REVIEW`. La aprobación final pertenece al usuario y a una auditoría posterior de Codex.

## Hallazgos vinculantes

### CE-P0-000 — Compatibilidad con el esquema existente

La implementación incorporó tablas y columnas sin evidencia en el sistema real. La ejecución confirmó `SQLSTATE[42P01]` para `sistema.m_tramite`. También existen referencias sospechosas a `d_tramite`, `nu_dto_trmte` y `nu_flio`, mientras los modelos y controladores funcionales evidencian `p_dto_trmtes`, `nu_expe_todo` y `nu_foli_trmte`.

Criterio de aceptación:

- Leer y cumplir `08-contrato-esquema-existente.md` antes de corregir cualquier consulta.
- Inventariar todas las tablas y columnas usadas por el repositorio, no solo la primera que produzca error.
- Cada identificador debe estar respaldado por modelo, código funcional, legacy o inventario manual del usuario.
- No crear tablas, vistas, aliases persistentes ni migraciones para adaptar la BD al código nuevo.
- Corregir el código para usar el esquema existente.
- Añadir pruebas estáticas que impidan reintroducir identificadores inventados.
- Un mock de Query Builder no cuenta como evidencia de compatibilidad del esquema.

### CE-P0-001 — El módulo impide cargar Laravel

`CorreoExternoController::vistaDesdeMenu()` redefine con visibilidad privada un método protegido del controlador base y omite el valor por defecto del segundo parámetro.

Criterio de aceptación:

- `php artisan route:list --name=mod_gm.segi_expedi` termina con código 0.
- PHPStan no reporta incompatibilidad de herencia.
- No se duplica el método si el controlador base ya resuelve la vista correctamente.

### CE-P0-002 — Rutas a métodos inexistentes

Las rutas `detalle` y `semilla` apuntan a `detalle()` y `obtenerSemilla()`, que no existen.

Criterio de aceptación:

- Toda ruta del módulo referencia una clase y método existentes.
- `detalle` devuelve el contrato consumido por el modal, con autorización por recurso.
- `semilla` reproduce de forma segura el comportamiento requerido por el legacy o se elimina junto con todos sus consumidores si se demuestra que no corresponde al alcance.
- No se inventan correlativos con `MAX()+1`; se reutiliza el mecanismo existente. Si no puede identificarse sin BD, marcar solamente esa capacidad como `BLOCKED_USER_DECISION`.

### CE-P0-003 — IDOR y aislamiento insuficiente

Las lecturas de histórico, hoja de ruta y descarga consolidada no están acotadas por compañía/custodia. El retorno de préstamo modifica un expediente sin Policy. La descarga individual solo limita por compañía y no por relación autorizada.

Criterio de aceptación:

- Toda lectura y mutación valida autenticación, compañía, cargo/área activa y relación de custodia o permiso administrativo explícito.
- `historial`, `detalle`, `hojaRuta`, descarga individual, descarga total, voucher e impresión no revelan la existencia de recursos ajenos.
- `prestar`, `retornarPrestamo`, `archivar` y `redirigir` aplican Policy y estados permitidos.
- Los repositorios reciben el contexto autorizado; no existen búsquedas sensibles únicamente por ID.
- Pruebas negativas cubren otra compañía, otro cargo, recurso inexistente, estado incorrecto y rol insuficiente.

### CE-P0-004 — Contrato de bandeja incompatible

El servidor devuelve `meta`, mientras la vista consume `pagination`. La vista espera campos que el repositorio no devuelve, entre ellos `nu_expe_todo`, `orig_doc`, `dc_estdo_trmte`, datos de silencio y video.

Criterio de aceptación:

- Definir un DTO/Resource canónico de fila de bandeja.
- El JSON y la vista usan exactamente los mismos nombres y tipos.
- La paginación informa `current_page`, `per_page`, `total` y `last_page` bajo un único nodo documentado.
- Cambiar de bandeja o filtros reinicia la página a 1.
- La pantalla nunca muestra columnas vacías por discrepancias de contrato.

### CE-P1-001 — Filtros y bandejas incompletos

El filtro de estado se recibe pero no se aplica; los criterios del frontend no coinciden con los valores interpretados por el repositorio. Faltan bandejas visibles y `inventariar` cae en el caso por defecto.

Criterio de aceptación:

- Implementar las 10 bandejas canónicas: Entrada, Recibidos, Salida, Finalizados, Todos, Sin finalizar, Archivo, Notificados, Prestados e Inventariar.
- Rechazar bandejas y criterios desconocidos con 422; nunca convertirlos silenciosamente en `Todos`.
- Aplicar descripción, criterio, estado, desde y hasta con bindings.
- Validar rango de fechas y límites de `per_page`.
- Cada bandeja tiene prueba de alcance y exclusión.

### CE-P1-002 — Formatos de fecha incompatibles

Los controles `datetime-local` envían `YYYY-MM-DDTHH:mm`, pero varios FormRequests exigen `Y-m-d H:i:s`.

Criterio de aceptación:

- Normalizar en una sola capa o enviar el formato esperado desde frontend.
- Todos los flujos comparten zona horaria `America/Lima` y validación consistente.
- Los errores 422 conservan mensajes por campo consumibles por la UI.

### CE-P1-003 — Destinos y envío multiárea incorrectos

Derivar mezcla IDs de área y cargo. Enviar procesa únicamente la primera área y delega indebidamente en `derivar()`.

Criterio de aceptación:

- Diferenciar explícitamente `area_id` y `cargo_id` en contratos, DTOs y consultas.
- Validar que el destino está activo y pertenece a la compañía permitida.
- Envío multiárea procesa todos los destinos válidos y usa la transición `enviar`, no `derivar`.
- La operación es atómica o define y prueba una semántica de resultados parciales deliberada.
- No se crean duplicados para destinos repetidos.

### CE-P1-004 — Datos solicitados por la UI ignorados

Adjuntos, documento vinculado, tipo documental, número, folios y preferencia de correo se envían pero el backend no los procesa.

Criterio de aceptación:

- Cada campo visible se implementa completamente o se retira hasta que exista soporte real.
- Adjuntos usan `GestorAdjuntos`, storage privado, nombre UUID, MIME real, tamaño máximo y rollback de archivos si falla la transacción.
- Los FormRequests incluyen reglas condicionales para documento vinculado, folios y adjuntos.
- Nunca se confía en extensión, nombre original o MIME declarado por el navegador.
- El documento vinculado conserva su trazabilidad en las estructuras existentes, sin cambios de BD.

### CE-P1-005 — Finalización y notificación incompletas

La implementación registra un mensaje en log, pero no realiza la notificación solicitada y el frontend no envía el campo `correo` esperado.

Criterio de aceptación:

- Alinear el contrato frontend/backend.
- Si el usuario solicita notificación, usar la infraestructura de notificaciones/colas existente después del commit.
- No enviar correo dentro de la transacción.
- Fallar de forma observable y reintentable sin revertir una finalización ya confirmada.
- No realizar envíos reales durante pruebas.
- Si la infraestructura necesaria no existe y exigiría BD/dependencias, marcar exclusivamente la notificación como `BLOCKED_USER_DECISION` y no simular éxito.

### CE-P1-006 — Histórico HIST-001 incompleto y N+1

El histórico ejecuta consultas de usuarios y auditorías dentro de un bucle; además no entrega consistentemente el nombre del estado esperado por la vista.

Criterio de aceptación:

- Mantener el diseño legacy acordado: columnas de movimiento más U, A y D.
- Columna U: popover con usuario origen y todos los usuarios destino; abre por hover, foco, clic, Enter/Espacio y cierra con Escape restaurando foco.
- Columna A: adjunto privado y auditoría visible solo a usuarios autorizados.
- Columna D: voucher/detalle autorizado.
- Resolver usuarios y descargas mediante consultas masivas, no por movimiento.
- Paginar histórico cuando pueda crecer sin límite, conservando el formato visual.
- Usar el catálogo para nombres visibles de estado y el enum para reglas.

### CE-P1-007 — Descarga consolidada incompleta

El ZIP contiene adjuntos y manifiesto, pero no genera el `UNIDO-*.pdf` requerido ni registra adecuadamente la descarga consolidada.

Criterio de aceptación:

- Autorizar primero el expediente completo.
- Generar ZIP temporal fuera de `public` con nombres internos saneados y `MANIFEST.json` con SHA-256.
- Generar `UNIDO-<expediente>.pdf` combinando únicamente PDFs válidos cuando la dependencia ya exista.
- Archivos no PDF permanecen separados en el ZIP.
- Registrar auditoría de la descarga consolidada mediante estructuras existentes.
- Eliminar temporales después de responder, incluso ante errores.
- Si falta una dependencia de merge PDF, no instalarla: marcar `BLOCKED_USER_DECISION` con alternativas concretas.

### CE-P1-008 — Concurrencia e idempotencia insuficientes

La validación ocurre antes de la transacción y no hay bloqueo del movimiento vigente. Las claves generadas por defecto pueden reutilizar resultados para una operación legítima posterior.

Criterio de aceptación:

- Leer y bloquear el movimiento vigente dentro de la transacción mediante el mecanismo soportado por el repositorio.
- Revalidar estado y autorización después del lock.
- Exigir una clave de idempotencia por intención o generar una UUID por envío en frontend.
- Asociar la clave a usuario, acción y hash del payload.
- Una misma clave con payload diferente produce 409.
- Verificar que la cantidad de expedientes recuperados coincide con los IDs únicos solicitados; no procesar subconjuntos silenciosamente.

### CE-P1-009 — Errores HTTP y exposición de información

Los controladores pasan mensajes de excepciones como texto público y convierten autorización, validación y conflicto en 500.

Criterio de aceptación:

- 401 para no autenticado, 403 para no autorizado, 404 sin enumeración, 409 para conflicto/idempotencia, 422 para validación y 500 genérico para fallos inesperados.
- Mensajes internos, SQL, rutas y stack trace solo aparecen en logs con correlation ID.
- La UI trata respuestas no JSON y errores de red sin quedar bloqueada en loading.

### CE-P1-010 — Detalle, video, hoja de ruta e impresión

`solicitudVideo()` no devuelve el `data` consumido por la UI; detalle no existe; hoja de ruta no autoriza; impresión necesita el mismo alcance que la bandeja.

Criterio de aceptación:

- Implementar contratos completos con DTOs/Resources y Policies.
- Usar rutas nombradas en Blade; no concatenar rutas absolutas.
- Hoja de ruta, impresión y detalle conservan paridad de datos con el legacy.
- Los reportes respetan filtros, compañía, cargo y límites explícitos.

### CE-P1-011 — Accesibilidad y coherencia visual

Criterio de aceptación:

- Mantener tipografía, espaciado y estados activos coherentes con el módulo nuevo y con la referencia legacy acordada.
- Los botones de bandeja nunca quedan con fondo y texto indistinguibles durante clic/foco/carga.
- Modales implementan foco inicial, trampa de foco, Escape, cierre explícito y retorno de foco.
- Popovers U/A/D funcionan con teclado y lector de pantalla.
- Iconos tienen nombre accesible y los estados no dependen solo del color.
- Loading evita doble envío sin ocultar permanentemente acciones.

### CE-P1-012 — Arquitectura y rendimiento

Criterio de aceptación:

- Controlador delgado, sin consultas SQL de dominio.
- Repository concentra consultas y scoping; Service concentra transacciones; Policy concentra autorización; FormRequest valida entradas.
- No duplicar `segi_expedi` y `segi_bandeja`; establecer una vista canónica y retirar referencias huérfanas sin borrar archivos dudosos.
- No incorporar consultas por fila.
- Índices deseables se documentan como recomendación manual; nunca se crean.

## Contratos mínimos

### Respuesta de bandeja

```json
{
  "status": "success",
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 0,
    "last_page": 1
  }
}
```

### Respuesta de error

```json
{
  "status": "error",
  "message": "Mensaje público seguro",
  "correlation_id": "uuid-solo-si-corresponde",
  "errors": {}
}
```

### Reglas de selección masiva

- IDs enteros positivos, únicos y con máximo documentado.
- Todo o nada para autorización y precondiciones.
- Ningún ID omitido silenciosamente.
- El contexto activo se obtiene desde sesión autenticada y se valida contra asociaciones vigentes.

## Definition of Done

Correo Externo solo puede quedar `READY_FOR_REVIEW` cuando:

1. Laravel carga y todas las rutas del módulo resuelven métodos existentes.
2. No quedan hallazgos P0 abiertos.
3. Las 10 bandejas, filtros, paginación y acciones tienen contratos coherentes.
4. Cada endpoint sensible tiene pruebas de autorización negativa.
5. No existen N+1 conocidos en bandejas o histórico.
6. Las pruebas unitarias y feature aisladas de BD pasan en una sola ejecución con OPcache CLI deshabilitado.
7. PHPStan del alcance termina sin errores.
8. `php -l`, `git diff --check` y build de frontend terminan correctamente.
9. No se creó ni ejecutó ninguna migración o script SQL.
10. El reporte final enumera archivos, comandos, resultados, limitaciones y pruebas manuales pendientes.

