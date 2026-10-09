<?php
declare(strict_types=1);

namespace App\Http\Requests\mod_gene\mod_tram_inter;

use Illuminate\Foundation\Http\FormRequest;

class StoreTramiteInternoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sel_cdgo_tpo_trmte'  => ['required', 'integer'],
            'txt_nu_smlla_intrno' => ['required', 'string'],
            'txt_fe_rcbdo'        => ['required', 'date'],
            'sel_areas'           => ['required', 'array', 'min:1'],
            'sel_areas.*'         => ['integer'],
            'txt_de_obser'        => ['required', 'string'],
            'rdn_in_prrdad_trmte' => ['required', 'in:1,2'],
            'sel_crgos'           => ['nullable', 'integer'],
            'fle_adjunto1'        => ['nullable', 'file', 'mimes:pdf', 'max:20480'], // 20MB
            'fle_adjunto2'        => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar', 'max:20480'],
            'fle_adjunto3'        => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar', 'max:20480'],
            'fle_adjunto4'        => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar', 'max:20480'],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'txt_de_obser.required' => 'El campo Asunto / Resumen es obligatorio.',
            'sel_areas.required'    => 'Debe seleccionar al menos una gerencia o área de destino.',
            'fle_adjunto1.max'      => 'El archivo adjunto 1 no debe superar los 20MB.',
            'fle_adjunto2.max'      => 'El archivo adjunto 2 no debe superar los 20MB.',
            'fle_adjunto3.max'      => 'El archivo adjunto 3 no debe superar los 20MB.',
            'fle_adjunto4.max'      => 'El archivo adjunto 4 no debe superar los 20MB.',
            'fle_adjunto1.mimes'    => 'El archivo adjunto principal debe ser formato PDF.',
            'fle_adjunto2.mimes'    => 'El Anexo 1 tiene un formato no seguro o no permitido.',
            'fle_adjunto3.mimes'    => 'El Anexo 2 tiene un formato no seguro o no permitido.',
            'fle_adjunto4.mimes'    => 'El Anexo 3 tiene un formato no seguro o no permitido.',
        ];
    }
}
