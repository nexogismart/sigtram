<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ArchivoExpedienteTablaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bandeja' => ['required', 'string', 'in:finalizados,archivados,prestados'],
            'campo' => ['nullable', 'string', 'in:expediente,expediente_varios,nombre,estado,observaciones'],
            'criterio' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:8,10,15,25,50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $desde = $this->input('desde');
            $hasta = $this->input('hasta');

            if ($desde && $hasta && $hasta < $desde) {
                $validator->errors()->add('hasta', 'La fecha hasta no puede ser menor que la fecha desde.');
            }
        });
    }
}
