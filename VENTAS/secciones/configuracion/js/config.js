let isLoading = false;
let debugMode = typeof CONFIG_JS !== "undefined" && CONFIG_JS.debug;

function logDebug(mensaje, datos = null) {
  if (debugMode) {
    console.log(`[CONFIG DEBUG] ${mensaje}`, datos || "");
  }
}

function showLoading(mensaje = "Procesando...") {
  if (document.getElementById("loadingOverlay")) return;

  const loading = document.createElement("div");
  loading.id = "loadingOverlay";
  loading.className = "loading-overlay";
  loading.innerHTML = `
        <div class="text-center">
            <div class="loading-spinner"></div>
            <div class="mt-3">
                <h5>${mensaje}</h5>
                <p class="text-muted">Por favor espere...</p>
            </div>
        </div>
    `;

  document.body.appendChild(loading);
  isLoading = true;
  logDebug(`Loading mostrado: ${mensaje}`);
}

function hideLoading() {
  const loading = document.getElementById("loadingOverlay");
  if (loading) {
    loading.remove();
    isLoading = false;
    logDebug("Loading ocultado");
  }
}

function showToast(mensaje, tipo = "info", duracion = 3000) {
  const existingToast = document.getElementById("customToast");
  if (existingToast) {
    existingToast.remove();
  }

  const toast = document.createElement("div");
  toast.id = "customToast";
  toast.className = `alert alert-${tipo} position-fixed fade-in`;
  toast.style.cssText = `
        top: 90px; 
        right: 20px; 
        z-index: 9999; 
        min-width: 300px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    `;

  const iconos = {
    success: "fas fa-check-circle",
    error: "fas fa-exclamation-triangle",
    warning: "fas fa-exclamation-circle",
    info: "fas fa-info-circle",
  };

  toast.innerHTML = `
        <i class="${iconos[tipo] || iconos.info} me-2"></i>
        ${mensaje}
        <button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>
    `;

  document.body.appendChild(toast);

  setTimeout(() => {
    if (toast.parentNode) {
      toast.remove();
    }
  }, duracion);

  logDebug(`Toast mostrado: ${tipo} - ${mensaje}`);
}

function confirmarEliminarCredito(id) {
  const modal = document.getElementById("confirmarEliminarModal");
  if (modal) {
    document.getElementById("btn-confirmar-eliminar").onclick = function () {
      eliminarCredito(id);
    };
    new bootstrap.Modal(modal).show();
  }
}

function eliminarCredito(id) {
  if (isLoading) return;

  showLoading("Eliminando crédito...");

  const formData = new FormData();
  formData.append("id", id);

  fetch("?action=eliminar_credito", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoading();

      if (data.success) {
        showToast(data.mensaje || "Crédito eliminado correctamente", "success");
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("confirmarEliminarModal")
        );
        if (modal) modal.hide();
        setTimeout(() => {
          location.reload();
        }, 1500);
      } else {
        showToast(`Error: ${data.error}`, "error");
      }
    })
    .catch((error) => {
      hideLoading();
      console.error("Error eliminando crédito:", error);
      showToast(`Error de conexión: ${error.message}`, "error");
    });
}

function cargarCreditos() {
  fetch("?action=listar_creditos")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        actualizarTablaCreditos(data.datos);
      }
    })
    .catch((error) => {
      logDebug("Error cargando créditos:", error);
    });
}

function actualizarTablaCreditos(creditos) {
  const tbody = document.querySelector("#tabla-creditos tbody");
  if (!tbody) return;

  let html = "";
  creditos.forEach((credito) => {
    html += `
            <tr>
                <td>${credito.id}</td>
                <td>${escapeHtml(credito.descripcion)}</td>
                <td>
                    <div class="btn-group">
                        <a href="creditosedit.php?id=${
                          credito.id
                        }" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="confirmarEliminarCredito(${
                          credito.id
                        })" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
  });

  tbody.innerHTML = html;
}

function confirmarEliminarTipoProducto(id) {
  const modal = document.getElementById("confirmarEliminarModal");
  if (modal) {
    document.getElementById("btn-confirmar-eliminar").onclick = function () {
      eliminarTipoProducto(id);
    };
    new bootstrap.Modal(modal).show();
  }
}

function eliminarTipoProducto(id) {
  if (isLoading) return;

  showLoading("Eliminando tipo de producto...");

  const formData = new FormData();
  formData.append("id", id);

  fetch("?action=eliminar_tipo_producto", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoading();

      if (data.success) {
        showToast(data.mensaje || "Tipo eliminado correctamente", "success");
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("confirmarEliminarModal")
        );
        if (modal) modal.hide();
        setTimeout(() => {
          location.reload();
        }, 1500);
      } else {
        showToast(`Error: ${data.error}`, "error");
      }
    })
    .catch((error) => {
      hideLoading();
      console.error("Error eliminando tipo de producto:", error);
      showToast(`Error de conexión: ${error.message}`, "error");
    });
}

function confirmarEliminarUnidadMedida(id) {
  const modal = document.getElementById("confirmarEliminarModal");
  if (modal) {
    document.getElementById("btn-confirmar-eliminar").onclick = function () {
      eliminarUnidadMedida(id);
    };
    new bootstrap.Modal(modal).show();
  }
}

function eliminarUnidadMedida(id) {
  if (isLoading) return;

  showLoading("Eliminando unidad de medida...");

  const formData = new FormData();
  formData.append("id", id);

  fetch("?action=eliminar_unidad_medida", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoading();

      if (data.success) {
        showToast(data.mensaje || "Unidad eliminada correctamente", "success");
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("confirmarEliminarModal")
        );
        if (modal) modal.hide();
        setTimeout(() => {
          location.reload();
        }, 1500);
      } else {
        showToast(`Error: ${data.error}`, "error");
      }
    })
    .catch((error) => {
      hideLoading();
      console.error("Error eliminando unidad de medida:", error);
      showToast(`Error de conexión: ${error.message}`, "error");
    });
}

function confirmarEliminar(id, tipo) {
  let mensaje, funcion;

  switch (tipo) {
    case "credito":
      mensaje = "¿Está seguro que desea eliminar este crédito?";
      funcion = () => eliminarCredito(id);
      break;
    case "tipo_producto":
      mensaje = "¿Está seguro que desea eliminar este tipo de producto?";
      funcion = () => eliminarTipoProducto(id);
      break;
    case "unidad_medida":
      mensaje = "¿Está seguro que desea eliminar esta unidad de medida?";
      funcion = () => eliminarUnidadMedida(id);
      break;
    default:
      return;
  }

  const modalHtml = `
        <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${mensaje}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-danger" id="btn-confirmar-eliminar-dinamico">
                            <i class="fas fa-trash me-1"></i> Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

  const modalAnterior = document.getElementById("modalConfirmarEliminar");
  if (modalAnterior) {
    modalAnterior.remove();
  }

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  document
    .getElementById("btn-confirmar-eliminar-dinamico")
    .addEventListener("click", function () {
      funcion();
      bootstrap.Modal.getInstance(
        document.getElementById("modalConfirmarEliminar")
      ).hide();
    });

  new bootstrap.Modal(document.getElementById("modalConfirmarEliminar")).show();
}

function validarFormulario(formulario) {
  const campos = formulario.querySelectorAll(
    "input[required], select[required]"
  );
  let valido = true;

  campos.forEach((campo) => {
    if (!campo.value.trim()) {
      campo.classList.add("is-invalid");
      valido = false;
    } else {
      campo.classList.remove("is-invalid");
    }
  });

  return valido;
}

function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text ? text.replace(/[&<>"']/g, (m) => map[m]) : "";
}

function actualizarEstadisticas() {
  fetch("?action=obtener_estadisticas")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const elementos = {
          totalCreditos: data.estadisticas.total_creditos,
          totalTiposProducto: data.estadisticas.total_tipos_producto,
          totalUnidadesMedida: data.estadisticas.total_unidades_medida,
        };

        Object.entries(elementos).forEach(([id, valor]) => {
          const elemento = document.getElementById(id);
          if (elemento) {
            elemento.textContent = valor || "0";
          }
        });
      }
    })
    .catch((error) => {
      logDebug("Error actualizando estadísticas:", error);
    });
}

function manejarEventosTeclado(e) {
  switch (e.key) {
    case "Escape":
      const modales = document.querySelectorAll(".modal.show");
      modales.forEach((modal) => {
        bootstrap.Modal.getInstance(modal)?.hide();
      });
      break;

    case "F5":
      if (e.ctrlKey) {
        e.preventDefault();
        location.reload();
        showToast("Página actualizada", "success", 2000);
      }
      break;
  }
}

document.addEventListener("DOMContentLoaded", function () {
  logDebug("DOM cargado, inicializando sistema de configuración");

  document.addEventListener("keydown", manejarEventosTeclado);

  const formularios = document.querySelectorAll("form");
  formularios.forEach((form) => {
    form.addEventListener("submit", function (e) {
      if (!validarFormulario(this)) {
        e.preventDefault();
        showToast("Por favor complete todos los campos requeridos", "warning");
      }
    });
  });

  const alertas = document.querySelectorAll(".alert:not(.alert-dismissible)");
  alertas.forEach((alerta) => {
    setTimeout(() => {
      alerta.style.opacity = "0";
      setTimeout(() => alerta.remove(), 300);
    }, 5000);
  });

  logDebug("Sistema de configuración inicializado completamente");
  console.log("Configuration Management - Sistema listo");

  if (typeof CONFIG_JS !== "undefined") {
    console.log("Configuración:", CONFIG_JS);
  }
});

window.addEventListener("beforeunload", function () {
  logDebug("Sistema de configuración desactivado");
});

window.ConfigApp = {
  confirmarEliminar: confirmarEliminar,
  confirmarEliminarCredito: confirmarEliminarCredito,
  confirmarEliminarTipoProducto: confirmarEliminarTipoProducto,
  confirmarEliminarUnidadMedida: confirmarEliminarUnidadMedida,
  eliminarCredito: eliminarCredito,
  eliminarTipoProducto: eliminarTipoProducto,
  eliminarUnidadMedida: eliminarUnidadMedida,
  showToast: showToast,
  logDebug: logDebug,
  actualizarEstadisticas: actualizarEstadisticas,
};

logDebug("config.js cargado completamente");
