<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use App\Http\Requests\mod_gm\mod_tram_exte\Concerns\NormalizaFechasRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para envío multi-área de expedientes.
 * Resuelve CE-P1-003, CE-P1-004 y CE-04.
 */
class EnviarExpedienteRequest extends FormRequest
{
    use NormalizaFechasRequestTrait;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarCamposFecha(['fecha']);
        if (!$this->has('movimiento_ids') && $this->has('ids')) {
            $this->merge(['movimiento_ids' => (array) $this->input('ids')]);
        }
        if ($this->has('movimiento_ids')) {
            $cleaned = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('movimiento_ids')), fn($v) => $v > 0)));
            $this->merge(['movimiento_ids' => $cleaned]);
        }

        $conTramite = $this->input('enviar_con_tramite');
        if ($conTramite === null || $conTramite === '0' || $conTramite === 0 || $conTramite === false || $conTramite === 'false') {
            $this->merge([
                'enviar_con_tramite' => 0,
                'doc_tipo'           => null,
                'tramite_nro'        => null,
            ]);
        } else {
            $this->merge([
                'enviar_con_tramite' => 1,
            ]);
        }
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('movimiento_ids') && $this->has('ids')) {
                $rawMov = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('movimiento_ids')), fn($v) => $v > 0)));
                $rawIds = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('ids')), fn($v) => $v > 0)));
                sort($rawMov);
                sort($rawIds);
                if ($rawMov !== $rawIds) {
                    $validator->errors()->add('movimiento_ids', 'Conflicto de identificadores: movimiento_ids e ids no coinciden.');
                }
            }
        });
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'movimiento_ids'     => ['required_without:ids', 'nullable', 'array', 'min:1', 'max:100'],
            'movimiento_ids.*'   => ['integer', 'min:1'],
            'ids'                => ['required_without:movimiento_ids', 'nullable', 'array', 'min:1', 'max:100'],
            'ids.*'              => ['integer', 'min:1'],
            'areas'              => ['required', 'array', 'min:1'],
            'areas.*'            => ['required', 'integer', 'min:1'],
            'fecha'              => ['required', 'date_format:Y-m-d H:i:s'],
            'observaciones'      => ['nullable', 'string', 'max:1000'],
            'enviar_con_tramite' => ['nullable', 'in:0,1,true,false'],
            'doc_tipo'           => ['exclude_unless:enviar_con_tramite,1', 'required', 'integer', 'min:1'],
            'tramite_nro'        => ['exclude_unless:enviar_con_tramite,1', 'nullable', 'string', 'max:50'],
            'folios'             => ['nullable', 'integer', 'min:0', 'max:99999'],
            'adjunto'            => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,zip,jpg,jpeg,png'],
            'fle_adjunto_pdf'    => ['nullable', 'file', 'max:20480', 'mimes:pdf'],
            'fle_adjunto1'       => ['prohibited'],
            'fle_adjunto2'       => ['prohibited'],
            'fle_adjunto3'       => ['prohibited'],
            'fle_adjunto4'       => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movimiento_ids.required_without' => 'Debe seleccionar al menos un movimiento.',
            'movimiento_ids.min'              => 'Debe seleccionar al menos un movimiento.',
            'ids.required_without'            => 'Debe seleccionar al menos un movimiento.',
            'ids.min'                         => 'Debe seleccionar al menos un movimiento.',
            'areas.required'                  => 'Debe seleccionar al menos un área de destino.',
            'areas.min'                       => 'Debe seleccionar al menos un área de destino.',
            'fecha.required'                  => 'La fecha de envío es obligatoria.',
            'fecha.date_format'               => 'El formato de fecha debe ser YYYY-MM-DD HH:MM:SS.',
            'doc_tipo.required'               => 'Seleccione un tipo de documento interno.',
            'doc_tipo.min'                    => 'El tipo de documento seleccionado no existe o está inactivo.',
            'doc_tipo.integer'                => 'El tipo de documento debe ser un valor entero.',
            'fle_adjunto1.prohibited'         => 'Los adjuntos adicionales están deshabilitados en esta fase.',
            'fle_adjunto2.prohibited'         => 'Los adjuntos adicionales están deshabilitados en esta fase.',
            'fle_adjunto3.prohibited'         => 'Los adjuntos adicionales están deshabilitados en esta fase.',
            'fle_adjunto4.prohibited'         => 'Los adjuntos adicionales están deshabilitados en esta fase.',
        ];
    }
}

