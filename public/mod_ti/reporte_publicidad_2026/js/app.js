let paginaActual = 1;
let cargandoReportes = false;

$(document).ready(function () {
  $.ajaxSetup({
    headers: {
      "X-CSRF-TOKEN": window.REPORTE_PUBLICIDAD_2026?.csrfToken || "",
      Accept: "application/json",
    },
  });

  cargarReportes(paginaActual);

  $("#btnNuevoReporte").click(function () {
    abrirModalNuevo();
  });

  $(document).on("click", "#btnCloseModal, #btnCancelModal", function () {
    cerrarModal();
  });

  $(document).on("click", "#btnAddDetalle", function () {
    agregarDetalle();
  });

  $(document).on("click", ".btn-remove-detalle", function () {
    if (!$(this).prop("disabled")) {
      $(this).closest(".detalle-form").remove();
      actualizarIndicesDetalles();
    }
  });

  $(document).on("submit", "#reporteForm", function (e) {
    e.preventDefault();
    guardarReporte();
  });

  $(document).on("click", ".btn-edit", function () {
    editarReporte($(this).data("reporte"));
  });

  $(document).on("click", ".btn-delete", function () {
    eliminarReporte($(this).data("reporte"));
  });

  $(document).on("click", ".btn-pdf", function () {
    generarPDF($(this).data("reporte"), $(this).data("contador") || $(this).data("reporte"));
  });

  $(document).on("click", ".page-link", function (e) {
    e.preventDefault();
    if (cargandoReportes || $(this).parent().hasClass("disabled")) return false;
    const pagina = $(this).data("pagina");
    if (pagina && pagina > 0) {
      paginaActual = pagina;
      cargarReportes(paginaActual);
    }
  });

  $(document).on("change", "#imagenesReporte", function () {
    const files = Array.from(this.files).filter((f) => f.type.startsWith("image/"));
    if (files.length > 6) {
      alert("Máximo 6 imágenes permitidas");
      $(this).val("");
      return;
    }
    mostrarPreviewDesdeArchivos(files);
  });
});

function route(key) {
  return window.REPORTE_PUBLICIDAD_2026?.routes?.[key] || "";
}

function abrirModalNuevo() {
  $.get(route("formulario"), function (data) {
    $("#modalContent").html(data);
    $("#modalTitle").text("Registrar Nuevo Reporte");
    $("#modalMode").val("nuevo");
    $("#cdgo_reporte").val("");
    $("#reporteForm")[0].reset();
    $("#detallesContainer").empty();
    $("#previewImagenes").empty();
    agregarDetalle();
    $("#reporteModal").removeAttr("hidden");
  });
}

function abrirModalEditar(reporte, detalles) {
  $.get(route("formulario"), function (data) {
    $("#modalContent").html(data);
    $("#modalTitle").text("Editar Reporte");
    $("#modalMode").val("editar");

    $("#cdgo_reporte").val(reporte.cdgo_reporte);
    $("#ubicacion").val(reporte.ubicacion || "");
    $("#periodo_difusion_ini_dia").val(reporte.periodo_difusion_ini_dia || "");
    $("#periodo_difusion_ini_mes").val(reporte.periodo_difusion_ini_mes || "");
    $("#periodo_difusion_ini_anio").val(reporte.periodo_difusion_ini_anio || "");
    $("#periodo_difusion_fin_dia").val(reporte.periodo_difusion_fin_dia || "");
    $("#periodo_difusion_fin_mes").val(reporte.periodo_difusion_fin_mes || "");
    $("#periodo_difusion_fin_anio").val(reporte.periodo_difusion_fin_anio || "");
    $("#enlace_link").val(reporte.enlace_link || "");
    $("#fundamento").val(reporte.fundamento || "");

    $("#detallesContainer").empty();
    if (detalles && detalles.length > 0) {
      detalles.forEach((detalle, index) => agregarDetalle(index, detalle));
    } else {
      agregarDetalle();
    }

    mostrarPreviewImagenes(reporte.imagenes || []);
    $("#reporteModal").removeAttr("hidden");
  });
}

function cerrarModal() {
  $("#reporteModal").attr("hidden", "hidden");
  $("#modalContent").empty();
}

function agregarDetalle(index = null, detalle = null) {
  if (index === null) index = $(".detalle-form").length;

  const nuevoDetalle = `
        <div class="detalle-form" data-index="${index}">
            <div class="row g-2 align-items-end">
                <div class="col-md-1 d-flex align-items-center" style="min-height: 50px;">
                    <h6 class="m-0" style="font-size: 0.85rem; font-weight: 600; color: #495057;">#${index + 1}</h6>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 500; display: block;">Empresa que elaboró la publicidad estatal</label>
                    <input type="text" class="form-control form-control-sm" name="detalles[${index}][empresa_elaboro]" value="${escapeAttr(detalle?.empresa_elaboro || "")}" placeholder="Empresa" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 500; display: block;">Tipo de medio empleado</label>
                    <input type="text" class="form-control form-control-sm" name="detalles[${index}][tipo_medio_empleado]" value="${escapeAttr(detalle?.tipo_medio_empleado || "")}" placeholder="Tipo de medio" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 500; display: block;">Cantidad</label>
                    <input type="number" step="0.01" class="form-control form-control-sm" name="detalles[${index}][cantidad]" value="${detalle?.cantidad || ""}" placeholder="Cantidad" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm btn-remove-detalle w-100">
                        <i class="fas fa-trash me-1"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    `;

  $("#detallesContainer").append(nuevoDetalle);
  if ($(".detalle-form").length > 1) {
    $(".btn-remove-detalle").prop("disabled", false);
  }
}

function actualizarIndicesDetalles() {
  $(".detalle-form").each(function (index) {
    $(this).attr("data-index", index);
    $(this).find("h6").text(`#${index + 1}`);
    $(this)
      .find("input")
      .each(function () {
        const name = $(this).attr("name");
        if (name) {
          $(this).attr("name", name.replace(/detalles\[\d+\]/, `detalles[${index}]`));
        }
      });
  });
  $(".btn-remove-detalle").prop("disabled", $(".detalle-form").length <= 1);
}

function cargarReportes(pagina = 1) {
  if (cargandoReportes) return;
  paginaActual = pagina;
  cargandoReportes = true;

  $("#tablaReportes").html(
    '<tr><td colspan="6" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Cargando...</span></div> Cargando reportes...</td></tr>',
  );
  deshabilitarPaginacion();

  $.ajax({
    url: route("listar"),
    type: "GET",
    data: { pagina: pagina },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        renderizarReportes(response.data.reportes);
        renderizarPaginacion(response.data.paginacion);
      } else {
        $("#tablaReportes").html(
          '<tr><td colspan="6" class="text-center text-danger">Error: ' +
            escapeHtml(response.message) +
            "</td></tr>",
        );
        $("#paginacion").html("");
      }
    },
    error: function () {
      $("#tablaReportes").html(
        '<tr><td colspan="6" class="text-center text-danger">Error al conectar con el servidor</td></tr>',
      );
      $("#paginacion").html("");
    },
    complete: function () {
      cargandoReportes = false;
      habilitarPaginacion();
    },
  });
}

function deshabilitarPaginacion() {
  $("#paginacion .page-link").css("pointer-events", "none").css("opacity", "0.6");
}

function habilitarPaginacion() {
  $("#paginacion .page-link").css("pointer-events", "auto").css("opacity", "1");
}

function renderizarReportes(reportes) {
  if (!reportes || reportes.length === 0) {
    $("#tablaReportes").html('<tr><td colspan="6" class="text-center">No hay registros</td></tr>');
    return;
  }

  $("#tablaReportes").css("opacity", "0.5");
  setTimeout(() => {
    const html = reportes
      .map(
        (reporte) => `
                <tr>
                    <td>${reporte.contador || "-"}</td>
                    <td title="${escapeAttr(reporte.mdio_empleado || "")}">${escapeHtml(reporte.mdio_empleado || "-")}</td>
                    <td title="${escapeAttr(reporte.ubicacion || "")}">${escapeHtml(reporte.ubicacion || "-")}</td>
                    <td>${escapeHtml(reporte.fech_ini || "-")}</td>
                    <td>${escapeHtml(reporte.fech_fin || "-")}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-primary btn-accion btn-pdf rounded-circle shadow-sm"
                                data-reporte="${reporte.cdgo_reporte}"
                                data-contador="${reporte.contador || reporte.cdgo_reporte}"
                                title="Descargar PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success btn-accion btn-edit rounded-circle shadow-sm"
                                data-reporte="${reporte.cdgo_reporte}" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-accion btn-delete rounded-circle shadow-sm"
                                data-reporte="${reporte.cdgo_reporte}" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `,
      )
      .join("");

    $("#tablaReportes").html(html).css("opacity", "1");
  }, 150);
}

function renderizarPaginacion(paginacion) {
  const totalPaginas = paginacion.total_paginas;
  const paginaAct = paginacion.pagina_actual;

  if (totalPaginas <= 1) {
    $("#paginacion").html("");
    return;
  }

  const maxVisible = 5;
  let inicio = Math.max(1, paginaAct - Math.floor(maxVisible / 2));
  let fin = Math.min(totalPaginas, inicio + maxVisible - 1);
  if (fin - inicio < maxVisible - 1) {
    inicio = Math.max(1, fin - maxVisible + 1);
  }

  let html = `<li class="page-item ${paginaAct === 1 ? "disabled" : ""}">
        <a class="page-link" href="#" data-pagina="${paginaAct - 1}" aria-label="Anterior">
            <span aria-hidden="true">&laquo;</span> Anterior
        </a></li>`;

  if (inicio > 1) {
    html += `<li class="page-item"><a class="page-link" href="#" data-pagina="1">1</a></li>`;
    if (inicio > 2) {
      html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
  }

  for (let i = inicio; i <= fin; i++) {
    html += `<li class="page-item ${i === paginaAct ? "active" : ""}">
            <a class="page-link" href="#" data-pagina="${i}">${i}</a></li>`;
  }

  if (fin < totalPaginas) {
    if (fin < totalPaginas - 1) {
      html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    html += `<li class="page-item"><a class="page-link" href="#" data-pagina="${totalPaginas}">${totalPaginas}</a></li>`;
  }

  html += `<li class="page-item ${paginaAct === totalPaginas ? "disabled" : ""}">
        <a class="page-link" href="#" data-pagina="${paginaAct + 1}" aria-label="Siguiente">
            Siguiente <span aria-hidden="true">&raquo;</span>
        </a></li>`;

  $("#paginacion").html(html);
}

function guardarReporte() {
  const formData = new FormData(document.getElementById("reporteForm"));
  const mode = formData.get("modalMode");

  const construirFecha = (dia, mes, anio) => {
    if (dia && mes && anio) {
      return `${anio}-${String(mes).padStart(2, "0")}-${String(dia).padStart(2, "0")}`;
    }
    return null;
  };

  const fechaIni = construirFecha(
    $("#periodo_difusion_ini_dia").val(),
    $("#periodo_difusion_ini_mes").val(),
    $("#periodo_difusion_ini_anio").val(),
  );
  const fechaFin = construirFecha(
    $("#periodo_difusion_fin_dia").val(),
    $("#periodo_difusion_fin_mes").val(),
    $("#periodo_difusion_fin_anio").val(),
  );

  if (!fechaIni) {
    alert("Por favor complete la fecha de inicio del período de difusión");
    return;
  }
  if (!fechaFin) {
    alert("Por favor complete la fecha de fin del período de difusión");
    return;
  }

  formData.append("fech_ini", fechaIni);
  formData.append("fech_fin", fechaFin);

  const detalles = $(".detalle-form")
    .map(function () {
      const empresa = $(this).find('input[name*="[empresa_elaboro]"]').val();
      const tipoMedio = $(this).find('input[name*="[tipo_medio_empleado]"]').val();
      return empresa && tipoMedio
        ? {
            empresa_elaboro: empresa,
            tipo_medio_empleado: tipoMedio,
            cantidad: parseFloat($(this).find('input[name*="[cantidad]"]').val()) || 0,
          }
        : null;
    })
    .get()
    .filter((d) => d !== null);

  formData.append("detalles", JSON.stringify(detalles));

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
        cargarReportes(paginaActual);
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
    url: route("obtener"),
    type: "GET",
    data: { reporte_id: reporteId },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        abrirModalEditar(response.data.reporte, response.data.detalles);
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
        cargarReportes(paginaActual);
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

function mostrarPreviewImagenes(imagenes) {
  const preview = $("#previewImagenes");
  preview.empty();
  imagenes.forEach((img) => {
    if (img) {
      $("<img>")
        .attr("src", `${route("imagen")}?f=${encodeURIComponent(img)}`)
        .css(estilosImagen)
        .on("error", function () {
          $(this).remove();
        })
        .appendTo(preview);
    }
  });
}

function mostrarPreviewDesdeArchivos(files) {
  const preview = $("#previewImagenes");
  preview.empty();
  files.forEach((file) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      $("<img>").attr("src", e.target.result).css(estilosImagen).appendTo(preview);
    };
    reader.readAsDataURL(file);
  });
}

const estilosImagen = {
  width: "80px",
  height: "80px",
  "object-fit": "cover",
  "border-radius": "4px",
  "margin-right": "5px",
  "margin-bottom": "5px",
  border: "1px solid #ddd",
};

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
