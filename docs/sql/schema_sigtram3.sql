-- =============================================================================
-- Script de Base de Datos PostgreSQL
-- Sistema SIGTRAM3 — Roles, Permisos y Flujos de Aprobación Jerárquicos
-- 
-- Descripción: Script consolidado de cambios de esquema en sistema SIGTRAM3.
--              Incluye gestión de permisos de roles y flujos de aprobación.
-- Compatible con: PostgreSQL 12+
-- Codificación: UTF-8
-- Esquema: sistema
-- =============================================================================
ALTER TABLE sistema.p_dtos_ntrnos ALTER COLUMN de_urls_adjnto TYPE varchar(1000);
ALTER TABLE sistema.r_trmtes_ntrnos ALTER COLUMN de_urls_agrdo TYPE varchar(1000);


SET search_path TO sistema, public;

-- =============================================================================
-- PARTE 1: ROLES Y PERMISOS (EXCEPCIONES DE ACCESO)
-- =============================================================================

-- 1.1 Agregar columna in_esta a la tabla base r_prmsos (si no existe)
ALTER TABLE sistema.r_prmsos ADD COLUMN IF NOT EXISTS in_esta bpchar(1) DEFAULT '1'::bpchar NULL;
COMMENT ON COLUMN sistema.r_prmsos.in_esta IS 'Estado de la relación de permiso (1=Activo, 0=Inactivo)';

-- 1.2 Recrear la tabla de excepciones r_prmsos_usrios
DROP TABLE IF EXISTS sistema.r_prmsos_usrios CASCADE;

CREATE TABLE sistema.r_prmsos_usrios (
    cdgo_prmso_usrios SERIAL PRIMARY KEY,
    cdgo_lgin INT4 NOT NULL REFERENCES sistema.p_lgin(cdgo_lgin) ON DELETE CASCADE,
    cdgo_prmso INT4 NOT NULL REFERENCES sistema.r_prmsos(cdgo_prmso) ON DELETE CASCADE,
    in_ver bpchar(1) DEFAULT '1'::bpchar NULL,
    in_crea bpchar(1) DEFAULT '1'::bpchar NULL,
    in_edit bpchar(1) DEFAULT '1'::bpchar NULL,
    in_elim bpchar(1) DEFAULT '1'::bpchar NULL,
    in_esta bpchar(1) DEFAULT '1'::bpchar NULL,
    -- Columnas de Auditoría
    ins_usrios INT4 NULL,
    fec_ins TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    upd_usrios INT4 NULL,
    upd_fec TIMESTAMP NULL,
    del_usrios INT4 NULL,
    del_fec TIMESTAMP NULL
);

CREATE INDEX r_prmsos_usrios_cdgo_lgin_idx ON sistema.r_prmsos_usrios(cdgo_lgin);
CREATE INDEX r_prmsos_usrios_cdgo_prmso_idx ON sistema.r_prmsos_usrios(cdgo_prmso);

-- Comentarios de la tabla de excepciones
COMMENT ON TABLE sistema.r_prmsos_usrios IS 'Tabla de excepciones individuales de permisos de acceso por usuario (sobrescribe los permisos base del perfil)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.cdgo_prmso_usrios IS 'Identificador único autoincremental de la excepción de permiso';
COMMENT ON COLUMN sistema.r_prmsos_usrios.cdgo_lgin IS 'FK al login de usuario asociado (sistema.p_lgin)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.cdgo_prmso IS 'FK al permiso base asignado al cargo (sistema.r_prmsos)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.in_ver IS 'Permiso para visualizar/listar (1=Habilitado, 0=Deshabilitado)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.in_crea IS 'Permiso para insertar/crear nuevos registros (1=Habilitado, 0=Deshabilitado)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.in_edit IS 'Permiso para actualizar/editar registros existentes (1=Habilitado, 0=Deshabilitado)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.in_elim IS 'Permiso para eliminar/dar de baja registros (1=Habilitado, 0=Deshabilitado)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.in_esta IS 'Estado del registro de excepción (1=Activo, 0=Inactivo/Suspendido)';
COMMENT ON COLUMN sistema.r_prmsos_usrios.ins_usrios IS 'Código del usuario (cdgo_usrios) que registró la excepción';
COMMENT ON COLUMN sistema.r_prmsos_usrios.fec_ins IS 'Fecha y hora de registro de la excepción';
COMMENT ON COLUMN sistema.r_prmsos_usrios.upd_usrios IS 'Código del usuario (cdgo_usrios) que actualizó la excepción';
COMMENT ON COLUMN sistema.r_prmsos_usrios.upd_fec IS 'Fecha y hora de la última modificación';
COMMENT ON COLUMN sistema.r_prmsos_usrios.del_usrios IS 'Código del usuario (cdgo_usrios) que eliminó de forma lógica la excepción';
COMMENT ON COLUMN sistema.r_prmsos_usrios.del_fec IS 'Fecha y hora de la eliminación lógica';

-- =============================================================================
-- PARTE 2: FLUJOS DE APROBACIÓN Y FERIADOS
-- =============================================================================

-- 2.1 TABLA: p_feriados
CREATE TABLE IF NOT EXISTS sistema.p_feriados (
    cdgo_feriado SERIAL PRIMARY KEY,
    fec_feriado DATE NOT NULL UNIQUE,
    de_descripcion VARCHAR(200) NOT NULL,
    in_esta bpchar(1) NOT NULL DEFAULT '1',
    -- Campos de Auditoría
    cdgo_usrios_ins INTEGER NOT NULL,
    fec_ins TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    cdgo_usrios_upd INTEGER NULL,
    fec_upd TIMESTAMP WITH TIME ZONE NULL,
    cdgo_usrios_del INTEGER NULL,
    fec_del TIMESTAMP WITH TIME ZONE NULL
);

-- Comentarios de la tabla feriados
COMMENT ON TABLE sistema.p_feriados IS 'Catálogo de feriados nacionales y locales oficiales para el cálculo de plazos en días hábiles';
COMMENT ON COLUMN sistema.p_feriados.cdgo_feriado IS 'Identificador único autoincremental del feriado';
COMMENT ON COLUMN sistema.p_feriados.fec_feriado IS 'Fecha calendario del día feriado (Única)';
COMMENT ON COLUMN sistema.p_feriados.de_descripcion IS 'Descripción o motivo del feriado (ej. Navidad, Aniversario)';
COMMENT ON COLUMN sistema.p_feriados.in_esta IS 'Estado de vigencia del feriado (1=Vigente, 0=Anulado)';
COMMENT ON COLUMN sistema.p_feriados.cdgo_usrios_ins IS 'Código del usuario (cdgo_usrios) que registró el feriado';
COMMENT ON COLUMN sistema.p_feriados.fec_ins IS 'Fecha y hora de inserción';
COMMENT ON COLUMN sistema.p_feriados.cdgo_usrios_upd IS 'Código del usuario (cdgo_usrios) que actualizó el feriado';
COMMENT ON COLUMN sistema.p_feriados.fec_upd IS 'Fecha y hora de última actualización';
COMMENT ON COLUMN sistema.p_feriados.cdgo_usrios_del IS 'Código del usuario (cdgo_usrios) que dio de baja de forma lógica el feriado';
COMMENT ON COLUMN sistema.p_feriados.fec_del IS 'Fecha y hora de baja lógica';

-- 2.2 TABLA: t_solicitudes_mod_historial
CREATE TABLE IF NOT EXISTS sistema.t_solicitudes_mod_historial (
    cdgo_solicitud_mod BIGSERIAL PRIMARY KEY,
    cdgo_historial_origen BIGINT NOT NULL, -- Asume relación con sistema.h_expedientes_movimientos
    cdgo_usrio_solicitante INTEGER NOT NULL REFERENCES sistema.p_usrios(cdgo_usrios),
    valores_nuevos JSONB NOT NULL,
    archivos_nuevos JSONB NULL,
    estado_solicitud bpchar(1) NOT NULL DEFAULT 'P',
    nu_dias_habiles_limite INTEGER NULL,
    -- Campos de Auditoría
    cdgo_usrios_ins INTEGER NOT NULL,
    fec_ins TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    cdgo_usrios_upd INTEGER NULL,
    fec_upd TIMESTAMP WITH TIME ZONE NULL,
    cdgo_usrios_del INTEGER NULL,
    fec_del TIMESTAMP WITH TIME ZONE NULL,
    CONSTRAINT chk_solicitud_mod_estado CHECK (estado_solicitud IN ('P', 'A', 'R'))
);

-- Comentarios de la tabla solicitudes
COMMENT ON TABLE sistema.t_solicitudes_mod_historial IS 'Solicitudes de modificación de movimientos históricos de expedientes sujetas a aprobación de áreas';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.cdgo_solicitud_mod IS 'Identificador único de la solicitud de modificación';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.cdgo_historial_origen IS 'Código del registro de movimiento histórico a modificar (sistema.h_expedientes_movimientos)';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.cdgo_usrio_solicitante IS 'Código del usuario que solicita la modificación (sistema.p_usrios)';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.valores_nuevos IS 'Objeto JSONB con los campos y sus respectivos nuevos valores a modificar';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.archivos_nuevos IS 'Objeto JSONB que contiene metadatos y rutas de los nuevos archivos de sustento cargados';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.estado_solicitud IS 'Estado de aprobación de la solicitud: P=Pendiente, A=Aprobada, R=Rechazada';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.nu_dias_habiles_limite IS 'Número máximo de días hábiles límite para resolver la solicitud';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.cdgo_usrios_ins IS 'Código del usuario (cdgo_usrios) que insertó la solicitud';
COMMENT ON COLUMN sistema.t_solicitudes_mod_historial.fec_ins IS 'Fecha y hora de la solicitud';

-- 2.3 TABLA: r_aprob_mod_historial
CREATE TABLE IF NOT EXISTS sistema.r_aprob_mod_historial (
    cdgo_aprobacion BIGSERIAL PRIMARY KEY,
    cdgo_solicitud_mod BIGINT NOT NULL REFERENCES sistema.t_solicitudes_mod_historial(cdgo_solicitud_mod) ON DELETE CASCADE,
    cdgo_area_aprobador INTEGER NOT NULL REFERENCES sistema.a_areas(cdgo_area),
    estado_aprobacion bpchar(1) NOT NULL DEFAULT 'P',
    cdgo_usrio_aprobador INTEGER NULL REFERENCES sistema.p_usrios(cdgo_usrios),
    fec_aprobacion TIMESTAMP WITH TIME ZONE NULL,
    -- Campos de Auditoría
    cdgo_usrios_ins INTEGER NOT NULL,
    fec_ins TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    cdgo_usrios_upd INTEGER NULL,
    fec_upd TIMESTAMP WITH TIME ZONE NULL,
    cdgo_usrios_del INTEGER NULL,
    fec_del TIMESTAMP WITH TIME ZONE NULL,
    CONSTRAINT uk_solicitud_area UNIQUE (cdgo_solicitud_mod, cdgo_area_aprobador),
    CONSTRAINT chk_aprobacion_mod_estado CHECK (estado_aprobacion IN ('P', 'A', 'R'))
);

CREATE INDEX idx_r_aprob_historial_solicitud ON sistema.r_aprob_mod_historial(cdgo_solicitud_mod);

-- Comentarios de la tabla intermedia de aprobaciones
COMMENT ON TABLE sistema.r_aprob_mod_historial IS 'Relación intermedia de aprobaciones requeridas por áreas específicas para la solicitud de modificación';
COMMENT ON COLUMN sistema.r_aprob_mod_historial.cdgo_aprobacion IS 'Identificador único de la aprobación por área';
COMMENT ON COLUMN sistema.r_aprob_mod_historial.cdgo_solicitud_mod IS 'Código de la solicitud de modificación asociada (FK)';
COMMENT ON COLUMN sistema.r_aprob_mod_historial.cdgo_area_aprobador IS 'Código de la oficina/área municipal que debe aprobar (sistema.a_areas)';
COMMENT ON COLUMN sistema.r_aprob_mod_historial.estado_aprobacion IS 'Estado de aprobación del área: P=Pendiente, A=Aprobada, R=Rechazada';
COMMENT ON COLUMN sistema.r_aprob_mod_historial.cdgo_usrio_aprobador IS 'Usuario físico que firmó la aprobación en el área (sistema.p_usrios)';
COMMENT ON COLUMN sistema.r_aprob_mod_historial.fec_aprobacion IS 'Fecha y hora en que se firmó la aprobación del área';

-- 2.4 TABLA: t_solicitudes_firma
CREATE TABLE IF NOT EXISTS sistema.t_solicitudes_firma (
    cdgo_solicitud_firma BIGSERIAL PRIMARY KEY,
    cdgo_documento BIGINT NOT NULL, -- Asume relación con sistema.h_documentos
    cdgo_usrio_solicitante INTEGER NOT NULL REFERENCES sistema.p_usrios(cdgo_usrios),
    cdgo_usrio_firmante INTEGER NOT NULL REFERENCES sistema.p_usrios(cdgo_usrios),
    in_derivacion_automatica BOOLEAN NOT NULL DEFAULT FALSE,
    estado_solicitud bpchar(1) NOT NULL DEFAULT 'P',
    de_observacion_rechazo TEXT NULL,
    nu_dias_habiles_limite INTEGER NULL,
    -- Campos de Auditoría
    cdgo_usrios_ins INTEGER NOT NULL,
    fec_ins TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    cdgo_usrios_upd INTEGER NULL,
    fec_upd TIMESTAMP WITH TIME ZONE NULL,
    cdgo_usrios_del INTEGER NULL,
    fec_del TIMESTAMP WITH TIME ZONE NULL,
    CONSTRAINT chk_evitar_autofirma CHECK (cdgo_usrio_solicitante <> cdgo_usrio_firmante),
    CONSTRAINT chk_solicitud_firma_estado CHECK (estado_solicitud IN ('P', 'F', 'R'))
);

CREATE INDEX idx_sol_firma_solicitante ON sistema.t_solicitudes_firma(cdgo_usrio_solicitante);
CREATE INDEX idx_sol_firma_firmante ON sistema.t_solicitudes_firma(cdgo_usrio_firmante);

-- Comentarios de la tabla solicitudes de firma
COMMENT ON TABLE sistema.t_solicitudes_firma IS 'Solicitudes de firma digital para autorizaciones de documentos jerárquicos';
COMMENT ON COLUMN sistema.t_solicitudes_firma.cdgo_solicitud_firma IS 'Identificador único de la solicitud de firma';
COMMENT ON COLUMN sistema.t_solicitudes_firma.cdgo_documento IS 'Código del borrador de documento a firmar (sistema.h_documentos)';
COMMENT ON COLUMN sistema.t_solicitudes_firma.cdgo_usrio_solicitante IS 'Usuario que redacta y solicita la firma (sistema.p_usrios)';
COMMENT ON COLUMN sistema.t_solicitudes_firma.cdgo_usrio_firmante IS 'Usuario jefe/autorizador que debe estampar la firma (sistema.p_usrios)';
COMMENT ON COLUMN sistema.t_solicitudes_firma.in_derivacion_automatica IS 'Indica si el documento se deriva de forma automática al firmar (true/false)';
COMMENT ON COLUMN sistema.t_solicitudes_firma.estado_solicitud IS 'Estado de la solicitud: P=Pendiente, F=Firmado/Aprobado, R=Rechazado';
COMMENT ON COLUMN sistema.t_solicitudes_firma.de_observacion_rechazo IS 'Motivo o detalle del rechazo de firma ingresado por el firmante';
COMMENT ON COLUMN sistema.t_solicitudes_firma.nu_dias_habiles_limite IS 'Número máximo de días hábiles límite para realizar la firma';

-- =============================================================================
-- PARTE 3: AUDITORÍA DE CONSULTAS PIDE (RENIEC / SUNAT)
-- =============================================================================

-- 3.1 TABLA: h_pide_consltas
CREATE TABLE IF NOT EXISTS sistema.h_pide_consltas (
    cdgo_pi_cons SERIAL PRIMARY KEY,
    nu_documento VARCHAR(20) NOT NULL,
    tp_documento VARCHAR(10) NOT NULL,
    cdgo_lgin INT,
    ip_terminal VARCHAR(45),
    fuente VARCHAR(50),
    pide_user_used VARCHAR(50),
    txt_dispositivo TEXT,
    fcha_cnsulta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    exito BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_pide_usuario FOREIGN KEY (cdgo_lgin) REFERENCES sistema.p_lgin(cdgo_lgin) ON DELETE SET NULL
);

-- Comentarios de la tabla auditoría PIDE
COMMENT ON TABLE sistema.h_pide_consltas IS 'Registro histórico de consultas realizadas a servicios externos de la PIDE (RENIEC, SUNAT, etc.)';
COMMENT ON COLUMN sistema.h_pide_consltas.cdgo_pi_cons IS 'Identificador único autoincremental de la consulta';
COMMENT ON COLUMN sistema.h_pide_consltas.nu_documento IS 'Número de DNI o RUC consultado';
COMMENT ON COLUMN sistema.h_pide_consltas.tp_documento IS 'Tipo de documento consultado (DNI, RUC)';
COMMENT ON COLUMN sistema.h_pide_consltas.cdgo_lgin IS 'Código de credencial de acceso del usuario municipal solicitante (FK a sistema.p_lgin)';
COMMENT ON COLUMN sistema.h_pide_consltas.ip_terminal IS 'IP desde la cual se realizó la consulta';
COMMENT ON COLUMN sistema.h_pide_consltas.fuente IS 'Nombre del proveedor consultado (PIDE, API DECOLECTA, MOCK, etc.)';
COMMENT ON COLUMN sistema.h_pide_consltas.pide_user_used IS 'Usuario/token de la PIDE/Decolecta utilizado para la consulta';
COMMENT ON COLUMN sistema.h_pide_consltas.txt_dispositivo IS 'User-Agent o identificador del dispositivo solicitante';
COMMENT ON COLUMN sistema.h_pide_consltas.fcha_cnsulta IS 'Fecha y hora de la consulta';
COMMENT ON COLUMN sistema.h_pide_consltas.exito IS 'Indica si la consulta fue exitosa (true/false)';
