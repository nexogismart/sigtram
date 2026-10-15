<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <script>
        // Evitar que el Login se cargue dentro de un iframe (si se vence la sesión, forzar redirección en la ventana principal)
        if (window.self !== window.top) {
            window.top.location.href = window.self.location.href;
        }
    </script>
    <meta name="description" content="Nexosigtram - Sistema de Gestión de Trámites">
    <meta name="theme-color" content="#071a30">
    <title>Iniciar sesión · SIGTRAM | Sistema de Gestión de Trámites</title>
    <link rel="icon" type="image/png" href="{{ asset('images/nexosigtram-documental-emblema.png') }}">

    <!-- Fuentes e Iconos -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-out forwards',
                        'shake': 'shake 0.5s cubic-bezier(.36,.07,.19,.97) both',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        shake: {
                            '10%, 90%': { transform: 'translate3d(-1px, 0, 0)' },
                            '20%, 80%': { transform: 'translate3d(2px, 0, 0)' },
                            '30%, 50%, 70%': { transform: 'translate3d(-4px, 0, 0)' },
                            '40%, 60%': { transform: 'translate3d(4px, 0, 0)' }
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Input autofill fix for Tailwind */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active{
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: #0f172a !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* Smooth transition for form switching */
        .form-container {
            transition: all 0.4s ease;
        }
        .form-hidden {
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px) scale(0.98);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
        }
        .form-active {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
            position: relative;
        }

        /* Custom Checkbox for Remember Me */
        .custom-checkbox {
            appearance: none;
            background-color: transparent;
            margin: 0;
            font: inherit;
            color: currentColor;
            width: 1.25em;
            height: 1.25em;
            border: 2px solid #cbd5e1;
            border-radius: 0.35em;
            display: grid;
            place-content: center;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .custom-checkbox::before {
            content: "";
            width: 0.65em;
            height: 0.65em;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em white;
            background-color: white;
            transform-origin: center;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }
        .custom-checkbox:checked {
            background-color: #ef4444;
            border-color: #ef4444;
        }
        .custom-checkbox:checked::before {
            transform: scale(1);
        }
        .custom-checkbox:focus-visible {
            outline: 2px solid #f87171;
            outline-offset: 2px;
        }
        .nexo-page-background { position: fixed; inset: -3vmax; background: #071a30 url('{{ asset('images/sigtram-login-office.png') }}') center / cover no-repeat; transform-origin: 28% 48%; animation: nexo-background-float 24s ease-in-out infinite; }
        @keyframes nexo-background-float { 0%, 100% { transform: scale(1.01); } 50% { transform: scale(1.035); } }
        .nexo-page-background::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, #071a3010 15%, #071a301f 45%, #071a3059 100%); }
        .login-card { width: 100%; max-width: 500px; min-height: 640px; margin-left: auto; margin-right: clamp(24px, 4vw, 80px); border-radius: 28px; box-shadow: 0 24px 80px #0005; }
        .login-form-panel { width: 100%; min-width: 0; flex: 1; }
        .login-welcome { display: none; }
        .welcome-eyebrow { color: #89bdf0; font-size: 10px; font-weight: 650; }
        .login-welcome h1 { margin-top: 18px; color: #fff; font-size: 30px; line-height: 1.17; font-weight: 750; text-wrap: balance; }
        .welcome-description { margin-top: 12px; color: #b5c9dd; font-size: 12px; line-height: 1.8; }
        .nexo-orbital-art { display: block; width: calc(100% + 24px); margin: 18px -12px 0; overflow: visible; }
        .document-stages { display: grid; grid-template-columns: 1fr 1fr 1.35fr; gap: 12px; list-style: none; padding: 0; margin: 0 0 26px; color: #e3f1fc; font-size: 10px; line-height: 1.5; }
        .document-stages span { display: block; margin-bottom: 4px; color: #91b9d6; font-size: 9px; }
        .banner-brand-emblem { flex: 0 0 44px; width: 44px; height: 44px; object-fit: contain; padding: 4px; background: #fff; border-radius: 10px; }
        .orbital-footer { display: flex; align-items: center; justify-content: flex-start; gap: 12px; margin-top: 6px; padding-top: 18px; border-top: 1px solid #ffffff18; }
        .orbital-caption { color: #a8c4dd; font-size: 11px; line-height: 1.7; }
        .orbital-caption strong { display: block; color: #fff; font-size: 12px; }
        @media(min-width: 1100px) { main.login-card { max-width: 820px; width: clamp(700px, 54vw, 820px); flex-direction: row; } .login-welcome { display: flex; flex-direction: column; justify-content: center; flex: 0 0 40%; min-width: 0; padding: 28px 24px; border-radius: 20px; background: linear-gradient(160deg, #103650, #082139 65%); overflow: hidden; } .login-card .login-form-panel { flex: 1; width: 60%; padding-left: 32px; padding-right: 32px; } }

        .nexo-form-logo { display: block; width: 300px; max-width: 100%; height: 118px; object-fit: contain; object-position: center; }
        .login-product-name { color: #52667b; font-size: 11px; text-align: center; margin-top: 4px; line-height: 1.6; }
        .company-credit { display: inline-block; color: #91a9bf; font-size: 9px; margin-top: 10px; }
        .login-card .login-form-panel { justify-content: center; gap: 44px; }
        #loginForm { gap: 22px; }
        #loginForm .login-field input { min-height: 54px; padding-left: 44px; padding-right: 16px; border-radius: 14px; background: #f8fafc; box-shadow: none; font-size: 14px; font-weight: 500; }
        #loginForm .login-field input[type="password"], #loginForm #password { padding-right: 54px; }
        #loginForm .login-field input:focus { background: #fff; }
        .login-field-icon { position: absolute; left: 16px; bottom: 20px; color: #92a4b8; font-size: 13px; pointer-events: none; }
        .login-field:focus-within .login-field-icon { color: #1768ad; }
        .password-toggle { position: absolute; right: 5px; bottom: 5px; width: 44px; height: 44px; border-radius: 10px; }
        .password-toggle:focus-visible { outline: 2px solid #1768ad; outline-offset: -2px; }
        #loginForm .login-submit { position: relative; display: flex; align-items: center; justify-content: center; width: 100%; min-height: 68px; margin-top: 12px; padding: 12px 80px 12px 36px; border: 1px solid #c8d8e9; border-radius: 999px; background: #f0f5fb; color: #0a2c4b; font: inherit; cursor: pointer; box-shadow: inset 0 1px 0 #fff, 0 5px 14px #0821390a; transition: background-color 160ms ease, border-color 160ms ease; }
        #loginForm .login-submit::before { content: ''; position: absolute; left: 18px; width: 4px; height: 18px; border-radius: 4px; background: #1c6fb7; transform: skew(-18deg); box-shadow: 7px 0 0 #7eb2df; }
        #loginForm .login-submit:hover { background: #e3eef9; border-color: #659bc7; }
        #loginForm .login-submit:focus-visible { outline: 3px solid #7ab6e8; outline-offset: 4px; }
        #loginForm .login-submit:disabled { cursor: wait; opacity: .8; }
        .login-submit .btn-text { font-size: 15px; font-weight: 700; }
        .submit-portal { position: absolute; right: 3px; top: 50%; transform: translateY(-50%); width: 60px; height: 60px; }
        .submit-portal svg { display: block; width: 100%; height: 100%; }
        .portal-disc { fill: #f43f49; }
        .login-submit:hover .portal-disc { fill: #db2837; }
        .submit-arrow { transform-origin: 30px 30px; }
        .login-submit .btn-loading { position: absolute; inset: 0; margin: auto; width: 16px; height: 16px; color: white; }
        .login-submit:disabled .submit-arrow { display: none; }
        .field-label { display: block; margin-bottom: 8px; color: #334155; font-size: 12px; font-weight: 600; }
        #loginForm input:focus { border-color: #1574c9; box-shadow: 0 0 0 3px #1574c91a; }
        @media(max-width: 1023px) { .login-card { max-width: 460px; min-height: auto; margin: 0 auto; border-radius: 24px; } .nexo-form-logo { width: 220px; height: 100px; } .nexo-page-background { background-position: 30% center; } .nexo-page-background::after { background: #071a3066; } }
        @media(prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/sigtram-typography.css') }}?v={{ filemtime(public_path('css/sigtram-typography.css')) }}">
</head>

<body class="sigtram-login min-h-screen relative flex items-center justify-center p-4 lg:p-8 bg-[#071a30] overflow-x-hidden">

    <div class="nexo-page-background" aria-hidden="true"></div>
    <main class="login-card relative z-10 w-full bg-white flex flex-col p-3 animate-fade-in">
        <aside class="login-welcome" aria-labelledby="welcome-title">
            <h1 id="welcome-title">Tu trámite,<br>de principio a fin.</h1>
            <p class="welcome-description">Registra documentos, deriva solicitudes y da seguimiento a cada etapa.</p>
            <svg id="nexo-orbital-scene" class="nexo-orbital-art" viewBox="0 0 320 330" role="img" aria-labelledby="orbital-title orbital-description">
                <title id="orbital-title">Un expediente, de su registro al seguimiento</title>
                <desc id="orbital-description">Documento digital con revisión y una ruta de tres etapas: registra, deriva y da seguimiento.</desc>
                <defs>
                    <linearGradient id="paper-edge" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#eef8ff"/><stop offset="1" stop-color="#c6deef"/></linearGradient>
                    <linearGradient id="folder-front" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#187bbd"/><stop offset="1" stop-color="#105386"/></linearGradient>
                    <filter id="paper-shadow" x="-30%" y="-20%" width="160%" height="160%"><feDropShadow dx="0" dy="12" stdDeviation="12" flood-color="#020f1b" flood-opacity=".32"/></filter>
                    <clipPath id="sheet-clip"><rect x="74" y="25" width="192" height="224" rx="8"/></clipPath>
                </defs>
                <path d="M31 97H289M31 157H289M31 217H289" stroke="#4f87ae" stroke-opacity=".1"/>
                <rect x="59" y="40" width="192" height="224" rx="8" fill="#83b5d7" transform="rotate(-7 155 152)" opacity=".4"/>
                <g filter="url(#paper-shadow)">
                    <rect x="74" y="25" width="192" height="224" rx="8" fill="url(#paper-edge)"/>
                    <path d="M234 25v30h32" fill="#a8cce4"/>
                    <rect x="92" y="46" width="28" height="30" rx="4" fill="#d3e7f5"/>
                    <path d="M100 55h12m-12 6h12m-12 6h7" stroke="#1376b9" stroke-width="2" stroke-linecap="round"/>
                    <text x="132" y="57" fill="#0b3556" font-family="Inter, sans-serif" font-size="10" font-weight="700">Expediente digital</text>
                    <text x="132" y="72" fill="#48738d" font-family="Inter, sans-serif" font-size="8">Gestión de trámites</text>
                    <path d="M94 94h151" stroke="#bed8e8"/>
                    <g fill="#aecbdc"><rect x="94" y="110" width="130" height="5" rx="2"/><rect x="94" y="124" width="151" height="5" rx="2"/><rect x="94" y="138" width="109" height="5" rx="2"/></g>
                    <g stroke="#1680bc" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m95 166 3 3 6-7m-9 24 3 3 6-7m-9 24 3 3 6-7"/></g>
                    <g fill="#aecbdc"><rect x="113" y="163" width="115" height="4" rx="2"/><rect x="113" y="183" width="100" height="4" rx="2"/><rect x="113" y="203" width="120" height="4" rx="2"/></g>
                    <g clip-path="url(#sheet-clip)"><rect x="74" y="100" width="192" height="2" fill="#298fcd" opacity=".3"><animate attributeName="y" values="100;219;100" dur="9s" repeatCount="indefinite"/></rect></g>
                </g>
                <path d="M45 211v-27q0-7 7-7h65l19 22h128q10 0 8 11l-12 56q-2 9-12 9H61q-9 0-11-9Z" fill="url(#folder-front)" stroke="#4da0cf" stroke-width="1"/>
                <path d="M51 211h214" stroke="#8bc7e6" stroke-opacity=".4"/>
                <text x="68" y="244" fill="#fff" font-family="Inter, sans-serif" font-size="11" font-weight="600">Cada etapa, bajo control.</text>
                <g transform="translate(242 263)"><circle r="22" fill="#edf8ff" stroke="#acd4ed"/><path d="m-9 0 6 6 12-13" fill="none" stroke="#1079bd" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></g>
                <path d="M51 308H268" stroke="#477593" stroke-width="1.5"/>
                <path d="M51 308H268" stroke="#76c9ff" stroke-width="2" stroke-dasharray="35 182"><animate attributeName="stroke-dashoffset" values="217;0" dur="6s" repeatCount="indefinite"/></path>
                <circle cx="51" cy="308" r="5" fill="#ff4b56"/><circle cx="160" cy="308" r="5" fill="#82c8f3"/><circle cx="268" cy="308" r="5" fill="#82c8f3"/>
            </svg>
            <ol class="document-stages" aria-label="Etapas de gestión"><li><span>01</span>Registra</li><li><span>02</span>Deriva</li><li><span>03</span>Da seguimiento</li></ol>
            <div class="orbital-footer"><img class="banner-brand-emblem" src="{{ asset('images/nexosigtram-documental-emblema.png') }}" alt="" width="44" height="44"><p class="orbital-caption"><strong>SIGTRAM</strong>Sistema de Gestión de Trámites<br><span class="company-credit">Desarrollado por Nexo</span></p></div>
        </aside>


        <!-- RIGHT COLUMN: FORM -->
        <!-- Zona blanca pura con tipografía limpia y minimalista -->
        <div class="login-form-panel w-full flex flex-col p-6 sm:p-10 lg:px-14 lg:py-10 relative">

            <!-- Header Right: Logo -->
            <div class="login-brand flex flex-col items-center justify-center w-full max-w-[360px] mx-auto">
                <!-- Identidad Nexosigtram -->
                <img src="{{ asset('images/nexosigtram-documental-logo.png') }}" class="nexo-form-logo" alt="Nexo SIGTRAM"><p class="login-product-name">Sistema de Gestión de Trámites</p>
            </div>

            <!-- Form Container -->
            <div class="login-form-body relative w-full max-w-[360px] mx-auto">

                <!-- ==================== LOGIN ==================== -->
                <div id="form-login" class="form-container form-active">

                    @if ($errors->any())
                        <!-- Mitigación de enumeración de usuarios: Mensaje Genérico RNF-03 -->
                        <div class="mb-6 bg-red-50 border border-red-100 p-3 rounded-xl flex items-start gap-3 animate-shake" role="alert">
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5 text-sm" aria-hidden="true"></i>
                            <div>
                                <p class="text-xs font-bold text-red-800">Error de autenticación</p>
                                <p class="text-[11px] font-medium text-red-600 mt-0.5">El correo o la contraseña son incorrectos.</p>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.post') }}" class="flex flex-col gap-5" id="loginForm" aria-label="Iniciar sesión" novalidate onsubmit="return handleLoginSubmit(event, this)">
                        @csrf

                        <!-- Inputs limpios con borde redondeado estilo iOS/Referencia (Mínimo 44px para RNF-11) -->
                        <div class="login-field relative">
                            <label for="email" class="field-label">Usuario</label>
                            <i class="fas fa-user login-field-icon" aria-hidden="true"></i>
                            <input type="text" id="email" name="usuario" value="{{ old('usuario') }}" required autocomplete="username" autofocus aria-required="true"
                                class="w-full px-4 py-3.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-[13px] font-semibold text-slate-800 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none placeholder:text-slate-400 placeholder:font-normal shadow-sm"
                                placeholder="Usuario" oninput="clearEmailError()">
                            <p id="email-error" class="hidden absolute -bottom-4 left-2 text-[10px] font-bold text-red-500" role="alert"></p>
                        </div>

                        <div class="login-field relative">
                            <label for="password" class="field-label">Contraseña</label>
                            <i class="fas fa-lock login-field-icon" aria-hidden="true"></i>
                            <input type="password" id="password" name="password" required autocomplete="current-password" aria-required="true"
                                class="w-full px-4 py-3.5 min-h-[44px] pr-12 bg-white border border-slate-200 rounded-xl text-[13px] font-semibold text-slate-800 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none placeholder:text-slate-400 placeholder:font-normal shadow-sm"
                                placeholder="Contraseña">
                            <!-- Botón Toggle de Contraseña (RF-18) -->
                            <button type="button" onclick="togglePasswordVisibility('password', 'eye-icon')" class="password-toggle flex items-center justify-center text-slate-400 hover:text-red-500 transition-colors" aria-label="Alternar visibilidad de contraseña">
                                <i class="fas fa-eye" id="eye-icon" aria-hidden="true"></i>
                            </button>

                            <!-- Alerta de Caps Lock (RF-20) - Posicionado absolutamente para evitar saltos de línea -->
                            <div id="caps-warning" class="opacity-0 transition-opacity absolute -bottom-4 left-2 flex items-center gap-1.5 pointer-events-none" aria-live="polite">
                                <i class="fas fa-arrow-up text-amber-500 text-[10px]"></i>
                                <span class="text-[10px] font-bold text-amber-600">Bloq Mayús activado</span>
                            </div>
                        </div>

                        <!-- Fila de Recordarme -->
                        <div class="flex items-center pt-2">
                            <!-- Checkbox Recordarme (RF-02) -->
                            <label class="flex items-center gap-2 cursor-pointer group/check">
                                <input type="checkbox" name="remember" class="custom-checkbox focus:ring-red-500">
                                <span class="text-[11px] font-semibold text-slate-500 group-hover/check:text-slate-800 transition-colors">Recordarme</span>
                            </label>
                        </div>

                        <!-- Acceso con la identidad visual de Nexosigtram -->
                        <button type="submit" class="login-submit">
                            <span class="btn-text">Iniciar sesión</span>
                            <span class="submit-portal" aria-hidden="true">
                                <svg viewBox="0 0 60 60" fill="none">
                                    <circle cx="30" cy="30" r="28" stroke="#9bbbd6" stroke-width="1"/>
                                    <g class="portal-orbit"><path d="M8 13A28 28 0 0 1 48 8M52 47A28 28 0 0 1 12 52" stroke="#1d70b7" stroke-width="2.5" stroke-linecap="round"/><circle cx="8" cy="13" r="2.5" fill="#f43f49"/><circle cx="52" cy="47" r="2.5" fill="#1d70b7"/></g>
                                    <circle cx="30" cy="30" r="22" class="portal-disc"/>

                                    <path class="submit-arrow" d="M21 30h18m-7-7 7 7-7 7" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <i class="fas fa-spinner fa-spin hidden btn-loading"></i>
                            </span>
                        </button>
                    </form>
                </div>

                <!-- ==================== REGISTRO ==================== -->
                <div id="form-register" class="form-container form-hidden">
                    <div class="text-left mb-8">
                        <h2 class="text-[32px] font-black text-slate-900 tracking-tight leading-none mb-2">Registro</h2>
                        <p class="text-[13px] font-medium text-slate-500">Solicitud de acceso a SIGTRAM</p>
                    </div>

                    <form class="space-y-4" onsubmit="event.preventDefault(); alert('Solicitud enviada. Contacte con TI.');">
                        <div>
                            <input type="text" required class="w-full px-4 py-3.5 bg-white border border-slate-200 rounded-xl text-[13px] font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none placeholder:text-slate-400 placeholder:font-normal shadow-sm" placeholder="Usuario o DNI">
                        </div>
                        <div>
                            <input type="email" required class="w-full px-4 py-3.5 bg-white border border-slate-200 rounded-xl text-[13px] font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none placeholder:text-slate-400 placeholder:font-normal shadow-sm" placeholder="Correo Electrónico">
                        </div>

                        <button type="submit" class="w-full py-3.5 px-4 bg-slate-900 hover:bg-slate-800 text-white text-[13px] font-bold rounded-xl shadow-lg transition-all active:scale-[0.98] mt-6">
                            Enviar Solicitud
                        </button>
                    </form>

                    <div class="mt-8 text-center">
                        <button type="button" onclick="toggleForms()" class="text-slate-500 font-bold hover:text-slate-800 transition-all text-[12px] flex items-center justify-center gap-2 mx-auto">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>

        // El movimiento se detiene al salir de pantalla o al solicitar menos animación.
        const orbitalScene = document.getElementById('nexo-orbital-scene');
        const animatedBackground = document.querySelector('.nexo-page-background');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let orbitalVisible = false;
        function syncOrbitalMotion() {
            const paused = reducedMotion.matches || !orbitalVisible || document.hidden;
            if (paused) orbitalScene.pauseAnimations(); else orbitalScene.unpauseAnimations();
            animatedBackground.style.animationPlayState = document.hidden || reducedMotion.matches ? 'paused' : 'running';
        }
        new IntersectionObserver(entries => { orbitalVisible = entries[0].isIntersecting; syncOrbitalMotion(); }).observe(orbitalScene);
        reducedMotion.addEventListener('change', syncOrbitalMotion);
        document.addEventListener('visibilitychange', syncOrbitalMotion);
        syncOrbitalMotion();

        // Toggle Login / Register views
        function toggleForms() {
            const formLogin = document.getElementById('form-login');
            const formRegister = document.getElementById('form-register');

            if (formLogin.classList.contains('form-active')) {
                formLogin.classList.replace('form-active', 'form-hidden');
                formRegister.classList.replace('form-hidden', 'form-active');
            } else {
                formRegister.classList.replace('form-active', 'form-hidden');
                formLogin.classList.replace('form-hidden', 'form-active');
            }
        }

        // RF-18: Toggle Password Visibility
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // RF-20: Detección de Caps Lock
        document.getElementById('password').addEventListener('keyup', function(e) {
            const warning = document.getElementById('caps-warning');
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                warning.classList.remove('opacity-0');
                warning.classList.add('opacity-100');
            } else {
                warning.classList.remove('opacity-100');
                warning.classList.add('opacity-0');
            }
        });

        // RF-17: Limpiar error al escribir
        function clearEmailError() {
            const errSpan = document.getElementById('email-error');
            const emailInput = document.getElementById('email');
            errSpan.classList.add('hidden');
            emailInput.classList.remove('border-red-500', 'ring-red-500/20');
        }

        // Validación JS y Estado de Carga (Loading)
        function handleLoginSubmit(e, form) {
            e.preventDefault(); // Prevenir envío automático para validar

            const emailInput = document.getElementById('email');
            const errSpan = document.getElementById('email-error');

            // Validación básica: no vacío y mínimo 3 caracteres (soporta 'administrador' o DNI)
            if (emailInput.value.trim().length < 3) {
                errSpan.textContent = 'Por favor, ingrese un usuario válido.';
                errSpan.classList.remove('hidden');
                emailInput.classList.add('border-red-500', 'ring-red-500/20');
                emailInput.focus();
                return false;
            }

            // RNF-10: Estado de carga (Deshabilitar botón y mostrar spinner)
            const btn = form.querySelector('button[type="submit"]');
            const text = btn.querySelector('.btn-text');
            const loading = btn.querySelector('.btn-loading');

            btn.disabled = true; // Deshabilitar inmediatamente para evitar multi-click
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            text.innerText = 'Autenticando...';
            if (loading) loading.classList.remove('hidden');

            // Si todo está correcto, enviar el formulario
            form.submit();
            return true;
        }
    </script>
</body>
</html>
