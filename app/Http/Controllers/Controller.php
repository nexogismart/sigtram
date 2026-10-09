<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Blade del menú: solo a_rles.de_dreccon apuntando a un .blade.php.
     */
    protected function vistaDesdeMenu(?string $pathVista, ?object $opcionMenu = null): string
    {
        if (! is_string($pathVista) || $pathVista === '') {
            $pathVista = $this->vistaDesdeOpcion($opcionMenu);
        }

        if ($pathVista === '' || ! $this->existeVistaBlade($pathVista)) {
            abort(404, 'El archivo de vista no existe.');
        }

        return $pathVista;
    }

    protected function vistaDesdeOpcion(?object $opcionMenu): string
    {
        if ($opcionMenu === null || ! isset($opcionMenu->de_dreccon)) {
            return '';
        }

        return $this->nombreVistaBlade((string) $opcionMenu->de_dreccon);
    }

    /**
     * Convierte de_dreccon en nombre de vista Laravel.
     * Solo acepta .blade.php (o sin extensión). Un .php legado no se abre.
     */
    protected function nombreVistaBlade(string $deDreccon): string
    {
        $ruta = trim($deDreccon);
        if ($ruta === '') {
            return '';
        }

        if (preg_match('/\.php$/i', $ruta) && ! preg_match('/\.blade\.php$/i', $ruta)) {
            return '';
        }

        $ruta = preg_replace('/\.blade\.php$/i', '', $ruta) ?? $ruta;

        return str_replace('/', '.', trim($ruta, '/'));
    }

    protected function existeVistaBlade(string $pathVista): bool
    {
        if ($pathVista === '') {
            return false;
        }

        $archivo = resource_path('views/' . str_replace('.', '/', $pathVista) . '.blade.php');

        return is_file($archivo);
    }
}
