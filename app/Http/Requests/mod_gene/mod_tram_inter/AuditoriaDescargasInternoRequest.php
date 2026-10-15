<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class AuditoriaDescargasInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movimiento_id' => ['required', 'integer', 'min:1'],
            'id'            => ['prohibited'],
            'page'          => ['nullable', 'integer', 'min:1'],
            'per_page'      => ['nullable', 'integer', 'in:10,25,50'],
        ];
    }

    public function messages(): array
    {
        return [
            'movimiento_id.required'   => 'El identificador de movimiento es obligatorio.',
            'id.prohibited'            => 'El parámetro ambiguo id está prohibido. Utilice movimiento_id.',
        ];
    }
}
