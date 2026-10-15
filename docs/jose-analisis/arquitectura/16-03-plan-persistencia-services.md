# SPEC 16-03 — Persistencia de Services a Repositories

## 1. Objetivo

Completar la separación arquitectónica. Esta fase es obligatoria: quitar DB de Controllers pero conservar SQL en Services no cierra el refactor.

## 2. CorreoInternoTransaccionalService

Archivo: `app/Services/mod_gene/CorreoInternoTransaccionalService.php`.

Mover sus accesos a datos al `TramiteInternoRepositoryInterface`. Mantener en el Service la secuencia de negocio de:

- `obtenerDatosTransaccionales`;
- `obtenerCorrelativoInterno`;
- creación del documento interno;
- `ejecutarRecepcionar`;
- `ejecutarAsignar`;
- `ejecutarDerivar`;
- `ejecutarFinalizar`;
- `ejecutarDevolver`;
- `ejecutarReiterar`;
- `obtenerDatosHojaRuta`.

### Familias de métodos Repository requeridas

No crear un método genérico `insertar(string $tabla, array $datos)`. Usar operaciones de dominio:

```php
obtenerCargosDeArea(...)
obtenerAreasDestino(...)
obtenerTiposDocumentoInterno(...)
obtenerAnioPorNumero(...)
obtenerUltimoAnio(...)
obtenerTipoDocumentoInterno(...)
obtenerSemillaInternaParaBloqueo(...)
crearSemillaInterna(...)
obtenerReferenciaDocumentoInterno(...)
crearDocumentoInterno(...)
crearMovimientoInterno(...)
desactivarMovimientoInternoEsperado(...): int
registrarSeguimientoInterno(...): void
obtenerCargoDestino(...)
obtenerArea(...)
obtenerDocumentoParaHojaRuta(...)
obtenerMovimientosHojaRuta(...)
obtenerSiguienteSecuenciaInterna(...)
```

Los parámetros concretos deben derivarse de los arrays actualmente insertados. Para payloads grandes, crear DTOs inmutables bajo `app/DTOs/mod_gene/`; no pasar `Request` ni arrays sin documentar.

### Invariantes

- conservar `lockForUpdate` de semilla y movimientos;
- `desactivarMovimientoInternoEsperado` retorna filas afectadas y el Service exige exactamente las esperadas;
- insertar el nuevo movimiento sólo después de desactivar correctamente el origen;
- conservar escritura en `p_sgmto_log` y su orden respecto de la transición;
- rollback también debe activar la limpieza de archivos ya prevista; no mover filesystem al Repository;
- no alterar significado de `in_esta` ni filtrar históricos.

## 3. TramiteExternoService

Archivo: `app/Services/mod_gm/mod_tram_exte/TramiteExternoService.php`.

Mover todos los accesos a tablas hacia `TramiteExternoRepositoryInterface`; mantener en Service reglas y transacción de:

- `recepcionar`;
- `derivar`;
- `resolverCargoParaArea`;
- `asignar`;
- `enviar`;
- `finalizar`;
- `devolver`;
- `archivar`;
- `prestar`;
- `retornarPrestamo`;
- `redirigir`.

### Métodos Repository de persistencia

```php
desactivarMovimientoExternoEsperado(...): int
crearMovimientoExterno(...): int
obtenerCargoPrincipalDeArea(...): ?object
validarCargoEnArea(...): bool
obtenerContactoNotificacionExpediente(...): ?array
registrarSeguimientoExterno(...): void
```

Reutilizar `obtenerMovimientosVigentesParaBloqueo(...)`; no recrear la consulta en el Service. Si las operaciones necesitan distintos scopes, ampliar explícitamente la firma o crear métodos con semántica diferente y prueba que justifique la separación.

### Invariantes

- cada lote se resuelve y bloquea una sola vez;
- cantidad solicitada = cantidad autorizada/bloqueada;
- compañía, cargo y estado se validan antes de escribir;
- desactivación debe afectar exactamente una fila por rama esperada;
- múltiples `in_esta = 1` son válidos cuando el expediente fue derivado a varias áreas; no colapsar ramas;
- histórico conserva todos los `in_esta = 0/1` y orden descendente ya acordado;
- notificación ocurre después de persistencia coherente y no sustituye el rollback de datos críticos.

## 4. Regla de transacción

El patrón final permitido en Service es equivalente a:

```php
return DB::transaction(function () use ($comando) {
    $movimientos = $this->repository->obtenerMovimientosVigentesParaBloqueo(...);
    // validar reglas de negocio
    $afectados = $this->repository->desactivarMovimientoExternoEsperado(...);
    // exigir cantidad exacta
    return $this->repository->crearMovimientoExterno(...);
});
```

No se permite `DB::table`, `$this->db->table`, SQL, Builder o nombres de tabla dentro del Service.

## 5. Tests obligatorios

1. Arquitectura: ambos Services sin `DB::table`, `->table`, `select`, `insert`, `update`, `delete` ni nombres de tabla. Permitir únicamente `DB::transaction` o un `TransactionManager` inyectado.
2. Por cada transición: happy path, no autorizado, estado incorrecto, lote parcial, cero filas actualizadas y excepción tras desactivación.
3. Ramas múltiples externas: no eliminar ni fusionar movimientos activos legítimos.
4. Interno: correlativo concurrente y rollback con archivo temporal simulado.
5. Verificar orden exacto de llamadas al Repository mediante mocks cuando ese orden sea una invariante.

