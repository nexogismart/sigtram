<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use App\Http\Requests\mod_gm\mod_tram_exte\Concerns\NormalizaFechasRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class PrestarExpedienteRequest extends FormRequest
{
    use NormalizaFechasRequestTrait;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarCamposFecha(['fecha_salida', 'fecha_retorno']);

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
            'prestatario'   => ['required', 'string', 'min:3', 'max:255'],
            'finalidad'     => ['required', 'string', 'min:3', 'max:500'],
            'fecha_salida'  => ['required', 'date'],
            'fecha_retorno' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movimiento_id.required_without' => 'Debe especificar el movimiento a prestar.',
            'expediente_id.required_without' => 'Debe especificar el movimiento a prestar.',
            'prestatario.required'           => 'Debe ingresar el nombre del prestatario.',
            'prestatario.min'                => 'El prestatario debe tener al menos :min caracteres.',
            'finalidad.required'             => 'Debe ingresar la finalidad del préstamo.',
            'finalidad.min'                  => 'La finalidad debe tener al menos :min caracteres.',
            'fecha_salida.required'          => 'La fecha de salida es obligatoria.',
        ];
    }
}
