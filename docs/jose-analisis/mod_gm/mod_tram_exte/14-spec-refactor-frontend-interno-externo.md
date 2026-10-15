# SPEC 14 — Refactor frontend progresivo de Trámite Interno y Externo

## Estado

`READY_FOR_IMPLEMENTATION — RF-00…RF-09`

Esta especificación gobierna únicamente la reorganización y endurecimiento del frontend ya migrado. No autoriza cambios funcionales, de contratos HTTP, reglas de negocio, esquema, datos ni publicación Git.

## 1. Objetivo

Transformar los dos Blade monolíticos en vistas delgadas y módulos JavaScript versionados por Vite, preservando paridad visual y funcional:

- `resources/views/mod_gene/mod_tram_inter/correo-interno.blade.php`;
- `resources/views/mod_gm/mod_tram_exte/segi_expedi.blade.php`.

El refactor debe reducir acoplamiento, eliminar JavaScript global e inline, cerrar riesgos de DOM XSS, permitir pruebas unitarias de funciones puras y mantener intactos todos los flujos existentes.

## 2. Línea base verificada

| Superficie | Evidencia | Riesgo |
|---|---:|---|
| Correo Interno | 2,831 líneas; bloque JS aproximado de 1,829 líneas | Monolito y estado global |
| Eventos inline de Interno | 131 atributos `onclick`/`onchange`/`oninput`/`onsubmit` | Acoplamiento y contexto de escape inseguro |
| Renderizado de Interno | `tbody.innerHTML = items.map(...)` | Riesgo DOM XSS y pruebas frágiles |
| Correo Externo | 2,355 líneas; componente Alpine aproximado de 900 líneas y 71 métodos | God component |
| Transporte HTTP | 14 `fetch` en Interno y 17 en Externo | Manejo de errores/CSRF duplicado |
| Build | `resources/js/app.js` prácticamente vacío | Vite no gobierna la lógica real |
| Alpine | CDN en la vista externa | Dependencia no fijada por lockfile/CSP |
| Vista duplicada | `segi_bandeja.blade.php`, 1,497 líneas, sin referencia runtime encontrada | Código huérfano divergente |

## 3. Guardrails obligatorios

1. Trabajar únicamente en `codex/back` local.
2. Prohibido `commit`, `push`, `pull`, `merge`, `rebase` y publicación externa.
3. Prohibido conectarse a PostgreSQL o ejecutar SQL, migraciones, seeders, DDL o DML.
4. No cambiar rutas, verbos HTTP, nombres de campos, DTOs, respuestas JSON, policies ni transiciones de estado.
5. No reescribir backend salvo un ajuste mínimo imprescindible para entregar configuración segura a la vista; cualquier necesidad debe justificarse antes.
6. No agregar otro framework frontend. Externo conserva Alpine; Interno se modulariza con ES modules y DOM seguro.
7. No usar `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `eval`, `new Function` ni atributos de eventos para datos dinámicos.
8. No usar `x-html` salvo un presentador auditado que escape primero y solo agregue marcado fijo. Preferir `x-text`.
9. No crear archivos temporales, copias, prompts, reportes duplicados ni artefactos generados dentro del repositorio.
10. Preservar cambios ajenos existentes y mantener el guardrail P0 de pruebas sin BD.
11. No declarar paridad ni cierre total basándose solo en pruebas estáticas.

## 4. Arquitectura destino

```text
resources/js/
├── app.js
├── shared/
│   ├── http-client.js
│   ├── route-config.js
│   ├── safe-dom.js
│   ├── modal-controller.js
│   └── pagination.js
├── tramite-interno/
│   ├── index.js
│   ├── state.js
│   ├── api.js
│   ├── bandejas.js
│   ├── renderers.js
│   ├── historico.js
│   ├── participantes.js
│   └── acciones.js
└── tramite-externo/
    ├── index.js
    ├── state.js
    ├── api.js
    ├── estados.js
    ├── bandejas.js
    ├── historico.js
    ├── participantes.js
    └── acciones.js
```

Los nombres pueden compactarse cuando dos módulos no tengan responsabilidad suficiente, pero no volver a crear un archivo monolítico. Como guía, ningún archivo nuevo de lógica debe superar 400 líneas ni mezclar transporte HTTP, renderizado DOM y reglas de interacción.

Las vistas se separan únicamente en parciales significativos, evitando fragmentación artificial:

```text
partials/
├── sidebar.blade.php
├── filtros-tabla.blade.php
├── historico.blade.php
└── modales.blade.php
```

## 5. Contrato de bootstrap Blade → JavaScript

Blade conserva solo datos serializados y rutas generadas por Laravel. Debe existir un nodo raíz inequívoco por módulo:

```blade
<div id="tramite-interno-app" data-config='@json($frontendConfig)'>
```

o un bloque inerte equivalente con `type="application/json"` y `@json`/`@js`. No interpolar valores dentro de código ejecutable ni construir rutas a mano cuando Laravel ya ofrece una ruta nombrada.

El bootstrap debe contener exclusivamente:

- rutas del módulo;
- CSRF o referencia al `meta` existente;
- catálogos ya autorizados para la vista;
- bandeja inicial;
- flags de permisos estrictamente necesarios.

## 6. Unidades de trabajo

### RF-00 — Preflight y caracterización

- Confirmar rama `codex/back`.
- Inventariar funciones, rutas, modales, bandejas, botones y eventos de ambos Blade.
- Ejecutar `tests/Unit`, PHPStan scoped y `npm run build` antes de editar.
- Registrar únicamente en el reporte final el resultado baseline; no crear otro documento.
- No continuar si el guardrail de `tests/TestCase.php` no bloquea consultas DB.

**Aceptación:** baseline reproducible, sin conexión a BD y con lista de contratos que no pueden cambiar.

### RF-01 — Bootstrap y entradas Vite

- Convertir `resources/js/app.js` en entry point real.
- Inicializar cada módulo solo cuando exista su nodo raíz; ningún código de Interno debe ejecutarse en Externo ni viceversa.
- Mover rutas y catálogos a configuración JSON segura generada por Blade.
- Eliminar interpolaciones Blade desde los módulos `.js`.
- Mover estilos locales reutilizables a `resources/css/app.css` o un CSS importado por Vite.

**Aceptación:** los Blade no contienen lógica de inicialización extensa y Vite compila ambas superficies.

### RF-02 — Infraestructura compartida

- Implementar cliente HTTP compartido para JSON y `FormData`.
- Centralizar encabezados `Accept`, `X-Requested-With`, CSRF, parseo seguro, errores y `AbortController`.
- Distinguir aborto voluntario, error de validación 422, no autorizado 401/403, conflicto 409 y error inesperado.
- Crear helpers puros de paginación y validación de configuración.
- Crear utilidades DOM que usen `createElement`, `textContent`, atributos permitidos y URLs validadas.

**Aceptación:** ningún módulo duplica la configuración base de `fetch`; no se filtran stack traces ni HTML del servidor en la UI.

### RF-03 — Seguridad DOM de Trámite Interno (prioridad P0)

- Sustituir `tbody.innerHTML = items.map(...)` por creación segura de nodos o templates estáticos clonados.
- Eliminar datos interpolados dentro de `onclick` y demás atributos ejecutables.
- Reemplazar los 131 handlers inline por `addEventListener` y delegación mediante `data-action`/`data-id`.
- Aplicar `textContent` a texto de BD y allowlist a URLs/atributos.
- Mantener exactamente las columnas, tooltips, selección, colores y acciones actuales.
- Probar payloads con comillas, etiquetas, entidades HTML, backticks, Unicode y `javascript:`.

**Aceptación:** cero atributos de evento inline en Correo Interno; cero sinks HTML dinámicos prohibidos; las pruebas XSS cubren contexto HTML, atributo, URL y evento.

### RF-04 — Modularización de Trámite Interno

- Encapsular estado; prohibido exponer decenas de funciones en `window`.
- Separar bandejas/paginación, histórico, participantes U y operaciones transaccionales.
- Unificar abrir/cerrar modales, bloqueo de botón durante envío, foco inicial, Escape y retorno de foco.
- Conservar recepción, asignación, derivación, finalización, devolución, reiteración, impresión, adjuntos, comprobantes y videos.
- Evitar listeners `DOMContentLoaded` duplicados; un solo bootstrap idempotente.

**Aceptación:** Blade delgado, estado no global, ausencia de listeners duplicados y paridad contractual completa.

### RF-05 — Modularización de Trámite Externo

- Extraer `correoExternoApp()` del Blade y registrarlo desde un módulo Vite.
- Dividir estado, API, bandejas, histórico, U y acciones transaccionales.
- Eliminar el método muerto `showSilencio()`; el silencio continúa integrado en Detalle.
- Mantener `getEstadoBadgeClass` como única resolución visual o moverla a un módulo puro único.
- Conservar dos acciones base y video condicional documentado.
- Mantener detalle plano, auditoría A y exactamente un modal U.
- Revisar los tres usos de `x-html`; sustituirlos por render seguro o demostrar mediante prueba que el highlighter escapa antes de insertar marcado fijo.

**Aceptación:** el Blade no contiene el God component, no hay estado funcional duplicado y las pruebas CEFX continúan verdes.

### RF-06 — Alpine local y dependencias controladas

- Sustituir Alpine CDN por dependencia local fijada en `package.json` y `package-lock.json` e importada por Vite.
- Se autoriza exclusivamente agregar `alpinejs` compatible con la versión 3 ya usada; prohibido `npm update` general o upgrades no relacionados.
- No agregar React, Vue, jQuery ni otro framework.
- Si la instalación no está disponible, marcar esta unidad `BLOCKED_DEPENDENCY`; no conservar CDN y declarar cierre completo.

**Aceptación:** cero carga CDN de Alpine y build reproducible desde lockfile.

### RF-07 — Parciales y eliminación de duplicación

- Extraer solo sidebar, filtros/tabla, histórico y grupos de modales cuando reduzca realmente el Blade principal.
- Confirmar con `rg`, rutas y tests que `segi_bandeja.blade.php` no tiene consumidores runtime.
- Si está huérfano, eliminarlo; si aparece un consumidor real, migrarlo a la vista canónica antes de retirarlo.
- Eliminar funciones, variables, modales y estilos muertos detectados por búsqueda y pruebas.
- No borrar specs ni evidencia histórica.

**Aceptación:** una sola vista canónica externa y ninguna copia divergente ejecutable.

### RF-08 — Pruebas de arquitectura y regresión

- Crear pruebas PHP estáticas/arquitectónicas que verifiquen:
  - ausencia de handlers inline en Interno;
  - ausencia de sinks DOM prohibidos;
  - ausencia del CDN Alpine;
  - ausencia del bloque monolítico `correoExternoApp()` dentro del Blade;
  - existencia de nodos raíz/config segura;
  - vista externa canónica única.
- Usar `node:test` de Node 22 para funciones JS puras; no agregar Vitest/Jest solo para esta fase.
- Probar cliente HTTP, estados visuales, paginación, URL allowlist y normalización de errores.
- Mantener todas las pruebas PHPUnit existentes, incluidas CEFX, XSS, U, paginación y contratos.

**Aceptación:** pruebas negativas capaces de fallar si reaparece un handler inline, un sink inseguro o un CDN ejecutable.

### RF-09 — Certificación y entrega

- Ejecutar suite `tests/Unit` completa con guardrail P0.
- Ejecutar PHPStan scoped en archivos PHP relacionados.
- Ejecutar pruebas JS con `node --test`.
- Ejecutar `npm run build`.
- Ejecutar `php -l` para archivos PHP tocados y `git diff --check` scoped.
- Verificar que no se crearon SQL, migraciones, seeders, prompts, temporales ni artefactos de build versionados.
- Entregar listado de archivos, decisiones, resultados y checklist manual; no hacer commit/push.

**Aceptación:** `READY_FOR_MANUAL_REVIEW`, nunca “100% producción”, hasta validación autenticada de Codex/usuario.

## 7. Regresión funcional obligatoria

### Interno

- Ocho bandejas, conteos y paginación servidor.
- Filtros, selección simple/masiva y estados visuales.
- Histórico con T.E., U, A, D y descarga completa.
- Recepcionar, asignar, derivar, finalizar, devolver y reiterar.
- Impresión, adjuntos, reemplazo firmado, comprobantes y videos.
- Modales accesibles, Escape y retorno de foco.

### Externo

- Diez bandejas, conteos y paginación servidor.
- Estados semánticos compartidos entre cabecera e histórico.
- Dos acciones base y video condicional.
- Detalle general plano e independiente del histórico.
- Histórico U/A/D, auditoría de descargas y consolidado.
- Recepcionar, derivar, asignar, enviar, finalizar, devolver, archivar, prestar, retornar y redirigir.
- Modales accesibles, Escape y retorno de foco.

## 8. Definition of Done

- Blade contiene presentación y configuración, no controladores frontend monolíticos.
- Cero eventos inline en las superficies refactorizadas.
- Cero construcción de HTML dinámico con datos no confiables.
- Alpine local bajo Vite y lockfile.
- Transporte HTTP y errores centralizados.
- Una sola vista canónica por módulo.
- Pruebas PHP y JS verdes, PHPStan limpio y build Vite correcto.
- Cero conexión/cambio de BD y cero publicación Git.
- Validación manual pendiente expresamente documentada.

