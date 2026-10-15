<?php

declare(strict_types=1);

namespace App\Contracts\Services\mod_gm;

/**
 * Fábrica para la creación de instancias de ExcelWriterInterface.
 */
interface ExcelWriterFactoryInterface
{
    /**
     * Crea una nueva instancia de escritor Excel.
     *
     * @return ExcelWriterInterface
     */
    public function create(): ExcelWriterInterface;
}
