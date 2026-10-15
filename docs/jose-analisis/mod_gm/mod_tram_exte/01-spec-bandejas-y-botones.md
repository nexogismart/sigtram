# SDD 01 — Bandejas y botones de Trámite Externo

La bandeja moderna debe preservar el conjunto de trabajo legacy, pero calcularlo desde una única máquina de estados autorizada. Este documento define qué entra en cada bandeja, qué acciones aparecen y qué debe validar el servidor; la visibilidad de un botón nunca sustituye una Policy.

## 1. Contrato común

### 1.1 Contexto obligatorio

Toda consulta y mutación recibe el contexto desde sesión/autenticación, no desde campos editables:

| Dato | Fuente autorizada |
|---|---|
| Usuario | `Auth::user()->cdgo_lgin` |
| Compañía | usuario autenticado / `SessionKeys::USER_COMPANY` |
| Cargo activo | `SessionKeys::ACTIVE_CARGO_ID` y pertenencia vigente en `r_crgos_lgin` |
| Área activa | `p_crgos.cdgo_area` del cargo autorizado |
| IP | `Request::ip()` con configuración correcta de proxies confiables |

Antes de devolver o cambiar un expediente se debe probar:

1. pertenece a la compañía activa;
2. el movimiento visible/activo corresponde al cargo o al alcance explícito del rol;
3. el estado origen admite la operación;
4. no existe otra operación idempotente en curso;
5. el expediente no cambió desde que se mostró al usuario.

### 1.2 Contrato de listado

Entrada: `bandeja`, `criterio`, `descripcion`, `estado?`, `desde?`, `hasta?`, `page`, `per_page`.

Validación:

- `bandeja`: enum cerrado.
- `criterio`: enum cerrado; nunca nombre de columna arbitrario.
- fechas: `Y-m-d`, rango coherente, límite configurable.
- `per_page`: 1–100.
- texto: longitud máxima y normalización UTF-8.
- estado: miembro del catálogo conocido.

Salida JSON:

```json
{
  "data": [],
  "meta": {"page": 1, "per_page": 20, "total": 0, "last_page": 1},
  "allowed_actions": [],
  "snapshot": "opaque-version-token"
}
```

La consulta debe usar Query Builder/repositorio con bindings, paginación del servidor y un `EXISTS` agregado para video; se evita el N+1 actual de una consulta por fila.

## 2. Catálogo de estados observado

Estos valores se infieren del código y deben confirmarse contra `a_estdos_trmtes` antes de implementación. No se autoriza usarlos como literales dispersos.

| Código | Semántica observada | Evidencia/uso |
|---:|---|---|
| 1 | Pendiente en destino | Derivar/enviar crea estado 1 |
| 2 | Recibido | Recepcionar crea estado 2 |
| 3 | Finalizado | Finalizar y filtros finales |
| 4 | Devuelto/Regresado | `segi_regresar.php`; falta en v3 |
| 5, 12, 29 | Excluidos de Entrada | Filtro legacy y v3 |
| 7 | Asignado a cargo interno | `segi_asignar.php`; v3 lo crea al asignar |
| 8 | Envío multi-área/notificación de estado | `segi_reenviar_areas.php`; v3 lo crea al enviar |
| 13 | Redirigido | `segi_otra.php` y controlador v3 de redirección |
| 25, 51 | Casos especiales que entran a Entrada | Filtro legacy y v3; 51 usa amarillo en histórico |
| 26 | Notificado | Bandeja legacy Notificados; combinado con finales |
| 27 | Archivado | Bandeja Archivados |
| 28 | Prestado | Bandeja Prestados |
| 29 | Anulado | Redirección/anulación; además se excluye de Entrada |
| 52 | Adjunto de trámite interno | Rama especial del histórico legacy |

Decisión pendiente crítica: `in_esta` no puede significar simultáneamente “movimiento actual” e “histórico visible”. Legacy termina creando estado 3 activo por una llamada con variable indefinida; v3 crea estado 3 inactivo, pero otros controladores sólo buscan finales activos. La implementación debe escoger un contrato único y migrar/reconciliar datos existentes.

## 3. Matriz de bandejas

| Bandeja | Regla legacy observable | v3 actual | Acciones requeridas |
|---|---|---|---|
| Entrada | destino=cargo; origen≠cargo; activo; estado no 5/12/29, o estado 25/51 | Implementada | Recepcionar, Imprimir, detalle, hoja de ruta |
| Recibidos | destino=origen=cargo; activo; legacy excluye 3/26 | Implementada; añade exclusión 27 | Derivar, Asignar, Enviar multi-área, Finalizar, Notificar estado, Imprimir |
| Salida | origen=cargo; destino≠cargo; activo | Implementada | Devolver/Regresar, Imprimir, detalle, hoja de ruta |
| Finalizados | destino=origen=cargo; estado 3/26; legacy los consulta como activos | v3 exige inactivo | Notificar, Archivar, Imprimir, reenviar alerta |
| Todos | área del cargo destino; movimiento activo | Implementada | Consulta, detalle, hoja de ruta, imprimir |
| Sin Finalizar | enviados por cargo cuyo movimiento actual no es 3 | Implementada vía parámetro | Consulta, detalle, imprimir |
| Archivados | destino=origen=cargo; activo; estado 27 | Parcial/duplicada | Imprimir, detalle, prestar |
| Notificados | destino=origen=cargo; activo; estado 26 | No hay bandeja propia | Imprimir, detalle |
| Prestados | activo; estado 28; alcance de archivo | Fuera de navegación principal | Archivar/retornar, detalle |
| Inventariar | matriz de archivo físico por responsable/ubicación | Endpoint `matriz` parcial | Consultar, imprimir/exportar, conciliar |
| Videos | tres tutoriales Recepcionar/Derivar/Asignar y solicitudes por expediente | Sólo solicitudes de video; no tutoriales | Reproducir tutorial accesible, ver solicitud |

### 3.1 Entrada

Legacy: `segi_entrada.php`; servicio fuente `general.class.php::gene_expediente_cargo_entrada`. V3: `CorreoExternoController::buildBandejaWhere('entrada')`.

Campos mínimos: selección, expediente, solicitante, fecha de ingreso/movimiento, estado, observación, semáforo, detalle, hoja de ruta y solicitud de video.

Reglas:

- sólo un movimiento actual recepcionable por expediente;
- el cargo destino debe ser el cargo activo;
- estado origen permitido: 1/25/51 u otros del catálogo confirmado, nunca 3/26/27/28/29;
- al recepcionar se cierra el movimiento anterior y se crea estado 2 para el mismo cargo;
- observación y fecha se auditan; no se acepta una fecha futura fuera de tolerancia.

```gherkin
Scenario: Recepcionar un expediente asignado al cargo activo
  Given el expediente tiene un movimiento vigente destinado al cargo activo
  And su estado permite recepción
  When el usuario confirma fecha y observación
  Then el sistema cierra exactamente ese movimiento
  And crea un único movimiento Recibido
  And registra auditoría Before/After, usuario e IP

Scenario: Impedir recepción por ID ajeno
  Given el ID pertenece a otra compañía o cargo
  When el usuario intenta recepcionarlo directamente por API
  Then la respuesta es 404 o 403
  And ningún movimiento cambia
```

### 3.2 Recibidos

Legacy: `segi_recepcion.php`. Acciones visibles en líneas 144–148: Derivar, Asignar, Enviar, Finalizar e Imprimir; además notificación individual y hoja de ruta por fila.

Reglas:

- todos los seleccionados deben pertenecer al mismo contexto autorizado;
- una operación por lote es atómica, o el contrato debe devolver resultado individual explícito; no se permite éxito ambiguo;
- destino de Asignar pertenece al área activa y está vigente;
- destino de Derivar/Enviar está vigente y pertenece a la compañía;
- generar trámite interno exige tipo y correlativo reservado mediante el mecanismo existente, sin cambiar el esquema.

```gherkin
Scenario Outline: Ejecutar una acción válida desde Recibidos
  Given todos los expedientes seleccionados están Recibidos por el cargo activo
  When el usuario confirma <accion> con datos válidos
  Then se aplica la transición definida para <accion> una sola vez
  And el lote queda auditado

  Examples:
    | accion |
    | Derivar |
    | Asignar |
    | Enviar |
    | Finalizar |

Scenario: Rechazar doble envío
  Given una solicitud con la misma clave idempotente ya fue confirmada
  When el navegador reintenta la solicitud
  Then se devuelve el resultado original
  And no se crea un segundo movimiento
```

### 3.3 Salida

Legacy: `segi_salida.php`; Devolver abre `segi_regresar.php` y el reporte usa tipo 3.

Reglas de Devolver:

- sólo el cargo emisor puede solicitarla dentro del estado/plazo permitido;
- se bloquea el movimiento actual y se valida que todavía esté en destino;
- se preserva la trazabilidad; nunca se borra el movimiento enviado;
- el nuevo movimiento expresa claramente regreso/devolución y motivo obligatorio.

```gherkin
Scenario: Devolver un expediente aún vigente en destino
  Given el cargo activo es el emisor del movimiento de salida
  And el movimiento sigue vigente y admite devolución
  When registra un motivo y confirma
  Then el sistema crea el movimiento de regreso según el contrato
  And el expediente reaparece en la bandeja correcta
```

### 3.4 Finalizados y Notificados

Legacy usa estado 3 o 26 para Finalizados, y estado 26 para Notificados. Debe conservarse una bandeja Notificados independiente aunque la navegación pueda ofrecer un resumen “Finali-Notifi”.

Reglas:

- Finalizar crea estado 3 y habilita Notificar/Archivar.
- Notificar crea estado 26 sólo después de aceptar el trabajo de notificación; entrega efectiva/fallo se registra aparte.
- Un fallo de correo no se oculta como éxito: queda `pending/failed` y puede reintentarse.
- Archivar crea estado 27 y metadatos físicos/digitales requeridos.

```gherkin
Scenario: Finalizar sin perderlo para Archivo
  Given un expediente Recibido por el cargo activo
  When se finaliza
  Then aparece en Finalizados
  And queda disponible para Notificar y Archivar

Scenario: Notificación asíncrona
  Given un expediente Finalizado y un correo validado
  When el usuario solicita Notificar
  Then la transacción registra la solicitud y publica el trabajo
  And la respuesta no espera al servidor de correo
```

### 3.5 Archivados, Prestados e Inventariar

Legacy: estado 27 archivado, 28 prestado. `segi_prestados.php` permite volver a Archivar. V3 reparte estas capacidades entre la bandeja principal y `segi_archivo.blade.php`.

Metadatos mínimos de archivo: código de archivador, ubicación, folios, responsable, fecha, observación y estado de préstamo. Préstamo exige destinatario, finalidad, fecha de salida y fecha prevista de retorno.

```gherkin
Scenario: Prestar expediente archivado
  Given el expediente está Archivado y bajo custodia del cargo autorizado
  When se registra destinatario, finalidad y retorno previsto
  Then pasa a Prestado con estado 28
  And permanece visible en Prestados hasta su retorno

Scenario: Retornar un préstamo
  Given el expediente está Prestado
  When el responsable confirma la devolución física
  Then vuelve a Archivado con un nuevo movimiento
  And se conserva íntegra la historia del préstamo
```

### 3.6 Todos y Sin Finalizar

Son bandejas de consulta. No deben ampliar permisos de escritura. “Todos” está limitado al alcance autorizado del área; “Sin Finalizar” toma envíos del cargo y evalúa el último movimiento vigente de cada expediente de forma determinista.

```gherkin
Scenario: Consultar Sin Finalizar sin subconsulta ambigua
  Given un expediente tiene múltiples movimientos históricos
  When se calcula su estado actual
  Then se selecciona exactamente el último movimiento vigente por clave ordenable
  And aparece sólo si ese estado no es Finalizado
```

### 3.7 Videos tutoriales

El legacy enlaza `Recepcionar.mp4`, `Derivar.mp4` y `Asignar.mp4` desde `segi_expedi.php`. El equivalente debe:

- servirse desde un repositorio multimedia autorizado;
- incluir subtítulos, controles de teclado, título y transcripción;
- abrirse en modal responsivo sin popup bloqueable;
- distinguir tutorial institucional de una solicitud de copia de video de un expediente.

## 4. Especificación de botones

| Botón | Entrada | Validaciones servidor | Resultado |
|---|---|---|---|
| Recepcionar | IDs, fecha, observación, idempotency key | ownership, compañía, estado origen, fecha | estado 2, mismo cargo |
| Imprimir | filtros normalizados | permiso de consulta y límite de filas | PDF de bandeja con filtros y sello de generación |
| Derivar | IDs, área, fecha, observación, folios, adjuntos, trámite interno opcional | destino activo, no mismo destino accidental, estado Recibido, MIME/tamaño | estado 1 en cargo responsable de destino |
| Asignar | IDs, cargo interno, resto igual | cargo activo de la misma área, no autoasignación inválida | estado 1 al cargo interno |
| Enviar multi-área | IDs, lista única de áreas | cada área válida; política de copia vs ramas | movimientos coherentes por destino |
| Finalizar | IDs, fecha, observación, folios, adjuntos, correo opcional | estado Recibido, datos de ciudadano, adjuntos | estado 3 + evento de notificación opcional |
| Devolver | IDs, motivo, fecha | emisor autorizado, movimiento vigente | movimiento de regreso |
| Archivar | IDs, ubicación, observación | estado 3/26 o retorno de 28 | estado 27 |
| Notificar | IDs, canal, destinatario, plantilla | estado 3, contacto validado | estado 26 + trabajo en cola |
| Prestar | IDs, prestatario, finalidad, retorno | estado 27 y custodia | estado 28 |
| Redirigir | ID, área nueva, motivo | privilegio especial, compañía, lock | nueva ruta sin sobrescribir historia |
| Agregar Anexo | movimiento/expediente, archivo, observación | Policy, MIME real, tamaño, antivirus, UUID | adjunto privado + movimiento/auditoría |
| Descargar | ID opaco de adjunto | Policy, path canónico, existencia/hash | stream privado + `p_sgmto_dscrgas` |

## 5. Contrato UI

- Los botones se deshabilitan tras clic y muestran spinner hasta respuesta.
- La API mantiene idempotencia; el bloqueo visual no es control de integridad.
- Cada modal enfoca el título al abrir, atrapa foco, cierra con Escape y devuelve foco al disparador.
- Tooltips/popovers funcionan con hover y focus; `aria-describedby` anuncia el contenido.
- Badges de estado usan texto e icono además de color.
- El error conserva entradas, señala el campo y no muestra SQL/stack trace.
- La tabla mantiene paginación del servidor y una vista móvil legible.

## 6. Criterios de aceptación transversales

```gherkin
Scenario: El servidor ignora una acción visualmente oculta
  Given un usuario no tiene permiso para una transición
  When invoca directamente el endpoint
  Then recibe 403
  And no se cambia la base de datos ni el filesystem

Scenario: Conflicto por edición concurrente
  Given otro usuario cambió el movimiento desde que se cargó la bandeja
  When se envía la transición con un snapshot antiguo
  Then el servidor responde 409
  And devuelve el estado actual sin sobrescribirlo

Scenario: Fallo dentro de un lote
  Given un lote atómico contiene un expediente inválido
  When se intenta procesar
  Then toda la transacción se revierte
  And no quedan archivos huérfanos
  And la respuesta identifica el elemento rechazado sin filtrar información sensible
```
