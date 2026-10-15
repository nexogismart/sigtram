<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;


use App\Contracts\Services\mod_gm\ExcelWriterFactoryInterface;
use App\Contracts\Services\mod_gm\ExcelWriterInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Enums\mod_gm\EstadoTramiteExterno;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use OpenSpout\Common\Entity\Row;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Servicio especializado para la exportación de expedientes de Trámite Externo a Excel (XLSX).
 *
 * Características:
 * - Paridad absoluta con la bandeja canónica reutilizando construirQueryBandejaBase().
 * - Generación de XLSX real mediante OpenSpout (escritura progresiva por cursor sin sobrecargar memoria).
 * - Aislamiento estricto por compañía activa sin fallbacks.
 * - Ciclo de vida determinista del recurso temporal y writer con try/catch/finally blindado.
 * - Neutralización estricta de Excel Formula Injection (=, +, -, @, \t, \r).
 * - Preservación de formato texto con ceros iniciales en DNI, RUC y Expedientes.
 */
class ExportarTramiteExternoExcelService
{
    private readonly TramiteExternoRepositoryInterface $repository;

    public function __construct(
        TramiteExternoRepositoryInterface|DatabaseManager|null $repositoryOrDb = null,
        private readonly ?ExcelWriterFactoryInterface $writerFactory = null
    ) {
        if ($repositoryOrDb instanceof TramiteExternoRepositoryInterface) {
            $this->repository = $repositoryOrDb;
        } elseif ($repositoryOrDb instanceof DatabaseManager) {
            $this->repository = new TramiteExternoRepository($repositoryOrDb);
        } else {
            $this->repository = app(TramiteExternoRepositoryInterface::class);
        }
    }

    /**
     * Genera el archivo Excel XLSX y retorna la respuesta de descarga binaria.
     * Garantiza el cierre del writer y la eliminación de archivos temporales en cualquier escenario de error.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<string, mixed> $filtros
     * @return BinaryFileResponse
     * @throws Throwable
     */
    public function exportar(ContextoUsuarioAutenticadoDto $contexto, array $filtros): BinaryFileResponse
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sigtram_ext_exp_');
        if ($tempFile === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal para la exportación.');
        }

        $writer = null;
        $writerAbierto = false;
        $operacionExitosa = false;

        try {
            $factory = $this->writerFactory ?? new class implements ExcelWriterFactoryInterface {
                public function create(): ExcelWriterInterface
                {

                    return new OpenSpoutExcelWriter();
                }
            };

            $writer = $factory->create();
            $writer->openToFile($tempFile);
            $writerAbierto = true;

            // 1. Cabeceras de columnas del libro
            $headers = [
                'Nº',
                'ID Trámite',
                'Nº Expediente',
                'Tipo Documento',
                'Nº Folios',
                'Fecha Ingreso',
                'DNI / RUC',
                'Administrado / Razón Social',
                'Teléfono',
                'Correo Electrónico',
                'Dirección',
                'Observación del Trámite',
                'Área Actual',
                'Cargo Actual',
                'Estado Actual',
                'Condición',
                'Fecha Último Movimiento',
                'Observación Último Movimiento',
            ];
            $writer->addRow(Row::fromValues($headers));

            // 2. Construcción de consulta canónica compartida con la bandeja
            $query = $this->construirQuery($contexto, $filtros);

            // 3. Recorrido streaming mediante cursor para mínimo consumo de memoria
            $numero = 1;
            foreach ($query->cursor() as $item) {
                $condicionTexto = match ((string) ($item->in_esta_ultimo_movimiento ?? $item->in_esta_movimiento ?? $item->in_esta ?? '')) {
                    '1' => 'ACTIVO',
                    '0' => 'DESACTIVADO/INACTIVO',
                    default => 'SIN MOVIMIENTO',
                };

                $estadoTexto = (string) ($item->dc_estdo_trmte ?? $item->de_estdo_trmte ?? '—');
                if ($estadoTexto === '—' && isset($item->cdgo_estdo_trmte)) {
                    $estadoEnum = EstadoTramiteExterno::tryFrom((int) $item->cdgo_estdo_trmte);
                    $estadoTexto = $estadoEnum?->label() ?? 'DESCONOCIDO';
                }

                $fechaIngreso = !empty($item->fe_ingr_trmte)
                    ? date('d/m/Y H:i', strtotime((string) $item->fe_ingr_trmte))
                    : '—';

                $fechaUltMov = !empty($item->fe_rcbdo_trmte)
                    ? date('d/m/Y H:i', strtotime((string) $item->fe_rcbdo_trmte))
                    : '—';

                $fila = [
                    $numero++,
                    (int) $item->cdgo_dto_trmte,
                    self::sanitizarFormula((string) ($item->nu_expe_todo ?? $item->cdgo_dto_trmte)),
                    self::sanitizarFormula((string) ($item->de_docmnto_trmte ?? '—')),
                    (int) ($item->nu_foli_trmte ?? 0),
                    $fechaIngreso,
                    self::sanitizarFormula((string) ($item->dni_ruc ?? '—')),
                    self::sanitizarFormula((string) ($item->persona_razon_social ?? '—')),
                    self::sanitizarFormula((string) ($item->nu_tele ?? '—')),
                    self::sanitizarFormula((string) ($item->de_mail ?? '—')),
                    self::sanitizarFormula((string) ($item->de_dire ?? '—')),
                    self::sanitizarFormula((string) ($item->observacion_tramite ?? '—')),
                    self::sanitizarFormula((string) ($item->area_actual ?? '—')),
                    self::sanitizarFormula((string) ($item->cargo_actual ?? '—')),
                    self::sanitizarFormula($estadoTexto),
                    self::sanitizarFormula($condicionTexto),
                    $fechaUltMov,
                    self::sanitizarFormula((string) ($item->observacion_ultimo_movimiento ?? $item->observacion_movimiento ?? '—')),
                ];

                $writer->addRow(Row::fromValues($fila));
            }

            // 4. Cerrar writer ordenadamente (marcar cerrado antes para asegurar máximo 1 llamada)
            $writerAbierto = false;
            $writer->close();

            // 5. Preparar BinaryFileResponse delegando eliminación post-envío
            $nombreDescarga = 'reporte_expedientes_' . date('Ymd_His') . '.xlsx';
            $response = $this->crearBinaryFileResponse($tempFile, $nombreDescarga);

            $operacionExitosa = true;

            return $response;
        } catch (Throwable $e) {
            // Cierre seguro del descriptor si quedó abierto
            if ($writerAbierto && $writer !== null) {
                try {
                    $writer->close();
                } catch (Throwable) {
                    // Silenciar excepción secundaria para no ocultar la causa raíz
                } finally {
                    $writerAbierto = false;
                }
            }

            // Limpieza inmediata del archivo huérfano ante fallo
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            throw $e;
        } finally {
            // Salvaguarda final ante cualquier salida abrupta no exitosa
            if (!$operacionExitosa) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        }
    }

    /**
     * Construye la respuesta de archivo binario para la descarga del XLSX generado.
     * Delega la eliminación del archivo físico tras la transmisión HTTP.
     *
     * @param string $tempFile
     * @param string $nombreDescarga
     * @return BinaryFileResponse
     */
    protected function crearBinaryFileResponse(string $tempFile, string $nombreDescarga): BinaryFileResponse
    {
        return response()->download($tempFile, $nombreDescarga, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Construye la consulta unificada de exportación a partir de la fuente única de verdad del repositorio.
     * Selecciona las 18 columnas requeridas manteniendo el orden y filtros de la bandeja canónica.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<string, mixed> $filtros
     * @return Builder
     */
    public function construirQuery(ContextoUsuarioAutenticadoDto $contexto, array $filtros): Builder
    {
        $bandeja = (string) ($filtros['bandeja'] ?? 'todos');
        $query = $this->repository->construirQueryBandejaBase($contexto, $bandeja, $filtros);

        $query->select([
            'r.cdgo_trmte_dcmnto',
            'r.cdgo_dto_trmte',
            'p.nu_expe_todo',
            'p.fe_ingr_trmte',
            'p.de_obse_todo as observacion_tramite',
            'p.nu_docu_trmte as numero_documento',
            'p.nu_foli_trmte',
            'dt.de_docmnto_trmte',
            'r.in_esta as in_esta_ultimo_movimiento',
            'r.cdgo_estdo_trmte',
            'e.dc_estdo_trmte',
            'e.de_estdo_trmte',
            'a.de_area as area_actual',
            'c.de_crgo as cargo_actual',
            'r.fe_rcbdo_trmte',
            'r.de_obse as observacion_ultimo_movimiento',
            'u.no_crto as persona_razon_social',
            'u.nu_docu as dni_ruc',
            'u.nu_tele',
            'u.de_mail',
            'u.de_dire',
        ]);

        return $query;
    }

    /**
     * Neutraliza Excel Formula Injection prepended con comilla simple si el valor
     * comienza con '=', '+', '-', '@', o caracteres de control '\t' (tab) o '\r' (CR).
     * Preserva textos regulares, ceros iniciales en DNI/RUC y caracteres especiales en español.
     *
     * @param string|null $valor
     * @return string
     */
    public static function sanitizarFormula(?string $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if ($valor === '') {
            return '';
        }

        // Si comienza directamente con tabulación o retorno de carro
        if ($valor[0] === "\t" || $valor[0] === "\r") {
            return "'" . $valor;
        }

        // Si después de espacios comienza con fórmula o carácter de control
        $trimmed = ltrim($valor);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $valor;
        }

        return $valor;
    }
}
