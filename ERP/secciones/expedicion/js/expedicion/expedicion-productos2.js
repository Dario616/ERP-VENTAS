// =====================================
// EXPEDICION PRODUCTOS - Gestión de productos y formularios
// =====================================

// ===== FUNCIÓN PARA VERIFICAR SI ES PAÑO O TOALLITA =====
function esProductoPañoOToallita(nombreProducto) {
  if (!nombreProducto) return false;

  const nombreUpper = nombreProducto.toUpperCase();

  return (
    nombreUpper.includes("PAÑO") ||
    nombreUpper.includes("PAÑOS") ||
    nombreUpper.includes("TOALLITA") ||
    nombreUpper.includes("TOALLA")
  );
}

// ===== FUNCIÓN PARA DETERMINAR TIPO DE UNIDAD =====
function determinarTipoUnidad(nombreProducto) {
  if (!nombreProducto) return "unidades";

  const nombreUpper = nombreProducto.toUpperCase();

  for (const tipo of EXPEDICION_SETTINGS.tiposProducto.bobinas) {
    if (nombreUpper.includes(tipo)) {
      return "bobinas";
    }
  }

  for (const tipo of EXPEDICION_SETTINGS.tiposProducto.cajas) {
    if (nombreUpper.includes(tipo)) {
      return "cajas";
    }
  }

  return "unidades";
}

// ===== FUNCIÓN PARA OBTENER TEXTO DE UNIDAD =====
function obtenerTextoUnidad(tipoProducto, nombreProducto = "") {
  const tipo = determinarTipoUnidad(nombreProducto || tipoProducto);

  switch (tipo) {
    case "bobinas":
      return "bobinas";
    case "cajas":
      const nombreUpper = (nombreProducto || tipoProducto).toUpperCase();
      if (nombreUpper.includes("PAÑO")) {
        return "cajas de paños";
      } else if (nombreUpper.includes("TOALLITA")) {
        return "cajas de toallitas";
      }
      return "cajas";
    default:
      return "unidades";
  }
}

// ===== VALIDACIÓN DE CÁLCULO =====
function validarCalculoPesoTotal(tipoProducto, cantidad, pesoUnitario) {
  return cantidad * pesoUnitario;
}

// ===== FUNCIÓN DE DEBUG DE CÁLCULO =====
function logCalculoDebug(nombreProducto, cantidad, pesoUnitario, pesoTotal) {
  if (debugMode) {
    const esPañoOToallita = esProductoPañoOToallita(nombreProducto);
    const tipoCalculo = esPañoOToallita
      ? "cantidad × peso_unitario"
      : "peso_total ÷ peso_unitario";

    console.log(`[CÁLCULO CORREGIDO] ${nombreProducto}:`, {
      es_paño_toallita: esPañoOToallita,
      cantidad: cantidad,
      peso_unitario: pesoUnitario,
      peso_total: pesoTotal,
      tipo_calculo: tipoCalculo,
      logica: esPañoOToallita
        ? "Usar cantidad directa y calcular peso"
        : "Usar peso total y calcular cantidad",
    });
  }
}

// ===== GENERAR CONTENIDO DE VENTAS SIMPLIFICADO =====
function generarContenidoVentasSimplificado(ventas, nombreCliente) {
  const contenedor = document.getElementById("contenidoVentasProductos");
  if (!contenedor) {
    logDebug("Contenedor de ventas no encontrado", null, "error");
    return;
  }

  if (!Array.isArray(ventas)) {
    mostrarErrorModal(
      "Error: Los datos recibidos no tienen el formato esperado"
    );
    return;
  }

  if (ventas.length === 0) {
    mostrarMensajeVacioMejorado();
    return;
  }

  contenedor.innerHTML = "";

  try {
    ventas.forEach((venta, index) => {
      if (
        !venta ||
        typeof venta !== "object" ||
        !venta.id_venta ||
        !Array.isArray(venta.productos)
      ) {
        logDebug(`Venta inválida en índice ${index}`, venta, "error");
        return;
      }

      const ventaHtml = generarHtmlVentaSimplificado(venta, nombreCliente);
      contenedor.innerHTML += ventaHtml;
    });

    if (contenedor.innerHTML.trim() === "") {
      mostrarMensajeVacioMejorado();
      return;
    }

    setTimeout(() => {
      inicializarElementosDinamicos();
    }, 100);

    logDebug(`Contenido generado para ${ventas.length} ventas`);
  } catch (error) {
    mostrarErrorModal("Error procesando los datos de ventas");
    logDebug("Error generando contenido de ventas", error, "error");
  }
}

// ===== ✅ OPTIMIZADO: GENERAR HTML DE VENTA COMPACTO =====
function generarHtmlVentaSimplificado(venta, nombreCliente) {
  if (!venta || !venta.id_venta || !Array.isArray(venta.productos)) {
    return '<div class="alert alert-warning p-2 small">Venta con datos incompletos</div>';
  }

  let productosHtml = "";
  let totalPesoVenta = 0;
  let totalUnidadesVenta = 0;
  let productosDisponibles = 0;

  try {
    venta.productos.forEach((producto, index) => {
      if (!producto || typeof producto !== "object") {
        return;
      }

      try {
        const productoHtml = generarHtmlProductoSimplificado(
          producto,
          nombreCliente,
          venta
        );
        productosHtml += productoHtml;

        totalPesoVenta += parseFloat(producto.peso_total_vendido_kg || 0);
        totalUnidadesVenta += parseInt(
          producto.cantidad_unidades_vendidas || 0
        );

        const disponible = parseInt(
          producto.disponible_para_reservar_unidades || 0
        );
        if (disponible > 0) {
          productosDisponibles++;
        }
      } catch (error) {
        productosHtml += `
          <div class="alert alert-warning p-2 small">
            Error procesando producto: ${
              producto.nombre_producto || "Desconocido"
            }
          </div>
        `;
        logDebug(
          `Error procesando producto ${producto.nombre_producto}`,
          error,
          "error"
        );
      }
    });
  } catch (error) {
    logDebug(
      `Error procesando productos de venta ${venta.id_venta}`,
      error,
      "error"
    );
    return `
      <div class="alert alert-danger p-2 small">
        <strong>Venta #${venta.id_venta}</strong> - Error procesando productos
      </div>
    `;
  }

  const fechaVenta = formatearFecha(venta.fecha_venta);

  return `
    <div class="venta-group mb-2" data-venta-id="${venta.id_venta}">
      <div class="venta-header p-2 bg-light border rounded" onclick="toggleVentaModal(this)" style="cursor: pointer;">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center">
            <span class="venta-badge badge bg-primary me-2" style="font-size: 0.7rem;">Venta #${
              venta.id_venta
            }</span>
            <small class="text-muted me-2" style="font-size: 0.7rem;">${
              venta.productos.length || 0
            } productos</small>
            <small class="text-muted" style="font-size: 0.7rem;">${fechaVenta}</small>
          </div>
          <div class="d-flex align-items-center gap-1">
            <span class="badge bg-primary" style="font-size: 0.65rem;">${totalUnidadesVenta} unid.</span>
            <span class="badge bg-success" style="font-size: 0.65rem;">${totalPesoVenta.toFixed(
              1
            )} kg</span>
            
            ${
              productosDisponibles >= 1
                ? `
              <button type="button" 
                      class="btn btn-outline-success btn-sm py-0 px-1"
                      onclick="event.stopPropagation(); asignarVentaCompleta(${
                        venta.id_venta
                      }, '${nombreCliente.replace(/'/g, "\\'")}')"
                      title="Asignar todos los productos de esta venta"
                      style="font-size: 0.7rem;">
                <i class="fas fa-rocket me-1"></i>
                Asignar Venta
              </button>
            `
                : ""
            }
            
            <i class="fas fa-chevron-down toggle-icon" style="font-size: 0.8rem;"></i>
          </div>
        </div>
      </div>
      <div class="venta-content" style="display: none;">
        ${
          productosHtml ||
          '<div class="alert alert-info p-2 small">No hay productos pendientes en esta venta</div>'
        }
      </div>
    </div>
  `;
}

// ===== ✅ OPTIMIZADO: GENERAR HTML DE PRODUCTO COMPACTO =====
function generarHtmlProductoSimplificado(producto, nombreCliente, venta) {
  if (
    !producto ||
    !producto.nombre_producto ||
    !producto.id_producto_presupuesto
  ) {
    return '<div class="alert alert-warning p-2 small">Producto sin datos suficientes</div>';
  }

  const tipoProducto = producto.tipo_producto || "GENERICO";
  const tipoClase = tipoProducto.toLowerCase();

  const pesoTotalVendido = parseFloat(producto.peso_total_vendido_kg || 0);
  const pesoUnitario = parseFloat(producto.peso_unitario_kg || 0);
  const unidadesVendidas = parseInt(producto.cantidad_unidades_vendidas || 0);

  const pesoProduccion = parseFloat(producto.peso_asignado_produccion_kg || 0);
  const unidadesProduccion = parseInt(
    producto.unidades_asignadas_produccion || 0
  );

  const pesoExpedicion = parseFloat(producto.peso_asignado_expedicion_kg || 0);
  const unidadesExpedicion = parseInt(
    producto.unidades_asignadas_expedicion || 0
  );

  const pesoPendiente = pesoTotalVendido - pesoProduccion - pesoExpedicion;
  const unidadesPendientes =
    unidadesVendidas - unidadesProduccion - unidadesExpedicion;

  const porcentajeProduccion =
    pesoTotalVendido > 0 ? (pesoProduccion / pesoTotalVendido) * 100 : 0;
  const porcentajeExpedicion =
    pesoTotalVendido > 0 ? (pesoExpedicion / pesoTotalVendido) * 100 : 0;
  const porcentajePendiente =
    pesoTotalVendido > 0 ? (pesoPendiente / pesoTotalVendido) * 100 : 0;

  const cantidadReservada = parseFloat(producto.cantidad_reservada || 0);
  const disponibleParaReservar = Math.max(
    0,
    unidadesVendidas - cantidadReservada
  );

  const unidadMedida = obtenerTextoUnidad(
    tipoProducto,
    producto.nombre_producto
  );

  logCalculoDebug(
    producto.nombre_producto,
    unidadesVendidas,
    pesoUnitario,
    pesoTotalVendido
  );

  return `
    <div class="producto-group-modal border-start border-1 border-black ps-2 ms-2 mt-2" data-producto-id="${
      producto.id_producto_presupuesto
    }">
      <div class="producto-header-modal p-1" onclick="toggleProductoSimplificado(this)" style="cursor: pointer;">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center">
            <span class="tipo-badge tipo-${tipoClase} badge bg-secondary me-2" style="font-size: 0.65rem;">${tipoProducto}</span>
            <strong class="me-2" style="font-size: 0.8rem;">${escapeHtml(
              producto.nombre_producto
            )}</strong>
            ${
              pesoUnitario > 0
                ? `<small class="text-muted" style="font-size: 0.7rem;">(${pesoUnitario.toFixed(
                    2
                  )} kg/unidad)</small>`
                : ""
            }
          </div>
          <div class="d-flex align-items-center gap-1">
            <span class="badge bg-info" style="font-size: 0.65rem;">${disponibleParaReservar} ${unidadMedida} disp.</span>
            <span class="badge bg-success" style="font-size: 0.65rem;">${pesoTotalVendido.toFixed(
              1
            )} kg</span>
            ${generarBadgeEstadoAsignacion(porcentajePendiente)}
            <i class="fas fa-chevron-down toggle-icon" style="font-size: 0.7rem;"></i>
          </div>
        </div>
      </div>
      <div class="producto-content-modal" style="display: none;">
        
        <!-- Resumen compacto -->
        ${generarResumenProduccionExpedicionCompacto(
          producto,
          unidadesVendidas,
          pesoTotalVendido,
          unidadMedida,
          pesoProduccion,
          pesoExpedicion,
          unidadesProduccion,
          unidadesExpedicion,
          porcentajeProduccion,
          porcentajeExpedicion,
          porcentajePendiente
        )}
        
        <!-- Formulario compacto -->
        ${
          disponibleParaReservar > 0
            ? generarFormularioReservaCompletaCompacto(
                producto,
                nombreCliente,
                venta,
                disponibleParaReservar,
                unidadMedida,
                pesoUnitario
              )
            : generarMensajeCompletoReservado()
        }
        
      </div>
    </div>
  `;
}

// ===== ✅ OPTIMIZADO: RESUMEN COMPACTO =====
function generarResumenProduccionExpedicionCompacto(
  producto,
  unidadesVendidas,
  pesoTotalVendido,
  unidadMedida,
  pesoProduccion,
  pesoExpedicion,
  unidadesProduccion,
  unidadesExpedicion,
  porcentajeProduccion,
  porcentajeExpedicion,
  porcentajePendiente
) {
  return `
    <div class="presupuesto-resumen p-2 bg-light border rounded mt-2">
      <h6 class="fw-bold text-primary mb-2 small">
        <i class="fas fa-chart-pie me-2"></i>
        Asignación: Producción / Desde Stock
      </h6>
      
      <div class="row g-2 mb-2">
        <div class="col-4">
          <div class="stat-card-compact text-center p-1 bg-white border rounded">
            <div class="stat-value-compact fw-bold">${unidadesVendidas}</div>
            <div class="stat-label-compact small text-muted">PEDIDO</div>
            <small class="text-muted" style="font-size: 0.65rem;">${pesoTotalVendido.toFixed(
              1
            )} kg</small>
          </div>
        </div>
        <div class="col-4">
          <div class="stat-card-compact text-center p-1 border rounded" style="background-color: rgba(255, 193, 7, 0.32); border-color: rgba(255, 193, 7, 0.05) !important;">
            <div class="stat-value-compact fw-bold" style="color: rgba(192, 144, 0, 1);">${unidadesProduccion}</div>
            <div class="stat-label-compact small text-muted">PRODUCCIÓN</div>
            <small class="text-muted" style="font-size: 0.65rem;">${pesoProduccion.toFixed(
              1
            )} kg (${porcentajeProduccion.toFixed(1)}%)</small>
          </div>
        </div>
        <div class="col-4">
          <div class="stat-card-compact text-center p-1 border rounded" style="background-color: rgba(1, 213, 255, 0.27); border-color: rgba(13, 202, 240, 0.05) !important;">
            <div class="stat-value-compact fw-bold" style="color: rgba(0, 163, 196, 1);">${unidadesExpedicion}</div>
            <div class="stat-label-compact small text-muted">DESDE STOCK</div>
            <small class="text-muted" style="font-size: 0.65rem;">${pesoExpedicion.toFixed(
              1
            )} kg (${porcentajeExpedicion.toFixed(1)}%)</small>
          </div>
        </div>
      </div>
    
      <!-- Barra de progreso compacta -->
      <div class="progreso-container-compact">
        <div class="progreso-bar-compact bg-light border rounded" style="height: 15px; position: relative;">
          <div class="progreso-fill-multi d-flex" style="height: 100%;">
            <div class="progreso-segment" 
                 style="width: ${porcentajeProduccion}%; background: linear-gradient(90deg, #fff3cd, #f8e5a0); border-radius: 2px;" 
                 title="Producción: ${porcentajeProduccion.toFixed(1)}%"></div>
            <div class="progreso-segment" 
                 style="width: ${porcentajeExpedicion}%; background: linear-gradient(90deg, #d1ecf1, #b8dde6); border-radius: 2px;" 
                 title="Desde Stock: ${porcentajeExpedicion.toFixed(1)}%"></div>
          </div>
          <div class="progreso-text-compact position-absolute top-50 start-50 translate-middle" style="font-size: 0.65rem; font-weight: 600;">
            ${porcentajeProduccion.toFixed(
              1
            )}% Prod | ${porcentajeExpedicion.toFixed(1)}% Stock
            ${
              porcentajePendiente > 0
                ? ` | ${porcentajePendiente.toFixed(1)}% Pend.`
                : ""
            }
          </div>
        </div>
      </div>
    </div>
  `;
}

// ===== ✅ OPTIMIZADO: BADGE DE ESTADO COMPACTO =====
function generarBadgeEstadoAsignacion(porcentajePendiente) {
  if (porcentajePendiente <= 0) {
    return '<span class="badge bg-success" style="font-size: 0.65rem;">Completo</span>';
  } else if (porcentajePendiente <= 20) {
    return '<span class="badge bg-info" style="font-size: 0.65rem;">Casi Completo</span>';
  } else if (porcentajePendiente <= 50) {
    return '<span class="badge bg-warning" style="font-size: 0.65rem;">Parcial</span>';
  } else {
    return '<span class="badge bg-danger" style="font-size: 0.65rem;">Pendiente</span>';
  }
}

// ===== ✅ OPTIMIZADO: FORMULARIO COMPACTO =====
function generarFormularioReservaCompletaCompacto(
  producto,
  nombreCliente,
  venta,
  disponibleParaReservar,
  unidadMedida,
  pesoUnitario
) {
  const pesoDisponible = disponibleParaReservar * pesoUnitario;
  const esPañoOToallita = esProductoPañoOToallita(producto.nombre_producto);

  return `
    <div class="asignacion-completa-form mt-2 p-2 bg-success bg-opacity-10 border border-success rounded">
      <h6 class="fw-bold text-success mb-2 small">
        <i class="fas fa-rocket me-2"></i>
        Asignar a Rejillas
      </h6>
      
      <form class="expedicion-form-simplificado" data-form-type="reserva-completa">
        <input type="hidden" name="id_venta" value="${venta.id_venta}">
        <input type="hidden" name="id_producto_presupuesto" value="${
          producto.id_producto_presupuesto
        }">
        <input type="hidden" name="nombre_producto" value="${escapeHtml(
          producto.nombre_producto
        )}">
        <input type="hidden" name="cliente" value="${escapeHtml(
          nombreCliente
        )}">
        
        <!-- Información compacta -->
        <div class="peso-info-card-compact mb-2 p-2 bg-white border rounded">
          <div class="d-flex align-items-center mb-1">
            <i class="fas fa-calculator me-2 text-primary" style="font-size: 0.8rem;"></i>
            <strong class="small">Se asignará todo lo disponible:</strong>
          </div>
          <div class="row text-center g-1">
            <div class="col-4">
              <div class="fw-bold text-primary" style="font-size: 0.9rem;">${disponibleParaReservar}</div>
              <small class="text-muted" style="font-size: 0.65rem;">${unidadMedida}</small>
            </div>
            <div class="col-4">
              <div class="fw-bold text-success" style="font-size: 0.9rem;">${pesoDisponible.toFixed(
                2
              )}</div>
              <small class="text-muted" style="font-size: 0.65rem;">kg total</small>
            </div>
            <div class="col-4">
              <div class="fw-bold text-info" style="font-size: 0.9rem;">${pesoUnitario.toFixed(
                2
              )}</div>
              <small class="text-muted" style="font-size: 0.65rem;">kg/${
                esPañoOToallita ? "caja" : "unidad"
              }</small>
            </div>
          </div>
        </div>
        
        <div class="row g-2 align-items-end">
          <div class="col-8">
            <label class="form-label small fw-bold mb-1">Rejilla destino</label>
            <select class="form-select form-select-sm rejilla-select" name="id_rejilla" required>
              <option value="">Seleccionar rejilla...</option>
              ${generarOpcionesRejillas()}
            </select>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-success btn-sm w-100 btn-reservar-completo py-1">
              <i class="fas fa-rocket me-1"></i>
              <span class="btn-text small">Asignar</span>
              <span class="btn-loading" style="display: none;">
                <i class="fas fa-spinner fa-spin"></i>
              </span>
            </button>
          </div>
        </div>
        
        <div class="alert alert-success mt-2 mb-0 p-1" style="display: none; font-size: 0.7rem;" id="info-rejilla">
          <i class="fas fa-check-circle me-1"></i>
          <small>Información de la rejilla aparecerá aquí</small>
        </div>
      </form>
    </div>
  `;
}

// ===== GENERAR MENSAJE COMPACTO =====
function generarMensajeCompletoReservado() {
  return `
    <div class="alert alert-success mt-2 mb-0 p-2">
      <i class="fas fa-check-circle me-2"></i>
      <strong class="small">Producto completamente asignado</strong><br>
      <small style="font-size: 0.7rem;">Este producto ya está completamente asignado a rejillas.</small>
    </div>
  `;
}

// ===== PROCESAR FORMULARIO DE EXPEDICIÓN SIMPLIFICADO =====
function procesarFormularioExpedicionSimplificado(form) {
  if (isLoading) {
    return;
  }

  const formData = new FormData(form);
  const tipoForm = form.dataset.formType;

  logDebug(`Procesando formulario tipo: ${tipoForm}`);

  if (tipoForm === "reserva-completa") {
    procesarReservaCompleta(formData, form);
  }
}

// ===== PROCESAR RESERVA COMPLETA =====
async function procesarReservaCompleta(formData, form) {
  const idRejilla = parseInt(formData.get("id_rejilla"));
  const nombreProducto = formData.get("nombre_producto");
  const cliente = formData.get("cliente");

  if (!idRejilla) {
    form.querySelector('[name="id_rejilla"]').focus();
    mostrarToastBonito(
      "Error de Validación",
      "Debe seleccionar una rejilla",
      "warning"
    );
    return;
  }

  const rejillaInfo = EXPEDICION_CONFIG.rejillasDisponibles.find(
    (r) => r.id == idRejilla
  );
  const rejillaNombre = rejillaInfo
    ? `#${rejillaInfo.numero_rejilla}`
    : idRejilla;

  const tipoUnidad = determinarTipoUnidad(nombreProducto);
  const textoUnidad = obtenerTextoUnidad("", nombreProducto);
  const esPañoOToallita = esProductoPañoOToallita(nombreProducto);

  const confirmMessage =
    `¿Confirma asignar a rejillas el producto "${nombreProducto}"?\n\n` +
    `• Cliente: ${cliente}\n` +
    `• Rejilla: ${rejillaNombre}\n` +
    `• Tipo: ${textoUnidad}\n` +
    `• Sistema: Asignación completa automática\n` +
    `• ✅ Lógica: ${
      esPañoOToallita
        ? "Cantidad real × peso unitario"
        : "Peso total ÷ peso unitario"
    }`;

  const confirmado = await confirmarAccionBonita(
    "Confirmar Asignación",
    confirmMessage,
    "Sí, Asignar",
    "Cancelar"
  );

  if (!confirmado) {
    logDebug("Usuario canceló la asignación");
    return;
  }

  const botonSubmit = form.querySelector('button[type="submit"]');
  actualizarEstadoBoton(botonSubmit, true);
  isLoading = true;

  logDebug(
    `Iniciando asignación: ${nombreProducto} a rejilla ${rejillaNombre}`
  );

  formData.append("accion", "reservar_completo");

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
        logDebug("Producto asignado correctamente", {
          producto: nombreProducto,
          unidades_asignadas: data.unidades_asignadas,
          peso_total: data.peso_total_asignado,
          peso_unitario: data.peso_unitario,
          tipo_unidad: data.tipo_unidad,
        });

        mostrarToastBonito(
          "¡Producto Asignado!",
          `${nombreProducto} ha sido asignado correctamente a la rejilla ${rejillaNombre}`,
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
      logDebug("Error en asignación", error, "error");

      if (error.message.includes("HTTP") || error.message.includes("red")) {
        mostrarToastBonito(
          "Error de Conexión",
          `No se pudo conectar con el servidor: ${error.message}`,
          "error"
        );
      } else {
        mostrarToastBonito("Error en la Asignación", error.message, "error");
      }
    })
    .finally(() => {
      actualizarEstadoBoton(botonSubmit, false);
      isLoading = false;
    });
}

// Hacer funciones disponibles globalmente
window.esProductoPañoOToallita = esProductoPañoOToallita;
window.determinarTipoUnidad = determinarTipoUnidad;
window.obtenerTextoUnidad = obtenerTextoUnidad;
window.validarCalculoPesoTotal = validarCalculoPesoTotal;
window.logCalculoDebug = logCalculoDebug;
window.generarContenidoVentasSimplificado = generarContenidoVentasSimplificado;
window.procesarFormularioExpedicionSimplificado =
  procesarFormularioExpedicionSimplificado;
