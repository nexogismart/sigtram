<?php

namespace App\Http\Controllers\mod_ti\dispositivo_seg;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_ti\DispositivoSegService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Proxy de video (MJPEG / HLS / RTSP) adaptado del módulo antiguo.
 */
class DispositivoSegStreamController extends Controller
{
    public function __construct(
        private readonly DispositivoSegService $service,
        private readonly SecurityCoreInterface $securityCore
    ) {}

    public function stream(Request $request): void
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(401, 'No autenticado.');
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            abort(403, 'Seleccione un cargo.');
        }
        if (!$this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/dispositivo_seg', $cargoId)
            && !$this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/dispositivo_seg/index', $cargoId)) {
            abort(403, 'Sin permiso.');
        }

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            abort(400, 'ID no válido.');
        }

        $obtener = $this->service->obtener($id);
        if (!$obtener['success'] || empty($obtener['data'])) {
            abort(404, 'Cámara no encontrada.');
        }

        $this->definirConstantesVisor();
        require_once app_path('Services/mod_ti/dispositivo_seg_credenciales_helper.php');

        $item = $obtener['data'];
        $logFile = storage_path('logs/dispositivo_seg_proxy_stream.log');
        $GLOBALS['DS_PROXY_CACHE_BASE'] = storage_path('app/dispositivo_seg');
        $GLOBALS['DS_PROXY_STREAM_BASE'] = url('/mod_ti/dispositivo_seg/stream');
        if (!is_dir($GLOBALS['DS_PROXY_CACHE_BASE'])) {
            @mkdir($GLOBALS['DS_PROXY_CACHE_BASE'], 0775, true);
        }

        require app_path('Services/mod_ti/dispositivo_seg_proxy_stream.inc.php');
    }

    public function tailLog(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(401);
        }

        $id = (int) $request->query('id', 0);
        $logFile = storage_path('logs/dispositivo_seg_proxy_stream.log');
        $cacheBase = storage_path('app/dispositivo_seg');
        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Logs cámara ' . $id . '</title>'
            . '<style>body{font-family:monospace;background:#111;color:#ddd;padding:16px} pre{white-space:pre-wrap;word-break:break-all}</style></head><body>';
        $html .= '<h2>proxy_stream.log</h2><pre>' . e(@file_exists($logFile) ? (string) @file_get_contents($logFile) : '(vacío)') . '</pre>';

        if ($id > 0) {
            foreach (glob($cacheBase . '/hls_cache*/cam_' . $id . '/ffmpeg_hls.log') ?: [] as $f) {
                $html .= '<h3>' . e(basename(dirname($f)) . '/' . basename($f)) . '</h3>';
                $html .= '<pre>' . e((string) @file_get_contents($f)) . '</pre>';
            }
        }
        $html .= '</body></html>';

        return response($html);
    }

    private function definirConstantesVisor(): void
    {
        $map = [
            'CAMARA_VISOR_USUARIO' => (string) config('camara_visor.usuario', 'admin'),
            'CAMARA_VISOR_PASSWORD' => (string) config('camara_visor.password', 'admin'),
            'CAMARA_VISOR_PATH' => (string) config('camara_visor.path'),
            'CAMARA_VISOR_USE_RTSP' => (bool) config('camara_visor.use_rtsp', false),
            'CAMARA_VISOR_RTSP_PORT' => (int) config('camara_visor.rtsp_port', 554),
            'CAMARA_VISOR_RTSP_PATH' => (string) config('camara_visor.rtsp_path'),
            'CAMARA_VISOR_FFMPEG_FPS' => (int) config('camara_visor.ffmpeg_fps', 5),
            'CAMARA_VISOR_FFMPEG_QUALITY' => (int) config('camara_visor.ffmpeg_quality', 8),
            'CAMARA_VISOR_RTSP_START_WAIT_SECONDS' => (int) config('camara_visor.rtsp_start_wait_seconds', 8),
            'CAMARA_VISOR_FFMPEG_BIN' => (string) config('camara_visor.ffmpeg_bin', '/usr/bin/ffmpeg'),
            'CAMARA_VISOR_ENABLE_LOG' => (bool) config('camara_visor.enable_log', false),
        ];
        foreach ($map as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
