# FB-01 — Auditoría total de fallbacks, aliases y variables ambiguas

## Baseline observado

- `app/`: 235 PHP; 107 archivos contienen `??` o `?:`.
- `resources/js/`: 23 JS; 21 contienen `||` o `??`.
- Estos números son referencia, no meta de eliminación. Antigravity debe volver a medirlos.

## Inventario obligatorio

Buscar en todo código productivo, excluyendo `vendor`, `node_modules`, tests y documentación:

- PHP: `??`, `?:`, ternarios, `isset`, `empty`, `property_exists`, `data_get` y casts con valor alternativo.
- JavaScript: `||`, `??`, ternarios, optional chaining y propiedades alternativas.
- Request: cadenas como `validated('a') ?? input('b')`.
- Sesión/Auth: cualquier fuente alternativa de identidad o contexto.
- SQL/filas: `$row->campo_a ?? $row->campo_b`.
- JSON/frontend: `item.a || item.b`, rutas o IDs alternativos.

Generar `inventario-fallbacks.md` con: archivo/línea, expresión, significado esperado, fuentes involucradas, clasificación, decisión y prueba que la cubre.

## Clasificación obligatoria

1. **SECURITY_IDENTITY**: alterna login/usuario/compañía/cargo/área/perfil. Eliminar y usar contexto canónico.
2. **CONTRACT_ALIAS**: dos nombres para el mismo dato entre SQL/DTO/API/frontend. Elegir un nombre canónico y corregir productores/consumidores.
3. **REQUEST_COMPAT**: aliases de payload. Normalizar una sola vez en FormRequest; Controller/Service reciben un solo nombre.
4. **HARD_CODED_BUSINESS**: valor funcional fijo, área, compañía, estado, tipo o ruta. Resolver desde catálogo/config/contrato confirmado.
5. **DISPLAY_DEFAULT**: texto visual cuando el dato realmente puede ser nulo. Puede conservarse, pero debe ser explícito y testeado.
6. **TECHNICAL_DEFAULT**: inyección opcional, colección vacía o resultado de biblioteca. Puede conservarse si el tipo y motivo son claros.
7. **BOOLEAN_LOGIC**: `||` lógico real, no fallback. No modificar.
8. **NUMERIC_PARSE**: `parseInt(x) || 0`; decidir si cero es dato válido y usar helper tipado/`Number.isFinite` cuando corresponda.
9. **DEAD_OR_UNKNOWN**: sin evidencia. No adivinar; bloquear el caso y consultar MCP/legacy.

## Regla central

El objetivo no es llegar a cero operadores, sino a cero ambigüedad. Cada fallback conservado debe tener una sola semántica, tipos compatibles y una prueba. Prohibido cambiar masivamente `||` por `??` o eliminar `??` sin comprobar el contrato.

## Aceptación

- Inventario completo y trazable.
- Todos los SECURITY_IDENTITY, CONTRACT_ALIAS y REQUEST_COMPAT asignados a una corrección de CI-02/03/04 o FB-02.
- Ningún nombre de columna se decide sin MCP SIGTRAM lectura.
