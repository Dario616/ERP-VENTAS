// ===== VARIABLES GLOBALES =====
let searchTimeout;
let autoRefreshInterval;
let isLoading = false;
let currentFilters = {
  producto: "",
  tipo: "",
  stock_completo: "0",
  page: 1,
};
let debugMode = typeof STOCK_CONFIG !== "undefined" && STOCK_CONFIG.debug;

// ===== FUNCIÓN DE DEBUG =====
function logDebug(mensaje, datos = null) {
  if (debugMode) {
    console.log(`[STOCK_AGREGADO DEBUG] ${mensaje}`, datos || "");
  }
}

// ===== FUNCIÓN PARA MOSTRAR LOADING =====
function showLoading(mensaje = "Actualizando stock agregado...") {
  if (document.getElementById("loadingOverlay")) return;

  const loading = document.createElement("div");
  loading.id = "loadingOverlay";
  loading.className = "loading-overlay";
  loading.innerHTML = `
        <div class="text-center">
            <div class="loading-spinner"></div>
            <div class="mt-3">
                <h5><i class="fas fa-layer-group me-2"></i>${mensaje}</h5>
                <p class="text-muted">Conectando con el sistema de stock agregado...</p>
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

// ===== FUNCIÓN PARA BUSCAR STOCK VIA AJAX =====
function buscarStockAjax(filtros = {}) {
  if (isLoading) {
    logDebug("Búsqueda ignorada: ya hay una búsqueda en curso");
    return;
  }

  // Fusionar filtros actuales con los nuevos
  const filtrosFinales = { ...currentFilters, ...filtros };
  currentFilters = filtrosFinales;

  const tipoStock =
    filtrosFinales.stock_completo === "1" ? "completo" : "disponible";
  showLoading(`Consultando stock ${tipoStock}...`);

  // Construir parámetros de URL
  const params = new URLSearchParams({
    action: "buscar_stock",
    producto: filtrosFinales.producto || "",
    tipo: filtrosFinales.tipo || "",
    stock_completo: filtrosFinales.stock_completo || "0",
    page: filtrosFinales.page || 1,
    limit: 10,
  });

  logDebug("Iniciando búsqueda AJAX con filtros:", filtrosFinales);

  fetch(`?${params.toString()}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      hideLoading();

      if (data.success) {
        logDebug("Datos de stock agregado recibidos", data);
        actualizarTablaStock(data.datos);
        actualizarPaginacion(data.paginacion);
        actualizarEstadisticas(data.estadisticas);

        const tipoStock =
          filtrosFinales.stock_completo === "1" ? "completo" : "disponible";
        showToast(
          `✅ Stock ${tipoStock} actualizado - ${data.datos.length} productos mostrados`,
          "success",
          2000
        );
      } else {
        logDebug("Error en respuesta", data);
        showToast(`❌ ${data.error || "Error al cargar el stock"}`, "error");
      }
    })
    .catch((error) => {
      hideLoading();
      console.error("Error en búsqueda AJAX:", error);
      logDebug(`Error de conexión: ${error.message}`);
      showToast(`❌ Error de conexión: ${error.message}`, "error");
    });
}

// ===== FUNCIÓN PARA ACTUALIZAR TABLA DE STOCK =====
function actualizarTablaStock(datos) {
  const tbody = document.querySelector(".stock-table tbody");
  if (!tbody) return;

  if (!datos || datos.length === 0) {
    tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5">
                    <i class="fas fa-database text-muted mb-3" style="font-size: 2rem;"></i>
                    <div class="text-muted">No se encontraron productos</div>
                </td>
            </tr>
        `;
    return;
  }

  let html = "";
  datos.forEach((item, index) => {
    const tipo = item.configuracion_tipo || {};

    html += `
            <tr class="fade-in" style="animation-delay: ${index * 50}ms">
                <!-- Producto -->
                <td>
                    <div class="producto-info">
                        <div class="producto-nombre fw-medium">
                            ${escapeHtml(item.nombre_producto)}
                        </div>
                        ${
                          item.gramatura || item.largura || item.metragem
                            ? `
                            <div class="producto-specs">
                                <small class="text-muted">
                                    ${
                                      item.gramatura
                                        ? item.gramatura + "g/m²"
                                        : ""
                                    }
                                    ${
                                      item.largura
                                        ? "| " + item.largura + "cm"
                                        : ""
                                    }
                                    ${
                                      item.metragem
                                        ? "| " + item.metragem + "m"
                                        : ""
                                    }
                                </small>
                            </div>
                        `
                            : ""
                        }
                    </div>
                </td>

                <!-- Tipo -->
                <td>
                    <span class="badge badge-tipo" style="background-color: ${
                      tipo.color || "#6b7280"
                    };">
                        ${escapeHtml(item.tipo_producto || "")}
                    </span>
                </td>

                <!-- Bobinas/Paquete -->
                <td class="text-center">
                    <span class="badge bg-info">
                        ${item.bobinas_pacote_formateado || 1}
                    </span>
                </td>

                <!-- Total -->
                <td class="text-center fw-medium">
                    ${item.cantidad_total_formateada || "0"}
                </td>

                <!-- Disponible -->
                <td class="text-center">
                    <div class="cantidad-container">
                        ${
                          (item.cantidad_disponible || 0) === 0
                            ? `
                            <span class="text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                0
                            </span>
                        `
                            : `
                            <span class="fw-medium text-success">
                                ${item.cantidad_disponible_formateada || "0"}
                            </span>
                        `
                        }
                    </div>
                </td>

                <!-- Reservado -->
                <td class="text-center">
                    ${
                      (item.cantidad_reservada || 0) > 0
                        ? `
                        <span class="fw-medium text-warning">
                            ${item.cantidad_reservada_formateada || "0"}
                        </span>
                    `
                        : `<span class="text-muted">0</span>`
                    }
                </td>

                <!-- Despachado -->
                <td class="text-center">
                    ${
                      (item.cantidad_despachada || 0) > 0
                        ? `
                        <span class="fw-medium text-primary">
                            ${item.cantidad_despachada_formateada || "0"}
                        </span>
                    `
                        : `<span class="text-muted">0</span>`
                    }
                </td>

                <!-- Cantidad Paquetes -->
                <td class="text-center">
                    ${
                      (item.cantidad_paquetes || 0) > 0
                        ? `
                        <span class="fw-medium text-primary">
                            ${item.cantidad_paquetes_formateada || "0"}
                        </span>
                    `
                        : `<span class="text-muted">0</span>`
                    }
                </td>

                <!-- Última Actualización -->
                <td class="text-center">
                    <small class="text-muted">
                        ${new Date().toLocaleDateString("es-ES")}
                    </small>
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

  logDebug("Tabla de stock agregado actualizada", { productos: datos.length });
}

// ===== FUNCIÓN PARA ACTUALIZAR PAGINACIÓN =====
function actualizarPaginacion(paginacion) {
  const paginationContainer = document.querySelector(".pagination");
  if (!paginationContainer || !paginacion) return;

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
  const inicio = Math.max(1, paginacion.pagina_actual - 2);
  const fin = Math.min(paginacion.total_paginas, paginacion.pagina_actual + 2);

  for (let i = inicio; i <= fin; i++) {
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

  paginationContainer.innerHTML = html;

  // Actualizar información de paginación
  const paginationInfo = document.getElementById("paginationInfo");
  if (paginationInfo) {
    const inicio =
      (paginacion.pagina_actual - 1) * paginacion.registros_por_pagina + 1;
    const fin = Math.min(
      paginacion.pagina_actual * paginacion.registros_por_pagina,
      paginacion.total_registros
    );

    paginationInfo.textContent = `Mostrando ${inicio}-${fin} de ${paginacion.total_registros} productos`;
  }
}

// ===== FUNCIÓN PARA ACTUALIZAR ESTADÍSTICAS =====
function actualizarEstadisticas(estadisticas) {
  if (!estadisticas) return;

  // Actualizar contadores en cards de estadísticas
  const elementos = {
    totalProductos: estadisticas.total_productos,
    stockDisponible: estadisticas.disponible_total_formateado,
    stockReservado: estadisticas.reservado_total_formateado,
    stockDespachado: estadisticas.despachado_total_formateado,
  };

  Object.entries(elementos).forEach(([id, valor]) => {
    const elemento = document.getElementById(id);
    if (elemento) {
      elemento.textContent = valor || "0";
    }
  });

  logDebug("Estadísticas actualizadas", estadisticas);
}

// ===== FUNCIÓN PARA CAMBIAR PÁGINA =====
function cambiarPagina(nuevaPagina) {
  if (nuevaPagina < 1) return;

  currentFilters.page = nuevaPagina;
  buscarStockAjax({ page: nuevaPagina });

  // Hacer scroll hacia arriba
  window.scrollTo({ top: 0, behavior: "smooth" });
}

// ===== FUNCIÓN PARA MANEJAR AUTOCOMPLETADO =====
function manejarAutocompletado(input) {
  clearTimeout(searchTimeout);

  const valor = input.value.trim();

  if (valor.length >= 2) {
    searchTimeout = setTimeout(() => {
      buscarAutocompletado(valor);
    }, 300);
  } else {
    ocultarAutocompletado();
  }
}

// ===== FUNCIÓN PARA BUSCAR AUTOCOMPLETADO =====
function buscarAutocompletado(termino) {
  const params = new URLSearchParams({
    action: "filtrar_productos",
    termino: termino,
  });

  fetch(`?${params.toString()}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.productos) {
        mostrarAutocompletado(data.productos);
      }
    })
    .catch((error) => {
      logDebug("Error en autocompletado:", error);
    });
}

// ===== FUNCIÓN PARA MOSTRAR AUTOCOMPLETADO =====
function mostrarAutocompletado(productos) {
  let dropdown = document.getElementById("autocompleteDropdown");

  if (!dropdown) {
    dropdown = document.createElement("div");
    dropdown.id = "autocompleteDropdown";
    dropdown.className = "autocomplete-dropdown";
    document.body.appendChild(dropdown);
  }

  const input = document.querySelector('input[name="producto"]');
  if (!input) return;

  const rect = input.getBoundingClientRect();
  dropdown.style.cssText = `
        position: fixed;
        top: ${rect.bottom + window.scrollY}px;
        left: ${rect.left + window.scrollX}px;
        width: ${rect.width}px;
        max-height: 250px;
        overflow-y: auto;
        background: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        z-index: 1000;
    `;

  let html = "";
  productos.forEach((producto) => {
    html += `
            <div class="autocomplete-item p-3 hover:bg-gray-100 cursor-pointer border-bottom" 
                 onclick="seleccionarProducto('${escapeHtml(
                   producto.nombre
                 )}')">
                <div class="fw-bold">${escapeHtml(producto.nombre)}</div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <span class="badge bg-secondary me-1">${escapeHtml(
                          producto.tipo
                        )}</span>
                        ${producto.variantes} variante(s)
                    </small>
                    <small class="text-success fw-medium">
                        ${Number(
                          producto.cantidad_disponible
                        ).toLocaleString()} disponibles
                    </small>
                </div>
            </div>
        `;
  });

  dropdown.innerHTML = html;
  dropdown.style.display = "block";
}

// ===== FUNCIÓN PARA OCULTAR AUTOCOMPLETADO =====
function ocultarAutocompletado() {
  const dropdown = document.getElementById("autocompleteDropdown");
  if (dropdown) {
    dropdown.style.display = "none";
  }
}

// ===== FUNCIÓN PARA SELECCIONAR PRODUCTO =====
function seleccionarProducto(nombreProducto) {
  const input = document.querySelector('input[name="producto"]');
  if (input) {
    input.value = nombreProducto;
    aplicarFiltros();
  }
  ocultarAutocompletado();
}

// ===== FUNCIÓN PARA APLICAR FILTROS =====
function aplicarFiltros() {
  const productoInput = document.querySelector('input[name="producto"]');
  const tipoSelect = document.querySelector('select[name="tipo"]');
  const stockCompletoSelect = document.querySelector(
    'select[name="stock_completo"]'
  );

  const filtros = {
    producto: productoInput ? productoInput.value.trim() : "",
    tipo: tipoSelect ? tipoSelect.value : "",
    stock_completo: stockCompletoSelect ? stockCompletoSelect.value : "0",
    page: 1, // Resetear a primera página al filtrar
  };

  buscarStockAjax(filtros);
}

// ===== FUNCIÓN PARA LIMPIAR FILTROS =====
function limpiarFiltros() {
  const form = document.querySelector(".filtros-form");
  if (form) {
    form.reset();
  }

  currentFilters = { producto: "", tipo: "", stock_completo: "0", page: 1 };
  buscarStockAjax(currentFilters);
  ocultarAutocompletado();

  showToast("🔄 Filtros limpiados", "info", 2000);
}

// ===== FUNCIÓN PARA EXPORTAR DATOS =====
function exportarStock(formato = "csv") {
  const params = new URLSearchParams({
    action: "exportar_stock",
    formato: formato,
    producto: currentFilters.producto || "",
    tipo: currentFilters.tipo || "",
    stock_completo: currentFilters.stock_completo || "0",
  });

  showToast("📁 Preparando exportación...", "info", 2000);

  // Crear enlace de descarga
  const url = `?${params.toString()}`;
  const link = document.createElement("a");
  link.href = url;
  link.download = `stock_agregado_america_tnt_${
    new Date().toISOString().split("T")[0]
  }.${formato}`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  logDebug(`Exportación iniciada: ${formato}`);
}

// ===== FUNCIÓN PARA REFRESCAR AUTOMÁTICAMENTE =====
function iniciarAutoRefresh() {
  if (typeof STOCK_CONFIG !== "undefined" && STOCK_CONFIG.autoRefresh) {
    const intervalo = STOCK_CONFIG.refreshInterval || 60000; // 1 minuto por defecto

    autoRefreshInterval = setInterval(() => {
      if (!isLoading) {
        logDebug("Auto-refresh ejecutado");
        buscarStockAjax();
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

// ===== FUNCIÓN PARA MANEJAR EVENTOS DE TECLADO =====
function manejarEventosTeclado(e) {
  switch (e.key) {
    case "Escape":
      // Ocultar autocompletado y limpiar búsqueda si está vacía
      ocultarAutocompletado();
      const input = document.querySelector('input[name="producto"]');
      if (input && input.value.trim() === "") {
        limpiarFiltros();
      }
      break;

    case "F5":
      // Actualizar datos en lugar de recargar la página
      if (e.ctrlKey) {
        e.preventDefault();
        aplicarFiltros();
        showToast("🔄 Stock actualizado", "success", 2000);
      }
      break;

    case "Enter":
      // Aplicar filtros si está en el formulario de búsqueda
      if (e.target && e.target.name === "producto") {
        e.preventDefault();
        ocultarAutocompletado();
        aplicarFiltros();
      }
      break;
  }
}

// ===== FUNCIÓN HELPER PARA ESCAPE HTML =====
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text ? text.replace(/[&<>"']/g, (m) => map[m]) : "";
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
  logDebug("DOM cargado, inicializando sistema de stock agregado");

  // Configurar formulario de filtros
  const filtrosForm = document.querySelector(".filtros-form");
  if (filtrosForm) {
    filtrosForm.addEventListener("submit", function (e) {
      e.preventDefault();
      aplicarFiltros();
    });
  }

  // Configurar input de búsqueda
  const productoInput = document.querySelector('input[name="producto"]');
  if (productoInput) {
    productoInput.addEventListener("input", function () {
      manejarAutocompletado(this);
    });

    productoInput.addEventListener("blur", function () {
      // Delay para permitir clics en autocompletado
      setTimeout(() => ocultarAutocompletado(), 200);
    });
  }

  // Configurar select de stock completo para cambio automático
  const stockCompletoSelect = document.querySelector(
    'select[name="stock_completo"]'
  );
  if (stockCompletoSelect) {
    stockCompletoSelect.addEventListener("change", function () {
      // Aplicar filtros automáticamente cuando cambia la opción de stock completo
      aplicarFiltros();

      // Mostrar toast informativo
      const tipoStock = this.value === "1" ? "completo" : "solo disponibles";
      showToast(`📊 Mostrando stock ${tipoStock}`, "info", 2000);
    });
  }

  // Event listeners globales
  document.addEventListener("keydown", manejarEventosTeclado);

  // Clic fuera del autocompletado para ocultarlo
  document.addEventListener("click", function (e) {
    if (
      !e.target.closest(".autocomplete-dropdown") &&
      !e.target.closest('input[name="producto"]')
    ) {
      ocultarAutocompletado();
    }
  });

  // Inicializar auto-refresh
  iniciarAutoRefresh();

  // Inicializar manejo de conexión
  manejarEstadoConexion();

  // Cargar datos iniciales si no hay contenido
  const tbody = document.querySelector(".stock-table tbody");
  if (tbody && tbody.children.length === 0) {
    buscarStockAjax();
  }

  logDebug("Sistema de stock agregado inicializado completamente");
  console.log("🎯 Stock Agregado Management - Sistema listo v2.0");

  if (typeof STOCK_CONFIG !== "undefined") {
    console.log("📊 Configuración:", STOCK_CONFIG);
  }
});

// ===== FUNCIÓN DE LIMPIEZA AL SALIR =====
window.addEventListener("beforeunload", function () {
  detenerAutoRefresh();
  logDebug("Sistema de stock agregado desactivado");
});

// ===== EXPORTAR FUNCIONES GLOBALES =====
window.StockAgregadoApp = {
  buscarStock: buscarStockAjax,
  aplicarFiltros: aplicarFiltros,
  limpiarFiltros: limpiarFiltros,
  exportarStock: exportarStock,
  cambiarPagina: cambiarPagina,
  showToast: showToast,
  logDebug: logDebug,
  iniciarAutoRefresh: iniciarAutoRefresh,
  detenerAutoRefresh: detenerAutoRefresh,
};

logDebug("Stock Agregado JS cargado completamente - v2.0");
