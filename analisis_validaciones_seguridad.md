# Plan y Análisis de Validaciones - Módulo de Seguridad (mod_seguridad)

Este documento contiene el análisis detallado de las validaciones tanto en el **Backend (Controladores)** como en el **Frontend (Vistas Blade)** del módulo de seguridad de **SIGTRAM3**, proponiendo mejoras concretas basadas en las reglas transversales del **Plan de Validaciones General**.

---

## 🚀 1. Reglas Transversales a Aplicar en mod_seguridad

### A. Sanitización y Reformateo
* **Trim y Espacios Múltiples:** Todos los campos de texto (`nc_prfle`, `de_prfle`, `dc_crgo`, `de_crgo`, `dc_rle`, `de_rle`, `ap_pate`, `ap_mate`, `no_usrio`) deben limpiarse con `trim()` y colapsar espacios internos múltiples antes de ser guardados o procesados con `strtoupper()`.
  * *Ejemplo:* `"  ADMINISTRADOR    DEL   SISTEMA  "` -> `"ADMINISTRADOR DEL SISTEMA"`
* **Caracteres invisibles:** Bloquear caracteres de control invisibles en inputs de texto.

### B. Campos Numéricos (DNI, Teléfono, IDs)
* **No usar `type="number"`:** Para campos identificadores (`DNI`, `Celular`, `Código`), usar siempre `type="text"` + `inputmode="numeric"` + restricción en eventos `keydown`/`input`.
* **Bloqueo de caracteres científicos:** Bloquear explícitamente `e`, `E`, `+`, `-`, `.`, `,` en el evento `keydown`.
* **DNI:** Estricto a 8 dígitos numéricos.
* **Teléfono:** Estricto a 9 dígitos numéricos para celulares de Perú (debe iniciar con `9`).

### C. Campos de Texto Personal (Nombres / Apellidos)
* **Regex estricta:** Permitir únicamente letras (incluidas tildes, diéresis y Ñ), espacios, apóstrofe y guion simple.
  * *Regex recomendada:* `/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/`

### D. Email
* Validar longitud máxima de 254 caracteres en backend (límite RFC).
* obligatorio.
* Formato valido email.

### E. Integridad Referencial (Exists en DB)
* Validar todas las claves foráneas recibidas en los request (`cdgo_prfle`, `cdgo_cmpnia`, `cdgo_area`, `cdgo_rle`, etc.) usando la regla `exists` de Laravel.

---

## 📁 2. Análisis Detallado de Controladores (Backend)

### 2.1 PerfilController.php
* **Ruta original:** [PerfilController.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/app/Http/Controllers/mod_seguridad/PerfilController.php)

#### Estado Actual:
```php
$request->validate([
    'nc_prfle' => ['required', 'string', 'max:100'],
    'de_prfle' => ['required', 'string', 'max:250'],
]);
```
* El ID del perfil (`cdgo_prfle`) no se valida en base de datos al actualizar.
* El método `destroy($id)` inactiva directamente el perfil sin validar dependencias.

#### Mejoras Propuestas:
1. **Validar existencia de IDs:**
   ```php
   'cdgo_prfle' => ['nullable', 'integer', 'exists:sistema.p_prfles,cdgo_prfle']
   ```
2. **Validar en `destroy`:**
   * Comprobar que el ID existe y que el perfil no esté asignado a ningún usuario activo en la tabla `sistema.p_lgin`.

---

### 2.2 CargoController.php
* **Ruta original:** [CargoController.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/app/Http/Controllers/mod_seguridad/CargoController.php)

#### Estado Actual:
```php
$request->validate([
    'dc_crgo' => ['required', 'string', 'max:100'],
    'de_crgo' => ['required', 'string', 'max:250'],
    'cdgo_area' => ['required', 'string'],
]);
```
* No se valida que `cdgo_area` exista en la tabla `sistema.a_areas`.
* `cdgo_crgo` (ID) al actualizar no se valida en BD.

#### Mejoras Propuestas:
1. **Validaciones en DB:**
   ```php
   'cdgo_area' => ['required', 'string', 'exists:sistema.a_areas,cdgo_area'],
   'cdgo_crgo' => ['nullable', 'integer', 'exists:sistema.p_crgos,cdgo_crgo']
   ```
2. **Control en `destroy`:** Evitar de baja de cargos asociados a usuarios en `sistema.r_crgos_lgin`.

---

### 2.3 OpcionController.php
* **Ruta original:** [OpcionController.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/app/Http/Controllers/mod_seguridad/OpcionController.php)

#### Estado Actual:
* `cdgo_mdlo` y `cdgo_sub_rle` se reciben como enteros pero no se comprueba su existencia en `sistema.a_mdlos` y `sistema.a_rles`.

#### Mejoras Propuestas:
1. **Validaciones cruzadas:**
   ```php
   'cdgo_mdlo' => ['required', 'integer', 'exists:sistema.a_mdlos,cdgo_mdlo'],
   'cdgo_sub_rle' => ['nullable', 'integer', 'exists:sistema.a_rles,cdgo_rle'],
   'cdgo_rle' => ['nullable', 'integer', 'exists:sistema.a_rles,cdgo_rle']
   ```
2. **Control de Borrado:** Impedir inactivar opciones padre que contengan submenús hijos activos.

---

### 2.4 PerfilOpcionController.php
* **Ruta original:** [PerfilOpcionController.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/app/Http/Controllers/mod_seguridad/PerfilOpcionController.php)

#### Estado Actual:
* `opcionesAsignadas($perfilId)` no valida el parámetro dinámico.
* `asignar` y `desasignar` no validan existencia física en BD.

#### Mejoras Propuestas:
1. **Validación en rutas/parámetros:** Validar que `$perfilId` sea un entero que exista en `sistema.p_prfles`.
2. **Modificar validaciones:**
   ```php
   $request->validate([
       'cdgo_prfle' => ['required', 'integer', 'exists:sistema.p_prfles,cdgo_prfle'],
       'cdgo_rle' => ['required', 'integer', 'exists:sistema.a_rles,cdgo_rle'],
   ]);
   ```

---

### 2.5 UsuarioController.php
* **Ruta original:** [UsuarioController.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/app/Http/Controllers/mod_seguridad/UsuarioController.php)

#### Estado Actual:
* `buscarDni($dni)` recibe el parámetro por ruta y no valida su formato.
* `nu_tele` no restringe caracteres y `de_mail` no tiene validación de dominio institucional.
* `cdgo_prfle` y `cdgo_cmpnia` no se validan contra sus tablas maestras.

#### Mejoras Propuestas:
1. **DNI y Teléfono:**
   * Validar `$dni` en búsqueda: regex `/^\d{8}$/`.
   * Validar teléfono: `'nu_tele' => ['nullable', 'regex:/^9\d{8}$/']`.
2. **Nombres y Apellidos:**
   ```php
   'ap_pate' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\' -]+$/'],
   'ap_mate' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\' -]+$/'],
   'no_usrio' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\' -]+$/'],
   ```
3. **Correo Obligatorio y Relaciones:**
   ```php
   'de_mail' => ['required', 'email', 'max:254'], // Cambiado a required por solicitud del usuario
   'cdgo_prfle' => ['required', 'integer', 'exists:sistema.p_prfles,cdgo_prfle'],
   'cdgo_cmpnia' => ['required', 'integer', 'exists:sistema.a_cmpnias,cdgo_cmpnia'],
   'cargos.*' => ['integer', 'exists:sistema.p_crgos,cdgo_crgo'],
   ```

---

### 2.6 AccesoController.php
* **Ruta original:** [AccesoController.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/app/Http/Controllers/mod_seguridad/AccesoController.php)

#### Estado Actual:
* Métodos de lectura `cargosArea($areaId)` y `cargosUsuario($loginId)` no validan parámetros.
* Métodos de asignación/eliminación no comprueban existencia real de los IDs.

#### Mejoras Propuestas:
1. **Validar Existencia en DB:**
   ```php
   'cdgo_lgin' => ['required', 'integer', 'exists:sistema.p_lgin,cdgo_lgin'],
   'cdgo_crgo' => ['required', 'integer', 'exists:sistema.p_crgos,cdgo_crgo'],
   'cdgo_crgo_lgin' => ['required', 'integer', 'exists:sistema.r_crgos_lgin,cdgo_crgo_lgin'],
   ```

---

## 🎨 3. Análisis Detallado de Vistas Blade (Frontend / UI)

### 3.1 [seguridad-perfiles.blade.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/resources/views/mod_seguridad/seguridad-perfiles.blade.php)
* **Inputs:** `nc_prfle` (Nombre) y `de_prfle` (Descripción).
* **Mejoras Frontend:**
  * Implementar directiva de AlpineJS o event handlers (`@blur` o `@input`) para aplicar `trim` en tiempo real y remover múltiples espacios.
  * Cambiar a mayúsculas en tiempo real en la UI mediante CSS (`class="uppercase"`) e input handlers para mejorar UX, ya que el backend realiza conversión `strtoupper`.

---

### 3.2 [seguridad-cargos.blade.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/resources/views/mod_seguridad/seguridad-cargos.blade.php)
* **Inputs:** `dc_crgo` (Abreviatura) y `de_crgo` (Cargo).
* **Mejoras Frontend:**
  * **Abreviatura (`dc_crgo`):** Limitar entrada a caracteres alfanuméricos y guion bajo (`[A-Z0-9_]`), bloqueando espacios y caracteres especiales directamente en el teclado (`keydown`).
  * **Cargo (`de_crgo`):** Aplicar sanitización de espacios extras y conversión visual a mayúsculas.

---

### 3.3 [seguridad-opciones.blade.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/resources/views/mod_seguridad/seguridad-opciones.blade.php)
* **Inputs:** `dc_rle` (Etiqueta), `de_rle` (Descripción), `de_dreccon` (Dirección/Ruta).
* **Mejoras Frontend:**
  * **Dirección/Ruta (`de_dreccon`):** Bloquear el ingreso de espacios, caracteres especiales (salvo `/`, `-`, `_`, `.`) y forzar minúsculas en tiempo real.
  * **Etiqueta (`dc_rle`):** Limpiar espacios al desenfocar (`blur`).

---

### 3.4 [seguridad-perfiles-opciones.blade.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/resources/views/mod_seguridad/seguridad-perfiles-opciones.blade.php)
* **Estructura:** Lista interactiva y peticiones `fetch()` vía AlpineJS.
* **Mejoras Frontend:**
  * Agregar manejo de respuestas de error. Actualmente los `fetch()` no controlan respuestas de estado fallido (ej. error 500 o 422 de Laravel) y asumen que siempre tienen éxito (`if (res.success)`). Se debe capturar errores y mostrar alertas visuales adecuadas.

---

### 3.5 [seguridad-accesos.blade.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/resources/views/mod_seguridad/seguridad-accesos.blade.php)
* **Estructura:** Asignaciones mediante `fetch()` y alertas usando `alert(res.message)`.
* **Mejoras Frontend:**
  * Reemplazar las alertas nativas del navegador `alert(...)` por un sistema de notificaciones/toasts nativos estilizados con Tailwind, manteniendo la estética premium y uniforme del sistema.
  * Controlar posibles fallas de conexión o de validaciones de Laravel en las peticiones asíncronas.

---

### 3.6 [seguridad-usuario.blade.php](file:///c:/Users/user/Documents/MUNI%20PUENTE%20PIEDRA/MuniPPWeb/sigtram3/resources/views/mod_seguridad/seguridad-usuario.blade.php)
* **Inputs:** `nu_docu` (DNI), `no_usrio` (Nombres), `ap_pate` (Paterno), `ap_mate` (Materno), `de_dire` (Dirección), `nu_tele` (Teléfono), `de_mail` (Email), `de_lgin` (Usuario), `de_pass` (Contraseña).
* **Mejoras Frontend (Críticas):**
  * **Número DNI (`nu_docu`):**
    * Cambiar input a: `type="text" inputmode="numeric" maxlength="8" pattern="\d{8}"`.
    * En el evento `keydown`, filtrar entrada de teclado para permitir solo números (0-9) y teclas de control (Backspace, Arrow keys). Bloquear `e`, `E`, `+`, `-`, `.`, `,`.
  * **Teléfono (`nu_tele`):**
    * Cambiar input a: `type="text" inputmode="numeric" maxlength="9" pattern="9\d{8}"`.
    * Filtrar teclas de control para admitir únicamente números y forzar que el primer carácter sea siempre `9`.
  * **Nombres / Apellidos:**
    * Validar con regex `/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\' -]+$/` en eventos `@input`.
    * Aplicar `trim` y compactación de espacios internos al desenfocar (`@blur`).
  * **Usuario / Login (`de_lgin`):**
    * Forzar minúsculas en tiempo real, bloqueando espacios y caracteres con tildes.

---

## 🔴 4. Validación Visual y Textos de Advertencia en Rojo

Para implementar las advertencias visuales en rojo debajo de cada campo del formulario cuando la información sea incompleta o errónea, se propone estructurar los formularios utilizando el estado reactivo de **Alpine.js**.

### A. Estructura del Estado en Alpine.js
Cada componente debe inicializar un objeto `errors` vacío dentro del estado principal de la vista:

```javascript
formData: {
    nu_docu: '',
    no_usrio: '',
    ap_pate: '',
    ap_mate: '',
    de_mail: '',
    de_lgin: '',
    de_pass: '',
    cdgo_prfle: '',
    cdgo_cmpnia: ''
},
errors: {
    nu_docu: '',
    no_usrio: '',
    ap_pate: '',
    ap_mate: '',
    de_mail: '',
    de_lgin: '',
    de_pass: '',
    cdgo_prfle: '',
    cdgo_cmpnia: ''
}
```

### B. Lógica de Validación en el Submit
Al intentar guardar la información, se ejecuta una función de verificación que limpia los errores anteriores y rellena las advertencias correspondientes si falta completar algún campo obligatorio o no se cumple el formato:

```javascript
validateForm(event) {
    // Reiniciar los errores
    this.errors = {
        nu_docu: '', no_usrio: '', ap_pate: '', ap_mate: '',
        de_mail: '', de_lgin: '', de_pass: '',
        cdgo_prfle: '', cdgo_cmpnia: ''
    };
    
    let isValid = true;

    // Validación DNI
    if (!this.formData.nu_docu) {
        this.errors.nu_docu = 'El número de DNI es obligatorio.';
        isValid = false;
    } else if (this.formData.nu_docu.length !== 8) {
        this.errors.nu_docu = 'El DNI debe contener exactamente 8 dígitos.';
        isValid = false;
    }

    // Validación Nombres
    if (!this.formData.no_usrio.trim()) {
        this.errors.no_usrio = 'Los nombres son obligatorios.';
        isValid = false;
    }

    // Validación Apellido Paterno
    if (!this.formData.ap_pate.trim()) {
        this.errors.ap_pate = 'El apellido paterno es obligatorio.';
        isValid = false;
    }

    // Validación Apellido Materno
    if (!this.formData.ap_mate.trim()) {
        this.errors.ap_mate = 'El apellido materno es obligatorio.';
        isValid = false;
    }

    // Validación Email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!this.formData.de_mail) {
        this.errors.de_mail = 'El correo electrónico es obligatorio.';
        isValid = false;
    } else if (!emailRegex.test(this.formData.de_mail)) {
        this.errors.de_mail = 'Ingrese un formato de correo válido.';
        isValid = false;
    }

    // Validación Perfil
    if (!this.formData.cdgo_prfle) {
        this.errors.cdgo_prfle = 'Debe seleccionar un perfil.';
        isValid = false;
    }

    // Si no es válido, cancelar el envío del formulario
    if (!isValid) {
        event.preventDefault();
        // Hacer scroll automático al primer input con error
        const firstErrorEl = document.querySelector('.border-red-500');
        if (firstErrorEl) firstErrorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
```

### C. Código HTML e Integración Visual (UX/UI Premium)
Para cada input del formulario, se agrega una clase condicional de Tailwind (`border-red-500` y `focus:ring-red-500`) y una etiqueta `<span>` en color rojo debajo del input para advertir visualmente la omisión del campo:

```html
<div>
    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="nu_docu">Número DNI</label>
    <div class="relative">
        <input type="text" name="nu_docu" id="nu_docu" x-model="formData.nu_docu" required maxlength="8"
               :class="errors.nu_docu ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
               class="w-full pl-3 pr-9 py-2 border rounded-xl text-xs font-semibold outline-none transition-all shadow-sm">
    </div>
    <!-- Mensaje de advertencia rojo -->
    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" 
          x-show="errors.nu_docu" 
          x-text="errors.nu_docu"></span>
</div>
```
