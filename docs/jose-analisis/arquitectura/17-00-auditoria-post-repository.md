# SPEC 17-00 — Auditoría posterior a la convergencia Repository

## Estado

`PARTIAL — NO APTO PARA REORGANIZACIÓN HASTA CERRAR P0`

## 1. Evidencia ejecutada

- `php artisan test --testsuite=Unit`: **462 passed, 2563 assertions**.
- pruebas dirigidas Repository/Service: **93 passed, 401 assertions**.
- `npm test`: **28 passed**.
- `npm run build`: aprobado; permanecen dos avisos de imágenes resueltas en runtime.
- `git diff --check`: aprobado, con aviso CRLF/LF preexistente en `AuthController.php`.
- PHPStan scoped de Controllers, Services, Repositories y Contracts internos/externos: **48 errores**.
- No se ejecutó conexión a PostgreSQL ni se alteró la base de datos.

Las pruebas verdes no certifican el refactor: los tests arquitectónicos actuales no incluyen todos los Services ni validan todos los scopes de compañía.

## 2. Hallazgos P0

### R17-P0-01 — IngresoExpedienteService sigue siendo una capa de persistencia

Archivo: `app/Services/IngresoExpedienteService.php`.

El Service todavía usa `DB::table`, `Model::where`, `Model::create`, `Model::update`, `findOrFail` y `max`. Esto contradice SPEC 16: la transacción puede permanecer en el Service, pero lecturas, escrituras, correlativos y locks pertenecen al Repository.

Además:

- no declara `strict_types=1`;
- está fuera de `mod_gm/mod_tram_exte`;
- utiliza `Area::where(...)` sin importar una clase `Area`, por lo que PHP resuelve `App\Services\Area`, clase inexistente;
- guarda adjuntos en el disco público; no cambiarlo todavía porque almacenamiento dual pertenece a otra SPEC, pero mantenerlo registrado como deuda.

**Cierre:** inyectar `IngresoExpedienteRepositoryInterface`, trasladar toda persistencia y resolver el área observada mediante método Repository. El Service conserva reglas y `DB::transaction`.

### R17-P0-02 — Mutaciones BuscaExpedi sin aislamiento obligatorio por compañía

Archivos:

- `app/Services/mod_gm/mod_tram_exte/BuscaExpedienteService.php`;
- `app/Contracts/Repositories/mod_gm/BuscaExpedienteRepositoryInterface.php`;
- `app/Repositories/mod_gm/BuscaExpedienteRepository.php`;
- `app/Http/Controllers/mod_gm/mod_tram_exte/BuscaExpediController.php`.

Problemas:

1. varias firmas usan `int $companiaId = 0`;
2. el Repository aplica compañía únicamente con `when($companiaId > 0, ...)`, convirtiendo el aislamiento en opcional;
3. `actualizarExpediente`, `obtenerDatosAnexo`, `agregarAnexo` y `registrarCitaVirtual` no reciben compañía;
4. `obtenerExpedienteParaActualizar`, `obtenerMovimientoParaAnexo`, `desactivarMovimientosActivos` y `obtenerExpedienteVirtualConMovimiento` consultan por ID global;
5. un ID válido de otra compañía puede alcanzar lecturas o mutaciones.

**Cierre:** eliminar todos los valores por defecto; exigir `companiaId > 0` en Controller/Service/Repository; incorporar el scope en la propia consulta, no autorizar después.

### R17-P0-03 — Concurrencia y filas afectadas incompletas en BuscaExpedi

`desactivarMovimientosActivos()` no filtra `in_esta = '1'`, no filtra compañía y no exige número esperado de filas. `obtenerMovimientoParaAnexo()` tampoco bloquea. `resolverCorrelativoAnio()` calcula `max + 1` sin lock, permitiendo correlativos duplicados concurrentes.

**Cierre:** dentro de transacción:

1. obtener expediente/movimientos autorizados con `lockForUpdate`;
2. conservar múltiples ramas activas cuando el flujo lo admita;
3. desactivar solamente las ramas activas esperadas y comprobar filas afectadas;
4. proteger el correlativo usando el mecanismo legacy existente bajo lock o la secuencia existente; no inventar esquema;
5. abortar y hacer rollback ante cero filas, lote parcial o carrera.

### R17-P0-04 — Notificación externa incompatible con el contrato Repository

En `TramiteExternoService::finalizar` se llama:

```php
$this->repository->obtenerContactoNotificacionExpediente($expId, $contexto->companiaId);
```

pero el contrato acepta un parámetro y retorna `?array`. Después el Service usa `$contacto?->correo_h` y `$contacto?->correo_u` como si fuera objeto.

**Cierre:** el contrato debe recibir también `companiaId`, la consulta debe filtrarla y el Service debe consumir claves tipadas (`$contacto['correo_h']`, `$contacto['correo_u']`) o un DTO consistente. Agregar pruebas de correo explícito, fallback a `correo_h`, fallback a `correo_u` y contacto inexistente.

## 3. Hallazgos P1

### R17-P1-01 — Fugas de excepciones

`BuscaExpediController` y `IngresoExpedienteController` concatenan `$e->getMessage()` en respuestas 500. Sustituir por mensaje estable y correlación; registrar contexto sanitizado sin payload completo ni PII.

### R17-P1-02 — Auditoría silenciada

`BuscaExpedienteRepository::registrarSeguimiento()` captura cualquier excepción y continúa. Definir si el log es obligatorio. Para mutaciones de expediente, aplicar fail-closed conforme a las reglas de auditoría; no ocultar el fallo.

### R17-P1-03 — Pruebas arquitectónicas incompletas

`RepositoryBoundaryArchitectureTest` inspecciona Services seleccionados, pero omitió `IngresoExpedienteService` y acceso Eloquent estático. El guardrail debe rechazar:

- `DB::table` y `$db->table`;
- `Model::query/where/create/update/find/findOrFail` dentro de Services;
- imports de modelos de persistencia dentro de Services del módulo;
- compañía opcional en Repositories con datos segregados.

### R17-P1-04 — PHPStan no está verde

No crear baseline ni ignores. Corregir primero errores del alcance tocado. Los errores ajenos se reportan por archivo y se programan en su módulo.

## 4. Orden obligatorio de corrección

```text
R17-P0-04 contrato de notificación
R17-P0-02 scope compañía
R17-P0-03 locks y filas afectadas
R17-P0-01 persistencia de ingreso
R17-P1-01 errores seguros
R17-P1-02 auditoría
R17-P1-03 pruebas
PHPStan scoped verde
recién entonces reorganización física
```

