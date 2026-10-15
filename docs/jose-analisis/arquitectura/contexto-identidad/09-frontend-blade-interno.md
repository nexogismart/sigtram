# UI-01 — Separación Blade/JavaScript/CSS de trámite interno

## Baseline observado

En `resources/views/mod_gene/mod_tram_inter`: 17 Blade; 9 con `<script>`, 11 con `<style>`, 7 con `style=` y 9 con eventos inline. Volver a medir antes de editar.

## Alcance

Todas las vistas y parciales interactivos bajo `resources/views/mod_gene/mod_tram_inter/**`, coordinados con:

- `resources/js/tramite-interno/**`;
- `resources/css/tramite-interno.css`;
- entrypoint Vite real.

## Trabajo

1. Clasificar cada Blade como `INTERACTIVE`, `PRINT_BROWSER`, `PDF_RENDERER`, `EMAIL` o `STATIC_PARTIAL`.
2. En `INTERACTIVE`:
   - mover scripts a módulos JS por responsabilidad;
   - mover `<style>` y `style=` a clases CSS;
   - reemplazar `onclick/onchange/oninput/onsubmit/...` por listeners o directivas Alpine declarativas coherentes;
   - pasar configuración servidor mediante `data-*` o bloque JSON no ejecutable escapado con `@json`/`Js::from`;
   - usar rutas nombradas proporcionadas por Blade, sin concatenar URLs;
   - mantener estados y funciones existentes: bandejas, acciones, histórico, paginación, modales, impresión y descargas.
3. No crear un único archivo JS gigante: mantener `api`, `state`, `bandejas`, `acciones`, `historico`, `renderers` y componentes compartidos con dependencias explícitas.
4. Eliminar funciones globales salvo adaptador temporal documentado; no depender del orden incidental de scripts.
5. Evitar HTML construido con datos sin escapar; usar DOM seguro o sanitización explícita.
6. Para PRINT/PDF/EMAIL aplicar las reglas de excepciones de UI-02, sin forzar Vite si el renderer no lo soporta.

## Aceptación

- Vistas interactivas sin `<script>`, `<style>`, `style=` ni eventos inline.
- CSP futura viable: sin `eval`, `new Function` ni handlers inline.
- Contratos DOM/JSON cubiertos por pruebas JS y guardrails Blade.
- Paridad funcional y visual verificada con checklist por bandeja/acción.
