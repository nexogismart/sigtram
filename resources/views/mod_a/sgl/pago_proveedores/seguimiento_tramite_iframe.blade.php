<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Histórico del trámite</title>
    <style>
        :root {
            --ink: #0f172a;
            --ink-2: #334155;
            --muted: #64748b;
            --line: #e2e8f0;
            --bg: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: .65rem .75rem 1.25rem;
            font-family: Verdana, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: var(--ink-2);
            background: #fff;
        }
        .tit-documento {
            text-align: center;
            margin: .35rem 0 .85rem;
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
        }
        .tit-documento span {
            background: khaki;
            padding: .15rem .45rem;
        }
        .msg {
            text-align: center;
            color: #b91c1c;
            font-weight: 700;
            padding: 1.5rem;
        }
        /* Estilo cercano a CSSTableGenerator del antiguo */
        .CSSTableGenerator {
            width: 98%;
            margin: 0 auto;
            border: 1px solid var(--line);
            border-radius: 6px;
            overflow: hidden;
        }
        .CSSTableGenerator table {
            width: 100%;
            border-collapse: collapse;
        }
        .CSSTableGenerator th {
            background: #0f172a;
            color: #e2e8f0;
            font-size: 11px;
            font-weight: 800;
            padding: .45rem .35rem;
            text-align: center;
            white-space: nowrap;
        }
        .CSSTableGenerator td {
            padding: .35rem .4rem;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }
        .CSSTableGenerator tr.fila_1 { background: #fff; }
        .CSSTableGenerator tr.fila_2 { background: #f8fafc; }
        .CSSTableGenerator tr:hover td { background: #f1f5f9; }
        .c { text-align: center; }
        .j { text-align: justify; }
        .te { text-transform: lowercase; font-weight: 700; color: var(--ink); }
        .ico {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            color: var(--muted);
            text-decoration: none;
            cursor: pointer;
            border: none;
            background: transparent;
            padding: 0;
        }
        .ico:hover { color: var(--ink); }
        .tip {
            position: relative;
            display: inline-block;
        }
        .tip .tipbox {
            display: none;
            position: absolute;
            z-index: 20;
            right: 0;
            top: 120%;
            min-width: 220px;
            max-width: 320px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(15,23,42,.18);
            padding: .45rem .55rem;
            text-align: left;
            font-size: 11px;
        }
        .tip:hover .tipbox { display: block; }
        .tipbox table { width: 100%; border-collapse: collapse; }
        .tipbox th { background: #f1f5f9; color: var(--ink); font-size: 10px; padding: .25rem; text-align: left; }
        .tipbox td { border: none; padding: .2rem .25rem; font-size: 11px; }
        .foot {
            text-align: right;
            padding: .55rem .45rem;
            font-weight: 700;
            color: var(--muted);
        }
        .empty { text-align: center; color: var(--muted); font-weight: 600; padding: 1.2rem; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_a.sgl.pago_proveedores.seguimiento_tramite_iframe'])
</head>
<body data-sigtram-screen="mod_a.sgl.pago_proveedores.seguimiento_tramite_iframe" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_a.sgl.pago_proveedores.seguimiento_tramite_iframe'])
@if (! $ok)
    <p class="msg">{{ $error ?: 'No se pudo cargar el seguimiento.' }}</p>
@else
    <div class="tit-documento">
        <br>
        <span>Histórico de {{ $titulo }}</span>
        <br>
    </div>

    <div class="CSSTableGenerator">
        <table>
            <thead>
                <tr>
                    <th style="width:9%">Entrada</th>
                    <th style="width:3%">T.E</th>
                    <th style="width:11%">Area Salida</th>
                    <th style="width:10%">Estado</th>
                    <th style="width:11%">Area Envio</th>
                    <th style="width:50%">Observaciones</th>
                    <th style="width:2%">U</th>
                    <th style="width:2%">A</th>
                    <th style="width:2%">D</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $i => $item)
                @php $fila = ($i % 2 === 0) ? 'fila_1' : 'fila_2'; @endphp
                <tr class="{{ $fila }}">
                    <td class="c">{{ $item['entrada'] }}</td>
                    <td class="c te">{{ $item['te'] }}</td>
                    <td class="c" title="{{ $item['area_salida_full'] }}">{{ $item['area_salida'] }}</td>
                    <td class="c">{{ $item['estado'] }}</td>
                    <td class="c" title="{{ $item['area_envio_full'] }}">{{ $item['area_envio'] }}</td>
                    <td class="j">{{ $item['observaciones'] }}</td>
                    <td class="c">
                        <span class="tip" title="{{ $item['usuario_origen'] }}">
                            <span class="ico" aria-label="Usuarios">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <span class="tipbox">
                                <table>
                                    <tr><th>Usuario Origen</th><th>Usuario Destino</th></tr>
                                    @if ($item['usuarios_destino'] === [])
                                        <tr>
                                            <td>{{ $item['usuario_origen'] !== '' ? $item['usuario_origen'] : '—' }}</td>
                                            <td>—</td>
                                        </tr>
                                    @else
                                        @foreach ($item['usuarios_destino'] as $di => $dest)
                                            <tr>
                                                <td>{{ $di === 0 ? ($item['usuario_origen'] !== '' ? $item['usuario_origen'] : '—') : '' }}</td>
                                                <td>{{ $dest }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </table>
                            </span>
                        </span>
                    </td>
                    <td class="c">
                        @if (! empty($item['adjunto']) && ! empty($item['id']))
                            <a class="ico" href="{{ route('mod_tram_inter.descargar', ['movimiento_id' => $item['id']]) }}"
                               target="_blank" title="Ver adjunto" aria-label="Adjunto">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            </a>
                        @endif
                    </td>
                    <td class="c">
                        <a class="ico" href="{{ route('sgl.buscar_requerimiento_pago.voucher_movimiento', ['id_tram' => $item['id']]) }}"
                           onclick="window.open(this.href, this.target, 'scrollbars=auto,width=470,height=310'); return false;"
                           target="_blank" title="Detalle del movimiento" aria-label="Detalle">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="empty">Sin movimientos en el histórico.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif
</body>
</html>
