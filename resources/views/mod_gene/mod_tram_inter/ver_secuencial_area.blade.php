<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram | Secuencial del Área</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/tramite-interno/secuencial-area.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gene.mod_tram_inter.ver_secuencial_area', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body class="secuencial-area-page">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gene.mod_tram_inter.ver_secuencial_area', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}


@php
    $frontendConfig = [
        'csrfToken' => csrf_token(),
        'urlLista' => route('mod_tram_inter.ver_secuencial_area.lista'),
    ];
@endphp

<div class="wrap" id="secuencial-area-app" x-data="secuencialAreaApp" data-config="{{ json_encode($frontendConfig) }}">
    <div class="top">
        <h1>Secuencial del <span>Área</span></h1>
        <p>Generación de numeración interna por tipo de documento</p>
    </div>

    @if (session('ok'))
        <div class="flash">{{ session('ok') }}</div>
    @endif
    <div id="err-banner" class="err-banner" x-show="hasError" x-text="errorMessage" x-cloak></div>

    <form method="POST" action="{{ url('/mod_gene/mod_tram_inter/ver_secuencial_area') }}" id="formSecuencial" name="formBandejaEntrada">
        @csrf
        <div class="panel">
            <div class="panel__head">Datos del trámite</div>
            <div class="panel__body">
                <div class="grid">
                    <div class="lbl">Área</div>
                    <div class="sep">:</div>
                    <div class="val">{{ $areaNombre }}</div>

                    <div class="lbl">Año</div>
                    <div class="sep">:</div>
                    <div>
                        <select name="sel_cdgo_anio" id="sel_cdgo_anio" @change="cargarLista()">
                            @foreach ($anios as $a)
                                <option value="{{ $a->cdgo_anio }}" @selected((int)$a->cdgo_anio === (int)$cdgoAnioActual)>
                                    {{ $a->de_anio }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lbl">Tipo de documento</div>
                    <div class="sep">:</div>
                    <div>
                        <select name="sel_cdgo_tpo_trmte" id="sel_cdgo_tpo_trmte" @change="cargarLista()">
                            <option value="0">-- Seleccione un tramite --</option>
                            @foreach ($tipos as $t)
                                <option value="{{ $t->cdgo_tpo_trmte }}" @selected((int)$t->cdgo_tpo_trmte === (int)$intCdgoTpoTrmte)>
                                    {{ $t->de_tpo_trmte }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lbl">Detalle documento</div>
                    <div class="sep">:</div>
                    <div>
                        <textarea name="txt_de_scncal_tpo_trmtes_area" id="txt_de_scncal_tpo_trmtes_area" placeholder="Descripción del documento"></textarea>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn" data-action="close">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn--primary" id="btn_generar" @click="generarNumero()">
                        <i class="fas fa-hashtag"></i> Generar Número
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="panel">
        <div class="panel__head">Datos del documento</div>
        <div class="panel__body">
            <div id="conte_lista_documentos"></div>
        </div>
    </div>
</div>
</body>
</html>
