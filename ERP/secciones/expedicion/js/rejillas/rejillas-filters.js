/**
 * Sistema de filtros y búsqueda para rejillas
 * Módulo encargado de la funcionalidad de filtrado en tiempo real
 */

/**
 * Generar barra de búsqueda y filtros
 */
function generarBarraBusquedaFiltros(itemsAsignados) {
  if (!itemsAsignados || itemsAsignados.length === 0) {
    return "";
  }

  // Extraer opciones únicas para los filtros
  const clientes = [
    ...new Set(itemsAsignados.map((item) => item.cliente).filter(Boolean)),
  ].sort();

  const productos = [
    ...new Set(
      itemsAsignados
        .map((item) => item.nombre_producto || item.nombre_producto_presupuesto)
        .filter(Boolean)
    ),
  ].sort();

  return `
    <div class="filtros-container mb-2 p-2 bg-light rounded border">
      <div class="row g-2">
        <!-- Búsqueda por texto -->
        <div class="col-md-4">
          <div class="input-group input-group-sm">
            <span class="input-group-text">
              <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" 
                   class="form-control form-control-sm" 
                   id="busquedaTexto" 
                   placeholder="Buscar cliente o producto..."
                   onkeyup="aplicarFiltros()">
          </div>
        </div>
        
        <!-- Filtro por cliente -->
        <div class="col-md-3">
          <select class="form-select form-select-sm" id="filtroCliente" onchange="aplicarFiltros()">
            <option value="">Todos los clientes</option>
            ${clientes
              .map(
                (cliente) => `<option value="${cliente}">${cliente}</option>`
              )
              .join("")}
          </select>
        </div>
        
        <!-- Filtro por producto -->
        <div class="col-md-4">
          <select class="form-select form-select-sm" id="filtroProducto" onchange="aplicarFiltros()">
            <option value="">Todos los productos</option>
            ${productos
              .map(
                (producto) =>
                  `<option value="${producto}" title="${producto}">${
                    producto.length > 35
                      ? producto.substring(0, 35) + "..."
                      : producto
                  }</option>`
              )
              .join("")}
          </select>
        </div>
        
        <!-- Botón limpiar -->
        <div class="col-md-1">
          <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarFiltros()" title="Limpiar filtros">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      
      <!-- Indicadores de filtros activos -->
      <div id="indicadoresFiltros" class="mt-1" style="display: none;">
        <small class="text-muted">
          <i class="fas fa-filter me-1"></i>
          Filtros activos: <span id="filtrosActivos"></span>
        </small>
      </div>
    </div>
    
    <!-- Estilos para la barra de filtros -->
    <style>
      .filtros-container {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1px solid #dee2e6;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
      }
      
      .filtros-container .form-control:focus,
      .filtros-container .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.25);
      }
      
      .filtros-container .input-group-text {
        background-color: #fff;
        border-color: #ced4da;
      }
      
      #indicadoresFiltros {
        padding: 4px 8px;
        background: rgba(13, 110, 253, 0.1);
        border-radius: 4px;
        border-left: 2px solid #0d6efd;
      }
    </style>
  `;
}

/**
 * Inicializar sistema de filtros
 */
function inicializarSistemaFiltros() {
  // Aplicar filtros iniciales (sin filtros)
  aplicarFiltros();

  // Configurar eventos adicionales
  const busquedaInput = document.getElementById("busquedaTexto");
  if (busquedaInput) {
    // Agregar evento para Enter
    busquedaInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        aplicarFiltros();
      }
    });
  }
}

/**
 * Aplicar filtros a los items
 */
function aplicarFiltros() {
  try {
    // Obtener valores de filtros
    const busquedaTexto = (
      document.getElementById("busquedaTexto")?.value || ""
    )
      .toLowerCase()
      .trim();
    const clienteSeleccionado =
      document.getElementById("filtroCliente")?.value || "";
    const productoSeleccionado =
      document.getElementById("filtroProducto")?.value || "";

    // Actualizar estado
    appState.filtros = {
      busquedaTexto,
      clienteSeleccionado,
      productoSeleccionado,
    };

    // Aplicar filtros
    let itemsFiltrados = [...appState.itemsOriginales];

    // Filtrar por texto de búsqueda
    if (busquedaTexto) {
      itemsFiltrados = itemsFiltrados.filter((item) => {
        const cliente = (item.cliente || "").toLowerCase();
        const producto = (
          item.nombre_producto ||
          item.nombre_producto_presupuesto ||
          ""
        ).toLowerCase();
        return (
          cliente.includes(busquedaTexto) || producto.includes(busquedaTexto)
        );
      });
    }

    // Filtrar por cliente
    if (clienteSeleccionado) {
      itemsFiltrados = itemsFiltrados.filter(
        (item) => item.cliente === clienteSeleccionado
      );
    }

    // Filtrar por producto
    if (productoSeleccionado) {
      itemsFiltrados = itemsFiltrados.filter((item) => {
        const producto =
          item.nombre_producto || item.nombre_producto_presupuesto || "";
        return producto === productoSeleccionado;
      });
    }

    // Actualizar estado filtrado
    appState.itemsFiltrados = itemsFiltrados;

    // Actualizar interfaz
    actualizarVistaFiltrada(itemsFiltrados);
    actualizarIndicadoresFiltros();
    actualizarContadores(itemsFiltrados.length);
  } catch (error) {
    console.error("❌ Error aplicando filtros:", error);
  }
}

/**
 * Actualizar vista con items filtrados
 */
function actualizarVistaFiltrada(itemsFiltrados) {
  const contenedor = document.getElementById("contenedorItemsAsignados");
  const sinResultados = document.getElementById("sinResultadosFiltros");

  if (!contenedor) return;

  if (itemsFiltrados.length === 0) {
    // Mostrar mensaje de sin resultados
    contenedor.style.display = "none";
    if (sinResultados) {
      sinResultados.style.display = "block";
    }
  } else {
    // Mostrar items filtrados
    contenedor.style.display = "block";
    if (sinResultados) {
      sinResultados.style.display = "none";
    }
    contenedor.innerHTML = generarHTMLItemsAsignados(itemsFiltrados);
  }
}

/**
 * Actualizar indicadores de filtros activos
 */
function actualizarIndicadoresFiltros() {
  const indicadores = document.getElementById("indicadoresFiltros");
  const filtrosActivos = document.getElementById("filtrosActivos");

  if (!indicadores || !filtrosActivos) return;

  const filtrosAplicados = [];

  if (appState.filtros.busquedaTexto) {
    filtrosAplicados.push(`Texto: "${appState.filtros.busquedaTexto}"`);
  }
  if (appState.filtros.clienteSeleccionado) {
    filtrosAplicados.push(`Cliente: ${appState.filtros.clienteSeleccionado}`);
  }
  if (appState.filtros.productoSeleccionado) {
    const productoCorto =
      appState.filtros.productoSeleccionado.length > 25
        ? appState.filtros.productoSeleccionado.substring(0, 25) + "..."
        : appState.filtros.productoSeleccionado;
    filtrosAplicados.push(`Producto: ${productoCorto}`);
  }

  if (filtrosAplicados.length > 0) {
    filtrosActivos.innerHTML = filtrosAplicados.join(", ");
    indicadores.style.display = "block";
  } else {
    indicadores.style.display = "none";
  }
}

/**
 * Actualizar contadores
 */
function actualizarContadores(cantidadFiltrados) {
  const contadorItems = document.getElementById("contadorItems");
  const contadorFiltrados = document.getElementById("contadorFiltrados");

  if (contadorItems) {
    contadorItems.textContent = appState.itemsOriginales.length;
  }

  if (contadorFiltrados) {
    if (cantidadFiltrados < appState.itemsOriginales.length) {
      contadorFiltrados.textContent = `${cantidadFiltrados} mostrados`;
      contadorFiltrados.style.display = "inline";
    } else {
      contadorFiltrados.style.display = "none";
    }
  }
}

/**
 * Limpiar filtros
 */
function limpiarFiltros() {
  // Limpiar controles
  const busquedaTexto = document.getElementById("busquedaTexto");
  const filtroCliente = document.getElementById("filtroCliente");
  const filtroProducto = document.getElementById("filtroProducto");

  if (busquedaTexto) busquedaTexto.value = "";
  if (filtroCliente) filtroCliente.value = "";
  if (filtroProducto) filtroProducto.value = "";

  // Resetear estado
  resetearFiltros();

  // Aplicar filtros (sin filtros = mostrar todos)
  aplicarFiltros();
}

// Exportar funciones para uso global
window.generarBarraBusquedaFiltros = generarBarraBusquedaFiltros;
window.inicializarSistemaFiltros = inicializarSistemaFiltros;
window.aplicarFiltros = aplicarFiltros;
window.limpiarFiltros = limpiarFiltros;
