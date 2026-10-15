<?php
/** Adapted proxy_stream for Laravel. Requires $id, $item, $logFile already set; CAMARA_VISOR_* defined; ds_credenciales_camara_por_ip() defined. */


// logFile set by controller
function ds_proxy_log($logFile, $msg) {
    $enableLog = defined('CAMARA_VISOR_ENABLE_LOG') ? (bool)CAMARA_VISOR_ENABLE_LOG : false;
    if (!$enableLog) return;
    $line = "[" . date("Y-m-d H:i:s") . "] " . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function ds_mask_pass($pass) {
    // No logueamos contraseñas en claro; se mantiene solo un marcador.
    if ($pass === null) return '';
    $s = (string)$pass;
    return $s === '' ? '' : '****';
}

function ds_safe_rtsp_credential($str) {
    $encoded = rawurlencode($str);
    $safeChars = array('%21'=>'!', '%24'=>'$', '%26'=>'&', '%27'=>"'", '%28'=>'(', '%29'=>')', '%2A'=>'*', '%2B'=>'+', '%2C'=>',', '%3D'=>'=');
    return str_replace(array_keys($safeChars), array_values($safeChars), $encoded);
}

function ds_mask_rtsp_url($rtspUrl) {
    // rtsp://usuario:password@ip:port/...
    $u = (string)$rtspUrl;
    return preg_replace('/^(rtsp:\/\/[^:]+:)[^@]+@/i', '$1****@', $u);
}

function ds_is_hikvision_camera($item) {
    $marca = strtoupper(trim((string)($item['marca'] ?? '')));
    $modelo = strtoupper(trim((string)($item['modelo'] ?? '')));
    // Hikvision suele venir como HIKVISION, y a veces también aparece en el modelo.
    return (strpos($marca, 'HIK') !== false) || (strpos($modelo, 'HIK') !== false);
}

function ds_is_hikvision_fixed_camera($item) {
    $marca = strtoupper(trim((string)($item['marca'] ?? '')));
    $modelo = strtoupper(trim((string)($item['modelo'] ?? '')));
    $tipo = strtoupper(trim((string)($item['tipo'] ?? '')));
    $isHik = (strpos($marca, 'HIK') !== false) || (strpos($modelo, 'HIK') !== false);
    return $isHik && (strpos($tipo, 'FIJA') !== false);
}

function ds_is_dahua_fixed_camera($item) {
    $marca = strtoupper(trim((string)($item['marca'] ?? '')));
    $tipo = strtoupper(trim((string)($item['tipo'] ?? '')));
    // Aplicar solo a DAHUA FIJA (no PTZ).
    return (strpos($marca, 'DAHUA') !== false) && (strpos($tipo, 'FIJA') !== false);
}

function ds_is_dahua_ptz_camera($item) {
    $marca = strtoupper(trim((string)($item['marca'] ?? '')));
    $tipo = strtoupper(trim((string)($item['tipo'] ?? '')));
    return (strpos($marca, 'DAHUA') !== false) && (strpos($tipo, 'PTZ') !== false);
}

// --- Laravel bootstrap inject ---
if (!isset($id, $logFile) || !is_int($id) || !is_string($logFile)) {
    throw new \LogicException('El proxy requiere un ID y una ruta de log válidos.');
}
if (!isset($item) || !is_array($item)) { http_response_code(500); header("Content-Type: text/plain; charset=UTF-8"); echo "Proxy mal configurado."; exit; }
$tipo = isset($item["tipo_dispositivo"]) ? strtoupper(trim((string)$item["tipo_dispositivo"])) : "";
if ($tipo !== "CAMARA") { http_response_code(400); header("Content-Type: text/plain; charset=UTF-8"); echo "El dispositivo no es una cámara."; exit; }
$cnCamara = null;

$ip = isset($item["ip"]) ? trim((string)$item["ip"]) : "";
if ($ip === "") {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "La cámara no tiene IP configurada.";
    ds_proxy_log($logFile, "id={$id} sin_ip");
    exit;
}
$ipSinMascara = trim(explode("/", $ip)[0]);
if ($ipSinMascara === "") {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "IP inválida.";
    ds_proxy_log($logFile, "id={$id} ip_invalida raw=" . str_replace(array("\r", "\n"), "", $ip));
    exit;
}

$format = isset($_GET["format"]) ? strtolower(trim((string)$_GET["format"])) : '';

$cred = ds_credenciales_camara_por_ip($ipSinMascara, $cnCamara);
$defUsuario = defined('CAMARA_VISOR_USUARIO') ? CAMARA_VISOR_USUARIO : 'admin';
$defPassword = defined('CAMARA_VISOR_PASSWORD') ? CAMARA_VISOR_PASSWORD : 'admin';
$usuarioDb = trim($cred["usuario"]);
$passwordDb = trim($cred["password"]);
// Evitar user/pass incompletos: si falta alguno, usar defaults
$usuario = ($usuarioDb !== '' && $passwordDb !== '') ? $usuarioDb : $defUsuario;
$password = ($usuarioDb !== '' && $passwordDb !== '') ? $passwordDb : $defPassword;

$path = defined('CAMARA_VISOR_PATH') ? CAMARA_VISOR_PATH : '/cgi-bin/mjpg/video.cgi?channel=0&subtype=0';
$url = "http://" . $ipSinMascara . $path;

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(true);
@set_time_limit(0);
// Importante: si el usuario cierra el modal/cancela la carga del stream,
// debemos permitir que PHP detecte el aborto para cortar el curl y no "buguear" el resto.
@ignore_user_abort(false);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$useRtsp = defined('CAMARA_VISOR_USE_RTSP') && CAMARA_VISOR_USE_RTSP;

// Hikvision no soporta el mismo CGI mjpg que Dahua; se maneja con RTSP/H.264 -> MJPEG vía FFmpeg.
$isHikvision = ds_is_hikvision_camera($item);
$isHikvisionFixed = ds_is_hikvision_fixed_camera($item);
$isDahuaFixed = ds_is_dahua_fixed_camera($item);
$isDahuaPtz = ds_is_dahua_ptz_camera($item);
if ($isHikvisionFixed) {
    if ($format === 'hls_m3u8') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_fija_mode on (hls->m3u8)");
        $ok = @ds_proxy_stream_hikvision_hls_m3u8($ipSinMascara, $usuario, $password, $logFile, $id, 'fija');
        if ($ok === false && !connection_aborted()) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Hikvision fija HLS: playlist no disponible aún.";
        }
        exit;
    }

    if ($format === 'hls_seg') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_fija_mode on (hls->seg)");
        ds_proxy_stream_hikvision_hls_seg($ipSinMascara, $usuario, $password, $logFile, $id, 'fija');
        exit;
    }
}
if ($isHikvision) {
    if ($format === 'hls_m3u8') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_mode on (hls->m3u8)");
        $ok = @ds_proxy_stream_hikvision_hls_m3u8($ipSinMascara, $usuario, $password, $logFile, $id);
        if ($ok === false && !connection_aborted()) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Hikvision HLS: playlist no disponible aún.";
        }
        exit;
    }

    if ($format === 'hls_seg') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_mode on (hls->seg)");
        ds_proxy_stream_hikvision_hls_seg($ipSinMascara, $usuario, $password, $logFile, $id);
        exit;
    }

    if ($format === 'ts') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_mode on (rtsp->mpegts)");
        $startedTs = @ds_proxy_stream_hikvision_ts($ipSinMascara, $usuario, $password, $logFile, $id);
        if ($startedTs === false && !connection_aborted()) {
            http_response_code(502);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Hikvision: no se pudo obtener el stream RTSP para MPEG-TS (canales 101/102).";
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_ts_fail");
        }
        exit;
    }

    if ($format === 'fmp4') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_mode on (rtsp->fmp4)");
        $startedFmp4 = @ds_proxy_stream_hikvision_fmp4($ipSinMascara, $usuario, $password, $logFile, $id);
        if ($startedFmp4 === false && !connection_aborted()) {
            http_response_code(502);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Hikvision: no se pudo obtener el stream RTSP para fMP4 (canales 101/102).";
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_fmp4_fail");
        }
        exit;
    }

    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_mode on (rtsp->mjpeg)");
    $startedHik = @ds_proxy_stream_rtsp_hikvision($ipSinMascara, $usuario, $password, $logFile, $id);
    if ($startedHik === false) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Hikvision: no se pudo obtener el stream RTSP (canales 101/102). Revise credenciales/RTSP en la cámara.";
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hikvision_rtsp_fail");
    }
    exit;
}

// DAHUA FIJA: usar el mismo player real HLS (RTSP->HLS) aplicado en Hikvision.
if ($isDahuaFixed) {
    if ($format === 'hls_m3u8') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_fija_mode on (hls->m3u8)");
        $ok = @ds_proxy_stream_dahua_hls_m3u8($ipSinMascara, $usuario, $password, $logFile, $id, 'fija');
        if ($ok === false && !connection_aborted()) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Dahua fija HLS: playlist no disponible aún.";
        }
        exit;
    }

    if ($format === 'hls_seg') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_fija_mode on (hls->seg)");
        ds_proxy_stream_dahua_hls_seg($ipSinMascara, $usuario, $password, $logFile, $id, 'fija');
        exit;
    }
}

// DAHUA PTZ: usar el mismo player real HLS (RTSP->HLS).
if ($isDahuaPtz) {
    if ($format === 'hls_m3u8') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_ptz_mode on (hls->m3u8)");
        $ok = @ds_proxy_stream_dahua_hls_m3u8($ipSinMascara, $usuario, $password, $logFile, $id, 'ptz');
        if ($ok === false && !connection_aborted()) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Dahua PTZ HLS: playlist no disponible aún.";
        }
        exit;
    }

    if ($format === 'hls_seg') {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_ptz_mode on (hls->seg)");
        ds_proxy_stream_dahua_hls_seg($ipSinMascara, $usuario, $password, $logFile, $id, 'ptz');
        exit;
    }
}

/**
 * Transcodifica RTSP (H.265/H.264) a MJPEG vía FFmpeg y envía al cliente.
 * Devuelve false si no se pudo iniciar (ej. FFmpeg no disponible); no vuelve si el stream está en curso.
 */
function ds_proxy_stream_rtsp($ipSinMascara, $usuario, $password, $logFile, $id) {
    $ffmpegBin = (defined('CAMARA_VISOR_FFMPEG_BIN') && CAMARA_VISOR_FFMPEG_BIN !== '') ? CAMARA_VISOR_FFMPEG_BIN : 'ffmpeg';
    $port = defined('CAMARA_VISOR_RTSP_PORT') ? (int)CAMARA_VISOR_RTSP_PORT : 554;
    $rtspPath = defined('CAMARA_VISOR_RTSP_PATH') ? CAMARA_VISOR_RTSP_PATH : '/cam/realmonitor?channel=1&subtype=0';
    $fps = defined('CAMARA_VISOR_FFMPEG_FPS') ? (int)CAMARA_VISOR_FFMPEG_FPS : 10;
    $quality = defined('CAMARA_VISOR_FFMPEG_QUALITY') ? (int)CAMARA_VISOR_FFMPEG_QUALITY : 5;
    $startWaitSeconds = defined('CAMARA_VISOR_RTSP_START_WAIT_SECONDS') ? (int)CAMARA_VISOR_RTSP_START_WAIT_SECONDS : 8;

    // Parse del path para variar canal/subtype sin tocar el resto de parámetros.
    $basePath = $rtspPath;
    $query = array();
    if (strpos($rtspPath, '?') !== false) {
        $parts = explode('?', $rtspPath, 2);
        $basePath = $parts[0];
        @parse_str($parts[1], $query);
    }

    $channelDefault = isset($query['channel']) ? (string)$query['channel'] : '1';
    $subtypeDefault = isset($query['subtype']) ? (string)$query['subtype'] : '0';

    $channelsOrderedRaw = array_filter(array($channelDefault, '0', '1'), function ($v) {
        return $v !== '' && is_numeric($v);
    });
    $channelsOrdered = array_values(array_unique($channelsOrderedRaw));
    // Priorizar principal (subtype=0) y luego secundaria si aplica.
    $subtypesOrderedRaw = array_filter(array($subtypeDefault, '0', '1'), function ($v) {
        return $v !== '' && is_numeric($v);
    });
    $subtypesOrdered = array_values(array_unique($subtypesOrderedRaw));

    // Asegurar que subtype=0 vaya primero si el default es otro.
    if (!in_array('0', $subtypesOrdered, true)) {
        $subtypesOrdered = array_merge(array('0'), $subtypesOrdered);
        $subtypesOrdered = array_values(array_unique($subtypesOrdered));
    }
    // Mantener el orden principal -> otros (si el default era 0, ya quedó).
    if ($subtypesOrdered[0] !== '0') {
        $subtypesOrdered = array_values(array_unique(array_merge(array('0'), array_diff($subtypesOrdered, array('0')))));
    }

    $candidates = array();
    foreach ($channelsOrdered as $ch) {
        foreach ($subtypesOrdered as $st) {
            $q = $query;
            $q['channel'] = $ch;
            $q['subtype'] = $st;
            $path = $basePath . '?' . http_build_query($q);
            $rtspUrl = 'rtsp://' . ds_safe_rtsp_credential($usuario) . ':' . ds_safe_rtsp_credential($password) . '@' . $ipSinMascara . ':' . $port . $path;
            $candidates[] = array(
                'channel' => $ch,
                'subtype' => $st,
                'path' => $path,
                'rtspUrl' => $rtspUrl
            );
        }
    }

    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} rtsp start_mode rtsp_candidates=" . count($candidates) . " fps={$fps} q={$quality} wait={$startWaitSeconds}s");

    $boundary = "\r\n--ffboundary\r\nContent-Type: image/jpeg\r\nContent-Length: ";
    $soi = "\xFF\xD8";
    $eoi = "\xFF\xD9";
    $chunkSize = 65536;

    foreach ($candidates as $idx => $cand) {
        if (connection_aborted()) {
            // Cliente desconectó: no seguir probando candidatos.
            return false;
        }
        $rtspUrlMasked = ds_mask_rtsp_url($cand['rtspUrl']);
        ds_proxy_log(
            $logFile,
            "id={$id} ip={$ipSinMascara} rtsp_try idx={$idx} channel=" . $cand['channel'] . " subtype=" . $cand['subtype'] .
            " rtsp=" . $rtspUrlMasked
        );

        $cmd = array(
            $ffmpegBin,
            '-use_wallclock_as_timestamps', '1',
            '-rtsp_transport', 'tcp',
            '-fflags', '+genpts+discardcorrupt',
            '-analyzeduration', '5000000',
            '-probesize', '5000000',
            '-rtbufsize', '2000000',
            '-i', $cand['rtspUrl'],
            '-an', '-sn',
            '-vf', 'fps=' . $fps,
            '-f', 'image2pipe',
            '-c:v', 'mjpeg',
            '-q:v', (string)$quality,
            'pipe:1'
        );

        $cmdForLog = array();
        foreach ($cmd as $p) {
            $pp = (string)$p;
            $cmdForLog[] = (stripos($pp, 'rtsp://') === 0) ? ds_mask_rtsp_url($pp) : $pp;
        }
        ds_proxy_log(
            $logFile,
            "id={$id} ip={$ipSinMascara} ffmpeg_cmd bin={$ffmpegBin} cmd=" . implode(' ', $cmdForLog)
        );

        $descriptorspec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $pipes = array();
        $proc = @proc_open($cmd, $descriptorspec, $pipes, null, null, array('bypass_shell' => true));
        if (!is_resource($proc)) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} rtsp ffmpeg proc_open_fail idx={$idx}");
            continue;
        }

        @fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $stderrCache = '';
        $firstFrameSent = false;
        $bytesEnviados = 0;
        $startAt = microtime(true);
        $lastStderrPullAt = $startAt;

        // Bucle de lectura no-bloqueante: asegura timeout y evita llamadas que puedan colgar.
        while (!connection_aborted()) {
            $elapsed = microtime(true) - $startAt;
            if (!$firstFrameSent && $elapsed > $startWaitSeconds) {
                ds_proxy_log(
                    $logFile,
                    "id={$id} ip={$ipSinMascara} rtsp_no_frames_timeout idx={$idx} channel=" . $cand['channel'] . " subtype=" . $cand['subtype'] .
                    " stderr_tail=" . trim(preg_replace('/\s+/', ' ', substr($stderrCache, -500)))
                );
                break;
            }

            $chunk = @fread($pipes[1], $chunkSize);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
            }

            // Leer stderr también en modo no-bloqueante (fread sobre pipe no bloqueante)
            $errChunk = @fread($pipes[2], 8192);
            if ($errChunk !== false && $errChunk !== '') {
                $stderrCache .= $errChunk;
                if (strlen($stderrCache) > 12000) {
                    $stderrCache = substr($stderrCache, -12000);
                }
            }

            // Evitar busy loop si no hay datos nuevos
            if (($chunk === '' || $chunk === false) && ($errChunk === '' || $errChunk === false)) {
                usleep(20000);
                continue;
            }

            while (($soiPos = strpos($buffer, $soi)) !== false) {
                $eoiPos = strpos($buffer, $eoi, $soiPos);
                if ($eoiPos === false) {
                    $buffer = substr($buffer, $soiPos);
                    break;
                }
                $frameLen = $eoiPos - $soiPos + 2;
                $frame = substr($buffer, $soiPos, $frameLen);
                $buffer = substr($buffer, $eoiPos + 2);

                if (!$firstFrameSent) {
                    // Enviar headers SOLO cuando ya tenemos al menos un frame.
                    if (!headers_sent()) {
                        header('Content-Type: multipart/x-mixed-replace; boundary=--ffboundary');
                    }
                    $firstFrameSent = true;
                    ds_proxy_log(
                        $logFile,
                        "id={$id} ip={$ipSinMascara} rtsp_first_frame_ok idx={$idx} channel=" . $cand['channel'] . " subtype=" . $cand['subtype'] .
                        " elapsed=" . round(microtime(true) - $startAt, 3) . "s"
                    );
                }

                $part = $boundary . strlen($frame) . "\r\n\r\n" . $frame . "\r\n";
                echo $part;
                $bytesEnviados += strlen($part);
                @flush();
            }

            if (strlen($buffer) > 2 * 1024 * 1024) {
                $buffer = '';
            }

            if (connection_aborted()) {
                ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} rtsp_client_aborted idx={$idx}");
                break;
            }
        }

        @fclose($pipes[1]);
        @fclose($pipes[2]);
        @proc_terminate($proc);
        @proc_close($proc);

        if (connection_aborted()) {
            // Si el cliente se fue, salimos inmediatamente sin fallback.
            return false;
        }

        if ($firstFrameSent) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} rtsp_stream_end idx={$idx} bytes={$bytesEnviados}");
            exit;
        }

        // Si no llegó primer frame en tiempo, probar siguiente candidato.
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} rtsp_try_failed idx={$idx} no_first_frame stderr_tail=" . trim(preg_replace('/\s+/', ' ', substr($stderrCache, -400))));
    }

    return false;
}

/**
 * RTSP (H.264) Hikvision -> MJPEG para el visor.
 * No usa el CGI mjpg/video.cgi (Hikvision normalmente no lo tiene / devuelve 404).
 */
function ds_proxy_stream_rtsp_hikvision($ipSinMascara, $usuario, $password, $logFile, $id) {
    $ffmpegBin = (defined('CAMARA_VISOR_FFMPEG_BIN') && CAMARA_VISOR_FFMPEG_BIN !== '') ? CAMARA_VISOR_FFMPEG_BIN : 'ffmpeg';
    $port = defined('CAMARA_VISOR_RTSP_PORT') ? (int)CAMARA_VISOR_RTSP_PORT : 554;
    $fps = defined('CAMARA_VISOR_FFMPEG_FPS') ? (int)CAMARA_VISOR_FFMPEG_FPS : 10;
    $quality = defined('CAMARA_VISOR_FFMPEG_QUALITY') ? (int)CAMARA_VISOR_FFMPEG_QUALITY : 5;
    $startWaitSeconds = defined('CAMARA_VISOR_RTSP_START_WAIT_SECONDS') ? (int)CAMARA_VISOR_RTSP_START_WAIT_SECONDS : 8;

    $hikChannels = array(101, 102); // main/substream típicos en Hikvision

    $boundary = "\r\n--ffboundary\r\nContent-Type: image/jpeg\r\nContent-Length: ";
    $soi = "\xFF\xD8";
    $eoi = "\xFF\xD9";
    $chunkSize = 65536;

    foreach ($hikChannels as $idx => $channelId) {
        if (connection_aborted()) return false;

        $rtspUrl = 'rtsp://' . ds_safe_rtsp_credential($usuario) . ':' . ds_safe_rtsp_credential($password) . '@' . $ipSinMascara . ':' . $port . '/Streaming/Channels/' . $channelId;

        ds_proxy_log(
            $logFile,
            "id={$id} ip={$ipSinMascara} hik_rtsp_try idx={$idx} channel={$channelId} rtsp=" . ds_mask_rtsp_url($rtspUrl)
        );

        $cmd = array(
            $ffmpegBin,
            '-use_wallclock_as_timestamps', '1',
            '-rtsp_transport', 'tcp',
            '-fflags', '+genpts+discardcorrupt',
            '-analyzeduration', '5000000',
            '-probesize', '5000000',
            '-rtbufsize', '2000000',
            '-an', '-sn',
            '-i', $rtspUrl,
            '-vf', 'fps=' . $fps,
            '-f', 'image2pipe',
            '-c:v', 'mjpeg',
            '-q:v', (string)$quality,
            'pipe:1'
        );

        $cmdForLog = array();
        foreach ($cmd as $p) {
            $pp = (string)$p;
            $cmdForLog[] = (stripos($pp, 'rtsp://') === 0) ? ds_mask_rtsp_url($pp) : $pp;
        }
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ffmpeg_cmd bin={$ffmpegBin} cmd=" . implode(' ', $cmdForLog));

        $descriptorspec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        $pipes = array();
        $proc = @proc_open($cmd, $descriptorspec, $pipes, null, null, array('bypass_shell' => true));
        if (!is_resource($proc)) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ffmpeg_proc_open_fail idx={$idx}");
            continue;
        }

        @fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $stderrCache = '';
        $firstFrameSent = false;
        $frameCount = 0;
        $bytesEnviados = 0;
        $startAt = microtime(true);

        while (!connection_aborted()) {
            $elapsed = microtime(true) - $startAt;
            if (!$firstFrameSent && $elapsed > $startWaitSeconds) {
                ds_proxy_log(
                    $logFile,
                    "id={$id} ip={$ipSinMascara} hik_no_frames_timeout idx={$idx} channel={$channelId} stderr_tail=" .
                    trim(preg_replace('/\\s+/', ' ', substr($stderrCache, -500)))
                );
                break;
            }

            $chunk = @fread($pipes[1], $chunkSize);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
            }

            $errChunk = @fread($pipes[2], 8192);
            if ($errChunk !== false && $errChunk !== '') {
                $stderrCache .= $errChunk;
                if (strlen($stderrCache) > 12000) {
                    $stderrCache = substr($stderrCache, -12000);
                }
            }

            if (($chunk === '' || $chunk === false) && ($errChunk === '' || $errChunk === false)) {
                usleep(20000);
                continue;
            }

            while (($soiPos = strpos($buffer, $soi)) !== false) {
                $eoiPos = strpos($buffer, $eoi, $soiPos);
                if ($eoiPos === false) {
                    $buffer = substr($buffer, $soiPos);
                    break;
                }

                $frameLen = $eoiPos - $soiPos + 2;
                $frame = substr($buffer, $soiPos, $frameLen);
                $buffer = substr($buffer, $eoiPos + 2);

                if (!$firstFrameSent) {
                    if (!headers_sent()) {
                        header('Content-Type: multipart/x-mixed-replace; boundary=--ffboundary');
                    }
                    $firstFrameSent = true;
                    ds_proxy_log(
                        $logFile,
                        "id={$id} ip={$ipSinMascara} hik_first_frame_ok idx={$idx} channel={$channelId} elapsed=" . round($elapsed, 3) . "s"
                    );
                }

                // Importante: usar CRLF reales para que el navegador pueda decodificar el multipart MJPEG.
                $part = $boundary . strlen($frame) . "\r\n\r\n" . $frame . "\r\n";
                echo $part;
                $bytesEnviados += strlen($part);
                @flush();

                $frameCount++;
                if (($frameCount % 10) === 0) {
                    ds_proxy_log(
                        $logFile,
                        "id={$id} ip={$ipSinMascara} hik_frame_sent count={$frameCount} idx={$idx} channel={$channelId} elapsed=" .
                        round(microtime(true) - $startAt, 3) . "s bytes={$bytesEnviados}"
                    );
                }
            }

            if (strlen($buffer) > 2 * 1024 * 1024) {
                $buffer = '';
            }

            if (connection_aborted()) {
                ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_client_aborted idx={$idx}");
                break;
            }
        }

        @fclose($pipes[1]);
        @fclose($pipes[2]);
        @proc_terminate($proc);
        @proc_close($proc);

        if (connection_aborted()) return false;

        if ($firstFrameSent) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_stream_end idx={$idx} channel={$channelId} bytes={$bytesEnviados}");
            exit;
        }

        ds_proxy_log(
            $logFile,
            "id={$id} ip={$ipSinMascara} hik_try_failed idx={$idx} channel={$channelId} no_first_frame"
        );
    }

    return false;
}

/**
 * Reempaqueta RTSP (H.264/H.265) de Hikvision a MPEG-TS para mostrarlo directo en <video>.
 * No transpone a MJPEG (reduce mucho la latencia y evita el decode->encode de FFmpeg).
 * Devuelve false si no logra arrancar con ninguno de los canales.
 */
function ds_proxy_stream_hikvision_ts($ipSinMascara, $usuario, $password, $logFile, $id) {
    $ffmpegBin = (defined('CAMARA_VISOR_FFMPEG_BIN') && CAMARA_VISOR_FFMPEG_BIN !== '') ? CAMARA_VISOR_FFMPEG_BIN : 'ffmpeg';
    $port = defined('CAMARA_VISOR_RTSP_PORT') ? (int)CAMARA_VISOR_RTSP_PORT : 554;
    $startWaitSeconds = defined('CAMARA_VISOR_RTSP_START_WAIT_SECONDS') ? (int)CAMARA_VISOR_RTSP_START_WAIT_SECONDS : 8;

    // En tus pruebas: 101 a veces no da frames; 102 suele responder mejor.
    // Mantenemos ambos por robustez.
    // Priorizar canal principal (101) y usar 102 como respaldo.
    $channelCandidates = array(101, 102);

    foreach ($channelCandidates as $idx => $channelId) {
        if (connection_aborted()) return false;

        $rtspPath = '/Streaming/Channels/' . (int)$channelId;
        $rtspUrl = 'rtsp://' . ds_safe_rtsp_credential($usuario) . ':' . ds_safe_rtsp_credential($password) . '@' . $ipSinMascara . ':' . $port . $rtspPath;
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ts_try idx={$idx} channel={$channelId} rtsp=" . ds_mask_rtsp_url($rtspUrl));

        // Importante:
        // 1) Como el navegador a veces no decodifica el bitstream "tal cual" (mosaicos/errores),
        // 2) Re-encodamos a H.264 "browser-friendly" pero sin pasar por MJPEG.
        //
        // Esto reduce la carga de decodificación y ayuda a lograr fluidez.
        $scaleW = 640;           // baja carga (prioridad fluidez)
        $outFps = 10;            // tasa de salida
        $gop = 20;              // GOP para cortes rápidos; 1.0-2.0s aprox con fps=10
        $bitrate = '900k';
        $maxrate = '900k';
        $bufsize = '1800k';

        $vf = "scale={$scaleW}:-2,fps={$outFps}";

        $cmd = array(
            $ffmpegBin,
            '-use_wallclock_as_timestamps', '1',
            '-rtsp_transport', 'tcp',
            '-fflags', '+genpts+discardcorrupt',
            '-analyzeduration', '10000000',
            '-probesize', '10000000',
            '-rtbufsize', '5000000',
            '-an', '-sn',
            '-i', $rtspUrl,
            '-vf', $vf,
            '-r', (string)$outFps,
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-profile:v', 'baseline',
            '-level', '3.1',
            '-pix_fmt', 'yuv420p',
            '-bf', '0',
            '-g', (string)$gop,
            '-keyint_min', (string)$gop,
            '-sc_threshold', '0',
            '-b:v', $bitrate,
            '-maxrate', $maxrate,
            '-bufsize', $bufsize,
            // Asegura Annex-B para H.264 dentro de MPEG-TS.
            '-bsf:v', 'h264_mp4toannexb',
            '-f', 'mpegts',
            '-mpegts_flags', 'resend_headers+initial_discontinuity',
            '-muxdelay', '0.1',
            '-muxpreload', '0.1',
            'pipe:1'
        );

        ds_proxy_log(
            $logFile,
            "id={$id} ip={$ipSinMascara} hik_ts_ffmpeg_cfg idx={$idx} channel={$channelId} scaleW={$scaleW} outfps={$outFps} gop={$gop} bitrate={$bitrate} rtsp=" . ds_mask_rtsp_url($rtspUrl)
        );

        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );

        $proc = @proc_open($cmd, $descriptorspec, $pipes, null, null, array('bypass_shell' => true));
        if (!is_resource($proc)) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ts_proc_open_fail idx={$idx} channel={$channelId}");
            continue;
        }

        @fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $firstBytesSent = false;
        $bytesEnviados = 0;
        $stderrCache = '';
        $startAt = microtime(true);
        $lastBytesLogAt = $startAt;

        while (!connection_aborted()) {
            $elapsed = microtime(true) - $startAt;

            if (!$firstBytesSent && $elapsed > $startWaitSeconds) {
                ds_proxy_log(
                    $logFile,
                    "id={$id} ip={$ipSinMascara} hik_ts_no_bytes_timeout idx={$idx} channel={$channelId} stderr_tail=" .
                    trim(preg_replace('/\\s+/', ' ', substr($stderrCache, -600)))
                );
                break;
            }

            $chunk = @fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                if (!$firstBytesSent) {
                    if (!headers_sent()) {
                        header('Content-Type: video/mp2t');
                    }
                    $firstBytesSent = true;
                    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ts_first_bytes_ok idx={$idx} channel={$channelId} elapsed=" . round($elapsed, 3) . "s");
                }
                echo $chunk;
                $bytesEnviados += strlen($chunk);
                @flush();

                // Logs periódicos para confirmar que el TS sigue fluyendo.
                $nowAt = microtime(true);
                if (($nowAt - $lastBytesLogAt) >= 5.0) {
                    ds_proxy_log(
                        $logFile,
                        "id={$id} ip={$ipSinMascara} hik_ts_bytes_progress idx={$idx} channel={$channelId} elapsed=" . round($nowAt - $startAt, 3) . "s bytes={$bytesEnviados}"
                    );
                    $lastBytesLogAt = $nowAt;
                }
            }

            $errChunk = @fread($pipes[2], 8192);
            if ($errChunk !== false && $errChunk !== '') {
                $stderrCache .= $errChunk;
                if (strlen($stderrCache) > 12000) {
                    $stderrCache = substr($stderrCache, -12000);
                }
            }

            if (($chunk === '' || $chunk === false) && ($errChunk === '' || $errChunk === false)) {
                usleep(20000);
                continue;
            }

            if (connection_aborted()) {
                ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ts_client_aborted idx={$idx} channel={$channelId}");
                break;
            }
        }

        // Diagnóstico: al salir del loop, guardamos un tail de stderr para ver si ffmpeg
        // se quedó sin producir datos o reportó algún error de muxing.
        ds_proxy_log(
            $logFile,
            "id={$id} ip={$ipSinMascara} hik_fmp4_loop_end idx={$idx} channel={$channelId} firstBytesSent=" .
            ($firstBytesSent ? '1' : '0') . " bytes={$bytesEnviados} stderr_tail=" .
            trim(preg_replace('/\\s+/', ' ', substr($stderrCache, -800)))
        );

        @fclose($pipes[1]);
        @fclose($pipes[2]);
        @proc_terminate($proc);
        @proc_close($proc);

        if (connection_aborted()) return false;

        if ($firstBytesSent) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ts_stream_end idx={$idx} channel={$channelId} bytes={$bytesEnviados}");
            exit;
        }

        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_ts_try_failed idx={$idx} channel={$channelId} no_first_bytes");
    }

    return false;
}

/**
 * Reempaqueta/transcodifica RTSP de Hikvision a fMP4 (fragmented MP4) para reproducir directo en <video>.
 * La idea es evitar el camino TS->repack que tu navegador no decodifica bien.
 */
function ds_proxy_stream_hikvision_fmp4($ipSinMascara, $usuario, $password, $logFile, $id) {
    $ffmpegBin = (defined('CAMARA_VISOR_FFMPEG_BIN') && CAMARA_VISOR_FFMPEG_BIN !== '') ? CAMARA_VISOR_FFMPEG_BIN : 'ffmpeg';
    $port = defined('CAMARA_VISOR_RTSP_PORT') ? (int)CAMARA_VISOR_RTSP_PORT : 554;
    $startWaitSeconds = defined('CAMARA_VISOR_RTSP_START_WAIT_SECONDS') ? (int)CAMARA_VISOR_RTSP_START_WAIT_SECONDS : 8;

    // Priorizar canal principal (101) y usar 102 como respaldo.
    $channelCandidates = array(101, 102);

    // Parámetros de salida "ligeros" para fluidez (menos carga = menos congelamientos).
    $scaleW = 480;
    $outFps = 5;
    $gop = 10; // ~2s con fps=5 (keyframes frecuentes)
    $bitrate = '600k';
    $maxrate = '600k';
    $bufsize = '1200k';

    $vf = "scale={$scaleW}:-2,fps={$outFps}";

    foreach ($channelCandidates as $idx => $channelId) {
        if (connection_aborted()) return false;

        $rtspPath = '/Streaming/Channels/' . (int)$channelId;
        $rtspUrl = 'rtsp://' . ds_safe_rtsp_credential($usuario) . ':' . ds_safe_rtsp_credential($password) . '@' . $ipSinMascara . ':' . $port . $rtspPath;
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_fmp4_try idx={$idx} channel={$channelId} rtsp=" . ds_mask_rtsp_url($rtspUrl));

        $cmd = array(
            $ffmpegBin,
            '-use_wallclock_as_timestamps', '1',
            '-rtsp_transport', 'tcp',
            '-fflags', '+genpts+discardcorrupt',
            '-analyzeduration', '10000000',
            '-probesize', '10000000',
            '-rtbufsize', '5000000',
            '-an', '-sn',
            '-i', $rtspUrl,
            '-vf', $vf,
            '-r', (string)$outFps,
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-profile:v', 'baseline',
            '-level', '3.1',
            '-pix_fmt', 'yuv420p',
            '-g', (string)$gop,
            '-keyint_min', (string)$gop,
            '-sc_threshold', '0',
            '-bf', '0',
            '-b_strategy', '0',
            '-b:v', $bitrate,
            '-maxrate', $maxrate,
            '-bufsize', $bufsize,
            '-movflags', 'cmaf+empty_moov+default_base_moof+separate_moof',
            '-frag_duration', '1000',
            '-min_frag_duration', '1000',
            '-f', 'mp4',
            'pipe:1'
        );

        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );

        $proc = @proc_open($cmd, $descriptorspec, $pipes, null, null, array('bypass_shell' => true));
        if (!is_resource($proc)) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_fmp4_proc_open_fail idx={$idx} channel={$channelId}");
            continue;
        }

        @fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $firstBytesSent = false;
        $bytesEnviados = 0;
        $stderrCache = '';
        $startAt = microtime(true);
        $lastBytesLogAt = $startAt;

        while (!connection_aborted()) {
            $elapsed = microtime(true) - $startAt;

            if (!$firstBytesSent && $elapsed > $startWaitSeconds) {
                ds_proxy_log(
                    $logFile,
                    "id={$id} ip={$ipSinMascara} hik_fmp4_no_bytes_timeout idx={$idx} channel={$channelId} stderr_tail=" .
                    trim(preg_replace('/\\s+/', ' ', substr($stderrCache, -600)))
                );
                break;
            }

            $chunk = @fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                if (!$firstBytesSent) {
                    if (!headers_sent()) {
                        header('Content-Type: video/mp4');
                    }
                    $firstBytesSent = true;
                    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_fmp4_first_bytes_ok idx={$idx} channel={$channelId} elapsed=" . round($elapsed, 3) . "s");
                }
                echo $chunk;
                $bytesEnviados += strlen($chunk);
                @flush();

                $nowAt = microtime(true);
                if (($nowAt - $lastBytesLogAt) >= 5.0) {
                    ds_proxy_log(
                        $logFile,
                        "id={$id} ip={$ipSinMascara} hik_fmp4_bytes_progress idx={$idx} channel={$channelId} elapsed=" . round($nowAt - $startAt, 3) . "s bytes={$bytesEnviados}"
                    );
                    $lastBytesLogAt = $nowAt;
                }
            }

            $errChunk = @fread($pipes[2], 8192);
            if ($errChunk !== false && $errChunk !== '') {
                $stderrCache .= $errChunk;
                if (strlen($stderrCache) > 12000) {
                    $stderrCache = substr($stderrCache, -12000);
                }
            }

            if (($chunk === '' || $chunk === false) && ($errChunk === '' || $errChunk === false)) {
                usleep(20000);
                continue;
            }

            if (connection_aborted()) {
                ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_fmp4_client_aborted idx={$idx} channel={$channelId}");
                break;
            }
        }

        @fclose($pipes[1]);
        @fclose($pipes[2]);
        @proc_terminate($proc);
        @proc_close($proc);

        if (connection_aborted()) return false;

        if ($firstBytesSent) {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_fmp4_stream_end idx={$idx} channel={$channelId} bytes={$bytesEnviados}");
            exit;
        }

        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hik_fmp4_try_failed idx={$idx} channel={$channelId} no_first_bytes");
    }

    return false;
}

/**
 * HLS (player real) para Hikvision:
 * - RTSP (H.264) -> FFmpeg -> HLS .m3u8 + .ts
 * - Servimos la playlist y los segmentos por endpoints internos del proxy.
 */
function ds_proxy_stream_hikvision_hls_m3u8($ipSinMascara, $usuario, $password, $logFile, $id, $hikProfile = 'default') {
    $ffmpegBin = (defined('CAMARA_VISOR_FFMPEG_BIN') && CAMARA_VISOR_FFMPEG_BIN !== '') ? CAMARA_VISOR_FFMPEG_BIN : 'ffmpeg';
    $port = defined('CAMARA_VISOR_RTSP_PORT') ? (int)CAMARA_VISOR_RTSP_PORT : 554;
    $startWaitSeconds = defined('CAMARA_VISOR_RTSP_START_WAIT_SECONDS') ? (int)CAMARA_VISOR_RTSP_START_WAIT_SECONDS : 8;

    $safeProfile = preg_replace('/[^a-z0-9_\\-]/i', '', (string)$hikProfile);
    if ($safeProfile === '') $safeProfile = 'default';
    $hlsBaseDir = rtrim($GLOBALS["DS_PROXY_CACHE_BASE"], "/\\") . "/hls_cache_" . $safeProfile;
    $camDir = $hlsBaseDir . "/cam_" . (int)$id;
    $playlistPath = $camDir . "/playlist.m3u8";
    $segPattern = $camDir . "/seg_%03d.ts";
    $ffmpegLog = $camDir . "/ffmpeg_hls.log";

    if (!is_dir($hlsBaseDir)) {
        @mkdir($hlsBaseDir, 0777, true);
    }
    if (!is_dir($camDir)) {
        @mkdir($camDir, 0777, true);
    }

    // Si la playlist no existe o está muy vieja, arrancamos un FFmpeg nuevo.
    $needStart = !file_exists($playlistPath) || ((time() - @filemtime($playlistPath)) > 20);

    // Prefijo único por sesión — evita que el reproductor sirva segmentos de sesiones anteriores.
    $sessionPrefix = 'v' . time();
    $segPattern = $camDir . "/{$sessionPrefix}_%03d.ts";
    $channelCandidates = array(101, 102);

    if ($needStart) {
        // Limpiar TODOS los segmentos y el playlist viejos sin excepción.
        foreach (glob($camDir . "/*.ts") ?: array() as $f) { @unlink($f); }
        @unlink($playlistPath);

        // Punto dulce: calidad alta + fluidez estable (CFR) para reducir tirones.
        $vf = "scale=1920:-2,fps=15";
        $outGop = 15; // keyframe ~cada 1s para cortes HLS suaves
        $bitrate = '8200k';
        $maxrate = '9000k';
        $bufsize = '9000k';

        foreach ($channelCandidates as $idx => $channelId) {
            if (connection_aborted()) return false;

            $rtspPath = '/Streaming/Channels/' . (int)$channelId;
            $rtspUrl = 'rtsp://' . ds_safe_rtsp_credential($usuario) . ':' . ds_safe_rtsp_credential($password) . '@' . $ipSinMascara . ':' . $port . $rtspPath;
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hls_start_try idx={$idx} channel={$channelId} rtsp=" . ds_mask_rtsp_url($rtspUrl));

            // Iniciar ffmpeg en background.
            // IMPORTANTE: NO usar cmd.exe aquí; rompe el escape de credenciales (401 en ffmpeg_hls.log).
            // -t limita para que no quede infinito; si el usuario abre de nuevo, se regenera.
            $args = array(
                $ffmpegBin,
                '-use_wallclock_as_timestamps', '1',
                '-rtsp_transport', 'tcp',
                '-fflags', '+genpts+discardcorrupt',
                '-analyzeduration', '2000000',
                '-probesize', '5000000',
                '-rtbufsize', '2000000',
                '-i', $rtspUrl,
                '-an', '-sn',
                '-vf', $vf,
                '-r', '15',
                '-c:v', 'libx264',
                '-preset', 'veryfast',
                '-profile:v', 'baseline',
                '-level', '3.1',
                '-pix_fmt', 'yuv420p',
                '-bf', '0',
                '-g', (string)$outGop,
                '-keyint_min', (string)$outGop,
                '-sc_threshold', '0',
                '-x264-params', 'force-cfr=1',
                '-b:v', $bitrate,
                '-maxrate', $maxrate,
                '-bufsize', $bufsize,
                '-f', 'hls',
                '-hls_time', '2',
                '-hls_list_size', '5',
                '-force_key_frames', 'expr:gte(t,n_forced*2)',
                '-hls_flags', 'delete_segments+omit_endlist+split_by_time+independent_segments',
                '-hls_segment_filename', $segPattern,
                '-hls_start_number_source', 'epoch',
                '-reset_timestamps', '1',
                '-t', '300',
                $playlistPath
            );

            $logHandle = @fopen($ffmpegLog, 'ab');
            $descriptorspec = array(
                0 => array("pipe", "r"),
                1 => $logHandle ? $logHandle : array("pipe", "w"),
                2 => $logHandle ? $logHandle : array("pipe", "w"),
            );
            $proc = @proc_open($args, $descriptorspec, $pipes, null, null, array('bypass_shell' => true));
            if (is_resource($proc)) {
                // No necesitamos stdin; cerrar para que ffmpeg no espere.
                @fclose($pipes[0]);
            }

            // Esperar a que el playlist se genere.
            $t0 = microtime(true);
            $ready = false;
            $maxWait = min(max(8, $startWaitSeconds), 18);
            while ((microtime(true) - $t0) < $maxWait) {
                if (file_exists($playlistPath)) {
                    $content = @file_get_contents($playlistPath);
                    if ($content && strpos($content, '#EXTM3U') !== false) {
                        $ready = true;
                        break;
                    }
                }
                usleep(200000);
            }
            if ($ready) break;
        }
    } else {
        // Playlist ya existe y es reciente: esperar hasta 5s a que tenga contenido válido
        // (puede estar siendo escrita por un FFmpeg en curso).
        $t0 = microtime(true);
        while ((microtime(true) - $t0) < 5) {
            $content = @file_get_contents($playlistPath);
            if ($content && strpos($content, '#EXTM3U') !== false) {
                break;
            }
            usleep(200000);
        }
    }

    if (!file_exists($playlistPath)) {
        if (file_exists($ffmpegLog)) {
            $tail = @file_get_contents($ffmpegLog);
            $tail = $tail ? trim(preg_replace('/\\s+/', ' ', substr($tail, -800))) : '';
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hls_playlist_missing tail={$tail}");
        } else {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hls_playlist_missing no_ffmpeg_log");
        }
        return false;
    }

    $content = @file_get_contents($playlistPath);
    if (!$content) return false;

    // Reescribir líneas de segmentos para que hls.js llame a nuestro endpoint de proxy.
    $idEnc = rawurlencode((string)$id);
    // Segment URIs deben resolverse relativo al directorio del playlist.
    // Como la playlist se sirve desde .../ajax/proxy_stream.php, usamos solo
    // 'proxy_stream.php' (sin prefijo 'ajax/') para evitar que el cliente
    // construya .../ajax/ajax/proxy_stream.php.
    $segBase = rtrim($GLOBALS["DS_PROXY_STREAM_BASE"], "?") . "?id={$idEnc}&format=hls_seg&file=";

    $lines = preg_split("/\\r?\\n/", $content);
    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if ($line === '' || strpos($line, '#') === 0) continue;
        // Solo tratamos el URI del segmento (.ts).
        $file = basename($line);
        if ($file !== '') {
            $lines[$i] = $segBase . rawurlencode($file);
        }
    }

    $out = implode("\n", $lines);
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $out;
    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} hls_playlist_sent");
    return true;
}

function ds_proxy_stream_hikvision_hls_seg($ipSinMascara, $usuario, $password, $logFile, $id, $hikProfile = 'default') {
    $file = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
    if ($file === '') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Missing file.";
        return;
    }

    $safeProfile = preg_replace('/[^a-z0-9_\\-]/i', '', (string)$hikProfile);
    if ($safeProfile === '') $safeProfile = 'default';
    $hlsBaseDir = rtrim($GLOBALS["DS_PROXY_CACHE_BASE"], "/\\") . "/hls_cache_" . $safeProfile;
    $camDir = $hlsBaseDir . "/cam_" . (int)$id;
    $path = $camDir . "/" . $file;
    if (!file_exists($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Segment not found.";
        return;
    }

    header('Content-Type: video/mp2t');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    @readfile($path);
}

/**
 * DAHUA FIJA -> HLS m3u8 (player real).
 * Preferimos principal (subtype=0) y usamos secundaria como respaldo.
 */
function ds_proxy_stream_dahua_hls_m3u8($ipSinMascara, $usuario, $password, $logFile, $id, $dahuaProfile = 'fija') {
    $ffmpegBin = (defined('CAMARA_VISOR_FFMPEG_BIN') && CAMARA_VISOR_FFMPEG_BIN !== '') ? CAMARA_VISOR_FFMPEG_BIN : 'ffmpeg';
    $port = defined('CAMARA_VISOR_RTSP_PORT') ? (int)CAMARA_VISOR_RTSP_PORT : 554;
    $startWaitSeconds = defined('CAMARA_VISOR_RTSP_START_WAIT_SECONDS') ? (int)CAMARA_VISOR_RTSP_START_WAIT_SECONDS : 8;

    $safeProfile = preg_replace('/[^a-z0-9_\\-]/i', '', (string)$dahuaProfile);
    if ($safeProfile === '') $safeProfile = 'fija';
    $hlsBaseDir = rtrim($GLOBALS["DS_PROXY_CACHE_BASE"], "/\\") . "/hls_cache_dahua_" . $safeProfile;
    $camDir = $hlsBaseDir . "/cam_" . (int)$id;
    $playlistPath = $camDir . "/playlist.m3u8";
    $segPattern = $camDir . "/seg_%03d.ts";
    $ffmpegLog = $camDir . "/ffmpeg_hls.log";
    $sourceMetaPath = $camDir . "/source.txt";

    if (!is_dir($hlsBaseDir)) @mkdir($hlsBaseDir, 0777, true);
    if (!is_dir($camDir)) @mkdir($camDir, 0777, true);

    $needStart = !file_exists($playlistPath) || ((time() - @filemtime($playlistPath)) > 20);

    // Dahua RTSP candidatos: principal y luego secundaria.
    $rtspCandidates = array(
        '/cam/realmonitor?channel=1&subtype=0',
        '/cam/realmonitor?channel=1&subtype=1',
        '/cam/realmonitor?channel=0&subtype=0',
        '/cam/realmonitor?channel=0&subtype=1',
    );

    // Prefijo único por sesión — evita que el reproductor sirva segmentos de sesiones anteriores.
    $sessionPrefix = 'v' . time();
    $segPattern = $camDir . "/{$sessionPrefix}_%03d.ts";

    if ($needStart) {
        foreach (glob($camDir . "/*.ts") as $f) { @unlink($f); }
        @unlink($playlistPath);
        @unlink($sourceMetaPath);

        $vf = "scale=1920:-2,fps=15";
        $outGop = 15;
        $bitrate = '8200k';
        $maxrate = '9000k';
        $bufsize = '9000k';

        foreach ($rtspCandidates as $idx => $pathCand) {
            if (connection_aborted()) return false;
            $rtspUrl = 'rtsp://' . ds_safe_rtsp_credential($usuario) . ':' . ds_safe_rtsp_credential($password) . '@' . $ipSinMascara . ':' . $port . $pathCand;
            $streamType = (strpos($pathCand, 'subtype=0') !== false) ? 'principal' : 'secundario';
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_hls_start_try idx={$idx} stream_type={$streamType} rtsp=" . ds_mask_rtsp_url($rtspUrl));

            $args = array(
                $ffmpegBin,
                '-use_wallclock_as_timestamps', '1',
                '-rtsp_transport', 'tcp',
                '-fflags', '+genpts+discardcorrupt',
                '-analyzeduration', '2000000',
                '-probesize', '5000000',
                '-rtbufsize', '2000000',
                '-i', $rtspUrl,
                '-an', '-sn',
                '-vf', $vf,
                '-r', '15',
                '-c:v', 'libx264',
                '-preset', 'veryfast',
                '-profile:v', 'baseline',
                '-level', '3.1',
                '-pix_fmt', 'yuv420p',
                '-bf', '0',
                '-g', (string)$outGop,
                '-keyint_min', (string)$outGop,
                '-sc_threshold', '0',
                '-x264-params', 'force-cfr=1',
                '-b:v', $bitrate,
                '-maxrate', $maxrate,
                '-bufsize', $bufsize,
                '-f', 'hls',
                '-hls_time', '2',
                '-hls_list_size', '5',
                '-force_key_frames', 'expr:gte(t,n_forced*2)',
                '-hls_flags', 'delete_segments+omit_endlist+split_by_time+independent_segments',
                '-hls_segment_filename', $segPattern,
                '-hls_start_number_source', 'epoch',
                '-reset_timestamps', '1',
                '-t', '300',
                $playlistPath
            );

            $logHandle = @fopen($ffmpegLog, 'ab');
            $descriptorspec = array(
                0 => array("pipe", "r"),
                1 => $logHandle ? $logHandle : array("pipe", "w"),
                2 => $logHandle ? $logHandle : array("pipe", "w"),
            );
            $proc = @proc_open($args, $descriptorspec, $pipes, null, null, array('bypass_shell' => true));
            if (is_resource($proc)) {
                @fclose($pipes[0]);
            }

            $t0 = microtime(true);
            $ready = false;
            // Arranque más rápido: no bloqueamos mucho en cada candidato.
            $candidateWaitSeconds = ($idx === 0) ? 3 : 3;
            while ((microtime(true) - $t0) < $candidateWaitSeconds) {
                if (file_exists($playlistPath)) {
                    $content = @file_get_contents($playlistPath);
                    if ($content && strpos($content, '#EXTM3U') !== false) {
                        $ready = true;
                        @file_put_contents($sourceMetaPath, $pathCand);
                        break;
                    }
                }
                usleep(200000);
            }
            if ($ready) break;
        }
    }

    if (!file_exists($playlistPath)) {
        if (file_exists($ffmpegLog)) {
            $tail = @file_get_contents($ffmpegLog);
            $tail = $tail ? trim(preg_replace('/\\s+/', ' ', substr($tail, -800))) : '';
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_hls_playlist_missing tail={$tail}");
        } else {
            ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_hls_playlist_missing no_ffmpeg_log");
        }
        return false;
    }

    $content = @file_get_contents($playlistPath);
    if (!$content) return false;

    $idEnc = rawurlencode((string)$id);
    $segBase = rtrim($GLOBALS["DS_PROXY_STREAM_BASE"], "?") . "?id={$idEnc}&format=hls_seg&file=";

    $lines = preg_split("/\\r?\\n/", $content);
    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if ($line === '' || strpos($line, '#') === 0) continue;
        $file = basename($line);
        if ($file !== '') $lines[$i] = $segBase . rawurlencode($file);
    }

    $out = implode("\n", $lines);
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $out;
    $sourceUsed = @file_get_contents($sourceMetaPath);
    $sourceUsed = $sourceUsed ? trim($sourceUsed) : 'unknown';
    $streamType = (strpos($sourceUsed, 'subtype=0') !== false) ? 'principal' : ((strpos($sourceUsed, 'subtype=1') !== false) ? 'secundario' : 'unknown');
    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} dahua_hls_playlist_sent source={$sourceUsed} stream_type={$streamType}");
    return true;
}

function ds_proxy_stream_dahua_hls_seg($ipSinMascara, $usuario, $password, $logFile, $id, $dahuaProfile = 'fija') {
    $file = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
    if ($file === '') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Missing file.";
        return;
    }

    $safeProfile = preg_replace('/[^a-z0-9_\\-]/i', '', (string)$dahuaProfile);
    if ($safeProfile === '') $safeProfile = 'fija';
    $hlsBaseDir = rtrim($GLOBALS["DS_PROXY_CACHE_BASE"], "/\\") . "/hls_cache_dahua_" . $safeProfile;
    $camDir = $hlsBaseDir . "/cam_" . (int)$id;
    $path = $camDir . "/" . $file;
    if (!file_exists($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Segment not found.";
        return;
    }

    header('Content-Type: video/mp2t');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    @readfile($path);
}

if ($useRtsp) {
    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} try_rtsp");
    $started = @ds_proxy_stream_rtsp($ipSinMascara, $usuario, $password, $logFile, $id);
    if (connection_aborted()) {
        // Si el cliente ya no está, no hacer fallback (evita bloqueos en cambios de cámara).
        exit;
    }
    if ($started === false) {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} rtsp_fail fallback_curl");
    }
    if ($started !== false) {
        exit;
    }
}

$ch = curl_init($url);
if (!$ch) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "No se pudo inicializar el proxy.";
    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} curl_init_fail");
    exit;
}

$contentTypeEnviado = false;
$statusFromUpstream = 0;
$bytesEnviados = 0;
ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} start url_path=" . $path . " url_full=" . $url);

/**
 * Ejecuta el proxy MJPEG. Devuelve array con (ok, errno, err, http).
 * Nota: En streams MJPEG el "ok" puede ser true y no terminar nunca (normal).
 */
function ds_proxy_exec_stream($ch, $usuario, $password, $logFile, $id, $ipSinMascara, &$contentTypeEnviado, &$statusFromUpstream, &$bytesEnviados) {
    $contentTypeEnviado = false;
    $statusFromUpstream = 0;
    $bytesEnviados = 0;

    // LOG de credenciales (password enmascarada)
    ds_proxy_log(
        $logFile,
        "id={$id} ip={$ipSinMascara} usar_cred usuario=" . $usuario . " password=" . ds_mask_pass($password)
    );

    curl_setopt($ch, CURLOPT_USERPWD, $usuario . ":" . $password);
    curl_setopt($ch, CURLOPT_HTTPAUTH, (defined('CURLAUTH_BASIC') ? CURLAUTH_BASIC : 0) | (defined('CURLAUTH_DIGEST') ? CURLAUTH_DIGEST : 0));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    // Usamos progress para detectar aborto del cliente y cortar el stream.
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $download_size, $downloaded, $upload_size, $uploaded) {
        return connection_aborted() ? 1 : 0;
    });
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024 * 16);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: close', 'Expect:'));
    curl_setopt($ch, CURLOPT_USERAGENT, 'SIGTRAM-ProxyStream/1.1');
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $headerLine) use (&$contentTypeEnviado, &$statusFromUpstream) {
        $len = strlen($headerLine);
        $h = trim($headerLine);
        if ($h === "") return $len;

        if (stripos($h, "HTTP/") === 0) {
            $parts = explode(" ", $h);
            if (count($parts) >= 2) $statusFromUpstream = (int)$parts[1];
            return $len;
        }
        if (!$contentTypeEnviado && stripos($h, "Content-Type:") === 0) {
            header($h);
            $contentTypeEnviado = true;
        }
        return $len;
    });
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$bytesEnviados) {
        if (connection_aborted()) {
            // Cortar el curl al instante cuando el cliente desconecta.
            return -1;
        }
        echo $data;
        $bytesEnviados += strlen($data);
        @flush();
        return strlen($data);
    });

    @curl_exec($ch);
    $err = curl_error($ch);
    $errno = curl_errno($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($errno !== 0) {
        ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} curl_errno={$errno} http={$httpCode} bytes={$bytesEnviados} err=" . str_replace(array("\r", "\n"), " ", $err));
    }
    return array('errno' => $errno, 'err' => $err, 'http' => $httpCode);
}

// Si no llegó Content-Type desde upstream, fijar uno típico de MJPEG.
if (!headers_sent()) {
    header('Content-Type: multipart/x-mixed-replace; boundary=--myboundary');
}

$usando = ($usuarioDb !== '' && $passwordDb !== '') ? 'bd' : 'default';
// LOG credenciales calculadas (password enmascarada)
ds_proxy_log(
    $logFile,
    "id={$id} ip={$ipSinMascara} auth={$usando} user=" . ($usando === 'bd' ? $usuarioDb : $defUsuario) .
    " pass=" . ds_mask_pass($usando === 'bd' ? $passwordDb : $defPassword)
);

// Primer intento: credenciales (BD si están completas, si no defaults)
$res = ds_proxy_exec_stream($ch, $usuario, $password, $logFile, $id, $ipSinMascara, $contentTypeEnviado, $statusFromUpstream, $bytesEnviados);

// Reintento si 401/403 con defaults (solo si el primero vino de BD)
if (($res['http'] === 401 || $res['http'] === 403) && ($usuarioDb !== '' && $passwordDb !== '')) {
    ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} retry_with_default http={$res['http']}");
    // reset para reintentar
    curl_setopt($ch, CURLOPT_URL, $url);
    $res2 = ds_proxy_exec_stream($ch, $defUsuario, $defPassword, $logFile, $id, $ipSinMascara, $contentTypeEnviado, $statusFromUpstream, $bytesEnviados);
    $res = $res2;
}

curl_close($ch);

// Si falló inmediatamente (sin bytes), devolver error legible
if ($bytesEnviados === 0 && ($res['http'] === 401 || $res['http'] === 403)) {
    if (!headers_sent()) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo "Acceso denegado por la cámara (401). Verifique usuario_visor/password_visor para la IP.";
    exit;
}

ds_proxy_log($logFile, "id={$id} ip={$ipSinMascara} end http={$statusFromUpstream} bytes={$bytesEnviados}");
exit;
