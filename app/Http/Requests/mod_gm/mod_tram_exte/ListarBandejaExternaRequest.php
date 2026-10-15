<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Http\Requests\mod_gm\mod_tram_exte\Concerns\NormalizaFechasRequestTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest para listar expedientes de bandeja de Trámite Externo.
 * Resuelve CE-P1-001 y CE-04.
 */
class ListarBandejaExternaRequest extends FormRequest
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
     * Bandejas públicas permitidas en Correo Externo (paridad menú legacy).
     */
    public const BANDEJAS_PERMITIDAS = [
        'todos',
        'entrada',
        'recibidos',
        'salida',
        'finalizados',
        'finalizados_notificados',
        'archivo',
        'sin_finalizar',
    ];

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'bandeja'     => ['required', 'string', Rule::in(self::BANDEJAS_PERMITIDAS)],
            'criterio'    => ['nullable', 'string', Rule::in(['1', '2', '3', 'expe_todo', 'expe_varios', 'solicitante', 'observaciones'])],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'desde'       => ['nullable', 'date'],
            'hasta'       => ['nullable', 'date', 'after_or_equal:desde'],
            'page'        => ['nullable', 'integer', 'min:1'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:500'],
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
