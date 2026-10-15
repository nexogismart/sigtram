# SPEC-06: Paginación Real de Bandejas, Trámites e Histórico

> **Prioridad:** P1 Rendimiento / UX  
> **Dependencias:** autorización y scopes de SPEC-05  
> **Restricción:** sin cambios de esquema ni ejecución de SQL en PostgreSQL.

## 1. Alcance

Implementar paginación real del lado servidor en:

1. Las ocho bandejas de Correo Interno: Entrada, Enviados, Todos, Recibidos, Salida, Finalizados, Sin Finalizar y Contabilidad.
2. El panel Histórico de cada trámite.
3. Buscar Trámite, verificando y normalizando su paginación existente.
4. Detalles tabulares de Reporte por Área, Reporte por Cargo y dashboard V2.
5. Listado de Secuenciales cuando el volumen lo requiera.

No se considera paginación válida cargar 100 o más filas y ocultarlas con JavaScript.

## 2. Contrato HTTP de bandejas

### Entrada

```text
POST /mod_gene/mod_tram_inter/correo_interno/tabla
```

Parámetros:

```json
{
  "bandeja": "entrada",
  "page": 1,
  "per_page": 25,
  "criterio": "tramite",
  "descripcion": "1098-2026",
  "desde": "2026-10-01",
  "hasta": "2026-10-31",
  "faltantes": false
}
```

Reglas:

- `page`: entero entre 1 y el máximo disponible.
- `per_page`: allowlist `10`, `25`, `50`; valor por defecto `25` y máximo absoluto `50`.
- `bandeja`: allowlist cerrada de ocho valores.
- Fechas y criterios validados mediante FormRequest.
- Los filtros se aplican antes de contar y paginar.
- El cambio de bandeja, criterio, fecha o descripción reinicia `page` a 1.

Respuesta:

```json
{
  "status": "success",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 248,
    "last_page": 10,
    "from": 1,
    "to": 25,
    "has_more_pages": true
  }
}
```

## 3. Consulta y orden estable

- Reemplazar `LIMIT 100` por Query Builder/Repository con `paginate()` o paginador equivalente.
- No concatenar filtros en SQL.
- Cada bandeja debe tener orden determinista y un desempate único:
  - Por fecha descendente.
  - Luego por `cdgo_trmtes_ntrnos DESC` o `cdgo_dtos_ntrnos DESC`.
- Mantener exactamente el scope de cargo, área, compañía, estado y permisos en el query de datos y en el query de conteo.
- Evitar `COUNT(*)` incorrecto por joins duplicados; contar la entidad raíz distinta de la bandeja.
- Seleccionar únicamente las columnas utilizadas por la vista.
- No usar `get()` antes de `paginate()`.

## 4. Histórico paginado

### Entrada

```text
POST /mod_gene/mod_tram_inter/correo_interno/historial
```

Parámetros:

```json
{
  "id": 12345,
  "page": 1,
  "per_page": 15
}
```

Reglas:

- `per_page`: allowlist `10`, `15`, `25`, `50`; default `15`.
- Autorizar el trámite antes de consultar movimientos.
- Mantener las nueve columnas y el banner khaki en todas las páginas.
- Precargar usuarios origen, usuarios destino y descargas únicamente para los movimientos de la página actual.
- El cálculo de `T.E` no puede depender de perder el movimiento adyacente por el corte de página. Si el algoritmo necesita el movimiento siguiente/anterior, obtener solo ese registro frontera adicional y no enviarlo como fila.
- Mantener orden cronológico compatible con `gene_estimado` y orden visual legacy.

La respuesta incluye `tramite`, `data` y `meta` con el mismo formato de bandejas.

## 5. Interfaz de paginación

Cada tabla debe mostrar:

- “Mostrando X–Y de Z”.
- Botones Primera, Anterior, Siguiente y Última.
- Ventana compacta de páginas, sin crear cientos de botones.
- Selector 10/25/50; Histórico agrega 15 como opción predeterminada.
- Estado de carga y botones deshabilitados durante la petición.
- Preservación de filtros al navegar.
- Respuesta al teclado y atributos `aria-label`.
- Foco devuelto de forma predecible al encabezado de tabla después de cambiar de página.
- Cancelación de solicitudes anteriores mediante `AbortController` para evitar que una respuesta lenta reemplace una página más reciente.

## 6. Impresión, CSV/XLSX y descargas

- Imprimir o exportar no debe reutilizar solo `data` de la página visible.
- Debe ejecutar una consulta autorizada independiente con los mismos filtros.
- La exportación completa debe tener un máximo documentado y procesamiento por chunks/cursor.
- Si supera el máximo, responder con un mensaje controlado solicitando acotar filtros.
- No colocar todos los registros en el DOM para exportarlos.

## 7. Pruebas requeridas

### Backend

1. Página 1 devuelve como máximo `per_page` elementos.
2. Página intermedia mantiene orden estable sin duplicados.
3. Última página devuelve el rango correcto.
4. `per_page=10000` es rechazado o normalizado al máximo.
5. Bandeja inválida es rechazada.
6. Los filtros permanecen activos en el conteo y los datos.
7. Un usuario no puede paginar registros de otra compañía, cargo o área.
8. Historial no expone movimientos de un trámite no autorizado.
9. El cálculo T.E es correcto en una frontera de página.
10. Las precargas U/A no generan consultas por fila.

### Frontend

1. Al cambiar filtro se vuelve a página 1.
2. Una respuesta AJAX antigua no pisa la nueva.
3. Los controles muestran `X–Y de Z` correctamente.
4. Primera/Anterior se deshabilitan en página 1.
5. Siguiente/Última se deshabilitan en la última página.
6. La paginación funciona con teclado.

Las pruebas deben usar mocks/fakes o fixtures aislados y no conectarse a PostgreSQL.

## 8. Criterios de aceptación

```gherkin
Scenario: Cargar la primera página de Entrada
  Given el usuario autenticado tiene 248 trámites autorizados en Entrada
  When solicita page 1 con per_page 25
  Then el servidor retorna como máximo 25 filas
  And meta.total es 248
  And meta.last_page es 10
  And no se cargan las 223 filas restantes en memoria del navegador

Scenario: Paginar el Histórico conservando T.E
  Given un trámite autorizado contiene más de 15 movimientos
  When el usuario navega a la segunda página del Histórico
  Then conserva las 9 columnas y la cabecera khaki
  And T.E se calcula considerando el movimiento frontera necesario
  And solo se consultan usuarios y auditorías de la página actual

Scenario: Evitar fuga por paginación
  Given un usuario de la compañía A
  When manipula page, filtros o el identificador del trámite
  Then jamás recibe datos de la compañía B ni de cargos no autorizados
```

