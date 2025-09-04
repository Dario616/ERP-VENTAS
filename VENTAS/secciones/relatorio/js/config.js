/**
 * config.js - Configuración e Inicialización del Sistema
 * Relatorio de Ventas USD
 */

// Variables globales
const CONFIG = window.CONFIG_RELATORIO;
const PUEDE_VER_TODOS = window.PUEDE_VER_TODOS;
const ES_ADMIN = window.ES_ADMIN;
const DATOS_FILTROS = window.DATOS_FILTROS;
const MENSAJES = window.MENSAJES;

// Variables para gráficos
let chartVentasPeriodo = null;
let chartProductos = null;
let chartVendedores = null;
let chartDistribucionMonedas = null;
let chartDistribucionSectores = null;

// Variables para datos globales
let datosVendedores = [];
let datosPeriodo = [];
let datosProductos = [];
let datosMonedas = [];
let datosSectores = [];

// Variables para tasas dinámicas
let tasasOriginales = {};
let tasasActuales = {};
let tasasCache = {};

// Variables para tabla
let ventasData = [];
let paginaActual = 1;
let registrosPorPagina = 10;

let chartTop5Vendedores = null;
/**
 * ========================================
 * INICIALIZACIÓN DEL SISTEMA
 * ========================================
 */
$(document).ready(function () {
  console.log("🚀 Relatorio de Ventas USD inicializado");
  console.log("💵 Moneda del sistema:", CONFIG.moneda);
  console.log("👤 Usuario:", CONFIG.usuario);
  console.log("🔐 Permisos:", {
    admin: CONFIG.esAdmin,
    verTodos: CONFIG.puedeVerTodos,
  });

  // Cargar tasas dinámicas al inicio
  cargarTasasDinamicas().then(() => {
    // Inicializar sistema después de cargar tasas
    inicializarSistema();
  });

  // Event listeners
  configurarEventListeners();

  // Mostrar mensajes si existen
  mostrarMensajesIniciales();
});

function inicializarSistema() {
  mostrarLoadingGeneral();
  cargarGraficos();
  cargarTablaDetallada();
}

function configurarEventListeners() {
  // Eventos principales del formulario
  $("#filtrosForm").submit(function (e) {
    e.preventDefault();
    if (validarFormulario()) {
      actualizarDatos();
    }
  });

  $("#btnLimpiarFiltros").click(limpiarFiltros);

  // Validación en tiempo real de fechas
  $("#fecha_inicio, #fecha_fin").change(function () {
    validarRangoFechas();
  });

  // Event listeners para ordenamiento
  $('input[name="ordenVendedores"]').change(function () {
    if (datosVendedores.length > 0) {
      actualizarGraficoConOrdenamiento();
    }
  });

  $('input[name="ordenPeriodo"]').change(function () {
    if (datosPeriodo.length > 0) {
      actualizarGraficoPeriodoConOrdenamiento();
    }
  });

  $('input[name="ordenProductos"]').change(function () {
    if (datosProductos.length > 0) {
      actualizarGraficoProductosConOrdenamiento();
    }
  });

  $('input[name="ordenSectores"]').change(function () {
    if (datosSectores.length > 0) {
      actualizarGraficoSectoresConOrdenamiento();
    }
  });

  $('input[name="ordenTop5Vendedores"]').change(function () {
    if (PUEDE_VER_TODOS) {
      // Recargar datos y aplicar nuevo ordenamiento
      cargarGraficoTop5Vendedores();
    }
  });

  // Event listeners para el modal del PDF
  configurarEventListenersPDF();

  // Event listeners para la configuración de tasas (solo administradores)
  if (ES_ADMIN) {
    configurarEventListenersConfiguracion();
  }
}

/**
 * ========================================
 * FUNCIONES PRINCIPALES DE DATOS
 * ========================================
 */
function actualizarDatos() {
  mostrarLoadingGeneral();
  cargarDashboard();
  cargarGraficos();
  cargarTablaDetallada();
  mostrarToast(
    "Datos actualizados correctamente con tasas dinámicas",
    "success"
  );
}

function mostrarLoadingGeneral() {
  $(".loading-overlay").show();
}

function ocultarLoadingGeneral() {
  $(".loading-overlay").hide();
}

function cargarDashboard() {
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "datos_dashboard",
    },
    dataType: "json",
    success: function (response) {
      if (response.success) {
        actualizarDashboardCards(response.datos);
      } else {
        console.error("Error cargando dashboard:", response.error);
        mostrarToast("Error al cargar métricas: " + response.error, "error");
      }
    },
    error: function (xhr, status, error) {
      console.error("Error en petición dashboard:", error);
      mostrarToast("Error de conexión al cargar métricas", "error");
    },
  });
}

function actualizarDashboardCards(datos) {
  $("#cantidadVentas").text(datos.cantidad_ventas || 0);
  $("#ticketPromedio").text(formatearMoneda(datos.promedio_venta || 0));
  $("#clientesUnicos").text(datos.clientes_unicos || 0);
  $("#ventasCredito").text(datos.ventas_credito || 0);

  // Calcular y mostrar porcentaje de crédito
  const totalVentas = datos.cantidad_ventas || 0;
  const ventasCredito = datos.ventas_credito || 0;
  const porcentajeCredito =
    totalVentas > 0 ? Math.round((ventasCredito / totalVentas) * 100) : 0;
  $("#porcentajeCredito").text(porcentajeCredito);

  // Actualizar variaciones si existen
  if (datos.variaciones) {
    actualizarCambios(datos.variaciones);
  }

  // Agregar animación a los números
  animarContadores();
}

function actualizarCambios(variaciones) {
  if (variaciones.cantidad_ventas) {
    const variacion = variaciones.cantidad_ventas;
    const elemento = document.getElementById("cambioCantidadVentas");

    if (elemento) {
      const icono =
        variacion.direccion === "up" ? "fa-arrow-up" : "fa-arrow-down";
      const clase = variacion.direccion === "up" ? "positive" : "negative";

      elemento.className = `stat-change ${clase}`;
      elemento.innerHTML = `
                <i class="fas ${icono}"></i>
                ${Math.abs(variacion.porcentaje)}% vs período anterior
            `;
    }
  }

  // Otras variaciones
  ["total_ventas", "promedio_venta", "clientes_unicos"].forEach((key) => {
    if (variaciones[key]) {
      const elemento = document.getElementById(
        `cambio${key
          .split("_")
          .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
          .join("")}`
      );
      if (elemento) {
        const variacion = variaciones[key];
        const icono =
          variacion.direccion === "up" ? "fa-arrow-up" : "fa-arrow-down";
        const clase = variacion.direccion === "up" ? "positive" : "negative";

        elemento.className = `stat-change ${clase}`;
        elemento.innerHTML = `
                    <i class="fas ${icono}"></i>
                    ${Math.abs(variacion.porcentaje)}% vs período anterior
                `;
      }
    }
  });
}

function animarContadores() {
  $(".stat-value").each(function () {
    const $this = $(this);
    const finalValue = $this.text();
    const numeroFinal = finalValue.replace(/[^0-9.]/g, "");

    if (numeroFinal) {
      $this.prop("Counter", 0).animate(
        {
          Counter: parseFloat(numeroFinal),
        },
        {
          duration: 1000,
          easing: "swing",
          step: function (now) {
            if (finalValue.includes("$")) {
              $this.text(formatearMoneda(now));
            } else {
              $this.text(Math.ceil(now).toLocaleString("en-US"));
            }
          },
        }
      );
    }
  });
}

function mostrarMensajesIniciales() {
  if (MENSAJES.mensaje) {
    mostrarToast(MENSAJES.mensaje, "success");
  }

  if (MENSAJES.error) {
    mostrarToast(MENSAJES.error, "error");
  }
}

function limpiarFiltros() {
  $("#fecha_inicio").val(CONFIG.mesActual);
  $("#fecha_fin").val(CONFIG.fechaActual);
  $("#cliente").val("");
  if (PUEDE_VER_TODOS) {
    $("#vendedor").val("");
  }
  $("#estado").val("");

  actualizarDatos();
  mostrarToast("Filtros limpiados", "info");
}

function obtenerParametrosFiltros() {
  return {
    fecha_inicio: $("#fecha_inicio").val(),
    fecha_fin: $("#fecha_fin").val(),
    cliente: $("#cliente").val(),
    vendedor: $("#vendedor").val() || "",
    estado: $("#estado").val(),
  };
}
