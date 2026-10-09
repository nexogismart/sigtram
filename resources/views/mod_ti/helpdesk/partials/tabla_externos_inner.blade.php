{{-- Fragmento: misma estructura que sigtram-antiguo tabla_externos.php --}}
@if ($tickets->isEmpty())
    <p class="hd-tabla-vacio" style="padding: 12px; color: #666; font-size: 14px;">No hay tickets en su bandeja.</p>
@else
    <div class="tablita-container">
        <table class="tablita">
            <thead class="tablita-header">
                <tr>
                    <th>id</th>
                    <th>asunto</th>
                    <th>detalle</th>
                    <th>Fecha y Hora</th>
                    <th>Solicitante</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tickets as $row)
                    <tr>
                        @if ($idLogin === \App\Http\Controllers\BandejaExternaTicketController::loginOtiSecretaria())
                            <td><a href="#" onclick="mostrarBandejaOtisecretaria({{ (int) $row->id }}); return false;">{{ $row->id }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOtisecretaria({{ (int) $row->id }}); return false;">{{ $row->asunto }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOtisecretaria({{ (int) $row->id }}); return false;">{{ $row->detalle }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOtisecretaria({{ (int) $row->id }}); return false;">{{ $row->fecha_fmt }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOtisecretaria({{ (int) $row->id }}); return false;">{{ $row->no_crto }}</a></td>
                            <td><button type="button" class="hd-btn-table" onclick="Calificar({{ (int) $row->id }})">Confirmar</button></td>
                        @elseif ($idLogin !== \App\Http\Controllers\BandejaExternaTicketController::loginOtiSecretaria() && $idLogin !== \App\Http\Controllers\BandejaExternaTicketController::loginExcluidoOti() && $areaId !== \App\Http\Controllers\BandejaExternaTicketController::areaOti())
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->id }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->asunto }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->detalle }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->fecha_fmt }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->no_crto }}</a></td>
                            <td>
                                @if ($row->situacion === 3)
                                    <button type="button" class="hd-btn-atencion" style="background-color: #eb5325;">En atencion</button>
                                @elseif ($row->situacion === 4)
                                    <button type="button" class="hd-btn-table" onclick="Calificar({{ (int) $row->id }})">Confirmar</button>
                                @endif
                            </td>
                        @elseif ($areaId === \App\Http\Controllers\BandejaExternaTicketController::areaOti() && $idLogin !== \App\Http\Controllers\BandejaExternaTicketController::loginHelpSpecial())
                            <td><a href="#" onclick="mostrarBandejaOti({{ (int) $row->id }}); return false;">{{ $row->id }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOti({{ (int) $row->id }}); return false;">{{ $row->asunto }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOti({{ (int) $row->id }}); return false;">{{ $row->detalle }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOti({{ (int) $row->id }}); return false;">{{ $row->fecha_fmt }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaOti({{ (int) $row->id }}); return false;">{{ $row->no_crto }}</a></td>
                            <td><button type="button" class="hd-btn-table" onclick="Calificar({{ (int) $row->id }})">Confirmar</button></td>
                        @elseif ($idLogin === \App\Http\Controllers\BandejaExternaTicketController::loginHelpSpecial())
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->id }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->asunto }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->detalle }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->fecha_fmt }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->no_crto }}</a></td>
                            <td>
                                @if ($row->situacion === 1)
                                    <button type="button" class="hd-btn-atencion" style="background-color: #eb5325;" onclick="Anular({{ (int) $row->id }})">Anular</button>
                                @elseif ($row->situacion === 3)
                                    <button type="button" class="hd-btn-atencion" style="background-color: #eb5325;">En atencion</button>
                                @elseif ($row->situacion === 4)
                                    <button type="button" class="hd-btn-table" onclick="Calificar({{ (int) $row->id }})">Confirmar</button>
                                @endif
                            </td>
                        @else
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->id }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->asunto }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->detalle }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->fecha_fmt }}</a></td>
                            <td><a href="#" onclick="mostrarBandejaExternos({{ (int) $row->id }}); return false;">{{ $row->no_crto }}</a></td>
                            <td>
                                @if ($row->situacion === 3)
                                    <button type="button" class="hd-btn-atencion" style="background-color: #eb5325;">En atencion</button>
                                @elseif ($row->situacion === 4)
                                    <button type="button" class="hd-btn-table" onclick="Calificar({{ (int) $row->id }})">Confirmar</button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
