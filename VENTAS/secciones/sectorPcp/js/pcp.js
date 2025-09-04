/**
 * PCP Management System - JavaScript Module (Versión Mejorada con Paquetes)
 * Manejo de funcionalidades para la gestión del sector PCP con lógica de paquetes
 */

// Objeto principal para manejar todas las funcionalidades de PCP
const PcpManager = {
  config: null,

  /**
   * Inicializar el módulo con la configuración
   */
  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("PcpManager inicializado", this.config);
  },

  /**
   * Vincular eventos generales
   */
  bindEvents: function () {
    this.initBootstrapTooltips();
    this.initDatepickers();
  },

  /**
   * Inicializar tooltips de Bootstrap
   */
  initBootstrapTooltips: function () {
    if (typeof bootstrap !== "undefined") {
      const tooltipTriggerList = document.querySelectorAll(
        '[data-bs-toggle="tooltip"]'
      );
      [...tooltipTriggerList].map((el) => new bootstrap.Tooltip(el));
    }
  },

  /**
   * Inicializar datepickers
   */
  initDatepickers: function () {
    const fechaDesde = document.getElementById("fecha_desde");
    const fechaHasta = document.getElementById("fecha_hasta");

    if (fechaDesde && fechaHasta) {
      if (!fechaDesde.value) {
        const primerDia = new Date();
        primerDia.setDate(1);
        fechaDesde.value = primerDia.toISOString().split("T")[0];
      }

      if (!fechaHasta.value) {
        fechaHasta.value = new Date().toISOString().split("T")[0];
      }
    }
  },

  /**
   * Módulo para la página de ventas aprobadas
   */
  ventasAprobadasPage: {
    init: function () {
      this.bindFilterEvents();
      this.bindPaginationEvents();
    },

    bindFilterEvents: function () {
      const filterForm = document.querySelector('form[method="GET"]');
      if (!filterForm) return;

      let timeout;
      const inputs = filterForm.querySelectorAll('input[type="text"]');

      inputs.forEach((input) => {
        input.addEventListener("input", function () {
          clearTimeout(timeout);
          timeout = setTimeout(() => {
            if (input.value.length >= 2 || input.value.length === 0) {
              // Auto-submit para filtros de texto está comentado intencionalmente
              // filterForm.submit();
            }
          }, 1000);
        });
      });
    },

    bindPaginationEvents: function () {
      const paginationLinks = document.querySelectorAll(
        ".pagination .page-link"
      );
      paginationLinks.forEach((link) => {
        link.addEventListener("click", function () {
          const icon = this.querySelector("i");
          if (icon) {
            icon.className = "fas fa-spinner fa-spin";
          }
        });
      });
    },
  },

  /**
   * Módulo para la página de procesamiento de venta (MEJORADO)
   */
  procesarVentaPage: {
    init: function () {
      this.bindFormEvents();
      this.bindModalEvents();
      this.initImageGallery();
      this.initProductImages();
      this.initStockCalculations();
      this.initReservasAutomaticas(); // AGREGAR esta línea
    },

    bindFormEvents: function () {
      // Formulario de producción
      const formProduccion = document.getElementById("formProduccion");
      if (formProduccion) {
        formProduccion.addEventListener("submit", (e) => {
          e.preventDefault();
          this.showConfirmacionProduccion(formProduccion);
        });
      }

      // Formulario de stock a expedición
      const formStockExpedicion = document.getElementById(
        "formStockExpedicion"
      );
      if (formStockExpedicion) {
        formStockExpedicion.addEventListener("submit", (e) => {
          e.preventDefault();
          if (this.validateStockForm(e)) {
            this.showConfirmacionExpedicion(formStockExpedicion);
          }
        });
      }

      // Formulario de devolución
      const formDevolucion = document.getElementById("formDevolucion");
      if (formDevolucion) {
        formDevolucion.addEventListener("submit", (e) => {
          e.preventDefault();
          if (this.validateReturnForm(e)) {
            this.showConfirmacionDevolucion(formDevolucion);
          }
        });
      }
    },

    /**
     * NUEVO: Inicializar calculadora de paquetes
     */
    initPaquetesCalculator: function () {
      // Event listeners para inputs de bobinas
      const inputsBobinas = document.querySelectorAll(
        'input[name^="cantidad_bobinas"]'
      );

      inputsBobinas.forEach((input) => {
        input.addEventListener("input", (e) => {
          this.actualizarCalculoPaquetes(e.target);
        });

        input.addEventListener("blur", (e) => {
          this.validarStockDisponible(e.target);
        });
      });

      // Event listeners para inputs de paquetes (variantes específicas)
      const inputsPaquetes = document.querySelectorAll(
        'input[name^="cantidad_paquetes"]'
      );

      inputsPaquetes.forEach((input) => {
        input.addEventListener("input", (e) => {
          this.actualizarCalculoBobinas(e.target);
        });
      });
    },

    /**
     * Mostrar confirmación de producción
     */
    showConfirmacionProduccion: function (form) {
      if (!this.validarProduccion()) return;

      const productos = this.getProductosProduccion();
      const modalHtml = this.createConfirmationModal(
        "modalConfirmarProduccion",
        "Confirmar Envío a Producción",
        "bg-primary",
        "fa-industry",
        productos,
        form.querySelector("#observaciones_produccion").value,
        "Enviar a Producción",
        "confirmarProduccion",
        "Esta acción enviará automáticamente todos los productos disponibles a producción."
      );

      this.showModal(
        modalHtml,
        "modalConfirmarProduccion",
        "confirmarProduccion",
        form
      );
    },

    /**
     * MEJORADO: Mostrar confirmación de expedición con información de paquetes
     */
    showConfirmacionExpedicion: function (form) {
      const productos = this.getProductosExpedicionMejorado(); // Método mejorado
      const modalHtml = this.createExpedicionModalMejorado(productos, form);

      this.showModal(
        modalHtml,
        "modalConfirmarExpedicion",
        "confirmarExpedicion",
        form
      );
    },

    /**
     * Mostrar confirmación de devolución
     */
    showConfirmacionDevolucion: function (form) {
      const motivo = form.querySelector("#motivo_devolucion").value;
      const modalHtml = `
        <div class="modal fade" id="modalConfirmarDevolucion" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                  <i class="fas fa-undo me-2"></i>Confirmar Devolución a Contabilidad
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-warning">
                  <i class="fas fa-exclamation-triangle me-2"></i>
                  <strong>¿Está seguro de devolver esta venta a contabilidad?</strong>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-bold">Motivo de la devolución:</label>
                  <div class="border rounded p-3 bg-light">
                    ${motivo.replace(/\n/g, "<br>")}
                  </div>
                </div>
                <div class="alert alert-info">
                  <i class="fas fa-info-circle me-2"></i>
                  <strong>Importante:</strong> La venta requerirá nueva aprobación del contador.
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-warning" id="confirmarDevolucion">
                  <i class="fas fa-undo me-2"></i>Confirmar Devolución
                </button>
              </div>
            </div>
          </div>
        </div>
      `;

      this.showModal(
        modalHtml,
        "modalConfirmarDevolucion",
        "confirmarDevolucion",
        form
      );
    },

    /**
     * Crear modal de confirmación genérico
     */
    createConfirmationModal: function (
      id,
      title,
      headerClass,
      icon,
      productos,
      observaciones,
      buttonText,
      buttonId,
      warning
    ) {
      return `
        <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header ${headerClass} text-white">
                <h5 class="modal-title">
                  <i class="fas ${icon} me-2"></i>${title}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  <i class="fas fa-info-circle me-2"></i>
                  <strong>¿Está seguro de realizar esta acción?</strong>
                </div>
                <h6 class="fw-bold mb-3">Productos:</h6>
                <div class="table-responsive">
                  <table class="table table-sm table-striped">
                    <thead class="table-primary">
                      <tr>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th class="text-end">Cantidad</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${productos
                        .map(
                          (p) => `
                        <tr>
                          <td>${p.nombre}</td>
                          <td><span class="badge bg-secondary">${p.tipo}</span></td>
                          <td class="text-end"><strong>${p.cantidad}</strong></td>
                        </tr>
                      `
                        )
                        .join("")}
                    </tbody>
                  </table>
                </div>
                <div class="mt-3">
                  <label class="form-label fw-bold">Observaciones:</label>
                  <p class="border rounded p-2 bg-light">${
                    observaciones || "Sin observaciones"
                  }</p>
                </div>
                <div class="alert alert-warning">
                  <i class="fas fa-exclamation-triangle me-2"></i>
                  <strong>Importante:</strong> ${warning}
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="${buttonId}">
                  <i class="fas ${icon} me-2"></i>${buttonText}
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
    },

    /**
     * VERSIÓN SIMPLIFICADA: Manejo de reservas automáticas
     */
    initReservasAutomaticas: function () {
      // Variables globales
      window.productosConCantidad = new Set();
      window.validacionesCompletadas = new Map();

      // Función global para actualizar cálculos
      window.actualizarCalculoPaquetes = (productoIndex) => {
        const input = document.querySelector(
          `input[data-producto-index="${productoIndex}"]`
        );
        if (!input) return;

        const cantidad = parseInt(input.value) || 0;
        const nombreProducto = input.getAttribute("data-nombre-producto");
        const esUnidades = input.getAttribute("data-es-unidades") === "true";
        const bobinasPorPaquete =
          parseInt(input.getAttribute("data-bobinas-por-paquete")) || 1;

        // Elementos de la interfaz
        const infoContainer = document.getElementById(
          `info-paquetes-${productoIndex}`
        );
        const mensajeValidacion = document.getElementById(
          `mensaje-validacion-${productoIndex}`
        );

        if (cantidad > 0) {
          if (!esUnidades) {
            // Calcular paquetes para productos con bobinas
            const paquetesNecesarios = Math.ceil(cantidad / bobinasPorPaquete);
            const bobinasTotales = paquetesNecesarios * bobinasPorPaquete;
            const excedente = bobinasTotales - cantidad;

            // Actualizar interfaz
            const paquetesSpan = document.getElementById(
              `paquetes-necesarios-${productoIndex}`
            );
            const bobinasTotalesSpan = document.getElementById(
              `bobinas-totales-${productoIndex}`
            );
            const excedenteSpan = document.getElementById(
              `excedente-bobinas-${productoIndex}`
            );
            const excedenteContainer = document.getElementById(
              `excedente-container-${productoIndex}`
            );

            if (paquetesSpan) paquetesSpan.textContent = paquetesNecesarios;
            if (bobinasTotalesSpan)
              bobinasTotalesSpan.textContent = bobinasTotales;
            if (excedenteSpan) excedenteSpan.textContent = excedente;
            if (excedenteContainer) {
              excedenteContainer.className = `fw-bold ${
                excedente > 0 ? "text-warning" : "text-success"
              }`;
            }

            if (infoContainer) infoContainer.style.display = "block";

            // Validar con la API
            this.validarStockAPI(
              productoIndex,
              nombreProducto,
              cantidad,
              input,
              mensajeValidacion
            );
          } else {
            // Para productos en unidades
            input.classList.add("is-valid");
            if (mensajeValidacion) {
              mensajeValidacion.innerHTML = `
                        <div class="alert alert-success alert-sm mt-2">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong>Listo:</strong> ${cantidad} unidades seleccionadas
                        </div>
                    `;
            }
            window.validacionesCompletadas.set(productoIndex, true);
          }

          window.productosConCantidad.add(productoIndex);
        } else {
          // Limpiar cuando no hay cantidad
          if (infoContainer) infoContainer.style.display = "none";
          if (mensajeValidacion) mensajeValidacion.innerHTML = "";
          window.productosConCantidad.delete(productoIndex);
          window.validacionesCompletadas.delete(productoIndex);
          input.classList.remove("is-valid", "is-invalid");
        }

        this.actualizarContadoresReservas();
      };

      // Inicializar contadores
      this.actualizarContadoresReservas();
    },

    /**
     * Validar stock mediante API
     */
    validarStockAPI: function (
      productoIndex,
      nombreProducto,
      cantidad,
      input,
      mensajeContainer
    ) {
      const url = `${
        window.location.pathname
      }?action=calcular_paquetes_necesarios&producto=${encodeURIComponent(
        nombreProducto
      )}&bobinas=${cantidad}`;

      if (mensajeContainer) {
        mensajeContainer.innerHTML = `
            <div class="alert alert-info alert-sm mt-2">
                <i class="fas fa-spinner fa-spin me-1"></i>
                <strong>Validando...</strong> Verificando disponibilidad
            </div>
        `;
      }

      fetch(url)
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.disponible) {
            input.classList.remove("is-invalid");
            input.classList.add("is-valid");
            window.validacionesCompletadas.set(productoIndex, true);

            if (mensajeContainer) {
              const alertClass =
                data.bobinas_excedente > 0 ? "alert-warning" : "alert-success";
              const mensaje =
                data.bobinas_excedente > 0
                  ? `${data.mensaje}<br><small><strong>⚠️ Excedente:</strong> ${data.bobinas_excedente} bobinas</small>`
                  : data.mensaje;

              mensajeContainer.innerHTML = `
                        <div class="alert ${alertClass} alert-sm mt-2">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong>Disponible:</strong> ${mensaje}
                        </div>
                    `;
            }
          } else {
            input.classList.remove("is-valid");
            input.classList.add("is-invalid");
            window.validacionesCompletadas.set(productoIndex, false);

            if (mensajeContainer) {
              mensajeContainer.innerHTML = `
                        <div class="alert alert-danger alert-sm mt-2">
                            <i class="fas fa-times-circle me-1"></i>
                            <strong>Stock insuficiente:</strong> ${
                              data.error || data.mensaje
                            }
                        </div>
                    `;
            }
          }
          this.actualizarContadoresReservas();
        })
        .catch((error) => {
          console.warn("Error validando stock:", error);
          // En caso de error, asumir válido para no bloquear
          input.classList.add("is-valid");
          window.validacionesCompletadas.set(productoIndex, true);
          if (mensajeContainer) {
            mensajeContainer.innerHTML = `
                    <div class="alert alert-warning alert-sm mt-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Validación offline:</strong> Verificar manualmente
                    </div>
                `;
          }
          this.actualizarContadoresReservas();
        });
    },

    /**
     * Actualizar contadores de la interfaz
     */
    actualizarContadoresReservas: function () {
      const contadorHeader = document.getElementById(
        "contador-productos-seleccionados"
      );
      const contadorBoton = document.getElementById("contador-boton");
      const btnCrear = document.getElementById("btnCrearReservas");

      const total = window.productosConCantidad.size;
      const hayValidacionesPendientes = Array.from(
        window.productosConCantidad
      ).some(
        (index) =>
          !window.validacionesCompletadas.has(index) ||
          window.validacionesCompletadas.get(index) === false
      );

      if (contadorHeader) contadorHeader.textContent = `${total} productos`;
      if (contadorBoton) contadorBoton.textContent = `${total} productos`;

      if (btnCrear) {
        btnCrear.disabled = total === 0 || hayValidacionesPendientes;

        if (total === 0) {
          btnCrear.innerHTML =
            '<i class="fas fa-shipping-fast me-2"></i>Crear Reservas Automáticas <span class="badge bg-light text-dark ms-2">0 productos</span>';
        } else if (hayValidacionesPendientes) {
          btnCrear.innerHTML =
            '<i class="fas fa-clock me-2"></i>Validando Stock... <span class="badge bg-warning text-dark ms-2">Espere</span>';
        } else {
          btnCrear.innerHTML = `<i class="fas fa-shipping-fast me-2"></i>Crear Reservas Automáticas <span class="badge bg-light text-dark ms-2">${total} productos</span>`;
        }
      }
    },
    /**
     * Mostrar modal y configurar eventos
     */
    showModal: function (modalHtml, modalId, buttonId, form) {
      // Remover modal anterior si existe
      const existingModal = document.getElementById(modalId);
      if (existingModal) existingModal.remove();

      document.body.insertAdjacentHTML("beforeend", modalHtml);

      const modal = new bootstrap.Modal(document.getElementById(modalId));
      modal.show();

      document.getElementById(buttonId).addEventListener("click", () => {
        modal.hide();
        form.submit();
      });
    },

    /**
     * Obtener productos para producción
     */
    getProductosProduccion: function () {
      const productos = [];
      const inputs = document.querySelectorAll(
        'input[name^="cantidad_produccion"]'
      );

      inputs.forEach((input) => {
        const cantidad = parseFloat(input.value) || 0;
        if (cantidad > 0) {
          const cardBody = input.closest(".card-body");
          const nombre = cardBody
            .querySelector("h6")
            .textContent.trim()
            .split("\n")[0];
          const tipoSpan = cardBody.querySelector(".producto-info-badge");
          const tipo = tipoSpan ? tipoSpan.textContent.trim() : "N/A";

          productos.push({ nombre, tipo, cantidad });
        }
      });

      return productos;
    },

    /**
     * Validaciones
     */
    validateReturnForm: function (e) {
      const motivo = document.getElementById("motivo_devolucion");
      if (!motivo || motivo.value.trim().length < 5) {
        e.preventDefault();
        alert("El motivo de devolución debe tener al menos 5 caracteres.");
        motivo?.focus();
        return false;
      }
      return true;
    },

    /**
     * MEJORADO: Validar formulario de stock con lógica de paquetes
     */
    validateStockForm: function (e) {
      const stockInputs = document.querySelectorAll(
        'input[name^="cantidad_bobinas"], input[name^="cantidad_paquetes"]'
      );
      let hasValidInput = false;
      let errorMessages = [];

      stockInputs.forEach((input) => {
        const cantidad = parseInt(input.value) || 0;
        const nombreProducto = input.getAttribute("data-nombre-producto");

        if (cantidad > 0) {
          hasValidInput = true;

          // Validar si tiene clase de error
          if (input.classList.contains("is-invalid")) {
            errorMessages.push(`${nombreProducto}: Stock insuficiente`);
          }
        }
      });

      if (!hasValidInput) {
        alert(
          "Debe especificar al menos una cantidad para enviar a expedición."
        );
        return false;
      }

      if (errorMessages.length > 0) {
        alert("Errores encontrados:\n" + errorMessages.join("\n"));
        return false;
      }

      return true;
    },

    validarProduccion: function () {
      const productosDisponibles = document.querySelectorAll(
        'input[name^="cantidad_produccion"]'
      );
      let hayProduccion = false;

      productosDisponibles.forEach((input) => {
        const cantProd = parseFloat(input.value) || 0;
        if (cantProd > 0) {
          hayProduccion = true;
        }
      });

      if (!hayProduccion) {
        alert("No hay productos disponibles para enviar a producción.");
        return false;
      }

      return true;
    },

    /**
     * Inicializar galería de imágenes
     */
    initImageGallery: function () {
      const imagenesAutorizacion = window.imagenesAutorizacionData || [];
      let imagenActualIndex = 0;

      const modal = document.getElementById("modalImagenAutorizacion");
      if (!modal || imagenesAutorizacion.length === 0) return;

      const elements = {
        imgContainer: document.getElementById("imagen-autorizacion-container"),
        nombreModal: document.getElementById("nombre-imagen-modal"),
        descripcionModal: document.getElementById("descripcion-imagen-modal"),
        tituloModal: document.getElementById("titulo-imagen-modal"),
        counter: document.getElementById("imagen-counter"),
        actualSpan: document.getElementById("imagen-actual"),
        totalSpan: document.getElementById("total-imagenes"),
        btnAnterior: document.getElementById("btn-imagen-anterior"),
        btnSiguiente: document.getElementById("btn-imagen-siguiente"),
        btnDescargar: document.getElementById("btn-descargar-imagen"),
      };

      let modalBootstrap = null;

      const mostrarDocumento = (index) => {
        const documento = imagenesAutorizacion[index];
        imagenActualIndex = index;

        const esImagen = documento.tipo_archivo.startsWith("image/");
        const esPDF = documento.tipo_archivo === "application/pdf";

        if (esImagen) {
          elements.imgContainer.innerHTML = `
            <img src="data:${documento.tipo_archivo};base64,${
            documento.base64_imagen
          }" 
                class="img-fluid rounded shadow" 
                style="max-height: 80vh; object-fit: contain;" 
                alt="${documento.nombre_archivo || "Imagen de autorización"}">
          `;
          elements.tituloModal.textContent = "Imagen de Autorización";
        } else if (esPDF) {
          elements.imgContainer.innerHTML = `
            <div style="width: 100%; height: 80vh; border: 2px solid #dee2e6; border-radius: 8px; overflow: hidden;">
              <iframe 
                src="data:${documento.tipo_archivo};base64,${
            documento.base64_imagen
          }#toolbar=1" 
                style="width: 100%; height: 100%; border: none;"
                title="${documento.nombre_archivo || "PDF de autorización"}">
              </iframe>
            </div>
          `;
          elements.tituloModal.textContent = "PDF de Autorización";
        }

        elements.nombreModal.textContent =
          documento.nombre_archivo || `Documento ${index + 1}`;
        elements.descripcionModal.innerHTML = documento.descripcion_imagen
          ? `<div class="alert alert-info"><strong>Descripción:</strong> ${documento.descripcion_imagen}</div>`
          : "";

        elements.actualSpan.textContent = index + 1;
        elements.totalSpan.textContent = imagenesAutorizacion.length;

        elements.btnDescargar.onclick = () => {
          const link = document.createElement("a");
          link.href = `data:${documento.tipo_archivo};base64,${documento.base64_imagen}`;
          link.download = documento.nombre_archivo || `documento_${index + 1}`;
          link.click();
        };

        // Mostrar/ocultar controles de navegación
        if (imagenesAutorizacion.length > 1) {
          elements.counter.style.display = "block";
          elements.btnAnterior.style.display = index > 0 ? "block" : "none";
          elements.btnSiguiente.style.display =
            index < imagenesAutorizacion.length - 1 ? "block" : "none";
        }
      };

      // Event listeners para thumbnails
      document
        .querySelectorAll(".imagen-autorizacion-thumb")
        .forEach((thumb) => {
          thumb.addEventListener("click", function () {
            const index = parseInt(this.getAttribute("data-imagen-index"));
            imagenActualIndex = index;
            mostrarDocumento(index);

            if (!modalBootstrap) {
              modalBootstrap = new bootstrap.Modal(modal);
            }
            modalBootstrap.show();
          });
        });

      // Navegación
      elements.btnAnterior?.addEventListener("click", () => {
        if (imagenActualIndex > 0) {
          mostrarDocumento(imagenActualIndex - 1);
        }
      });

      elements.btnSiguiente?.addEventListener("click", () => {
        if (imagenActualIndex < imagenesAutorizacion.length - 1) {
          mostrarDocumento(imagenActualIndex + 1);
        }
      });

      // Navegación con teclado
      modal.addEventListener("keydown", (e) => {
        if (e.key === "ArrowLeft" && imagenActualIndex > 0) {
          mostrarDocumento(imagenActualIndex - 1);
        } else if (
          e.key === "ArrowRight" &&
          imagenActualIndex < imagenesAutorizacion.length - 1
        ) {
          mostrarDocumento(imagenActualIndex + 1);
        }
      });
    },

    /**
     * Inicializar imágenes de productos
     */
    initProductImages: function () {
      const imagenesProductos = window.imagenesProductosData || {};
      const nombresProductos = window.nombresProductosData || {};

      document.querySelectorAll(".ver-imagen-producto").forEach((btn) => {
        btn.addEventListener("click", function () {
          const idProducto = this.getAttribute("data-id-producto");
          const nombreProducto = nombresProductos[idProducto] || "Producto";
          const modal = new bootstrap.Modal(
            document.getElementById("modalImagenProducto")
          );

          document.getElementById("producto-nombre").textContent =
            nombreProducto;
          const imgContainer = document.getElementById(
            "producto-imagen-container"
          );

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

          modal.show();
        });
      });
    },

    /**
     * MEJORADO: Inicializar cálculos de stock con lógica de paquetes
     */
    initStockCalculations: function () {
      // Función global para actualizar información de productos específicos
      window.actualizarInfoProductoEspecifico = (nombreProducto) => {
        const input = document.querySelector(
          `input[data-nombre-producto="${nombreProducto}"]`
        );
        const infoElement = document.getElementById(`info-${nombreProducto}`);

        if (!input || !infoElement) return;

        const esProductoEnUnidades =
          input.getAttribute("data-es-toallitas") === "true";

        if (!esProductoEnUnidades) {
          const infoBobinasElement = infoElement.querySelector(".info-bobinas");
          const infoPesoElement = infoElement.querySelector(".info-peso");

          if (infoBobinasElement && infoPesoElement) {
            const cantidad = parseInt(input.value) || 0;
            const bobinasPorItem =
              parseFloat(input.getAttribute("data-bobinas-por-item")) || 0;
            const pesoPromedio =
              parseFloat(input.getAttribute("data-peso-promedio")) || 0;

            if (cantidad > 0) {
              infoBobinasElement.textContent = Math.round(
                cantidad * bobinasPorItem
              );
              infoPesoElement.textContent = (cantidad * pesoPromedio).toFixed(
                2
              );
              infoElement.style.display = "block";
            } else {
              infoElement.style.display = "none";
            }
          }
        }
      };

      // Función global para validar producción
      window.validarProduccion = () => this.validarProduccion();

      // NUEVO: Función global para actualizar cálculo de paquetes
      window.actualizarCalculoPaquetes = (nombreProducto) => {
        const input = document.querySelector(
          `input[data-nombre-producto="${nombreProducto}"]`
        );
        if (input) {
          this.actualizarCalculoPaquetes(input);
        }
      };
    },

    /**
     * Vincular eventos de modales
     */
    bindModalEvents: function () {
      document.addEventListener("hidden.bs.modal", function () {
        setTimeout(() => {
          const backdrops = document.querySelectorAll(".modal-backdrop");
          backdrops.forEach((backdrop) => backdrop.remove());
          document.body.classList.remove("modal-open");
          document.body.style.overflow = "";
          document.body.style.paddingRight = "";
        }, 150);
      });
    },
  },

  /**
   * Módulo para devoluciones a PCP
   */
  devolucionesPage: {
    init: function () {
      this.bindMotivoButtons();
      this.bindFilterEvents();
    },

    bindMotivoButtons: function () {
      document.querySelectorAll(".mostrar-motivo").forEach((boton) => {
        boton.addEventListener("click", function () {
          const motivo = this.getAttribute("data-motivo");
          PcpManager.devolucionesPage.showMotivoModal(motivo);
        });
      });
    },

    showMotivoModal: function (motivo) {
      const modal = document.getElementById("modalMotivo");
      const textoMotivo = document.getElementById("textoMotivo");

      if (modal && textoMotivo) {
        textoMotivo.textContent = motivo || "Sin información disponible";
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
      }
    },

    bindFilterEvents: function () {
      const btnLimpiar = document.querySelector('a[href*="limpiar"]');
      if (btnLimpiar) {
        btnLimpiar.addEventListener("click", function (e) {
          if (!confirm("¿Está seguro de limpiar todos los filtros?")) {
            e.preventDefault();
          }
        });
      }
    },
  },

  /**
   * Módulo para historial de acciones
   */
  historialPage: {
    init: function () {
      this.bindDetailButtons();
    },

    bindDetailButtons: function () {
      const modal = document.getElementById("modalDetalles");
      if (modal) {
        modal.addEventListener("show.bs.modal", function (event) {
          const button = event.relatedTarget;
          PcpManager.historialPage.loadModalData(button, modal);
        });
      }
    },

    loadModalData: function (button, modal) {
      const data = {
        venta: button.getAttribute("data-venta"),
        cliente: button.getAttribute("data-cliente"),
        usuario: button.getAttribute("data-usuario"),
        accion: button.getAttribute("data-accion"),
        fecha: button.getAttribute("data-fecha"),
        estado: button.getAttribute("data-estado"),
        observaciones: button.getAttribute("data-observaciones"),
      };

      // Llenar modal con datos
      modal.querySelector("#modal-id-venta").textContent = data.venta;
      modal.querySelector("#modal-cliente").textContent = data.cliente;
      modal.querySelector("#modal-usuario").textContent = data.usuario;
      modal.querySelector("#modal-accion").textContent = data.accion;
      modal.querySelector("#modal-fecha").textContent = data.fecha;
      modal.querySelector("#modal-estado").textContent = data.estado;

      const observacionesEl = modal.querySelector("#modal-observaciones");
      if (observacionesEl) {
        observacionesEl.innerHTML = data.observaciones
          ? data.observaciones.replace(/\n/g, "<br>")
          : "<em>Sin observaciones</em>";
      }
    },
  },

  /**
   * Módulo para dashboard principal
   */
  dashboardPage: {
    init: function () {
      this.loadStatistics();
      this.bindRefreshEvents();
    },

    loadStatistics: function () {
      if (!PcpManager.config) return;

      fetch(
        `${PcpManager.config.urlBase}secciones/sectorPcp/main.php?action=obtener_estadisticas`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.updateStatisticsDisplay(data.estadisticas);
          }
        })
        .catch((error) => {
          console.error("Error cargando estadísticas:", error);
        });
    },

    updateStatisticsDisplay: function (stats) {
      const elementos = {
        "stat-pendientes": stats.pendientes,
        "stat-produccion": stats.produccion,
        "stat-procesadas": stats.procesadas,
        "stat-devueltas": stats.devueltas,
      };

      Object.entries(elementos).forEach(([id, valor]) => {
        const elemento = document.getElementById(id);
        if (elemento) {
          elemento.textContent = valor;
          // Animar actualización
          elemento.style.transform = "scale(1.1)";
          setTimeout(() => {
            elemento.style.transform = "scale(1)";
          }, 200);
        }
      });
    },

    bindRefreshEvents: function () {
      const btnRefresh = document.getElementById("btn-refresh-stats");
      if (btnRefresh) {
        btnRefresh.addEventListener("click", () => {
          this.loadStatistics();
        });
      }

      // Auto-refresh cada 5 minutos
      setInterval(() => {
        this.loadStatistics();
      }, 300000);
    },
  },

  /**
   * Utilidades generales
   */
  utils: {
    showToast: function (message, type = "info") {
      console.log(`[${type.toUpperCase()}] ${message}`);

      const toast = document.createElement("div");
      toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
      toast.style.cssText =
        "top: 20px; right: 20px; z-index: 9999; min-width: 300px;";
      toast.innerHTML = `
        <i class="fas fa-${
          type === "success"
            ? "check-circle"
            : type === "error"
            ? "exclamation-circle"
            : "info-circle"
        } me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;

      document.body.appendChild(toast);

      setTimeout(() => {
        if (toast.parentNode) {
          toast.remove();
        }
      }, 5000);
    },

    formatDate: function (dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString("es-PY", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },

    formatNumber: function (number, decimals = 2) {
      return new Intl.NumberFormat("es-PY", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      }).format(number);
    },

    copyToClipboard: function (text) {
      navigator.clipboard
        .writeText(text)
        .then(() => {
          this.showToast("Texto copiado al portapapeles", "success");
        })
        .catch(() => {
          this.showToast("Error al copiar texto", "error");
        });
    },
  },
};

// Funciones globales para compatibilidad con onclick en HTML
window.confirmarAccion = function (mensaje, callback) {
  if (confirm(mensaje)) {
    if (typeof callback === "function") {
      callback();
    } else if (typeof callback === "string") {
      window.location.href = callback;
    }
  }
};

window.mostrarDetalleVenta = function (idVenta) {
  if (PcpManager.config) {
    window.open(
      `${PcpManager.config.urlBase}secciones/sectorPcp/ver.php?id=${idVenta}`,
      "_blank"
    );
  }
};

// Inicialización automática cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  // Buscar la configuración global en el HTML
  if (typeof PCP_CONFIG !== "undefined") {
    PcpManager.init(PCP_CONFIG);
  }

  // Detectar qué página estamos viendo e inicializar módulos correspondientes
  const currentPath = window.location.pathname;

  if (currentPath.includes("main.php") || currentPath.endsWith("/sectorPcp/")) {
    PcpManager.dashboardPage.init();
  } else if (currentPath.includes("index.php")) {
    PcpManager.ventasAprobadasPage.init();
  } else if (currentPath.includes("ver.php")) {
    PcpManager.procesarVentaPage.init();
  } else if (
    currentPath.includes("devuelto_pcp.php") ||
    currentPath.includes("devoluciones")
  ) {
    PcpManager.devolucionesPage.init();
  } else if (currentPath.includes("historial.php")) {
    PcpManager.historialPage.init();
  }
});

// Exportar para uso en módulos (si es necesario)
if (typeof module !== "undefined" && module.exports) {
  module.exports = PcpManager;
}
