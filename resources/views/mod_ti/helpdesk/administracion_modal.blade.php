<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">

<title>Detalle Ticket</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

<style>

body{
    font-family:'Inter', sans-serif;
    background:#0f172a;
    margin:0;
    color:white;
}

.wrap{
    max-width:900px;
    margin:30px auto;
    background:#1e293b;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 8px 20px rgba(0,0,0,.25);
}

.header{
    background:#dc2626;
    padding:16px 20px;
    font-size:18px;
    font-weight:600;
}

.content{
    padding:20px;
}

.detalle{
    display:grid;
    grid-template-columns:220px 1fr;
    border-bottom:1px solid #334155;
}

.label{
    background:#273549;
    padding:12px;
    font-weight:600;
    border-bottom:1px solid #334155;
}

.valor{
    padding:12px;
    border-bottom:1px solid #334155;
    color:#cbd5e1;
}

.actions{
    display:flex;
    gap:10px;
    margin-top:25px;
}

.btn{
    border:none;
    padding:10px 18px;
    border-radius:8px;
    cursor:pointer;
    color:white;
    font-weight:600;
    transition:.2s;
}

.derivar{
    background:#16a34a;
}

.derivar:hover{
    background:#15803d;
}

.editar{
    background:#2563eb;
}

.editar:hover{
    background:#1d4ed8;
}

.cerrar{
    background:#dc2626;
}

.cerrar:hover{
    background:#b91c1c;
}

</style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_ti.helpdesk.administracion_modal'])
</head>
<body data-sigtram-screen="mod_ti.helpdesk.administracion_modal" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_ti.helpdesk.administracion_modal'])

<div class="wrap">
    <div class="header">
        Detalles del Ticket
    </div>

    <div class="content">

        <div class="detalle">
            <div class="label">ID</div>
        </div>

        <div class="detalle">
            <div class="label">Prioridad</div>
        </div>

        <div class="detalle">
            <div class="label">Razón</div>
        </div>

        <div class="detalle">
            <div class="label">Celular</div>
        </div>

        <div class="detalle">
            <div clas="label">Anydesk</div>
        </div>

        <div class="detalle">
            <div class="label">Asunto</div>
        </div>

        <div class="detalle">
            <div class="label">Detalle</div>
        </div>

        <div class="detalle">
            <div class="label">Solicitante</div>
        </div>

        <div class="detalle">
            <div class="label">Cargo</div>
        </div>

        <div class="detalle">
            <div class="label">Área</div>
        </div>

        <div class="detalle">
            <div class="label">Inicio Solicitud</div>
        </div>

        <div class="detalle">
            <div class="label">Atendido por</div>
        </div>

        <div class="actions">
            <button class="btn derivar">
                Derivar
            </button>

            <button class="btn editar">
                Editar
            </button>

            <button class="btn cerrar">
                Cerrar
            </button>

        </div>

    </div>
</div>
</body>
</html>