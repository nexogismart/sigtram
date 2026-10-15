# CI-00 — Refactor canónico de identidad y contexto activo

## Objetivo

Dejar una sola interpretación, tipada y verificable, de la identidad autenticada y del contexto operativo de SIGTRAM3. Un lector nuevo debe distinguir sin inferencias:

| Nombre de dominio | Fuente real esperada |
|---|---|
| `loginId` | `sistema.p_lgin.cdgo_lgin` |
| `usuarioId` | `sistema.p_usrios.cdgo_usrios` |
| `loginNombre` | `sistema.p_lgin.de_lgin` |
| `perfilId` | `sistema.p_lgin.cdgo_prfle` |
| `companiaId` | `sistema.a_cmpnias.cdgo_cmpnia` autorizada por `r_cmpnias_lgin` |
| `cargoId` | `sistema.p_crgos.cdgo_crgo` autorizado por `r_crgos_lgin` |
| `areaId` | `sistema.a_areas.cdgo_area` correspondiente al cargo activo |

Esta tabla es una hipótesis inicial: CI-01 debe confirmarla con el esquema real antes de editar código.

## Orden obligatorio

`CI-01 → FB-01 → CI-02 → CI-03 → CI-04 → CI-05 → FB-02 → UI-01 → UI-02 → QA-01`

No ejecutar tareas en paralelo. Cada fase debe terminar con pruebas dirigidas verdes y `git diff --check` antes de continuar.

## Decisiones obligatorias

1. Un único DTO canónico para interno y externo.
2. Un único servicio que resuelve y valida el contexto.
3. Un Repository tipado contiene las consultas de relaciones de identidad/contexto.
4. `SessionKeys` tiene nombres semánticos inequívocos; no existen strings de sesión dispersos.
5. La sesión no es autoridad: sólo conserva la selección. La autorización se verifica contra relaciones activas de BD.
6. Falta, incoherencia o manipulación del contexto produce fallo cerrado; nunca se adivina compañía, cargo o área.
7. La compatibilidad legacy sólo puede vivir en un adaptador de frontera explícito; nunca como fallbacks distribuidos.

## Alcance transversal adicional

- Auditar todas las expresiones de fallback/alias en PHP, JavaScript y Blade (`??`, `?:`, `||`, ternarios y cadenas de propiedades alternativas).
- Corregir contratos inestables entre SQL, Repository, DTO, JSON, Blade y JavaScript; no sustituir operadores mecánicamente.
- Extraer JavaScript, CSS y eventos inline de las vistas interactivas de trámite interno y externo hacia assets Vite modulares.
- Clasificar por separado vouchers, PDF, impresión y correo, donde el renderer puede exigir estilos autocontenidos.

## Fuera de alcance

- almacenamiento nuevo/legacy, descargas y QR;
- migraciones, DDL, DML, seeds o corrección de datos;
- rediseño visual o ruptura de contratos HTTP públicos; la normalización interna debe conservar compatibilidad en una frontera explícita;
- cambios funcionales ajenos a identidad, contratos de datos, fallbacks o separación Blade/assets.

## Estado final permitido

`READY_FOR_REVIEW`. “100 %” significa únicamente que todas las tareas y criterios verificables quedaron ejecutados; no afirmar producción ni validación total mientras existan pruebas reales omitidas o checklist manual pendiente.
