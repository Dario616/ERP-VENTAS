/**
 * utils.js - Funciones Utilitarias y Validaciones
 * Relatorio de Ventas USD - Utilidades, formateo, validaciones y notificaciones
 */

/**
 * ========================================
 * VALIDACIONES Y UTILIDADES
 * ========================================
 */
function validarFormulario() {
  const fechaInicio = $("#fecha_inicio").val();
  const fechaFin = $("#fecha_fin").val();

  if (!fechaInicio || !fechaFin) {
    mostrarToast("Las fechas de inicio y fin son requeridas", "warning");
    return false;
  }

  if (new Date(fechaInicio) > new Date(fechaFin)) {
    mostrarToast(
      "La fecha de inicio no puede ser mayor que la fecha fin",
      "warning"
    );
    return false;
  }

  const diasDiferencia =
    (new Date(fechaFin) - new Date(fechaInicio)) / (1000 * 60 * 60 * 24);
  if (diasDiferencia > 730) {
    mostrarToast("El período no puede ser mayor a 2 años", "warning");
    return false;
  }

  return true;
}

function validarRangoFechas() {
  const fechaInicio = $("#fecha_inicio").val();
  const fechaFin = $("#fecha_fin").val();

  if (fechaInicio && fechaFin && new Date(fechaInicio) > new Date(fechaFin)) {
    $("#fecha_fin").addClass("is-invalid");
    mostrarToast(
      "La fecha fin debe ser mayor o igual a la fecha inicio",
      "warning"
    );
  } else {
    $("#fecha_fin").removeClass("is-invalid");
  }
}

/**
 * ========================================
 * FUNCIONES DE FORMATEO
 * ========================================
 */
function formatearMoneda(valor) {
  if (!valor || isNaN(valor)) return "$ 0.00";
  return (
    "$ " +
    parseFloat(valor).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function formatearFecha(fecha) {
  if (!fecha) return "N/A";
  try {
    // Extraer partes de la fecha directamente sin conversión de zona horaria
    const fechaParts = fecha.split("-");
    if (fechaParts.length === 3) {
      const año = fechaParts[0];
      const mes = fechaParts[1];
      const dia = fechaParts[2];
      return `${dia}/${mes}/${año}`;
    }

    const fechaLocal = new Date(fecha + "T12:00:00");
    return fechaLocal.toLocaleDateString("es-PY", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch (e) {
    return fecha;
  }
}

function formatearNumero(numero, decimales = 0) {
  if (!numero || isNaN(numero)) return "0";
  return parseFloat(numero).toLocaleString("en-US", {
    minimumFractionDigits: decimales,
    maximumFractionDigits: decimales,
  });
}

function formatearPorcentaje(valor, decimales = 1) {
  if (!valor || isNaN(valor)) return "0%";
  return parseFloat(valor).toFixed(decimales) + "%";
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA FECHAS
 * ========================================
 */
function obtenerFechaInicioPeriodo(tipo = "mes") {
  const hoy = new Date();
  let fecha = new Date();

  switch (tipo) {
    case "semana":
      fecha.setDate(hoy.getDate() - 7);
      break;
    case "mes":
      fecha.setMonth(hoy.getMonth() - 1);
      break;
    case "trimestre":
      fecha.setMonth(hoy.getMonth() - 3);
      break;
    case "año":
      fecha.setFullYear(hoy.getFullYear() - 1);
      break;
    case "año_actual":
      fecha = new Date(hoy.getFullYear(), 0, 1); // 1 de enero del año actual
      break;
    default:
      fecha.setMonth(hoy.getMonth() - 1);
  }

  return fecha.toISOString().split("T")[0];
}

function obtenerFechaFinPeriodo() {
  return new Date().toISOString().split("T")[0];
}

function calcularDiferenciaDias(fechaInicio, fechaFin) {
  const inicio = new Date(fechaInicio);
  const fin = new Date(fechaFin);
  return Math.ceil((fin - inicio) / (1000 * 60 * 60 * 24));
}

/**
 * ========================================
 * FUNCIONES DE VALIDACIÓN DE DATOS
 * ========================================
 */
function validarEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

function validarTelefono(telefono) {
  const regex = /^[\+]?[0-9\s\-\(\)]{7,15}$/;
  return regex.test(telefono);
}

function validarMoneda(valor) {
  return !isNaN(valor) && parseFloat(valor) >= 0;
}

function validarFechaFormato(fecha) {
  const regex = /^\d{4}-\d{2}-\d{2}$/;
  return regex.test(fecha) && !isNaN(Date.parse(fecha));
}

/**
 * ========================================
 * FUNCIONES DE MANIPULACIÓN DE STRINGS
 * ========================================
 */
function limpiarTexto(texto) {
  if (!texto) return "";
  return texto.trim().replace(/\s+/g, " ");
}

function capitalizar(texto) {
  if (!texto) return "";
  return texto.charAt(0).toUpperCase() + texto.slice(1).toLowerCase();
}

function truncarTexto(texto, longitud = 50, sufijo = "...") {
  if (!texto || texto.length <= longitud) return texto || "";
  return texto.substring(0, longitud).trim() + sufijo;
}

function removerAcentos(texto) {
  if (!texto) return "";
  return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA ARRAYS
 * ========================================
 */
function agruparPor(array, campo) {
  if (!Array.isArray(array)) return {};

  return array.reduce((agrupado, item) => {
    const clave = item[campo] || "Sin clasificar";
    if (!agrupado[clave]) {
      agrupado[clave] = [];
    }
    agrupado[clave].push(item);
    return agrupado;
  }, {});
}

function ordenarPor(array, campo, direccion = "asc") {
  if (!Array.isArray(array)) return [];

  return [...array].sort((a, b) => {
    const valorA = a[campo];
    const valorB = b[campo];

    if (typeof valorA === "string" && typeof valorB === "string") {
      return direccion === "asc"
        ? valorA.localeCompare(valorB)
        : valorB.localeCompare(valorA);
    }

    if (direccion === "asc") {
      return valorA - valorB;
    } else {
      return valorB - valorA;
    }
  });
}

function obtenerUnicosArray(array, campo) {
  if (!Array.isArray(array)) return [];

  if (campo) {
    return [...new Set(array.map((item) => item[campo]))];
  }

  return [...new Set(array)];
}

function sumarCampoArray(array, campo) {
  if (!Array.isArray(array)) return 0;

  return array.reduce((suma, item) => {
    const valor = parseFloat(item[campo]) || 0;
    return suma + valor;
  }, 0);
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA OBJETOS
 * ========================================
 */
function clonarObjeto(objeto) {
  return JSON.parse(JSON.stringify(objeto));
}

function fusionarObjetos(objeto1, objeto2) {
  return { ...objeto1, ...objeto2 };
}

function objetoVacio(objeto) {
  return !objeto || Object.keys(objeto).length === 0;
}

function extraerCampos(objeto, campos) {
  if (!objeto || !Array.isArray(campos)) return {};

  const resultado = {};
  campos.forEach((campo) => {
    if (objeto.hasOwnProperty(campo)) {
      resultado[campo] = objeto[campo];
    }
  });

  return resultado;
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA NÚMEROS
 * ========================================
 */
function redondear(numero, decimales = 2) {
  if (isNaN(numero)) return 0;
  return Math.round(numero * Math.pow(10, decimales)) / Math.pow(10, decimales);
}

function calcularPorcentaje(parte, total) {
  if (!total || total === 0) return 0;
  return (parte / total) * 100;
}

function calcularPromedio(array, campo = null) {
  if (!Array.isArray(array) || array.length === 0) return 0;

  let suma = 0;
  if (campo) {
    suma = array.reduce((acc, item) => acc + (parseFloat(item[campo]) || 0), 0);
  } else {
    suma = array.reduce((acc, valor) => acc + (parseFloat(valor) || 0), 0);
  }

  return suma / array.length;
}

function obtenerMinMax(array, campo = null) {
  if (!Array.isArray(array) || array.length === 0) {
    return { min: 0, max: 0 };
  }

  let valores = array;
  if (campo) {
    valores = array.map((item) => parseFloat(item[campo]) || 0);
  }

  return {
    min: Math.min(...valores),
    max: Math.max(...valores),
  };
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA COLORES
 * ========================================
 */
function generarColorAleatorio() {
  return "#" + Math.floor(Math.random() * 16777215).toString(16);
}

function generarPaletaColores(cantidad = 5) {
  const colores = [
    "#FF6384",
    "#36A2EB",
    "#FFCE56",
    "#4BC0C0",
    "#9966FF",
    "#FF9F40",
    "#C9CBCF",
    "#FF6B6B",
    "#4ECDC4",
    "#45B7D1",
  ];

  const resultado = [];
  for (let i = 0; i < cantidad; i++) {
    resultado.push(colores[i % colores.length]);
  }

  return resultado;
}

function hexATransparencia(hex, alpha = 0.5) {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);

  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA ALMACENAMIENTO
 * ========================================
 */
function guardarEnStorage(clave, valor) {
  try {
    localStorage.setItem(clave, JSON.stringify(valor));
    return true;
  } catch (e) {
    console.warn("No se pudo guardar en localStorage:", e);
    return false;
  }
}

function obtenerDeStorage(clave, valorDefault = null) {
  try {
    const item = localStorage.getItem(clave);
    return item ? JSON.parse(item) : valorDefault;
  } catch (e) {
    console.warn("No se pudo obtener de localStorage:", e);
    return valorDefault;
  }
}

function eliminarDeStorage(clave) {
  try {
    localStorage.removeItem(clave);
    return true;
  } catch (e) {
    console.warn("No se pudo eliminar de localStorage:", e);
    return false;
  }
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA AJAX
 * ========================================
 */
function realizarPeticionAjax(opciones) {
  const configuracionDefault = {
    dataType: "json",
    timeout: 30000,
    beforeSend: function () {
      console.log("Realizando petición:", opciones.url);
    },
    error: function (xhr, status, error) {
      console.error("Error en petición AJAX:", {
        url: opciones.url,
        status: status,
        error: error,
        response: xhr.responseText,
      });
    },
  };

  const configuracionFinal = { ...configuracionDefault, ...opciones };

  return $.ajax(configuracionFinal);
}

function construirParametrosURL(objeto) {
  const params = new URLSearchParams();

  Object.keys(objeto).forEach((clave) => {
    const valor = objeto[clave];
    if (valor !== null && valor !== undefined && valor !== "") {
      params.append(clave, valor);
    }
  });

  return params.toString();
}

/**
 * ========================================
 * SISTEMA DE NOTIFICACIONES TOAST
 * ========================================
 */
function mostrarToast(mensaje, tipo = "info", duracion = 4000) {
  const iconos = {
    success: "fas fa-check-circle",
    error: "fas fa-exclamation-circle",
    warning: "fas fa-exclamation-triangle",
    info: "fas fa-info-circle",
  };

  const colores = {
    success: "text-success",
    error: "text-danger",
    warning: "text-warning",
    info: "text-primary",
  };

  const fondos = {
    success: "bg-success-subtle border-success",
    error: "bg-danger-subtle border-danger",
    warning: "bg-warning-subtle border-warning",
    info: "bg-primary-subtle border-primary",
  };

  const toastId = "toast-" + Date.now();
  const toastHTML = `
        <div id="${toastId}" class="toast ${fondos[tipo]} border" role="alert">
            <div class="toast-header ${fondos[tipo]} border-0">
                <i class="${iconos[tipo]} ${colores[tipo]} me-2"></i>
                <strong class="me-auto ${colores[tipo]}">Relatorio USD</strong>
                <small class="text-muted">${new Date().toLocaleTimeString(
                  "es-PY",
                  { hour: "2-digit", minute: "2-digit" }
                )}</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${mensaje}</div>
        </div>
    `;

  $(".toast-container").append(toastHTML);

  const toastElement = document.getElementById(toastId);
  const toast = new bootstrap.Toast(toastElement, {
    delay: duracion,
    autohide: true,
  });

  toast.show();

  toastElement.addEventListener("hidden.bs.toast", function () {
    toastElement.remove();
  });
}

function mostrarToastConAccion(mensaje, tipo, accion) {
  const toastId = "toast-action-" + Date.now();
  const iconos = {
    success: "fas fa-check-circle",
    error: "fas fa-exclamation-circle",
    warning: "fas fa-exclamation-triangle",
    info: "fas fa-info-circle",
  };

  const colores = {
    success: "text-success",
    error: "text-danger",
    warning: "text-warning",
    info: "text-primary",
  };

  const fondos = {
    success: "bg-success-subtle border-success",
    error: "bg-danger-subtle border-danger",
    warning: "bg-warning-subtle border-warning",
    info: "bg-primary-subtle border-primary",
  };

  const toastHTML = `
        <div id="${toastId}" class="toast ${fondos[tipo]} border" role="alert">
            <div class="toast-header ${fondos[tipo]} border-0">
                <i class="${iconos[tipo]} ${colores[tipo]} me-2"></i>
                <strong class="me-auto ${colores[tipo]}">Relatorio USD</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${mensaje}
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-${tipo}" onclick="${accion.funcion}">
                        ${accion.texto}
                    </button>
                </div>
            </div>
        </div>
    `;

  $(".toast-container").append(toastHTML);

  const toastElement = document.getElementById(toastId);
  const toast = new bootstrap.Toast(toastElement, {
    delay: 8000, // Más tiempo para toasts con acción
    autohide: true,
  });

  toast.show();

  toastElement.addEventListener("hidden.bs.toast", function () {
    toastElement.remove();
  });
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA DEBUG
 * ========================================
 */
function log(mensaje, tipo = "info") {
  const timestamp = new Date().toISOString();
  const prefijos = {
    info: "ℹ️",
    success: "✅",
    warning: "⚠️",
    error: "❌",
    debug: "🐛",
  };

  const prefijo = prefijos[tipo] || "ℹ️";
  console.log(`${prefijo} [${timestamp}] ${mensaje}`);
}

function debugObject(objeto, nombre = "Objeto") {
  console.group(`🔍 DEBUG: ${nombre}`);
  console.log("Tipo:", typeof objeto);
  console.log("Contenido:", objeto);
  if (typeof objeto === "object" && objeto !== null) {
    console.log("Keys:", Object.keys(objeto));
    console.log("Valores:", Object.values(objeto));
  }
  console.groupEnd();
}

function medirTiempo(nombre, funcion) {
  const inicio = performance.now();
  const resultado = funcion();
  const fin = performance.now();

  console.log(`⏱️ ${nombre} tardó ${(fin - inicio).toFixed(2)}ms`);
  return resultado;
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA EXPORTACIÓN
 * ========================================
 */
function exportarACSV(datos, nombreArchivo = "datos.csv") {
  if (!Array.isArray(datos) || datos.length === 0) {
    mostrarToast("No hay datos para exportar", "warning");
    return;
  }

  // Obtener encabezados del primer objeto
  const encabezados = Object.keys(datos[0]);

  // Crear contenido CSV
  let contenidoCSV = encabezados.join(",") + "\n";

  datos.forEach((fila) => {
    const valores = encabezados.map((encabezado) => {
      let valor = fila[encabezado] || "";
      // Escapar comillas y agregar comillas si contiene comas
      if (
        typeof valor === "string" &&
        (valor.includes(",") || valor.includes('"'))
      ) {
        valor = '"' + valor.replace(/"/g, '""') + '"';
      }
      return valor;
    });
    contenidoCSV += valores.join(",") + "\n";
  });

  // Crear y descargar archivo
  const blob = new Blob([contenidoCSV], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = nombreArchivo;
  link.style.display = "none";

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  mostrarToast(`Archivo ${nombreArchivo} descargado exitosamente`, "success");
}

/**
 * ========================================
 * INICIALIZACIÓN DE UTILIDADES
 * ========================================
 */
$(document).ready(function () {
  // Configurar ajaxSetup global para manejo de errores
  $.ajaxSetup({
    timeout: 30000,
    error: function (xhr, status, error) {
      if (status === "timeout") {
        mostrarToast(
          "La petición tardó demasiado tiempo. Intente nuevamente.",
          "warning"
        );
      } else if (xhr.status === 0) {
        mostrarToast(
          "Sin conexión a internet. Verifique su conexión.",
          "error"
        );
      } else if (xhr.status >= 500) {
        mostrarToast("Error del servidor. Intente más tarde.", "error");
      }
    },
  });

  // Log de inicialización
  log("Sistema de utilidades inicializado", "success");
});
