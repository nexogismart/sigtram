# Checklist Forense de Cierre Previo a la Arquitectura Dual de Archivos

> **Proyecto:** `c:\laragon\www\migracion_sigtram\sigtram_v3`  
> **Auditor/Arquitecto:** Lead Laravel Architect & Auditor Forense  
> **Estado General:** COMPLETADO Y SELLADO AL 100% (AUDITORÍA FORENSE SUPERADA)  
> **Nota de Auditoría Forense:** La certificación forense ha sido validada y confirmada en su totalidad tanto en Backend como en Frontend:
> - Backend: 0 accesos directos a BD en Controllers y Services, DTO canónico inmutable, sesión unificada y policies estrictas.
> - Frontend: 44/44 vistas interactivas migradas a Vite y Alpine.js con estrictamente 0 `<script>`, 0 `<style>`, 0 eventos inline (`on*`) y 0 CDNs.
> - Allowlist estrictamente delimitada a 11 vistas de renderizado no interactivo tipo B (DomPDF / impresión física) con 0 scripts y 0 CDNs.
> - Guardrail recursivo `BladeInteractivoArchitectureTest` ejecutado y pasando al 100% (2/2 pruebas, 3 aserciones).
> **Compromisos de Confinamiento:** Cero commits en Git, dirty working tree intacto, cero DDL/DML, arquitectura dual de archivos intacta, QR intacto.

---

## Fase 0 — Checklist Persistente e Inventario Inicial
- [x] Crear `docs/jose-analisis/cierre-prearchivos/00-checklist.md`
- [x] Crear `docs/jose-analisis/cierre-prearchivos/01-inventario-inicial.md` con mediciones exactas antes de modificar
- [x] Crear `docs/jose-analisis/cierre-prearchivos/02-resultado-final.md` (se actualiza progresivamente y se sella al final)

---

## Fase 1 — Unificar el Contexto Autenticado
- [x] 1.1 Migrar todos los consumidores de Trámite Interno y Externo al contexto canónico `App\DTOs\Core\ContextoUsuarioAutenticadoDto`
- [x] 1.2 Controllers reciben `App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface` por constructor
- [x] 1.3 Controllers resuelven contexto obligatorio vía `$contextoService->resolverContextoObligatorio()`
- [x] 1.4 Services y Policies reciben `ContextoUsuarioAutenticadoDto` (no DTOs legacy)
- [x] 1.5 Cero acceso a Auth, Request, Session, DB o Repository dentro de DTOs
- [x] 1.6 Eliminar `app/DTOs/mod_gm/ContextoFuncionarioDto.php` y `app/DTOs/mod_gene/ContextoFuncionarioInternoDto.php` sin dejar wrappers ni aliases
- [x] 1.7 Vocabulario canónico unificado en DTO: `$idLogin`, `$idUsuario`, `$nombreLogin`, `$idPerfil`, `$idCompania`, `$idCargo`, `$idArea`, `$cargosAsignados`, `$permisos`, `$roles`, `$esContabilidad`, `$nombreCompania`, `$nombreCargo`, `$nombreArea`
- [x] 1.8 Eliminar propiedades duplicadas en DTO canónico: `$loginId`, `$usuarioId`, `$loginNombre`, `$perfilId`, `$companiaId`, `$cargoId`, `$areaId`, `$companiaNombre`, `$cargoNombre`, `$areaNombre`, `$login`
- [x] 1.9 Actualizar todos los consumidores al vocabulario canónico `$idLogin`, `$idCompania`, etc.
- [x] 1.10 Mantener métodos legítimos: `getCdgoLgin()`, `getCdgoLginAudit()`, `cargoAsignadoValido()`, `tieneRol()`, `tienePermiso()`
- [x] 1.11 Cero valores fijos predeterminados (sin fallback a 1 o 2) en compañía, cargo, área, login o usuario
- [x] 1.12 Falla cerrada ante contexto inválido o incoherente con excepción controlada / HTTP 403
- [x] 1.13 Eliminar `app()->runningUnitTests()` en reglas de seguridad del contexto

---

## Fase 2 — Sesión Canónica sin Ambigüedad
- [x] 2.1 Auditar `app/Support/SessionKeys.php`: una sola constante semántica y clave física por concepto (`LOGIN_ID`, `USUARIO_ID`, `LOGIN_NOMBRE`, `PERFIL_ID`, `COMPANIA_ID`, `COMPANIA_NOMBRE`, `CARGO_ID`, `CARGO_NOMBRE`, `AREA_ID`, `AREA_NOMBRE`)
- [x] 2.2 Eliminar lecturas directas de strings (`'sigtram_cargo_id'`, etc.) en Core, Auth, Interno y Externo
- [x] 2.3 Cero cadenas de fallback con `?:`, `??` u `||` entre claves de sesión
- [x] 2.4 Cero dual-write en sesión
- [x] 2.5 Inventariar módulos fuera de alcance (OMAPED, SGL, Helpdesk, Promesa) conservando estrictamente lo necesario sin refactorizarlos masivamente

---

## Fase 3 — Una Sola Implementación por Acción
- [x] 3.1 Unificar acciones externas (generar E/S/O, recepcionar, derivar, asignar, enviar, finalizar, devolver, archivar, prestar, retornar préstamo, redirigir, notificar, adjuntar, imprimir bandeja) en un único Service
- [x] 3.2 Unificar acciones internas (generar, recepcionar, asignar, derivar, finalizar, devolver, reiterar, reemplazar firmado, imprimir, descargar) en un único Service
- [x] 3.3 Convertir controladores paralelos en adaptadores delgados o unificarlos:
  - `ArchivoExpedienteController`
  - `AdjuntarTramiteController`
  - `RedirigirExpedienteController`
  - `CorreoExternoController`
  - `CorreoInternoController`
- [x] 3.4 Verificar consumidores en rutas del menú legacy antes de eliminar rutas
- [x] 3.5 Transacciones atómicas, `lockForUpdate`, cardinalidad estricta e idempotencia preservadas

---

## Fase 4 — FormRequest, Policy y Respuestas HTTP
- [x] 4.1 FormRequest específico para toda mutación
- [x] 4.2 Corregir `AdjuntarTramiteController::upload(Request ...)` y `RedirigirExpedienteController::derivar(Request ...)`
- [x] 4.3 Eliminar aliases ambiguos (`ids`, `movement_ids`) hacia `movimiento_ids`
- [x] 4.4 FormRequests con `authorize()`, `rules()`, mensajes en español y normalización inequívoca
- [x] 4.5 Policies de mutación validan compañía, cargo activo, área, relación con movimiento y estado vigente
- [x] 4.6 Políticas de lectura histórica preservan acceso al expediente completo sin IDOR
- [x] 4.7 Manejo de errores homogéneo (403/404/409/422/500 con correlation_id) sin filtrar PostgreSQL al cliente

---

## Fase 5 — Controllers Delgados y Repositories
- [x] 5.1 Eliminar todos los accesos directos a BD (`DB::`, `->table()`, SQL, `select`, `selectOne`, transacciones manuales) en los controladores del alcance (0 matches certificados)
- [x] 5.2 Controladores delegan 100% de consultas y mutaciones a Services y Repositories
- [x] 5.3 Services delegan consultas de negocio directas a Repositories (manteniendo `DB::transaction()` solo para atomicidad)
- [x] 5.4 Repositories encapsulan queries, mutaciones, locks y cardinalidad de filas
- [x] 5.5 Estructurar por módulo:
  - `app/Services/mod_gene/mod_tram_inter/`
  - `app/Repositories/mod_gene/mod_tram_inter/`
  - `app/Services/mod_gm/mod_tram_exte/`
  - `app/Repositories/mod_gm/mod_tram_exte/`
- [x] 5.6 Revisión exhaustiva de los controladores prioritarios del sistema (10/10 externo y 7/7 interno saneados)

### Inventario Forense de Incumplimientos (Línea Base: 140 Controllers, 13 Services -> Estado Final: 0 Incumplimientos)

| Archivo | Método | Tipo Acceso BD | Service Responsable | Repository Destino | Estado |
|---|---|---|---|---|---|
| `BuscarTramiteController.php` | `index`, `tiposPorArea`, `detalle`, `historial` | `DB::table`, `DB::select` (8) | `BuscarTramiteService` | `TramiteInternoRepository` | Completado (0 matches) |
| `ReporteCargoInternoController.php` | `index`, `ajaxDetalle`, `areaUsuarioActual`, `count*`, `usuariosDelCargo` | `DB::table` (8) | `ReporteCargoInternoService` | `TramiteInternoReporteRepository` | Completado (0 matches) |
| `ReporteTramiteController.php` | `ajaxDetalle`, `buildReport` | `DB::table`, `DB::select` (4) | `ReporteTramiteService` | `TramiteInternoReporteRepository` | Completado (0 matches) |
| `SecuencialTramiteController.php` | `index`, `lista`, `generar`, `contextoSesion` | `DB::table` (8) | `SecuencialTramiteService` | `TramiteInternoRepository` | Completado (0 matches) |
| `V2ListaReporteAreaCargoController.php` | `index`, `ajaxDetalle`, `buildEvolution`, `count*` | `DB::table`, `DB::select`, `DB::selectOne` (11) | `ReporteAreaCargoInternoService` | `TramiteInternoReporteRepository` | Completado (0 matches) |
| `AdjuntarTramiteController.php` | `detalle`, `baseQuery`, `actualizarMovimiento` | `DB::table` (7) | `TramiteExternoService` | `TramiteExternoRepository` | Completado (0 matches) |
| `ArchivoExpedienteController.php` | `archivar`, `notificar`, `prestar`, `matriz`, `detalle`, `createMovement`, `closeCargoTracking`, `appendObservacion`, etc. | `DB::transaction`, `DB::table`, `DB::raw` (18) | `TramiteExternoService` | `TramiteExternoRepository` | Completado (0 matches) |
| `ListaExpediTodoController.php` | `queryBase` | `DB::table` (1) | `BuscaExpedienteService` | `BuscaExpedienteRepository` | Completado (0 matches) |
| `ListaReporAreaController.php` | `queryBase` | `DB::table`, `DB::raw` (2) | `BuscaExpedienteService` | `BuscaExpedienteRepository` | Completado (0 matches) |
| `ListaReporteController.php` | `queryBase` | `DB::table`, `DB::raw` (2) | `BuscaExpedienteService` | `BuscaExpedienteRepository` | Completado (0 matches) |
| `NotificarPlataformaController.php` | `tabla` | `DB::table` (1) | `NotificarPlataformaService` | `TramiteExternoRepository` | Completado (0 matches) |
| `RedirigirExpedienteController.php` | `index`, `derivar`, `anular`, `detalle`, `queryReenviar`, `cerrarLapsoSilencio`, `festivos` | `DB::beginTransaction`, `DB::table`, `DB::commit`, `DB::rollBack` (24) | `TramiteExternoService` | `TramiteExternoRepository` | Completado (0 matches) |
| `ReporteExpedienteController.php` | `buildAreaReport`, `listarBandejaArea`, `candidatosCriticos`, `festivos`, `buildCargoReport`, `baseSearchQuery` | `DB::table`, `DB::select`, `DB::raw` (12) | `ReporteExpedienteExternoService` | `TramiteExternoReporteRepository` | Completado (0 matches) |
| `SegiVerExpediController.php` | `historico`, `cambiarEstado`, `queryBase`, `estadosDelArea`, `lapsoSilencio`, `festivos` | `DB::table`, `DB::beginTransaction`, `DB::raw` (19) | `TramiteExternoService` | `TramiteExternoRepository` | Completado (0 matches) |
| `V2ListaReporteAreaCargoExternoController.php` | `index`, `ajaxDetalle`, `historico`, `buildEvolution`, `count*`, `usuariosDelCargo`, `rutasAreas` | `DB::table`, `DB::select`, `DB::selectOne` (15) | `ReporteAreaCargoExternoService` | `TramiteExternoReporteRepository` | Completado (0 matches) |
| `ConsolidadoExpedienteService.php` | `generarConsolidado` | `->table(` (3) | `ConsolidadoExpedienteService` | `TramiteInternoRepository` | Completado (0 matches) |
| `DescargaLoteTramiteInternoService.php` | `generarLoteZip` | `->table(` (3) | `DescargaLoteTramiteInternoService` | `TramiteInternoRepository` | Completado (0 matches) |
| `DescargaSeguraTramiteInternoService.php` | `descargarPorDocumento`, `descargarPorMovimiento`, `auditarDescarga` | `->table(` (3) | `DescargaSeguraTramiteInternoService` | `TramiteInternoRepository` | Completado (0 matches) |
| `ReemplazarDocumentoFirmadoService.php` | `reemplazar` | `->table(` (2) | `ReemplazarDocumentoFirmadoService` | `TramiteInternoRepository` | Completado (0 matches) |
| `ValidadorPagoProveedoresTipo64Service.php` | `validarLoteParaRecepcion` | `->table(` (2) | `ValidadorPagoProveedoresTipo64Service` | `TramiteInternoRepository` | Completado (0 matches) |

---

## Fase 6 — Extraer JS y CSS de Blades Interactivos
- [x] 6.1 `ingresar-tramite.blade.php` sin `<style>`, scripts con lógica ni handlers inline (extraído a `resources/js/tramite-interno/ingresar-tramite.js`)
- [x] 6.2 `index.blade.php` de externo sin `<style>`, scripts con lógica ni handlers inline (extraído a `resources/js/tramite-externo/ingresar-expediente.js`)
- [x] 6.3 Blades interactivos de interno y externo limpios de Tailwind/Alpine CDN
- [x] 6.4 Módulos JS y CSS organizados en `resources/js/` y `resources/css/` cargados vía `@vite` y registrados en `app.js` vía `Alpine.data`
- [x] 6.5 Configuración del servidor inyectada de forma segura vía `@json` / atributos `data-config`
- [x] 6.6 Excepción documentada: vistas exclusivas de impresión/voucher/PDF conservan únicamente CSS de renderizado, sin JS inline

---

## Fase 7 — Credenciales y Configuración
- [x] 7.1 Saneamiento de `config/database.php`: eliminar contraseñas y credenciales por defecto
- [x] 7.2 Saneamiento de `config/pide.php`: eliminar DNI/claves de `credentials_pool` y credenciales de `sunarp`
- [x] 7.3 Saneamiento de `config/whatsapp.php`: token por defecto vacío
- [x] 7.4 Saneamiento de `config/camara_visor.php`: credenciales default vacías
- [x] 7.5 Saneamiento de `.env.example`: placeholders limpios sin credenciales reales ni comentadas; `APP_DEBUG=false`
- [x] 7.6 Crear `docs/jose-analisis/cierre-prearchivos/03-rotacion-credenciales.md` listando solo nombres de variables comprometidas (nunca secretos)
- [x] 7.7 Confirmar que `QR_KEY` permanece separado de `APP_KEY` sin fallback fijo

---

## Fase 8 — Guardrails que Eviten Repetir el Problema
- [x] 8.1 Guardrail recursivo contra `ContextoFuncionarioDto` y `ContextoFuncionarioInternoDto`
- [x] 8.2 Guardrail recursivo contra acceso a Auth, Request, Session, DB en DTOs
- [x] 8.3 Guardrail contra propiedades duplicadas en DTO canónico
- [x] 8.4 Guardrail contra strings literales de sesión en Core/Auth/Interno/Externo
- [x] 8.5 Guardrail recursivo contra `DB::` y `->table()` en Controllers del alcance (`tests/Unit/ArquitecturaControllersYServicesTest.php` y `tests/Unit/RepositoryBoundaryArchitectureTest.php`)
- [x] 8.6 Guardrail recursivo contra queries de negocio directas en Services (solo `DB::transaction()` permitido)
- [x] 8.7 Guardrail contra mutaciones con `Request` genérico
- [x] 8.8 Guardrail contra controladores con implementaciones duales de la misma acción
- [x] 8.9 Guardrail contra credenciales predeterminadas no vacías en `config/`
- [x] 8.10 Guardrail contra `<style>`, `<script>`, CDN o eventos inline en Blades interactivos (`tests/Unit/BladeInteractivoArchitectureTest.php` — 2/2 pasados, 3 aserciones)
- [x] 8.11 Guardrail contra nombres en inglés para acciones de negocio
- [x] 8.12 Guardrail contra fallback fijo a compañía 1 o 2
- [x] 8.13 Guardrail contra `runningUnitTests()` en lógica de seguridad

---

## Fase 9 — Certificación y Validación
- [x] 9.1 `composer validate`: Exitoso (exit code 0)
- [x] 9.2 `php artisan optimize:clear`: Exitoso
- [x] 9.3 `php artisan route:list`: Exitoso (374 rutas verificadas sin colisiones)
- [x] 9.4 `php artisan test --compact --display-skipped`: 657 pasados, 7 omitidos, 0 fallos (4,800 aserciones)
- [x] 9.5 `npm test`: 28 pasados, 0 fallos
- [x] 9.6 `npm run build`: Compilación exitosa Vite (1.91s)
- [x] 9.7 `php -l` sobre 100% de archivos PHP modificados: 0 errores de sintaxis
- [x] 9.8 Confirmación de inventarios negativos (0 referencias legacy, 0 DB en controladores, 0 queries en servicios, 0 scripts/styles inline no justificados en 44/44 vistas interactivas)
- [x] 9.9 Confirmación expresa: almacenamiento de archivos y QR intactos

