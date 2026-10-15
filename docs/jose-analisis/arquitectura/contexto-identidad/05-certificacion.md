# CI-05 — Certificación y evidencia

## Matriz mínima

Probar:

1. un login con una compañía/un cargo;
2. un login con múltiples compañías y cargos;
3. compañía no asignada, inactiva o manipulada;
4. cargo no asignado, inactivo o perteneciente a otra compañía;
5. área incoherente con cargo o compañía;
6. sesión antigua, parcial o adulterada;
7. diferencia real entre `loginId` y `usuarioId` en auditoría;
8. cambio de compañía invalida cargo y área;
9. contexto interno y externo produce el mismo DTO;
10. bridge legacy no autoriza ni contamina el contexto canónico.

## Comandos finales

```powershell
php artisan test
npm test
npm run build
php artisan route:list
git diff --check
```

Ejecutar además búsquedas negativas de CI-04. No modificar pruebas para conservar aliases eliminados.

## Evidencia final

Entregar:

- CI-01…CI-05 con `DONE`, `BLOCKED` o `NOT_APPLICABLE`;
- contrato BD confirmado y MCP usado;
- tabla término canónico → columna → tipo → sesión;
- archivos creados/modificados;
- aliases eliminados y aliases confinados al bridge;
- comandos y resultados exactos;
- pruebas omitidas y validación manual pendiente;
- confirmación de cero cambios BD, cero Git remoto y cero cambios de archivos/QR.

Estado máximo: `READY_FOR_REVIEW`.
