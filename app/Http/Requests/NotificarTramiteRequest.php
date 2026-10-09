<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificarTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'documento' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Debe ingresar la fecha y hora de la notificación.',
            'fecha.date' => 'La fecha ingresada no es válida.',
            'documento.required' => 'Debe adjuntar el cargo firmado de la notificación.',
            'documento.file' => 'El adjunto debe ser un archivo válido.',
            'documento.mimes' => 'Solo se permiten formatos PDF, JPG, JPEG o PNG.',
            'documento.max' => 'El tamaño máximo del archivo es de 10 MB.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
        ];
    }
}
