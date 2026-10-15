# Especificaciones Técnicas y Plan de Migración: Trámite Interno (`mod_tram_inter`)
> **Metodología:** Spec-Driven Development (SDD) & Organic-Driven Development (ODD) — [Gentle-AI](https://github.com/Gentleman-Programming/gentle-ai.git)  
> **Sistema de Memoria:** Engram™ Persistent Memory System (SQLite + FTS5)  
> **Sistema:** SIGTRAM 3 (Laravel 13 + PHP 8.4 + PostgreSQL + Tailwind CSS)  
> **Referencia Legacy:** SIGTRAM Monolito (`c:\laragon\www\sigtram\Presentacion\mod_gene\mod_tram_inter`)  
> **Fecha:** Octubre 2026 | **Versión:** 1.1.0
> **Estado auditado:** `REMEDIATION_REQUIRED — RA-00…RA-12 — NO APTO PARA PRODUCCIÓN`

---

## 1. Propósito y Alcance

Este conjunto de especificaciones define con rigor técnico, arquitectónico y forense la migración al **1000% de paridad operativa** del módulo de **Trámite Interno / Correo Interno** (`mod_tram_inter`) desde el sistema legacy PHP 7.4 hacia la arquitectura moderna de SIGTRAM 3 en Laravel.

Se abordan de forma exhaustiva:
1. **Todas las bandejas del menú:** Entrada, Enviados, Todos, Recibidos, Salida, Finalizados, Sin Finalizar (Reporte), Comprobantes/Contabilidad.
2. **Todos los botones y acciones por bandeja:** Recepcionar, Imprimir, Mostrar solo faltantes, Reiterativo, Descargar, Derivar (multi-área + borrador Word), Asignar (cargo interno), Finalizar, Devolver, Hoja de Ruta, Editar Adjunto Firmado.
3. **El panel de Histórico completo:** Banner Khaki, columna `T.E` (tiempo transcurrido), columna `U` con popover dinámico de 2 columnas (Usuario Origen vs Usuarios Destino del cargo receptor), columna `A` con trazabilidad de descargas (Fecha, Usuario, IP), columna `D` (voucher de movimiento) y pie `"Descargar Todos => [ZIP]"`.
4. **Los 6 videos tutoriales:** Despliegue completo en la barra lateral con reproducción modal o aviso de actualización.

---

## 2. Estructura de Especificaciones (SDD / Gentle-AI)

| Archivo | Contenido y Cobertura |
|---|---|
| [`01-spec-bandejas-y-botones.md`](./01-spec-bandejas-y-botones.md) | Especificación de las 8 bandejas, filtros, tabla principal, y **cada uno de los botones de acción** con sus reglas de negocio y validaciones. |
| [`02-spec-historico-y-usuarios.md`](./02-spec-historico-y-usuarios.md) | Especificación detallada del **Histórico**, cálculo de `T.E`, interacción hover/focus de la columna `U` (Usuarios Origen vs Destino), trazabilidad `A`, voucher `D` y ZIP consolidado. |
| [`03-spec-flujos-transaccionales.md`](./03-spec-flujos-transaccionales.md) | Contratos y flujos completos de los 5 modales transaccionales: **Derivar**, **Asignar**, **Finalizar**, **Devolver**, **Reiterar**, más **Hoja de Ruta** y **Modificar Adjunto**. |
| [`04-matriz-errores-y-soluciones.md`](./04-matriz-errores-y-soluciones.md) | Matriz forense de discrepancias actuales en Laravel vs Legacy, diagnóstico de causas raíz y especificación técnica de solución 1:1. |
| [`05-spec-cierre-produccion-seguridad-y-paridad.md`](./05-spec-cierre-produccion-seguridad-y-paridad.md) | Guardas P0, autorización, adjuntos, descargas, transacciones, brechas funcionales y Definition of Done posterior a la auditoría. |
| [`06-spec-paginacion-servidor.md`](./06-spec-paginacion-servidor.md) | Contrato de paginación real para las ocho bandejas, Histórico, buscadores y reportes, incluida la preservación del cálculo `T.E`. |
| [`07-plan-tareas-antigravity.md`](./07-plan-tareas-antigravity.md) | Orden de ejecución granular para Antigravity, dependencias, pruebas y formato de entrega para revisión independiente. |
| [`SPEC 14 — Refactor frontend Interno/Externo`](../../mod_gm/mod_tram_exte/14-spec-refactor-frontend-interno-externo.md) | Arquitectura Vite, eliminación de eventos inline y `innerHTML`, modularización, seguridad DOM y tareas RF-00…RF-09 compartidas. |
| [`SPEC 15 — Remediación postauditoría Interno/Externo`](../../mod_gm/mod_tram_exte/15-spec-remediacion-postauditoria-interno-externo.md) | Plan vigente RA-00…RA-12 para autorización, atomicidad, contratos HTTP, errores seguros, U/A/D, deuda estructural y certificación sin BD. |

---

## 3. Estado de Memoria Persistente (Engram AI)

Las decisiones arquitectónicas, diagnósticos forenses y contratos de este módulo se encuentran sincronizados y versionados en **Engram AI** bajo los siguientes topics:
* `architecture/tramite-interno-gap-analysis`: Diagnóstico y brechas 1:1 entre legacy y Laravel.
* `architecture/tramite-interno-historico-spec`: Especificación del comportamiento del Histórico y columna `U`.
* `architecture/tramite-interno-botones-bandejas`: Mapeo de botones transaccionales por bandeja.
