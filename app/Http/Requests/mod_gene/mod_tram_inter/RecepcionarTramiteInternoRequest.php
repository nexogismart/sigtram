<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class RecepcionarTramiteInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('observaciones') && !$this->has('observacion')) {
            $this->merge(['observacion' => $this->input('observaciones')]);
        }
        if ($this->has('observacion') && !$this->has('observaciones')) {
            $this->merge(['observaciones' => $this->input('observacion')]);
        }
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos un trámite.',
            'ids.min' => 'Debe seleccionar al menos un trámite.',
            'ids.max' => 'No puede procesar más de 100 trámites en un solo lote.',
            'ids.*.distinct' => 'El lote contiene trámites duplicados.',
            'ids.*.integer' => 'El identificador del trámite debe ser un número entero.',
            'ids.*.min' => 'El identificador del trámite debe ser un valor positivo.',
        ];
    }
}