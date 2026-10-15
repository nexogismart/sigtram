{{-- Local asset works for browser printing and server-side PDF rendering. --}}
<div class="sigtram-print-brand" style="font-family: DejaVu Sans, Arial, sans-serif; border-bottom: 2px solid #163b59; padding-bottom: 7px; margin-bottom: 12px; color: #163b59;">
    <img src="{{ ($sigtramPdf ?? false) ? public_path('images/nexosigtram-documental-logo.png') : asset('images/nexosigtram-documental-logo.png') }}" alt="Nexo SIGTRAM" style="width: 112px; height: auto; vertical-align: middle;">
    <span style="font-size: 9px; margin-left: 12px;">Sistema de Gestión de Trámites</span>
</div>
