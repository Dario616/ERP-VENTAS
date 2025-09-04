const ContableManager = {
  config: null,

  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("ContableManager inicializado", this.config);
  },

  bindEvents: function () {
    this.initBootstrapTooltips();
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

  mainPage: {
    init: function () {
      this.bindStatisticsRefresh();
    },
    bindStatisticsRefresh: function () {
      const btnActualizar = document.getElementById("btn-actualizar");
      if (btnActualizar) {
        btnActualizar.addEventListener("click", function () {
          this.querySelector("i").classList.add("fa-spin");
          setTimeout(() => {
            window.location.reload();
          }, 500);
        });
      }
    },
  },

  indexPage: {
    init: function () {
      this.bindFilterForm();
      this.bindPagination();
    },

    bindFilterForm: function () {
      const filterForm = document.querySelector('form[method="GET"]');
      if (filterForm) {
        const dateInputs = filterForm.querySelectorAll('input[type="date"]');
        dateInputs.forEach((input) => {
          input.addEventListener("change", function () {});
        });
      }
    },
    bindPagination: function () {
      const paginationLinks = document.querySelectorAll(
        ".pagination .page-link"
      );
      paginationLinks.forEach((link) => {
        link.addEventListener("click", function (e) {
          const icon = document.createElement("i");
          icon.className = "fas fa-spinner fa-spin me-1";
          this.prepend(icon);
        });
      });
    },
  },

  viewPage: {
    imagenesAutorizacion: [],
    imagenActualIndex: 0,
    modalBootstrap: null,

    init: function (imagenesAutorizacion = [], imagenesProductos = {}) {
      this.imagenesAutorizacion = imagenesAutorizacion;
      this.initFormValidation();
      this.initImageGallery();
      this.initProductImages(imagenesProductos);
    },
    initFormValidation: function () {
      this.initRechazoForm();
      this.initAprobacionForm();
    },
    initRechazoForm: function () {
      const btnRechazar = document.getElementById("btn-rechazar");
      const btnConfirmarRechazo = document.getElementById(
        "btn-confirmar-rechazo"
      );
      const formRechazo = document.getElementById("form-rechazo");
      const txtDescripcionRechazo = document.getElementById(
        "descripcion_rechazo"
      );

      if (btnRechazar) {
        btnRechazar.addEventListener("click", function () {
          if (!txtDescripcionRechazo.value.trim()) {
            txtDescripcionRechazo.classList.add("is-invalid");
            txtDescripcionRechazo.focus();
            return;
          }
          const rechazoModal = new bootstrap.Modal(
            document.getElementById("confirmarRechazoModal")
          );
          rechazoModal.show();
        });
      }

      if (btnConfirmarRechazo && formRechazo) {
        btnConfirmarRechazo.addEventListener("click", function () {
          formRechazo.submit();
        });
      }

      if (txtDescripcionRechazo) {
        txtDescripcionRechazo.addEventListener("input", function () {
          if (this.value.trim()) {
            this.classList.remove("is-invalid");
          }
        });
      }
    },

    initAprobacionForm: function () {
      const btnAprobar = document.getElementById("btn-aprobar");
      const btnConfirmarAprobacion = document.getElementById(
        "btn-confirmar-aprobacion"
      );
      const formAprobacion = document.getElementById("form-aprobacion");

      if (btnAprobar) {
        btnAprobar.addEventListener("click", function () {
          const aprobacionModal = new bootstrap.Modal(
            document.getElementById("confirmarAprobacionModal")
          );
          aprobacionModal.show();
        });
      }

      if (btnConfirmarAprobacion && formAprobacion) {
        btnConfirmarAprobacion.addEventListener("click", function () {
          formAprobacion.submit();
        });
      }
    },

    initImageGallery: function () {
      if (this.imagenesAutorizacion.length === 0) return;

      const modal = document.getElementById("modalImagenAutorizacion");
      const imgContainer = document.getElementById(
        "imagen-autorizacion-container"
      );
      const nombreImagenModal = document.getElementById("nombre-imagen-modal");
      const descripcionImagenModal = document.getElementById(
        "descripcion-imagen-modal"
      );
      const tituloImagenModal = document.getElementById("titulo-imagen-modal");
      const imagenCounter = document.getElementById("imagen-counter");
      const imagenActualSpan = document.getElementById("imagen-actual");
      const totalImagenesSpan = document.getElementById("total-imagenes");
      const btnAnterior = document.getElementById("btn-imagen-anterior");
      const btnSiguiente = document.getElementById("btn-imagen-siguiente");
      const btnDescargar = document.getElementById("btn-descargar-imagen");

      const mostrarDocumento = (index) => {
        if (this.imagenesAutorizacion.length === 0) {
          imgContainer.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No hay documentos de autorización disponibles
            </div>
          `;
          return;
        }

        const documento = this.imagenesAutorizacion[index];
        this.imagenActualIndex = index;

        const esImagen = documento.tipo_archivo.startsWith("image/");
        const esPDF = documento.tipo_archivo === "application/pdf";

        if (esImagen) {
          imgContainer.innerHTML = `
            <img src="data:${documento.tipo_archivo};base64,${
            documento.base64_imagen
          }" 
                class="img-fluid rounded shadow" 
                style="max-height: 80vh; object-fit: contain;" 
                alt="${documento.nombre_archivo || "Imagen de autorización"}">
          `;
          tituloImagenModal.textContent = "Imagen de Autorización";
        } else if (esPDF) {
          const iframeId = `pdf-iframe-${index}`;
          imgContainer.innerHTML = `
            <div style="width: 100%; height: 80vh; border: 2px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #f8f9fa;">
                <iframe 
                    id="${iframeId}"
                    src="data:${documento.tipo_archivo};base64,${
            documento.base64_imagen
          }#toolbar=1&navpanes=1&scrollbar=1" 
                    style="width: 100%; height: 100%; border: none;"
                    title="${documento.nombre_archivo || "PDF de autorización"}"
                    loading="eager">
                    <p>Su navegador no soporta la visualización de PDFs. 
                       <a href="data:${documento.tipo_archivo};base64,${
            documento.base64_imagen
          }" 
                          target="_blank" class="btn btn-primary">
                          <i class="fas fa-external-link-alt me-2"></i>Abrir PDF en nueva pestaña
                       </a>
                    </p>
                </iframe>
            </div>
            <div class="mt-3">
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <span>Este es un archivo PDF. Puede usar los controles del visor para hacer zoom, navegar páginas, buscar texto, etc.</span>
                </div>
            </div>
          `;
          tituloImagenModal.textContent = "PDF de Autorización";
        } else {
          imgContainer.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Tipo de archivo no soportado para visualización: ${documento.tipo_archivo}
                <br><br>
                <a href="data:${documento.tipo_archivo};base64,${documento.base64_imagen}" 
                   download="${documento.nombre_archivo}" 
                   class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Descargar archivo
                </a>
            </div>
          `;
          tituloImagenModal.textContent = "Documento de Autorización";
        }

        if (nombreImagenModal) {
          nombreImagenModal.textContent =
            documento.nombre_archivo || `Documento ${index + 1}`;
        }

        if (descripcionImagenModal) {
          descripcionImagenModal.innerHTML = documento.descripcion_imagen
            ? `<div class="alert alert-info"><strong>Descripción:</strong> ${documento.descripcion_imagen}</div>`
            : "";
        }

        if (imagenActualSpan) imagenActualSpan.textContent = index + 1;
        if (totalImagenesSpan)
          totalImagenesSpan.textContent = this.imagenesAutorizacion.length;

        if (btnDescargar) {
          btnDescargar.style.display = "inline-block";
          btnDescargar.onclick = function () {
            const link = document.createElement("a");
            link.href = `data:${documento.tipo_archivo};base64,${documento.base64_imagen}`;
            link.download =
              documento.nombre_archivo || `documento_${index + 1}`;
            link.click();
          };
        }

        if (this.imagenesAutorizacion.length > 1) {
          if (imagenCounter) imagenCounter.style.display = "block";
          if (btnAnterior)
            btnAnterior.style.display = index > 0 ? "block" : "none";
          if (btnSiguiente)
            btnSiguiente.style.display =
              index < this.imagenesAutorizacion.length - 1 ? "block" : "none";
        } else {
          if (imagenCounter) imagenCounter.style.display = "none";
          if (btnAnterior) btnAnterior.style.display = "none";
          if (btnSiguiente) btnSiguiente.style.display = "none";
        }
      };

      const limpiarModal = () => {
        const iframes = imgContainer.querySelectorAll("iframe");
        iframes.forEach((iframe) => {
          iframe.src = "about:blank";
          iframe.remove();
        });

        imgContainer.innerHTML = `
          <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>No hay documento de autorización disponible
          </div>
        `;

        if (descripcionImagenModal) descripcionImagenModal.innerHTML = "";
        if (nombreImagenModal) nombreImagenModal.textContent = "";

        if (btnDescargar) btnDescargar.style.display = "none";
        if (imagenCounter) imagenCounter.style.display = "none";
        if (btnAnterior) btnAnterior.style.display = "none";
        if (btnSiguiente) btnSiguiente.style.display = "none";

        setTimeout(() => {
          const backdrops = document.querySelectorAll(".modal-backdrop");
          backdrops.forEach((backdrop) => backdrop.remove());
          document.body.classList.remove("modal-open");
          document.body.style.overflow = "";
          document.body.style.paddingRight = "";
        }, 150);
      };

      const documentosThumbs = document.querySelectorAll(
        ".imagen-autorizacion-thumb"
      );
      documentosThumbs.forEach((thumb) => {
        thumb.addEventListener("click", () => {
          const index = parseInt(thumb.getAttribute("data-imagen-index"));
          this.imagenActualIndex = index;

          if (tituloImagenModal) {
            tituloImagenModal.textContent =
              this.imagenesAutorizacion.length > 1
                ? "Galería de Documentos de Autorización"
                : "Documento de Autorización";
          }

          mostrarDocumento(index);

          if (!this.modalBootstrap) {
            this.modalBootstrap = new bootstrap.Modal(modal, {
              backdrop: true,
              keyboard: true,
              focus: true,
            });
          }

          this.modalBootstrap.show();
        });
      });

      if (modal) {
        modal.addEventListener("hidden.bs.modal", () => {
          limpiarModal();
          this.modalBootstrap = null;
        });

        modal.addEventListener("hide.bs.modal", () => {
          const iframes = imgContainer.querySelectorAll("iframe");
          iframes.forEach((iframe) => {
            iframe.src = "about:blank";
          });
        });

        const btnCerrarModal = modal.querySelector(
          '.btn-close, [data-bs-dismiss="modal"]'
        );
        if (btnCerrarModal) {
          btnCerrarModal.addEventListener("click", () => {
            if (this.modalBootstrap) {
              this.modalBootstrap.hide();
            }
          });
        }

        const btnCerrarFooter = modal.querySelector(
          ".modal-footer .btn-secondary"
        );
        if (btnCerrarFooter) {
          btnCerrarFooter.addEventListener("click", () => {
            if (this.modalBootstrap) {
              this.modalBootstrap.hide();
            }
          });
        }
      }

      if (btnAnterior) {
        btnAnterior.addEventListener("click", () => {
          if (this.imagenActualIndex > 0) {
            mostrarDocumento(this.imagenActualIndex - 1);
          }
        });
      }

      if (btnSiguiente) {
        btnSiguiente.addEventListener("click", () => {
          if (this.imagenActualIndex < this.imagenesAutorizacion.length - 1) {
            mostrarDocumento(this.imagenActualIndex + 1);
          }
        });
      }

      if (modal) {
        modal.addEventListener("keydown", (e) => {
          if (e.key === "Escape") {
            if (this.modalBootstrap) {
              this.modalBootstrap.hide();
            }
            return;
          }

          if (e.key === "ArrowLeft" && this.imagenActualIndex > 0) {
            mostrarDocumento(this.imagenActualIndex - 1);
          } else if (
            e.key === "ArrowRight" &&
            this.imagenActualIndex < this.imagenesAutorizacion.length - 1
          ) {
            mostrarDocumento(this.imagenActualIndex + 1);
          }
        });

        modal.addEventListener("shown.bs.modal", () => {
          modal.focus();
        });
      }

      const limpiarBackdropsRemanentes = () => {
        const backdrops = document.querySelectorAll(".modal-backdrop");
        if (backdrops.length > 0) {
          backdrops.forEach((backdrop) => {
            backdrop.style.opacity = "0";
            setTimeout(() => backdrop.remove(), 150);
          });
          document.body.classList.remove("modal-open");
          document.body.style.overflow = "";
          document.body.style.paddingRight = "";
        }
      };

      document.addEventListener("click", function (e) {
        if (e.target.classList.contains("modal-backdrop")) {
          setTimeout(limpiarBackdropsRemanentes, 300);
        }
      });

      limpiarBackdropsRemanentes();
    },

    initProductImages: function (imagenesProductos) {
      const nombresProductos = {};

      const productRows = document.querySelectorAll("tbody tr");
      productRows.forEach((row) => {
        const btn = row.querySelector(".ver-imagen-producto");
        if (btn) {
          const idProducto = btn.getAttribute("data-id-producto");
          const descripcion = row.querySelector("td:nth-child(2)");
          if (descripcion) {
            nombresProductos[idProducto] = descripcion.textContent.trim();
          }
        }
      });

      const botonesVerImagen = document.querySelectorAll(
        ".ver-imagen-producto"
      );
      botonesVerImagen.forEach((btn) => {
        btn.addEventListener("click", () => {
          const idProducto = btn.getAttribute("data-id-producto");
          const nombreProducto = nombresProductos[idProducto] || "Producto";
          const modal = document.getElementById("modalImagenProducto");

          if (modal) {
            const productoNombre = document.getElementById("producto-nombre");
            const imgContainer = document.getElementById(
              "producto-imagen-container"
            );

            if (productoNombre) {
              productoNombre.textContent = nombreProducto;
            }

            if (imgContainer) {
              if (imagenesProductos[idProducto]) {
                const imgData = imagenesProductos[idProducto];
                imgContainer.innerHTML = `
                  <img src="data:${imgData.tipo};base64,${imgData.imagen}" 
                      class="img-fluid rounded shadow" 
                      style="max-height: 70vh; object-fit: contain;" 
                      alt="${nombreProducto}">
                `;
              } else {
                imgContainer.innerHTML = `
                  <div class="alert alert-warning">
                      <i class="fas fa-exclamation-triangle me-2"></i>
                      No hay imagen disponible para este producto
                  </div>
                `;
              }
            }

            const modalBootstrap = new bootstrap.Modal(modal);
            modalBootstrap.show();
          }
        });
      });
    },
  },

  historialPage: {
    init: function () {
      this.initModalDetalles();
      this.bindFilterForm();
    },

    initModalDetalles: function () {
      const modal = document.getElementById("modalDetalles");
      if (modal) {
        modal.addEventListener("show.bs.modal", (event) => {
          const button = event.relatedTarget;
          const data = this.extractDataFromButton(button);
          this.populateModal(data);
        });
      }
    },

    extractDataFromButton: function (button) {
      return {
        idVenta: button.getAttribute("data-venta"),
        cliente: button.getAttribute("data-cliente"),
        usuario: button.getAttribute("data-usuario"),
        sector: button.getAttribute("data-sector"),
        accion: button.getAttribute("data-accion"),
        fecha: button.getAttribute("data-fecha"),
        estado: button.getAttribute("data-estado"),
        observaciones: button.getAttribute("data-observaciones"),
      };
    },

    populateModal: function (data) {
      const elements = {
        "modal-id-venta": data.idVenta,
        "modal-cliente": data.cliente,
        "modal-usuario": data.usuario,
        "modal-accion": data.accion,
        "modal-fecha": data.fecha,
        "modal-estado": data.estado,
      };

      Object.keys(elements).forEach((id) => {
        const element = document.getElementById(id);
        if (element) {
          element.textContent = elements[id];
        }
      });

      const observacionesElement = document.getElementById(
        "modal-observaciones"
      );
      if (observacionesElement) {
        observacionesElement.innerHTML = data.observaciones
          ? data.observaciones.replace(/\n/g, "<br>")
          : "<em>Sin observaciones</em>";
      }

      const verVentaLink = document.getElementById("modal-ver-venta");
      if (verVentaLink && ContableManager.config) {
        verVentaLink.href = `${ContableManager.config.urlBase}secciones/contable/verventas.php?id=${data.idVenta}`;
      }
    },

    bindFilterForm: function () {
      const filterForm = document.querySelector('form[method="GET"]');
      if (filterForm) {
        const dateInputs = filterForm.querySelectorAll('input[type="date"]');
        dateInputs.forEach((input) => {
          input.addEventListener("change", function () {
            const fechaDesde = document.querySelector(
              'input[name="fecha_desde"]'
            );
            const fechaHasta = document.querySelector(
              'input[name="fecha_hasta"]'
            );

            if (
              fechaDesde &&
              fechaHasta &&
              fechaDesde.value &&
              fechaHasta.value
            ) {
              if (fechaDesde.value > fechaHasta.value) {
                alert("La fecha desde no puede ser mayor que la fecha hasta");
                this.value = "";
              }
            }
          });
        });
      }
    },
  },

  utils: {
    showToast: function (message, type = "info") {
      console.log(`[${type.toUpperCase()}] ${message}`);
    },

    formatDate: function (dateString) {
      const date = new Date(dateString);
      return (
        date.toLocaleDateString("es-PY") +
        " " +
        date.toLocaleTimeString("es-PY")
      );
    },

    formatCurrency: function (amount, currency) {
      const symbol = currency === "Dólares" ? "U$D " : "₲ ";
      return symbol + new Intl.NumberFormat("es-PY").format(amount);
    },

    validateForm: function (form) {
      const requiredFields = form.querySelectorAll("[required]");
      let isValid = true;

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          field.classList.add("is-invalid");
          isValid = false;
        } else {
          field.classList.remove("is-invalid");
        }
      });

      return isValid;
    },
  },
};

document.addEventListener("DOMContentLoaded", function () {
  if (typeof CONTABLE_CONFIG !== "undefined") {
    ContableManager.init(CONTABLE_CONFIG);
  }

  const currentPath = window.location.pathname;

  if (currentPath.includes("main.php")) {
    ContableManager.mainPage.init();
  } else if (
    currentPath.includes("index.php") ||
    currentPath.endsWith("/contable/")
  ) {
    ContableManager.indexPage.init();
  } else if (currentPath.includes("ver.php")) {
    if (
      typeof IMAGENES_AUTORIZACION !== "undefined" &&
      typeof IMAGENES_PRODUCTOS !== "undefined"
    ) {
      ContableManager.viewPage.init(IMAGENES_AUTORIZACION, IMAGENES_PRODUCTOS);
    } else {
      ContableManager.viewPage.init();
    }
  } else if (currentPath.includes("historial.php")) {
    ContableManager.historialPage.init();
  }
});

if (typeof module !== "undefined" && module.exports) {
  module.exports = ContableManager;
}
