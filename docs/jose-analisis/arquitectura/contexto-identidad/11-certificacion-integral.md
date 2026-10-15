# QA-01 — Cierre integral verificable

## Puertas automáticas

```powershell
php artisan test
npm test
npm run build
php artisan route:list
git diff --check
```

Ejecutar PHPStan/configuración estática vigente si está operativa. Registrar fallos previos y nuevos por separado; no ocultarlos ni relajar reglas.

## Búsquedas negativas

Verificar en código productivo migrado:

- cero claves literales de identidad/contexto;
- cero aliases legacy fuera del bridge;
- cero fallback entre `loginId` y `usuarioId`;
- cero contratos JSON alternativos no justificados;
- cero JS/CSS/eventos inline en Blade `INTERACTIVE`;
- cero `DB` en DTO/Controller y cero tablas en Service;
- cero hardcodes funcionales identificados en FB-01.

## Verificación funcional

Checklist autenticado para interno y externo: ingreso, búsqueda, bandejas, recepción, derivación, asignación, envío, finalización, devolución, archivo, préstamo, retorno, redirección, históricos, hoja de ruta, reportes, impresión, voucher y manejo 401/403/404/409/422/500.

Las operaciones con datos reales sólo se ejecutan en entorno desechable autorizado. Si no existe, marcarlas `PENDING_MANUAL_ENV`, nunca “aprobadas”.

## Informe final

Entregar estado de cada tarea, inventarios, términos canónicos, archivos, excepciones justificadas, métricas antes/después, comandos exactos, pruebas omitidas y pendientes manuales. No afirmar “100 % producción”; estado máximo `READY_FOR_REVIEW`.
