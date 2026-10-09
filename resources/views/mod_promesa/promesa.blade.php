<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $id ? 'Modificar Compromiso' : 'Ingresar Compromiso' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_promesa/css/app.css') }}?v=3">
</head>
<body>
<div class="popup-wrap">
    <form id="frmPromesa" onsubmit="return false;">
        <table class="tablebordecero">
            <tr>
                <td class="title_td" id="tituloPromesa">{{ $id ? 'MODIFICAR COMPROMISO' : 'INGRESAR COMPROMISO' }}</td>
            </tr>
        </table>
        <table style="width: 100%; margin-top: 8px;">
            <col style="width: 24%;">
            <col style="width: 2%;">
            <col style="width: 74%;">
            <tr>
                <td class="lbl">DNI</td>
                <td>:</td>
                <td><input type="text" id="dni" style="width: 150px;"/></td>
            </tr>
            <tr>
                <td class="lbl">Nombres del dirigente</td>
                <td>:</td>
                <td><input type="text" id="nombres" style="width: 95%;"/></td>
            </tr>
            <tr>
                <td class="lbl">Teléfono</td>
                <td>:</td>
                <td><input type="text" id="telefono" style="width: 150px;"/></td>
            </tr>
            <tr>
                <td class="lbl">Descripción de Pedido</td>
                <td>:</td>
                <td><textarea id="pedido" style="width: 95%; height: 60px;"></textarea></td>
            </tr>
            <tr>
                <td class="lbl">Unidad Orgánica</td>
                <td>:</td>
                <td>
                    <select id="unidad" style="width: 95%;">
                        <option value="0">--Área--</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="lbl">Asignado</td>
                <td>:</td>
                <td>
                    <select id="responsable" style="width: 95%;">
                        <option value="0">--Asignado--</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="lbl">Descripción de Compromiso</td>
                <td>:</td>
                <td><textarea id="des_compromiso" style="width: 95%; height: 60px;"></textarea></td>
            </tr>
            <tr>
                <td class="lbl">Fecha de cumplimiento</td>
                <td>:</td>
                <td><input type="date" id="fecha_cumplimiento" style="width: 140px;"/></td>
            </tr>
        </table>
        <div id="mensajePromesa" class="msg-ok" style="display: none;"></div>
        <hr/>
        <table class="tablebordecero" style="width: 50%; margin: 8px auto;">
            <tr>
                <td><input type="button" id="btn_cerrar" value="Cerrar" class="boton black"/></td>
                <td><input type="button" id="btn_guardar" value="Guardar" class="boton green"/></td>
            </tr>
        </table>
    </form>
</div>

<script>
    const ROUTES = @json($routes);
    const ID_PROMESA = @json($id);
    const ID_REUNION = @json($idReunion);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.getElementById('btn_cerrar').addEventListener('click', function () {
        window.close();
    });

    function mostrarMensaje(texto) {
        const form = document.getElementById('frmPromesa');
        const msg = document.getElementById('mensajePromesa');
        form.querySelectorAll('table, hr').forEach(function (el) { el.style.display = 'none'; });
        msg.textContent = texto;
        msg.style.display = 'block';
    }

    function notificarYCerrar(texto) {
        mostrarMensaje(texto);
        setTimeout(function () {
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({ promesa: 'reload-promesas' }, '*');
            }
            window.close();
        }, 1000);
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

    async function cargarAreas(areaSeleccionada) {
        const resultado = await fetchJson(ROUTES.catalogos, { headers: { Accept: 'application/json' } });
        const select = document.getElementById('unidad');
        select.innerHTML = '<option value="0">--Área--</option>';
        if (resultado.success) {
            (resultado.data || []).forEach(function (area) {
                const opt = document.createElement('option');
                opt.value = area.cdgo_area;
                opt.textContent = area.de_area;
                select.appendChild(opt);
            });
        }
        if (areaSeleccionada) {
            select.value = String(areaSeleccionada);
        }
    }

    async function cargarPersonal(areaId, responsableSeleccionado) {
        const select = document.getElementById('responsable');
        select.innerHTML = '<option value="0">--Asignado--</option>';
        if (!areaId || areaId === '0') {
            return;
        }
        const resultado = await fetchJson(ROUTES.personal + '?area=' + areaId, { headers: { Accept: 'application/json' } });
        if (resultado.success) {
            (resultado.data || []).forEach(function (persona) {
                const opt = document.createElement('option');
                opt.value = persona.cdgo_usrios;
                opt.textContent = persona.no_usrios;
                select.appendChild(opt);
            });
        }
        if (responsableSeleccionado) {
            select.value = String(responsableSeleccionado);
        }
    }

    document.getElementById('unidad').addEventListener('change', function () {
        cargarPersonal(this.value, null);
    });

    async function cargarDatos() {
        if (ID_PROMESA) {
            const resultado = await fetchJson(ROUTES.obtener_promesa + '?id=' + ID_PROMESA, { headers: { Accept: 'application/json' } });
            if (!resultado.success) {
                alert(resultado.error || 'No se pudo cargar el compromiso.');
                await cargarAreas(null);
                return;
            }
            const data = resultado.data;
            document.getElementById('dni').value = data.dni_dirigente || '';
            document.getElementById('nombres').value = data.dirigente || '';
            document.getElementById('telefono').value = data.telefono || '';
            document.getElementById('pedido').value = data.pedido || '';
            document.getElementById('des_compromiso').value = data.compromiso || '';
            document.getElementById('fecha_cumplimiento').value = data.fec_cumplimiento || '';

            await cargarAreas(data.area_id);
            await cargarPersonal(data.area_id, data.responsable_id);
        } else {
            await cargarAreas(null);
        }
    }

    async function guardar() {
        const dni = document.getElementById('dni').value.trim();
        const nombres = document.getElementById('nombres').value.trim();
        const pedido = document.getElementById('pedido').value.trim();
        const unidad = document.getElementById('unidad').value;
        const desCompromiso = document.getElementById('des_compromiso').value.trim();
        const fechaCumplimiento = document.getElementById('fecha_cumplimiento').value;

        if (!nombres) {
            alert('Ingrese el nombre del dirigente.');
            return;
        }
        if (!pedido) {
            alert('Ingrese la descripción del pedido.');
            return;
        }
        if (!unidad || unidad === '0') {
            alert('Seleccione la Unidad Orgánica.');
            return;
        }

        const responsableSelect = document.getElementById('responsable');
        const responsableId = responsableSelect.value;
        const responsableNombre = responsableSelect.selectedIndex >= 0 ? responsableSelect.options[responsableSelect.selectedIndex].textContent : '';

        const body = new URLSearchParams();
        body.append('id_reunion', ID_REUNION || '');
        body.append('dni', dni);
        body.append('nombres', nombres);
        body.append('telefono', document.getElementById('telefono').value.trim());
        body.append('pedido', pedido);
        body.append('unidad', unidad);
        body.append('responsable', responsableId && responsableId !== '0' ? responsableId : '');
        body.append('responsable_nombre', responsableId && responsableId !== '0' ? responsableNombre : '');
        body.append('des_compromiso', desCompromiso);
        body.append('fecha_cumplimiento', fechaCumplimiento);

        let url = ROUTES.guardar_promesa;
        if (ID_PROMESA) {
            body.append('id', ID_PROMESA);
            url = ROUTES.actualizar_promesa;
        }

        document.getElementById('btn_guardar').disabled = true;
        const resultado = await fetchJson(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: body.toString(),
        });

        if (!resultado.success) {
            alert(resultado.error || 'No se pudo guardar el compromiso.');
            document.getElementById('btn_guardar').disabled = false;
            return;
        }

        notificarYCerrar(ID_PROMESA ? 'LA PROMESA FUE MODIFICADA' : 'LA PROMESA FUE INGRESADA');
    }

    document.getElementById('btn_guardar').addEventListener('click', guardar);

    cargarDatos();
</script>
</body>
</html>
