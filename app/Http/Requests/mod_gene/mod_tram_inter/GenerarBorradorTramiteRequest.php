<?php
declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class GenerarBorradorTramiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sel_cdgo_tpo_trmte'  => ['required', 'integer'],
            'txt_nu_smlla_intrno' => ['required', 'string'],
            'sel_areas'           => ['required', 'array', 'min:1'],
            'sel_areas.*'         => ['integer'],
            'sel_crgos'           => ['nullable', 'integer'],
            'txt_fe_rcbdo'        => ['required', 'date'],
            'txt_de_obser'        => ['nullable', 'string'], // Asunto
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'sel_areas.required' => 'Debe seleccionar al menos una gerencia o área de destino.',
        ];
    }
}
