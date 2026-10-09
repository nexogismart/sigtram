document.addEventListener("DOMContentLoaded", () => {
  UI.init();
});

function switchTab(tabId) {
  const btn = typeof event !== "undefined" && event?.target ? event.target : null;
  UI.setTab(tabId, btn);
}

function updatePreview() {
  const data = {
    titulo: document.getElementById("titulo").value,
    nombre: document.getElementById("nombre").value,
    cargo: document.getElementById("cargo").value,
    celular: document.getElementById("celular").value,
    anexo: document.getElementById("anexo").value,
    correo: document.getElementById("correo").value,
  };
  UI.updatePreview(data);
}

async function buscarPorDni() {
  const dniInput = document.getElementById("search_dni");
  const dni = dniInput.value.trim();
  if (!dni) return alert("Ingrese un DNI");

  UI.showLoading(true);
  try {
    const res = await API.buscarPorDni(dni);
    if (res.success) {
      document.getElementById("nombre").value = toTitleCase(res.data.nombre);
      document.getElementById("cargo").value = toTitleCase(res.data.cargo || "");
      document.getElementById("correo").value = res.data.correo || "";
      document.getElementById("celular").value = res.data.celular || "";
      updatePreview();
    } else {
      alert(res.error);
    }
  } catch (e) {
    alert("Error conectando con el servidor");
  } finally {
    UI.showLoading(false);
  }
}

async function guardarFirma() {
  const data = {
    titulo: document.getElementById("titulo").value,
    nombre: document.getElementById("nombre").value,
    cargo: document.getElementById("cargo").value,
    celular: document.getElementById("celular").value,
    anexo: document.getElementById("anexo").value,
    correo: document.getElementById("correo").value,
    dni: document.getElementById("search_dni").value,
  };

  UI.showLoading(true);
  try {
    const res = await API.generarIndividual(data);
    if (res.success) {
      const link = document.createElement("a");
      link.href = "data:image/png;base64," + res.data.base64;
      link.download = res.data.filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } else {
      alert(res.error);
    }
  } catch (e) {
    console.error("Error detallado:", e);
    alert("Error al generar la imagen: " + e.message);
  } finally {
    UI.showLoading(false);
  }
}

async function procesarMasivo(inputOrFile) {
  const file = inputOrFile instanceof File ? inputOrFile : inputOrFile.files[0];
  if (!file) return;

  UI.showLoading(true);
  try {
    const res = await API.generarMasivo(file);
    if (res.success) {
      document.getElementById("massive-results").style.display = "block";

      const zipContainer = document.getElementById("zip-download-container");
      zipContainer.innerHTML = `
                <div class="card" style="background: rgba(46, 203, 113, 0.05); border: 1px solid #2ecc71; box-shadow: none;">
                    <p style="color: #27ae60; font-weight: 600;">¡Proceso completado! Se han generado las firmas.</p>
                    <a href="${res.data.zip_url}" class="btn btn-primary" style="width: auto; display: inline-flex; align-items: center; gap: 8px;">
                    Descargar Todas las Firmas (ZIP)
                    </a>
                </div>
            `;

      const tbody = document.getElementById("results-body");
      tbody.innerHTML = "";
      res.data.results.forEach((item) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
                    <td>${item.nombre}</td>
                    <td class="${item.success ? "status-ok" : "status-error"}">${item.success ? "EXITO" : "ERROR"}</td>
                    <td>${item.message}</td>
                `;
        tbody.appendChild(tr);
      });
    } else {
      alert(res.error);
    }
  } catch (e) {
    alert("Error procesando el archivo CSV");
  } finally {
    UI.showLoading(false);
  }
}
