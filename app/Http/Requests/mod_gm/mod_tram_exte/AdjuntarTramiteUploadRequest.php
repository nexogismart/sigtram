<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

class AdjuntarTramiteUploadRequest extends FormRequest
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
            'documento' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,zip', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documento.required' => 'Debe seleccionar un archivo para adjuntar.',
            'documento.file'     => 'El archivo adjunto no es válido.',
            'documento.mimes'    => 'El archivo debe tener formato PDF, JPG, PNG o ZIP.',
            'documento.max'      => 'El tamaño máximo permitido para el archivo es 10MB.',
        ];
    }
}
