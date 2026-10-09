<?php

namespace App\Http\Requests\Core;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarPideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'documento' => ['required', 'string', 'regex:/^\d{8}$|^\d{11}$/'],
            'tipo'      => ['required', 'string', 'in:DNI,RUC'],
            'forzar'    => ['nullable', 'boolean']
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documento.required' => 'El número de documento es obligatorio.',
            'documento.regex'    => 'El DNI debe tener 8 dígitos y el RUC debe tener 11 dígitos.',
            'tipo.in'            => 'El tipo de documento debe ser DNI o RUC.'
        ];
    }
}
