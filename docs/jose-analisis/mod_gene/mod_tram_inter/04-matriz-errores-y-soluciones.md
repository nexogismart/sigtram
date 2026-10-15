# SPEC-04: Matriz Forense de Errores, Discrepancias y Soluciones
> **Estándar:** Spec-Driven Development (Gentle-AI)  
> **Componente:** `mod_gene / mod_tram_inter`  
> **Objetivo:** Registro exhaustivo de causas raíz, especificación de solución y verificación al 1000% de paridad.  
> **Estado:** ⚠️ **IMPLEMENTACIÓN INICIAL — NO CERTIFICADA**

> **Nota de auditoría (2026-10-10):** los estados históricos de esta matriz describen lo que la primera implementación declaró resuelto, pero no constituyen evidencia de producción. La revisión posterior detectó brechas P0 de autorización, archivos, descargas y concurrencia, además de paridad incompleta. El cierre vigente se define en SPEC-05, SPEC-06 y el plan AG-TI-00…17.

---

## 1. Matriz de Errores y Soluciones 1:1

| ID | Área / Componente | Error / Discrepancia en Laravel | Causa Raíz en Código | Solución Específica en Arquitectura Laravel | Estado Paridad |
|---|---|---|---|---|:---:|
| **ERR-01** | **Menú Sidebar Videos** | En Laravel solo se mostraban 3 videos tutoriales en lugar de los 6 que tiene el legacy (Fotos 1, 2 y 3). | `CorreoInternoController.php` filtraba con `is_file(public_path(...))` y en Blade se usaba `@if ($video['existe'])`. | Se renderizan los 6 botones en Blade. Si el archivo MP4 existe abre el player modal; si no existe, notifica con modal amigable sin quebrar la UX. | ✅ **100% RESUELTO** |
| **ERR-02** | **Bandeja Recibidos: Botones** | Al seleccionar la bandeja Recibidos, la barra superior de acciones quedaba vacía. Faltaban `Derivar`, `Asignar`, `Finalizar`. | `correo-interno.blade.php` tenía condicionales rígidos `clave === 'entrada'` que ocultaban las acciones. | Implementada barra dinámica según `bandejaActiva`. Para Recibidos muestra: `Derivar`, `Asignar`, `Finalizar`, `Imprimir`. | ✅ **100% RESUELTO** |
| **ERR-03** | **Bandeja Enviados: Botones** | Faltaban los botones `Reiterativo` y `Descargar`. `Imprimir` solo tenía un `alert()`. | Faltaban rutas, métodos de controlador y modal en Blade para ejecutar `segi_reiterar.php` y `segi_descargar.php`. | Implementado modal `modal-reiterar`, endpoint POST de reiteración (estado 53), endpoint `descargar-lote` (ZIP) e impresión oficial. | ✅ **100% RESUELTO** |
| **ERR-04** | **Bandeja Salida: Botones** | Faltaba el botón `Devolver`. No se podían rechazar expedientes. | No se desarrolló el modal ni el controlador de devolución (`segi_regresar.php`). | Implementado modal `modal-devolver`, endpoint POST que actualiza a estado 4 (`DEVUELTO`) y registra causal. | ✅ **100% RESUELTO** |
| **ERR-05** | **Histórico: Banner Khaki** | El encabezado del histórico mostraba solo texto gris *"Detalle del trámite Nº {id}"* en vez del formato oficial. | `historial()` en Laravel solo retornaba la lista de movimientos sin los metadatos del trámite cabecera (`de_tpo_trmte`, `nu_tram_todo`, `dc_area`). | Endpoint `historial()` retorna metadatos del trámite y Blade renderiza badge Khaki persistente: `Histórico de MEMORANDUM 1683-2026-S.G.R.E.C.-`. | ✅ **100% RESUELTO** |
| **ERR-06** | **Histórico: Columna T.E** | La tabla del histórico no tenía la columna de Tiempo Estimado / Transcurrido. | Se ignoró la función `clase_procesos::gene_estimado` del legacy al diseñar la vista. | Implementado helper de cálculo de tiempo transcurrido en backend (`7d`, `2H`, `30m`, `15s`) e incluida la columna `T.E` en la tabla. | ✅ **100% RESUELTO** |
| **ERR-07** | **Histórico: Columna U (Foto 4)** | No existía la columna `U` de Usuarios ni el popover/modal flotante con las dos columnas (Origen vs Destinatarios). | `historial()` no consultaba la relación de usuarios del cargo destino en `p_crgos -> r_crgos_lgin -> p_lgin -> p_usrios`. | Backend resuelve `usuario_origen` y lista de `usuarios_destino` del cargo. Frontend despliega modal zoom interactivo con ambas columnas. | ✅ **100% RESUELTO** |
| **ERR-08** | **Histórico: Columna A Trazabilidad** | El clip de adjunto era un enlace simple y no mostraba la trazabilidad de descargas (Fecha, Usuario, IP). | No se migró la consulta contra `p_sgmto_dscrgas`. | Popover hover sobre el clip con tabla completa de descargas auditadas (`p_sgmto_dscrgas`). | ✅ **100% RESUELTO** |
| **ERR-09** | **Histórico: Columna D Voucher** | No existía la columna de comprobante de movimiento individual. | No se implementó el componente de voucher `busca_vauche_adjun.php`. | Columna `D` con icono de ojo que abre modal con el ticket de pase del movimiento (`/correo_interno/voucher/{id}`). | ✅ **100% RESUELTO** |
| **ERR-10** | **Histórico: Descargar Todos** | No existía la opción para descargar todos los adjuntos del expediente compactados en un ZIP con el PDF consolidado. | No se migró la lógica de `segi_descargar_adjunto.php` que une adjuntos y comprime en ZIP. | Endpoint `/correo_interno/descargar-completo/{id}` implementado con `ZipArchive` y botón en pie de tabla `"Descargar Todos => [ZIP]"`. | ✅ **100% RESUELTO** |
| **ERR-11** | **Columna Hoja de Ruta en Tablas** | Las bandejas Enviados, Recibidos y Sin Finalizar no tenían la columna de Hoja de Ruta (`segi_hoja_ruta.php`). | Omitida de las cabeceras `renderThead()`. | Columna `H` con icono de documento en las 3 bandejas correspondientes conectada directamente a la vista imprimible A4 de Hoja de Ruta. | ✅ **100% RESUELTO** |
| **ERR-12** | **Validación Pago Proveedor 64** | En bandeja Entrada con filtro de faltantes activo, se permitía recepcionar trámites tipo 64 sin validar campos obligatorios. | No se implementó la llamada síncrona/validación de `ajax/ajax_validar_recepcion.php`. | Integrada en el backend de recepción la regla de negocio que verifica `logistica.p_pgo_prvdres` y bloquea con error 422 si faltan anexos requeridos. | ✅ **100% RESUELTO** |

---

## 2. Plan de Ejecución por Tareas Atómicas (Work Units SDD)

* **Tarea 1 (Histórico 1000%):** ✅ CULMINADA. Backend `CorreoInternoController::historial` enriquecido y `#panel-detalle` en `correo-interno.blade.php` con banner Khaki, columna `T.E`, columna `U` con modal interactivo (Foto 4), columna `A`, columna `D` y fila de descarga ZIP.
* **Tarea 2 (Barra de Videos Completa):** ✅ CULMINADA. 6 tutoriales activos en el sidebar con reproductor modal HD o aviso amigable de actualización.
* **Tarea 3 (Modales Transaccionales):** ✅ CULMINADA. Programados en Blade y `CorreoInternoTransaccionalService` los modales de **Derivar**, **Asignar**, **Finalizar**, **Devolver** y **Reiterar**.
* **Tarea 4 (Hojas de Ruta y Vouchers):** ✅ CULMINADA. Vista imprimible oficial de Hoja de Ruta (`hoja-ruta.blade.php`), columna `H` en tablas, botón en histórico y modal de voucher del movimiento (`busca_vauche_adjun.php`).
* **Tarea 5 (Impresión y Exportación Excel):** ✅ CULMINADA. Vista imprimible A4 `reporte-bandeja.blade.php` y endpoints de impresión / exportación CSV para todas las bandejas según filtros aplicados.
