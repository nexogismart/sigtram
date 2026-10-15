# Inventario Forense Inicial (Línea Base Previa a Remediación)

> **Fecha:** 2026-10-15  
> **Proyecto:** `c:\laragon\www\migracion_sigtram\sigtram_v3`  
> **Auditor/Arquitecto:** Lead Laravel Architect & Auditor Forense  

---

## 1. Contexto de Identidad y Autenticación Legacy

- **Archivos de definición identificados:**
  1. `app/DTOs/mod_gm/ContextoFuncionarioDto.php` (contiene propiedades duales y lógica permisiva)
  2. `app/DTOs/mod_gene/ContextoFuncionarioInternoDto.php` (accede directamente a `Auth::user()`, `Request`, sesión `sigtram_cargo_id` y ejecuta `DB::table('sistema.p_crgos')`)
- **Consumidores en `app/` (21 archivos encontrados):**
  - `app/Services/mod_gm/ExportarTramiteExternoExcelService.php`
  - `app/Services/mod_gm/mod_tram_exte/ConsolidacionExpedienteService.php`
  - `app/Services/mod_gm/mod_tram_exte/TramiteExternoService.php`
  - `app/Services/mod_gm/mod_tram_exte/DocumentoInternoDesdeExpedienteExternoService.php`
  - `app/Services/mod_gene/CorreoInternoTransaccionalService.php`
  - `app/Services/mod_gene/DescargaLoteTramiteInternoService.php`
  - `app/Services/mod_gene/ReemplazarDocumentoFirmadoService.php`
  - `app/Services/mod_gene/DescargaSeguraTramiteInternoService.php`
  - `app/Services/mod_gene/ConsolidadoExpedienteService.php`
  - `app/Repositories/mod_gm/TramiteExternoRepository.php`
  - `app/Repositories/mod_gene/TramiteInternoRepository.php`
  - `app/Policies/mod_gm/TramiteExternoPolicy.php`
  - `app/Policies/mod_gene/TramiteInternoPolicy.php`
  - `app/Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php`
  - `app/Http/Controllers/mod_gene/mod_tram_inter/IngresarTramiteController.php`
  - `app/Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php`
  - `app/Http/Controllers/mod_gene/mod_tram_inter/BuscarTramiteController.php`
  - `app/Contracts/Repositories/mod_gm/TramiteExternoRepositoryInterface.php`
  - `app/Contracts/Repositories/mod_gene/TramiteInternoRepositoryInterface.php`
  - `app/DTOs/mod_gm/ContextoFuncionarioDto.php`
  - `app/DTOs/mod_gene/ContextoFuncionarioInternoDto.php`
- **Consumidores en `tests/` (31 archivos encontrados).**
- **Propiedades duplicadas en `ContextoUsuarioAutenticadoDto`:**
  - `loginId`, `usuarioId`, `loginNombre`, `perfilId`, `companiaId`, `cargoId`, `areaId`, `companiaNombre`, `cargoNombre`, `areaNombre`, `login`.
  - Deben retirarse tras actualizar todos los consumidores a `$idLogin`, `$idUsuario`, `$nombreLogin`, `$idPerfil`, `$idCompania`, `$idCargo`, `$idArea`, `$nombreCompania`, `$nombreCargo`, `$nombreArea`.

---

## 2. Accesos Directos a Base de Datos en Controladores del Alcance

Se identificaron **154 accesos directos** a BD (`DB::table`, `DB::select`, `DB::beginTransaction`, etc.) distribuidos en **21 archivos** de controladores de Trámite Interno y Trámite Externo:

### Trámite Interno (7 archivos):
1. `app/Http/Controllers/mod_gene/mod_tram_inter/V2ListaReporteAreaCargoController.php` (~30 accesos directos a BD)
2. `app/Http/Controllers/mod_gene/mod_tram_inter/SecuencialTramiteController.php` (~8 accesos directos a BD)
3. `app/Http/Controllers/mod_gene/mod_tram_inter/ReporteTramiteController.php` (~18 accesos directos a BD)
4. `app/Http/Controllers/mod_gene/mod_tram_inter/ReporteCargoInternoController.php` (~12 accesos directos a BD)
5. `app/Http/Controllers/mod_gene/mod_tram_inter/BuscarTramiteController.php` (~8 accesos directos a BD)
6. `app/Http/Controllers/mod_gene/mod_tram_inter/IngresarTramiteController.php` (delega parcialmente, persisten accesos auxiliares)
7. `app/Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php` (delega al repo, limpia de SQL directo)

### Trámite Externo (14 archivos incluyendo Concerns):
1. `app/Http/Controllers/mod_gm/mod_tram_exte/V2ListaReporteAreaCargoExternoController.php` (~32 accesos directos a BD)
2. `app/Http/Controllers/mod_gm/mod_tram_exte/SegiVerExpediController.php` (~14 accesos directos a BD)
3. `app/Http/Controllers/mod_gm/mod_tram_exte/ReporteExpedienteController.php` (~20 accesos directos a BD)
4. `app/Http/Controllers/mod_gm/mod_tram_exte/RedirigirExpedienteController.php` (~10 accesos directos a BD)
5. `app/Http/Controllers/mod_gm/mod_tram_exte/ListaReporteController.php` (~8 accesos directos a BD)
6. `app/Http/Controllers/mod_gm/mod_tram_exte/ListaReporAreaController.php` (~6 accesos directos a BD)
7. `app/Http/Controllers/mod_gm/mod_tram_exte/ListaExpediTodoController.php` (~8 accesos directos a BD)
8. `app/Http/Controllers/mod_gm/mod_tram_exte/ArchivoExpedienteController.php` (~12 accesos directos a BD)
9. `app/Http/Controllers/mod_gm/mod_tram_exte/AdjuntarTramiteController.php` (~6 accesos directos a BD)
10. `app/Http/Controllers/mod_gm/mod_tram_exte/NotificarPlataformaController.php` (~4 accesos directos a BD)
11. `app/Http/Controllers/mod_gm/mod_tram_exte/BuscaExpediController.php` (~5 accesos directos a BD)
12. `app/Http/Controllers/mod_gm/mod_tram_exte/IngresoExpedienteController.php` (delega a repo, accesos puntuales)
13. `app/Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php` (delega)
14. `app/Http/Controllers/mod_gm/mod_tram_exte/Concerns/` (consultas compartidas)

---

## 3. Mutaciones con `Request` Genérico sin FormRequest

- `app/Http/Controllers/mod_gm/mod_tram_exte/AdjuntarTramiteController.php::upload(Request $request)`
- `app/Http/Controllers/mod_gm/mod_tram_exte/RedirigirExpedienteController.php::derivar(Request $request)`
- Métodos en `ArchivoExpedienteController.php` que reciben parámetros sin validación estricta en FormRequest de mutación.

---

## 4. Credenciales Expuestas en Valores por Defecto (`config/` y `.env.example`)

1. `config/database.php`:
   - Conexiones con contraseñas por defecto no vacías: `predio`, `camara_seg`, `mdpp_gis`, `reniec`, `asterisk`, `satmunxp`, `siga`.
2. `config/pide.php`:
   - DNI y credenciales reales en `credentials_pool` y `sunarp`.
3. `config/whatsapp.php`:
   - Token por defecto `'bheB4JTJVawX1ajhOTqDugZwTuW1TGTQ'`.
4. `config/camara_visor.php`:
   - Usuario y contraseña por defecto `'admin' / 'admin'`.
5. `.env.example`:
   - Credenciales comentadas y `APP_DEBUG=true`.

---

## 5. Vistas Blade con `<style>`, Scripts Complejos y Eventos Inline

Se detectaron bloques inline en:
- `resources/views/mod_gene/mod_tram_inter/ingresar-tramite.blade.php`
- `resources/views/mod_gene/mod_tram_inter/v2_lista_reporte_area_cargo.blade.php`
- `resources/views/mod_gene/mod_tram_inter/ver_secuencial_area.blade.php`
- `resources/views/mod_gene/mod_tram_inter/reporte-tramite.blade.php`
- `resources/views/mod_gene/mod_tram_inter/reporte-cargo-interno.blade.php`
- `resources/views/mod_gm/mod_tram_exte/index.blade.php`
- `resources/views/mod_gm/mod_tram_exte/v2_lista_reporte_area_cargo.blade.php`
- `resources/views/mod_gm/mod_tram_exte/segi_verexpedi.blade.php`
- `resources/views/mod_gm/mod_tram_exte/segi_archivo.blade.php`
- `resources/views/mod_gm/mod_tram_exte/segi_adjuntar.blade.php`
- `resources/views/mod_gm/mod_tram_exte/reporte-expediente.blade.php`
