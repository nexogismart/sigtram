const ROUTES = window.PROMESA_ROUTES || {};
const ACCESO_PLENO = window.PROMESA_ACCESO_PLENO === true;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

let paginaLugar = 1;
let paginaPromesa = 1;
let reunionActual = { id: null, nombre: "" };

function promesaUrl(key, query) {
    const base = ROUTES[key] || "";
    if (!query) return base;
    const params = new URLSearchParams();
    Object.entries(query).forEach(([k, v]) => {
        if (v === null || v === undefined || v === "") return;
        if (Array.isArray(v)) {
            v.forEach((item) => params.append(k + "[]", item));
        } else {
            params.append(k, v);
        }
    });
    const qs = params.toString();
    return qs ? base + "?" + qs : base;
}

async function fetchJson(url, options) {
    options = options || {};
    const config = {
        method: options.method || "GET",
        headers: { Accept: "application/json" },
    };
    if (options.body) {
        config.headers["Content-Type"] = "application/x-www-form-urlencoded";
        config.headers["X-CSRF-TOKEN"] = csrfToken;
        config.body = new URLSearchParams(options.body).toString();
    } else if (config.method !== "GET") {
        config.headers["X-CSRF-TOKEN"] = csrfToken;
    }
    try {
        const response = await fetch(url, config);
        return await response.json();
    } catch (e) {
        console.error(e);
        return { success: false, error: "Error de comunicación." };
    }
}

function escapeHtml(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function ventanaPop(url, nombre, alto, ancho) {
    const left = Math.max(0, (screen.width - ancho) / 2);
    const top = Math.max(0, (screen.height - alto) / 2);
    window.open(
        url,
        nombre || "popup",
        "width=" + ancho + ",height=" + alto + ",left=" + left + ",top=" + top + ",scrollbars=yes,resizable=yes"
    );
}

function openPopupWithSelectedCheckboxes() {
    const checkboxes = document.querySelectorAll("#checkboxTable input[type='checkbox']:checked");
    const selectedIds = [];
    checkboxes.forEach(function (checkbox) {
        selectedIds.push(checkbox.value);
    });
    if (selectedIds.length === 0) {
        alert("Seleccione al menos un compromiso.");
        return;
    }
    const params = new URLSearchParams();
    selectedIds.forEach(function (id) {
        params.append("ids[]", id);
    });
    window.open(
        promesaUrl("imprimir") + "?" + params.toString(),
        "popupWindow",
        "width=800,height=1000,scrollbars=yes"
    );
}

function renderPaginacion(pagina, totalPaginas, total, onPagina) {
    let html = '<div class="pagination" style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">';
    html += '<div>';
    if (pagina > 1) {
        html += '<button type="button" class="btn btn--ghost btn--sm" data-pag="' + (pagina - 1) + '">Anterior</button> ';
    }
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === pagina) {
            html += '<button type="button" class="btn btn--primary btn--sm" disabled>' + i + "</button> ";
        } else {
            html += '<button type="button" class="btn btn--ghost btn--sm" data-pag="' + i + '">' + i + "</button> ";
        }
    }
    if (pagina < totalPaginas) {
        html += '<button type="button" class="btn btn--ghost btn--sm" data-pag="' + (pagina + 1) + '">Siguiente</button>';
    }
    html += '</div><div>Total: <b>' + total + "</b></div></div>";
    return html;
}

async function openModal(id) {
    const modal = document.getElementById("detailModal");
    const modalBody = document.getElementById("modalBody");
    modalBody.innerHTML = "Cargando...";
    modal.style.display = "block";

    const resultado = await fetchJson(promesaUrl("detalle_lugar", { id_reunion: id }));
    if (!resultado.success) {
        modalBody.innerHTML = "<p>" + escapeHtml(resultado.error || "No se pudo cargar.") + "</p>";
        return;
    }

    const items = resultado.data || [];
    let html = '<h3 style="margin-top:0">Avance por área</h3><div class="table-wrap"><table class="table"><thead><tr>';
    html += "<th>#</th><th>Área</th><th>Total</th><th>Finalizados</th><th>%</th>";
    html += "</tr></thead><tbody>";
    if (items.length === 0) {
        html += '<tr><td colspan="5" style="text-align:center">Sin datos</td></tr>';
    } else {
        items.forEach(function (item, idx) {
            html += "<tr>";
            html += "<td>" + (idx + 1) + "</td>";
            html += "<td>" + escapeHtml(item.de_area || item.dc_area || "") + "</td>";
            html += '<td style="text-align:center">' + (item.total || 0) + "</td>";
            html += '<td style="text-align:center">' + (item.finalizados || 0) + "</td>";
            html += '<td style="text-align:center">' + (item.porcentaje || 0) + "%</td>";
            html += "</tr>";
        });
    }
    html += "</tbody></table></div>";
    modalBody.innerHTML = html;
}

async function cargarListaLugar(pagina) {
    paginaLugar = pagina || 1;
    const cont = document.getElementById("conte_listaLugar");
    cont.innerHTML = "<p>Cargando...</p>";

    const resultado = await fetchJson(promesaUrl("listar_lugares", {
        palabra: document.getElementById("txt_palabra").value,
        desde: document.getElementById("txt_fechadesde").value,
        hasta: document.getElementById("txt_fechahasta").value,
        pagina: paginaLugar,
        porPagina: document.getElementById("txt_cantidad").value || 7,
    }));

    if (!resultado.success) {
        cont.innerHTML = "<p>" + escapeHtml(resultado.error || "Error al listar.") + "</p>";
        return;
    }

    const data = resultado.data || {};
    const items = data.items || [];
    const total = data.total || 0;
    const totalPaginas = data.total_paginas || 1;
    const cols = ACCESO_PLENO ? 11 : 9;

    let html = '<div class="table-wrap"><table class="table"><thead><tr>';
    html += "<th>#</th><th>AA.HH Urb. Gru.</th><th>Ubicación en MAPA</th><th>Total</th><th>Finalizado</th><th>%</th><th>Hora</th><th>Fecha</th><th title=\"Detalles\">D</th>";
    if (ACCESO_PLENO) {
        html += '<th title="Modificar">M</th><th title="Anular">A</th>';
    }
    html += "</tr></thead><tbody>";

    if (items.length === 0) {
        html += '<tr><td colspan="' + cols + '" style="text-align:center">Sin registros</td></tr>';
    } else {
        items.forEach(function (item, idx) {
            const n = ((paginaLugar - 1) * (data.por_pagina || 7)) + idx + 1;
            const maps = item.maps_url
                ? '<a href="' + escapeHtml(item.maps_url) + '" target="_blank" onclick="event.stopPropagation()">' + escapeHtml(item.zona_nombre) + "</a>"
                : escapeHtml(item.zona_nombre);

            html += '<tr class="row-clickable" data-id="' + item.id + '" data-nombre="' + escapeHtml(item.zona_nombre) + '">';
            html += '<td style="text-align:center">' + n + "</td>";
            html += "<td>" + escapeHtml(item.zona_nombre) + "</td>";
            html += "<td>" + maps + "</td>";
            html += '<td style="text-align:center">' + (item.total || 0) + "</td>";
            html += '<td style="text-align:center">' + (item.finalizado || 0) + "</td>";
            html += '<td style="text-align:center">' + (item.porcentaje || 0) + "%</td>";
            html += '<td style="text-align:center">' + escapeHtml(item.hora_rnn || "") + "</td>";
            html += '<td style="text-align:center">' + escapeHtml(item.fecha_rnn || "") + "</td>";
            html += '<td style="text-align:center"><img src="' + escapeHtml(ROUTES.icono_nuevo || "") + '" width="23" height="18" class="ico18Pointer btn-detalle" data-id="' + item.id + '" title="Detalles" alt="D"/></td>';
            if (ACCESO_PLENO) {
                html += '<td style="text-align:center"><img src="' + escapeHtml(ROUTES.icono_editar || "") + '" class="ico18Pointer btn-edit-lugar" data-id="' + item.id + '" alt="M"/></td>';
                html += '<td style="text-align:center"><img src="' + escapeHtml(ROUTES.icono_cancel || "") + '" class="ico18Pointer btn-del-lugar" data-id="' + item.id + '" alt="A"/></td>';
            }
            html += "</tr>";
        });
    }
    html += "</tbody></table></div>";
    html += renderPaginacion(paginaLugar, totalPaginas, total);

    cont.innerHTML = html;

    cont.querySelectorAll("tbody tr[data-id]").forEach(function (tr) {
        tr.addEventListener("click", function (e) {
            if (e.target.closest("a, img, button")) return;
            cargarPromesasLugar(tr.getAttribute("data-id"), tr.getAttribute("data-nombre"), 1);
        });
    });
    cont.querySelectorAll(".btn-detalle").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.stopPropagation();
            openModal(btn.getAttribute("data-id"));
        });
    });
    cont.querySelectorAll(".btn-edit-lugar").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.stopPropagation();
            ventanaPop(promesaUrl("form_reunion", { id: btn.getAttribute("data-id") }), "Reuniones", 550, 750);
        });
    });
    cont.querySelectorAll(".btn-del-lugar").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.stopPropagation();
            ventanaPop(promesaUrl("form_eliminar_reunion", { id: btn.getAttribute("data-id") }), "Eliminar", 400, 200);
        });
    });
    cont.querySelectorAll(".pagination [data-pag]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            cargarListaLugar(parseInt(btn.getAttribute("data-pag"), 10));
        });
    });
}

async function cargarPromesasLugar(idReunion, nombre, pagina) {
    reunionActual = { id: idReunion, nombre: nombre || "" };
    paginaPromesa = pagina || 1;
    document.getElementById("cardPromesas").style.display = "";
    const cont = document.getElementById("conte_promesaLugar");
    cont.innerHTML = "<p>Cargando...</p>";

    const criterioSel = document.getElementById("sel_criteri");
    const palabraInp = document.getElementById("txt_palabr");
    const cantInp = document.getElementById("txt_cantida");

    const criterio = criterioSel ? criterioSel.value : "sem_nom_dirigente";
    const palabra = palabraInp ? palabraInp.value : "";
    const cantidad = cantInp ? cantInp.value : 8;

    const resultado = await fetchJson(promesaUrl("listar_promesas", {
        id_reunion: idReunion,
        criterio: criterio,
        palabra: palabra,
        pagina: paginaPromesa,
        porPagina: cantidad,
    }));

    if (!resultado.success) {
        cont.innerHTML = "<p>" + escapeHtml(resultado.error || "Error al listar compromisos.") + "</p>";
        return;
    }

    const data = resultado.data || {};
    const items = data.items || [];
    const total = data.total || 0;
    const totalPaginas = data.total_paginas || 1;

    let html = '<div class="table-meta">';
    html += '<h2 class="card__title">Compromisos de ' + escapeHtml(nombre) + "</h2>";
    html += '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
    if (ACCESO_PLENO) {
        html += '<button type="button" class="btn btn--ghost btn--sm" id="btn_nuevo_promesa">Nuevo</button>';
        html += '<button type="button" class="btn btn--ghost btn--sm" id="btn_imprimir_reunion">Imprimir</button>';
    }
    html += "</div></div>";

    html += '<form class="filters" id="frmPromesas" onsubmit="return false;">';
    html += '<div class="field" style="max-width:180px;"><label for="sel_criteri">Opción</label>';
    html += '<select id="sel_criteri"><option value="sem_nom_dirigente"' + (criterio === "sem_nom_dirigente" ? " selected" : "") + ">DIRIGENTE</option>";
    html += '<option value="sem_desc_pedido"' + (criterio === "sem_desc_pedido" ? " selected" : "") + ">PETICION</option>";
    html += '<option value="de_area"' + (criterio === "de_area" ? " selected" : "") + ">AREA</option></select></div>";
    html += '<div class="field"><label for="txt_palabr">Palabra</label>';
    html += '<input id="txt_palabr" type="text" value="' + escapeHtml(palabra) + '" placeholder="escriba en MAYUSCULAS"></div>';
    html += '<div class="field" style="max-width:90px;"><label for="txt_cantida">Registros</label>';
    html += '<input id="txt_cantida" type="text" value="' + escapeHtml(cantidad) + '"></div>';
    html += '<div class="filters__actions"><button type="button" class="btn btn--primary" id="btn_consultar_promesa">Consultar</button></div>';
    html += "</form>";

    const cols = ACCESO_PLENO ? 10 : 8;
    html += '<div class="table-wrap"><table class="table" id="checkboxTable"><thead><tr>';
    html += '<th>#</th><th><input type="checkbox" value="' + escapeHtml(idReunion) + '" checked></th>';
    html += "<th>Dirigente</th><th>Petición</th><th>Área</th><th>Encargado</th><th>F. Comp.</th><th title=\"Seguimiento\">S</th>";
    if (ACCESO_PLENO) {
        html += '<th title="Editar">E</th><th title="Anular">A</th>';
    }
    html += "</tr></thead><tbody>";

    if (items.length === 0) {
        html += '<tr><td colspan="' + cols + '" style="text-align:center">Sin compromisos</td></tr>';
    } else {
        items.forEach(function (item, idx) {
            const n = ((paginaPromesa - 1) * (data.por_pagina || 8)) + idx + 1;
            html += "<tr>";
            html += "<td>" + n + "</td>";
            if (item.cumplido) {
                html += '<td class="cumplido-check"><input type="checkbox" value="' + item.id_promesa + '"></td>';
            } else {
                html += "<td></td>";
            }
            html += "<td>" + escapeHtml(item.dirigente) + "</td>";
            html += "<td>" + escapeHtml(item.pedido) + "</td>";
            html += '<td title="' + escapeHtml(item.de_area || "") + '">' + escapeHtml(item.dc_area || "") + "</td>";
            html += "<td>" + escapeHtml(item.no_usrios || "") + "</td>";
            html += "<td>" + escapeHtml(item.fec_compromiso || "") + "</td>";
            html += '<td style="text-align:center"><img src="' + escapeHtml(ROUTES.icono_reporte || "") + '" class="ico18Pointer btn-seg" data-id="' + item.id_promesa + '" alt="S"/></td>';
            if (ACCESO_PLENO) {
                html += '<td style="text-align:center"><img src="' + escapeHtml(ROUTES.icono_editar || "") + '" class="ico18Pointer btn-edit-promesa" data-id="' + item.id_promesa + '" alt="E"/></td>';
                html += '<td style="text-align:center"><img src="' + escapeHtml(ROUTES.icono_cancel || "") + '" class="ico18Pointer btn-del-promesa" data-id="' + item.id_promesa + '" alt="A"/></td>';
            }
            html += "</tr>";
        });
    }
    html += "</tbody></table></div>";
    html += '<div style="margin-top:10px"><button type="button" class="btn btn--ghost" id="btn_imprimir_sel">Imprimir Seleccionados</button></div>';
    html += renderPaginacion(paginaPromesa, totalPaginas, total);

    cont.innerHTML = html;

    document.getElementById("btn_nuevo_promesa")?.addEventListener("click", function () {
        ventanaPop(promesaUrl("form_promesa", { id_reunion: reunionActual.id }), "Promesa", 700, 600);
    });
    document.getElementById("btn_imprimir_reunion")?.addEventListener("click", function () {
        ventanaPop(promesaUrl("imprimir", { "ids[]": [reunionActual.id] }), "Imprimir", 1200, 700);
    });
    document.getElementById("btn_consultar_promesa")?.addEventListener("click", function () {
        cargarPromesasLugar(reunionActual.id, reunionActual.nombre, 1);
    });
    document.getElementById("btn_imprimir_sel")?.addEventListener("click", openPopupWithSelectedCheckboxes);

    cont.querySelectorAll(".btn-seg").forEach(function (btn) {
        btn.addEventListener("click", function () {
            ventanaPop(promesaUrl("form_seguimiento", { id_promesa: btn.getAttribute("data-id") }), "Seguimiento", 900, 500);
        });
    });
    cont.querySelectorAll(".btn-edit-promesa").forEach(function (btn) {
        btn.addEventListener("click", function () {
            ventanaPop(promesaUrl("form_promesa", { id: btn.getAttribute("data-id") }), "Promesa", 700, 500);
        });
    });
    cont.querySelectorAll(".btn-del-promesa").forEach(function (btn) {
        btn.addEventListener("click", function () {
            ventanaPop(promesaUrl("form_eliminar_promesa", { id: btn.getAttribute("data-id") }), "Eliminar", 400, 200);
        });
    });
    cont.querySelectorAll(".pagination [data-pag]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            cargarPromesasLugar(reunionActual.id, reunionActual.nombre, parseInt(btn.getAttribute("data-pag"), 10));
        });
    });
}

document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("btn_consultar")?.addEventListener("click", function () {
        cargarListaLugar(1);
    });
    document.getElementById("btn_nuevo")?.addEventListener("click", function () {
        ventanaPop(promesaUrl("form_reunion"), "Reuniones", 550, 750);
    });

    const modal = document.getElementById("detailModal");
    document.getElementById("detailModalClose")?.addEventListener("click", function () {
        modal.style.display = "none";
    });
    window.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });

    window.addEventListener("message", function (ev) {
        if (!ev.data || !ev.data.promesa) return;
        if (ev.data.promesa === "reload-lugares") {
            cargarListaLugar(paginaLugar);
            document.getElementById("conte_promesaLugar").innerHTML = "";
            document.getElementById("cardPromesas").style.display = "none";
        }
        if (ev.data.promesa === "reload-promesas" && reunionActual.id) {
            cargarPromesasLugar(reunionActual.id, reunionActual.nombre, paginaPromesa);
        }
    });

    cargarListaLugar(1);
});
