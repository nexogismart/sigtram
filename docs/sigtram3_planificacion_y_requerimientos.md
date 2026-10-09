# Especificación de Planificación y Requerimientos (SRS)
## Proyecto: SIGTRAM3 (Migración y Modernización)

Este documento especifica los requerimientos funcionales, no funcionales y de seguridad que guiarán la migración, desarrollo y validación de **SIGTRAM3**.

---

## 1. Contexto y Objetivos del Proyecto

El objetivo principal es la migración tecnológica del sistema legacy `sigtram` (arquitectura tradicional en N-Capas) hacia **SIGTRAM3** (Laravel 13), mejorando la escalabilidad mediante Arquitectura Modular Orientada a Dominios (DDD) e implementando estrictas medidas de seguridad basadas en el estándar OWASP Top 10 (2025).

---

## 2. Requerimientos Funcionales (RF)

Los requisitos funcionales describen las características operativas y casos de uso del sistema.

### RF-01: Migración del Alcance Legacy
*   **Descripción:** El sistema debe replicar y optimizar todos los flujos del sistema antiguo (Mesa de Partes Virtual/Física, Bandeja de Expedientes, Licencias de Edificación, Resoluciones).
*   **Detalle:** El código antiguo debe adaptarse a la capa de Controladores, Servicios y Repositorios.

### RF-02: Gestión de Sesión Multi-Cargo y Multi-Área
*   **Descripción:** Al iniciar sesión, el funcionario municipal debe poder seleccionar su cargo y área activa.
*   **Detalle:** 
    *   Estos datos deben persistir de forma inmutable en el contexto de sesión de Laravel (`USER_CARGO`, `USER_COMPANY`, `USER_AREA`) y heredarse en todas las consultas del sistema.
    *   **Sincronización en Navegador:** Para evitar inconsistencias de datos al trabajar en múltiples pestañas del navegador, si el usuario cambia su área o cargo activo en la Pestaña B, se notificará a todas las demás pestañas del navegador utilizando la API `BroadcastChannel` o el evento `storage` de HTML5, forzando la sincronización inmediata del estado visual y del contexto sin recargar bruscamente o permitiendo una alerta de refresco automática.
    *   **Doble Capa de Seguridad (Frontend + Backend):** El bloqueo visual del frontend (backdrop) es para fines de experiencia de usuario (UX). El backend (controlador y middleware) debe validar obligatoriamente los permisos de acceso del cargo/área activa antes de procesar cualquier petición HTTP de escritura o lectura. Si se detecta un bypass por manipulación HTML (DevTools) en el frontend, el backend retornará inmediatamente un error `403 Forbidden` bloqueando la transacción.

### RF-03: Control de Permisos por Cargo y Excepciones Individuales
*   **Descripción:** El sistema debe resolver de forma ágil los accesos ordinarios del cargo y las excepciones de cada usuario.
*   **Detalle:** 
    *   Uso de una sola consulta unificada (`LEFT JOIN` y `COALESCE`) entre permisos base y excepciones.
    *   Guardado automático en la caché del sistema (`Cache::remember`) bajo la clave `user_perms_{cdgo_lgin}` para evitar lecturas repetidas a la base de datos.

### RF-04: Núcleo de Notificaciones Asíncronas (NotificationCore)
*   **Descripción:** Los envíos de WhatsApp (vía Whapi), Correos Electrónicos (SMTP) y SMS deben centralizarse y procesarse sin bloquear la interfaz del usuario.
*   **Detalle:** Implementar colas de trabajo asíncronas en segundo plano con Redis (`ShouldQueue`).

### RF-05: Generador Modular de Documentos Oficiales
*   **Descripción:** Generación de borradores editables en formato `.docx` y conversión automatizada a PDF final para firma digital.
*   **Detalle:** Uso de la librería PHPWord para manipulación de plantillas y ejecución de comandos desatendidos con LibreOffice Headless en el servidor de producción.

### RF-05.1: Edición de Word en Navegador vía ONLYOFFICE (Fase 2)
*   **Descripción:** Permitir al funcionario abrir y editar el borrador `.docx` directamente en el navegador web sin tener que descargarlo y volverlo a subir de forma manual.
*   **Detalle:** Integración con un servidor ONLYOFFICE Document Server municipal mediante un `<iframe>` incrustado y tokens JWT de acceso. Al guardar el cambio, ONLYOFFICE notifica a la API de Laravel mediante callbacks para actualizar el archivo físico en el almacenamiento privado de forma asíncrona.

### RF-06: Integración con Firma Perú (PCM)
*   **Descripción:** Firma digital de documentos PDF utilizando lectores de tarjetas DNIe y tokens USB, así como validación automatizada de firmas externas.
*   **Detalle:** Integración vía WebSocket con el agente local ReFirma PC de la PCM y consumo del validador central de firmas.

### RF-07: Consulta de Identidad en Cascada y Actualización Inteligente (PideCore)
*   **Descripción:** Consulta de datos de ciudadanos (DNI/RUC) priorizando el rendimiento, la vigencia del dato y la economía de peticiones externas.
*   **Detalle:** 
    *   **Lógica Híbrida en Cascada:** Al realizar una búsqueda por DNI o RUC, el sistema ejecutará la siguiente lógica secuencial:
        1. **Paso 1: Consulta APIs Externas (PIDE Oficial / Decolecta):** Se consulta prioritariamente el canal oficial PIDE. Si este falla o sufre timeout, se consulta Decolecta.
           * **Si el ciudadano/empresa es nuevo:** Se registra automáticamente de forma limpia en la base de datos local y se genera su `cdgo_usrio`.
           * **Si ya existe pero hay discrepancias:** El sistema **no** sobrescribe silenciosamente los datos locales. En su lugar, retorna una alerta de discrepancia con los datos locales vs los externos para que el operador decida si aprueba la actualización.
        2. **Paso 2: Fallback Base de Datos Local:** Si todas las APIs externas fallan de red o timeout, el sistema busca al ciudadano/empresa en la base de datos municipal local y retorna la información guardada si existe.
        3. **Paso 3: Fallback Entrada Manual:** Si todos los servicios externos fallan Y el ciudadano/empresa no existe localmente en el sistema, el backend retorna `null`, lo que habilita de inmediato los campos de la interfaz para que el operador digite la información manualmente (a partir del documento físico). Al guardar el trámite, el sistema inserta el registro y genera su `cdgo_usrio`.
    *   **Actualización Forzada en Interfaz:** Se provee un botón **"Actualizar desde RENIEC/SUNAT"** que omite la base de datos local, fuerza la consulta directa a las APIs externas y permite sincronizar los datos locales ante discrepancias aprobadas.

### RF-08: Flujo de Aprobación Colectiva para Modificaciones en el Histórico
*   **Descripción:** Si un área solicita modificar datos, observaciones o documentos adjuntos en una fila de su historial de trámite, dicha modificación no se aplica directamente si existen registros posteriores en la línea de tiempo del expediente de otras áreas. Requiere la aprobación unánime de todas las áreas subsecuentes.
*   **Detalle:**
    *   **Determinación de Aprobadores:** Al intentar editar un registro del historial, el sistema consulta dinámicamente y determina todas las áreas distintas que interactuaron con el expediente en fechas/secuencias posteriores al paso que se desea modificar.
    *   **Aislamiento en Borrador (Staging):** Los cambios sugeridos no sobrescriben el registro real de inmediato, sino que se guardan de forma aislada en una tabla de solicitudes de modificación en estado `PENDIENTE`.
    *   **Bandeja de Aprobaciones:** Se genera una notificación y tarea de validación en la bandeja de los directores de las áreas subsecuentes amarradas al expediente.
    *   **Consistencia, Transaccionalidad y Caducidad:** 
        *   **Aprobación Unánime:** Solo si **todas** las áreas amarradas aceptan la solicitud, se ejecuta la transacción en base de datos para sobrescribir los valores y archivos en el historial real, y el estado de la solicitud pasa a `APROBADO`.
        *   **Rechazo Único o Caducidad:** Si al menos una de las áreas subsecuentes rechaza la solicitud, la modificación es cancelada (`RECHAZADO`).
        *   **Límite de Días Hábiles (Opcional):** Se agregará una columna `nu_dias_habiles_limite` (INTEGER NULL). Si se define un valor numérico, el sistema controlará la expiración del plazo para responder. Si es `NULL` o está vacío, no habrá límite temporal de respuesta.
        *   **Cálculo de Días Hábiles y Feriados:** El cálculo de plazos de días hábiles excluirá sábados y domingos, así como las fechas registradas dinámicamente en la tabla de feriados de la municipalidad (`sistema.p_feriados`).
        *   **Aprobación por Transacción Única:** Todo cambio final en el historial real se aplica de manera atómica bajo transacciones SQL controladas.

### RF-09: Flujo de Firma Jerárquica Delegada (Visto Bueno del Jefe)
*   **Descripción:** Las secretarias y especialistas que redactan o preparan documentos oficiales (oficios, informes, resoluciones) no cuentan con facultades de firma final. Deben solicitar la validación y firma digital del Jefe de su área para oficializar el documento y continuar con el trámite.
*   **Detalle:**
    *   **Solicitud de Firma:** Al terminar de redactar un documento, el especialista/secretaria selecciona la opción "Solicitar Firma al Jefe". El sistema genera un registro de solicitud en estado `PENDIENTE` y asocia el PDF del borrador.
    *   **Determinación Automática del Firmante:** El sistema determina automáticamente quién es el Jefe del área basándose en la sesión activa (`USER_AREA`) y consultando la tabla de jefes de área de la compañía. Si hay múltiples jefes firmantes posibles, se habilita un selector visual para que el solicitante elija a quién dirigirlo.
    *   **Prohibición de Autofirma:** El sistema valida que el creador/especialista que solicita la firma (`cdgo_usrio_solicitante`) sea distinto al usuario jefe asignado para firmar (`cdgo_usrio_firmante`), evitando la autofirma si se cuenta con doble rol.
    *   **Plazo de Firma (Opcional):** Se incluye el campo opcional `nu_dias_habiles_limite` (INTEGER NULL) para definir plazos de atención si corresponde.
    *   **Bandeja de Firmas del Jefe:** El Jefe de área dispone de una bandeja de entrada exclusiva para ver, descargar y validar los documentos que sus subordinados prepararon.
    *   **Firma Criptográfica Directa e Híbrida:** Al presionar "Aprobar y Firmar", el sistema invoca al componente `FirmaCore` (ReFirma PC) para que el Jefe aplique su certificado digital sobre el PDF. Una vez firmado, el comportamiento de la derivación dependerá de la opción seleccionada por la secretaria/especialista al solicitar la firma:
        *   **Derivación Manual (Por Defecto):** El documento firmado regresa a la bandeja de la secretaria como "Firmados por el Jefe" para que ella lo revise, pueda adjuntar otros documentos complementarios o cargos, y realice la derivación manual cuando esté lista.
        *   **Derivación Automática:** El sistema da por completado el paso y empuja el expediente directamente al siguiente destinatario del flujo de forma inmediata bajo una transacción transaccional atómica única.

### RF-10: Módulo de Auditoría y Conciliación de Pagos
*   **Descripción:** El sistema debe proveer una interfaz gráfica de administración para que el personal de TI y Tesorería audite e investigue cobros fallidos de la pasarela o inconsistencias en los descargos.
*   **Detalle:**
    *   **Consulta de Logs y Transacciones:** SIGTRAM3 leerá en caliente (modo solo lectura) las tablas `pagos.sesiones_pago`, `pagos.transacciones` y `pagos.registros_auditoria` que residen en la base de datos PostgreSQL compartida.
    *   **Detección de Inconsistencias:** Listará transacciones aprobadas por la pasarela bancaria que sufrieron fallos de conexión o timeout durante el descargo en SATMUN o SIGTRAM3.
    *   **Conciliación Manual y Prevención de Fraude (Control de Cuatro Ojos):** Para evitar alteraciones fraudulentas de estados de deudas en multas o licencias, la conciliación manual de cobros observados requiere la segregación de funciones:
        1. **Solicitud de Conciliación:** Un operador de TI o Tesorería registra la solicitud indicando el ID de la transacción y adjuntando obligatoriamente el comprobante oficial de la pasarela (voucher digital en PDF o imagen de respaldo). **La base de datos de deudas no se modifica en este punto.**
        2. **Límite de Días Hábiles en Conciliación:** La solicitud de conciliación manual admitirá el campo opcional `nu_dias_habiles_limite` (INTEGER NULL) para controlar los plazos máximos de respuesta de la jefatura contados según el calendario de feriados (`sistema.p_feriados`).
        3. **Verificación Automatizada:** El sistema realiza una llamada viva a la API de la pasarela de pagos (Niubiz/Izipay) para comprobar que la transacción esté en estado "Cobrado/Exitoso" y que el monto coincida.
        4. **Aprobación del Supervisor/Jefe:** Un Jefe de Tesorería o Administrador del área con permisos de supervisor evalúa la solicitud y el archivo adjunto. Solo al dar su aprobación, el sistema ejecuta la transacción atómica de descargo de la deuda en la base de datos correspondiente (SATMUN o SIGTRAM3). Si se rechaza, no se altera la base de datos de deudas.
        5. **Bitácora Inmutable:** Toda acción se escribe con estado antes y después (Audit Trail) en `pagos.registros_auditoria` (incluyendo soft deletes con `cdgo_usrios_del` / `fec_del`).
    *   **Reporte de Liquidación Diaria:** Dispondrá de un botón **"Reporte de Liquidación Diaria"** con selector de fecha para descargar un archivo CSV que desglosa la recaudación, las comisiones de pasarela calculadas dinámicamente y el neto resultante a depositar (consumiendo la ruta `/api/v1/pagos/liquidacion-diaria` de la API de PAGOS).
    *   **Uso del Script Automático (`pagos:generar-liquidacion`):** Ejecuta diariamente a medianoche la consolidación de transacciones reportadas exitosas por la pasarela, restando las comisiones parametrizadas para generar el Reporte de Liquidación Diaria (CSV) para Tesorería. También realiza un cruce automatizado contra el API de la pasarela para detectar transacciones locales con timeout y registrar alertas en `config.alertas_conectividad` en caso de discrepancias.

### RF-11: Panel de Monitoreo y Alertas de Conectividad
*   **Descripción:** El sistema debe proveer un panel de visualización en tiempo real para las alertas de conectividad de la pasarela y las bases de datos externas de los municipios.
*   **Detalle:**
    *   **Dashboard Widget:** Un banner o widget en el panel de administración central de SIGTRAM3 que consulte la tabla `config.alertas_conectividad` y muestre de manera llamativa (alertas rojas/badges) cualquier caída de servicio con `in_esta = '1'`.
    *   **Gestión de Alertas:** El personal de soporte TI municipal podrá seleccionar la alerta, leer el mensaje de error técnico detallado registrado por la API de pagos, y marcar la alerta como "Resuelta/Leída" (cambiando `in_esta` a `'0'`).

### Requerimientos Diferidos (Planificados para la Fase 2 / Fuera de Alcance Inicial):
1.  **Módulo de Extornos en SIGTRAM3:** Panel administrativo para que el personal de Tesorería inicie y registre reembolsos a tarjetas de crédito de transacciones observadas o duplicadas.
2.  **Monitoreo de Comprobantes SUNAT (TUSNE):** Panel para consultar el estado de las facturas/boletas electrónicas enviadas a SUNAT, con opciones para reenviar XMLs rechazados por fallas del PSE.

---

## 3. Requisitos No Funcionales (RNF)

Los requisitos no funcionales definen los atributos de calidad, rendimiento y seguridad de la plataforma.

### RNF-01: Seguridad OWASP Top 10 (2025)
*   **Seguridad de Rutas:** Protección de todas las rutas mediante el middleware `auth` y uso de Policies para evitar manipulación de IDs.
*   **Prevención de Inyecciones:** Prohibición estricta de `DB::raw()` con variables no sanitizadas; uso exclusivo de bindings PDO.
*   **Migración de Contraseñas On-The-Fly:** Autenticar con hash Bcrypt moderno; si falla, verificar con SHA1 legacy, actualizar automáticamente a Bcrypt en la base de datos y conceder acceso de forma transparente.

### RNF-02: Gestión Segura de Cargas (GestorAdjuntos)
*   **Criterio:** Los archivos subidos por ciudadanos y empleados deben validarse y aislarse.
*   **Métrica:**
    *   Validación obligatoria de Mime-Type real (vía `fileinfo`).
    *   Guardado privado estructurado por fechas (`storage/app/documentos/YYYY/MM/DD/`).
    *   Enmascaramiento de nombres originales mediante hashes/UUIDs únicos.

### RNF-03: Prevención de Dobles Registros
*   **Criterio:** Bloqueo de peticiones concurrentes por clic repetido del usuario.
*   **Métrica:** Bloqueo de botones en el frontend al hacer clic y candados atómicos en el backend con `Cache::lock`.

### RNF-04: Mantenibilidad y Futuras Actualizaciones (Future-Proofing)
*   **Criterio:** El código debe ser tolerante al cambio de versiones del framework o dependencias.
*   **Métrica:** Declaración de tipado estricto PHP 8.4 (`declare(strict_types=1)`), validación con PHPStan nivel 5 y aislamiento de dependencias externas mediante interfaces.
