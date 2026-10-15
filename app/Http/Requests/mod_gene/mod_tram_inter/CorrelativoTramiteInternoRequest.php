<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class CorrelativoTramiteInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cdgo_tpo_trmte') && !$this->filled('id_tipo_documento')) {
            $this->merge(['id_tipo_documento' => $this->input('cdgo_tpo_trmte')]);
        }
        if ($this->filled('id_tipo_documento') && !$this->filled('cdgo_tpo_trmte')) {
            $this->merge(['cdgo_tpo_trmte' => $this->input('id_tipo_documento')]);
        }
    }

    public function rules(): array
    {
        return [
            'id_tipo_documento' => ['required_without:cdgo_tpo_trmte', 'integer', 'min:1'],
            'cdgo_tpo_trmte' => ['nullable', 'integer', 'min:1'],
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_tipo_documento.required' => 'El tipo de documento es obligatorio para consultar el correlativo.',
            'id_tipo_documento.min' => 'El tipo de documento no es válido.',
        ];
    }
}