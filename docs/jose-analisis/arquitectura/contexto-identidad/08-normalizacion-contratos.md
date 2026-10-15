# FB-02 — Normalización de contratos y fallbacks

## Trabajo por frontera

1. **BD → Repository:** seleccionar aliases SQL canónicos y retornar DTOs/records tipados; no exponer filas con nombres variables.
2. **Repository → Service:** un campo por concepto; ausencia obligatoria produce excepción de dominio, no `0`, `1`, `2`, cadena vacía o nombre institucional inventado.
3. **Request → Controller:** FormRequest acepta sólo contrato actual. Si se conserva compatibilidad temporal, normaliza en `prepareForValidation()` y rechaza valores contradictorios.
4. **Controller → JSON:** Resources/DTOs documentan un único esquema estable.
5. **JSON → JavaScript:** eliminar cadenas `a || b || c` causadas por respuestas inconsistentes. Centralizar parseo de enteros, booleanos, fechas, URLs y errores HTTP.
6. **Blade:** recibir ViewModels listos para mostrar; evitar resolver entidades, identidad o aliases dentro de la plantilla.

## Prioridad

1. seguridad e IDs;
2. escrituras y auditoría;
3. rutas/URLs y acciones;
4. estados/tipos/catálogos;
5. listados, histórico y exportación;
6. valores puramente visuales.

## Reglas

- Confirmar columnas mediante MCP SIGTRAM lectura y comportamiento mediante legacy cuando corresponda.
- Preservar nulos reales: no convertir “desconocido” en una entidad válida.
- No permitir que un fallback oculte un error de backend.
- No alterar lógica booleana correcta ni valores de presentación legítimos.
- Implementar en lotes pequeños con caracterización previa y prueba posterior.

## Guardrails

Crear pruebas estáticas/contractuales contra:

- aliases ya retirados;
- fallback entre IDs de dominios distintos;
- propiedades JSON alternativas en JS;
- rutas hardcodeadas cuando existe ruta nombrada;
- valores funcionales fijos para compañía/cargo/área/estado/tipo;
- acceso a filas dinámicas fuera del Repository/mapper.

## Aceptación

Todos los elementos del inventario FB-01 quedan `CORRECTED`, `JUSTIFIED_WITH_TEST` o `BLOCKED_WITH_EVIDENCE`; ninguno queda sin clasificar.
