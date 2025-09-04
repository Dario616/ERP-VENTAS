// =====================================
// DESPACHO SCANNER - Scanner y procesamiento de items
// =====================================
let itemsEscaneadosData = [];
let itemsSeleccionados = new Set();
// ===== ABRIR SCANNER =====
function abrirScanner(numeroExpedicion) {
  logDebug("Abriendo scanner para expedición", numeroExpedicion);

  expedicionActiva = numeroExpedicion;
  document.getElementById("expedicionActiva").textContent = numeroExpedicion;

  modalScanner.show();

  setTimeout(() => {
    const input = document.getElementById("barcodeInput");
    if (input) {
      input.focus();
      input.value = "";
    }

    actualizarIndicadorRejillaExpedicion();
    cargarVistaPreviaClientes(numeroExpedicion);
    logDebug("Scanner configurado y listo");
  }, 500);
}

const abrirScannerOriginal = window.abrirScanner;
window.abrirScanner = function (numeroExpedicion) {
  // Llamar función original
  if (abrirScannerOriginal) {
    abrirScannerOriginal(numeroExpedicion);
  }

  // Cargar lista de items escaneados
  setTimeout(() => {
    cargarItemsEscaneadosDetallados(numeroExpedicion);
  }, 1000);
};

logDebug("Sistema de gestión de items escaneados cargado");

// ===== ACTUALIZAR INDICADOR DE REJILLA =====
function actualizarIndicadorRejillaExpedicion() {
  if (!expedicionActiva) {
    return;
  }

  const formData = new FormData();
  formData.append("accion", "obtener_info_expedicion");
  formData.append("numero_expedicion", expedicionActiva);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.expedicion.numero_rejilla) {
        mostrarIndicadorRejilla(data.expedicion.numero_rejilla);
      }
    })
    .catch((error) => {
      logDebug("Error obteniendo info de expedición", error);
    });
}

function mostrarIndicadorRejilla(numeroRejilla) {
  let indicador = document.getElementById("indicadorRejillaExpedicion");

  if (!indicador) {
    const contenedorScanner = document.querySelector(
      "#modalEscanear .col-md-4"
    );
    if (contenedorScanner) {
      const indicadorHtml = `
        <div id="indicadorRejillaExpedicion" class="alert alert-info mb-3">
          <i class="fas fa-map-marker-alt me-2"></i>
          <strong>Rejilla asignada: #${numeroRejilla}</strong><br>
          <small>Items de esta rejilla: asignación automática</small><br>
        </div>
      `;

      const alertSuccess = contenedorScanner.querySelector(".alert-success");
      if (alertSuccess) {
        alertSuccess.insertAdjacentHTML("afterend", indicadorHtml);
      }
    }
  } else {
    indicador.innerHTML = `
      <i class="fas fa-map-marker-alt me-2"></i>
      <strong>Rejilla asignada: #${numeroRejilla}</strong><br>
      <small>Items de esta rejilla: asignación automática</small><br>
      <small class="text-warning">📍 Items fuera de rejilla: → DESCONOCIDOS</small><br>
      <small class="text-success">📊 Considera despachos anteriores</small>
    `;
    indicador.style.display = "block";
  }
}

// ===== PROCESAR CÓDIGO ESCANEADO =====
function procesarCodigoEscaneado(codigo) {
  const input = document.getElementById("barcodeInput");

  if (!codigo || !expedicionActiva) {
    logDebug("Código vacío o no hay expedición activa");
    return;
  }

  if (isLoading) {
    logDebug("Ya hay una operación en curso, ignorando escaneo");
    return;
  }

  if (!/^\d+$/.test(codigo)) {
    mostrarToast("❌ El código debe ser numérico", "warning");
    input.value = "";
    input.focus();
    return;
  }

  logDebug("Procesando código escaneado", {
    codigo,
    expedicion: expedicionActiva,
  });

  escanearItem(codigo);
  input.value = "";
}

// ===== CARGAR ITEMS ESCANEADOS DETALLADOS =====
function cargarItemsEscaneadosDetallados(numeroExpedicion) {
  if (!numeroExpedicion || !expedicionActiva) {
    logDebug("No hay expedición activa para cargar items");
    return;
  }

  const formData = new FormData();
  formData.append("accion", "obtener_items_escaneados_detallados");
  formData.append("numero_expedicion", numeroExpedicion);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        itemsEscaneadosData = data.items || [];
        renderizarListaItemsEscaneados(data.items);
        actualizarContadorItems(data.items.length);
      } else {
        console.error("Error cargando items:", data.error);
        mostrarToast("❌ Error cargando lista de items", "danger");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      mostrarToast("❌ Error de conexión", "danger");
    });
}

// ===== RENDERIZAR LISTA DE ITEMS =====
// ===== ACTUALIZAR ESTADO DE BOTONES Y SUMADORES =====
function actualizarEstadoBotonesSeleccion() {
  const btnEliminar = document.getElementById("btnEliminarSeleccionados");
  const contador = document.getElementById("contadorSeleccionados");
  const contadorBobinas = document.getElementById(
    "contadorBobinasSeleccionadas"
  );
  const resumenSeleccion = document.getElementById("resumenSeleccion");

  if (btnEliminar && contador) {
    const totalSeleccionados = itemsSeleccionados.size;

    // Calcular total de bobinas seleccionadas
    let totalBobinas = 0;
    let pesoTotalSeleccionado = 0;

    // Iterar sobre los items seleccionados y sumar las bobinas
    itemsSeleccionados.forEach((itemId) => {
      const item = itemsEscaneadosData.find(
        (i) => i.expedicion_item_id === itemId
      );
      if (item) {
        totalBobinas += parseInt(item.bobinas_pacote) || 1;
        // Extraer el peso numérico para sumarlo
        const pesoMatch = item.peso_formateado.match(/[\d,]+/);
        if (pesoMatch) {
          const peso = parseFloat(pesoMatch[0].replace(",", ""));
          pesoTotalSeleccionado += peso;
        }
      }
    });

    // Actualizar contadores
    contador.textContent = totalSeleccionados;
    if (contadorBobinas) {
      contadorBobinas.textContent = totalBobinas;
    }

    // Actualizar resumen de selección
    if (resumenSeleccion) {
      if (totalSeleccionados > 0) {
        resumenSeleccion.innerHTML = `
          <div class="alert alert-info py-2 mb-2">
            <div class="row text-center">
              <div class="col-4">
                <strong>${totalSeleccionados}</strong><br>
                <small class="text-muted">Items</small>
              </div>
              <div class="col-4">
                <strong>${totalBobinas}</strong><br>
                <small class="text-muted">Bobinas</small>
              </div>
              <div class="col-4">
                <strong>${pesoTotalSeleccionado.toLocaleString(
                  "es-PY"
                )} kg</strong><br>
                <small class="text-muted">Peso Total</small>
              </div>
            </div>
          </div>
        `;
        resumenSeleccion.style.display = "block";
      } else {
        resumenSeleccion.style.display = "none";
      }
    }

    // Habilitar/deshabilitar botón de eliminar
    btnEliminar.disabled = totalSeleccionados === 0;

    if (totalSeleccionados > 0) {
      btnEliminar.classList.remove("btn-outline-danger");
      btnEliminar.classList.add("btn-danger");
    } else {
      btnEliminar.classList.remove("btn-danger");
      btnEliminar.classList.add("btn-outline-danger");
    }
  }
}

// ===== RENDERIZAR LISTA DE ITEMS (MODIFICADA) =====
function renderizarListaItemsEscaneados(items) {
  const contenedor = document.getElementById("listaItemsEscaneados");
  if (!contenedor) {
    console.error("Contenedor listaItemsEscaneados no encontrado");
    return;
  }

  if (!items || items.length === 0) {
    contenedor.innerHTML = `
      <div class="text-center text-muted p-4">
        <i class="fas fa-box-open fa-2x mb-2"></i>
        <p>No hay items escaneados aún</p>
      </div>
    `;
    return;
  }

  let html = `
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0">
        <i class="fas fa-list me-1"></i>
        Items Escaneados (${items.length})
      </h6>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-primary" onclick="seleccionarTodosItems()">
          <i class="fas fa-check-square"></i> Todos
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="limpiarSeleccionItems()">
          <i class="fas fa-square"></i> Ninguno
        </button>
        <button type="button" class="btn btn-outline-danger" onclick="eliminarItemsSeleccionados()" 
                id="btnEliminarSeleccionados" disabled>
          <i class="fas fa-trash"></i> Eliminar 
          (<span id="contadorSeleccionados">0</span> items, 
          <span id="contadorBobinasSeleccionadas">0</span> bobinas)
        </button>
      </div>
    </div>
    <!-- Resumen de selección -->
    <div id="resumenSeleccion" style="display: none;"></div>
    
    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
      <table class="table table-sm table-hover">
        <thead class="table-light sticky-top">
          <tr>
            <th width="40">
              <input type="checkbox" id="selectAllItems" onchange="toggleSeleccionTodos()">
            </th>
            <th>Etiqueta <i class="fas fa-search text-muted ms-1" title="Buscar por este campo"></i></th>
            <th>Producto</th>
            <th>Bobinas</th>
            <th>Metragem</th>
            <th>Cliente</th>
            <th>Peso</th>
            <th>Estado</th>
            <th width="80">Acciones</th>
          </tr>
        </thead>
        <tbody>
  `;

  items.forEach((item, index) => {
    const esDesconocido = item.es_desconocido;
    const fueraDeRejilla = item.modo_asignacion === "desconocido_fuera_rejilla";

    // Determinar clase y icono según el estado
    let claseFilaEstado = "";
    let iconoEstado = "✅";
    let textoEstado = "Asignado";

    if (esDesconocido) {
      if (fueraDeRejilla) {
        claseFilaEstado = "table-info";
        iconoEstado = "📍";
        textoEstado = "Fuera Rejilla";
      } else {
        claseFilaEstado = "table-warning";
        iconoEstado = "❓";
        textoEstado = "DESCONOCIDO";
      }
    }

    html += `
      <tr class="${claseFilaEstado}" data-item-id="${
      item.expedicion_item_id
    }" data-etiqueta="${escapeHtml(item.stock_id)}">
        <td>
          <input type="checkbox" class="item-checkbox" value="${
            item.expedicion_item_id
          }" 
                 onchange="actualizarSeleccionItem(this)">
        </td>
        <td>
          <strong class="etiqueta-searchable">${escapeHtml(
            item.stock_id
          )}</strong>
          <br><small class="text-muted">${item.tiempo_transcurrido}</small>
        </td>
        <td>
          <div class="small">
            ${escapeHtml(item.nombre_producto)}
          </div>
        </td>
        <td>
          <strong>${item.bobinas_pacote}</strong>
        </td>
        <td>
          <strong>${item.metragem || "N/A"}</strong>
        </td>
        <td>
  <div style="display: flex; align-items: flex-start; gap: 4px;">
    <span style="word-break: break-word; line-height: 1.3;">
      ${escapeHtml(item.cliente_display)}
    </span>
  </div>
</td>
        
        <td>
          <strong>${item.peso_formateado}</strong>
        </td>
        <td>
          <span class="badge ${
            item.es_desconocido
              ? fueraDeRejilla
                ? "bg-info"
                : "bg-warning text-dark"
              : "bg-success"
          }">
            ${textoEstado}
          </span>
        </td>
        <td>
          <button type="button" class="btn btn-outline-danger btn-sm" 
                  onclick="eliminarItemIndividual(${
                    item.expedicion_item_id
                  }, '${escapeHtml(item.numero_item)}')"
                  title="Eliminar este item">
            <i class="fas fa-trash fa-xs"></i>
          </button>
        </td>
      </tr>
    `;
  });

  // Calcular totales generales
  const totalBobinas = items.reduce(
    (sum, item) => sum + (parseInt(item.bobinas_pacote) || 1),
    0
  );
  const totalPesoNumerico = items.reduce((sum, item) => {
    const pesoMatch = item.peso_formateado.match(/[\d,]+/);
    if (pesoMatch) {
      const peso = parseFloat(pesoMatch[0].replace(",", ""));
      return sum + peso;
    }
    return sum;
  }, 0);

  html += `
        </tbody>
      </table>
    </div>
    
    <!-- Resumen rápido -->
    <div class="row mt-2">
      <div class="col-md-3">
        <small class="text-muted">
          <i class="fas fa-check-circle text-success"></i> 
          Asignados: ${items.filter((i) => !i.es_desconocido).length}
        </small>
      </div>
      <div class="col-md-3">
        <small class="text-muted">
          <i class="fas fa-question-circle text-warning"></i> 
          Desconocidos: ${
            items.filter(
              (i) =>
                i.es_desconocido &&
                i.modo_asignacion !== "desconocido_fuera_rejilla"
            ).length
          }
        </small>
      </div>
      <div class="col-md-3">
        <small class="text-muted">
          <i class="fas fa-map-marker-alt text-info"></i> 
          Fuera Rejilla: ${
            items.filter(
              (i) => i.modo_asignacion === "desconocido_fuera_rejilla"
            ).length
          }
        </small>
      </div>
      <div class="col-md-3">
        <small class="text-muted">
          <i class="fas fa-boxes text-primary"></i> 
          Total: ${totalBobinas} bobinas/unidades (${totalPesoNumerico.toLocaleString(
    "es-PY"
  )} kg)
        </small>
      </div>
    </div>
  `;

  contenedor.innerHTML = html;

  // Limpiar selecciones previas
  itemsSeleccionados.clear();
  actualizarEstadoBotonesSeleccion();
}

// ===== GESTIÓN DE SELECCIONES =====
function actualizarSeleccionItem(checkbox) {
  const itemId = parseInt(checkbox.value);

  if (checkbox.checked) {
    itemsSeleccionados.add(itemId);
  } else {
    itemsSeleccionados.delete(itemId);
  }

  actualizarEstadoBotonesSeleccion();
}

function toggleSeleccionTodos() {
  const selectAll = document.getElementById("selectAllItems");
  const checkboxes = document.querySelectorAll(".item-checkbox");

  checkboxes.forEach((checkbox) => {
    checkbox.checked = selectAll.checked;
    actualizarSeleccionItem(checkbox);
  });
}

function seleccionarTodosItems() {
  const checkboxes = document.querySelectorAll(".item-checkbox");
  checkboxes.forEach((checkbox) => {
    checkbox.checked = true;
    actualizarSeleccionItem(checkbox);
  });

  const selectAll = document.getElementById("selectAllItems");
  if (selectAll) selectAll.checked = true;

  // Mostrar toast con resumen
  const totalItems = itemsSeleccionados.size;
  const totalBobinas = Array.from(itemsSeleccionados).reduce((sum, itemId) => {
    const item = itemsEscaneadosData.find(
      (i) => i.expedicion_item_id === itemId
    );
    return sum + (item ? parseInt(item.bobinas_pacote) || 1 : 0);
  }, 0);

  mostrarToast(
    `✅ Seleccionados: ${totalItems} items (${totalBobinas} bobinas)`,
    "info",
    3000
  );
}

function limpiarSeleccionItems() {
  const checkboxes = document.querySelectorAll(".item-checkbox");
  checkboxes.forEach((checkbox) => {
    checkbox.checked = false;
    actualizarSeleccionItem(checkbox);
  });

  const selectAll = document.getElementById("selectAllItems");
  if (selectAll) selectAll.checked = false;

  mostrarToast("🔄 Selección limpiada", "info", 2000);
}
function limpiarSeleccionItems() {
  const checkboxes = document.querySelectorAll(".item-checkbox");
  checkboxes.forEach((checkbox) => {
    checkbox.checked = false;
    actualizarSeleccionItem(checkbox);
  });

  const selectAll = document.getElementById("selectAllItems");
  if (selectAll) selectAll.checked = false;
}

function actualizarEstadoBotonesSeleccion() {
  const btnEliminar = document.getElementById("btnEliminarSeleccionados");
  const contador = document.getElementById("contadorSeleccionados");
  const contadorBobinas = document.getElementById(
    "contadorBobinasSeleccionadas"
  );
  const resumenSeleccion = document.getElementById("resumenSeleccion");

  if (btnEliminar && contador) {
    const totalSeleccionados = itemsSeleccionados.size;

    // Calcular total de bobinas seleccionadas
    let totalBobinas = 0;
    let pesoTotalSeleccionado = 0;

    // Iterar sobre los items seleccionados y sumar las bobinas
    itemsSeleccionados.forEach((itemId) => {
      const item = itemsEscaneadosData.find(
        (i) => i.expedicion_item_id === itemId
      );
      if (item) {
        totalBobinas += parseInt(item.bobinas_pacote) || 1;
        // Extraer el peso numérico para sumarlo
        const pesoMatch = item.peso_formateado.match(/[\d,]+/);
        if (pesoMatch) {
          const peso = parseFloat(pesoMatch[0].replace(",", ""));
          pesoTotalSeleccionado += peso;
        }
      }
    });

    // Actualizar contadores
    contador.textContent = totalSeleccionados;
    if (contadorBobinas) {
      contadorBobinas.textContent = totalBobinas;
    }

    // Actualizar resumen de selección
    if (resumenSeleccion) {
      if (totalSeleccionados > 0) {
        resumenSeleccion.innerHTML = `
          <div class="alert alert-info py-2 mb-2">
            <div class="row text-center">
              <div class="col-4">
                <strong>${totalSeleccionados}</strong><br>
                <small class="text-muted">Items</small>
              </div>
              <div class="col-4">
                <strong>${totalBobinas}</strong><br>
                <small class="text-muted">Bobinas</small>
              </div>
              <div class="col-4">
                <strong>${pesoTotalSeleccionado.toLocaleString(
                  "es-PY"
                )} kg</strong><br>
                <small class="text-muted">Peso Total</small>
              </div>
            </div>
          </div>
        `;
        resumenSeleccion.style.display = "block";
      } else {
        resumenSeleccion.style.display = "none";
      }
    }

    // Habilitar/deshabilitar botón de eliminar
    btnEliminar.disabled = totalSeleccionados === 0;

    if (totalSeleccionados > 0) {
      btnEliminar.classList.remove("btn-outline-danger");
      btnEliminar.classList.add("btn-danger");
    } else {
      btnEliminar.classList.remove("btn-danger");
      btnEliminar.classList.add("btn-outline-danger");
    }
  }
}

// ===== ELIMINAR ITEM INDIVIDUAL =====
function eliminarItemIndividual(idExpedicionItem, numeroItem) {
  if (!expedicionActiva) {
    mostrarToast("❌ No hay expedición activa", "warning");
    return;
  }

  if (
    !confirm(
      `¿Eliminar el item ${numeroItem}?\n\nEsta acción no se puede deshacer.`
    )
  ) {
    return;
  }

  const formData = new FormData();
  formData.append("accion", "eliminar_item_escaneado");
  formData.append("id_expedicion_item", idExpedicionItem);
  formData.append("numero_expedicion", expedicionActiva);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarToast(
          `✅ Item ${numeroItem} eliminado exitosamente`,
          "success",
          4000
        );

        // Recargar listas
        cargarItemsEscaneadosDetallados(expedicionActiva);
        cargarVistaPreviaClientes(expedicionActiva);

        // Limpiar selecciones
        itemsSeleccionados.delete(idExpedicionItem);
        actualizarEstadoBotonesSeleccion();

        logDebug("Item eliminado exitosamente", data);
      } else {
        mostrarToast(`❌ Error eliminando item: ${data.error}`, "danger", 6000);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      mostrarToast("❌ Error de conexión al eliminar item", "danger");
    });
}

// ===== ELIMINAR ITEMS SELECCIONADOS =====
function eliminarItemsSeleccionados() {
  if (itemsSeleccionados.size === 0) {
    mostrarToast("❌ No hay items seleccionados", "warning");
    return;
  }

  if (!expedicionActiva) {
    mostrarToast("❌ No hay expedición activa", "warning");
    return;
  }

  const totalItems = itemsSeleccionados.size;
  const mensaje = `¿Eliminar ${totalItems} item(s) seleccionado(s)?\n\nEsta acción no se puede deshacer.`;

  if (!confirm(mensaje)) {
    return;
  }

  const idsArray = Array.from(itemsSeleccionados);

  const formData = new FormData();
  formData.append("accion", "eliminar_multiples_items");
  formData.append("numero_expedicion", expedicionActiva);
  formData.append("ids_items", JSON.stringify(idsArray));

  // Mostrar indicador de carga
  const btnEliminar = document.getElementById("btnEliminarSeleccionados");
  const textoOriginal = btnEliminar.innerHTML;
  btnEliminar.innerHTML =
    '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
  btnEliminar.disabled = true;

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.total_eliminados > 0) {
        const mensaje = `✅ ${data.total_eliminados} de ${data.total_solicitados} items eliminados exitosamente`;
        mostrarToast(mensaje, "success", 5000);

        if (data.total_errores > 0) {
          mostrarToast(
            `⚠️ ${data.total_errores} items no pudieron eliminarse`,
            "warning",
            5000
          );
        }

        // Recargar listas
        cargarItemsEscaneadosDetallados(expedicionActiva);
        cargarVistaPreviaClientes(expedicionActiva);

        // Limpiar selecciones
        itemsSeleccionados.clear();
        limpiarSeleccionItems();

        logDebug("Eliminación masiva completada", data);
      } else {
        const error = data.error || "No se pudieron eliminar los items";
        mostrarToast(`❌ Error: ${error}`, "danger", 6000);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      mostrarToast("❌ Error de conexión al eliminar items", "danger");
    })
    .finally(() => {
      // Restaurar botón
      btnEliminar.innerHTML = textoOriginal;
      actualizarEstadoBotonesSeleccion();
    });
}

// ===== ACTUALIZAR CONTADOR DE ITEMS =====
function actualizarContadorItems(totalItems) {
  const contador = document.getElementById("contadorItemsEscaneados");
  if (contador) {
    contador.textContent = totalItems;
  }
}

// ===== INTEGRACIÓN CON EL SCANNER EXISTENTE =====
// Modificar la función existente para que también recargue la lista detallada
function procesarItemEscaneadoExitosoExtendido(data) {
  // Llamar a la función original
  if (typeof procesarItemEscaneadoExitoso === "function") {
    procesarItemEscaneadoExitoso(data);
  }

  // Recargar la lista detallada de items
  if (expedicionActiva) {
    cargarItemsEscaneadosDetallados(expedicionActiva);
  }
}

// ===== BÚSQUEDA RÁPIDA EN ITEMS =====
function filtrarItemsEscaneados(filtro) {
  const filas = document.querySelectorAll("#listaItemsEscaneados tbody tr");
  const filtroLower = filtro.toLowerCase().trim();

  filas.forEach((fila) => {
    // Buscar específicamente en la columna de etiqueta (stock_id)
    const celdaEtiqueta = fila.querySelector("td:nth-child(2)"); // Segunda columna (Etiqueta)

    if (celdaEtiqueta) {
      // Extraer solo el stock_id (primera línea de la celda, sin el tiempo)
      const strongElement = celdaEtiqueta.querySelector("strong");
      const etiqueta = strongElement
        ? strongElement.textContent.toLowerCase().trim()
        : "";

      // Mostrar fila si la etiqueta contiene el filtro
      const mostrar = etiqueta.includes(filtroLower);
      fila.style.display = mostrar ? "" : "none";
    } else {
      // Si no encuentra la celda, ocultar la fila
      fila.style.display = "none";
    }
  });

  // Mostrar contador de resultados
  const filasVisibles = document.querySelectorAll(
    "#listaItemsEscaneados tbody tr[style=''], #listaItemsEscaneados tbody tr:not([style])"
  );
  const totalFiltradas = filasVisibles.length;

  if (filtro) {
    mostrarToast(
      `🔍 ${totalFiltradas} etiqueta(s) encontrada(s)`,
      "info",
      2000
    );
  }
}
// ===== ESCANEAR ITEM =====
function escanearItem(idItem) {
  if (isLoading) return;

  isLoading = true;

  const formData = new FormData();
  formData.append("accion", "escanear_item");
  formData.append("numero_expedicion", expedicionActiva);
  formData.append("id_item", idItem);

  logDebug("Enviando request de escaneo", {
    expedicion: expedicionActiva,
    item: idItem,
  });

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        procesarItemEscaneadoExitoso(data);
      } else {
        procesarErrorEscaneo(data);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      mostrarToast("❌ Error de conexión al servidor", "danger", 6000);
      logDebug("Error de red en escaneo", error);
    })
    .finally(() => {
      isLoading = false;

      setTimeout(() => {
        const input = document.getElementById("barcodeInput");
        if (input && modalScanner && modalScanner._isShown) {
          input.focus();
        }
      }, 100);
    });
}

// ===== PROCESAR ITEM ESCANEADO EXITOSO =====
function procesarItemEscaneadoExitoso(data) {
  const item = data.item;
  const info = data.info_asignacion;
  const validacionRejilla = data.validacion_rejilla;

  // Mostrar mensaje de éxito
  if (info.origen === "fuera_de_rejilla") {
    mostrarMensajeItemFueraDeRejilla(item, info, validacionRejilla);
  } else {
    mostrarMensajeItemEnRejilla(item, info, validacionRejilla);
  }

  // 🚀 ACTUALIZACIÓN SIMULTÁNEA Y OPTIMIZADA
  Promise.all([
    cargarVistaPreviaClientes(expedicionActiva),
    cargarItemsEscaneadosDetallados(expedicionActiva)
  ]).then(() => {
    logDebug("Listas actualizadas después del escaneo exitoso");
  }).catch((error) => {
    logDebug("Error actualizando listas", error);
  });
}

function mostrarMensajeItemFueraDeRejilla(item, info, validacionRejilla) {
  let mensaje = `⚠️ Item FUERA DE REJILLA agregado como DESCONOCIDO<br><strong>${escapeHtml(
    item.cliente
  )}</strong>`;
  mensaje += `<br>📍 <strong>Item no asignado a Rejilla #{${
    validacionRejilla.rejilla_expedicion || "N/A"
  }}</strong>`;
  mensaje += `<br>🔄 <strong>Requiere reasignación manual</strong>`;
  mensaje += `<br>💡 <strong>Razón:</strong> ${
    info.mensaje_asignacion || "Sin asignaciones en esta rejilla"
  }`;

  if (item.cantidad_escaneada > 1) {
    mensaje += `<br>📦 <strong>${item.cantidad_escaneada} unidades</strong> agregadas`;
  }

  mensaje += `<br>${escapeHtml(item.nombre_producto)}<br><strong>${
    item.peso_bruto_formateado
  }</strong>`;

  if (item.bobinas_pacote && item.bobinas_pacote > 1) {
    mensaje += `<br><small>📊 Item contiene ${item.bobinas_pacote} unidades</small>`;
  }

  mostrarToast(mensaje, "warning", 10000);
}

function mostrarMensajeItemEnRejilla(item, info, validacionRejilla) {
  let mensaje = `✅ Item agregado exitosamente<br><strong>${escapeHtml(
    item.cliente
  )}</strong>`;

  // Información de rejilla
  if (validacionRejilla && validacionRejilla.rejilla_item) {
    mensaje += `<br>📍 <strong>Rejilla #${validacionRejilla.rejilla_item}</strong>`;
  } else if (item.rejilla_fisica) {
    mensaje += `<br>📍 <strong>Rejilla #${item.rejilla_fisica}</strong>`;
  }

  if (item.cantidad_escaneada > 1) {
    mensaje += `<br>📦 <strong>${item.cantidad_escaneada} unidades</strong> agregadas`;
  }

  if (item.es_desconocido) {
    mensaje += `<br>❓ <strong>DESCONOCIDO</strong> - Requiere reasignación manual`;
  } else {
    mensaje += `<br>🎯 ${info.mensaje_asignacion}`;

    // Agregar información de despachos anteriores si está disponible
    if (info.info_despacho && info.info_despacho.ya_despachado > 0) {
      mensaje += `<br>📊 <small class="text-info">Ya despachado anteriormente: ${info.info_despacho.ya_despachado} unidades</small>`;
    }
    if (info.info_despacho && info.info_despacho.disponible !== undefined) {
      mensaje += `<br>📊 <small class="text-success">Disponible para escanear: ${info.info_despacho.disponible} unidades</small>`;
    }
  }

  mensaje += `<br>${escapeHtml(item.nombre_producto)}<br><strong>${
    item.peso_bruto_formateado
  }</strong>`;

  if (item.bobinas_pacote && item.bobinas_pacote > 1) {
    mensaje += `<br><small>📊 Item contiene ${item.bobinas_pacote} unidades</small>`;
  }

  let tipoToast = item.es_desconocido ? "warning" : "success";
  let duracion = 5000;

  mostrarToast(mensaje, tipoToast, duracion);
}

// ===== PROCESAR ERROR DE ESCANEO =====
function procesarErrorEscaneo(data) {
  if (data.tipo_error === "STOCK_INSUFICIENTE") {
    mostrarErrorStock(data);
  } else {
    let mensajeError = "❌ " + data.error;
    let tipoToast = "danger";
    let duracion = 8000;

    // Manejo específico de errores
    if (data.tipo_error === "ITEM_DUPLICADO") {
      mensajeError += `<br><small>💡 ${
        data.sugerencia || "Este item ya fue escaneado anteriormente"
      }</small>`;
      tipoToast = "warning";
      duracion = 8000;
    } else if (data.tipo_error === "EXPEDICION_CERRADA") {
      mensajeError += `<br><small>💡 No se pueden agregar items a expediciones cerradas</small>`;
    } else if (data.tipo_error === "ITEM_NO_ENCONTRADO") {
      mensajeError = `❌ <strong>Item no disponible</strong><br>${data.error}`;
      mensajeError += `<br><small>💡 ${
        data.sugerencia || "Verifique que el item existe y está en stock"
      }</small>`;
      tipoToast = "warning";
      duracion = 10000;
    }

    mostrarToast(mensajeError, tipoToast, duracion);
  }

  logDebug("Error escaneando item", data);
}

// ===== MOSTRAR ERROR DE STOCK =====
function mostrarErrorStock(data) {
  const itemInfo = data.item_info || {};
  const stockInfo = data.stock_info || {};

  let mensaje = `❌ <strong>Stock Insuficiente</strong><br>`;
  mensaje += `📦 Item: <strong>${itemInfo.numero_item || "N/A"}</strong><br>`;
  mensaje += `🏷️ Producto: <strong>${
    itemInfo.nombre_producto || "N/A"
  }</strong><br>`;

  if (itemInfo.bobinas_pacote > 1) {
    mensaje += `📊 Paquete de: <strong>${itemInfo.bobinas_pacote} unidades</strong><br>`;
  }

  mensaje += `🔢 Requiere: <strong>${
    itemInfo.cantidad_requerida || 0
  } unidades</strong><br>`;

  if (stockInfo.cantidad_disponible !== undefined) {
    mensaje += `📋 Stock disponible: <strong>${stockInfo.cantidad_disponible} unidades</strong><br>`;
  }

  mensaje += `<br>💡 <strong>Sugerencia:</strong> ${
    data.sugerencia || "Verifique el stock disponible"
  }`;

  mostrarToast(mensaje, "danger", 12000);
}

// ===== MANTENER FOCUS EN SCANNER =====
function mantenerFocusScanner() {
  if (modalScanner && modalScanner._isShown) {
    const otrosModalesAbiertos =
      document.querySelectorAll(".modal.show").length > 1;

    if (!otrosModalesAbiertos) {
      const input = document.getElementById("barcodeInput");
      if (input && document.activeElement !== input) {
        const elementoActivo = document.activeElement;

        // MEJORA: Verificar si el elemento activo es parte de la tabla de items
        const esElementoTabla =
          elementoActivo &&
          (elementoActivo.closest("#listaItemsEscaneados") ||
            elementoActivo.closest(".table-responsive") ||
            elementoActivo.type === "checkbox");

        const esElementoFormulario =
          elementoActivo &&
          (elementoActivo.tagName === "INPUT" ||
            elementoActivo.tagName === "SELECT" ||
            elementoActivo.tagName === "TEXTAREA" ||
            elementoActivo.tagName === "BUTTON");

        // CLAVE: No mover focus si el usuario está interactuando con la tabla
        if (!esElementoFormulario && !esElementoTabla) {
          // Usar setTimeout para evitar conflictos
          setTimeout(() => {
            if (input && document.activeElement !== input) {
              input.focus({ preventScroll: true }); // IMPORTANTE: preventScroll
            }
          }, 100);
        }
      }
    }

    // Reducir frecuencia cuando hay otros modales
    if (!otrosModalesAbiertos) {
      setTimeout(mantenerFocusScanner, 1000); // Aumentado de 500ms a 1000ms
    } else {
      setTimeout(mantenerFocusScanner, 3000); // Aumentado de 2000ms a 3000ms
    }
  }
}

// ===== LIMPIAR SCANNER =====
function limpiarScanner() {
  const input = document.getElementById("barcodeInput");
  if (input) {
    input.value = "";
  }

  expedicionActiva = null;
  isLoading = false;
  clearTimeout(searchTimeout);

  const contenedorClientes = document.getElementById("contenedorItemsClientes");
  if (contenedorClientes) {
    contenedorClientes.innerHTML =
      '<div class="text-center text-muted p-3"><i class="fas fa-clipboard-list me-2"></i>No hay items escaneados aún</div>';
  }

  logDebug("Scanner limpiado");
}

// ===== ACTUALIZAR LISTA ITEMS =====
function actualizarListaItems() {
  if (expedicionActiva) {
    cargarVistaPreviaClientes(expedicionActiva);
    mostrarToast("🔄 Vista previa actualizada", "info");
  } else {
    mostrarToast("❌ No hay expedición activa", "warning");
  }
}

// Hacer funciones disponibles globalmente
window.abrirScanner = abrirScanner;
window.procesarCodigoEscaneado = procesarCodigoEscaneado;
window.escanearItem = escanearItem;
window.mantenerFocusScanner = mantenerFocusScanner;
window.limpiarScanner = limpiarScanner;
window.actualizarListaItems = actualizarListaItems;
window.cargarItemsEscaneadosDetallados = cargarItemsEscaneadosDetallados;
window.eliminarItemIndividual = eliminarItemIndividual;
window.eliminarItemsSeleccionados = eliminarItemsSeleccionados;
window.seleccionarTodosItems = seleccionarTodosItems;
window.limpiarSeleccionItems = limpiarSeleccionItems;
window.toggleSeleccionTodos = toggleSeleccionTodos;
window.actualizarSeleccionItem = actualizarSeleccionItem;
window.filtrarItemsEscaneados = filtrarItemsEscaneados;
