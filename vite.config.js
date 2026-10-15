import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/shared/v2-reporte-area-cargo.css',
                'resources/css/tramite-interno/buscar-tramite.css',
                'resources/css/tramite-interno/reporte-bandeja.css',
                'resources/css/tramite-interno/reporte-cargo-interno.css',
                'resources/css/tramite-interno/reporte-tramite.css',
                'resources/css/tramite-interno/secuencial-area.css',
                'resources/css/tramite-externo/modales-tramite.css',
                'resources/css/tramite-externo/reportes-expedientes.css',
                'resources/css/tramite-externo/segi-adjuntar.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
