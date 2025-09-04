/**
 * pdf.js - Sistema de Generación de PDF
 * Relatorio de Ventas USD - Configuración y generación de documentos PDF
 */

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
    .filter((k) => k !== "USD")
    .map((k) => {
      const simbolos = { PYG: "₲", BRL: "R$" };
      return `${simbolos[k] || ""} ${tasasCache[k]} = $1`;
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

  // Opciones de agrupación
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

  // Agregar opciones de agrupación
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
    // Nuevos parámetros de agrupación
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
 * VALIDACIONES ESPECÍFICAS PARA PDF
 * ========================================
 */
function validarParametrosPDF(parametros) {
  const errores = [];

  // Validar fechas obligatorias
  if (!parametros.fecha_inicio) {
    errores.push("La fecha de inicio es requerida");
  }

  if (!parametros.fecha_fin) {
    errores.push("La fecha de fin es requerida");
  }

  // Validar rango de fechas
  if (parametros.fecha_inicio && parametros.fecha_fin) {
    const fechaInicio = new Date(parametros.fecha_inicio);
    const fechaFin = new Date(parametros.fecha_fin);

    if (fechaInicio > fechaFin) {
      errores.push("La fecha de inicio no puede ser mayor que la fecha fin");
    }

    // Validar que no sea un rango muy amplio (más de 2 años)
    const diasDiferencia = Math.ceil(
      (fechaFin - fechaInicio) / (1000 * 60 * 60 * 24)
    );
    if (diasDiferencia > 730) {
      errores.push("El período no puede ser mayor a 2 años para generar PDF");
    }
  }

  // Validar formato de papel
  const formatosValidos = ["A4", "A4_horizontal", "Letter"];
  if (!formatosValidos.includes(parametros.formato_papel)) {
    errores.push("Formato de papel no válido");
  }

  return errores;
}

/**
 * ========================================
 * FUNCIONES AUXILIARES PARA PDF
 * ========================================
 */
function obtenerEstadisticasPDF() {
  // Obtener estadísticas actuales del dashboard para incluir en el PDF
  return {
    cantidadVentas: $("#cantidadVentas").text() || "0",
    ticketPromedio: $("#ticketPromedio").text() || "$ 0.00",
    clientesUnicos: $("#clientesUnicos").text() || "0",
    ventasCredito: $("#ventasCredito").text() || "0",
    porcentajeCredito: $("#porcentajeCredito").text() || "0",
    totalRegistros: $("#totalRegistros").text() || "0 registros",
  };
}

function generarConfiguracionPDF() {
  // Generar un objeto con toda la configuración actual para debugging
  return {
    filtros: obtenerParametrosFiltros(),
    tasas: { ...tasasCache },
    estadisticas: obtenerEstadisticasPDF(),
    permisos: {
      puede_ver_todos: PUEDE_VER_TODOS,
      es_admin: ES_ADMIN,
    },
    timestamp: new Date().toISOString(),
  };
}

/**
 * ========================================
 * FUNCIONES DE UTILIDAD PARA OPCIONES
 * ========================================
 */
function resetearOpcionesPDF() {
  // Resetear todas las opciones del modal de PDF a valores por defecto
  $("#incluir_graficos").prop("checked", false);
  $("#incluir_totales").prop("checked", true);
  $("#incluir_productos").prop("checked", false);
  $("#formato_papel").val("A4");
  $('input[name="tipo_agrupacion_ventas"][value=""]').prop("checked", true);
  $("#agrupar_productos").prop("checked", false);

  // Limpiar fechas
  $("#pdf_fecha_inicio").val("");
  $("#pdf_fecha_fin").val("");
  $("#pdf_cliente").val("");
  $("#pdf_estado").val("");

  if (PUEDE_VER_TODOS) {
    $("#pdf_vendedor").val("");
  }

  actualizarVistaPrevia();
}

function aplicarConfiguracionRapida(tipo) {
  // Configuraciones predefinidas para diferentes tipos de reportes
  resetearOpcionesPDF();

  switch (tipo) {
    case "resumen":
      $("#incluir_totales").prop("checked", true);
      $("#incluir_graficos").prop("checked", true);
      $("#formato_papel").val("A4");
      break;

    case "detallado":
      $("#incluir_totales").prop("checked", true);
      $("#incluir_productos").prop("checked", true);
      $("#incluir_graficos").prop("checked", true);
      $("#formato_papel").val("A4_horizontal");
      break;

    case "por_cliente":
      $("#incluir_totales").prop("checked", true);
      $('input[name="tipo_agrupacion_ventas"][value="cliente"]').prop(
        "checked",
        true
      );
      $("#formato_papel").val("A4");
      break;

    case "por_vendedor":
      $("#incluir_totales").prop("checked", true);
      $("#incluir_graficos").prop("checked", true);
      $('input[name="tipo_agrupacion_ventas"][value="vendedor"]').prop(
        "checked",
        true
      );
      $("#formato_papel").val("A4");
      break;

    default:
      // Configuración básica
      $("#incluir_totales").prop("checked", true);
      break;
  }

  actualizarVistaPrevia();
}

/**
 * ========================================
 * FUNCIONES DE DEBUGGING PARA PDF
 * ========================================
 */
function debugParametrosPDF() {
  // Función para debugging - mostrar en consola los parámetros que se enviarán
  const tipoAgrupacionVentas = $(
    'input[name="tipo_agrupacion_ventas"]:checked'
  ).val();

  const parametros = {
    fecha_inicio: $("#pdf_fecha_inicio").val(),
    fecha_fin: $("#pdf_fecha_fin").val(),
    cliente: $("#pdf_cliente").val() || "",
    estado: $("#pdf_estado").val() || "",
    incluir_graficos: $("#incluir_graficos").is(":checked"),
    incluir_totales: $("#incluir_totales").is(":checked"),
    incluir_productos: $("#incluir_productos").is(":checked"),
    formato_papel: $("#formato_papel").val(),
    agrupar_por_cliente: tipoAgrupacionVentas === "cliente",
    agrupar_por_vendedor: tipoAgrupacionVentas === "vendedor",
    agrupar_productos: $("#agrupar_productos").is(":checked"),
  };

  if (PUEDE_VER_TODOS) {
    parametros.vendedor = $("#pdf_vendedor").val() || "";
  }

  console.log("🔍 DEBUG PDF - Parámetros:", parametros);
  console.log(
    "🔍 DEBUG PDF - Configuración completa:",
    generarConfiguracionPDF()
  );

  return parametros;
}

function mostrarResumenPDF() {
  // Mostrar un resumen de lo que incluirá el PDF
  const params = debugParametrosPDF();
  const errores = validarParametrosPDF(params);

  if (errores.length > 0) {
    console.warn("⚠️ Errores en configuración PDF:", errores);
    return false;
  }

  const fechaInicio = new Date(params.fecha_inicio);
  const fechaFin = new Date(params.fecha_fin);
  const diasDiferencia = Math.ceil(
    (fechaFin - fechaInicio) / (1000 * 60 * 60 * 24)
  );

  const resumen = {
    periodo: `${diasDiferencia} días`,
    incluye: [],
    agrupaciones: [],
  };

  if (params.incluir_totales) resumen.incluye.push("Totales y estadísticas");
  if (params.incluir_graficos) resumen.incluye.push("Gráficos visuales");
  if (params.incluir_productos)
    resumen.incluye.push("Lista detallada de productos");

  if (params.agrupar_por_cliente)
    resumen.agrupaciones.push("Agrupado por cliente");
  if (params.agrupar_por_vendedor)
    resumen.agrupaciones.push("Agrupado por vendedor");
  if (params.agrupar_productos)
    resumen.agrupaciones.push("Productos agrupados por nombre");

  console.log("📋 Resumen del PDF a generar:", resumen);
  return resumen;
}

/**
 * ========================================
 * EVENTOS ADICIONALES PARA MEJORAR UX
 * ========================================
 */
function configurarEventosAvanzadosPDF() {
  // Evento para mostrar/ocultar opciones según selecciones
  $("#incluir_productos").change(function () {
    const incluir = $(this).is(":checked");
    if (incluir) {
      // Si incluye productos, sugerir formato horizontal
      if ($("#formato_papel").val() === "A4") {
        $("#formato_papel").val("A4_horizontal");
        mostrarToast(
          "Formato cambiado a horizontal para mejor visualización de productos",
          "info"
        );
      }
    }
  });

  // Evento para validar fechas en tiempo real
  $("#pdf_fecha_inicio, #pdf_fecha_fin").change(function () {
    validarFechasPDF();
  });

  // Evento para mostrar preview de estadísticas
  $("#incluir_totales").change(function () {
    if ($(this).is(":checked")) {
      const stats = obtenerEstadisticasPDF();
      console.log("📊 Estadísticas que se incluirán:", stats);
    }
  });
}

function validarFechasPDF() {
  const fechaInicio = $("#pdf_fecha_inicio").val();
  const fechaFin = $("#pdf_fecha_fin").val();

  if (fechaInicio && fechaFin) {
    const inicio = new Date(fechaInicio);
    const fin = new Date(fechaFin);

    if (inicio > fin) {
      $("#pdf_fecha_fin").addClass("is-invalid");
      return false;
    } else {
      $("#pdf_fecha_fin").removeClass("is-invalid");

      // Calcular días y mostrar información
      const dias = Math.ceil((fin - inicio) / (1000 * 60 * 60 * 24));

      if (dias > 365) {
        mostrarToast(
          `Período muy amplio: ${dias} días. El PDF puede tardar en generarse.`,
          "warning"
        );
      }

      actualizarVistaPrevia();
      return true;
    }
  }

  return true;
}

// Inicializar eventos avanzados cuando se carga el documento
$(document).ready(function () {
  if (typeof configurarEventosAvanzadosPDF === "function") {
    configurarEventosAvanzadosPDF();
  }
});
