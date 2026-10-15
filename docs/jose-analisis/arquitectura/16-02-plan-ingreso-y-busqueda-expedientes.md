# SPEC 16-02 — Ingreso y búsqueda de expedientes

## 1. IngresoExpedienteController

Archivo: `app/Http/Controllers/mod_gm/mod_tram_exte/IngresoExpedienteController.php`.

Crear:

- `app/Contracts/Repositories/mod_gm/IngresoExpedienteRepositoryInterface.php`
- `app/Repositories/mod_gm/IngresoExpedienteRepository.php`

Inyectar el contrato por constructor. `IngresoExpedienteService` sigue siendo responsable del registro; el Repository nuevo concentra lecturas auxiliares y, en una fase posterior de esta misma SPEC, las operaciones de persistencia que todavía existan en dicho Service.

### Métodos mínimos del contrato

```php
public function obtenerDatosFormularioIngreso(int $companiaId): array;
public function buscarUsuarioNatural(string $documento, int $companiaId): ?array;
public function buscarUsuarioJuridico(string $ruc, int $companiaId): ?array;
public function sugerirUsuariosJuridicos(string $termino, int $companiaId, int $limite = 20): array;
public function listarSubgerencias(int $gerenciaId, int $companiaId): array;
public function listarTramitesPorServicio(int $servicioId, int $companiaId): array;
public function listarRequisitosPorTramite(int $tramiteId, int $companiaId): array;
public function buscarTramitesTupa(string $termino, int $companiaId, int $limite = 20): array;
public function listarExpedientes(array $filtros, int $companiaId, int $pagina, int $porPagina): array;
public function obtenerVoucherExpediente(int $expedienteId, int $companiaId): ?array;
```

Si un método actual requiere área/cargo/usuario además de compañía, agregar esos escalares o un DTO de contexto; nunca leer sesión dentro del Repository.

### Mapeo obligatorio

| Método Controller | Método Repository | Consulta/tablas actuales a trasladar |
|---|---|---|
| `index` | `obtenerDatosFormularioIngreso` | `a_dcmntos_iden`, `a_tpo_trmtes` y catálogos que hoy arma el método |
| `buscarUsuario` | `buscarUsuarioNatural` | queries actuales de usuario natural, dirección y contacto |
| `buscarJuridico` | `buscarUsuarioJuridico` | queries actuales de persona jurídica |
| `sugerirJuridicos` | `sugerirUsuariosJuridicos` | sugerencia actual con límite y filtros |
| `listarSubgerencias` | `listarSubgerencias` | `a_areas`/relaciones actuales |
| `listarTramites` | `listarTramitesPorServicio` | `a_docmntos_trmtes` y joins actuales |
| `listarRequisitos` | `listarRequisitosPorTramite` | requisitos actuales |
| `buscarTramitesTupa` | `buscarTramitesTupa` | `a_docmntos_trmtes` y joins actuales |
| `lista` | `listarExpedientes` | `p_dto_trmtes`, tipos, usuarios y relaciones existentes |
| `voucher` | `obtenerVoucherExpediente` | lectura usada por la vista voucher |

`contarFolios` es lógica de archivo/PDF y queda sin cambios en esta SPEC salvo que contenga consulta DB. No trasladar filesystem al Repository.

## 2. BuscaExpediController

Archivo: `app/Http/Controllers/mod_gm/mod_tram_exte/BuscaExpediController.php`.

Crear:

- `app/Contracts/Repositories/mod_gm/BuscaExpedienteRepositoryInterface.php`
- `app/Repositories/mod_gm/BuscaExpedienteRepository.php`
- `app/Services/mod_gm/mod_tram_exte/BuscaExpedienteService.php`

### División obligatoria

- Controller: Request/FormRequest, autorización, llamada al Service, Response/View.
- Service: reglas de actualización, anexo, cita virtual y generación; abre la transacción.
- Repository: todas las lecturas/escrituras en tablas.
- GestorAdjuntos actual: operaciones físicas de archivo; no rediseñar sus rutas en esta fase.

### Casos de uso y métodos Repository

| Caso Controller | Service | Métodos Repository mínimos |
|---|---|---|
| `show`, `detallePopup`, `imprimir` | lecturas simples o `obtenerDetalle` | `buscarExpediente`, `obtenerDetalleExpediente`, `obtenerFichaExpediente` |
| `update` | `actualizarExpediente` | `obtenerExpedienteParaActualizar`, `actualizarDatosExpediente` |
| `agreAnexoForm` | `obtenerDatosAnexo` | `obtenerDatosParaAnexo` |
| `agreAnexoStore` | `agregarAnexo` | `obtenerMovimientoParaAnexo`, `insertarAnexo`, `actualizarMovimientoDespuesDeAnexo`, `registrarSeguimiento` según consulta actual |
| `listaVaucher`, `voucherAnexo` | lectura | `listarVouchers`, `obtenerVoucherAnexo` |
| `citaVirtualForm` | lectura | `obtenerDatosCitaVirtual` |
| `citaVirtualStore` | `registrarCitaVirtual` | `insertarCitaVirtual` y actualizaciones actuales |
| `generarExpedienteForm` | lectura | `obtenerDatosGeneracionExpediente` |
| `generarExpedienteStore` | `generarExpedienteDesdeObservado` | métodos de lock/correlativo/año, inserción en `p_dto_trmtes`, `r_trmtes_dcmntos`, copia de `r_dtos_rqtos`, actualización del origen y `p_sgmto_log` |
| helper `cargarFicha` | lectura | `obtenerFichaExpediente` |
| helper `cargarPersona` | lectura | `obtenerPersonaNatural` / `obtenerPersonaJuridica` |

### Tablas cuyo comportamiento se congela antes de mover

Como mínimo, las consultas actuales afectan:

- `p_dto_trmtes`;
- `r_trmtes_dcmntos`;
- `r_dtos_rqtos`;
- `p_sgmto_log`;
- `a_areas`;
- `p_crgos`;
- `a_tpo_trmtes`;
- `a_anios`;
- `h_usrios_crreo`;
- `p_usrios`, `h_usrios_ntral`, `h_usrios_jrid`;
- `a_docmntos_trmtes` y servicios relacionados usados por `cargarFicha`.

No usar esta lista para reconstruir consultas. Copiar la semántica exacta de cada bloque actual: columnas, aliases, joins, `where`, orden, `in_esta`, secuencias/correlativos y valores por defecto.

## 3. Transacciones

Reemplazar `DB::beginTransaction/commit/rollBack` del Controller por métodos Service con `DB::transaction`. Dentro del callback, toda operación DB se efectúa a través del Repository inyectado. No capturar una excepción para hacer commit parcial.

Para correlativos:

1. conservar el mecanismo actual hasta caracterizarlo;
2. aplicar lock donde ya exista o donde la prueba de concurrencia demuestre necesidad;
3. no cambiar a `max + 1`, secuencia o tabla semilla sin una decisión separada;
4. exigir que la actualización de origen afecte el número esperado de filas antes de insertar el siguiente movimiento.

## 4. Requests

Esta fase no debe perpetuar `Request` genérico en escrituras. Crear FormRequests con nombres exactos:

- `ActualizarExpedienteRequest`
- `AgregarAnexoExpedienteRequest`
- `RegistrarCitaVirtualRequest`
- `GenerarExpedienteObservadoRequest`

Ubicación: `app/Http/Requests/mod_gm/mod_tram_exte/`.

Las reglas deben copiar el payload real de las vistas/JS; no renombrar campos unilateralmente. Las lecturas pueden conservar `Request` si no existe validación compleja, pero deben usar `$request->validate()` o FormRequest según riesgo.

## 5. Pruebas

- pruebas unitarias de ambos Repositories con conexión/Builder mockeado;
- pruebas unitarias del Service para commit lógico, rollback, cero filas y excepción intermedia;
- wiring de Controller con mocks de contrato/Service;
- prueba arquitectónica que recorra el archivo completo y prohíba DB en ambos Controllers;
- prueba de que `AppServiceProvider` resuelve ambos contratos nuevos;
- no hacer Feature tests que intenten conectarse a PostgreSQL.

