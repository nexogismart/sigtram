<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eliminar Reunión</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_promesa/css/app.css') }}?v=3">
    @include('components.sigtram.module-head', ['screenKey' => 'mod_promesa.eliminar_reunion'])
</head>
<body data-sigtram-screen="mod_promesa.eliminar_reunion" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_promesa.eliminar_reunion'])
<div class="popup-wrap">
    <table class="tablebordecero" style="width: 100%">
        <tr>
            <td class="title_td">ELIMINAR REUNION</td>
        </tr>
    </table>
    <div id="conteConfirma" style="text-align: center; padding: 16px; font-size: 14px;">
        ¿Eliminar?
    </div>
    <div id="mensajeEliminar" class="msg-ok" style="display: none;"></div>
    <table class="tablebordecero" style="width: 50%; margin: 8px auto;" id="tablaBotones">
        <tr>
            <td><input type="button" id="btn_cerrar" value="Cerrar" class="boton black"/></td>
            <td><input type="button" id="btn_eliminar" value="Eliminar" class="boton green"/></td>
        </tr>
    </table>
</div>

<script>
    const ROUTES = @json($routes);
    const ID_REUNION = @json($id);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.getElementById('btn_cerrar').addEventListener('click', function () {
        window.close();
    });

    document.getElementById('btn_eliminar').addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        try {
            const resp = await fetch(ROUTES.eliminar_lugar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: new URLSearchParams({ id: ID_REUNION }).toString(),
            });
            const resultado = await resp.json();
            if (!resultado.success) {
                alert(resultado.error || 'No se pudo eliminar la reunión.');
                btn.disabled = false;
                return;
            }

            document.getElementById('conteConfirma').style.display = 'none';
            document.getElementById('tablaBotones').style.display = 'none';
            const msg = document.getElementById('mensajeEliminar');
            msg.textContent = 'LA REUNION FUE ELIMINADA';
            msg.style.display = 'block';

            setTimeout(function () {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({ promesa: 'reload-lugares' }, '*');
                }
                window.close();
            }, 1000);
        } catch (e) {
            console.error(e);
            alert('Error de comunicación al eliminar.');
            btn.disabled = false;
        }
    });
</script>
</body>
</html>
