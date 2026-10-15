# Prompt breve para Antigravity

```text
ACTÚA COMO LEAD LARAVEL ARCHITECT Y EJECUTOR FORENSE.

Proyecto: C:\laragon\www\migracion_sigtram\sigtram_v3

Implementa, en el orden definido, todas las tareas CI, FB, UI y QA de:
docs/jose-analisis/arquitectura/contexto-identidad/00-spec-maestra.md

Lee completos 00-spec-maestra.md y los archivos 01…05 y 07…11 antes de editar. Lee también .agents/AGENTS.md. Usa el MCP de sólo lectura “SIGTRAM lectura” en CI-01 y cuando FB encuentre columnas dudosas: nada puede inferirse. Si falta el MCP, marca bloqueadas sólo las tareas dependientes; no inventes.

Respeta el árbol sucio. No uses reset/checkout/clean; no commit/push/pull/merge/rebase; no DDL/DML/migrate/seed; no cambies almacenamiento, descargas, QR, endpoints ni comportamiento HTTP observable. Normaliza contratos internos conservando cualquier compatibilidad pública exclusivamente en la frontera. No hagas reemplazos globales ciegos.

Objetivo: identidad/contexto inequívocos; un DTO, Service, Repository y SessionKeys canónicos; aliases legacy confinados a un bridge; todos los fallbacks/aliases de PHP/JS/Blade clasificados y resueltos o justificados con prueba; vistas interactivas internas/externas sin JS, CSS ni eventos inline, preservando correctamente las excepciones de PDF, impresión y email. No reemplaces operadores de forma masiva: corrige contratos por frontera y caso de uso. Mantén pruebas verdes después de cada tarea.

No declares “limpio” por pasar tests: completa los inventarios, búsquedas negativas, checklist funcional y evidencia de QA-01. Estado máximo READY_FOR_REVIEW; no afirmes 100 % producción con pruebas reales omitidas.
```
