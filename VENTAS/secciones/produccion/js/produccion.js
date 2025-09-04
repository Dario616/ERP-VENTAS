const ProduccionManager = {
  config: {
    urlBase: "",
    usuario: "",
    esAdmin: false,
    debug: false,
    rol: "0",
  },

  init: function (config) {
    this.config = { ...this.config, ...config };
    this.bindEvents();
    this.initTooltips();

    if (this.config.debug) {
      console.log("ProduccionManager inicializado:", this.config);
    }
  },

  bindEvents: function () {
    $(document).on(
      "click",
      ".btn-ver-detalles",
      this.handleVerDetalles.bind(this)
    );
    $(document).on(
      "click",
      ".btn-actualizar-stock",
      this.handleActualizarStock.bind(this)
    );

    // Modales
    $(document).on(
      "show.bs.modal",
      "#modalDetallesProduccion",
      this.handleModalDetalles.bind(this)
    );
    $(document).on(
      "hide.bs.modal",
      "#modalDetallesProduccion",
      this.handleModalClose.bind(this)
    );
  },

  handleVerDetalles: function (e) {
    e.preventDefault();

    const btn = $(e.target).closest(".btn-ver-detalles");
    const idOrden = btn.data("id-orden");
    const tipoProducto = btn.data("tipo-producto");

    this.cargarDetallesOrden(idOrden, tipoProducto);
  },

  cargarDetallesOrden: function (idOrden, tipoProducto) {
    $("#modalDetallesProduccion").modal("show");

    const modalBody = $("#modalDetallesProduccion .modal-body");
    modalBody.html(this.getLoadingHTML());

    const btnPDF = $("#btnGenerarPDF");
    const archivoPDF = this.obtenerArchivoPDF(tipoProducto);
    btnPDF.attr(
      "onclick",
      `window.open('${this.config.urlBase}pdf/${archivoPDF}?id_orden=${idOrden}', '_blank')`
    );
    btnPDF.html(
      `<i class="${this.obtenerIconoTipo(
        tipoProducto
      )} me-1"></i>Generar PDF ${tipoProducto}`
    );

    $.ajax({
      url: `${this.config.urlBase}secciones/produccion/obtener_detalles_orden.php`,
      method: "GET",
      data: { id_orden: idOrden },
      success: function (response) {
        modalBody.html(response);
        this.initTooltipsModal();
      }.bind(this),
      error: function () {
        modalBody.html(
          this.getErrorHTML("Error al cargar detalles de la orden")
        );
      }.bind(this),
    });
  },

  handleActualizarStock: function (e) {
    e.preventDefault();

    const btn = $(e.target).closest(".btn-actualizar-stock");
    const idOrden = btn.data("id-orden");

    this.actualizarStockReal(idOrden, btn);
  },

  actualizarStockReal: function (idOrden, btn) {
    const originalText = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...');
    btn.prop("disabled", true);

    $.ajax({
      url: `${this.config.urlBase}secciones/produccion/controllers/ProduccionController.php`,
      method: "GET",
      data: {
        action: "obtener_stock_real",
        id_orden: idOrden,
      },
      success: function (response) {
        if (response.success) {
          const stockElement = btn.closest("tr").find(".stock-real");
          stockElement.text(response.stock_real.toFixed(2));

          this.mostrarExito("Stock actualizado correctamente");
        } else {
          this.mostrarError(response.error || "Error al actualizar stock");
        }
      }.bind(this),
      error: function () {
        this.mostrarError("Error de conexión al actualizar stock");
      }.bind(this),
      complete: function () {
        btn.html(originalText);
        btn.prop("disabled", false);
      },
    });
  },

  handleModalDetalles: function (e) {
    const modal = $(e.target);
  },

  handleModalClose: function (e) {
    const modal = $(e.target);
    modal.find(".modal-body").html("");
  },

  obtenerArchivoPDF: function (tipoProducto) {
    const tipo = tipoProducto.toUpperCase();
    switch (tipo) {
      case "SPUNLACE":
        return "produccion_spunlace.php";
      case "TOALLITAS":
        return "producciontoallitas.php";
      case "LAMINADORA":
        return "produccion.php";
      case "PAÑOS":
        return "produccionpanos.php";
      case "TNT":
      default:
        return "produccion.php";
    }
  },

  obtenerIconoTipo: function (tipoProducto) {
    const tipo = tipoProducto.toUpperCase();
    switch (tipo) {
      case "TNT":
        return "fas fa-scroll";
      case "SPUNLACE":
        return "fas fa-swatchbook";
      case "LAMINADORA":
        return "fas fa-layer-group";
      case "TOALLITAS":
        return "fas fa-soap";
      case "PAÑOS":
        return "fas fa-tshirt";
      default:
        return "fas fa-box";
    }
  },

  mostrarError: function (mensaje) {
    this.mostrarToast(mensaje, "error");
  },

  mostrarExito: function (mensaje) {
    this.mostrarToast(mensaje, "success");
  },

  mostrarToast: function (mensaje, tipo = "info") {
    const alertClass =
      tipo === "error"
        ? "alert-danger"
        : tipo === "success"
        ? "alert-success"
        : "alert-info";
    const icon =
      tipo === "error"
        ? "fas fa-exclamation-triangle"
        : tipo === "success"
        ? "fas fa-check-circle"
        : "fas fa-info-circle";

    const toast = $(`
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 10000; min-width: 300px;" role="alert">
                <i class="${icon} me-2"></i>${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);

    $("body").append(toast);
    setTimeout(() => {
      toast.alert("close");
    }, 5000);
  },

  getLoadingHTML: function () {
    return `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando detalles...</p>
            </div>
        `;
  },

  getErrorHTML: function (mensaje) {
    return `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${mensaje}
            </div>
        `;
  },

  initTooltips: function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
  },

  initTooltipsModal: function () {
    $('#modalDetallesProduccion [data-bs-toggle="tooltip"]').tooltip();
  },

  completados: {
    init: function () {
      this.bindCompletadosEvents();
    },

    bindCompletadosEvents: function () {
      $(document).on("click", ".btn-ver-stock", function (e) {
        e.preventDefault();
        const idOrden = $(this).data("id-orden");
        ProduccionManager.mostrarDetallesStock(idOrden);
      });
    },
  },

  generarPDF: function (idOrden, tipoProducto) {
    const archivoPDF = this.obtenerArchivoPDF(tipoProducto);
    const url = `${this.config.urlBase}pdf/${archivoPDF}?id_orden=${idOrden}`;
    window.open(url, "_blank");
  },
};

$(document).ready(function () {
  if (typeof PRODUCCION_CONFIG !== "undefined") {
    ProduccionManager.init(PRODUCCION_CONFIG);
  }

  if ($("body").hasClass("page-completados")) {
    ProduccionManager.completados.init();
  }

  if ($("body").hasClass("page-ordenes")) {
    ProduccionManager.ordenes.init();
  }
});

window.ProduccionManager = ProduccionManager;
