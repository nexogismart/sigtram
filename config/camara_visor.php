<?php

return [
    'usuario' => env('CAMARA_VISOR_USUARIO', ''),
    'password' => env('CAMARA_VISOR_PASSWORD', ''),
    'path' => env('CAMARA_VISOR_PATH', '/cgi-bin/mjpg/video.cgi?channel=1&subtype=1'),
    'use_rtsp' => env('CAMARA_VISOR_USE_RTSP', false),
    'rtsp_port' => (int) env('CAMARA_VISOR_RTSP_PORT', 554),
    'rtsp_path' => env('CAMARA_VISOR_RTSP_PATH', '/cam/realmonitor?channel=1&subtype=0'),
    'ffmpeg_fps' => (int) env('CAMARA_VISOR_FFMPEG_FPS', 5),
    'ffmpeg_quality' => (int) env('CAMARA_VISOR_FFMPEG_QUALITY', 8),
    'rtsp_start_wait_seconds' => (int) env('CAMARA_VISOR_RTSP_START_WAIT_SECONDS', 8),
    'ffmpeg_bin' => env('CAMARA_VISOR_FFMPEG_BIN', '/usr/bin/ffmpeg'),
    'enable_log' => env('CAMARA_VISOR_ENABLE_LOG', false),
];
