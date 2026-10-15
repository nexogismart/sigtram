# Inventario Forense de Fallbacks, Aliases, Nomenclatura y Variables Ambiguas (FB-01 / FB-02)

> **Auditoría Forense:** Lead Laravel Architect & Forensic Auditor  
> **Ámbito:** `app/`, `resources/js/`, `resources/views/`, `tests/`  
> **Estado de Pruebas:** 647 pasadas (3,842 aserciones), 0 fallos, 7 omitidas (pruebas DB con guardrail P0)  
> **Compilación & Rutas:** `npm run build` exitoso (2.03s) | `composer dump-autoload` 8,639 clases | `route:list` 374 rutas compiladas  

---

## 1. Clasificación y Taxonomía Forense

1. **`SECURITY_IDENTITY`**: Alterna o infiere login, usuario, compañía, cargo, área o perfil. **Decisión:** ELIMINAR / PROHIBIR; sustituido por `ContextoUsuarioAutenticadoDto` canónico validado contra PostgreSQL y resuelto por `ContextoUsuarioAutenticadoService`.
2. **`CONTRACT_ALIAS`**: Múltiples nombres para el mismo dato entre SQL, DTO, API y Frontend (`id`, `company_id`, `cdgo_cmpnia`). **Decisión:** Unificar en nombre canónico; normalizar en fronteras.
3. **`REQUEST_COMPAT`**: Aliases de payload HTTP (`compania_id` vs `cdgo_cmpnia`). **Decisión:** Normalizar en `prepareForValidation()` del FormRequest.
4. **`HARD_CODED_BUSINESS`**: Valores fijos institucionales asumidos (`?? 1`, `?? 2`). **Decisión:** ELIMINAR; fail-closed estricto.
5. **`DISPLAY_DEFAULT`**: Texto o valor de presentación cuando el dato es legítimamente nulo en BD (e.g. `de_obsv ?: '-'`). **Decisión:** CONSERVAR explícitamente con tipado y prueba.
6. **`TECHNICAL_DEFAULT`**: Inyección opcional de servicios, colecciones vacías o configuración por defecto (e.g. `$normalizer ?? new ModuleRouteNormalizer()`). **Decisión:** CONSERVAR.
7. **`BOOLEAN_LOGIC`**: Operador `||` usado para lógica booleana condicional en JS/PHP (`if (a || b)`). **Decisión:** NO MODIFICAR.
8. **`NUMERIC_PARSE`**: `parseInt(x) || 0` o `(int) ($val ?? 0)`. **Decisión:** Helper tipado con centinela explícito.
9. **`DEAD_OR_UNKNOWN`**: Código muerto o sin evidencia. **Decisión:** Reportar y aislar.

---

## 2. Matriz de Nomenclatura Canónica de Dominio

| Concepto de Negocio | Nombre Canónico | Nombres Anteriores / Discrepancias | Capa / Rol Arquitectónico | Archivos Afectados |
| :--- | :--- | :--- | :--- | :--- |
| **Recepción de trámite/expediente** | `recepcionar` | `receive`, `receptionar` | Policy, Service, FormRequest | `TramiteInternoPolicy.php`, `CorreoInternoTransaccionalService.php`, `RecepcionarTramiteInternoRequest.php`, `TramiteInternoRemediacionP0Test.php` |
| **Derivación de documento** | `derivar` | `derive`, `forward` | Policy, Service, Controller | `TramiteInternoPolicy.php`, `CorreoInternoTransaccionalService.php`, `TramiteExternoService.php` |
| **Asignación interna** | `asignar` | `assign` | Policy, Service, FormRequest | `TramiteInternoPolicy.php`, `CorreoInternoTransaccionalService.php`, `AsignarTramiteInternoRequest.php`, `TramiteInternoRemediacionP0Test.php` |
| **Envío de trámite** | `enviar` | `send` | Policy, Service | `TramiteInternoPolicy.php`, `TramiteExternoService.php` |
| **Finalización de trámite** | `finalizar` | `finish`, `finalize` | Policy, Service | `TramiteInternoPolicy.php`, `CorreoInternoTransaccionalService.php`, `TramiteExternoService.php` |
| **Devolución de expediente** | `devolver` | `return`, `returnExpedient` | Policy, Service | `TramiteInternoPolicy.php`, `CorreoInternoTransaccionalService.php`, `TramiteExternoService.php` |
| **Archivado institucional** | `archivar` | `archive` | Policy, Service, Controller | `TramiteExternoService.php`, `ArchivoExpedienteController.php` |
| **Préstamo de expediente** | `prestar` | `lend` | Policy, Service | `TramiteExternoService.php`, `ArchivoExpedienteController.php` |
| **Retorno de préstamo** | `retornarPrestamo` | `returnLoan` | Policy, Service | `TramiteExternoService.php`, `ArchivoExpedienteController.php` |
| **Redirección de expediente** | `redirigir` | `redirectExpedient` | Policy, Service | `TramiteExternoService.php` |
| **Reiteración de trámite** | `reiterar` | `reiterate` | Policy, Service | `TramiteInternoPolicy.php`, `CorreoInternoTransaccionalService.php`, `TramiteInternoRemediacionP0Test.php` |
| **Reemplazo de adjunto firmado** | `reemplazarFirmado` | `replaceSignedAttachment` | Policy, Service | `TramiteInternoPolicy.php`, `ReemplazarDocumentoFirmadoService.php`, `TramiteInternoRemediacionP0Test.php` |
| **Descarga de adjuntos/lote** | `descargar` | `download` | Policy, Service | `TramiteInternoPolicy.php`, `DescargaSeguraTramiteInternoService.php`, `DescargaLoteTramiteInternoService.php`, `ConsolidadoExpedienteService.php`, `TramiteInternoRemediacionP0Test.php`, `TramiteInternoDescargaSeguraTest.php` |
| **Consulta de histórico** | `consultarHistorico` | `viewHistory` | Policy, Controller | `TramiteInternoPolicy.php`, `CorreoInternoController.php`, `TramiteInternoArquitecturaMigradaTest.php`, `TramiteInternoAutorizacionIdorTest.php` |
| **Visualización operativa** | `ver` | `view` | Policy | `TramiteInternoPolicy.php`, `TramiteInternoAutorizacionIdorTest.php` |
| **Visualización doc histórico** | `verDocumentoHistorico` | `viewHistoricalDocument` | Policy | `TramiteInternoPolicy.php`, `TramiteInternoPolicyTest.php` |
| **Visualización contable** | `verContabilidad` | `viewAccounting` | Policy | `TramiteInternoPolicy.php`, `TramiteInternoPolicyTest.php` |
| **ID de movimiento** | `$idMovimiento` | `$movementId` | DTO, Service, Controller | `ContextoUsuarioAutenticadoDto.php`, `TramiteInternoMovimientoDto.php` |
| **ID de compañía** | `$idCompania` | `$companyId` | DTO, Service, Session | `ContextoUsuarioAutenticadoDto.php`, `ContextoUsuarioAutenticadoService.php`, `ContextoInvalidoException.php` |
| **ID de cargo** | `$idCargo` | `$cargoId`, `$positionId` | DTO, Service, Session | `ContextoUsuarioAutenticadoDto.php`, `ContextoUsuarioAutenticadoService.php`, `ContextoInvalidoException.php` |
| **ID de área** | `$idArea` | `$areaId` | DTO, Repository, Session | `ContextoUsuarioAutenticadoDto.php`, `ContextoUsuarioAutenticadoRepository.php` |
| **ID de login** | `$idLogin` | `$loginId` | DTO, Service, Repository | `ContextoUsuarioAutenticadoDto.php`, `ContextoUsuarioAutenticadoService.php`, `ContextoUsuarioAutenticadoRepository.php` |
| **ID de usuario/persona** | `$idUsuario` | `$usuarioId` | DTO, Repository | `ContextoUsuarioAutenticadoDto.php`, `ContextoUsuarioAutenticadoRepository.php` |
| **ID de perfil** | `$idPerfil` | `$perfilId` | DTO, Repository | `ContextoUsuarioAutenticadoDto.php`, `ContextoUsuarioAutenticadoRepository.php` |

---

## 3. Categorización de Casos Forenses

### Categoría A: Corregido Ahora (Fase Activa)

| Componente / Archivo | Detalle del Cambio | Regla / Decisión Forense |
| :--- | :--- | :--- |
| `app/Support/SessionKeys.php` | Constantes canónicas únicas consolidadas: `LOGIN_ID`, `USUARIO_ID`, `LOGIN_NOMBRE`, `PERFIL_ID`, `COMPANIA_ID`, `COMPANIA_NOMBRE`, `CARGO_ID`, `CARGO_NOMBRE`, `AREA_ID`, `AREA_NOMBRE`. Cero dual-writes. | `CI-03` |
| `app/DTOs/Core/ContextoUsuarioAutenticadoDto.php` | DTO inmutable puro sin dependencias de BD, Request o Sesión. Propiedades canónicas: `$idLogin`, `$idUsuario`, `$nombreLogin`, `$idPerfil`, `$idCompania`, `$idCargo`, `$idArea`. Propiedades legacy conservadas en modo lectura transparente. | `CI-02` |
| `app/Contracts/Services/ContextoUsuarioAutenticadoServiceInterface.php` | Contrato con métodos de dominio en español: `obtenerContextoActivo`, `resolverContextoObligatorio`, `establecerContexto`, `limpiarContexto`. | `CI-02` |
| `app/Services/Core/ContextoUsuarioAutenticadoService.php` | Implementación con una sola escritura a claves canónicas de `SessionKeys`. Variables de dominio `$idLogin`, `$idCompania`, `$idCargo`. Cero fallbacks ciegos. | `CI-02` |
| `app/Contracts/Repositories/mod_sistema/ContextoUsuarioAutenticadoRepositoryInterface.php` | Contrato de acceso atómico a asignaciones en PostgreSQL (`p_lgin`, `r_cmpnias_lgin`, `r_crgos_lgin`, `a_cmpnias`, `p_crgos`, `a_areas`). | `CI-02` |
| `app/Repositories/mod_sistema/ContextoUsuarioAutenticadoRepository.php` | Consulta atómica parametrizada única con parámetros de dominio. | `CI-02` |
| `app/Providers/AppServiceProvider.php` | Enlace canónico de interfaces en el contenedor de servicios de Laravel. | `ARCH-01` |
| `app/Http/Controllers/AuthController.php` | Inyección de `ContextoUsuarioAutenticadoServiceInterface`; uso exclusivo de `SessionKeys::COMPANIA_ID`, `SessionKeys::CARGO_ID`, `SessionKeys::AREA_NOMBRE`. | `CI-04` |
| `app/Services/Core/SecurityCore.php` | Uso de `SessionKeys::CARGO_ID`. | `CI-04` |
| `app/Policies/mod_gene/TramiteInternoPolicy.php` | Refactorización de métodos en inglés a acciones canónicas de negocio en español (`recepcionar`, `derivar`, `asignar`, `enviar`, `finalizar`, `devolver`, `reiterar`, `reemplazarFirmado`, `descargar`, `consultarHistorico`, `ver`, `verDocumentoHistorico`, `verContabilidad`). | `CI-04` / Nomenclatura |
| `app/Services/mod_gene/*` | Consumidores de `TramiteInternoPolicy` actualizados a nombres canónicos en español (`CorreoInternoTransaccionalService`, `ReemplazarDocumentoFirmadoService`, `DescargaSeguraTramiteInternoService`, `DescargaLoteTramiteInternoService`, `ConsolidadoExpedienteService`, `IngresarTramiteService`). | `CI-04` |
| `app/Http/Controllers/mod_gm/mod_tram_exte/*` | Controladores de trámite externo migrados a constantes `SessionKeys` (`ArchivoExpedienteController`, `CorreoExternoController`, `V2ListaReporteAreaCargoExternoController`, `ReporteExpedienteController`, `ListaReporAreaController`, `BuscaExpediController`). | `CI-04` |
| `app/Http/Controllers/mod_gene/mod_tram_inter/*` | Controladores de trámite interno migrados a constantes `SessionKeys` (`SecuencialTramiteController`, `ReporteCargoInternoController`, `BuscarTramiteController`, `CorreoInternoController`). | `CI-04` |
| `tests/Unit/TramiteInternoRemediacionP0Test.php` | Mock expectations actualizadas a métodos canónicos en español (47 pruebas pasando). | `TEST-01` |
| `tests/Unit/TramiteInternoDescargaSeguraTest.php` | Mock expectations de `download` actualizadas a `descargar` (5 pruebas pasando). | `TEST-01` |
| `tests/Unit/TramiteInternoArquitecturaMigradaTest.php` | Mock expectation de `viewHistory` actualizada a `consultarHistorico` (4 pruebas pasando). | `TEST-01` |
| `tests/Unit/TramiteInternoAutorizacionIdorTest.php` | Llamadas de policy actualizadas a `ver`, `consultarHistorico`, `descargar` (5 pruebas pasando). | `TEST-01` |
| `tests/Unit/TramiteExternoHttpWiringTest.php` | Mocks actualizados a `ContextoUsuarioAutenticadoRepositoryInterface` y `ContextoUsuarioAutenticadoDto` (10 pruebas pasando). | `TEST-01` |
| `tests/Unit/AutenticacionCompaniaCargoDinamicoTest.php` | Mocks actualizados a `ContextoUsuarioAutenticadoRepositoryInterface` e inyección en `AuthController` (11 pruebas pasando sin tocar BD). | `TEST-01` |

---

### Categoría B: Inventariado para Próximas Fases (No Modificar en Esta Fase)

Los siguientes módulos no forman parte del alcance de la presente fase (Auth, Core, Trámite Interno y Trámite Externo). Se inventarían con sus referencias exactas para su posterior migración progresiva:

| Módulo / Archivo | Líneas Afectadas | Expresión / Clave Encontrada | Clasificación | Plan para Fase Futura |
| :--- | :--- | :--- | :--- | :--- |
| `app/Services/mod_gp/omaped/OmapedBridge.php` | 58, 59 | `session('sigtram_area_nombre')`, `session('sigtram_cargo_nombre')` | `SECURITY_IDENTITY` | Migrar cuando se refactorice el módulo OMAPED; aislar llamadas superglobales `$_SESSION`. |
| `app/Services/mod_gp/omaped/clase_personas_stub.php` | 61 | `session(SessionKeys::USER_COMPANY, 0)` | `CONTRACT_ALIAS` | Inyectar `ContextoUsuarioAutenticadoDto`. |
| `app/Http/Controllers/mod_gp/omaped/OmapedController.php` | 29, 41, 42, 415 | `session()->get('sigtram_cargo_id')`, `session()->get('sigtram_area_nombre')`, `session()->get('sigtram_cargo_nombre')` | `SECURITY_IDENTITY` | Migrar a `SessionKeys::CARGO_ID`, `AREA_NOMBRE`, `CARGO_NOMBRE`. |
| `app/Http/Controllers/mod_a/sgl/VerRequerimientoTesoreriaController.php` | 35, 152 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar durante la refactorización del módulo SGL/Requerimientos. |
| `app/Http/Controllers/mod_a/sgl/VerRequerimientoPresupuestoController.php` | 39, 313 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar durante la refactorización del módulo SGL/Requerimientos. |
| `app/Http/Controllers/mod_a/sgl/VerRequerimientoLogisticaController.php` | 39, 419 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar durante la refactorización del módulo SGL/Requerimientos. |
| `app/Http/Controllers/mod_a/sgl/VerRequerimientoLocadorController.php` | 42, 326 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar durante la refactorización del módulo SGL/Requerimientos. |
| `app/Http/Controllers/mod_a/sgl/VerRequerimientoFinanzasController.php` | 35, 143 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar durante la refactorización del módulo SGL/Requerimientos. |
| `app/Http/Controllers/mod_a/sgl/BuscarRequerimientoPagoController.php` | 38, 201 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar durante la refactorización del módulo SGL/Requerimientos. |
| `app/Services/mod_a/sgl/VerRequerimientoPresupuestoService.php` | 40, 209, 352, 492, 708, 777 | `session(SessionKeys::CARGO_ID)`, `session(SessionKeys::USER_COMPANY)` | `SECURITY_IDENTITY` | Inyectar `ContextoUsuarioAutenticadoDto`. |
| `app/Services/mod_a/sgl/VerRequerimientoLogisticaService.php` | 44, 66, 296, 762, 816, 945, 1551, 1651 | `session(SessionKeys::CARGO_ID)`, `session(SessionKeys::USER_COMPANY)` | `SECURITY_IDENTITY` | Inyectar `ContextoUsuarioAutenticadoDto`. |
| `app/Services/mod_a/sgl/VerRequerimientoLocadorService.php` | 29 | `session(SessionKeys::CARGO_ID)` | `SECURITY_IDENTITY` | Inyectar `ContextoUsuarioAutenticadoDto`. |
| `app/Services/mod_a/sgl/VerRequerimientoFinanzasService.php` | 242 | `session(SessionKeys::CARGO_ID)` | `SECURITY_IDENTITY` | Inyectar `ContextoUsuarioAutenticadoDto`. |
| `app/Services/mod_a/sgl/PagoProveedoresIndexService.php` | 36, 390, 473, 776, 1282, 1394 | `session(SessionKeys::CARGO_ID)`, `session(SessionKeys::USER_COMPANY)` | `SECURITY_IDENTITY` | Inyectar `ContextoUsuarioAutenticadoDto`. |
| `app/Services/mod_a/sgl/FichaSeleccionSeaceService.php` | 478 | `session(SessionKeys::USER_COMPANY)` | `SECURITY_IDENTITY` | Inyectar `ContextoUsuarioAutenticadoDto`. |
| `app/Http/Controllers/mod_ti/helpdesk/TicketsController.php` | 32, 55 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de Mesa de Ayuda / TI. |
| `app/Http/Controllers/mod_ti/helpdesk/IngresarServiciosController.php` | 32, 89, 256 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de Mesa de Ayuda / TI. |
| `app/Http/Controllers/mod_ti/helpdesk/IngresarBandejaController.php` | 47, 106 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de Mesa de Ayuda / TI. |
| `app/Http/Controllers/mod_ti/helpdesk/ConsultasController.php` | 37, 78 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de Mesa de Ayuda / TI. |
| `app/Http/Controllers/mod_ti/reporte_publicidad/ReportePublicidadController.php` | 36, 193 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de TI. |
| `app/Http/Controllers/mod_ti/reporte_publicidad_2026/ReportePublicidad2026Controller.php` | 36, 195 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de TI. |
| `app/Http/Controllers/mod_ti/firma_email/FirmaEmailController.php` | 36, 183 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de TI. |
| `app/Http/Controllers/mod_ti/dispositivo_seg/DispositivoSegStreamController.php` | 28 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de TI. |
| `app/Http/Controllers/mod_ti/dispositivo_seg/DispositivoSegController.php` | 32, 50, 69, 388 | `session()->get('sigtram_cargo_id')` | `SECURITY_IDENTITY` | Migrar en la fase de TI. |
| `app/Http/Controllers/mod_promesa/PromesaController.php` | 38, 51, 52, 67, 91, 115, 135, 155, 516, 554 | `session()->get('sigtram_cargo_id')`, `session()->get('sigtram_area_nombre')`, `session()->get('sigtram_cargo_nombre')` | `SECURITY_IDENTITY` | Migrar en la fase de Promesa. |

---

### Categoría C: Bloqueado por Falta de Evidencia

Actualmente existen **0 elementos bloqueados**. Todos los elementos del alcance de esta fase (Core/Auth, Trámite Interno y Trámite Externo) disponen de evidencia forense verificada en código fuente, contratos de base de datos validados y pruebas unitarias/feature ejecutables.
