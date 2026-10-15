<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para retornar un expediente prestado al archivo central.
 */
class RetornarPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $id = $this->input('movimiento_id') ?? $this->input('expediente_id') ?? $this->input('id');
        if ($id !== null && is_numeric($id) && (int) $id > 0) {
            $this->merge([
                'movimiento_id' => (int) $id,
            ]);
        }
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('movimiento_id') && ($this->has('expediente_id') || $this->has('id'))) {
                $rawMov = (int) $this->input('movimiento_id');
                $rawExp = (int) ($this->input('expediente_id') ?? $this->input('id'));
                if ($rawMov !== $rawExp) {
                    $validator->errors()->add('movimiento_id', 'Conflicto de identificadores: movimiento_id y expediente_id no coinciden.');
                }
            }
        });
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'movimiento_id' => ['required_without:expediente_id', 'nullable', 'integer', 'min:1'],
            'expediente_id' => ['required_without:movimiento_id', 'nullable', 'integer', 'min:1'],
            'id'            => ['nullable', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movimiento_id.required_without' => 'El identificador del movimiento es obligatorio.',
            'expediente_id.required_without' => 'El identificador del movimiento es obligatorio.',
            'movimiento_id.integer'          => 'El identificador del movimiento debe ser un número entero.',
            'expediente_id.integer'          => 'El identificador del movimiento debe ser un número entero.',
            'movimiento_id.min'              => 'El identificador del movimiento debe ser mayor a cero.',
            'expediente_id.min'              => 'El identificador del movimiento debe ser mayor a cero.',
        ];
    }
}

