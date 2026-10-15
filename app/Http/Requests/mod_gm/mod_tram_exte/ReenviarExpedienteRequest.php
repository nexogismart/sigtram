<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

class ReenviarExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('movimiento_ids') && $this->has('ids')) {
            $this->merge(['movimiento_ids' => (array) $this->input('ids')]);
        }
        if ($this->has('movimiento_ids')) {
            $cleaned = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('movimiento_ids')), fn($v) => $v > 0)));
            $this->merge(['movimiento_ids' => $cleaned]);
        }
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('movimiento_ids') && $this->has('ids')) {
                $rawMov = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('movimiento_ids')), fn($v) => $v > 0)));
                $rawIds = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('ids')), fn($v) => $v > 0)));
                sort($rawMov);
                sort($rawIds);
                if ($rawMov !== $rawIds) {
                    $validator->errors()->add('movimiento_ids', 'Conflicto de identificadores: movimiento_ids e ids no coinciden.');
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
            'movimiento_ids'   => ['required_without:ids', 'nullable', 'array', 'min:1'],
            'movimiento_ids.*' => ['integer', 'min:1'],
            'ids'              => ['required_without:movimiento_ids', 'nullable', 'array', 'min:1'],
            'ids.*'            => ['integer', 'min:1'],
            'cdgo_gerencia'    => ['required', 'integer', 'min:1'],
            'cdgo_subgerencia' => ['nullable', 'integer'],
            'usar_subgerencia' => ['nullable'],
            'observacion'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movimiento_ids.required_without' => 'Debe seleccionar al menos un Documento',
            'movimiento_ids.min'              => 'Debe seleccionar al menos un Documento',
            'ids.required_without'            => 'Debe seleccionar al menos un Documento',
            'ids.min'                         => 'Debe seleccionar al menos un Documento',
            'cdgo_gerencia.required'          => 'Debe especificar la Gerencia',
            'cdgo_gerencia.min'               => 'Debe especificar una Gerencia válida',
        ];
    }
}
