<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte general de pago a proveedores</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_a/sgl/pago_proveedores/css/app.css') }}?v=3">
    @include('components.sigtram.module-head', ['screenKey' => 'mod_a.sgl.pago_proveedores.reporte.reporte_gerencial'])
</head>
<body data-sigtram-screen="mod_a.sgl.pago_proveedores.reporte.reporte_gerencial" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_a.sgl.pago_proveedores.reporte.reporte_gerencial'])
    <main class="app">
        <div class="app__title-row">
            <h1>REPORTE GENERAL DE PAGO A PROVEEDORES</h1>
        </div>

        <section class="card">
            <form class="filters" method="get" action="{{ $actionUrl }}" autocomplete="off">
                <div class="field">
                    <label for="fe_inicio">Desde</label>
                    <input id="fe_inicio" name="fe_inicio" type="date" value="{{ $feInicio }}" required>
                </div>
                <div class="field">
                    <label for="fe_fin">Hasta</label>
                    <input id="fe_fin" name="fe_fin" type="date" value="{{ $feFin }}" required>
                </div>
                <div class="filters__actions">
                    <button class="btn btn--primary" type="submit">Buscar</button>
                </div>
            </form>

            @if($error)
                <p class="table-empty">{{ $error }}</p>
            @else
                <div class="table-wrap">
                    <table class="table table--report">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>U/O</th>
                                <th class="text-center">Proveedores</th>
                                <th class="text-center">Proveedores<br>con O/S</th>
                                <th class="text-center">Conformidad<br>U/O</th>
                                <th class="text-center">Comprometido<br>Abastecimiento</th>
                                <th class="text-center">Devengado<br>Contabilidad</th>
                                <th class="text-center">Girado<br>Tesorería</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($filas as $fila)
                                <tr>
                                    <td>{{ $fila['n'] }}</td>
                                    <td>{{ $fila['uo'] }}</td>
                                    <td class="text-center">{{ $fila['proveedores'] }}</td>
                                    <td class="text-center">{{ $fila['os_pct'] }}%<br>{{ $fila['os'] }}</td>
                                    <td class="text-center">{{ $fila['conformidad_pct'] }}%<br>{{ $fila['conformidad'] }}</td>
                                    <td class="text-center">{{ $fila['comprometido_pct'] }}%<br>{{ $fila['comprometido'] }}</td>
                                    <td class="text-center">{{ $fila['devengado_pct'] }}%<br>{{ $fila['devengado'] }}</td>
                                    <td class="text-center">{{ $fila['girado_pct'] }}%<br>{{ $fila['girado'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="table-empty">No hay áreas registradas.</td>
                                </tr>
                            @endforelse

                            @if($totales)
                                <tr class="table-row--total">
                                    <td colspan="2" class="text-right"><strong>TOTAL</strong></td>
                                    <td class="text-center"><strong>{{ $totales['proveedores'] }}</strong></td>
                                    <td class="text-center"><strong>{{ $totales['os_pct'] }}%<br>{{ $totales['os'] }}</strong></td>
                                    <td class="text-center"><strong>{{ $totales['conformidad_pct'] }}%<br>{{ $totales['conformidad'] }}</strong></td>
                                    <td class="text-center"><strong>{{ $totales['comprometido_pct'] }}%<br>{{ $totales['comprometido'] }}</strong></td>
                                    <td class="text-center"><strong>{{ $totales['devengado_pct'] }}%<br>{{ $totales['devengado'] }}</strong></td>
                                    <td class="text-center"><strong>{{ $totales['girado_pct'] }}%<br>{{ $totales['girado'] }}</strong></td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</body>
</html>
