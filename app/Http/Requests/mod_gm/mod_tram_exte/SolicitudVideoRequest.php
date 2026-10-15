<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para solicitar lista de videos asociados a un expediente.
 * Resuelve CE-04.
 */
class SolicitudVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El identificador del expediente es obligatorio.',
            'id.integer'  => 'El identificador del expediente debe ser un número entero.',
            'id.min'      => 'El identificador del expediente debe ser mayor a cero.',
        ];
    }
}
