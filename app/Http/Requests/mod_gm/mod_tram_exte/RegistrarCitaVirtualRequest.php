<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarCitaVirtualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_tram' => ['required', 'integer', 'min:1'],
            'txt_fecha' => ['required', 'string'],
            'txt_hora' => ['required', 'string'],
            'TA1' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'txt_fecha.required' => 'Debe indicar fecha y hora de la cita.',
            'txt_hora.required' => 'Debe indicar fecha y hora de la cita.',
        ];
    }
}
