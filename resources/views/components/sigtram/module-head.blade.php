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
<link rel="icon" type="image/png" href="{{ asset('images/nexosigtram-documental-emblema.png') }}">
<link rel="stylesheet" href="{{ asset('css/sigtram-modules.css') }}?v={{ filemtime(public_path('css/sigtram-modules.css')) }}">
<link rel="stylesheet" href="{{ asset('css/sigtram-typography.css') }}?v={{ filemtime(public_path('css/sigtram-typography.css')) }}">
<script src="{{ asset('js/sigtram-modules.js') }}?v={{ filemtime(public_path('js/sigtram-modules.js')) }}" defer></script>
