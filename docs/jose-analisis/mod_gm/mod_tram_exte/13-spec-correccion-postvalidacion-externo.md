# SPEC 13 — Corrección posvalidación de Correo Externo

## Estado

`CHANGES_REQUIRED — Validación dinámica del 2026-10-10`

Este documento prevalece sobre cualquier declaración previa de cierre respecto de los defectos CEFX-01 a CEFX-06. La implementación debe ser incremental: no reescribir el módulo, no alterar el esquema ni los datos existentes y no publicar cambios Git.

## Evidencia de validación

- Rama inspeccionada: `codex/back`.
- Pantalla validada: Correo Externo en `http://127.0.0.1:8000` con sesión existente.
- Expediente usado como muestra visual: uno con siete movimientos, adjuntos y registros de descarga existentes.
- Suite aislada: 74 tests, 357 assertions, todos en verde.
- Conclusión: los tests actuales no cubren varios contratos Blade/JSON ni duplicaciones del DOM.
- Base de datos: no se ejecutaron scripts, migraciones ni modificaciones. La lectura dinámica fue realizada exclusivamente mediante la aplicación ya levantada.

## Reglas innegociables

1. Cero migraciones, seeders, SQL, DDL, DML, Tinker o consultas manuales a PostgreSQL.
2. Reutilizar exclusivamente tablas y columnas ya evidenciadas.
3. No ejecutar `commit`, `push`, `pull`, `merge`, `rebase`, `reset` ni `clean`.
4. No instalar ni actualizar dependencias.
5. No crear copias, archivos `tmp`, `bak`, `final`, dumps, logs o documentación paralela.
6. Escribir primero pruebas de regresión que fallen por la brecha real y luego aplicar el cambio mínimo.
7. No declarar paridad total: el máximo estado posible es `READY_FOR_REVIEW`.

## CEFX-01 — Colores semánticos de estados

### Defecto probado

La bandeja y el histórico solo distinguen `PENDIENTE`; cualquier otro estado recibe estilo verde. Esto presenta como equivalentes estados de proceso, éxito, cierre y error.

### Implementación requerida

- Crear una única función/presentador frontend para resolver el estilo del estado y reutilizarla en cabecera e histórico.
- Resolver prioritariamente por `cdgo_estdo_trmte`; el nombre solo puede actuar como fallback normalizado.
- Agrupar estados en familias semánticas, no asignar una clase por cada texto repetido:
  - espera/pendiente: ámbar;
  - ingreso/asignación/transferencia: azul;
  - recepción/aprobación/realización: verde;
  - finalización/archivo: gris o esmeralda oscuro;
  - devolución/rechazo/anulación/no recepción: rojo;
  - desconocido o estado nuevo de BD: gris neutro.
- No modificar el catálogo de estados ni crear columnas de color en BD.
- Mantener contraste AA y `focus-visible`.

### Criterios de aceptación

- `PENDIENTE`, `INGRESADO`, `RECEPCIONADO`, `ASIGNADO`, `FINALIZADO`, `ARCHIVADO`, `DEVUELTO` y `ANULADO` no terminan todos en la misma clase.
- Un estado desconocido no se muestra verde por defecto.
- Cabecera e histórico usan la misma resolución visual.

## CEFX-02 — Consolidar acciones de cabecera con paridad legacy

### Evidencia legacy

`Presentacion/mod_gm/mod_tram_exte/segi_entrada.php` tenía dos acciones por fila:

1. hoja de ruta;
2. un ojo de detalle que también comunicaba el plazo de silencio administrativo mediante su apariencia.

### Defecto probado

La vista Laravel muestra tres botones: detalle, hoja de ruta y silencio administrativo. Los dos ojos son visualmente ambiguos y el tercero, cuando no aplica silencio, solo emite un aviso.

### Implementación requerida

- Dejar dos acciones claramente identificables:
  1. `Hoja de ruta`;
  2. `Detalle del expediente`.
- Integrar en el detalle los datos de silencio administrativo: aplica/no aplica, días permitidos, adicionales, transcurridos y vencimiento cuando existan.
- El indicador puede cambiar color o icono cuando el plazo esté próximo/vencido, pero debe seguir abriendo el mismo detalle.
- El clic de la fila continúa cargando el histórico; no debe confundirse con el detalle general.
- Agregar `aria-label` y tooltip textual; no depender solo del icono.

### Criterios de aceptación

- Solo existen dos acciones visibles por fila, salvo video condicional documentado.
- No existen dos botones con icono de ojo y propósitos diferentes.
- Un trámite sin silencio sigue permitiendo abrir su detalle.

## CEFX-03 — Columna A: fecha, persona e IP sin cambiar BD

### Defecto probado

Existen registros en `p_sgmto_dscrgas` y el popup muestra fecha e IP, pero la persona aparece vacía. El repositorio entrega `no_crto`; el Blade intenta leer `cdgo_lgin`.

### Implementación requerida

- Unificar el contrato de cada descarga como mínimo con:
  - `fecha`;
  - `nombre_usuario`;
  - `login`, si está disponible;
  - `ip`.
- Seleccionar también el login registrado como fallback; no sustituir un usuario ausente por un nombre inventado.
- Mostrar `Nombre completo (login)` cuando ambos existan.
- Si el registro histórico carece de usuario resoluble, mostrar `Usuario no disponible` y conservar fecha/IP.
- Si no existen registros, conservar `Sin descargas previas registradas`.
- No insertar, actualizar ni reconstruir registros históricos.

### Criterios de aceptación

- Un registro existente muestra fecha y persona cuando la relación existente permite resolverla.
- No aparece una barra vacía antes de la fecha.
- La ausencia de auditoría no se presenta como error ni genera escritura en BD.

## CEFX-04 — Contrato del detalle general y relación con histórico

### Defecto probado

El repositorio devuelve un contenedor con `movimiento_dto` y `data`, el controlador lo vuelve a envolver en `data`, y el frontend espera campos planos. El popup abre, pero solicitante, tipo, servicio, documento y contacto quedan vacíos.

Además, `showDetalle()` asigna `selectedExpediente`, mostrando el contenedor del histórico sin cargarlo.

### Implementación requerida

- Definir un único contrato JSON del endpoint `detalle` y probarlo. Preferencia:

```json
{
  "status": "success",
  "data": {
    "expediente": "...",
    "solicitante": "...",
    "documento_id": "...",
    "tipo_documento": "...",
    "domicilio": "...",
    "telefono": "...",
    "correo": "...",
    "servicio": "...",
    "silencio_admin": "...",
    "folios": 0,
    "observaciones": "..."
  }
}
```

- Evitar doble `data.data`.
- No abrir ni vaciar el panel histórico al consultar únicamente el detalle.
- Mantener separados los conceptos:
  - detalle general del expediente;
  - histórico de movimientos;
  - columna D: voucher/comprobante del movimiento.
- Renombrar tooltip de D a `Ver comprobante del movimiento`.
- Renderizar fallback honesto solo para campos realmente nulos.

### Criterios de aceptación

- El popup muestra los datos existentes del expediente probado.
- Abrir/cerrar el detalle no borra ni crea un histórico vacío.
- D continúa mostrando movimiento, fecha, estado, áreas, responsable y observaciones.

## CEFX-05 — Columna U: eliminar duplicación y corregir actor

### Defecto probado

La carga de participantes funciona y devuelve usuarios reales, búsqueda, paginación y total. Sin embargo:

- hay dos árboles completos `x-show="modalUsuarios"`, por lo que se renderizan dos diálogos a la vez;
- el ejecutor muestra nombre, pero el login queda vacío por usar `cdgo_usrio` en lugar de `cdgo_lgin`;
- el contrato no entrega consistentemente cargo/área del actor;
- los destinatarios representan asignación actual, no un snapshot histórico.

### Implementación requerida

- Conservar un solo modal U externo y eliminar únicamente el duplicado.
- Unificar las claves del actor: `nombre`, `login`, `cargo`, `area`.
- Reutilizar el mismo contrato canónico definido para Interno/Externo; no mantener dos variantes de markup.
- Mantener carga lazy, debounce, cancelación, caché, paginación, Escape y retorno de foco.
- Mantener visible `Fuente: Asignación actual` porque no existe evidencia de snapshot histórico.
- No denominar a todos los asignados como “recepcionados”.
- No exponer DNI, correo, teléfono ni dirección.

### Criterios de aceptación

- En el DOM existe exactamente un diálogo U.
- El contador coincide con la lista paginada.
- El actor muestra nombre y login cuando existen.
- Buscar, paginar, cerrar con Escape y recuperar foco siguen funcionando.
- Un movimiento sin asignados presenta estado vacío honesto.

## CEFX-06 — Regresión, calidad y entrega

### Pruebas mínimas nuevas

1. Contrato plano del endpoint detalle, evitando `data.data`.
2. Contrato de auditoría A con nombre/login/fecha/IP y fallback nulo.
3. Estado desconocido usa estilo neutro; estados de familias diferentes usan clases distintas.
4. La vista contiene un solo modal U.
5. El actor U consume `cdgo_lgin`/`login`, no `cdgo_usrio`.
6. Cabecera tiene dos acciones canónicas y no dos ojos ambiguos.
7. Abrir detalle no manipula el estado del histórico.

### Verificación permitida

```powershell
php -d opcache.enable_cli=0 vendor/bin/phpunit <tests-unitarios-nuevos-y-relacionados> --testdox
php -d opcache.enable_cli=0 vendor/bin/phpstan analyse --no-progress --memory-limit=512M <archivos-tocados>
php -l <cada-archivo-php-tocado>
git diff --check
```

No ejecutar tests Feature que puedan conectarse a PostgreSQL. Antes de PHPUnit debe verificarse estáticamente el guardrail existente en `tests/TestCase.php`.

## Resultado esperado

El módulo queda en `READY_FOR_REVIEW`, sujeto a una nueva revisión de Codex y a pruebas manuales del usuario. Ninguna prueba verde por sí sola permite declarar paridad total o producción.

