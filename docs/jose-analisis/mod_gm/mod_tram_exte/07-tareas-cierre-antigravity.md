# Plan granular de cierre — Antigravity IDE

## Regla de ejecución

Ejecutar las unidades en orden. No reiniciar la migración ni reemplazar archivos completos sin necesidad. Antes de cada unidad, releer el diff actual porque hay cambios locales compartidos.

Estados permitidos: `READY`, `IN_PROGRESS`, `DONE`, `BLOCKED_USER_DECISION`, `READY_FOR_REVIEW`.

## CE-00 — Preflight y guardas

Estado inicial: `READY`

- Confirmar rama `codex/back`.
- Registrar `git status --short` sin limpiar cambios.
- Verificar que el harness bloquea conexiones reales a BD.
- Inventariar rutas, controlador, servicios, repositorios, Requests, Policies, vistas y pruebas de Correo Externo.
- Ejecutar baseline sin BD: route list, PHPStan acotado, lint, unit tests y diff check.

Aceptación: evidencia del baseline y ninguna modificación de BD/Git remoto.

## CE-00A — Reconciliar el esquema real sin tocar BD

Dependencia: CE-00.

- Leer `08-contrato-esquema-existente.md`.
- Inventariar todas las llamadas `table`, `join`, `leftJoin`, `select`, `where` y `orderBy` del repositorio.
- Contrastar cada tabla y columna con modelos, controladores funcionales y consultas legacy.
- Reemplazar `m_tramite` por la cabecera externa real `p_dto_trmtes` y corregir sus campos/aliases.
- Eliminar `d_tramite` salvo que aparezca evidencia real aportada por el usuario.
- Corregir `nu_dto_trmte`/`nu_flio` a los campos externos evidenciados cuando corresponda.
- Revisar el resto de identificadores sospechosos; no detenerse al resolver el primer `42P01`.
- Agregar una prueba estática de contrato del esquema.

Aceptación: cero identificadores sin evidencia y ninguna propuesta de crear tablas para satisfacer el código.

## CE-01 — Recuperar arranque y consistencia de rutas

Dependencia: CE-00A.

- Corregir la incompatibilidad de `vistaDesdeMenu` reutilizando el controlador base.
- Implementar o retirar coherentemente `detalle` y `obtenerSemilla`.
- Agregar prueba que compruebe que cada acción registrada existe.

Aceptación: route list verde y ninguna ruta muerta.

## CE-02 — Contratos DTO/Resource y paginación

Dependencia: CE-01.

- Definir el contrato canónico de fila, detalle, histórico y paginación.
- Alinear aliases SQL, JSON y Alpine.
- Aplicar `per_page`, filtros y reinicio de página.
- Rechazar enums de bandeja/criterio desconocidos.

Aceptación: tests contractuales y navegación de páginas verificable sin BD real mediante mocks/fakes.

## CE-03 — Cerrar IDOR y autorización transversal

Dependencia: CE-02.

- Aplicar scoping por compañía, cargo/área, custodia y rol a todas las lecturas y mutaciones.
- Incorporar Policy a retorno de préstamo, descargas, detalle, histórico, hoja de ruta, voucher, impresión y ZIP.
- Evitar diferencias observables entre ID inexistente y ajeno.

Aceptación: matriz de pruebas negativas completa.

## CE-04 — FormRequests y errores HTTP

Dependencia: CE-03.

- Crear Requests faltantes para tabla, histórico, detalle, enviar, retornar préstamo, descargas lógicas y semilla si permanece.
- Normalizar fechas `datetime-local` y zona horaria.
- Mapear excepciones a 401/403/404/409/422/500 sin revelar internals.
- Eliminar mensajes públicos derivados directamente de excepciones.

Aceptación: pruebas de validación y seguridad de salida.

## CE-05 — Repositorio y rendimiento

Dependencia: CE-04.

- Sacar consultas de dominio del controlador.
- Implementar consultas masivas de histórico U/A/D.
- Resolver aliases y nombres de estado desde catálogo.
- Eliminar N+1 y documentar cantidad esperada de consultas.
- Paginar listas e histórico de volumen no acotado.

Aceptación: tests de construcción/contrato y evidencia de ausencia de consultas en bucles.

## CE-06 — Entrada y Recepcionar

Dependencia: CE-05.

- Validar conjunto completo de IDs.
- Autorizar y bloquear movimientos vigentes dentro de transacción.
- Normalizar fecha, observación e idempotencia.
- Conservar histórico sin duplicados.

Aceptación: éxito, doble clic, estado inválido, ID ajeno y carrera simulada.

## CE-07 — Recibidos: Derivar, Asignar y Enviar

Dependencia: CE-06.

- Separar área y cargo.
- Validar destinos activos y permitidos.
- Implementar envío real a todos los destinos y transición `enviar`.
- Procesar documento vinculado, folios y adjuntos con rollback seguro.

Aceptación: pruebas por flujo, múltiples destinos, duplicados, rollback y adjunto inválido.

## CE-08 — Finalizar y notificar

Dependencia: CE-07.

- Alinear campos de correo y opción de notificación.
- Finalizar transaccionalmente.
- Despachar notificación después del commit mediante infraestructura existente.
- No fingir envío si la infraestructura está bloqueada.

Aceptación: pruebas de finalización con/sin notificación y fallo posterior al commit.

## CE-09 — Salida, devolver y redirigir

Dependencia: CE-08.

- Completar devolución por el emisor autorizado.
- Redirigir con rol y destino válidos, preservando trazabilidad.
- Proteger contra cambio concurrente del estado.

Aceptación: pruebas de custodia, rol, estado y concurrencia.

## CE-10 — Finalizados, Notificados, Archivo e Inventario

Dependencia: CE-09.

- Implementar las bandejas separadas y sus filtros reales.
- Completar Archivar, Prestar y Retornar con Policy estricta.
- Implementar Inventariar según la especificación existente, sin caer en `Todos`.
- Mantener IDs estables del enum y nombres visibles dinámicos.

Aceptación: las 10 bandejas tienen prueba y no existe default permisivo.

## CE-11 — Histórico legacy mejorado

Dependencia: CE-10.

- Preservar forma visual acordada del histórico.
- Completar U/A/D con autorización y accesibilidad.
- Corregir estado, áreas, duración y destinatarios.
- Paginar sin perder interacciones.

Aceptación: pruebas de contrato y checklist de teclado/foco.

## CE-12 — Adjuntos, ZIP y UNIDO PDF

Dependencia: CE-11.

- Integrar `GestorAdjuntos` en flujos de escritura.
- Autorizar y auditar descargas.
- Consolidar ZIP, manifiesto, checksums y `UNIDO-*.pdf` cuando exista soporte instalado.
- Limpiar temporales en éxito y error.

Aceptación: tests con storage fake y PDFs de fixture mínimos. Si falta dependencia, registrar `BLOCKED_USER_DECISION` sin instalar nada.

## CE-13 — UI, accesibilidad y resiliencia

Dependencia: CE-12.

- Corregir estados activos/focus de navegación.
- Usar rutas nombradas y eliminar URLs concatenadas.
- Implementar modales y popovers accesibles.
- Manejar 401/403/409/422/500, HTML inesperado, red y loading.
- Mantener diseño consistente y responsive.

Aceptación: build verde y checklist visual/manual documentado.

## CE-14 — Certificación aislada

Dependencia: CE-13.

- Ejecutar todos los tests del módulo en un solo comando con OPcache CLI deshabilitado.
- Ejecutar PHPStan con memoria suficiente, lint, route list, build y `git diff --check`.
- Revisar archivos nuevos y eliminar solo basura creada por esta ejecución.
- Confirmar que no hay migraciones, SQL ejecutado, commits ni push.
- Actualizar `odd-task.md` con evidencia real y pendientes manuales.

Aceptación: estado máximo `READY_FOR_REVIEW`; nunca `DONE` o “100 % validado” sin revisión independiente y pruebas con entorno autorizado.

## Orden obligatorio

`CE-00 → CE-00A → CE-01 → CE-02 → CE-03 → CE-04 → CE-05 → CE-06 → CE-07 → CE-08 → CE-09 → CE-10 → CE-11 → CE-12 → CE-13 → CE-14`

Si una unidad queda bloqueada, continuar únicamente con unidades que no dependan de la capacidad bloqueada y documentar el impacto exacto.

