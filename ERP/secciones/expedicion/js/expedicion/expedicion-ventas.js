// =====================================
// EXPEDICION VENTAS - Gestión de ventas completas
// =====================================

// ===== ✅ ACTUALIZADO: FUNCIÓN PARA ASIGNAR VENTA COMPLETA (INCLUSO CON 1 PRODUCTO) =====
async function asignarVentaCompleta(idVenta, cliente) {
  if (isLoading) {
    return;
  }

  logDebug(
    `Iniciando asignación de venta completa: ${idVenta} para ${cliente}`
  );

  const ventaElement = document.querySelector(`[data-venta-id="${idVenta}"]`);
  if (!ventaElement) {
    mostrarToastBonito("Error", "No se encontró la venta", "error");
    return;
  }

  const productosDisponibles = ventaElement.querySelectorAll(
    ".producto-group-modal"
  );
  const productosParaAsignar = [];
  let pesoTotalVenta = 0;
  let unidadesTotalesVenta = 0;

  productosDisponibles.forEach((productoEl) => {
    const formulario = productoEl.querySelector(
      ".expedicion-form-simplificado"
    );
    if (formulario) {
      const nombreProducto = formulario.querySelector(
        '[name="nombre_producto"]'
      ).value;
      const idProductoPresupuesto = formulario.querySelector(
        '[name="id_producto_presupuesto"]'
      ).value;

      const badgeDisponible = productoEl.querySelector(".badge.bg-info");
      if (badgeDisponible) {
        const textoDisponible = badgeDisponible.textContent;
        const unidadesMatch = textoDisponible.match(/(\d+)/);
        const unidadesDisponibles = unidadesMatch
          ? parseInt(unidadesMatch[1])
          : 0;

        if (unidadesDisponibles > 0) {
          const pesoUnitarioEl = productoEl.querySelector("small.text-muted");
          let pesoUnitario = 0;
          if (pesoUnitarioEl) {
            const pesoMatch =
              pesoUnitarioEl.textContent.match(/([\d.]+) kg\/unidad/);
            pesoUnitario = pesoMatch ? parseFloat(pesoMatch[1]) : 0;
          }

          const pesoProducto = unidadesDisponibles * pesoUnitario;

          productosParaAsignar.push({
            id_producto_presupuesto: idProductoPresupuesto,
            nombre_producto: nombreProducto,
            unidades_disponibles: unidadesDisponibles,
            peso_unitario: pesoUnitario,
            peso_total: pesoProducto,
          });

          pesoTotalVenta += pesoProducto;
          unidadesTotalesVenta += unidadesDisponibles;
        }
      }
    }
  });

  if (productosParaAsignar.length === 0) {
    mostrarToastBonito(
      "Sin Productos",
      "No hay productos disponibles para asignar en esta venta",
      "warning"
    );
    return;
  }

  logDebug(
    `Productos recopilados para venta completa: ${productosParaAsignar.length}`,
    productosParaAsignar
  );

  // ✅ CAMBIO: Mostrar confirmación incluso para 1 producto
  const textoCantidad =
    productosParaAsignar.length === 1 ? "EL PRODUCTO" : "TODA LA VENTA";
  const resumenProductos = productosParaAsignar
    .map(
      (p) =>
        `• ${p.nombre_producto}: ${
          p.unidades_disponibles
        } unidades (${p.peso_total.toFixed(1)} kg)`
    )
    .join("\n");

  const mensaje =
    `¿Confirma asignar ${textoCantidad} a una rejilla?\n\n` +
    `Cliente: ${cliente}\n` +
    `Total productos: ${productosParaAsignar.length}\n` +
    `Total unidades: ${unidadesTotalesVenta}\n` +
    `Peso total: ${pesoTotalVenta.toFixed(1)} kg\n\n` +
    `Productos:\n${resumenProductos}\n\n` +
    `Seleccione una rejilla en el siguiente paso.`;

  const confirmado = await confirmarAccionBonita(
    `Asignar ${textoCantidad}`,
    mensaje,
    "Continuar",
    "Cancelar"
  );

  if (!confirmado) {
    logDebug("Usuario canceló asignación de venta completa");
    return;
  }

  mostrarSelectorRejillaVentaCompleta(
    idVenta,
    cliente,
    productosParaAsignar,
    pesoTotalVenta,
    unidadesTotalesVenta
  );
}

// ===== MOSTRAR SELECTOR DE REJILLA COMPACTO =====
function mostrarSelectorRejillaVentaCompleta(
  idVenta,
  cliente,
  productos,
  pesoTotal,
  unidadesTotal
) {
  const textoCantidad = productos.length === 1 ? "Producto" : "Venta Completa";

  const modalHtml = `
    <div class="modal fade" id="modalAsignarVentaCompleta" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-success text-white p-2">
            <h6 class="modal-title mb-0">
              <i class="fas fa-rocket me-2"></i>
              Asignar ${textoCantidad} - ${cliente}
            </h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-3">
            <div class="alert alert-info p-2">
              <i class="fas fa-info-circle me-2"></i>
              <strong style="font-size: 0.9rem;">Resumen de la asignación:</strong><br>
              <small>• ${productos.length} producto${
    productos.length > 1 ? "s" : ""
  }<br>
              • ${unidadesTotal} unidades totales<br>
              • ${pesoTotal.toFixed(1)} kg de peso total</small>
            </div>
            
            <form id="formVentaCompleta">
              <input type="hidden" name="id_venta" value="${idVenta}">
              <input type="hidden" name="cliente" value="${cliente}">
              <input type="hidden" name="productos_data" value='${JSON.stringify(
                productos
              )}'>
              
              <div class="mb-3">
                <label class="form-label fw-bold small">
                  <i class="fas fa-th-large me-2"></i>
                  Seleccionar rejilla destino
                </label>
                <select class="form-select form-select-sm" name="id_rejilla" onchange="actualizarAdvertenciaCapacidad(this, ${pesoTotal})" required>
                  <option value="">Seleccionar rejilla...</option>
                  ${generarOpcionesRejillasParaVenta(pesoTotal)}
                </select>
                <div class="form-text small">
                  Peso requerido: ${pesoTotal.toFixed(1)} kg
                </div>
              </div>
              
              <div id="advertenciaCapacidad" style="display: none;"></div>
              
              <div class="alert alert-warning p-2">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong class="small">Importante:</strong> 
                <small>Todos los productos serán asignados a la misma rejilla y desaparecerán del listado.</small>
              </div>
            </form>
          </div>
          <div class="modal-footer p-2">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>Cancelar
            </button>
            <button type="button" class="btn btn-success btn-sm" onclick="confirmarAsignacionVentaCompleta()">
              <i class="fas fa-rocket me-1"></i>
              <span class="btn-text">Asignar ${textoCantidad}</span>
              <span class="btn-loading" style="display: none;">
                <i class="fas fa-spinner fa-spin"></i>
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  const modalAnterior = document.getElementById("modalAsignarVentaCompleta");
  if (modalAnterior) {
    modalAnterior.remove();
  }

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  const modal = new bootstrap.Modal(
    document.getElementById("modalAsignarVentaCompleta")
  );
  modal.show();

  logDebug("Modal de venta completa mostrado");
}

// ===== CONFIRMAR ASIGNACIÓN VENTA COMPLETA =====
async function confirmarAsignacionVentaCompleta() {
  if (isLoading) {
    return;
  }

  const form = document.getElementById("formVentaCompleta");
  const formData = new FormData(form);
  const idRejilla = formData.get("id_rejilla");

  if (!idRejilla) {
    mostrarToastBonito("Error", "Debe seleccionar una rejilla", "warning");
    form.querySelector('[name="id_rejilla"]').focus();
    return;
  }

  const selectRejilla = form.querySelector('[name="id_rejilla"]');
  const selectedOption = selectRejilla.selectedOptions[0];
  const exceso = parseFloat(selectedOption.dataset.exceso || 0);

  logDebug(`Confirmando asignación de venta completa con exceso: ${exceso}`);

  if (exceso > 0) {
    const excesoTexto =
      exceso >= 1000
        ? `${(exceso / 1000).toFixed(1)} toneladas`
        : `${Math.round(exceso)} kg`;
    const confirmacionExceso =
      `⚠️ ADVERTENCIA: La rejilla excederá su capacidad por ${excesoTexto}\n\n` +
      `¿Está seguro de continuar con la asignación?\n\n` +
      `La rejilla quedará sobrecargada pero funcionalmente seguirá operativa.`;

    const confirmado = await confirmarAccionBonita(
      "Confirmar Exceso de Capacidad",
      confirmacionExceso,
      "Sí, Continuar",
      "Cancelar"
    );

    if (!confirmado) {
      logDebug("Usuario canceló por exceso de capacidad");
      return;
    }
  }

  const botonSubmit = document.querySelector(
    "#modalAsignarVentaCompleta .btn-success"
  );
  actualizarEstadoBoton(botonSubmit, true);
  isLoading = true;

  formData.append("accion", "reservar_venta_completa");
  formData.append("exceso_capacidad", exceso);

  logDebug("Enviando solicitud de venta completa al servidor");

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("modalAsignarVentaCompleta")
        );
        if (modal) {
          modal.hide();
        }

        logDebug("Venta completa asignada exitosamente", data);

        let mensajeToast = `Venta asignada exitosamente con ${data.productos_asignados} productos`;
        if (exceso > 0) {
          const excesoTexto =
            exceso >= 1000
              ? `${(exceso / 1000).toFixed(1)} toneladas`
              : `${Math.round(exceso)} kg`;
          mensajeToast += `\n⚠️ Rejilla excedió capacidad por ${excesoTexto}`;
        }

        mostrarToastBonito(
          "¡Venta Asignada Completa!",
          mensajeToast,
          exceso > 0 ? "warning" : "success",
          5000
        );

        setTimeout(() => {
          recargarPagina();
        }, EXPEDICION_SETTINGS.timeouts.reload);
      } else {
        throw new Error(data.error || "Error desconocido");
      }
    })
    .catch((error) => {
      logDebug("Error en asignación de venta completa", error, "error");
      mostrarToastBonito("Error en la Asignación", error.message, "error");
    })
    .finally(() => {
      actualizarEstadoBoton(botonSubmit, false);
      isLoading = false;
    });
}

// ===== CANCELAR RESERVA POR ID =====
async function cancelarReservaPorId(idAsignacion) {
  if (isLoading) {
    return;
  }

  if (!idAsignacion) {
    mostrarToastBonito("Error", "ID de asignación no válido", "error");
    return;
  }

  const confirmado = await confirmarAccionBonita(
    "Confirmar Cancelación",
    `¿Está seguro de cancelar esta reserva?\n\nEsta acción liberará el producto para futuras asignaciones.`,
    "Sí, Cancelar Reserva",
    "No, Mantener"
  );

  if (!confirmado) {
    logDebug("Usuario canceló la cancelación de reserva");
    return;
  }

  isLoading = true;

  const formData = new FormData();
  formData.append("accion", "cancelar_reserva");
  formData.append("id_asignacion", idAsignacion);

  logDebug(`Cancelando reserva con ID: ${idAsignacion}`);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        logDebug("Reserva cancelada exitosamente", data);

        mostrarToastBonito(
          "¡Reserva Cancelada!",
          data.mensaje ||
            "La reserva ha sido cancelada y el producto está disponible nuevamente",
          "success"
        );

        setTimeout(() => {
          recargarPagina();
        }, EXPEDICION_SETTINGS.timeouts.reload);
      } else {
        throw new Error(data.error || "Error desconocido");
      }
    })
    .catch((error) => {
      logDebug("Error cancelando reserva", error, "error");

      if (error.message.includes("HTTP") || error.message.includes("red")) {
        mostrarToastBonito(
          "Error de Conexión",
          `No se pudo conectar con el servidor: ${error.message}`,
          "error"
        );
      } else {
        mostrarToastBonito("Error Cancelando Reserva", error.message, "error");
      }
    })
    .finally(() => {
      isLoading = false;
    });
}



// ===== FUNCIONES GLOBALES PARA DEBUG =====
if (debugMode) {
  console.log(
    `[EXPEDICION-VENTAS] Módulo de ventas v${EXPEDICION_SETTINGS.version} cargado`,
    window.ExpedicionVentasDebug
  );
}

// Hacer funciones disponibles globalmente
window.asignarVentaCompleta = asignarVentaCompleta;
window.confirmarAsignacionVentaCompleta = confirmarAsignacionVentaCompleta;
window.cancelarReservaPorId = cancelarReservaPorId;
