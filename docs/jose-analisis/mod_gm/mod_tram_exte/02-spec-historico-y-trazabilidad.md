# SDD 02 — Histórico, usuarios, adjuntos y vouchers

El histórico es el comprobante de cadena de custodia del expediente. La implementación actual de SIGTRAM v3 es una cronología visual básica; la paridad exige recuperar el banner del trámite, las columnas funcionales U/A/D, la auditoría de descargas y la consolidación ZIP/PDF sin volver a introducir las vulnerabilidades del legacy.

> **HIST-001 — Regla obligatoria y transversal:** este diseño no pertenece únicamente a `segi_expedi`. Se aplica a todo lugar donde SIGTRAM muestre un histórico: bandejas externas e internas, búsqueda de expedientes, reportes por área/cargo, archivo, redirección, adjuntos, hoja de ruta y cualquier modal o vista futura. Una representación compacta puede resumir datos, pero debe ofrecer acceso inmediato al histórico completo conforme a esta especificación.

## 1. Resultado contractual

Al abrir un expediente autorizado, el usuario obtiene:

1. cabecera inequívoca del expediente;
2. movimientos ordenados de forma determinista;
3. tiempo estimado por tramo;
4. origen, destino, estado y observación;
5. usuarios origen/destino mediante popover accesible U;
6. adjuntos privados y auditoría de descargas mediante A;
7. voucher del movimiento mediante D;
8. descarga consolidada ZIP y `UNIDO-<expediente>.pdf` cuando sea posible.

## 2. Brecha comprobada

| Capacidad | Legacy | v3 actual | Decisión |
|---|---|---|---|
| Título/cabecera | `Histórico del Expediente N° ...` | Modal “Detalle / historial” | Añadir banner completo, no sólo número |
| Fecha entrada | Sí | Sí | Conservar zona America/Lima |
| Tiempo estimado T.E | `gene_estimado` por tramo | `calcularTiempoTranscurrido` | Especificar extremos y calendario |
| Área origen/salida | Implícita en cargo salida | `area_origen` | Rotular explícitamente |
| Área destino | `a_areas` de cargo entrada | `area` | Rotular “Área envío/destino” |
| Estado | Sí, colores 3/26 verde y 51 amarillo | Sí, badge genérico | Usar catálogo/tokens y no sólo color |
| Observación | Sí | Sí | Escapar salida y preservar saltos |
| U usuarios | Popover origen + todos los destinos del cargo | Un único actor `usuario` | Implementar completo |
| A adjunto | Link + popover `p_sgmto_dscrgas` | Link directo/endpoint sin auditoría | Implementar privado y auditado |
| D voucher | Artefactos `busca_vauche*` | Vouchers existen, no enlazados por movimiento | Integrar modal |
| Descargar todos | `segi_descargar_adjunto.php` | Ausente | Implementar job/stream seguro |
| PDF unificado | Convención `UNIDO-*` solicitada | Ausente | Implementar con manifiesto de errores |

Evidencia principal: `segi_tramite.php:25-148`; `general.class.php:3932-3965`; `CorreoExternoController.php:279-318`; `segi_expedi.blade.php:271-310,1106-1124`.

## 3. Autorización y API

### 3.1 Endpoint de detalle

`GET /mod_gm/mod_tram_exte/expedientes/{expediente}/historico`

No se usará un POST con `id` como único control. Route Model Binding resuelve el expediente dentro de la compañía y la Policy exige visibilidad por cargo/área/rol.

Respuesta propuesta:

```json
{
  "expediente": {
    "id": 123,
    "numero": "2026-00001234",
    "solicitante": "...",
    "documento": "...",
    "asunto": "...",
    "fecha_ingreso": "2026-10-09T10:00:00-05:00",
    "estado_actual": {"id": 2, "nombre": "RECIBIDO"}
  },
  "movimientos": [],
  "downloads": {
    "zip_url": "...",
    "pdf_url": "...",
    "available": true
  }
}
```

### 3.2 Consulta de movimientos

El repositorio obtiene en una consulta base:

- `r_trmtes_dcmntos` y estado;
- cargos/áreas de salida y entrada;
- login/usuario actor;
- metadatos de adjunto, no su ruta pública;
- relación con trámite interno/voucher;
- versión/estado vigente.

Luego obtiene en consultas agrupadas:

- usuarios activos del cargo destino por `p_crgos → r_crgos_lgin → p_lgin → p_usrios`;
- descargas por `p_sgmto_dscrgas` y adjunto;
- vouchers disponibles.

Se prohíbe un query por fila.

## 4. Cabecera formal

Campos:

| Campo | Regla |
|---|---|
| Nº expediente | Formato institucional completo, texto seleccionable |
| Solicitante | Nombre/razón social según tipo de persona |
| Documento | Enmascarado según permiso |
| Tipo/trámite TUPA | Catálogo vigente al momento de ingreso |
| Asunto/observación inicial | Texto escapado |
| Fecha ingreso | Fecha, hora y zona |
| Área de ingreso | Área original |
| Estado actual | Badge con texto, icono y token |
| Folios/anexos | Totales y advertencia si hay discrepancia |

La cabecera permanece visible al recorrer una historia larga y tiene versión imprimible.

## 5. Tabla de histórico

Columnas canónicas:

| Columna | Contenido | Cálculo |
|---|---|---|
| Entrada | `fe_rcbdo_trmte` | `d/m/Y H:i` America/Lima |
| T.E | duración del tramo | siguiente movimiento menos entrada; para el vigente, ahora menos entrada |
| Área salida | área de `cdgo_crgo_sa` | “Mesa/externo” si origen 0 según catálogo |
| Estado | nombre y código | catálogo, no comparación de texto “FINALIZADO” |
| Área envío | área de `cdgo_crgo_en` | nombre vigente/histórico |
| Observaciones | `de_obse` | texto escapado, expandible |
| U | usuarios | botón/popover accesible |
| A | adjuntos | botón/link privado + descargas |
| D | voucher | modal imprimible |

Orden: `cdgo_trmte_dcmnto ASC` para cronología. El cálculo de T.E. usa el siguiente movimiento; no depende de una variable de sesión como el legacy. Si existen dos movimientos simultáneos por un flujo multi-área, el contrato debe mostrar ramas o copias de forma explícita, no fingir una secuencia lineal.

### 5.1 Semáforo

- finalizado/notificado: token success;
- reingreso 51: token warning;
- vencido por silencio: token danger;
- resto: neutral/info.

Cada color lleva etiqueta textual y descripción. Los umbrales se calculan en Service a partir de configuración/TUPA y calendario definido, no por JavaScript local.

## 6. Columna U — usuarios

### 6.1 Datos

`usuario_origen` es el usuario actor del movimiento (`r.cdgo_lgin → p_lgin → p_usrios`). `usuarios_destino` son todos los usuarios activos asignados al cargo destino:

```text
p_crgos.cdgo_crgo
  → r_crgos_lgin.cdgo_crgo
  → p_lgin.cdgo_lgin (activo)
  → p_usrios.cdgo_usrios
```

Si origen y destino son el mismo cargo, el legacy repite al actor; v3 puede mostrar “mismo cargo” pero debe mantener ambos encabezados para reconocimiento.

### 6.2 Interacción accesible

- disparador `<button>` con etiqueta “Ver usuarios del movimiento”;
- abre con clic, Enter o Espacio; también puede previsualizar con hover/focus;
- popover de dos columnas: Usuario origen / Usuarios destino;
- `aria-expanded`, `aria-controls`, Escape para cerrar;
- foco administrado y contenido usable en móvil;
- lista vacía expresa “Sin usuarios activos asignados”, no queda en blanco.

```gherkin
Scenario: Consultar todos los usuarios destino
  Given un cargo destino tiene tres logins activos
  When el usuario abre U con teclado
  Then el popover muestra al actor origen a la izquierda
  And los tres usuarios destino a la derecha
  And un lector de pantalla anuncia ambas columnas
```

## 7. Columna A — adjuntos y descargas auditadas

### 7.1 Custodia

- archivos en `storage/app/documentos/externos/YYYY/MM/DD/`;
- nombre físico UUID, nombre original sólo como metadato;
- MIME real y extensión permitida;
- tamaño, SHA-256, usuario cargador y timestamp;
- nunca `/storage/adjuntos/...`, `storage:link`, `file_exists` con input del usuario ni `Content-Disposition` sin sanitizar.

### 7.2 Descarga

El cliente usa un ID opaco. El servidor resuelve el adjunto, autoriza expediente y movimiento, valida path canónico/hash y registra en la misma unidad lógica:

| Campo de auditoría | Fuente |
|---|---|
| Fecha | reloj servidor |
| Usuario/login | autenticación |
| IP | request/proxy confiable |
| Archivo | ID y nombre persistido |
| Expediente/movimiento | relación servidor |
| Resultado | success/not_found/hash_mismatch/denied |

La UI muestra en popover la tabla Fecha, Usuario, IP de `p_sgmto_dscrgas`. Por privacidad, IP completa sólo para roles autorizados; otros ven versión enmascarada.

```gherkin
Scenario: Descargar un adjunto autorizado
  Given el usuario puede ver el expediente y el archivo conserva su hash
  When solicita la descarga
  Then el servidor transmite desde storage privado
  And registra fecha, usuario, IP, archivo y éxito

Scenario: Bloquear path traversal
  Given el cliente envía un nombre con segmentos de ruta
  When invoca el endpoint
  Then el servidor no usa ese texto como ruta
  And responde 404/422 sin revelar paths físicos
```

## 8. Columna D — voucher

Los artefactos legacy `busca_vauche.php` y `busca_vauche_anex.php` distinguen voucher principal y anexo. El endpoint moderno recibe `movimiento_id`, autoriza su expediente y devuelve un DTO de voucher.

El modal contiene:

- número/fecha del movimiento;
- expediente;
- origen y destino;
- estado y observación;
- folios/anexos;
- usuario actor;
- referencia a trámite interno si existe;
- acciones Imprimir y Cerrar.

No abre ventanas emergentes obligatorias. La ruta imprimible puede abrir nueva pestaña y debe tener CSS de impresión.

```gherkin
Scenario: Ver voucher de un anexo
  Given el movimiento pertenece a un expediente visible
  And tiene número de anexo
  When se activa D
  Then el modal presenta el voucher de ese movimiento
  And ofrece volver al voucher principal sin perder el histórico
```

## 9. Descargar todos — ZIP y PDF unificado

### 9.1 ZIP

El ZIP contiene todos los adjuntos autorizados y un `MANIFEST.json`:

```json
{
  "expediente": "2026-00001234",
  "generated_at": "2026-10-09T12:00:00-05:00",
  "files": [
    {"movement_id": 1, "name": "documento.pdf", "sha256": "...", "status": "included"}
  ]
}
```

Se eliminan temporales en `finally`; nombres internos se normalizan para impedir Zip Slip y colisiones.

### 9.2 `UNIDO-*.pdf`

- sólo PDFs válidos se fusionan directamente;
- imágenes permitidas se convierten con límites de resolución;
- ODT/DOC requieren conversor aislado y timeouts; si no se pueden convertir, el manifiesto los marca y el ZIP los conserva;
- PDF protegido, corrupto o firmado no se altera silenciosamente;
- el nombre de descarga es `UNIDO-<numero-normalizado>.pdf`;
- la generación grande se procesa en cola y entrega una URL firmada de corta duración.

### 9.3 Auditoría

La descarga consolidada genera un evento padre y eventos por archivo incluido. Se registra quién solicitó, qué versión del conjunto se generó y los hashes.

```gherkin
Scenario: Consolidar expediente mixto
  Given el expediente contiene PDF, imagen y un formato no convertible
  When solicita Descargar todos
  Then el ZIP incluye los tres originales y manifiesto
  And el PDF unificado incluye PDF e imagen
  And el manifiesto explica por qué el tercer archivo no fue unido
```

## 10. Errores y rendimiento

- 401 sin sesión; 403 sin permiso; 404 para recurso no visible; 409 en versión obsoleta; 422 para filtro/formato inválido.
- Mensajes públicos no contienen SQL, stack trace ni rutas físicas.
- Histórico paginado o virtualizado a partir de umbral, pero la exportación conserva todo.
- Índices candidatos: movimiento por `(cdgo_dto_trmte, cdgo_trmte_dcmnto)`, cargo/activo/estado, descargas por adjunto+fecha.
- La generación consolidada tiene límite de tamaño/número y métricas de duración/fallos.

## 11. Pruebas obligatorias

- Unit: cálculo T.E., ramas multi-área, manifest, normalización de nombres, decisión de conversión.
- Feature: 401/403/404, compañía cruzada, U con 0/1/N destinos, A con auditoría, D principal/anexo, descarga consolidada.
- Seguridad: path traversal, IDOR, ZIP bomb, MIME falso, archivo corrupto, XSS en observación/nombre.
- Accesibilidad: foco, teclado, Escape, roles ARIA y contraste.
- Caracterización: mismo número y orden de movimientos que legacy sobre dataset anonimizado.
- Conformidad HIST-001: inventario automatizado de cada vista/endpoint que presenta histórico; ninguna puede omitir U/A/D o la ruta al detalle completo sin una excepción aprobada.
