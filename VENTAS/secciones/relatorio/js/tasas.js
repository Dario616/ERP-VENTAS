/**
 * tasas.js - Sistema de Tasas de Conversión Dinámicas
 * Relatorio de Ventas USD
 */

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
