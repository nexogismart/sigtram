# SDD — Conteos de bandeja y mejoras transversales Interno/Externo

## Estado

`APPROVED_FOR_IMPLEMENTATION — SIN CAMBIOS DE BD`

## Diagnóstico confirmado

- Trámite Externo muestra badges únicamente para `Entrada` y `Recibidos`; las demás bandejas no presentan total.
- Esos dos conteos se calculan al abrir la vista y pueden quedar obsoletos después de una operación.
- Trámite Interno no muestra totales en el menú. Solo muestra el total filtrado de la bandeja actualmente abierta en la cabecera.
- Interno ya posee `TramiteInternoRepository::contarBandeja()`, pero `CorreoInternoController::tabla()` continúa construyendo SQL interpolado y no usa el repositorio nuevo.
- La vista Interna incluye JavaScript dentro de una etiqueta `<script src="https://cdn.tailwindcss.com">`; el contenido inline de un script con `src` no constituye una inicialización fiable.
- Ambas vistas dependen de CDN de Tailwind, Google Fonts y Font Awesome en lugar de los assets compilados del proyecto.
- Interno mantiene listeners repetidos y varios renderizados con `innerHTML`; aunque existe un helper de escape, la superficie es frágil y difícil de mantener.

## Conteos unificados

### Comportamiento visible

Cada bandeja visible debe mostrar su total global para el contexto activo:

```text
Entrada          12
Recibidos         8
Salida           31
Finalizados     104
Notificados      19
Archivo         245
Prestados         3
Inventariar       6
Todos           428
Sin finalizar    27
```

En Interno se muestran únicamente las bandejas que realmente existan para el rol/área: Entrada, Enviados, Todos, Recibidos, Salida, Finalizados, Sin finalizar y Contabilidad cuando corresponda.

### Semántica

- El badge lateral representa el total sin filtros de texto/fecha para la bandeja y contexto actuales.
- La cabecera de la tabla representa el total resultante de los filtros activos.
- No mezclar ambos conceptos ni reemplazar el total lateral por el tamaño de la página.
- Durante carga mostrar `—` o skeleton; no mostrar `0` hasta recibir respuesta válida.
- Después de una mutación exitosa, refrescar la tabla, el histórico afectado y todos los conteos.

### Contrato HTTP

Endpoint autenticado por módulo:

```json
{
  "status": "success",
  "data": {
    "entrada": 12,
    "recibidos": 8,
    "salida": 31,
    "finalizados": 104
  },
  "meta": {
    "contexto": "cargo",
    "actualizado_en": "2026-10-10T10:00:00-05:00"
  }
}
```

- Solo incluir bandejas autorizadas y conocidas.
- Los conteos deben reutilizar exactamente los mismos scopes que `listarBandeja`; queda prohibida una segunda definición divergente.
- Resolverlos en una operación de repositorio agrupada o con el mínimo de consultas comprobable.
- Cache corta opcional de 15–30 segundos por compañía, cargo, área y permisos; invalidar después de mutaciones propias.
- Nunca cachear por usuario solamente si el cargo activo puede cambiar.

## Migración arquitectónica pendiente en Interno

### Listado

- `CorreoInternoController::tabla()` debe recibir `ListarBandejaInternaRequest`.
- Debe crear el DTO de contexto mediante `SessionKeys`, no literales de sesión.
- Debe delegar en `TramiteInternoRepositoryInterface::listarBandeja()`.
- Trasladar y preservar al repositorio los casos especiales de referencia, faltantes tipo 64 y contabilidad.
- Eliminar SQL concatenado/interpolado y `DB::select()` del controlador.
- No perder columnas consumidas por cada variante de tabla.

### Histórico

- Delegar listado/paginación del histórico en el repositorio.
- Autorizar antes de recuperar cabecera, usuarios, adjuntos o auditoría.
- Mantener el cálculo T.E. consistente entre páginas mediante fila frontera.
- Eliminar consultas duplicadas y listeners duplicados de U.

### Controlador

- Inyectar repositorio, Policy y servicios por constructor; no resolverlos con `app()` dentro de métodos.
- Limitar el controlador a contexto, validación, autorización, invocación y respuesta.
- Conservar las rutas y contratos públicos para no romper el menú legacy migrado.

## Mejoras frontend obligatorias

### Assets y carga

- Usar los assets del pipeline Vite existente; retirar Tailwind CDN runtime.
- No depender de Google Fonts/Font Awesome remotos si ya existen alternativas locales o empaquetadas.
- No generar un build versionado sin revisar el diff.
- Corregir la etiqueta script mal formada antes de evaluar listeners.

### Estado asíncrono

- Usar `AbortController` o un identificador monotónico para descartar respuestas anteriores al cambiar rápidamente de bandeja/filtros.
- Un único loading por superficie y botones bloqueados durante mutaciones.
- Diferenciar vacío, error de red, 403/404, 409 de concurrencia y 422 de validación.
- No ocultar errores mostrando “sin registros”.

### Renderizado y XSS

- Preferir plantillas Blade/Alpine o creación DOM con `textContent`.
- Si se mantiene HTML generado, separar estructura estática de datos y escapar texto y atributos con funciones distintas.
- No insertar URLs del servidor en handlers inline.
- Eliminar listeners duplicados y registrar cada listener una sola vez.

### Usabilidad

- Retirar `select-none` global; permitir copiar número, observaciones y nombres.
- Mantener encabezado sticky, tabla scrollable y acciones visibles sin reducir el histórico a una franja inutilizable.
- Conservar selección solo al cambiar de página si el producto lo define explícitamente; por defecto limpiarla al cambiar de bandeja o filtros.
- Mostrar “Mostrando X–Y de Z” y selector 10/25/50 en ambos módulos.
- Mantener el estado seleccionado con contraste AA: nunca texto blanco sobre fondo blanco durante carga/clic.

### Accesibilidad

- `aria-current` en bandeja activa y badge con etiqueta legible.
- Focus visible y retorno de foco desde modales.
- Tablas con headers asociados; botones de solo icono con `aria-label`.
- `aria-live` para conteos, resultados, errores y éxito.
- No abrir modales mediante hover de forma automática; hover puede mostrar preview, clic/foco abre interacción persistente.

## Rendimiento

- Paginación de servidor para bandejas e histórico.
- Consultas selectivas: no recuperar columnas no usadas ni listas completas de U en la carga inicial.
- Endpoint U lazy, con búsqueda y paginación según spec 10.
- Evitar N+1 en usuarios, áreas, cargos, auditoría y adjuntos.
- No consultar todos los conteos por cada pulsación del filtro; los filtros solo actualizan el total de cabecera.

## Pruebas obligatorias

1. Cada badge coincide con `contarBandeja()` bajo el mismo contexto.
2. El total lateral no cambia con filtros; el total de cabecera sí.
3. Mutación exitosa refresca badges una sola vez.
4. Usuario cambia de cargo activo y no recibe cache del cargo anterior.
5. Bandeja no autorizada no aparece ni se cuenta.
6. Respuestas AJAX fuera de orden no reemplazan la bandeja actual.
7. Estado 403/404/409/422 no se representa como lista vacía.
8. No hay Tailwind CDN ni script con `src` y contenido inline.
9. No existe SQL interpolado en el flujo de listado Interno.
10. Navegación de bandejas y modal U completa solo con teclado.

## Definition of Done

- Todas las bandejas visibles poseen badge coherente y actualizado.
- Interno y Externo comparten convenciones de conteo, paginación, errores y carga.
- Interno usa realmente FormRequest/Repository/Policy en tabla e histórico.
- No hay SQL de negocio ni construcción de queries en el controlador principal.
- Los assets se sirven mediante el pipeline del proyecto.
- La UI funciona con ratón, teclado y pantalla pequeña sin pérdida de información.
- No se modifica la BD.

