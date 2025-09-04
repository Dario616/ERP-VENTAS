/**
 * Relatorio de Ventas USD - JavaScript
 * Sistema de reportes con tasas de conversión dinámicas desde BD
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
let datosVendedores = []; // Variable global para manejar ordenamiento vendedores
let datosPeriodo = []; // Variable global para manejar ordenamiento período

// Variables para configuración de tasas dinámicas
let tasasOriginales = {}; // Para comparar cambios
let tasasActuales = {}; // Tasas en edición
let tasasCache = {}; // Cache de tasas cargadas desde BD
let ventasData = [];
let paginaActual = 1;
let registrosPorPagina = 10;
let datosProductos = []; // Variable global para manejar ordenamiento productos
let chartDistribucionMonedas = null;
let datosMonedas = [];
let chartDistribucionSectores = null;
let datosSectores = [];

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

function cargarGraficoDistribucionMonedas() {
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "distribucion_por_moneda",
    },
    dataType: "json",
    success: function (response) {
      $("#loadingMonedas").hide();
      if (response.success && response.datos && response.datos.length > 0) {
        datosMonedas = response.datos;
        actualizarGraficoDistribucionMonedas(response.datos);
        $("#monedasCount").text(`${response.datos.length} monedas`);
      } else {
        mostrarGraficoVacio(
          "chartDistribucionMonedas",
          "No hay datos de monedas"
        );
        $("#monedasCount").text("0 monedas");
      }
    },
    error: function () {
      $("#loadingMonedas").hide();
      mostrarGraficoVacio(
        "chartDistribucionMonedas",
        "Error al cargar distribución"
      );
      $("#monedasCount").text("Error");
    },
  });
}

function actualizarGraficoDistribucionMonedas(datos) {
  const ctx = document
    .getElementById("chartDistribucionMonedas")
    .getContext("2d");

  if (chartDistribucionMonedas) {
    chartDistribucionMonedas.destroy();
  }

  // Configurar colores para cada moneda
  const coloresMonedas = {
    USD: "#28a745", // Verde para USD
    PYG: "#dc3545", // Rojo para Guaraníes
    BRL: "#ffc107", // Amarillo para Reales
  };

  const labels = datos.map((item) => {
    const moneda = item.moneda_original || "USD";
    const simbolo = obtenerSimboloMoneda(moneda);
    return `${simbolo} ${getNombreMoneda(moneda)}`;
  });

  // 🔥 CAMBIO PRINCIPAL: Usar porcentajes en lugar de valores absolutos
  const porcentajes = datos.map((item) => parseFloat(item.porcentaje));
  const valores = datos.map((item) => parseFloat(item.total_original));

  const colores = datos.map((item) => {
    const moneda = item.moneda_original || "USD";
    return coloresMonedas[moneda] || "#6c757d";
  });

  const coloresBorde = colores.map((color) => color);

  chartDistribucionMonedas = new Chart(ctx, {
    type: "pie",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Distribución por Moneda",
          data: porcentajes, // 🔥 USAR PORCENTAJES EN LUGAR DE VALORES
          backgroundColor: colores,
          borderColor: coloresBorde,
          borderWidth: 2,
          hoverBorderWidth: 3,
          hoverOffset: 10,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,

      radius: "90%",

      layout: {
        padding: {
          top: 0,
          bottom: 20,
          left: 10,
          right: 10,
        },
      },

      plugins: {
        legend: {
          display: true,
          position: "bottom",
          labels: {
            usePointStyle: true,
            padding: 5,
            font: {
              size: 15,
            },

            boxHeight: 8,
            boxWidth: 8,
            generateLabels: function (chart) {
              const dataset = chart.data.datasets[0];
              return chart.data.labels.map((label, index) => ({
                text: `${label} (${porcentajes[index]}%)`,
                fillStyle: dataset.backgroundColor[index],
                strokeStyle: dataset.borderColor[index],
                pointStyle: "circle",
                hidden: false,
                index: index,
              }));
            },
          },
          align: "center",
          maxHeight: 50,
        },
        tooltip: {
          backgroundColor: "rgba(0,0,0,0.8)",
          titleColor: "white",
          bodyColor: "white",
          borderColor: "#667eea",
          borderWidth: 1,
          displayColors: true,
          callbacks: {
            title: function (context) {
              const item = datos[context[0].dataIndex];
              return `💰 ${getNombreMoneda(item.moneda_original)}`;
            },
            label: function (context) {
              const item = datos[context.dataIndex];
              const simbolo = obtenerSimboloMoneda(item.moneda_original);
              return [
                `💵 Valor Original: ${simbolo} ${parseFloat(
                  item.total_original
                ).toLocaleString("en-US", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}`,
                `💸 Equivalente USD: $${parseFloat(
                  item.total_usd
                ).toLocaleString("en-US", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}`,
                `🛒 Ventas: ${item.cantidad_ventas}`,
                `📊 Participación: ${item.porcentaje}%`,
              ];
            },
          },
        },
      },
      animation: {
        duration: 1000,
        easing: "easeInOutQuart",
        animateRotate: true,
        animateScale: true,
      },
    },
    // 🔥 REGISTRAR EL PLUGIN PERSONALIZADO
    plugins: [
      {
        id: "customDataLabels",
        afterDatasetsDraw: function (chart, args, options) {
          const ctx = chart.ctx;
          const dataset = chart.data.datasets[0];
          const meta = chart.getDatasetMeta(0);

          ctx.save();
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillStyle = "#fff";
          ctx.strokeStyle = "#000";
          ctx.lineWidth = 3;

          meta.data.forEach((element, index) => {
            const percentage = dataset.data[index];

            // Solo mostrar etiquetas en segmentos mayores a 3%
            if (percentage >= 3) {
              const position = element.tooltipPosition();
              const moneda = datos[index].moneda_original || "USD";
              const simbolo = obtenerSimboloMoneda(moneda);

              // Configurar fuentes más pequeñas y proporcionadas
              const monedaText = `${simbolo} ${moneda}`;
              const percentageText = `${percentage}%`;

              // Calcular posiciones para dos líneas con menor espaciado
              const lineHeight = 14;
              const topY = position.y - lineHeight / 2;
              const bottomY = position.y + lineHeight / 2;

              // Dibujar porcentaje (línea inferior) - tamaño moderado
              ctx.font = "bold 8px Arial";
              ctx.strokeText(percentageText, position.x, bottomY);
              ctx.fillText(percentageText, position.x, bottomY);

              // Dibujar nombre de moneda (línea superior) - más pequeño
              ctx.font = "bold 10px Arial";
              ctx.strokeText(monedaText, position.x, topY);
              ctx.fillText(monedaText, position.x, topY);
            }
          });

          ctx.restore();
        },
      },
    ],
  });
}

/**
 * Obtener símbolo de moneda
 */
function obtenerSimboloMoneda(moneda) {
  const simbolos = {
    USD: "$",
    PYG: "₲",
    BRL: "R$",
    EUR: "€",
    ARS: "$",
    Dólares: "$",
    Guaraníes: "₲",
    Real: "R$",
    "Real brasileño": "R$",
  };

  return simbolos[moneda] || "$";
}

/**
 * Obtener nombre completo de moneda
 */
function getNombreMoneda(moneda) {
  const nombres = {
    USD: "Dólares",
    PYG: "Guaraníes",
    BRL: "Reales",
    EUR: "Euros",
    ARS: "Pesos Argentinos",
    Dólares: "Dólares",
    Guaraníes: "Guaraníes",
    Real: "Reales",
    "Real brasileño": "Reales",
  };

  return nombres[moneda] || moneda;
}

/**
 * ========================================
 * SISTEMA DE TASAS DINÁMICAS
 * ========================================
 */
function cargarTasasDinamicas() {
  return new Promise((resolve, reject) => {
    $.ajax({
      url: "relatorio.php",
      method: "GET",
      data: { action: "obtener_tasas_conversion" },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          // Actualizar cache de tasas
          tasasCache = response.datos || {};

          // Actualizar CONFIG global para compatibilidad
          CONFIG.tasas_conversion = tasasCache;

          console.log("✅ Tasas dinámicas cargadas:", tasasCache);
          resolve(tasasCache);
        } else {
          console.warn(
            "⚠️ Error cargando tasas, usando fallback:",
            response.error
          );
          // Usar tasas por defecto si falla
          tasasCache = {
            PYG: 7500,
            BRL: 5.55,
            USD: 1,
          };
          CONFIG.tasas_conversion = tasasCache;
          resolve(tasasCache);
        }
      },
      error: function (xhr, status, error) {
        console.error("❌ Error de conexión al cargar tasas:", error);
        // Usar tasas por defecto si falla completamente
        tasasCache = {
          PYG: 7500,
          BRL: 5.55,
          USD: 1,
        };
        CONFIG.tasas_conversion = tasasCache;
        resolve(tasasCache);
      },
    });
  });
}

function actualizarTasasEnSistema(nuevasTasas) {
  // Actualizar cache local
  tasasCache = { ...nuevasTasas };

  // Actualizar CONFIG global
  CONFIG.tasas_conversion = tasasCache;

  // Mostrar notificación
  mostrarToast(
    "Tasas de conversión actualizadas desde la base de datos",
    "success"
  );

  console.log("🔄 Tasas actualizadas en el sistema:", tasasCache);
}

/**
 * ========================================
 * CONFIGURACIÓN INICIAL
 * ========================================
 */
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

  // Event listeners para ordenamiento de vendedores
  $('input[name="ordenVendedores"]').change(function () {
    if (datosVendedores.length > 0) {
      actualizarGraficoConOrdenamiento();
    }
  });

  // Event listeners para ordenamiento de período
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

  // Event listeners para el modal del PDF
  configurarEventListenersPDF();

  // Event listeners para la configuración de tasas (solo administradores)
  if (ES_ADMIN) {
    configurarEventListenersConfiguracion();
  }
}

/**
 * ========================================
 * CONFIGURACIÓN DE TASAS DE CONVERSIÓN DINÁMICAS
 * ========================================
 */
function configurarEventListenersConfiguracion() {
  // Abrir modal de configuración
  $("#btnConfiguracion").click(function () {
    cargarTasasConversion();
  });

  // Recargar tasas desde BD
  $("#btnRecargarTasas").click(function () {
    recargarTasasDeBaseDatos();
  });

  // Guardar cambios en BD
  $("#btnGuardarTasas").click(function () {
    guardarTasasEnBaseDatos();
  });

  // Detectar cambios en los inputs de tasas
  $(document).on("input", ".tasa-input", function () {
    const moneda = $(this).data("moneda");
    const nuevoValor = parseFloat($(this).val()) || 0;

    // Actualizar equivalencia en tiempo real
    actualizarEquivalencia(moneda, nuevoValor);

    // Detectar cambios para habilitar botón guardar
    detectarCambiosEnTasas();
  });
}

function cargarTasasConversion() {
  $("#loadingTasas").show();
  $("#tablaTasasContainer").hide();
  $("#errorTasas").hide();

  // Usar tasas del cache (ya cargadas desde BD)
  if (Object.keys(tasasCache).length > 0) {
    $("#loadingTasas").hide();
    tasasOriginales = { ...tasasCache };
    tasasActuales = { ...tasasCache };
    mostrarTasasEnTabla(tasasCache);
    $("#ultimaActualizacion").text(new Date().toLocaleString("es-PY"));
    $("#tablaTasasContainer").show();
  } else {
    // Si no hay cache, recargar desde BD
    recargarTasasDeBaseDatos();
  }
}

function recargarTasasDeBaseDatos() {
  $("#loadingTasas").show();
  $("#tablaTasasContainer").hide();
  $("#errorTasas").hide();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: { action: "obtener_tasas_conversion", reload: true },
    dataType: "json",
    success: function (response) {
      $("#loadingTasas").hide();

      if (response.success) {
        // Actualizar sistema con nuevas tasas
        actualizarTasasEnSistema(response.datos);

        // Actualizar modal
        tasasOriginales = { ...response.datos };
        tasasActuales = { ...response.datos };
        mostrarTasasEnTabla(response.datos);
        $("#ultimaActualizacion").text(
          response.timestamp || new Date().toLocaleString("es-PY")
        );
        $("#tablaTasasContainer").show();

        mostrarToast(
          "Tasas recargadas exitosamente desde la base de datos",
          "success"
        );
      } else {
        $("#errorTasas")
          .show()
          .find("p")
          .text(
            response.error || "Error al cargar tasas desde la base de datos"
          );
        console.error("Error cargando tasas desde BD:", response.error);
      }
    },
    error: function (xhr, status, error) {
      $("#loadingTasas").hide();
      $("#errorTasas")
        .show()
        .find("p")
        .text("Error de conexión al cargar las tasas");
      console.error("Error en petición de tasas:", error);
      mostrarToast("Error de conexión al cargar las tasas", "error");
    },
  });
}

function mostrarTasasEnTabla(tasas) {
  let html = "";

  // Configuración de monedas con información adicional
  const monedasConfig = {
    PYG: {
      nombre: "Guaraníes Paraguayos",
      simbolo: "₲",
      descripcion: "Moneda oficial de Paraguay",
      rango: { min: 1000, max: 50000 },
    },
    BRL: {
      nombre: "Reales Brasileños",
      simbolo: "R$",
      descripcion: "Moneda oficial de Brasil",
      rango: { min: 1, max: 100 },
    },
    USD: {
      nombre: "Dólares Estadounidenses",
      simbolo: "$",
      descripcion: "Moneda base del sistema",
      rango: { min: 1, max: 1 },
    },
  };

  Object.keys(monedasConfig).forEach(function (codigo) {
    const config = monedasConfig[codigo];
    const tasa = tasas[codigo] || (codigo === "USD" ? 1 : 0);
    const isUSD = codigo === "USD";

    html += `
            <tr>
                <td>
                    <div>
                        <strong>${config.nombre}</strong>
                        <br><small class="text-muted">${
                          config.descripcion
                        }</small>
                        ${
                          !isUSD
                            ? `<br><small class="text-info">Rango sugerido: ${config.rango.min.toLocaleString()} - ${config.rango.max.toLocaleString()}</small>`
                            : ""
                        }
                    </div>
                </td>
                <td class="text-center">
                    <span class="moneda-simbolo">${config.simbolo}</span>
                </td>
                <td>
                    ${
                      isUSD
                        ? `<div class="d-flex align-items-center">
                            <span class="form-control text-center fw-bold">1.00</span>
                            <small class="text-muted ms-2">(Base)</small>
                        </div>`
                        : `<input type="number" 
                                class="form-control tasa-input" 
                                data-moneda="${codigo}"
                                value="${tasa}" 
                                min="${config.rango.min}" 
                                max="${config.rango.max}"
                                step="0.01" 
                                placeholder="Ingrese tasa">`
                    }
                </td>
                <td class="text-center">
                    <small class="text-info" id="equivalencia-${codigo}">
                        ${getEquivalenciaTexto(codigo, tasa)}
                    </small>
                </td>
            </tr>
        `;
  });

  $("#tablaTasas").html(html);
  $("#tablaTasasContainer").show();

  // Deshabilitar el botón guardar inicialmente
  $("#btnGuardarTasas").prop("disabled", true);
}

function getEquivalenciaTexto(codigo, tasa) {
  if (codigo === "USD") {
    return "Moneda base";
  }

  const monedasConfig = {
    PYG: { simbolo: "₲", nombre: "Guaraníes" },
    BRL: { simbolo: "R$", nombre: "Reales" },
  };

  const config = monedasConfig[codigo];
  if (!config) return "";

  return `${config.simbolo} ${tasa.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} = $1 USD`;
}

function actualizarEquivalencia(moneda, tasa) {
  const equivalenciaElement = $(`#equivalencia-${moneda}`);
  if (equivalenciaElement.length) {
    equivalenciaElement.text(getEquivalenciaTexto(moneda, tasa));
  }
}

function detectarCambiosEnTasas() {
  let hayCambios = false;

  $(".tasa-input").each(function () {
    const moneda = $(this).data("moneda");
    const valorActual = parseFloat($(this).val()) || 0;
    const valorOriginal = tasasOriginales[moneda] || 0;

    if (Math.abs(valorActual - valorOriginal) > 0.001) {
      hayCambios = true;
      return false; // Break the loop
    }
  });

  $("#btnGuardarTasas").prop("disabled", !hayCambios);

  if (hayCambios) {
    $("#btnGuardarTasas")
      .removeClass("btn-success")
      .addClass("btn-warning")
      .html(
        '<i class="fas fa-exclamation-triangle me-1"></i>Guardar Cambios en BD'
      );
  } else {
    $("#btnGuardarTasas")
      .removeClass("btn-warning")
      .addClass("btn-success")
      .html('<i class="fas fa-save me-1"></i>Guardar en Base de Datos');
  }
}

function guardarTasasEnBaseDatos() {
  // Recopilar todas las tasas actuales
  const tasasParaGuardar = {};

  $(".tasa-input").each(function () {
    const moneda = $(this).data("moneda");
    const valor = parseFloat($(this).val()) || 0;

    if (valor > 0) {
      tasasParaGuardar[moneda] = valor;
    }
  });

  console.log("DEBUG: Tasas a guardar:", tasasParaGuardar);

  // Validar que las tasas sean razonables
  if (
    tasasParaGuardar.PYG &&
    (tasasParaGuardar.PYG < 1000 || tasasParaGuardar.PYG > 50000)
  ) {
    mostrarToast(
      "La tasa de Guaraníes parece incorrecta (debe estar entre 1,000 y 50,000)",
      "warning"
    );
    return;
  }

  if (
    tasasParaGuardar.BRL &&
    (tasasParaGuardar.BRL < 1 || tasasParaGuardar.BRL > 100)
  ) {
    mostrarToast(
      "La tasa de Reales parece incorrecta (debe estar entre 1 y 100)",
      "warning"
    );
    return;
  }

  // Mostrar loading
  $("#btnGuardarTasas")
    .prop("disabled", true)
    .html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando en BD...');

  const datosParaEnviar = {
    action: "actualizar_tasas_conversion",
    tasas: JSON.stringify(tasasParaGuardar),
  };

  console.log("DEBUG: Datos que se van a enviar:", datosParaEnviar);

  // Enviar a la base de datos
  $.ajax({
    url: "relatorio.php",
    method: "POST",
    data: datosParaEnviar,
    dataType: "json",
    beforeSend: function () {
      console.log("DEBUG: Enviando petición...");
    },
    success: function (response) {
      console.log("DEBUG: Respuesta exitosa:", response);

      if (response.success) {
        // Actualizar tasas originales
        tasasOriginales = { ...tasasParaGuardar };

        // Actualizar sistema con nuevas tasas
        actualizarTasasEnSistema(tasasParaGuardar);

        // Mostrar éxito
        mostrarToast(
          "Tasas de conversión actualizadas exitosamente en la base de datos",
          "success"
        );

        // Recargar datos para aplicar nuevas tasas
        setTimeout(() => {
          actualizarDatos();
        }, 1000);

        // Cerrar modal
        $("#modalConfiguracion").modal("hide");
      } else {
        mostrarToast(
          "Error al guardar las tasas: " +
            (response.error || "Error desconocido"),
          "error"
        );
      }

      // Restaurar botón
      $("#btnGuardarTasas")
        .removeClass("btn-warning")
        .addClass("btn-success")
        .prop("disabled", true)
        .html('<i class="fas fa-save me-1"></i>Guardar en Base de Datos');
    },
    error: function (xhr, status, error) {
      console.error("DEBUG: Error completo:", {
        status: status,
        error: error,
        responseText: xhr.responseText,
        readyState: xhr.readyState,
        statusText: xhr.statusText,
      });

      // Mostrar los primeros 500 caracteres de la respuesta
      console.error(
        "DEBUG: Primeros 500 chars de respuesta:",
        xhr.responseText.substring(0, 500)
      );

      mostrarToast(
        "Error de conexión al guardar las tasas. Ver consola para detalles.",
        "error"
      );

      // Restaurar botón
      $("#btnGuardarTasas")
        .removeClass("btn-warning")
        .addClass("btn-success")
        .prop("disabled", false)
        .html('<i class="fas fa-save me-1"></i>Guardar en Base de Datos');
    },
  });
}

/**
 * ========================================
 * CONFIGURACIÓN DEL PDF
 * ========================================
 */
function configurarEventListenersPDF() {
  // Abrir modal y cargar configuración actual
  $("#btnGenerarPDF").click(function () {
    cargarConfiguracionActual();
    actualizarVistaPrevia();
  });

  // Copiar filtros actuales al modal
  $("#btnCopiarFiltrosActuales").click(function () {
    $("#pdf_fecha_inicio").val($("#fecha_inicio").val());
    $("#pdf_fecha_fin").val($("#fecha_fin").val());
    $("#pdf_cliente").val($("#cliente").val());
    $("#pdf_estado").val($("#estado").val());
    if (PUEDE_VER_TODOS) {
      $("#pdf_vendedor").val($("#vendedor").val());
    }

    actualizarVistaPrevia();
    mostrarToast("Filtros copiados exitosamente", "success");
  });

  // Actualizar vista previa cuando cambien los filtros
  $("#formGenerarPDF input, #formGenerarPDF select").change(function () {
    actualizarVistaPrevia();
  });

  // Generar el PDF
  $("#btnGenerarDocumento").click(function () {
    generarPDF();
  });
}

function cargarConfiguracionActual() {
  const fechaInicio = $("#fecha_inicio").val() || "Todas";
  const fechaFin = $("#fecha_fin").val() || "Todas";
  const cliente = $("#cliente").val() || "Todos";
  const estado = $("#estado").val() || "Todos";
  const vendedor = $("#vendedor").val() || "Todos";

  const totalRegistros = $("#totalRegistros").text();

  // Mostrar información de tasas actuales
  const infoTasas = Object.keys(tasasCache)
    .map((codigo) => {
      if (codigo === "USD") return null;
      const simbolos = { PYG: "₲", BRL: "R$" };
      return `${simbolos[codigo] || ""} ${tasasCache[codigo]} = $1`;
    })
    .filter(Boolean)
    .join(" | ");

  $("#infoConfiguracionActual").html(`
        <div class="row">
            <div class="col-6"><strong>Período:</strong> ${fechaInicio} - ${fechaFin}</div>
            <div class="col-6"><strong>Registros:</strong> ${totalRegistros}</div>
            <div class="col-6"><strong>Cliente:</strong> ${cliente}</div>
            <div class="col-6"><strong>Estado:</strong> ${estado}</div>
            ${
              PUEDE_VER_TODOS
                ? `<div class="col-6"><strong>Vendedor:</strong> ${vendedor}</div>`
                : ""
            }
            <div class="col-12 mt-2"><small class="text-info"><strong>Tasas actuales:</strong> ${infoTasas}</small></div>
        </div>
    `);
}

function actualizarVistaPrevia() {
  const fechaInicio = $("#pdf_fecha_inicio").val() || "No especificada";
  const fechaFin = $("#pdf_fecha_fin").val() || "No especificada";
  const cliente = $("#pdf_cliente").val() || "Todos";
  const estado = $("#pdf_estado").val() || "Todos";
  const vendedor = $("#pdf_vendedor").val() || "Todos";

  const incluirGraficos = $("#incluir_graficos").is(":checked");
  const incluirTotales = $("#incluir_totales").is(":checked");
  const incluirProductos = $("#incluir_productos").is(":checked");
  const formatoPapel = $("#formato_papel").val();

  // ✅ NUEVAS OPCIONES DE AGRUPACIÓN
  const tipoAgrupacionVentas = $(
    'input[name="tipo_agrupacion_ventas"]:checked'
  ).val();
  const agruparPorCliente = tipoAgrupacionVentas === "cliente";
  const agruparPorVendedor = tipoAgrupacionVentas === "vendedor";
  const agruparProductos = $("#agrupar_productos").is(":checked");

  const opciones = [];
  if (incluirGraficos) opciones.push("Gráficos");
  if (incluirTotales) opciones.push("Totales");
  if (incluirProductos) opciones.push("Productos");

  // ✅ AGREGAR OPCIONES DE AGRUPACIÓN
  const agrupaciones = [];
  if (agruparPorCliente) agrupaciones.push("Por Cliente");
  if (agruparPorVendedor) agrupaciones.push("Por Vendedor");
  if (agruparProductos) agrupaciones.push("Productos");

  $("#vistaPreviewConfiguracion").html(`
        <div class="row">
            <div class="col-6"><strong>Período:</strong> ${fechaInicio} - ${fechaFin}</div>
            <div class="col-6"><strong>Formato:</strong> ${formatoPapel}</div>
            <div class="col-6"><strong>Cliente:</strong> ${cliente}</div>
            <div class="col-6"><strong>Estado:</strong> ${estado}</div>
            ${
              $("#pdf_vendedor").length
                ? `<div class="col-6"><strong>Vendedor:</strong> ${vendedor}</div>`
                : ""
            }
            <div class="col-12"><strong>Incluye:</strong> ${
              opciones.join(", ") || "Solo tabla básica"
            }</div>
            ${
              agrupaciones.length > 0
                ? `<div class="col-12"><strong>Agrupaciones:</strong> ${agrupaciones.join(
                    ", "
                  )}</div>`
                : ""
            }
        </div>
    `);
}

function generarPDF() {
  // Validar fechas
  const fechaInicio = $("#pdf_fecha_inicio").val();
  const fechaFin = $("#pdf_fecha_fin").val();

  if (!fechaInicio || !fechaFin) {
    mostrarToast("Las fechas son requeridas para generar el PDF", "warning");
    return;
  }

  if (new Date(fechaInicio) > new Date(fechaFin)) {
    mostrarToast(
      "La fecha de inicio no puede ser mayor que la fecha fin",
      "warning"
    );
    return;
  }
  const tipoAgrupacionVentas = $(
    'input[name="tipo_agrupacion_ventas"]:checked'
  ).val();

  // Crear objeto con parámetros
  const parametros = {
    fecha_inicio: fechaInicio,
    fecha_fin: fechaFin,
    cliente: $("#pdf_cliente").val() || "",
    estado: $("#pdf_estado").val() || "",
    incluir_graficos: $("#incluir_graficos").is(":checked") ? "1" : "0",
    incluir_totales: $("#incluir_totales").is(":checked") ? "1" : "0",
    incluir_productos: $("#incluir_productos").is(":checked") ? "1" : "0",
    formato_papel: $("#formato_papel").val(),
    // ✅ NUEVOS PARÁMETROS DE AGRUPACIÓN
    agrupar_por_cliente: tipoAgrupacionVentas === "cliente" ? "1" : "0",
    agrupar_por_vendedor: tipoAgrupacionVentas === "vendedor" ? "1" : "0",
    agrupar_productos: $("#agrupar_productos").is(":checked") ? "1" : "0",
  };

  // Agregar vendedor solo si el usuario puede ver todos
  if (PUEDE_VER_TODOS) {
    parametros.vendedor = $("#pdf_vendedor").val() || "";
  }

  // Crear URL con parámetros
  const params = new URLSearchParams(parametros);

  // Mostrar loading
  $("#btnGenerarDocumento")
    .prop("disabled", true)
    .html('<i class="fas fa-spinner fa-spin me-1"></i>Generando...');

  // Abrir en nueva ventana
  const url = `${
    CONFIG.url_base
  }secciones/relatorio/pdf/relatorio_vendedor.php?${params.toString()}`;
  window.open(url, "_blank");

  // Restaurar botón después de un delay
  setTimeout(() => {
    $("#btnGenerarDocumento")
      .prop("disabled", false)
      .html('<i class="fas fa-download me-1"></i>Generar PDF');
    $("#modalGenerarPDF").modal("hide");
    mostrarToast("PDF generado exitosamente", "success");
  }, 2000);
}
/**
 * ========================================
 * VALIDACIONES Y UTILIDADES
 * ========================================
 */
function mostrarMensajesIniciales() {
  if (MENSAJES.mensaje) {
    mostrarToast(MENSAJES.mensaje, "success");
  }

  if (MENSAJES.error) {
    mostrarToast(MENSAJES.error, "error");
  }
}

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

    // Validar que el elemento existe antes de usarlo
    if (elemento) {
      const icono =
        variacion.direccion === "up" ? "fa-arrow-up" : "fa-arrow-down";
      const clase = variacion.direccion === "up" ? "positive" : "negative";

      elemento.className = `stat-change ${clase}`;
      elemento.innerHTML = `
              <i class="fas ${icono}"></i>
              ${Math.abs(variacion.porcentaje)}% vs período anterior
          `;
    } else {
      // Log para debug - puedes quitar esto después
      console.log("Elemento 'cambioCantidadVentas' no encontrado en el DOM");
    }
  }

  // Puedes agregar más variaciones aquí si las necesitas
  if (variaciones.total_ventas) {
    const elemento = document.getElementById("cambioTotalVentas");
    if (elemento) {
      const variacion = variaciones.total_ventas;
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

  if (variaciones.promedio_venta) {
    const elemento = document.getElementById("cambioPromedioVenta");
    if (elemento) {
      const variacion = variaciones.promedio_venta;
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

  if (variaciones.clientes_unicos) {
    const elemento = document.getElementById("cambioClientesUnicos");
    if (elemento) {
      const variacion = variaciones.clientes_unicos;
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

/**
 * ========================================
 * GRÁFICOS
 * ========================================
 */
function cargarGraficos() {
  cargarGraficoVentasPeriodo();
  cargarGraficoProductos();
  cargarGraficoDistribucionMonedas(); // ← AGREGAR ESTA LÍNEA
  cargarGraficoDistribucionSectores(); // ← AGREGAR ESTA LÍNEA
  if (PUEDE_VER_TODOS) {
    cargarGraficoVendedores();
  }
}

function encontrarMaximo(datos, campo) {
  if (!datos || datos.length === 0) return { valor: 0, indice: -1 };

  let maxValor = 0;
  let maxIndice = 0;

  datos.forEach((item, index) => {
    const valor = parseFloat(item[campo]) || 0;
    if (valor > maxValor) {
      maxValor = valor;
      maxIndice = index;
    }
  });

  return { valor: maxValor, indice: maxIndice, item: datos[maxIndice] };
}

function encontrarMinimo(datos, campo) {
  if (!datos || datos.length === 0) return { valor: 0, indice: -1 };

  let minValor = Infinity;
  let minIndice = 0;

  datos.forEach((item, index) => {
    const valor = parseFloat(item[campo]) || 0;
    if (valor < minValor) {
      minValor = valor;
      minIndice = index;
    }
  });

  return { valor: minValor, indice: minIndice, item: datos[minIndice] };
}

function cargarGraficoVentasPeriodo() {
  const params = obtenerParametrosFiltros();

  const fechaInicio = new Date(params.fecha_inicio);
  const fechaFin = new Date(params.fecha_fin);

  const diasDiferencia = Math.ceil(
    (fechaFin - fechaInicio) / (1000 * 60 * 60 * 24)
  );

  const mesesDiferencia =
    (fechaFin.getFullYear() - fechaInicio.getFullYear()) * 12 +
    (fechaFin.getMonth() - fechaInicio.getMonth()) +
    1;

  const agruparPor = diasDiferencia <= 30 ? "dia" : "mes";

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "ventas_por_periodo",
      agrupar_por: agruparPor,
    },
    dataType: "json",
    success: function (response) {
      $("#loadingVentasPeriodo").hide();
      if (response.success && response.datos.length > 0) {
        datosPeriodo = response.datos;
        actualizarGraficoPeriodoConOrdenamiento();

        const unidad = agruparPor === "dia" ? "días" : "meses";
        const cantidad =
          agruparPor === "dia" ? response.datos.length : mesesDiferencia;
        $("#periodoCount").text(`${cantidad} ${unidad}`);
      } else {
        mostrarGraficoVacio(
          "chartVentasPeriodo",
          "No hay datos para el período seleccionado"
        );
        $("#periodoCount").text("0 días");
      }
    },
    error: function () {
      $("#loadingVentasPeriodo").hide();
      mostrarGraficoVacio("chartVentasPeriodo", "Error al cargar datos");
      $("#periodoCount").text("Error");
    },
  });
}

// Nueva función para cargar sectores
function cargarGraficoDistribucionSectores() {
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "distribucion_por_sectores",
    },
    dataType: "json",
    success: function (response) {
      $("#loadingSectores").hide();
      if (response.success && response.datos && response.datos.length > 0) {
        // ✅ GUARDAR datos en variable global
        datosSectores = response.datos;

        // ✅ USAR la nueva función con ordenamiento
        actualizarGraficoSectoresConOrdenamiento();

        $("#sectoresCount").text(`${response.datos.length} sectores`);
      } else {
        mostrarGraficoVacio(
          "chartDistribucionSectores",
          "No hay datos de sectores"
        );
        $("#sectoresCount").text("0 sectores");
        datosSectores = []; // ✅ Limpiar datos
      }
    },
    error: function () {
      $("#loadingSectores").hide();
      mostrarGraficoVacio(
        "chartDistribucionSectores",
        "Error al cargar sectores"
      );
      $("#sectoresCount").text("Error");
      datosSectores = []; // ✅ Limpiar datos
    },
  });
}
function actualizarGraficoSectoresConOrdenamiento() {
  const tipoOrden = $('input[name="ordenSectores"]:checked').val();

  // Verificar que tenemos datos
  if (!datosSectores || datosSectores.length === 0) {
    console.warn("⚠️ No hay datos de sectores para reordenar");
    return;
  }

  // Ordenar TODOS los datos disponibles
  const datosOrdenados = ordenarDatosSectores(datosSectores, tipoOrden);

  // Tomar solo los TOP 8 para mostrar en el gráfico
  const top8 = datosOrdenados.slice(0, 8);

  // DEBUG: Log para verificar que funciona
  console.log("🔄 Ordenamiento sectores:", {
    criterio: tipoOrden,
    total_disponibles: datosSectores.length,
    despues_ordenar: datosOrdenados.length,
    mostrando_top: top8.length,
    primer_sector: top8[0]?.tipo?.substring(0, 30) + "...",
  });

  // Actualizar gráfico con top 8
  actualizarGraficoDistribucionSectores(top8, tipoOrden);
}

function ordenarDatosSectores(datos, criterio) {
  const datosClonados = [...datos];

  switch (criterio) {
    case "ingresos":
      return datosClonados.sort(
        (a, b) => parseFloat(b.total_ingresos) - parseFloat(a.total_ingresos)
      );

    case "ventas":
      return datosClonados.sort(
        (a, b) => parseInt(b.ventas_asociadas) - parseInt(a.ventas_asociadas)
      );
    // ✅ NUEVA: Productos diferentes (variedad)
    case "productos":
      return datosClonados.sort(
        (a, b) =>
          parseInt(b.productos_diferentes) - parseInt(a.productos_diferentes)
      );

    // ✅ NUEVA: Cantidad vendida (volumen)
    case "cantidad":
      return datosClonados.sort(
        (a, b) => parseInt(b.cantidad_vendida) - parseInt(a.cantidad_vendida)
      );

    default:
      return datosClonados.sort(
        (a, b) => parseFloat(b.total_ingresos) - parseFloat(a.total_ingresos)
      );
  }
}

// Nueva función para actualizar el gráfico
function actualizarGraficoDistribucionSectores(datos, tipoOrden = "ingresos") {
  const ctx = document
    .getElementById("chartDistribucionSectores")
    .getContext("2d");

  if (chartDistribucionSectores) {
    chartDistribucionSectores.destroy();
  }

  // Preparar datos según el criterio de ordenamiento
  const labels = datos.map((item) => {
    const tipo = item.tipo || "Sin categoría";
    return tipo.length > 15 ? tipo.substring(0, 15) + "..." : tipo;
  });

  // ✅ DETERMINAR QUÉ DATOS MOSTRAR SEGÚN EL CRITERIO
  let datosGrafico, tooltipTitle;

  switch (tipoOrden) {
    case "ventas":
      datosGrafico = datos.map((item) =>
        parseFloat(item.ventas_asociadas || 0)
      );
      tooltipTitle = "🛒 Por Ventas";
      break;
    // ✅ NUEVA: Productos diferentes
    case "productos":
      datosGrafico = datos.map((item) =>
        parseFloat(item.productos_diferentes || 0)
      );
      tooltipTitle = "📦 Por Productos";
      break;

    // ✅ NUEVA: Cantidad vendida
    case "cantidad":
      datosGrafico = datos.map((item) =>
        parseFloat(item.cantidad_vendida || 0)
      );
      tooltipTitle = "📊 Por Cantidad";
      break;

    case "ingresos":
    default:
      datosGrafico = datos.map((item) => parseFloat(item.total_ingresos || 0));
      tooltipTitle = "💰 Por Ingresos";
      break;
  }

  // Calcular porcentajes basados en los datos mostrados
  const totalMostrado = datosGrafico.reduce((sum, val) => sum + val, 0);
  const porcentajes = datosGrafico.map((val) =>
    totalMostrado > 0 ? (val / totalMostrado) * 100 : 0
  );

  // Colores dinámicos mejorados
  const colores = [
    "#FF6384",
    "#36A2EB",
    "#FFCE56",
    "#4BC0C0",
    "#9966FF",
    "#FF9F40",
    "#C9CBCF",
    "#FF6B6B",
  ];

  chartDistribucionSectores = new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: labels,
      datasets: [
        {
          label: `Sectores ${tooltipTitle}`,
          data: porcentajes,
          backgroundColor: colores.slice(0, datos.length),
          borderColor: "#fff",
          borderWidth: 2,
          hoverBorderWidth: 3,
          hoverOffset: 8,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "60%",

      plugins: {
        legend: {
          display: true,
          position: "bottom",
          labels: {
            usePointStyle: true,
            padding: 8,
            font: { size: 11 },
            generateLabels: function (chart) {
              return chart.data.labels.map((label, index) => ({
                text: `${label} (${porcentajes[index].toFixed(1)}%)`,
                fillStyle: colores[index],
                hidden: false,
                index: index,
              }));
            },
          },
        },
        tooltip: {
          backgroundColor: "rgba(0,0,0,0.8)",
          callbacks: {
            title: function (context) {
              const item = datos[context[0].dataIndex];
              return `🏭 ${item.tipo || "Sin categoría"}`;
            },
            label: function (context) {
              const item = datos[context.dataIndex];

              // ✅ MOSTRAR INFORMACIÓN RELEVANTE SEGÚN EL FILTRO
              const baseInfo = [
                `💰 Ingresos: $${parseFloat(
                  item.total_ingresos
                ).toLocaleString()}`,
                `🛒 Ventas: ${item.ventas_asociadas}`,
                `📦 Productos: ${item.productos_diferentes}`,
                `📊 Participación: ${porcentajes[context.dataIndex].toFixed(
                  1
                )}%`,
              ];

              switch (tipoOrden) {
                case "ventas":
                  baseInfo[1] = `🛒 Ventas: ${item.ventas_asociadas} ⭐`;
                  break;

                case "productos":
                  baseInfo[2] = `📦 Productos: ${item.productos_diferentes} ⭐`;
                  break;

                case "cantidad":
                  baseInfo[3] = `📊 Cantidad: ${parseFloat(item.cantidad_vendida).toLocaleString()} ⭐`;
                  break;

                default:
                  baseInfo[0] = `💰 Ingresos: $${parseFloat(
                    item.total_ingresos
                  ).toLocaleString()} ⭐`;
              }

              return baseInfo;
            },
          },
        },
      },

      animation: {
        duration: 1000,
        easing: "easeInOutQuart",
      },
    },
  });

  // ✅ DEBUG: Log para verificar funcionamiento
  console.log("📈 Gráfico sectores actualizado:", {
    criterio: tipoOrden,
    sectores_mostrados: datos.length,
    total_valor: totalMostrado.toLocaleString(),
    primer_sector: datos[0]?.tipo,
  });
}

function actualizarGraficoPeriodoConOrdenamiento() {
  const tipoOrden = $('input[name="ordenPeriodo"]:checked').val();
  actualizarGraficoVentasPeriodo(datosPeriodo, tipoOrden);
}

function actualizarGraficoVentasPeriodo(datos, tipoMostrar = "ingresos") {
  const ctx = document.getElementById("chartVentasPeriodo").getContext("2d");

  if (chartVentasPeriodo) {
    chartVentasPeriodo.destroy();
  }

  // Calcular si es agrupación por días o meses
  const fechaInicio = new Date($("#fecha_inicio").val());
  const fechaFin = new Date($("#fecha_fin").val());
  const diasDiferencia = Math.ceil(
    (fechaFin - fechaInicio) / (1000 * 60 * 60 * 24)
  );
  const esPorDias = diasDiferencia <= 30;

  // Formatear labels según el tipo de agrupación
  const labels = datos.map((item) => {
    if (esPorDias) {
      return formatearFechaCorta(item.fecha_venta);
    } else {
      return formatearMes(item.fecha_venta);
    }
  });

  // Determinar qué datos mostrar
  let datosGrafico, labelDataset, colorPrincipal, colorFondo;

  if (tipoMostrar === "ventas") {
    datosGrafico = datos.map((item) => parseInt(item.cantidad_ventas || 0));
    labelDataset = "Cantidad de Ventas";
    colorPrincipal = "#28a745";
    colorFondo = "rgba(40, 167, 69, 0.1)";
  } else {
    datosGrafico = datos.map((item) => parseFloat(item.total_ventas));
    labelDataset = "Ingresos (USD)";
    colorPrincipal = "#667eea";
    colorFondo = "rgba(102, 126, 234, 0.1)";
  }

  // 🚀 ENCONTRAR EL PICO MÁXIMO Y MÍNIMO
  const campo = tipoMostrar === "ventas" ? "cantidad_ventas" : "total_ventas";
  const picoMaximo = encontrarMaximo(datos, campo);
  const picoMinimo = encontrarMinimo(datos, campo);

  // 🎨 CONFIGURAR COLORES Y TAMAÑOS DE PUNTOS
  const coloresPuntos = datosGrafico.map((_, index) => {
    if (index === picoMaximo.indice) return "#007527ff"; // Naranja para máximo
    if (index === picoMinimo.indice) return "#a81100ff"; // Rojo para mínimo
    return colorPrincipal; // Color normal
  });

  const tamañosPuntos = datosGrafico.map((_, index) => {
    if (index === picoMaximo.indice || index === picoMinimo.indice) return 8;
    return 5; // Tamaño normal
  });

  chartVentasPeriodo = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: labelDataset,
          data: datosGrafico,
          borderColor: colorPrincipal,
          backgroundColor: colorFondo,
          borderWidth: 3,
          fill: true,
          tension: 0.4,
          // 🎯 DESTACAR LOS PUNTOS MÁXIMO Y MÍNIMO
          pointBackgroundColor: coloresPuntos,
          pointRadius: tamañosPuntos,
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointHoverRadius: tamañosPuntos.map((size) => size + 2),
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        intersect: false,
        mode: "index",
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function (value) {
              return tipoMostrar === "ventas"
                ? value.toLocaleString("en-US")
                : "$" + value.toLocaleString("en-US");
            },
          },
          grid: { color: "rgba(0,0,0,0.1)" },
        },
        x: {
          grid: { color: "rgba(0,0,0,0.1)" },
          ticks: {
            maxRotation: esPorDias ? 45 : 0,
            maxTicksLimit: esPorDias ? 31 : 100,
          },
        },
      },
      plugins: {
        legend: {
          display: true,
          position: "top",
          labels: { usePointStyle: true, padding: 15 },
        },
        tooltip: {
          backgroundColor: "rgba(0,0,0,0.8)",
          titleColor: "white",
          bodyColor: "white",
          borderColor: colorPrincipal,
          borderWidth: 1,
          callbacks: {
            title: function (context) {
              const item = datos[context[0].dataIndex];
              // 📅 SIEMPRE MOSTRAR FECHA COMPLETA EN TOOLTIP
              const fecha = formatearFecha(item.fecha_venta);

              // 🏆 AGREGAR INDICADORES DE PICOS
              const esPicoMaximo = context[0].dataIndex === picoMaximo.indice;
              const esPicoMinimo = context[0].dataIndex === picoMinimo.indice;

              let indicador = "";
              if (esPicoMaximo) indicador = " 🏆 PICO MÁXIMO";
              else if (esPicoMinimo) indicador = " 📉 PICO MÍNIMO";

              return fecha + indicador;
            },
            label: function (context) {
              const item = datos[context.dataIndex];
              if (tipoMostrar === "ventas") {
                return [
                  `🛒 Ventas: ${item.cantidad_ventas || 0}`,
                  `💰 Ingresos: ${formatearMoneda(item.total_ventas)}`,
                ];
              } else {
                return [
                  `💰 Ingresos: ${formatearMoneda(item.total_ventas)}`,
                  `🛒 Ventas: ${item.cantidad_ventas || 0}`,
                ];
              }
            },
          },
        },
      },
      animation: { duration: 800, easing: "easeInOutQuart" },
    },
  });

  // 📊 MOSTRAR INFORMACIÓN DE PICOS EN CONSOLA (opcional, para debug)
  console.log("📈 Análisis de período:", {
    total_puntos: datos.length,
    pico_maximo: {
      fecha: picoMaximo.item?.fecha_venta,
      valor: picoMaximo.valor,
      posicion: picoMaximo.indice + 1,
    },
    pico_minimo: {
      fecha: picoMinimo.item?.fecha_venta,
      valor: picoMinimo.valor,
      posicion: picoMinimo.indice + 1,
    },
  });
}

function formatearFechaCorta(fecha) {
  if (!fecha) return "N/A";
  try {
    const fechaParts = fecha.split("-");
    if (fechaParts.length === 3) {
      const dia = fechaParts[2];
      const mes = fechaParts[1];
      return `${dia}/${mes}`;
    }
    return fecha;
  } catch (e) {
    return fecha;
  }
}

function formatearMes(fecha) {
  if (!fecha) return "N/A";
  try {
    const fechaObj = new Date(fecha + "T12:00:00");
    return fechaObj.toLocaleDateString("es-ES", {
      month: "short",
      year: "numeric",
    });
  } catch (e) {
    return fecha;
  }
}

function cargarGraficoProductos() {
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "productos_mas_vendidos",
      limite: 100, // ✅ FIX: Cambiar de 5 a 15
    },
    dataType: "json",
    success: function (response) {
      $("#loadingProductos").hide();
      if (
        response.success &&
        response.datos &&
        validarDatosProductos(response.datos)
      ) {
        datosProductos = response.datos;

        actualizarGraficoProductosConOrdenamiento();
        // ✅ FIX: Mostrar que tenemos más productos disponibles
        $("#productosCount").text(`5 de ${response.datos.length} productos`);
      } else {
        console.error("Error en datos de productos:", response);
        mostrarGraficoVacio("chartProductos", "No hay productos para mostrar");
        $("#productosCount").text("0 productos");
      }
    },
    error: function (xhr, status, error) {
      $("#loadingProductos").hide();
      console.error("Error AJAX productos:", { xhr, status, error });
      mostrarGraficoVacio("chartProductos", "Error al cargar productos");
      $("#productosCount").text("Error");
    },
  });
}

function actualizarGraficoProductosConOrdenamiento() {
  const tipoOrden = $('input[name="ordenProductos"]:checked').val();

  // ✅ FIX: Verificar que tenemos datos
  if (!datosProductos || datosProductos.length === 0) {
    console.warn("⚠️ No hay datos de productos para reordenar");
    return;
  }

  // ✅ FIX: Ordenar TODOS los datos disponibles
  const datosOrdenados = ordenarDatosProductos(datosProductos, tipoOrden);

  // ✅ FIX: Tomar solo los TOP 5 para mostrar en el gráfico
  const top5 = datosOrdenados.slice(0, 5);

  // ✅ DEBUG: Log para verificar que funciona
  console.log("🔄 Ordenamiento productos:", {
    criterio: tipoOrden,
    total_disponibles: datosProductos.length,
    despues_ordenar: datosOrdenados.length,
    mostrando_top: top5.length,
    primer_producto: top5[0]?.descripcion?.substring(0, 30) + "...",
  });

  // Actualizar gráfico con top 5
  actualizarGraficoProductos(top5, tipoOrden);
}

function ordenarDatosProductos(datos, criterio) {
  const datosClonados = [...datos];

  switch (criterio) {
    case "ingresos":
      return datosClonados.sort(
        (a, b) => parseFloat(b.total_ingresos) - parseFloat(a.total_ingresos)
      );

    case "ventas":
      return datosClonados.sort(
        (a, b) => parseInt(b.ventas_asociadas) - parseInt(a.ventas_asociadas)
      );

    case "cantidad":
      return datosClonados.sort(
        (a, b) => parseInt(b.cantidad_vendida) - parseInt(a.cantidad_vendida)
      );

    default:
      return datosClonados.sort(
        (a, b) => parseFloat(b.total_ingresos) - parseFloat(a.total_ingresos)
      );
  }
}

function actualizarGraficoProductos(datos, tipoOrden = "ingresos") {
  const ctx = document.getElementById("chartProductos").getContext("2d");

  if (chartProductos) {
    chartProductos.destroy();
  }

  const labels = datos.map((item) => {
    const descripcion = item.descripcion || "Sin descripción";
    // Reducir longitud para mejor visualización
    return descripcion.length > 20
      ? descripcion.substring(0, 20) + "..."
      : descripcion;
  });

  // Determinar qué datos mostrar según el criterio de ordenamiento
  let datosGrafico, labelDataset, colorBase, colorBorde;

  switch (tipoOrden) {
    case "ventas":
      datosGrafico = datos.map((item) => parseInt(item.ventas_asociadas || 0));
      labelDataset = "Número de Ventas";
      colorBase = "#fd7e14"; // Naranja más suave
      colorBorde = "#e8590c";
      break;

    case "cantidad":
      datosGrafico = datos.map((item) => parseInt(item.cantidad_vendida || 0));
      labelDataset = "Cantidad Vendida";
      colorBase = "#20c997"; // Verde azulado
      colorBorde = "#1aa085";
      break;

    case "ingresos":
    default:
      datosGrafico = datos.map((item) => parseFloat(item.total_ingresos));
      labelDataset = "Ingresos (USD)";
      colorBase = "#28a745"; // Verde original
      colorBorde = "#1e7e34";
      break;
  }

  // Generar colores degradados para cada barra
  const colores = datos.map((_, index) => {
    const intensity = 1 - index * 0.15;
    return adjustColorOpacity(colorBase, Math.max(intensity, 0.4));
  });

  const coloresBorde = datos.map(() => colorBorde);

  // En la función actualizarGraficoProductos, modifica la configuración del chart así:

  chartProductos = new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: labelDataset,
          data: datosGrafico,
          backgroundColor: colores,
          borderColor: coloresBorde,
          borderWidth: 0,
          borderRadius: 4,
          borderSkipped: false,
          // AGREGAR ESTAS LÍNEAS PARA CONTROLAR EL GROSOR:
          barThickness: 25, // Grosor fijo de 25px (ajusta este valor)
          maxBarThickness: 30, // Grosor máximo de 30px
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: "y", // Barras horizontales

      // AGREGAR ESTAS CONFIGURACIONES:
      datasets: {
        bar: {
          categoryPercentage: 0.95,
          barPercentage: 0.9,
        },
      },

      layout: {
        padding: { top: 5, right: 10, bottom: 32, left: 5 },
      },
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            callback: function (value) {
              if (tipoOrden === "ingresos") {
                return "$" + value.toLocaleString("en-US");
              } else {
                return value.toLocaleString("en-US");
              }
            },
            font: {
              size: 11,
            },
          },
          grid: {
            color: "rgba(0,0,0,0.1)",
            drawBorder: false,
          },
        },
        y: {
          ticks: {
            font: {
              size: 11,
              weight: 10,
            },
            color: "#495057",
          },
          grid: {
            display: false,
          },
        },
      },
      plugins: {
        legend: {
          display: true,
          position: "top",
          labels: {
            usePointStyle: true,
            padding: 15,
            font: {
              size: 12,
              weight: 500,
            },
          },
        },
        tooltip: {
          backgroundColor: "rgba(0,0,0,0.8)",
          titleColor: "white",
          bodyColor: "white",
          borderColor: colorBase,
          borderWidth: 1,
          displayColors: false,
          callbacks: {
            title: function (context) {
              const descripcion =
                datos[context[0].dataIndex].descripcion || "Sin descripción";
              // Insertar salto de línea cada 20 caracteres (o el número que prefieras)
              return descripcion.match(/.{1,85}/g).join("\n");
            },
            label: function (context) {
              const item = datos[context.dataIndex];
              return [
                `💰 Ingresos: $${parseFloat(item.total_ingresos).toLocaleString(
                  "en-US"
                )}`,
                `🛒 En ${item.ventas_asociadas} ventas`,
                `📦 Cantidad: ${item.cantidad_vendida} `,
              ];
            },
          },
        },
      },
      animation: {
        duration: 800,
        easing: "easeInOutQuart",
      },
    },
  });
}

// También necesitas actualizar la función adjustColorOpacity si está causando problemas
function adjustColorOpacity(color, opacity) {
  let r, g, b;

  if (color.startsWith("#")) {
    const hex = color.slice(1);
    r = parseInt(hex.substr(0, 2), 16);
    g = parseInt(hex.substr(2, 2), 16);
    b = parseInt(hex.substr(4, 2), 16);
  } else {
    // Manejar colores RGB o nombres de colores comunes
    const colorMap = {
      "#fd7e14": [253, 126, 20],
      "#20c997": [32, 201, 151],
      "#28a745": [40, 167, 69],
    };

    if (colorMap[color]) {
      [r, g, b] = colorMap[color];
    } else {
      // Valores por defecto si no se reconoce el color
      r = 102;
      g = 126;
      b = 234;
    }
  }

  return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

// Función auxiliar para verificar si hay datos válidos
function validarDatosProductos(datos) {
  if (!Array.isArray(datos) || datos.length === 0) {
    console.warn("No hay datos de productos para mostrar");
    return false;
  }

  // Verificar que cada item tenga las propiedades necesarias
  const datosValidos = datos.filter(
    (item) =>
      item.descripcion &&
      (item.total_ingresos || item.ventas_asociadas || item.cantidad_vendida)
  );

  if (datosValidos.length === 0) {
    console.warn("Los datos de productos no tienen las propiedades necesarias");
    return false;
  }

  return true;
}

function cargarGraficoVendedores() {
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "ventas_por_vendedor",
    },
    dataType: "json",
    success: function (response) {
      $("#loadingVendedores").hide();
      if (response.success && response.datos.length > 0) {
        datosVendedores = response.datos;
        actualizarGraficoConOrdenamiento();
        $("#vendedoresCount").text(`${response.datos.length} vendedores`);
      } else {
        mostrarGraficoVacio("chartVendedores", "No hay datos de vendedores");
        $("#vendedoresCount").text("0 vendedores");
      }
    },
    error: function () {
      $("#loadingVendedores").hide();
      mostrarGraficoVacio("chartVendedores", "Error al cargar vendedores");
      $("#vendedoresCount").text("Error");
    },
  });
}

function actualizarGraficoConOrdenamiento() {
  const tipoOrden = $('input[name="ordenVendedores"]:checked').val();
  const datosOrdenados = ordenarDatosVendedores(datosVendedores, tipoOrden);
  actualizarGraficoVendedores(datosOrdenados, tipoOrden);
}

function calcularScoreCombinado(datos) {
  if (!datos || datos.length === 0) return datos;

  // Extraer valores para cada métrica
  const ingresos = datos.map((item) => parseFloat(item.total_ventas) || 0);
  const ventas = datos.map((item) => parseInt(item.cantidad_ventas) || 0);
  const promedios = datos.map((item) => parseFloat(item.promedio_venta) || 0);

  // Función para calcular percentiles
  function calcularPercentil(valores, valor) {
    if (valores.length === 0) return 0;

    const valoresOrdenados = [...valores].sort((a, b) => a - b);
    const posicion = valoresOrdenados.indexOf(valor);
    return (posicion / (valores.length - 1)) * 100;
  }

  const datosConScore = datos.map((vendedor, index) => {
    const percentilIngresos = calcularPercentil(ingresos, ingresos[index]);
    const percentilVentas = calcularPercentil(ventas, ventas[index]);
    const percentilPromedio = calcularPercentil(promedios, promedios[index]);

    const scoreCombinado =
      percentilIngresos * 0.4 +
      percentilVentas * 0.35 +
      percentilPromedio * 0.25;

    return {
      ...vendedor,
      score_combinado: Math.round(scoreCombinado * 100) / 100,
      percentil_ingresos: Math.round(percentilIngresos),
      percentil_ventas: Math.round(percentilVentas),
      percentil_promedio: Math.round(percentilPromedio),
    };
  });

  return datosConScore;
}

function ordenarDatosVendedores(datos, criterio) {
  const datosClonados = [...datos];

  switch (criterio) {
    case "ingresos":
      return datosClonados.sort(
        (a, b) => parseFloat(b.total_ventas) - parseFloat(a.total_ventas)
      );

    case "ventas":
      return datosClonados.sort(
        (a, b) => parseInt(b.cantidad_ventas) - parseInt(a.cantidad_ventas)
      );

    case "promedio":
      return datosClonados.sort(
        (a, b) => parseFloat(b.promedio_venta) - parseFloat(a.promedio_venta)
      );

    case "combinado":
      // Calcular scores combinados y ordenar
      const datosConScore = calcularScoreCombinado(datosClonados);
      return datosConScore.sort(
        (a, b) => b.score_combinado - a.score_combinado
      );

    default:
      return datosClonados.sort(
        (a, b) => parseFloat(b.total_ventas) - parseFloat(a.total_ventas)
      );
  }
}

function adjustColorOpacity(color, opacity) {
  let r, g, b;

  if (color.startsWith("#")) {
    const hex = color.slice(1);
    r = parseInt(hex.substr(0, 2), 16);
    g = parseInt(hex.substr(2, 2), 16);
    b = parseInt(hex.substr(4, 2), 16);
  } else {
    // Valores por defecto para colores no reconocidos
    r = 102;
    g = 126;
    b = 234;
  }

  return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

function actualizarGraficoVendedores(datos, tipoOrden = "ingresos") {
  const ctx = document.getElementById("chartVendedores").getContext("2d");

  if (chartVendedores) {
    chartVendedores.destroy();
  }

  const labels = datos.map((item) => item.nombre_vendedor || "Sin asignar");

  // Determinar qué datos mostrar según el criterio de ordenamiento
  let datosGrafico, labelDataset, colorBase, tooltipCallback;

  switch (tipoOrden) {
    case "ventas":
      datosGrafico = datos.map((item) => parseInt(item.cantidad_ventas));
      labelDataset = "Cantidad de Ventas";
      colorBase = "#28a745";
      tooltipCallback = function (context) {
        const vendedor = datos[context.dataIndex];
        return [
          `🛒 Ventas: ${vendedor.cantidad_ventas}`,
          `💰 Total: ${formatearMoneda(vendedor.total_ventas)}`,
          `📊 Promedio: ${formatearMoneda(vendedor.promedio_venta)}`,
          `👥 Clientes: ${vendedor.clientes_atendidos || 0}`,
        ];
      };
      break;

    case "promedio":
      datosGrafico = datos.map((item) => parseFloat(item.promedio_venta));
      labelDataset = "Ticket Promedio (USD)";
      colorBase = "#ffc107";
      tooltipCallback = function (context) {
        const vendedor = datos[context.dataIndex];
        return [
          `📊 Promedio: ${formatearMoneda(vendedor.promedio_venta)}`,
          `💰 Total: ${formatearMoneda(vendedor.total_ventas)}`,
          `🛒 Ventas: ${vendedor.cantidad_ventas}`,
          `👥 Clientes: ${vendedor.clientes_atendidos || 0}`,
        ];
      };
      break;

    case "combinado":
      datosGrafico = datos.map((item) => parseFloat(item.score_combinado || 0));
      labelDataset = "Score Combinado";
      colorBase = "#e74c3c"; // Rojo para destacar
      tooltipCallback = function (context) {
        const vendedor = datos[context.dataIndex];
        return [
          `🏆 Score: ${vendedor.score_combinado}/100`,
          `💰 Total: ${formatearMoneda(vendedor.total_ventas)} (${
            vendedor.percentil_ingresos
          }%)`,
          `🛒 Ventas: ${vendedor.cantidad_ventas} (${vendedor.percentil_ventas}%)`,
          `📊 Promedio: ${formatearMoneda(vendedor.promedio_venta)} (${
            vendedor.percentil_promedio
          }%)`,
          `👥 Clientes: ${vendedor.clientes_atendidos || 0}`,
        ];
      };
      break;

    case "ingresos":
    default:
      datosGrafico = datos.map((item) => parseFloat(item.total_ventas));
      labelDataset = "Ingresos Totales (USD)";
      colorBase = "#667eea";
      tooltipCallback = function (context) {
        const vendedor = datos[context.dataIndex];
        return [
          `💰 Total: ${formatearMoneda(vendedor.total_ventas)}`,
          `🛒 Ventas: ${vendedor.cantidad_ventas}`,
          `📊 Promedio: ${formatearMoneda(vendedor.promedio_venta)}`,
          `👥 Clientes: ${vendedor.clientes_atendidos || 0}`,
        ];
      };
      break;
  }

  // Generar colores degradados
  const colores = datos.map((_, index) => {
    const intensity = 1 - index * 0.15;
    return adjustColorOpacity(colorBase, Math.max(intensity, 0.3));
  });

  chartVendedores = new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: labelDataset,
          data: datosGrafico,
          backgroundColor: colores,
          borderColor: colorBase,
          borderWidth: 2,
          borderRadius: 6,
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: "top",
          labels: {
            usePointStyle: true,
            padding: 15,
          },
        },
        tooltip: {
          backgroundColor: "rgba(0,0,0,0.8)",
          titleColor: "white",
          bodyColor: "white",
          borderColor: colorBase,
          borderWidth: 1,
          displayColors: false,
          callbacks: {
            title: function (context) {
              return datos[context[0].dataIndex].nombre_vendedor;
            },
            label: tooltipCallback,
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function (value) {
              if (tipoOrden === "ventas") {
                return value.toLocaleString("en-US");
              } else if (tipoOrden === "combinado") {
                return value.toFixed(1) + "/100";
              } else {
                return "$" + value.toLocaleString("en-US");
              }
            },
          },
          grid: {
            color: "rgba(0,0,0,0.1)",
          },
        },
        x: {
          ticks: {
            maxRotation: 45,
          },
          grid: {
            color: "rgba(0,0,0,0.1)",
          },
        },
      },
      animation: {
        duration: 800,
        easing: "easeInOutQuart",
      },
    },
  });

  // Mostrar información del score combinado en consola para debug
  if (tipoOrden === "combinado") {
    console.log(
      "🏆 Score Combinado - Top 3:",
      datos.slice(0, 3).map((v) => ({
        nombre: v.nombre_vendedor,
        score: v.score_combinado,
        ingresos: `$${parseFloat(v.total_ventas).toLocaleString()} (${
          v.percentil_ingresos
        }%)`,
        ventas: `${v.cantidad_ventas} (${v.percentil_ventas}%)`,
        promedio: `$${parseFloat(v.promedio_venta).toLocaleString()} (${
          v.percentil_promedio
        }%)`,
      }))
    );
  }
}
function mostrarGraficoVacio(canvasId, mensaje) {
  const ctx = document.getElementById(canvasId).getContext("2d");

  // Limpiar canvas
  ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);

  // Mostrar mensaje
  ctx.save();
  ctx.font = "18px Arial";
  ctx.fillStyle = "#6c757d";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText(mensaje, ctx.canvas.width / 2, ctx.canvas.height / 2);
  ctx.restore();
}

/**
 * ========================================
 * TABLA DE VENTAS DETALLADAS
 * ========================================
 */
function cargarTablaDetallada() {
  $("#loadingTabla").show();
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "ventas_detalladas",
    },
    dataType: "json",
    success: function (response) {
      $("#loadingTabla").hide();
      if (response.success) {
        if (response.datos && response.datos.length > 0) {
          actualizarTablaVentasDetalladas(response.datos);
          $("#totalRegistros").text(`${response.datos.length} registros`);
        } else {
          mostrarTablaVacia(
            "No se encontraron ventas para los filtros seleccionados"
          );
          $("#totalRegistros").text("0 registros");
        }
      } else {
        console.error("Error cargando ventas detalladas:", response.error);
        mostrarTablaVacia(
          "Error al cargar las ventas: " +
            (response.error || "Error desconocido")
        );
        $("#totalRegistros").text("Error");
      }
    },
    error: function (xhr, status, error) {
      $("#loadingTabla").hide();
      console.error("Error en petición ventas detalladas:", error);
      mostrarTablaVacia("Error de conexión al cargar las ventas");
      $("#totalRegistros").text("Error");
    },
  });
}

function actualizarTablaVentasDetalladas(ventas) {
  ventasData = ventas;
  paginaActual = 1;
  mostrarPagina();
  crearControlesPaginacion();
  $("#totalRegistros").text(`${ventas.length} registros`);
}

function mostrarPagina() {
  const inicio = (paginaActual - 1) * registrosPorPagina;
  const fin = inicio + registrosPorPagina;
  const ventasPagina = ventasData.slice(inicio, fin);

  let html = "";
  ventasPagina.forEach(function (venta, index) {
    const fecha = formatearFecha(venta.fecha_venta);
    const vendedor = venta.nombre_vendedor || "Sin asignar";
    const estado = venta.estado || "Sin estado";
    const total = formatearMoneda(venta.monto_total || 0);
    const tipoPago =
      (venta.cond_pago || "") +
      (venta.tipo_pago ? " - " + venta.tipo_pago : "");
    const productos = venta.cantidad_productos || 0;

    html += `<tr>
            <td><strong>#${venta.id}</strong></td>
            <td>${fecha}</td>
            <td>${venta.cliente || "Sin cliente"}</td>`;

    if (PUEDE_VER_TODOS) {
      html += `<td><i class="fas fa-user-tie me-1"></i>${vendedor}</td>`;
    }

    html += `
            <td><span class="badge bg-${obtenerColorEstado(
              estado
            )}">${estado}</span></td>
            <td class="text-end"><strong>${total}</strong></td>
            <td>${tipoPago}</td>
            <td class="text-center"><span class="badge bg-secondary">${productos}</span></td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary" 
                        onclick="verProductosVenta(${venta.id}, '${(
      venta.cliente || "Sin cliente"
    ).replace(/'/g, "\\'")}', '${total}')"
                        title="Ver productos de esta venta">
                    <i class="fas fa-eye me-1"></i>Ver
                </button>
            </td>
        </tr>`;
  });

  $("#tablaVentasDetalladas tbody").html(html);
}

function crearControlesPaginacion() {
  const totalPaginas = Math.ceil(ventasData.length / registrosPorPagina);

  if (totalPaginas <= 1) {
    $("#controlesPaginacion").html("");
    return;
  }

  let html = `
        <nav aria-label="Paginación de ventas">
            <ul class="pagination justify-content-center">
                <li class="page-item ${paginaActual === 1 ? "disabled" : ""}">
                    <button class="page-link" onclick="cambiarPagina(${
                      paginaActual - 1
                    })">Anterior</button>
                </li>`;

  // Mostrar páginas
  for (let i = 1; i <= totalPaginas; i++) {
    if (
      i === paginaActual ||
      i === 1 ||
      i === totalPaginas ||
      (i >= paginaActual - 1 && i <= paginaActual + 1)
    ) {
      html += `<li class="page-item ${i === paginaActual ? "active" : ""}">
                        <button class="page-link" onclick="cambiarPagina(${i})">${i}</button>
                     </li>`;
    } else if (i === paginaActual - 2 || i === paginaActual + 2) {
      html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
  }

  html += `
                <li class="page-item ${
                  paginaActual === totalPaginas ? "disabled" : ""
                }">
                    <button class="page-link" onclick="cambiarPagina(${
                      paginaActual + 1
                    })">Siguiente</button>
                </li>
            </ul>
        </nav>
        <div class="text-center text-muted">
            Mostrando ${
              (paginaActual - 1) * registrosPorPagina + 1
            } - ${Math.min(
    paginaActual * registrosPorPagina,
    ventasData.length
  )} de ${ventasData.length} registros
        </div>`;

  $("#controlesPaginacion").html(html);
}

function cambiarPagina(nuevaPagina) {
  const totalPaginas = Math.ceil(ventasData.length / registrosPorPagina);
  if (nuevaPagina >= 1 && nuevaPagina <= totalPaginas) {
    paginaActual = nuevaPagina;
    mostrarPagina();
    crearControlesPaginacion();
  }
}

function mostrarTablaVacia(mensaje) {
  const colspan = PUEDE_VER_TODOS ? "9" : "8";
  $("#tablaVentasDetalladas tbody").html(`
        <tr>
            <td colspan="${colspan}" class="text-center py-5">
                <div class="text-muted">
                    <i class="fas fa-info-circle me-2" style="font-size: 2rem;"></i>
                    <br><br>
                    <h5>${mensaje}</h5>
                    <p>Ajuste los filtros para obtener resultados</p>
                </div>
            </td>
        </tr>
    `);
}

/**
 * ========================================
 * MODAL DE PRODUCTOS DE VENTA
 * ========================================
 */
function verProductosVenta(ventaId, cliente, total) {
  // Actualizar información de la venta en el modal
  $("#ventaId").text("#" + ventaId);
  $("#ventaCliente").text(cliente);
  $("#totalVentaModal").text(total);

  // Mostrar loading y ocultar contenido
  $("#loadingProductosModal").show();
  $("#tablaProductosContainer").hide();
  $("#noProductosMessage").hide();

  // Abrir modal
  const modal = new bootstrap.Modal(
    document.getElementById("modalProductosVenta")
  );
  modal.show();

  // Hacer petición AJAX para obtener productos
  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      action: "productos_venta",
      venta_id: ventaId,
    },
    dataType: "json",
    success: function (response) {
      $("#loadingProductosModal").hide();

      if (response.success && response.datos && response.datos.length > 0) {
        mostrarProductosEnModal(response.datos);
        $("#tablaProductosContainer").show();
      } else {
        $("#noProductosMessage").show();
        console.log("No se encontraron productos para la venta:", ventaId);

        // Mostrar mensaje más descriptivo
        const mensajeError =
          response.error || "No hay productos registrados para esta venta";
        $("#noProductosMessage h5").text(mensajeError);
      }
    },
    error: function (xhr, status, error) {
      $("#loadingProductosModal").hide();
      $("#noProductosMessage").show();
      console.error("Error al cargar productos de la venta:", error);
      mostrarToast("Error al cargar los productos de la venta", "error");

      // Mostrar error específico
      $("#noProductosMessage h5").text("Error de conexión");
      $("#noProductosMessage p").text(
        "No se pudieron cargar los productos. Intente nuevamente."
      );
    },
  });
}

function mostrarProductosEnModal(productos) {
  let html = "";
  let totalCalculado = 0;
  let cantidadTotal = 0;

  productos.forEach(function (producto, index) {
    const cantidad = parseInt(producto.cantidad) || 0;
    const precioUnitario = parseFloat(producto.precio_unitario) || 0;
    const totalUsd = parseFloat(producto.total_usd) || 0;
    const subtotal = cantidad * precioUnitario;

    totalCalculado += totalUsd; // Usar el total convertido
    cantidadTotal += cantidad;

    // Alternar colores de fila
    const rowClass = index % 2 === 0 ? "" : "table-light";

    // Determinar si hay diferencia entre total guardado y calculado
    const diferencia = Math.abs(totalUsd - subtotal);
    const hayDiferencia = diferencia > 0.01;
    const claseDiferencia = hayDiferencia ? "text-warning" : "";

    html += `
        <tr class="${rowClass}">
            <td>
                <div>
                    <strong>${
                      producto.nombre_producto || "Producto sin nombre"
                    }</strong>
                    ${
                      producto.descripcion &&
                      producto.descripcion !== "Sin categoría"
                        ? `<br><small class="text-muted"><i class="fas fa-tag me-1"></i>${producto.descripcion}</small>`
                        : ""
                    }
                    ${
                      producto.codigo && producto.codigo !== "Sin código"
                        ? `<br><small class="text-info"><i class="fas fa-barcode me-1"></i>${producto.codigo}</small>`
                        : ""
                    }
                    ${
                      producto.unidad_medida &&
                      producto.unidad_medida !== "Unidad"
                        ? `<br><small class="text-secondary"><i class="fas fa-ruler me-1"></i>${producto.unidad_medida}</small>`
                        : ""
                    }
                </div>
            </td>
            <td class="text-center">
                <span class="badge bg-primary fs-6">${cantidad}</span>
                ${
                  producto.unidad_medida && producto.unidad_medida !== "Unidad"
                    ? `<br><small class="text-muted">${producto.unidad_medida}</small>`
                    : ""
                }
            </td>
            <td class="text-end">
                <strong>${formatearMoneda(precioUnitario)}</strong>
                ${
                  producto.precio_original_formateado &&
                  producto.moneda_original !== "USD"
                    ? `<br><small class="text-muted">Original: ${producto.precio_original_formateado}</small>`
                    : ""
                }
            </td>
            <td class="text-end">
                <strong class="text-success ${claseDiferencia}">${formatearMoneda(
      totalUsd
    )}</strong>
                ${
                  hayDiferencia
                    ? `<br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Dif: ${formatearMoneda(
                        diferencia
                      )}</small>`
                    : ""
                }
                ${
                  producto.total_original_formateado &&
                  producto.moneda_original !== "USD"
                    ? `<br><small class="text-muted">Original: ${producto.total_original_formateado}</small>`
                    : ""
                }
            </td>
        </tr>
    `;
  });

  $("#tablaProductosModal").html(html);
  $("#totalVentaModal").text(formatearMoneda(totalCalculado));

  // Mostrar información detallada
  const infoDetallada = `${productos.length} productos (${cantidadTotal} unidades)`;
  $("#cantidadProductosModal").text(infoDetallada);

  // Agregar información adicional si hay conversiones
  const hayConversiones = productos.some((p) => p.moneda_original !== "USD");
  if (hayConversiones) {
    const tasasUsadas = Object.keys(tasasCache)
      .filter((k) => k !== "USD")
      .map((k) => {
        const simbolos = { PYG: "₲", BRL: "R$" };
        return `${simbolos[k] || ""} ${tasasCache[k]}`;
      })
      .join(" | ");

    const notaConversion = `
            <small class="text-info d-block mt-1">
                <i class="fas fa-exchange-alt me-1"></i>Valores convertidos a USD
                <br>Tasas aplicadas: ${tasasUsadas}
            </small>
        `;
    $("#cantidadProductosModal").html(infoDetallada + notaConversion);
  }
}

/**
 * ========================================
 * UTILIDADES Y FUNCIONES AUXILIARES
 * ========================================
 */
function limpiarFiltros() {
  $("#fecha_inicio").val(CONFIG.anoActual); // Primer día del año
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

function obtenerColorEstado(estado) {
  switch (estado?.toLowerCase()) {
    case "completado":
    case "aprobado":
    case "finalizado":
    case "confirmado":
      return "success";
    case "pendiente":
    case "en revision":
    case "en proceso":
      return "warning";
    case "rechazado":
    case "cancelado":
      return "danger";
    case "pagado":
      return "primary";
    default:
      return "secondary";
  }
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
