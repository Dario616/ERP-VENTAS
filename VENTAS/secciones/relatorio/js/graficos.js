/**
 * graficos.js - Gráficos y Visualizaciones
 * Relatorio de Ventas USD - Sistema de gráficos con Chart.js
 */

/**
 * ========================================
 * FUNCIONES PRINCIPALES DE GRÁFICOS
 * ========================================
 */

// Agregar esta función a la función cargarGraficos() existente
function cargarGraficos() {
  cargarGraficoVentasPeriodo();
  cargarGraficoProductos();
  cargarGraficoDistribucionMonedas();
  cargarGraficoDistribucionSectores();
  if (PUEDE_VER_TODOS) {
    cargarGraficoVendedores();
    cargarGraficoTop5Vendedores(); // ✅ AGREGAR ESTA LÍNEA
  }
}
/**
 * ========================================
 * GRÁFICO DE DISTRIBUCIÓN POR MONEDAS
 * ========================================
 */
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
          data: porcentajes,
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
          position: "right",
          labels: {
            usePointStyle: true,
            padding: 5,
            font: { size: 15 },
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

            if (percentage >= 3) {
              const position = element.tooltipPosition();
              const moneda = datos[index].moneda_original || "USD";
              const simbolo = obtenerSimboloMoneda(moneda);

              const monedaText = `${simbolo} ${moneda}`;
              const percentageText = `${percentage}%`;

              const lineHeight = 14;
              const topY = position.y - lineHeight / 2;
              const bottomY = position.y + lineHeight / 2;

              ctx.font = "bold 8px Arial";
              ctx.strokeText(percentageText, position.x, bottomY);
              ctx.fillText(percentageText, position.x, bottomY);

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
 * ========================================
 * GRÁFICO DE DISTRIBUCIÓN POR SECTORES
 * ========================================
 */
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
        datosSectores = response.datos;
        actualizarGraficoSectoresConOrdenamiento();
        $("#sectoresCount").text(`${response.datos.length} sectores`);
      } else {
        mostrarGraficoVacio(
          "chartDistribucionSectores",
          "No hay datos de sectores"
        );
        $("#sectoresCount").text("0 sectores");
        datosSectores = [];
      }
    },
    error: function () {
      $("#loadingSectores").hide();
      mostrarGraficoVacio(
        "chartDistribucionSectores",
        "Error al cargar sectores"
      );
      $("#sectoresCount").text("Error");
      datosSectores = [];
    },
  });
}

function actualizarGraficoSectoresConOrdenamiento() {
  const tipoOrden = $('input[name="ordenSectores"]:checked').val();

  if (!datosSectores || datosSectores.length === 0) {
    console.warn("⚠️ No hay datos de sectores para reordenar");
    return;
  }

  const datosOrdenados = ordenarDatosSectores(datosSectores, tipoOrden);
  const top8 = datosOrdenados.slice(0, 8);

  console.log("🔄 Ordenamiento sectores:", {
    criterio: tipoOrden,
    total_disponibles: datosSectores.length,
    despues_ordenar: datosOrdenados.length,
    mostrando_top: top8.length,
    primer_sector: top8[0]?.tipo?.substring(0, 30) + "...",
  });

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
    case "productos":
      return datosClonados.sort(
        (a, b) =>
          parseInt(b.productos_diferentes) - parseInt(a.productos_diferentes)
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

function actualizarGraficoDistribucionSectores(datos, tipoOrden = "ingresos") {
  const ctx = document
    .getElementById("chartDistribucionSectores")
    .getContext("2d");

  if (chartDistribucionSectores) {
    chartDistribucionSectores.destroy();
  }

  const labels = datos.map((item) => {
    const tipo = item.tipo || "Sin categoría";
    return tipo.length > 15 ? tipo.substring(0, 15) + "..." : tipo;
  });

  let datosGrafico, tooltipTitle;

  switch (tipoOrden) {
    case "ventas":
      datosGrafico = datos.map((item) =>
        parseFloat(item.ventas_asociadas || 0)
      );
      tooltipTitle = "🛒 Por Ventas";
      break;
    case "productos":
      datosGrafico = datos.map((item) =>
        parseFloat(item.productos_diferentes || 0)
      );
      tooltipTitle = "📦 Por Productos";
      break;
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

  const totalMostrado = datosGrafico.reduce((sum, val) => sum + val, 0);
  const porcentajes = datosGrafico.map((val) =>
    totalMostrado > 0 ? (val / totalMostrado) * 100 : 0
  );

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
          bottom: 100,
          left: 10,
          right: 10,
        },
      },
      plugins: {
        legend: {
          display: true,
          position: "right",
          labels: {
            usePointStyle: true,
            padding: 5,
            font: { size: 15 },
            boxHeight: 8,
            boxWidth: 8,
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
                  baseInfo[3] = `📊 Cantidad: ${parseFloat(
                    item.cantidad_vendida
                  ).toLocaleString()} ⭐`;
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

  console.log("📈 Gráfico sectores actualizado:", {
    criterio: tipoOrden,
    sectores_mostrados: datos.length,
    total_valor: totalMostrado.toLocaleString(),
    primer_sector: datos[0]?.tipo,
  });
}

/**
 * ========================================
 * GRÁFICO DE VENTAS POR PERÍODO
 * ========================================
 */
function cargarGraficoVentasPeriodo() {
  const params = obtenerParametrosFiltros();
  const fechaInicio = new Date(params.fecha_inicio);
  const fechaFin = new Date(params.fecha_fin);
  const diasDiferencia = Math.ceil(
    (fechaFin - fechaInicio) / (1000 * 60 * 60 * 24)
  );
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
        const cantidad = response.datos.length;
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

function actualizarGraficoPeriodoConOrdenamiento() {
  const tipoOrden = $('input[name="ordenPeriodo"]:checked').val();
  actualizarGraficoVentasPeriodo(datosPeriodo, tipoOrden);
}

function actualizarGraficoVentasPeriodo(datos, tipoMostrar = "ingresos") {
  const ctx = document.getElementById("chartVentasPeriodo").getContext("2d");

  if (chartVentasPeriodo) {
    chartVentasPeriodo.destroy();
  }

  const fechaInicio = new Date($("#fecha_inicio").val());
  const fechaFin = new Date($("#fecha_fin").val());
  const diasDiferencia = Math.ceil(
    (fechaFin - fechaInicio) / (1000 * 60 * 60 * 24)
  );
  const esPorDias = diasDiferencia <= 30;

  const labels = datos.map((item) => {
    if (esPorDias) {
      return formatearFechaCorta(item.fecha_venta);
    } else {
      return formatearMes(item.fecha_venta);
    }
  });

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

  const campo = tipoMostrar === "ventas" ? "cantidad_ventas" : "total_ventas";
  const picoMaximo = encontrarMaximo(datos, campo);
  const picoMinimo = encontrarMinimo(datos, campo);

  const coloresPuntos = datosGrafico.map((_, index) => {
    if (index === picoMaximo.indice) return "#007527ff";
    if (index === picoMinimo.indice) return "#a81100ff";
    return colorPrincipal;
  });

  const tamañosPuntos = datosGrafico.map((_, index) => {
    if (index === picoMaximo.indice || index === picoMinimo.indice) return 8;
    return 5;
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
              const fecha = formatearFecha(item.fecha_venta);

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

/**
 * ========================================
 * GRÁFICO DE PRODUCTOS
 * ========================================
 */
function cargarGraficoProductos() {
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "productos_mas_vendidos",
      limite: 100,
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

  if (!datosProductos || datosProductos.length === 0) {
    console.warn("⚠️ No hay datos de productos para reordenar");
    return;
  }

  const datosOrdenados = ordenarDatosProductos(datosProductos, tipoOrden);
  const top5 = datosOrdenados.slice(0, 5);

  console.log("🔄 Ordenamiento productos:", {
    criterio: tipoOrden,
    total_disponibles: datosProductos.length,
    despues_ordenar: datosOrdenados.length,
    mostrando_top: top5.length,
    primer_producto: top5[0]?.descripcion?.substring(0, 30) + "...",
  });

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
    return descripcion.length > 20
      ? descripcion.substring(0, 20) + "..."
      : descripcion;
  });

  let datosGrafico, labelDataset, colorBase, colorBorde;

  switch (tipoOrden) {
    case "ventas":
      datosGrafico = datos.map((item) => parseInt(item.ventas_asociadas || 0));
      labelDataset = "Número de Ventas";
      colorBase = "#fd7e14";
      colorBorde = "#e8590c";
      break;
    case "cantidad":
      datosGrafico = datos.map((item) => parseInt(item.cantidad_vendida || 0));
      labelDataset = "Cantidad Vendida";
      colorBase = "#20c997";
      colorBorde = "#1aa085";
      break;
    case "ingresos":
    default:
      datosGrafico = datos.map((item) => parseFloat(item.total_ingresos));
      labelDataset = "Ingresos (USD)";
      colorBase = "#28a745";
      colorBorde = "#1e7e34";
      break;
  }

  const colores = datos.map((_, index) => {
    const intensity = 1 - index * 0.15;
    return adjustColorOpacity(colorBase, Math.max(intensity, 0.4));
  });

  const coloresBorde = datos.map(() => colorBorde);

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
          barThickness: 25,
          maxBarThickness: 30,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: "y",
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
            font: { size: 11 },
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
          grid: { display: false },
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

/**
 * ========================================
 * GRÁFICO DE VENDEDORES
 * ========================================
 */
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

  const ingresos = datos.map((item) => parseFloat(item.total_ventas) || 0);
  const ventas = datos.map((item) => parseInt(item.cantidad_ventas) || 0);
  const promedios = datos.map((item) => parseFloat(item.promedio_venta) || 0);

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

function actualizarGraficoVendedores(datos, tipoOrden = "ingresos") {
  const ctx = document.getElementById("chartVendedores").getContext("2d");

  if (chartVendedores) {
    chartVendedores.destroy();
  }

  const labels = datos.map((item) => item.nombre_vendedor || "Sin asignar");

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
      colorBase = "#e74c3c";
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
          grid: { color: "rgba(0,0,0,0.1)" },
        },
        x: {
          ticks: { maxRotation: 45 },
          grid: { color: "rgba(0,0,0,0.1)" },
        },
      },
      animation: {
        duration: 800,
        easing: "easeInOutQuart",
      },
    },
  });

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
function cargarGraficoTop5Vendedores() {
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
      $("#loadingTop5Vendedores").hide();
      if (response.success && response.datos.length > 0) {
        // Reutilizar los datos pero limitarlos a top 5
        const top5Datos = response.datos.slice(0, 5);
        actualizarGraficoTop5VendedoresConOrdenamiento(top5Datos);
        $("#top5VendedoresCount").text(
          `Top 5 de ${response.datos.length} vendedores`
        );
      } else {
        mostrarGraficoVacio(
          "chartTop5Vendedores",
          "No hay datos de vendedores"
        );
        $("#top5VendedoresCount").text("0 vendedores");
      }
    },
    error: function () {
      $("#loadingTop5Vendedores").hide();
      mostrarGraficoVacio("chartTop5Vendedores", "Error al cargar vendedores");
      $("#top5VendedoresCount").text("Error");
    },
  });
}

function actualizarGraficoTop5VendedoresConOrdenamiento(datos) {
  const tipoOrden = $('input[name="ordenTop5Vendedores"]:checked').val();

  if (!datos || datos.length === 0) {
    console.warn("⚠️ No hay datos de vendedores para reordenar");
    return;
  }

  const datosOrdenados = ordenarDatosTop5Vendedores(datos, tipoOrden);
  const top5 = datosOrdenados.slice(0, 5);

  console.log("🔄 Ordenamiento Top 5 vendedores:", {
    criterio: tipoOrden,
    total_disponibles: datos.length,
    mostrando_top: top5.length,
    primer_vendedor: top5[0]?.nombre_vendedor,
  });

  actualizarGraficoTop5Vendedores(top5, tipoOrden);
}

function ordenarDatosTop5Vendedores(datos, criterio) {
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
    default:
      return datosClonados.sort(
        (a, b) => parseFloat(b.total_ventas) - parseFloat(a.total_ventas)
      );
  }
}

function actualizarGraficoTop5Vendedores(datos, tipoOrden = "ingresos") {
  const ctx = document.getElementById("chartTop5Vendedores").getContext("2d");

  if (chartTop5Vendedores) {
    chartTop5Vendedores.destroy();
  }

  const labels = datos.map((item) => {
    const nombre = item.nombre_vendedor || "Sin asignar";
    return nombre.length > 15 ? nombre.substring(0, 15) + "..." : nombre;
  });

  let datosGrafico, labelDataset, colorBase, colorBorde;

  switch (tipoOrden) {
    case "ventas":
      datosGrafico = datos.map((item) => parseInt(item.cantidad_ventas || 0));
      labelDataset = "Número de Ventas";
      colorBase = "#28a745";
      colorBorde = "#1e7e34";
      break;
    case "ingresos":
    default:
      datosGrafico = datos.map((item) => parseFloat(item.total_ventas || 0));
      labelDataset = "Ingresos (USD)";
      colorBase = "#667eea";
      colorBorde = "#5a67d8";
      break;
  }

  // Crear gradientes de colores para el top 5
  const colores = datos.map((_, index) => {
    const intensity = 1 - index * 0.15;
    return adjustColorOpacity(colorBase, Math.max(intensity, 0.4));
  });

  const coloresBorde = datos.map(() => colorBorde);

  chartTop5Vendedores = new Chart(ctx, {
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
          borderRadius: 6,
          borderSkipped: false,
          barThickness: 35,
          maxBarThickness: 40,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: "y", // Barras horizontales
      datasets: {
        bar: {
          categoryPercentage: 0.95,
          barPercentage: 0.85,
        },
      },
      layout: {
        padding: { top: 10, right: 15, bottom: 35, left: 10 },
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
            font: { size: 11 },
          },
          grid: {
            color: "rgba(0,0,0,0.1)",
            drawBorder: false,
          },
        },
        y: {
          ticks: {
            font: {
              size: 12,
              weight: 600,
            },
            color: "#495057",
          },
          grid: { display: false },
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
              const vendedor = datos[context[0].dataIndex];
              return `🏆 ${vendedor.nombre_vendedor || "Sin asignar"}`;
            },
            label: function (context) {
              const vendedor = datos[context.dataIndex];
              const ranking = context.dataIndex + 1;

              return [
                `🥇 Posición: #${ranking}`,
                `💰 Ingresos: $${parseFloat(
                  vendedor.total_ventas
                ).toLocaleString("en-US")}`,
                `🛒 Ventas: ${vendedor.cantidad_ventas}`,
                `📊 Promedio: $${parseFloat(
                  vendedor.promedio_venta
                ).toLocaleString("en-US")}`,
                `👥 Clientes: ${vendedor.clientes_atendidos || 0}`,
              ];
            },
          },
        },
      },
      animation: {
        duration: 1000,
        easing: "easeInOutQuart",
      },
    },
    plugins: [
      {
        id: "rankingLabels",
        afterDatasetsDraw: function (chart, args, options) {
          const ctx = chart.ctx;
          const dataset = chart.data.datasets[0];
          const meta = chart.getDatasetMeta(0);

          ctx.save();
          ctx.textAlign = "left";
          ctx.textBaseline = "middle";
          ctx.fillStyle = "#fff";
          ctx.strokeStyle = "#000";
          ctx.lineWidth = 2;
          ctx.font = "bold 11px Arial";

          meta.data.forEach((element, index) => {
            const ranking = index + 1;
            const rankingText = `#${ranking}`;

            const { x, y } = element;

            // Posicionar el número dentro de la barra
            const textX = x - element.width * 0.85;

            // Solo mostrar si hay espacio suficiente
            if (element.width > 30) {
              ctx.strokeText(rankingText, textX, y);
              ctx.fillText(rankingText, textX, y);
            }
          });

          ctx.restore();
        },
      },
    ],
  });

  console.log("📊 Top 5 Vendedores actualizado:", {
    criterio: tipoOrden,
    vendedores_mostrados: datos.length,
    primer_vendedor: datos[0]?.nombre_vendedor,
    valor_lider:
      tipoOrden === "ingresos"
        ? `$${parseFloat(datos[0]?.total_ventas || 0).toLocaleString()}`
        : `${datos[0]?.cantidad_ventas || 0} ventas`,
  });
}
/**
 * ========================================
 * FUNCIONES AUXILIARES
 * ========================================
 */
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

function adjustColorOpacity(color, opacity) {
  let r, g, b;

  if (color.startsWith("#")) {
    const hex = color.slice(1);
    r = parseInt(hex.substr(0, 2), 16);
    g = parseInt(hex.substr(2, 2), 16);
    b = parseInt(hex.substr(4, 2), 16);
  } else {
    const colorMap = {
      "#fd7e14": [253, 126, 20],
      "#20c997": [32, 201, 151],
      "#28a745": [40, 167, 69],
    };

    if (colorMap[color]) {
      [r, g, b] = colorMap[color];
    } else {
      r = 102;
      g = 126;
      b = 234;
    }
  }

  return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

function validarDatosProductos(datos) {
  if (!Array.isArray(datos) || datos.length === 0) {
    console.warn("No hay datos de productos para mostrar");
    return false;
  }

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

function mostrarGraficoVacio(canvasId, mensaje) {
  const ctx = document.getElementById(canvasId).getContext("2d");

  ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);

  ctx.save();
  ctx.font = "18px Arial";
  ctx.fillStyle = "#6c757d";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText(mensaje, ctx.canvas.width / 2, ctx.canvas.height / 2);
  ctx.restore();
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
