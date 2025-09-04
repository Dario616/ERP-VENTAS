// ===== VARIABLES GLOBALES =====
let searchTimeout;
let autoRefreshInterval;
let isLoading = false;
let currentFilters = {
  producto: "",
  page: 1,
};
let currentProductData = null;
let selectedReservations = [];
let debugMode = false;

// ===== FUNCIÓN DE DEBUG =====
function logDebug(mensaje, datos = null) {
  // Verificar si existe RESERVAS_CONFIG y tiene debug habilitado
  const debugEnabled =
    (typeof RESERVAS_CONFIG !== "undefined" && RESERVAS_CONFIG.debug) ||
    window.location.search.includes("debug=1");

  if (debugEnabled) {
    console.log(`[RESERVAS_PRODUCTOS DEBUG] ${mensaje}`, datos || "");
  }

  debugMode = debugEnabled;
}

// ===== FUNCIÓN PARA MOSTRAR LOADING =====
function showLoading(mensaje = "Procesando...") {
  if (document.getElementById("loadingOverlay")) return;

  const loading = document.createElement("div");
  loading.id = "loadingOverlay";
  loading.className = "loading-overlay";
  loading.innerHTML = `
        <div class="text-center text-white">
            <div class="loading-spinner mb-3"></div>
            <div>
                <h5><i class="fas fa-boxes me-2"></i>${mensaje}</h5>
                <p class="text-white-50">Conectando con el sistema de reservas...</p>
            </div>
        </div>
    `;

  document.body.appendChild(loading);
  isLoading = true;
  logDebug(`Loading mostrado: ${mensaje}`);
}

// ===== FUNCIÓN PARA OCULTAR LOADING =====
function hideLoading() {
  const loading = document.getElementById("loadingOverlay");
  if (loading) {
    loading.remove();
    isLoading = false;
    logDebug("Loading ocultado");
  }
}

// ===== FUNCIÓN PARA MOSTRAR TOAST/NOTIFICACIÓN =====
function showToast(mensaje, tipo = "info", duracion = 3000) {
  // Remover toast anterior si existe
  const existingToast = document.getElementById("customToast");
  if (existingToast) {
    existingToast.remove();
  }

  const toast = document.createElement("div");
  toast.id = "customToast";
  toast.className = `alert alert-${tipo} position-fixed fade-in`;
  toast.style.cssText = `
        top: 90px; 
        right: 20px; 
        z-index: 9999; 
        min-width: 350px;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        border: none;
    `;

  const iconos = {
    success: "fas fa-check-circle",
    error: "fas fa-exclamation-triangle",
    warning: "fas fa-exclamation-circle",
    info: "fas fa-info-circle",
  };

  toast.innerHTML = `
        <i class="${iconos[tipo] || iconos.info} me-2"></i>
        ${mensaje}
        <button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>
    `;

  document.body.appendChild(toast);

  // Auto-remove después de la duración especificada
  setTimeout(() => {
    if (toast.parentNode) {
      toast.remove();
    }
  }, duracion);

  logDebug(`Toast mostrado: ${tipo} - ${mensaje}`);
}

// ===== FUNCIÓN PARA TEST DE CONECTIVIDAD =====
function testConectividad() {
  return new Promise((resolve, reject) => {
    const params = new URLSearchParams({
      action: "test_conectividad",
    });

    const currentUrl = window.location.pathname;
    const fetchUrl = `${currentUrl}?${params.toString()}`;

    logDebug("Test de conectividad:", fetchUrl);

    fetch(fetchUrl, {
      method: "GET",
      cache: "no-cache",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then((response) => {
        logDebug("Test conectividad - Status:", response.status);
        if (response.ok) {
          return response.json().then(() => resolve());
        } else {
          reject(new Error(`HTTP ${response.status}`));
        }
      })
      .catch((error) => {
        logDebug("Test conectividad - Error:", error);
        reject(error);
      });
  });
}

// ===== FUNCIÓN PARA CARGAR PRODUCTOS CON RESERVAS =====
function cargarProductos(filtros = {}) {
  if (isLoading) {
    logDebug("Carga ignorada: ya hay una carga en curso");
    return;
  }

  // Fusionar filtros actuales con los nuevos
  const filtrosFinales = { ...currentFilters, ...filtros };
  currentFilters = filtrosFinales;

  showLoading("Cargando productos con reservas...");

  // Construir parámetros de URL
  const params = new URLSearchParams({
    action: "buscar_productos",
    producto: filtrosFinales.producto || "",
    page: filtrosFinales.page || 1,
    limit: 20,
  });

  // Usar la ruta correcta - misma página actual
  const currentUrl = window.location.pathname;
  const fetchUrl = `${currentUrl}?${params.toString()}`;

  logDebug("Iniciando carga de productos con filtros:", {
    filtros: filtrosFinales,
    url: fetchUrl,
  });

  fetch(fetchUrl, {
    method: "GET",
    cache: "no-cache",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => {
      logDebug("Respuesta recibida:", response.status);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      // Verificar que el content-type sea JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        throw new Error("La respuesta no es JSON válido");
      }

      return response.json();
    })
    .then((data) => {
      hideLoading();
      logDebug("Datos de productos recibidos", data);

      if (data.success) {
        actualizarTablaProductos(data.datos);
        actualizarPaginacion(data.paginacion);
        actualizarEstadisticas(data.estadisticas);

        const mensaje = `✅ ${data.datos.length} productos cargados`;
        showToast(mensaje, data.datos.length > 0 ? "success" : "warning", 2000);
      } else {
        logDebug("Error en respuesta", data);
        const errorMsg = data.error || "Error desconocido al cargar productos";
        showToast(`❌ Error: ${errorMsg}`, "error");
        mostrarErrorEnTablaProductos(errorMsg);
      }
    })
    .catch((error) => {
      hideLoading();
      console.error("Error en carga de productos:", error);
      logDebug(`Error de conexión: ${error.message}`);
      showToast(`❌ Error de conexión: ${error.message}`, "error");
      mostrarErrorEnTablaProductos(error.message);
    });
}

// ===== FUNCIÓN PARA MOSTRAR ERROR EN TABLA DE PRODUCTOS =====
function mostrarErrorEnTablaProductos(error) {
  const tbody = document.getElementById("tablaProductosBody");
  if (!tbody) return;

  tbody.innerHTML = `
    <tr>
        <td colspan="7" class="text-center py-5">
            <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 2rem;"></i>
            <div class="text-danger">Error al cargar productos</div>
            <div class="small text-muted">${escapeHtml(error)}</div>
            <button class="btn btn-outline-primary btn-sm mt-2" onclick="cargarProductos()">
                <i class="fas fa-retry me-1"></i>Reintentar
            </button>
        </td>
    </tr>
  `;
}

// ===== FUNCIÓN PARA ACTUALIZAR TABLA DE PRODUCTOS =====
function actualizarTablaProductos(productos) {
  const tbody = document.getElementById("tablaProductosBody");
  if (!tbody) return;

  if (!productos || productos.length === 0) {
    const mensajeVacio = currentFilters.producto
      ? `No se encontraron productos que contengan "${currentFilters.producto}"`
      : "No se encontraron productos con reservas activas";

    tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <i class="fas fa-boxes text-muted mb-3" style="font-size: 2rem;"></i>
                    <div class="text-muted">${mensajeVacio}</div>
                    ${
                      currentFilters.producto
                        ? `
                        <button class="btn btn-outline-primary btn-sm mt-2" onclick="limpiarFiltros()">
                            <i class="fas fa-eraser me-1"></i>Ver todos los productos
                        </button>
                    `
                        : ""
                    }
                </td>
            </tr>
        `;
    return;
  }

  let html = "";
  productos.forEach((producto, index) => {
    const urgencia = producto.configuracion_urgencia || {};
    const tipo = producto.configuracion_tipo || {};
    const estado = producto.estado_stock || {};

    html += `
            <tr class="fade-in" style="animation-delay: ${
              index * 50
            }ms" data-producto-id="${producto.id_stock}">
                <!-- Producto -->
                <td>
                    <div class="producto-info">
                        <div class="producto-nombre">
                            ${escapeHtml(producto.nombre_producto)}
                        </div>
                        <div class="producto-tipo">
                            <span class="badge" style="background-color: ${
                              tipo.color || "#6b7280"
                            };">
                                <i class="${
                                  tipo.icono || "fas fa-box"
                                } me-1"></i>
                                ${escapeHtml(producto.tipo_producto || "")}
                            </span>
                            <span class="badge bg-info ms-1">
                                ${producto.bobinas_pacote || 1} bob/paq
                            </span>
                        </div>
                        <div class="stock-info">
                            <small>
                                <strong>Total:</strong> ${
                                  producto.cantidad_total_formateada || "0"
                                }
                            </small>
                        </div>
                    </div>
                </td>
                <!-- Total Reservas -->
                <td class="text-center">
                    <div class="fw-bold text-primary fs-5">
                        ${producto.total_reservas || 0}
                    </div>
                    <small class="text-muted">reservas</small>
                </td>

                <!-- Bobinas/Paquetes -->
                <td class="text-center">
                    <div class="fw-bold text-success">
                        ${producto.total_bobinas_reservadas_formateado || "0"}
                    </div>
                    <small class="text-muted">bobinas</small>
                    <div class="fw-bold text-warning mt-1">
                        ${producto.total_paquetes_reservados_formateado || "0"}
                    </div>
                    <small class="text-muted">paquetes</small>
                </td>

                <!-- Clientes -->
                <td>
                    <div class="clientes-info">
                        <div class="fw-bold">
                            ${producto.total_clientes || 0} cliente(s)
                        </div>
                        <div class="small text-muted">
                            ${escapeHtml(
                              producto.clientes_formateados || "Sin clientes"
                            )}
                        </div>
                    </div>
                </td>
                <!-- Acciones -->
                <td>
                    <div class="btn-group-vertical btn-group-sm">
                        <button 
                            class="btn btn-info btn-sm" 
                            onclick="verDetallesProducto(${
                              producto.id_stock
                            }, '${escapeHtml(producto.nombre_producto)}')"
                            title="Ver Detalles de Reservas">
                            <i class="fas fa-eye me-1"></i>Ver Detalles
                        </button>
                        <button 
                            class="btn btn-warning btn-sm mt-1" 
                            onclick="abrirCancelacionProducto(${
                              producto.id_stock
                            }, '${escapeHtml(producto.nombre_producto)}')"
                            title="Cancelar Reservas">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                    </div>
                </td>
            </tr>
        `;
  });

  tbody.innerHTML = html;

  // Aplicar animaciones
  setTimeout(() => {
    tbody.querySelectorAll(".fade-in").forEach((row, index) => {
      setTimeout(() => {
        row.classList.add("visible");
      }, index * 50);
    });
  }, 100);

  logDebug("Tabla de productos actualizada", { productos: productos.length });
}

// ===== FUNCIÓN PARA ACTUALIZAR PAGINACIÓN =====
function actualizarPaginacion(paginacion) {
  const container = document.getElementById("paginacionContainer");
  const lista = document.getElementById("paginacionLista");
  const info = document.getElementById("infoPaginacion");

  if (!paginacion || paginacion.total_registros === 0) {
    container.style.display = "none";
    return;
  }

  container.style.display = "block";

  // Actualizar información
  const inicio =
    (paginacion.pagina_actual - 1) * paginacion.registros_por_pagina + 1;
  const fin = Math.min(
    paginacion.pagina_actual * paginacion.registros_por_pagina,
    paginacion.total_registros
  );
  info.textContent = `Mostrando ${inicio}-${fin} de ${paginacion.total_registros} productos`;

  // Generar páginas
  let html = "";

  // Botón anterior
  html += `
        <li class="page-item ${
          !paginacion.hay_pagina_anterior ? "disabled" : ""
        }">
            <a class="page-link" href="#" onclick="cambiarPagina(${
              paginacion.pagina_anterior
            }); return false;">
                <i class="fas fa-chevron-left me-1"></i>Anterior
            </a>
        </li>
    `;

  // Páginas numéricas
  const inicio_pag = Math.max(1, paginacion.pagina_actual - 2);
  const fin_pag = Math.min(
    paginacion.total_paginas,
    paginacion.pagina_actual + 2
  );

  for (let i = inicio_pag; i <= fin_pag; i++) {
    html += `
            <li class="page-item ${
              i === paginacion.pagina_actual ? "active" : ""
            }">
                <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
            </li>
        `;
  }

  // Botón siguiente
  html += `
        <li class="page-item ${
          !paginacion.hay_pagina_siguiente ? "disabled" : ""
        }">
            <a class="page-link" href="#" onclick="cambiarPagina(${
              paginacion.pagina_siguiente
            }); return false;">
                Siguiente<i class="fas fa-chevron-right ms-1"></i>
            </a>
        </li>
    `;

  lista.innerHTML = html;
}

// ===== FUNCIÓN PARA ACTUALIZAR ESTADÍSTICAS =====
function actualizarEstadisticas(estadisticas) {
  if (!estadisticas) return;

  const elementos = {
    totalProductos: estadisticas.total_productos || 0,
    totalReservas: estadisticas.total_reservas || 0,
    totalBobinas: estadisticas.total_bobinas_formateado || "0",
    productosCriticos: estadisticas.productos_criticos || 0,
  };

  Object.entries(elementos).forEach(([id, valor]) => {
    const elemento = document.getElementById(id);
    if (elemento) {
      elemento.textContent = valor;
    }
  });

  logDebug("Estadísticas actualizadas", estadisticas);
}

// ===== FUNCIÓN PARA VER DETALLES DE PRODUCTO =====
function verDetallesProducto(idStock, nombreProducto) {
  currentProductData = { id: idStock, nombre: nombreProducto };

  document.getElementById("modalProductoNombre").textContent = nombreProducto;

  // Limpiar tabla de detalles
  document.getElementById("tablaReservasDetalle").innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-3">
                <div class="loading-spinner mb-2 mx-auto"></div>
                <div class="text-muted">Cargando reservas...</div>
            </td>
        </tr>
    `;

  // Limpiar información del producto
  document.getElementById("detalleProductoInfo").textContent = "-";
  document.getElementById("detalleStockDisponible").textContent = "-";
  document.getElementById("detalleTotalReservado").textContent = "-";
  document.getElementById("detallePorcentajeComprometido").textContent = "-";

  // Mostrar modal
  const modal = new bootstrap.Modal(
    document.getElementById("modalDetallesReservas")
  );
  modal.show();

  // Cargar datos
  cargarReservasProducto(idStock);

  logDebug("Modal de detalles abierto para producto", {
    id: idStock,
    nombre: nombreProducto,
  });
}

// ===== FUNCIÓN PARA CARGAR RESERVAS DE UN PRODUCTO (CORREGIDA) =====
function cargarReservasProducto(idStock) {
  const params = new URLSearchParams({
    action: "obtener_reservas_producto",
    id_stock: idStock,
  });

  // Usar la ruta correcta - misma página actual
  const currentUrl = window.location.pathname;
  const fetchUrl = `${currentUrl}?${params.toString()}`;

  logDebug("Cargando reservas del producto:", { idStock, url: fetchUrl });

  fetch(fetchUrl, {
    method: "GET",
    cache: "no-cache",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => {
      logDebug("Respuesta recibida:", response.status);

      // Verificar si la respuesta es exitosa
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      // Verificar que el content-type sea JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        throw new Error("La respuesta no es JSON válido");
      }

      return response.json();
    })
    .then((data) => {
      logDebug("Datos recibidos:", data);

      if (data.success) {
        mostrarReservasEnModal(data.reservas);
        logDebug("Reservas del producto cargadas", data);
      } else {
        // Mostrar mensaje de error específico
        const errorMsg = data.error || "Error desconocido al cargar reservas";
        showToast(`❌ Error: ${errorMsg}`, "error");

        // Mostrar mensaje en la tabla también
        mostrarErrorEnTablaReservas(errorMsg);
      }
    })
    .catch((error) => {
      console.error("Error cargando reservas del producto:", error);
      logDebug(`Error de conexión: ${error.message}`);
      showToast(`❌ Error de conexión: ${error.message}`, "error");

      // Mostrar error en la tabla
      mostrarErrorEnTablaReservas(error.message);
    });
}

// ===== FUNCIÓN PARA MOSTRAR ERROR EN TABLA DE RESERVAS =====
function mostrarErrorEnTablaReservas(error) {
  const tbody = document.getElementById("tablaReservasDetalle");
  if (tbody) {
    tbody.innerHTML = `
      <tr>
          <td colspan="8" class="text-center py-4">
              <i class="fas fa-exclamation-triangle text-danger mb-2" style="font-size: 1.5rem;"></i>
              <div class="text-danger">Error al cargar reservas</div>
              <div class="small text-muted">${escapeHtml(error)}</div>
              <button class="btn btn-outline-primary btn-sm mt-2" onclick="reintentarCargaReservas()">
                  <i class="fas fa-retry me-1"></i>Reintentar
              </button>
          </td>
      </tr>
    `;
  }
}

// ===== FUNCIÓN PARA REINTENTAR CARGA DE RESERVAS =====
function reintentarCargaReservas() {
  if (currentProductData) {
    cargarReservasProducto(currentProductData.id);
  }
}

// ===== FUNCIÓN PARA MOSTRAR RESERVAS EN MODAL =====
function mostrarReservasEnModal(reservas) {
  const tbody = document.getElementById("tablaReservasDetalle");

  if (!reservas || reservas.length === 0) {
    tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-3">
                    <i class="fas fa-info-circle text-muted mb-2" style="font-size: 1.5rem;"></i>
                    <div class="text-muted">No se encontraron reservas para este producto</div>
                </td>
            </tr>
        `;
    return;
  }

  // Actualizar información del producto
  const primerReserva = reservas[0];
  document.getElementById("detalleProductoInfo").textContent =
    primerReserva.nombre_producto;
  document.getElementById("detalleStockDisponible").textContent =
    primerReserva.cantidad_disponible_formateada || "0";

  const totalReservado = reservas.reduce(
    (sum, r) => sum + (parseInt(r.cantidad_reservada) || 0),
    0
  );
  document.getElementById("detalleTotalReservado").textContent =
    totalReservado.toLocaleString();

  const porcentaje =
    primerReserva.cantidad_total > 0
      ? ((totalReservado / primerReserva.cantidad_total) * 100).toFixed(1)
      : "0";
  document.getElementById("detallePorcentajeComprometido").textContent =
    porcentaje + "%";

  // Generar tabla de reservas
  let html = "";
  reservas.forEach((reserva) => {
    const estado = reserva.configuracion_estado_venta || {};

    html += `
            <tr>
                <td class="fw-bold">#${reserva.id}</td>
                <td>${escapeHtml(reserva.cliente_formateado)}</td>
                <td class="text-end fw-bold">${
                  reserva.cantidad_reservada_formateada
                }</td>
                <td class="text-end">${
                  reserva.paquetes_reservados_formateados
                }</td>
                <td>${reserva.fecha_reserva_corta || "-"}</td>
                <td>
                    ${
                      reserva.id_venta
                        ? `
                        <span class="badge bg-info">Venta #${
                          reserva.id_venta
                        }</span>
                        ${
                          reserva.proforma
                            ? `<br><small>${escapeHtml(
                                reserva.proforma
                              )}</small>`
                            : ""
                        }
                    `
                        : '<span class="text-muted">Sin venta</span>'
                    }
                </td>
                <td>
                    ${
                      reserva.estado_venta
                        ? `
                        <span class="badge" style="background-color: ${estado.color};">
                            <i class="${estado.icono} me-1"></i>
                            ${estado.texto}
                        </span>
                    `
                        : '<span class="badge bg-secondary">Sin estado</span>'
                    }
                </td>
                <td class="text-center">
                    <span class="badge ${
                      reserva.dias_reserva >= 15 ? "bg-warning" : "bg-success"
                    }">
                        ${reserva.dias_reserva} días
                    </span>
                </td>
            </tr>
        `;
  });

  tbody.innerHTML = html;
}

// ===== FUNCIÓN PARA ABRIR MODAL DE CANCELACIÓN DESDE DETALLES =====
function abrirModalCancelacion() {
  if (!currentProductData) {
    showToast("❌ Error: No hay producto seleccionado", "error");
    return;
  }

  abrirCancelacionProducto(currentProductData.id, currentProductData.nombre);
}

// ===== FUNCIÓN PARA ABRIR CANCELACIÓN DE PRODUCTO =====
function abrirCancelacionProducto(idStock, nombreProducto) {
  currentProductData = { id: idStock, nombre: nombreProducto };
  selectedReservations = [];

  document.getElementById("modalCancelacionProducto").textContent =
    nombreProducto;
  document.getElementById("filtroClienteCancelacion").value = "";
  document.getElementById("motivoCancelacionReservas").value = "";

  // Ocultar resumen de cancelación
  document.getElementById("resumenCancelacion").style.display = "none";
  document.getElementById("btnConfirmarCancelaciones").disabled = true;

  // Mostrar modal
  const modal = new bootstrap.Modal(
    document.getElementById("modalCancelarReservas")
  );
  modal.show();

  // Cargar reservas para cancelación
  cargarReservasCancelacion();

  logDebug("Modal de cancelación abierto para producto", {
    id: idStock,
    nombre: nombreProducto,
  });
}

// ===== FUNCIÓN PARA CARGAR RESERVAS PARA CANCELACIÓN (CORREGIDA) =====
function cargarReservasCancelacion() {
  if (!currentProductData) {
    showToast("❌ Error: No hay producto seleccionado", "error");
    return;
  }

  const cliente = document
    .getElementById("filtroClienteCancelacion")
    .value.trim();

  const params = new URLSearchParams({
    action: "buscar_reservas_cancelacion",
    id_stock: currentProductData.id,
    cliente: cliente,
  });

  // Usar la ruta correcta
  const currentUrl = window.location.pathname;
  const fetchUrl = `${currentUrl}?${params.toString()}`;

  logDebug("Cargando reservas para cancelación:", {
    idStock: currentProductData.id,
    cliente,
    url: fetchUrl,
  });

  // Mostrar loading en la lista
  document.getElementById("listaReservasCancelacion").innerHTML = `
    <div class="text-center py-4">
        <div class="loading-spinner mb-3 mx-auto"></div>
        <div class="text-muted">Buscando reservas...</div>
    </div>
  `;

  fetch(fetchUrl, {
    method: "GET",
    cache: "no-cache",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => {
      logDebug("Respuesta cancelación recibida:", response.status);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        throw new Error("La respuesta no es JSON válido");
      }

      return response.json();
    })
    .then((data) => {
      logDebug("Datos de cancelación recibidos:", data);

      if (data.success) {
        mostrarReservasCancelacion(data.reservas);
        logDebug("Reservas para cancelación cargadas", data);
      } else {
        const errorMsg = data.error || "Error desconocido al buscar reservas";
        showToast(`❌ Error: ${errorMsg}`, "error");
        mostrarErrorEnListaCancelacion(errorMsg);
      }
    })
    .catch((error) => {
      console.error("Error cargando reservas para cancelación:", error);
      logDebug(`Error de conexión: ${error.message}`);
      showToast(`❌ Error de conexión: ${error.message}`, "error");
      mostrarErrorEnListaCancelacion(error.message);
    });
}

// ===== FUNCIÓN PARA MOSTRAR ERROR EN LISTA DE CANCELACIÓN =====
function mostrarErrorEnListaCancelacion(error) {
  const container = document.getElementById("listaReservasCancelacion");
  if (container) {
    container.innerHTML = `
      <div class="text-center py-4">
          <i class="fas fa-exclamation-triangle text-danger mb-2" style="font-size: 1.5rem;"></i>
          <div class="text-danger">Error al buscar reservas</div>
          <div class="small text-muted">${escapeHtml(error)}</div>
          <button class="btn btn-outline-primary btn-sm mt-2" onclick="cargarReservasCancelacion()">
              <i class="fas fa-retry me-1"></i>Reintentar
          </button>
      </div>
    `;
  }
}

// ===== FUNCIÓN PARA MOSTRAR RESERVAS PARA CANCELACIÓN =====
function mostrarReservasCancelacion(reservas) {
  const container = document.getElementById("listaReservasCancelacion");

  if (!reservas || reservas.length === 0) {
    container.innerHTML = `
      <div class="text-center py-4">
          <i class="fas fa-info-circle text-muted mb-2" style="font-size: 1.5rem;"></i>
          <div class="text-muted">No se encontraron reservas que coincidan con los filtros</div>
      </div>
    `;
    return;
  }

  // NUEVO HTML mejorado para el checkbox "seleccionar todo"
  let html = `
  <div class="seleccionar-todo-container form-check mb-3 p-3">
    <input class="form-check-input" type="checkbox" id="seleccionarTodo" onchange="toggleSeleccionarTodo()">
    <label class="form-check-label fw-bold text-primary" for="seleccionarTodo">
      <i class="fas fa-check-double me-2"></i>
      <span>Seleccionar todas las reservas</span>
      <span class="badge bg-secondary ms-2">${reservas.length}</span>
    </label>
    <div class="small text-muted mt-1">
      <i class="fas fa-info-circle me-1"></i>
      Haz clic para seleccionar/deseleccionar todas las reservas de este producto
    </div>
  </div>
`;

  reservas.forEach((reserva) => {
    html += `
      <div class="reserva-item" data-reserva-id="${reserva.id}">
          <div class="d-flex justify-content-between align-items-start">
              <div class="form-check">
                  <input class="form-check-input" type="checkbox" 
                         id="reserva_${reserva.id}" 
                         onchange="toggleReservaSeleccion(${reserva.id})">
                  <label class="form-check-label" for="reserva_${reserva.id}">
                      <div class="fw-bold">Cliente: ${escapeHtml(
                        reserva.cliente
                      )}</div>
                      <div class="text-muted small">
                          <i class="fas fa-calendar me-1"></i>
                          Reservado: ${reserva.fecha_reserva_formateada} (${
      reserva.dias_reserva
    } días)
                          ${
                            reserva.proforma
                              ? `| <i class="fas fa-file-invoice me-1"></i>Venta: ${escapeHtml(
                                  reserva.id_venta
                                )}`
                              : ""
                          }
                      </div>
                  </label>
              </div>
              <div class="text-end">
                  <div class="fw-bold text-primary">${
                    reserva.cantidad_reservada_formateada
                  } bobinas</div>
                  <div class="text-success">${
                    reserva.paquetes_reservados_formateados
                  } paquetes</div>
                  <div class="small text-muted">ID: #${reserva.id}</div>
              </div>
          </div>
      </div>
    `;
  });

  container.innerHTML = html;

  // Resetear selecciones
  selectedReservations = [];
  actualizarResumenCancelacion();

  // NUEVO: Inicializar estado del checkbox seleccionar todo
  setTimeout(() => {
    actualizarEstadoSeleccionarTodo();
  }, 100);
}

// Función para toggle seleccionar todo
function toggleSeleccionarTodo() {
  const checkboxSeleccionarTodo = document.getElementById("seleccionarTodo");
  const todasLasReservas = document.querySelectorAll(
    'input[id^="reserva_"]:not(#seleccionarTodo)'
  );

  if (checkboxSeleccionarTodo.checked) {
    // Seleccionar todas
    selectedReservations = [];
    todasLasReservas.forEach((checkbox) => {
      const idReserva = parseInt(checkbox.id.replace("reserva_", ""));
      checkbox.checked = true;
      selectedReservations.push(idReserva);

      const reservaDiv = document.querySelector(
        `[data-reserva-id="${idReserva}"]`
      );
      if (reservaDiv) {
        reservaDiv.classList.add("reserva-seleccionada");
      }
    });

    logDebug("Todas las reservas seleccionadas", {
      seleccionadas: selectedReservations,
    });
  } else {
    // Deseleccionar todas
    todasLasReservas.forEach((checkbox) => {
      const idReserva = parseInt(checkbox.id.replace("reserva_", ""));
      checkbox.checked = false;

      const reservaDiv = document.querySelector(
        `[data-reserva-id="${idReserva}"]`
      );
      if (reservaDiv) {
        reservaDiv.classList.remove("reserva-seleccionada");
      }
    });

    selectedReservations = [];
    logDebug("Todas las reservas deseleccionadas");
  }

  actualizarResumenCancelacion();
}

// Función para actualizar el estado del checkbox "seleccionar todo"
function actualizarEstadoSeleccionarTodo() {
  const checkboxSeleccionarTodo = document.getElementById("seleccionarTodo");
  const todasLasReservas = document.querySelectorAll(
    'input[id^="reserva_"]:not(#seleccionarTodo)'
  );
  const reservasSeleccionadas = document.querySelectorAll(
    'input[id^="reserva_"]:not(#seleccionarTodo):checked'
  );

  if (!checkboxSeleccionarTodo || todasLasReservas.length === 0) return;

  if (reservasSeleccionadas.length === 0) {
    // Ninguna seleccionada
    checkboxSeleccionarTodo.checked = false;
    checkboxSeleccionarTodo.indeterminate = false;
  } else if (reservasSeleccionadas.length === todasLasReservas.length) {
    // Todas seleccionadas
    checkboxSeleccionarTodo.checked = true;
    checkboxSeleccionarTodo.indeterminate = false;
  } else {
    // Algunas seleccionadas
    checkboxSeleccionarTodo.checked = false;
    checkboxSeleccionarTodo.indeterminate = true;
  }
}

// ===== FUNCIÓN PARA TOGGLE SELECCIÓN DE RESERVA =====
function toggleReservaSeleccion(idReserva) {
  const checkbox = document.getElementById(`reserva_${idReserva}`);
  const reservaDiv = document.querySelector(`[data-reserva-id="${idReserva}"]`);

  if (checkbox.checked) {
    selectedReservations.push(idReserva);
    reservaDiv.classList.add("reserva-seleccionada");
  } else {
    selectedReservations = selectedReservations.filter(
      (id) => id !== idReserva
    );
    reservaDiv.classList.remove("reserva-seleccionada");
  }

  // NUEVO: Actualizar estado del checkbox "seleccionar todo"
  actualizarEstadoSeleccionarTodo();

  actualizarResumenCancelacion();
  logDebug("Reserva seleccionada/deseleccionada", {
    id: idReserva,
    seleccionadas: selectedReservations,
  });
}

// ===== FUNCIÓN PARA ACTUALIZAR RESUMEN DE CANCELACIÓN =====
function actualizarResumenCancelacion() {
  const btnConfirmar = document.getElementById("btnConfirmarCancelaciones");
  const resumen = document.getElementById("resumenCancelacion");
  const lista = document.getElementById("listaCancelaciones");

  if (selectedReservations.length === 0) {
    btnConfirmar.disabled = true;
    resumen.style.display = "none";
    return;
  }

  btnConfirmar.disabled = false;
  resumen.style.display = "block";

  let html = "";
  selectedReservations.forEach((idReserva) => {
    const checkbox = document.getElementById(`reserva_${idReserva}`);
    const label = checkbox.nextElementSibling;
    const cliente = label.querySelector(".fw-bold").textContent;
    const cantidades =
      label.parentElement.parentElement.querySelector(
        ".text-end .fw-bold"
      ).textContent;

    html += `<li>Reserva #${idReserva}: ${cliente} - ${cantidades}</li>`;
  });

  lista.innerHTML = html;
}

// ===== FUNCIÓN PARA FILTRAR RESERVAS PARA CANCELACIÓN =====
function filtrarReservasCancelacion() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    cargarReservasCancelacion();
  }, 500);
}

// ===== FUNCIÓN PARA CONFIRMAR CANCELACIONES MASIVAS =====
function confirmarCancelaciones() {
  if (selectedReservations.length === 0) {
    showToast("❌ Debe seleccionar al menos una reserva", "error");
    return;
  }

  const motivo =
    document.getElementById("motivoCancelacionReservas").value.trim() ||
    "Cancelación masiva desde interfaz";

  const btnConfirmar = document.getElementById("btnConfirmarCancelaciones");
  btnConfirmar.disabled = true;
  btnConfirmar.innerHTML =
    '<i class="fas fa-spinner fa-spin me-1"></i>Cancelando...';

  let cancelacionesCompletas = 0;
  let errores = [];
  const totalCancelaciones = selectedReservations.length;

  logDebug("Iniciando cancelaciones masivas", {
    reservas: selectedReservations,
    motivo: motivo,
  });

  // Procesar cancelaciones una por una
  selectedReservations.forEach((idReserva, index) => {
    setTimeout(() => {
      cancelarReservaIndividual(idReserva, motivo)
        .then((resultado) => {
          if (resultado.success) {
            cancelacionesCompletas++;
            logDebug(`Reserva ${idReserva} cancelada exitosamente`);
          } else {
            errores.push(`Reserva #${idReserva}: ${resultado.error}`);
            logDebug(`Error cancelando reserva ${idReserva}:`, resultado.error);
          }

          // Verificar si es la última cancelación
          if (index === totalCancelaciones - 1) {
            finalizarCancelacionesMasivas(
              cancelacionesCompletas,
              errores,
              btnConfirmar
            );
          }
        })
        .catch((error) => {
          errores.push(`Reserva #${idReserva}: Error de conexión`);
          logDebug(`Error de conexión cancelando reserva ${idReserva}:`, error);

          if (index === totalCancelaciones - 1) {
            finalizarCancelacionesMasivas(
              cancelacionesCompletas,
              errores,
              btnConfirmar
            );
          }
        });
    }, index * 500); // Esperar 500ms entre cancelaciones para no saturar el servidor
  });
}

// ===== FUNCIÓN PARA CANCELAR RESERVA INDIVIDUAL =====
function cancelarReservaIndividual(idReserva, motivo) {
  const datos = {
    id_reserva: idReserva,
    motivo: motivo,
  };

  const currentUrl = window.location.pathname;

  return fetch(`${currentUrl}?action=cancelar_reserva`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify(datos),
  }).then((response) => response.json());
}

// ===== FUNCIÓN PARA FINALIZAR CANCELACIONES MASIVAS =====
function finalizarCancelacionesMasivas(exitos, errores, btnConfirmar) {
  // Restaurar botón
  btnConfirmar.disabled = false;
  btnConfirmar.innerHTML =
    '<i class="fas fa-times me-1"></i>Confirmar Cancelaciones';

  if (exitos > 0) {
    showToast(
      `✅ ${exitos} reserva(s) cancelada(s) exitosamente`,
      "success",
      5000
    );

    // Cerrar modal de cancelación
    const modal = bootstrap.Modal.getInstance(
      document.getElementById("modalCancelarReservas")
    );
    modal.hide();

    // Actualizar datos después de un breve delay
    setTimeout(() => {
      cargarProductos(); // Recargar lista de productos
      if (currentProductData) {
        // Si hay modal de detalles abierto, actualizar también
        const modalDetalles = bootstrap.Modal.getInstance(
          document.getElementById("modalDetallesReservas")
        );
        if (modalDetalles && modalDetalles._isShown) {
          cargarReservasProducto(currentProductData.id);
        }
      }
    }, 1000);
  }

  if (errores.length > 0) {
    showToast(`❌ ${errores.length} error(es) en cancelación`, "error", 5000);
    console.error("Errores en cancelación masiva:", errores);
  }

  // Resetear selecciones
  selectedReservations = [];
  actualizarResumenCancelacion();

  logDebug("Cancelaciones masivas finalizadas", {
    exitos: exitos,
    errores: errores.length,
  });
}

// ===== FUNCIÓN PARA CAMBIAR PÁGINA =====
function cambiarPagina(nuevaPagina) {
  if (nuevaPagina < 1) return;

  currentFilters.page = nuevaPagina;
  cargarProductos({ page: nuevaPagina });

  // Hacer scroll hacia arriba
  window.scrollTo({ top: 0, behavior: "smooth" });
}

// ===== FUNCIÓN PARA APLICAR FILTROS =====
function aplicarFiltros() {
  const productoInput = document.getElementById("filtroProducto");

  const filtros = {
    producto: productoInput ? productoInput.value.trim() : "",
    page: 1, // Resetear a primera página al filtrar
  };

  cargarProductos(filtros);
}

// ===== FUNCIÓN PARA LIMPIAR FILTROS =====
function limpiarFiltros() {
  const form = document.querySelector(".filtros-form");
  if (form) {
    form.reset();
  }

  currentFilters = { producto: "", page: 1 };
  cargarProductos(currentFilters);

  showToast("🔄 Filtros limpiados", "info", 2000);
}

// ===== FUNCIÓN PARA REFRESCAR AUTOMÁTICAMENTE =====
function iniciarAutoRefresh() {
  if (typeof RESERVAS_CONFIG !== "undefined" && RESERVAS_CONFIG.autoRefresh) {
    const intervalo = RESERVAS_CONFIG.refreshInterval || 120000; // 2 minutos por defecto

    autoRefreshInterval = setInterval(() => {
      if (!isLoading) {
        logDebug("Auto-refresh ejecutado");
        cargarProductos();
      }
    }, intervalo);

    logDebug(`Auto-refresh iniciado cada ${intervalo}ms`);
  }
}

// ===== FUNCIÓN PARA DETENER AUTO-REFRESH =====
function detenerAutoRefresh() {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
    autoRefreshInterval = null;
    logDebug("Auto-refresh detenido");
  }
}

// ===== FUNCIÓN HELPER PARA ESCAPE HTML (CORREGIDA PARA EVITAR ERROR .replace) =====
function escapeHtml(text) {
  // Verificar que text sea string, si no lo es, convertirlo
  if (text === null || text === undefined) {
    return "";
  }

  // Convertir a string si no lo es
  const textStr = String(text);

  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };

  return textStr.replace(/[&<>"']/g, (m) => map[m]);
}

// ===== FUNCIÓN PARA DETECTAR OFFLINE/ONLINE =====
function manejarEstadoConexion() {
  window.addEventListener("online", function () {
    showToast("🌐 Conexión restaurada", "success", 2000);
    logDebug("Conexión online detectada");
    iniciarAutoRefresh();
  });

  window.addEventListener("offline", function () {
    showToast("📵 Sin conexión a internet", "warning", 5000);
    hideLoading();
    detenerAutoRefresh();
    logDebug("Conexión offline detectada");
  });
}

// ===== INICIALIZACIÓN AL CARGAR EL DOM =====
document.addEventListener("DOMContentLoaded", function () {
  logDebug("DOM cargado, inicializando sistema de reservas por productos");

  // Verificar dependencias críticas
  if (typeof bootstrap === "undefined") {
    console.error("Bootstrap no está cargado");
    showToast("❌ Error: Bootstrap no está disponible", "error");
    return;
  }

  // Verificar que los elementos críticos existen
  const elementosRequeridos = [
    "tablaProductosBody",
    "modalDetallesReservas",
    "modalCancelarReservas",
    "filtroProducto",
  ];

  const elementosFaltantes = elementosRequeridos.filter(
    (id) => !document.getElementById(id)
  );

  if (elementosFaltantes.length > 0) {
    console.error("Elementos faltantes:", elementosFaltantes);
    showToast(
      `❌ Error: Faltan elementos en la página: ${elementosFaltantes.join(
        ", "
      )}`,
      "error"
    );
    return;
  }

  // Configurar formulario de filtros
  const filtrosForm = document.querySelector(".filtros-form");
  if (filtrosForm) {
    filtrosForm.addEventListener("submit", function (e) {
      e.preventDefault();
      aplicarFiltros();
    });
  }

  // Configurar input de búsqueda de producto
  const productoInput = document.getElementById("filtroProducto");
  if (productoInput) {
    // Búsqueda en tiempo real con debounce
    productoInput.addEventListener("input", function () {
      clearTimeout(searchTimeout);
      const valor = this.value.trim();

      // Solo buscar si tiene al menos 2 caracteres o está vacío
      if (valor.length === 0 || valor.length >= 2) {
        searchTimeout = setTimeout(() => {
          aplicarFiltros();
        }, 800); // Aumentar el debounce para evitar demasiadas peticiones
      }
    });
  }

  // Test de conectividad antes de cargar datos
  testConectividad()
    .then(() => {
      logDebug("Test de conectividad exitoso, cargando datos iniciales");
      cargarProductos();
    })
    .catch((error) => {
      console.error("Error de conectividad:", error);
      showToast(`❌ Error de conectividad: ${error.message}`, "error");
      // Intentar cargar datos de todos modos
      cargarProductos();
    });

  // Inicializar auto-refresh
  iniciarAutoRefresh();

  // Inicializar manejo de conexión
  manejarEstadoConexion();

  logDebug("Sistema de reservas por productos inicializado completamente");
  console.log("🎯 Reservas por Productos Management - Sistema listo v2.0");

  if (typeof RESERVAS_CONFIG !== "undefined") {
    console.log("📊 Configuración:", RESERVAS_CONFIG);
  }
});

// ===== FUNCIÓN DE LIMPIEZA AL SALIR =====
window.addEventListener("beforeunload", function () {
  detenerAutoRefresh();
  logDebug("Sistema de reservas por productos desactivado");
});

// ===== EXPORTAR FUNCIONES GLOBALES =====
window.ReservasProductosApp = {
  cargarProductos: cargarProductos,
  aplicarFiltros: aplicarFiltros,
  limpiarFiltros: limpiarFiltros,
  cambiarPagina: cambiarPagina,
  verDetallesProducto: verDetallesProducto,
  abrirCancelacionProducto: abrirCancelacionProducto,
  abrirModalCancelacion: abrirModalCancelacion,
  filtrarReservasCancelacion: filtrarReservasCancelacion,
  confirmarCancelaciones: confirmarCancelaciones,
  toggleReservaSeleccion: toggleReservaSeleccion,
  // NUEVAS FUNCIONES AGREGADAS:
  toggleSeleccionarTodo: toggleSeleccionarTodo,
  actualizarEstadoSeleccionarTodo: actualizarEstadoSeleccionarTodo,
  // FUNCIONES EXISTENTES:
  showToast: showToast,
  logDebug: logDebug,
  iniciarAutoRefresh: iniciarAutoRefresh,
  detenerAutoRefresh: detenerAutoRefresh,
  testConectividad: testConectividad,
};
logDebug("Reservas por Productos JS cargado completamente - v2.0");
