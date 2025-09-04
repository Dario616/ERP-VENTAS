const VentaManager = {
  config: null,

  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log(
      "VentaManager inicializado con lógica simplificada",
      this.config
    );
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
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach((input) => {
      input.addEventListener("change", function () {
        VentaManager.aplicarFiltros();
      });
    });
  },

  estadoManager: {
    cambiarEstadoVenta: function (idVenta, nuevoEstado) {
      if (!confirm("¿Está seguro de cambiar el estado de esta venta?")) {
        return;
      }

      VentaManager.utils.showLoader();

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=actualizar_estado_venta`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `id=${idVenta}&estado=${nuevoEstado}`,
        }
      )
        .then((response) => response.json())
        .then((data) => {
          VentaManager.utils.hideLoader();

          if (data.success) {
            VentaManager.utils.showToast(data.mensaje, "success");
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            VentaManager.utils.showToast(data.error, "error");
          }
        })
        .catch((error) => {
          VentaManager.utils.hideLoader();
          console.error("Error:", error);
          VentaManager.utils.showToast("Error de conexión", "error");
        });
    },

    cambiarEstadoStock: function (idStock, nuevoEstado) {
      if (!confirm("¿Está seguro de cambiar el estado de este item?")) {
        return;
      }

      VentaManager.utils.showLoader();

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=actualizar_estado_stock`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `id=${idStock}&estado=${nuevoEstado}`,
        }
      )
        .then((response) => response.json())
        .then((data) => {
          VentaManager.utils.hideLoader();

          if (data.success) {
            VentaManager.utils.showToast(data.mensaje, "success");
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            VentaManager.utils.showToast(data.error, "error");
          }
        })
        .catch((error) => {
          VentaManager.utils.hideLoader();
          console.error("Error:", error);
          VentaManager.utils.showToast("Error de conexión", "error");
        });
    },

    mostrarModalEstado: function (idVenta, estadoActual) {
      const modal = document.getElementById("cambiarEstadoModal");
      const selectEstado = document.getElementById("nuevoEstado");
      const btnConfirmar = document.getElementById("confirmarCambioEstado");

      if (!modal || !selectEstado || !btnConfirmar) return;

      selectEstado.innerHTML = "";

      const estadosValidos = this.obtenerEstadosValidosDesde(estadoActual);

      estadosValidos.forEach((estado) => {
        const option = document.createElement("option");
        option.value = estado.value;
        option.textContent = estado.label;
        selectEstado.appendChild(option);
      });

      btnConfirmar.onclick = () => {
        const nuevoEstado = selectEstado.value;
        if (nuevoEstado) {
          this.cambiarEstadoVenta(idVenta, nuevoEstado);
          const bsModal = bootstrap.Modal.getInstance(modal);
          bsModal.hide();
        }
      };

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    },

    obtenerEstadosValidosDesde: function (estadoActual) {
      const transiciones = {
        pendiente: [
          { value: "confirmada", label: "Confirmada" },
          { value: "cancelada", label: "Cancelada" },
          { value: "Finalizado Manualmente", label: "Finalizar Manualmente" },
        ],
        confirmada: [
          { value: "en_produccion", label: "En Producción" },
          { value: "cancelada", label: "Cancelada" },
          { value: "Finalizado Manualmente", label: "Finalizar Manualmente" },
        ],
        en_produccion: [
          { value: "produccion_completa", label: "Producción Completa" },
          { value: "cancelada", label: "Cancelada" },
          { value: "Finalizado Manualmente", label: "Finalizar Manualmente" },
        ],
        produccion_completa: [
          { value: "despachada", label: "Despachada" },
          { value: "Finalizado Manualmente", label: "Finalizar Manualmente" },
        ],
        despachada: [
          { value: "completada", label: "Completada" },
          { value: "Finalizado Manualmente", label: "Finalizar Manualmente" },
        ],
        completada: [
          { value: "Finalizado Manualmente", label: "Finalizar Manualmente" },
        ],
        cancelada: VentaManager.config.esAdmin
          ? [{ value: "pendiente", label: "Reactivar" }]
          : [],
        "Finalizado Manualmente": [],
      };

      return transiciones[estadoActual] || [];
    },

    confirmarFinalizacionManual: function (idVenta) {
      const mensaje = `¿Está seguro de que desea finalizar manualmente esta venta?
      
Esta acción:
- Marcará la venta como completada sin seguimiento automático
- Requerirá observaciones del proceso
- No se podrá revertir fácilmente

¿Continuar?`;

      if (!confirm(mensaje)) {
        return;
      }

      this.mostrarModalObservaciones(idVenta);
    },

    mostrarModalObservaciones: function (idVenta) {
      let modal = document.getElementById("observacionesFinalizacionModal");

      if (!modal) {
        const modalHtml = `
          <div class="modal fade" id="observacionesFinalizacionModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                  <h5 class="modal-title">
                    <i class="fas fa-hand-paper me-2"></i>
                    Finalizar Venta Manualmente
                  </h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atención:</strong> Esta acción marcará la venta como finalizada sin seguimiento automático.
                  </div>
                  <div class="mb-3">
                    <label for="observacionesFinalizacion" class="form-label">
                      <strong>Observaciones del procesamiento:</strong>
                      <small class="text-muted">(Explique el motivo de la finalización manual)</small>
                    </label>
                    <textarea 
                      class="form-control" 
                      id="observacionesFinalizacion" 
                      rows="4" 
                      placeholder="Ingrese las observaciones sobre por qué se finaliza manualmente esta venta..."
                      required
                    ></textarea>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                  </button>
                  <button type="button" class="btn btn-dark" id="confirmarFinalizacionBtn">
                    <i class="fas fa-check me-1"></i>Finalizar Venta
                  </button>
                </div>
              </div>
            </div>
          </div>
        `;

        document.body.insertAdjacentHTML("beforeend", modalHtml);
        modal = document.getElementById("observacionesFinalizacionModal");
      }

      const btnConfirmar = document.getElementById("confirmarFinalizacionBtn");
      const textareaObservaciones = document.getElementById(
        "observacionesFinalizacion"
      );

      btnConfirmar.onclick = () => {
        const observaciones = textareaObservaciones.value.trim();

        if (!observaciones) {
          VentaManager.utils.showToast(
            "Por favor ingrese las observaciones",
            "error"
          );
          textareaObservaciones.focus();
          return;
        }

        this.procesarFinalizacionManual(idVenta, observaciones);

        const bsModal = bootstrap.Modal.getInstance(modal);
        bsModal.hide();
      };

      textareaObservaciones.value = "";
      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    },

    procesarFinalizacionManual: function (idVenta, observaciones) {
      VentaManager.utils.showLoader();

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=finalizar_manualmente`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `id=${idVenta}&observaciones=${encodeURIComponent(
            observaciones
          )}`,
        }
      )
        .then((response) => response.json())
        .then((data) => {
          VentaManager.utils.hideLoader();

          if (data.success) {
            VentaManager.utils.showToast(
              "Venta finalizada manualmente con éxito",
              "success"
            );
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            VentaManager.utils.showToast(
              data.error || "Error al finalizar venta",
              "error"
            );
          }
        })
        .catch((error) => {
          VentaManager.utils.hideLoader();
          console.error("Error:", error);
          VentaManager.utils.showToast("Error de conexión", "error");
        });
    },
  },

  indexPage: {
    init: function () {
      this.bindFilterEvents();
      this.initSearchInput();
      this.bindStateChangeEvents();
      console.log("📋 Página de listado inicializada con lógica simplificada");
    },

    bindFilterEvents: function () {
      const filterInputs = document.querySelectorAll(
        "#filtroCliente, #filtroEstado, #fechaDesde, #fechaHasta, #filtroProforma"
      );

      filterInputs.forEach((input) => {
        input.addEventListener("change", function () {
          VentaManager.aplicarFiltros();
        });

        if (input.type === "text") {
          let timeout;
          input.addEventListener("input", function () {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
              VentaManager.aplicarFiltros();
            }, 500);
          });
        }
      });
    },

    initSearchInput: function () {
      const searchInput = document.getElementById("busquedaVentas");
      if (searchInput) {
        let timeout;
        searchInput.addEventListener("input", function () {
          clearTimeout(timeout);
          timeout = setTimeout(() => {
            VentaManager.buscarVentas(this.value);
          }, 300);
        });
      }
    },

    bindStateChangeEvents: function () {
      window.cambiarEstadoVenta =
        VentaManager.estadoManager.mostrarModalEstado.bind(
          VentaManager.estadoManager
        );
      window.cambiarEstadoStock =
        VentaManager.estadoManager.cambiarEstadoStock.bind(
          VentaManager.estadoManager
        );
      window.finalizarManualmente =
        VentaManager.estadoManager.confirmarFinalizacionManual.bind(
          VentaManager.estadoManager
        );
    },
  },

  detallePage: {
    init: function (ventaId) {
      this.ventaId = ventaId;
      this.verificarEstadoVenta();
      this.cargarResumenDetallado();
      this.bindStockEvents();
      this.bindProduccionEvents();

      if (VentaManager.config.debug) {
        this.debugNuevaLogica();
      }
    },

    debugNuevaLogica: function () {
      if (!this.ventaId) return;

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=debug_nueva_logica&id=${this.ventaId}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            console.log("🔍 DEBUG NUEVA LÓGICA SIMPLIFICADA:", data.debug);
            console.log(
              "📊 Resumen producción:",
              data.debug.resumen_produccion
            );
            console.log(
              "🏭 Items producción (individuales):",
              data.debug.items_produccion_count
            );
            console.log(
              "📦 Items producción (agrupados):",
              data.debug.items_produccion_agrupados_count
            );
            console.log(
              "🚛 Items despachos (agrupados):",
              data.debug.items_despachos_count
            );
          }
        })
        .catch((error) => {
          console.error("Error en debug:", error);
        });
    },

    verificarEstadoVenta: function () {
      const estadoBadge = document.querySelector(".status-badge");
      if (
        estadoBadge &&
        estadoBadge.textContent.includes("Finalizado Manualmente")
      ) {
        this.esFinalizadaManualmente = true;
        this.configurarVistaFinalizadaManualmente();
      } else {
        this.esFinalizadaManualmente = false;
      }
    },

    configurarVistaFinalizadaManualmente: function () {
      this.deshabilitarAutoRefresh = true;

      this.agregarIndicadoresFinalizadaManualmente();

      this.cargarInformacionPCP();
    },

    agregarIndicadoresFinalizadaManualmente: function () {
      document.body.classList.add("venta-finalizada-manualmente");

      if (document.title) {
        document.title = document.title.replace(
          "Estado de Venta",
          "Venta Finalizada Manualmente"
        );
      }
    },

    cargarInformacionPCP: function () {
      if (!this.ventaId) return;

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=obtener_proceso_pcp&id=${this.ventaId}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.mostrarInformacionPCP(data.proceso_pcp, data.historial_pcp);
          }
        })
        .catch((error) => {
          console.error("Error cargando información PCP:", error);
        });
    },

    mostrarInformacionPCP: function (procesoPcp, historialPcp) {
      const containerPcp = document.getElementById("informacionPCP");
      if (containerPcp && procesoPcp) {
        console.log("Información PCP cargada:", procesoPcp);
      }
    },

    cargarResumenDetallado: function () {
      if (!this.ventaId) return;

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=obtener_resumen_venta&id=${this.ventaId}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            console.log(
              `✅ Resumen cargado (${data.metodo || "simplificado"}):`,
              data.resumen
            );
            this.actualizarVistaResumen(data.resumen);
          } else {
            console.error("❌ Error cargando resumen:", data.error);
          }
        })
        .catch((error) => {
          console.error("❌ Error de conexión:", error);
        });
    },

    actualizarVistaResumen: function (resumen) {
      if (this.esFinalizadaManualmente) {
        console.log(
          "ℹ️ Venta finalizada manualmente - sin actualizaciones de progreso"
        );
        return;
      }

      const progressBar = document.getElementById("progressGeneral");
      if (progressBar && resumen.progreso_general) {
        progressBar.style.width = `${resumen.progreso_general.porcentaje}%`;
        progressBar.className = `progress-bar ${resumen.progreso_general.clase_progreso}`;
        progressBar.setAttribute(
          "aria-valuenow",
          resumen.progreso_general.porcentaje
        );

        console.log(
          `📊 Progreso actualizado: ${resumen.progreso_general.porcentaje}%`
        );
      }

      this.actualizarTablaProductos(resumen.resumen_produccion);
    },

    actualizarTablaProductos: function (productos) {
      const tbody = document.getElementById("tablaProductosBody");
      if (!tbody || !productos) return;

      tbody.innerHTML = "";

      productos.forEach((producto, index) => {
        const porcentajeProduccion = producto.porcentaje_produccion || 0;
        const porcentajeDespacho = producto.porcentaje_despacho || 0;

        const row = `
          <tr>
            <td>
              <strong>${producto.producto}</strong>
              <br><small class="text-muted">${
                producto.unidad_medida || "kg"
              }</small>
            </td>
            <td class="text-center">
              <span class="badge bg-secondary">${
                producto.cantidad_pedida || 0
              }</span>
            </td>
            <td class="text-center">
              <span class="badge bg-primary">${
                producto.cantidad_producida || 0
              }</span>
            </td>
            <td class="text-center">
              <span class="badge bg-success">${
                producto.cantidad_despachada || 0
              }</span>
            </td>
            <td class="text-center">
              <span class="badge bg-info">${producto.cantidad_stock || 0}</span>
            </td>
            <td>
              <div class="progress" style="height: 20px;">
                <div class="progress-bar ${
                  producto.clase_progreso_produccion || "bg-secondary"
                }" 
                     role="progressbar" 
                     style="width: ${porcentajeProduccion}%" 
                     aria-valuenow="${porcentajeProduccion}" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                  ${porcentajeProduccion}%
                </div>
              </div>
            </td>
            <td>
              <div class="progress" style="height: 20px;">
                <div class="progress-bar ${
                  producto.clase_progreso_despacho || "bg-secondary"
                }" 
                     role="progressbar" 
                     style="width: ${porcentajeDespacho}%" 
                     aria-valuenow="${porcentajeDespacho}" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                  ${porcentajeDespacho}%
                </div>
              </div>
            </td>
          </tr>
        `;

        tbody.innerHTML += row;
      });

      console.log(`📋 Tabla actualizada con ${productos.length} productos`);
    },

    bindStockEvents: function () {
      const stockButtons = document.querySelectorAll("[data-stock-id]");
      stockButtons.forEach((button) => {
        button.addEventListener("click", function () {
          const stockId = this.getAttribute("data-stock-id");
          const nuevoEstado = this.getAttribute("data-nuevo-estado");
          VentaManager.estadoManager.cambiarEstadoStock(stockId, nuevoEstado);
        });
      });
    },

    bindProduccionEvents: function () {
      window.verItemsProduccion = () => {
        this.cargarItemsProduccion();
      };
      window.verItemsDespacho = () => {
        this.cargarItemsDespacho();
      };
    },

    cargarItemsDespachado: function (idProducto, nombreProducto) {
      if (!this.ventaId || !idProducto) return;

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=obtener_items_despachados_agrupados&id=${this.ventaId}&id_producto=${idProducto}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            console.log(
              `✅ Items despacho cargados (${data.metodo}):`,
              data.items.length,
              "grupos"
            );
            this.mostrarItemsDespacho(data.items, nombreProducto);
          } else {
            console.error("❌ Error cargando items despachados:", data.error);
            VentaManager.utils.showToast(
              "Error cargando despachos: " + data.error,
              "error"
            );
          }
        })
        .catch((error) => {
          console.error("❌ Error de conexión:", error);
          VentaManager.utils.showToast("Error de conexión", "error");
        });
    },

    mostrarItemsDespacho: function (items, nombreProducto) {
      let modal = document.getElementById("itemsDespachosModal");

      if (!modal) {
        const modalHtml = `
            <div class="modal fade" id="itemsDespachosModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-truck me-2"></i>
                                Items Despachados
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="itemsDespachosContent"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML("beforeend", modalHtml);
        modal = document.getElementById("itemsDespachosModal");
      }

      const content = document.getElementById("itemsDespachosContent");

      if (items.length === 0) {
        content.innerHTML = `
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                No se encontraron items despachados para esta venta.
            </div>
        `;
      } else {
        let html = `
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-success">
                        <tr>
                            <th>Nombre Producto</th>
                            <th>Metragem</th>
                            <th>Largura</th>
                            <th>Gramatura</th>
                            <th>Total Items</th>
                            <th>Bobinas Total</th>
                            <th>Peso Bruto Total</th>
                            <th>Peso Líquido Total</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        items.forEach((item) => {
          html += `
            <tr>
                <td><strong>${item.nombre_producto}</strong></td>
                <td><span class="badge bg-success">${item.metragem}m</span></td>
                <td>${item.largura || "N/A"}m</td>
                <td>${item.gramatura_formateada || "N/A"}</td>
                <td><span class="badge bg-success">${
                  item.total_items_formateado
                }</span></td>
                <td><span class="badge bg-success">${
                  item.bobinas_pacote_total_formateado
                }</span></td>
                <td><strong>${item.peso_bruto_total_formateado}</strong></td>
                <td><strong>${item.peso_liquido_total_formateado}</strong></td>
            </tr>
        `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        content.innerHTML = html;
      }

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    },

    cargarItemsProduccionProducto: function (idProducto, nombreProducto) {
      if (!this.ventaId || !idProducto) return;

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=obtener_items_produccion_agrupados&id=${this.ventaId}&id_producto=${idProducto}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            console.log(
              `✅ Items producción cargados (${data.metodo}):`,
              data.items.length,
              "grupos"
            );
            this.mostrarItemsProduccion(data.items, nombreProducto);
          } else {
            console.error("❌ Error cargando items de producción:", data.error);
            VentaManager.utils.showToast(
              "Error cargando producción: " + data.error,
              "error"
            );
          }
        })
        .catch((error) => {
          console.error("❌ Error de conexión:", error);
          VentaManager.utils.showToast("Error de conexión", "error");
        });
    },

    mostrarItemsProduccion: function (items, nombreProducto) {
      let modal = document.getElementById("itemsProduccionModal");

      if (!modal) {
        const modalHtml = `
            <div class="modal fade" id="itemsProduccionModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-industry me-2"></i>
                                Items Producidos
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="itemsProduccionContent"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML("beforeend", modalHtml);
        modal = document.getElementById("itemsProduccionModal");
      }

      const content = document.getElementById("itemsProduccionContent");

      if (items.length === 0) {
        content.innerHTML = `
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                No se encontraron items de producción para esta venta.
            </div>
        `;
      } else {
        let html = `
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>Nombre Producto</th>
                            <th>Metragem</th>
                            <th>Largura</th>
                            <th>Gramatura</th>
                            <th>Total Items</th>
                            <th>Bobinas Total</th>
                            <th>Peso Bruto Total</th>
                            <th>Peso Líquido Total</th>
                            <th>OP</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        items.forEach((item) => {
          html += `
            <tr>
                <td><strong>${item.nombre_producto}</strong></td>
                <td><span class="badge bg-primary">${item.metragem}m</span></td>
                <td>${item.largura || "N/A"}m</td>
                <td>${item.gramatura_formateada || "N/A"}</td>
                <td><span class="badge bg-primary">${
                  item.total_items_formateado
                }</span></td>
                <td><span class="badge bg-primary">${
                  item.bobinas_pacote_total_formateado
                }</span></td>
                <td><strong>${item.peso_bruto_total_formateado}</strong></td>
                <td><strong>${item.peso_liquido_total_formateado}</strong></td>
                <td>${item.ordenes_produccion || "N/A"}</td>
            </tr>
        `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        content.innerHTML = html;
      }

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    },
  },

  aplicarFiltros: function () {
    const params = new URLSearchParams();

    const cliente = document.getElementById("filtroCliente")?.value;
    const estado = document.getElementById("filtroEstado")?.value;
    const fechaDesde = document.getElementById("fechaDesde")?.value;
    const fechaHasta = document.getElementById("fechaHasta")?.value;
    const proforma = document.getElementById("filtroProforma")?.value;

    if (cliente) params.append("cliente", cliente);
    if (estado) params.append("estado", estado);
    if (fechaDesde) params.append("fecha_desde", fechaDesde);
    if (fechaHasta) params.append("fecha_hasta", fechaHasta);
    if (proforma) params.append("proforma", proforma);

    window.location.href = `${
      this.config.url_base
    }secciones/estado/index.php?${params.toString()}`;
  },

  buscarVentas: function (termino) {
    if (termino.length < 2) return;

    fetch(
      `${
        this.config.url_base
      }secciones/estado/index.php?action=buscar_ventas&termino=${encodeURIComponent(
        termino
      )}`
    )
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          this.mostrarResultadosBusqueda(data.ventas);
        }
      })
      .catch((error) => {
        console.error("Error en búsqueda:", error);
      });
  },

  mostrarResultadosBusqueda: function (ventas) {
    const resultadosDiv = document.getElementById("resultadosBusqueda");
    if (!resultadosDiv) return;

    if (ventas.length === 0) {
      resultadosDiv.innerHTML =
        '<div class="alert alert-info">No se encontraron ventas</div>';
      return;
    }

    let html = '<div class="list-group">';
    ventas.forEach((venta) => {
      const estadoClass =
        venta.estado === "Finalizado Manualmente" ? "text-dark fw-bold" : "";
      const estadoIcon =
        venta.estado === "Finalizado Manualmente"
          ? '<i class="fas fa-hand-paper me-1"></i>'
          : "";

      html += `
        <a href="${this.config.url_base}secciones/estado/ver.php?id=${venta.id}" 
           class="list-group-item list-group-item-action">
          <div class="d-flex w-100 justify-content-between">
            <h6 class="mb-1">${venta.texto_completo}</h6>
            <small class="text-muted">${venta.fecha_venta}</small>
          </div>
          <small class="${estadoClass}">
            ${estadoIcon}Estado: ${venta.estado}
          </small>
        </a>
      `;
    });
    html += "</div>";

    resultadosDiv.innerHTML = html;
  },

  limpiarFiltros: function () {
    document.getElementById("filtroCliente").value = "";
    document.getElementById("filtroEstado").value = "";
    document.getElementById("fechaDesde").value = "";
    document.getElementById("fechaHasta").value = "";
    document.getElementById("filtroProforma").value = "";

    window.location.href = `${this.config.url_base}secciones/estado/index.php`;
  },

  utils: {
    showToast: function (message, type = "info") {
      let toastContainer = document.getElementById("toastContainer");
      if (!toastContainer) {
        toastContainer = document.createElement("div");
        toastContainer.id = "toastContainer";
        toastContainer.className = "position-fixed top-0 end-0 p-3";
        toastContainer.style.zIndex = "1100";
        document.body.appendChild(toastContainer);
      }

      const toastId = "toast_" + Date.now();
      const bgClass =
        type === "success"
          ? "bg-success"
          : type === "error"
          ? "bg-danger"
          : "bg-info";

      const toastHtml = `
        <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
          <div class="toast-body">
            ${message}
          </div>
        </div>
      `;

      toastContainer.innerHTML += toastHtml;

      const toastElement = document.getElementById(toastId);
      const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000,
      });
      toast.show();

      toastElement.addEventListener("hidden.bs.toast", function () {
        toastElement.remove();
      });
    },

    showLoader: function () {
      let loader = document.getElementById("globalLoader");
      if (!loader) {
        loader = document.createElement("div");
        loader.id = "globalLoader";
        loader.innerHTML = `
          <div class="position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" 
               style="background: rgba(0,0,0,0.5); z-index: 9999;">
            <div class="spinner-border text-light" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
          </div>
        `;
        document.body.appendChild(loader);
      }
      loader.style.display = "block";
    },

    hideLoader: function () {
      const loader = document.getElementById("globalLoader");
      if (loader) {
        loader.style.display = "none";
      }
    },

    formatDate: function (dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString("es-PY");
    },

    formatCurrency: function (amount) {
      return "₲ " + new Intl.NumberFormat("es-PY").format(amount);
    },

    logSimplifiedLogic: function (ventaId) {
      console.group(`🔍 DEBUG - Lógica Simplificada para Venta ${ventaId}`);
      console.log(
        "📊 PRODUCIDOS: venta → orden_produccion → stock (por id_orden_produccion)"
      );
      console.log(
        "🚛 DESPACHADOS: stock directo (por id_venta + estado='despachado')"
      );
      console.log("✅ Ventajas: Más rápido, más simple, menos joins complejos");
      console.groupEnd();
    },
  },

  debug: {
    compararLogicas: function (ventaId) {
      console.group(`🔬 COMPARACIÓN DE LÓGICAS - Venta ${ventaId}`);

      fetch(
        `${VentaManager.config.url_base}secciones/estado/index.php?action=debug_nueva_logica&id=${ventaId}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            console.table(data.debug.resumen_produccion);
            console.log("📈 Diferencias encontradas:", data.debug);
          }
        })
        .catch((error) => {
          console.error("Error en comparación:", error);
        });

      console.groupEnd();
    },

    analizarVenta: function (ventaId) {
      console.group(`🔎 ANÁLISIS DETALLADO - Venta ${ventaId}`);

      Promise.all([
        fetch(
          `${VentaManager.config.url_base}secciones/estado/index.php?action=obtener_resumen_venta&id=${ventaId}`
        ),
        fetch(
          `${VentaManager.config.url_base}secciones/estado/index.php?action=obtener_items_produccion_agrupados&id=${ventaId}`
        ),
        fetch(
          `${VentaManager.config.url_base}secciones/estado/index.php?action=obtener_items_despachados_agrupados&id=${ventaId}`
        ),
      ])
        .then((responses) => Promise.all(responses.map((r) => r.json())))
        .then(([resumen, produccion, despacho]) => {
          console.log("📊 Resumen:", resumen);
          console.log("🏭 Producción:", produccion);
          console.log("🚛 Despacho:", despacho);

          if (resumen.success && produccion.success && despacho.success) {
            const progreso = resumen.resumen?.progreso_general?.porcentaje || 0;
            console.log(`📈 Progreso general: ${progreso}%`);
            console.log(
              `🏭 Grupos producción: ${produccion.items?.length || 0}`
            );
            console.log(`🚛 Grupos despacho: ${despacho.items?.length || 0}`);
          }
        })
        .catch((error) => {
          console.error("❌ Error en análisis:", error);
        });

      console.groupEnd();
    },
  },
};

window.cambiarEstadoVenta = function (idVenta, estadoActual) {
  VentaManager.estadoManager.mostrarModalEstado(idVenta, estadoActual);
};

window.cambiarEstadoStock = function (idStock, nuevoEstado) {
  VentaManager.estadoManager.cambiarEstadoStock(idStock, nuevoEstado);
};

window.finalizarManualmente = function (idVenta) {
  VentaManager.estadoManager.confirmarFinalizacionManual(idVenta);
};

window.limpiarFiltros = function () {
  VentaManager.limpiarFiltros();
};

window.verItemsProduccionProducto = function (idProducto, nombreProducto) {
  if (
    VentaManager.detallePage &&
    VentaManager.detallePage.cargarItemsProduccionProducto
  ) {
    console.log(
      `🏭 Cargando items producción: ${nombreProducto} (ID: ${idProducto})`
    );
    VentaManager.detallePage.cargarItemsProduccionProducto(
      idProducto,
      nombreProducto
    );
  }
};

window.verItemsDespachosProducto = function (idProducto, nombreProducto) {
  if (
    VentaManager.detallePage &&
    VentaManager.detallePage.cargarItemsDespachado
  ) {
    console.log(
      `🚛 Cargando items despacho: ${nombreProducto} (ID: ${idProducto})`
    );
    VentaManager.detallePage.cargarItemsDespachado(idProducto, nombreProducto);
  }
};

window.debugNuevaLogica = function (ventaId) {
  if (VentaManager.debug) {
    VentaManager.debug.compararLogicas(
      ventaId || VentaManager.detallePage?.ventaId
    );
  }
};

window.analizarVenta = function (ventaId) {
  if (VentaManager.debug) {
    VentaManager.debug.analizarVenta(
      ventaId || VentaManager.detallePage?.ventaId
    );
  }
};

document.addEventListener("DOMContentLoaded", function () {
  if (typeof VENTA_CONFIG !== "undefined") {
    VentaManager.init(VENTA_CONFIG);
  }

  const currentPath = window.location.pathname;
  const urlParams = new URLSearchParams(window.location.search);
  const ventaId = urlParams.get("id");

  if (ventaId) {
    console.log(`🎯 Inicializando página de detalle para venta ${ventaId}`);
    VentaManager.detallePage.init(ventaId);

    if (VentaManager.config?.debug) {
      VentaManager.utils.logSimplifiedLogic(ventaId);
    }
  } else {
    console.log("📋 Inicializando página de listado");
    VentaManager.indexPage.init();
  }
});

if (typeof module !== "undefined" && module.exports) {
  module.exports = VentaManager;
}
