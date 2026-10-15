# SDD — Columna U unificada para Trámite Interno y Externo

## Estado

`APPROVED_FOR_IMPLEMENTATION — SIN CAMBIOS DE BD`

## Objetivo

Unificar la visualización de participantes de cada movimiento histórico para que funcione con uno, varios o decenas de usuarios, sin recortar contenido, bloquear la tabla ni presentar como hecho histórico información que la BD no conserva.

## Semántica obligatoria

La interfaz debe distinguir cuatro conceptos:

1. **Ejecutado por:** usuario registrado como actor del movimiento.
2. **Destino:** cargo y área a los que se dirigió el movimiento.
3. **Usuarios actualmente asignados al destino:** asignaciones activas actuales del cargo. No deben llamarse “usuarios que recepcionaron”.
4. **Recepcionado por:** actor del movimiento posterior de recepción, cuando pueda inferirse de manera inequívoca.

Sin una tabla de snapshot histórico no puede afirmarse qué usuarios estaban asignados exactamente en la fecha pasada. Esta implementación no modifica BD; por ello debe informar `fuente = asignacion_actual` y mostrar la etiqueta “Actualmente asignados”.

## Diseño visual

### Disparador compacto en la tabla

```text
┌────────┐
│ 👥 U 12│
└────────┘
```

- `U` conserva el reconocimiento del legacy.
- El badge muestra `total_destinatarios`.
- `aria-label`: “Ver 12 participantes del movimiento”.
- Debe funcionar con clic, Enter y Espacio; Escape cierra y devuelve el foco.

### Vista rápida

Hover o foco puede mostrar una tarjeta no modal con:

- actor;
- cargo/área destino;
- primeros tres usuarios;
- enlace/botón “Ver los 9 restantes”.

La tarjeta debe renderizarse en una capa `fixed`/portal o con posicionamiento que no quede recortado por `overflow-auto`. No debe cerrarse al atravesar unos pocos píxeles entre el botón y la tarjeta.

### Modal de lista completa

```text
┌────────────────────────────────────────────────────────────┐
│ Participantes — Movimiento #1234                     [×]  │
├───────────────────┬────────────────────────────────────────┤
│ EJECUTADO POR     │ DESTINO — 12 actualmente asignados    │
│ Nombre completo   │ [ Buscar usuario…                  ]   │
│ Login             │ 01 Nombre · Cargo · Área · Login      │
│ Cargo / Área      │ 02 Nombre · Cargo · Área · Login      │
│ Fecha / Estado    │ …                                      │
├───────────────────┴────────────────────────────────────────┤
│ Recepcionado por: Nombre, fecha        Página 1 de 1       │
└────────────────────────────────────────────────────────────┘
```

- Ancho de escritorio entre 760 y 900 px; alto máximo `70vh`.
- En móvil, paneles apilados.
- Lista derecha con scroll propio; no comprimir nombres.
- Búsqueda por nombre, login, cargo y área.
- Mostrar nombre completo, login, cargo, área y estado de asignación.
- Si no hay usuarios: “No existen usuarios activos asignados actualmente a este cargo”.
- No insertar sentinelas como si fueran usuarios reales.

## Contrato HTTP

El histórico paginado devuelve únicamente resumen:

```json
{
  "movimiento_id": 1234,
  "actor": {
    "login": "jsalazar",
    "nombre": "José Salazar",
    "cargo": "Soporte OTI",
    "area": "OTI"
  },
  "destino": {
    "cargo_id": 514,
    "cargo": "Jefatura de Logística",
    "area_id": 40,
    "area": "Oficina de Logística"
  },
  "usuarios_resumen": {
    "total": 12,
    "preview": [],
    "fuente": "asignacion_actual"
  },
  "recepcionado_por": null
}
```

Un endpoint lazy autorizado entrega la lista completa:

```json
{
  "status": "success",
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 25,
    "total": 12,
    "last_page": 1
  },
  "meta": {
    "fuente": "asignacion_actual",
    "consultado_en": "2026-10-10T10:00:00-05:00"
  }
}
```

## Backend común

- Crear un DTO común de participante y un contrato de resumen U; no devolver objetos de Query Builder sin normalizar.
- Resolver usuarios por cargo mediante consultas masivas, deduplicar por login y ordenar por nombre.
- No hacer una consulta por movimiento ni por usuario.
- El endpoint recibe `movimiento_id`, `page`, `per_page` y `search`; no recibe nombres de tabla, orden o columna arbitrarios.
- `per_page` permitido: 10, 25 o 50.
- Cache frontend por `movimiento_id + page + search`; invalidar al recargar el histórico.

## Autorización y privacidad

- Aplicar la misma Policy estricta del expediente antes de revelar participantes.
- Autorizar por compañía y relación/custodia/participación o rol administrativo.
- Devolver 404 cuando el movimiento no exista o no sea visible.
- No exponer DNI, correo, teléfono, dirección ni datos personales adicionales.
- Escapar toda salida; queda prohibido interpolar valores de usuario con `innerHTML` sin escape.

## Correcciones específicas de Trámite Interno

- Cuando cargo origen y destino sean iguales, no reemplazar los destinatarios por el actor: consultar todos los usuarios activos del cargo destino.
- Incorporar en el contrato las propiedades que el modal intenta mostrar (`cargo_salida`, `area_salida`, login), en lugar de usar fallbacks silenciosos.
- Eliminar bindings/event listeners duplicados de apertura/cierre de U.
- Reutilizar el componente visual común, no mantener una segunda implementación divergente.

## Correcciones específicas de Trámite Externo

- Sustituir el popover fijo `w-80` y la lista `max-h-28` por preview + modal común.
- No renderizar el popover dentro del contenedor recortado por la tabla.
- Agregar total visible y datos de cargo/área/login, no solo el nombre.
- Paginar primero el histórico para que U no multiplique el payload.

## Accesibilidad

- Dialog con `role="dialog"`, `aria-modal="true"` y título referenciado.
- Focus trap real, foco inicial en búsqueda/cerrar y retorno al disparador.
- Cierre por Escape, botón visible y backdrop; nunca depender solo de mouseleave.
- Estados de carga, vacío y error anunciados mediante `aria-live`.
- Contraste AA y áreas táctiles mínimas de 40×40 px.

## Pruebas obligatorias

1. Cero, uno, cuatro, veinticinco y cincuenta usuarios.
2. Cargo origen igual a destino.
3. Usuarios duplicados y logins inactivos.
4. Movimiento con actor inexistente/inactivo.
5. Recepción posterior inequívoca y ausencia de recepción.
6. Usuario de otra compañía y usuario de la misma compañía sin relación.
7. Búsqueda, paginación y límites inválidos.
8. Nombres con caracteres de XSS mostrados como texto.
9. Apertura/cierre por teclado y retorno de foco.
10. Verificación de ausencia de N+1 con contador de consultas mockeado.

## Definition of Done

- Interno y Externo utilizan el mismo patrón visual y contrato semántico.
- La tabla no se ensancha ni recorta la lista de usuarios.
- La carga inicial no incluye listas completas innecesarias.
- No se llama “recepcionados” a usuarios solamente asignados.
- No se modifica la BD.
- Tests de contrato, seguridad, accesibilidad y rendimiento pasan sin conexión a PostgreSQL.

