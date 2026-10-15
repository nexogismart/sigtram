@php
    $workspaceMenu = \App\Support\WorkspaceNavigation::decorate($menuItems);
    $visibleWorkspaceMenu = array_filter($workspaceMenu, fn ($group) => $group['workspace']['active'] ?? true);
    $workspaceCompanyId = (int) session(\App\Support\SessionKeys::COMPANIA_ID, 0);
    $workspaceCargoId = (int) session(\App\Support\SessionKeys::CARGO_ID, 0);
    $workspaceStorageKey = 'nexosigtram.workspace.' . hash('sha256', json_encode([
        auth()->id() ?? $usuario, $workspaceCompanyId,
        $workspaceCargoId > 0 ? $workspaceCargoId : [$area, $cargo],
    ], JSON_THROW_ON_ERROR));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SIGTRAM · Sistema de Gestión de Trámites</title>
    <link rel="icon" type="image/png" href="{{ asset('images/nexosigtram-documental-emblema.png') }}">
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('nexosigtram.theme') || 'light'; } catch (_) {}</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/nexo-workspace.css') }}?v={{ filemtime(public_path('css/nexo-workspace.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/sigtram-typography.css') }}?v={{ filemtime(public_path('css/sigtram-typography.css')) }}">
</head>
<body class="area-workspace" data-area-aliases="{{ json_encode(\App\Support\WorkspaceNavigation::areaAliases(), JSON_UNESCAPED_UNICODE) }}">
    <a class="skip-link" href="#mainContent">Ir al contenido</a>
    <div class="workspace-shell">
        <header class="topbar">
            <div class="topbar-start"><a class="header-brand" href="{{ route('inicio') }}" data-home><img src="{{ asset('images/nexosigtram-documental-logo.png') }}" alt="Nexosigtram" width="130" height="54"></a><span class="header-purpose">Sistema de Gestión<br>de Trámites</span><div class="rail-product"><span id="currentPage">Mi espacio</span></div></div>
            <nav class="primary-navigation" aria-label="Vistas de trabajo">
                <button class="dock-item active" data-home type="button" aria-current="page"><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span>Mi espacio</span></button>
                <button class="dock-item" data-home-view="favorites" type="button"><i class="fa-regular fa-star" aria-hidden="true"></i><span>Favoritos</span><span class="dock-badge" id="favoriteCount" hidden>0</span></button>
                <button class="dock-item" data-home-view="recent" type="button"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Recientes</span></button>
                <details class="helpdesk-nav" id="helpdeskMenu"><summary class="dock-item" aria-label="Abrir Mesa de Ayuda"><i class="fa-solid fa-headset" aria-hidden="true"></i><span>Mesa de Ayuda</span><i class="fa-solid fa-chevron-down helpdesk-chevron" aria-hidden="true"></i></summary><div class="helpdesk-dropdown"><p class="helpdesk-caption">Opciones de soporte</p><nav id="helpdeskLinks" aria-label="Opciones autorizadas de Mesa de Ayuda"><p class="helpdesk-empty">Activa JavaScript para consultar tus opciones. También están disponibles al final de la página.</p></nav></div></details>
            </nav>
<div class="topbar-actions"><button class="icon-button" id="themeToggle" type="button" aria-label="Activar modo oscuro"><i class="fa-regular fa-moon" aria-hidden="true"></i></button><details class="profile" id="profileMenu"><summary aria-label="Abrir perfil y opciones"><span class="avatar"><span data-avatar-initial>{{ mb_substr(trim($usuario), 0, 1) }}</span><img data-profile-photo src="{{ route('perfil.foto') }}" alt="" width="34" height="34" decoding="async" hidden></span><span class="profile-summary"><strong>{{ $usuario }}</strong><span>{{ $cargo }}</span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="profile-panel">
    <div class="profile-identity">
        <span class="avatar profile-portrait"><span data-avatar-initial>{{ mb_substr(trim($usuario), 0, 1) }}</span><img data-profile-photo src="{{ route('perfil.foto') }}" alt="Foto de perfil" width="72" height="84" decoding="async" hidden></span>
        <div class="profile-identity-copy"><span class="profile-label">Mi perfil</span><strong>{{ $usuario }}</strong><span class="profile-role">{{ $cargo }}</span></div>
    </div>
    <div class="profile-area"><i class="fa-regular fa-building" aria-hidden="true"></i><div><span>Área activa</span><strong>{{ $area }}</strong></div></div>
    <div class="profile-options">
        <a href="{{ route('seleccionar.cargo') }}"><span class="profile-action-icon"><i class="fa-solid fa-repeat" aria-hidden="true"></i></span><span class="profile-action-copy"><strong>Cambiar cargo</strong><small>Usar otro cargo asignado</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        <button id="logoutButton" type="button"><span class="profile-action-icon"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></span><span class="profile-action-copy"><strong>Cerrar sesión</strong><small>Finalizar esta sesión</small></span></button>
    </div>
</div></details></div>
        </header>
        <main id="mainContent" tabindex="-1">
            <div id="dashboardHome">
                <header class="workspace-intro"><div><p class="intro-label">Sistema de Gestión de Trámites</p><h1>Gestión de trámites</h1><p id="workspaceIntroDescription">Tu área y los servicios que necesitas para trabajar.</p><div id="workspaceResume" class="workspace-resume" hidden></div></div><div class="desk-search"><label for="boardSearch"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" id="boardSearch" placeholder="Buscar trámite, servicio o acción" aria-label="Buscar un servicio o una acción" autocomplete="off"></label><button id="clearSearch" type="button" aria-label="Limpiar búsqueda" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i></button><kbd>Ctrl K</kbd></div></header>
                <section class="workboard" aria-labelledby="boardTitle">
                    <header class="board-heading" hidden><div><h2 id="boardTitle">Mi espacio</h2><p id="boardDescription"></p></div><span id="boardCount" aria-live="polite"></span></header>
                    <div id="moduleBoard" class="module-board"></div><button id="showMoreBoard" class="show-more" type="button" hidden>Ver más accesos <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                </section>
                <noscript>
                    <h2>Tus herramientas</h2>
                    <p>Activa JavaScript para usar el explorador, la búsqueda y tus favoritos.</p>
                    <nav aria-label="Módulos disponibles">
                        @forelse ($visibleWorkspaceMenu as $menuItem)
                            <section><h3>{{ $menuItem['label'] }}</h3>
                            @foreach (!empty($menuItem['children']) ? $menuItem['children'] : [$menuItem] as $entry)
                                <a href="{{ $entry['url'] }}" data-module-link @if($entry['popup']) target="_blank" rel="noopener" @endif>{{ $entry['label'] }}</a>
                            @endforeach
                            </section>
                        @empty
                            <p>No hay módulos asignados a este cargo.</p>
                        @endforelse
                    </nav>
                </noscript>
                <footer class="workspace-footer"><span>SIGTRAM <span class="footer-slash">/</span> Desarrollado por Nexo</span><span>{{ $area }}</span></footer>
            </div>
<section id="modulePanel" hidden aria-label="Módulo abierto"><div class="module-toolbar"><button class="text-button" type="button" data-home><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Mi espacio</button><strong id="moduleTitle"></strong><a id="openModuleTab" target="_blank" rel="noopener" aria-label="Abrir módulo en otra pestaña"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></div><iframe id="contentFrame" title="Contenido del módulo" name="contentFrame"></iframe></section>
        </main>
    </div>
    <dialog class="explorer-dialog" id="explorerDialog" aria-labelledby="explorerTitle">
        <header class="explorer-header"><div class="explorer-symbol"><i class="fa-solid fa-shapes" aria-hidden="true"></i></div><div><h2 id="explorerTitle">Explorar accesos</h2><p>Acciones habilitadas para tu cargo activo.</p></div><button class="icon-button" id="closeExplorer" type="button" aria-label="Cerrar explorador"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
        <label class="explorer-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="explorerSearch" type="search" placeholder="Buscar un servicio o una acción" aria-label="Buscar en los módulos habilitados" autocomplete="off"><kbd>Esc</kbd></label>
        <div class="explorer-location"><button id="backToSpaces" class="text-button" type="button" hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Espacios</button><strong id="explorerLocation">Tus espacios</strong><span id="explorerCount" aria-live="polite"></span></div>
        <div class="explorer-results" id="explorerResults"></div>
        <button class="show-more" id="showMore" type="button" hidden>Mostrar más <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
        <footer class="explorer-footer"><span><i class="fa-regular fa-star" aria-hidden="true"></i> La estrella guarda el acceso en Favoritos.</span><span>↑ ↓ para recorrer · Enter para abrir</span></footer>
    </dialog>
<form id="logoutForm" method="POST" action="{{ route('logout') }}" hidden>@csrf</form>
    <dialog id="logoutDialog"><div class="dialog-icon"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></div><h2>¿Cerrar tu sesión?</h2><p>Podrás volver a ingresar cuando lo necesites.</p><div class="dialog-actions"><button id="cancelLogout" type="button">Seguir trabajando</button><button class="confirm-logout" id="confirmLogout" type="button">Cerrar sesión</button></div></dialog>
    <p class="toast" id="workspaceToast" role="status" hidden></p>
    <script id="workspaceData" type="application/json">{!! json_encode(['menu' => $workspaceMenu, 'spaces' => config('workspace.spaces'), 'context' => ['area' => $area, 'cargo' => $cargo], 'storageKey' => $workspaceStorageKey], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <script src="{{ asset('js/nexo-workspace.js') }}?v={{ filemtime(public_path('js/nexo-workspace.js')) }}" defer></script>
</body>
</html>
