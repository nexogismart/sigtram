<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

class AgregarAnexoExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Cha' => ['required', 'integer', 'min:1'],
            'hdn_tipo' => ['required', 'integer', 'in:1,2'],
            'TA1' => ['required', 'string'],
            'txt_nu_foli_anex' => ['required', 'integer', 'min:0'],
            'fle_adjunto' => ['nullable', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'TA1.required' => 'Debe especificar las observaciones.',
            'txt_nu_foli_anex.required' => 'Debe especificar la cantidad de folios.',
        ];
    }
}
