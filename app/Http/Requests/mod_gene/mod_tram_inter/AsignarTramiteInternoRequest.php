<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class AsignarTramiteInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cdgo_crgo_en') && !$this->filled('id_cargo_destino')) {
            $this->merge(['id_cargo_destino' => $this->input('cdgo_crgo_en')]);
        }
        if ($this->filled('id_cargo_destino') && !$this->filled('cdgo_crgo_en')) {
            $this->merge(['cdgo_crgo_en' => $this->input('id_cargo_destino')]);
        }
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
            'id_cargo_destino' => ['required_without:cdgo_crgo_en', 'integer', 'min:1'],
            'cdgo_crgo_en' => ['nullable', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'fecha' => ['nullable', 'string'],
            'crear_tramite_interno' => ['nullable'],
            'cdgo_tpo_trmte' => ['nullable', 'integer', 'min:1'],
            'txt_nu_smlla_intrno' => ['nullable', 'string', 'max:50'],
            'in_envo_fsco' => ['nullable'],
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
            'ids.max' => 'No puede asignar más de 100 trámites en un lote.',
            'ids.*.distinct' => 'El lote contiene trámites duplicados.',
            'id_cargo_destino.required' => 'El cargo de destino es obligatorio.',
            'id_cargo_destino.min' => 'El cargo de destino no es válido.',
            'archivos.max' => 'No se permite adjuntar más de 4 archivos.',
            'archivos.*.mimes' => 'Solo se permiten archivos en formato PDF, DOCX, XLSX o ZIP.',
            'archivos.*.max' => 'Cada archivo adjunto no debe exceder 20 MiB.',
        ];
    }
}