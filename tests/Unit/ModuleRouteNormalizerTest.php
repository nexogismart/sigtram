<?php

declare(strict_types=1);

use App\Services\Core\ModuleRouteNormalizer;

describe('ModuleRouteNormalizer', function () {
    $normalizer = new ModuleRouteNormalizer();

    test('1. normaliza ruta con extension .blade.php', function () use ($normalizer) {
        expect($normalizer->normalize('/mod_gm/mod_tram_exte/segi_expedi.blade.php'))
            ->toBe('mod_gm.mod_tram_exte.segi_expedi');
    });

    test('2. normaliza ruta con extension .php', function () use ($normalizer) {
        expect($normalizer->normalize('/mod_a/sgl/pago_proveedores/ver_requerimiento_finanzas.php'))
            ->toBe('mod_a.sgl.pago_proveedores.ver_requerimiento_finanzas');
    });

    test('3. normaliza ruta sin extension', function () use ($normalizer) {
        expect($normalizer->normalize('mod_gene/mod_tram_inter/correo-interno'))
            ->toBe('mod_gene.mod_tram_inter.correo-interno');
    });

    test('4. normaliza ruta con slash inicial y final', function () use ($normalizer) {
        expect($normalizer->normalize('/mod_gene/mod_tram_inter/buscar-tramite/'))
            ->toBe('mod_gene.mod_tram_inter.buscar-tramite');
    });

    test('5. normaliza separadores Windows backslash', function () use ($normalizer) {
        expect($normalizer->normalize('mod_gm\mod_tram_exte\segi_archivo.blade.php'))
            ->toBe('mod_gm.mod_tram_exte.segi_archivo');
    });

    test('6. retira query string de forma segura', function () use ($normalizer) {
        expect($normalizer->normalize('/mod_gm/mod_tram_exte/segi_expedi.blade.php?tab=pendientes&page=2'))
            ->toBe('mod_gm.mod_tram_exte.segi_expedi');
    });

    test('7. rechaza path traversal con dos puntos', function () use ($normalizer) {
        expect($normalizer->normalize('../mod_gm/mod_tram_exte/segi_expedi'))
            ->toBe('');
        expect($normalizer->normalize('/mod_gm/../../etc/passwd'))
            ->toBe('');
        expect($normalizer->normalize('..\\mod_gm\\segi_expedi'))
            ->toBe('');
    });

    test('8. preserva guiones y guiones bajos sin alterar palabras', function () use ($normalizer) {
        expect($normalizer->normalize('/mod_gene/mod_tram_inter/v2_lista_reporte_area_cargo.blade.php'))
            ->toBe('mod_gene.mod_tram_inter.v2_lista_reporte_area_cargo');
    });

    test('9. maneja multiples slashes consecutivos y espacios', function () use ($normalizer) {
        expect($normalizer->normalize('   //mod_ti///helpdesk//index.blade.php   '))
            ->toBe('mod_ti.helpdesk.index');
    });

    test('10. toUrlPath genera ruta con slash inicial limpia', function () use ($normalizer) {
        expect($normalizer->toUrlPath('/mod_gm/mod_tram_exte/segi_expedi.blade.php'))
            ->toBe('/mod_gm/mod_tram_exte/segi_expedi');
    });

    test('11. routeVariants genera las variantes exactas para a_rles', function () use ($normalizer) {
        $variants = $normalizer->routeVariants('/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        expect($variants)->toContain('/mod_gm/mod_tram_exte/segi_expedi')
            ->toContain('/mod_gm/mod_tram_exte/segi_expedi.blade.php')
            ->toContain('/mod_gm/mod_tram_exte/segi_expedi.php')
            ->toContain('mod_gm/mod_tram_exte/segi_expedi')
            ->toContain('mod_gm/mod_tram_exte/segi_expedi.blade.php')
            ->toContain('mod_gm/mod_tram_exte/segi_expedi.php');
    });

    test('12. normaliza clave canonica con puntos y es idempotente', function () use ($normalizer) {
        $canonical = $normalizer->normalize('/mod_gm/mod_tram_exte/segi_expedi.blade.php');
        expect($canonical)->toBe('mod_gm.mod_tram_exte.segi_expedi');
        expect($normalizer->normalize($canonical))->toBe($canonical);
        expect($normalizer->toUrlPath($canonical))->toBe('/mod_gm/mod_tram_exte/segi_expedi');
    });

    test('13. toUrlPath acepta tanto ruta con slash como clave canonica con puntos', function () use ($normalizer) {
        expect($normalizer->toUrlPath('/mod_gm/mod_tram_exte/segi_expedi.blade.php'))
            ->toBe('/mod_gm/mod_tram_exte/segi_expedi');
        expect($normalizer->toUrlPath('mod_gm.mod_tram_exte.segi_expedi'))
            ->toBe('/mod_gm/mod_tram_exte/segi_expedi');
        expect($normalizer->toUrlPath('mod_gene.mod_tram_inter.correo-interno'))
            ->toBe('/mod_gene/mod_tram_inter/correo-interno');
    });

    test('14. rechaza URLs externas y esquemas no permitidos', function () use ($normalizer) {
        expect($normalizer->normalize('http://malicious.com/mod_gm/segi_expedi'))->toBe('');
        expect($normalizer->normalize('https://evil.org/mod_gm/segi_expedi.blade.php'))->toBe('');
        expect($normalizer->normalize('javascript:alert(1)'))->toBe('');
        expect($normalizer->normalize('data:text/html;base64,PHNjcmlwdD4='))->toBe('');
    });

    test('15. maneja entradas vacias o nulas', function () use ($normalizer) {
        expect($normalizer->normalize(''))->toBe('');
        expect($normalizer->normalize('   '))->toBe('');
        expect($normalizer->normalize(null))->toBe('');
        expect($normalizer->toUrlPath(''))->toBe('');
        expect($normalizer->toUrlPath(null))->toBe('');
    });

    test('16. rechaza rutas con caracteres invalidos o segmentos vacios', function () use ($normalizer) {
        expect($normalizer->normalize('/mod_gm/<script>/segi_expedi'))->toBe('');
        expect($normalizer->normalize('/mod_gm/segi expedi'))->toBe('');
        expect($normalizer->normalize('/mod_gm/segi;expedi'))->toBe('');
        expect($normalizer->normalize('.mod_gm.segi_expedi'))->toBe('');
        expect($normalizer->normalize('mod_gm.segi_expedi.'))->toBe('');
        expect($normalizer->normalize('mod_gm..segi_expedi'))->toBe('');
        expect($normalizer->normalize('/mod_gm.mod_tram_exte/segi_expedi'))->toBe('');
    });
});
