<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class ReemplazarFirmadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'El identificador del documento es obligatorio.',
            'id.integer' => 'El identificador del documento debe ser un entero.',
            'id.min' => 'El identificador del documento debe ser un valor positivo.',
            'archivo.required' => 'Debe adjuntar un archivo PDF para el reemplazo.',
            'archivo.file' => 'El archivo proporcionado no es válido.',
            'archivo.mimes' => 'El archivo debe ser un documento en formato PDF.',
            'archivo.max' => 'El archivo no puede exceder el tamaño máximo de 20 MiB.',
        ];
    }
}