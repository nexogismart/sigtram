# SDD 03 — Flujos transaccionales de Trámite Externo

Todas las operaciones deben ejecutarse mediante una máquina de estados explícita, Policy por objeto, bloqueo de fila e idempotencia. El patrón actual de desactivar por `cdgo_dto_trmte` y luego insertar sin verificar dueño/estado permite alterar expedientes ajenos; queda prohibido como base de la migración.

## 1. Máquina de estados objetivo

Mapa inferido del legacy, sujeto a confirmación con `a_estdos_trmtes`:

| Código | Nombre observado | Puede originarse en | Destinos típicos |
|---:|---|---|---|
| 1 | Pendiente/Derivado | alta, derivación | 2, 4 |
| 2 | Recibido | recepción | 3, 7, 8, 1 |
| 3 | Finalizado | finalización | 26, 27 |
| 4 | Devuelto/Regresado | devolución | 2/1 según recepción posterior |
| 7 | Asignado | asignación interna | 2, 3, 1 |
| 8 | Enviado multi-área / notificación de estado | envío a áreas | 2, 3 |
| 13 | Redirigido | redirección | 1/2 según flujo acordado |
| 25 | Anexo | agregar anexo | flujo del expediente |
| 26 | Notificado | notificación final | 27 |
| 27 | Archivado/Inventariado | archivo | 28 |
| 28 | Prestado | préstamo | 27 |
| 29 | Anulado | anulación de ruta | terminal/auditoría |
| 51 | Reingreso | reingreso/anexo | 2 |
| 52 | Especial histórico | referencia legacy | confirmar o retirar mediante migración |

`in_esta` se redefine como **movimiento vigente**: exactamente uno por expediente en un flujo lineal. Si el negocio permite múltiples destinos simultáneos, se modela rama/copia con identidad propia; no se insertan varios “actuales” indistinguibles. Los movimientos terminales siguen siendo consultables aunque no sean vigentes.

## 2. Patrón transaccional obligatorio

```text
1. Validar FormRequest y clave idempotente
2. Resolver usuario, compañía, cargo y área activos
3. Policy sobre cada expediente
4. BEGIN
5. SELECT movimiento actual ... FOR UPDATE
6. Verificar versión + estado origen + destino
7. Reservar correlativo mediante el mecanismo existente, sin cambios de esquema
8. Preparar/stagear metadatos de adjuntos privados
9. Cerrar sólo el movimiento bloqueado
10. Crear nuevo movimiento y auditoría Before/After
11. Registrar outbox/eventos de notificación
12. COMMIT
13. Promover/limpiar archivos; despachar job afterCommit
14. Responder resultado idempotente
```

Una excepción revierte base de datos y limpia archivos en staging. Los errores externos nunca prolongan locks.

## 3. Contratos HTTP

| Flujo | Endpoint propuesto | Método | Estado éxito |
|---|---|---|---:|
| Recepcionar | `/expedientes/recepciones` | POST | 201 |
| Derivar | `/expedientes/derivaciones` | POST | 201 |
| Enviar multi-área | `/expedientes/envios-multiples` | POST | 202/201 |
| Asignar | `/expedientes/asignaciones` | POST | 201 |
| Finalizar | `/expedientes/finalizaciones` | POST | 201 |
| Devolver | `/expedientes/devoluciones` | POST | 201 |
| Archivar | `/expedientes/archivos` | POST | 201 |
| Notificar | `/expedientes/notificaciones` | POST | 202 |
| Prestar | `/expedientes/prestamos` | POST | 201 |
| Retornar préstamo | `/expedientes/prestamos/{id}/retorno` | POST | 201 |
| Redirigir | `/expedientes/redirecciones` | POST | 201 |
| Agregar anexo | `/expedientes/{id}/anexos` | POST | 201 |
| Hoja de ruta | `/expedientes/{id}/hoja-ruta` | GET | 200 |

Los nombres actuales pueden mantenerse como aliases temporales; los contratos internos y Policies son obligatorios.

## 4. Derivar / Reenviar

Legacy: `segi_reenviar.php`, variante `segi_reenviar9.php`. V3: `CorreoExternoController::derivar`.

Entrada:

- `expediente_ids[]` enteros únicos;
- área/gerencia/subgerencia destino;
- fecha, observación, folios;
- `enviar_con_tramite`, tipo documental y adjuntos opcionales;
- `snapshot` e `Idempotency-Key`.

Validaciones:

- estado origen recibido/asignado según catálogo;
- cargo activo es dueño del movimiento;
- área destino activa, misma compañía y diferente cuando el negocio lo exige;
- cargo responsable de destino definido de forma declarativa, no necesariamente `nu_orde=1` sin contrato;
- trámite interno: tipo habilitado para el área y correlativo mediante el mecanismo existente;
- adjuntos por `GestorAdjuntos`.

Persistencia:

- cierra el movimiento exacto bloqueado;
- crea estado 1 hacia cargo destino;
- crea/enlaza trámite interno dentro de la misma transacción;
- auditoría registra destino anterior/nuevo y adjuntos por ID.

```gherkin
Scenario: Derivar con trámite interno concurrentemente
  Given dos usuarios solicitan correlativo para el mismo tipo y ámbito
  When ambas transacciones se ejecutan
  Then cada una obtiene un número único por el mecanismo existente
  And ninguna usa MAX+1
```

## 5. Enviar Multi-Área

Legacy: `segi_reenviar_areas.php`; v3: `CorreoExternoController::enviar`.

Decisión de dominio necesaria: una derivación a N áreas representa N copias/rutas hijas, no N movimientos vigentes del mismo agregado sin identidad. Se crea una `derivacion_grupo_id` y cada rama guarda destino, copia/principal y estado independiente.

Validaciones adicionales:

- 1..N áreas únicas;
- no incluir áreas no autorizadas/inactivas;
- definir cuál rama es principal o declarar todas copias;
- cierre del padre sólo cuando todas las ramas fueron persistidas.

```gherkin
Scenario: Fallar una de tres áreas
  Given un envío atómico a tres áreas
  And la tercera no tiene cargo responsable
  When se confirma
  Then no se crea ninguna rama
  And el movimiento original sigue vigente
```

## 6. Asignar a cargo interno

Legacy: `segi_asignar.php`; estado 7. V3: `CorreoExternoController::asignar`.

- cargo destino activo y de la misma área;
- usuario ejecutor autorizado sobre el movimiento;
- no confiar en un `cargo` enviado por cliente sin consultar pertenencia;
- crear estado 7, no degradarlo a 1;
- si se adjunta trámite interno, compartir el patrón seguro de correlativo y archivos.

```gherkin
Scenario: Rechazar cargo de otra área
  Given el expediente está Recibido en el área A
  When se intenta asignar a un cargo del área B
  Then la API responde 422/403
  And conserva el movimiento original
```

## 7. Finalizar expediente

Legacy: `segi_finaliza.php`; v3: `CorreoExternoController::finalizar`.

Reglas:

- estado origen permitido y ownership;
- observación de cierre obligatoria y folios no negativos;
- estado 3 queda disponible para Archivo/Notificar mediante la definición única de vigencia;
- email opcional crea un evento/outbox después de commit;
- el fallo de envío se registra y reintenta, sin deshacer el cierre ni mentir sobre entrega;
- adjuntos ya persistidos no deben quedar huérfanos tras rollback.

```gherkin
Scenario: Finalizar y fallar correo
  Given un expediente válido y un servidor de correo no disponible
  When se finaliza con notificación solicitada
  Then la finalización se confirma
  And la notificación queda Failed/Pending para reintento
  And la interfaz no afirma que el correo fue entregado
```

## 8. Devolver / Regresar

Legacy: `segi_regresar.php`; estado 4. No existe endpoint equivalente en Correo v3.

Entrada: IDs, motivo obligatorio, fecha y snapshot. Sólo el actor autorizado por la regla de retorno puede ejecutar. El servicio bloquea el movimiento actual en destino, confirma que proviene del cargo solicitante cuando corresponda y crea estado 4 conservando origen/destino legibles.

```gherkin
Scenario: Impedir devolución de un expediente ya recepcionado y reasignado
  Given el movimiento mostrado en Salida ya dejó de ser vigente
  When el emisor intenta devolver con snapshot antiguo
  Then recibe 409
  And no se altera la nueva asignación
```

## 9. Archivar e inventariar

Legacy: `segi_archivar.php`, `segi_archivo.php`, `segi_inventariar.php`. V3: `ArchivoExpedienteController`.

Hay dos semánticas legacy que deben unificarse: archivar sólo cambia estado 27; inventariar además crea `r_mtriz_archvdor_trmtes`. El contrato moderno exige ubicación/matriz cuando la política de archivo físico lo requiera y evita duplicados activos.

Entrada: IDs, fecha, observación, archivador/ubicación, folios verificados. Origen admitido 3/26 o 28 en retorno. Resultado estado 27 y registro de custodia.

```gherkin
Scenario: Evitar inventario duplicado
  Given existe una relación activa expediente-archivador
  When se intenta inventariar de nuevo en el mismo lugar
  Then se responde idempotentemente
  And no se duplica la matriz
```

## 10. Notificar a ciudadano / plataforma

Legacy: `segi_notificar.php`, `segi_notificar_estado*.php`, `notificar_pltfrma.php`. V3 mezcla correo directo en controladores y servicio.

Canales: email, plataforma y otros adaptadores configurados. `NotificationCore::send()` publica a Redis con DTO sin exponer modelos. Campos: expediente, plantilla/version, destinatario verificado, adjuntos autorizados y correlation ID.

- estado 26 se aplica según regla aprobada: al aceptar la notificación o al confirmar entrega; la decisión se documenta;
- intentos, proveedor, timestamp y respuesta normalizada se auditan;
- no SMTP/cURL directo;
- secretos y BCC salen de configuración, nunca código.

```gherkin
Scenario: Reintentar notificación sin duplicar estado
  Given el primer intento falló después de registrar estado
  When la cola reintenta
  Then reutiliza la misma notificación/correlation ID
  And no crea otro movimiento 26
```

## 11. Préstamo y retorno

Legacy: `segi_prestar.php`, `segi_prestados.php`. V3: `ArchivoExpedienteController::prestar`.

Entrada: expediente, prestatario, unidad, finalidad, fecha salida, retorno previsto, observación. Origen único: 27. Resultado: 28 y registro de custodia. El retorno crea 27 y fecha real; no reutiliza “Archivar” sin semántica de devolución.

Permisos y consultas se limitan al custodio/cargo; se elimina la exposición de todos los préstamos de la compañía observada en legacy.

## 12. Redirigir y anular ruta

Legacy: `segi_redirigir.php`, `segi_otra.php`, `segi_detalle_anular.php`; v3: `RedirigirExpedienteController`.

Redirigir es privilegio especial. No se limita a sobrescribir `p_dto_trmtes.cdgo_area`; crea estado 13 y conserva área original/nueva en auditoría. Anular crea 29 sobre la ruta concreta y exige motivo; nunca acepta un movimiento arbitrario fuera de scope.

```gherkin
Scenario: Redirigir conservando procedencia
  Given un expediente fue registrado en el área incorrecta
  When un rol autorizado lo redirige
  Then la historia conserva área anterior, nueva, actor y motivo
  And la cabecera refleja el destino vigente
```

## 13. Agregar anexo / adjuntar

Legacy: `agre_anexo.php`, `segi_adjuntar.php`; v3: `BuscaExpediController` y `AdjuntarTramiteController`.

Entrada multipart: archivo(s), tipo, descripción, movimiento/expediente y snapshot. El cliente nunca define el nombre físico.

Pipeline:

1. Policy del expediente.
2. Validar tamaño/extensión y MIME real.
3. Antivirus/sandbox si está disponible.
4. UUID + hash + staging privado.
5. Crear movimiento/anexo (estado 25/51 según regla formal).
6. Promover archivo afterCommit.
7. Auditar.

El endpoint de lectura recibe `attachment_id`, no `nombre` con regex `.*`.

## 14. Hoja de ruta imprimible

Legacy: `segi_hoja_ruta.php`; v3: `hojaRuta` + `hoja_ruta.blade.php`.

Debe usar el mismo DTO autorizado del histórico, no otra query divergente. Incluye cabecera, movimientos, áreas, estados, usuarios y fecha de generación. No expone adjuntos ni PII no requerida. HTML accesible y PDF deben ser deterministas.

## 15. Observabilidad, errores y compensación

- logs estructurados con correlation ID, operación, cantidad, duración y resultado;
- auditoría de dominio Before/After dentro de transacción;
- métricas de conflictos 409, rechazos 403/422, fallos de cola y archivos huérfanos;
- mensajes 5xx genéricos; detalle sólo en logs protegidos;
- job periódico de reconciliación de staging/metadata, no borrado ciego;
- kill switch por flujo para detener una transición defectuosa sin apagar consultas.

## 16. Plan de pruebas

Para cada flujo:

- Unit: transición permitida/prohibida, bordes, DTO y evento.
- Feature: 401, 403 por compañía/cargo, 404 por recurso oculto, 409 por snapshot, 422 por campo.
- Integración: rollback, archivo, outbox, correlativo existente y lock concurrente.
- Idempotencia: dos requests idénticos producen un movimiento.
- Caracterización: conteos y destinos equivalentes al legacy sobre fixture anonimizado.
