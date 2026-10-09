<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $id ? 'Modificar Reunión' : 'Ingresar Reunión' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_promesa/css/app.css') }}?v=3">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"/>
</head>
<body>
<div class="popup-wrap">
    <form id="frmReunion" onsubmit="return false;" enctype="multipart/form-data">
        <table class="tablebordecero">
            <tr>
                <td class="title_td" id="tituloReunion">{{ $id ? 'MODIFICAR REUNION' : 'INGRESAR REUNION' }}</td>
            </tr>
        </table>
        <table style="width: 100%; margin-top: 8px;">
            <col style="width: 22%;">
            <col style="width: 2%;">
            <col style="width: 76%;">
            <tr>
                <td class="lbl">Nombre/Ubicación</td>
                <td>:</td>
                <td><input type="text" name="no_ubicacion" id="no_ubicacion" placeholder="AA.HH / URB / CONJUNTO" style="width: 95%;"/></td>
            </tr>
            <tr>
                <td class="lbl">Fecha/Hora</td>
                <td>:</td>
                <td>
                    <input type="date" name="fecha" id="fecha" style="width: 140px;"/>
                    <input type="time" name="hora" id="hora" style="width: 100px;"/>
                </td>
            </tr>
            <tr>
                <td class="lbl">Coordenadas</td>
                <td>:</td>
                <td><input type="text" name="coor" id="coor" placeholder="Seleccione la ubicación en el MAPA" style="width: 95%;" readonly/></td>
            </tr>
            <tr>
                <td colspan="3"><div id="map"></div></td>
            </tr>
            <tr id="filaAdjunto" style="display: none;">
                <td class="lbl">Adjuntar PDF</td>
                <td>:</td>
                <td><input type="file" name="file_url_rnn" id="file_url_rnn" accept="application/pdf"/></td>
            </tr>
        </table>
        <div id="mensajeReunion" class="msg-ok" style="display: none;"></div>
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
    const ID_REUNION = @json($id);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.getElementById('btn_cerrar').addEventListener('click', function () {
        window.close();
    });

    function mostrarMensaje(texto) {
        const form = document.getElementById('frmReunion');
        const msg = document.getElementById('mensajeReunion');
        form.querySelectorAll('table, hr').forEach(function (el) { el.style.display = 'none'; });
        msg.textContent = texto;
        msg.style.display = 'block';
    }

    function notificarYCerrar(texto) {
        mostrarMensaje(texto);
        setTimeout(function () {
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({ promesa: 'reload-lugares' }, '*');
            }
            window.close();
        }, 1000);
    }
</script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
    const PP_LAT = -11.8651344;
    const PP_LNG = -77.0742974;
    let map = L.map('map').setView([PP_LAT, PP_LNG], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    let marker = null;

    function ponerMarcador(lat, lng) {
        if (marker) {
            map.removeLayer(marker);
        }
        marker = L.marker([lat, lng]).addTo(map);
        document.getElementById('coor').value = lat + ',' + lng;
    }

    map.on('click', function (e) {
        ponerMarcador(e.latlng.lat, e.latlng.lng);
    });

    async function cargarDatos() {
        if (!ID_REUNION) {
            return;
        }
        document.getElementById('filaAdjunto').style.display = '';

        try {
            const resp = await fetch(ROUTES.obtener_lugar + '?id=' + ID_REUNION, { headers: { Accept: 'application/json' } });
            const resultado = await resp.json();
            if (!resultado.success) {
                alert(resultado.error || 'No se pudo cargar la reunión.');
                return;
            }
            const data = resultado.data;
            document.getElementById('no_ubicacion').value = data.zona_nombre || '';
            document.getElementById('fecha').value = data.fecha_rnn || '';
            document.getElementById('hora').value = data.hora_rnn || '';
            document.getElementById('coor').value = data.zona_long_lat || '';

            const longLat = String(data.zona_long_lat || '').split(',');
            if (longLat.length === 2) {
                const lat = parseFloat(longLat[0]);
                const lng = parseFloat(longLat[1]);
                if (!isNaN(lat) && !isNaN(lng)) {
                    map.setView([lat, lng], 18);
                    ponerMarcador(lat, lng);
                }
            }
        } catch (e) {
            console.error(e);
            alert('Error de comunicación al cargar la reunión.');
        }
    }

    async function guardar() {
        const nombre = document.getElementById('no_ubicacion').value.trim();
        const fecha = document.getElementById('fecha').value.trim();
        const hora = document.getElementById('hora').value.trim();
        const coor = document.getElementById('coor').value.trim();

        if (!nombre) {
            alert('Ingrese el Nombre/Ubicación.');
            return;
        }
        if (!fecha) {
            alert('Ingrese la Fecha de la reunión.');
            return;
        }
        if (!coor) {
            alert('Seleccione la ubicación en el mapa.');
            return;
        }

        const formData = new FormData();
        formData.append('zona_nombre', nombre);
        formData.append('fecha_rnn', fecha);
        formData.append('hora_rnn', hora);
        formData.append('zona_long_lat', coor);

        const fileInput = document.getElementById('file_url_rnn');
        if (fileInput.files && fileInput.files[0]) {
            formData.append('file_url_rnn', fileInput.files[0]);
        }

        let url = ROUTES.guardar_lugar;
        if (ID_REUNION) {
            formData.append('id', ID_REUNION);
            url = ROUTES.actualizar_lugar;
        }

        document.getElementById('btn_guardar').disabled = true;
        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: formData,
            });
            const resultado = await resp.json();
            if (!resultado.success) {
                alert(resultado.error || 'No se pudo guardar la reunión.');
                document.getElementById('btn_guardar').disabled = false;
                return;
            }
            notificarYCerrar(ID_REUNION ? 'LA REUNION FUE MODIFICADA' : 'LA REUNION FUE INGRESADA');
        } catch (e) {
            console.error(e);
            alert('Error de comunicación al guardar.');
            document.getElementById('btn_guardar').disabled = false;
        }
    }

    document.getElementById('btn_guardar').addEventListener('click', guardar);

    cargarDatos();
</script>
</body>
</html>
