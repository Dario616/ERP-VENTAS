// =====================================
// EXPEDICION UI - Gestión de UI y Modales
// =====================================

// ===== ✅ FUNCIÓN: TOASTS BONITOS COMPACTOS =====
function mostrarToastBonito(
  titulo,
  mensaje,
  tipo = "success",
  duracion = 4000
) {
  const toastId = `toast-${Date.now()}-${Math.random()
    .toString(36)
    .substr(2, 9)}`;

  const tiposConfig = {
    success: {
      icon: "fas fa-check-circle",
      color: "success",
      titulo: titulo || "¡Éxito!",
    },
    error: {
      icon: "fas fa-exclamation-triangle",
      color: "danger",
      titulo: titulo || "Error",
    },
    warning: {
      icon: "fas fa-exclamation-circle",
      color: "warning",
      titulo: titulo || "Advertencia",
    },
    info: {
      icon: "fas fa-info-circle",
      color: "info",
      titulo: titulo || "Información",
    },
  };

  const config = tiposConfig[tipo] || tiposConfig.success;

  const toastHtml = `
    <div id="${toastId}" class="toast align-items-center text-bg-${config.color} border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body p-2">
          <div class="d-flex align-items-start">
            <i class="${config.icon} me-2 mt-1" style="font-size: 0.9rem;"></i>
            <div class="flex-grow-1">
              <div class="fw-bold" style="font-size: 0.8rem;">${config.titulo}</div>
              <div class="small mt-1" style="font-size: 0.7rem;">${mensaje}</div>
            </div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" style="font-size: 0.7rem;"></button>
      </div>
    </div>
  `;

  let contenedor = document.getElementById("toast-container");
  if (!contenedor) {
    contenedor = document.createElement("div");
    contenedor.id = "toast-container";
    contenedor.className = "toast-container position-fixed top-0 end-0 p-2";
    contenedor.style.zIndex = "9999";
    document.body.appendChild(contenedor);
  }

  contenedor.insertAdjacentHTML("beforeend", toastHtml);

  const toastElement = document.getElementById(toastId);
  const bsToast = new bootstrap.Toast(toastElement, {
    autohide: duracion > 0,
    delay: duracion,
  });

  bsToast.show();

  toastElement.addEventListener("hidden.bs.toast", () => {
    toastElement.remove();
  });

  return bsToast;
}

// ===== ✅ FUNCIÓN: CONFIRMACIÓN BONITA COMPACTA =====
function confirmarAccionBonita(
  titulo,
  mensaje,
  textoConfirmar = "Confirmar",
  textoCancelar = "Cancelar"
) {
  return new Promise((resolve) => {
    const modalId = `modal-confirm-${Date.now()}`;

    const modalHtml = `
      <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white p-2">
              <h6 class="modal-title mb-0">
                <i class="fas fa-question-circle me-2"></i>${titulo}
              </h6>
            </div>
            <div class="modal-body p-3">
              <div class="text-center py-2">
                <i class="fas fa-question-circle text-primary mb-2" style="font-size: 2rem;"></i>
                <div style="font-size: 0.9rem;">${mensaje.replace(
                  /\n/g,
                  "<br>"
                )}</div>
              </div>
            </div>
            <div class="modal-footer p-2">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i>${textoCancelar}
              </button>
              <button type="button" class="btn btn-primary btn-sm" id="${modalId}-confirm">
                <i class="fas fa-check me-1"></i>${textoConfirmar}
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHtml);

    const modalElement = document.getElementById(modalId);
    const modal = new bootstrap.Modal(modalElement);

    document.getElementById(`${modalId}-confirm`).onclick = () => {
      modal.hide();
      resolve(true);
    };

    modalElement.addEventListener("hidden.bs.modal", () => {
      modalElement.remove();
      resolve(false);
    });

    modal.show();
  });
}

// ===== ABRIR MODAL DE CLIENTE =====
function abrirModalCliente(nombreCliente) {
  if (!nombreCliente) {
    logDebug("Nombre de cliente vacío", null, "error");
    return;
  }

  clienteActual = nombreCliente;
  document.getElementById("nombreClienteModal").textContent = nombreCliente;

  logDebug(`Abriendo modal para cliente: ${nombreCliente}`);

  mostrarLoadingModal(true);
  limpiarContenidoModal();
  modalClienteActivo.show();

  const formData = new FormData();
  formData.append("accion", "obtener_productos_vendidos_cliente");
  formData.append("cliente", nombreCliente);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.text();
    })
    .then((responseText) => {
      let ventas;
      try {
        ventas = JSON.parse(responseText);
      } catch (parseError) {
        throw new Error("Respuesta del servidor no es JSON válido");
      }

      mostrarLoadingModal(false);

      if (ventas && ventas.debug === true) {
        logDebug("Respuesta de debug recibida", ventas);
        if (ventas.error) {
          mostrarErrorModalConDebug(ventas.error, ventas);
        } else {
          mostrarMensajeDebugDetallado(ventas);
        }
        return;
      }

      if (ventas && ventas.error) {
        throw new Error(ventas.error);
      }

      if (!Array.isArray(ventas)) {
        if (
          ventas &&
          typeof ventas === "object" &&
          ventas.data &&
          Array.isArray(ventas.data)
        ) {
          ventas = ventas.data;
        } else {
          throw new Error("El servidor no devolvió un array de ventas válido");
        }
      }

      if (ventas.length === 0) {
        mostrarMensajeVacioMejorado();
        return;
      }

      logDebug(`Generando contenido para ${ventas.length} ventas`);
      generarContenidoVentasSimplificado(ventas, nombreCliente);
    })
    .catch((error) => {
      mostrarLoadingModal(false);
      mostrarErrorModal(
        "Error al cargar los productos pendientes del cliente."
      );
      logDebug("Error cargando productos del cliente", error, "error");
    });
}

// ===== MOSTRAR MENSAJE DE DEBUG DETALLADO COMPACTO =====
function mostrarMensajeDebugDetallado(datos) {
  const contenido = document.getElementById("contenidoVentasProductos");
  if (!contenido) {
    return;
  }

  let analisisHtml = "";
  if (datos.analisis_detallado && datos.analisis_detallado.length > 0) {
    analisisHtml = `
      <div class="mt-3">
        <h6 class="text-primary small">
          <i class="fas fa-search me-2"></i>
          Análisis Detallado (${datos.analisis_detallado.length} productos)
        </h6>
        <div class="table-responsive">
          <table class="table table-xs table-striped">
            <thead class="table-dark">
              <tr style="font-size: 0.7rem;">
                <th>Producto</th>
                <th>Vendido</th>
                <th>Prod</th>
                <th>Exp</th>
                <th>100%?</th>
                <th>Estados</th>
                <th>Motivo Exclusión</th>
              </tr>
            </thead>
            <tbody>
    `;

    datos.analisis_detallado.forEach((item) => {
      const completadoIcon = item.esta_100_completado ? "✅" : "❌";

      analisisHtml += `
        <tr style="font-size: 0.7rem;">
          <td class="small fw-bold">${escapeHtml(item.producto)}</td>
          <td>${item.cantidad_vendida}</td>
          <td>${item.cantidad_produccion}</td>
          <td>${item.cantidad_expedicion}</td>
          <td>${completadoIcon}</td>
          <td class="small">
            P: ${item.estados_produccion || "NULL"}<br>
            E: ${item.estados_expedicion || "NULL"}
          </td>
          <td class="small text-${
            item.motivo_exclusion.includes("Debería") ? "warning" : "muted"
          }">
            ${item.motivo_exclusion}
          </td>
        </tr>
      `;
    });

    analisisHtml += `
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  contenido.innerHTML = `
    <div class="alert alert-info p-2">
      <i class="fas fa-info-circle me-2"></i>
      <strong style="font-size: 0.9rem;">Información del Sistema</strong>
      <hr class="my-2">
      <p class="mb-1 small"><strong>Cliente:</strong> ${escapeHtml(
        datos.cliente
      )}</p>
      <p class="mb-1 small"><strong>Productos en el sistema:</strong> ${
        datos.productos_sistema || 0
      }</p>
      <p class="mb-2 small"><strong>Motivo:</strong> ${
        datos.razon || "No especificado"
      }</p>
      
      <div class="mt-2 p-2 bg-light rounded">
        <h6 class="text-primary small">
          <i class="fas fa-lightbulb me-2"></i>
          ¿Por qué no aparecen productos?
        </h6>
        <ul class="mb-0 small" style="font-size: 0.75rem;">
          <li>Los productos deben estar <strong>100% completados</strong></li>
          <li>Deben tener registros con estado <strong>"PENDIENTE"</strong></li>
          <li>No deben estar asignados con movimiento <strong>"EN REJILLAS"</strong></li>
          <li>Deben existir en las tablas de producción o expedición</li>
        </ul>
      </div>
      
      ${analisisHtml}
      
      <div class="mt-2">
        <button type="button" class="btn btn-outline-primary btn-sm" 
                onclick="recargarDatosClientes()">
          <i class="fas fa-sync-alt me-1"></i>
          Recargar
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" 
                onclick="abrirModalCliente('${escapeHtml(datos.cliente)}')">
          <i class="fas fa-redo me-1"></i>
          Reintentar
        </button>
      </div>
    </div>
  `;
}

// ===== MOSTRAR ERROR CON DEBUG COMPACTO =====
function mostrarErrorModalConDebug(mensaje, datosDebug) {
  const contenido = document.getElementById("contenidoVentasProductos");
  if (!contenido) {
    return;
  }

  contenido.innerHTML = `
    <div class="alert alert-warning p-2">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong style="font-size: 0.9rem;">Error al cargar productos</strong>
      <hr class="my-2">
      <p class="small">${escapeHtml(mensaje)}</p>
      
      ${
        datosDebug
          ? `
        <div class="mt-2 p-2 bg-light rounded">
          <h6 class="text-secondary small">Información de Debug:</h6>
          <pre class="small mb-0" style="font-size: 0.7rem;">${JSON.stringify(
            datosDebug,
            null,
            2
          )}</pre>
        </div>
      `
          : ""
      }
      
      <div class="mt-2">
        <button type="button" class="btn btn-outline-primary btn-sm" 
                onclick="abrirModalCliente('${escapeHtml(
                  clienteActual || ""
                )}')">
          <i class="fas fa-redo me-1"></i>
          Reintentar
        </button>
      </div>
    </div>
  `;
}

// ===== MENSAJE VACÍO MEJORADO COMPACTO =====
function mostrarMensajeVacioMejorado() {
  const contenido = document.getElementById("contenidoVentasProductos");
  if (contenido) {
    contenido.innerHTML = `
      <div class="alert alert-success p-2">
        <i class="fas fa-check-circle me-2"></i>
        <strong style="font-size: 0.9rem;">¡Excelente!</strong> No hay productos pendientes para este cliente.
        
        <div class="mt-2 p-2 bg-light rounded">
          <h6 class="text-success small">
            <i class="fas fa-info-circle me-2"></i>
            Estado Actual
          </h6>
          <p class="mb-1 small">Este cliente no tiene productos que cumplan con los criterios:</p>
          <ul class="mb-0 small" style="font-size: 0.75rem;">
            <li>✅ Productos 100% completados</li>
            <li>✅ Con registros en estado <strong>"PENDIENTE"</strong></li>
            <li>✅ No asignados previamente a rejillas</li>
          </ul>
        </div>
        
        <div class="mt-2">
          <button type="button" class="btn btn-outline-success btn-sm" 
                  onclick="recargarDatosClientes()">
            <i class="fas fa-sync-alt me-1"></i>
            Verificar Nuevamente
          </button>
        </div>
      </div>
    `;
  }
}

// ===== GENERAR OPCIONES DE REJILLAS =====
function generarOpcionesRejillas() {
  const rejillas = EXPEDICION_CONFIG.rejillasDisponibles || [];
  let opciones = "";

  rejillas
    .filter((rejilla) => rejilla.estado !== "llena")
    .forEach((rejilla) => {
      const capacidadDisponible = Math.round(rejilla.capacidad_disponible || 0);
      const porcentaje = Math.round(rejilla.porcentaje_uso || 0);

      let estadoTexto;
      if (capacidadDisponible > 10000) {
        estadoTexto = "Excelente";
      } else if (capacidadDisponible > 5000) {
        estadoTexto = "Buena";
      } else if (capacidadDisponible > 2000) {
        estadoTexto = "Moderada";
      } else {
        estadoTexto = "Limitada";
      }

      const capacidadTexto =
        capacidadDisponible >= 1000
          ? `${(capacidadDisponible / 1000).toFixed(1)}t`
          : `${capacidadDisponible}kg`;

      opciones += `<option value="${rejilla.id}" data-capacidad="${capacidadDisponible}" data-porcentaje="${porcentaje}">
        Rejilla #${rejilla.numero_rejilla} - ${capacidadTexto} libres (${porcentaje}%) - ${estadoTexto}
      </option>`;
    });

  return opciones;
}

function generarOpcionesRejillasParaVenta(pesoRequerido) {
  const rejillas = EXPEDICION_CONFIG.rejillasDisponibles || [];
  let opciones = "";

  rejillas
    .filter((rejilla) => rejilla.estado !== "fuera_servicio")
    .forEach((rejilla) => {
      const capacidadDisponible = Math.round(rejilla.capacidad_disponible || 0);
      const porcentaje = Math.round(rejilla.porcentaje_uso || 0);

      const porcentajeDespues = Math.round(
        ((rejilla.peso_actual + pesoRequerido) / rejilla.capacidad_maxima) * 100
      );
      const exceso = pesoRequerido - capacidadDisponible;

      const capacidadTexto =
        capacidadDisponible >= 1000
          ? `${(capacidadDisponible / 1000).toFixed(1)}t`
          : `${capacidadDisponible}kg`;

      let estadoTexto = "";
      let claseEstado = "";

      if (exceso > 0) {
        const excesoTexto =
          exceso >= 1000
            ? `${(exceso / 1000).toFixed(1)}t`
            : `${Math.round(exceso)}kg`;
        estadoTexto = ` ⚠️ EXCEDE ${excesoTexto}`;
        claseEstado = "text-danger fw-bold";
      } else if (capacidadDisponible < pesoRequerido * 0.1) {
        estadoTexto = " ⚡ Quedará muy llena";
        claseEstado = "text-warning";
      } else {
        estadoTexto = " ✅ Espacio OK";
        claseEstado = "text-success";
      }

      opciones += `<option value="${rejilla.id}" 
                          data-capacidad="${capacidadDisponible}" 
                          data-exceso="${exceso}"
                          class="${claseEstado}">
        Rejilla #${rejilla.numero_rejilla} - ${capacidadTexto} libres → ${porcentajeDespues}%${estadoTexto}
      </option>`;
    });

  if (opciones === "") {
    opciones = '<option value="">No hay rejillas disponibles</option>';
  }

  return opciones;
}

// ===== ACTUALIZAR INFORMACIÓN DE REJILLA =====
function actualizarInformacionRejilla(selectElement) {
  const selectedOption = selectElement.selectedOptions[0];
  if (!selectedOption || !selectedOption.value) return;

  const capacidad = parseInt(selectedOption.dataset.capacidad);
  const porcentaje = selectedOption.dataset.porcentaje;

  const form = selectElement.closest("form");
  if (form) {
    let alertInfo = form.querySelector("#info-rejilla");

    if (alertInfo) {
      alertInfo.style.display = "block";
      const numeroRejilla = selectedOption.textContent.match(/#(\d+)/)[1];

      const capacidadTexto =
        capacidad >= 1000
          ? `${(capacidad / 1000).toFixed(1)} toneladas`
          : `${capacidad} kg`;

      const infoTexto = `
        <strong>Rejilla seleccionada:</strong> Se asignará automáticamente en la rejilla #${numeroRejilla}. 
        Capacidad disponible: ${capacidadTexto} (${porcentaje}% ocupada).
      `;

      alertInfo.innerHTML = `<i class="fas fa-check-circle me-1"></i><small>${infoTexto}</small>`;

      if (capacidad < 2000) {
        alertInfo.className = "alert alert-warning mt-2 mb-0 p-1";
        alertInfo.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i><small><strong>Advertencia:</strong> La rejilla #${numeroRejilla} tiene capacidad limitada (${capacidadTexto} disponibles).</small>`;
      } else if (capacidad < 5000) {
        alertInfo.className = "alert alert-info mt-2 mb-0 p-1";
        alertInfo.innerHTML = `<i class="fas fa-info-circle me-1"></i><small><strong>Nota:</strong> La rejilla #${numeroRejilla} tiene capacidad moderada (${capacidadTexto} disponibles).</small>`;
      } else {
        alertInfo.className = "alert alert-success mt-2 mb-0 p-1";
      }
    }
  }
}

// Función para actualizar advertencia de capacidad
function actualizarAdvertenciaCapacidad(selectElement, pesoRequerido) {
  const advertenciaDiv = document.getElementById("advertenciaCapacidad");
  const selectedOption = selectElement.selectedOptions[0];

  if (!selectedOption || !selectedOption.value) {
    advertenciaDiv.style.display = "none";
    return;
  }

  const capacidadDisponible = parseFloat(selectedOption.dataset.capacidad || 0);
  const exceso = parseFloat(selectedOption.dataset.exceso || 0);
  const numeroRejilla = selectedOption.textContent.match(/#(\d+)/)[1];

  let contenidoAdvertencia = "";
  let claseAlerta = "alert-info";

  if (exceso > 0) {
    const excesoTexto =
      exceso >= 1000
        ? `${(exceso / 1000).toFixed(1)} toneladas`
        : `${Math.round(exceso)} kg`;
    contenidoAdvertencia = `
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong>⚠️ ADVERTENCIA:</strong> La rejilla #${numeroRejilla} excederá su capacidad por <strong>${excesoTexto}</strong>.
      <br><small>La asignación se puede realizar igualmente, pero la rejilla quedará sobrecargada.</small>
    `;
    claseAlerta = "alert-danger";
  } else if (capacidadDisponible < pesoRequerido * 0.2) {
    contenidoAdvertencia = `
      <i class="fas fa-info-circle me-2"></i>
      <strong>Nota:</strong> La rejilla #${numeroRejilla} quedará muy llena después de esta asignación.
      <br><small>Espacio restante será mínimo para futuras asignaciones.</small>
    `;
    claseAlerta = "alert-warning";
  } else {
    contenidoAdvertencia = `
      <i class="fas fa-check-circle me-2"></i>
      <strong>✅ Perfecto:</strong> La rejilla #${numeroRejilla} tiene espacio suficiente.
      <br><small>Capacidad disponible adecuada para esta asignación.</small>
    `;
    claseAlerta = "alert-success";
  }

  advertenciaDiv.innerHTML = `<div class="alert ${claseAlerta} mb-2 p-2" style="font-size: 0.8rem;">${contenidoAdvertencia}</div>`;
  advertenciaDiv.style.display = "block";
}

// Toggle functions
function toggleVentaModal(element) {
  const content = element.nextElementSibling;
  const icon = element.querySelector(".toggle-icon");

  if (content.style.display === "none") {
    content.style.display = "block";
    icon.classList.remove("fa-chevron-down");
    icon.classList.add("fa-chevron-up");

    content.style.opacity = "0";
    content.style.transform = "translateY(-5px)";

    setTimeout(() => {
      content.style.transition = "all 0.2s ease";
      content.style.opacity = "1";
      content.style.transform = "translateY(0)";
    }, 50);
  } else {
    content.style.display = "none";
    icon.classList.remove("fa-chevron-up");
    icon.classList.add("fa-chevron-down");
  }
}

function toggleProductoSimplificado(element) {
  const content = element.nextElementSibling;
  const icon = element.querySelector(".toggle-icon");

  if (content.style.display === "none") {
    content.style.display = "block";
    icon.classList.remove("fa-chevron-down");
    icon.classList.add("fa-chevron-up");

    setTimeout(() => {
      inicializarElementosDinamicos(content);
    }, 100);
  } else {
    content.style.display = "none";
    icon.classList.remove("fa-chevron-up");
    icon.classList.add("fa-chevron-down");
  }
}

// Hacer funciones disponibles globalmente
window.mostrarToastBonito = mostrarToastBonito;
window.confirmarAccionBonita = confirmarAccionBonita;
window.abrirModalCliente = abrirModalCliente;
window.toggleVentaModal = toggleVentaModal;
window.toggleProductoSimplificado = toggleProductoSimplificado;
window.actualizarAdvertenciaCapacidad = actualizarAdvertenciaCapacidad;
