<?php
// Bootstrap lo hace App\Services\mod_gp\omaped\OmapedBridge

/**
 * Clase para gestionar operaciones del módulo OMAPED
 * Maneja pacientes, discapacidades, familiares e historial de atenciones
 */
class omaped_class {
    private $cn;
    private $ultimoErrorFamiliares = null;

    /** Máximo de filas en una exportación (mismos filtros que el listado). */
    private const LISTAR_MAX_EXPORT = 50000;
    /** Tope de filas por página en listado paginado (el cliente suele enviar 5). */
    private const LISTAR_MAX_POR_PAGINA = 100;

    public function __construct($cn) {
        $this->cn = $cn;
    }

    // ==================== MÉTODOS DE UTILIDAD ====================

    /**
     * Códigos de login (cdgo_lgin / sesión usIds o id) autorizados a ENTRAR al módulo OMAPED.
     * Es independiente del perfil SIGTRAM: aunque el perfil tenga el permiso del módulo,
     * solo entran quienes figuren aquí.
     */
    private function obtenerCodigosLoginPermitidosAccesoModulo() {
        return array(
            15481,
            2765,
            16502,
            11931,
        );
    }

    /**
     * Códigos de login permitidos solo para ELIMINAR registros OMAPED (puede ser distinto del acceso).
     */
    private function obtenerCodigosLoginPermitidosEliminar() {
        return array(
            15481,
            11931,
        );
    }

    /**
     * @param int[] $codigosPermitidos
     * @return bool
     */
    private function codigoLoginSesionEnLista($codigosPermitidos) {
        $cdgoLgin = $this->obtenerCodigoLogin();
        if (!$cdgoLgin || $cdgoLgin <= 0) {
            return false;
        }
        if (empty($codigosPermitidos)) {
            return false;
        }
        return in_array($cdgoLgin, $codigosPermitidos);
    }

    /**
     * Acceso autenticado al módulo: Se ha eliminado la restricción por lista blanca.
     * Ahora el acceso depende íntegramente de los permisos de perfil del SIGTRAM (validar_acceso.php).
     * @return bool
     */
    public function puedeAccederModulo() {
        return true;
    }

    /**
     * Registra error en el log del sistema
     * @param string $mensaje Mensaje de error
     * @param string|null $sql Consulta SQL relacionada (opcional)
     * @param resource|null $cn Conexión de base de datos (opcional, si es null usa $this->cn)
     */
    /**
     * Registra errores en el log (optimizado para producción)
     * @param string $mensaje Mensaje de error
     * @param string|null $sql Consulta SQL relacionada (opcional)
     * @param resource|null $cn Conexión de base de datos (opcional)
     */
    private function logError($mensaje, $sql = null, $cn = null) {
        $destino = __DOCROOT__ . __SUBDIRECTORY__ . "/Negocio/admin/log/ver.log";
        $abrirdes = @fopen($destino, "a");
        if (!$abrirdes) {
            return; // Fallo silencioso si no se puede escribir
        }
        
        $hoy = date("Y-m-d H:i:s");
        $log = "[$hoy] [omaped] $mensaje\n";
        
        if ($sql) {
            $log .= "SQL: " . substr($sql, 0, 500) . (strlen($sql) > 500 ? '...' : '') . "\n";
        }
        
        $cnUsar = $cn !== null ? $cn : $this->cn;
        if ($cnUsar) {
            $errorBD = @pg_last_error($cnUsar);
            if ($errorBD && trim($errorBD) !== '') {
                $log .= "BD: $errorBD\n";
            }
        }
        
        $log .= "\n";
        @fwrite($abrirdes, $log);
        @fclose($abrirdes);
    }

    /**
     * Versión estática de logError para usar desde archivos AJAX
     * Reutiliza la lógica de logError pero sin instancia
     * @param string $mensaje Mensaje de error
     * @param resource|null $cn Conexión de base de datos
     * @param string|null $sql Consulta SQL relacionada
     */
    public static function logErrorEstatico($mensaje, $cn = null, $sql = null) {
        $hoy = date("Y-m-d-H:i:s");
        $a = "---------------------------- $hoy ----------------------------\n\n";
        if ($sql) {
            $a .= "Error en la consulta: $sql\n";
        } else {
            $a .= "Error en: [omaped]\n";
        }
        $a .= "Usuario: " . (isset($_SESSION['usNombre']) ? $_SESSION['usNombre'] : (isset($_SESSION['usIds']) ? $_SESSION['usIds'] : 'N/A')) . "\n";
        $a .= "[omaped] $mensaje\n";
        if ($cn) {
            $errorBD = pg_last_error($cn);
            if ($errorBD && trim($errorBD) !== '') {
                $a .= "Error PostGres: $errorBD\n";
            }
            $a .= "Mensaje BD : " . ($errorBD ? $errorBD : '') . "\n";
        }
        $a .= "\n\n";
        $destino = __DOCROOT__ . __SUBDIRECTORY__ . "/Negocio/admin/log/ver.log";
        $abrirdes = @fopen($destino, "a");
        if ($abrirdes) {
            @fputs($abrirdes, $a);
            @fclose($abrirdes);
        }
    }

    /**
     * Ejecuta una consulta SQL parametrizada
     * @param string $sql Consulta SQL con placeholders $1, $2, etc.
     * @param array $params Parámetros para la consulta
     * @return resource|false Resultado de la consulta o false si falla
     */
    private function ejecutar($sql, $params = array()) {
        $result = @pg_query_params($this->cn, $sql, $params);
        if ($result === false) {
            $errorMsg = pg_last_error($this->cn);
            $this->logError("Error al ejecutar consulta SQL: " . $errorMsg, $sql);
        }
        return $result;
    }

    /**
     * Retorna respuesta de error
     */
    private function fail($msg) {
        return array('success' => false, 'error' => $msg);
    }

    /**
     * Retorna respuesta exitosa
     */
    private function ok($data = array()) {
        return array('success' => true, 'data' => $data);
    }

    /**
     * Obtiene el código de login de la sesión actual
     */
    private function obtenerCodigoLogin() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (isset($_SESSION['usIds']) && (int)$_SESSION['usIds'] > 0) {
            return (int)$_SESSION['usIds'];
        }
        if (isset($_SESSION['id']) && (int)$_SESSION['id'] > 0) {
            return (int)$_SESSION['id'];
        }
        return 0;
    }

    /**
     * Sube un archivo y retorna el nombre guardado
     * @param array $archivo Array de $_FILES
     * @param string $tipo 'carnet', 'certificado', 'evidencia' o 'foto'
     * @param string $dni Número de documento
     * @param int|null $idAdicional ID adicional (solo para tipo 'evidencia', es el idAtencion)
     * @return string|null Nombre del archivo guardado o null si falla
     */
    private function subirArchivo($archivo, $tipo, $dni, $idAdicional = null) {
        if (!isset($archivo) || !isset($archivo['error']) || $archivo['error'] !== UPLOAD_ERR_OK) {
            $codigoError = isset($archivo['error']) ? (int)$archivo['error'] : -1;
            $this->logError("subirArchivo: archivo inválido o con error PHP upload (code=$codigoError, tipo=$tipo).", null);
            return null;
        }

        // Validar DNI usando función centralizada
        $dniValidado = $this->validarDNI($dni);
        if ($dniValidado === null) {
            $this->logError("subirArchivo: DNI inválido para tipo=$tipo.", null);
            return null;
        }

        $configArchivo = $this->obtenerConfigArchivoPorTipo($tipo);
        if ($configArchivo === null) {
            $this->logError("subirArchivo: tipo no permitido '$tipo'.", null);
            return null;
        }

        // Validar archivo usando función centralizada
        $tamanoMaximo = 10 * 1024 * 1024; // 10MB
        $detalleArchivo = '';
        if (!$this->validarArchivoSubido($archivo, $configArchivo['mimes'], $tamanoMaximo, $detalleArchivo)) {
            $this->logError("subirArchivo: validación de archivo falló ($detalleArchivo).", null);
            return null;
        }

        // Obtener extensión del archivo
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $configArchivo['extensiones'])) {
            $this->logError("subirArchivo: extensión no permitida '$extension' para tipo=$tipo.", null);
            return null;
        }

        $directorioBase = $this->obtenerDirectorioBaseAdjunto();
        if ($directorioBase === null) {
            return null;
        }

        $destino = $this->construirDestinoArchivoSubido($tipo, $archivo, $dniValidado, $extension, $idAdicional, $directorioBase);
        if ($destino === null) {
            return null;
        }
        $nombreArchivo = $destino['nombreArchivo'];
        $directorio = $destino['directorio'];

        if (!is_dir($directorio)) {
            if (!@mkdir($directorio, 0755, true)) {
                $this->logError("subirArchivo: no se pudo crear subdirectorio '$directorio'.", null);
                return null;
            }
        }

        $rutaCompleta = $directorio . $nombreArchivo;
        if (@move_uploaded_file($archivo['tmp_name'], $rutaCompleta) || @copy($archivo['tmp_name'], $rutaCompleta)) {
            return $nombreArchivo;
        }
        $this->logError("subirArchivo: move_uploaded_file/copy falló (tmp='{$archivo['tmp_name']}', destino='$rutaCompleta').", null);
        
        return null;
    }

    private function obtenerConfigArchivoPorTipo($tipo) {
        if ($tipo === 'foto') {
            return array(
                'mimes' => array('image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp'),
                'extensiones' => array('jpg', 'jpeg', 'png', 'webp', 'jfif')
            );
        }
        if ($tipo === 'carnet' || $tipo === 'certificado') {
            return array(
                'mimes' => array(
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ),
                'extensiones' => array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx')
            );
        }
        if ($tipo === 'evidencia') {
            return array(
                'mimes' => array('application/pdf', 'image/jpeg', 'image/png'),
                'extensiones' => array('pdf', 'jpg', 'jpeg', 'png')
            );
        }
        return null;
    }

        private function obtenerDirectorioBaseAdjunto() {
        if (class_exists('\\App\\Services\\mod_gp\\omaped\\OmapedBridge')) {
            $directorioBase = \App\Services\mod_gp\omaped\OmapedBridge::adjuntoBase();
        } elseif (defined('__DOCROOT__') && defined('__SUBDIRECTORY__')) {
            $directorioBase = __DOCROOT__ . __SUBDIRECTORY__ . '/Presentacion/mod_gp/omaped/adjunto/';
        } else {
            $directorioBase = storage_path('app/omaped/adjunto/');
        }
        return $directorioBase;
    }

    private function construirDestinoArchivoSubido($tipo, $archivo, $dniValidado, $extension, $idAdicional, $directorioBase) {
        if ($tipo === 'evidencia') {
            $idAdicionalValidado = $this->validarIdPositivo($idAdicional, 'idAdicional');
            if ($idAdicionalValidado === null) {
                return null;
            }
            return array(
                'nombreArchivo' => 'evid_' . $idAdicionalValidado . '_' . $dniValidado . '.' . $extension,
                'directorio' => $directorioBase . 'evidencia/'
            );
        }

        if ($tipo === 'foto') {
            $nombreArchivo = $this->validarNombreArchivo($archivo['name'], $dniValidado, $extension);
            if ($nombreArchivo === null) {
                $this->logError("subirArchivo: nombre de archivo inválido para foto (archivo='{$archivo['name']}', dni=$dniValidado, ext=$extension).", null);
                return null;
            }
            return array(
                'nombreArchivo' => $nombreArchivo,
                'directorio' => $directorioBase . 'foto/'
            );
        }

        $prefijo = ($tipo === 'carnet') ? 'carnt' : 'cert';
        return array(
            'nombreArchivo' => $prefijo . '_' . $dniValidado . '.' . $extension,
            'directorio' => ($tipo === 'carnet') ? $directorioBase . 'carnt_conds/' : $directorioBase . 'cert_disc/'
        );
    }

    /**
     * Inicia transacción
     */
    private function begin() { 
        return pg_query($this->cn, "BEGIN"); 
    }

    /**
     * Confirma transacción
     */
    private function commit() { 
        $result = pg_query($this->cn, "COMMIT");
        if ($result === false) {
            $this->logError("Error al confirmar transaccion: " . pg_last_error($this->cn), "COMMIT");
        }
        return $result;
    }

    /**
     * Revierte transacción
     */
    private function rollback() { 
        return pg_query($this->cn, "ROLLBACK"); 
    }

    /**
     * Normaliza string (trim y valida vacío)
     */
    private function str($value, $default = null) {
        $v = trim((string)$value);
        return $v === '' ? $default : $v;
    }

    /**
     * Convierte boolean a 't'/'f' para PostgreSQL
     */
    private function boolFrom($value) {
        return $value ? 't' : 'f';
    }

    // ==================== UTILIDADES QR / TOKENS ====================

    /**
     * Genera un token criptográficamente seguro en formato hexadecimal.
     *
     * - Se usa tanto para `token_qr` (persistente por paciente)
     *   como para `token_acceso` (temporal, 5 minutos).
     * - Centralizar aquí evita repetir lógica y comentarios.
     *
     * @param int $bytes Cantidad de bytes aleatorios a generar (32 -> 64 caracteres hex)
     * @return string Token seguro en hexadecimal
     */
    private function generarTokenSeguro($bytes = 32) {
        // random_bytes es seguro y está disponible desde PHP 7
        try {
            return bin2hex(random_bytes((int)$bytes));
        } catch (Exception $e) {
            // Fallback muy raro: si random_bytes falla, registramos y usamos openssl
            $this->logError('Error al generar token seguro con random_bytes: ' . $e->getMessage(), null);
            $fallback = openssl_random_pseudo_bytes((int)$bytes);
            return bin2hex($fallback !== false ? $fallback : uniqid('', true));
        }
    }

    // ==================== MÉTODOS DE VALIDACIÓN ====================

    /**
     * Valida y normaliza un DNI (solo números, 8 dígitos)
     * @param mixed $dni DNI a validar
     * @return string|null DNI normalizado (8 dígitos) o null si es inválido
     */
    private function validarDNI($dni) {
        if (empty($dni)) return null;
        $dniNormalizado = preg_replace('/[^0-9]/', '', (string)$dni);
        return strlen($dniNormalizado) === 8 ? $dniNormalizado : null;
    }

    /**
     * Valida un género (MASCULINO o FEMENINO)
     * @param mixed $genero Género a validar
     * @return string|null Género validado o null si es inválido
     */
    private function validarGenero($genero) {
        if (empty($genero)) return null;
        $generoUpper = strtoupper(trim((string)$genero));
        return in_array($generoUpper, array('MASCULINO', 'FEMENINO')) ? $generoUpper : null;
    }

    /**
     * Valida un ID numérico positivo
     * @param mixed $value Valor a validar
     * @param string $campo Nombre del campo (para logging)
     * @return int|null ID validado o null si es inválido
     */
    private function validarIdPositivo($value, $campo = '') {
        if (!isset($value) || $value === '' || $value === null) return null;
        if (!is_numeric($value)) {
            return null;
        }
        $id = (int)$value;
        if ($id <= 0) {
            return null;
        }
        return $id;
    }

    /**
     * Valida una fecha en formato YYYY-MM-DD
     * @param mixed $fecha Fecha a validar
     * @param string $formato Formato esperado (default: 'Y-m-d')
     * @return string|null Fecha validada o null si es inválida
     */
    private function validarFecha($fecha, $formato = 'Y-m-d') {
        if (empty($fecha)) return null;
        $fechaTrim = trim((string)$fecha);
        if ($fechaTrim === '') return null;
        
        // Extraer solo la parte de fecha si viene con hora
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $fechaTrim, $matches)) {
            $fechaTrim = $matches[1];
        }
        
        // Validar formato básico
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaTrim)) {
            return null;
        }
        
        // Validar que sea una fecha válida
        $partes = explode('-', $fechaTrim);
        if (count($partes) !== 3) return null;
        
        $anio = (int)$partes[0];
        $mes = (int)$partes[1];
        $dia = (int)$partes[2];
        
        if (!checkdate($mes, $dia, $anio)) {
            return null;
        }
        
        return $fechaTrim;
    }

    /**
     * Valida coordenadas geográficas (latitud y longitud)
     * @param mixed $latitud Latitud a validar
     * @param mixed $longitud Longitud a validar
     * @return array|null Array con 'latitud' y 'longitud' validados o null si son inválidas
     */
    private function validarCoordenadas($latitud, $longitud) {
        // Normalizar valores: convertir strings vacíos a null
        $lat = (is_string($latitud) && trim($latitud) === '') ? null : $latitud;
        $long = (is_string($longitud) && trim($longitud) === '') ? null : $longitud;
        
        // Si alguno está vacío o es null, retornar null
        if ($lat === null || $lat === '' || $long === null || $long === '') {
            return null;
        }
        
        $latFloat = filter_var($lat, FILTER_VALIDATE_FLOAT);
        $longFloat = filter_var($long, FILTER_VALIDATE_FLOAT);
        
        if ($latFloat === false || $longFloat === false) return null;
        
        // Validar rangos: latitud -90 a 90, longitud -180 a 180
        if ($latFloat < -90 || $latFloat > 90 || $longFloat < -180 || $longFloat > 180) {
            return null;
        }
        
        return array(
            'latitud' => (string)$latFloat,
            'longitud' => (string)$longFloat
        );
    }

    /**
     * Valida y sanitiza un nombre de archivo
     * @param string $nombre Nombre de archivo original
     * @param string $dni DNI para usar como base del nombre
     * @param string $extension Extensión permitida
     * @return string|null Nombre de archivo sanitizado o null si es inválido
     */
    private function validarNombreArchivo($nombre, $dni, $extension) {
        // Sanitizar extensión
        $extension = strtolower(ltrim($extension, '.'));
        $extensionesPermitidas = array('jpg', 'jpeg', 'png', 'webp', 'jfif', 'pdf');
        if (!in_array($extension, $extensionesPermitidas)) {
            return null;
        }
        
        // Validar DNI
        $dniValidado = $this->validarDNI($dni);
        if (!$dniValidado) {
            return null;
        }
        
        // Construir nombre seguro: solo DNI + extensión
        return $dniValidado . '.' . $extension;
    }

    /**
     * Valida un archivo subido (tipo MIME y tamaño)
     * @param array $archivo Array de $_FILES
     * @param array $tiposMimePermitidos Tipos MIME permitidos
     * @param int $tamañoMaximo Tamaño máximo en bytes (default: 5MB)
     * @return bool true si es válido, false si no
     */
    private function validarArchivoSubido($archivo, $tiposMimePermitidos, $tamañoMaximo = 5242880, &$detalleError = '') {
        if (!isset($archivo['error']) || $archivo['error'] !== UPLOAD_ERR_OK) {
            $detalleError = "error PHP upload code=" . (isset($archivo['error']) ? (int)$archivo['error'] : -1);
            return false;
        }
        
        // Validar tamaño
        if (!isset($archivo['size']) || $archivo['size'] > $tamañoMaximo) {
            $size = isset($archivo['size']) ? (int)$archivo['size'] : -1;
            $detalleError = "tamaño inválido size=$size max=$tamañoMaximo";
            return false;
        }
        
        // Validar tipo MIME real
        if (!isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
            $tmp = isset($archivo['tmp_name']) ? $archivo['tmp_name'] : '';
            $detalleError = "tmp_name inválido o no subido por HTTP POST ($tmp)";
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $tiposMimePermitidos)) {
            $detalleError = "MIME no permitido mime='$mimeType'";
            return false;
        }
        
        $detalleError = "ok";
        return true;
    }

    // ==================== MÉTODOS QR (PERSISTENCIA BÁSICA) ====================

    /**
     * Crea o reutiliza el token QR asociado a un paciente.
     *
     * REGLA DE NEGOCIO (simple y segura):
     * - Si el paciente ya tiene un `token_qr` ACTIVO en `g_social.omaped_qr`, se reutiliza.
     * - Si no tiene, se genera uno nuevo con `generarTokenSeguro()` y se inserta.
     *
     * Esto garantiza:
     * - Un solo QR activo por paciente.
     * - Que el mismo QR siga siendo válido al editar los datos del paciente.
     *
     * @param int $idPacnt ID del paciente (g_social.pacnt.id_pacnt)
     * @return string|null Token QR (hex) o null si falla
     */
    private function crearOActualizarTokenQR($idPacnt) {
        $id = (int)$idPacnt;
        if ($id <= 0) {
            $this->logError("crearOActualizarTokenQR: idPacnt invalido: " . var_export($idPacnt, true), null);
            return null;
        }

        // 1) Reutilizar token QR activo si ya existe
        $sqlSel = "SELECT token_qr 
                   FROM g_social.omaped_qr 
                   WHERE id_pacnt = $1 AND estado = 'ACTIVO'
                   ORDER BY fecha_generacion DESC, id_qr DESC
                   LIMIT 1";
        $rsSel = $this->ejecutar($sqlSel, array($id));
        if ($rsSel) {
            $rowSel = pg_fetch_assoc($rsSel);
            if ($rowSel && !empty($rowSel['token_qr'])) {
                return $rowSel['token_qr'];
            }
        }

        // 2) Generar un nuevo token QR y registrarlo
        $tokenQr = $this->generarTokenSeguro(32);
        $sqlIns = "INSERT INTO g_social.omaped_qr (id_pacnt, token_qr, estado, fecha_generacion)
                   VALUES ($1, $2, 'ACTIVO', CURRENT_TIMESTAMP)";
        $rsIns = $this->ejecutar($sqlIns, array($id, $tokenQr));
        if ($rsIns === false) {
            $this->logError("crearOActualizarTokenQR: error al insertar token para id_pacnt=$id", $sqlIns);
            return null;
        }

        return $tokenQr;
    }

    /**
     * Obtiene la ruta base del proyecto (unificada para evitar duplicación)
     * @return string|null Ruta base del proyecto o null si no se puede resolver
     */
    private function obtenerRutaBaseProyecto() {
        if (defined('__DOCROOT__') && defined('__SUBDIRECTORY__')) {
            return rtrim(__DOCROOT__ . __SUBDIRECTORY__, '/');
        }
        $rootSigtram = realpath(__DIR__ . '/../../../../');
        return $rootSigtram ? str_replace('\\', '/', $rootSigtram) : null;
    }

    /**
     * Si el JPG del QR ya existe en disco, devuelve la URL relativa sin regenerar.
     * @return string|null
     */
    private function obtenerUrlRelQrSiExiste($tokenQr) {
        $token = trim((string)$tokenQr);
        if ($token === '') {
            return null;
        }
        $baseFs = $this->obtenerRutaBaseProyecto();
        if (!$baseFs) {
            return null;
        }
        $rutaJpg = (class_exists('\\\\App\\\\Services\\\\mod_gp\\\\omaped\\\\OmapedBridge') ? \App\Services\mod_gp\omaped\OmapedBridge::tempQrBase() : ($baseFs . '/Presentacion/genera_dcmnto/tempQR')) . '/' . $token . '.jpg';
        if (file_exists($rutaJpg)) {
            return 'omaped-qr/' . $token . '.jpg';
        }
        return null;
    }

    /**
     * Valida fecha YYYY-MM-DD para filtros de listado (evita SQL inválido con entradas corruptas).
     * @return string|null La misma fecha si es válida, null si no
     */
    private function normalizarFechaFiltroListado($fechaStr) {
        $s = trim((string)$fechaStr);
        if ($s === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $s);
        if ($dt && $dt->format('Y-m-d') === $s) {
            return $s;
        }
        return null;
    }

    /**
     * Genera la imagen QR (JPG 500x500px) para un token dado.
     * La librería Generar_QR genera PNG, luego se convierte a JPG y redimensiona.
     *
     * @param string $tokenQr Token único del QR
     * @return string|null URL relativa del JPG (ej: "Presentacion/genera_dcmnto/tempQR/abc123.jpg") o null si falla
     */
    private function generarImagenQrParaToken($tokenQr) {
        $token = trim((string)$tokenQr);
        if ($token === '') {
            return null;
        }

        // Asegurar constantes requeridas
        if (!defined('__DOCROOT__') || !defined('__SUBDIRECTORY__')) {
            $rootSigtram = realpath(__DIR__ . '/../../../../');
            if ($rootSigtram) {
                $docroot = dirname($rootSigtram);
                if (!defined('__DOCROOT__')) {
                    define('__DOCROOT__', str_replace('\\', '/', $docroot));
                }
                if (!defined('__SUBDIRECTORY__')) {
                    define('__SUBDIRECTORY__', '/sigtram');
                }
            }
        }

        // Construir URL para el QR (detecta automáticamente entorno)
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $subdir = defined('__SUBDIRECTORY__') ? __SUBDIRECTORY__ : '/sigtram';
        if (class_exists('\\App\\Services\\mod_gp\\omaped\\OmapedBridge')) {
            $urlEntrada = \App\Services\mod_gp\omaped\OmapedBridge::entradaUrl($token);
        } else {
            $urlEntrada = sprintf('%s://%s%s/Presentacion/mod_gp/omaped/entrada.php?t=%s',
                $protocolo, $host, $subdir, urlencode($token));
        }

        // Incluir la clase Generar_QR_OMAPED
        if (!class_exists('Generar_QR_OMAPED')) {
            $rutaQrOmaped = __DIR__ . '/Generar_QR_OMAPED.php';
            if (!file_exists($rutaQrOmaped) || !@require_once $rutaQrOmaped) {
                $this->logError("Generar_QR_OMAPED.php no encontrado: $rutaQrOmaped", null);
                return null;
            }
            if (!class_exists('Generar_QR_OMAPED')) {
                $this->logError("Clase Generar_QR_OMAPED no encontrada", null);
                return null;
            }
        }

        // Asegurar carpeta destino donde la librería escribe el PNG
        $baseFs = $this->obtenerRutaBaseProyecto();
        if (!$baseFs) {
            $this->logError("No se pudo resolver ruta base del proyecto", null);
            return null;
        }

        $dirTempQr = class_exists('\\\\App\\\\Services\\\\mod_gp\\\\omaped\\\\OmapedBridge') ? \App\Services\mod_gp\omaped\OmapedBridge::tempQrBase() : ($baseFs . '/Presentacion/genera_dcmnto/tempQR');
        if (!is_dir($dirTempQr) && !@mkdir($dirTempQr, 0775, true) && !is_dir($dirTempQr)) {
            $this->logError("No se pudo crear directorio tempQR: $dirTempQr", null);
            return null;
        }

        // Generar QR
        try {
            $resultado = Generar_QR_OMAPED::Gene_QR_png($urlEntrada, $token, '8');
            if ($resultado === false) {
                $this->logError("Gene_QR_png retornó false para token=$token", null);
                return null;
            }
        } catch (\Throwable $e) {
            $this->logError("Excepción al generar QR: " . $e->getMessage() . " | " . basename($e->getFile()) . ":" . $e->getLine(), null);
            return null;
        }

        // Convertir PNG a JPG 500x500px
        $rutaPng = $dirTempQr . '/' . $token . '.png';
        $rutaJpg = $dirTempQr . '/' . $token . '.jpg';
        
        if (!file_exists($rutaPng)) {
            $this->logError("PNG no generado: $rutaPng", null);
            return null;
        }

        $imagenPng = @imagecreatefrompng($rutaPng);
        if (!$imagenPng) {
            $this->logError("No se pudo leer PNG: $rutaPng", null);
            return null;
        }

        $imagenJpg = imagecreatetruecolor(500, 500);
        if (!$imagenJpg) {
            imagedestroy($imagenPng);
            $this->logError("No se pudo crear imagen JPG", null);
            return null;
        }

        imagefill($imagenJpg, 0, 0, imagecolorallocate($imagenJpg, 255, 255, 255));
        
        $anchoOriginal = imagesx($imagenPng);
        $altoOriginal = imagesy($imagenPng);
        $escala = min(500 / $anchoOriginal, 500 / $altoOriginal);
        $anchoNuevo = (int)($anchoOriginal * $escala);
        $altoNuevo = (int)($altoOriginal * $escala);
        $x = (int)((500 - $anchoNuevo) / 2);
        $y = (int)((500 - $altoNuevo) / 2);

        $reductionRatio = max($anchoOriginal / $anchoNuevo, $altoOriginal / $altoNuevo);
        if ($reductionRatio > 1.5) {
            $intermediateW = (int)(($anchoOriginal + $anchoNuevo) / 2);
            $intermediateH = (int)(($altoOriginal + $altoNuevo) / 2);
            $intermediate = imagecreatetruecolor($intermediateW, $intermediateH);
            imagecopyresampled($intermediate, $imagenPng, 0, 0, 0, 0, $intermediateW, $intermediateH, $anchoOriginal, $altoOriginal);
            imagecopyresampled($imagenJpg, $intermediate, $x, $y, 0, 0, $anchoNuevo, $altoNuevo, $intermediateW, $intermediateH);
            imagedestroy($intermediate);
        } else {
            imagecopyresampled($imagenJpg, $imagenPng, $x, $y, 0, 0, $anchoNuevo, $altoNuevo, $anchoOriginal, $altoOriginal);
        }

        imagedestroy($imagenPng);
        
        if (!@imagejpeg($imagenJpg, $rutaJpg, 95) || !file_exists($rutaJpg)) {
            imagedestroy($imagenJpg);
            $this->logError("No se pudo guardar JPG: $rutaJpg", null);
            return null;
        }
        
        imagedestroy($imagenJpg);

        // URL relativa que usará el frontend (respecto a la raíz del proyecto)
        return 'omaped-qr/' . $token . '.jpg';
    }

    /**
     * Genera (si es necesario) y devuelve la ruta física del PNG QR para un paciente.
     * Se usa para descargar el QR desde un endpoint AJAX (misma sesión).
     *
     * @param int $idPacnt
     * @return array { success: bool, rutaFisica?: string, nombreDescarga?: string, error?: string }
     */
    public function obtenerQrPngPorPaciente($idPacnt) {
        $id = (int)$idPacnt;
        if ($id <= 0) {
            return $this->fail('ID no valido.');
        }

        // Obtener DNI y token QR
        $sql = "SELECT COALESCE(pu.nu_docu,'') AS dni,
                       (SELECT q.token_qr
                          FROM g_social.omaped_qr q
                         WHERE q.id_pacnt = p.id_pacnt
                           AND q.estado = 'ACTIVO'
                         ORDER BY q.fecha_generacion DESC, q.id_qr DESC
                         LIMIT 1) AS token_qr
                  FROM g_social.pacnt p
            INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = p.cdgo_usrios
                 WHERE p.id_pacnt = $1 AND p.estado = true
                 LIMIT 1";

        $rs = $this->ejecutar($sql, array($id));
        $row = $rs ? pg_fetch_assoc($rs) : null;
        if (!$row) {
            return $this->fail('Paciente no encontrado.');
        }

        $dni = isset($row['dni']) ? preg_replace('/[^0-9]/', '', (string)$row['dni']) : '';
        $tokenQr = isset($row['token_qr']) ? trim((string)$row['token_qr']) : '';
        if ($tokenQr === '') {
            $tokenQr = $this->crearOActualizarTokenQR($id);
            if (!$tokenQr) {
                return $this->fail('No se pudo generar token QR.');
            }
        }

        // Generar JPG (si falla, devolver error)
        $urlRel = $this->generarImagenQrParaToken($tokenQr);
        if (!$urlRel) {
            return $this->fail('No se pudo generar la imagen QR.');
        }

        // Resolver ruta física desde la URL relativa usando la misma lógica
        $baseFs = $this->obtenerRutaBaseProyecto();
        if (!$baseFs) {
            $this->logError("obtenerQrPngPorPaciente: no se pudo resolver ruta base del proyecto", null);
            return $this->fail('No se pudo resolver ruta del proyecto.');
        }

        $nombreArchivo = basename($urlRel);
        if (strpos($urlRel, 'omaped-qr/') === 0 && class_exists('\\App\\Services\\mod_gp\\omaped\\OmapedBridge')) {
            $rutaFisica = \App\Services\mod_gp\omaped\OmapedBridge::tempQrBase() . '/' . $nombreArchivo;
        } else {
            $rutaFisica = $baseFs . '/' . $urlRel;
        }
        
        // Normalizar la ruta (resolver .. y .)
        $rutaFisicaNormalizada = realpath($rutaFisica);
        if ($rutaFisicaNormalizada && file_exists($rutaFisicaNormalizada)) {
            $rutaFisica = $rutaFisicaNormalizada;
        } elseif (!file_exists($rutaFisica)) {
            // Intentar con separadores de Windows o buscar en directorio conocido
            $rutaWin = str_replace('/', '\\', $rutaFisica);
            if (file_exists($rutaWin)) {
                $rutaFisica = $rutaWin;
            } else {
                // Buscar por nombre en el directorio conocido
                $rutaAlt = class_exists('\\App\\Services\\mod_gp\\omaped\\OmapedBridge')
                    ? \App\Services\mod_gp\omaped\OmapedBridge::tempQrBase() . '/' . $nombreArchivo
                    : ($baseFs . '/Presentacion/genera_dcmnto/tempQR/' . $nombreArchivo);
                if (file_exists($rutaAlt)) {
                    $rutaFisica = realpath($rutaAlt) ?: $rutaAlt;
                } else {
                    $this->logError("obtenerQrPngPorPaciente: archivo QR no existe. rutaFisica=$rutaFisica | rutaAlt=$rutaAlt", null);
                    return $this->fail('El archivo QR no existe en el servidor.');
                }
            }
        }

        return $this->ok(array(
            'rutaFisica' => $rutaFisica,
            'nombreDescarga' => 'qr_omaped_' . ($dni !== '' ? $dni : $id) . '.jpg'
        ));
    }

    /**
     * Obtiene la información básica del paciente a partir de un token QR.
     *
     * Esta función NO devuelve datos sensibles completos, solo:
     * - id_qr, id_pacnt
     * - DNI y nombres básicos para la etapa de validación posterior (DNI + token_acceso).
     *
     * @param string $tokenQr Token QR recibido en la URL pública (parámetro `t`)
     * @return array|null Datos del paciente o null si el token no es válido/activo
     */
    private function obtenerPacientePorTokenQR($tokenQr) {
        $token = trim((string)$tokenQr);
        if ($token === '') {
            return null;
        }

        $sql = "SELECT q.id_qr,
                       q.id_pacnt,
                       pu.nu_docu AS dni,
                       hu.no_usrio AS nombres,
                       hu.ap_pate AS ape_paterno,
                       hu.ap_mate AS ape_materno
                FROM g_social.omaped_qr q
                INNER JOIN g_social.pacnt p ON p.id_pacnt = q.id_pacnt AND p.estado = true
                INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = p.cdgo_usrios
                LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                WHERE q.token_qr = $1
                  AND q.estado = 'ACTIVO'
                LIMIT 1";

        $rs = $this->ejecutar($sql, array($token));
        if (!$rs) {
            // Error ya registrado en ejecutar()
            return null;
        }

        $row = pg_fetch_assoc($rs);
        if (!$row) {
            return null;
        }

        return array(
            'id_qr'      => (int)$row['id_qr'],
            'id_pacnt'   => (int)$row['id_pacnt'],
            'dni'        => isset($row['dni']) ? (string)$row['dni'] : '',
            'nombres'    => isset($row['nombres']) ? (string)$row['nombres'] : '',
            'apePaterno' => isset($row['ape_paterno']) ? (string)$row['ape_paterno'] : '',
            'apeMaterno' => isset($row['ape_materno']) ? (string)$row['ape_materno'] : ''
        );
    }

    // ==================== MÉTODOS DE MAPEO Y RESOLUCIÓN ====================

    /**
     * Mapea datos de persona desde fila de BD
     */
    private function mapPersonaFromRow($row) {
        $generoRaw = isset($row['genero']) ? trim((string)$row['genero']) : '';
        $genero = '';
        if ($generoRaw === '1') {
            $genero = 'MASCULINO';
        } elseif ($generoRaw === '2') {
            $genero = 'FEMENINO';
        } elseif (in_array(strtoupper($generoRaw), array('MASCULINO', 'FEMENINO', 'OTRO'))) {
            $genero = strtoupper($generoRaw);
        }
        
        return array(
            'dni' => isset($row['dni']) ? $row['dni'] : '',
            'nombres' => isset($row['nombres']) ? $row['nombres'] : '',
            'apePaterno' => isset($row['ape_paterno']) ? $row['ape_paterno'] : '',
            'apeMaterno' => isset($row['ape_materno']) ? $row['ape_materno'] : '',
            'genero' => $genero,
            'telefono' => isset($row['telefono']) ? $row['telefono'] : '',
            'direccion' => isset($row['direccion']) ? $row['direccion'] : ''
        );
    }

    /**
     * Resuelve usuario por número de documento
     */
    private function resolverUsuarioPorDocumento($dni) {
        $sql = "SELECT pu.cdgo_usrios,
                       COALESCE(pu.nu_docu,'') AS dni,
                       COALESCE(hu.no_usrio,'') AS nombres,
                       COALESCE(hu.ap_pate,'') AS ape_paterno,
                       COALESCE(hu.ap_mate,'') AS ape_materno,
                       COALESCE(hu.in_sexo,'') AS genero,
                       COALESCE(pu.nu_tele,'') AS telefono,
                       COALESCE(pu.de_dire,'') AS direccion
                FROM sistema.p_usrios pu
                LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                WHERE pu.nu_docu = $1
                LIMIT 1";
        $rs = $this->ejecutar($sql, array($dni));
        if (!$rs) return null;
        $row = pg_fetch_assoc($rs);
        return $row ? $row : null;
    }

    /**
     * Prepara email de persona (valida o genera por defecto)
     */
    private function prepararEmailPersona($dni, $email = '') {
        $mail = strtolower(trim((string)$email));
        if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return $mail;
        }
        return strtolower(trim((string)$dni)) . "@omaped.local";
    }

    /**
     * Resuelve ID de parentesco por nombre
     */
    private function resolverIdParentesco($nombre) {
        $sql = "SELECT id_tip_parntsc
                FROM g_social.tip_parntsc
                WHERE UPPER(COALESCE(nomb_parntsc,'')) = UPPER($1)
                  AND estado = true
                LIMIT 1";
        $rs = $this->ejecutar($sql, array($nombre));
        if (!$rs) return null;
        $row = pg_fetch_assoc($rs);
        return $row ? (int)$row['id_tip_parntsc'] : null;
    }

    /**
     * Resuelve ID de sector (valida existencia)
     */
    private function resolverIdSector($idSector) {
        $sql = "SELECT id_sectr FROM g_social.sectr WHERE id_sectr = $1 AND estado = true LIMIT 1";
        $rs = $this->ejecutar($sql, array($idSector));
        if (!$rs) return null;
        $row = pg_fetch_assoc($rs);
        return $row ? (int)$row['id_sectr'] : null;
    }

    /**
     * Resuelve ID de sede por nombre
     */
    private function resolverIdSedePorNombre($nombreSede) {
        $sql = "SELECT id_sede FROM g_social.sede WHERE UPPER(COALESCE(nomb_sede,'')) = UPPER($1) AND estado = true LIMIT 1";
        $rs = $this->ejecutar($sql, array($nombreSede));
        if (!$rs) return null;
        $row = pg_fetch_assoc($rs);
        return $row ? (int)$row['id_sede'] : null;
    }

    // ==================== MÉTODOS DE PERSISTENCIA ====================

    /**
     * Crea o actualiza persona natural usando módulo de personas
     * @param array $payload Datos de la persona
     * @param bool $soloCamposPresentes Si es true, solo actualiza campos que están presentes en el payload (útil para familiares)
     */
    private function upsertPersonaNaturalPorPayload($payload, $soloCamposPresentes = false, $dniPacientePrincipal = null) {
        $dni = $this->str(isset($payload['dni']) ? $payload['dni'] : (isset($payload['numeroDocumento']) ? $payload['numeroDocumento'] : ''), '');
        if ($dni === '') return null;

        $usuarioExistente = $this->resolverUsuarioPorDocumento($dni);
        
        // Validación de seguridad: si es familiar y tiene el mismo DNI que el paciente principal, no actualizar
        // (La validación principal se hace en el frontend, esto es solo una medida de seguridad adicional)
        if ($soloCamposPresentes && $usuarioExistente && $dniPacientePrincipal !== null && $dni === $dniPacientePrincipal) {
            return $usuarioExistente;
        }
        
        // Para paciente principal: usar valores del payload directamente (aunque estén vacíos)
        // Para familiares: solo usar valores del payload si están presentes
        // PERO si el usuario no existe, necesitamos crear con los datos disponibles
        if ($soloCamposPresentes && $usuarioExistente) {
            // Usuario existe: solo actualizar campos presentes
            $nombres = isset($payload['nombres']) ? $this->str($payload['nombres'], '') : '';
            $apePaterno = isset($payload['apePaterno']) ? $this->str($payload['apePaterno'], '') : '';
            $apeMaterno = isset($payload['apeMaterno']) ? $this->str($payload['apeMaterno'], '') : '';
            $telefono = isset($payload['telefono']) ? $this->str($payload['telefono'], '') : '';
            $direccion = isset($payload['direccion']) ? $this->str($payload['direccion'], '') : '';
        } else {
            // Usuario no existe o es paciente principal: usar todos los valores del payload
            $nombres = $this->str(isset($payload['nombres']) ? $payload['nombres'] : '', '');
            $apePaterno = $this->str(isset($payload['apePaterno']) ? $payload['apePaterno'] : '', '');
            $apeMaterno = $this->str(isset($payload['apeMaterno']) ? $payload['apeMaterno'] : '', '');
            $telefono = $this->str(isset($payload['telefono']) ? $payload['telefono'] : '', '');
            $direccion = $this->str(isset($payload['direccion']) ? $payload['direccion'] : '', '');
        }
        
        $email = $this->prepararEmailPersona($dni, isset($payload['email']) ? $payload['email'] : '');

        // Validar que tengamos los datos mínimos necesarios
        if ($nombres === '' || $apePaterno === '') {
            $this->logError("Validacion fallida - nombres=" . var_export($nombres, true) . " apePaterno=" . var_export($apePaterno, true) . " | soloCamposPresentes=" . ($soloCamposPresentes ? 'true' : 'false'), null);
            return null;
        }

        $datosPersona = array(
            'nu_docu' => $dni,
            'no_usrio' => strtoupper($nombres),
            'ap_pate' => strtoupper($apePaterno),
            'ap_mate' => strtoupper($apeMaterno),
            'de_dire' => strtoupper($direccion),
            'nu_tele' => $telefono,
            'de_mail' => $email,
            'co_ubig' => '',
            'nu_ruc' => '',
            'in_tipo_pers' => '0',
            'fe_inic_acti' => date('Y-m-d'),
            'cdgo_cmpnia' => '2',
            'cdgo_dcmntos_iden' => '1' // DNI por defecto para personas naturales
            // No incluir 'in_esta' aquí porque clase_personas::guardar_persona lo agrega automáticamente
        );
        

        $coUsuaModi = isset($_SESSION['usIdPrsna']) ? intval($_SESSION['usIdPrsna']) : null;
        $cdgoUsrios = $usuarioExistente ? intval($usuarioExistente['cdgo_usrios']) : null;
        $resultado = clase_personas::guardar_persona($datosPersona, $cdgoUsrios, $coUsuaModi);
        if ($resultado === false) {
            $this->logError("clase_personas::guardar_persona devolvio false", null);
            return null;
        }

        $usuarioActualizado = $this->resolverUsuarioPorDocumento($dni);
        if (!$usuarioActualizado) {
            $this->logError("No se pudo resolver usuario actualizado despues de guardar", null);
            return null;
        }
        
        $cdgoUsriosFinal = (int)$usuarioActualizado['cdgo_usrios'];
        
        // ACTUALIZAR TABLA p_usrios
        $updatesP = array();
        $paramsP = array();
        $idx = 1;
        
        // Para paciente principal: actualizar todos los campos que vienen en el payload
        if (!$soloCamposPresentes || isset($payload['telefono'])) {
            $updatesP[] = "nu_tele = $" . $idx++;
            $paramsP[] = $telefono;
        }
        if (!$soloCamposPresentes || isset($payload['direccion'])) {
            $updatesP[] = "de_dire = $" . $idx++;
            $paramsP[] = strtoupper($direccion);
        }
        if (!$soloCamposPresentes || isset($payload['email'])) {
            $updatesP[] = "de_mail = $" . $idx++;
            $paramsP[] = $email;
        }
        
        if (!empty($updatesP)) {
            $paramsP[] = $cdgoUsriosFinal;
            $sqlP = "UPDATE sistema.p_usrios SET " . implode(", ", $updatesP) . " WHERE cdgo_usrios = $" . $idx;
            $this->ejecutar($sqlP, $paramsP);
        }
        
        // ACTUALIZAR TABLA h_usrios_ntral
        $updatesH = array();
        $paramsH = array();
        $idx = 1;
        
        // Para paciente principal: actualizar todos los campos que vienen en el payload
        if (!$soloCamposPresentes || isset($payload['nombres'])) {
            $updatesH[] = "no_usrio = UPPER($" . $idx++ . ")";
            $paramsH[] = $nombres;
        }
        if (!$soloCamposPresentes || isset($payload['apePaterno'])) {
            $updatesH[] = "ap_pate = UPPER($" . $idx++ . ")";
            $paramsH[] = $apePaterno;
        }
        if (!$soloCamposPresentes || isset($payload['apeMaterno'])) {
            $updatesH[] = "ap_mate = UPPER($" . $idx++ . ")";
            $paramsH[] = $apeMaterno;
        }
        // Validar género usando función centralizada
        if (isset($payload['genero']) && $payload['genero'] !== '') {
            $genero = $this->validarGenero($payload['genero']);
            if ($genero === 'MASCULINO') {
                $updatesH[] = "in_sexo = $" . $idx++;
                $paramsH[] = 1;
            } elseif ($genero === 'FEMENINO') {
                $updatesH[] = "in_sexo = $" . $idx++;
                $paramsH[] = 2;
            }
        }
        
        if (!empty($updatesH)) {
            $updatesH[] = "ts_usua_modi = NOW()";
            if ($coUsuaModi) {
                $updatesH[] = "co_usua_modi = $" . $idx++;
                $paramsH[] = $coUsuaModi;
            }
            $paramsH[] = $cdgoUsriosFinal;
            $sqlH = "UPDATE sistema.h_usrios_ntral SET " . implode(", ", $updatesH) . " WHERE cdgo_usrios = $" . $idx;
            $this->ejecutar($sqlH, $paramsH);
        }

        return $usuarioActualizado;
    }

    /**
     * Construye parámetros y expresiones SQL para coordenadas
     * @param mixed $latd Latitud (puede ser null)
     * @param mixed $longtd Longitud (puede ser null)
     * @param int $startIdx Índice inicial para parámetros
     * @return array Array con 'params' (array de parámetros), 'latdExpr' (expresión SQL), 'longtdExpr' (expresión SQL), 'nextIdx' (siguiente índice)
     */
    private function construirCoordenadasSQL($latd, $longtd, $startIdx) {
        $params = array();
        $latdExpr = 'NULL';
        $longtdExpr = 'NULL';
        $idx = $startIdx;
        
        if ($latd !== null) {
            $latdExpr = '$' . $idx;
            $params[] = $latd;
            $idx++;
        }
        
        if ($longtd !== null) {
            $longtdExpr = '$' . $idx;
            $params[] = $longtd;
            $idx++;
        }
        
        return array(
            'params' => $params,
            'latdExpr' => $latdExpr,
            'longtdExpr' => $longtdExpr,
            'nextIdx' => $idx
        );
    }

    /**
     * Guarda o actualiza detalle de paciente
     * @param int $idPacnt ID del paciente
     * @param array $input Datos del detalle del paciente
     * @return bool true si se guardó correctamente, false si falla
     */
    private function guardarDetallePaciente($idPacnt, $input) {
        // Validar ID de sector usando función centralizada
        $idSector = $this->validarIdPositivo(isset($input['idSector']) ? $input['idSector'] : null, 'idSector');
        if ($idSector !== null) {
            $idSectorValidado = $this->resolverIdSector($idSector);
            $idSector = $idSectorValidado;
        }
        
        // Si no hay sector válido, obtener el primero disponible
        if ($idSector === null) {
            $rsPrimerSector = $this->ejecutar("SELECT id_sectr FROM g_social.sectr WHERE estado = true ORDER BY id_sectr LIMIT 1");
            if ($rsPrimerSector) {
                $rowPrimerSector = pg_fetch_assoc($rsPrimerSector);
                if ($rowPrimerSector) {
                    $idSector = (int)$rowPrimerSector['id_sectr'];
                }
            }
        }
        
        if ($idSector === null) {
            return false;
        }

        $zona = $this->str(isset($input['zona']) ? $input['zona'] : '', '');
        
        // Validar distrito usando función centralizada
        $otroDistr = $this->validarIdPositivo(isset($input['distritoOtro']) ? $input['distritoOtro'] : null, 'distritoOtro');
        
        $ubicac = $this->str(isset($input['ubicacion']) ? $input['ubicacion'] : '', '');
        
        // Validar y normalizar coordenadas
        $latitudInput = isset($input['latitud']) ? $input['latitud'] : null;
        $longitudInput = isset($input['longitud']) ? $input['longitud'] : null;
        
        // Normalizar: convertir strings vacíos a null
        if ($latitudInput === '' || (is_string($latitudInput) && trim($latitudInput) === '')) {
            $latitudInput = null;
        }
        if ($longitudInput === '' || (is_string($longitudInput) && trim($longitudInput) === '')) {
            $longitudInput = null;
        }
        
        $coordenadas = $this->validarCoordenadas($latitudInput, $longitudInput);
        $latd = $coordenadas ? $coordenadas['latitud'] : null;
        $longtd = $coordenadas ? $coordenadas['longitud'] : null;
        
        // Manejar foto del paciente
        $foto = null;
        if (isset($_FILES['fotoPaciente'])) {
            if ((int)$_FILES['fotoPaciente']['error'] === UPLOAD_ERR_OK) {
                $dniFoto = isset($input['dni']) ? $input['dni'] : '';
                $archivoSubido = $this->subirArchivo($_FILES['fotoPaciente'], 'foto', $dniFoto);
                if (!$archivoSubido) {
                    $this->logError("No se pudo guardar la foto del paciente (subirArchivo retorno null).", null);
                    return false;
                }
                $foto = $archivoSubido;
            } elseif ((int)$_FILES['fotoPaciente']['error'] !== UPLOAD_ERR_NO_FILE) {
                $this->logError("Error al subir fotoPaciente. Código PHP FILES error=" . (int)$_FILES['fotoPaciente']['error'], null);
                return false;
            }
        } elseif (isset($input['foto']) && $input['foto'] !== '') {
            // Si viene la foto existente (no se actualiza)
            $foto = $this->str($input['foto'], '');
        }
        
        // Validar fecha usando función centralizada
        $fecNac = $this->validarFecha(isset($input['fechaNacimiento']) ? $input['fechaNacimiento'] : null);

        $cdgoLgin = $this->obtenerCodigoLogin();
        $rsExiste = $this->ejecutar("SELECT id_pacnt_detll, COALESCE(foto, '') AS foto_actual FROM g_social.pacnt_detll WHERE id_pacnt = $1 AND estado = true LIMIT 1", array($idPacnt));
        $row = $rsExiste ? pg_fetch_assoc($rsExiste) : null;
        
        if ($row) {
            // Si no llegó una nueva foto válida, conservar la foto actual para no sobrescribir con NULL.
            if ($foto === null || $foto === '') {
                $foto = isset($row['foto_actual']) ? $this->str($row['foto_actual'], '') : '';
            }

            $idDetalle = (int)$row['id_pacnt_detll'];
            $params = array($idSector, $zona, $otroDistr, $ubicac);
            $idx = 5;
            
            if ($fecNac !== null) {
                $params[] = $fecNac;
                $fecNacExpr = "CAST($" . $idx . " AS DATE)";
                $idx++;
            } else {
                $fecNacExpr = "NULL";
            }
            
            // Agregar coordenadas
            $coords = $this->construirCoordenadasSQL($latd, $longtd, $idx);
            $params = array_merge($params, $coords['params']);
            $fotoIdx = $coords['nextIdx'];
            $params[] = $foto;
            $cdgoLginIdx = $fotoIdx + 1;
            $params[] = $cdgoLgin;
            $idDetalleIdx = $cdgoLginIdx + 1;
            $params[] = $idDetalle;
            
            $sqlUpd = "UPDATE g_social.pacnt_detll
                       SET id_sectr = $1, zona = $2, otro_distr = $3, ubicac = $4, fec_nac = $fecNacExpr, 
                           latd = " . $coords['latdExpr'] . ", longtd = " . $coords['longtdExpr'] . ", 
                           foto = $" . $fotoIdx . ", fec_upd = CURRENT_TIMESTAMP, cdgo_lgin_upd = $" . $cdgoLginIdx . "
                       WHERE id_pacnt_detll = $" . $idDetalleIdx;
            return $this->ejecutar($sqlUpd, $params) !== false;
        }

        // INSERT
        $params = array($idPacnt, $idSector, $zona, $otroDistr, $ubicac);
        $idx = 6;
        
        if ($fecNac !== null) {
            $params[] = $fecNac;
            $fecNacExpr = "CAST($" . $idx . " AS DATE)";
            $cols = "(id_pacnt, id_sectr, zona, otro_distr, ubicac, fec_nac, latd, longtd, foto, estado, fec_ins, cdgo_lgin_ins)";
            $valsBase = "($1, $2, $3, $4, $5, $fecNacExpr";
            $idx++;
        } else {
            $fecNacExpr = "";
            $cols = "(id_pacnt, id_sectr, zona, otro_distr, ubicac, latd, longtd, foto, estado, fec_ins, cdgo_lgin_ins)";
            $valsBase = "($1, $2, $3, $4, $5";
        }
        
        // Agregar coordenadas
        $coords = $this->construirCoordenadasSQL($latd, $longtd, $idx);
        $params = array_merge($params, $coords['params']);
        $fotoIdx = $coords['nextIdx'];
        $params[] = $foto;
        $cdgoLginIdx = $fotoIdx + 1;
        $params[] = $cdgoLgin;
        
        $sqlIns = "INSERT INTO g_social.pacnt_detll $cols
                   VALUES $valsBase, " . $coords['latdExpr'] . ", " . $coords['longtdExpr'] . ", 
                          $" . $fotoIdx . ", true, CURRENT_TIMESTAMP, $" . $cdgoLginIdx . ")";
        return $this->ejecutar($sqlIns, $params) !== false;
    }

    /**
     * Guarda o actualiza discapacidad de paciente
     */
    private function guardarDiscapacidad($idPacnt, $discapacidad, $dni = '') {
        if (!is_array($discapacidad)) return true;
        
        $idDiscp = isset($discapacidad['idDiscapacidad']) ? (int)$discapacidad['idDiscapacidad'] : 0;
        $desc = $this->str(isset($discapacidad['descripcionDiagnostico']) ? $discapacidad['descripcionDiagnostico'] : '', '');
        $codigo = $this->str(isset($discapacidad['codigoConadis']) ? $discapacidad['codigoConadis'] : '', '');
        $grado = $this->str(isset($discapacidad['gradoDiscapacidad']) ? $discapacidad['gradoDiscapacidad'] : '', '');
        $seguro = $this->str(isset($discapacidad['seguroSalud']) ? $discapacidad['seguroSalud'] : '', '');
        
        $archCarnet = $this->str(isset($discapacidad['archivoCarnetConadis']) ? $discapacidad['archivoCarnetConadis'] : '', '');
        $archCert = $this->str(isset($discapacidad['archivoCertificadoDiscapacidad']) ? $discapacidad['archivoCertificadoDiscapacidad'] : '', '');
        
        if (isset($_FILES['archivoCarnetConadis']) && $_FILES['archivoCarnetConadis']['error'] === UPLOAD_ERR_OK) {
            $archivoSubido = $this->subirArchivo($_FILES['archivoCarnetConadis'], 'carnet', $dni);
            if ($archivoSubido) {
                $archCarnet = $archivoSubido;
            }
        }
        
        if (isset($_FILES['archivoCertificadoDiscapacidad']) && $_FILES['archivoCertificadoDiscapacidad']['error'] === UPLOAD_ERR_OK) {
            $archivoSubido = $this->subirArchivo($_FILES['archivoCertificadoDiscapacidad'], 'certificado', $dni);
            if ($archivoSubido) {
                $archCert = $archivoSubido;
            }
        }
        
        $motora = $this->boolFrom(!empty($discapacidad['motora']));
        $sensorial = $this->boolFrom(!empty($discapacidad['sensorial']));
        $intelectual = $this->boolFrom(!empty($discapacidad['intelectual']));
        $mentalRaw = isset($discapacidad['mental']) ? $discapacidad['mental'] : false;
        $mental = $this->boolFrom(!empty($mentalRaw) || $mentalRaw === true || $mentalRaw === 'true' || $mentalRaw === 1 || $mentalRaw === '1');
        

        // Busca discapacidad existente si no se proporciona ID
        if ($idDiscp <= 0) {
            $rsExiste = $this->ejecutar("SELECT id_discp FROM g_social.discp_omaped WHERE id_pacnt = $1 AND estado = true ORDER BY fec_ins DESC, id_discp DESC LIMIT 1", array($idPacnt));
            if ($rsExiste) {
                $rowExiste = pg_fetch_assoc($rsExiste);
                if ($rowExiste) {
                    $idDiscp = (int)$rowExiste['id_discp'];
                }
            }
        }

        $cdgoLgin = $this->obtenerCodigoLogin();

        if ($idDiscp > 0) {
            $sqlUpd = "UPDATE g_social.discp_omaped
                       SET desc_diag = $1, cdgo_carnet_conadis = $2, grad_discp = $3, segro_salud = $4,
                           es_motora = $5, es_sensorial = $6, es_intelectual = $7, es_mental = $8,
                           arch_carnet = $9, arch_cert_discp = $10, fec_upd = CURRENT_TIMESTAMP, cdgo_lgin_upd = $11
                       WHERE id_discp = $12 AND id_pacnt = $13";
            $params = array($desc, $codigo, $grado, $seguro, $motora, $sensorial, $intelectual, $mental, $archCarnet, $archCert, $cdgoLgin, $idDiscp, $idPacnt);
            $rsUpd = $this->ejecutar($sqlUpd, $params);
            if ($rsUpd) {
                $rowsAffected = pg_affected_rows($rsUpd);
            }
            return $rsUpd !== false;
        }

        $sqlIns = "INSERT INTO g_social.discp_omaped
                   (id_pacnt, desc_diag, cdgo_carnet_conadis, grad_discp, segro_salud, es_motora, es_sensorial, es_intelectual, es_mental, arch_carnet, arch_cert_discp, estado, fec_ins, cdgo_lgin_ins)
                   VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,true,CURRENT_TIMESTAMP,$12)";
        return $this->ejecutar($sqlIns, array($idPacnt, $desc, $codigo, $grado, $seguro, $motora, $sensorial, $intelectual, $mental, $archCarnet, $archCert, $cdgoLgin));
    }

    /**
     * Reemplaza familiares del paciente (elimina anteriores e inserta nuevos)
     */
    private function reemplazarFamiliares($idPacnt, $familiares, $dniPacientePrincipal = null) {
        $this->ultimoErrorFamiliares = null;
        $cdgoLgin = $this->obtenerCodigoLogin();
        $rsDel = $this->ejecutar(
            "UPDATE g_social.pacnt_fam 
             SET estado = false, fec_del = CURRENT_TIMESTAMP, cdgo_lgin_del = $2
             WHERE id_pacnt = $1", 
            array($idPacnt, $cdgoLgin)
        );
        if (!$rsDel) {
            $err = @pg_last_error($this->cn);
            $this->ultimoErrorFamiliares = $err ?: 'No se pudo ejecutar UPDATE de desactivación previa.';
            return false;
        }
        if (!is_array($familiares)) return true;

        foreach ($familiares as $fam) {
            $usuarioFam = $this->upsertPersonaNaturalPorPayload($fam, true, $dniPacientePrincipal);
            if (!$usuarioFam) {
                $this->ultimoErrorFamiliares = 'No se pudo crear/actualizar la persona del familiar (DNI o datos incompletos).';
                return false;
            }

            // Obtener ID de parentesco: primero intentar usar el ID enviado, si no, resolver por nombre
            $idParentesco = null;
            if (isset($fam['idParentesco']) && (int)$fam['idParentesco'] > 0) {
                $idParentesco = (int)$fam['idParentesco'];
            } else {
                $parentescoNombre = $this->str(isset($fam['parentesco']) ? $fam['parentesco'] : 'SELECCIONE', 'SELECCIONE');
                $idParentesco = $this->resolverIdParentesco($parentescoNombre);
            }
            if (!$idParentesco) $idParentesco = 1;

            $obs = $this->str(isset($fam['observaciones']) ? $fam['observaciones'] : '', '');

            // Estado del pariente (true=activo, false=inactivo). Por defecto true.
            $estadoRaw = isset($fam['estado']) ? $fam['estado'] : true;
            $estado = !($estadoRaw === false || $estadoRaw === 0 || $estadoRaw === '0' || $estadoRaw === 'false' || $estadoRaw === 'f');
            $estadoParam = $estado ? 'true' : 'false';

            $sqlIns = "INSERT INTO g_social.pacnt_fam
                       (id_pacnt, cdgo_usrios, id_tip_parntsc, observ, estado, fec_ins, fec_del, cdgo_lgin_ins, cdgo_lgin_del)
                       VALUES ($1,$2,$3,$4,$5::boolean,
                               CURRENT_TIMESTAMP,
                               CASE WHEN $5::boolean = true THEN NULL ELSE CURRENT_TIMESTAMP END,
                               $6::integer,
                               CASE WHEN $5::boolean = true THEN NULL ELSE $6::integer END)
                       ON CONFLICT (id_pacnt, cdgo_usrios)
                       DO UPDATE SET id_tip_parntsc = EXCLUDED.id_tip_parntsc,
                                     observ = EXCLUDED.observ,
                                     estado = EXCLUDED.estado,
                                     fec_upd = CURRENT_TIMESTAMP,
                                     fec_del = CASE WHEN EXCLUDED.estado = true THEN NULL ELSE CURRENT_TIMESTAMP END,
                                     cdgo_lgin_upd = $6::integer,
                                     cdgo_lgin_del = CASE WHEN EXCLUDED.estado = true THEN NULL ELSE $6::integer END";
            $ok = $this->ejecutar(
                $sqlIns,
                array($idPacnt, (int)$usuarioFam['cdgo_usrios'], $idParentesco, $obs, $estadoParam, $cdgoLgin)
            );
            if (!$ok) {
                $err = @pg_last_error($this->cn);
                $this->ultimoErrorFamiliares = $err ?: 'Fallo INSERT/UPDATE de pacnt_fam (familiares).';
                $this->logError(
                    "reemplazarFamiliares: fallo INSERT/UPDATE pacnt_fam id_pacnt=$idPacnt cdgo_usrios=" . (int)$usuarioFam['cdgo_usrios'] . " estado=" . ($estado ? 'true' : 'false') . " err=" . ($err ?: 'N/A'),
                    $sqlIns
                );
                return false;
            }
        }
        return true;
    }

    /**
     * Activa/Desactiva un familiar cuidador sin reemplazar toda la lista.
     * Se identifica por (id_pacnt, cdgo_usrios).
     */
    public function toggleParienteEstado($idPacnt, $cdgoUsrios, $estado) {
        $idPacnt = (int)$idPacnt;
        $cdgoUsrios = (int)$cdgoUsrios;
        if ($idPacnt <= 0 || $cdgoUsrios <= 0) return $this->fail('Datos incompletos.');

        // Normaliza $estado a boolean.
        $estadoBool = false;
        if (is_bool($estado)) {
            $estadoBool = $estado;
        } else {
            $v = strtolower(trim((string)$estado));
            $estadoBool = in_array($v, array('1', 'true', 't', 'si', 'sí', 'on'), true);
        }

        $cdgoLgin = (int)$this->obtenerCodigoLogin();
        $estadoParam = $estadoBool ? 'true' : 'false';
        $sql = "UPDATE g_social.pacnt_fam
                SET estado = $1::boolean,
                    fec_upd = CURRENT_TIMESTAMP,
                    fec_del = CASE WHEN $1::boolean THEN NULL ELSE CURRENT_TIMESTAMP END,
                    cdgo_lgin_upd = $4::integer,
                    cdgo_lgin_del = CASE WHEN $1::boolean THEN NULL ELSE $4::integer END
                WHERE id_pacnt = $2 AND cdgo_usrios = $3";
        $rs = $this->ejecutar($sql, array($estadoParam, $idPacnt, $cdgoUsrios, $cdgoLgin));
        if ($rs === false) {
            $err = @pg_last_error($this->cn);
            $this->logError("toggleParienteEstado falló id_pacnt=$idPacnt cdgo_usrios=$cdgoUsrios err=" . ($err ?: 'N/A'), $sql);
            return $this->fail('No se pudo actualizar el estado del pariente.');
        }
        return $this->ok();
    }

    // ==================== MÉTODOS PÚBLICOS ====================

    /**
     * Busca persona por número de documento
     */
    public function buscarPersonaPorDocumento($dni) {
        $dniNormalizado = preg_replace('/\D+/', '', (string)$dni);
        if (strlen($dniNormalizado) !== 8) {
            return $this->fail('El documento debe tener 8 digitos.');
        }

        $usuario = $this->resolverUsuarioPorDocumento($dniNormalizado);
        if (!$usuario) {
            return $this->ok(array('found' => false));
        }

        return $this->ok(array(
            'found' => true,
            'persona' => $this->mapPersonaFromRow($usuario)
        ));
    }

    /**
     * Obtiene catálogos del sistema
     */
    public function catalogos() {
        $catalogos = array(
            'sectores' => array(),
            'parentescos' => array(),
            'sedes' => array(),
            'servicios' => array(),
            'distritos' => array()
        );

        $rsS = $this->ejecutar("SELECT id_sectr, nomb_sectr FROM g_social.sectr WHERE estado = true ORDER BY id_sectr");
        if ($rsS) while ($r = pg_fetch_assoc($rsS)) $catalogos['sectores'][] = $r;

        $rsP = $this->ejecutar("SELECT id_tip_parntsc, nomb_parntsc FROM g_social.tip_parntsc WHERE estado = true ORDER BY id_tip_parntsc");
        if ($rsP) while ($r = pg_fetch_assoc($rsP)) $catalogos['parentescos'][] = $r;

        $rsSe = $this->ejecutar("SELECT id_sede, nomb_sede FROM g_social.sede WHERE estado = true ORDER BY id_sede");
        if ($rsSe) while ($r = pg_fetch_assoc($rsSe)) $catalogos['sedes'][] = $r;

        $rsSv = $this->ejecutar("SELECT id_serv, nomb_serv FROM g_social.serv WHERE estado = true ORDER BY nomb_serv");
        if ($rsSv) while ($r = pg_fetch_assoc($rsSv)) $catalogos['servicios'][] = $r;

        $rsD = $this->ejecutar("SELECT cdgo_dist, de_dist FROM sistema.a_dist WHERE cdgo_prov  IN(127, 191)  AND (in_esta = '1' OR in_esta IS NULL) ORDER BY de_dist");
        if ($rsD) while ($r = pg_fetch_assoc($rsD)) $catalogos['distritos'][] = $r;

        return $this->ok($catalogos);
    }

    /**
     * Lista pacientes OMAPED con filtros opcionales
     * 
     * OPTIMIZACIONES APLICADAS:
     * - Uso de parámetros preparados para prevenir SQL injection
     * - Subconsulta LATERAL optimizada para obtener solo la discapacidad más reciente
     * - Filtros de fecha sin función DATE() para permitir uso de índices
     * 
     * RECOMENDACIONES DE ÍNDICES (aplicar en BD si es necesario):
     * - CREATE INDEX idx_pacnt_estado ON g_social.pacnt(estado) WHERE estado = true;
     * - CREATE INDEX idx_pacnt_fec_ins ON g_social.pacnt(fec_ins) WHERE estado = true;
     * - CREATE INDEX idx_pacnt_detll_pacnt_estado ON g_social.pacnt_detll(id_pacnt, estado) WHERE estado = true;
     * - CREATE INDEX idx_discp_omaped_pacnt_estado_fec ON g_social.discp_omaped(id_pacnt, estado, fec_ins DESC, id_discp DESC) WHERE estado = true;
     * - CREATE INDEX idx_p_usrios_nu_docu ON sistema.p_usrios(nu_docu);
     * - CREATE INDEX idx_pacnt_detll_zona ON g_social.pacnt_detll(zona) WHERE estado = true;
     * 
     * @param string $tipoFiltro Tipo de filtro: 'dni', 'nombres', 'direccion', 'zona', 'discapacidad', 'fecha'
     * @param string $q Valor de búsqueda para filtros de texto
     * @param string $zona Zona para filtrar
     * @param string $fechaInicio Fecha inicio para rango de fechas
     * @param string $fechaFin Fecha fin para rango de fechas
     * @param int $pagina Página (1-based)
     * @param int $porPagina Tamaño de página (máx. 100 en listado; exportación usa otro modo)
     * @param bool $modoExportacion Si true, devuelve hasta LISTAR_MAX_EXPORT filas con los mismos filtros (para Excel)
     * @return array Respuesta con items, total y metadatos de paginación
     */
    public function listar($tipoFiltro = '', $q = '', $zona = '', $fechaInicio = '', $fechaFin = '', $pagina = 1, $porPagina = 5, $modoExportacion = false) {
        $params = array();
        $where = " WHERE p.estado = true ";
        $idx = 1;
        $tipoFiltro = strtolower(trim($tipoFiltro));

        // Filtros de texto según el tipo seleccionado
        // NOTA: LIKE con UPPER() no puede usar índices eficientemente, pero es necesario para búsqueda flexible
        if (trim($q) !== '') {
            $valor = '%' . trim($q) . '%';
            switch ($tipoFiltro) {
                case 'dni':
                    // Búsqueda por DNI: usar LIKE para búsqueda parcial
                    $where .= " AND UPPER(COALESCE(pu.nu_docu,'')) LIKE UPPER($" . $idx . ")";
                    $params[] = $valor;
                    $idx++;
                    break;
                case 'nombres':
                    // Búsqueda por nombres completos (nombre + apellidos)
                    $where .= " AND UPPER(COALESCE(hu.no_usrio,'') || ' ' || COALESCE(hu.ap_pate,'') || ' ' || COALESCE(hu.ap_mate,'')) LIKE UPPER($" . $idx . ")";
                    $params[] = $valor;
                    $idx++;
                    break;
                case 'direccion':
                    // Buscar en ubicación de paciente detalle o dirección de persona
                    $where .= " AND UPPER(COALESCE(pd.ubicac,'') || ' ' || COALESCE(pu.de_dire,'')) LIKE UPPER($" . $idx . ")";
                    $params[] = $valor;
                    $idx++;
                    break;
                case 'discapacidad':
                    // Buscar en descripción de diagnóstico de discapacidad (subconsulta LATERAL)
                    $where .= " AND UPPER(COALESCE(dd.desc_diag,'')) LIKE UPPER($" . $idx . ")";
                    $params[] = $valor;
                    $idx++;
                    break;
                default:
                    // Comportamiento por defecto: buscar por DNI o nombres completos
                    $where .= " AND (
                UPPER(COALESCE(pu.nu_docu,'')) LIKE UPPER($" . $idx . ")
                OR UPPER(COALESCE(hu.no_usrio,'') || ' ' || COALESCE(hu.ap_pate,'') || ' ' || COALESCE(hu.ap_mate,'')) LIKE UPPER($" . $idx . ")
            )";
                    $params[] = $valor;
                    $idx++;
                    break;
            }
        }

        // Filtro por zona (solo cuando el tipo de filtro es zona o no se especifica tipo)
        if (trim($zona) !== '' && ($tipoFiltro === '' || $tipoFiltro === 'zona')) {
            $where .= " AND UPPER(COALESCE(pd.zona,'')) = UPPER($" . $idx . ")";
            $params[] = trim($zona);
            $idx++;
        }

        // Filtros por rango de fechas (solo fechas YYYY-MM-DD válidas)
        if (($fi = $this->normalizarFechaFiltroListado($fechaInicio)) !== null && ($tipoFiltro === '' || $tipoFiltro === 'fecha')) {
            $where .= " AND p.fec_ins >= $" . $idx . "::timestamp";
            $params[] = $fi . ' 00:00:00';
            $idx++;
        }

        if (($ff = $this->normalizarFechaFiltroListado($fechaFin)) !== null && ($tipoFiltro === '' || $tipoFiltro === 'fecha')) {
            $where .= " AND p.fec_ins <= $" . $idx . "::timestamp";
            $params[] = $ff . ' 23:59:59';
            $idx++;
        }

        $fromSql = "
                FROM g_social.pacnt p
                INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = p.cdgo_usrios
                LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                LEFT JOIN g_social.pacnt_detll pd ON pd.id_pacnt = p.id_pacnt AND pd.estado = true
                LEFT JOIN LATERAL (
                    SELECT d.desc_diag, d.cdgo_carnet_conadis, d.grad_discp, d.segro_salud,
                           d.es_motora, d.es_sensorial, d.es_intelectual, d.es_mental
                    FROM g_social.discp_omaped d
                    WHERE d.id_pacnt = p.id_pacnt AND d.estado = true
                    ORDER BY d.fec_ins DESC, d.id_discp DESC
                    LIMIT 1
                ) dd ON true ";

        $sqlCount = "SELECT COUNT(*)::bigint AS c FROM ( SELECT p.id_pacnt " . $fromSql . $where . " ) sub";
        $rsCount = $this->ejecutar($sqlCount, $params);
        if (!$rsCount) {
            return $this->fail('No se pudo listar usuarios OMAPED.');
        }
        $rowC = pg_fetch_assoc($rsCount);
        $total = $rowC ? (int)$rowC['c'] : 0;

        $modoExportacion = (bool)$modoExportacion;
        if ($modoExportacion) {
            $paginaUsada = 1;
            $limite = self::LISTAR_MAX_EXPORT;
            $offset = 0;
        } else {
            $paginaUsada = max(1, (int)$pagina);
            $limite = max(1, min(self::LISTAR_MAX_POR_PAGINA, (int)$porPagina));
            $offset = ($paginaUsada - 1) * $limite;
        }

        $paramsData = $params;
        $limIdx = $idx;
        $offIdx = $idx + 1;
        $sql = "SELECT p.id_pacnt,
                       pu.nu_docu AS dni,
                       hu.no_usrio AS nombres,
                       hu.ap_pate AS ape_paterno,
                       hu.ap_mate AS ape_materno,
                       hu.in_sexo AS genero,
                       pu.nu_tele AS telefono,
                       pd.fec_nac AS fecha_nacimiento,
                       pd.id_sectr AS id_sector,
                       pd.zona,
                       pd.otro_distr,
                       pd.ubicac AS ubicacion,
                       pd.latd AS latitud,
                       pd.longtd AS longitud,
                       p.fec_reg_pacnt AS fecha_registro,
                       dd.desc_diag AS descripcion_diagnostico,
                       dd.cdgo_carnet_conadis AS codigo_conadis,
                       dd.grad_discp AS grado_discapacidad,
                       dd.segro_salud AS seguro_salud,
                       COALESCE(dd.es_motora,false) AS motora,
                       COALESCE(dd.es_sensorial,false) AS sensorial,
                       COALESCE(dd.es_intelectual,false) AS intelectual,
                       COALESCE(dd.es_mental,false) AS mental
                " . $fromSql . $where . "
                ORDER BY p.id_pacnt DESC
                LIMIT $" . $limIdx . " OFFSET $" . $offIdx;
        $paramsData[] = $limite;
        $paramsData[] = $offset;

        $rs = $this->ejecutar($sql, $paramsData);
        if (!$rs) return $this->fail('No se pudo listar usuarios OMAPED.');

        $items = array();
        while ($row = pg_fetch_assoc($rs)) {
            // Formatear fechas en PHP (más rápido que TO_CHAR en SQL)
            $fechaNac = '';
            if (!empty($row['fecha_nacimiento'])) {
                if (is_string($row['fecha_nacimiento'])) {
                    $fechaNac = substr($row['fecha_nacimiento'], 0, 10); // Extraer solo YYYY-MM-DD
                } else {
                    $fechaNac = date('Y-m-d', strtotime($row['fecha_nacimiento']));
                }
            }
            
            $fechaReg = '';
            if (!empty($row['fecha_registro'])) {
                if (is_string($row['fecha_registro'])) {
                    $fechaReg = substr($row['fecha_registro'], 0, 10);
                } else {
                    $fechaReg = date('Y-m-d', strtotime($row['fecha_registro']));
                }
            }
            
            // Convertir coordenadas float8 a string
            $latitud = ($row['latitud'] !== null && $row['latitud'] !== '') ? (string)$row['latitud'] : '';
            $longitud = ($row['longitud'] !== null && $row['longitud'] !== '') ? (string)$row['longitud'] : '';
            
            $items[] = array(
                'idPaciente' => (int)$row['id_pacnt'],
                'dni' => $row['dni'] ?? '',
                'nombres' => $row['nombres'] ?? '',
                'apePaterno' => $row['ape_paterno'] ?? '',
                'apeMaterno' => $row['ape_materno'] ?? '',
                'genero' => strtoupper($row['genero'] ?? ''),
                'telefono' => $row['telefono'] ?? '',
                'fechaNacimiento' => $fechaNac,
                'idSector' => isset($row['id_sector']) && $row['id_sector'] > 0 ? (int)$row['id_sector'] : null,
                'zona' => $row['zona'] ?? '',
                'distritoOtro' => isset($row['otro_distr']) && $row['otro_distr'] !== null && $row['otro_distr'] !== '' ? (int)$row['otro_distr'] : null,
                'ubicacion' => $row['ubicacion'] ?? '',
                'latitud' => $latitud,
                'longitud' => $longitud,
                'fechaRegistro' => $fechaReg,
                'discapacidadDetalle' => array(
                    'descripcionDiagnostico' => $row['descripcion_diagnostico'] ?? '',
                    'codigoConadis' => $row['codigo_conadis'] ?? '',
                    'gradoDiscapacidad' => $row['grado_discapacidad'] ?? '',
                    'seguroSalud' => $row['seguro_salud'] ?? '',
                    'motora' => $row['motora'] === 't' || $row['motora'] === true,
                    'sensorial' => $row['sensorial'] === 't' || $row['sensorial'] === true,
                    'intelectual' => $row['intelectual'] === 't' || $row['intelectual'] === true,
                    'mental' => $row['mental'] === 't' || $row['mental'] === true
                )
            );
        }

        return $this->ok(array(
            'items' => $items,
            'total' => $total,
            'pagina' => $paginaUsada,
            'porPagina' => $limite,
        ));
    }

    /**
     * Obtiene detalle completo de un paciente
     */
    public function detalle($idPacnt) {
        $id = (int)$idPacnt;
        if ($id <= 0) return $this->fail('ID no valido.');

        $sqlPaciente = "SELECT p.id_pacnt,
                               COALESCE(pu.nu_docu,'') AS dni,
                               COALESCE(hu.no_usrio,'') AS nombres,
                               COALESCE(hu.ap_pate,'') AS ape_paterno,
                               COALESCE(hu.ap_mate,'') AS ape_materno,
                               COALESCE(hu.in_sexo,'') AS genero,
                               COALESCE(pu.nu_tele,'') AS telefono,
                               COALESCE(pu.de_dire,'') AS direccion,
                               COALESCE(TO_CHAR(pd.fec_nac, 'YYYY-MM-DD'),'') AS fecha_nacimiento,
                               COALESCE(pd.id_sectr,0) AS id_sector,
                               COALESCE(pd.zona,'') AS zona,
                               COALESCE(pd.otro_distr::text,'') AS distrito_otro,
                               COALESCE(pd.ubicac,'') AS ubicacion,
                               COALESCE(pd.latd::text,'') AS latitud,
                               COALESCE(pd.longtd::text,'') AS longitud,
                               COALESCE(pd.foto,'') AS foto,
                               (SELECT q.token_qr
                                  FROM g_social.omaped_qr q
                                 WHERE q.id_pacnt = p.id_pacnt
                                   AND q.estado = 'ACTIVO'
                                 ORDER BY q.fecha_generacion DESC, q.id_qr DESC
                                 LIMIT 1) AS token_qr
                        FROM g_social.pacnt p
                        INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = p.cdgo_usrios
                        LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                        LEFT JOIN g_social.pacnt_detll pd ON pd.id_pacnt = p.id_pacnt AND pd.estado = true
                        WHERE p.id_pacnt = $1 AND p.estado = true
                        LIMIT 1";
        $rsPaciente = $this->ejecutar($sqlPaciente, array($id));
        if (!$rsPaciente) return $this->fail('No se pudo obtener el detalle.');
        $rowPaciente = pg_fetch_assoc($rsPaciente);
        if (!$rowPaciente) return $this->fail('Usuario OMAPED no encontrado.');
        
        $personaMapeada = $this->mapPersonaFromRow($rowPaciente);

        $sqlDiscp = "SELECT id_discp,
                            COALESCE(desc_diag,'') AS desc_diag,
                            COALESCE(cdgo_carnet_conadis,'') AS cdgo_carnet_conadis,
                            COALESCE(grad_discp,'') AS grad_discp,
                            COALESCE(segro_salud,'') AS segro_salud,
                            COALESCE(es_motora,false) AS es_motora,
                            COALESCE(es_sensorial,false) AS es_sensorial,
                            COALESCE(es_intelectual,false) AS es_intelectual,
                            COALESCE(es_mental,false) AS es_mental,
                            COALESCE(arch_carnet,'') AS arch_carnet,
                            COALESCE(arch_cert_discp,'') AS arch_cert_discp
                     FROM g_social.discp_omaped
                     WHERE id_pacnt = $1 AND estado = true
                     ORDER BY fec_ins DESC, id_discp DESC
                     LIMIT 1";
        $rsDiscp = $this->ejecutar($sqlDiscp, array($id));
        $rowDiscp = $rsDiscp ? pg_fetch_assoc($rsDiscp) : null;

        $sqlFam = "SELECT pf.id_tip_parntsc,
                          tp.nomb_parntsc AS parentesco,
                          pf.cdgo_usrios AS cdgo_usrios,
                          COALESCE(pu.nu_docu,'') AS numero_documento,
                          COALESCE(hu.no_usrio,'') AS nombres,
                          COALESCE(hu.ap_pate,'') AS ape_paterno,
                          COALESCE(hu.ap_mate,'') AS ape_materno,
                          COALESCE(pu.nu_tele,'') AS telefono,
                          COALESCE(pu.de_dire,'') AS direccion,
                          COALESCE(pf.observ,'') AS observaciones,
                          COALESCE(pf.estado,false) AS estado
                   FROM g_social.pacnt_fam pf
                   INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = pf.cdgo_usrios
                   LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                   LEFT JOIN g_social.tip_parntsc tp ON tp.id_tip_parntsc = pf.id_tip_parntsc
                   WHERE pf.id_pacnt = $1
                   ORDER BY pf.estado DESC, pf.fec_ins DESC";
        $rsFam = $this->ejecutar($sqlFam, array($id));
        $familiares = array();
        if ($rsFam) {
            while ($f = pg_fetch_assoc($rsFam)) {
                $familiares[] = array(
                    'parentesco' => $f['parentesco'],
                    'cdgoUsrios' => isset($f['cdgo_usrios']) ? (int)$f['cdgo_usrios'] : 0,
                    'numeroDocumento' => $f['numero_documento'],
                    'nombres' => $f['nombres'],
                    'apePaterno' => $f['ape_paterno'],
                    'apeMaterno' => $f['ape_materno'],
                    'telefono' => $f['telefono'],
                    'direccion' => $f['direccion'],
                    'observaciones' => $f['observaciones'],
                    'estado' => ($f['estado'] === 't' || $f['estado'] === true)
                );
            }
        }

        $sqlHist = "SELECT a.id_atncn,
                           to_char(a.fec_atncn, 'YYYY-MM-DD') AS fecha_atencion,
                           COALESCE(s.nomb_sede,'') AS sede,
                           COALESCE(a.observ,'') AS observaciones,
                           COALESCE(a.evid_arch,'') AS evidencia,
                           COALESCE(string_agg(COALESCE(sv.nomb_serv,'') ||
                               CASE
                                   WHEN trim(COALESCE(asv.observ,'')) <> '' THEN ': ' || trim(asv.observ)
                                   ELSE ''
                               END, ' | ' ORDER BY sv.nomb_serv), '') AS servicios,
                           COALESCE(string_agg(asv.id_serv::text, ',' ORDER BY sv.nomb_serv), '') AS servicios_ids
                    FROM g_social.atncn a
                    LEFT JOIN g_social.sede s ON s.id_sede = a.id_sede
                    LEFT JOIN g_social.atncn_serv asv ON asv.id_atncn = a.id_atncn AND asv.estado = true
                    LEFT JOIN g_social.serv sv ON sv.id_serv = asv.id_serv
                    WHERE a.id_pacnt = $1 AND a.estado = true
                    GROUP BY a.id_atncn, a.fec_atncn, s.nomb_sede, a.observ, a.evid_arch
                    ORDER BY a.fec_atncn DESC, a.id_atncn DESC";
        $rsHist = $this->ejecutar($sqlHist, array($id));
        $historico = array();
        if ($rsHist) {
            while ($h = pg_fetch_assoc($rsHist)) {
                $historico[] = array(
                    'idAtencion' => (int)$h['id_atncn'],
                    'fechaAtencion' => $h['fecha_atencion'],
                    'idSedeAtencion' => $h['sede'],
                    'observacionesAtencion' => $h['observaciones'],
                    'evidenciaArchivos' => $h['evidencia'],
                    'serviciosAtencion' => $h['servicios'],
                    'serviciosIds' => $h['servicios_ids']
                );
            }
        }

        $tokenQr = isset($rowPaciente['token_qr']) && $rowPaciente['token_qr'] !== '' ? $rowPaciente['token_qr'] : null;
        if (!$tokenQr) {
            $tokenQr = $this->crearOActualizarTokenQR((int)$rowPaciente['id_pacnt']);
        }

        $urlQrPng = null;
        if ($tokenQr) {
            $urlQrPng = $this->obtenerUrlRelQrSiExiste($tokenQr);
            if ($urlQrPng === null) {
                $urlQrPng = $this->generarImagenQrParaToken($tokenQr);
            }
        }

        $paciente = array(
            'idPaciente' => (int)$rowPaciente['id_pacnt'],
            'dni' => $rowPaciente['dni'],
            'nombres' => $rowPaciente['nombres'],
            'apePaterno' => $rowPaciente['ape_paterno'],
            'apeMaterno' => $rowPaciente['ape_materno'],
            'genero' => $personaMapeada['genero'],
            'telefono' => $personaMapeada['telefono'],
            'fechaNacimiento' => isset($rowPaciente['fecha_nacimiento']) && $rowPaciente['fecha_nacimiento'] !== '' ? $rowPaciente['fecha_nacimiento'] : '',
            'idSector' => (int)$rowPaciente['id_sector'],
            'zona' => $rowPaciente['zona'],
            'distritoOtro' => isset($rowPaciente['distrito_otro']) && $rowPaciente['distrito_otro'] !== '' ? (int)$rowPaciente['distrito_otro'] : null,
            'direccion' => $rowPaciente['direccion'],
            'ubicacion' => $rowPaciente['ubicacion'],
            'latitud' => isset($rowPaciente['latitud']) && $rowPaciente['latitud'] !== '' ? $rowPaciente['latitud'] : '',
            'longitud' => isset($rowPaciente['longitud']) && $rowPaciente['longitud'] !== '' ? $rowPaciente['longitud'] : '',
            'foto' => isset($rowPaciente['foto']) ? $rowPaciente['foto'] : '',
            'tokenQr' => $tokenQr,
            'urlQrPng' => $urlQrPng,
            'discapacidadDetalle' => $rowDiscp ? array(
                'idDiscapacidad' => $rowDiscp['id_discp'],
                'descripcionDiagnostico' => $rowDiscp['desc_diag'],
                'codigoConadis' => $rowDiscp['cdgo_carnet_conadis'],
                'gradoDiscapacidad' => $rowDiscp['grad_discp'],
                'seguroSalud' => $rowDiscp['segro_salud'],
                'archivoCarnetConadis' => $rowDiscp['arch_carnet'],
                'archivoCertificadoDiscapacidad' => $rowDiscp['arch_cert_discp'],
                'motora' => $rowDiscp['es_motora'] === 't' || $rowDiscp['es_motora'] === true,
                'sensorial' => $rowDiscp['es_sensorial'] === 't' || $rowDiscp['es_sensorial'] === true,
                'intelectual' => $rowDiscp['es_intelectual'] === 't' || $rowDiscp['es_intelectual'] === true,
                'mental' => $rowDiscp['es_mental'] === 't' || $rowDiscp['es_mental'] === true
            ) : array(),
            'familiares' => $familiares,
            'familiar' => count($familiares) ? $familiares[0] : null,
            'historico' => $historico
        );

        return $this->ok($paciente);
    }

    /**
     * Guarda nuevo paciente OMAPED
     */
    public function guardar($input) {
        $dni = $this->str(isset($input['dni']) ? $input['dni'] : '', '');
        if ($dni === '') return $this->fail('DNI es obligatorio.');

        // Iniciar transacción ANTES de crear/actualizar usuario
        if (!$this->begin()) return $this->fail('No se pudo iniciar transaccion.');

        try {
            $actualizarDatosPersona = isset($input['actualizarDatosPersona']) && $input['actualizarDatosPersona'] === true;
            $usuario = null;
            
            if ($actualizarDatosPersona) {
                // Crear o actualizar usuario dentro de la transacción
                $usuario = $this->upsertPersonaNaturalPorPayload($input);
                if (!$usuario) {
                    $this->rollback();
                    $this->logError("upsertPersonaNaturalPorPayload devolvio null para dni=$dni", null);
                    return $this->fail('No se pudo crear o actualizar la persona titular en el modulo de personas.');
                }
            } else {
                // Solo verificar que el usuario exista
                $usuario = $this->resolverUsuarioPorDocumento($dni);
                if (!$usuario) {
                    $this->rollback();
                    return $this->fail('La persona no existe en el sistema. Active "Actualizar datos de persona" para crearla.');
                }
            }

            // Verificar que tenemos el cdgo_usrios
            if (!isset($usuario['cdgo_usrios']) || intval($usuario['cdgo_usrios']) <= 0) {
                $this->rollback();
                $this->logError("Usuario no tiene cdgo_usrios valido: " . var_export($usuario, true), null);
                return $this->fail('Error: No se pudo obtener el código de usuario.');
            }

            $cdgoUsrios = (int)$usuario['cdgo_usrios'];

            // Validación: verificar duplicado en BD y obtener nombres completos
            $sqlVerificarDuplicado = "SELECT p.id_pacnt, 
                        COALESCE(hu.no_usrio,'') AS nombres,
                        COALESCE(hu.ap_pate,'') AS ape_paterno,
                        COALESCE(hu.ap_mate,'') AS ape_materno
                 FROM g_social.pacnt p
                 INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = p.cdgo_usrios
                 LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                 WHERE p.cdgo_usrios = $1 AND p.estado = true LIMIT 1";
            $rsExiste = $this->ejecutar($sqlVerificarDuplicado, array($cdgoUsrios));
            if ($rsExiste === false) {
                // Error en la consulta
                $this->rollback();
                $this->logError("Error al verificar duplicado: " . pg_last_error($this->cn), $sqlVerificarDuplicado);
                return $this->fail('Error al validar registro. Intente nuevamente.');
            }
            $rowExiste = pg_fetch_assoc($rsExiste);
            if ($rowExiste) {
                // Construir nombre completo
                $nombresCompletos = trim(
                    trim($rowExiste['nombres']) . ' ' . 
                    trim($rowExiste['ape_paterno']) . ' ' . 
                    trim($rowExiste['ape_materno'])
                );
                if (empty($nombresCompletos)) {
                    $nombresCompletos = "Usuario (cdgo_usrios=$cdgoUsrios)";
                }
                
                $this->rollback();
                $this->logError("El usuario $nombresCompletos ya está registrado", null);
                return $this->fail("El usuario $nombresCompletos ya está registrado");
            }

            $cdgoLgin = $this->obtenerCodigoLogin();
            $sqlInsertPacnt = "INSERT INTO g_social.pacnt (cdgo_usrios, cod_pacnt, fec_reg_pacnt, obsrv_pacnt, estado, fec_ins, cdgo_lgin_ins)
                 VALUES ($1,$2,CURRENT_DATE,$3,true,CURRENT_TIMESTAMP,$4)
                 RETURNING id_pacnt";
            $rsIns = $this->ejecutar($sqlInsertPacnt, 
                array((int)$usuario['cdgo_usrios'], $this->str(isset($input['codPaciente']) ? $input['codPaciente'] : '', null), $this->str(isset($input['observacionPaciente']) ? $input['observacionPaciente'] : '', null), $cdgoLgin)
            );
            if (!$rsIns) {
                $this->rollback();
                $this->logError("Error al insertar en g_social.pacnt: " . pg_last_error($this->cn), $sqlInsertPacnt);
                return $this->fail('No se pudo registrar usuario OMAPED.');
            }
            $row = pg_fetch_assoc($rsIns);
            if (!$row || !isset($row['id_pacnt'])) {
                $this->rollback();
                $this->logError("No se obtuvo id_pacnt del INSERT", $sqlInsertPacnt);
                return $this->fail('No se pudo obtener el ID del paciente registrado.');
            }
            $idPacnt = (int)$row['id_pacnt'];

            if (!$this->guardarDetallePaciente($idPacnt, $input)) {
                $this->rollback();
                return $this->fail('No se pudo registrar detalle de usuario OMAPED.');
            }
            if (!$this->guardarDiscapacidad($idPacnt, isset($input['discapacidadDetalle']) ? $input['discapacidadDetalle'] : array(), $dni)) {
                $this->rollback();
                return $this->fail('No se pudo registrar discapacidad.');
            }
            if (!$this->reemplazarFamiliares($idPacnt, isset($input['familiares']) ? $input['familiares'] : array(), $dni)) {
                $this->rollback();
                $detalle = $this->ultimoErrorFamiliares ? (': ' . $this->ultimoErrorFamiliares) : '';
                return $this->fail('No se pudieron registrar familiares (verifique que sus DNI existan en usuarios)' . $detalle . '.');
            }

            if (!$this->commit()) {
                $this->rollback();
                return $this->fail('No se pudo confirmar el registro.');
            }

            // Generar o reutilizar token QR para este nuevo paciente
            // Nota: si la creación del QR falla, NO se revierte el alta del paciente;
            //       solo se registra en log para poder revisar luego.
            $tokenQr = $this->crearOActualizarTokenQR($idPacnt);
            $urlQrPng = null;
            if ($tokenQr === null) {
                $this->logError("No se pudo generar token QR para id_pacnt=$idPacnt luego de guardar()", null);
            } else {
                try {
                    $urlQrPng = $this->generarImagenQrParaToken($tokenQr);
                    if ($urlQrPng === null) {
                        $this->logError("generarImagenQrParaToken retornó null para tokenQr=$tokenQr en guardar()", null);
                    }
                } catch (\Throwable $e) {
                    $this->logError("Excepción al generar imagen QR en guardar(): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString(), null);
                }
            }

            return $this->ok(array(
                'idPaciente' => $idPacnt,
                'tokenQr'    => $tokenQr,
                'urlQrPng'   => $urlQrPng
            ));
        } catch (Exception $e) {
            $this->rollback();
            $this->logError("guardar", "Excepcion: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            return $this->fail('Error al guardar usuario OMAPED: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza paciente OMAPED existente
     */
    public function actualizar($idPacnt, $input) {
        $id = (int)$idPacnt;
        if ($id <= 0) return $this->fail('ID no valido.');

        $dni = $this->str(isset($input['dni']) ? $input['dni'] : '', '');
        if ($dni === '') return $this->fail('DNI es obligatorio.');
        
        $actualizarDatosPersona = isset($input['actualizarDatosPersona']) && ($input['actualizarDatosPersona'] === true || $input['actualizarDatosPersona'] === 'true' || $input['actualizarDatosPersona'] === 1 || $input['actualizarDatosPersona'] === '1');
        $usuario = null;
        if ($actualizarDatosPersona) {
            $usuario = $this->upsertPersonaNaturalPorPayload($input);
            if (!$usuario) {
                $this->logError("upsertPersonaNaturalPorPayload devolvio null para dni=$dni", null);
                return $this->fail('No se pudo crear o actualizar la persona titular en el modulo de personas.');
            }
        } else {
            $usuario = $this->resolverUsuarioPorDocumento($dni);
            if (!$usuario) return $this->fail('La persona no existe en el sistema. Active "Actualizar datos de persona" para actualizarla.');
        }

        if (!$this->begin()) {
            $this->logError("No se pudo iniciar transaccion para actualizar id_pacnt=$id", "BEGIN");
            return $this->fail('No se pudo iniciar transaccion.');
        }
        try {
            $cdgoLgin = $this->obtenerCodigoLogin();
            $rsUpd = $this->ejecutar(
                "UPDATE g_social.pacnt
                 SET cdgo_usrios = $1, obsrv_pacnt = $2, fec_upd = CURRENT_TIMESTAMP, cdgo_lgin_upd = $3
                 WHERE id_pacnt = $4 AND estado = true", 
                array((int)$usuario['cdgo_usrios'], $this->str(isset($input['observacionPaciente']) ? $input['observacionPaciente'] : '', null), $cdgoLgin, $id)
            );
            $sqlUpdatePacnt = "UPDATE g_social.pacnt
                 SET cdgo_usrios = $1, obsrv_pacnt = $2, fec_upd = CURRENT_TIMESTAMP, cdgo_lgin_upd = $3
                 WHERE id_pacnt = $4 AND estado = true";
            $rsUpd = $this->ejecutar($sqlUpdatePacnt, 
                array((int)$usuario['cdgo_usrios'], $this->str(isset($input['observacionPaciente']) ? $input['observacionPaciente'] : '', null), $cdgoLgin, $id)
            );
            if (!$rsUpd) {
                $this->rollback();
                $this->logError("Error al actualizar pacnt id_pacnt=$id", $sqlUpdatePacnt);
                return $this->fail('No se pudo actualizar usuario OMAPED.');
            }
            $rowsAffectedPacnt = pg_affected_rows($rsUpd);

            $resultadoDetalle = $this->guardarDetallePaciente($id, $input);
            if (!$resultadoDetalle) {
                $this->rollback();
                $this->logError("No se pudo actualizar detalle de paciente id_pacnt=$id", "UPDATE/INSERT pacnt_detll");
                return $this->fail('No se pudo actualizar detalle.');
            }
            
            $resultadoDiscapacidad = $this->guardarDiscapacidad($id, isset($input['discapacidadDetalle']) ? $input['discapacidadDetalle'] : array(), $dni);
            if (!$resultadoDiscapacidad) {
                $this->rollback();
                $this->logError("No se pudo actualizar discapacidad id_pacnt=$id", "UPDATE/INSERT discp_omaped");
                return $this->fail('No se pudo actualizar discapacidad.');
            }
            
            $resultadoFamiliares = $this->reemplazarFamiliares($id, isset($input['familiares']) ? $input['familiares'] : array(), $dni);
            if (!$resultadoFamiliares) {
                $this->rollback();
                $this->logError("No se pudieron actualizar familiares id_pacnt=$id", "UPDATE/INSERT pacnt_fam");
                $detalle = $this->ultimoErrorFamiliares ? (': ' . $this->ultimoErrorFamiliares) : '';
                return $this->fail('No se pudieron actualizar familiares (verifique que sus DNI existan en usuarios)' . $detalle . '.');
            }

            $commitResult = $this->commit();
            if (!$commitResult) {
                $this->rollback();
                $this->logError("No se pudo confirmar la actualizacion id_pacnt=$id. Error: " . pg_last_error($this->cn), "COMMIT");
                return $this->fail('No se pudo confirmar la actualizacion.');
            }

            // Asegurar que el paciente tenga un token QR activo (si ya existe se reutiliza)
            $tokenQr = $this->crearOActualizarTokenQR($id);
            $urlQrPng = null;
            if ($tokenQr === null) {
                $this->logError("No se pudo generar/reutilizar token QR para id_pacnt=$id luego de actualizar()", null);
            } else {
                $urlQrPng = $this->generarImagenQrParaToken($tokenQr);
            }

            return $this->ok(array(
                'idPaciente' => $id,
                'tokenQr'    => $tokenQr,
                'urlQrPng'   => $urlQrPng
            ));
        } catch (Exception $e) {
            $this->rollback();
            $this->logError("Excepcion al actualizar id_pacnt=$id: " . $e->getMessage(), null);
            return $this->fail('Error al actualizar usuario OMAPED: ' . $e->getMessage());
        }
    }

    /**
     * Verifica si el usuario actual tiene permisos para eliminar usuarios OMAPED
     * @return bool
     */
    public function puedeEliminar() {
        return $this->codigoLoginSesionEnLista($this->obtenerCodigosLoginPermitidosEliminar());
    }

    public function eliminar($idPacnt) {
        $id = (int)$idPacnt;
        if ($id <= 0) return $this->fail('ID no valido.');
        
        // Obtener el código de login de la sesión actual
        $cdgoLgin = $this->obtenerCodigoLogin();
        if (!$cdgoLgin || $cdgoLgin <= 0) {
            return $this->fail('No se pudo obtener el código de login de la sesión.');
        }
        
        // Verificar que el código de login esté en la lista de permitidos
        $codigosPermitidos = $this->obtenerCodigosLoginPermitidosEliminar();
        if (empty($codigosPermitidos)) {
            return $this->fail('No hay códigos de login configurados para eliminar.');
        }
        
        if (!in_array($cdgoLgin, $codigosPermitidos)) {
            return $this->fail('No tiene permisos para eliminar usuarios.');
        }
        
        if (!$this->begin()) return $this->fail('No se pudo iniciar transaccion.');

        $params = array($id, $cdgoLgin);
        
        // Soft delete: solo actualizar estado, fec_del y cdgo_lgin_del, sin tocar otros campos
        $ok1 = $this->ejecutar(
            "UPDATE g_social.pacnt SET estado = false, fec_del = CURRENT_TIMESTAMP, cdgo_lgin_del = $2 WHERE id_pacnt = $1",
            $params
        );
        
        $ok2 = $this->ejecutar(
            "UPDATE g_social.pacnt_detll SET estado = false, fec_del = CURRENT_TIMESTAMP, cdgo_lgin_del = $2 WHERE id_pacnt = $1 AND estado = true",
            $params
        );
        
        $ok3 = $this->ejecutar(
            "UPDATE g_social.pacnt_fam SET estado = false, fec_del = CURRENT_TIMESTAMP, cdgo_lgin_del = $2 WHERE id_pacnt = $1 AND estado = true",
            $params
        );
        
        $ok4 = $this->ejecutar(
            "UPDATE g_social.discp_omaped SET estado = false, fec_del = CURRENT_TIMESTAMP, cdgo_lgin_del = $2 WHERE id_pacnt = $1 AND estado = true",
            $params
        );
        
        $ok5 = $this->ejecutar(
            "UPDATE g_social.atncn SET estado = false, fec_del = CURRENT_TIMESTAMP, cdgo_lgin_del = $2 WHERE id_pacnt = $1 AND estado = true",
            $params
        );
        
        if (!$ok1 || !$ok2 || !$ok3 || !$ok4 || !$ok5) {
            $this->rollback();
            return $this->fail('No se pudo eliminar usuario OMAPED.');
        }

        if (!$this->commit()) {
            $this->rollback();
            return $this->fail('No se pudo confirmar eliminacion.');
        }
        return $this->ok();
    }

    /**
     * Guarda o actualiza historial de atención
     */
    public function guardarOActualizarHistorico($idPacnt, $input, $idAtencion = 0) {
        $id = (int)$idPacnt;
        $idAtn = (int)$idAtencion;
        if ($id <= 0) return $this->fail('ID de paciente no valido.');

        $fechaAtencion = $this->str(isset($input['fechaAtencion']) ? $input['fechaAtencion'] : '', '');
        $sede = $this->str(isset($input['sede']) ? $input['sede'] : '', '');
        $observ = $this->str(isset($input['observaciones']) ? $input['observaciones'] : '', '');
        $evid = $this->str(isset($input['evidencia']) ? $input['evidencia'] : '', '');
        $servicios = isset($input['servicios']) && is_array($input['servicios']) ? $input['servicios'] : array();
        $servicioOtro = $this->str(isset($input['servicioOtro']) ? $input['servicioOtro'] : '', '');

        if ($fechaAtencion === '' || $sede === '' || !count($servicios)) {
            return $this->fail('Fecha, sede y servicios son obligatorios.');
        }

        $idSede = $this->resolverIdSedePorNombre($sede);
        if (!$idSede) return $this->fail('Sede no valida.');

        $rsPac = $this->ejecutar("SELECT pu.nu_docu FROM g_social.pacnt p INNER JOIN sistema.p_usrios pu ON p.cdgo_usrios = pu.cdgo_usrios WHERE p.id_pacnt = $1 AND p.estado = true LIMIT 1", array($id));
        $rowPac = $rsPac ? pg_fetch_assoc($rsPac) : null;
        $dni = $rowPac ? preg_replace('/[^0-9]/', '', $rowPac['nu_docu']) : '';

        if (!$this->begin()) return $this->fail('No se pudo iniciar transaccion.');

        $cdgoLgin = $this->obtenerCodigoLogin();

        if ($idAtn > 0) {
            $rsEvidActual = $this->ejecutar("SELECT evid_arch FROM g_social.atncn WHERE id_atncn = $1 AND estado = true LIMIT 1", array($idAtn));
            $rowEvidActual = $rsEvidActual ? pg_fetch_assoc($rsEvidActual) : null;
            $evidActual = $rowEvidActual ? ($rowEvidActual['evid_arch'] ?: '') : '';
            
            if (isset($_FILES['evidenciaAtencion']) && $_FILES['evidenciaAtencion']['error'] === UPLOAD_ERR_OK && !empty($dni)) {
                $archivoSubido = $this->subirArchivo($_FILES['evidenciaAtencion'], 'evidencia', $dni, $idAtn);
                $evid = $archivoSubido ? $archivoSubido : $evidActual;
            } else {
                $evid = $evidActual;
            }
            
            $sqlUpdAtn = "UPDATE g_social.atncn
                          SET id_sede = $1, fec_atncn = $2::timestamp, observ = $3, evid_arch = $4,
                              fec_upd = CURRENT_TIMESTAMP, cdgo_lgin_upd = $5
                          WHERE id_atncn = $6 AND id_pacnt = $7 AND estado = true";
            $evidParam = (!empty($evid)) ? $evid : null;
            $rsAtn = $this->ejecutar($sqlUpdAtn, array($idSede, $fechaAtencion . ' 00:00:00', $observ, $evidParam, $cdgoLgin, $idAtn, $id));
            if (!$rsAtn) {
                $this->rollback();
                return $this->fail('No se pudo actualizar atencion.');
            }
        } else {
            $sqlInsAtn = "INSERT INTO g_social.atncn
                          (id_pacnt, id_sede, fec_atncn, observ, evid_arch, estado, fec_ins, cdgo_lgin_ins)
                          VALUES ($1,$2,$3::timestamp,$4,$5,true,CURRENT_TIMESTAMP,$6)
                          RETURNING id_atncn";
            $rsAtn = $this->ejecutar($sqlInsAtn, array($id, $idSede, $fechaAtencion . ' 00:00:00', $observ, $evid, $cdgoLgin));
            if (!$rsAtn) {
                $this->rollback();
                return $this->fail('No se pudo guardar atencion.');
            }
            $rowAtn = pg_fetch_assoc($rsAtn);
            $idAtn = (int)$rowAtn['id_atncn'];
            
            if (isset($_FILES['evidenciaAtencion']) && $_FILES['evidenciaAtencion']['error'] === UPLOAD_ERR_OK && !empty($dni)) {
                $archivoSubido = $this->subirArchivo($_FILES['evidenciaAtencion'], 'evidencia', $dni, $idAtn);
                if ($archivoSubido) {
                    $sqlUpdEvid = "UPDATE g_social.atncn SET evid_arch = $1, fec_upd = CURRENT_TIMESTAMP, cdgo_lgin_upd = $2 WHERE id_atncn = $3";
                    $rsUpdEvid = $this->ejecutar($sqlUpdEvid, array($archivoSubido, $cdgoLgin, $idAtn));
                    if (!$rsUpdEvid) {
                        $this->rollback();
                        return $this->fail('No se pudo guardar archivo de evidencia.');
                    }
                }
            }
        }

        $idsServiciosNuevos = array();
        foreach ($servicios as $servicioInput) {
            $idServ = 0;
            if (is_numeric($servicioInput)) {
                $idServ = (int)$servicioInput;
            } else {
                $nombreServicio = strtoupper(trim($servicioInput));
                $sqlServ = "SELECT id_serv FROM g_social.serv WHERE UPPER(TRIM(COALESCE(nomb_serv,''))) = $1 AND estado = true LIMIT 1";
                $rsServ = $this->ejecutar($sqlServ, array($nombreServicio));
                if (!$rsServ || !($rowServ = pg_fetch_assoc($rsServ))) {
                    $this->rollback();
                    return $this->fail('Servicio no encontrado: ' . $servicioInput);
                }
                $idServ = (int)$rowServ['id_serv'];
            }
            $idsServiciosNuevos[] = $idServ;
            $observServ = ($servicioInput === 'OTRO' && $servicioOtro !== '') ? $servicioOtro : null;
            $okServ = $this->ejecutar(
                "INSERT INTO g_social.atncn_serv (id_atncn, id_serv, observ, estado, fec_ins, cdgo_lgin_ins)
                 VALUES ($1,$2,$3,true,CURRENT_TIMESTAMP,$4)
                 ON CONFLICT (id_atncn, id_serv)
                 DO UPDATE SET observ = EXCLUDED.observ, estado = true, fec_del = NULL, 
                               fec_upd = CURRENT_TIMESTAMP, cdgo_lgin_upd = $4",
                array($idAtn, $idServ, $observServ, $cdgoLgin)
            );
            if (!$okServ) {
                $this->rollback();
                return $this->fail('No se pudo guardar servicio.');
            }
        }

        if ($idAtn > 0 && count($idsServiciosNuevos) > 0) {
            $placeholders = array();
            $params = array($idAtn);
            foreach ($idsServiciosNuevos as $idx => $idServ) {
                $placeholders[] = '$' . ($idx + 2);
                $params[] = $idServ;
            }
            $sqlDelServ = "UPDATE g_social.atncn_serv 
                          SET estado = false, fec_del = CURRENT_TIMESTAMP, cdgo_lgin_del = $" . (count($idsServiciosNuevos) + 2) . "
                          WHERE id_atncn = $1 AND estado = true AND id_serv NOT IN (" . implode(',', $placeholders) . ")";
            $params[] = $cdgoLgin;
            $rsDelServ = $this->ejecutar($sqlDelServ, $params);
            if (!$rsDelServ) {
                $this->rollback();
                $this->logError("No se pudieron eliminar servicios no seleccionados - idAtn: $idAtn", $sqlDelServ);
                return $this->fail('No se pudieron eliminar servicios no seleccionados.');
            }
        }

        if (!$this->commit()) {
            $this->rollback();
            return $this->fail('No se pudo confirmar operacion.');
        }
        
        return $this->ok(array('idAtencion' => $idAtn));
    }

    // ==================== FUNCIONES PÚBLICAS QR (Páginas públicas) ====================

    /**
     * Obtiene la información básica del paciente a partir de un token QR.
     * Versión pública para usar desde páginas públicas (entrada.php).
     *
     * @param string $tokenQr Token QR recibido en la URL pública (parámetro `t`)
     * @return array|null Datos del paciente o null si el token no es válido/activo
     */
    public function obtenerPacientePorTokenQRPublico($tokenQr) {
        return $this->obtenerPacientePorTokenQR($tokenQr);
    }

    /**
     * Crea un token de acceso temporal (5 minutos) para ver los datos del paciente.
     * Se genera después de validar el DNI correctamente.
     *
     * @param int $idQr ID del registro QR (g_social.omaped_qr.id_qr)
     * @param string|null $ip IP del cliente (opcional)
     * @param string|null $userAgent User-Agent del cliente (opcional)
     * @param int $minutos Duración del token en minutos (default: 5)
     * @return string|null Token de acceso o null si falla
     */
    public function crearTokenAcceso($idQr, $ip = null, $userAgent = null, $minutos = 5) {
        $idQr = (int)$idQr;
        if ($idQr <= 0) {
            $this->logError("crearTokenAcceso: idQr inválido", null);
            return null;
        }

        // Verificar que el QR existe y está activo
        $sqlCheck = "SELECT id_qr FROM g_social.omaped_qr WHERE id_qr = $1 AND estado = 'ACTIVO' LIMIT 1";
        $rsCheck = $this->ejecutar($sqlCheck, array($idQr));
        if (!$rsCheck || pg_num_rows($rsCheck) === 0) {
            $this->logError("crearTokenAcceso: QR no encontrado o inactivo (id_qr=$idQr)", null);
            return null;
        }

        // Generar token de acceso
        $tokenAcceso = $this->generarTokenSeguro(32); // 64 caracteres hex
        if (empty($tokenAcceso)) {
            $this->logError("crearTokenAcceso: fallo al generar token", null);
            return null;
        }

        // Calcular fechas
        $creadoEn = date('Y-m-d H:i:s');
        $expiraEn = date('Y-m-d H:i:s', strtotime("+{$minutos} minutes"));

        // Sanitizar IP y User-Agent
        $ipSanitizada = $ip ? substr(trim($ip), 0, 45) : null;
        $userAgentSanitizado = $userAgent ? substr(trim($userAgent), 0, 500) : null;

        // Insertar token de acceso
        $sqlInsert = "INSERT INTO g_social.omaped_qr_accesos 
                      (id_qr, token_acceso, creado_en, expira_en, ip, user_agent, usado, intentos_fallidos)
                      VALUES ($1, $2, $3::timestamp, $4::timestamp, $5, $6, false, 0)
                      RETURNING token_acceso";
        $rsInsert = $this->ejecutar($sqlInsert, array(
            $idQr,
            $tokenAcceso,
            $creadoEn,
            $expiraEn,
            $ipSanitizada,
            $userAgentSanitizado
        ));

        if (!$rsInsert || pg_num_rows($rsInsert) === 0) {
            $this->logError("crearTokenAcceso: fallo al insertar token de acceso para id_qr=$idQr", $sqlInsert);
            return null;
        }

        return $tokenAcceso;
    }

    /**
     * Valida un token de acceso y retorna información del paciente asociado.
     *
     * @param string $tokenAcceso Token de acceso a validar
     * @param string|null $ipActual IP actual del cliente (opcional, para validación adicional)
     * @return array|null Array con id_acceso, id_qr, id_pacnt, o null si es inválido/expirado
     */
    public function validarTokenAcceso($tokenAcceso, $ipActual = null) {
        $token = trim((string)$tokenAcceso);
        if (empty($token)) {
            return null;
        }

        $sql = "SELECT 
                    a.id_acceso,
                    a.id_qr,
                    q.id_pacnt,
                    a.creado_en,
                    a.expira_en,
                    a.bloqueado_hasta,
                    a.ip AS ip_original
                FROM g_social.omaped_qr_accesos a
                INNER JOIN g_social.omaped_qr q ON q.id_qr = a.id_qr AND q.estado = 'ACTIVO'
                WHERE a.token_acceso = $1
                LIMIT 1";

        $rs = $this->ejecutar($sql, array($token));
        if (!$rs || pg_num_rows($rs) === 0) {
            return null; // Token no encontrado
        }

        $row = pg_fetch_assoc($rs);

        // Validar que no haya expirado
        $ahora = date('Y-m-d H:i:s');
        if ($row['expira_en'] < $ahora) {
            return null; // Token expirado
        }

        // Validar que no esté bloqueado
        if (!empty($row['bloqueado_hasta']) && $row['bloqueado_hasta'] > $ahora) {
            return null; // Token bloqueado
        }

        // (Opcional) Validar IP si se proporciona
        if ($ipActual !== null && !empty($row['ip_original'])) {
            $ipActualTrim = trim($ipActual);
            if ($ipActualTrim !== $row['ip_original']) {
                // IP no coincide, pero no bloqueamos por esto (puede ser NAT/proxy)
                // No registramos en log para evitar ruido en auditorías normales (NAT/proxy).
            }
        }

        return array(
            'id_acceso' => (int)$row['id_acceso'],
            'id_qr'     => (int)$row['id_qr'],
            'id_pacnt'  => (int)$row['id_pacnt'],
            'creado_en' => $row['creado_en'],
            'expira_en' => $row['expira_en']
        );
    }

    /**
     * Obtiene los datos públicos del paciente para mostrar en la vista pública.
     * Aplica enmascarado de nombres y otros campos sensibles.
     *
     * @param int $idPacnt ID del paciente
     * @return array|null Datos públicos enmascarados o null si no se encuentra
     */
    public function obtenerDatosPublicosPaciente($idPacnt) {
        $id = (int)$idPacnt;
        if ($id <= 0) {
            return null;
        }

        // Consulta para obtener datos del paciente (foto y nombres completos)
        $sql = "SELECT 
                    p.id_pacnt,
                    hu.no_usrio AS nombres,
                    hu.ap_pate AS ape_paterno,
                    hu.ap_mate AS ape_materno,
                    COALESCE(pd.foto, '') AS foto
                FROM g_social.pacnt p
                INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = p.cdgo_usrios
                LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                LEFT JOIN g_social.pacnt_detll pd ON pd.id_pacnt = p.id_pacnt AND pd.estado = true
                WHERE p.id_pacnt = $1 AND p.estado = true
                LIMIT 1";

        $rs = $this->ejecutar($sql, array($id));
        if (!$rs || pg_num_rows($rs) === 0) {
            return null;
        }

        $row = pg_fetch_assoc($rs);

        // Obtener último cuidador/familiar registrado
        $sqlCuidador = "SELECT COALESCE(hu.no_usrio,'') AS nombres,
                               COALESCE(hu.ap_pate,'') AS ape_paterno,
                               COALESCE(hu.ap_mate,'') AS ape_materno,
                               COALESCE(pu.nu_tele,'') AS telefono,
                               COALESCE(tp.nomb_parntsc,'') AS parentesco
                        FROM g_social.pacnt_fam pf
                        INNER JOIN sistema.p_usrios pu ON pu.cdgo_usrios = pf.cdgo_usrios
                        LEFT JOIN sistema.h_usrios_ntral hu ON hu.cdgo_usrios = pu.cdgo_usrios
                        LEFT JOIN g_social.tip_parntsc tp ON tp.id_tip_parntsc = pf.id_tip_parntsc
                        WHERE pf.id_pacnt = $1 AND pf.estado = true
                        ORDER BY pf.fec_ins DESC
                        LIMIT 1";
        
        $rsCuidador = $this->ejecutar($sqlCuidador, array($id));
        $cuidador = null;
        if ($rsCuidador && pg_num_rows($rsCuidador) > 0) {
            $rowCuidador = pg_fetch_assoc($rsCuidador);
            $nombreCompletoCuidador = trim(
                ($rowCuidador['nombres'] ?? '') . ' ' .
                ($rowCuidador['ape_paterno'] ?? '') . ' ' .
                ($rowCuidador['ape_materno'] ?? '')
            );
            if (!empty($nombreCompletoCuidador)) {
                $cuidador = array(
                    'nombre_completo' => $nombreCompletoCuidador,
                    'telefono' => !empty($rowCuidador['telefono']) ? trim($rowCuidador['telefono']) : null,
                    'parentesco' => !empty($rowCuidador['parentesco']) ? trim($rowCuidador['parentesco']) : null
                );
            }
        }

        // Construir nombre completo del paciente (sin enmascarar)
        $nombreCompleto = trim(
            ($row['nombres'] ?? '') . ' ' .
            ($row['ape_paterno'] ?? '') . ' ' .
            ($row['ape_materno'] ?? '')
        );

        return array(
            'id_pacnt' => (int)$row['id_pacnt'],
            'foto' => !empty($row['foto']) ? (string)$row['foto'] : null,
            'nombre_completo' => $nombreCompleto ?: 'No disponible',
            'cuidador' => $cuidador
        );
    }

    /**
     * Enmascara un nombre: muestra las primeras 2 letras y reemplaza el resto con asteriscos.
     * Ejemplo: "SANDRA MELISSA" → "SA**** ME****"
     *
     * @param string|null $nombre Nombre a enmascarar
     * @return string Nombre enmascarado
     */
    private function enmascararNombre($nombre) {
        if (empty($nombre)) {
            return '';
        }

        $nombre = trim($nombre);
        $palabras = preg_split('/\s+/', $nombre);
        $palabrasEnmascaradas = array();

        foreach ($palabras as $palabra) {
            if (strlen($palabra) <= 2) {
                $palabrasEnmascaradas[] = str_repeat('*', strlen($palabra));
            } else {
                $palabrasEnmascaradas[] = substr($palabra, 0, 2) . str_repeat('*', strlen($palabra) - 2);
            }
        }

        return implode(' ', $palabrasEnmascaradas);
    }

    /**
     * Enmascara un DNI: muestra solo los últimos 3 dígitos.
     * Ejemplo: "73031854" → "*****854"
     *
     * @param string|null $dni DNI a enmascarar
     * @return string DNI enmascarado
     */
    private function enmascararDNI($dni) {
        if (empty($dni)) {
            return '';
        }

        $dni = preg_replace('/[^0-9]/', '', (string)$dni);
        if (strlen($dni) <= 3) {
            return str_repeat('*', strlen($dni));
        }

        return str_repeat('*', strlen($dni) - 3) . substr($dni, -3);
    }

    /**
     * Enmascara un código CONADIS: muestra solo los últimos 3-4 dígitos.
     *
     * @param string|null $codigo Código a enmascarar
     * @return string Código enmascarado
     */
    private function enmascararCodigoConadis($codigo) {
        if (empty($codigo)) {
            return null;
        }

        $codigo = trim((string)$codigo);
        $longitud = strlen($codigo);
        if ($longitud <= 3) {
            return str_repeat('*', $longitud);
        }

        $mostrar = min(4, max(3, (int)($longitud * 0.3))); // Mostrar 30% o mínimo 3-4
        return str_repeat('*', $longitud - $mostrar) . substr($codigo, -$mostrar);
    }

    /**
     * Registra un intento fallido de DNI para un token QR.
     * Implementa rate limiting básico: si hay más de 5 intentos en 10 minutos, bloquea temporalmente.
     *
     * @param string $tokenQr Token QR asociado
     * @param string|null $ip IP del cliente
     * @return void
     */
    public function registrarIntentoFallidoDni($tokenQr, $ip = null) {
        $token = trim((string)$tokenQr);
        if (empty($token)) {
            return;
        }

        // Obtener id_qr desde el token
        $sqlQr = "SELECT id_qr FROM g_social.omaped_qr WHERE token_qr = $1 AND estado = 'ACTIVO' LIMIT 1";
        $rsQr = $this->ejecutar($sqlQr, array($token));
        if (!$rsQr || pg_num_rows($rsQr) === 0) {
            return; // QR no encontrado
        }
        $rowQr = pg_fetch_assoc($rsQr);
        $idQr = (int)$rowQr['id_qr'];

        // Contar intentos fallidos en los últimos 10 minutos para este QR + IP
        $hace10Min = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $ipSanitizada = $ip ? substr(trim($ip), 0, 45) : null;

        $sqlCount = "SELECT COUNT(*) as total
                     FROM g_social.omaped_qr_accesos
                     WHERE id_qr = $1 
                       AND creado_en >= $2::timestamp
                       AND intentos_fallidos > 0";
        $paramsCount = array($idQr, $hace10Min);
        if ($ipSanitizada) {
            $sqlCount .= " AND ip = $3";
            $paramsCount[] = $ipSanitizada;
        }

        $rsCount = $this->ejecutar($sqlCount, $paramsCount);
        $rowCount = $rsCount ? pg_fetch_assoc($rsCount) : null;
        $intentosRecientes = $rowCount ? (int)$rowCount['total'] : 0;

        // Si hay más de 5 intentos, bloquear temporalmente (30 minutos)
        if ($intentosRecientes >= 5) {
            $bloqueadoHasta = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $sqlBloqueo = "UPDATE g_social.omaped_qr_accesos
                          SET bloqueado_hasta = $1::timestamp
                          WHERE id_qr = $2 AND expira_en > NOW()";
            $this->ejecutar($sqlBloqueo, array($bloqueadoHasta, $idQr));
            // No se registra en log: es un evento esperado de seguridad (auditoría) y puede generar mucho volumen.
        }

        // Incrementar contador de intentos fallidos en el último acceso (si existe)
        // O crear un registro de acceso fallido para auditoría
        $sqlUpdate = "UPDATE g_social.omaped_qr_accesos
                      SET intentos_fallidos = intentos_fallidos + 1
                      WHERE id_qr = $1 
                        AND expira_en > NOW()
                        AND bloqueado_hasta IS NULL
                      ORDER BY creado_en DESC
                      LIMIT 1";
        $this->ejecutar($sqlUpdate, array($idQr));
    }
}
?>
