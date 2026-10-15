<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para solicitar número correlativo semilla para trámite interno asociado.
 * Resuelve CE-04.
 */
class ConsultarSemillaRequest extends FormRequest
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
            'doc_tipo' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doc_tipo.required' => 'El tipo de documento es obligatorio.',
            'doc_tipo.integer'  => 'El tipo de documento debe ser un entero.',
            'doc_tipo.min'      => 'El tipo de documento debe ser mayor a cero.',
        ];
    }
}
