# Integración de presentación y trámites

Fecha: 15 de octubre de 2026. Rama de revisión: `codex/integracion-front-back`.

## Referencias conservadas

- Frontend: `62bee7bd`, publicado en `codex/front`.
- Backend: `7b095bef`, obtenido de `origin/codex/back`.
- `main` y `codex/back` no se modificaron.

## Resolución

Se utilizó como base la implementación de trámites internos y externos del backend: sus vistas, componentes, scripts de Vite, formularios, configuración JSON, controladores, servicios, repositorios, solicitudes, permisos y contratos HTTP. Las pantallas eliminadas por backend siguen eliminadas. Los formatos de impresión de esos módulos se conservan íntegros.

Las pantallas operativas completas reciben únicamente la cabecera institucional y su hoja de estilos aislada. No cargan `sigtram-modules.js` ni los estilos generales que modifican formularios/tablas. El workspace y login conservan el diseño frontend. La selección de compañía y las rutas del backend se conservaron. `SecurityCore` conserva la versión backend con dos IDs adicionales para organizar el menú; la única ruta añadida a `web.php` es la foto del propio usuario autenticado.

Se añadieron los callbacks de navegación que utilizan los módulos migrados (`activarModuloPorUrl` y `loadContentInPanel`), resolviendo únicamente destinos del menú asignado y del mismo origen. No amplían permisos. Los destinos no reconocidos no se abren mediante esos callbacks.

Las capturas y auditorías locales con posibles datos personales se excluyeron del commit. No se modificó la BD ni se realizaron operaciones reales de trámites.

## Evidencia de validación

- `python scripts/frontend/audit-integration.py`: 348 archivos del backend comprobados, sin diferencias fuera de las adiciones identificadas. Requiere que los commits de referencia estén disponibles en el checkout.
- PHP 8.4: 118 pruebas seleccionadas aprobadas, 971 aserciones. Cubren presentación, navegación, foto privada con HTTP simulado, solicitudes de trámites, contratos HTTP, permisos, estados y servicios con colaboradores simulados. No equivalen al conjunto completo del repositorio.
- JavaScript: 28 pruebas del backend y 13 del frontend aprobadas.
- `npm run build`: compilación correcta. Vite avisa de dos imágenes de login heredadas (`imagen1.png` y `imagen2.png`) que no resuelve durante la compilación; el login aprobado utiliza sus propios recursos.
- `artisan view:cache`: compilación correcta.
- Navegador: bandeja externa Entrada → Salida e interna Entrada → Enviados, con scripts reales compilados y respuestas vacías simuladas. Sin errores de consola observados. Estas pruebas no ejercitan escrituras reales ni certifican todos los modales.
- Capturas locales excluidas de Git: `output/nexo-workspace/integracion-externos.png` y `integracion-internos.png`.

La auditoría de contratos originales anterior a la migración se mantiene como referencia histórica. Para esta rama se usa la auditoría de integración contra el backend migrado; no se reescribió la referencia antigua para presentar sus cambios funcionales como idénticos.

## Pendientes antes de main

PHPStan nivel 5 informa 325 incidencias en el código integrado. No aparecen incidencias en los archivos nuevos `ProfilePhotoController` y `WorkspaceNavigation`, ni en `SecurityCore`. Se conservaron los archivos funcionales del backend, sin corregirlos indiscriminadamente para silenciar el analizador.

`composer audit` informa avisos en siete paquetes (dompdf, Guzzle, PSR-7, Laravel, CommonMark, Flysystem y HttpFoundation), además de Larastan abandonado. `npm audit` informa seis dependencias afectadas, cuatro de severidad alta y dos críticas. Los lockfiles son los recibidos del backend y no se ejecutó `audit fix` ni se actualizaron versiones durante la integración.

Los controles de calidad no están completamente verdes. Esta rama queda para revisión conjunta, sin promoverla a `main`. Falta resolver o evaluar los hallazgos, realizar la aceptación funcional con datos de prueba y completar la estructura de archivos híbrida que el commit backend señala como pendiente. No se certifica el sistema como terminado ni apto para producción.

## Revisión posterior de la integración

Se corrigieron dos problemas de compatibilidad del frontend:

1. Los callbacks del iframe comparaban las rutas literalmente. Un menú legado con extensión `.blade.php` o una ruta interna con guiones no coincidía con su destino actual con guiones bajos. Ahora se reconocen extensiones y las tres equivalencias verificadas de `correo-interno`, `buscar-tramite` e `ingresar-tramite`. Una prueba compara sus controladores, métodos y middleware `auth` con las rutas reales del backend. No se reconocen subrutas de operaciones ni menús no asignados; se conserva la query del destino autorizado.
2. Las preferencias del workspace se separaban por nombres de usuario/área/cargo, sin compañía activa. Ahora se usan la identidad autenticada y los IDs de compañía y cargo de `SessionKeys`, con nombres como alternativa cuando no hay IDs. Dos compañías con nombres iguales no comparten favoritos/recientes y renombrar un área/cargo con el mismo ID no reinicia la clave. Las preferencias anteriores permanecen en su clave local; no se migran automáticamente porque no puede determinarse su compañía original con certeza.

Validación específica posterior: 37 pruebas PHP aprobadas (703 aserciones), 15 pruebas JavaScript frontend aprobadas, compilación Blade correcta y auditoría de 348 archivos del backend sin diferencias no previstas. La prueba del callback verifica el iframe, la conservación de parámetros, el registro de recientes y la ausencia de navegación a operaciones no asignadas.

Revisión ampliada con Pest: 622 pruebas unitarias ejecutadas, **616 aprobadas y 6 fallidas**. Se forzó PostgreSQL a localhost con un puerto sin servicio para impedir acceso a la BD real. Cinco fallos pertenecen a `TramiteInternoCompaniaTest`: el repositorio está simulado pero la transacción del servicio intenta abrir PostgreSQL; necesitan una BD desechable o aislar también esa transacción en el test. El sexto fue `IngresoExpedienteVoucherYTrackingTest::test_token_qr_valido_permite_acceso_a_seguimiento_publico`, por ausencia de `QR_KEY` en el entorno local. Este último pasó al proporcionar una clave **solo al proceso de pruebas**, sin cambiar `.env`, y el grupo adicional de identidad/contexto/QR tuvo 28 pruebas aprobadas y cuatro omitidas por ausencia de usuarios de prueba en SQLite. No se cuentan las omitidas como aprobadas.

Los controladores, servicios, repositorios, solicitudes, políticas, scripts Vite y formatos de trámites siguen siendo los de `7b095bef`. Permanecen los pendientes generales de PHPStan y dependencias anteriores. También se observó una propiedad `$contextoService` dentro de un comentario PHPDoc en `AuthController`; el constructor la asigna dinámicamente. Es un hallazgo heredado del backend que explica parte de las incidencias de análisis estático, no una modificación de esta revisión. No se corrigió modificando indiscriminadamente el backend.

Resultado: correcciones de integración verificadas y listas para revisión conjunta, **sin aprobación para main/producción**. No se hicieron operaciones de negocio ni escrituras en la BD remota.
