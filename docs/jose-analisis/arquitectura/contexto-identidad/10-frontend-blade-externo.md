# UI-02 — Separación Blade/JavaScript/CSS de trámite externo

## Baseline observado

En `resources/views/mod_gm/mod_tram_exte`: 38 Blade; 18 con `<script>`, 28 con `<style>`, 17 con `style=` y 15 con eventos inline. Volver a medir antes de editar.

## Alcance

Todas las vistas/parciales de trámite externo, coordinadas con:

- `resources/js/tramite-externo/**`;
- `resources/css/tramite-externo.css`;
- módulos compartidos y entrypoint Vite.

Aplicar a vistas interactivas las mismas reglas de UI-01. Eliminar especialmente parciales cuyo único propósito sea inyectar JavaScript, como `partials/script.blade.php`, trasladando su comportamiento a módulos importados.

## Vistas especiales

Clasificar `voucher*`, `pdf/**`, impresión y correo:

- **PDF renderer:** cero JavaScript. CSS dedicado en `resources/css/print/**`; cargarlo mediante el mecanismo compatible confirmado del renderer. Si debe incrustarse CSS, la fuente sigue siendo un archivo CSS dedicado, no reglas mantenidas manualmente dentro del Blade.
- **Print browser/voucher:** comportamiento de imprimir/cerrar en módulo JS pequeño; estilos en CSS de impresión con `@page` y `@media print` probado en 80 mm cuando aplique.
- **Email:** cero JavaScript. CSS inline sólo cuando lo exige el cliente de correo y generado desde una fuente mantenible; documentar la excepción.

No eliminar estilos requeridos por Dompdf/navegador sin una prueba renderizada equivalente.

## Aceptación

- Vistas interactivas limpias de JS/CSS/eventos inline.
- Cada excepción de renderer registrada en `inventario-frontend.md` con motivo y prueba.
- `npm test`, build y pruebas PHP verdes.
- Verificación manual de ingreso, bandejas, diez acciones, histórico, hoja de ruta, voucher, reportes y modales.
