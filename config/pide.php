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
    'ruc_institucional' => env('PIDE_RUC_INSTITUCIONAL', '20131366702'),
    'credentials_pool' => [
        ['usuario' => '70068099', 'clave' => '70068099'],
        ['usuario' => '46156461', 'clave' => '46156461'],
        ['usuario' => '72978383', 'clave' => '72978383'],
        ['usuario' => '62126949', 'clave' => '62126949'],
        ['usuario' => '73031854', 'clave' => '73031854'],
        ['usuario' => '73758325', 'clave' => '73758325'],
        ['usuario' => '72495583', 'clave' => '72495583'],
        ['usuario' => '09930622', 'clave' => '09930622']
    ],
    'sunarp' => [
        'user' => env('PIDE_SUNARP_USER', '20131366702-MUNIPUENTEPIEDRA'),
        'clave' => env('PIDE_SUNARP_CLAVE', 'S$btJXBEACns2Cs'),
    ],
];
