<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Módulo de Notificaciones de WhatsApp de SIGTRAM3
    |--------------------------------------------------------------------------
    |
    | Define el comportamiento del canal de WhatsApp, el proveedor activo
    | y las credenciales correspondientes.
    |
    */

    // Activa (true) o Pausa (false) el canal de WhatsApp al instante
    'activo' => env('WHATSAPP_ACTIVO', true),

    // Proveedor activo: 'whapi' (producción) o 'mock' (pruebas locales/simuladas)
    'proveedor' => env('WHATSAPP_PROVEEDOR', 'whapi'),

    // Credenciales de acceso a la API externa
    'token' => env('WHATSAPP_TOKEN', 'bheB4JTJVawX1ajhOTqDugZwTuW1TGTQ'),
    'endpoint' => env('WHATSAPP_ENDPOINT', 'https://gate.whapi.cloud'),

    // Difusión automática (Broadcasting) para supervisores
    // Lista separada por comas de números con código de país
    'copias_broadcast' => array_filter(
        explode(',', (string) env('WHATSAPP_BROADCAST_COPIAS', ''))
    ),
];
