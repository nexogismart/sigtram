<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class ListarHistoricoInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Diferenciación canónica estricta entre documento_id, movimiento_id e id legacy

    public function rules(): array
    {
        return [
            'documento_id'  => ['required_without:movimiento_id', 'integer', 'min:1'],
            'movimiento_id' => ['required_without:documento_id', 'integer', 'min:1'],
            'id_movimiento' => ['prohibited'],
            'id'            => ['prohibited'],
            'page'          => ['nullable', 'integer', 'min:1'],
            'per_page'      => ['nullable', 'integer', 'in:10,15,20,25,50,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.prohibited'            => 'El parámetro ambiguo id está prohibido. Utilice documento_id o movimiento_id.',
            'id_movimiento.prohibited' => 'El parámetro ambiguo id_movimiento está prohibido. Utilice movimiento_id.',
            'documento_id.required_without' => 'Debe proporcionar documento_id o movimiento_id.',
            'movimiento_id.required_without' => 'Debe proporcionar movimiento_id o documento_id.',
        ];
    }
}