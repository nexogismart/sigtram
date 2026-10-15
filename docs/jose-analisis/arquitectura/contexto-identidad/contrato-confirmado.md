# Contrato Confirmado de Esquema, Identidad y Vocabulario Canónico (CI-01)

> **Fecha de Certificación:** 2026-10-14  
> **Auditoría Forense:** Lead Laravel Architect & Forensic Auditor  
> **Herramienta de Verificación:** MCP de sólo lectura `sigtram_lectura` (PostgreSQL `db_sigtram_251212`)  
> **Modificaciones ejecutadas en BD:** CERO (sólo consultas SELECT e information_schema)

---

## 1. Esquema Real de Base de Datos Confirmado con MCP

### 1.1 `sistema.p_lgin` (Cuenta de acceso / Credencial)
- **`cdgo_lgin`**: `integer` (NOT NULL, PK). Identificador canónico del registro de login.
- **`cdgo_usrios`**: `integer` (NOT NULL, FK a `sistema.p_usrios.cdgo_usrios`). Identificador del ciudadano/funcionario.
- **`cdgo_prfle`**: `integer` (NOT NULL). Código de perfil de seguridad.
- **`de_lgin`**: `varchar(50)` (Nullable). Nombre alfanumérico de login.
- **`de_pass`**: `varchar` (Nullable). Hash de contraseña (SHA1 legacy / bcrypt).
- **`in_esta`**: `char(1)` (Nullable). Estado (`'1'` activo, `'0'` inactivo).
- **`cdgo_crgo`**: `integer` (Nullable). Cargo primario/predeterminado legacy (obsoleto; la relación real es `r_crgos_lgin`).
- **HALLAZGO FORENSE:** `sistema.p_lgin` **NO** contiene la columna `cdgo_cmpnia`.

### 1.2 `sistema.p_usrios` (Funcionario / Persona Natural)
- **`cdgo_usrios`**: `integer` (NOT NULL, PK). Identificador canónico de persona/usuario.
- **`nu_docu`**: `varchar(20)` (Nullable). DNI o documento de identidad.
- **`no_crto`**: `varchar` (Nullable). Nombre corto o denominación.
- **`in_esta`**: `char(1)` (Nullable). Estado (`'1'` activo, `'0'` inactivo).
- **`cdgo_cmpnia`**: `integer` (Nullable). Referencia registral histórica; **NO** define la autorización de acceso multitenant activa.

### 1.3 `sistema.a_cmpnias` (Compañía / Entidad Municipal)
- **`cdgo_cmpnia`**: `integer` (NOT NULL, PK). Identificador canónico de compañía.
- **`de_cmpnia`**: `varchar(150)` (Nullable). Razón social completa.
- **`dc_cmpnia`**: `varchar` (Nullable). Denominación corta / sigla.
- **`in_esta`**: `char(1)` (Nullable). Estado (`'1'` activo, `'0'` inactivo).
- **HALLAZGO FORENSE:** La tabla se denomina **`a_cmpnias`** (la tabla `p_cmpnias` no existe).
- **HALLAZGO DE DATOS:**
  - `cdgo_cmpnia = 1`: `in_esta = '0'` (INACTIVA). Fallback legacy `?? 1` asignaba a empresa inactiva.
  - `cdgo_cmpnia = 2`: `in_esta = '1'` (ACTIVA, "MUNICIPALIDAD DE PUENTE PIEDRA").
  - `cdgo_cmpnia = 3`: `in_esta = '1'` (ACTIVA, "PALACIO CULTURA").

### 1.4 `sistema.r_cmpnias_lgin` (Relación Login ↔ Compañía)
- **`cdgo_cmpnia_lgin`**: `integer` (NOT NULL, PK).
- **`cdgo_lgin`**: `integer` (NOT NULL, FK a `p_lgin`).
- **`cdgo_cmpnia`**: `integer` (NOT NULL, FK a `a_cmpnias`).
- **`in_esta`**: `integer` / `char(1)` (Nullable). Estado de la autorización (`1` activo).

### 1.5 `sistema.p_crgos` (Cargo Institucional)
- **`cdgo_crgo`**: `integer` (NOT NULL, PK).
- **`cdgo_area`**: `integer` (NOT NULL, FK a `a_areas`).
- **`de_crgo`**: `varchar(150)` (Nullable). Denominación del cargo.
- **`dc_crgo`**: `varchar` (Nullable). Siglas del cargo.
- **`in_esta`**: `char(1)` (Nullable). Estado (`'1'` activo).
- **HALLAZGO FORENSE:** `sistema.p_crgos` **NO** contiene la columna `cdgo_cmpnia`. La compañía se determina exclusivamente por el área asociada.

### 1.6 `sistema.r_crgos_lgin` (Relación Login ↔ Cargo)
- **`cdgo_crgo_lgin`**: `integer` (NOT NULL, PK).
- **`cdgo_lgin`**: `integer` (NOT NULL, FK a `p_lgin`).
- **`cdgo_crgo`**: `integer` (NOT NULL, FK a `p_crgos`).
- **`in_esta`**: `char(1)` (Nullable). Estado (`'1'` activo).
- **`in_crgo_prmro`**: `integer` (Nullable). Indicador de cargo principal.

### 1.7 `sistema.a_areas` (Área Orgánica)
- **`cdgo_area`**: `integer` (NOT NULL, PK).
- **`cdgo_cmpnia`**: `integer` (Nullable, FK a `a_cmpnias`). **Vínculo institucional con la compañía.**
- **`de_area`**: `varchar(150)` (Nullable). Nombre del área.
- **`dc_area`**: `varchar` (Nullable). Siglas del área.
- **`in_esta`**: `char(1)` (Nullable). Estado (`'1'` activo).

### 1.8 `sistema.r_prmsos` y `sistema.r_prmsos_usrios` (Permisos y Excepciones)
- **`sistema.r_prmsos`**: `cdgo_prmso` (PK), `cdgo_prfle` (FK), `cdgo_cmpnia` (FK), `cdgo_rle` (FK rol/opción), `cdgo_crgo` (FK opcional).
- **`sistema.r_prmsos_usrios`**: `cdgo_prmso_usrios` (PK), `cdgo_lgin` (FK), `cdgo_rle` (FK), `in_ver`, `in_crea`, `in_edit`, `in_elim`, `in_esta`.

---

## 2. Consultas Ejecutadas en MCP (Solo Lectura)

```sql
-- 1. Inspección de columnas y tipos en esquema sistema
SELECT table_schema, table_name, column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_schema = 'sistema'
  AND table_name IN ('p_lgin', 'p_usrios', 'r_cmpnias_lgin', 'a_cmpnias', 'r_crgos_lgin', 'p_crgos', 'a_areas', 'r_prmsos', 'r_prmsos_usrios')
ORDER BY table_name, ordinal_position;

-- 2. Estado de compañías registradas
SELECT cdgo_cmpnia, de_cmpnia, dc_cmpnia, in_esta
FROM sistema.a_cmpnias
ORDER BY cdgo_cmpnia;
```

---

## 3. Matriz de Vocabulario e Identidad Canónica

| Concepto de Dominio | Fuente BD Canónica | Tipo de Dato | Clave Canónica de Sesión | Término Prohibido / Alias Erróneo |
| :--- | :--- | :--- | :--- | :--- |
| **Login ID** | `sistema.p_lgin.cdgo_lgin` | `int` | `SessionKeys::LOGIN_ID` (`'usCdgoLgin'`) | `usId` (llamado erróneamente `USER_ID`), `usuarioId` conteniendo login |
| **Usuario ID** | `sistema.p_usrios.cdgo_usrios` | `int` | `SessionKeys::USUARIO_ID` (`'usId'`) | `$user->id`, `$row->id`, mezclar con login |
| **Nombre Login** | `sistema.p_lgin.de_lgin` | `string` | `SessionKeys::LOGIN_NOMBRE` | `de_lgin` expuesto en controladores |
| **Perfil ID** | `sistema.p_lgin.cdgo_prfle` | `int` | `SessionKeys::PERFIL_ID` | `cdgo_prfle` en sesión sin tipar |
| **Compañía ID** | `sistema.a_cmpnias.cdgo_cmpnia` | `int` | `SessionKeys::COMPANIA_ID` (`'usComp'`) | `sigtram_compania_id`, `company_id`, `?? 1`, `?? 2` |
| **Nombre Compañía** | `sistema.a_cmpnias.de_cmpnia` | `string` | `SessionKeys::COMPANIA_NOMBRE` | `usNomComp`, `sigtram_compania_nombre` |
| **Cargo ID** | `sistema.p_crgos.cdgo_crgo` | `int` | `SessionKeys::CARGO_ID` (`'usIdsCargo'`) | `sigtram_cargo_id` |
| **Nombre Cargo** | `sistema.p_crgos.de_crgo` | `string` | `SessionKeys::CARGO_NOMBRE` | `sigtram_cargo_nombre` |
| **Área ID** | `sistema.a_areas.cdgo_area` | `int` | `SessionKeys::AREA_ID` (`'usIdsArea'`) | `sigtram_area_id` |
| **Nombre Área** | `sistema.a_areas.de_area` | `string` | `SessionKeys::AREA_NOMBRE` | `sigtram_area_nombre` |

---

## 4. Matriz de Inventario y Clasificación de Aliases

| Símbolo / Expresión | Origen / Ubicación | Clasificación | Destino Arquitectónico |
| :--- | :--- | :--- | :--- |
| `SessionKeys::USER_COMPANY` | `app/Support/SessionKeys.php` | Clave de sesión canónica | Migrar a alias canónico `COMPANIA_ID = 'usComp'` |
| `'usComp'` | Legacy PHP nativo | Alias legacy en cookie/sesión | Confinado a subyacente de `COMPANIA_ID` en `SessionKeys` y Bridge |
| `'sigtram_compania_id'` | Sesión v3 anterior | Clave redundante | Eliminar en escritura; purgar en invalidación |
| `['company_id']`, `->company_id` | DTOs y Requests | CONTRACT_ALIAS | Eliminar de DTOs internos; normalizar en FormRequest |
| `['id']`, `->id` como compañía | DTOs | SECURITY_IDENTITY | Prohibido; rechazar con 0 |
| `Auth::user()->cdgo_cmpnia` | Fallback en Controllers/Services | SECURITY_IDENTITY | Prohibido; usar sesión/DTO validado |
| `?? 1` / `?? '1'` | Repositories/Services | HARD_CODED_BUSINESS (Empresa inactiva) | Prohibido; fail-closed |
| `?? 2` / `?? '2'` | Stubs (`clase_personas_stub.php`) | HARD_CODED_BUSINESS | Prohibido; recibir contexto tipado |
| `?? $companiaId` en repositorios | Repositories SQL rows | CONTRACT_ALIAS / MASKING | Prohibido; validar `cdgo_cmpnia` explícito |
| `SessionKeys::USER_ID = 'usId'` | `app/Support/SessionKeys.php` | Semántica errónea (almacena login) | Rediseñar a `LOGIN_ID` y `USUARIO_ID` |
| `'sigtram_cargo_id'` | Sesión v3 anterior | Alias legacy | Confinar a `LegacySessionContextBridge` |
| `'sigtram_area_id'` | Sesión v3 anterior | Alias legacy | Confinar a `LegacySessionContextBridge` |
| `fromRequest(Request $request)` con DB | `ContextoFuncionarioInternoDto.php` | Violación arquitectónica | Retirar DB del DTO; delegar a `ContextoFuncionarioService` |
