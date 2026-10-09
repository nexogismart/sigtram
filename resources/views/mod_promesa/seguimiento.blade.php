<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seguimiento de Compromiso</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_promesa/css/app.css') }}?v=3">
</head>
<body>
<div class="popup-wrap">
    <table class="tablebordecero">
        <tr>
            <td class="title_td">SEGUIMIENTO DE COMPROMISO</td>
        </tr>
    </table>

    <div id="conteSeguimiento">Cargando...</div>

    <table class="tablebordecero" style="width: 100%; margin-top: 10px;" id="tablaNuevaActividad">
        <tr>
            <td class="title_td" id="tituloActividad" style="text-align:left; font-size: 13px;">NUEVA ACTIVIDAD</td>
        </tr>
    </table>
    <table style="width: 100%;">
        <col style="width: 24%;">
        <col style="width: 2%;">
        <col style="width: 74%;">
        <tr>
            <td class="lbl">Descripción</td>
            <td>:</td>
            <td><textarea id="actividad" style="width: 95%; height: 45px;"></textarea></td>
        </tr>
        <tr>
            <td class="lbl">Fecha Inicio</td>
            <td>:</td>
            <td><input type="date" id="fe_inicio" style="width: 140px;"/></td>
        </tr>
        <tr>
            <td class="lbl">Fecha Fin</td>
            <td>:</td>
            <td><input type="date" id="fe_final" style="width: 140px;"/></td>
        </tr>
    </table>
    <div id="mensajeSeguimiento" class="msg-ok" style="display: none;"></div>
    <table class="tablebordecero" style="width: 100%; margin-top: 6px;">
        <tr>
            <td style="text-align: right;">
                <input type="button" id="btn_cancelar_extender" value="Cancelar extensión" class="boton black" style="display: none;"/>
                <input type="button" id="btn_guardar_actividad" value="Guardar" class="boton green"/>
            </td>
        </tr>
    </table>

    <table class="tablebordecero" style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="text-align: center;">
                <input type="button" id="btn_finalizar_seguimiento" value="Finalizar Seguimiento" class="boton green"/>
            </td>
        </tr>
    </table>
</div>

<script>
    const ROUTES = @json($routes);
    const ID_PROMESA = @json($idPromesa);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let extendiendoId = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function fetchJson(url, options) {
        options = options || {};
        try {
            const resp = await fetch(url, options);
            return await resp.json();
        } catch (e) {
            console.error(e);
            return { success: false, error: 'Error de comunicación.' };
        }
    }

    function claseSemaforo(estado) {
        if (estado === 'EN EJECUCION') return 'verde';
        if (estado === 'FINALIZADO') return 'rojo';
        return 'naranja';
    }

    function notificarPromesas() {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({ promesa: 'reload-promesas' }, '*');
        }
    }

    async function cargarSeguimiento() {
        const cont = document.getElementById('conteSeguimiento');
        cont.innerHTML = 'Cargando...';

        const resultado = await fetchJson(ROUTES.listar_seguimiento + '?id_promesa=' + ID_PROMESA, { headers: { Accept: 'application/json' } });
        if (!resultado.success) {
            cont.innerHTML = '<p>' + escapeHtml(resultado.error || 'No se pudo listar el seguimiento.') + '</p>';
            return;
        }

        const items = resultado.data || [];
        let html = '<div class="CSSTableGenerator"><table><thead><tr>';
        html += '<th style="width: 110px;">Semáforo</th><th>Descripción</th><th>Fecha Inicio</th><th>Fecha Fin</th>';
        html += '<th title="Anular">A</th><th title="Extender">E</th><th title="Finalizar">F</th>';
        html += '</tr></thead><tbody>';

        if (items.length === 0) {
            html += '<tr><td colspan="7" style="text-align:center">Sin actividades registradas</td></tr>';
        } else {
            items.forEach(function (item) {
                html += '<tr>';
                html += '<td class="semaforo-celda ' + claseSemaforo(item.estado) + '">' + escapeHtml(item.estado) + '</td>';
                html += '<td>' + escapeHtml(item.actividad) + '</td>';
                html += '<td style="text-align:center">' + escapeHtml(item.fecha_inicio || '') + '</td>';
                html += '<td style="text-align:center">' + escapeHtml(item.fecha_final || '') + '</td>';

                if (item.puede_eliminar) {
                    html += '<td style="text-align:center"><button type="button" class="boton black btn-anular" data-id="' + item.id_actividad + '">A</button></td>';
                } else {
                    html += '<td style="text-align:center; color:#999;">A</td>';
                }

                if (item.puede_extender) {
                    html += '<td style="text-align:center"><button type="button" class="boton gray btn-extender" data-id="' + item.id_actividad + '">E</button></td>';
                } else {
                    html += '<td style="text-align:center; color:#999;">E</td>';
                }

                if (item.puede_finalizar) {
                    html += '<td style="text-align:center"><button type="button" class="boton green btn-finalizar" data-id="' + item.id_actividad + '">F</button></td>';
                } else {
                    html += '<td style="text-align:center; color:#999;">F</td>';
                }

                html += '</tr>';
            });
        }
        html += '</tbody></table></div>';
        cont.innerHTML = html;

        cont.querySelectorAll('.btn-anular').forEach(function (btn) {
            btn.addEventListener('click', function () {
                anularActividad(btn.getAttribute('data-id'));
            });
        });
        cont.querySelectorAll('.btn-extender').forEach(function (btn) {
            btn.addEventListener('click', function () {
                iniciarExtension(btn.getAttribute('data-id'));
            });
        });
        cont.querySelectorAll('.btn-finalizar').forEach(function (btn) {
            btn.addEventListener('click', function () {
                finalizarActividad(btn.getAttribute('data-id'));
            });
        });
    }

    async function anularActividad(idActividad) {
        if (!confirm('¿Anular esta actividad?')) return;
        const resultado = await fetchJson(ROUTES.eliminar_actividad, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body: new URLSearchParams({ id_actividad: idActividad }).toString(),
        });
        if (!resultado.success) {
            alert(resultado.error || 'No se pudo anular la actividad.');
            return;
        }
        cargarSeguimiento();
    }

    async function finalizarActividad(idActividad) {
        if (!confirm('¿Finalizar esta actividad?')) return;
        const resultado = await fetchJson(ROUTES.finalizar_actividad, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body: new URLSearchParams({ id_actividad: idActividad }).toString(),
        });
        if (!resultado.success) {
            alert(resultado.error || 'No se pudo finalizar la actividad.');
            return;
        }
        cargarSeguimiento();
    }

    function iniciarExtension(idActividad) {
        extendiendoId = idActividad;
        document.getElementById('tituloActividad').textContent = 'EXTENDER ACTIVIDAD';
        document.getElementById('btn_guardar_actividad').value = 'Extender';
        document.getElementById('btn_cancelar_extender').style.display = '';
        document.getElementById('actividad').value = '';
        document.getElementById('fe_inicio').value = '';
        document.getElementById('fe_final').value = '';
        document.getElementById('tablaNuevaActividad').scrollIntoView({ behavior: 'smooth' });
    }

    function cancelarExtension() {
        extendiendoId = null;
        document.getElementById('tituloActividad').textContent = 'NUEVA ACTIVIDAD';
        document.getElementById('btn_guardar_actividad').value = 'Guardar';
        document.getElementById('btn_cancelar_extender').style.display = 'none';
        document.getElementById('actividad').value = '';
        document.getElementById('fe_inicio').value = '';
        document.getElementById('fe_final').value = '';
    }

    async function guardarActividad() {
        const actividad = document.getElementById('actividad').value.trim();
        const feInicio = document.getElementById('fe_inicio').value;
        const feFinal = document.getElementById('fe_final').value;

        if (!actividad) {
            alert('Ingrese la descripción de la actividad.');
            return;
        }
        if (!feInicio || !feFinal) {
            alert('Ingrese la fecha de inicio y fin.');
            return;
        }

        const btn = document.getElementById('btn_guardar_actividad');
        btn.disabled = true;

        let resultado;
        if (extendiendoId) {
            resultado = await fetchJson(ROUTES.extender_actividad, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: new URLSearchParams({
                    id_actividad: extendiendoId,
                    id_promesa: ID_PROMESA,
                    actividad: actividad,
                    fe_inicio: feInicio,
                    fe_final: feFinal,
                }).toString(),
            });
        } else {
            resultado = await fetchJson(ROUTES.guardar_actividad, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: new URLSearchParams({
                    id_promesa: ID_PROMESA,
                    actividad: actividad,
                    fe_inicio: feInicio,
                    fe_final: feFinal,
                }).toString(),
            });
        }

        btn.disabled = false;

        if (!resultado.success) {
            alert(resultado.error || 'No se pudo guardar la actividad.');
            return;
        }

        cancelarExtension();
        cargarSeguimiento();
    }

    async function finalizarSeguimiento() {
        if (!confirm('¿Finalizar el seguimiento de este compromiso?')) return;

        const resultado = await fetchJson(ROUTES.finalizar_seguimiento, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body: new URLSearchParams({ id_promesa: ID_PROMESA }).toString(),
        });

        if (!resultado.success) {
            alert(resultado.error || 'No se pudo finalizar el seguimiento.');
            return;
        }

        const msg = document.getElementById('mensajeSeguimiento');
        msg.textContent = 'EL SEGUIMIENTO FUE FINALIZADO';
        msg.style.display = 'block';
        notificarPromesas();
        cargarSeguimiento();
        setTimeout(function () {
            msg.style.display = 'none';
        }, 2000);
    }

    document.getElementById('btn_guardar_actividad').addEventListener('click', guardarActividad);
    document.getElementById('btn_cancelar_extender').addEventListener('click', cancelarExtension);
    document.getElementById('btn_finalizar_seguimiento').addEventListener('click', finalizarSeguimiento);

    cargarSeguimiento();
</script>
</body>
</html>
