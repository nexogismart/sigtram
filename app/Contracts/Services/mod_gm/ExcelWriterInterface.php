<?php

declare(strict_types=1);

namespace App\Contracts\Services\mod_gm;

use OpenSpout\Common\Entity\Row;

/**
 * Contrato de abstracción para el escritor de Excel (XLSX).
 * Permite desacoplar el ciclo de vida del writer para pruebas unitarias exhaustivas
 * y manejo determinista de recursos temporales.
 */
interface ExcelWriterInterface
{
    /**
     * Abre el archivo de destino para escritura progresiva.
     *
     * @param string $filePath
     * @return void
     */
    public function openToFile(string $filePath): void;

    /**
     * Escribe una fila en el libro.
     *
     * @param Row $row
     * @return void
     */
    public function addRow(Row $row): void;

    /**
     * Cierra el escritor y finaliza el archivo XLSX.
     *
     * @return void
     */
    public function close(): void;
}
