# SPEC 16-01 — Consultas directas en Correo Interno y Externo

## 1. CorreoInternoController

Archivo origen: `app/Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php`.

Ampliar `TramiteInternoRepositoryInterface` y `TramiteInternoRepository` con métodos descriptivos. Las firmas finales pueden usar DTOs/arrays tipados, pero los nombres y responsabilidades deben conservarse:

| Acción/endpoint | Acceso directo actual | Método Repository requerido | Tablas que la consulta actual caracteriza |
|---|---|---|---|
| `index` — GET `correo_interno` | resuelve cargo | `obtenerCargoPorId(int $cargoId): ?object` | `p_crgos` |
| `detalleDocumento` — GET `detalle_documento/{id}` | resuelve movimiento histórico | reutilizar `obtenerMovimientoHistoricoAutorizado(...)`; no crear consulta paralela | `r_trmtes_ntrnos`, documento asociado |
| `voucher` — GET `voucher/{id}` | cabecera/detalle y fallback por identificador | `obtenerVoucherMovimientoInterno(int $movimientoId, ContextoFuncionarioInternoDto $contexto): ?array` | query exacta hoy ubicada en el método; preservar joins/aliases |
| `comprobantesPago` — GET `comprobantes_pago` | consulta comprobantes | `obtenerComprobantesPago(array $criterios, ContextoFuncionarioInternoDto $contexto): array` | `caja.p_cmprbntes_pgo` y joins existentes |
| `datosTransaccionales` | carga cargo | reutilizar `obtenerCargoPorId(...)` antes de delegar al Service | `p_crgos` |
| `correlativoInterno` | carga cargo | reutilizar `obtenerCargoPorId(...)` | `p_crgos` |
| `hojaRuta` — GET `hoja_ruta/{id}` | documento + verificación de movimientos | `obtenerDocumentoParaHojaRuta(int $documentoId, ContextoFuncionarioInternoDto $contexto): ?object` | `p_dtos_ntrnos`, `r_trmtes_ntrnos` |
| `imprimirBandeja` | cargo y área | `obtenerCabeceraImpresion(ContextoFuncionarioInternoDto $contexto): array` | `p_crgos`, `a_areas` |

### Reglas específicas

1. `detalleDocumento`, `voucher` y `hojaRuta` deben resolver registros históricos con `in_esta = 0` o `1` cuando el contrato funcional lo exige; no añadir filtro de activo.
2. La autorización por compañía/contexto debe estar dentro de la consulta Repository, no como consulta global seguida de un `if` en Controller.
3. No duplicar `obtenerMovimientoHistoricoPorId`, `obtenerMovimientoHistoricoAutorizado` y una tercera variante equivalente. Consolidar el alias si las pruebas demuestran identidad.
4. `caja.p_cmprbntes_pgo` permanece en el Repository interno aunque use otro esquema, porque sirve al caso de uso interno.
5. El Controller sólo debe: recibir FormRequest/Request ya existente, construir contexto, autorizar, llamar Repository/Service y responder.

## 2. CorreoExternoController

Archivo origen: `app/Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php`.

Ampliar `TramiteExternoRepositoryInterface` y `TramiteExternoRepository`:

| Acción/endpoint | Acceso directo actual | Método Repository requerido | Tablas caracterizadas |
|---|---|---|---|
| `descargarAdjunto` — GET `segi_expedi/descargar/{id}` | obtiene nombre/ruta del adjunto | `obtenerAdjuntoAutorizadoPorMovimiento(int $movimientoId, int $companiaId): ?array` | consulta actual del movimiento/documento |
| `hojaRuta` — GET `segi_expedi/hoja-ruta/{id}` | carga expediente y movimientos | `obtenerDatosHojaRuta(int $expedienteId, int $companiaId): ?array` | `p_dto_trmtes`, `r_trmtes_dcmntos` y joins actuales |
| `obtenerContexto` | cargo, área, compañía | `obtenerContextoFuncionario(int $cargoId, int $usuarioId, int $companiaId): ?ContextoFuncionarioDto` o datos escalares equivalentes | `p_crgos`, `a_areas` y consulta actual |

### Reglas específicas

1. No modificar aún la resolución física del archivo de `descargarAdjunto`; sólo trasladar la lectura de metadatos. El almacenamiento dual será otra SPEC.
2. No cambiar `descargarTodos`, `ConsolidacionExpedienteService` ni formatos ZIP/PDF en esta fase.
3. `obtenerContexto` puede seguir siendo helper privado, pero no puede ejecutar Query Builder.
4. Reutilizar `obtenerMovimientosHistoricosPorExpediente` cuando cubra el caso. No crear métodos por endpoint si la semántica es idéntica.

## 3. Pruebas obligatorias

Crear/actualizar pruebas en `tests/Unit/`:

- `RepositoryBoundaryArchitectureTest.php`: inspecciona el cuerpo completo de ambos Controllers y falla ante `DB::`, `ConnectionInterface`, `->table(`, `->select(` o SQL literal.
- extender `TramiteInternoRepositoryTest.php` para cargo, voucher, comprobantes, hoja de ruta y cabecera de impresión;
- extender `TramiteExternoRepositoryTest.php` para adjunto autorizado, hoja de ruta y contexto;
- actualizar wiring tests para demostrar que los Controllers llaman contratos, no implementaciones.

Los tests no deben limitarse a buscar que exista un nombre de método; deben instanciar dobles y verificar argumentos, contexto, respuesta y errores 404/403 seguros.

## 4. Criterio de cierre

```powershell
rg -n "DB::|->table\(" app/Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php app/Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php
```

Debe producir cero coincidencias. La ausencia de coincidencias no basta: las pruebas conductuales también deben pasar.

