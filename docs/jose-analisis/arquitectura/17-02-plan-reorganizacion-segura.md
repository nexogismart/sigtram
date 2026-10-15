# SPEC 17-02 — Ejecución segura de la reorganización

## 1. Precondición

No mover namespaces hasta que SPEC 17-00 P0 esté verde. Primero funcionalidad y seguridad; después movimiento mecánico.

## 2. Estrategia por módulo

Ejecutar Trámite Interno y Trámite Externo en fases separadas. Por cada fase:

1. registrar lista exacta de archivos origen/destino;
2. mover el archivo una sola vez;
3. actualizar su `namespace`;
4. actualizar todos los `use` mediante `rg`;
5. actualizar bindings de `AppServiceProvider`;
6. actualizar mocks, alias y `Mockery::mock(...)` de tests;
7. ejecutar `composer dump-autoload`;
8. ejecutar `php -l` en todos los PHP movidos;
9. ejecutar pruebas dirigidas;
10. ejecutar Unit completa y PHPStan scoped;
11. buscar referencias al namespace antiguo; debe haber cero;
12. sólo entonces iniciar el siguiente módulo.

## 3. No crear compatibilidad duplicada

No dejar clases proxy en la ruta anterior, `class_alias`, interfaces duplicadas ni archivos que extiendan la nueva clase sólo para conservar imports. Todo el árbol está en desarrollo y debe converger a un namespace único.

## 4. Pruebas arquitectónicas nuevas

Crear `tests/Unit/ModuleFolderArchitectureTest.php` que compruebe:

1. toda clase específica de trámite interno usa el segmento `mod_gene\mod_tram_inter`;
2. toda clase específica de trámite externo usa `mod_gm\mod_tram_exte`;
3. no existen `IngresoExpedienteService.php` ni `NotificarPlataformaService.php` en `app/Services/` raíz;
4. no existen Repositories de esos módulos directamente en `Repositories/mod_gene` o `Repositories/mod_gm`;
5. el namespace declarado coincide con el path;
6. los componentes compartidos sólo residen en `Core` o `Support`.

## 5. Validación mínima final

```powershell
composer dump-autoload
php artisan test --testsuite=Unit
npm test
npm run build
vendor\bin\phpstan.bat analyse app/Http/Controllers/mod_gene/mod_tram_inter app/Http/Controllers/mod_gm/mod_tram_exte app/Services/mod_gene/mod_tram_inter app/Services/mod_gm/mod_tram_exte app/Repositories/mod_gene/mod_tram_inter app/Repositories/mod_gm/mod_tram_exte --memory-limit=1G --no-progress
git diff --check
```

PHPStan debe quedar verde en todos los archivos movidos/tocados. No agregar baseline ni ignores.

## 6. Límites

- no cambiar rutas HTTP, nombres de ruta, Blade, JS o contratos JSON;
- no conectar a PostgreSQL ni cambiar esquema;
- no implementar aún almacenamiento dual o QR;
- no mezclar esta reorganización con optimizaciones de queries;
- no commit/push/merge/rebase;
- preservar el árbol sucio.

