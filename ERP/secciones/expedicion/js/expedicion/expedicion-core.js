// =====================================
// EXPEDICION CORE - Núcleo e inicialización
// =====================================

// ===== VARIABLES GLOBALES =====
let modalClienteActivo = null;
let clienteActual = null;
let isLoading = false;
let debugMode =
  typeof EXPEDICION_CONFIG !== "undefined" && EXPEDICION_CONFIG.debug;

// ===== CONFIGURACIÓN DEL SISTEMA SIMPLIFICADO =====
const EXPEDICION_SETTINGS = {
  version: "4.4-COMPACTO",
  funcionalidad:
    "Sistema Compacto con Auto-Ocultación + Unidades + Asignación Completa Mejorada",
  caracteristicas: [
    "Productos asignados desaparecen automáticamente",
    "Lista siempre actualizada sin productos ya asignados",
    "✅ NUEVO: Elementos más compactos para mostrar más contenido",
    "✅ NUEVO: Asignación completa de venta incluso con 1 producto",
    "✅ OPTIMIZADO: Interfaz más eficiente y compacta",
    "Manejo de unidades específicas (bobinas/cajas/unidades)",
    "PAÑOS tratados como cajas con peso calculado correctamente",
    "Notificaciones bonitas con toasts",
    "Sistema de debug avanzado",
  ],
  tiposProducto: {
    bobinas: ["TNT", "SPUNLACE", "LAMINADORA"],
    cajas: ["TOALLITAS", "TOALLA", "PAÑOS", "PAÑO"],
    unidades: ["OTROS"],
  },
  calculoUnidades: {
    bobinas: "peso_total ÷ peso_unitario = cantidad_bobinas",
    cajas: "cantidad_real × peso_unitario = peso_total",
    unidades: "cantidad_directa",
  },
  timeouts: {
    toast: 3000,
    reload: 1200,
    debounce: 200,
  },
  animaciones: {
    modalFade: 200,
    cardHover: 150,
    progressBar: 300,
  },
  notificaciones: {
    sistema: true,
    exito: true,
    autoOcultacion: true,
    ayuda: true,
    debug: debugMode,
  },
};

// ===== FUNCIÓN DE DEBUG (SILENCIOSA) =====
function logDebug(mensaje, datos = null, nivel = "info") {
  if (debugMode && nivel === "error") {
    console.error(`[ERROR] ${mensaje}`, datos || "");
  }
  if (debugMode && EXPEDICION_SETTINGS.notificaciones.debug) {
    console.log(`[DEBUG] ${mensaje}`, datos || "");
  }
}

// ===== INICIALIZACIÓN DEL SISTEMA =====
document.addEventListener("DOMContentLoaded", function () {
  try {
    inicializarModales();
    configurarEventListeners();
    configurarTeclasRapidas();
    inicializarTooltips();
    verificarConfiguracion();

    logDebug(
      "Sistema de expedición compacto inicializado correctamente",
      EXPEDICION_SETTINGS
    );
  } catch (error) {
    logDebug("Error durante la inicialización", error, "error");
  }
});

// ===== VERIFICAR CONFIGURACIÓN =====
function verificarConfiguracion() {
  if (typeof EXPEDICION_CONFIG === "undefined") {
    logDebug("EXPEDICION_CONFIG no definido", null, "error");
    return false;
  }

  const configRequerida = ["urlBase", "usuario", "rejillasDisponibles"];
  const configFaltante = configRequerida.filter(
    (key) => !EXPEDICION_CONFIG[key]
  );

  if (configFaltante.length > 0) {
    logDebug("Configuración faltante", configFaltante, "error");
    return false;
  }

  logDebug("Configuración verificada correctamente", EXPEDICION_CONFIG);
  return true;
}

// ===== INICIALIZACIÓN DE MODALES =====
function inicializarModales() {
  const modalClienteElement = document.getElementById(
    "modalVentasProductosCliente"
  );
  if (modalClienteElement) {
    modalClienteActivo = new bootstrap.Modal(modalClienteElement, {
      backdrop: "static",
      keyboard: true,
    });

    modalClienteElement.addEventListener("hidden.bs.modal", function () {
      limpiarModalCliente();
    });

    logDebug("Modal de cliente inicializado");
  } else {
    logDebug("Modal de cliente no encontrado", null, "error");
  }
}

// ===== CONFIGURAR EVENT LISTENERS =====
function configurarEventListeners() {
  document.addEventListener("submit", function (e) {
    if (e.target.classList.contains("expedicion-form-simplificado")) {
      e.preventDefault();
      procesarFormularioExpedicionSimplificado(e.target);
    }
  });

  document.addEventListener("click", function (e) {
    if (e.target.closest(".btn-reservar-completo")) {
      e.preventDefault();
      const btn = e.target.closest(".btn-reservar-completo");
      const form = btn.closest("form");
      if (form) {
        procesarFormularioExpedicionSimplificado(form);
      }
    }

    if (e.target.closest(".btn-cancelar-reserva")) {
      e.preventDefault();
      const btn = e.target.closest(".btn-cancelar-reserva");
      const idAsignacion = btn.dataset.idAsignacion;
      if (idAsignacion) {
        cancelarReservaPorId(idAsignacion);
      }
    }
  });

  document.addEventListener("change", function (e) {
    if (e.target.classList.contains("rejilla-select")) {
      actualizarInformacionRejilla(e.target);
    }
  });

  logDebug("Event listeners configurados");
}

// ===== CONFIGURAR TECLAS RÁPIDAS =====
function configurarTeclasRapidas() {
  document.addEventListener("keydown", function (e) {
    if (
      e.target.tagName === "INPUT" ||
      e.target.tagName === "TEXTAREA" ||
      e.target.tagName === "SELECT"
    ) {
      return;
    }

    switch (e.key) {
      case "Escape":
        cerrarTodosLosModales();
        break;

      case "F5":
        if (!e.ctrlKey) {
          e.preventDefault();
          recargarPaginaConConfirmacion();
        }
        break;

      case "r":
      case "R":
        if (e.ctrlKey) {
          e.preventDefault();
        }
        break;
    }
  });

  logDebug("Teclas rápidas configuradas");
}

// ===== INICIALIZAR TOOLTIPS =====
function inicializarTooltips() {
  try {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    logDebug(`Tooltips inicializados: ${tooltipTriggerList.length}`);
  } catch (error) {
    logDebug("Error inicializando tooltips", error, "error");
  }
}

// ===== FUNCIONES DE UTILIDAD BÁSICAS =====
function actualizarEstadoBoton(boton, cargando) {
  if (!boton) return;

  const textoSpan = boton.querySelector(".btn-text");
  const loadingSpan = boton.querySelector(".btn-loading");

  boton.disabled = cargando;

  if (textoSpan) textoSpan.style.display = cargando ? "none" : "inline";
  if (loadingSpan) loadingSpan.style.display = cargando ? "inline" : "none";
}

function mostrarLoadingModal(mostrar) {
  const loading = document.getElementById("loadingVentasProductos");
  if (loading) {
    loading.style.display = mostrar ? "block" : "none";
  }
}

function limpiarContenidoModal() {
  const contenido = document.getElementById("contenidoVentasProductos");
  if (contenido) {
    contenido.innerHTML = "";
  }
}

function mostrarErrorModal(mensaje) {
  const contenido = document.getElementById("contenidoVentasProductos");
  if (contenido) {
    contenido.innerHTML = `
      <div class="alert alert-danger p-2">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <small>${mensaje}</small>
      </div>
    `;
  }
}

function limpiarModalCliente() {
  clienteActual = null;
  limpiarContenidoModal();
  mostrarLoadingModal(false);
  logDebug("Modal de cliente limpiado");
}

function cerrarTodosLosModales() {
  if (modalClienteActivo && modalClienteActivo._isShown) {
    modalClienteActivo.hide();
  }

  const modalesDinamicos = document.querySelectorAll(".modal.show");
  modalesDinamicos.forEach((modal) => {
    const bsModal = bootstrap.Modal.getInstance(modal);
    if (bsModal) {
      bsModal.hide();
    }
  });

  logDebug("Todos los modales cerrados");
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

function formatearFecha(fecha) {
  if (!fecha) return "N/A";
  try {
    return new Date(fecha).toLocaleDateString("es-ES", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch (e) {
    return fecha;
  }
}

function recargarPagina() {
  logDebug("Recargando página");
  window.location.reload();
}

async function recargarPaginaConConfirmacion() {
  if (isLoading) {
    const confirmado = await confirmarAccionBonita(
      "Operación en Curso",
      "Hay una operación en curso. ¿Está seguro de recargar la página?",
      "Sí, Recargar",
      "Cancelar"
    );
    if (confirmado) {
      recargarPagina();
    }
  } else {
    recargarPagina();
  }
}


// ===== INICIALIZAR ELEMENTOS DINÁMICOS =====
function inicializarElementosDinamicos(contenedor = document) {
  const tooltips = contenedor.querySelectorAll('[data-bs-toggle="tooltip"]');
  tooltips.forEach((el) => {
    if (!el._tooltip) {
      new bootstrap.Tooltip(el);
    }
  });

  logDebug(`Elementos dinámicos inicializados: ${tooltips.length} tooltips`);
}
