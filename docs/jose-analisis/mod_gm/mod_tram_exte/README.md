# Auditoría forense y contrato de paridad — Trámite Externo

Este directorio conserva la línea base, los contratos y la evidencia de revisión de `mod_tram_exte` en SIGTRAM v3. Las matrices iniciales describen el primer corte histórico; el estado vigente y sus bloqueos se mantienen en [odd-task.md](odd-task.md).

## Ruta rápida de revisión

1. Usar como plan vigente de cierre [15-spec-remediacion-postauditoria-interno-externo.md](15-spec-remediacion-postauditoria-interno-externo.md), tareas RA-00…RA-12.
2. Conservar [14-spec-refactor-frontend-interno-externo.md](14-spec-refactor-frontend-interno-externo.md) como arquitectura ya implantada que la remediación no puede degradar.
3. Conservar [13-spec-correccion-postvalidacion-externo.md](13-spec-correccion-postvalidacion-externo.md) como contrato funcional que el cierre no puede degradar.
4. Consultar [10-spec-u-usuarios-interno-externo.md](10-spec-u-usuarios-interno-externo.md), [08-contrato-esquema-existente.md](08-contrato-esquema-existente.md) y [02-spec-historico-y-trazabilidad.md](02-spec-historico-y-trazabilidad.md) como contratos complementarios.
5. Consultar [05-plan-ejecucion-antigravity.md](05-plan-ejecucion-antigravity.md) únicamente como historial del primer ciclo AG-00…AG-18.
6. Los prompts maestros se entregan únicamente por chat; no crear archivos de prompt en el repositorio.
7. No afirmar paridad hasta superar SPEC 15, una nueva auditoría independiente y las pruebas manuales autorizadas.

> **Control DB:** esta migración no cambia esquema ni datos. Ningún agente puede conectarse, ejecutar SQL, crear migraciones o preparar scripts. Una necesidad DB inesperada se documenta como fuera de alcance y bloquea la unidad.

## Veredicto ejecutivo

| Dimensión | Estado | Evidencia principal |
|---|---|---|
| Bandejas esenciales | Parcial | Blade ofrece Entrada, Recibidos, Salida, Finali-Notifi, Archivo, Todos y Sin Finalizar; faltan como vistas funcionales independientes Notificados, Prestados e Inventariar. |
| Acciones básicas | Parcial | Existen `recepcionar`, `derivar`, `asignar`, `enviar` y `finalizar`; devolver, notificar contextual, prestar y descargar consolidado no están en `CorreoExternoController`. |
| Histórico | Crítico | El endpoint devuelve fecha, áreas, estado, observación, adjunto y un usuario; no implementa U, auditoría A, voucher D ni ZIP/PDF unificado. |
| Seguridad de dominio | Crítico | Las escrituras aceptan IDs del cliente y desactivan movimientos por expediente sin demostrar que el cargo activo sea dueño del movimiento. |
| Adjuntos | Crítico | Se escriben en `storage/app/public/*`; contradice SEC-11/14 y no registra cada descarga. |
| Arquitectura | Crítico | `CorreoExternoController.php` tiene 1,746 líneas y contiene consultas, reglas, archivos, correlativos y correo síncrono. |
| Verificación | Parcial | Dependencias y build operativos; Pest previo 34/34, PHPStan con 352 errores globales/61 del módulo y Composer con 28 advisories. Las pruebas DB quedan congeladas por el guardrail manual. |

## Alcance realmente inspeccionado

Fecha de corte: **2026-10-09**, zona horaria America/Lima.

| Fuente | Inventario observado | Tratamiento |
|---|---:|---|
| `sigtram/Presentacion/mod_gm/mod_tram_exte` | 372 archivos | 114 PHP revisables, 258 adjuntos/datos históricos; los binarios se clasificaron como datos, no como código ejecutable. |
| PHP legacy raíz | 82 archivos | Bandejas, formularios transaccionales, vouchers, reportes e impresión. |
| PHP legacy `ajax/` | 24 archivos | Dependencias de carga dinámica y búsquedas. |
| PHP legacy auxiliares | 8 archivos | `adjunto/`, `correo/`, `imprimir/` y `reportes/`. |
| Vistas Laravel objetivo | 35 Blade | 26 vistas/partials principales y 9 piezas auxiliares/PDF. |
| Controladores Laravel objetivo | 13 archivos / 8,022 líneas | Se compararon responsabilidades, endpoints, validación y persistencia. |
| Servicios/repositorios del módulo | 0 | Brecha contra ARCH-01/03/04/14. |
| Tests detectados | 1 referencia genérica | `tests/Feature/SeguimientoExpedienteTest.php`; no constituye cobertura contractual completa del módulo. |

> Hallazgo de custodia: el árbol legacy contiene 256 archivos de usuario en `adjunto/` y otros 2 artefactos de reporte, principalmente `.odt`. Deben inventariarse y migrarse a almacenamiento privado, con hash, clasificación de PII y política de retención; no deben copiarse a `public/`.

## Fuente de verdad y reglas de decisión

> **HIST-001 — Invariante global del histórico:** todo histórico de trámite o expediente, en cualquier bandeja, reporte, consulta o modal de SIGTRAM v3, debe conservar la misma estructura funcional: cabecera formal; columnas Entrada, T.E., Área Salida, Estado, Área Envío y Observaciones; columna U con Usuario Origen y todos los Usuarios Destino; columna A con adjunto privado y trazabilidad Fecha/Usuario/IP; columna D con voucher; y pie “Descargar todos” con ZIP y PDF unificado cuando corresponda. U/A/D deben funcionar mediante hover, foco de teclado y activación por clic/Enter/Espacio. Ninguna vista reducida puede omitir estas capacidades sin una excepción documentada y aprobada.

1. El comportamiento legacy es la fuente funcional, no la fuente de seguridad ni de diseño.
2. Cuando el legacy sea inseguro o ambiguo, SIGTRAM v3 conserva el resultado de negocio con controles modernos y deja la divergencia explícita en la matriz.
3. Una transición sólo es válida si el expediente pertenece a la compañía, el movimiento activo pertenece al cargo/área autorizados y el estado origen admite la operación.
4. Los estados se encapsulan en un enum/catálogo; queda prohibido repartir números mágicos por controladores y Blade.
5. Toda mutación se ejecuta en transacción, con bloqueo de fila e idempotencia.
6. Los adjuntos son privados; toda lectura pasa por un endpoint autorizado que registra usuario, fecha e IP.

## Mapa funcional consolidado

| Capacidad legacy | Artefacto legacy | Artefacto v3 actual | Evaluación |
|---|---|---|---|
| Contenedor de bandejas | `segi_expedi.php` | `segi_expedi.blade.php` | Parcial |
| Entrada | `segi_entrada.php` | `tabla(bandeja=entrada)` | Presente |
| Recibidos | `segi_recepcion.php` | `tabla(bandeja=recibidos)` | Presente con diferencias de estado |
| Salida | `segi_salida.php` | `tabla(bandeja=salida)` | Presente; falta Devolver |
| Finalizados | `segi_finalizados.php` | `tabla(bandeja=finalizados)` | Incompatible con datos legacy activos |
| Todos | `segi_todos.php` | `tabla(bandeja=todos)` | Presente |
| Sin Finalizar | `segi_sinfinalizar.php` | `reporte=sin_finalizar` | Presente, sin contrato visible separado |
| Archivados | `segi_archivados.php` | `bandeja=archivo` y `segi_archivo.blade.php` | Duplicado/inconsistente |
| Notificados | `segi_notificados.php` | Sólo mezclado con Finali-Notifi / flujo archivo | Falta bandeja propia |
| Prestados | `segi_prestados.php` | `segi_archivo.blade.php` + `ArchivoExpedienteController` | No integrado en bandeja principal |
| Inventariar | `segi_inventariar.php` | `ArchivoExpedienteController::matriz` | Parcial, sin equivalencia UI demostrada |
| Histórico | `segi_tramite.php` | `historial()` + modal Blade | Insuficiente |
| Voucher | `busca_vauche*.php` | `voucher*.blade.php` | Existe fuera del histórico |
| Descargar todos | `segi_descargar_adjunto.php` | Sin endpoint equivalente | Ausente |

## Inventario de código legacy por familia

La cobertura se controla por familias para evitar confundir documentos históricos con PHP ejecutable.

| Familia | Archivos |
|---|---|
| Bandejas y seguimiento | `segi_expedi.php`, `segi_entrada.php`, `segi_recepcion.php`, `segi_salida.php`, `segi_finalizados.php`, `segi_todos.php`, `segi_sinfinalizar.php`, `segi_archivados.php`, `segi_notificados.php`, `segi_prestados.php`, `segi_inventariar.php`, `segi_tramite.php`, `segi_verexpedi.php`, `ver_detalle_historico.php` |
| Transacciones | `segi_reenviar.php`, `segi_reenviar9.php`, `segi_reenviar_areas.php`, `segi_asignar.php`, `segi_enviar.php`, `segi_finaliza.php`, `segi_regresar.php`, `segi_archivar.php`, `segi_archivo.php`, `segi_notificar.php`, `segi_notificar_estado.php`, `segi_notificar_estado2.php`, `segi_prestar.php`, `segi_redirigir.php`, `segi_adjuntar.php`, `agre_anexo.php` |
| Consulta y alta | `index.php`, `index_2.php`, `index_ulti.php`, `index_pru.php`, `index3.php`, `ingresar.php`, `busca_expedi.php`, `busca_expedi_deta.php`, `gene_expe_exter.php`, `gene_expedi_cita.php`, `gene_expedi_obser.php`, `prescripcion.php` |
| Voucher e impresión | `busca_vauche.php`, `busca_vauche_anex.php`, `lista_vaucher.php`, `impr_vauche.php`, `impr_vauche3.php`, `impr_vauche_anex.php`, `impr_vauche_linux.php`, `impr_bandeja.php`, `impr_expedi.php`, `segi_hoja_ruta.php`, `segi_descargar_adjunto.php` |
| Reportes/listados | `lista_expedi*.php`, `lista_repor*.php`, `lista_reporte*.php`, `lista_criticos_area.php`, `exportar_expedientes_excel.php`, `filtro_exportar_excel.php`, `reportes/*.php` |
| AJAX y utilitarios | 24 archivos en `ajax/`, más `segi_estado.php`, `segi_otra.php`, `segi_gene_docu.php`, `segi_relacionar_ticket.php`, `ver_archivo.php`, `ver_*tupa.php` |

## Gobernanza de implementación

Arquitectura objetivo:

```text
HTTP/FormRequest/Policy
        ↓
CorreoExternoController (orquestación y respuesta)
        ↓
TramiteExternoService (reglas, transición, idempotencia)
        ↓
TramiteExternoRepository (consultas y locks)
        ↓
PostgreSQL + AuditLog + GestorAdjuntos + NotificationCore
```

Componentes mínimos:

- `EstadoTramiteExterno` y `BandejaTramiteExterno` como enums.
- `TramiteExternoPolicy` para ver, descargar y transicionar.
- FormRequest por operación; nunca validación ad hoc incompleta.
- `TramiteExternoService` para las transacciones.
- contrato y repositorio para queries de 3+ tablas.
- `GestorAdjuntos` para MIME real, UUID, hash, cuarentena y storage privado.
- `NotificationCore::send()` en cola, nunca `Mail::send()` síncrono.
- eventos de dominio y auditoría Before/After para cada cambio.

## Mapeo de subagentes de auditoría

| Frente | Responsabilidad | Salida consolidada |
|---|---|---|
| `legacy_forensics` | Bandejas, estados, acciones, consultas y artefactos legacy | Specs 01–03 y riesgos legacy en 04 |
| `backend_forensics` | Controladores, rutas, Services/Repositories, auth, storage y tests | Arquitectura objetivo y defectos backend |
| `frontend_forensics` | Blade, botones, modales, histórico y accesibilidad | Contratos visuales/UX y brechas de interfaz |
| Agente principal | Correlación, severidad, decisiones y documentación | Suite SDD completa |

## Limitación de Engram

El encargo solicita `mem_context`, `mem_search`, `mem_save` y `mem_session_summary`. Ninguna herramienta `mem_*`/Engram está expuesta en esta sesión. No se simuló persistencia inexistente. La memoria durable equivalente queda en estos archivos, con IDs estables `TE-###`, rutas y líneas para reanudar el trabajo sin pérdida de contexto.

## Definition of Done para “1000% de paridad”

- [ ] Las diez bandejas devuelven el mismo conjunto funcional sobre una copia anonimizada de datos legacy.
- [ ] HIST-001 se cumple en cada bandeja, consulta, reporte y modal que presente histórico.
- [ ] Cada botón legacy tiene equivalente, permiso, validación, transición, auditoría y prueba.
- [ ] U, A y D funcionan con teclado, foco y lector de pantalla, no sólo `hover`.
- [ ] “Descargar todos” genera ZIP y `UNIDO-*.pdf` reproducibles y auditados.
- [ ] No existe SQL, correo, filesystem ni regla de negocio en Blade.
- [ ] Controladores delgados; Service/Repository cumplen las reglas del repositorio.
- [ ] Storage privado y cero URL pública directa a adjuntos.
- [ ] Pruebas happy/error/border, 401, 403, 404/409 y 422 pasan.
- [ ] PHPStan nivel 5+, Pest, `composer audit` y revisión visual pasan.
- [ ] Una prueba de reconciliación documenta cualquier divergencia intencional del legacy.

## Estado de verificación de este corte

| Comprobación | Resultado |
|---|---|
| Inventario de archivos | Completado |
| Comparación estática de rutas/controladores/Blade/legacy | Completada para el primer corte |
| Ejecución de Artisan/Pest | 34 pruebas / 124 assertions pasaron antes del guardrail; resultado no certifica una DB aislada |
| Prueba contra PostgreSQL | Congelada: ninguna nueva conexión o ejecución por agentes; operación exclusiva del usuario |
| Prueba visual en navegador | No ejecutada: requiere aplicación instalable y dataset seguro |

## Próximo incremento seguro

Entregar el plan a Antigravity y ejecutar sólo AG-00. La implementación continúa por unidades pequeñas sobre el esquema existente; cualquier requisito de cambio PostgreSQL queda fuera de alcance y bloquea la unidad.
