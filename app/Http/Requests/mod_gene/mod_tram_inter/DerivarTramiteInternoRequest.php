<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class DerivarTramiteInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('sel_areas') && !$this->filled('areas_destino')) {
            $this->merge(['areas_destino' => (array) $this->input('sel_areas')]);
        }
        if ($this->filled('areas_destino') && !$this->filled('sel_areas')) {
            $this->merge(['sel_areas' => (array) $this->input('areas_destino')]);
        }
        if ($this->filled('id_area_destino') && !$this->filled('areas_destino') && (int) $this->input('id_area_destino') > 0) {
            $this->merge(['areas_destino' => [(int) $this->input('id_area_destino')]]);
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
            'id_area_destino' => ['nullable', 'integer', 'min:1'],
            'id_cargo_destino' => ['nullable', 'integer', 'min:1'],
            'sel_areas' => ['nullable', 'array', 'min:1', 'max:50'],
            'sel_areas.*' => ['required', 'integer', 'min:1', 'distinct'],
            'areas_destino' => ['required_without:id_area_destino', 'array', 'min:1', 'max:50'],
            'areas_destino.*' => ['required', 'integer', 'min:1', 'distinct'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'tipo_documento' => ['nullable', 'integer', 'min:1'],
            'cdgo_tpo_trmte' => ['nullable', 'integer', 'min:1'],
            'numero_documento' => ['nullable', 'string', 'max:50'],
            'txt_nu_smlla_intrno' => ['nullable', 'string', 'max:50'],
            'proveido' => ['nullable', 'string', 'max:500'],
            'fecha' => ['nullable', 'string'],
            'crear_tramite_interno' => ['nullable'],
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
            'ids.max' => 'No puede derivar más de 100 trámites en un lote.',
            'ids.*.distinct' => 'El lote contiene trámites duplicados.',
            'id_area_destino.required' => 'El área de destino es obligatoria.',
            'id_area_destino.min' => 'El área de destino no es válida.',
            'id_cargo_destino.required' => 'El cargo de destino es obligatorio.',
            'id_cargo_destino.min' => 'El cargo de destino no es válido.',
            'archivos.max' => 'No se permite adjuntar más de 4 archivos.',
            'archivos.*.mimes' => 'Solo se permiten archivos en formato PDF, DOCX, XLSX o ZIP.',
            'archivos.*.max' => 'Cada archivo adjunto no debe exceder 20 MiB.',
        ];
    }
}