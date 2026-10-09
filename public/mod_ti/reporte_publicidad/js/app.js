$(document).ready(function () {
  $.ajaxSetup({
    headers: {
      "X-CSRF-TOKEN": window.REPORTE_PUBLICIDAD?.csrfToken || "",
      Accept: "application/json",
    },
  });

  cargarReportes();

  $("#btnNuevoReporte").click(function () {
    abrirModalNuevo();
  });

  $("#btnCloseModal, #btnCancelModal").click(function () {
    cerrarModal();
  });

  $("#btnAddCuadro").click(function () {
    agregarCuadro();
  });

  $("#cuadrosContainer").on("click", ".btn-remove-cuadro", function () {
    if (!$(this).prop("disabled")) {
      $(this).closest(".cuadro-form").remove();
      actualizarIndicesCuadros();
    }
  });

  $("#reporteForm").submit(function (e) {
    e.preventDefault();
    guardarReporte();
  });

  $("#tablaReportes").on("click", ".btn-toggle", function () {
    const reporteId = $(this).data("reporte");
    const icon = $(this).find("i");

    if (icon.hasClass("fa-chevron-down")) {
      icon.removeClass("fa-chevron-down").addClass("fa-chevron-up");
      cargarCuadros(reporteId);
    } else {
      icon.removeClass("fa-chevron-up").addClass("fa-chevron-down");
      $(`#detalle-${reporteId}`).remove();
    }
  });

  $("#tablaReportes").on("click", ".btn-edit", function () {
    editarReporte($(this).data("reporte"));
  });

  $("#tablaReportes").on("click", ".btn-delete", function () {
    eliminarReporte($(this).data("reporte"));
  });

  $("#tablaReportes").on("click", ".btn-pdf", function () {
    generarPDF($(this).data("reporte"), $(this).data("count"));
  });
});

function route(key) {
  return window.REPORTE_PUBLICIDAD?.routes?.[key] || "";
}

function abrirModalNuevo() {
  $("#modalTitle").text("Nuevo Reporte");
  $("#modalMode").val("nuevo");
  $("#cdgo_reporte").val("");
  $("#reporteForm")[0].reset();
  $("#cuadrosContainer").empty();
  agregarCuadro();
  $("#reporteModal").removeAttr("hidden");
}

function abrirModalEditar(reporte, cuadros) {
  $("#modalTitle").text("Editar Reporte");
  $("#modalMode").val("editar");
  $("#cdgo_reporte").val(reporte.cdgo_reporte);
  $("#mdio_empleado").val(reporte.mdio_empleado);
  $("#ubicacion").val(reporte.ubicacion);
  $("#fech_ini").val(reporte.fech_ini);
  $("#fech_fin").val(reporte.fech_fin);
  $("#enlace").val(reporte.enlace);
  $("#fundamento").val(reporte.fundamento);

  $("#cuadrosContainer").empty();
  if (cuadros && cuadros.length > 0) {
    cuadros.forEach((cuadro, index) => agregarCuadro(index, cuadro));
  } else {
    agregarCuadro();
  }

  $("#reporteModal").removeAttr("hidden");
}

function cerrarModal() {
  $("#reporteModal").attr("hidden", "hidden");
}

function agregarCuadro(index = null, cuadro = null) {
  if (index === null) {
    index = $(".cuadro-form").length;
  }

  const nuevoCuadro = `
        <div class="cuadro-form" data-index="${index}">
            <div class="row g-2">
                <div class="col-auto" style="width: 4%;">
                    <h6 class="m-0">#${index + 1}</h6>
                </div>
                <div class="col-auto" style="width: 55%;">
                    <input type="text" class="form-control form-control-sm" name="cuadros[${index}][empresa]" value="${cuadro?.empresa || ""}" placeholder="Empresa" required>
                </div>
                <div class="col-auto" style="width: 10%;">
                    <input type="text" class="form-control form-control-sm" name="cuadros[${index}][medida]" value="${cuadro?.medida || ""}" placeholder="Medida" required>
                </div>
                <div class="col-auto" style="width: 10%;">
                    <input type="number" step="0.01" class="form-control form-control-sm" name="cuadros[${index}][cantidad]" value="${cuadro?.cantidad || ""}" placeholder="Cant" required>
                </div>
                <div class="col-auto" style="width: 13%;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" class="form-control form-control-sm" name="cuadros[${index}][inversion]" value="${cuadro?.inversion || ""}" placeholder="Inversión" required>
                    </div>
                </div>
                <div class="col-auto" style="width: 2%;">
                    <button type="button" class="btn btn-danger btn-sm btn-remove-cuadro" ${$(".cuadro-form").length === 0 ? "disabled" : ""}>
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            ${cuadro ? `<input type="hidden" name="cuadros[${index}][cdgo_cuadro]" value="${cuadro.cdgo_cuadro}">` : ""}
        </div>
    `;

  $("#cuadrosContainer").append(nuevoCuadro);

  if ($(".cuadro-form").length > 1) {
    $(".btn-remove-cuadro").prop("disabled", false);
  }
}

function actualizarIndicesCuadros() {
  $(".cuadro-form").each(function (index) {
    $(this).attr("data-index", index);
    $(this).find("h6").text(`#${index + 1}`);
    $(this)
      .find("input")
      .each(function () {
        const name = $(this).attr("name");
        if (name) {
          $(this).attr("name", name.replace(/cuadros\[\d+\]/, `cuadros[${index}]`));
        }
      });
  });

  $(".btn-remove-cuadro").prop("disabled", $(".cuadro-form").length <= 1);
}

function cargarReportes() {
  $.ajax({
    url: route("listar"),
    type: "GET",
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        renderizarReportes(response.data || []);
      } else {
        alert("Error: " + response.message);
      }
    },
    error: function () {
      alert("Error al conectar con el servidor");
    },
  });
}

function renderizarReportes(reportes) {
  if (!reportes.length) {
    $("#tablaReportes").html(
      '<tr><td colspan="7" class="text-center">No hay reportes disponibles</td></tr>',
    );
    return;
  }

  let html = "";
  let contador = reportes.length;
  reportes.forEach((reporte) => {
    html += `
            <tr>
                <td>
                    <button type="button" class="btn btn-sm btn-light rounded-circle btn-toggle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; min-width: 24px;" data-reporte="${reporte.cdgo_reporte}">
                        <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
                    </button>
                </td>
                <td>${contador--}</td>
                <td>${escapeHtml(reporte.mdio_empleado)}</td>
                <td>${escapeHtml(reporte.ubicacion)}</td>
                <td>${escapeHtml(reporte.fech_ini)}</td>
                <td>${escapeHtml(reporte.fech_fin)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary btn-accion btn-pdf rounded-circle shadow-sm" data-reporte="${reporte.cdgo_reporte}" data-count="${contador + 1}" title="Generar PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-success btn-accion btn-edit rounded-circle shadow-sm" data-reporte="${reporte.cdgo_reporte}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-accion btn-delete rounded-circle shadow-sm" data-reporte="${reporte.cdgo_reporte}" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
  });

  $("#tablaReportes").html(html);
}

function cargarCuadros(reporteId) {
  $.ajax({
    url: route("cuadros"),
    type: "GET",
    data: { reporte_id: reporteId },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        const data = response.data;
        const reporte = data.reporte;
        const cuadros = data.cuadros || [];

        const detalleRow = `
                    <tr id="detalle-${reporteId}">
                        <td colspan="7" class="p-0">
                            <div class="cuadro-detalle">
                                <h5>Cuadros Relacionados</h5>
                                ${
                                  cuadros.length > 0
                                    ? `
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Empresa</th>
                                                    <th>Medida</th>
                                                    <th>Cantidad</th>
                                                    <th>Inversión</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${cuadros
                                                  .map(
                                                    (cuadro) => `
                                                    <tr>
                                                        <td>${cuadro.cdgo_cuadro}</td>
                                                        <td>${escapeHtml(cuadro.empresa)}</td>
                                                        <td>${escapeHtml(cuadro.medida)}</td>
                                                        <td>${cuadro.cantidad}</td>
                                                        <td>${parseFloat(cuadro.inversion).toFixed(2)}</td>
                                                    </tr>
                                                `,
                                                  )
                                                  .join("")}
                                            </tbody>
                                        </table>
                                    </div>
                                `
                                    : '<p class="mt-3">No hay cuadros asociados a este reporte.</p>'
                                }
                                <div class="mt-3">
                                    <h6>Información adicional:</h6>
                                    <p><strong>Enlace:</strong> <a href="${escapeAttr(reporte.enlace)}" target="_blank" rel="noopener">${escapeHtml(reporte.enlace)}</a></p>
                                    <p><strong>Fundamento:</strong> ${escapeHtml(reporte.fundamento)}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;

        $(`button.btn-toggle[data-reporte="${reporteId}"]`)
          .closest("tr")
          .after(detalleRow);
      } else {
        alert("Error: " + response.message);
      }
    },
    error: function () {
      alert("Error al conectar con el servidor");
    },
  });
}

function guardarReporte() {
  const formData = new FormData(document.getElementById("reporteForm"));
  const mode = formData.get("modalMode");

  $.ajax({
    url: route("guardar"),
    type: "POST",
    data: formData,
    processData: false,
    contentType: false,
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        alert(`Reporte ${mode === "nuevo" ? "creado" : "actualizado"} correctamente`);
        cerrarModal();
        cargarReportes();
      } else {
        alert("Error: " + response.message);
      }
    },
    error: function () {
      alert("Error al conectar con el servidor");
    },
  });
}

function editarReporte(reporteId) {
  $.ajax({
    url: route("editar"),
    type: "GET",
    data: { reporte_id: reporteId },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        abrirModalEditar(response.data.reporte, response.data.cuadros);
      } else {
        alert("Error: " + response.message);
      }
    },
    error: function () {
      alert("Error al conectar con el servidor");
    },
  });
}

function eliminarReporte(reporteId) {
  if (!confirm("¿Está seguro que desea eliminar este reporte?")) return;

  $.ajax({
    url: route("eliminar"),
    type: "POST",
    data: { reporte_id: reporteId },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        alert("Reporte eliminado correctamente");
        cargarReportes();
      } else {
        alert("Error: " + response.message);
      }
    },
    error: function () {
      alert("Error al conectar con el servidor");
    },
  });
}

function generarPDF(reporteId, contador) {
  const url = `${route("pdf")}?reporte_id=${encodeURIComponent(reporteId)}&contador=${encodeURIComponent(contador)}`;
  window.open(url, "_blank");
}

function escapeHtml(str) {
  return String(str ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function escapeAttr(str) {
  return escapeHtml(str).replace(/'/g, "&#39;");
}
