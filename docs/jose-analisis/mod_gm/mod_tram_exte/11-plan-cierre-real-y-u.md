# Plan granular — Cierre real de Trámite Externo y U unificada

## Regla de ejecución

Antigravity debe ejecutar las unidades en orden, sobre el diff existente. No debe rehacer el módulo, tocar BD, instalar dependencias, crear archivos paralelos ni hacer operaciones Git de publicación.

## Tareas

### CEF-00 — Preflight y verdad documental

- Confirmar rama local `codex/back` y revisar `git status`.
- Leer specs 06 a 11 y `.agents/AGENTS.md`.
- Cambiar cualquier estado engañoso de `DONE` a `CHANGES_REQUIRED`.
- Ejecutar únicamente pruebas protegidas contra BD.

Aceptación: inventario de archivos tocados, comandos permitidos y baseline reproducible.

### CEF-01 — Pruebas de regresión que evidencien las brechas

- Agregar pruebas para nombres reales de inputs/archivos, email, campos de documento, PDF válido, Policy de lectura, lock dentro de transacción y destino multiárea.
- Las pruebas deben fallar primero con el código actual por la razón correcta.
- No aceptar pruebas que solo busquen strings o mockeen el resultado que pretenden demostrar.

### CEF-02 — Autorización transversal de lecturas

- Endurecer `ver` y aplicarlo a detalle, histórico, hoja de ruta, voucher, video, U y descargas.
- Retornar 404 para no autorizado/inexistente.
- Eliminar listas de usuarios hardcodeadas para permisos de exportación; usar el mecanismo de permisos existente.

### CEF-03 — Concurrencia y transacciones

- Mover lectura con lock y revalidación dentro de cada transacción.
- Exigir `in_esta = 1` al cerrar el movimiento y verificar filas afectadas.
- Eliminar fallback silencioso sin lock.
- Vincular idempotencia a usuario, acción y hash estable del payload.

### CEF-04 — Destinos de Derivar, Asignar y Multiárea

- Verificar cargo destino activo, área, compañía y regla institucional.
- Prohibir fallback área→cargo y “primer cargo” no documentado.
- Mantener atomicidad para múltiples expedientes/destinos.

### CEF-05 — Contrato de formularios y adjuntos

- Alinear Blade, FormData, FormRequests, Controller y Service.
- Procesar documento firmado y anexos con nombres únicos y arrays explícitos.
- Retirar campos que no correspondan a una operación.
- Integrar `GestorAdjuntos`, rollback y descarga privada.

### CEF-06 — Documento interno y correo de finalización

- Procesar honestamente `enviar_con_tramite`, tipo, correlativo provisional, folios y adjuntos, reutilizando servicios internos existentes.
- Resolver el correo institucional/ciudadano desde relaciones existentes.
- Encolar notificación post-commit mediante `NotificationCoreInterface`.
- No usar `MAX()+1` como reserva definitiva.

### CEF-07 — Consolidación ZIP/PDF real

- Mantener ZIP y manifiesto verificables.
- Reemplazar el falso `UNIDO-*.pdf` por fusión real con dependencias ya instaladas.
- Si no hay motor disponible, retirar el falso archivo y marcar `BLOCKED_USER_DECISION`; no instalar paquetes.
- Probar PDF con parser/lector disponible y validar páginas.

### CEF-08 — Datos simulados, videos, requisitos y errores

- Implementar o retirar `has_video`, silencio, días acumulados y requisitos.
- No convertir excepciones en arrays vacíos.
- Normalizar respuestas seguras con correlation ID.

### CEF-09 — Bandejas e histórico paginado

- Probar inclusión/exclusión de las 10 bandejas.
- Acotar `Prestados`, `Todos`, `Notificados`, `Archivo` e `Inventariar` por permisos/custodia.
- Paginar histórico en servidor y conservar el contrato HIST-001.

### CEF-09A — Conteos completos de bandeja

- Implementar `12-spec-conteos-y-mejoras-transversales.md` en Interno y Externo.
- Mostrar badge en cada bandeja autorizada, usando el mismo scope del listado.
- Separar total global lateral y total filtrado de cabecera.
- Refrescar conteos después de mutaciones y cambios de cargo.

### CEF-09B — Activar realmente la arquitectura migrada de Interno

- Sustituir SQL interpolado de `CorreoInternoController::tabla()` por FormRequest + Repository.
- Delegar histórico, conteos y casos especiales sin perder comportamiento.
- Inyectar Policy/repositorio y usar `SessionKeys`.
- Añadir pruebas que invoquen el flujo real de ruta/controlador, no solo el repositorio aislado.

### CEF-10 — Backend U compartido

- Implementar DTO, servicio/repositorio y endpoint lazy de `10-spec-u-usuarios-interno-externo.md`.
- Distinguir actor, asignados actuales y receptor real.
- Deduplicar, ordenar, buscar y paginar.
- Corregir el caso Interno donde origen y destino comparten cargo.

### CEF-11 — Frontend U compartido

- Crear una sola pieza reutilizable para Interno y Externo.
- Implementar disparador con contador, preview de tres y modal completo.
- Evitar clipping, listeners duplicados y `innerHTML` inseguro.
- Implementar focus trap, Escape, retorno de foco, loading y error.

### CEF-11A — Resiliencia y coherencia frontend

- Retirar Tailwind CDN/runtime y dependencias remotas evitables; usar Vite.
- Corregir el script inválido de Interno y consolidar listeners.
- Implementar cancelación de respuestas AJAX obsoletas.
- Eliminar `select-none` global, estados de contraste defectuosos y errores representados como vacío.
- Reducir `innerHTML` con datos dinámicos y asegurar texto, atributos y URLs por contexto.

### CEF-12 — Calidad estática del módulo

- Ejecutar PHPStan sobre el alcance completo realmente modificado.
- Corregir problemas reales sin ignores ni baseline.
- Separar con evidencia los errores preexistentes genuinamente fuera de alcance.

### CEF-13 — Certificación aislada

- Tests happy/error/border y 401/403/404/409/422.
- `route:list`, PHPStan, `php -l`, pruebas unitarias, pruebas de contrato UI y `git diff --check`.
- No ejecutar suites que puedan abrir PostgreSQL.
- No ejecutar build si genera artefactos versionados sin revisar y limpiar de forma segura.

### CEF-14 — Entrega para revisión humana

- Estado máximo: `READY_FOR_REVIEW`.
- Reportar archivos, decisiones, comandos/resultados, bloqueos y pruebas manuales pendientes.
- No hacer commit, push, merge ni tocar remotos.
- La aceptación final corresponde al usuario y a la auditoría posterior de Codex.

## Prohibiciones de cierre

No marcar una tarea `DONE` porque:

- compila;
- una prueba con mocks pasa;
- existe un botón o modal;
- existe un archivo llamado PDF;
- el error fue capturado y convertido en lista vacía;
- el código tiene un comentario que afirma seguridad o paridad.

