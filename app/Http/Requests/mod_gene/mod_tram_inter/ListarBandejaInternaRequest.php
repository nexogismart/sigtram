<?php

declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class ListarBandejaInternaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bandeja' => ['nullable', 'string', 'in:entrada,enviados,recibidos,salida,finalizados,sin_finalizar,contabilidad,todos'],
            'tipo' => ['nullable', 'integer', 'between:1,8'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,15,20,25,50,100'],
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'buscar' => ['nullable', 'string', 'max:150'],
            'campo' => ['nullable', 'string', 'in:asunto,expediente,emisor,documento,todos'],
            'criterio' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:200'],
            'faltantes' => ['nullable'],
            'reporte' => ['nullable', 'string', 'max:50'],
        ];
    }
}