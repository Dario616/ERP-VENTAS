const ProductosAsignadosManager = {
  config: null,

  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("ProductosAsignadosManager inicializado", this.config);
  },

  bindEvents: function () {
    this.initBootstrapTooltips();
    this.initDatePickers();
  },

  initBootstrapTooltips: function () {
    if (typeof bootstrap !== "undefined") {
      const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
      );
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }
  },

  initDatePickers: function () {
    const fechaInputs = document.querySelectorAll('input[type="date"]');
    const hoy = new Date().toISOString().split("T")[0];

    fechaInputs.forEach((input) => {
      if (!input.hasAttribute("max")) {
        input.setAttribute("max", hoy);
      }
    });
  },

  indexPage: {
    init: function () {
      this.bindFilterEvents();
      this.bindSearchEvents();
      this.bindOrderDetailEvents();
      this.initAutoRefresh();
    },

    bindFilterEvents: function () {
      const filterForm = document.getElementById("filter-form");
      const clearFiltersBtn = document.getElementById("clear-filters");

      if (filterForm) {
        filterForm.addEventListener("submit", function (e) {
          e.preventDefault();
          ProductosAsignadosManager.indexPage.aplicarFiltros();
        });
      }

      if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener("click", function (e) {
          e.preventDefault();
          ProductosAsignadosManager.indexPage.limpiarFiltros();
        });
      }

      const fechaInputs = document.querySelectorAll(
        'input[name="fecha_desde"], input[name="fecha_hasta"]'
      );
      fechaInputs.forEach((input) => {
        input.addEventListener("change", function () {
          setTimeout(() => {
            ProductosAsignadosManager.indexPage.aplicarFiltros();
          }, 500);
        });
      });
    },

    bindSearchEvents: function () {
      const searchInputs = document.querySelectorAll(
        "#search-cliente, #search-producto"
      );

      searchInputs.forEach((searchInput) => {
        if (searchInput) {
          let searchTimeout;

          searchInput.addEventListener("input", function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
              ProductosAsignadosManager.indexPage.aplicarFiltros();
            }, 800);
          });
        }
      });
    },

    bindOrderDetailEvents: function () {
      window.verDetallesOrden = this.verDetallesOrden;
    },

    aplicarFiltros: function () {
      const form = document.getElementById("filter-form");
      if (!form) return;

      const formData = new FormData(form);
      const params = new URLSearchParams();

      for (let [key, value] of formData.entries()) {
        if (value.trim() !== "") {
          params.append(key, value);
        }
      }

      window.location.search = params.toString();
    },

    limpiarFiltros: function () {
      const form = document.getElementById("filter-form");
      if (form) {
        form.reset();
      }

      window.location.href = window.location.pathname;
    },

    verDetallesOrden: function (idOrdenProduccion) {
      const modal = document.getElementById("detallesOrdenModal");
      const modalBody = document.getElementById("detalles-orden-content");

      if (!modal || !modalBody) return;

      modalBody.innerHTML = `
        <div class="text-center py-4">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Cargando...</span>
          </div>
          <p class="mt-2">Cargando detalles de la orden...</p>
        </div>
      `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();

      const params = new URLSearchParams({
        action: "obtener_detalles_orden",
        id_orden_produccion: idOrdenProduccion,
      });

      fetch(
        `${ProductosAsignadosManager.config.url_base}secciones/produccion_seguimiento/productos_asignados.php?${params}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            ProductosAsignadosManager.indexPage.renderDetallesOrden(
              data.detalles,
              idOrdenProduccion
            );
          } else {
            modalBody.innerHTML = `
              <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: ${data.error || "No se pudieron cargar los detalles"}
              </div>
            `;
          }
        })
        .catch((error) => {
          console.error("Error cargando detalles:", error);
          modalBody.innerHTML = `
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-circle me-2"></i>
              Error de conexión al cargar los detalles
            </div>
          `;
        });
    },

    renderDetallesOrden: function (detalles, idOrdenProduccion) {
      const modalBody = document.getElementById("detalles-orden-content");
      if (!modalBody || !detalles.length) return;

      const primerItem = detalles[0];

      let html = `
    <div class="row mb-4">
      <div class="col-md-6">
        <div class="card bg-light">
          <div class="card-body">
            <h6 class="card-title">
              <i class="fas fa-info-circle me-2"></i>Información de la Orden
            </h6>
            <div class="row">
              <div class="col-6">
                <strong>Orden #:</strong> ${primerItem.id_orden_produccion}<br>
                <strong>Cliente:</strong> ${primerItem.cliente}<br>
                <strong>Fecha Orden:</strong> ${
                  primerItem.fecha_orden_formateada
                }
              </div>
              <div class="col-6">
                <strong>Estado:</strong> 
                <span class="badge ${primerItem.estado_badge_class}">${
        primerItem.estado_orden
      }</span><br>
                ${
                  primerItem.id_venta
                    ? `<strong>ID Venta:</strong> ${primerItem.id_venta}<br>`
                    : ""
                }
                <strong>Productos Agrupados:</strong> <span class="badge bg-primary">${
                  detalles.length
                }</span>
              </div>
            </div>
            ${
              primerItem.observaciones
                ? `<div class="mt-2">
                <strong>Observaciones:</strong><br>
                <small class="text-muted">${primerItem.observaciones}</small>
              </div>`
                : ""
            }
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card bg-light">
          <div class="card-body">
            <h6 class="card-title">
              <i class="fas fa-chart-bar me-2"></i>Resumen de Productos
            </h6>
            <div class="row">
              <div class="col-6">
                <strong>Items Totales:</strong><br>
                <span class="text-primary">${ProductosAsignadosManager.utils.calcularTotalItems(
                  detalles
                )}</span><br>
                <strong>Peso Bruto Total:</strong><br>
                <span class="text-primary">${ProductosAsignadosManager.utils.calcularTotalPeso(
                  detalles,
                  "peso_bruto"
                )}</span>
              </div>
              <div class="col-6">
                <strong>Peso Líquido Total:</strong><br>
                <span class="text-success">${ProductosAsignadosManager.utils.calcularTotalPeso(
                  detalles,
                  "peso_liquido"
                )}</span><br>
                <strong>Bobinas Total:</strong><br>
                <span class="text-info">${ProductosAsignadosManager.utils.calcularTotalBobinas(
                  detalles
                )}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  
    
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light">
          <tr>
            <th>Producto</th>
            <th>Tipo</th>
            <th>Metragem</th>
            <th>Largura</th>
            <th>Bobinas</th>
            <th>Peso Bruto</th>
            <th>Peso Líquido</th>
            <th>Fecha Producción</th>
            <th>Maquina</th>
          </tr>
        </thead>
        <tbody>
  `;

      detalles.forEach((item) => {
        html += `
      <tr>
        <td>
          <strong>${item.nombre_producto || "N/A"}</strong>
        </td>
        <td>
          <span class="badge bg-info">${item.tipo_producto || "N/A"}</span>
        </td>
        <td>
          <small>${item.metragem || "N/A"}</small>
        </td>
        <td>
          <small>${item.largura || "N/A"}</small>
        </td>
        <td class="text-center">${item.bobinas_pacote || 0}</td>
        <td>${item.peso_bruto_formateado || "0 kg"}</td>
        <td>${item.peso_liquido_formateado || "0 kg"}</td>
        <td>
          <small>${item.fecha_producida_formateada || "N/A"}</small>
          ${
            item.fecha_ultima_produccion &&
            item.fecha_ultima_produccion !== item.fecha_hora_producida
              ? `<br><small class="text-muted">Hasta: ${ProductosAsignadosManager.utils.formatearFecha(
                  item.fecha_ultima_produccion
                )}</small>`
              : ""
          }
          ${
            item.tiempo_desde_produccion
              ? `<br><small class="text-muted">hace ${item.tiempo_desde_produccion}</small>`
              : ""
          }
        </td>
        <td>
          <small>${item.usuario || "N/A"}</small>
        </td>
      </tr>
    `;
      });

      html += `
        </tbody>
      </table>
    </div>
  `;

      modalBody.innerHTML = html;
    },

    initAutoRefresh: function () {
      if (ProductosAsignadosManager.config.auto_refresh) {
        setInterval(() => {
          ProductosAsignadosManager.indexPage.refreshStats();
        }, 300000);
      }
    },

    refreshStats: function () {
      fetch(
        `${ProductosAsignadosManager.config.url_base}secciones/produccion_seguimiento/productos_asignados.php?action=obtener_estadisticas_asignados`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.estadisticas) {
            ProductosAsignadosManager.indexPage.updateStatsDisplay(
              data.estadisticas
            );
          }
        })
        .catch((error) => {
          console.error("Error actualizando estadísticas:", error);
        });
    },

    updateStatsDisplay: function (stats) {
      const elements = {
        "total-ordenes": stats.ordenes_diferentes,
        "total-clientes": stats.clientes_diferentes,
        "total-items": stats.total_items_asignados,
        "total-peso": stats.peso_bruto_formateado,
      };

      Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element && value !== undefined) {
          element.textContent = value;
        }
      });
    },
  },

  exportModule: {
    exportarCSV: function (ordenes) {
      if (!ordenes || ordenes.length === 0) {
        alert("No hay datos para exportar");
        return;
      }

      const headers = [
        "Orden #",
        "ID Venta",
        "Cliente",
        "Estado",
        "Fecha Orden",
        "Total Items",
        "Productos Diferentes",
        "Peso Bruto (kg)",
        "Peso Líquido (kg)",
        "Bobinas Total",
        "Productos Lista",
      ];

      let csvContent = headers.join(",") + "\n";

      ordenes.forEach((orden) => {
        const row = [
          orden.id_orden_produccion || "",
          orden.id_venta || "",
          `"${orden.cliente || ""}"`,
          `"${orden.estado_orden || ""}"`,
          `"${orden.fecha_orden_formateada || ""}"`,
          orden.total_items || 0,
          orden.productos_diferentes || 0,
          orden.peso_bruto_total || 0,
          orden.peso_liquido_total || 0,
          orden.bobinas_pacote_total || 0,
          `"${orden.productos_lista || ""}"`,
        ];
        csvContent += row.join(",") + "\n";
      });

      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      const url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute(
        "download",
        `productos_asignados_${new Date().toISOString().split("T")[0]}.csv`
      );
      link.style.visibility = "hidden";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    },
  },

  utils: {
    showToast: function (message, type = "info") {
      console.log(`[${type.toUpperCase()}] ${message}`);
    },

    formatPeso: function (peso) {
      if (!peso || peso == 0) return "0 kg";

      if (peso >= 1000) {
        return (peso / 1000).toFixed(2).replace(".", ",") + " t";
      } else {
        return peso.toFixed(2).replace(".", ",") + " kg";
      }
    },

    calcularTotalPeso: function (items, campo) {
      const total = items.reduce((sum, item) => {
        return sum + (parseFloat(item[campo]) || 0);
      }, 0);
      return this.formatPeso(total);
    },

    calcularTotalBobinas: function (items) {
      const total = items.reduce((sum, item) => {
        return sum + (parseInt(item.bobinas_pacote) || 0);
      }, 0);
      return total.toLocaleString();
    },

    calcularTotalItems: function (items) {
      const total = items.reduce((sum, item) => {
        return sum + (parseInt(item.total_items) || 1);
      }, 0);
      return total.toLocaleString();
    },

    contarProductosUnicos: function (items) {
      const productosUnicos = new Set(
        items.map((item) => item.nombre_producto)
      );
      return productosUnicos.size;
    },

    formatearFecha: function (fecha) {
      if (!fecha) return "";

      try {
        const dt = new Date(fecha);
        return dt.toLocaleDateString("es-PY", {
          day: "2-digit",
          month: "2-digit",
          year: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        });
      } catch (e) {
        return fecha;
      }
    },

    debounce: function (func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    },
  },
};

window.verDetallesOrden = function (idOrdenProduccion) {
  ProductosAsignadosManager.indexPage.verDetallesOrden(idOrdenProduccion);
};

window.exportarCSVAsignados = function (ordenes) {
  ProductosAsignadosManager.exportModule.exportarCSV(ordenes);
};

document.addEventListener("DOMContentLoaded", function () {
  if (typeof PRODUCTOS_ASIGNADOS_CONFIG !== "undefined") {
    ProductosAsignadosManager.init(PRODUCTOS_ASIGNADOS_CONFIG);
  }

  const currentPath = window.location.pathname;

  if (currentPath.includes("productos_asignados.php")) {
    ProductosAsignadosManager.indexPage.init();
  }
});

if (typeof module !== "undefined" && module.exports) {
  module.exports = ProductosAsignadosManager;
}