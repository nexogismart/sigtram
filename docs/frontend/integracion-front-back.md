# Preparación de la integración para main

Fecha: 15 de octubre de 2026. Rama: `codex/integracion-front-back`.

**Alcance autorizado para esta entrega:** trámites internos y externos, login, selección de compañía/cargo y workspace. El usuario indicó expresamente que se priorice este alcance por ahora. La cobertura global queda como seguimiento informativo temporal; las pruebas, auditorías, compilación y PHPStan siguen siendo obligatorios. No se certifican todos los módulos ni escrituras sobre una BD de producción.

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

Los cambios de funcionamiento del backend se enumeran explícitamente: **no se presenta el código corregido como idéntico al backend recibido**. `integration-reviewed-fixes.json` registra los hashes exactos de 65 archivos auditados modificados. Toda diferencia posterior no registrada hace fallar la auditoría. Se conservan formularios, eventos y contratos de operaciones; se corrigió únicamente el respaldo de área/cargo del JSON de configuración de la bandeja interna para usar constantes existentes.

## Validación efectivamente ejecutada

| Control | Resultado local |
| --- | --- |
| Pest completo, entrega acotada | **714 aprobadas, 0 omitidas, 0 fallidas; 5.621 aserciones** |
| JavaScript backend y frontend | **43 aprobadas, 0 omitidas, 0 fallidas** |
| PHPStan nivel 5, aplicación completa | **0 errores**; inicialmente 325 |
| Sintaxis PHP | Revisión integral anterior y ejecución de las nuevas pruebas correctas |
| Composer validate --strict | Correcto |
| Composer audit | **0 avisos y 0 paquetes abandonados** |
| npm audit | **0 vulnerabilidades** |
| npm run build | Correcto, sin avisos de imágenes inexistentes |
| artisan view:cache | Correcto |
| Auditoría de integración con fixes explícitos | **348 archivos; 65 modificaciones documentadas; 0 diferencias imprevistas** |
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

## Cobertura global y pendientes fuera del alcance actual

`AGENTS.md` TEST-01 exige **80 % en negocio y 100 % en Core**. La medición de referencia con Xdebug sobre la suite completa anterior (709 pruebas), incluyendo archivos no cubiertos, fue:

| Alcance | Cubiertas / total | Cobertura | Mínimo |
| --- | --- | --- | --- |
| Servicios Core | 658 / 1.190 | **55,29 %** | 100 % |
| Servicios y repositorios de negocio | 3.273 / 19.536 | **16,75 %** | 80 % |
| Aplicación completa | 5.848 / 31.077 | **18,82 %** | Informativo |

El control estricto de cobertura **falla** con estas cifras; no se redujeron los mínimos ni se ocultaron archivos. Por la entrega acotada autorizada por el usuario, únicamente ese paso del workflow continúa con una advertencia. Cualquier fallo de pruebas, auditorías, build o análisis estático sigue haciendo fallar el job. Se debe restaurar la exigencia de cobertura al ampliar la entrega al sistema completo. El workflow por sí solo no modifica las reglas de protección de main.

Falta ampliar pruebas de comportamiento, especialmente `DocumentGeneratorService` (0/200 sentencias cubiertas), adaptadores de notificaciones/PIDE y servicios de módulos que no ejercita la suite actual. Deben usar fixtures, procesos y servicios externos simulados o entornos desechables; no se deben habilitar pruebas sobre la BD de negocio para subir cobertura. También falta la aceptación de los flujos completos de documentos, firma y almacenamiento híbrido en un entorno de pruebas representativo. No se certifica compatibilidad total con PostgreSQL ni servicios externos a partir de SQLite y mocks.

No se modificaron permisos o datos reales para sortear pendientes. Las pruebas de las operaciones usan SQLite/mocks; la aceptación con PostgreSQL, firma y sistemas externos sigue siendo distinta de estas comprobaciones.


## Contraste frontend y correcciones de la entrega acotada

- El login, logos documentales y fondo de oficina coinciden con `codex/front` (`62bee7bd`); se conservaron los cambios de compatibilidad del workspace y sus preferencias por compañía/cargo.
- La selección de compañía aún mostraba SGT, un logo municipal anterior y una entidad fija. Se unificó con SIGTRAM, el logo documental y el fondo aprobado, manteniendo los formularios y rutas. Se quitaron dos botones decorativos sin acción y se etiquetó el buscador.
- Se verificó en navegador la entrada al módulo externo desde el workspace y Entrada → Salida; también Entrada → Enviados en el módulo interno, con sus scripts Vite reales y respuestas sintéticas que reproducen el contrato backend. No se enviaron trámites ni se simularon resultados de escrituras reales.
- Se contrastaron login y workspace en escritorio y móvil (390 px); se verificó el fallback de foto cuando no existe una imagen de prueba, sin consultar RENIEC. Los filtros y la navegación móvil de las bandejas no desbordan la página.
- Se corrigió el mínimo de anchura del botón Consultar que recortaba Limpiar filtros a 1280 px. Se comprobó en DOM que ambos quedan dentro del panel. Es una corrección CSS, sin alterar scripts, parámetros o acciones.
- Los buscadores de compañía y cargo ahora responden al evento input; se comprobó que pegar texto sin coincidencias muestra el estado vacío y que recuperar un texto válido vuelve a mostrar la opción.
- Un test reprodujo la constante inexistente `USER_AREA_ID` al renderizar el respaldo de contexto interno. Ahora se usan `AREA_ID` y `CARGO_ID`; se conservan los valores explícitos enviados por el controlador.
- Se revisó la ejecución remota anterior de GitHub Actions (`38810132266`): detectó un error Linux en el nombre de la vista de la hoja de ruta externa, que utilizaba barras de Windows. Se corrigió a notación Laravel con puntos; un test del endpoint verifica compañía, vista y número de expediente. El formato de impresión no se modificó.

Se añadieron pruebas de renderizado y contrato del login, compañía, doble cargo y contexto interno, además del endpoint de hoja de ruta externa. El servidor visual local bloquea DB/HTTP externos y escrituras y usa exclusivamente identidades sintéticas. Estas comprobaciones no se presentan como una sesión real con todas las operaciones contra PostgreSQL.
