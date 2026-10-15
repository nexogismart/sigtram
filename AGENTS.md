# Reglas de Ingeniería SIGTRAM3

## 🧬 STACK & ARQUITECTURA
- **Stack:** PHP 8.4+, Laravel 13, PostgreSQL, Tailwind CSS, Redis (para colas).
- **Patrón:** MVC + Service Layer + Ports & Adapters + Repositories (cuando aplica) + FormRequests + Event-driven.
- **ARCH-01/02/03:** Controladores delgados (solo reciben, validan y responden). Lógica de negocio exclusiva en `app/Services/`.
- **ARCH-04/14:** Queries complejas (3+ tablas, SQL crudo o lógica de negocio en BD) exigen Repository en `app/Repositories/mod_<area>/` y contrato en `app/Contracts/Repositories/`.
- **ARCH-05/10:** Notificaciones asíncronas vía `NotificationCore::send()`, cola Redis. Prohibido usar librerías SMTP o cURL directos.
- **ARCH-06:** APIs externas desacopladas mediante interfaces (Puertos) y adaptadores (Implementaciones) que retornen DTOs.
- **ARCH-08:** Plantillas Office en `storage/app/plantillas/mod_<area>/{nombre}.docx`. Generación centralizada con `DocumentGeneratorService`.
- **ARCH-09:** Contexto activo (cargo, compañía, área) persistido en sesión (`SessionKeys`).
- **ARCH-11/12:** Cachear permisos en `user_perms_{cdgo_lgin}`. Excepciones de usuario (`r_prmsos_usrios`) vinculadas con `ON DELETE CASCADE` a `r_prmsos`.
- **ARCH-13:** Firma digital aplicada solo a PDFs. Flujo directo o flujo con edición manual de borrador Word.
- **ARCH-15:** PHPStan nivel 5+ y Pest obligatorios para garantizar compatibilidad ante actualizaciones del framework.

## 🔐 SEGURIDAD (SEC) — OWASP Top 10:2025
- **SEC-01:** Rutas protegidas con `auth`. Validación de acceso a recursos mediante Policies y Gates.
- **SEC-02/04:** APP_DEBUG=false en prod. HTTPS obligatorio. Contraseñas con `Hash::make`. PII encriptada con `Crypt::encryptString`.
- **SEC-05:** Prohibido `DB::raw()` con variables. Usar enlaces de parámetros PDO.
- **SEC-06/07:** Rate limiting en logins. Capturar excepciones críticas y retornar mensajes seguros (prohibido exponer stack traces).
- **SEC-08/09:** CSRF activo en formularios y AJAX. Encriptar DNI, dirección y celular del ciudadano (cast `'encrypted'`).
- **SEC-10/15:** Bitácora de auditoría en escrituras (trazar IP, usuario, y cambios en JSON con estado Before/After).
- **SEC-11:** Storage privado: Trámites (`storage/app/documentos/externos/` o `internos/`), Generados (`storage/app/documentos/generados/`) por `YYYY/MM/DD/`. Prohibido public/ y `storage:link`. Descarga por control de Laravel (`Storage::download()`).
- **SEC-13:** Prohibidas credenciales hardcodeadas. Leer siempre de `.env` vía configs.
- **SEC-14:** Carga de archivos delegada al `GestorAdjuntos` (UUID, verificación mime real, lista blanca extensiones y tamaño).
- **SEC-16:** Autenticación: verificar Bcrypt. Si falla, verificar SHA1 legacy y actualizar en caliente a Bcrypt.

## 💻 CONVENCIONES DE CÓDIGO (CODE)
- **CODE-01:** Declarar `declare(strict_types=1);` en la primera línea de cada archivo PHP.
- **CODE-02/03:** Usar Early Returns. Nombres descriptivos cortos en español para negocio; directorios y namespaces en inglés.
- **CODE-04:** Bloquear botón tras clic en frontend. Usar `Cache::lock` o throttling en backend para prevenir doble envío.
- **CODE-06:** Prohibido strings literales para sesión; usar constantes de `app/Support/SessionKeys.php`.
- **CODE-07:** PHPDoc obligatorio encima de firmas de métodos públicos en Services, Repositories y Controllers.

## 🧪 TESTING (TEST)
- **TEST-01:** Cobertura mínima: 80% en negocio y 100% en servicios del Core.
- **TEST-02/03:** Tests unitarios (Pest) en `tests/Unit/` (happy, error, border) y Feature Tests en `tests/Feature/` (status, 401, 403, 422).
- **TEST-04:** Conexiones externas mockeadas en tests.
- **TEST-06/09:** Usar Factories para datos de prueba. Base SQLite `:memory:` para feature tests.
- **TEST-07/10:** Pipeline CI/CD ejecuta `composer audit`, PHPStan y Pest. Bloquea merge si fallan tests o cobertura baja del 80%.
- **TEST-08:** DoD: Toda tarea completada debe incluir sus archivos de prueba unitarios/feature.

## 🎨 INTERFAZ VISUAL & DISEÑO (FE)
- **FE-01/02:** Tokens de diseño en `tailwind.config.js`. Modo Claro/Oscuro persistido en `localStorage`.
- **FE-03/04:** Micro-interacciones y responsive.
- **FE-05:** Prohibido DataTables jQuery. Listados grandes (>500) exigen paginación del servidor (`paginate()`).
- **FE-06/07:** Tematización dinámica: leer variables de marca desde sesión (`USER_COMPANY`) e inyectarlas en `:root` de `app.blade.php`.
- **FE-08:** Utilizar `ui-skills-root` ejecutando `npx ui-skills` para UI premium.

## 🗄️ BASE DE DATOS (DB)
- **DB-01/02:** Correlativos vía secuencias de PostgreSQL. Eliminación lógica (cambiar estado a `0`).
- **DB-03/08:** Transacciones explícitas (`DB::transaction()`). Conexiones externas gestionadas por `BdCore` (solo SELECT y Lazy Loading).
- **DB-04:** Prohibido `migrate` automático. DDL/DML en `doc/sql/` para ejecución manual en DBeaver.
- **DB-05:** Definir esquema en Eloquent (`protected $table = 'esquema.tabla'`).
- **DB-07:** Conexión en UTF-8. Sanitizar codificaciones legacy con `mb_convert_encoding`.

---

## 2. WORKFLOWS

### `/nuevo-modulo`
1. Identificar área (`mod_<area>`).
2. Crear FormRequest, Controller (`app/Http/Controllers/mod_<area>/`), Service (`app/Services/mod_<area>/`) y Repository si aplica.
3. Crear vista Blade responsiva compatible con modo oscuro.
4. Registrar ruta protegida en `web.php` con middleware `auth` y aplicar Gates/Policies.
5. Crear tests Pest unitarios y feature, y ejecutar `git commit -m "[mod_<area>]..."`.

### `/corregir-bug`
1. Reproducir local $\rightarrow$ Aislar capa $\rightarrow$ Revisar logs $\rightarrow$ Aplicar mínimo cambio seguro $\rightarrow$ Validar con test $\rightarrow$ Commit `[BUGFIX]...`.

### `/generar-documento`
1. Validar plantilla Word $\rightarrow$ Invocar `DocumentGeneratorService` $\rightarrow$ Generar PDF headless con LibreOffice $\rightarrow$ Actualizar estado BD a PDF.

### `/firmar-documento`
1. Validar estado PDF $\rightarrow$ Invocar `FirmaCore::firmarLocal()` (ReFirma PC) $\rightarrow$ Validar firma. Si hay error administrativo, aplicar `FIR_ERR` con clave del jefe de área.

### `/migrar-desde-sigtram`
1. Mapear lógica legacy $\rightarrow$ Crear script SQL manual $\rightarrow$ Programar FormRequest/Controller/Service/Model en Laravel $\rightarrow$ Probar paridad.

### `/agregar-nucleo`
1. Definir interfaz (Puerto) $\rightarrow$ Crear adaptador real y mock $\rightarrow$ Vincular en ServiceProvider con .env $\rightarrow$ Tests al 100% $\rightarrow$ Documentar.
