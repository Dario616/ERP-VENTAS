const ProduccionManager = {
  config: null,

  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("ProduccionManager inicializado", this.config);
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
      this.bindGroupDetailEvents();
      this.initAutoRefresh();
    },

    bindFilterEvents: function () {
      const filterForm = document.getElementById("filter-form");
      const clearFiltersBtn = document.getElementById("clear-filters");

      if (filterForm) {
        filterForm.addEventListener("submit", function (e) {
          e.preventDefault();
          ProduccionManager.indexPage.aplicarFiltros();
        });
      }

      if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener("click", function (e) {
          e.preventDefault();
          ProduccionManager.indexPage.limpiarFiltros();
        });
      }

      const fechaInputs = document.querySelectorAll(
        'input[name="fecha_desde"], input[name="fecha_hasta"]'
      );
      fechaInputs.forEach((input) => {
        input.addEventListener("change", function () {
          setTimeout(() => {
            ProduccionManager.indexPage.aplicarFiltros();
          }, 500);
        });
      });
    },

    bindSearchEvents: function () {
      const searchInput = document.getElementById("search-producto");

      if (searchInput) {
        let searchTimeout;

        searchInput.addEventListener("input", function () {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            ProduccionManager.indexPage.aplicarFiltros();
          }, 800);
        });
      }
    },

    bindGroupDetailEvents: function () {
      window.verDetallesGrupo = this.verDetallesGrupo;
      window.toggleGrupoDetalles = this.toggleGrupoDetalles;
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

    verDetallesGrupo: function (
      nombreProducto,
      tipoProducto,
      metragem,
      largura,
      gramatura
    ) {
      const modal = document.getElementById("detallesGrupoModal");
      const modalBody = document.getElementById("detalles-grupo-content");

      if (!modal || !modalBody) return;

      modalBody.innerHTML = `
        <div class="text-center py-4">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Cargando...</span>
          </div>
          <p class="mt-2">Cargando detalles...</p>
        </div>
      `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();

      const params = new URLSearchParams({
        action: "obtener_detalles_grupo",
        nombre_producto: nombreProducto,
        tipo_producto: tipoProducto,
        metragem: metragem || "",
        largura: largura || "",
        gramatura: gramatura || "",
      });

      fetch(
        `${ProduccionManager.config.url_base}secciones/produccion_seguimiento/productos.php?${params}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            ProduccionManager.indexPage.renderDetallesGrupo(data.detalles, {
              nombre_producto: nombreProducto,
              tipo_producto: tipoProducto,
              metragem: metragem,
              largura: largura,
              gramatura: gramatura,
            });
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

    renderDetallesGrupo: function (detalles, grupoInfo) {
      const modalBody = document.getElementById("detalles-grupo-content");
      if (!modalBody || !detalles.length) return;

      let html = `
    <div class="row mb-3">
      <div class="col-12">
        <h6 class="text-muted mb-2">
          <i class="fas fa-info-circle me-1"></i>
          Total de items: <span class="badge bg-primary">${detalles.length}</span> de <span class="badge bg-primary">${grupoInfo.nombre_producto}</span>
        </h6>
      </div>
    </div>
        
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead class="table-light">
              <tr>
                <th>Item #</th>
                <th>Metragem</th>
                <th>Peso Bruto</th>
                <th>Peso Líquido</th>
                <th>Bobinas</th>
                <th>Fecha Producción</th>
                <th>Usuario</th>
              </tr>
            </thead>
            <tbody>
      `;

      detalles.forEach((item) => {
        html += `
          <tr>
            <td>
              <span class="badge bg-secondary">${
                item.numero_item || "N/A"
              }</span>
            </td>
            <td>
              <small>${item.metragem || "N/A"}</small>
            </td>
            <td>${item.peso_bruto_formateado || "0 kg"}</td>
            
            <td>${item.peso_liquido_formateado || "0 kg"}</td>
            <td>${item.bobinas_pacote || 0}</td>
            <td>
              <small>${item.fecha_producida_formateada || "N/A"}</small>
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

    toggleGrupoDetalles: function (idGrupo) {
      const detalleRow = document.getElementById(`detalles-${idGrupo}`);
      const toggleBtn = document.querySelector(`[onclick*="${idGrupo}"]`);

      if (detalleRow) {
        if (detalleRow.style.display === "none") {
          detalleRow.style.display = "table-row";
          if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
          }
        } else {
          detalleRow.style.display = "none";
          if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
          }
        }
      }
    },

    initAutoRefresh: function () {
      if (ProduccionManager.config.auto_refresh) {
        setInterval(() => {
          ProduccionManager.indexPage.refreshStats();
        }, 300000);
      }
    },

    refreshStats: function () {
      fetch(
        `${ProduccionManager.config.url_base}secciones/produccion_seguimiento/productos.php?action=obtener_estadisticas`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.estadisticas) {
            ProduccionManager.indexPage.updateStatsDisplay(data.estadisticas);
          }
        })
        .catch((error) => {
          console.error("Error actualizando estadísticas:", error);
        });
    },

    updateStatsDisplay: function (stats) {
      const elements = {
        "total-items": stats.total_items_stock,
        "total-peso": stats.peso_bruto_formateado,
        "productos-diferentes": stats.productos_diferentes,
        "tipos-diferentes": stats.tipos_diferentes,
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
    exportarCSV: function (grupos) {
      if (!grupos || grupos.length === 0) {
        alert("No hay datos para exportar");
        return;
      }

      const headers = [
        "Producto",
        "Tipo",
        "Metragem",
        "Largura",
        "Gramatura",
        "Total Items",
        "Peso Bruto (kg)",
        "Peso Líquido (kg)",
        "Bobinas Total",
        "Fecha Primera",
        "Fecha Última",
      ];

      let csvContent = headers.join(",") + "\n";

      grupos.forEach((grupo) => {
        const row = [
          `"${grupo.nombre_producto || ""}"`,
          `"${grupo.tipo_producto || ""}"`,
          grupo.metragem || "",
          grupo.largura || "",
          grupo.gramatura || "",
          grupo.total_items || 0,
          grupo.peso_bruto_total || 0,
          grupo.peso_liquido_total || 0,
          grupo.bobinas_pacote_total || 0,
          `"${grupo.fecha_primera_formateada || ""}"`,
          `"${grupo.fecha_ultima_formateada || ""}"`,
        ];
        csvContent += row.join(",") + "\n";
      });

      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      const url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute(
        "download",
        `produccion_stock_${new Date().toISOString().split("T")[0]}.csv`
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

window.verDetallesGrupo = function (
  nombreProducto,
  tipoProducto,
  metragem,
  largura,
  gramatura
) {
  ProduccionManager.indexPage.verDetallesGrupo(
    nombreProducto,
    tipoProducto,
    metragem,
    largura,
    gramatura
  );
};

window.toggleGrupoDetalles = function (idGrupo) {
  ProduccionManager.indexPage.toggleGrupoDetalles(idGrupo);
};

window.exportarCSV = function (grupos) {
  ProduccionManager.exportModule.exportarCSV(grupos);
};

document.addEventListener("DOMContentLoaded", function () {
  if (typeof PRODUCCION_CONFIG !== "undefined") {
    ProduccionManager.init(PRODUCCION_CONFIG);
  }

  const currentPath = window.location.pathname;

  if (
    currentPath.includes("productos.php") ||
    currentPath.endsWith("/produccion_seguimiento/")
  ) {
    ProduccionManager.indexPage.init();
  }
});

if (typeof module !== "undefined" && module.exports) {
  module.exports = ProduccionManager;
}
