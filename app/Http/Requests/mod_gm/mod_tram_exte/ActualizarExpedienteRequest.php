<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gm\mod_tram_exte;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_tram' => ['required', 'integer', 'min:1'],
            'TA1' => ['nullable', 'string'],
            'req_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
