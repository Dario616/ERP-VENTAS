/**
 * JavaScript para Reportes de Producción - America TNT
 * Sistema de análisis y visualización de datos de producción con filtros de horario
 * Versión actualizada con soporte para filtros por hora
 */

// Variables globales (declaración única)
let chartEvolucion = null;
let datosActuales = null;
let chartSector = null;
let chartClasificacion = null;
let chartDispersion = null;
let paginaActualProductos = 1;
let datosProductos = null;

// Configuración del gráfico
let configChart = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "top",
      labels: {
        usePointStyle: true,
        padding: 15,
        font: {
          family: "Inter",
          size: 12,
          weight: 500,
        },
      },
    },
    tooltip: {
      backgroundColor: "rgba(30, 58, 95, 0.9)",
      titleColor: "#ffffff",
      bodyColor: "#ffffff",
      borderColor: "#dc2626",
      borderWidth: 1,
      cornerRadius: 8,
      titleFont: {
        family: "Inter",
        size: 13,
        weight: 600,
      },
      bodyFont: {
        family: "Inter",
        size: 12,
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        color: "rgba(0, 0, 0, 0.1)",
      },
      ticks: {
        font: {
          family: "Inter",
          size: 11,
        },
        color: "#64748b",
      },
    },
    x: {
      grid: {
        color: "rgba(0, 0, 0, 0.1)",
      },
      ticks: {
        font: {
          family: "Inter",
          size: 11,
        },
        color: "#64748b",
      },
    },
  },
};

/**
 * ✅ NUEVA FUNCIÓN: Detectar y manejar filtros de horario
 */
function manejarFiltrosHorario() {
  const fechaInicio = document.getElementById("fechaInicio");
  const fechaFin = document.getElementById("fechaFin");
  const horaContainer = document.getElementById("horaContainer");

  if (!fechaInicio || !fechaFin || !horaContainer) return;

  const esMismoDia =
    fechaInicio.value === fechaFin.value && fechaInicio.value !== "";

  if (esMismoDia) {
    // Mostrar campos de hora con animación
    horaContainer.style.display = "block";
    horaContainer.classList.add("visible", "fade-in");

    // Actualizar el badge del período
    const badge = document.getElementById("periodoMostrar");
    if (badge) {
      badge.textContent = "1 día (por horas)";
      badge.className = "badge bg-info ms-2"; // Cambiar color para indicar filtro por hora
    }
  } else {
    // Ocultar campos de hora
    horaContainer.style.display = "none";
    horaContainer.classList.remove("visible", "fade-in");

    // Restaurar valores por defecto
    document.getElementById("horaInicio").value = "00:00";
    document.getElementById("horaFin").value = "23:59";

    // Restaurar badge del período
    const badge = document.getElementById("periodoMostrar");
    if (badge) {
      badge.className = "badge bg-primary ms-2";
    }
  }

  console.log(
    `⏰ Filtros de horario ${esMismoDia ? "habilitados" : "deshabilitados"}`
  );
}

/**
 * ✅ NUEVA FUNCIÓN: Validar horarios
 */
function validarHorarios() {
  const horaInicio = document.getElementById("horaInicio").value;
  const horaFin = document.getElementById("horaFin").value;

  if (!horaInicio || !horaFin) return true; // No validar si están vacíos

  const [horaInicioHH, horaInicioMM] = horaInicio.split(":").map(Number);
  const [horaFinHH, horaFinMM] = horaFin.split(":").map(Number);

  const minutosInicio = horaInicioHH * 60 + horaInicioMM;
  const minutosFin = horaFinHH * 60 + horaFinMM;

  if (minutosInicio >= minutosFin) {
    mostrarError("La hora de inicio debe ser anterior a la hora de fin");
    document.getElementById("horaFin").focus();
    return false;
  }

  return true;
}

/**
 * ✅ ACTUALIZADA: Configurar eventos iniciales - Con soporte para horarios
 */
function configurarEventos() {
  // Formulario de filtros
  const form = document.getElementById("filtrosForm");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      // Validar horarios antes de proceder
      if (!validarHorarios()) {
        return;
      }

      cargarDatosDashboard();
    });
  }

  // Plugin para mostrar valores siempre activos
  const pluginValoresSiempre = {
    id: "valoresSiempre",
    afterDatasetsDraw(chart) {
      const ctx = chart.ctx;
      ctx.save();
      ctx.font = "bold 11px Inter";
      ctx.textAlign = "center";

      chart.data.datasets[0].data.forEach((value, index) => {
        const meta = chart.getDatasetMeta(0);
        const point = meta.data[index];

        const chartArea = chart.chartArea;
        const margenSuperior = 0;

        let textY = point.y - 15;
        let colorTexto = "#000000ff";

        if (textY < chartArea.top + margenSuperior) {
          textY = point.y + 20;
          colorTexto = "#ffffffff";

          const alturaDisponible = point.y - chartArea.top;
          if (alturaDisponible < 35) {
            textY = chartArea.top + margenSuperior;
            colorTexto = "#000000ff";
          }
        }

        ctx.fillStyle = colorTexto;

        const valorFormateado = value.toLocaleString("es-PY");
        ctx.fillText(valorFormateado, point.x, textY);

        if (colorTexto === "#ffffff") {
          const medidas = ctx.measureText(valorFormateado);
          const padding = 4;

          ctx.save();
          ctx.fillStyle = "rgba(0, 0, 0, 0.7)";
          ctx.fillRect(
            point.x - medidas.width / 2 - padding,
            textY - 8,
            medidas.width + padding * 2,
            16
          );
          ctx.restore();

          ctx.fillStyle = colorTexto;
          ctx.fillText(valorFormateado, point.x, textY);
        }
      });

      ctx.restore();
    },
  };

  Chart.register(pluginValoresSiempre);

  // Radio buttons para métricas del gráfico
  const radiosBobinas = document.querySelectorAll(
    'input[name="mostrarMetrica"]'
  );
  radiosBobinas.forEach((radio) => {
    radio.addEventListener("change", function () {
      if (datosActuales && datosActuales.evolucion_produccion) {
        actualizarGraficoEvolucion(datosActuales.evolucion_produccion);
      }
    });
  });

  // Radio buttons para ordenar productos
  const radiosOrden = document.querySelectorAll('input[name="ordenarPor"]');
  radiosOrden.forEach((radio) => {
    radio.addEventListener("change", function () {
      if (datosActuales && datosActuales.top_productos) {
        mostrarTopProductos(datosActuales.top_productos);
      }
    });
  });

  const radiosSector = document.querySelectorAll('input[name="metricaSector"]');
  radiosSector.forEach((radio) => {
    radio.addEventListener("change", function () {
      if (datosActuales && datosActuales.performance_sectores) {
        actualizarGraficoSector(datosActuales.performance_sectores);
      }
    });
  });

  // ✅ NUEVO: Eventos para campos de fecha - detectar cambios para horarios
  const fechaInicio = document.getElementById("fechaInicio");
  const fechaFin = document.getElementById("fechaFin");

  if (fechaInicio && fechaFin) {
    fechaInicio.addEventListener("change", function () {
      validarRangoFechas();
      manejarFiltrosHorario();
    });

    fechaFin.addEventListener("change", function () {
      validarRangoFechas();
      manejarFiltrosHorario();
    });
  }

  // ✅ NUEVO: Eventos para campos de hora
  const horaInicio = document.getElementById("horaInicio");
  const horaFin = document.getElementById("horaFin");

  if (horaInicio && horaFin) {
    horaInicio.addEventListener("change", validarHorarios);
    horaFin.addEventListener("change", validarHorarios);
  }

  // Ejecutar verificación inicial de horarios
  manejarFiltrosHorario();

  console.log(
    "✅ Eventos configurados correctamente (con soporte para horarios)"
  );
}


/**
 * ✅ ACTUALIZADA: Cargar todos los datos del dashboard - Con filtros de horario
 */
async function cargarDatosDashboard() {
  mostrarLoading(true);

  try {
    const filtros = obtenerFiltrosFormulario(); // Ya incluye los filtros de hora

    if (typeof PRODUCCION_CONFIG === "undefined") {
      throw new Error("Configuración no disponible");
    }

    const url = `?action=obtener_datos_dashboard&${new URLSearchParams(
      filtros
    ).toString()}`;

    console.log("🔄 Cargando datos desde:", url);
    console.log("📊 Filtros aplicados:", filtros);

    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const resultado = await response.json();

    if (resultado.success) {
      datosActuales = resultado.datos;

      // Actualizar todas las secciones
      actualizarEstadisticas(datosActuales.estadisticas_generales);
      actualizarGraficoEvolucion(datosActuales.evolucion_produccion);
      mostrarTopProductos(datosActuales.top_productos);
      mostrarInformacionAdicional(datosActuales);
      actualizarGraficoSector(datosActuales.performance_sectores);
      cargarProductosPaginados(1);

      // Cargar gráficos de clasificación CON filtros
      await cargarGraficosConFiltros(filtros);

      console.log("✅ Datos cargados exitosamente:", datosActuales);
    } else {
      mostrarError(
        "Error al cargar los datos: " + (resultado.error || "Error desconocido")
      );
    }
  } catch (error) {
    console.error("❌ Error cargando datos:", error);
    mostrarError("Error de conexión. Verifique su conexión a internet.");
  } finally {
    mostrarLoading(false);
  }
}

/**
 * ✅ ACTUALIZADA: Obtener filtros del formulario - Con horarios incluidos
 */
function obtenerFiltrosFormulario() {
  const filtros = {
    fecha_inicio: document.getElementById("fechaInicio")?.value || "",
    fecha_fin: document.getElementById("fechaFin")?.value || "",
    operador: document.getElementById("operador")?.value || "",
    tipo_producto: document.getElementById("tipoProducto")?.value || "",
    estado: document.getElementById("estado")?.value || "",
  };

  // ✅ NUEVO: Agregar filtros de hora solo si están visibles y son del mismo día
  const horaContainer = document.getElementById("horaContainer");
  if (horaContainer && horaContainer.style.display !== "none") {
    const horaInicio = document.getElementById("horaInicio")?.value;
    const horaFin = document.getElementById("horaFin")?.value;

    if (horaInicio && horaFin) {
      filtros.hora_inicio = horaInicio;
      filtros.hora_fin = horaFin;
    }
  }

  return filtros;
}

/**
 * ✅ NUEVA FUNCIÓN: Cargar gráficos con filtros aplicados
 */
async function cargarGraficosConFiltros(filtros) {
  try {
    const url = `?action=obtener_datos_graficos&${new URLSearchParams(
      filtros
    ).toString()}`;

    console.log("🔄 Cargando gráficos filtrados desde:", url);

    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const resultado = await response.json();

    if (resultado.success) {
      crearGraficoClasificacion(resultado.datos.clasificaciones);
      crearGraficoDispersion(resultado.datos.dispersion);
      console.log("📊 Gráficos de clasificación actualizados con filtros");
    } else {
      console.error("Error del servidor:", resultado.error);
      mostrarError("Error al cargar gráficos de clasificación");
    }
  } catch (error) {
    console.error("Error cargando gráficos filtrados:", error);
    mostrarError("Error al cargar gráficos de clasificación");
  }
}

/**
 * ✅ ACTUALIZADA: Cargar productos paginados - Con horarios en la tabla
 */
async function cargarProductosPaginados(pagina = 1) {
  try {
    const filtros = obtenerFiltrosFormulario();
    const url = `?action=obtener_productos_paginados&pagina=${pagina}&${new URLSearchParams(
      filtros
    ).toString()}`;

    const response = await fetch(url);
    const resultado = await response.json();

    if (resultado.success) {
      datosProductos = resultado;
      paginaActualProductos = pagina;
      mostrarTablaProductos(
        resultado.productos,
        filtros.hora_inicio || filtros.hora_fin
      );
      mostrarPaginacion(resultado.paginacion);
      actualizarContadorProductos(resultado.paginacion.total);
    }
  } catch (error) {
    console.error("Error cargando productos:", error);
    mostrarErrorTabla();
  }
}

/**
 * ✅ ACTUALIZADA: Mostrar tabla de productos - Con fecha/hora completa cuando se usan filtros de hora
 */
function mostrarTablaProductos(productos, usandoFiltroHorario = false) {
  const tbody = document.getElementById("tbodyProductos");

  if (!productos || productos.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" class="text-center py-4">
          <i class="fas fa-box-open text-muted"></i>
          <p class="mb-0 mt-2 text-muted">No se encontraron productos</p>
        </td>
      </tr>
    `;
    return;
  }

  let html = "";
  productos.forEach((producto) => {
    const estadoClass = producto.estado === "ACTIVO" ? "success" : "secondary";
    const eficienciaClass =
      producto.eficiencia >= 85
        ? "success"
        : producto.eficiencia >= 75
        ? "warning"
        : "danger";

    // ✅ NUEVO: Mostrar fecha/hora completa si se usan filtros de horario
    let fechaMostrar;
    if (usandoFiltroHorario && producto.fecha_hora_producida) {
      // Formatear fecha y hora completa
      const fecha = new Date(producto.fecha_hora_producida);
      fechaMostrar = `
        <small><strong>${producto.fecha_formateada}</strong></small><br>
        <small class="text-muted">${fecha.toLocaleTimeString("es-PY", {
          hour: "2-digit",
          minute: "2-digit",
        })}</small>
      `;
    } else {
      fechaMostrar = `<small>${producto.fecha_formateada}</small>`;
    }

    html += `
      <tr>
        <td>${fechaMostrar}</td>
        <td><strong>${producto.nombre_producto}</strong></td>
        <td><span class="badge bg-info">${producto.tipo_producto}</span></td>
        <td class="text-center">${producto.bobinas_pacote}</td>
        <td class="text-center">${producto.metragem}</td>
        <td>
          <small>B: ${producto.peso_bruto} kg</small><br>
          <small>L: ${producto.peso_liquido} kg</small>
        </td>
        <td>
          <span class="badge bg-${eficienciaClass}">${producto.eficiencia}%</span>
        </td>
        <td><span class="badge bg-${estadoClass}">${producto.estado}</span></td>
        <td><small>${producto.usuario}</small></td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

/**
 * ✅ ACTUALIZADA: Actualizar gráfico de evolución - Con soporte para horas
 */
function actualizarGraficoEvolucion(datosEvolucion) {
  if (!datosEvolucion || datosEvolucion.length === 0) {
    mostrarSinDatos(
      "chartEvolucion",
      "No hay datos de producción para el período seleccionado"
    );
    return;
  }

  const ctx = document.getElementById("chartEvolucion");
  if (!ctx) return;

  // Determinar qué métrica mostrar
  const metricaSeleccionada =
    document.querySelector('input[name="mostrarMetrica"]:checked')?.value ||
    "bobinas";

  // ✅ NUEVO: Detectar si estamos en modo horario para cambiar las etiquetas
  const filtros = obtenerFiltrosFormulario();
  const modoHorario = filtros.hora_inicio || filtros.hora_fin;

  let labels;
  if (modoHorario && datosEvolucion.length <= 24) {
    // Modo horario: mostrar horas
    labels = datosEvolucion.map((item) => {
      if (item.hora !== undefined) {
        return `${item.hora}:00`;
      }
      // Fallback: extraer hora de la fecha
      const fecha = new Date(item.fecha + " 00:00:00");
      return fecha.toLocaleTimeString("es-PY", {
        hour: "2-digit",
        minute: "2-digit",
      });
    });
  } else {
    // Modo normal: mostrar fechas
    labels = datosEvolucion.map((item) => item.fecha_formateada);
  }

  let datos, label, color;

  if (metricaSeleccionada === "bobinas") {
    datos = datosEvolucion.map((item) => item.cantidad_producida);
    label = "Bobinas Producidas";
    color = {
      border: "#325b91",
      background: "rgba(50, 91, 145, 0.1)",
      point: "#325b91",
    };
  } else {
    datos = datosEvolucion.map((item) => item.items_producidos);
    label = "Items Producidos";
    color = {
      border: "#059669",
      background: "rgba(5, 150, 105, 0.1)",
      point: "#059669",
    };
  }

  // Destruir gráfico anterior si existe
  if (chartEvolucion) {
    chartEvolucion.destroy();
  }

  // Crear nuevo gráfico
  chartEvolucion = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: label,
          data: datos,
          borderColor: color.border,
          backgroundColor: color.background,
          pointBackgroundColor: color.point,
          pointBorderColor: "#ffffff",
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 3,
          fill: true,
          tension: 0.4,
        },
      ],
    },
    options: {
      ...configChart,
      plugins: {
        ...configChart.plugins,
        tooltip: {
          ...configChart.plugins.tooltip,
          callbacks: {
            label: function (context) {
              const valor = context.parsed.y.toLocaleString("es-PY");
              return `${context.dataset.label}: ${valor}`;
            },
          },
        },
      },
    },
  });

  // ✅ NUEVO: Actualizar badge del período según el modo
  if (modoHorario) {
    actualizarBadgePeriodo(`${datosEvolucion.length} horas`);
  } else {
    actualizarBadgePeriodo(datosEvolucion.length);
  }

  console.log("📈 Gráfico de evolución actualizado");
}

/**
 * ✅ ACTUALIZADA: Limpiar filtros - Incluir campos de hora
 */
function limpiarFiltros() {
  document.getElementById("fechaInicio").value =
    new Date().getFullYear() + "-01-01";
  document.getElementById("fechaFin").value = new Date()
    .toISOString()
    .slice(0, 10);
  document.getElementById("operador").value = "";
  document.getElementById("tipoProducto").value = "";
  document.getElementById("estado").value = "";

  // ✅ NUEVO: Limpiar campos de hora
  document.getElementById("horaInicio").value = "00:00";
  document.getElementById("horaFin").value = "23:59";

  // Ocultar contenedor de horarios
  const horaContainer = document.getElementById("horaContainer");
  if (horaContainer) {
    horaContainer.style.display = "none";
    horaContainer.classList.remove("visible", "fade-in");
  }

  // Recargar datos
  cargarDatosDashboard();
}

// ===============================================
// RESTO DE FUNCIONES EXISTENTES (sin cambios)
// ===============================================

function actualizarGraficoSector(datosSector) {
  if (!datosSector || datosSector.length === 0) {
    mostrarSinDatos(
      "chartSector",
      "No hay datos de sectores para el período seleccionado"
    );
    return;
  }

  const ctx = document.getElementById("chartSector");
  if (!ctx) return;

  const metricaSeleccionada =
    document.querySelector('input[name="metricaSector"]:checked')?.value ||
    "tara";

  const labels = datosSector.map((item) => item.tipo_producto);
  let datos, label, backgroundColor;

  if (metricaSeleccionada === "eficiencia") {
    datos = datosSector.map((item) => item.eficiencia_porcentaje);
    label = "Eficiencia (%)";
    backgroundColor = [
      "rgba(50, 91, 145, 0.8)",
      "rgba(220, 38, 38, 0.8)",
      "rgba(234, 88, 12, 0.8)",
      "rgba(5, 150, 105, 0.8)",
      "rgba(139, 92, 246, 0.8)",
    ];
  } else {
    datos = datosSector.map((item) => item.tara_total);
    label = "Tara Total (kg)";
    backgroundColor = [
      "rgba(6, 182, 212, 0.8)",
      "rgba(168, 85, 247, 0.8)",
      "rgba(34, 197, 94, 0.8)",
      "rgba(251, 146, 60, 0.8)",
      "rgba(239, 68, 68, 0.8)",
    ];
  }

  if (chartSector) {
    chartSector.destroy();
  }

  chartSector = new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: label,
          data: datos,
          backgroundColor: backgroundColor,
          borderColor: backgroundColor.map((color) =>
            color.replace("0.8", "1")
          ),
          borderWidth: 2,
          borderRadius: 6,
          borderSkipped: false,
          maxBarThickness: 80,
          barPercentage: 0.6,
          categoryPercentage: 0.8,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: {
          left: 10,
          right: 10,
          top: 10,
          bottom: 10,
        },
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          backgroundColor: "rgba(30, 58, 95, 0.9)",
          titleColor: "#ffffff",
          bodyColor: "#ffffff",
          callbacks: {
            label: function (context) {
              const item = datosSector[context.dataIndex];
              if (metricaSeleccionada === "eficiencia") {
                return [
                  `Eficiencia: ${context.parsed.y.toFixed(1)}%`,
                  `Peso Líquido: ${item.peso_liquido_total.toLocaleString(
                    "es-PY"
                  )} kg`,
                  `Peso Bruto: ${item.peso_bruto_total.toLocaleString(
                    "es-PY"
                  )} kg`,
                  `Tara: ${item.tara_total.toLocaleString("es-PY")} kg`,
                ];
              } else {
                return [
                  `Tara: ${context.parsed.y.toLocaleString("es-PY")} kg`,
                  `Bobinas: ${item.total_bobinas.toLocaleString("es-PY")}`,
                  `Eficiencia: ${item.eficiencia_porcentaje.toFixed(1)}%`,
                ];
              }
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            color: "rgba(0, 0, 0, 0.1)",
          },
          ticks: {
            font: {
              family: "Inter",
              size: 11,
            },
            color: "#64748b",
            callback: function (value) {
              if (metricaSeleccionada === "eficiencia") {
                return value + "%";
              }
              return value.toLocaleString("es-PY") + " kg";
            },
          },
        },
        x: {
          grid: {
            display: false,
          },
          ticks: {
            font: {
              family: "Inter",
              size: 11,
              weight: 600,
            },
            color: "#325b91",
            callback: function (value, index) {
              const label = this.getLabelForValue(value);
              return label.length > 10 ? label.substring(0, 10) + "..." : label;
            },
          },
        },
      },
    },
  });

  document.getElementById(
    "sectorCount"
  ).textContent = `${datosSector.length} sectores`;
  console.log("📊 Gráfico de sectores actualizado");
}

function crearGraficoClasificacion(datos) {
  const ctx = document.getElementById("chartClasificacion");
  if (!ctx) return;

  if (chartClasificacion) chartClasificacion.destroy();

  const labels = [
    "Dentro Media",
    "Pesado 0.5%",
    "Pesado 1%",
    "Liviano 3%",
    "Liviano 4%",
    "Muy Liviano",
    "Sin Datos",
  ];
  const valores = [
    datos["dentro-media"] || 0,
    datos["pesado-05"] || 0,
    datos["pesado-1"] || 0,
    datos["liviano-3"] || 0,
    datos["liviano-4"] || 0,
    datos["muy-liviano"] || 0,
    datos["sin-datos"] || 0,
  ];

  chartClasificacion = new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Cantidad de Items",
          data: valores,
          backgroundColor: [
            "#10b981",
            "#dc2626",
            "#b91c1c",
            "#fbbf24",
            "#f59e0b",
            "#991b1b",
            "#6b7280",
          ],
          borderColor: [
            "#059669",
            "#b91c1c",
            "#991b1b",
            "#f59e0b",
            "#d97706",
            "#7f1d1d",
            "#4b5563",
          ],
          borderWidth: 1,
          borderRadius: 4,
          maxBarThickness: 60,
          barPercentage: 0.8,
          categoryPercentage: 0.9,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: {
          left: 5,
          right: 5,
          top: 15,
          bottom: 5,
        },
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          backgroundColor: "rgba(30, 58, 95, 0.9)",
          titleColor: "#ffffff",
          bodyColor: "#ffffff",
          borderColor: "#dc2626",
          borderWidth: 1,
          cornerRadius: 8,
          callbacks: {
            label: function (context) {
              const total = valores.reduce((a, b) => a + b, 0);
              const porcentaje =
                total > 0 ? ((context.parsed.y / total) * 100).toFixed(1) : 0;
              return [
                `Cantidad: ${context.parsed.y.toLocaleString("es-PY")}`,
                `Porcentaje: ${porcentaje}%`,
              ];
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            color: "rgba(0, 0, 0, 0.1)",
          },
          ticks: {
            font: {
              family: "Inter",
              size: 10,
            },
            color: "#64748b",
          },
        },
        x: {
          grid: {
            display: false,
          },
          ticks: {
            font: {
              family: "Inter",
              size: 9,
              weight: 500,
            },
            color: "#325b91",
            maxRotation: 45,
            minRotation: 0,
            callback: function (value, index) {
              const label = this.getLabelForValue(value);
              return label.replace(/(\w+)\s(\d+\.?\d*%?)/, "$1\n$2");
            },
          },
        },
      },
    },
  });
}

function crearGraficoDispersion(datos) {
  const ctx = document.getElementById("chartDispersion");
  if (!ctx) return;

  if (chartDispersion) chartDispersion.destroy();

  const colores = {
    "dentro-media": "#10b981",
    "pesado-05": "#dc2626",
    "pesado-1": "#b91c1c",
    "liviano-3": "#fbbf24",
    "liviano-4": "#f59e0b",
    "muy-liviano": "#991b1b",
    "sin-datos": "#6b7280",
  };

  chartDispersion = new Chart(ctx, {
    type: "scatter",
    data: {
      datasets: [
        {
          label: "Peso Real vs Teórico",
          data: datos,
          backgroundColor: datos.map(
            (item) => colores[item.clase] || "#6b7280"
          ),
        },
        {
          label: "Línea Ideal",
          type: "line",
          data: [
            { x: 0, y: 0 },
            {
              x: Math.max(...datos.map((d) => d.x)),
              y: Math.max(...datos.map((d) => d.x)),
            },
          ],
          borderColor: "#374151",
          borderDash: [5, 5],
          pointRadius: 0,
        },
      ],
    },
    options: {
      responsive: true,
      scales: {
        x: {
          title: { display: true, text: "Peso Teórico (kg)" },
        },
        y: {
          title: { display: true, text: "Peso Real (kg)" },
        },
      },
    },
  });
}

function actualizarEstadisticas(stats) {
  if (!stats) {
    console.warn("⚠️ No hay estadísticas para mostrar");
    return;
  }

  animarContador("totalBobinas", 0, stats.total_bobinas || 0, 1000);
  animarContador("totalItems", 0, stats.total_items || 0, 1000);
  animarContador("totalPeso", 0, Math.round(stats.total_peso_bruto || 0), 1000);
  animarContador(
    "totalPesoli",
    0,
    Math.round(stats.total_peso_liquido || 0),
    1000
  );
  animarContador("totalOperadores", 0, stats.operadores_diferentes || 0, 800);

  console.log("📊 Estadísticas actualizadas");
}

function animarContador(elementoId, desde, hasta, duracion) {
  const elemento = document.getElementById(elementoId);
  if (!elemento) return;

  const inicio = performance.now();
  const diferencia = hasta - desde;

  function actualizar(tiempoActual) {
    const elapsed = tiempoActual - inicio;
    const progreso = Math.min(elapsed / duracion, 1);

    const valorActual = desde + diferencia * easeOutCubic(progreso);

    if (elementoId === "totalPeso") {
      elemento.textContent = Math.round(valorActual).toLocaleString("es-PY");
    } else {
      elemento.textContent = Math.round(valorActual).toLocaleString("es-PY");
    }

    if (progreso < 1) {
      requestAnimationFrame(actualizar);
    }
  }

  requestAnimationFrame(actualizar);
}

function easeOutCubic(t) {
  return 1 - Math.pow(1 - t, 3);
}

function mostrarTopProductos(productos) {
  const contenedor = document.getElementById("topProductosLista");
  if (!contenedor) return;

  if (!productos || productos.length === 0) {
    contenedor.innerHTML = `
      <div class="sin-datos">
        <i class="fas fa-box-open"></i>
        <h5>Sin productos</h5>
        <p>No se encontraron productos para el período seleccionado</p>
      </div>
    `;
    return;
  }

  const ordenSeleccionado =
    document.querySelector('input[name="ordenarPor"]:checked')?.value ||
    "bobinas";

  let productosOrdenados = [...productos];

  if (ordenSeleccionado === "ventas") {
    productosOrdenados.sort((a, b) => b.items_producidos - a.items_producidos);
  } else {
    productosOrdenados.sort((a, b) => {
      const cantidadA =
        a.cantidad_producida ||
        a.cantidad_bobinas ||
        parseInt(a.cantidad_formateada.replace(/[^\d]/g, "")) ||
        0;
      const cantidadB =
        b.cantidad_producida ||
        b.cantidad_bobinas ||
        parseInt(b.cantidad_formateada.replace(/[^\d]/g, "")) ||
        0;
      return cantidadB - cantidadA;
    });
  }

  let html = "";
  productosOrdenados.forEach((producto, index) => {
    const colorBorde = obtenerColorProducto(index);
    const icono = obtenerIconoTipo(producto.tipo_producto);

    let valorPrincipal, valorSecundario;
    if (ordenSeleccionado === "ventas") {
      valorPrincipal = producto.items_producidos.toLocaleString("es-PY");
      valorSecundario = `${producto.cantidad_formateada} items`;
    } else {
      valorPrincipal = producto.cantidad_formateada;
      valorSecundario = `${producto.items_producidos} bobinas`;
    }

    html += `
      <div class="producto-item" style="border-left-color: ${colorBorde};">
        <div class="producto-info">
          <div class="producto-nombre">
            <i class="${icono}" style="color: ${colorBorde}; margin-right: 0.5rem;"></i>
            ${producto.nombre_producto}
          </div>
          <div class="producto-tipo">
            ${producto.tipo_producto} • ${valorSecundario}
          </div>
        </div>
        <div class="producto-cantidad">
          <div class="cantidad-principal">
            ${valorPrincipal}
          </div>
          <div class="cantidad-detalle">
            ${producto.porcentaje_del_total}% del total
          </div>
        </div>
      </div>
    `;
  });

  contenedor.innerHTML = html;
  actualizarBadgeProductos(productos.length);

  console.log(
    `🏆 Top productos actualizado - Ordenado por: ${ordenSeleccionado}`
  );
}

function obtenerColorProducto(index) {
  const colores = ["#dc2626", "#ea580c", "#325b91", "#059669", "#8b5cf6"];
  return colores[index % colores.length];
}

function obtenerIconoTipo(tipo) {
  const iconos = {
    TOALLITAS: "fas fa-tissue",
    TNT: "fas fa-industry",
    SPUNLACE: "fas fa-layer-group",
    LAMINADO: "fas fa-layers",
  };
  return iconos[tipo?.toUpperCase()] || "fas fa-box";
}

function mostrarInformacionAdicional(datos) {
  const infoContainer = document.getElementById("infoAdicional");
  const textoInfo = document.getElementById("textoInfoAdicional");

  if (!infoContainer || !textoInfo) return;

  if (datos.resumen_periodo) {
    const resumen = datos.resumen_periodo;
    let mensaje = `Período analizado: ${resumen.dias_activos} días activos. `;

    if (resumen.mejor_dia) {
      mensaje += `Mejor día: ${
        resumen.mejor_dia.fecha_formateada
      } (${resumen.mejor_dia.cantidad.toLocaleString("es-PY")} bobinas). `;
    }

    if (resumen.tendencia !== "neutral") {
      const iconoTendencia = resumen.tendencia === "creciente" ? "📈" : "📉";
      mensaje += `${iconoTendencia} Tendencia ${resumen.tendencia}.`;
    }

    textoInfo.textContent = mensaje;
    infoContainer.style.display = "block";
  } else {
    infoContainer.style.display = "none";
  }
}

function actualizarBadgePeriodo(dias) {
  const badge = document.getElementById("periodoMostrar");
  if (badge) {
    let texto;
    if (typeof dias === "string") {
      texto = dias; // Para casos como "24 horas"
    } else {
      texto = dias === 1 ? "1 día" : `${dias} días`;
    }
    badge.textContent = texto;
  }
}

function actualizarBadgeProductos(cantidad) {
  const badge = document.getElementById("topProductosCount");
  if (badge) {
    const texto = cantidad === 1 ? "1 producto" : `${cantidad} productos`;
    badge.textContent = texto;
  }
}

function mostrarSinDatos(canvasId, mensaje) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  const parent = ctx.parentElement;
  parent.innerHTML = `
    <div class="sin-datos">
      <i class="fas fa-chart-line"></i>
      <h5>Sin datos</h5>
      <p>${mensaje}</p>
    </div>
  `;
}

function validarRangoFechas() {
  const fechaInicio = document.getElementById("fechaInicio");
  const fechaFin = document.getElementById("fechaFin");

  if (!fechaInicio || !fechaFin) return;

  const inicio = new Date(fechaInicio.value);
  const fin = new Date(fechaFin.value);

  if (inicio > fin) {
    mostrarError("La fecha de inicio no puede ser posterior a la fecha de fin");
    fechaInicio.focus();
    return false;
  }

  const diferenciaDias = (fin - inicio) / (1000 * 60 * 60 * 24);
  if (diferenciaDias > 365) {
    mostrarError("El rango de fechas no puede ser mayor a 1 año");
    fechaFin.focus();
    return false;
  }

  return true;
}

function mostrarPaginacion(paginacion) {
  const container = document.getElementById("paginacionProductos");
  const { pagina_actual, total_paginas } = paginacion;

  let html = "";

  html += `
    <li class="page-item ${pagina_actual === 1 ? "disabled" : ""}">
      <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(1); return false;" title="Primera página">
        <i class="fas fa-angle-double-left"></i>
      </a>
    </li>
  `;

  html += `
    <li class="page-item ${pagina_actual === 1 ? "disabled" : ""}">
      <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${
        pagina_actual - 1
      }); return false;" title="Página anterior">
        <i class="fas fa-chevron-left"></i>
      </a>
    </li>
  `;

  const rango = 2;

  if (pagina_actual > rango + 2) {
    html += `
      <li class="page-item">
        <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(1); return false;">1</a>
      </li>
    `;
    if (pagina_actual > rango + 3) {
      html += `
        <li class="page-item disabled">
          <span class="page-link">...</span>
        </li>
      `;
    }
  }

  for (
    let i = Math.max(1, pagina_actual - rango);
    i <= Math.min(total_paginas, pagina_actual + rango);
    i++
  ) {
    html += `
      <li class="page-item ${i === pagina_actual ? "active" : ""}">
        <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${i}); return false;">${i}</a>
      </li>
    `;
  }

  if (pagina_actual < total_paginas - rango - 1) {
    if (pagina_actual < total_paginas - rango - 2) {
      html += `
        <li class="page-item disabled">
          <span class="page-link">...</span>
        </li>
      `;
    }
    html += `
      <li class="page-item">
        <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${total_paginas}); return false;">${total_paginas}</a>
      </li>
    `;
  }

  html += `
    <li class="page-item ${pagina_actual === total_paginas ? "disabled" : ""}">
      <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${
        pagina_actual + 1
      }); return false;" title="Página siguiente">
        <i class="fas fa-chevron-right"></i>
      </a>
    </li>
  `;

  html += `
    <li class="page-item ${pagina_actual === total_paginas ? "disabled" : ""}">
      <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${total_paginas}); return false;" title="Última página">
        <i class="fas fa-angle-double-right"></i>
      </a>
    </li>
  `;

  container.innerHTML = html;

  const info = document.getElementById("infoPaginacion");
  const inicio = (pagina_actual - 1) * 10 + 1;
  const fin = Math.min(pagina_actual * 10, paginacion.total);
  info.textContent = `Mostrando ${inicio}-${fin} de ${paginacion.total} productos`;
}

function cambiarPagina(pagina) {
  if (pagina >= 1) {
    cargarProductosPaginados(pagina);
  }
}

function actualizarContadorProductos(total) {
  const badge = document.getElementById("totalProductosCount");
  badge.textContent = `${total.toLocaleString("es-PY")} productos`;
}

function mostrarErrorTabla() {
  const tbody = document.getElementById("tbodyProductos");
  tbody.innerHTML = `
    <tr>
      <td colspan="9" class="text-center py-4 text-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <p class="mb-0 mt-2">Error al cargar los productos</p>
      </td>
    </tr>
  `;
}

async function exportarPDF() {
  mostrarLoading(true, "Generando reporte...");

  try {
    const filtros = obtenerFiltrosFormulario();

    if (typeof PRODUCCION_CONFIG === "undefined") {
      throw new Error("Configuración no disponible");
    }

    const url = `?action=exportar_reporte&formato=csv&${new URLSearchParams(
      filtros
    ).toString()}`;

    const link = document.createElement("a");
    link.href = url;
    link.download = `reporte_produccion_${new Date()
      .toISOString()
      .slice(0, 10)}.csv`;
    link.click();

    mostrarExito("Reporte exportado exitosamente");
  } catch (error) {
    console.error("❌ Error exportando:", error);
    mostrarError("Error al exportar el reporte");
  } finally {
    mostrarLoading(false);
  }
}

function mostrarLoading(mostrar, mensaje = "Cargando datos...") {
  const overlay = document.getElementById("loadingOverlay");
  if (overlay) {
    if (mostrar) {
      overlay.style.display = "flex";
      const textoLoading = overlay.querySelector("h5");
      if (textoLoading) textoLoading.textContent = mensaje;
    } else {
      overlay.style.display = "none";
    }
  }
}

function mostrarError(mensaje) {
  console.error("❌ Error:", mensaje);
  const toast = crearToast("error", "Error", mensaje);
  mostrarToast(toast);
}

function mostrarExito(mensaje) {
  console.log("✅ Éxito:", mensaje);
  const toast = crearToast("success", "Éxito", mensaje);
  mostrarToast(toast);
}

function crearToast(tipo, titulo, mensaje) {
  const iconos = {
    error: "fas fa-exclamation-triangle",
    success: "fas fa-check-circle",
    warning: "fas fa-exclamation-circle",
    info: "fas fa-info-circle",
  };

  const colores = {
    error: "#dc2626",
    success: "#059669",
    warning: "#d97706",
    info: "#2563eb",
  };

  const toast = document.createElement("div");
  toast.className = `alert alert-${
    tipo === "error" ? "danger" : tipo === "success" ? "success" : tipo
  } alert-dismissible fade show position-fixed`;
  toast.style.cssText = `
    top: 20px;
    right: 20px;
    z-index: 10000;
    min-width: 300px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  `;

  toast.innerHTML = `
    <i class="${iconos[tipo]} me-2" style="color: ${colores[tipo]};"></i>
    <strong>${titulo}:</strong> ${mensaje}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;

  return toast;
}

function mostrarToast(toast) {
  document.body.appendChild(toast);

  setTimeout(() => {
    if (toast.parentNode) {
      toast.parentNode.removeChild(toast);
    }
  }, 5000);
}

function actualizarFechaHora() {
  const fechaElemento = document.getElementById("fechaActual");
  if (fechaElemento) {
    const ahora = new Date();
    fechaElemento.innerHTML = `<i class="fas fa-clock me-2"></i>${ahora.toLocaleString(
      "es-PY"
    )}`;
  }
}

// Inicialización cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  console.log(
    "📈 Sistema de Reportes de Producción v1.1 inicializado con filtros de horario"
  );

  if (typeof PRODUCCION_CONFIG !== "undefined") {
    console.log("📊 Configuración:", PRODUCCION_CONFIG);

    configurarEventos();
    cargarDatosDashboard();

    setInterval(actualizarFechaHora, 60000);
  } else {
    console.error("❌ Error: PRODUCCION_CONFIG no está definido");
    mostrarError("Error de configuración del sistema");
  }
});

// Exportar funciones al objeto window para uso global
window.cargarDatosDashboard = cargarDatosDashboard;
window.limpiarFiltros = limpiarFiltros;
window.exportarPDF = exportarPDF;
window.configurarEventos = configurarEventos;
window.cargarProductosPaginados = cargarProductosPaginados;
window.cambiarPagina = cambiarPagina;

console.log(
  "📈 Sistema de Reportes de Producción v1.1 cargado correctamente (con filtros de horario)"
);
