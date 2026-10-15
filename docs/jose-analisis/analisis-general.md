# Análisis técnico general, arquitectura y seguridad de SIGTRAM3

**Fecha del análisis:** 9 de octubre de 2026  
**Repositorio analizado:** `sigtram` / SIGTRAM3  
**Tipo de revisión:** análisis estático de código, configuración, estructura, documentación, pruebas y artefactos versionados  
**Alcance:** aplicación Laravel, frontend Blade/JavaScript, acceso a datos, autenticación/autorización, archivos, integraciones, Docker, pruebas y documentación

> **Nota de seguridad:** este documento identifica archivos y líneas que contienen secretos, pero no reproduce sus valores. Todo secreto versionado debe considerarse comprometido y rotarse, aunque el repositorio sea privado.

---

## 1. Resumen ejecutivo

SIGTRAM3 es una modernización activa de un sistema municipal legado. La solución ya tiene una base Laravel 13 funcional, módulos de negocio amplios, servicios transversales, integración con varias bases de datos, notificaciones, generación documental, PIDE/SUNARP y un esquema de permisos por perfil/cargo/usuario. Sin embargo, el estado actual no cumple todavía los objetivos de seguridad y arquitectura que declara su propia documentación.

La conclusión principal es:

> **El proyecto no debería desplegarse en producción ni exponerse a redes no confiables en su estado actual.**

Los riesgos más graves son:

1. **Credenciales reales versionadas:** existen contraseñas de bases de datos, tokens y credenciales de integraciones dentro de archivos del repositorio y en el historial Git.
2. **Control de acceso roto:** las rutas administrativas de seguridad están protegidas únicamente por `auth`; un usuario autenticado puede intentar administrar perfiles, cargos, usuarios y permisos sin una Policy, Gate o middleware de autorización.
3. **Caché de permisos incorrecta:** la clave de caché depende solo del usuario, aunque los permisos se calculan también con cargo y perfil. Un cambio de cargo puede reutilizar permisos de otro contexto durante ocho horas.
4. **Contraseñas con SHA-1:** el login, cambio y creación de claves todavía usan SHA-1 sin salt; la migración a bcrypt/Argon está escrita como comentario, pero desactivada.
5. **Archivos sensibles expuestos o versionados:** existen adjuntos ZIP/HTML dentro de `public/` y al menos un PDF de trámite dentro de `storage/app/private` versionado.
6. **Endpoints de diagnóstico y errores sensibles:** `/dev-config` es público, consulta configuración interna y devuelve mensajes de excepción; además, numerosos controladores exponen `$e->getMessage()` al cliente.

### Valoración global

| Dimensión | Valoración | Comentario |
|---|---:|---|
| Seguridad | **Crítica** | Hay secretos comprometidos y fallos de autorización de alto impacto. |
| Arquitectura | **Media-baja** | Existe una dirección correcta, pero conviven arquitectura moderna y lógica legacy monolítica. |
| Mantenibilidad | **Media-baja** | Hay clases y vistas muy grandes, rutas centralizadas y poco aislamiento por dominio. |
| Calidad de datos | **Media** | Se usan transacciones y validaciones en varios flujos, pero la integridad depende demasiado de código y BD legacy. |
| Pruebas | **Baja-media** | Hay 29 casos útiles, pero no cubren los módulos críticos ni los límites de autorización. |
| Operabilidad | **Baja** | El contenedor no ejecuta worker de colas; faltan endurecimiento, observabilidad y despliegue reproducible. |
| Documentación | **Media** | Es extensa y valiosa, pero describe componentes y controles todavía no implementados. |

---

## 2. Alcance, método y limitaciones

### 2.1 Actividades realizadas

- Inventario de archivos y módulos.
- Lectura de `README.md`, arquitectura, planificación, análisis previos y esquema SQL.
- Revisión de `routes/web.php`, autenticación, permisos, controladores, servicios, repositorios, modelos y Form Requests.
- Búsqueda de secretos, SQL dinámico, ejecución de procesos, operaciones de archivos, salida HTML sin escapar, mensajes de excepción y controles de tasa.
- Revisión de Docker, Nginx, sesiones, colas, caché, dependencias y configuración de bases de datos.
- Inspección de pruebas automatizadas y configuración de PHPStan/PHPUnit.
- Validación sintáctica de todos los archivos PHP del repositorio.
- Auditoría de dependencias NPM a partir de `package-lock.json`.

### 2.2 Limitaciones

- `vendor/` no está instalado; por ello no fue posible ejecutar Pest/PHPUnit ni PHPStan.
- `composer audit --locked` no pudo consultar Packagist debido a la restricción de red del entorno. Su resultado queda **inconcluso**, no limpio.
- No se contó con una base PostgreSQL municipal ni con credenciales de prueba aisladas; no se realizaron pruebas dinámicas de autorización, SQL o concurrencia.
- No se hizo pentesting activo contra un despliegue.
- Los hallazgos son del código revisado en el commit `900b6be521cb54dc285cd8553251f600cff62267` más el archivo local no versionado `env`.

### 2.3 Evidencia cuantitativa

| Métrica | Resultado aproximado |
|---|---:|
| Archivos versionados | 522 |
| Tamaño versionado | 51.8 MB |
| Archivos PHP en `app/` | 139 |
| Líneas PHP en `app/` | 37,610 |
| Controladores | 54 |
| Servicios | 38 |
| Repositorios | 2 |
| Modelos | 16 |
| Form Requests | 10 |
| Vistas Blade | 106 |
| Definiciones de rutas | 322 |
| Archivos de pruebas | 10 |
| Casos Pest detectados | 29 |
| Archivos PHP con `strict_types=1` | 30 de 139 |
| Usos de `innerHTML`/`.html()` | 221 |
| Referencias a `getMessage()` en controladores | 44 |

---

## 3. Visión funcional y módulos identificados

La aplicación cubre o inicia los siguientes dominios:

- Autenticación heredada, selección de cargo y menú dinámico.
- Trámite interno y externo.
- Bandejas, derivación, recepción, asignación, envío, finalización y archivo.
- Reportes de expedientes y trámites.
- Seguridad: perfiles, cargos, opciones, accesos, usuarios y cambio de contraseña.
- PIDE, RENIEC, SUNARP y Decolecta.
- Notificaciones por correo y WhatsApp.
- Generación de documentos Word/QR y plantillas oficiales.
- OMAPED y acceso público temporal mediante QR.
- Helpdesk y tickets TI.
- Firma de correo.
- Cámaras/dispositivos de seguridad y streaming RTSP/MJPEG/HLS.
- Reportes de publicidad.
- Promesas/reuniones.
- GDU, OAC y módulos administrativos/SGA-SGL.

La cobertura funcional es amplia para un único despliegue. Esto aumenta el impacto de cualquier falla transversal en autenticación, permisos, sesiones, archivos o conexiones.

---

## 4. Arquitectura actual

### 4.1 Estructura real

El sistema es actualmente un **monolito modular Laravel con integración intensiva a un esquema de datos legacy**:

```text
Browser
  -> Nginx
    -> Laravel / routes/web.php
      -> Controllers por módulo
        -> Services (parcialmente extraídos)
          -> Query Builder / SQL directo
            -> PostgreSQL principal + 7 conexiones auxiliares
      -> Blade + JavaScript embebido
      -> Queue jobs para correo/WhatsApp
      -> Storage local / documentos / cámaras / APIs externas
```

Hay elementos positivos de arquitectura:

- Interfaces para `SecurityCore`, `BdCore`, `NotificationCore`, `PideCore` y proveedores.
- Inyección de dependencias registrada en `AppServiceProvider`.
- Repositorio específico de permisos.
- DTOs para persona y empresa.
- Proveedores separados para PIDE/local/Decolecta/SUNARP.
- Jobs para notificaciones.
- Form Requests en algunos flujos críticos.
- Uso de transacciones en procesos multitabla.

No obstante, la mayor parte del sistema sigue siendo una modernización por envoltura del legado:

- 322 rutas viven en un solo `routes/web.php`.
- Muchos controladores consultan directamente tablas del esquema `sistema`.
- Solo existen dos repositorios para 54 controladores y 38 servicios.
- Hay lógica de dominio, consultas SQL, transformación y respuestas HTTP mezcladas.
- El frontend se implementa principalmente como Blade con grandes bloques JavaScript.
- Varias clases superan 1,000 líneas.
- La selección de vistas depende de rutas almacenadas en base de datos y de un catch-all dinámico.

### 4.2 Diferencia entre arquitectura documentada e implementada

La documentación declara DDD/Clean Architecture, `AuditCore`, `AdjuntosCore`, `FirmaCore`, autorización mediante Policies, gestión privada de adjuntos, bloqueo atómico y migración SHA-1 a bcrypt. La implementación actual no presenta esos componentes o no los aplica transversalmente.

Ejemplos:

| Declarado | Estado real observado |
|---|---|
| Policies en todas las rutas | Predomina únicamente `auth`; autorización puntual dentro de algunos controladores. |
| Migración SHA-1 -> bcrypt al iniciar sesión | Código comentado y desactivado. |
| `AdjuntosCore` privado, hash/UUID y MIME real | No existe núcleo central; algunos adjuntos usan disco `public` y nombre predecible. |
| `AuditCore` inmutable con diff | No existe servicio transversal; hay logs parciales y auditorías específicas. |
| `FirmaCore` | No se encontró implementación equivalente. |
| Bloqueos atómicos contra doble envío | No se encontraron `Cache::lock` ni mecanismos equivalentes. |
| PHP estricto generalizado | Solo 30 de 139 archivos de `app/` lo declaran. |
| Esquema administrado por migraciones | Solo hay cuatro migraciones; el dominio depende de un esquema legacy externo. |

Esto no invalida la dirección planteada, pero la documentación debe distinguir claramente **estado actual**, **en construcción** y **objetivo futuro**.

---

## 5. Hallazgos de seguridad

### Escala usada

- **Crítica:** compromiso inmediato de credenciales, privilegios o datos sensibles.
- **Alta:** explotación viable con impacto serio, normalmente requiere usuario o acceso parcial.
- **Media:** debilidad importante que facilita ataques o incidentes.
- **Baja:** endurecimiento, deuda o riesgo limitado.

### Resumen de hallazgos

| ID | Severidad | Hallazgo |
|---|---|---|
| SEC-01 | Crítica | Credenciales y tokens reales versionados. |
| SEC-02 | Crítica | Administración de seguridad sin autorización granular. |
| SEC-03 | Crítica | Caché de permisos no separada por cargo/perfil. |
| SEC-04 | Crítica | Contraseñas almacenadas y verificadas con SHA-1. |
| SEC-05 | Alta | Login y cambio de contraseña sin rate limit robusto. |
| SEC-06 | Alta | Endpoint público `/dev-config` expone datos y errores. |
| SEC-07 | Alta | IDOR y autorización insuficiente en adjuntos/expedientes. |
| SEC-08 | Alta | Adjuntos sensibles en `public/` y artefactos privados versionados. |
| SEC-09 | Alta | Redis expuesto al host sin autenticación. |
| SEC-10 | Alta | Verificación TLS desactivada en proxy de cámaras. |
| SEC-11 | Alta | Mensajes internos de excepciones devueltos al cliente. |
| SEC-12 | Alta | APIs de módulos con autorización inconsistente. |
| SEC-13 | Media-Alta | Control “solo lectura” de `BdCore` evadible. |
| SEC-14 | Media | Posible XSS por contextos HTML/atributos construidos con `innerHTML`. |
| SEC-15 | Media | Faltan cabeceras de seguridad HTTP. |
| SEC-16 | Media | OTP de cambio de clave débil y sin límite de intentos. |
| SEC-17 | Media | Valores por defecto inseguros para cámaras. |
| SEC-18 | Media | Ausencia de defensa de concurrencia/idempotencia. |

### SEC-01 — Credenciales y tokens versionados

**Evidencia**

- `config/database.php:120-196` contiene hosts internos, usuarios y contraseñas por defecto para múltiples conexiones municipales.
- `.env.example:78-118` contiene contraseñas, un token de proveedor externo y credenciales PIDE/SUNARP no vacías.
- `config/pide.php:26-27` define credenciales por defecto.
- `config/camara_visor.php:4-5` usa credenciales por defecto conocidas.
- El contenido está presente desde el commit inicial, por lo que eliminarlo del último commit no elimina su exposición histórica.

**Impacto**

- Acceso no autorizado a bases de datos internas si hay conectividad o pivote de red.
- Consumo fraudulento de APIs, filtración de datos personales y movimiento lateral.
- Las variables de entorno no protegen el secreto si el valor por defecto ya está en el código.

**Acción inmediata**

1. Rotar todas las credenciales halladas: PostgreSQL, MySQL, SQL Server, cámaras, Decolecta, PIDE/SUNARP y cualquier token relacionado.
2. Revocar tokens antes de reescribir el historial.
3. Reemplazar todo valor sensible por `env('CLAVE')` sin default o con `null`.
4. Limpiar historial Git con una herramienta apropiada y coordinar un force-push controlado.
5. Invalidar clones, artefactos CI y copias que contengan el historial anterior.
6. Activar escaneo de secretos en pre-commit y CI.

### SEC-02 — Control de acceso roto en administración de seguridad

**Evidencia**

- `routes/web.php:95` abre un grupo protegido por `auth`.
- `routes/web.php:457-498` registra todas las rutas de perfiles, cargos, opciones, usuarios y accesos dentro de ese grupo, sin middleware de permiso administrativo.
- Los controladores de `app/Http/Controllers/mod_seguridad/` no llaman consistentemente a `SecurityCore`, Gate, Policy o `authorize()`.
- `UsuarioController` permite crear/editar credenciales, perfiles y cargos desde dichas rutas.

**Impacto**

Un usuario autenticado con perfil de bajo privilegio puede invocar directamente los endpoints, aunque el menú no se los muestre. Ocultar enlaces no es control de acceso.

**Recomendación**

- Crear middleware `sigtram.permission:<ruta>,<acción>` o Policies por recurso.
- Aplicarlo en el archivo de rutas y no depender de verificaciones voluntarias en cada método.
- Separar `ver`, `crear`, `editar` y `eliminar`.
- Proteger de forma explícita todo `mod_seguridad` con rol administrativo y permisos de acción.
- Agregar pruebas que autentiquen perfiles sin permiso y esperen `403` para cada operación.

### SEC-03 — Contaminación de caché entre cargos/perfiles

**Evidencia**

- `PermisosRepository.php:23-30` calcula permisos con usuario, cargo y perfil.
- `SessionKeys.php:28-42` genera la clave `user_perms_<login>` usando solo el usuario.
- La entrada se conserva ocho horas.
- `SecurityCore.php:56-118` reutiliza esos permisos en cada verificación.

**Escenario**

1. Un usuario selecciona un cargo privilegiado y llena la caché.
2. Cambia a otro cargo con menos permisos.
3. El repositorio devuelve la entrada anterior porque la clave no distingue cargo/perfil.
4. Las comprobaciones pueden autorizar acciones con permisos del contexto previo.

**Recomendación**

- Clave mínima: `user_perms:{login}:{perfil}:{cargo}`.
- Invalidar todas las variantes al cambiar asignaciones, perfil, cargo o excepciones.
- Reducir TTL y usar tags si el backend de caché lo permite.
- Agregar una prueba de cambio A -> B -> A con permisos diferentes.

### SEC-04 — SHA-1 para contraseñas

**Evidencia**

- `AuthController.php:143-184` calcula `sha1($pwdPlain)` y compara el hash en SQL.
- `PasswordController.php:75-77`, `141-166` verifica y guarda SHA-1.
- `UsuarioController.php:300-305` crea o cambia contraseñas con SHA-1.
- La migración hacia `SigtramPassword` está comentada.

**Impacto**

SHA-1 es rápido y sin salt en este uso; una filtración de la tabla permite cracking masivo y reutilización de credenciales.

**Recomendación**

- Activar migración progresiva: verificar hash moderno; si el registro tiene 40 caracteres hexadecimales, validar legacy en comparación de tiempo constante y rehashear inmediatamente con `Hash::make()`.
- Forzar restablecimiento de cuentas sensibles.
- Elevar requisito mínimo; la creación administrativa actualmente admite una contraseña de solo cuatro caracteres.
- No conservar compatibilidad SHA-1 indefinidamente: definir fecha de retiro y métrica de migración.

### SEC-05 — Ausencia de rate limiting general

Solo PIDE implementa un límite explícito (`PideCore.php:393-402`). No se encontró throttling para:

- `POST /login`.
- Envío/confirmación de código de cambio de contraseña.
- QR público OMAPED y validación de DNI.
- Endpoints costosos de generación, búsqueda o streaming.

Aplicar límites combinados por IP, usuario, cuenta objetivo y dispositivo, con ventanas progresivas y registro de eventos.

### SEC-06 — Endpoint público de diagnóstico

`routes/web.php:58-80` publica `/dev-config` fuera de `auth`. El endpoint:

- Consulta toda la tabla `ti.configuracion`.
- Devuelve su contenido como JSON.
- Devuelve el mensaje de la excepción al cliente.
- Escribe `dev-config.json` en la raíz del proyecto.

Debe eliminarse. Si existe una necesidad operativa, reemplazarlo por un comando Artisan o endpoint interno autenticado que entregue solo un estado booleano sin datos ni errores internos.

### SEC-07 — IDOR y adjuntos sin autorización de objeto

`AdjuntarTramiteController.php` presenta varios problemas:

- `upload()` recibe `movimientoId` de la ruta y actualiza ese registro sin comprobar que pertenece al cargo/área/compañía del usuario (`111-135`, `280-290`).
- `detalle()` devuelve expediente, DNI e historial por ID sin comprobar pertenencia (`138-193`).
- `verArchivo()` permite leer cualquier nombre existente en la carpeta de adjuntos a todo usuario autenticado que conozca la URL (`304-312`).
- La consulta principal filtra compañía, pero las operaciones individuales no reutilizan esa restricción.

La autorización debe aplicarse por objeto, no solo por módulo. Cada operación debe validar compañía, cargo, área, estado permitido y relación con el expediente dentro de la misma transacción.

### SEC-08 — Archivos públicos y datos versionados

- Hay 20 ZIP versionados en `public/adjuntados/`.
- Hay 38 HTML versionados en `public/contenidos/`.
- Existe un PDF de trámite versionado en `storage/app/private/.../pri378812.pdf`.
- `AdjuntarTramiteController.php:271-275` guarda archivos en el disco `public` con nombres predecibles.

Aunque una muestra superficial de los HTML no mostró correos/DNI en texto plano, el patrón es inseguro y los documentos binarios pueden contener datos personales o metadatos.

**Recomendación**

- Retirar los documentos reales de Git y del webroot.
- Guardar adjuntos en disco privado con UUID aleatorio.
- Descargar mediante controlador autorizado, con `Content-Disposition`, `nosniff` y registro de acceso.
- Validar MIME real, extensión, tamaño, estructura de ZIP y antivirus/sandbox.
- Evitar `doc`, `docx`, `xls`, `xlsx`, `zip` y `rar` salvo que el negocio lo exija y exista análisis antimalware.

### SEC-09 — Redis expuesto sin autenticación

`docker-compose.yml:33-40` publica `6379:6379`, no configura contraseña ni restringe interfaz. En una estación o servidor accesible, esto permite leer/modificar caché, sesiones o colas.

Eliminar `ports` si Redis solo se usa dentro de la red Docker. Si debe exponerse, enlazar a loopback, exigir autenticación/ACL y segmentar la red.

### SEC-10 — TLS desactivado en cámaras

`dispositivo_seg_proxy_stream.inc.php:1404-1405` desactiva `CURLOPT_SSL_VERIFYPEER` y `CURLOPT_SSL_VERIFYHOST`. Esto permite un ataque de intermediario contra credenciales y video.

Instalar la CA interna o configurar certificados válidos; nunca desactivar verificación globalmente.

### SEC-11 — Divulgación de errores internos

Se detectaron 44 referencias a `getMessage()` en controladores. Ejemplos incluyen autenticación administrativa, adjuntos, trámites, generación documental y cambio de contraseña.

Esto puede revelar SQL, tablas, rutas, hosts, nombres de archivos y comportamiento interno.

Patrón recomendado:

```php
report($exception);

return response()->json([
    'success' => false,
    'message' => 'No se pudo completar la operación.',
    'error_id' => $correlationId,
], 500);
```

El detalle debe ir a un log estructurado con acceso restringido.

### SEC-12 — Autorización inconsistente por módulo

De 54 controladores, solo una parte visible invoca `tienePermiso`, `checkAccess` o autorizadores equivalentes. El catch-all del menú sí valida permisos, pero muchas APIs POST/GET tienen rutas directas que solo heredan `auth`.

El control de acceso debe centralizarse en middleware/Policies y cubrir tanto la página como cada API. La regla debe ser **denegar por defecto**.

### SEC-13 — “Solo lectura” evadible en BdCore

`BdCore.php:37-47` solo comprueba que el texto empiece por `select` o `with`. Esto no garantiza lectura:

- PostgreSQL permite CTE modificadores como `WITH x AS (DELETE ... RETURNING ...) SELECT ...`.
- Dependiendo del driver, pueden existir consultas múltiples.
- No restringe el usuario de BD a permisos SELECT.

El control real debe ser una cuenta de base de datos read-only. La validación de texto puede mantenerse como segunda barrera, pero no como frontera de seguridad.

### SEC-14 — Riesgo XSS en HTML dinámico

Hay 221 usos de `innerHTML`/`.html()`. Muchos aplican escape, pero el escape es manual e inconsistente. Por ejemplo:

- `buscar-tramite.blade.php:247-250` escapa comillas dobles, pero no comillas simples.
- `buscar-tramite.blade.php:294-296` inserta un valor dentro de un atributo JavaScript delimitado con comilla simple.

Un nombre de archivo o dato manipulado con `'` puede romper el contexto. Evitar handlers inline y crear nodos con `textContent`, `setAttribute` y `addEventListener`. Como defensa adicional, aplicar CSP sin `unsafe-inline` tras eliminar scripts inline.

### SEC-15 — Cabeceras HTTP faltantes

No se encontraron configuraciones explícitas para CSP, HSTS, `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy` o `Permissions-Policy` en Nginx o middleware.

Definirlas considerando los iframes/documentos que realmente use el sistema. Agregar `X-Content-Type-Options: nosniff` globalmente.

### SEC-16 — OTP de cambio de contraseña

`PasswordController.php:94-98` usa `rand()` para generar el código, lo guarda en sesión y no limita intentos. Tampoco se observa invalidación de otras sesiones tras cambiar la contraseña.

- Usar `random_int()`.
- Guardar hash del código, no código plano.
- Vincularlo a usuario, propósito y nonce.
- Máximo de intentos y reenvíos.
- Invalidar código al usarlo.
- Rotar sesión y revocar otras sesiones/tokens.

### SEC-17 — Credenciales predeterminadas de cámaras

`config/camara_visor.php:4-5` cae en usuario/clave conocidos si faltan variables. Una mala configuración se convierte silenciosamente en credenciales débiles. Fallar durante el arranque si no están configuradas.

### SEC-18 — Concurrencia e idempotencia

La especificación exige `Cache::lock`, pero no se detectó su uso. Flujos de derivar, recepcionar, finalizar, registrar o notificar pueden procesarse dos veces por doble clic, reintento de red o dos operadores concurrentes.

Usar transacciones, bloqueos pesimistas/optimistas, claves idempotentes y constraints únicos según el flujo.

---

## 6. Autenticación, autorización y sesiones

### Aspectos correctos

- El login regenera el identificador de sesión (`AuthController.php:205-209`).
- Logout invalida sesión y regenera token CSRF (`216-223`).
- La selección de cargo valida que el cargo pertenezca al usuario (`63-90`).
- El menú se deriva de permisos y el catch-all valida la ruta solicitada.
- Laravel aporta protección CSRF a las rutas web POST.

### Debilidades

- SHA-1 invalida la fortaleza del proceso de autenticación.
- No hay rate limit de login.
- El perfil `1` tiene bypass total hardcodeado (`SecurityCore.php:63-70`). Debe ser una capacidad auditable, no un número mágico.
- `obtenerCargosUsuario()` no filtra explícitamente registros activos de la relación/cargo.
- El permiso se comprueba de forma irregular en controladores.
- La caché no incluye contexto de cargo/perfil.
- El archivo local `env` usa sesión no cifrada y configuración de desarrollo; al no estar versionado no demuestra producción, pero requiere checklist de despliegue.

### Modelo recomendado

1. Autenticación moderna con `Hash` y migración temporal de legado.
2. Middleware de contexto que exija cargo activo válido en cada request de negocio.
3. Policy/Gate por agregado: expediente, ticket, usuario, reporte, adjunto.
4. Middleware de permiso por ruta y acción.
5. Autorización por objeto dentro de la consulta SQL para impedir IDOR.
6. Auditoría de toda operación privilegiada.
7. Pruebas de matriz usuario x cargo x acción x recurso.

---

## 7. Acceso a datos y SQL

### Fortalezas

- Predomina Query Builder y bindings en SQL directo.
- Las columnas dinámicas revisadas suelen pasar por allowlists.
- Varios procesos usan transacciones.
- Los nombres de esquema provienen de configuración y no directamente del request.

### Riesgos y deuda

- Hay una gran cantidad de SQL manual distribuido entre controladores y servicios.
- Algunas consultas concatenan fragmentos de tabla/campo. Aunque varios se controlan, el patrón requiere revisión continua.
- Controladores de más de 1,500 líneas combinan SQL, autorización, lógica de estado y transporte.
- Siete conexiones auxiliares adicionales amplían superficie de ataque y complejidad operativa.
- Los secretos por defecto convierten una ausencia de configuración en conexión real, no en fallo seguro.
- La base legacy no está reproducida por migraciones. `docs/sql/schema_sigtram3.sql` solo crea seis tablas complementarias, mientras la aplicación usa muchas decenas de tablas preexistentes.

### Recomendaciones

- Prohibir credenciales por defecto.
- Crear usuarios read-only por integración.
- Mover queries de módulos a repositorios/query objects con contratos.
- Añadir pruebas de integración sobre una copia anonimizada o fixtures representativos.
- Gestionar cambios propios por migraciones idempotentes y documentar precondiciones del esquema legacy.
- Incorporar índices guiados por consultas reales y `EXPLAIN ANALYZE`, no solo por intuición.
- Evitar transacciones largas que incluyan llamadas HTTP, correo o generación de archivos.

---

## 8. Gestión de archivos y documentos

### Estado actual

- Coexisten almacenamiento privado y público.
- Se aceptan PDF, imágenes, documentos Office y archivos comprimidos en diferentes flujos.
- Hay generación de DOCX mediante OpenTBS y QR temporal.
- Hay descargas protegidas en algunos módulos, como OMAPED, pero no existe una política transversal.

### Problemas

- Nombres predecibles basados en ID y timestamp.
- Uso del disco `public` para adjuntos de expedientes.
- Falta de autorización de objeto al leer/subir.
- No se observó antivirus ni CDR para Office/PDF.
- El tamaño Nginx/PHP llega a 100 MB, superior a varias reglas de negocio.
- `DocumentGeneratorService.php:171-174` deja comentada la eliminación del QR temporal, provocando acumulación y posible exposición local.
- Debe validarse que todo nombre de salida usado por el generador sea reducido a `basename` o generado por el servidor.

### Diseño objetivo

Crear un servicio único de adjuntos con:

- ID lógico y UUID físico.
- Disco privado.
- MIME real + firma mágica + allowlist por caso de uso.
- Límite en proxy, PHP y validación consistente.
- Hash SHA-256 para integridad/deduplicación.
- Antivirus asíncrono con estado `pending/clean/rejected`.
- Descarga autorizada y auditada.
- Retención y eliminación gobernadas.
- Protección contra ZIP bomb y traversal al inspeccionar comprimidos.

---

## 9. Frontend y seguridad del navegador

La UI combina Blade, Tailwind y JavaScript embebido. Es funcional, pero varias vistas son demasiado grandes:

- `v2_lista_reporte_area_cargo.blade.php`: ~1,889 líneas.
- `inicio.blade.php`: ~1,489 líneas.
- `segi_bandeja.blade.php`: ~1,408 líneas.
- `segi_expedi.blade.php`: ~1,318 líneas.

### Consecuencias

- Mayor riesgo de XSS por construcción manual de HTML.
- Duplicación de helpers de escape, fetch, modales y paginación.
- Dificultad de pruebas unitarias.
- Cambios visuales con alto riesgo de regresión.

### Recomendaciones

- Extraer componentes Blade y módulos JS por dominio.
- Evitar scripts inline y handlers `onclick`.
- Centralizar cliente HTTP, manejo de errores y CSRF.
- Usar `textContent` para texto y plantillas seguras para estructura.
- Introducir ESLint y pruebas de componentes/flujo.
- Añadir CSP inicialmente en modo report-only y luego enforcement.

---

## 10. Calidad, diseño y mantenibilidad

### Clases de mayor complejidad

Entre los archivos más grandes se encuentran:

- `omaped.class.php`: ~2,579 líneas.
- `CorreoExternoController.php`: ~1,557 líneas.
- `VerRequerimientoLogisticaService.php`: ~1,471 líneas.
- `dispositivo_seg_proxy_stream.inc.php`: ~1,297 líneas.
- `PagoProveedoresIndexService.php`: ~1,233 líneas.
- `V2ListaReporteRequerimientosGeneralService.php`: ~1,061 líneas.
- `PromesaService.php`: ~984 líneas.
- `BuscaExpediController.php`: ~917 líneas.
- `ReporteExpedienteController.php`: ~879 líneas.
- `AuthController.php`: ~770 líneas.

Estas dimensiones indican baja cohesión y múltiples motivos de cambio por clase.

### Observaciones

- Hay mezcla de nombres en español e inglés, consistente con parte de la guía, pero sin límites claros.
- Existen includes procedurales legacy junto a servicios orientados a objetos.
- La lógica de autorización se repite mediante métodos `checkAccess` diferentes.
- Hay comentarios TODO de seguridad en caminos productivos.
- Varias excepciones se silencian completamente, reduciendo observabilidad.
- El `README` conserva enlaces `file:///c:/...` de otra estación, no portables.
- Hay artefactos de trabajo en `scratch/` y `output/` versionados.

### Estrategia de refactorización

No conviene reescribir todo. Aplicar una estrategia incremental:

1. Crear fronteras de seguridad transversales.
2. Extraer casos de uso de los controladores más críticos.
3. Encapsular consultas legacy en repositorios/query objects.
4. Definir estados y transiciones explícitas de expediente/ticket.
5. Añadir pruebas de caracterización antes de modificar lógica legacy.
6. Reducir controladores a validar, autorizar, invocar y responder.

---

## 11. Pruebas y aseguramiento de calidad

### Estado observado

Hay pruebas para:

- `BdCore`.
- `SecurityCore`.
- PIDE y su controlador.
- Notificaciones.
- Seguimiento de expediente.
- Algunas rutas/controladores.

La sintaxis de todos los PHP revisados es válida.

No fue posible ejecutar las pruebas porque `vendor/` no está instalado. Además, `phpunit.xml` depende de la base configurada: SQLite en memoria está comentado, por lo que varias pruebas requieren infraestructura municipal o una BD de prueba compatible.

### Brechas prioritarias

- No hay suite exhaustiva de autorización negativa.
- No hay pruebas de login SHA-1 -> hash moderno.
- No hay prueba de aislamiento de permisos entre cargos.
- No hay pruebas IDOR de expedientes/adjuntos.
- No hay pruebas de cargas maliciosas.
- No hay pruebas de concurrencia/idempotencia.
- No hay pruebas de seguridad de cabeceras.
- No hay cobertura automatizada visible ni umbral CI.
- PHPStan nivel 5 está configurado, pero no se pudo ejecutar y solo analiza `app/`.

### Pirámide recomendada

- **Unitarias:** reglas de estado, normalizadores, DTOs, política de contraseña, permisos.
- **Integración:** repositorios contra PostgreSQL efímero con esquema mínimo.
- **Feature:** rutas, validación, autorización positiva y negativa.
- **Contrato:** PIDE, SUNARP, WhatsApp, correo y cámaras mediante fakes.
- **E2E:** login, cambio de cargo, ingreso/derivación/finalización y descarga.
- **Seguridad:** secretos, dependencias, SAST, archivos, CSP y fuzzing básico.

---

## 12. Dependencias y cadena de suministro

### PHP

Versiones relevantes del lock al momento del análisis:

- Laravel Framework `v13.16.1`.
- DOMPDF `v3.1.2`.
- chillerlan/php-qrcode `6.0.1`.
- simplesoftwareio/simple-qrcode `4.2.0`.
- OpenTBS `1.12.3`.
- Pest `4.7.4`.
- PHPUnit `12.5.30`.
- Larastan `3.10.0`.

`composer.json` es válido. La auditoría de vulnerabilidades PHP no pudo completarse por falta de red; debe ejecutarse en CI.

### JavaScript

`npm audit --package-lock-only` no reportó vulnerabilidades conocidas en el lock durante esta revisión. Esto no sustituye revisión de código ni garantiza ausencia futura.

### Riesgos de build

- `Dockerfile` usa `composer:latest`, sin digest.
- `docker-compose.yml` usa `nginx:alpine` y `redis:alpine`, sin versión/digest.
- El entrypoint instala dependencias en el arranque si falta `vendor`.
- La imagen no construye assets NPM de forma reproducible.
- El bind mount de todo el repositorio corresponde a desarrollo, no a producción.

Usar build multi-stage, imágenes fijadas por versión/digest, `composer install --no-dev`, assets precompilados, usuario no root, filesystem de solo lectura donde sea posible y SBOM.

---

## 13. Docker, despliegue y operación

### Problemas operativos

1. **No hay worker de colas en Docker Compose.** `NotificationCore` y jobs pueden encolar trabajo, pero el stack solo inicia PHP-FPM, Nginx y Redis. Los mensajes asíncronos quedarían pendientes.
2. **No hay scheduler.** No existe servicio para `schedule:work`/cron.
3. **Redis está publicado sin necesidad.**
4. **Nginx solo sirve HTTP.** TLS queda implícitamente delegado, sin documentación de proxy confiable.
5. **No hay health checks de contenedor.** Laravel ofrece `/up`, pero Compose no lo usa.
6. **No hay límites de CPU/memoria ni estrategia de logs.**
7. **El entrypoint limpia cachés en cada inicio**, y no ejecuta `config:cache`, `route:cache` o `view:cache` para producción.
8. **Carga máxima de 100 MB** y ejecución PHP de hasta 600 segundos favorecen agotamiento de recursos.

### Stack mínimo de producción

- `web` Nginx endurecido.
- `app` PHP-FPM con build inmutable.
- `queue` con Supervisor o proceso dedicado.
- `scheduler` dedicado.
- Redis interno con ACL.
- Base de datos fuera del stack con cuentas de mínimo privilegio.
- Proxy TLS/WAF según infraestructura municipal.
- Logs JSON, métricas y alertas.
- Backups verificados y procedimiento de restauración.

---

## 14. Privacidad y datos personales

El sistema maneja DNI, nombres, teléfonos, correos, direcciones, expedientes, información OMAPED/discapacidad, documentos y posiblemente video de seguridad. Son datos de alta sensibilidad operacional y personal.

Controles requeridos:

- Minimización de datos por pantalla y endpoint.
- Autorización por finalidad y cargo.
- Auditoría de consultas a identidad, OMAPED, expedientes y video.
- Cifrado en tránsito y, cuando corresponda, en reposo.
- Retención definida por tipo documental.
- Anonimización de datos de desarrollo/prueba.
- Prohibición de documentos reales en Git.
- Respuesta a incidentes y trazabilidad de exportaciones.
- Revisión legal y organizacional conforme a la normativa peruana aplicable.

---

## 15. Rendimiento y escalabilidad

### Riesgos

- Consultas complejas y SQL voluminoso en cada request.
- Paginación y conteos que pueden repetir consultas caras.
- Potenciales N+1 en relaciones y transformaciones.
- Caché de permisos rápida pero incorrectamente indexada.
- Generación PDF/DOCX y streaming ejecutados desde procesos web.
- Timeouts de hasta 600 segundos.
- HLS/FFmpeg iniciado desde la solicitud web; puede agotar procesos.
- Varias integraciones sin circuit breaker estandarizado.

### Recomendaciones

- Medir p50/p95/p99 por endpoint y consulta.
- Instrumentar consultas lentas y planes de ejecución.
- Mover generación pesada a colas con progreso.
- Aislar streaming/video en servicio específico o gateway dedicado.
- Cachear catálogos y reportes con claves correctas e invalidación explícita.
- Usar circuit breaker, timeouts y backoff con jitter en integraciones.
- Limitar concurrencia por usuario y por recurso costoso.

---

## 16. Fortalezas encontradas

El proyecto no parte de cero. Hay decisiones valiosas que conviene preservar:

- Migración incremental en lugar de reescritura total.
- Laravel moderno y versiones recientes fijadas en lock.
- Contratos e inyección en servicios centrales.
- Query Builder y bindings en la mayoría de accesos revisados.
- Allowlist para varias columnas dinámicas.
- Regeneración/invalidez de sesión correcta en login/logout.
- Validaciones backend en múltiples flujos.
- Transacciones en operaciones multitabla.
- PIDE con proveedores, DTOs, rate limit y auditoría específica.
- OMAPED público usa tokens temporales ligados a IP y compara el archivo autorizado.
- Notificaciones abstraídas y testeadas con proveedores fake.
- Pruebas que cubren parte de servicios transversales.
- PHPStan, Pint, Pest y lockfiles ya forman parte del proyecto.

Estas fortalezas permiten corregir la plataforma de forma evolutiva.

---

## 17. Plan de remediación priorizado

### P0 — Inmediato, antes de cualquier exposición

| Acción | Criterio de aceptación |
|---|---|
| Rotar secretos y tokens | Ningún valor anterior autentica; evidencia de revocación documentada. |
| Eliminar secretos del código e historial | Escáner de secretos limpio en HEAD e historial reescrito/coordinado. |
| Cerrar `/dev-config` | La ruta no existe o responde 404 fuera de desarrollo local. |
| Restringir `mod_seguridad` | Usuario sin permiso obtiene 403 en cada ruta directa. |
| Corregir clave de caché | Incluye usuario, perfil y cargo; prueba de aislamiento pasa. |
| Activar hash moderno | Nuevos cambios usan bcrypt/Argon; login migra hashes legacy. |
| Retirar adjuntos de `public/` y Git | Archivos servidos solo mediante controlador autorizado. |
| Cerrar Redis al host | Puerto no publicado o protegido con ACL y red restringida. |

### P1 — Primera semana

| Acción | Criterio de aceptación |
|---|---|
| Middleware/Policies transversales | Toda ruta de negocio tiene permiso de módulo/acción. |
| Autorización por expediente/adjunto | IDs ajenos retornan 404/403 y no revelan existencia. |
| Rate limit | Login, OTP, QR y APIs costosas limitados y monitorizados. |
| Errores seguros | Ninguna respuesta 5xx devuelve excepción/SQL/ruta. |
| TLS cámaras | Verificación peer/host activa con CA confiable. |
| Worker y scheduler | Servicios Docker dedicados con health check/restart. |
| Cabeceras base | `nosniff`, frame policy, referrer policy y HSTS bajo HTTPS. |

### P2 — Primer mes

- Implementar servicio central de adjuntos.
- Añadir antivirus y estados de cuarentena.
- Crear pruebas de autorización negativa para todos los módulos.
- Añadir CI con Pint, PHPStan, Pest, audit PHP/NPM y escaneo de secretos.
- Dividir `routes/web.php` por módulo.
- Extraer casos de uso de los cinco controladores/servicios más grandes.
- Alinear documentación con el estado real.
- Crear estrategia de migraciones para extensiones del esquema legacy.
- Implementar auditoría transversal de acciones privilegiadas.

### P3 — 60 a 90 días

- Modularizar frontend y eliminar handlers inline.
- CSP estricta.
- Observabilidad con trazas, métricas y correlation IDs.
- Pruebas de carga y concurrencia.
- Separar streaming de cámaras del proceso PHP web.
- Implementar idempotencia y máquina de estados de expedientes.
- Revisar privacidad, retención y anonimización.
- Despliegue inmutable con SBOM, firmas y rollback probado.

---

## 18. Arquitectura objetivo recomendada

Mantener un monolito modular es razonable; no se recomienda dividir prematuramente en microservicios. La prioridad es establecer fronteras internas firmes.

```text
app/
  Modules/
    Tramites/
      Application/       Casos de uso
      Domain/            Entidades, estados, políticas
      Infrastructure/    Repositorios SQL/legacy
      Http/              Controllers, Requests, Resources
    Seguridad/
    Helpdesk/
    Omaped/
    Reportes/
  Shared/
    Auth/
    Attachments/
    Audit/
    Notifications/
    Documents/
    Integrations/
```

Cada request debería seguir:

```text
Route middleware
  -> FormRequest (validación + autorización básica)
    -> Policy (recurso/acción)
      -> Use case
        -> Transaction
          -> Repository
          -> Domain events / outbox
```

Para integraciones externas, usar interfaces, timeouts, reintentos controlados, circuit breaker y métricas. Para notificaciones confiables, considerar patrón outbox para que el cambio de estado y el evento a encolar sean atómicos.

---

## 19. Checklist de salida a producción

### Seguridad

- [ ] Todos los secretos fueron rotados.
- [ ] No hay secretos en repositorio/historial/imagen.
- [ ] SHA-1 está en proceso de retiro con fecha límite.
- [ ] Matriz de autorización probada.
- [ ] Adjuntos privados y autorizados por objeto.
- [ ] Rate limits activos.
- [ ] Errores internos no llegan al cliente.
- [ ] TLS y cabeceras endurecidos.
- [ ] Redis y bases no están expuestos públicamente.
- [ ] Logs no contienen claves, tokens, documentos ni contraseñas.

### Calidad

- [ ] Pest pasa en CI.
- [ ] PHPStan pasa al nivel acordado.
- [ ] Pint/formatter pasa.
- [ ] Composer/NPM audit pasa o excepciones están justificadas.
- [ ] Escaneo SAST y secretos pasa.
- [ ] Pruebas E2E críticas pasan.

### Operación

- [ ] Worker y scheduler activos.
- [ ] Health checks funcionales.
- [ ] Métricas y alertas configuradas.
- [ ] Backups y restauración probados.
- [ ] Migraciones probadas en copia representativa.
- [ ] Rollback documentado.
- [ ] Límites de recursos definidos.

### Datos

- [ ] No hay documentos reales en Git.
- [ ] Datos de pruebas anonimizados.
- [ ] Retención y eliminación definidas.
- [ ] Accesos sensibles auditados.

---

## 20. Conclusión

SIGTRAM3 tiene una base funcional amplia y una intención arquitectónica correcta, pero actualmente existe una brecha significativa entre la arquitectura descrita y los controles realmente ejecutados. El mayor problema no es el uso de código legacy en sí, sino que las fronteras críticas —secretos, autorización, contraseñas y archivos— todavía son inconsistentes.

La remediación debe comenzar por **credenciales, control de acceso, caché de permisos, hashes de contraseña y adjuntos**. Una vez cerrados esos riesgos, el mejor retorno técnico vendrá de modularizar gradualmente los controladores grandes, consolidar consultas y archivos en servicios transversales, y construir una suite de autorización negativa que evite regresiones.

Con un ciclo P0/P1 disciplinado, el proyecto puede pasar de un prototipo/migración funcional de alto riesgo a una base apta para piloto controlado. La salida a producción debe condicionarse a evidencia verificable de que los hallazgos críticos y altos fueron corregidos y probados.

---

## Anexo A — Archivos clave revisados

- `routes/web.php`
- `bootstrap/app.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/mod_seguridad/*`
- `app/Http/Controllers/mod_gm/mod_tram_exte/*`
- `app/Http/Controllers/mod_gp/omaped/OmapedController.php`
- `app/Services/Core/*`
- `app/Repositories/mod_sistema/PermisosRepository.php`
- `app/Support/SessionKeys.php`
- `config/auth.php`
- `config/session.php`
- `config/database.php`
- `config/pide.php`
- `config/camara_visor.php`
- `docker-compose.yml`
- `Dockerfile`
- `docker/nginx/default.conf`
- `tests/*`
- `docs/Arquitectura.md`
- `docs/sigtram3_planificacion_y_requerimientos.md`
- `docs/sql/schema_sigtram3.sql`

## Anexo B — Comandos de verificación recomendados para CI

```bash
composer validate --strict
composer audit --locked
php artisan test --parallel
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
npm ci
npm audit
npm run build
```

Añadir además un escáner de secretos, SAST para PHP/JavaScript, análisis de imagen Docker y generación de SBOM. Los comandos deben ejecutarse con credenciales y bases de datos de prueba aisladas, nunca con valores municipales reales.
