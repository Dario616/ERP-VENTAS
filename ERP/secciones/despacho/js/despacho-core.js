// =====================================
// DESPACHO CORE - Inicialización y Configuración
// =====================================

// Variables globales del sistema
let expedicionActiva = null;
let modalScanner = null;
let modalConfirmarDespacho = null;
let modalNuevaExpedicion = null;
let modalMoverItem = null;
let expedicionParaDespachar = null;
let searchTimeout = null;
let isLoading = false;
let debugMode = typeof DESPACHO_CONFIG !== "undefined" && DESPACHO_CONFIG.debug;
let clientesDisponibles = [];
let itemsParaMover = [];

// ===== FUNCIÓN DE DEBUG =====
function logDebug(mensaje, datos = null) {
  if (debugMode) {
    const timestamp = new Date().toLocaleTimeString();
    console.log(`[${timestamp}] [DESPACHO v7.1] ${mensaje}`, datos || "");
  }
}

// ===== INICIALIZACIÓN DEL SISTEMA =====
document.addEventListener("DOMContentLoaded", function () {
  logDebug(
    "DOM cargado, inicializando sistema v7.1 - Automático + Flexibilidad"
  );

  inicializarModales();
  configurarEventListeners();
  inicializarFormularios();
  configurarTeclasRapidas();
  cargarClientesDisponibles();

  logDebug("Sistema v7.1 inicializado completamente");
  console.log(
    "Sistema de Expediciones v7.1 - Automático con Flexibilidad para Items Fuera de Rejilla"
  );
});

// ===== INICIALIZACIÓN DE MODALES =====
function inicializarModales() {
  const modalEscanearElement = document.getElementById("modalEscanear");
  if (modalEscanearElement) {
    modalScanner = new bootstrap.Modal(modalEscanearElement);

    modalEscanearElement.addEventListener("shown.bs.modal", function () {
      mantenerFocusScanner();
      logDebug("Modal scanner abierto");
    });

    modalEscanearElement.addEventListener("hidden.bs.modal", function () {
      limpiarScanner();
      logDebug("Modal scanner cerrado");
      location.reload();
    });
  }

  const modalDespachoElement = document.getElementById(
    "modalConfirmarDespacho"
  );
  if (modalDespachoElement) {
    modalConfirmarDespacho = new bootstrap.Modal(modalDespachoElement);
  }

  const modalNuevaExpElement = document.getElementById("modalNuevaExpedicion");
  if (modalNuevaExpElement) {
    modalNuevaExpedicion = new bootstrap.Modal(modalNuevaExpElement);

    modalNuevaExpElement.addEventListener("hidden.bs.modal", function () {
      limpiarFormularioExpedicion();
      logDebug("Modal nueva expedición cerrado");
    });
  }

  logDebug("Modales inicializados");
}

// ===== CONFIGURAR EVENT LISTENERS =====
function configurarEventListeners() {
  const btnConfirmarDespacho = document.getElementById("btnConfirmarDespacho");
  if (btnConfirmarDespacho) {
    btnConfirmarDespacho.addEventListener("click", function () {
      if (expedicionParaDespachar) {
        ejecutarDespacho(expedicionParaDespachar);
      }
    });
  }

  const barcodeInput = document.getElementById("barcodeInput");
  if (barcodeInput) {
    barcodeInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        procesarCodigoEscaneado(this.value.trim());
      }
    });

    barcodeInput.addEventListener("input", function () {
      manejarAutoSubmit(this);
    });
  }

  const selectRejilla = document.getElementById("selectRejilla");
  if (selectRejilla) {
    selectRejilla.addEventListener("change", function () {
      validarFormularioExpedicion();
      if (this.value) {
        cargarAsignacionesRejilla(this.value);
      } else {
        limpiarVisualizacionRejilla();
      }
    });
  }

  logDebug("Event listeners configurados");
}

// ===== CARGAR CLIENTES DISPONIBLES =====
function cargarClientesDisponibles() {
  const formData = new FormData();
  formData.append("accion", "obtener_clientes_disponibles");

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        clientesDisponibles = data.clientes;
        logDebug("Clientes disponibles cargados", clientesDisponibles);
      }
    })
    .catch((error) => {
      console.error("Error cargando clientes:", error);
    });
}

// ===== INICIALIZAR FORMULARIOS =====
function inicializarFormularios() {
  const formNuevaExpedicion = document.getElementById("formNuevaExpedicion");
  if (formNuevaExpedicion) {
    formNuevaExpedicion.addEventListener("submit", function (e) {
      e.preventDefault();
      crearNuevaExpedicion();
    });
  }

  const camposRequeridos = ["transportista", "id_rejilla"];
  camposRequeridos.forEach((campo) => {
    const elemento = document.querySelector(`[name="${campo}"]`);
    if (elemento) {
      elemento.addEventListener("change", validarFormularioExpedicion);
      elemento.addEventListener("input", validarFormularioExpedicion);
    }
  });

  logDebug("Formularios inicializados");
}

// ===== CONFIGURAR TECLAS RÁPIDAS =====
function configurarTeclasRapidas() {
  document.addEventListener("keydown", function (e) {
    const modalMoverAbierto = modalMoverItem && modalMoverItem._isShown;

    if (
      e.target.tagName === "INPUT" ||
      e.target.tagName === "TEXTAREA" ||
      e.target.tagName === "SELECT" ||
      modalMoverAbierto
    ) {
      return;
    }

    switch (e.key) {
      case "n":
      case "N":
        if (e.ctrlKey || e.metaKey) {
          e.preventDefault();
          const btnNueva = document.querySelector(
            '[data-bs-target="#modalNuevaExpedicion"]'
          );
          if (btnNueva && !btnNueva.disabled) {
            btnNueva.click();
          }
        }
        break;

      case "Escape":
        if (!modalMoverAbierto) {
          cerrarTodosLosModales();
        }
        break;

      case "F5":
        if (!e.ctrlKey) {
          e.preventDefault();
          location.reload();
        }
        break;
    }
  });

  logDebug("Teclas rápidas configuradas");
}

// ===== FUNCIONES AUXILIARES CORE =====
function cerrarTodosLosModales() {
  [
    modalScanner,
    modalConfirmarDespacho,
    modalNuevaExpedicion,
    modalMoverItem,
  ].forEach((modal) => {
    if (modal && modal._isShown) {
      modal.hide();
    }
  });
}

function manejarAutoSubmit(input) {
  clearTimeout(searchTimeout);
  const valor = input.value.trim();

  if (valor.length >= 3) {
    searchTimeout = setTimeout(() => {
      if (input.value.trim() === valor && valor.length >= 3) {
        procesarCodigoEscaneado(valor);
        logDebug("Auto-submit activado para código", valor);
      }
    }, 800);
  }
}

// ===== UTILIDADES =====
function mostrarToast(mensaje, tipo = "info", duracion = 4000) {
  const toastId = "toast_" + Date.now();
  const iconos = {
    success: "fas fa-check-circle",
    danger: "fas fa-exclamation-triangle",
    warning: "fas fa-exclamation-circle",
    info: "fas fa-info-circle",
  };

  const toast = document.createElement("div");
  toast.id = toastId;
  toast.className = `alert alert-${tipo} scan-feedback alert-dismissible fade show`;
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 350px;
    max-width: 500px;
    opacity: 0.95;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
  `;

  toast.innerHTML = `
    <div class="d-flex align-items-start">
      <i class="${iconos[tipo]} me-2 fs-5 mt-1"></i>
      <div class="flex-grow-1">${mensaje}</div>
      <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
    </div>
  `;

  document.body.appendChild(toast);

  setTimeout(() => {
    const element = document.getElementById(toastId);
    if (element) {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(element);
      bsAlert.close();
    }
  }, duracion);
}

function escapeHtml(unsafe) {
  if (typeof unsafe !== "string") return unsafe;
  return unsafe
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
