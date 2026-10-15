# Contrato estático del esquema existente — Correo Externo

## Propósito

Evitar que la migración invente tablas o columnas. Este documento se construye únicamente con evidencia del repositorio y del legacy; no autoriza conexiones ni cambios de base de datos.

## Evidencia confirmada en el código existente

| Concepto | Tabla existente evidenciada | Columnas principales evidenciadas | Fuente |
|---|---|---|---|
| Cabecera del expediente externo | `p_dto_trmtes` | `cdgo_dto_trmte`, `cdgo_cmpnia`, `nu_expe_todo`, `nu_docu_trmte`, `nu_foli_trmte`, `fe_ingr_trmte`, `de_obse_trmte`, `de_obse_todo`, `cdgo_usrios`, `cdgo_tpo_trmte`, `cdgo_area` | `App\Models\DtoTramite` y controladores externos existentes |
| Movimiento/histórico | `r_trmtes_dcmntos` | `cdgo_trmte_dcmnto`, `cdgo_dto_trmte`, `cdgo_crgo_sa`, `cdgo_crgo_en`, `cdgo_estdo_trmte`, `fe_rcbdo_trmte`, `de_obse`, `in_esta`, `de_urls_imag`, `cdgo_lgin`, `nu_foli_anex` | `App\Models\TramiteDocumento` y controladores externos existentes |
| Estado | `a_estdos_trmtes` | `cdgo_estdo_trmte`, `dc_estdo_trmte` | consultas existentes del módulo |
| Cargos y áreas | `p_crgos`, `a_areas` | relación por `cdgo_crgo`/`cdgo_area` | modelos y consultas existentes |

## Identificadores no válidos detectados

- `m_tramite`: PostgreSQL responde `42P01 undefined_table`; no debe crearse. La cabecera externa evidenciada es `p_dto_trmtes`.
- `d_tramite`: solo aparece en el repositorio nuevo; no tiene modelo ni uso previo que confirme su existencia.
- `m.nu_dto_trmte`: no pertenece al contrato evidenciado de expediente externo; el número existente es `p_dto_trmtes.nu_expe_todo`.
- `p.nu_flio`: la columna evidenciada es `p_dto_trmtes.nu_foli_trmte`.

## Regla de compatibilidad

Antes de usar una tabla o columna, debe existir al menos una de estas evidencias:

1. modelo Laravel activo que la mapee;
2. controlador/repositorio del proyecto que ya funcione con ella;
3. consulta legacy correspondiente;
4. resultado de un inventario manual entregado por el usuario.

Un test con `Mockery` no confirma que una tabla o columna exista. Los mocks deben verificar también los nombres contractuales establecidos en este documento.

## Resolución requerida

1. Sustituir todos los joins a `m_tramite` por el contrato real de `p_dto_trmtes`, ajustando aliases y campos.
2. Eliminar `d_tramite` y resolver solicitante/contacto desde las relaciones existentes de `p_dto_trmtes`, `p_usrios`, `h_usrios_crreo` u otras fuentes ya evidenciadas por el legacy.
3. Sustituir `nu_dto_trmte` por `nu_expe_todo` donde represente el número del expediente externo.
4. Sustituir `nu_flio` por `nu_foli_trmte` cuando represente folios de la cabecera.
5. Revisar todas las demás tablas/columnas nuevas con la misma regla; no limitar la corrección al primer error mostrado.
6. Añadir pruebas estáticas de contrato que fallen si reaparecen `m_tramite`, `d_tramite`, `nu_dto_trmte` o `nu_flio` dentro del repositorio de Correo Externo.

## Verificación manual opcional

Si el usuario desea confirmar el inventario directamente, deberá ejecutar personalmente una consulta de solo lectura. Antigravity no debe conectarse a la BD ni ejecutar el SQL.

