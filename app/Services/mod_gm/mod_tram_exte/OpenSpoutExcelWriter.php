<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;

use App\Contracts\Services\mod_gm\ExcelWriterInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Adaptador de OpenSpout para la generación progresiva de archivos XLSX reales.
 */
class OpenSpoutExcelWriter implements ExcelWriterInterface
{
    private Writer $writer;

    public function __construct(?Writer $writer = null)
    {
        $this->writer = $writer ?? new Writer();
    }

    public function openToFile(string $filePath): void
    {
        $this->writer->openToFile($filePath);
    }

    public function addRow(Row $row): void
    {
        $this->writer->addRow($row);
    }

    public function close(): void
    {
        $this->writer->close();
    }
}
