# SPEC 16-00 — Convergencia real a Repository

## Estado

`READY_FOR_IMPLEMENTATION — REPO-00…REPO-06`

## 1. Decisión arquitectónica

SIGTRAM3 conservará este flujo único:

```text
Route → FormRequest → Controller → Service/caso de uso → Repository → PostgreSQL
```

- El **Controller** valida el contrato HTTP, obtiene el contexto autenticado, autoriza y transforma la respuesta.
- El **Service** contiene reglas, secuencia del caso de uso y límites transaccionales.
- El **Repository** es el único componente que conoce tablas, joins, Query Builder, SQL, locks y persistencia.
- El Service puede abrir `DB::transaction(...)`, pero no puede usar `DB::table`, `select`, `insert`, `update`, `delete` ni SQL.
- El Controller no puede importar ni usar `DB`, `ConnectionInterface` o Query Builder.

No se elimina el Service para “usar directamente el Repository”: eso trasladaría negocio al Controller. Tampoco se mantiene SQL dentro del Service: eso dejaría un Repository nominal y dos fuentes de verdad.

## 2. Alcance exacto

### Incluido

1. Eliminar acceso directo a datos de:
   - `app/Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php`
   - `app/Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php`
   - `app/Http/Controllers/mod_gm/mod_tram_exte/IngresoExpedienteController.php`
   - `app/Http/Controllers/mod_gm/mod_tram_exte/BuscaExpediController.php`
   - `app/Services/mod_gene/CorreoInternoTransaccionalService.php`
   - `app/Services/mod_gm/mod_tram_exte/TramiteExternoService.php`
2. Reutilizar y ampliar los contratos existentes de trámite interno y externo.
3. Crear contratos separados para ingreso y búsqueda de expedientes; no convertir `TramiteExternoRepository` en un God Repository.
4. Mantener exactamente rutas, verbos HTTP, parámetros, claves JSON, vistas, estados, seguridad y semántica legacy ya migrada.
5. Agregar pruebas de caracterización y arquitectura sin conexión real a PostgreSQL.

### Excluido y expresamente pospuesto

- arquitectura física de adjuntos nuevos/legacy;
- descarga dual desde almacenamiento nuevo y legado;
- nombres físicos de archivos;
- publicación QR y consulta pública;
- migraciones, DDL, DML o cambios de esquema;
- rediseño Blade/CSS/JS;
- cambios funcionales de bandejas, estados, histórico, participantes o auditoría.

Si durante esta tarea aparece un defecto de almacenamiento o QR, se documenta en la entrega y no se corrige aquí.

## 3. Archivos canónicos

### Repositories existentes que se amplían

- `app/Contracts/Repositories/mod_gene/TramiteInternoRepositoryInterface.php`
- `app/Repositories/mod_gene/TramiteInternoRepository.php`
- `app/Contracts/Repositories/mod_gm/TramiteExternoRepositoryInterface.php`
- `app/Repositories/mod_gm/TramiteExternoRepository.php`

### Repositories nuevos obligatorios

- `app/Contracts/Repositories/mod_gm/IngresoExpedienteRepositoryInterface.php`
- `app/Repositories/mod_gm/IngresoExpedienteRepository.php`
- `app/Contracts/Repositories/mod_gm/BuscaExpedienteRepositoryInterface.php`
- `app/Repositories/mod_gm/BuscaExpedienteRepository.php`

### Service nuevo para el controlador monolítico de búsqueda

- `app/Services/mod_gm/mod_tram_exte/BuscaExpedienteService.php`

### Binding

Registrar los dos contratos nuevos en `app/Providers/AppServiceProvider.php`. No usar `app()->make()` dentro de métodos ni instanciar implementaciones con `new`.

## 4. Nombres que no deben variar

Usar exactamente:

- `IngresoExpedienteRepositoryInterface` / `IngresoExpedienteRepository`;
- `BuscaExpedienteRepositoryInterface` / `BuscaExpedienteRepository`;
- `BuscaExpedienteService`;
- los contratos existentes `TramiteInternoRepositoryInterface` y `TramiteExternoRepositoryInterface`.

No crear variantes como `ExpediRepository`, `CorreoRepository`, `RepositoryService`, `DataService`, `HelperDb` o `BaseRepository`.

## 5. Orden obligatorio

```text
REPO-00 caracterización y baseline
  ↓
REPO-01 lecturas directas de CorreoInternoController
  ↓
REPO-02 lecturas directas de CorreoExternoController
  ↓
REPO-03 IngresoExpedienteController
  ↓
REPO-04 BuscaExpediController
  ↓
REPO-05 SQL de Services transaccionales
  ↓
REPO-06 certificación y reporte
```

No iniciar una fase si la anterior rompe pruebas. Si faltan tokens/contexto, detenerse al final de una fase verde y reportar el siguiente ID; nunca dejar una operación parcialmente migrada.

## 6. Guardrails

1. Rama obligatoria: `codex/back`.
2. Preservar el árbol sucio; prohibido reset, checkout, restore, clean o reformateo masivo.
3. Prohibido commit, push, pull, merge, rebase y PR.
4. Prohibido conectarse a PostgreSQL, ejecutar `artisan migrate`, seed, tinker o SQL.
5. No alterar rutas, nombres de rutas, verbos, payloads o respuestas públicas.
6. No inventar tablas, columnas, estados ni relaciones. La consulta actual es la fuente de caracterización.
7. Antes de mover cada consulta, crear o actualizar una prueba que congele parámetros, resultado y comportamiento de error.
8. Mover la consulta, no reescribirla “más limpia” en el mismo paso. Optimización posterior sólo con evidencia equivalente.
9. Todos los métodos públicos nuevos: `declare(strict_types=1)`, tipos y PHPDoc conforme a `.agents/AGENTS.md`.
10. Prohibido devolver `Builder` desde los Repositories nuevos. La excepción heredada `construirQueryBandejaBase()` no debe propagarse como patrón.

## 7. Definition of Done

- Los cuatro controladores del alcance no contienen `DB::`, `->table(` ni SQL.
- Los dos Services transaccionales no contienen acceso a tablas; `DB::transaction` es la única dependencia DB admitida en Service.
- No se modificó el comportamiento HTTP observable.
- Todos los contratos tienen implementación y binding.
- Los Repositories reciben parámetros de dominio/contexto; no reciben `Request`.
- Las escrituras mantienen lock, orden, conteo exacto de filas, idempotencia y rollback actuales.
- Las pruebas unitarias, JS, build y `git diff --check` permanecen verdes.
- La entrega declara qué validación manual y Feature tests con BD siguen pendientes; no afirma producción sin ellos.

