<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class ReiterarTramiteInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('observaciones') && !$this->filled('observacion')) {
            $this->merge(['observacion' => $this->input('observaciones')]);
        }
        if ($this->filled('observacion') && !$this->filled('observaciones')) {
            $this->merge(['observaciones' => $this->input('observacion')]);
        }
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'fecha' => ['nullable', 'string'],
            'fle_adjunto1' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,zip', 'max:20480'],
            'fle_adjunto2' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,zip', 'max:20480'],
            'fle_adjunto3' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,zip', 'max:20480'],
            'fle_adjunto4' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,zip', 'max:20480'],
            'archivos' => ['nullable', 'array', 'max:4'],
            'archivos.*' => ['file', 'mimes:pdf,docx,xlsx,zip', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos un trámite.',
            'ids.max' => 'No puede reiterar más de 100 trámites en un lote.',
            'ids.*.distinct' => 'El lote contiene trámites duplicados.',
            'archivos.max' => 'No se permite adjuntar más de 4 archivos.',
            'archivos.*.mimes' => 'Solo se permiten archivos en formato PDF, DOCX, XLSX o ZIP.',
            'archivos.*.max' => 'Cada archivo adjunto no debe exceder 20 MiB.',
        ];
    }
}