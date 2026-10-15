<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class DescargarLoteTramiteInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('ids'))) {
            $parts = array_filter(array_map('trim', explode(',', (string) $this->input('ids'))));
            $this->merge(['ids' => array_map('intval', $parts)]);
        }
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'incluir_unido' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos un expediente.',
            'ids.max' => 'No puede descargar más de 100 expedientes simultáneamente.',
            'ids.*.distinct' => 'La lista de descarga contiene identificadores duplicados.',
        ];
    }
}