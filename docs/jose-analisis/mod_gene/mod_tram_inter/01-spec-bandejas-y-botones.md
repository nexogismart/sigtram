# SPEC-01: Bandejas, Botones y Menú Lateral (Trámite Interno)
> **Estándar:** Spec-Driven Development (Gentle-AI)  
> **Componente:** `mod_gene / mod_tram_inter / CorreoInterno`  
> **Archivos Legacy:** `segi_expedi.php`, `segi_entrada.php`, `segi_enviado.php`, `segi_todos.php`, `segi_recepcion.php`, `segi_salida.php`, `segi_finalizados.php`, `segi_sinfinalizar.php`, `segi_tramites_conta.php`, `ver_comprobantes_pago.php`  
> **Archivos Laravel:** `CorreoInternoController.php`, `correo-interno.blade.php`, `web.php`

---

## 1. Contexto y Análisis de Funcionalidades por Botón

En el sistema legacy, el archivo `segi_expedi.php` actúa como el contenedor maestro que divide la pantalla en un menú lateral izquierdo (10%) y un iframe principal derecho (90%). Cada ítem del menú carga una bandeja especializada con sus propios **botones de acción superior**, **columnas de datos** y **acciones por fila**.

A continuación se detalla la especificación formal de cada ítem y la validación minuciosa de cada botón:

---

## 2. Especificación del Menú Lateral (Sidebar)

### 2.1. Bloque: Bandejas
1. **Entrada (`segi_entrada.php`):** Carga los expedientes dirigidos al cargo del usuario que provienen de otra área/cargo y aún no han sido recepcionados.
2. **Enviados (`segi_enviado.php`):** Lista los trámites que el usuario o su área han generado y remitido a otras áreas o cargos.
3. **Todos (`segi_todos.php`):** Vista global consolidada de todos los trámites que han pasado por el área o cargo, sin importar su estado.
4. **Recibidos (`segi_recepcion.php`):** Trámites recepcionados que se encuentran actualmente en posesión activa del cargo del usuario listos para ser trabajados.
5. **Salida (`segi_salida.php`):** Trámites despachados hacia otro cargo que están a la espera de ser recepcionados por el destinatario.
6. **Finalizados (`segi_finalizados.php`):** Trámites concluidos por el cargo (estado 3 o 24).

### 2.2. Bloque: Reportes
7. **Sin Finalizar (`segi_sinfinalizar.php`):** Trámites enviados por el cargo que se encuentran en tránsito o pendientes de atención en otras áreas sin llegar aún a estado finalizado.

### 2.3. Bloque: Comprobantes (Condicional a Área = 21 Contabilidad)
8. **Lista (`ver_comprobantes_pago.php`):** Popup modal para gestión y consulta de comprobantes de pago vinculados a trámites.
9. **Contabilidad (`segi_tramites_conta.php`):** Bandeja especializada de trámites contables (documentos tipo 7 con cargo 21/20 o tipo 15).

### 2.4. Bloque: Videos Tutoriales
El menú lateral legacy expone **6 videos tutoriales** de inducción al usuario:
* **¿Cómo Ingreso?** $\rightarrow$ `asset/videos/i.Ingresar.mp4`
* **¿Cómo Recepciono?** $\rightarrow$ `asset/videos/i.Recepcionar.mp4`
* **¿Cómo Derivo?** $\rightarrow$ `asset/videos/i.Derivar.mp4`
* **¿Cómo Asigno?** $\rightarrow$ `asset/videos/i.Asignar.mp4`
* **¿Cómo Finalizo?** $\rightarrow$ `asset/videos/i.Finalizar.mp4`
* **¿Cómo Devuelvo?** $\rightarrow$ `asset/videos/i.Devolver.mp4`

> **Defecto detectado en Laravel:**  
> Actualmente solo se renderizan 3 videos porque el controlador ejecuta `is_file(public_path(...))` y oculta los otros 3 en la vista.  
> **Solución Spec:**  
> Los 6 botones deben figurar siempre en la UI. Si el video MP4 existe en `public/asset/videos/`, abre el reproductor modal. Si no existe, abre un modal de asistencia informativa: *"Tutorial en proceso de actualización por soporte técnico"*.

---

## 3. Matriz de Botones de Acción por Bandeja

### 3.1. Bandeja ENTRADA (`segi_entrada.php`)

| Botón / Control | Comportamiento Legacy | Estado Actual en Laravel | Especificación de Paridad (1000%) |
|---|---|---|---|
| **`Recepcionar`** | Valida selección múltiple. Si `chk_faltantes` está activo y hay trámites tipo 64, valida en AJAX contra `ajax_validar_recepcion.php` que no falten campos obligatorios de logística. Si es válido, abre modal para fecha y observación (*"Sin novedades para recepcionar"*). | Modal básico implementado; **falta validación tipo 64**. | Mantener modal Tailwind, pero agregar la validación previa de tipo 64 retornando listado de errores antes de permitir el submit. |
| **`Imprimir`** | Abre `impr_bandeja.php?tipo=1` con filtro base64 para reporte de impresión y botón a Excel OpenTBS. | `alert('pendiente...')` | Implementar endpoint `/correo_interno/imprimir` que genere vista limpia para imprimir y botón de exportar Excel. |
| **`Mostrar solo faltantes`** | Checkbox toggle. Filtra trámites con requisitos incompletos de logística (`gene_lista_req_incpleto`). | Toggle funcional en frontend. | Validar que el query coincida con `gene_lista_req_incpleto` y ojo rojo en la fila. |
| **`Todos N / Ninguno`** | Marca o desmarca todos los checkboxes de la página. | Botón simple. | Mantener toggle con conteo exacto de filas marcadas. |

### 3.2. Bandeja ENVIADOS (`segi_enviado.php`)

| Botón / Control | Comportamiento Legacy | Estado Actual en Laravel | Especificación de Paridad (1000%) |
|---|---|---|---|
| **`Imprimir`** | `impr_bandeja.php?tipo=0`. Reporte de enviados. | `alert('pendiente...')` | Integrar en el servicio de impresión con `tipo=enviados`. |
| **`Reiterativo`** | Abre popup a `segi_reiterar.php`. Permite generar un nuevo pase con estado `53` (`REITERATIVO`) sobre el trámite seleccionado. Opción *"Enviar con trámite"* (`chemi`), selector de documento interno (tipo, número correlativo automático), adjuntos y proveído. | **No existe** | Crear modal `modal-reiterativo` y endpoint POST `/correo_interno/reiterar`. |
| **`Descargar`** | Descarga consolidada de documentos seleccionados (`segi_descargar.php?tipo=0`). | **No existe** | Crear endpoint GET `/correo_interno/descargar-lote`. |
| **Columna `Hoja de Ruta`** | Icono impresora abre `segi_hoja_ruta.php?nu_expe=...` (imprime la secuencia cronológica de áreas y firmas). | **Omitida en tabla** | Agregar columna con icono de impresora que abra modal/pestaña de Hoja de Ruta. |
| **Columna `D` (Editar Adjunto)** | Icono ojo abre `ver_adjunto_modi.php` permitiendo ver y **reemplazar/modificar el archivo firmado**. | Solo ojo genérico. | Si el usuario tiene permisos, abrir modal para reemplazar el adjunto firmado. |

### 3.3. Bandeja RECIBIDOS (`segi_recepcion.php`) — *Núcleo Operativo*

| Botón / Control | Comportamiento Legacy | Estado Actual en Laravel | Especificación de Paridad (1000%) |
|---|---|---|---|
| **`Derivar`** | Abre `segi_reenviar.php`. Permite: 1) Seleccionar múltiples áreas destino (`sel_areas[]`), 2) Fecha/hora, 3) Checkbox *"Crear Trámite Interno"* (tipo doc, correlativo con `ajax_semilla_interno`, botón "Generar Borrador" Word, subida de PDF firmado, flag físico), 4) Subir hasta 4 archivos digitales empaquetados en ZIP, 5) Observación *"Sin novedades para enviar"*. | **No visible / No implementado** | Crear modal de Derivación completa (`modal-derivar`) con selector múltiple de áreas, bloque condicional de creación de documento interno y subida de archivos múltiples. Endpoint POST `/correo_interno/derivar`. |
| **`Asignar`** | Abre `segi_asignar.php`. Permite asignar internamente el trámite a un usuario o cargo de la propia área (`sel_cdgo_cargo`) con estado `7` (`ASIGNADO`). | **No visible / No implementado** | Crear modal `modal-asignar` con dropdown de cargos/usuarios del área activa y endpoint POST `/correo_interno/asignar`. |
| **`Finalizar`** | Abre `segi_finaliza.php`. Cierra el expediente asignando estado `3` (`FINALIZADO`), adjuntando documentación de cierre y proveído. | **No visible / No implementado** | Crear modal `modal-finalizar` con confirmación, adjuntos finales y endpoint POST `/correo_interno/finalizar`. |
| **`Imprimir`** | `impr_bandeja.php?tipo=2`. | Inexistente. | Integrar al generador de reportes. |
| **Columna `Hoja de Ruta`** | Enlace directo a `segi_hoja_ruta.php`. | Omitida. | Incluir en tabla de Recibidos. |

### 3.4. Bandeja SALIDA (`segi_salida.php`)

| Botón / Control | Comportamiento Legacy | Estado Actual en Laravel | Especificación de Paridad (1000%) |
|---|---|---|---|
| **`Devolver`** | Abre `segi_regresar.php`. Rechaza/devuelve el trámite al área remitente cambiando el estado a `4` (`DEVUELTO`) con justificación. | **No visible / No implementado** | Crear modal `modal-devolver` y endpoint POST `/correo_interno/devolver`. |
| **`Imprimir`** | `impr_bandeja.php?tipo=3`. | Inexistente. | Integrar a impresión. |

### 3.5. Bandeja FINALIZADOS (`segi_finalizados.php`)

| Botón / Control | Comportamiento Legacy | Estado Actual en Laravel | Especificación de Paridad (1000%) |
|---|---|---|---|
| **`Imprimir`** | `impr_bandeja.php?tipo=4`. | Inexistente. | Integrar a impresión. |

### 3.6. Bandeja TODOS (`segi_todos.php`)

| Botón / Control | Comportamiento Legacy | Estado Actual en Laravel | Especificación de Paridad (1000%) |
|---|---|---|---|
| **`Imprimir`** | `impr_bandeja.php?tipo=5`. | Inexistente. | Integrar a impresión. |
| **Columnas Especiales** | Muestra columnas adicionales: `Estado` (`ORIGINAL`/`COPIA`), `Cargo` (`de_crgo`), además de `A` y `D`. | Parcial. | Asegurar consistencia de columnas en vista. |

### 3.7. Reportes: SIN FINALIZAR (`segi_sinfinalizar.php`)

| Botón / Control | Comportamiento Legacy | Estado Actual en Laravel | Especificación de Paridad (1000%) |
|---|---|---|---|
| **`Imprimir`** | `impr_bandeja.php?tipo=6`. | Inexistente. | Integrar a impresión. |
| **Columna `Hoja de Ruta`** | Enlace a `segi_hoja_ruta.php`. | Omitida. | Incluir en tabla. |

---

## 4. Criterios de Aceptación (Gherkin / Gentle-AI)

```gherkin
Scenario: Derivar trámites desde Bandeja Recibidos
  Given el usuario está autenticado y tiene trámites en la bandeja "Recibidos"
  When selecciona uno o más trámites y presiona el botón "Derivar"
  Then se abre el modal de derivación con la lista de áreas destino disponibles
  And si activa "Crear Trámite Interno", se consulta el correlativo disponible automáticamente
  And al enviar el formulario con adjunto y áreas destino, se generan los movimientos con estado 1 en r_trmtes_ntrnos
  And se deshabilita el movimiento anterior en recibidos y se muestra mensaje de éxito

Scenario: Verificación de videos en el menú lateral
  Given el usuario visualiza el sidebar de "Correo Interno"
  Then deben mostrarse los 6 botones de tutoriales: Ingresar, Recepcionar, Derivar, Asignar, Finalizar y Devolver
  And al hacer clic en un video existente se reproduce en modal
  And al hacer clic en un video sin archivo físico se muestra modal explicativo sin romper la UI
```
