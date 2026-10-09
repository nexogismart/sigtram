# Diccionario de Datos Detallado — Base de Datos de SIGTRAM3

Este documento detalla todas las tablas, columnas, relaciones, restricciones e índices del esquema `sistema` del proyecto **SIGTRAM3**.

---

## Índice de Tablas

1.  **[r_prmsos_usrios](#1-sistemar_prmsos_usrios)**: Excepciones individuales de permisos de acceso por usuario.
2.  **[p_feriados](#2-sistemap_feriados)**: Catálogo de feriados oficiales para el cálculo de días hábiles.
3.  **[t_solicitudes_mod_historial](#3-sistemat_solicitudes_mod_historial)**: Peticiones de modificaciones del historial de expedientes.
4.  **[r_aprob_mod_historial](#4-sistemar_aprob_mod_historial)**: Aprobaciones requeridas por áreas para las modificaciones del historial.
5.  **[t_solicitudes_firma](#5-sistemat_solicitudes_firma)**: Solicitudes de firmas digitales para autorizaciones de documentos.
6.  **[h_pide_consltas](#6-sistemah_pide_consltas)**: Registro de auditoría histórica de consultas externas (PIDE/Decolecta).

---

## Detalle de Estructuras

### 1. `sistema.r_prmsos_usrios`
**Propósito:** Almacena excepciones individuales de permisos de acceso por usuario, sobrescribiendo los permisos asignados por defecto a su cargo en `r_prmsos`.

#### Columnas y Atributos:

| Columna | Tipo de Datos | Nulo | Por Defecto | Llave / Relación | Descripción y Lógica de Negocio |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `cdgo_prmso_usrios` | `serial` | NO | `nextval(...)` | **PK** | Identificador único autoincremental de la excepción. |
| `cdgo_lgin` | `integer` | NO | | **FK** | ID de credencial de acceso del usuario (Relación a `sistema.p_lgin` ON DELETE CASCADE). |
| `cdgo_prmso` | `integer` | NO | | **FK** | ID del permiso base asignado a su cargo (Relación a `sistema.r_prmsos` ON DELETE CASCADE). |
| `in_ver` | `bpchar(1)` | SÍ | `'1'` | | Permiso de Visualización: `'1'` (Habilitado), `'0'` (Deshabilitado). |
| `in_crea` | `bpchar(1)` | SÍ | `'1'` | | Permiso de Creación: `'1'` (Habilitado), `'0'` (Deshabilitado). |
| `in_edit` | `bpchar(1)` | SÍ | `'1'` | | Permiso de Edición: `'1'` (Habilitado), `'0'` (Deshabilitado). |
| `in_elim` | `bpchar(1)` | SÍ | `'1'` | | Permiso de Eliminación: `'1'` (Habilitado), `'0'` (Deshabilitado). |
| `in_esta` | `bpchar(1)` | SÍ | `'1'` | | Estado de la excepción: `'1'` (Activa), `'0'` (Inactiva/Suspendida). |
| `ins_usrios` | `integer` | SÍ | | | ID del usuario (`sistema.p_usrios`) que insertó el registro. |
| `fec_ins` | `timestamp` | SÍ | `now()` | | Fecha y hora en que se creó la excepción. |
| `upd_usrios` | `integer` | SÍ | | | ID del usuario (`sistema.p_usrios`) que modificó el registro. |
| `upd_fec` | `timestamp` | SÍ | | | Fecha y hora de la última modificación. |
| `del_usrios` | `integer` | SÍ | | | ID del usuario (`sistema.p_usrios`) que dio de baja (soft delete). |
| `del_fec` | `timestamp` | SÍ | | | Fecha y hora de la baja de la excepción. |

#### Restricciones (Constraints):
*   `r_prmsos_usrios_pkey`: Clave primaria sobre `cdgo_prmso_usrios`.
*   `r_prmsos_usrios_cdgo_lgin_fkey`: Clave foránea `cdgo_lgin` references `sistema.p_lgin(cdgo_lgin)` ON DELETE CASCADE.
*   `r_prmsos_usrios_cdgo_prmso_fkey`: Clave foránea `cdgo_prmso` references `sistema.r_prmsos(cdgo_prmso)` ON DELETE CASCADE.

#### Índices de Rendimiento:
*   `r_prmsos_usrios_cdgo_lgin_idx` (B-Tree) sobre `cdgo_lgin`.
*   `r_prmsos_usrios_cdgo_prmso_idx` (B-Tree) sobre `cdgo_prmso`.

---

### 2. `sistema.p_feriados`
**Propósito:** Catálogo de feriados oficiales no laborables para poder calcular dinámicamente el transcurso de plazos en días hábiles.

#### Columnas y Atributos:

| Columna | Tipo de Datos | Nulo | Por Defecto | Llave / Relación | Descripción y Lógica de Negocio |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `cdgo_feriado` | `serial` | NO | `nextval(...)` | **PK** | Identificador único autoincremental del feriado. |
| `fec_feriado` | `date` | NO | | **Unique** | Fecha del feriado (No se permiten duplicados de fecha). |
| `de_descripcion` | `varchar(200)`| NO | | | Nombre o motivo del feriado (ej: Navidad, Combate de Angamos). |
| `in_esta` | `bpchar(1)` | NO | `'1'` | | Estado del feriado: `'1'` (Vigente/Activo), `'0'` (Anulado/No vigente). |
| `cdgo_usrios_ins` | `integer` | NO | | | ID del usuario (`sistema.p_usrios`) que registró el feriado. |
| `fec_ins` | `timestamptz` | NO | `now()` | | Fecha y hora del registro del feriado. |
| `cdgo_usrios_upd` | `integer` | SÍ | | | ID del usuario que actualizó el feriado. |
| `fec_upd` | `timestamptz` | SÍ | | | Fecha y hora de la última modificación. |
| `cdgo_usrios_del` | `integer` | SÍ | | | ID del usuario que dio de baja (soft delete). |
| `fec_del` | `timestamptz` | SÍ | | | Fecha y hora de la baja. |

#### Restricciones (Constraints):
*   `p_feriados_pkey`: Clave primaria sobre `cdgo_feriado`.
*   `p_feriados_fec_feriado_key`: Restricción de valor único (`UNIQUE`) sobre la fecha `fec_feriado`.

---

### 3. `sistema.t_solicitudes_mod_historial`
**Propósito:** Peticiones de modificaciones físicas de registros de movimientos históricos de expedientes. Almacena las modificaciones planteadas en JSONB y exige un flujo de firma colectiva.

#### Columnas y Atributos:

| Columna | Tipo de Datos | Nulo | Por Defecto | Llave / Relación | Descripción y Lógica de Negocio |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `cdgo_solicitud_mod`| `bigserial` | NO | `nextval(...)` | **PK** | Identificador único autoincremental de la solicitud. |
| `cdgo_historial_origen`| `bigint` | NO | | | ID del movimiento a modificar (referencia a `sistema.h_expedientes_movimientos`). |
| `cdgo_usrio_solicitante`| `integer` | NO | | **FK** | ID del usuario municipal solicitante (Relación a `sistema.p_usrios`). |
| `valores_nuevos` | `jsonb` | NO | | | Objeto JSONB estructurado con los nombres de columnas y sus nuevos valores. |
| `archivos_nuevos` | `jsonb` | SÍ | | | Metadatos y rutas de almacenamiento de los nuevos documentos de sustento. |
| `estado_solicitud` | `bpchar(1)` | NO | `'P'` | | Estado del flujo: `'P'` (Pendiente), `'A'` (Aprobada), `'R'` (Rechazada). |
| `nu_dias_habiles_limite`|`integer`| SÍ | | | Plazo de días hábiles asignados para resolver la solicitud (NULL = Ilimitado). |
| `cdgo_usrios_ins` | `integer` | NO | | | ID del usuario que creó el registro (creador de la solicitud). |
| `fec_ins` | `timestamptz` | NO | `now()` | | Fecha y hora en que se ingresó la solicitud. |
| `cdgo_usrios_upd` | `integer` | SÍ | | | ID del usuario que realizó la última actualización. |
| `fec_upd` | `timestamptz` | SÍ | | | Fecha y hora de modificación. |
| `cdgo_usrios_del` | `integer` | SÍ | | | ID del usuario que eliminó de forma lógica la solicitud. |
| `fec_del` | `timestamptz` | SÍ | | | Fecha y hora de eliminación. |

#### Restricciones (Constraints):
*   `t_solicitudes_mod_historial_pkey`: Clave primaria sobre `cdgo_solicitud_mod`.
*   `t_solicitudes_mod_historial_cdgo_usrio_solicitante_fkey`: Clave foránea references `sistema.p_usrios(cdgo_usrios)`.
*   `chk_solicitud_mod_estado`: Valida que `estado_solicitud` sea únicamente `'P'`, `'A'` o `'R'`.

---

### 4. `sistema.r_aprob_mod_historial`
**Propósito:** Relación intermedia de firmas y aprobaciones que deben emitir las distintas oficinas municipales (`sistema.a_areas`) antes de aplicar la modificación solicitada.

#### Columnas y Atributos:

| Columna | Tipo de Datos | Nulo | Por Defecto | Llave / Relación | Descripción y Lógica de Negocio |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `cdgo_aprobacion` | `bigserial` | NO | `nextval(...)` | **PK** | Identificador único de la firma/aprobación de área. |
| `cdgo_solicitud_mod` | `bigint` | NO | | **FK** | ID de la solicitud de modificación (FK a `sistema.t_solicitudes_mod_historial` ON DELETE CASCADE). |
| `cdgo_area_aprobador`| `integer` | NO | | **FK** | ID del área municipal autorizadora (FK a `sistema.a_areas`). |
| `estado_aprobacion` | `bpchar(1)` | NO | `'P'` | | Estado de la aprobación del área: `'P'` (Pendiente), `'A'` (Aprobado), `'R'` (Rechazado). |
| `cdgo_usrio_aprobador`| `integer` | SÍ | | **FK** | ID del usuario físico que firma por el área (FK a `sistema.p_usrios`). |
| `fec_aprobacion` | `timestamptz` | SÍ | | | Fecha y hora en que el área firmó la aprobación. |
| `cdgo_usrios_ins` | `integer` | NO | | | ID del usuario que registró la regla de aprobación. |
| `fec_ins` | `timestamptz` | NO | `now()` | | Fecha y hora de creación de la regla. |
| `cdgo_usrios_upd` | `integer` | SÍ | | | ID del usuario que actualizó el estado de la aprobación. |
| `fec_upd` | `timestamptz` | SÍ | | | Fecha y hora de actualización. |
| `cdgo_usrios_del` | `integer` | SÍ | | | ID del usuario que eliminó el registro de aprobación. |
| `fec_del` | `timestamptz` | SÍ | | | Fecha y hora de baja lógica. |

#### Restricciones (Constraints):
*   `r_aprob_mod_historial_pkey`: Clave primaria sobre `cdgo_aprobacion`.
*   `r_aprob_mod_historial_cdgo_solicitud_mod_fkey`: Clave foránea references `sistema.t_solicitudes_mod_historial` ON DELETE CASCADE.
*   `r_aprob_mod_historial_cdgo_area_aprobador_fkey`: Clave foránea references `sistema.a_areas`.
*   `r_aprob_mod_historial_cdgo_usrio_aprobador_fkey`: Clave foránea references `sistema.p_usrios`.
*   `uk_solicitud_area`: Restricción única que impide duplicar aprobaciones de la misma área para una misma solicitud.
*   `chk_aprobacion_mod_estado`: Valida que `estado_aprobacion` sea únicamente `'P'`, `'A'` o `'R'`.

#### Índices de Rendimiento:
*   `idx_r_aprob_historial_solicitud` (B-Tree) sobre `cdgo_solicitud_mod`.

---

### 5. `sistema.t_solicitudes_firma`
**Propósito:** Solicitudes de firmas digitales para aprobaciones de borradores de documentos. Permite delegar la firma jerárquicamente a los jefes.

#### Columnas y Atributos:

| Columna | Tipo de Datos | Nulo | Por Defecto | Llave / Relación | Descripción y Lógica de Negocio |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `cdgo_solicitud_firma`| `bigserial`| NO | `nextval(...)` | **PK** | Identificador único autoincremental de la solicitud de firma. |
| `cdgo_documento` | `bigint` | NO | | | ID del documento borrador a firmar (referencia a `sistema.h_documentos`). |
| `cdgo_usrio_solicitante`|`integer` | NO | | **FK** | ID del usuario que solicita la firma (FK a `sistema.p_usrios`). |
| `cdgo_usrio_firmante` | `integer` | NO | | **FK** | ID del usuario jefe/autorizador que estampará la firma (FK a `sistema.p_usrios`). |
| `in_derivacion_automatica`|`boolean`| NO | `false` | | Si es `true`, al firmar el documento se derivará automáticamente al destinatario. |
| `estado_solicitud` | `bpchar(1)` | NO | `'P'` | | Estado: `'P'` (Pendiente), `'F'` (Firmado), `'R'` (Rechazado). |
| `de_observacion_rechazo`|`text` | SÍ | | | Texto opcional redactado por el firmante explicando la razón de un rechazo. |
| `nu_dias_habiles_limite`|`integer` | SÍ | | | Plazo de días hábiles máximo para estampar la firma. |
| `cdgo_usrios_ins` | `integer` | NO | | | ID del usuario que creó la solicitud de firma. |
| `fec_ins` | `timestamptz` | NO | `now()` | | Fecha y hora de creación de la solicitud. |
| `cdgo_usrios_upd` | `integer` | SÍ | | | ID del usuario que actualizó el estado de la firma. |
| `fec_upd` | `timestamptz` | SÍ | | | Fecha de actualización de la firma. |
| `cdgo_usrios_del` | `integer` | SÍ | | | ID del usuario que eliminó la solicitud de firma. |
| `fec_del` | `timestamptz` | SÍ | | | Fecha de eliminación. |

#### Restricciones (Constraints):
*   `t_solicitudes_firma_pkey`: Clave primaria sobre `cdgo_solicitud_firma`.
*   `t_solicitudes_firma_cdgo_usrio_solicitante_fkey`: Clave foránea references `sistema.p_usrios(cdgo_usrios)`.
*   `t_solicitudes_firma_cdgo_usrio_firmante_fkey`: Clave foránea references `sistema.p_usrios(cdgo_usrios)`.
*   `chk_evitar_autofirma`: Regla de validación que impide que el solicitante sea igual al firmante (`cdgo_usrio_solicitante <> cdgo_usrio_firmante`).
*   `chk_solicitud_firma_estado`: Valida que `estado_solicitud` sea únicamente `'P'`, `'F'` o `'R'`.

#### Índices de Rendimiento:
*   `idx_sol_firma_solicitante` (B-Tree) sobre `cdgo_usrio_solicitante`.
*   `idx_sol_firma_firmante` (B-Tree) sobre `cdgo_usrio_firmante`.

---

### 6. `sistema.h_pide_consltas`
**Propósito:** Almacena el histórico y la auditoría física de todas las consultas realizadas a través de la interfaz de la PIDE (RENIEC, SUNAT) o Decolecta, registrando datos de control de seguridad e IP.

#### Columnas y Atributos:

| Columna | Tipo de Datos | Nulo | Por Defecto | Llave / Relación | Descripción y Lógica de Negocio |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `cdgo_pi_cons` | `serial` | NO | `nextval(...)` | **PK** | Identificador único autoincremental de la auditoría. |
| `nu_documento` | `varchar(20)` | NO | | | Número de DNI o RUC consultado externamente. |
| `tp_documento` | `varchar(10)` | NO | | | Tipo de documento consultado (`DNI` o `RUC`). |
| `cdgo_lgin` | `integer` | SÍ | | **FK** | ID de credencial de acceso del usuario que consulta (Relación a `sistema.p_lgin` ON DELETE SET NULL). |
| `ip_terminal` | `varchar(45)` | SÍ | | | Dirección IP del cliente municipal que realizó la consulta. |
| `fuente` | `varchar(50)` | SÍ | | | Nombre del servicio proveedor consultado (`PIDE`, `API DECOLECTA`, `MOCK`). |
| `pide_user_used` | `varchar(50)` | SÍ | | | Identificador o token del operador de conexión utilizado. |
| `txt_dispositivo`| `text` | SÍ | | | User-Agent o dispositivo desde el cual se originó la petición. |
| `fcha_cnsulta` | `timestamp` | SÍ | `now()` | | Fecha y hora exacta de la consulta. |
| `exito` | `boolean` | SÍ | `true` | | Indica si la consulta externa se procesó con éxito (`true`) o falló/no se encontró (`false`). |

#### Restricciones (Constraints):
*   `h_pide_consltas_pkey`: Clave primaria sobre `cdgo_pi_cons`.
*   `fk_pide_usuario`: Clave foránea `cdgo_lgin` references `sistema.p_lgin(cdgo_lgin)` ON DELETE SET NULL.
