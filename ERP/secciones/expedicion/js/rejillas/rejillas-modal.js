/**
 * Gestión de modales y detalles de rejillas
 * Módulo encargado de mostrar los detalles de las rejillas y manejar modales
 */

/**
 * Abrir modal con detalles de una rejilla específica
 */
async function abrirModalRejilla(idRejilla, numeroRejilla) {
  console.log("🔍 Abriendo detalles de rejilla:", numeroRejilla);

  try {
    // Validar parámetros
    if (!idRejilla || !numeroRejilla) {
      throw new Error("Parámetros inválidos para abrir modal de rejilla");
    }

    // Actualizar estado
    appState.rejillaSeleccionada = { id: idRejilla, numero: numeroRejilla };

    // Actualizar título del modal
    const tituloElement = document.getElementById("tituloRejillaModal");
    if (tituloElement) {
      tituloElement.textContent = `Rejilla ${numeroRejilla}`;
    }

    // Mostrar loading
    mostrarLoadingModal(true);

    // Abrir modal
    if (modalDetallesRejilla) {
      modalDetallesRejilla.show();
    }

    // Cargar detalles
    await cargarDetallesRejilla(idRejilla);
  } catch (error) {
    console.error("❌ Error abriendo modal de rejilla:", error);
    mostrarNotificacion("Error al abrir los detalles de la rejilla", "error");

    if (modalDetallesRejilla) {
      modalDetallesRejilla.hide();
    }
  }
}

/**
 * Cargar detalles específicos de una rejilla
 */
async function cargarDetallesRejilla(idRejilla) {
  try {
    const formData = new FormData();
    formData.append("accion", "obtener_detalles_rejilla");
    formData.append("id_rejilla", idRejilla);

    const response = await fetch(
      `${REJILLAS_CONFIG.urlBase}controller/rejillasController.php`,
      {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.exito) {
      mostrarDetallesRejilla(data.datos);
    } else {
      throw new Error(data.mensaje || "Error desconocido al cargar detalles");
    }
  } catch (error) {
    console.error("Error cargando detalles de rejilla:", error);
    mostrarErrorModal("Error al cargar los detalles: " + error.message);
  }
}

/**
 * Mostrar detalles de la rejilla en el modal
 */
function mostrarDetallesRejilla(datos) {
  try {
    const { rejilla, items_asignados } = datos;

    // Validar datos
    if (!rejilla) {
      throw new Error("Datos de rejilla no válidos");
    }

    // Actualizar título con descripción si existe
    const tituloElement = document.getElementById("tituloRejillaModal");
    if (rejilla.descripcion && tituloElement) {
      tituloElement.textContent += ` - ${rejilla.descripcion}`;
    }

    // Guardar datos en el estado
    appState.rejillaSeleccionada.itemsAsignados = items_asignados || [];

    // Guardar items originales para filtrado
    appState.itemsOriginales = items_asignados || [];
    appState.itemsFiltrados = items_asignados || [];

    // Resetear filtros
    resetearFiltros();

    // Generar HTML de detalles
    const html = generarHTMLDetallesRejilla(rejilla, items_asignados || []);

    // Mostrar contenido
    const contenidoElement = document.getElementById(
      "contenidoDetallesRejilla"
    );
    if (contenidoElement) {
      contenidoElement.innerHTML = html;
    }

    // Ocultar loading
    mostrarLoadingModal(false);

    // Inicializar componentes específicos del modal
    inicializarComponentesModal();

    // Inicializar sistema de filtros
    inicializarSistemaFiltros();

    console.log("✅ Detalles de rejilla mostrados correctamente");
  } catch (error) {
    console.error("❌ Error mostrando detalles de rejilla:", error);
    mostrarErrorModal("Error al mostrar los detalles");
  }
}

/**
 * Generar HTML para los detalles de la rejilla
 */
function generarHTMLDetallesRejilla(rejilla, itemsAsignados) {
  let html = `
    <!-- Información general de la rejilla -->
    <div class="row mb-3">
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h6 class="mb-1">
                  <i class="fas fa-list me-2 text-primary"></i>
                  Items Activos
                  <span class="badge bg-success ms-2" id="contadorItems">${
                    itemsAsignados.length
                  }</span>
                  <span class="badge bg-secondary ms-1" id="contadorFiltrados" style="display: none;"></span>
                </h6>
              </div>
            </div>
            
            <!-- Barra de búsqueda y filtros -->
            ${generarBarraBusquedaFiltros(itemsAsignados)}
            
            <!-- Contenedor de items -->
            <div id="contenedorItemsAsignados" class="mt-3">
  `;

  if (itemsAsignados && itemsAsignados.length > 0) {
    html += generarHTMLItemsAsignados(itemsAsignados);
  } else {
    html += `
      <div class="no-items text-center py-4">
        <div class="mb-3">
          <i class="fas fa-inbox fa-3x text-muted mb-2"></i>
          <h6 class="text-muted mb-1">No hay items activos</h6>
          <p class="text-muted mb-3 small">Esta rejilla está disponible para nuevas asignaciones</p>
        </div>
        <a href="${REJILLAS_CONFIG.urlBase}secciones/expedicion/expedicion.php" class="btn btn-primary">
          <i class="fas fa-plus me-2"></i>Asignar Productos
        </a>
      </div>
    `;
  }

  html += `
            </div>
            
            <!-- Mensaje cuando no hay resultados de filtrado -->
            <div id="sinResultadosFiltros" class="text-center py-4" style="display: none;">
              <i class="fas fa-search fa-2x text-muted mb-2"></i>
              <h6 class="text-muted mb-1">No se encontraron resultados</h6>
              <p class="text-muted mb-2 small">Intenta ajustar los filtros de búsqueda</p>
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="limpiarFiltros()">
                <i class="fas fa-times me-1"></i>Limpiar filtros
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

  return html;
}

/**
 * Generar HTML para los items asignados en formato tabla
 */
function generarHTMLItemsAsignados(items) {
  if (!items || items.length === 0) {
    return `
      <div class="no-items text-center py-3">
        <div class="mb-3">
          <i class="fas fa-inbox fa-3x text-muted mb-2"></i>
          <h6 class="text-muted mb-1">No hay items activos</h6>
          <p class="text-muted mb-3 small">Esta rejilla está disponible para nuevas asignaciones</p>
        </div>
      </div>
    `;
  }

  let html = `
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-dark sticky-top">
          <tr>
            <th style="width: 15%;">Cliente</th>
            <th style="width: 25%;">Producto</th>
            <th style="width: 12%; text-align: center;">Asignado</th>
            <th style="width: 12%; text-align: center;">Producido</th>
            <th style="width: 12%; text-align: center;">Despachado</th>
            <th style="width: 8%; text-align: center;">Acciones</th>
          </tr>
        </thead>
        <tbody>
  `;

  items.forEach((item) => {
    // Extraer datos básicos
    const cantidadUnidades = parseInt(item.cantidad_unidades_asignadas || 0);
    const pesoUnitario = parseFloat(item.peso_unitario || 0);
    const pesoReservado = parseFloat(item.cantidad_reservada || 0);
    const tipoUnidad =
      item.tipo_unidad || determinarTipoUnidad(item.nombre_producto || "");

    // Producción real
    const cantidadProducida = parseFloat(item.cantidad_producida || 0);
    const pesoTotalProducido = parseFloat(item.peso_total_producido_real || 0);

    // Información de despacho
    const despachado = parseFloat(item.despachado || 0);
    const pesoDespachado = parseFloat(item.peso_despachado || 0);

    // Estado de asignación
    const estadoAsignacion = item.estado_asignacion || "activa";
    let claseRow = "";
    if (estadoAsignacion === "completada") {
      claseRow = "table-secondary";
    } else if (estadoAsignacion === "cancelada") {
      claseRow = "table-danger";
    }

    html += `
      <tr class="${claseRow}" data-item-id="${
      item.id
    }" data-estado-asignacion="${estadoAsignacion}">
        <!-- CLIENTE -->
        <td>
          <div class="fw-bold text-primary" style="font-size: 0.85rem;">
            ${item.cliente || "N/D"}
          </div>
          <small class="text-muted">
            # Venta: ${item.id_venta}
          </small>
          <br>
          <small class="text-muted">
            ${formatearFecha(item.fecha_asignacion, true)}
          </small>
        </td>

        <!-- PRODUCTO -->
        <td>
          <div class="fw-bold" style="font-size: 0.85rem;" title="${
            item.nombre_producto || item.nombre_producto_presupuesto || "N/D"
          }">
            ${truncarTexto(
              item.nombre_producto || item.nombre_producto_presupuesto || "N/D",
              40
            )}
          </div>
          <small class="text-muted">
            Peso unit: ${formatearNumero(pesoUnitario)} kg
          </small>
        </td>

        <!-- ASIGNADO -->
        <td>
          <div class="text-center">
            <div class="fw-bold text-info" style="font-size: 0.9rem;">
              ${formatearNumero(cantidadUnidades)}
            </div>
            <small class="text-muted">${tipoUnidad}</small>
            <br>
            <div class="fw-bold text-secondary" style="font-size: 0.85rem;">
              ${formatearNumero(pesoReservado)} kg
            </div>
          </div>
        </td>

        <!-- PRODUCIDO -->
        <td>
          <div class="text-center">
            ${
              cantidadProducida > 0
                ? `
              <div class="fw-bold text-success" style="font-size: 0.9rem;">
                ${formatearNumero(cantidadProducida)}
              </div>
              <small class="text-muted">${tipoUnidad}</small>
              <br>
              <div class="fw-bold text-success" style="font-size: 0.85rem;">
                ${formatearNumero(pesoTotalProducido)} kg
              </div>
            `
                : `<span class="text-muted">-</span>`
            }
          </div>
        </td>

        <!-- DESPACHADO -->
        <td>
          <div class="text-center">
            ${
              despachado > 0 || pesoDespachado > 0
                ? `
              <div class="fw-bold text-warning" style="font-size: 0.9rem;">
                ${formatearNumero(despachado)}
              </div>
              <small class="text-muted">${tipoUnidad}</small>
              <br>
              <div class="fw-bold text-warning" style="font-size: 0.85rem;">
                ${formatearNumero(pesoDespachado)} kg
              </div>
            `
                : `<span class="text-muted">-</span>`
            }
          </div>
        </td>

        <!-- ACCIONES -->
        <td class="text-center">
          ${generarBotonesAccionTabla(item.id, estadoAsignacion)}
        </td>
      </tr>
    `;
  });

  html += `
        </tbody>
      </table>
    </div>
    
    <style>
      .table-sm td, .table-sm th {
        padding: 0.4rem 0.3rem;
        vertical-align: middle;
        border-bottom: 1px solid #dee2e6;
      }
      
      .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.05);
      }
      
      .table-secondary {
        background-color: rgba(108, 117, 125, 0.1) !important;
      }
      
      .table-danger {
        background-color: rgba(220, 53, 69, 0.1) !important;
      }
      
      .sticky-top {
        position: sticky !important;
        top: 0;
        z-index: 10;
      }
      
      .btn-tabla {
        padding: 0.15rem 0.3rem;
        font-size: 0.7rem;
        border-radius: 0.2rem;
      }
    </style>
  `;

  return html;
}

/**
 * Generar botones de acción para tabla
 */
function generarBotonesAccionTabla(idAsignacion, estadoAsignacion) {
  if (estadoAsignacion === "completada" || estadoAsignacion === "cancelada") {
    return `<span class="text-muted small">-</span>`;
  } else {
    return `
      <button type="button" class="btn btn-danger btn-tabla" 
              onclick="limpiarAsignacion(${idAsignacion})"
              title="Cancelar asignación">
        <i class="fas fa-times"></i> Cancelar
      </button>
    `;
  }
}

/**
 * Limpiar asignación (cambiar estado a "cancelada")
 */
async function limpiarAsignacion(idAsignacion) {
  if (
    !confirm(
      "¿Está seguro de que desea cancelar esta asignación? Esto cambiará su estado a 'cancelada' y liberará el peso asignado."
    )
  ) {
    return;
  }

  try {
    const formData = new FormData();
    formData.append("accion", "limpiar_item_asignacion");
    formData.append("id_asignacion", idAsignacion);

    const response = await fetch(
      `${REJILLAS_CONFIG.urlBase}controller/rejillasController.php`,
      {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    const data = await response.json();

    if (data.exito) {
      mostrarNotificacion("Asignación limpiada correctamente", "success");
      // Recargar detalles de la rejilla
      if (appState.rejillaSeleccionada) {
        await cargarDetallesRejilla(appState.rejillaSeleccionada.id);
      }
    } else {
      throw new Error(data.mensaje || "Error al limpiar asignación");
    }
  } catch (error) {
    console.error("❌ Error limpiando asignación:", error);
    mostrarNotificacion(
      "Error al limpiar asignación: " + error.message,
      "error"
    );
  }
}

/**
 * Mostrar/ocultar loading en modal
 */
function mostrarLoadingModal(mostrar) {
  const loadingElement = document.getElementById("loadingDetallesRejilla");
  const contenidoElement = document.getElementById("contenidoDetallesRejilla");

  if (loadingElement && contenidoElement) {
    if (mostrar) {
      loadingElement.style.display = "block";
      contenidoElement.style.display = "none";
    } else {
      loadingElement.style.display = "none";
      contenidoElement.style.display = "block";
    }
  }
}

/**
 * Mostrar error en modal
 */
function mostrarErrorModal(mensaje) {
  const contenidoElement = document.getElementById("contenidoDetallesRejilla");
  if (contenidoElement) {
    contenidoElement.innerHTML = `
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        ${mensaje}
      </div>
    `;
  }
  mostrarLoadingModal(false);
}

/**
 * Limpiar contenido del modal
 */
function limpiarModalDetalles() {
  const contenidoElement = document.getElementById("contenidoDetallesRejilla");
  if (contenidoElement) {
    contenidoElement.innerHTML = "";
  }

  // Resetear estado
  appState.rejillaSeleccionada = null;
}

/**
 * Inicializar componentes específicos del modal
 */
function inicializarComponentesModal() {
  // Inicializar tooltips si los hay
  const tooltips = document.querySelectorAll(
    '#contenidoDetallesRejilla [data-bs-toggle="tooltip"]'
  );
  tooltips.forEach((el) => {
    new bootstrap.Tooltip(el);
  });
}

// Exportar funciones para uso global
window.abrirModalRejilla = abrirModalRejilla;
window.limpiarAsignacion = limpiarAsignacion;
