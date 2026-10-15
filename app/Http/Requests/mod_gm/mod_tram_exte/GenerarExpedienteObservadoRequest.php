<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

class GenerarExpedienteObservadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza los parámetros de tipo de trámite asegurando estrictez sin valores inventados.
     */
    protected function prepareForValidation(): void
    {
        $hasTipo = $this->has('tipo_tramite') && $this->input('tipo_tramite') !== null && trim((string) $this->input('tipo_tramite')) !== '';
        $hasAlias = $this->has('rdn_cdgo_tpo_trmtes') && $this->input('rdn_cdgo_tpo_trmtes') !== null && trim((string) $this->input('rdn_cdgo_tpo_trmtes')) !== '';

        $tipo = $hasTipo ? strtoupper(trim((string) $this->input('tipo_tramite'))) : null;
        $alias = $hasAlias ? strtoupper(trim((string) $this->input('rdn_cdgo_tpo_trmtes'))) : null;

        // Si se recibieron ambos campos y sus valores normalizados son diferentes, se marca contradicción
        if ($tipo !== null && $alias !== null && $tipo !== $alias) {
            $this->merge([
                'tipo_tramite' => 'CONTRADICCION',
                'rdn_cdgo_tpo_trmtes' => 'CONTRADICCION',
                '__contradiccion_tipo' => true,
            ]);
            return;
        }

        // Si solo se envió el alias legacy, se copia hacia tipo_tramite
        if ($tipo === null && $alias !== null) {
            $tipo = $alias;
        }

        $merge = [];
        if ($tipo !== null) {
            $merge['tipo_tramite'] = $tipo;
        }
        if ($alias !== null) {
            $merge['rdn_cdgo_tpo_trmtes'] = $alias;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('__contradiccion_tipo')) {
                $validator->errors()->add('tipo_tramite', 'Existe una contradicción entre los campos tipo_tramite y rdn_cdgo_tpo_trmtes.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'id_tram' => ['required', 'integer', 'min:1'],
            'tipo_tramite' => ['required', 'string', 'in:E,S'],
            'rdn_cdgo_tpo_trmtes' => ['nullable', 'string', 'in:E,S'],
            'T6' => ['nullable', 'integer', 'min:0'],
            'TA1' => ['nullable', 'string'],
            'hdn_cdgo_usrios' => ['required', 'integer', 'min:1'],
            'Se2' => ['required', 'integer', 'min:1'],
            'Se6' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_tramite.required' => 'Debe seleccionar el tipo de documento (Expediente o Simple).',
            'tipo_tramite.in' => 'El tipo de trámite debe ser exclusivamente E o S.',
            'rdn_cdgo_tpo_trmtes.in' => 'El tipo de trámite debe ser exclusivamente E o S.',
            'Se2.required' => 'Debe seleccionar la gerencia de destino.',
            'Se2.min' => 'Debe seleccionar la gerencia de destino.',
        ];
    }
}
