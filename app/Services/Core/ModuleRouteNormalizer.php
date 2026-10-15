<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * Class ModuleRouteNormalizer
 *
 * Servicio con responsabilidad única: convertir una dirección de módulo
 * en una clave canónica normalizada con notación de punto.
 */
class ModuleRouteNormalizer
{
    /**
     * Convierte una dirección de módulo en una clave canónica con notación de punto.
     * Ejemplo: "/mod_gm/mod_tram_exte/segi_expedi.blade.php?tab=1" -> "mod_gm.mod_tram_exte.segi_expedi"
     *
     * @param string|null $ruta Dirección de módulo o URL relativa.
     * @return string Clave canónica en minúsculas/preservando guiones, o cadena vacía si es inválida.
     */
    public function normalize(?string $ruta): string
    {
        if ($ruta === null) {
            return '';
        }

        $trimmed = trim($ruta);
        if ($trimmed === '') {
            return '';
        }

        // 1. Rechazar esquemas y URLs externas con esquema (e.g. http://, https://, ftp://, javascript:)
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $trimmed)) {
            return '';
        }

        // 2. Separar de forma segura path y query string
        $pathOnly = explode('?', $trimmed, 2)[0];
        $pathOnly = trim($pathOnly);
        if ($pathOnly === '') {
            return '';
        }

        // 3. Rechazar path traversal
        if (str_contains($pathOnly, '..')) {
            return '';
        }

        // 4. Normalizar separadores de directorio \ -> /
        $normalizedSeparators = str_replace('\\', '/', $pathOnly);

        // 5. Retirar slashes iniciales y finales
        $stripped = trim($normalizedSeparators, '/');
        if ($stripped === '') {
            return '';
        }

        // Colapsar slashes múltiples
        $collapsed = preg_replace('#/+#', '/', $stripped) ?? $stripped;

        // 6. Retirar extensiones .blade.php y .php (case-insensitive) al final
        if (preg_match('/\.blade\.php$/i', $collapsed)) {
            $collapsed = preg_replace('/\.blade\.php$/i', '', $collapsed) ?? $collapsed;
        } elseif (preg_match('/\.php$/i', $collapsed)) {
            $collapsed = preg_replace('/\.php$/i', '', $collapsed) ?? $collapsed;
        }

        $collapsed = trim($collapsed, '/');
        if ($collapsed === '') {
            return '';
        }

        // 7. Determinar separador de segmentos:
        // No permitir mezcla ambigua de slashes y dots en la ruta sin extensión
        if (str_contains($collapsed, '/') && str_contains($collapsed, '.')) {
            return '';
        }

        if (str_contains($collapsed, '/')) {
            $segments = explode('/', $collapsed);
        } elseif (str_contains($collapsed, '.')) {
            $segments = explode('.', $collapsed);
        } else {
            $segments = [$collapsed];
        }

        // 8. Validar cada segmento individual: solo alfanuméricos (incluyendo letras unicode como ñ), guiones y guiones bajos
        foreach ($segments as $segment) {
            $segTrim = trim($segment);
            if ($segTrim === '' || !preg_match('/^[\p{L}0-9_\-]+$/u', $segTrim)) {
                return '';
            }
        }

        // 9. Unir deterministamente en notación de punto
        return implode('.', $segments);
    }

    /**
     * Convierte una ruta o clave canónica en path de URL estándar con slash inicial.
     * Ejemplo: "mod_gm.mod_tram_exte.segi_expedi" -> "/mod_gm/mod_tram_exte/segi_expedi"
     *
     * @param string|null $ruta
     * @return string
     */
    public function toUrlPath(?string $ruta): string
    {
        $canonical = $this->normalize($ruta);
        if ($canonical === '') {
            return '';
        }

        return '/' . str_replace('.', '/', $canonical);
    }

    /**
     * Retorna las variantes de de_dreccon aceptadas en base de datos para la misma opción de menú.
     *
     * @param string|null $ruta
     * @return list<string>
     */
    public function routeVariants(?string $ruta): array
    {
        $canonical = $this->normalize($ruta);
        if ($canonical === '') {
            return [];
        }

        $slashPath = str_replace('.', '/', $canonical);
        $baseWithSlash = '/' . $slashPath;

        return array_values(array_unique([
            $baseWithSlash,
            $baseWithSlash . '.blade.php',
            $baseWithSlash . '.php',
            $slashPath,
            $slashPath . '.blade.php',
            $slashPath . '.php',
        ]));
    }
}
