# SPEC-05: Cierre de Producción, Seguridad y Paridad

> **Módulo:** `mod_gene / mod_tram_inter`  
> **Estado:** `READY_FOR_IMPLEMENTATION — NO APTO PARA PRODUCCIÓN`  
> **Rama autorizada:** `codex/back`  
> **Restricción P0:** prohibido crear o ejecutar migraciones, DDL, DML manual, seeds o scripts contra PostgreSQL.  
> **Entrega:** cambios locales sin commit, push, merge ni pull request.

## 1. Objetivo

Cerrar las brechas comprobadas después de la primera implementación de Antigravity y dejar Trámite Interno / Correo Interno listo para una auditoría final independiente. La implementación debe conservar el modelo de datos existente, la paridad funcional legacy y las URLs públicas actuales.

Esta especificación reemplaza cualquier afirmación previa de “100% resuelto” mientras no existan pruebas y evidencia para todos los criterios de aceptación.

## 2. Guardas obligatorias

1. Trabajar exclusivamente en la rama local `codex/back`.
2. No ejecutar `git commit`, `git push`, `git merge`, `git rebase` ni crear PR.
3. No conectarse a PostgreSQL durante la implementación o las pruebas automáticas.
4. No crear migraciones, tablas, columnas, índices, triggers, funciones ni secuencias.
5. Si se considera necesario un cambio o índice en BD, detener esa parte y documentar una propuesta SQL separada para ejecución manual del propietario. La solución principal debe funcionar sin ese cambio.
6. No modificar el legacy. Puede leerse únicamente como evidencia funcional.
7. No copiar archivos subidos al webroot legacy ni a `public/`.
8. No crear respaldos, archivos `*.bak`, `*.old`, `*.tmp`, copias de controladores ni documentación duplicada.
9. Reutilizar las abstracciones existentes (`GestorAdjuntosInterface`, DTOs, Policies, Repository/Service) o ampliarlas limpiamente.
10. Toda modificación debe estar respaldada por una prueba automatizada que no requiera BD real.

## 3. Requisitos P0 de control de acceso

### SEC-TI-01: Contexto de usuario confiable

- El usuario, login, compañía, cargo y área deben resolverse desde `Auth` y la sesión autorizada.
- Nunca se aceptará `cdgo_cmpnia`, cargo actual, área actual o login actual desde la petición.
- Validar que `sigtram_cargo_id` esté activo y asignado al login autenticado mediante `r_crgos_lgin`.
- Toda consulta debe quedar limitada a la compañía autenticada cuando la entidad disponga de `cdgo_cmpnia`.
- Eliminar el valor fijo `cdgo_cmpnia = 1`.
- Los permisos legacy de operación, incluido el permiso `18` donde corresponda, deben representarse mediante Policy/Gate o servicio de autorización centralizado.

### SEC-TI-02: Policy por recurso y acción

Crear una Policy de Trámite Interno con capacidades explícitas:

- `view`
- `viewHistory`
- `download`
- `receive`
- `assign`
- `forward`
- `finish`
- `return`
- `reiterate`
- `replaceSignedAttachment`
- `viewAccounting`

La Policy debe verificar como mínimo compañía, cargo/área participante, estado activo y transición permitida. La autorización debe ejecutarse también en histórico, detalle, voucher, hoja de ruta, descarga individual, descarga completa y descarga por lote.

### SEC-TI-03: Protección contra IDOR y mass assignment lógico

- Los IDs recibidos son referencias no confiables.
- Resolver los movimientos autorizados dentro del Repository; no consultar primero sin scope y autorizar después.
- Si un lote contiene un solo ID no autorizado, rechazar toda la operación sin cambios parciales.
- No insertar un nuevo movimiento si la actualización del movimiento activo no afectó exactamente una fila.
- No revelar si un ID ajeno existe: responder `404` o mensaje homogéneo según el contrato del módulo.
- Definir máximo de 100 IDs por operación y rechazar duplicados.

## 4. Validación de peticiones y archivos

### SEC-TI-04: FormRequests dedicados

Crear FormRequests independientes para:

- `ListarBandejaInternaRequest`
- `ListarHistoricoInternoRequest`
- `RecepcionarTramiteInternoRequest`
- `DerivarTramiteInternoRequest`
- `AsignarTramiteInternoRequest`
- `FinalizarTramiteInternoRequest`
- `DevolverTramiteInternoRequest`
- `ReiterarTramiteInternoRequest`
- `DescargarLoteTramiteInternoRequest`
- `CorrelativoTramiteInternoRequest`

Reglas mínimas:

- `ids`: array, `min:1`, `max:100`, valores enteros positivos y distintos.
- Fechas: formato explícito y rango razonable.
- Observaciones: texto plano, máximo definido. La causal de devolución es obligatoria y no puede ser el texto genérico por defecto.
- Áreas y cargos destino: enteros positivos y posteriormente autorizados contra el contexto.
- Archivos: máximo cuatro; principal PDF cuando el flujo lo exija; allowlist de MIME real y extensión; 20 MiB máximo por archivo y límite total del lote.

### SEC-TI-05: Almacenamiento privado

- Usar `GestorAdjuntosInterface` o un servicio equivalente con disco privado.
- Generar nombres internos, no confiar en el nombre ni extensión del cliente.
- Verificar MIME real, extensión permitida y tamaño antes de persistir.
- Prohibido `move()` directo a rutas construidas manualmente.
- Prohibido copiar a `public/` o a `sigtram/Presentacion/.../adjunto`.
- Ante rollback, eliminar archivos creados; ante error de archivo, no confirmar la transacción.
- No usar `@mkdir`, `@copy`, `@unlink` ni permisos `0777`.

## 5. Transacciones, estados y concurrencia

### REL-TI-01: Máquina de estados

Centralizar los estados en un Enum y validar transiciones:

- Recepción: `1 -> 2` y regla documentada para copias.
- Asignación: estado `7`.
- Finalización: `3` o `24` según semántica legacy comprobada.
- Devolución: estado `4` con causal obligatoria.
- Reiteración: estado `53` solo desde un expediente enviado y autorizado.

No codificar números mágicos en controladores o vistas.

### REL-TI-02: Locks e idempotencia sin cambiar BD

- Dentro de `DB::transaction`, recuperar el movimiento activo con `lockForUpdate()`.
- Revalidar Policy y estado después de obtener el lock.
- Rechazar una repetición si el movimiento ya dejó el estado origen.
- El doble clic o dos solicitudes simultáneas deben producir un solo movimiento válido.
- El mensaje de éxito debe indicar únicamente la cantidad realmente procesada.

### REL-TI-03: Correlativo seguro

- El correlativo mostrado en pantalla es informativo, no una reserva.
- Recalcular y reservar el correlativo dentro de la transacción.
- Bloquear la fila/rango existente de `a_smlla_intrno` al calcular `max + 1`.
- Validar que el tipo de documento esté habilitado para el área/cargo actual.
- No consumir una secuencia de movimiento únicamente para nombrar un archivo.

## 6. Descargas y trazabilidad

### SEC-TI-06: Resolución segura

- La petición debe identificar un adjunto o movimiento autorizado, no aceptar rutas físicas arbitrarias.
- Aplicar `basename`, normalización y comprobación `realpath` dentro del root privado.
- Rechazar valores con traversal, separadores inesperados, enlaces simbólicos fuera del root o archivos no regulares.
- No buscar archivos en rutas públicas genéricas.

### AUD-TI-01: Auditoría obligatoria

- Toda descarga individual, voucher con adjunto, ZIP completo y ZIP por lote debe registrar usuario, IP, fecha y archivo en `p_sgmto_dscrgas` usando el contrato existente.
- La auditoría no puede quedar en `catch` vacío.
- Si el registro de auditoría es requisito legal y falla, la descarga debe bloquearse.
- No exponer mensajes de excepción o rutas absolutas al cliente.

### FUN-TI-01: Expediente consolidado

- El ZIP debe incluir los adjuntos autorizados sin colisiones de nombres.
- Si existen PDFs compatibles, generar el consolidado `UNIDO-*.pdf` exigido por la especificación legacy e incluirlo en el ZIP.
- Si un PDF está cifrado/corrupto, informar qué elemento no pudo consolidarse sin exponer rutas y aplicar la regla funcional acordada.
- El ZIP temporal debe tener nombre aleatorio, permisos restrictivos y limpieza garantizada incluso ante error.

## 7. Seguridad del frontend y accesibilidad

### SEC-TI-07: XSS contextual

- Eliminar handlers inline que interpolen datos (`onclick="funcion('${dato}')"`).
- Renderizar IDs y valores en `data-*` seguros y registrar listeners con `addEventListener`.
- Usar `textContent` para texto libre.
- Si se genera HTML, escapar según contexto; el helper HTML no debe reutilizarse dentro de JavaScript o URL.
- Añadir pruebas con observaciones, nombres y adjuntos que contengan comillas simples, dobles, `<`, `>`, `&` y payloads XSS.

### UX-TI-01: Popover de usuarios igual al legacy

- El icono `U` debe abrir el popover de dos columnas por `mouseenter` y `focus`.
- Debe permanecer visible al mover el puntero al contenido.
- Debe cerrarse con `mouseleave`, `blur` controlado y tecla `Escape`.
- En dispositivos táctiles debe alternarse por clic.
- Columna izquierda: Usuario Origen. Columna derecha: todos los Usuarios Destino activos con cargo.
- No reemplazarlo únicamente por un modal. El modal ampliado puede mantenerse como opción secundaria.

### UX-TI-02: Estado visual del menú y tipografía consistente

- El botón de la bandeja activa debe conservar fondo oscuro y texto blanco durante `hover`, `focus`, carga y finalización de la petición.
- Una clase de hover de estado inactivo no puede permanecer aplicada al botón activo. En particular, evitar la combinación `bg-slate-800 text-white hover:bg-slate-50`.
- El estado activo no debe depender de que el puntero abandone el botón.
- Exponer el estado con `aria-current="page"` y mantener un foco visible accesible.
- Las ocho bandejas deben usar la misma familia tipográfica, escala, altura de fila y jerarquía visual para columnas equivalentes.
- No aplicar `font-mono` de manera aislada a Enviados si no forma parte del diseño común de números de trámite para todas las bandejas.
- El cambio de bandeja debe mostrar un indicador de carga sin borrar ni volver ilegible el botón activo.

## 8. Brechas funcionales restantes

### FUN-TI-02: Comprobantes

- Sustituir el aviso “Módulo en proceso de integración” por la funcionalidad de consulta equivalente a `ver_comprobantes_pago.php`.
- Aplicar permiso y scope de Contabilidad.
- No modificar el modelo de datos.

### FUN-TI-03: Reemplazo de adjunto firmado

- Implementar el equivalente seguro de `ver_adjunto_modi.php`.
- En la bandeja Enviados, agregar una columna `D` explícita y su icono de ojo; hacer clic en toda la fila para abrir el Histórico no sustituye esta acción.
- El ojo debe abrir el detalle del documento y, cuando la Policy lo autorice, permitir reemplazar el PDF firmado.
- Mantener separadas las acciones `H` (Hoja de Ruta), `A` (Adjunto/descarga) y `D` (Detalle/reemplazo); no reutilizar un icono con semánticas ambiguas.
- Solo el rol/cargo autorizado puede reemplazar el documento.
- Conservar trazabilidad del evento mediante las tablas de auditoría existentes.
- Validar PDF real, tamaño y almacenamiento privado.

### FUN-TI-04: Impresión y exportación

- No construir un `new Request()` sin sesión para reutilizar el método de bandeja.
- Extraer la consulta a un Query Service/Repository reutilizable.
- La impresión debe respetar todos los filtros y no limitarse silenciosamente a 100 filas.
- Si el archivo es CSV, denominarlo CSV. Si se ofrece XLSX, generar XLSX real.
- La exportación completa debe ser autorizada, acotada y procesada sin cargar datasets ilimitados en memoria.

### FUN-TI-05: Histórico y cálculo T.E

- Verificar el orden requerido por el algoritmo legacy antes de calcular `$fepasa`.
- Probar intervalos de segundos, minutos, horas, un día, dos días y finalizados.
- Un trámite inexistente o no autorizado debe responder correctamente, no devolver “éxito” con título de documento inexistente.

### FUN-TI-06: Validación logística tipo 64

- Debe operar en modo fail-closed.
- Si Logística no puede consultarse, bloquear recepción con error controlado.
- Importar y utilizar correctamente el logger.
- Ejecutar la validación en lote para evitar N+1.

## 9. Arquitectura objetivo incremental

No hacer una reescritura masiva. Refactorizar por extracción manteniendo rutas y contratos externos:

```text
app/Features/TramiteInterno/
├── Domain/
│   ├── Enums/EstadoTramiteInterno.php
│   ├── DTOs/
│   └── Policies/TramiteInternoPolicy.php
├── Application/
│   ├── Actions/
│   └── Queries/
├── Infrastructure/
│   ├── Persistence/PostgresTramiteInternoRepository.php
│   ├── Files/AdjuntosTramiteInterno.php
│   └── Audit/AuditoriaTramiteInterno.php
└── Http/
    ├── Controllers/
    ├── Requests/
    └── Resources/
```

Los controladores deben limitarse a autorizar, recibir un FormRequest, invocar un caso de uso y construir la respuesta.

## 10. Definition of Done

El módulo solo puede marcarse `READY_FOR_REVIEW` cuando:

1. No existe ningún endpoint de lectura o escritura sin Policy.
2. No existe carga o descarga fuera del gestor privado de adjuntos.
3. No existe SQL con valores de usuario concatenados.
4. No existen handlers inline con contenido dinámico.
5. Paginación de bandejas e histórico cumple SPEC-06.
6. Popover `U` funciona con hover, focus, teclado y táctil.
7. El menú no presenta texto blanco sobre fondo blanco y la tipografía es consistente en las ocho bandejas.
8. Enviados contiene las columnas/acciones `H`, `A` y `D`, incluido el ojo legacy autorizado.
9. Comprobantes y reemplazo de firmado ya no son placeholders.
10. ZIP consolidado, auditoría de descargas, impresión y exportación cumplen sus contratos.
11. PHPStan del alcance termina con cero errores nuevos y sin suppressions.
12. Las pruebas unitarias/feature del módulo pasan sin conexión a PostgreSQL.
13. `git diff --check` no reporta errores.
14. Se entrega una matriz de evidencia requisito → prueba → archivo, sin declarar “100%” sin evidencia.

