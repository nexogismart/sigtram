# Protocolo de Rotación de Credenciales — SIGTRAM3

> **Fecha:** 2026-10-15  
> **Auditoría:** Auditoría Forense y Cierre Previo a Arquitectura Dual de Archivos  
> **Clasificación:** Confidencial / Administrativa  
> **Regla de Seguridad:** Este documento lista **únicamente los nombres de las variables de entorno** que contenían valores por defecto o estuvieron expuestas en archivos de configuración versionados en repositorios previos. **Bajo ninguna circunstancia se documentan ni registran valores secretos, contraseñas o tokens reales**.

---

## 1. Resumen Ejecutivo
Durante la auditoría forense del repositorio `sigtram_v3` y el monolito histórico `sigtram`, se detectó la presencia de contraseñas de bases de datos auxiliares, tokens de servicios externos y credenciales de interoperabilidad configuradas como valores de fallback (valores por defecto) dentro del código PHP (`config/*.php`) y archivos de ejemplo (`.env.example`).

Todos los archivos de configuración en el repositorio han sido saneados (eliminando cualquier fallback por defecto y forzando lectura estricta de variables de entorno). Se requiere que el equipo de Infraestructura y Seguridad de TI de la Municipalidad proceda con la **rotación inmediata en los servidores de origen** de los secretos asociados a las variables listadas a continuación.

---

## 2. Inventario de Variables Comprometidas a Rotar

### 2.1 Conexiones a Bases de Datos Auxiliares (BdCore / PostgreSQL, MySQL, SQL Server)
Las siguientes variables de entorno deben tener sus contraseñas cambiadas en sus respectivos motores de base de datos y actualizarse en el archivo `.env` del servidor de producción:

1. `DB_PREDIO_PASSWORD` (Base de datos de Rentas/Predios `db_simun`)
2. `DB_CAMARA_PASSWORD` (Base de datos de Cámaras/Seguridad Ciudadana `ppiedra`)
3. `DB_GIS_PASSWORD` (Base de datos Catastral/GIS `mdpp_gis`)
4. `DB_RENIEC_PASSWORD` (Base de datos de réplica local RENIEC `reniec`)
5. `DB_ASTERISK_PASSWORD` (Base de datos de telefonía IP Asterisk `asteriskcdrdb`)
6. `DB_SATMUNXP_PASSWORD` (Base de datos tributaria SATMUN SQL Server)
7. `DB_SIGA_PASSWORD` (Base de datos de gestión administrativa SIGA SQL Server)

### 2.2 Plataforma de Interoperabilidad del Estado (PIDE) y Servicios Externos
1. `PIDE_CREDENTIALS_POOL` (Credenciales y claves de usuarios asignados para consultas PIDE)
2. `PIDE_SUNARP_CLAVE` (Clave de acceso al servicio de consultas vehiculares/registrales SUNARP)
3. `DECOLECTA_TOKENS` (Tokens de autenticación del servicio decolecta)
4. `WHATSAPP_TOKEN` (Token de la pasarela de notificaciones Whapi Cloud)
5. `CAMARA_VISOR_PASSWORD` (Credencial de acceso al visor de streaming de cámaras)

---

## 3. Acciones de Mitigación Implementadas en Código
1. **Sanitización de `config/database.php`:** Todos los fallbacks de contraseñas, bases de datos y usuarios fueron reemplazados por cadenas vacías y referencias puras a `env(...)`.
2. **Sanitización de `config/pide.php`:** El array por defecto `credentials_pool` fue vaciado y configurado para carga opcional desde variable de entorno serializada. Las claves de SUNARP fueron despojadas de valores fallback.
3. **Sanitización de `config/whatsapp.php`:** El token por defecto de Whapi fue eliminado (`''`).
4. **Sanitización de `config/camara_visor.php`:** Usuario y password por defecto eliminados.
5. **Sanitización de `.env.example`:** `APP_DEBUG=false`, todos los comentarios con credenciales reales fueron sustituidos por placeholders neutrales vacíos.
6. **Separación Criptográfica de QR:** Se confirmó que `QR_KEY` es una clave criptográfica independiente de `APP_KEY`, sin fallback embebido en código.

---

## 4. Procedimiento para el Administrador de Infraestructura
1. Cambiar las contraseñas en los motores de base de datos involucrados (PostgreSQL, MySQL, SQL Server).
2. Solicitar revocación y reemisión de tokens ante los proveedores externos (Whapi Cloud, PIDE / SUNARP, Decolecta).
3. Desplegar los nuevos valores en el archivo `.env` del servidor productivo asegurando permisos `600` (lectura exclusiva del usuario web).
4. Ejecutar `php artisan config:cache` para regenerar la caché de configuración en el entorno de producción.
