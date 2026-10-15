@php
    $sigtramFamily = explode('.', $screenKey ?? '')[0];
    $sigtramPresentation = config('frontend.families.' . $sigtramFamily, ['label' => 'Gestión de trámites', 'description' => 'Sistema de Gestión de Trámites']);
    foreach (config('frontend.screens', []) as $sigtramPrefix => $sigtramScreen) {
        if (($screenKey ?? '') === $sigtramPrefix || str_starts_with($screenKey ?? '', $sigtramPrefix . '.')) {
            $sigtramPresentation = $sigtramScreen;
            break;
        }
    }
@endphp
<a class="sigtram-skip" href="#sigtram-content-start">Ir al contenido</a>
<header class="sigtram-modulebar" aria-label="Sistema de Gestión de Trámites">
    <a class="sigtram-modulebrand" href="{{ route('inicio') }}" target="_top" aria-label="SIGTRAM: volver a Mi espacio"><img src="{{ asset('images/nexosigtram-documental-logo.png') }}" alt="Nexo SIGTRAM" width="142" height="48"></a>
    <div class="sigtram-module-context"><span>Sistema de Gestión de Trámites</span><strong>{{ $sigtramPresentation['label'] }}</strong></div>
    @unless(in_array($screenKey ?? '', ['mod_gp.omaped.entrada', 'mod_gp.omaped.ver_datos'], true))
    <div class="sigtram-module-tools">@unless($sigtramNative ?? false)<button type="button" class="sigtram-density" data-sigtram-density aria-pressed="false" title="Cambiar la densidad de las tablas">Vista compacta</button>@endunless<a href="{{ route('inicio') }}" target="_top" class="sigtram-return">Mi espacio <span aria-hidden="true">↗</span></a></div>
    @endunless
</header>
<div id="sigtram-content-start" class="sigtram-content-start" tabindex="-1"></div>
