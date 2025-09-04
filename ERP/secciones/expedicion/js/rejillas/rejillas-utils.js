/**
 * Utilidades y funciones auxiliares para el sistema de rejillas
 * Módulo con funciones de formateo, inicialización y helpers generales
 */

/**
 * Inicialización principal de la aplicación
 */
document.addEventListener("DOMContentLoaded", function () {
  console.log(
    "🚀 Sistema de Rejillas v" +
      REJILLAS_CONFIG.version +
      " iniciado (Versión Modularizada)"
  );

  try {
    // Inicializar configuración
    if (!inicializarConfiguracion()) {
      throw new Error("Error en la configuración inicial");
    }

    // Inicializar componentes
    inicializarComponentes();

    // Configurar eventos
    configurarEventos();

    // Cargar datos iniciales
    cargarDatosIniciales();

    console.log("✅ Inicialización completada exitosamente");
  } catch (error) {
    console.error("❌ Error durante la inicialización:", error);
    mostrarNotificacion("Error al inicializar la aplicación", "error");
  }
});

/**
 * Inicializar componentes de la interfaz
 */
function inicializarComponentes() {
  // Inicializar modales
  const modalElement = document.getElementById("modalDetallesRejilla");
  if (modalElement) {
    modalDetallesRejilla = new bootstrap.Modal(modalElement, {
      backdrop: "static",
      keyboard: true,
    });

    // Evento al cerrar modal
    modalElement.addEventListener("hidden.bs.modal", function () {
      limpiarModalDetalles();
    });
  }

  // Inicializar tooltips
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Inicializar popovers
  const popoverTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="popover"]')
  );
  popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
  });
}

/**
 * Configurar eventos de la interfaz
 */
function configurarEventos() {
  // Evento de redimensionamiento de ventana
  window.addEventListener("resize", debounce(ajustarLayoutResponsivo, 250));

  // Eventos de teclado
  document.addEventListener("keydown", manejarEventosTeclado);
}

/**
 * Cargar datos iniciales de la aplicación
 */
async function cargarDatosIniciales() {
  try {
    // Actualizar timestamp
    actualizarTimestampActualizacion();
    console.log("Datos iniciales cargados correctamente");
  } catch (error) {
    console.error("Error cargando datos iniciales:", error);
    mostrarNotificacion("Error al cargar los datos iniciales", "error");
  }
}

/**
 * Refrescar datos de la página
 */
function refrescarDatos() {
  console.log("🔄 Refrescando datos de rejillas...");

  try {
    // Mostrar indicador de carga
    mostrarNotificacion("Actualizando datos...", "info", 2000);

    // Recargar página
    window.location.reload();
  } catch (error) {
    console.error("❌ Error refrescando datos:", error);
    mostrarNotificacion("Error al refrescar los datos", "error");
  }
}

/**
 * Manejar eventos de teclado
 */
function manejarEventosTeclado(event) {
  // ESC para cerrar modales
  if (event.key === "Escape" && modalDetallesRejilla) {
    modalDetallesRejilla.hide();
  }

  // F5 para refrescar
  if (event.key === "F5") {
    event.preventDefault();
    refrescarDatos();
  }

  // Ctrl+R para refrescar
  if (event.ctrlKey && event.key === "r") {
    event.preventDefault();
    refrescarDatos();
  }
}

/**
 * Ajustar layout responsivo
 */
function ajustarLayoutResponsivo() {
  const width = window.innerWidth;

  if (width < 768) {
    console.log("📱 Ajustando para móvil");
  } else if (width < 1200) {
    console.log("📟 Ajustando para tablet");
  } else {
    console.log("🖥️ Ajustando para desktop");
  }
}

/**
 * Mostrar notificación
 */
function mostrarNotificacion(mensaje, tipo = "info", duracion = 5000) {
  // Crear elemento de notificación
  const notificacion = document.createElement("div");
  notificacion.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
  notificacion.style.cssText = `
    top: 100px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    max-width: 400px;
  `;

  notificacion.innerHTML = `
    ${mensaje}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;

  document.body.appendChild(notificacion);

  // Auto-eliminar después de la duración especificada
  if (duracion > 0) {
    setTimeout(() => {
      if (notificacion.parentNode) {
        notificacion.remove();
      }
    }, duracion);
  }
}

// ===== FUNCIONES DE FORMATEO Y UTILIDADES =====

/**
 * Formatear número con separadores de miles
 */
function formatearNumero(numero) {
  if (numero === null || numero === undefined || isNaN(numero)) {
    return "0";
  }
  return parseFloat(numero).toLocaleString("es-ES", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
}

/**
 * Formatear fecha
 */
function formatearFecha(fecha, corta = false) {
  if (!fecha) return "N/D";

  try {
    const d = new Date(fecha);
    if (isNaN(d.getTime())) return "Fecha inválida";

    if (corta) {
      return d.toLocaleDateString("es-ES", {
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
      });
    } else {
      return d.toLocaleDateString("es-ES", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    }
  } catch (error) {
    console.error("Error formateando fecha:", error);
    return "Error de fecha";
  }
}

/**
 * Truncar texto para evitar desbordamiento
 */
function truncarTexto(texto, maxLength) {
  if (!texto) return "N/D";
  if (texto.length <= maxLength) return texto;
  return texto.substring(0, maxLength) + "...";
}

/**
 * Determinar tipo de unidad según el producto
 */
function determinarTipoUnidad(nombreProducto) {
  if (!nombreProducto) return "unidades";

  const nombreUpper = nombreProducto.toUpperCase();

  if (nombreUpper.includes("SPUNBOND") || nombreUpper.includes("SPUNLACE")) {
    return "bobinas";
  } else if (
    nombreUpper.includes("TOALLITA") ||
    nombreUpper.includes("TOALLA") ||
    nombreUpper.includes("PAÑO") ||
    nombreUpper.includes("PAÑOS")
  ) {
    return "cajas";
  } else {
    return "unidades";
  }
}

/**
 * Capitalizar primera letra
 */
function capitalizarPrimera(str) {
  if (!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

/**
 * Obtener color del estado para badges
 */
function obtenerColorEstado(estado) {
  switch (estado) {
    case "disponible":
      return "success";
    case "ocupada":
      return "warning";
    case "llena":
      return "danger";
    case "mantenimiento":
      return "info";
    default:
      return "secondary";
  }
}

/**
 * Debounce function para optimizar eventos
 */
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Validar si un objeto está vacío
 */
function esObjetoVacio(obj) {
  return Object.keys(obj).length === 0;
}

/**
 * Validar si una cadena es un número válido
 */
function esNumeroValido(valor) {
  return !isNaN(parseFloat(valor)) && isFinite(valor);
}

/**
 * Generar ID único simple
 */
function generarIdUnico() {
  return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

/**
 * Convertir string a boolean seguro
 */
function aBooleanSeguro(valor) {
  if (typeof valor === "boolean") return valor;
  if (typeof valor === "string") {
    return valor.toLowerCase() === "true" || valor === "1";
  }
  return Boolean(valor);
}

/**
 * Limpiar y validar entrada de texto
 */
function limpiarTexto(texto, maxLength = 255) {
  if (!texto) return "";

  return texto.toString().trim().substring(0, maxLength).replace(/[<>]/g, ""); // Remover caracteres peligrosos básicos
}

/**
 * Formatear tamaño de archivo
 */
function formatearTamañoArchivo(bytes) {
  if (bytes === 0) return "0 Bytes";

  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

/**
 * Crear elemento HTML desde string
 */
function crearElementoHTML(htmlString) {
  const template = document.createElement("template");
  template.innerHTML = htmlString.trim();
  return template.content.firstChild;
}

// Limpiar recursos al cerrar la página
window.addEventListener("beforeunload", function () {
  if (intervaloRefresh) {
    clearInterval(intervaloRefresh);
  }
});

// Manejar cambios de visibilidad
document.addEventListener("visibilitychange", function () {
  if (document.visibilityState === "visible") {
    console.log("📖 Página visible");
  } else {
    console.log("📖 Página oculta");
  }
});

// Exportar funciones principales para uso global
window.refrescarDatos = refrescarDatos;
window.mostrarNotificacion = mostrarNotificacion;
window.formatearNumero = formatearNumero;
window.formatearFecha = formatearFecha;
window.truncarTexto = truncarTexto;
window.determinarTipoUnidad = determinarTipoUnidad;
window.capitalizarPrimera = capitalizarPrimera;
window.obtenerColorEstado = obtenerColorEstado;
window.limpiarTexto = limpiarTexto;
