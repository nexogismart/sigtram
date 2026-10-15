# SPEC 17-01 — Estructura modular destino

## 1. Problema actual

Las capas no están espejadas:

- Controllers y Requests ya usan `mod_gene/mod_tram_inter` y `mod_gm/mod_tram_exte`;
- Services internos están directamente en `mod_gene`;
- Repositories y Contracts están directamente en `mod_gene` o `mod_gm`;
- algunos Services externos están en la raíz `app/Services`;
- otros están en `mod_gm` y otros en `mod_gm/mod_tram_exte`.

Esto no impide a Laravel resolver clases, pero sí genera colisiones y dificulta migrar nuevos módulos legacy.

## 2. Regla de organización

Cada submódulo funcional tendrá el mismo segmento en todas sus capas:

```text
app/
├── Http/Controllers/{area}/{submodulo}/
├── Http/Requests/{area}/{submodulo}/
├── Services/{area}/{submodulo}/
├── Repositories/{area}/{submodulo}/
├── Contracts/Repositories/{area}/{submodulo}/
├── DTOs/{area}/{submodulo}/
├── Enums/{area}/{submodulo}/
└── Policies/{area}/{submodulo}/
```

El namespace debe coincidir exactamente con la ruta PSR-4.

Los componentes realmente compartidos permanecen en `Core` o `Support`; no se duplican por módulo.

## 3. Destino Trámite Interno

Segmento canónico: `mod_gene/mod_tram_inter`.

### Services a mover

Desde `app/Services/mod_gene/` hacia `app/Services/mod_gene/mod_tram_inter/`:

- `ConsolidadoExpedienteService.php`
- `CorreoInternoTransaccionalService.php`
- `DescargaLoteTramiteInternoService.php`
- `DescargaSeguraTramiteInternoService.php`
- `IdempotenciaTramiteInternoService.php`
- `IngresarTramiteService.php`
- `MaquinaEstadosTramiteInterno.php`
- `ReemplazarDocumentoFirmadoService.php`
- `ValidadorPagoProveedoresTipo64Service.php`

### Repositories y Contracts a mover

```text
app/Repositories/mod_gene/IngresarTramiteRepository.php
  → app/Repositories/mod_gene/mod_tram_inter/IngresarTramiteRepository.php

app/Repositories/mod_gene/TramiteInternoRepository.php
  → app/Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php

app/Contracts/Repositories/mod_gene/IngresarTramiteRepositoryInterface.php
  → app/Contracts/Repositories/mod_gene/mod_tram_inter/IngresarTramiteRepositoryInterface.php

app/Contracts/Repositories/mod_gene/TramiteInternoRepositoryInterface.php
  → app/Contracts/Repositories/mod_gene/mod_tram_inter/TramiteInternoRepositoryInterface.php
```

### DTOs, Enums y Policies

Mover los específicos del módulo al mismo segmento:

- `ContextoFuncionarioInternoDto.php`
- `TramiteInternoMovimientoDto.php`
- `EstadoTramiteInterno.php`
- `TramiteInternoPolicy.php`

## 4. Destino Trámite Externo

Segmento canónico: `mod_gm/mod_tram_exte`.

### Services a mover o consolidar

- `app/Services/IngresoExpedienteService.php`
  → `app/Services/mod_gm/mod_tram_exte/IngresoExpedienteService.php`
- `app/Services/NotificarPlataformaService.php`
  → `app/Services/mod_gm/mod_tram_exte/NotificarPlataformaService.php`
- `app/Services/mod_gm/ExportarTramiteExternoExcelService.php`
  → `app/Services/mod_gm/mod_tram_exte/ExportarTramiteExternoExcelService.php`
- conservar bajo el destino los Services que ya están allí: búsqueda, consolidación, idempotencia, máquina de estados y transacciones.

`OpenSpoutExcelWriter` es infraestructura reutilizable. Mover a:

```text
app/Services/Support/Spreadsheet/OpenSpoutExcelWriter.php
```

y mover sus contratos de fábrica/writer al segmento `app/Contracts/Services/Support/Spreadsheet/` si ningún otro módulo depende del namespace anterior sin actualizar.

### Repositories y Contracts

Mover:

```text
app/Repositories/mod_gm/{BuscaExpediente,IngresoExpediente,TramiteExterno}Repository.php
  → app/Repositories/mod_gm/mod_tram_exte/

app/Contracts/Repositories/mod_gm/{BuscaExpediente,IngresoExpediente,TramiteExterno}RepositoryInterface.php
  → app/Contracts/Repositories/mod_gm/mod_tram_exte/
```

### DTOs, Enums y Policies

Mover a `mod_gm/mod_tram_exte`:

- `ContextoFuncionarioDto.php`
- `ExpedienteMovimientoDto.php`
- `FilaBandejaExternaDto.php`
- `BandejaTramiteExterno.php`
- `EstadoTramiteExterno.php`
- `TramiteExternoPolicy.php`

## 5. Qué no debe colocarse dentro del módulo

- `GestorAdjuntos`: `Services/Support`, compartido por interno y externo.
- `ArchivoAdjuntoDto`, `ParticipanteUDto`, `ResumenUDto`: `DTOs/Support` si ambos módulos los usan.
- `SecurityCore`, `BdCore`, notificaciones y PIDE: `Services/Core`.
- autorización de menú/sistema: `mod_sistema`.

## 6. Regla para módulos futuros

Al migrar una carpeta legacy como:

```text
Presentacion/mod_gm/nuevo_modulo
```

crear desde el inicio:

```text
Controllers/mod_gm/nuevo_modulo
Requests/mod_gm/nuevo_modulo
Services/mod_gm/nuevo_modulo
Repositories/mod_gm/nuevo_modulo
Contracts/Repositories/mod_gm/nuevo_modulo
DTOs/mod_gm/nuevo_modulo
Policies/mod_gm/nuevo_modulo
```

No colocar clases nuevas en la raíz de `Services`, `Repositories` o `Contracts/Repositories`.

