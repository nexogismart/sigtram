# Matriz forense de errores y soluciones

El módulo no debe liberarse como “paridad completa” mientras exista un P0 o P1 abierto. Este corte es diagnóstico y especificación: **ninguna corrección de producción se marca como implementada**; la columna final define el cambio verificable requerido.

## Convención

| Prioridad | Significado |
|---|---|
| P0 | Riesgo de acceso indebido, corrupción, ejecución/XSS o pérdida de custodia; bloquear liberación |
| P1 | Flujo esencial ausente/inconsistente o incumplimiento arquitectónico grave |
| P2 | Calidad, accesibilidad, deuda o fragilidad relevante |
| P3 | Ajuste menor/documental |

Estados: `ABIERTO`, `BLOQUEADO` (requiere dependencia/dato), `IMPLEMENTADO` (código+prueba). Todos los hallazgos siguientes están `ABIERTO`, salvo la ejecución de pruebas marcada `BLOQUEADO`.

## Matriz

| ID | Pri. | Componente | Comportamiento legacy / contrato | Falla u omisión v3 | Causa raíz y evidencia | Solución propuesta | Estado |
|---|---:|---|---|---|---|---|---|
| TE-001 | P0 | Autorización de mutaciones | El resultado funcional debe afectar sólo expedientes del cargo/compañía | IDs arbitrarios pueden recepcionarse, derivarse, asignarse, enviarse o finalizarse | `CorreoExternoController.php:324-381,392-1072` desactiva por expediente+activo sin scope; Redirigir `:136-313` repite patrón | Policy por objeto, query scoped, estado origen, `lockForUpdate`, 403/404/409 y tests IDOR | ABIERTO |
| TE-002 | P0 | Descargas | Sólo usuarios con acceso al expediente descargan; toda descarga se audita | Descarga por movimiento/nombre sin Policy ni compañía/cargo | `CorreoExternoController.php:1651-1673`; `AdjuntarTramiteController.php:304-312` | ID opaco, resolver relación servidor, Policy, storage privado, `Storage::download`, auditoría | ABIERTO |
| TE-003 | P0 | XSS DOM | Texto del expediente se renderiza como texto | `x-html` recibe datos BD y búsqueda; regex usa input sin escape | `segi_expedi.blade.php:214-222,1082-1086`; clon `segi_bandeja.blade.php:313-321,1185+` | Usar `x-text`; highlighter por nodos/escape HTML y regex; pruebas payload/regex | ABIERTO |
| TE-004 | P0 | Vigencia/estado final | Estado 3/26 debe aparecer en Finalizados y habilitar Archivo/Notificar | Correo finaliza con `in_esta=0`; Archivo/Notificar consultan sólo `in_esta=1` | Correo `:983-995,1182-1186`; Archivo `:326-365`; Notificar `:56-65` | Definir contrato único y adaptar Repository/Service a los datos existentes, sin migrarlos | ABIERTO |
| TE-005 | P0 | Doble envío | Una confirmación genera una transición | Botones/handlers críticos permiten reenvío; backend no es idempotente | Blade `:383-384,489-490,583-584,678-679,782-783,1222-1387`; sin locks en controller | Deshabilitar+spinner, guard JS, Idempotency-Key y lock/cache sin cambios de esquema | ABIERTO |
| TE-006 | P0 | Custodia de archivos | Adjuntos privados, validados y auditables | Archivos en `storage/app/public`; MIME/nombres insuficientes | Correo `:1406-1477`; Adjuntar `:271-286`; Archivo `:143-149` | `GestorAdjuntos`, UUID, MIME real, hash, privado por fecha, antivirus y cleanup | ABIERTO |
| TE-007 | P0 | Legacy path traversal | El legacy sólo debería servir adjuntos propios | `nom_archivo` puede recorrer paths y se transmite directo | legacy `adjunto/ver_archivo.php:11-27` | Retirar endpoint o encerrar con realpath/basename+Policy; bloquear acceso web directo | ABIERTO |
| TE-008 | P0 | Legacy upload/RCE | Upload documental, no ejecutable | Copia extensión/nombre controlado a webroot | `segi_notificar.php:46-53`, `segi_adjuntar.php:24-29`, `agre_anexo.php:35-40`, `general.class.php:8837-8843` | Deshabilitar uploads legacy al migrar; WAF temporal; storage privado y allowlist | ABIERTO |
| TE-009 | P0 | Legacy SQLi | Filtros acotados | Columna, valor, LIMIT/OFFSET concatenados tras base64 | `segi_entrada.php:87-107` y pares; `general.class.php:3242-3387`; 106/114 PHP referencian superglobales | Retirar endpoints; bindings y enums; pentest de rutas restantes | ABIERTO |
| TE-010 | P0 | Legacy CSRF/GET | Mutaciones POST+CSRF | Side effects por GET y acciones sin token | `segi_finalizados.php:21-97,267`; `segi_redirigir.php:22-29` | Bloquear GET mutantes; CSRF; SameSite; migrar a endpoints Laravel | ABIERTO |
| TE-011 | P1 | Histórico U | Origen + todos los usuarios destino | Sólo entrega actor y `title` | backend `:286-306`; Blade `:301-304`; legacy `segi_tramite.php:73-91` | Join agrupado de cargo→logins→usuarios; popover accesible | ABIERTO |
| TE-012 | P1 | Histórico A | Adjuntos con Fecha/Usuario/IP | Link sin trazas; incluso URLs `/storage` directas | legacy `segi_tramite.php:93-98`; v3 modal `:306-310`; partials directos `modal_detalle:102`, `tabla_resultados:81` | Endpoint privado y `p_sgmto_dscrgas`; popover accesible | ABIERTO |
| TE-013 | P1 | Histórico D | Voucher principal/anexo por movimiento | Sin columna/modal en histórico | Voucher existe fuera del flujo; rutas `web.php:381,389` | DTO y ruta por movimiento autorizada; modal/print | ABIERTO |
| TE-014 | P1 | Descargar todos | ZIP + PDF unificado `UNIDO-*` | Sin ruta/backend/UI | legacy `segi_tramite.php:145-150`; v3 rutas `web.php:413-425` | Service de consolidación, manifest, cola, temporales seguros y auditoría | ABIERTO |
| TE-015 | P1 | Devolver | Salida ofrece `segi_regresar.php`, estado 4 | No endpoint ni botón | legacy `segi_salida.php:149-150`, `segi_regresar.php:14-22`; v3 sin ruta | FormRequest, Service, Policy, lock y botón en Salida | ABIERTO |
| TE-016 | P1 | Bandejas | Notificados, Prestados e Inventariar son capacidades identificables | Principal sólo tiene 7 vistas; capacidades fragmentadas | Blade `segi_expedi:51-76`; Archivo separado `:493-521` | Integrar navegación/contratos propios sin duplicar queries | ABIERTO |
| TE-017 | P1 | Acción contextual final | Finalizados ofrece Notificar, Archivar, Imprimir | Correo principal sólo imprime; acciones están en otra pantalla | legacy `segi_finalizados.php:220-223`; Blade `:129-141` | Acciones contextuales con allowed_actions o navegación explícita equivalente | ABIERTO |
| TE-018 | P1 | Notificar desde Recibidos | Legacy notifica estado al solicitante por fila | Acción ausente en tabla principal | legacy `segi_recepcion.php:191`; Blade acciones `:223-247` | Añadir contrato diferenciado de notificación de estado | ABIERTO |
| TE-019 | P1 | Correo/colas | Notificación desacoplada | `Mail::send` dentro de transacción; fallo se traga | Correo `:918-1067`; Archivo `:131-160,488-519` | Outbox + `NotificationCore::send()` afterCommit + estado de entrega | ABIERTO |
| TE-020 | P1 | Correlativos | Números únicos | `MAX+1`/lectura+insert tiene carrera | Correo `:1370-1403`; legacy `gene_ulti_id` `general.class.php:3437-3444` | Reutilizar el mecanismo existente mediante puerto transaccional; si no es atómico, bloquear sin cambiar DB | ABIERTO |
| TE-021 | P1 | Arquitectura | Controller delgado, Service/Repository | 13 controladores / 8,022 líneas; Correo 1,746 con SQL/files/mail | No hay Service/Repository específico | Extraer Policy, Requests, Service, Repository/contract, adapters y DTOs | ABIERTO |
| TE-022 | P1 | Validación | Contrato servidor completo | Correo usa `Request`, casts y `empty()` | métodos `:324+`; sin FormRequests | FormRequest por flujo, enums, límites, validación condicional y mensajes | ABIERTO |
| TE-023 | P1 | Fuga de errores | 5xx seguro | Devuelve `$e->getMessage()` al cliente | Correo `:235-239,384-385,562-564,716-718,887-889,1070-1072`; otros | Error público genérico + correlation ID; detalle sólo log | ABIERTO |
| TE-024 | P1 | Histórico sin finalizar | Último movimiento determinista | Legacy subquery puede devolver varias filas; v3 corrige parcialmente | legacy `general.class.php:3360-3388`, `segi_sinfinalizar.php:78`; link errado `:226` | `DISTINCT ON`/window bien definido; estado vigente; prueba múltiples activos | ABIERTO |
| TE-025 | P1 | Préstamos | Sólo custodio ve/gestiona | Legacy filtra compañía+estado 28, no cargo/área | `segi_prestados.php:86-154` | Scope de custodia y Policy; revisión de exposición histórica | ABIERTO |
| TE-026 | P1 | Inventario | Archivar/inventariar con una semántica | Dos flujos estado 27; posible duplicado y campos UI ignorados | `segi_inventariar.php:23-38,67-86`; `segi_archivar.php` | Unificar servicio e idempotencia de aplicación usando el esquema existente | ABIERTO |
| TE-027 | P1 | Pruebas | Cobertura de todas las transiciones y auth | Un test genérico selecciona usuarios activos y puede mutar la BD configurada | `tests/Feature/SeguimientoExpedienteTest.php:29-182`; ejecución previa no certificada | Guard anti-DB, Unit Tests con puertos mockeados y Feature DB sólo mediante ejecución manual/autorizada | ABIERTO |
| TE-028 | P1 | Ejecución CI | Artisan/Pest/PHPStan ejecutables | Toolchain ya inicia, pero PHPStan reporta 352 errores (61 del módulo) y Composer 28 advisories | baseline 2026-10-09; Vite y Pest iniciaron, sin certificar DB | Corregir deuda por unidades, actualizar 7 paquetes autorizados y mantener gates sin conexión DB | ABIERTO |
| TE-029 | P2 | Accesibilidad modal | Modal operable con teclado/lector | Sin `role=dialog`, `aria-modal`, foco, Escape; X sin nombre | `segi_expedi.blade.php:323-929` | Dialog component accesible; focus trap/restore; aria-live | ABIERTO |
| TE-030 | P2 | HTTP frontend | Mostrar 401/419/422/500 correctamente | `.then(r => r.json())` sin `r.ok` | Blade `:1235-1383` | Cliente fetch común, content-type/status, errores por campo | ABIERTO |
| TE-031 | P2 | Rutas UI | Páginas con GET explícito | GET depende de catch-all y `a_rles.de_dreccon` | `routes/web.php:370-455,518-523`; dispatch AuthController | Rutas GET nombradas; metadata sólo navegación, no resolución | ABIERTO |
| TE-032 | P2 | URLs | Funcionar en subdirectorio/prefix | Hoja/descarga hardcodeadas | Blade `:230,307` | `route()`/URLs firmadas, no concatenación literal | ABIERTO |
| TE-033 | P2 | Dependencias frontend | Assets compilados, versionados | Tailwind/Alpine CDN inline y lógica duplicada | Blade `:8-11`; otras vistas | Vite, versiones fijadas, CSP/SRI si CDN excepcional, componentes compartidos | ABIERTO |
| TE-034 | P2 | Código duplicado | Una bandeja canónica | `segi_bandeja.blade.php` clon de 1,497 líneas, mojibake y aparentemente huérfano | AuthController usa `segi_expedi`; mojibake líneas `251,373,...` | Confirmar referencias, eliminar/archivar clon y test de vista canónica | ABIERTO |
| TE-035 | P2 | Tutoriales | Recepcionar/Derivar/Asignar disponibles | No existen; modal video trata otra capacidad | legacy `segi_expedi.php:105-117`; Blade `:790-845` | Modal multimedia accesible y contenidos gestionados | ABIERTO |
| TE-036 | P2 | Tipado/estándares | `strict_types`, PHPDoc, SessionKeys | 13/13 controladores sin strict y usan strings de sesión | cabeceras de controladores; p.ej. Correo `:1-40` | Añadir strict, tipos/PHPDoc, constantes `SessionKeys` | ABIERTO |
| TE-037 | P2 | Consulta/N+1 | Listado escalable | `has_video` ejecuta consulta por fila | Correo `:250-258` | `EXISTS`/join agregado en repositorio | ABIERTO |
| TE-038 | P2 | Seguridad legacy de permisos | Permiso 3 exacto | Se busca carácter `'3'`; 13/30/31 también conceden | `segi_expedi.php:6-14` y bandejas equivalentes | Tokenizar/consulta relacional; retirar auth legacy | ABIERTO |
| TE-039 | P2 | XSS legacy | Salida escapada | Observaciones/nombres/voucher sin escape | `segi_tramite.php:87,96,119-132`; `impr_vauche*:134/159` | Escapar mientras siga activo; CSP; retirar endpoint | ABIERTO |
| TE-040 | P2 | Datos en repositorio | Código separado de documentos/PII | 256 adjuntos reales en `adjunto/`, más XLSX/CSV de reporte | inventario filesystem: 372 total, 258 no-PHP | Retirar de VCS/webroot, rotar backups, clasificar PII, hashes y retención | ABIERTO |
| TE-041 | P2 | Seguridad nueva pestaña | Evitar acceso a opener | `_blank` sin `rel=noopener` | múltiples Blade; p.ej. histórico `:307` | Añadir `rel="noopener noreferrer"` o apertura segura | ABIERTO |
| TE-042 | P2 | URL legacy residual | Todo tráfico usa Laravel | Servicio GDU enlaza PHP legacy | `app/Services/mod_gdu/ProcedenteLicenciasService.php:58` | Sustituir con ruta nombrada y prueba de no-regresión | ABIERTO |
| TE-043 | P2 | Semántica de Archivo | FormRequests autorizan correctamente | Requests retornan `authorize(): true`; autorización manual desigual | `ArchivoExpediente*Request.php:9-12` | Policy en FormRequest/controller de manera uniforme | ABIERTO |
| TE-044 | P2 | Redirección | Historia inmutable | Cambia `p_dto_trmtes.cdgo_area`; IDs arbitrarios | `RedirigirExpedienteController.php:136-238` | Movimiento 13, Policy, lock, auditoría área anterior/nueva | ABIERTO |
| TE-045 | P3 | Placeholders | No publicar pantallas incompletas | Existe `stub_pendiente.blade.php`, sin referencias; no se halló `alert('pendiente')` | búsqueda de referencias/alertas | Mantener fuera de rutas o eliminar tras confirmar; test de rutas | ABIERTO |

## Defectos legacy que no deben reproducirse

- SQL dinámico y superglobales concatenados.
- Acciones sin sesión, permiso o CSRF.
- uploads en webroot y nombres controlados por usuario.
- lectura por path/nombre proporcionado.
- autorización basada en botón oculto o carácter de permiso.
- `last_value`, variables no inicializadas y ausencia de transacción.
- XSS en observaciones, nombres y vouchers.
- SMTP debug, BCC/dominios/municipios hardcodeados.
- temporales ZIP predecibles y no eliminados.

## Orden de remediación

1. **Contención P0:** cerrar IDOR/XSS/path/storage/doble envío y acordar vigencia de estados.
2. **Caracterización:** fixtures anonimizados, conteos por bandeja y transición.
3. **Núcleo:** enums, Policy, FormRequests, Service, Repository, locks/idempotencia/outbox.
4. **Paridad:** Devolver, Notificados, Inventariar, U/A/D y descarga consolidada.
5. **UX/operación:** accesibilidad, assets, rutas explícitas, tutoriales y observabilidad.
6. **Cierre:** Pest/PHPStan/audit, prueba visual y reconciliación legacy↔v3.

## Evidencia de verificación pendiente

Para cambiar un ítem a `IMPLEMENTADO` se adjunta:

- commit/archivo y líneas;
- prueba automatizada que fallaba antes y pasa después;
- resultado de PHPStan/Pest;
- evidencia de autorización negativa;
- captura o revisión visual cuando afecte UI;
- decisión de compatibilidad cuando afecte estados, sin migración de datos.
