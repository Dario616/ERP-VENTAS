// ⭐ VARIABLES GLOBALES ⭐

//nueva-orden.js - ACTUALIZADO CON CANTIDAD DIRECTA COMO BOBINAS
let searchTimeout;
let currentSelection = null;
let paginaActual = 1;
let debugMode = false; // Cambiar a true para activar debug

// ⭐ FUNCIÓN DE DEBUG ⭐
function logDebug(mensaje, datos = null) {
  if (debugMode) {
    console.log(`[DEBUG] ${mensaje}`, datos || "");
    updateDebugInfo(mensaje);
  }
}

// ⭐ FUNCIÓN PARA BUSCAR PRODUCTOS ⭐
function buscarProductos(termino) {
  if (termino.length < 2) {
    document.getElementById("productSuggestions").style.display = "none";
    return;
  }

  logDebug(`Buscando productos: "${termino}"`);
  document.getElementById("loadingIndicator").style.display = "block";

  fetch(`?action=buscar_productos&q=${encodeURIComponent(termino)}`)
    .then((response) => response.json())
    .then((productos) => {
      logDebug(`Encontrados ${productos.length} productos`);
      mostrarSugerencias(productos);
      document.getElementById("loadingIndicator").style.display = "none";
    })
    .catch((error) => {
      console.error("Error buscando productos:", error);
      logDebug(`Error en búsqueda: ${error.message}`);
      document.getElementById("loadingIndicator").style.display = "none";
    });
}

// ⭐ FUNCIÓN PARA MOSTRAR SUGERENCIAS CON INFO DE BOBINAS ⭐
function mostrarSugerencias(productos) {
  const container = document.getElementById("productSuggestions");

  if (productos.length === 0) {
    container.style.display = "none";
    mostrarNotificacionProductoRequerido();
    logDebug("No se encontraron productos");
    return;
  }

  let html = "";
  productos.forEach((producto) => {
    // ⭐ USAR TIPO DETECTADO SI ESTÁ DISPONIBLE ⭐
    const tipoMostrar = producto.tipo_detectado || producto.tipo || "SIN TIPO";
    const tipoColor = getTipoColor(tipoMostrar);
    const tipoIcon = getTipoIcon(tipoMostrar);

    // ⭐ MOSTRAR INFO DE BOBINAS PARA TNT/SPUNLACE/LAMINADORA ⭐
    const esTipoBobinas = ["TNT", "SPUNLACE", "LAMINADORA"].includes(
      tipoMostrar
    );
    const infoBobinas =
      esTipoBobinas && producto.stock_actual
        ? `<small class="bobina-info"><i class="fas fa-weight me-1"></i>${producto.stock_actual} kg/bobina</small>`
        : "";

    html += `
            <div class="suggestion-item" onclick="seleccionarProducto('${
              producto.id
            }', '${producto.descripcion.replace(
      /'/g,
      "\\'"
    )}', '${tipoMostrar}', '${producto.stock_actual}')">
                <div class="suggestion-main">${producto.descripcion}</div>
                <div class="suggestion-meta">
                    <span class="suggestion-type" style="background-color: ${tipoColor}20; color: ${tipoColor};">
                        <i class="fas ${tipoIcon} me-1"></i>${tipoMostrar}
                    </span>
                    ${infoBobinas}
                </div>
            </div>
        `;
  });

  container.innerHTML = html;
  container.style.display = "block";
  ocultarNotificacionProductoRequerido();
  logDebug(`Mostrando ${productos.length} sugerencias`);
}

// ⭐ FUNCIÓN PARA MOSTRAR NOTIFICACIÓN DE PRODUCTO REQUERIDO ⭐
function mostrarNotificacionProductoRequerido() {
  const notice = document.getElementById("requiredProductNotice");
  notice.style.display = "block";
}

// ⭐ FUNCIÓN PARA OCULTAR NOTIFICACIÓN DE PRODUCTO REQUERIDO ⭐
function ocultarNotificacionProductoRequerido() {
  const notice = document.getElementById("requiredProductNotice");
  notice.style.display = "none";
}

function convertirTextoVisual(texto) {
  // Cambiar "Kilos" por "Bobinas" solo visualmente
  if (texto.toLowerCase() === "kilos" || texto.toLowerCase() === "kg") {
    return "Bobinas";
  }
  return texto;
}

function cargarUnidadesProducto(idProducto) {
  const selectUnidades = document.getElementById("unidad_medida");
  const loadingIndicator = document.getElementById("unidadesLoading");
  const labelUnidad = document.getElementById("labelUnidadMedida");

  logDebug(`Cargando unidades para producto ID: ${idProducto}`);
  loadingIndicator.style.display = "block";

  fetch(`?action=obtener_unidades&id_producto=${idProducto}`)
    .then((response) => response.json())
    .then((unidades) => {
      selectUnidades.innerHTML = '<option value="">Seleccionar...</option>';

      if (unidades.length > 0) {
        unidades.forEach((unidad) => {
          const option = document.createElement("option");
          option.value = unidad.descripcion; // Valor real para el backend
          option.textContent = convertirTextoVisual(unidad.descripcion); // Texto visual convertido
          selectUnidades.appendChild(option);
        });

        labelUnidad.innerHTML =
          'Unidad * <span class="unidades-dynamic-label">(Específicas)</span>';
        logDebug(`Cargadas ${unidades.length} unidades específicas`);
      } else {
        cargarUnidadesGenerales();
      }

      loadingIndicator.style.display = "none";
    })
    .catch((error) => {
      console.error("Error cargando unidades específicas:", error);
      logDebug(`Error cargando unidades: ${error.message}`);
      cargarUnidadesGenerales();
      loadingIndicator.style.display = "none";
    });
}

// ⭐ FUNCIÓN MODIFICADA PARA CARGAR UNIDADES GENERALES ⭐
function cargarUnidadesGenerales() {
  const selectUnidades = document.getElementById("unidad_medida");
  const labelUnidad = document.getElementById("labelUnidadMedida");

  logDebug("Cargando unidades generales");

  fetch(`?action=obtener_unidades`)
    .then((response) => response.json())
    .then((unidades) => {
      selectUnidades.innerHTML = '<option value="">Seleccionar...</option>';

      unidades.forEach((unidad) => {
        const option = document.createElement("option");
        option.value = unidad.descripcion; // Valor real para el backend
        option.textContent = convertirTextoVisual(unidad.descripcion); // Texto visual convertido
        selectUnidades.appendChild(option);
      });

      labelUnidad.innerHTML = "Unidad *";
      logDebug(`Cargadas ${unidades.length} unidades generales`);
    })
    .catch((error) => {
      console.error("Error cargando unidades generales:", error);
      logDebug(`Error cargando unidades generales: ${error.message}`);
    });
}

// ⭐ FUNCIÓN PARA SELECCIONAR PRODUCTO ⭐
function seleccionarProducto(id, descripcion, tipo, stock) {
  // ⭐ ESTABLECER EXACTAMENTE LA DESCRIPCIÓN DEL PRODUCTO ⭐
  document.getElementById("descripcion").value = descripcion;
  document.getElementById("productSuggestions").style.display = "none";

  currentSelection = {
    id: id,
    descripcion: descripcion, // ⭐ GUARDAR DESCRIPCIÓN EXACTA ⭐
    tipo: tipo,
    stock_actual: stock,
  };

  logDebug(`Producto seleccionado: ${descripcion} (ID: ${id}, Tipo: ${tipo})`);

  document.getElementById("unidadMedidaContainer").style.display = "block";
  cargarUnidadesProducto(id);
  mostrarVistaPrevia(currentSelection);
  ocultarNotificacionProductoRequerido();
  validarFormulario();
}

// ⭐ FUNCIÓN PARA OBTENER DETALLES DEL PRODUCTO ⭐
function obtenerDetallesProducto(descripcion) {
  logDebug(`Obteniendo detalles para: "${descripcion}"`);

  fetch(
    `?action=detalles_producto&descripcion=${encodeURIComponent(descripcion)}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.encontrado) {
        currentSelection = data.producto;
        currentSelection.tipo = data.tipo_detectado || data.producto.tipo;

        document.getElementById("unidadMedidaContainer").style.display =
          "block";
        cargarUnidadesProducto(data.producto.id);
        mostrarVistaPrevia(currentSelection);
        ocultarNotificacionProductoRequerido();
        logDebug(
          `Producto encontrado: ${data.producto.descripcion} (Tipo: ${currentSelection.tipo})`
        );
      } else {
        currentSelection = null;
        document.getElementById("unidadMedidaContainer").style.display = "none";
        document.getElementById("previewBox").style.display = "none";
        mostrarNotificacionProductoRequerido();
        logDebug(`Producto no encontrado: "${descripcion}"`);
      }
      validarFormulario();
    })
    .catch((error) => {
      console.error("Error obteniendo detalles:", error);
      logDebug(`Error obteniendo detalles: ${error.message}`);
      currentSelection = null;
      document.getElementById("unidadMedidaContainer").style.display = "none";
      document.getElementById("previewBox").style.display = "none";
      mostrarNotificacionProductoRequerido();
      validarFormulario();
    });
}

// ⭐ FUNCIÓN ACTUALIZADA PARA MOSTRAR VISTA PREVIA CON INFO DE BOBINAS ⭐
function mostrarVistaPrevia(producto) {
  const previewBox = document.getElementById("previewBox");
  const statusIndicator = document.getElementById("statusIndicator");
  const productDetails = document.getElementById("productDetails");
  const previewTitle = document.getElementById("previewTitle");

  previewTitle.innerHTML =
    '<i class="fas fa-database me-2"></i>Producto Seleccionado:';

  statusIndicator.innerHTML = `
        <span class="status-badge status-found">
            <i class="fas fa-check-circle me-1"></i>Producto Válido
        </span>
        <span class="status-badge" style="background: ${getTipoColor(
          producto.tipo
        )}20; color: ${getTipoColor(producto.tipo)};">
            <i class="fas ${getTipoIcon(producto.tipo)} me-1"></i>${
    producto.tipo
  }
        </span>
    `;

  // ⭐ CORREGIR VISTA PREVIA - CANTIDAD DIRECTA COMO BOBINAS ⭐
  const esTipoBobinas = ["TNT", "SPUNLACE", "LAMINADORA"].includes(
    producto.tipo
  );
  const infoBobinasHtml =
    esTipoBobinas && producto.stock_actual
      ? `
        `
      : "";

  productDetails.innerHTML = `
        <div style="margin-top: 15px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; font-size: 12px;">
                <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e0e0e0; text-align: center;">
                    <span style="color: #666;">ID</span><br>
                    <strong style="color: #2c3e50;">${producto.id}</strong>
                </div>
                <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e0e0e0; text-align: center;">
                    <span style="color: #666;">Tipo</span><br>
                    <strong style="color: ${getTipoColor(producto.tipo)};">${
    producto.tipo
  }</strong>
                </div>
                ${
                  producto.stock_actual
                    ? `
                <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e0e0e0; text-align: center;">
                    <span style="color: #666;">Peso líquido</span><br>
                    <strong style="color: #2e7d32;">${producto.stock_actual} kg</strong>
                </div>
                `
                    : ""
                }
            </div>
            ${infoBobinasHtml}
        </div>
    `;

  previewBox.style.display = "block";
  logDebug(
    `Vista previa mostrada para producto ID: ${producto.id} (Con info bobinas: ${esTipoBobinas})`
  );
}

// ⭐ FUNCIONES AUXILIARES ACTUALIZADAS CON SOPORTE COMPLETO ⭐
function getTipoColor(tipo) {
  const colors = {
    TNT: "#1976d2",
    SPUNLACE: "#7b1fa2",
    LAMINADORA: "#ff9800", // ⭐ COLOR PARA LAMINADORA ⭐
    TOALLITAS: "#388e3c",
    PAÑOS: "#fd7e14",
  };
  return colors[tipo] || "#666";
}

function getTipoIcon(tipo) {
  const icons = {
    TNT: "fa-industry",
    SPUNLACE: "fa-fabric",
    LAMINADORA: "fa-layer-group", // ⭐ ICONO PARA LAMINADORA ⭐
    TOALLITAS: "fa-soap",
    PAÑOS: "fa-handshirt",
  };
  return icons[tipo] || "fa-box";
}

function validarFormulario() {
  const descripcionCampo = document.getElementById("descripcion").value.trim();
  const cantidad = parseFloat(document.getElementById("cantidad").value);
  let unidadMedida = "";

  if (
    document.getElementById("unidadMedidaContainer").style.display !== "none"
  ) {
    unidadMedida = document.getElementById("unidad_medida").value;
  }

  // ⭐ VALIDACIÓN ESTRICTA: La descripción debe coincidir EXACTAMENTE ⭐
  const productoValido =
    currentSelection &&
    currentSelection.id &&
    currentSelection.descripcion === descripcionCampo;

  const esValido =
    productoValido &&
    descripcionCampo.length > 5 &&
    cantidad > 0 &&
    unidadMedida;

  const btnCrear = document.getElementById("btnCrearOrden");
  btnCrear.disabled = !esValido;

  if (esValido) {
    btnCrear.style.opacity = "1";
    btnCrear.style.cursor = "pointer";

    // Mostrar cálculo si aplica
    if (
      ["TNT", "SPUNLACE", "LAMINADORA"].includes(currentSelection.tipo) &&
      currentSelection.stock_actual
    ) {
      const pesoTotal = (
        cantidad * parseFloat(currentSelection.stock_actual)
      ).toFixed(2);
      updateDebugInfo(
        `${cantidad} bobinas × ${currentSelection.stock_actual} kg = ${pesoTotal} kg total`
      );
    }

    logDebug("Formulario válido - Producto verificado");
  } else {
    btnCrear.style.opacity = "0.6";
    btnCrear.style.cursor = "not-allowed";

    // ⭐ MOSTRAR MENSAJE ESPECÍFICO SI HAY DISCREPANCIA ⭐
    if (currentSelection && currentSelection.descripcion !== descripcionCampo) {
      updateDebugInfo(
        "⚠️ Descripción modificada - debe coincidir exactamente con producto seleccionado"
      );
    }
  }
}

// ⭐ FUNCIÓN PARA MANEJAR EL ENVÍO DEL FORMULARIO ⭐
function manejarEnvioFormulario(e) {
  e.preventDefault();

  const descripcionCampo = document.getElementById("descripcion").value.trim();

  // ⭐ VALIDACIÓN DOBLE: EXISTENCIA Y COINCIDENCIA EXACTA ⭐
  if (!currentSelection || !currentSelection.id) {
    alert("Error: Debe seleccionar un producto existente de la base de datos.");
    return;
  }

  if (currentSelection.descripcion !== descripcionCampo) {
    alert(
      "Error: La descripción fue modificada. Debe seleccionar el producto exacto de las sugerencias."
    );
    return;
  }

  // ⭐ AGREGAR ID DEL PRODUCTO AL FORMULARIO ⭐
  const form = e.target;
  const formData = new FormData(form);

  // Agregar ID del producto seleccionado para validación adicional en backend
  formData.append("id_producto_seleccionado", currentSelection.id);

  const btnCrear = document.getElementById("btnCrearOrden");

  logDebug(
    `Enviando formulario - Producto ID: ${currentSelection.id}, Descripción: "${currentSelection.descripcion}"`
  );

  btnCrear.disabled = true;
  btnCrear.innerHTML =
    '<i class="fas fa-spinner fa-spin me-2"></i>Creando orden...';

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        logDebug(`Orden creada exitosamente: ID ${data.id_orden}`);

        window.open(data.pdf_url, "_blank");

        btnCrear.innerHTML = '<i class="fas fa-check me-2"></i>¡Orden creada!';
        btnCrear.style.background =
          "linear-gradient(135deg, #28a745 0%, #20c997 100%)";

        setTimeout(() => {
          limpiarFormulario();
          cargarOrdenes(1);
        }, 1000);
      } else {
        throw new Error(data.error || "Error desconocido");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error: " + error.message);

      btnCrear.innerHTML =
        '<i class="fas fa-exclamation-triangle me-2"></i>Error';
      btnCrear.style.background = "#dc3545";
      btnCrear.disabled = false;

      setTimeout(() => {
        btnCrear.innerHTML =
          '<i class="fas fa-cogs me-2"></i>Crear Orden de Producción';
        btnCrear.style.background =
          "linear-gradient(135deg, #28a745 0%, #20c997 100%)";
        validarFormulario();
      }, 3000);
    });
}

// ⭐ FUNCIÓN MEJORADA PARA LIMPIAR FORMULARIO ⭐
function limpiarFormulario() {
  document.getElementById("descripcion").value = "";
  document.getElementById("cantidad").value = "";
  document.getElementById("observaciones").value = "";
  document.getElementById("unidad_medida").value = "";
  document.getElementById("unidadMedidaContainer").style.display = "none";
  document.getElementById("previewBox").style.display = "none";
  document.getElementById("productSuggestions").style.display = "none";

  // ⭐ LIMPIAR COMPLETAMENTE LA SELECCIÓN ⭐
  currentSelection = null;
  ocultarNotificacionProductoRequerido();

  const btnCrear = document.getElementById("btnCrearOrden");
  btnCrear.innerHTML =
    '<i class="fas fa-cogs me-2"></i>Crear Orden de Producción';
  btnCrear.style.background =
    "linear-gradient(135deg, #28a745 0%, #20c997 100%)";

  validarFormulario();
  logDebug("Formulario limpiado completamente");
}
// ⭐ FUNCIÓN PARA LIMPIAR FORMULARIO ⭐
function limpiarFormulario() {
  document.getElementById("descripcion").value = "";
  document.getElementById("cantidad").value = "";
  document.getElementById("observaciones").value = "";
  document.getElementById("unidad_medida").value = "";
  document.getElementById("unidadMedidaContainer").style.display = "none";
  document.getElementById("previewBox").style.display = "none";

  currentSelection = null;
  ocultarNotificacionProductoRequerido();

  const btnCrear = document.getElementById("btnCrearOrden");
  btnCrear.innerHTML =
    '<i class="fas fa-cogs me-2"></i>Crear Orden de Producción';
  btnCrear.style.background =
    "linear-gradient(135deg, #28a745 0%, #20c997 100%)";

  validarFormulario();
  logDebug("Formulario limpiado");
}

// ⭐ FUNCIÓN PARA CARGAR ÓRDENES DE PRODUCCIÓN ⭐
function cargarOrdenes(pagina = 1) {
  paginaActual = pagina;

  const ordenFiltro = document.getElementById("filterOrden").value.trim();
  let url = `?action=obtener_ordenes&pagina=${pagina}&limite=${ITEMS_POR_PAGINA}`;
  if (ordenFiltro) url += `&orden=${encodeURIComponent(ordenFiltro)}`;

  logDebug(
    `Cargando órdenes página ${pagina}${
      ordenFiltro ? `, filtro: ${ordenFiltro}` : ""
    }`
  );

  document.getElementById("ordersLoading").style.display = "block";
  document.getElementById("ordersList").innerHTML = "";
  document.getElementById("paginationContainer").style.display = "none";

  fetch(url)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      document.getElementById("ordersLoading").style.display = "none";
      if (data.ordenes) {
        mostrarOrdenes(data.ordenes);
        mostrarPaginacion(data.paginas, data.pagina_actual);
        logDebug(`Cargadas ${data.ordenes.length} órdenes`);
      } else {
        throw new Error("Datos de órdenes inválidos");
      }
    })
    .catch((error) => {
      console.error("Error cargando órdenes:", error);
      logDebug(`Error cargando órdenes: ${error.message}`);
      document.getElementById("ordersLoading").style.display = "none";
      document.getElementById("ordersList").innerHTML = `
                <div class="text-center text-danger p-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error al cargar las órdenes: ${error.message}
                    <br>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="cargarOrdenes(${pagina})">
                        <i class="fas fa-redo me-1"></i>Reintentar
                    </button>
                </div>
            `;
    });
}

// ⭐ FUNCIÓN PARA MOSTRAR ÓRDENES ⭐
function mostrarOrdenes(ordenes) {
  const container = document.getElementById("ordersList");

  if (ordenes.length === 0) {
    container.innerHTML = `
            <div class="text-center text-muted p-4">
                <i class="fas fa-inbox me-2"></i>
                No hay órdenes de producción
            </div>
        `;
    return;
  }

  let html = "";
  ordenes.forEach((orden) => {
    const fecha = new Date(orden.fecha_orden).toLocaleDateString("es-ES", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });

    const tipoColor = getTipoColor(orden.tipo_producto);
    const tipoIcon = getTipoIcon(orden.tipo_producto);
    const estadoClass =
      orden.estado === "Orden Emitida" ? "success" : "secondary";

    html += `
            <div class="order-item">
                <div class="order-header">
                    <span class="order-id">Orden #${orden.id}</span>
                    <span class="order-date">${fecha}</span>
                </div>
                <div class="order-product">${
                  orden.nombre_producto || "Sin descripción"
                }</div>
                <div class="order-details">
                    <div>
                        <span class="order-type" style="background-color: ${tipoColor}20; color: ${tipoColor};">
                            <i class="fas ${tipoIcon} me-1"></i>${
      orden.tipo_producto
    }
                        </span>
                        <span class="badge bg-${estadoClass} ms-2">${
      orden.estado
    }</span>
                    </div>
                    <span class="order-quantity">
                        <i class="fas fa-cube me-1"></i>${
                          orden.cantidad_total || "N/A"
                        }
                    </span>
                </div>
                <div class="order-actions">
                    <button class="btn btn-pdf" onclick="generarPDF(${
                      orden.id
                    }, '${orden.tipo_producto}')">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </button>
                    <small class="text-muted ms-2">${
                      orden.cliente || "AMERICA TNT"
                    }</small>
                </div>
            </div>
        `;
  });

  container.innerHTML = html;
}

// ⭐ FUNCIÓN PARA MOSTRAR PAGINACIÓN ⭐
function mostrarPaginacion(totalPaginas, paginaActual) {
  if (totalPaginas <= 1) {
    document.getElementById("paginationContainer").style.display = "none";
    return;
  }

  const pagination = document.getElementById("pagination");
  let html = "";

  if (paginaActual > 1) {
    html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="cargarOrdenes(${
                  paginaActual - 1
                })">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
  }

  const maxPaginas = 5;
  let inicio = Math.max(1, paginaActual - Math.floor(maxPaginas / 2));
  let fin = Math.min(totalPaginas, inicio + maxPaginas - 1);

  if (fin - inicio + 1 < maxPaginas) {
    inicio = Math.max(1, fin - maxPaginas + 1);
  }

  for (let i = inicio; i <= fin; i++) {
    html += `
            <li class="page-item ${i === paginaActual ? "active" : ""}">
                <a class="page-link" href="#" onclick="cargarOrdenes(${i})">${i}</a>
            </li>
        `;
  }

  if (paginaActual < totalPaginas) {
    html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="cargarOrdenes(${
                  paginaActual + 1
                })">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
  }

  pagination.innerHTML = html;
  document.getElementById("paginationContainer").style.display = "block";
}

// ⭐ FUNCIÓN ACTUALIZADA PARA GENERAR PDF (incluye LAMINADORA) ⭐
function generarPDF(idOrden, tipoProducto) {
  let pdfUrl = "";

  if (tipoProducto === "TOALLITAS") {
    pdfUrl = `pdf/ordenToallitas.php?id_orden=${idOrden}`;
  } else if (tipoProducto === "SPUNLACE") {
    pdfUrl = `pdf/ordenSpunlace.php?id_orden=${idOrden}`;
  } else if (tipoProducto === "LAMINADORA") {
    // ⭐ PDF para LAMINADORA ⭐
    pdfUrl = `pdf/ordenTNT.php?id_orden=${idOrden}`;
  } else if (tipoProducto === "PAÑOS") {
    pdfUrl = `pdf/produccionpanos.php?id_orden=${idOrden}`;
  } else {
    pdfUrl = `pdf/ordenTNT.php?id_orden=${idOrden}`;
  }

  logDebug(`Abriendo PDF: ${pdfUrl}`);
  window.open(pdfUrl, "_blank");
}

// ⭐ INICIALIZACIÓN CUANDO SE CARGA EL DOM ⭐
document.addEventListener("DOMContentLoaded", function () {
  const descripcionField = document.getElementById("descripcion");

  descripcionField.addEventListener("input", function () {
    const termino = this.value.trim();

    clearTimeout(searchTimeout);

    // ⭐ LIMPIAR SELECCIÓN SI SE MODIFICA EL TEXTO ⭐
    if (currentSelection && currentSelection.descripcion !== termino) {
      console.log("⚠️ Descripción modificada, limpiando selección");
      currentSelection = null;
      document.getElementById("previewBox").style.display = "none";
      document.getElementById("unidadMedidaContainer").style.display = "none";
      mostrarNotificacionProductoRequerido();
    }

    if (termino.length >= 2) {
      searchTimeout = setTimeout(() => {
        buscarProductos(termino);
      }, 300);
    } else {
      document.getElementById("productSuggestions").style.display = "none";
      document.getElementById("previewBox").style.display = "none";
      document.getElementById("unidadMedidaContainer").style.display = "none";
      currentSelection = null;
      ocultarNotificacionProductoRequerido();
    }

    validarFormulario();
  });

  descripcionField.addEventListener("blur", function () {
    setTimeout(() => {
      const descripcion = this.value.trim();

      // Solo buscar detalles si no hay selección actual y el texto es diferente
      if (descripcion.length > 2 && !currentSelection) {
        obtenerDetallesProducto(descripcion);
      }

      // ⭐ VALIDAR QUE LA DESCRIPCIÓN COINCIDA CON LA SELECCIÓN ⭐
      if (currentSelection && currentSelection.descripcion !== descripcion) {
        console.warn(
          "⚠️ La descripción no coincide con el producto seleccionado"
        );
        mostrarAdvertenciaDescripcionModificada();
      }
    }, 300);
  });

  function mostrarAdvertenciaDescripcionModificada() {
    const notice = document.getElementById("requiredProductNotice");
    notice.innerHTML = `
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Texto modificado:</strong> Debe seleccionar un producto exacto de la lista de sugerencias.
  `;
    notice.style.display = "block";
    notice.style.backgroundColor = "#fff3cd";
    notice.style.borderColor = "#ffeaa7";
    notice.style.color = "#856404";
  }

  // ⭐ OCULTAR SUGERENCIAS AL HACER CLIC FUERA ⭐
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".product-input-container")) {
      document.getElementById("productSuggestions").style.display = "none";
    }
  });

  // ⭐ VALIDAR FORMULARIO EN TIEMPO REAL ⭐
  ["descripcion", "cantidad", "unidad_medida"].forEach((campoId) => {
    const elemento = document.getElementById(campoId);
    if (elemento) {
      elemento.addEventListener("input", validarFormulario);
      elemento.addEventListener("change", validarFormulario);
    }
  });

  // ⭐ INTERCEPTAR ENVÍO DEL FORMULARIO ⭐
  const form = document.getElementById("formNuevaOrden");
  if (form) {
    form.addEventListener("submit", manejarEnvioFormulario);
  }

  // ⭐ BOTÓN DE FILTRAR ⭐
  document.getElementById("btnFiltrar").addEventListener("click", () => {
    cargarOrdenes(1);
  });

  // ⭐ FILTRO POR ENTER ⭐
  document.getElementById("filterOrden").addEventListener("keypress", (e) => {
    if (e.key === "Enter") {
      cargarOrdenes(1);
    }
  });

  // Validación inicial
  validarFormulario();

  // Cargar unidades generales al inicio
  cargarUnidadesGenerales();

  // Cargar órdenes al inicio
  cargarOrdenes(1);

  logDebug(
    "Sistema inicializado completamente con cantidad directa como bobinas"
  );
  console.log("Nueva Orden de Producción - Sistema listo");
  console.log("Tipos soportados:", TIPOS_SOPORTADOS);
  console.log("Cantidad directa como bobinas: TNT, SPUNLACE, LAMINADORA");
  console.log("Sin conversión: TOALLITAS, PAÑOS");
});
