<?php

namespace App\Services\mod_gp\omaped;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bridge: prepara sesión/paths/pg y carga omaped_class legacy.
 */
class OmapedBridge
{
    /** @var \PgSql\Connection|null */
    private static $cn = null;

    public static function boot(): void
    {
        $base = storage_path('app/omaped');
        if (!defined('__DOCROOT__')) {
            define('__DOCROOT__', str_replace('\\', '/', $base));
        }
        if (!defined('__SUBDIRECTORY__')) {
            define('__SUBDIRECTORY__', '');
        }
        if (!defined('__ESQUEMA__')) {
            define('__ESQUEMA__', 'sistema.');
        }
        if (!defined('__WEB_ROOT__')) {
            define('__WEB_ROOT__', '');
        }

        // Paths esperados por Generar_QR_OMAPED / omaped_class
        // __DOCROOT__/Negocio/qr_img/... y __DOCROOT__/Presentacion/...
        $qrImg = $base . '/qr_img';
        $negoQr = $base . '/Negocio/qr_img';
        if (!is_dir($negoQr) && is_dir($qrImg)) {
            @mkdir(dirname($negoQr), 0775, true);
            // symlink o junction no siempre disponible en Windows; copiar referencia vía define override en Generar_QR
        }

        self::syncSessionFromAuth();
    }

    public static function syncSessionFromAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $user = Auth::user();
        if ($user) {
            $_SESSION['usIds'] = (int) $user->cdgo_lgin;
            $_SESSION['id'] = (int) $user->cdgo_lgin;
            $_SESSION['usIdPrsna'] = (int) ($user->cdgo_usrios ?? 0);
            $_SESSION['usNombre'] = (string) $user->nombreMostrar();
        }
        $_SESSION['usNomArea'] = (string) session('sigtram_area_nombre', '');
        $_SESSION['usNomCargo'] = (string) session('sigtram_cargo_nombre', '');
    }

    public static function connection()
    {
        if (self::$cn) {
            return self::$cn;
        }

        $cfg = config('database.connections.pgsql');
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = $cfg['port'] ?? '5432';
        $db = $cfg['database'] ?? '';
        $user = $cfg['username'] ?? '';
        $pass = $cfg['password'] ?? '';
        $connStr = "host={$host} port={$port} dbname={$db} user={$user} password={$pass}";
        $cn = @pg_connect($connStr);
        if (!$cn) {
            Log::error('[omaped] No se pudo conectar a PostgreSQL');
            return false;
        }
        self::$cn = $cn;
        $GLOBALS['OMAPED_PG'] = $cn;

        return $cn;
    }

    public static function make()
    {
        self::boot();
        require_once app_path('Services/mod_gp/omaped/clase_personas_stub.php');
        require_once app_path('Services/mod_gp/omaped/omaped.class.php');
        require_once app_path('Services/mod_gp/omaped/Generar_QR_OMAPED.php');

        $cn = self::connection();
        if (!$cn) {
            return null;
        }

        return new \omaped_class($cn);
    }

    public static function tempQrBase(): string
    {
        $dir = storage_path('app/omaped/Presentacion/genera_dcmnto/tempQR');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function adjuntoBase(): string
    {
        // Mantener estructura Presentacion/.../adjunto para compatibilidad con rutas legacy en BD
        $dir = storage_path('app/omaped/adjunto/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function qrLibDataPath(): string
    {
        return storage_path('app/omaped/qr_img/data');
    }

    public static function qrLibImagePath(): string
    {
        return storage_path('app/omaped/qr_img/image');
    }

    public static function entradaUrl(string $token): string
    {
        return url('/mod_gp/omaped/entrada?t=' . urlencode($token));
    }
}
