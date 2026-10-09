# Manual de Implementación: Servicio PideCore (SIGTRAM3)
> Orquestador centralizado de consulta de identidad nacional con lógica de cascada y tolerancia a fallos.

---

## 🧬 Introducción y Objetivos
El servicio `PideCore` proporciona una interfaz unificada para realizar consultas oficiales de identidad de ciudadanos (DNI) y empresas (RUC) en el sistema **SIGTRAM3**. 

Sus principales directrices arquitectónicas son:
1.  **Integridad y Frescura:** Siempre consulta prioritariamente la fuente oficial del Estado (PIDE) para garantizar la exactitud en vivo del dato.
2.  **Tolerancia a fallos:** Si las APIs externas están caídas o sufren timeouts, conmuta automáticamente a la base de datos municipal local y al proveedor de respaldo Decolecta.
3.  **Prevención de Sobreescritura Silenciosa (Discrepancia):** Si los datos devueltos por RENIEC/SUNAT difieren de los locales, notifica al operador permitiendo decidir si actualiza el sistema.
4.  **Rate Limiting Integrado:** Bloquea bots o intentos de scraping interno controlando un límite de 30 consultas por minuto en memoria (RateLimiter/Redis).

---

## 🏛️ Arquitectura del Servicio (Puertos y Adaptadores)

El diseño se desacopla a través de contratos (interfaces) para permitir una fácil extensión y mantenimiento:

```
app/
├── DTOs/
│   ├── PersonaDto.php             ← DTO inmutable para Personas Naturales
│   └── EmpresaDto.php             ← DTO inmutable para Personas Jurídicas
├── Contracts/
│   └── Services/
│       ├── PideCoreInterface.php      ← Puerto del orquestador central
│       └── PideProviderInterface.php  ← Puerto de los proveedores individuales
└── Services/
    └── Core/
        └── Pide/
            ├── PideCore.php           ← Orquestador (cascada, rate-limiting, auditoría)
            └── Providers/
                ├── LocalProvider.php  ← Consulta la base de datos local
                ├── PideProvider.php   ← Consulta la API REST/SOAP PIDE Oficial
                └── DecolectaProvider.php ← Consulta la API REST Decolecta (Respaldo)
```

---

## ⚙️ Variables de Entorno (.env)
El comportamiento del servicio se configura en caliente sin alterar el código fuente mediante las siguientes variables:

```env
# Habilitar/Deshabilitar proveedores
PIDE_USAR_OFICIAL=true       # true = Consultar API PIDE Oficial
PIDE_USAR_LOCAL=true         # true = Buscar localmente si el externo falla
PIDE_USAR_DECOLECTA=true     # true = Usar API Decolecta de respaldo

# Parámetros técnicos
PIDE_TIMEOUT=3               # Límite en segundos de llamadas HTTP externas
PIDE_RUC_INSTITUCIONAL=20131366702

# Credenciales oficiales de la PIDE (JSON Array para soportar múltiples operadoras/tokens)
PIDE_CREDENTIALS_POOL='[{"usuario":"70068099","clave":"secret_pide"}]'

# Pool de tokens Decolecta (separados por comas para rotación automática en caliente)
DECOLECTA_TOKENS=sk_token_123,sk_token_456,sk_token_789
```

---

## 🛠️ Modos de Uso del Servicio

### Caso de Uso A: Consulta Simple (Informativa / Ver Detalles)
Se utiliza para pantallas de visualización de perfiles o consultas simples. **No realiza ninguna inserción** en la base de datos local si el ciudadano es nuevo en la municipalidad.

```php
use App\Contracts\Services\PideCoreInterface;

class DetalleCiudadanoController extends Controller 
{
    public function __construct(protected PideCoreInterface $pideCore) {}

    public function ver(string $dni) 
    {
        // Ejecuta la consulta en cascada pero no registra al ciudadano
        $persona = $this->pideCore->consultarDetalle($dni, 'DNI');

        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => $this->pideCore->obtenerMensajeError('DNI')
            ], 404);
        }

        return view('mod_sistema.ver_persona', compact('persona'));
    }
}
```

### Caso de Uso B: Búsqueda para Trámite (Transaccional)
Se utiliza en Mesa de Partes o flujos de registro de expedientes. Necesita obligatoriamente que el ciudadano tenga un ID local (`cdgo_usrio`). 
*   **Si es nuevo:** Realiza un `INSERT` automático en `p_usrios` + `h_usrios_ntral` y genera el ID.
*   **Si ya existe pero discrepa:** Retorna una bandera `tieneDiscrepancia => true` junto con los datos locales para alertar al operador en la interfaz.

```php
use App\Contracts\Services\PideCoreInterface;
use Illuminate\Http\Request;

class RegistroTramiteController extends Controller
{
    public function __construct(protected PideCoreInterface $pideCore) {}

    public function buscarParaAsociar(Request $request)
    {
        $dni = $request->input('dni');
        $forzar = $request->boolean('forzar'); // Botón "Actualizar desde RENIEC"
        
        $forceProviders = $forzar ? ['Pide', 'Decolecta'] : null;

        $persona = $this->pideCore->buscarYRegistrarParaTramite($dni, 'DNI', $forceProviders);

        if (!$persona) {
            $ultimoAviso = $this->pideCore->obtenerUltimoAviso();
            return response()->json([
                'success' => false,
                'error_code' => $ultimoAviso, // Ej: 'EXTERNAL_SERVICE_UNAVAILABLE'
                'desbloquear_formulario' => ($ultimoAviso === 'EXTERNAL_SERVICE_UNAVAILABLE'),
                'message' => $this->pideCore->obtenerMensajeError('DNI')
            ], 404);
        }

        // Si se detectaron discrepancias entre RENIEC y lo local
        if ($persona->tieneDiscrepancia) {
            return response()->json([
                'success' => true,
                'discrepancia' => true,
                'data' => $persona->toArray() // Envía datos oficiales y datos locales para el modal comparativo
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $persona->toArray() // Incluye cdgo_usrios listo para asociar al expediente
        ]);
    }
}
```

---

## 🔒 Rate Limiting y Seguridad
Para evitar ataques de denegación de servicio (DoS) o web scraping masivo por parte de usuarios internos del sistema:
*   El servicio utiliza el orquestador `RateLimiter` de Laravel.
*   El límite está fijado en **30 consultas por minuto** por cada ID de login (`cdgo_lgin`) o IP de terminal.
*   Si se excede el límite, el backend lanza una excepción `RuntimeException` de inmediato (tiempo de proceso ~0.5ms) bloqueando la llamada antes de realizar conexiones externas SOAP/REST o de base de datos.

---

## 🗄️ Auditoría y Base de Datos (h_pide_consltas)
Toda consulta ejecutada (exitosa, fallida o no encontrada) se audita físicamente de forma automática en la tabla `sistema.h_pide_consltas`:
*   **Columnas principales registradas**:
    *   `nu_documento`: Número de DNI o RUC consultado.
    *   `tp_documento`: Tipo de documento (`DNI`/`RUC`).
    *   `cdgo_lgin`: ID de login del usuario municipal (obtenido de `SessionKeys::USER_ID`), o `NULL` si es anónimo/test.
    *   `ip_terminal`: Dirección IP de origen.
    *   `fuente`: Proveedor de la respuesta (`PIDE`, `API DECOLECTA`, `MOCK`).
    *   `pide_user_used`: Token/usuario específico utilizado (facilita la trazabilidad del consumo de cuotas).
    *   `exito`: `true` (encontrado y devuelto) o `false` (error o inexistente).
*   **Aislamiento y Robustez**: El registro de auditoría está blindado con un bloque `try-catch`. Si ocurre un fallo al insertar en la tabla de auditoría, el error se reporta de forma silenciosa en `laravel.log` sin interrumpir el flujo del trámite principal del operador.
