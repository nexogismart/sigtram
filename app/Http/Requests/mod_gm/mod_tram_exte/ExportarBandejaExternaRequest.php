<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Http\Requests\mod_gm\mod_tram_exte\Concerns\NormalizaFechasRequestTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest específico para la exportación de expedientes de Trámite Externo a Excel.
 * Valida estrictamente los filtros permitidos y previene la inyección de parámetros no autorizados.
 */
class ExportarBandejaExternaRequest extends FormRequest
{
    use NormalizaFechasRequestTrait;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarCamposFecha(['desde', 'hasta']);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'bandeja'     => ['required', 'string', Rule::in(array_column(BandejaTramiteExterno::cases(), 'value'))],
            'criterio'    => ['nullable', 'string', Rule::in(['1', '2', '3', 'expe_todo', 'expe_varios', 'solicitante', 'observaciones'])],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'desde'       => ['nullable', 'date'],
            'hasta'       => ['nullable', 'date', 'after_or_equal:desde'],
            'estado'      => ['nullable', 'string', 'max:20'],
            'area_id'     => ['nullable', 'integer', 'min:1'],
            'condicion'   => ['nullable', 'string', 'max:50'],
            'expediente'  => ['nullable', 'string', 'max:50'],
            'persona'     => ['nullable', 'string', 'max:255'],
            'dni_ruc'     => ['nullable', 'string', 'max:20'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'folios'      => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bandeja.required'     => 'La bandeja solicitada es obligatoria.',
            'bandeja.in'           => 'La bandeja especificada no es válida.',
            'criterio.in'          => 'El criterio de búsqueda especificado no es válido.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
        ];
    }
}
