<?php

declare(strict_types=1);

return [
    'usar_oficial' => (bool) env('PIDE_USAR_OFICIAL', true),
    'usar_local' => (bool) env('PIDE_USAR_LOCAL', true),
    'usar_decolecta' => (bool) env('PIDE_USAR_DECOLECTA', true),
    'usar_sunarp' => (bool) env('PIDE_USAR_SUNARP', true),
    'timeout' => (int) env('PIDE_TIMEOUT', 3),
    'decolecta_tokens' => array_filter(
        explode(',', env('DECOLECTA_TOKENS', ''))
    ),
    'ruc_institucional' => env('PIDE_RUC_INSTITUCIONAL', ''),
    'credentials_pool' => env('PIDE_CREDENTIALS_POOL') ? json_decode((string) env('PIDE_CREDENTIALS_POOL'), true) : [],
    'sunarp' => [
        'user' => env('PIDE_SUNARP_USER', ''),
        'clave' => env('PIDE_SUNARP_CLAVE', ''),
    ],
];
