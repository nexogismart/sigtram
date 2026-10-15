# Auditoría final de brechas reales — Trámite Externo

## Estado vinculante

`CHANGES_REQUIRED — NO APTO PARA DECLARAR PARIDAD NI CIERRE`

Fecha de corte: 2026-10-10. Rama inspeccionada: `codex/back`.

Este documento corrige cualquier afirmación anterior de `DONE`, `PASS`, “100 %” o “1000 %” que no esté respaldada por comportamiento real. Las pruebas con mocks demuestran estructura aislada; no prueban por sí solas contratos Blade/HTTP, compatibilidad con PostgreSQL, concurrencia, autorización integral ni contenido válido de los archivos generados.

## Evidencia reproducida sin base de datos

| Control | Resultado |
|---|---|
| Suite unitaria focalizada | 57 tests, 272 assertions, PASS |
| Rutas `mod_gm.segi_expedi` | 19 rutas registradas, PASS |
| PHPStan acotado solo a las clases nuevas principales | Sin errores en el corte anterior |
| PHPStan sobre el directorio completo `mod_tram_exte` | 36 errores, FAIL |
| PostgreSQL | No conectado, no consultado, no modificado |
| Pruebas dinámicas con datos anonimizados | No ejecutadas |
| Prueba visual interactiva | No ejecutada |

## Hallazgos P0

### CEF-P0-01 — Contrato de adjuntos roto

La vista envía `fle_adjunto_pdf`, `fle_adjunto1`…`fle_adjunto4`; `EnviarExpedienteRequest` acepta únicamente `adjunto`, y el controlador obtiene solamente `file('adjunto')`. Derivar, asignar y finalizar muestran controles de archivos que sus Requests y servicios no procesan.

Criterios de aceptación:

- Definir un contrato único y documentado para documento firmado y anexos, reutilizable por las operaciones que realmente los admiten.
- Todo control visible debe procesarse completamente o retirarse de la interfaz; queda prohibida la UI decorativa.
- Validar cantidad, tamaño, extensión y MIME real mediante `GestorAdjuntos`.
- Guardar en storage privado y vincular únicamente mediante columnas existentes verificadas.
- Ante rollback, eliminar únicamente los archivos creados por la operación fallida.
- Agregar pruebas que construyan el mismo `FormData` del navegador y demuestren que los archivos llegan al servicio.

### CEF-P0-02 — Bloqueo de fila fuera de la transacción

`obtenerMovimientosVigentesParaBloqueo()` usa `lockForUpdate()`, pero los servicios consultan primero y abren `DB::transaction()` después. En PostgreSQL el bloqueo no protege la escritura posterior si no pertenece a la misma transacción.

Criterios de aceptación:

- Abrir la transacción antes de leer el movimiento vigente.
- Ejecutar `SELECT ... FOR UPDATE`, revalidar compañía, custodia, estado y vigencia dentro de esa transacción y recién después mutar.
- No capturar `Throwable` para degradar silenciosamente a una lectura sin lock.
- Actualizar el movimiento anterior con precondición `in_esta = 1` y verificar exactamente una fila afectada.
- Probar conflicto/replay y transición concurrente con dobles deterministas, sin conectar a BD.

### CEF-P0-03 — Resolución insegura del destino multiárea

El envío multiárea toma el primer cargo activo del área. Si no existe, utiliza el ID del área como si fuera ID de cargo. Esto puede dirigir un expediente a un cargo arbitrario o inválido.

Criterios de aceptación:

- Resolver el cargo receptor mediante la regla legacy/institucional ya existente y verificable.
- Nunca convertir un ID de área en ID de cargo.
- Si un área posee más de un cargo elegible y no existe regla inequívoca, exigir selección explícita o marcar `BLOCKED_USER_DECISION`.
- Verificar que área, cargo y compañía correspondan y estén activos.
- Procesar todos los destinos atómicamente o no procesar ninguno.

### CEF-P0-04 — Autorización de lectura demasiado amplia

`TramiteExternoPolicy::ver()` solo compara compañía. Salvo que exista una regla de visibilidad institucional global documentada, un usuario de la misma compañía puede enumerar IDs y consultar detalle, histórico u hoja de ruta ajenos.

Criterios de aceptación:

- Autorizar por compañía y relación con el expediente: cargo/área de origen o destino, custodia vigente, participación histórica o permiso administrativo explícito.
- Aplicar la misma regla a detalle, histórico, hoja de ruta, voucher, usuarios U, videos y descargas.
- Responder 404 ante recurso inexistente o no visible para no facilitar enumeración.
- Añadir matriz negativa: otra compañía, misma compañía sin relación, otro cargo, otro área, estado no permitido y rol insuficiente.

### CEF-P0-05 — `UNIDO-*.pdf` ficticio

`ConsolidacionExpedienteService` agrega una cadena que comienza con `%PDF-1.4`; no fusiona páginas ni genera un PDF válido. La prueba actual tampoco inspecciona `UNIDO-*.pdf`.

Criterios de aceptación:

- No crear archivos de apariencia PDF que no puedan abrirse y recorrerse con un lector PDF.
- Fusionar solo PDF válidos y preservar los anexos no PDF por separado dentro del ZIP.
- Incluir en `MANIFEST.json` orden, hash, estado y causa de exclusión por archivo.
- Probar apertura, cantidad de páginas y contenido mínimo del PDF resultante.
- Si no existe motor de unión entre las dependencias instaladas, no instalar nada: marcar exclusivamente esta capacidad `BLOCKED_USER_DECISION` y retirar el falso PDF.

### CEF-P0-06 — Finalización, correo y documento vinculado desconectados

La vista envía `enviar_correo`, `enviar_con_tramite`, `doc_tipo`, `tramite_nro`, `folios` y archivos. El backend de finalizar espera opcionalmente `correo` y no procesa el resto. El texto de interfaz promete enviar al correo ciudadano, pero esa dirección no se resuelve.

Criterios de aceptación:

- Definir la semántica de `enviar_correo` como booleano/enumerado validado.
- Resolver el correo desde el expediente usando relaciones existentes; no confiar en una dirección arbitraria del request.
- Procesar de forma transaccional el documento interno vinculado o retirar el control hasta que exista implementación completa.
- Despachar mediante `NotificationCoreInterface` después del commit.
- Si la notificación falla, registrar el fallo sin revertir la finalización y devolver un estado honesto.

### CEF-P0-07 — Interno conserva SQL interpolado y no usa el repositorio migrado

`CorreoInternoController::tabla()` concatena filtros y contexto en SQL, ejecuta `DB::select()` y replica reglas que ya deberían residir en `TramiteInternoRepository`. Esto contradice la arquitectura declarada y permite divergencia entre listado, conteo y pruebas del repositorio.

Criterios de aceptación:

- Reemplazar el Request genérico por `ListarBandejaInternaRequest`.
- Delegar todas las bandejas a `TramiteInternoRepositoryInterface` con bindings/Query Builder.
- Preservar casos de referencia, tipo 64 y contabilidad con pruebas de contrato.
- Eliminar SQL interpolado, lógica de consulta y resolución de Policy mediante `app()` desde el controlador.
- Certificar que el código ejercitado por los tests es el utilizado por las rutas reales.

## Hallazgos P1

### CEF-P1-01 — Capacidades representadas con valores constantes

`nu_tiem_sile_adcnal`, `dias_acumulados` y `has_video` se retornan siempre como `0`, `0` y `false`; `requisitos` siempre es `[]`. Un error de consulta de video se transforma silenciosamente en lista vacía.

- Implementar desde fuentes existentes verificadas o retirar/inhabilitar la capacidad indicando “No disponible”.
- No confundir error técnico con ausencia de información.
- Registrar internamente el error con correlation ID y retornar respuesta segura.

### CEF-P1-02 — Correlativo preliminar con `MAX()+1`

La consulta de semilla calcula `MAX(nu_smlla_intrno)+1`. Puede mostrarse como vista previa, pero no puede reservar ni confirmar el correlativo.

- La asignación definitiva debe ocurrir dentro de la transacción y bajo el mecanismo institucional existente.
- El número mostrado por la UI debe marcarse como provisional hasta confirmar.
- Si el mecanismo real no puede verificarse sin BD, usar `BLOCKED_USER_DECISION`; nunca afirmar que está resuelto.

### CEF-P1-03 — Histórico no paginado y payload creciente

El histórico externo carga todos los movimientos y todos los usuarios activos de sus cargos. Esto crecerá con expedientes largos y cargos numerosos.

- Paginar movimientos en servidor con opciones 10/20/50.
- Cargar el detalle completo de U bajo demanda por movimiento.
- El histórico inicial solo entrega contador y hasta tres usuarios de vista previa.
- Prohibidas consultas por fila; agrupar consultas o usar endpoint lazy paginado.

### CEF-P1-04 — Alcance de bandejas no demostrado

`Prestados` filtra primordialmente por estado y `Todos` por compañía/vigencia. Varias bandejas no demuestran aislamiento por cargo/área. El comportamiento debe compararse contra el contrato legacy antes de aprobarse.

- Cada bandeja necesita pruebas de inclusión y exclusión por compañía, cargo, área, estado y vigencia.
- `Todos` no significa visibilidad de todos los funcionarios salvo permiso explícito.
- No silenciar bandejas desconocidas ni convertirlas a una bandeja permisiva.

### CEF-P1-05 — Errores estáticos fuera del subconjunto certificado

PHPStan sobre el directorio completo de Trámite Externo reporta 36 errores en controladores vecinos. Certificar solo las clases nuevas no permite declarar sano el módulo completo.

- Resolver los errores del alcance funcional tocado, sin ignores, baseline ni casts ficticios.
- Separar claramente errores preexistentes fuera de alcance, con archivo y línea, si realmente no pertenecen al módulo migrado.
- La certificación final debe publicar el comando exacto y su código de salida.

### CEF-P1-06 — Conteos laterales incompletos e inconsistentes

Externo cuenta únicamente Entrada y Recibidos al renderizar. Interno no muestra conteos laterales y solo actualiza el total de la bandeja activa.

- Implementar el contrato de `12-spec-conteos-y-mejoras-transversales.md`.
- Mostrar total para todas las bandejas autorizadas, separando total global y total filtrado.
- Actualizar conteos después de mutaciones y cambios de contexto sin consultas redundantes.

### CEF-P1-07 — Assets/runtime y JavaScript frágil

Las vistas usan CDN runtime y la vista Interna coloca código inline dentro de una etiqueta `script` con `src`. También existen listeners repetidos, `select-none` global y abundante renderizado `innerHTML`.

- Migrar al pipeline Vite existente y corregir la inicialización de scripts.
- Consolidar listeners y eliminar handlers inline cuando contengan datos dinámicos.
- Permitir seleccionar/copiar información y proteger texto/atributos/URLs por contexto.
- Implementar cancelación de solicitudes obsoletas al cambiar filtros o bandeja.

## Definition of Done vinculante

No se permite `READY_FOR_REVIEW` hasta que:

- todos los P0 estén `DONE` o uno esté explícitamente `BLOCKED_USER_DECISION` sin simulación;
- el frontend y cada FormRequest/Controller/Service compartan exactamente el mismo contrato;
- las mutaciones bloqueen y revaliden dentro de la misma transacción;
- las lecturas sensibles superen pruebas negativas de autorización;
- el PDF consolidado sea real o la capacidad quede honestamente bloqueada;
- histórico y U sean paginados/escalables;
- pruebas nuevas fallen contra el código anterior y pasen con la corrección;
- PHPStan, rutas, pruebas aisladas, sintaxis y `git diff --check` terminen correctamente para el alcance declarado;
- el usuario ejecute posteriormente las pruebas manuales con su BD y navegador.

