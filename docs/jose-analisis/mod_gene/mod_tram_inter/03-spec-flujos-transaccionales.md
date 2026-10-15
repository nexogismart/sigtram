# SPEC-03: Flujos Transaccionales, Modales y Acciones Operativas
> **Estándar:** Spec-Driven Development (Gentle-AI)  
> **Componente:** `mod_gene / mod_tram_inter / Acciones Transaccionales`  
> **Archivos Legacy:** `segi_reenviar.php`, `segi_asignar.php`, `segi_finaliza.php`, `segi_regresar.php`, `segi_reiterar.php`, `segi_hoja_ruta.php`, `ver_adjunto_modi.php`, `busca_vauche.php`  
> **Archivos Laravel:** Controladores, Servicios y Vistas Blade de Modales

---

## 1. Contexto

La operatividad diaria del usuario municipal en Trámite Interno no se limita a consultar listas: radica en **hacer avanzar el expediente**. Cada acción transaccional modifica el estado del trámite en `r_trmtes_ntrnos`, registra una entrada de trazabilidad y, si corresponde, genera un nuevo trámite interno de referencia.

A continuación se especifican los 5 flujos transaccionales clave más los 3 módulos complementarios de soporte.

---

## 2. Flujo 1: DERIVAR TRÁMITE (`segi_reenviar.php`)

### 2.1. Propósito
Permite que el área que tiene el trámite en **Recibidos** lo remita a una o más áreas municipales competentes, pudiendo adjuntar documentos de sustento o emitir un nuevo documento interno (Memorándum, Proveído, Oficio) que lo acompaña.

### 2.2. Campos del Modal / Formulario
1. **Trámites Seleccionados:** Array de IDs de trámites (`ids[]`). Límite de seguridad: hasta 200 expedientes por lote.
2. **Áreas Destino (`sel_areas[]`):** Selector múltiple con buscador (Select2 / multiselect nativo). Permite derivar a 1, 2 o N áreas simultáneamente.
3. **Fecha de Envío (`fecha_envio`):** Input `datetime-local` con valor por defecto del momento actual (`now()`).
4. **Checkbox *"Crear Trámite Interno"* (`crear_tramite_interno`):**
   * Al activarlo, despliega un sub-formulario (`fieldset`):
     * **Tipo de Documento (`cdgo_tpo_trmte`):** Select de tipos de trámite autorizados para el área (`r_tpo_trmte_areas`).
     * **Nº Trámite / Correlativo (`nu_smlla_intrno`):** Se consulta automáticamente vía AJAX al seleccionar el tipo de documento.
     * **Botón *"Generar Borrador"*:** Descarga la plantilla Word procesada con los metadatos del trámite para redacción.
     * **PDF Firmado (`adjunto_firmado`):** Archivo PDF firmado digitalmente.
     * **Checkbox *"Físico?"* (`in_envo_fsco`):** Indica si el expediente también se traslada en soporte papel.
5. **Más Archivos Digitales:**
   * Soporte para adjuntar hasta 3 archivos digitales complementarios (Anexos 1, 2 y 3). El sistema debe consolidarlos junto al PDF principal en un archivo ZIP nombrado `adjun{ID}.zip`.
6. **Observaciones (`observaciones`):** Textarea con texto inicial por defecto: *"Sin novedades para enviar"*.

### 2.3. Lógica Transaccional (Base de Datos):
1. Para cada área destino seleccionada:
   * Se deshabilita el movimiento activo en `r_trmtes_ntrnos` (`in_esta = '0'`).
   * Se inserta un nuevo registro en `r_trmtes_ntrnos` con:
     * `cdgo_estdo_trmte = 1` (`INGRESADO`).
     * `cdgo_crgo_sa = cargo_actual`.
     * `cdgo_crgo_en = cargo_destino`.
     * `de_urls_agrdo = nombre_archivo_zip_o_pdf`.
     * `de_obser_ntrnos = observaciones`.
     * `fe_rcbdo = fecha_envio`.
     * `in_esta = '1'`.
2. Si se marcó *"Crear Trámite Interno"*:
   * Se genera un nuevo registro en `p_dtos_ntrnos` con su respectivo correlativo en `a_smlla_intrno`.
   * Se asocia la referencia en `cdgo_dtos_ntrnos_rfrncia`.
3. Se registra evento en `p_sgmto_log`.

---

## 3. Flujo 2: ASIGNAR TRÁMITE INTERNAMENTE (`segi_asignar.php`)

### 3.1. Propósito
Permite al jefe de área o encargado distribuir el trámite que está en **Recibidos** hacia un especialista, técnico o cargo interno dentro de su propia dependencia.

### 3.2. Campos del Modal
1. **Trámites Seleccionados:** Array de IDs (`ids[]`).
2. **Cargo Destino (`cdgo_crgo_en`):** Dropdown de cargos/usuarios pertenecientes a la **misma área activa** del usuario (`p_crgos WHERE cdgo_area = :area_usuario`).
3. **Observaciones / Proveído:** Instrucciones específicas para el colaborador asignado.
4. **Adjuntos Opcionales:** Documentos adicionales de trabajo interno.

### 3.3. Lógica Transaccional
* Se actualiza el movimiento previo a `in_esta = '0'`.
* Se inserta nuevo movimiento en `r_trmtes_ntrnos`:
  * `cdgo_estdo_trmte = 7` (`ASIGNADO`).
  * `cdgo_crgo_sa = cargo_actual`.
  * `cdgo_crgo_en = cargo_seleccionado`.
  * `in_esta = '1'`.
* El trámite pasa a la bandeja del colaborador asignado.

---

## 4. Flujo 3: FINALIZAR TRÁMITE (`segi_finaliza.php`)

### 4.1. Propósito
Concluye formalmente el trámite en **Recibidos**, cerrando el ciclo de atención administrativa.

### 4.2. Campos del Modal
1. **Trámites Seleccionados:** Array de IDs (`ids[]`).
2. **Observaciones de Cierre:** Fundamentación del cumplimiento o informe final.
3. **Adjunto de Conclusión:** Documento o acta de resolución/finalización.
4. **Opción de emisión de documento interno de cierre** (opcional, idéntico a derivación).

### 4.3. Lógica Transaccional
* Deshabilita el movimiento activo en `r_trmtes_ntrnos`.
* Inserta nuevo registro con `cdgo_estdo_trmte = 3` (`FINALIZADO`) o `24` (`FINAL. COPIA`).
* El trámite desaparece de Recibidos y se traslada a la bandeja **Finalizados**.

---

## 5. Flujo 4: DEVOLVER TRÁMITE (`segi_regresar.php`)

### 5.1. Propósito
Permite al área que tiene el trámite en **Salida** o **Recibidos** rechazarlo y devolverlo a su procedencia por observaciones de forma, falta de requisitos o incompetencia del área.

### 5.2. Campos del Modal
1. **Trámites Seleccionados:** Array de IDs (`ids[]`).
2. **Motivo de Devolución:** Textarea obligatorio justificando la causal del rechazo (*"Sin novedades para devolver"*).

### 5.3. Lógica Transaccional
* Inserta nuevo registro con `cdgo_estdo_trmte = 4` (`DEVUELTO`).
* Se retorna el trámite al cargo de origen para subsanación.

---

## 6. Flujo 5: REITERAR TRÁMITE (`segi_reiterar.php`)

### 6.1. Propósito
Desde la bandeja **Enviados**, cuando un área destino no atiende el trámite en plazo, el emisor emite un proveído o memorándum reiterativo exigiendo atención oportuna.

### 6.2. Campos del Modal
1. **Trámites Seleccionados:** Array de IDs (`ids[]`).
2. **Opción *"Enviar con trámite"* (`chemi`):** Genera correlativo de Memorándum o Proveído Reiterativo.
3. **Observaciones:** Texto de conminación o reiteración de plazo.
4. **Adjuntos digitales de reiteración.**

### 6.3. Lógica Transaccional
* Inserta nuevo movimiento con `cdgo_estdo_trmte = 53` (`REITERATIVO`) dirigido al área morosa.

---

## 7. Módulos Complementarios de Soporte

### 7.1. Hoja de Ruta (`segi_hoja_ruta.php`)
* **Endpoint:** `GET /correo_interno/hoja_ruta?id={CDGO_DTOS_NTRNOS}`
* **Vista:** Formato de impresión limpio A4 con membrete institucional.
* **Columnas:**
  1. `N°` (Secuencia cronológica 1, 2, 3...)
  2. `Destino` (`de_area`)
  3. `Fecha` (`fe_rcbdo`)
  4. `Estado` (`dc_estdo_trmte`)
  5. `Responsable` (`p_usrios.no_crto`)
  6. `Conformidad` (Casillero para sello y firma manual o validación digital)

### 7.2. Voucher de Movimiento (`busca_vauche.php` / `busca_vauche_adjun.php`)
* **Endpoint:** `GET /correo_interno/voucher?id={CDGO_DTOS_NTRNOS}&mov={CDGO_TRMTES_NTRNOS}`
* **Vista:** Ticket modal compacto de 470x310 px con número de trámite, fecha, hora, área de inicio, adjuntado y detalle del pase.

### 7.3. Modificar Adjunto Firmado (`ver_adjunto_modi.php`)
* **Endpoint:** `POST /correo_interno/modificar_adjunto`
* **Regla de Negocio:**
  * Si el usuario emisor o el administrador requiere corregir el archivo digital firmado por un error en el escaneo o sellado:
  * Permite subir un nuevo PDF o conjunto de archivos.
  * Reemplaza el archivo en `storage/app/private/mod_gene/mod_tram_inter/adjunto/` y actualiza `de_urls_adjnto` en `p_dtos_ntrnos` y `de_urls_agrdo` en `r_trmtes_ntrnos`.
  * Registra obligatoriamente auditoría en `p_sgmto_log`.
