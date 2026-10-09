/* ── API Dispositivo Seg (Laravel) ── */
var DS_CSRF = (function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") : "";
})();

var DS_ROUTES = window.DS_ROUTES || {};

function dsApiFetch(url, opciones) {
    opciones = opciones || {};
    opciones.credentials = "same-origin";
    opciones.headers = Object.assign({
        "X-Requested-With": "XMLHttpRequest",
        "Accept": "application/json"
    }, opciones.headers || {});
    return fetch(url, opciones).then(function (res) {
        return res.text().then(function (text) {
            var data = {};
            if (text && text.length > 0) {
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error(res.status === 500 ? "Error interno del servidor. La respuesta no es JSON válido." : "Respuesta inválida.");
                }
            }
            if (!res.ok || !data.success) {
                var msg = (data && data.error && String(data.error).trim()) || ("Error en la solicitud (código " + res.status + ").");
                if (data && data.debug) { msg += " " + String(data.debug); }
                throw new Error(msg);
            }
            return data;
        });
    });
}

function dsApiListar(params) {
    return dsApiFetch((DS_ROUTES.listar || "") + "?" + new URLSearchParams(params).toString())
        .then(function (d) { return d.data; });
}

function dsApiDetalle(id) {
    return dsApiFetch((DS_ROUTES.detalle || "") + "?id=" + encodeURIComponent(id))
        .then(function (d) { return d.data; });
}

function dsApiPost(url, datos) {
    return dsApiFetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": DS_CSRF
        },
        body: JSON.stringify(datos)
    });
}

function dsApiGuardar(datos)    { return dsApiPost(DS_ROUTES.guardar || "", datos); }
function dsApiActualizar(datos) { return dsApiPost(DS_ROUTES.actualizar || "", datos); }
function dsApiEliminar(id)      { return dsApiPost(DS_ROUTES.eliminar || "", { id: id }); }

function dsApiSectores() {
    return dsApiFetch(DS_ROUTES.sectores || "").then(function (d) { return d.data; });
}

function dsApiSubSectores(sectorId) {
    var url = DS_ROUTES.subsectores || "";
    if (sectorId != null && sectorId !== "") {
        url += (url.indexOf("?") >= 0 ? "&" : "?") + "sector_id=" + encodeURIComponent(sectorId);
    }
    return dsApiFetch(url).then(function (d) { return d.data || []; });
}
