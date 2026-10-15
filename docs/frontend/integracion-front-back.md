# Preparación de la integración para main

Fecha: 15 de octubre de 2026. Rama: `codex/integracion-front-back`.

**Estado: correcciones verificadas, pero todavía no cumple el control de cobertura requerido para main.** No se ha unido ni publicado esta rama en main. Que las pruebas existentes pasen no certifica todas las operaciones del sistema.

## Referencias y alcance

- Frontend: `62bee7bd` (`codex/front`).
- Backend integrado: `7b095bef` (`codex/back`).
- Main remoto obtenido durante esta revisión: `15d51ffc`; ya contiene el merge del backend.
- Se conservaron los cambios locales y los contratos HTTP, rutas, formularios, scripts de negocio y formatos oficiales de trámites. No se restauraron vistas eliminadas por backend ni se inventaron pantallas ausentes.
- No se ejecutaron migraciones ni escrituras en la BD remota, operaciones reales de trámites, notificaciones o consultas reales a PIDE.

Las vistas operativas migradas mantienen su implementación backend y reciben la cabecera institucional aislada mediante `sigtramNative`. El workspace y login mantienen la identidad documental aprobada. Las equivalencias de navegación y preferencias por compañía/cargo del frontend se conservan.

## Correcciones realizadas

1. **Autenticación:** se declaró realmente `$contextoService`, que estaba dentro de un comentario PHPDoc y se asignaba como propiedad dinámica.
2. **Permisos al cambiar de cargo:** la caché conservaba una sola respuesta por login, aunque la consulta depende del cargo y perfil. Se separó el contenido por ambos valores dentro de la misma clave de usuario. La invalidación por login sigue eliminando todos los contextos. Las entradas antiguas sin contexto no se reutilizan. Un test con SQLite verifica que alternar cargos no hereda accesos del anterior y que la invalidación funciona.
3. **Numeración interna:** los documentos institucionales ya no concatenan el nombre del emisor. Los personales usan iniciales, como el borrador Word. Se verificaron ambas variantes y la compañía activa. El test de rollback ahora inserta una fila real en SQLite y comprueba que el fallo la revierte.
4. **Idempotencia externa:** se retiró el UUID automático exclusivo del entorno de pruebas. Las pruebas y producción exigen ahora la misma cabecera/clave; una clave vacía o formada por espacios se rechaza. Los tests HTTP envían la clave explícita y verifican el rechazo cuando falta.
5. **Compatibilidad y análisis:** correcciones de tipos GD/PgSQL para PHP 8.4, errores de tipos en archivos subidos, atributos de consulta Eloquent y paginación; validación explícita del contexto del proxy de cámaras; eliminación de ramas, propiedades, dependencias y constantes sin uso, de una clave de estado duplicada y de comprobaciones redundantes sobre contratos ya tipados. Se eliminó una lectura `env()` fuera de configuración. No se añadieron ignores ni baseline de PHPStan.
6. **Dependencias y instalación:** lockfiles actualizados, Larastan sustituido por el paquete mantenido, requisito PHP 8.4, autoload explícito de las tres clases globales heredadas de OMAPED y eliminación de migraciones automáticas en los scripts Composer. No se cambió `.env` ni se generó una clave QR para producción.
7. **Recursos visuales:** se sustituyó el escudo inexistente de selección de compañía por el emblema documental aprobado. Se retiró del build la entrada CSS del login antiguo, sin consumidores en las vistas actuales. La compilación ya no avisa de esas imágenes heredadas.
8. **Pruebas:** todas usan SQLite en memoria, HTTP simulado y credenciales/clave QR de prueba. Las conexiones PostgreSQL heredadas no heredan URLs ni destinos remotos. El inicializador SQLite falla explícitamente cuando no puede crear el esquema. Las pruebas antes omitidas por ausencia de usuarios se sustituyeron por identidades y permisos sintéticos; las de seguimiento externo usan el contrato HTTP actual y simulan únicamente los colaboradores de negocio.

Los cambios de funcionamiento del backend se enumeran explícitamente: **no se presenta el código corregido como idéntico al backend recibido**. `integration-reviewed-fixes.json` registra los hashes exactos de 63 archivos auditados modificados. Toda diferencia posterior no registrada hace fallar la auditoría. Los contratos y scripts de las vistas de trámites permanecen intactos respecto a la integración anterior.

## Validación efectivamente ejecutada

| Control | Resultado local |
| --- | --- |
| Pest completo con cobertura Xdebug | **709 aprobadas, 0 omitidas, 0 fallidas; 5.594 aserciones** |
| JavaScript backend y frontend | **43 aprobadas, 0 omitidas, 0 fallidas** |
| PHPStan nivel 5, aplicación completa | **0 errores**; inicialmente 325 |
| Sintaxis PHP de los archivos modificados | **74 archivos, 0 errores** |
| Composer validate --strict | Correcto |
| Composer audit | **0 avisos y 0 paquetes abandonados** |
| npm audit | **0 vulnerabilidades** |
| npm run build | Correcto, sin avisos de imágenes inexistentes |
| artisan view:cache | Correcto |
| Auditoría de integración con fixes explícitos | **348 archivos; 63 modificaciones documentadas; 0 diferencias imprevistas** |
| git diff --check | Correcto |
| Tests del control de cobertura | **4 aprobados**; comprueban umbrales y rechazo de alcances ausentes |

Comandos reproducibles (PHP 8.4 y Node 22):

```text
composer install --no-scripts
composer validate --strict
composer audit
npm ci --ignore-scripts
npm audit
npm test
npm run build
php vendor/bin/phpstan analyse --no-progress
php vendor/bin/pest --compact --coverage-clover storage/app/coverage.xml
python scripts/quality/check-coverage.py storage/app/coverage.xml
python scripts/frontend/audit-integration.py --reviewed-fixes
php artisan view:cache
```

Para medir cobertura debe estar disponible Xdebug/PCOV. Aquí se cargó Xdebug solo en el proceso de pruebas, sin modificar php.ini. Los logs, Clover y evidencias con posibles datos personales se conservan localmente bajo `storage/app/main-*` y `output/`, excluidos de Git. No se afirman resultados de CI remota ni pruebas de escritura en PostgreSQL real.

El auditor original `audit-contracts.py` sigue siendo una referencia anterior a la migración: se detiene en `segi_bandeja.blade.php`, eliminada por backend. No se restauró esa pantalla ni se reescribió su referencia para fabricar un resultado verde. La comparación vigente es contra el backend migrado con el registro explícito de correcciones.

## Bloqueo de main y pendientes concretos

`AGENTS.md` TEST-01 exige **80 % en negocio y 100 % en Core**. La cobertura real de sentencias, incluyendo archivos no cubiertos, es:

| Alcance | Cubiertas / total | Cobertura | Mínimo |
| --- | --- | --- | --- |
| Servicios Core | 658 / 1.190 | **55,29 %** | 100 % |
| Servicios y repositorios de negocio | 3.273 / 19.536 | **16,75 %** | 80 % |
| Aplicación completa | 5.848 / 31.077 | **18,82 %** | Informativo |

El control de cobertura **falla** con estas cifras. Se añadió `.github/workflows/quality.yml` para ejecutar auditorías, build, análisis estático, tests y este control sin ocultarlo ni reducir los mínimos. Para que GitHub impida físicamente un merge, el propietario debe exigir este check en las reglas de protección de main; el workflow por sí solo no configura esa protección.

Falta ampliar pruebas de comportamiento, especialmente `DocumentGeneratorService` (0/200 sentencias cubiertas), adaptadores de notificaciones/PIDE y servicios de módulos que no ejercita la suite actual. Deben usar fixtures, procesos y servicios externos simulados o entornos desechables; no se deben habilitar pruebas sobre la BD de negocio para subir cobertura. También falta la aceptación de los flujos completos de documentos, firma y almacenamiento híbrido en un entorno de pruebas representativo. No se certifica compatibilidad total con PostgreSQL ni servicios externos a partir de SQLite y mocks.

No se tocaron main ni los permisos/datos reales para sortear estos pendientes.
