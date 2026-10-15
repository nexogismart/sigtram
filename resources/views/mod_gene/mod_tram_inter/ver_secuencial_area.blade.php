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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        :root {
            --ink: #0f172a;
            --ink-2: #334155;
            --muted: #64748b;
            --faint: #94a3b8;
            --line: #e6ecf2;
            --bg: #f6f8fb;
            --card: #ffffff;
            --accent: #334155;
            --accent-soft: #f1f5f9;
            --accent-dark: #0f172a;
            --ok: #475569;
            --bad: #ef4444;
            --radius: 14px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            background:
                radial-gradient(900px 420px at 8% -10%, rgba(51, 65, 85, .08), transparent 55%),
                radial-gradient(700px 380px at 100% 0%, rgba(15,23,42,.04), transparent 50%),
                var(--bg);
            color: var(--ink-2);
            font-size: 14px;
            min-height: 100vh;
        }
        h1 { font-family: "Outfit", "Figtree", sans-serif; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 1.25rem 1.5rem 3rem; }
        .top { margin-bottom: 1rem; }
        .top h1 { margin: 0; font-size: 1.45rem; font-weight: 800; letter-spacing: -.02em; color: var(--ink); }
        .top h1 span { color: var(--accent); }
        .top p { margin: .25rem 0 0; font-size: .78rem; font-weight: 600; color: var(--muted); }

        .panel {
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            box-shadow: 0 1px 2px rgba(15,23,42,.04); margin-bottom: 1rem; overflow: hidden;
        }
        .panel__head {
            padding: .7rem 1rem; border-bottom: 1px solid var(--line);
            font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--accent-dark);
            background: #f8fafc;
        }
        .panel__body { padding: 1rem 1.1rem 1.15rem; }

        .grid { display: grid; grid-template-columns: 140px 14px 1fr; gap: .55rem .4rem; align-items: start; }
        .lbl { font-size: .78rem; font-weight: 800; color: var(--muted); padding-top: .65rem; }
        .sep { color: var(--faint); padding-top: .65rem; font-weight: 700; }
        .val { font-weight: 700; color: var(--ink); padding-top: .65rem; }

        select, textarea {
            width: 100%; padding: .6rem .75rem; border: 1px solid var(--line); border-radius: 10px;
            background: #fff; color: var(--ink); font: inherit; font-weight: 600; outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        select:focus, textarea:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px rgba(51, 65, 85, .15);
        }
        textarea { min-height: 88px; resize: vertical; }

        .actions { display: flex; justify-content: center; gap: .75rem; margin-top: 1rem; }
        .btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .6rem 1.1rem; border-radius: 10px; border: 1px solid var(--line);
            background: var(--card); color: var(--ink-2); font: inherit; font-size: .78rem; font-weight: 700;
            cursor: pointer; transition: background .15s, border-color .15s, color .15s;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent-dark); }
        .btn--primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn--primary:hover { background: var(--accent-dark); border-color: var(--accent-dark); color: #fff; }

        .flash {
            margin-bottom: 1rem; padding: .7rem 1rem; border-radius: 10px;
            background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font-weight: 700; font-size: .85rem;
        }
        .err-banner {
            margin-bottom: 1rem; padding: .7rem 1rem; border-radius: 10px;
            background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-weight: 700; font-size: .85rem;
            display: none;
        }

        #conte_lista_documentos { min-height: 2rem; }
        .loading {
            text-align: center; padding: 1rem; color: var(--accent-dark);
            font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
        }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gene.mod_tram_inter.ver_secuencial_area'])
</head>
<body data-sigtram-screen="mod_gene.mod_tram_inter.ver_secuencial_area" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gene.mod_tram_inter.ver_secuencial_area'])
<div class="wrap">
    <div class="top">
        <h1>Secuencial del <span>Área</span></h1>
        <p>Generación de numeración interna por tipo de documento</p>
    </div>

    @if (session('ok'))
        <div class="flash">{{ session('ok') }}</div>
    @endif
    <div id="err-banner" class="err-banner"></div>

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
                        <select name="sel_cdgo_anio" id="sel_cdgo_anio">
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
                        <select name="sel_cdgo_tpo_trmte" id="sel_cdgo_tpo_trmte">
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
                    <button type="button" class="btn" onclick="window.close()">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn--primary" id="btn_generar">
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

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const URL_LISTA = @json(route('mod_tram_inter.ver_secuencial_area.lista'));
    const selAnio = document.getElementById('sel_cdgo_anio');
    const selTipo = document.getElementById('sel_cdgo_tpo_trmte');
    const box = document.getElementById('conte_lista_documentos');
    const err = document.getElementById('err-banner');

    function cargarLista() {
        const tpo = selTipo.value;
        const anio = selAnio.value;
        if (!tpo || tpo === '0' || !anio) {
            box.innerHTML = '<p style="text-align:center;color:#94a3b8;font-weight:700;margin:0">Seleccione un tipo de documento.</p>';
            return;
        }
        box.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
        $.ajax({
            url: URL_LISTA,
            method: 'POST',
            data: { _token: CSRF, cdgo_tpo_trmte: tpo, cdgo_anio: anio },
            success: function (res) {
                box.innerHTML = res.html || '';
            },
            error: function () {
                box.innerHTML = '<p style="text-align:center;color:#ef4444;font-weight:700">Error al cargar la lista.</p>';
            }
        });
    }

    function validaGenerar() {
        err.style.display = 'none';
        if (!selTipo.value || selTipo.value === '0') {
            err.textContent = 'Seleccione un tipo de documento.';
            err.style.display = 'block';
            return false;
        }
        return true;
    }

    selAnio.addEventListener('change', cargarLista);
    selTipo.addEventListener('change', cargarLista);
    document.getElementById('btn_generar').addEventListener('click', function () {
        if (!validaGenerar()) return;
        document.getElementById('formSecuencial').submit();
    });

    // Carga inicial (como el antiguo ready)
    document.addEventListener('DOMContentLoaded', cargarLista);
})();
</script>
</body>
</html>
