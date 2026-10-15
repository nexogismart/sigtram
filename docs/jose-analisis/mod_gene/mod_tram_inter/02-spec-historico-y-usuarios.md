# SPEC-02: Panel de Histórico, Columna U (Usuarios) y Trazabilidad
> **Estándar:** Spec-Driven Development (Gentle-AI)  
> **Componente:** `mod_gene / mod_tram_inter / Histórico (segi_tramite.php)`  
> **Capturas de Referencia:** Imagen 1, Imagen 2 y en especial **Imagen 4 (Popover Columna U)**  
> **Archivos Legacy:** `segi_tramite.php`, `busca_vauche_adjun.php`, `segi_descargar_adjunto.php`  
> **Archivos Laravel:** `CorreoInternoController.php` (método `historial`), `correo-interno.blade.php` (`#panel-detalle`)

---

## 1. Contexto y Requerimiento Específico

En el sistema legacy, al seleccionar cualquier trámite de la tabla superior, el frame inferior carga el archivo `segi_tramite.php`. Esta sección es de vital importancia jurídica y administrativa para la municipalidad porque expone la **cadena de custodia completa del expediente** y la trazabilidad de qué funcionario lo tuvo, quién lo derivó y quiénes tienen acceso a recibirlo.

En la versión actual de Laravel, este panel fue simplificado a una tabla de 6 columnas que omite los datos más críticos solicitados por el usuario:
1. El banner Khaki distintivo con el nombre y número completo del trámite.
2. La columna **T.E** (Tiempo Estimado / Transcurrido).
3. La columna **U** (Usuarios), con el popover interactivo en Hover/Focus que divide a **Usuario Origen** a la izquierda y la lista de **Usuarios Destino** a la derecha (evidenciado en la **Foto 4**).
4. La columna **A** con auditoría de descargas en popup (Fecha, Usuario, IP).
5. La columna **D** con el voucher específico del pase.
6. La fila inferior `"Descargar Todos => [ZIP]"` que consolida todos los anexos del expediente en un ZIP con PDF fusionado.

---

## 2. Anatomía de la Cabecera del Histórico

* **Legacy:**
  ```html
  <span style="background-color:khaki; font-weight:bold; padding:4px 10px;">
      Histórico de MEMORANDUM 1683-2026-S.G.R.E.C.-
  </span>
  ```
  Construido a partir de: `$rows_tram["de_tpo_trmte"] . " " . $rows_tram["nu_tram_todo"] . "-" . $rows_tram["dc_area"]`.
* **Especificación Tailwind CSS en Laravel:**
  ```html
  <div class="text-center my-3">
      <span class="inline-block bg-[#f5f5dc] border border-[#e6e6c8] text-slate-800 text-xs font-black uppercase px-4 py-1.5 rounded-md shadow-sm tracking-wide">
          Histórico de <span id="hist-titulo-completo">MEMORANDUM 1683-2026-S.G.R.E.C.-</span>
      </span>
  </div>
  ```

---

## 3. Especificación de Columnas del Histórico (9 Columnas Exactas)

```
┌──────────┬──────┬─────────────┬───────────┬────────────┬───────────────────────────────┬───┬───┬───┐
│ Entrada  │ T.E  │ Area Salida │  Estado   │ Area Envio │         Observaciones         │ U │ A │ D │
└──────────┴──────┴─────────────┴───────────┴────────────┴───────────────────────────────┴───┴───┴───┘
```

### 3.1. Columna 1: `Entrada`
* **Formato:** `dd/mm/YYYY` (hora opcional en tooltip).
* **Origen de datos:** `r_trmtes_ntrnos.fe_rcbdo`.

### 3.2. Columna 2: `T.E` (Tiempo Estimado / Transcurrido)
* **Regla de Cálculo (Algoritmo exacto de `clase_procesos::gene_estimado`):**
  * Si el registro tiene `in_esta == '0'`: calcula el tiempo entre su recepción y la recepción del movimiento anterior (`$_SESSION["fepasa"]`).
  * Si el registro tiene `in_esta == '1'` y su estado es `FINALIZADO` o `FINAL. COPIA`: calcula el tiempo transcurrido entre `fe_ingrso` y `fe_rcbdo`.
  * En cualquier otro caso (trámite en curso): calcula el tiempo transcurrido entre `fe_rcbdo` y el momento actual (`now()`).
* **Unidades de salida:**
  * Días: `7d` (o `7D` en minúscula/mayúscula).
  * Horas: `2H` (o `2h`).
  * Minutos: `35m`.
  * Segundos: `15s`.
* **Estilo visual:** Centrado, texto en minúscula/negrita suave (`text-[11px] font-semibold text-slate-600`).

### 3.3. Columna 3: `Area Salida`
* **Texto visible:** Abreviatura del área remitente (`a_areas.dc_area`).
* **Atributo `title`:** Nombre completo del área remitente (`a_areas.de_area`).

### 3.4. Columna 4: `Estado`
* **Texto visible:** Descripción corta del estado del movimiento (`a_estdos_trmtes.dc_estdo_trmte`), por ejemplo: `INGRESADO`, `DERIVADO`, `RECIBIDO`, `ASIGNADO`, `FINALIZADO`, `DEVUELTO`.
* **Estilo visual:** Badge redondeado limpio con borde suave.

### 3.5. Columna 5: `Area Envio`
* **Texto visible:** Abreviatura del área destinataria (`a_areas_1.dc_area`).
* **Atributo `title`:** Nombre completo del área destinataria (`a_areas_1.de_area`).

### 3.6. Columna 6: `Observaciones`
* **Texto visible:** Contenido de `r_trmtes_ntrnos.de_obser_ntrnos`.
* **Alineación:** Justificado / texto regular (`text-[11px] text-gray-700`).

---

### 3.7. Columna 7: `U` (Usuarios — Análisis Forense de la Foto 4)

Este es el comportamiento central solicitado por el usuario.

#### Interacción en la UI:
1. **Icono:** Icono representativo de usuarios (`<i class="fas fa-users text-slate-600"></i>` o imagen PNG idéntica al legacy).
2. **Atributo `title`:** Nombre completo o login del funcionario emisor (ej. `"NICOLLE ALLANIS MARCELO ROJAS"`).
3. **Disparador de Interacción:** Eventos `mouseenter`, `focus` (y click en móviles/tablets).
4. **Popover Flotante (Popup Estilizado):**
   * Despliega una tarjeta flotante estilizada con sombra profunda (`shadow-2xl`) y borde sutil, dividida en dos columnas:
     * **Columna 1 (Izquierda — 50%):** Encabezado en negrita **`Usuario Origen`**. Debajo, el nombre del funcionario que ejecutó el pase (`p_usrios.no_crto`).
     * **Columna 2 (Derecha — 50%):** Encabezado en negrita **`Usuario Destino`**. Debajo, la lista de todos los funcionarios asignados al cargo destino.

#### Lógica Backend (SQL Contract):
* **Usuario Origen:**
  ```sql
  SELECT p_usrios.no_crto
  FROM sistema.p_lgin p_lgin
  INNER JOIN sistema.p_usrios p_usrios ON p_lgin.cdgo_usrios = p_usrios.cdgo_usrios
  WHERE p_lgin.cdgo_lgin = :cdgo_lgin;
  ```
* **Usuario(s) Destino:**
  * Si `cdgo_crgo_en == cdgo_crgo_sa`: el usuario destino es idéntico al usuario origen.
  * Si `cdgo_crgo_en != cdgo_crgo_sa`: se ejecuta la consulta de [`gene_cargo_lista_usuario(:cdgo_crgo_en)`](file:///c:/laragon/www/sigtram/Negocio/general.class.php#L3932):
    ```sql
    SELECT p_usrios.no_crto, p_crgos.de_crgo
    FROM sistema.p_crgos p_crgos
    INNER JOIN sistema.r_crgos_lgin r_crgos_lgin ON r_crgos_lgin.cdgo_crgo = p_crgos.cdgo_crgo
    INNER JOIN sistema.p_lgin p_lgin ON r_crgos_lgin.cdgo_lgin = p_lgin.cdgo_lgin
    INNER JOIN sistema.p_usrios p_usrios ON p_lgin.cdgo_usrios = p_usrios.cdgo_usrios
    WHERE p_lgin.in_esta = '1' AND p_crgos.cdgo_crgo = :cdgo_crgo_en;
    ```
    *Retorna todos los usuarios asignados al cargo para que el operador sepa a quiénes les cayó en bandeja dicho documento.*

#### Ejemplo de Salida según la Foto 4:
* **Usuario Origen:** `NICOLLE ALLANIS MARCELO ROJAS`
* **Usuario Destino:**
  1. `KASSANDRA FELIX SANCHEZ`
  2. `ROGER JHOEL PINASHCA HUERTA`
  3. `MIGUEL ERNESTO LOZANO CORREA`
  4. `SERGIO LUIS MELENDEZ MORA`
  5. `SISTEMAS SISTEMAS SISTEMAS`
  6. `ZAIDA LISETTE CASTILLA AQUINO`
  7. `JESUS MIGUEL VILLAVERDE ROJAS`
  8. `CAROLAI BEATRIZ CANCHARI TORRICHELLI`
  9. `MIRIAM ALEXANDRA VERGARA SANTOS`

---

### 3.8. Columna 8: `A` (Adjuntos con Trazabilidad de Descargas)
* **Si el movimiento tiene archivo adjunto (`de_urls_agrdo`):**
  * Icono Clip (`<i class="fas fa-paperclip"></i>`).
  * Enlace para abrir o descargar el documento.
  * **Popover en Mouseover / Focus:**
    Muestra la tabla de auditoría de quiénes descargaron ese archivo (`gene_sgmto_dscrgas`):
    * Columnas: **`Fecha`** | **`Usuario`** | **`IP`**.
    * Consulta contra `p_sgmto_dscrgas` unida con `p_lgin` y `p_usrios`.

### 3.9. Columna 9: `D` (Detalle de Movimiento / Voucher)
* **Icono:** Icono Ojo (`<i class="fas fa-eye"></i>`).
* **Acción:** Abre un modal compacto que reproduce el voucher de movimiento de `busca_vauche_adjun.php` (Número de trámite, fecha y hora del pase, archivo adjunto y observaciones específicas).

---

## 4. Pie de Tabla: `"Descargar Todos => [ZIP]"`

En la parte inferior de la tabla del histórico, a la derecha, debe existir la fila de consolidación:

* **Texto:** `"Descargar Todos => "`
* **Icono:** Icono de archivo ZIP / carpeta comprimida.
* **Acción:** Invoca el endpoint `/correo_interno/descargar-expediente-completo?id={CDGO_DTOS_NTRNOS}`.
* **Regla de Negocio (equivalente a `segi_descargar_adjunto.php`):**
  1. Consulta todos los adjuntos subidos en cada movimiento de la historia del trámite (`de_urls_agrdo`).
  2. Copia y empaqueta todos los archivos individuales en un archivo ZIP nombrado:  
     `archivos-{DE_TPO_TRMTE}-{NU_TRAM_TODO}-{DC_AREA}.zip`
  3. Si existen múltiples archivos PDF, ejecuta la función de unión de PDFs para crear un documento consolidado único nombrado:  
     `UNIDO-{DE_TPO_TRMTE}-{NU_TRAM_TODO}-{DC_AREA}.pdf` y lo incluye dentro del ZIP.
  4. Entrega el archivo como descarga directa al navegador.

---

## 5. Contrato JSON del Endpoint `/correo_interno/historial`

```json
{
  "status": "success",
  "tramite": {
    "de_tpo_trmte": "MEMORANDUM",
    "nu_tram_todo": "1683-2026",
    "dc_area": "S.G.R.E.C.",
    "titulo_completo": "MEMORANDUM 1683-2026-S.G.R.E.C.-"
  },
  "movimientos": [
    {
      "cdgo_trmtes_ntrnos": 154201,
      "fecha": "02/10/2026 16:08:00",
      "fecha_formateada": "02/10/2026",
      "tiempo_estimado": "7d",
      "area_salida_dc": "S.G.R.E.C.",
      "area_salida_de": "SUBGERENCIA DE REGISTRO CIVIL",
      "estado": "INGRESADO",
      "area_envio_dc": "O.T.I",
      "area_envio_de": "OFICINA DE TECNOLOGÍAS DE INFORMACIÓN",
      "observaciones": "PRIMER REITERATIVO Y AMPLIACIÓN DE SOLICITUD DE INFORMACIÓN...",
      "usuario_origen": "NICOLLE ALLANIS MARCELO ROJAS",
      "usuarios_destino": [
        "KASSANDRA FELIX SANCHEZ",
        "ROGER JHOEL PINASHCA HUERTA",
        "MIGUEL ERNESTO LOZANO CORREA",
        "SERGIO LUIS MELENDEZ MORA",
        "SISTEMAS SISTEMAS SISTEMAS",
        "ZAIDA LISETTE CASTILLA AQUINO",
        "JESUS MIGUEL VILLAVERDE ROJAS",
        "CAROLAI BEATRIZ CANCHARI TORRICHELLI",
        "MIRIAM ALEXANDRA VERGARA SANTOS"
      ],
      "adjunto_url": "adjunto/tramites/adjun154201.pdf",
      "adjunto_descargas": [
        {
          "fecha": "03/10/2026 09:15:20",
          "usuario": "SISTEMAS SISTEMAS SISTEMAS",
          "ip": "192.168.2.14"
        }
      ]
    }
  ]
}
```
