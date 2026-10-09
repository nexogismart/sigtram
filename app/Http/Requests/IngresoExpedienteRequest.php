<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Models\DocumentoTramiteTupa;
use App\Models\RequisitoTramite;
use App\Models\ServicioArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngresoExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        // Forzar fecha de ingreso con la del servidor
        $sanitized['fecha_ingreso'] = now()->format('Y-m-d H:i:s');

        // Campos de texto: strip_tags + eliminar espacios dobles + trim
        foreach (['nombres', 'ape_paterno', 'ape_materno', 'razon_social', 'direccion', 'dir_jur', 'observaciones'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_string($value)) {
                    $value = strip_tags($value);
                    $value = preg_replace('/\s+/', ' ', $value);
                    $value = trim($value);
                    $sanitized[$field] = $value;
                }
            }
        }

        // Correo a minúsculas + trim
        foreach (['correo', 'cor_jur'] as $emailField) {
            if ($this->has($emailField)) {
                $value = $this->input($emailField);
                if (is_string($value)) {
                    $sanitized[$emailField] = strtolower(trim($value));
                }
            }
        }

        // Teléfonos: solo números
        foreach (['telefono', 'tel_jur'] as $telField) {
            if ($this->has($telField)) {
                $value = $this->input($telField);
                if (is_string($value)) {
                    $sanitized[$telField] = preg_replace('/[^0-9]/', '', $value);
                }
            }
        }

        // Documentos: solo números
        foreach (['num_doc', 'ruc'] as $docField) {
            if ($this->has($docField)) {
                $value = $this->input($docField);
                if (is_string($value)) {
                    $sanitized[$docField] = preg_replace('/[^0-9]/', '', $value);
                }
            }
        }

        $this->merge($sanitized);

        if ($this->has('observado')) {
            $this->merge(['observado' => filter_var($this->input('observado'), FILTER_VALIDATE_BOOLEAN)]);
        }

        if (is_string($this->input('requisitos_marcados'))) {
            $decoded = json_decode($this->input('requisitos_marcados'), true);
            if (is_array($decoded)) {
                $this->merge(['requisitos_marcados' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $areaTable = (new Area())->getTable();
        $servicioTable = (new ServicioArea())->getTable();
        $tupaTable = (new DocumentoTramiteTupa())->getTable();
        $requisitoTable = (new RequisitoTramite())->getTable();

        return [
            'tipo_tramite' => ['required', 'in:externo,simple'],
            'cdgo_tpo_trmte' => ['nullable', 'integer'],
            'cdgo_tpo_observado' => ['nullable', 'integer'],
            'cdgo_area_observado' => ['nullable', 'integer'],
            'fecha_ingreso' => ['required', 'date'],
            'tipo_persona' => ['required', 'in:natural,juridica'],
            
            'tipo_doc' => ['required_if:tipo_persona,natural', 'nullable'],
            'num_doc' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $tipoPersona = $this->input('tipo_persona');
                    $tipoDoc = $this->input('tipo_doc');
                    if ($tipoPersona === 'natural') {
                        if ($tipoDoc != '5' && empty($value)) {
                            $fail('El número de documento es obligatorio.');
                            return;
                        }
                        if ($tipoDoc == '1' && !preg_match('/^\d{8}$/', $value)) {
                            $fail('El DNI debe contener exactamente 8 dígitos.');
                        }
                        if ($tipoDoc == '2' && !preg_match('/^\d{9}$/', $value)) {
                            $fail('El Carnet de Extranjería debe contener exactamente 9 dígitos.');
                        }
                    }
                }
            ],
            'nombres' => [
                'required_if:tipo_persona,natural',
                'nullable', 'string', 'min:2', 'max:150',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'\-]+$/'
            ],
            'ape_paterno' => [
                'required_if:tipo_persona,natural',
                'nullable', 'string', 'min:2', 'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'\-]+$/'
            ],
            'ape_materno' => [
                'required_if:tipo_persona,natural',
                'nullable', 'string', 'min:2', 'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'\-]+$/'
            ],
            'telefono' => [
                'nullable', 'string',
                'regex:/^9\d{8}$/'
            ],
            'correo' => [
                'nullable', 'email', 'max:254'
            ],
            'direccion' => [
                'required_if:tipo_persona,natural',
                'nullable', 'string', 'min:5', 'max:250',
                'regex:/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\.\,\-\#\/°]+$/'
            ],
            
            // Persona Jurídica
            'ruc' => [
                'required_if:tipo_persona,juridica',
                'nullable', 'string',
                'regex:/^(10|15|17|20)\d{9}$/'
            ],
            'razon_social' => [
                'required_if:tipo_persona,juridica',
                'nullable', 'string', 'min:3', 'max:150',
                'regex:/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\.\&\-\/\(\)]+$/'
            ],
            'tel_jur' => [
                'nullable', 'string',
                'regex:/^9\d{8}$/'
            ],
            'cor_jur' => [
                'nullable', 'email', 'max:254'
            ],
            'dir_jur' => [
                'required_if:tipo_persona,juridica',
                'nullable', 'string', 'min:5', 'max:255',
                'regex:/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\.\,\-\#\/°]+$/'
            ],
            
            // Destino
            'destino_modo' => ['required', 'in:areas,servicios,seleccion'],
            'gerencia' => [
                'required_if:destino_modo,areas',
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($areaTable) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (!\Illuminate\Support\Facades\DB::table($areaTable)->where('cdgo_area', $value)->exists()) {
                        $fail('La gerencia seleccionada no existe.');
                    }
                }
            ],
            'subgerencia' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($areaTable) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (!\Illuminate\Support\Facades\DB::table($areaTable)->where('cdgo_area', $value)->exists()) {
                        $fail('La subgerencia seleccionada no existe.');
                    }
                }
            ],
            'servicio' => [
                'nullable',
                'required_if:destino_modo,servicios',
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($servicioTable) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (!\Illuminate\Support\Facades\DB::table($servicioTable)->where('cdgo_srvcio_area', $value)->exists()) {
                        $fail('El servicio seleccionado no existe.');
                    }
                }
            ],
            'tipo_tram_tupa' => [
                'nullable',
                'required_if:destino_modo,servicios',
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($tupaTable) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (!\Illuminate\Support\Facades\DB::table($tupaTable)->where('cdgo_docmnto_trmte', $value)->exists()) {
                        $fail('El tipo de trámite seleccionado no existe.');
                    }
                }
            ],
            
            // Detalle físico/observación
            'folios' => ['required', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'observado' => ['required', 'boolean'],
            'fle_adjunto' => ['nullable', 'file', 'max:20480'],
            'requisitos_marcados' => ['nullable', 'array'],
            'requisitos_marcados.*' => [
                'integer',
                function ($attribute, $value, $fail) use ($requisitoTable) {
                    if (!\Illuminate\Support\Facades\DB::table($requisitoTable)->where('cdgo_rqto_trmte', $value)->exists()) {
                        $fail('Uno de los requisitos marcados no existe en el catálogo.');
                    }
                }
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_tramite.required' => 'El tipo de trámite es obligatorio.',
            'tipo_tramite.in' => 'El tipo de trámite seleccionado no es válido.',
            'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',
            'fecha_ingreso.date' => 'La fecha de ingreso no es una fecha válida.',
            'tipo_persona.required' => 'El tipo de persona es obligatorio.',
            'tipo_persona.in' => 'El tipo de persona debe ser natural o jurídica.',
            
            'tipo_doc.required_if' => 'El tipo de documento es obligatorio para personas naturales.',
            'tipo_doc.in' => 'El tipo de documento seleccionado no es válido.',
            
            'nombres.required_if' => 'El nombre es obligatorio.',
            'nombres.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombres.max' => 'El nombre no puede exceder 150 caracteres.',
            'nombres.regex' => 'Solo se permiten letras, tildes, espacios, guiones y apóstrofes.',
            
            'ape_paterno.required_if' => 'El apellido paterno es obligatorio.',
            'ape_paterno.min' => 'El apellido paterno debe tener al menos 2 caracteres.',
            'ape_paterno.max' => 'El apellido paterno no puede exceder 100 caracteres.',
            'ape_paterno.regex' => 'Solo se permiten letras, tildes, espacios, guiones y apóstrofes.',
            
            'ape_materno.required_if' => 'El apellido materno es obligatorio.',
            'ape_materno.min' => 'El apellido materno debe tener al menos 2 caracteres.',
            'ape_materno.max' => 'El apellido materno no puede exceder 100 caracteres.',
            'ape_materno.regex' => 'Solo se permiten letras, tildes, espacios, guiones y apóstrofes.',
            
            'telefono.regex' => 'Ingrese un número de teléfono válido de 9 dígitos que inicie con 9.',
            
            'correo.email' => 'Ingrese un correo electrónico válido (ej: usuario@dominio.com).',
            'correo.max' => 'El correo no puede exceder 254 caracteres.',
            
            'direccion.required_if' => 'La dirección es obligatoria.',
            'direccion.min' => 'La dirección debe tener al menos 5 caracteres.',
            'direccion.max' => 'La dirección no puede exceder 250 caracteres.',
            'direccion.regex' => 'Solo se permiten letras, números, espacios y caracteres comunes (.,#/-°).',
            
            'ruc.required_if' => 'El RUC es obligatorio para personas jurídicas.',
            'ruc.regex' => 'El RUC debe contener 11 dígitos y comenzar con 10, 15, 17 o 20.',
            
            'razon_social.required_if' => 'La razón social es obligatoria.',
            'razon_social.min' => 'La razón social debe tener al menos 3 caracteres.',
            'razon_social.max' => 'La razón social no puede exceder 150 caracteres.',
            'razon_social.regex' => 'Ingrese una razón social válida. Solo se permiten letras, números, espacios y caracteres empresariales (.&-/()).',
            
            'tel_jur.regex' => 'Ingrese un número de teléfono válido de 9 dígitos que inicie con 9.',
            
            'cor_jur.email' => 'Ingrese un correo electrónico válido.',
            'cor_jur.max' => 'El correo no puede exceder 254 caracteres.',
            
            'dir_jur.required_if' => 'El domicilio fiscal es obligatorio.',
            'dir_jur.min' => 'El domicilio fiscal debe tener al menos 5 caracteres.',
            'dir_jur.max' => 'El domicilio fiscal no puede exceder 255 caracteres.',
            'dir_jur.regex' => 'Ingrese un domicilio fiscal válido. Solo se permiten letras, números, espacios y caracteres comunes (.,#/-°).',
            
            'destino_modo.required' => 'Debe seleccionar el modo de derivación.',
            'gerencia.required_if' => 'Debe seleccionar la gerencia.',
            'gerencia.exists' => 'La gerencia seleccionada no existe.',
            'subgerencia.exists' => 'La subgerencia seleccionada no existe.',
            'servicio.required_if' => 'Debe seleccionar el servicio.',
            'servicio.exists' => 'El servicio seleccionado no existe.',
            'tipo_tram_tupa.required_if' => 'Debe seleccionar el trámite.',
            'tipo_tram_tupa.exists' => 'El tipo de trámite seleccionado no existe.',
            
            'folios.required' => 'Debe especificar la cantidad de folios.',
            'folios.integer' => 'La cantidad de folios debe ser un número entero.',
            'folios.min' => 'Debe especificar la cantidad de folios.',
            'requisitos_marcados.*.exists' => 'Uno de los requisitos marcados no existe en el catálogo.',
            'fle_adjunto.uploaded' => 'El archivo adjunto no se pudo cargar. El archivo excede el tamaño máximo permitido por el servidor o la carga fue interrumpida.',
            'fle_adjunto.max' => 'El archivo adjunto no debe superar los 20MB.',
        ];
    }
}
