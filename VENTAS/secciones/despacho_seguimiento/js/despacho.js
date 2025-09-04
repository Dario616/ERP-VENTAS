const DespachoManager = {
  config: null,

  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("DespachoManager inicializado", this.config);
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

  mainPage: {
    init: function () {
      this.bindFilterEvents();
      this.bindSearchEvents();
      this.initAutoRefresh();
    },

    bindFilterEvents: function () {
      const filterForm = document.getElementById("filter-form");
      const clearFiltersBtn = document.getElementById("clear-filters");

      if (filterForm) {
        filterForm.addEventListener("submit", function (e) {
          e.preventDefault();
          DespachoManager.mainPage.aplicarFiltros();
        });
      }

      if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener("click", function (e) {
          e.preventDefault();
          DespachoManager.mainPage.limpiarFiltros();
        });
      }

      const fechaInputs = document.querySelectorAll(
        'input[name="fecha_desde"], input[name="fecha_hasta"]'
      );
      fechaInputs.forEach((input) => {
        input.addEventListener("change", function () {
          setTimeout(() => {
            DespachoManager.mainPage.aplicarFiltros();
          }, 500);
        });
      });

      this.initIdStockValidation();
    },

    bindSearchEvents: function () {
      const searchInput = document.getElementById("search-expedicion");

      if (searchInput) {
        let searchTimeout;

        searchInput.addEventListener("input", function () {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            DespachoManager.mainPage.aplicarFiltros();
          }, 800);
        });
      }
    },

    aplicarFiltros: function () {
      const form = document.getElementById("filter-form");
      if (!form) return;

      const idStockInput = document.querySelector('input[name="id_stock"]');
      if (idStockInput && idStockInput.value.trim() !== "") {
        const validacion = DespachoManager.utils.validarIdStock(
          idStockInput.value
        );
        if (!validacion.valido) {
          alert("Error en ID Stock: " + validacion.mensaje);
          idStockInput.focus();
          return;
        }
      }

      const formData = new FormData(form);
      const params = new URLSearchParams();

      for (let [key, value] of formData.entries()) {
        if (value.trim() !== "") {
          params.append(key, value);
        }
      }

      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML =
          '<i class="fas fa-spinner fa-spin me-1"></i>Buscando...';
        submitBtn.disabled = true;
        setTimeout(() => {
          submitBtn.innerHTML = originalHtml;
          submitBtn.disabled = false;
        }, 5000);
      }

      window.location.search = params.toString();
    },

    limpiarFiltros: function () {
      const form = document.getElementById("filter-form");
      if (form) {
        form.reset();
      }

      const campos = [
        'input[name="numero_expedicion"]',
        'input[name="id_stock"]',
        'select[name="transportista"]',
        'select[name="destino"]',
        'select[name="estado"]',
        'input[name="fecha_desde"]',
        'input[name="fecha_hasta"]',
      ];

      campos.forEach((selector) => {
        const campo = document.querySelector(selector);
        if (campo) {
          campo.value = "";
        }
      });

      window.location.href = window.location.pathname;
    },

    initIdStockValidation: function () {
      const idStockInput = document.querySelector('input[name="id_stock"]');

      if (idStockInput) {
        idStockInput.addEventListener("input", function () {
          const value = this.value.trim();
          this.setCustomValidity("");
          this.classList.remove("is-invalid");

          if (value !== "") {
            if (!/^\d+$/.test(value) || parseInt(value) <= 0) {
              this.setCustomValidity("Debe ser un número entero mayor a 0");
              this.classList.add("is-invalid");
              return;
            }
            if (value.length > 10) {
              this.setCustomValidity("El ID es demasiado largo");
              this.classList.add("is-invalid");
              return;
            }
          }
        });

        idStockInput.addEventListener("blur", function () {
          const value = this.value.trim();

          if (value !== "" && /^\d+$/.test(value) && parseInt(value) > 0) {
            setTimeout(() => {
              DespachoManager.mainPage.aplicarFiltros();
            }, 300);
          }
        });

        idStockInput.addEventListener("keypress", function (e) {
          if (
            [8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true)
          ) {
            return;
          }
          if (
            (e.shiftKey || e.keyCode < 48 || e.keyCode > 57) &&
            (e.keyCode < 96 || e.keyCode > 105)
          ) {
            e.preventDefault();
          }
        });
      }
    },

    initAutoRefresh: function () {
      if (DespachoManager.config.auto_refresh) {
        setInterval(() => {
          DespachoManager.mainPage.refreshStats();
        }, 300000);
      }
    },

    refreshStats: function () {
      fetch(
        `${DespachoManager.config.url_base}secciones/despacho_seguimiento/main.php?action=obtener_estadisticas_despacho`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.estadisticas) {
            DespachoManager.mainPage.updateStatsDisplay(data.estadisticas);
          }
        })
        .catch((error) => {
          console.error("Error actualizando estadísticas:", error);
        });
    },

    updateStatsDisplay: function (stats) {
      const elements = {
        "total-expediciones": stats.total_expediciones,
        "total-items": stats.total_items_escaneados,
        "total-peso": stats.peso_bruto_formateado,
        "total-transportistas": stats.transportistas_diferentes,
      };

      Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element && value !== undefined) {
          element.textContent = value;
        }
      });
    },
  },

  detailPage: {
    init: function () {
      this.bindDetailEvents();
      this.initTableFilters();
      this.initStockFilter();
    },

    bindDetailEvents: function () {
      this.initReasignedFilter();
      this.initExportButtons();
    },

    initReasignedFilter: function () {
      const filterBtn = document.getElementById("filter-reasignados");

      if (filterBtn) {
        filterBtn.addEventListener("click", function () {
          const reasignedRows = document.querySelectorAll("tr.table-warning");
          const isHidden = reasignedRows[0]?.style.display === "none";

          reasignedRows.forEach((row) => {
            row.style.display = isHidden ? "" : "none";
          });

          this.innerHTML = isHidden
            ? '<i class="fas fa-eye-slash me-1"></i>Ocultar Reasignados'
            : '<i class="fas fa-eye me-1"></i>Mostrar Solo Reasignados';
        });
      }
    },

    initExportButtons: function () {
      const exportBtn = document.getElementById("export-expedicion");

      if (exportBtn) {
        exportBtn.addEventListener("click", function () {
          DespachoManager.exportModule.exportarExpedicionCSV();
        });
      }
    },

    initTableFilters: function () {
      const clienteFilter = document.getElementById("cliente-filter");

      if (clienteFilter) {
        clienteFilter.addEventListener("input", function () {
          const filterValue = this.value.toLowerCase();
          const rows = document.querySelectorAll("tbody tr");

          rows.forEach((row) => {
            const clienteCell = row.querySelector("td:nth-child(3)");
            if (clienteCell) {
              const clienteText = clienteCell.textContent.toLowerCase();
              row.style.display = clienteText.includes(filterValue)
                ? ""
                : "none";
            }
          });
        });
      }
    },

    initStockFilter: function () {
      const filtroIdStock = document.getElementById("filtro-id-stock");
      const limpiarFiltro = document.getElementById("limpiar-filtro-stock");
      const resultadosSpan = document.getElementById("filtro-resultados");

      if (filtroIdStock) {
        console.log("Inicializando filtro de ID Stock");

        filtroIdStock.addEventListener("input", function () {
          const filtro = this.value.trim().toLowerCase();
          const filas = document.querySelectorAll("#items-table tbody tr");
          let visibles = 0;

          filas.forEach((fila) => {
            const stockIdCell = fila.querySelector("td:first-child");
            if (stockIdCell) {
              const stockIdText = stockIdCell.textContent.toLowerCase();
              const contiene = filtro === "" || stockIdText.includes(filtro);

              fila.style.display = contiene ? "" : "none";
              if (contiene) visibles++;
            }
          });

          if (resultadosSpan) {
            if (filtro !== "") {
              resultadosSpan.textContent = `${visibles} de ${filas.length} items`;
              resultadosSpan.style.display = "inline";
              resultadosSpan.className =
                visibles > 0
                  ? "ms-2 badge bg-success"
                  : "ms-2 badge bg-warning text-dark";
            } else {
              resultadosSpan.style.display = "none";
            }
          }
        });

        filtroIdStock.addEventListener("keypress", function (e) {
          if (
            ![8, 9, 27, 13, 46].includes(e.keyCode) &&
            !(
              (e.keyCode === 65 ||
                e.keyCode === 67 ||
                e.keyCode === 86 ||
                e.keyCode === 88) &&
              e.ctrlKey
            ) &&
            (e.shiftKey || e.keyCode < 48 || e.keyCode > 57) &&
            (e.keyCode < 96 || e.keyCode > 105)
          ) {
            e.preventDefault();
          }
        });

        filtroIdStock.addEventListener("focus", function () {
          this.select();
        });

        filtroIdStock.addEventListener("keydown", function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            const filtro = this.value.trim();
            if (filtro !== "") {
              const filas = document.querySelectorAll("#items-table tbody tr");
              filas.forEach((fila) => {
                const stockIdCell = fila.querySelector("td:first-child");
                if (stockIdCell) {
                  const stockId =
                    stockIdCell.textContent.match(/#(\d+)/)?.[1] || "";
                  fila.style.display = stockId === filtro ? "" : "none";
                }
              });
            }
          }
        });
      }

      if (limpiarFiltro) {
        limpiarFiltro.addEventListener("click", function () {
          if (filtroIdStock) {
            filtroIdStock.value = "";
            filtroIdStock.dispatchEvent(new Event("input"));
            filtroIdStock.focus();
          }
        });
      }
    },
  },

  exportModule: {
    exportarExpedicionesCSV: function (expediciones) {
      if (!expediciones || expediciones.length === 0) {
        alert("No hay datos para exportar");
        return;
      }

      const headers = [
        "N° Expedición",
        "Estado",
        "Transportista",
        "Conductor",
        "Placa Vehículo",
        "Destino",
        "Fecha Creación",
        "Fecha Despacho",
        "Total Items",
        "Clientes Diferentes",
        "Peso Bruto (kg)",
        "Peso Escaneado (kg)",
        "Usuario Creación",
      ];

      let csvContent = headers.join(",") + "\n";

      expediciones.forEach((exp) => {
        const row = [
          exp.numero_expedicion || "",
          `"${exp.estado || ""}"`,
          `"${exp.transportista || ""}"`,
          `"${exp.conductor || ""}"`,
          `"${exp.placa_vehiculo || ""}"`,
          `"${exp.destino || ""}"`,
          `"${exp.fecha_creacion_formateada || ""}"`,
          `"${exp.fecha_despacho_formateada || ""}"`,
          exp.total_items || 0,
          exp.clientes_diferentes || 0,
          exp.peso_bruto_total || 0,
          exp.peso_escaneado_total || 0,
          `"${exp.usuario_creacion || ""}"`,
        ];
        csvContent += row.join(",") + "\n";
      });

      this.downloadCSV(csvContent, "expediciones");
    },

    exportarExpedicionCSV: function () {
      const table = document.querySelector("#items-table");
      if (!table) {
        alert("No se encontró tabla para exportar");
        return;
      }

      const headers = [];
      const headerCells = table.querySelectorAll("thead th");
      headerCells.forEach((cell) => {
        headers.push(`"${cell.textContent.trim()}"`);
      });

      let csvContent = headers.join(",") + "\n";
      let filasExportadas = 0;

      const rows = table.querySelectorAll("tbody tr");
      rows.forEach((row) => {
        if (row.style.display !== "none") {
          const cells = row.querySelectorAll("td");
          const rowData = [];

          cells.forEach((cell) => {
            let text = cell.textContent.trim().replace(/\s+/g, " ");
            text = text.replace(/[\r\n\t]/g, " ");
            rowData.push(`"${text}"`);
          });

          csvContent += rowData.join(",") + "\n";
          filasExportadas++;
        }
      });

      if (filasExportadas === 0) {
        alert("No hay items visibles para exportar");
        return;
      }

      const numeroExp =
        document
          .querySelector("h1, .breadcrumb-item.active")
          ?.textContent?.match(/\d+/)?.[0] || "expedicion";

      const mensaje = `Se exportarán ${filasExportadas} items. ¿Continuar?`;
      if (confirm(mensaje)) {
        this.downloadCSV(csvContent, `expedicion_${numeroExp}_items`);
      }
    },

    downloadCSV: function (csvContent, filename) {
      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      const url = URL.createObjectURL(blob);

      link.setAttribute("href", url);
      link.setAttribute(
        "download",
        `${filename}_${new Date().toISOString().split("T")[0]}.csv`
      );
      link.style.visibility = "hidden";

      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      DespachoManager.utils.showToast(
        `Archivo ${filename}.csv descargado exitosamente`,
        "success"
      );
    },
  },

  statsModule: {
    updateStats: function (newStats) {
      if (!newStats) return;

      this.updateCounter("total-expediciones", newStats.total_expediciones);
      this.updateCounter("total-items", newStats.total_items_escaneados);
      this.updateCounter(
        "total-transportistas",
        newStats.transportistas_diferentes
      );

      const pesoElement = document.querySelector('[data-stat="peso-total"]');
      if (pesoElement && newStats.peso_bruto_formateado) {
        pesoElement.textContent = newStats.peso_bruto_formateado;
      }
    },

    updateCounter: function (elementId, newValue) {
      const element = document.getElementById(elementId);
      if (!element || !newValue) return;

      const currentValue =
        parseInt(element.textContent.replace(/\D/g, "")) || 0;
      const targetValue = parseInt(newValue) || 0;

      if (currentValue !== targetValue) {
        this.animateCounter(element, currentValue, targetValue);
      }
    },

    animateCounter: function (element, start, end) {
      const duration = 1000;
      const range = end - start;
      const increment = range / (duration / 16);
      let current = start;

      const timer = setInterval(() => {
        current += increment;

        if (
          (increment > 0 && current >= end) ||
          (increment < 0 && current <= end)
        ) {
          current = end;
          clearInterval(timer);
        }

        element.textContent = Math.floor(current).toLocaleString();
      }, 16);
    },
  },

  utils: {
    showToast: function (message, type = "info") {
      console.log(`[${type.toUpperCase()}] ${message}`);

      if (typeof bootstrap !== "undefined" && bootstrap.Toast) {
        this.createDynamicToast(message, type);
      } else {
        alert(message);
      }
    },

    createDynamicToast: function (message, type) {
      const toastContainer = document.querySelector(".toast-container");
      if (!toastContainer) {
        const container = document.createElement("div");
        container.className = "toast-container position-fixed top-0 end-0 p-3";
        container.style.zIndex = "1080";
        document.body.appendChild(container);
      }

      const toastHtml = `
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="toast-header">
            <i class="fas fa-${this.getToastIcon(type)} me-2 text-${type}"></i>
            <strong class="me-auto">Despacho</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
          </div>
          <div class="toast-body">${message}</div>
        </div>
      `;

      const container = document.querySelector(".toast-container");
      container.insertAdjacentHTML("beforeend", toastHtml);

      const toastEl = container.lastElementChild;
      const toast = new bootstrap.Toast(toastEl);
      toast.show();

      toastEl.addEventListener("hidden.bs.toast", () => {
        toastEl.remove();
      });
    },

    getToastIcon: function (type) {
      const icons = {
        success: "check-circle",
        error: "exclamation-circle",
        warning: "exclamation-triangle",
        info: "info-circle",
      };
      return icons[type] || "info-circle";
    },

    formatPeso: function (peso) {
      if (!peso || peso == 0) return "0 kg";

      if (peso >= 1000) {
        return (peso / 1000).toFixed(2).replace(".", ",") + " t";
      } else {
        return peso.toFixed(2).replace(".", ",") + " kg";
      }
    },

    formatFecha: function (fecha) {
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

    validarNumeroExpedicion: function (numero) {
      return numero && numero.length > 0;
    },

    validarIdStock: function (idStock) {
      if (!idStock || idStock.trim() === "") {
        return { valido: true, mensaje: "" };
      }

      const numero = parseInt(idStock.trim());

      if (isNaN(numero) || numero <= 0) {
        return {
          valido: false,
          mensaje: "El ID de stock debe ser un número entero mayor a 0",
        };
      }

      if (numero > 999999999) {
        return {
          valido: false,
          mensaje: "El ID de stock es demasiado grande",
        };
      }

      return { valido: true, mensaje: "" };
    },

    calcularTiempoTranscurrido: function (fecha) {
      if (!fecha) return "";

      try {
        const fechaObj = new Date(fecha);
        const ahora = new Date();
        const diff = ahora - fechaObj;

        const dias = Math.floor(diff / (1000 * 60 * 60 * 24));
        const horas = Math.floor(
          (diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
        );
        const minutos = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        if (dias > 0) {
          return `${dias} día${dias > 1 ? "s" : ""}`;
        } else if (horas > 0) {
          return `${horas} hora${horas > 1 ? "s" : ""}`;
        } else {
          return `${minutos} minuto${minutos > 1 ? "s" : ""}`;
        }
      } catch (e) {
        return "";
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

    limpiarHighlights: function () {
      const marks = document.querySelectorAll("mark");
      marks.forEach((mark) => {
        mark.outerHTML = mark.innerHTML;
      });
    },

    scrollToElement: function (element) {
      if (element) {
        element.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      }
    },
  },
};

window.verDetallesExpedicion = function (numeroExpedicion) {
  if (DespachoManager.config && DespachoManager.config.url_base) {
    window.location.href = `${
      DespachoManager.config.url_base
    }secciones/despacho_seguimiento/ver.php?numero_expedicion=${encodeURIComponent(
      numeroExpedicion
    )}`;
  }
};

window.exportarCSVExpediciones = function (expediciones) {
  DespachoManager.exportModule.exportarExpedicionesCSV(expediciones);
};

window.exportarCSVExpedicion = function () {
  DespachoManager.exportModule.exportarExpedicionCSV();
};

window.toggleAgrupamiento = function () {
  console.log("toggleAgrupamiento() llamada");

  try {
    const urlParams = new URLSearchParams(window.location.search);
    const agruparActual = urlParams.get("agrupar") === "true";

    console.log("Estado actual agrupar:", agruparActual);
    console.log("URL actual:", window.location.href);
    if (agruparActual) {
      urlParams.delete("agrupar");
      console.log("Removiendo parámetro agrupar");
    } else {
      urlParams.set("agrupar", "true");
      console.log("Agregando parámetro agrupar=true");
    }

    const newSearch = urlParams.toString();
    const newUrl =
      window.location.pathname + (newSearch ? "?" + newSearch : "");

    console.log("Nueva URL será:", newUrl);
    const button = document.getElementById("toggle-agrupamiento");
    if (button) {
      button.disabled = true;
      button.innerHTML =
        '<i class="fas fa-spinner fa-spin me-1"></i>Cargando...';
    }
    window.location.href = newUrl;
  } catch (error) {
    console.error("Error en toggleAgrupamiento:", error);
    alert("Error al cambiar vista: " + error.message);
  }
};

function updateButtonAppearance(button, agrupado) {
  if (agrupado) {
    button.classList.remove("btn-outline-dark");
    button.classList.add("btn-dark");
  } else {
    button.classList.remove("btn-dark");
    button.classList.add("btn-outline-dark");
  }
}

window.testAgrupamiento = function () {
  console.log("=== TEST AGRUPAMIENTO ===");
  console.log("URL actual:", window.location.href);
  console.log(
    "Parámetros URL:",
    new URLSearchParams(window.location.search).toString()
  );

  const button = document.getElementById("toggle-agrupamiento");
  console.log("Botón encontrado:", !!button);

  if (button) {
    console.log("Botón onclick:", button.onclick);
    console.log("Botón onclick attr:", button.getAttribute("onclick"));
    console.log("Clases del botón:", button.className);
  }

  console.log(
    "Función toggleAgrupamiento existe:",
    typeof window.toggleAgrupamiento
  );
  console.log("=== FIN TEST ===");
};

window.mostrarEstadisticasFiltro = function () {
  const filas = document.querySelectorAll("#items-table tbody tr");
  const visibles = Array.from(filas).filter(
    (fila) => fila.style.display !== "none"
  ).length;
  const total = filas.length;

  if (visibles < total) {
    DespachoManager.utils.showToast(
      `Mostrando ${visibles} de ${total} items`,
      "info"
    );
  }
};

document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM cargado, inicializando DespachoManager");

  if (typeof DESPACHO_CONFIG !== "undefined") {
    DespachoManager.init(DESPACHO_CONFIG);
  } else {
    console.warn(
      "DESPACHO_CONFIG no definida, usando configuración por defecto"
    );
    DespachoManager.init({
      url_base: window.location.origin + "/",
      debug: true,
    });
  }

  const currentPath = window.location.pathname;

  if (currentPath.includes("main.php")) {
    console.log("Inicializando página principal");
    DespachoManager.mainPage.init();
  } else if (currentPath.includes("ver.php")) {
    console.log("Inicializando página de detalles");
    DespachoManager.detailPage.init();
    const toggleBtn = document.getElementById("toggle-agrupamiento");
    if (toggleBtn) {
      const urlParams = new URLSearchParams(window.location.search);
      const agrupado = urlParams.get("agrupar") === "true";
      updateButtonAppearance(toggleBtn, agrupado);
      if (!toggleBtn.onclick && !toggleBtn.getAttribute("onclick")) {
        console.warn("Botón no tiene onclick, agregando evento");
        toggleBtn.addEventListener("click", window.toggleAgrupamiento);
      }
    }
  }

  if (
    window.location.hostname === "localhost" ||
    window.location.hostname.includes("dev") ||
    window.location.search.includes("debug")
  ) {
    setTimeout(window.testAgrupamiento, 1000);
  }
});

if (typeof module !== "undefined" && module.exports) {
  module.exports = DespachoManager;
}
