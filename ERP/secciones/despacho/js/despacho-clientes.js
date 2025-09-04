// =====================================
// DESPACHO CLIENTES - Gestión de clientes y reasignación
// =====================================

// ===== CARGAR VISTA PREVIA DE CLIENTES =====
function cargarVistaPreviaClientes(numeroExpedicion) {
  const contenedorClientes = document.getElementById("contenedorItemsClientes");

  if (!contenedorClientes) {
    logDebug("Contenedor de items por cliente no encontrado");
    return;
  }

  contenedorClientes.innerHTML = `
    <div class="text-center p-3">
      <i class="fas fa-spinner fa-spin me-2"></i>Cargando vista previa...
    </div>`;

  const formData = new FormData();
  formData.append("accion", "obtener_vista_previa_clientes");
  formData.append("numero_expedicion", numeroExpedicion);

  logDebug("Cargando vista previa para expedición", numeroExpedicion);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarVistaPreviaClientes(data.clientes || []);
        actualizarEstadisticasVistaPrevia(data.estadisticas || {});
        logDebug("Vista previa cargada", data);
      } else {
        cargarItemsPorCliente(numeroExpedicion);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      cargarItemsPorCliente(numeroExpedicion);
      logDebug("Error de red cargando vista previa", error);
    });
}

// ===== MOSTRAR VISTA PREVIA CLIENTES =====
function mostrarVistaPreviaClientes(asignaciones) {
  const contenedorClientes = document.getElementById("contenedorItemsClientes");

  if (!contenedorClientes) {
    logDebug("Contenedor de vista previa no encontrado");
    return;
  }

  if (asignaciones.length === 0) {
    contenedorClientes.innerHTML = `
      <div class="text-center text-muted p-4">
        <i class="fas fa-clipboard-list me-2 fs-4"></i>
        <h5>No hay asignaciones configuradas</h5>
        <p>Configure asignaciones de rejilla para ver clientes aquí</p>
        <small>Los items se asignarán como DESCONOCIDOS</small><br>
        <small class="text-warning">📍 Items fuera de rejilla permitidos como DESCONOCIDOS</small>
      </div>`;
    return;
  }

  let html = "";
  const clientesAgrupados = {};

  asignaciones.forEach((asignacion) => {
    const cliente = asignacion.cliente;
    if (!clientesAgrupados[cliente]) {
      clientesAgrupados[cliente] = [];
    }
    clientesAgrupados[cliente].push(asignacion);
  });

  Object.keys(clientesAgrupados).forEach((cliente) => {
    html += generarHtmlCliente(cliente, clientesAgrupados[cliente]);
  });

  contenedorClientes.innerHTML = html;

  setTimeout(() => {
    configurarBotonesReasignarIndividuales();

    if (debugMode) {
      logDebug("Vista previa renderizada", {
        totalClientes: Object.keys(clientesAgrupados).length,
        totalAsignaciones: asignaciones.length,
        botonesReasignar: document.querySelectorAll(".btn-reasignar").length,
      });
    }
  }, 100);

  logDebug("Vista previa mostrada", {
    totalClientes: Object.keys(clientesAgrupados).length,
    totalAsignaciones: asignaciones.length,
  });
}

// ===== GENERAR HTML DE CLIENTE =====
function generarHtmlCliente(cliente, asignacionesCliente) {
  const esDesconocido = cliente === "DESCONOCIDO";

  // Calcular totales del cliente
  const totalItemsCliente = asignacionesCliente.reduce(
    (sum, a) => sum + (parseInt(a.total_items_escaneados) || 0),
    0
  );
  const totalUnidadesCliente = asignacionesCliente.reduce(
    (sum, a) => sum + (parseInt(a.cantidad_total_escaneada) || 0),
    0
  );
  const totalPesoCliente = asignacionesCliente.reduce(
    (sum, a) => sum + (parseFloat(a.peso_total_escaneado) || 0),
    0
  );
  const totalAsignadoCliente = asignacionesCliente.reduce(
    (sum, a) => sum + (parseInt(a.total_asignado) || 0),
    0
  );
  const totalYaDespachado = asignacionesCliente.reduce(
    (sum, a) => sum + (parseInt(a.cantidad_ya_despachada) || 0),
    0
  );

  // Verificar si hay items fuera de rejilla
  const hayItemsFueraDeRejilla = asignacionesCliente.some(
    (a) => a.fuera_de_rejilla || false
  );

  // Calcular progreso
  const totalDespachado = totalYaDespachado + totalUnidadesCliente;
  const progresoGeneralCliente =
    !esDesconocido && totalAsignadoCliente > 0
      ? (totalDespachado / totalAsignadoCliente) * 100
      : 0;

  // Determinar estado y clase
  const { estadoCliente, claseCliente, iconoCliente } = determinarEstadoCliente(
    esDesconocido,
    hayItemsFueraDeRejilla,
    progresoGeneralCliente
  );

  let html = `
    <div class="cliente-grupo mb-4">
      <div class="card">
        <div class="card-header ${claseCliente} d-flex justify-content-between align-items-center">
          ${generarHeaderCliente(
            cliente,
            asignacionesCliente,
            iconoCliente,
            esDesconocido,
            hayItemsFueraDeRejilla,
            totalItemsCliente,
            totalUnidadesCliente,
            totalAsignadoCliente,
            totalPesoCliente,
            totalYaDespachado,
            progresoGeneralCliente
          )}
        </div>
        <div class="card-body">
          ${generarBodyCliente(
            esDesconocido,
            totalAsignadoCliente,
            progresoGeneralCliente,
            totalYaDespachado,
            totalUnidadesCliente,
            totalDespachado
          )}
          <div class="accordion" id="acordion_${cliente.replace(/\s+/g, "_")}">
            ${generarAsignacionesCliente(
              cliente,
              asignacionesCliente,
              esDesconocido
            )}
          </div>
        </div>
      </div>
    </div>
  `;

  return html;
}

function determinarEstadoCliente(
  esDesconocido,
  hayItemsFueraDeRejilla,
  progreso
) {
  let estadoCliente = "pendiente";
  let claseCliente = "bg-light text-dark";
  let iconoCliente = "⏳";

  if (esDesconocido) {
    if (hayItemsFueraDeRejilla) {
      estadoCliente = "fuera_de_rejilla";
      claseCliente = "bg-info text-white";
      iconoCliente = "📍";
    } else {
      estadoCliente = "desconocido";
      claseCliente = "bg-warning text-dark";
      iconoCliente = "❓";
    }
  } else if (progreso > 100) {
    estadoCliente = "excedido";
    claseCliente = "bg-danger text-white";
    iconoCliente = "⚠️";
  } else if (progreso >= 100) {
    estadoCliente = "completado";
    claseCliente = "bg-success";
    iconoCliente = "✅";
  } else if (progreso >= 75) {
    estadoCliente = "casi_completo";
    claseCliente = "bg-warning";
    iconoCliente = "🟡";
  } else if (progreso >= 25) {
    estadoCliente = "en_progreso";
    claseCliente = "bg-info";
    iconoCliente = "🔵";
  } else if (progreso > 0) {
    estadoCliente = "iniciado";
    claseCliente = "bg-primary";
    iconoCliente = "🔵";
  }

  return { estadoCliente, claseCliente, iconoCliente };
}

function generarHeaderCliente(
  cliente,
  asignacionesCliente,
  iconoCliente,
  esDesconocido,
  hayItemsFueraDeRejilla,
  totalItemsCliente,
  totalUnidadesCliente,
  totalAsignadoCliente,
  totalPesoCliente,
  totalYaDespachado,
  progresoGeneralCliente
) {
  return `
    <div class="d-flex align-items-center">
      <span class="me-2 fs-5">${iconoCliente}</span>
      <div>
        <strong>${escapeHtml(cliente)}</strong>
        <span class="badge bg-info ms-2">${asignacionesCliente.length} ${
    esDesconocido ? "items" : "asignaciones"
  }</span>
        ${
          !esDesconocido
            ? '<span class="badge bg-success ms-1"><i class="fas fa-check me-1"></i>Validado</span>'
            : hayItemsFueraDeRejilla
            ? '<span class="badge bg-light text-dark ms-1"><i class="fas fa-map-marker-alt me-1"></i>Fuera de Rejilla</span>'
            : '<span class="badge bg-warning ms-1"><i class="fas fa-question-circle me-1"></i>Reasignable</span>'
        }
      </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <span class="badge bg-light text-dark">${totalItemsCliente} items</span>
      <span class="badge bg-light text-dark">${totalUnidadesCliente}${
    !esDesconocido ? "/" + totalAsignadoCliente : ""
  } bobinas/unidades</span>
      <span class="badge bg-light text-dark">${totalPesoCliente.toFixed(
        1
      )} kg</span>
      ${
        !esDesconocido && totalYaDespachado > 0
          ? `<span class="badge bg-success">✓ ${totalYaDespachado} ya despachado</span>`
          : ""
      }
      ${
        progresoGeneralCliente > 100
          ? `<span class="badge bg-danger">EXCEDIDO ${progresoGeneralCliente.toFixed(
              1
            )}%</span>`
          : ""
      }
    </div>
  `;
}

function generarBodyCliente(
  esDesconocido,
  totalAsignadoCliente,
  progresoGeneralCliente,
  totalYaDespachado,
  totalUnidadesCliente,
  totalDespachado
) {
  if (esDesconocido || totalAsignadoCliente === 0) {
    return "";
  }

  const progresoMostrado = Math.min(progresoGeneralCliente, 100);
  const progresoYaDespachado =
    totalAsignadoCliente > 0
      ? (totalYaDespachado / totalAsignadoCliente) * 100
      : 0;
  const progresoExpedicionActual =
    totalAsignadoCliente > 0
      ? (totalUnidadesCliente / totalAsignadoCliente) * 100
      : 0;

  return `
    <div class="mb-3">
      <div class="d-flex justify-content-between small mb-1">
        <span><strong>Progreso Total: ${totalDespachado}/${totalAsignadoCliente}</strong></span>
        <span><strong>${progresoGeneralCliente.toFixed(1)}%</strong>${
    progresoGeneralCliente > 100
      ? ' <i class="fas fa-exclamation-triangle text-danger" title="Excede asignación"></i>'
      : ""
  }</span>
      </div>
      
      ${
        totalYaDespachado > 0
          ? `
        <div class="small text-muted mb-1">
          <i class="fas fa-check-circle text-success me-1"></i>
          Ya despachado anteriormente: <strong>${totalYaDespachado}</strong> bobinas/unidades
        </div>
        <div class="small text-primary mb-1">
          <i class="fas fa-scanner me-1"></i>
          Escaneado en esta expedición: <strong>${totalUnidadesCliente}</strong> bobinas/unidades
        </div>
      `
          : ""
      }
      
      <div class="progress" style="height: 8px;">
        <div class="progress-bar bg-success" 
             style="width: ${Math.min(progresoYaDespachado, 100)}%" 
             title="Ya despachado anteriormente"></div>
        <div class="progress-bar ${
          progresoGeneralCliente > 100 ? "bg-danger" : "bg-primary"
        }" 
             style="width: ${Math.min(
               Math.max(progresoExpedicionActual, 0),
               100 - Math.min(progresoYaDespachado, 100)
             )}%" 
             title="Escaneado en esta expedición"></div>
      </div>
      
      ${
        progresoGeneralCliente > 100
          ? `<small class="text-danger">
            <i class="fas fa-exclamation-triangle me-1"></i>
            ⚠️ EXCEDE ASIGNACIÓN por ${
              totalDespachado - totalAsignadoCliente
            } bobinas/unidades
           </small>`
          : `<small class="text-muted">
            ${totalAsignadoCliente - totalDespachado} unidades restantes
           </small>`
      }
    </div>
  `;
}

function generarAsignacionesCliente(
  cliente,
  asignacionesCliente,
  esDesconocido
) {
  let html = "";

  asignacionesCliente.forEach((asignacion, index) => {
    html += generarAsignacionIndividual(
      asignacion,
      index,
      cliente,
      esDesconocido
    );
  });

  return html;
}

function generarAsignacionIndividual(
  asignacion,
  index,
  cliente,
  esDesconocido
) {
  const asignacionId = asignacion.asignacion_id || asignacion.id || index;
  const progresoAsignacion = !esDesconocido
    ? parseFloat(asignacion.progreso_cantidad) || 0
    : 0;
  const estadoAsignacion = asignacion.estado;
  const esFueraDeRejilla = asignacion.fuera_de_rejilla || false;

  let claseAsignacion = "text-muted";
  let iconoAsignacion = "⏳";

  if (esDesconocido) {
    if (esFueraDeRejilla) {
      claseAsignacion = "text-info";
      iconoAsignacion = "📍";
    } else {
      claseAsignacion = "text-warning";
      iconoAsignacion = "❓";
    }
  } else if (progresoAsignacion > 100) {
    claseAsignacion = "text-danger";
    iconoAsignacion = "⚠️";
  } else if (estadoAsignacion === "completado") {
    claseAsignacion = "text-success";
    iconoAsignacion = "✅";
  } else if (progresoAsignacion > 0) {
    claseAsignacion = "text-primary";
    iconoAsignacion = "🔵";
  }

  const accordionId = `asignacion_${asignacionId}_${cliente.replace(
    /\s+/g,
    "_"
  )}`;
  const dataAsignacionId = esDesconocido ? "DESCONOCIDO" : asignacionId || "";

  return `
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" 
                data-bs-toggle="collapse" data-bs-target="#${accordionId}">
          <div class="d-flex justify-content-between w-100 me-3">
            <div class="d-flex align-items-center">
              <span class="me-2">${iconoAsignacion}</span>
              <strong>${escapeHtml(
                asignacion.productos_asignados ||
                  asignacion.productos ||
                  "Sin especificar"
              )}</strong>
              ${
                !esDesconocido && asignacion.rejilla_numero
                  ? `<span class="badge bg-secondary ms-2">Rejilla #${asignacion.rejilla_numero}</span>`
                  : ""
              }
              ${
                esDesconocido
                  ? esFueraDeRejilla
                    ? `<span class="badge bg-info ms-2">Fuera de Rejilla</span>`
                    : `<span class="badge bg-warning ms-2">Requiere asignación</span>`
                  : ""
              }
              ${
                progresoAsignacion > 100
                  ? `<span class="badge bg-danger ms-2">EXCEDIDO</span>`
                  : ""
              }
            </div>
            <div class="text-end">
              <small class="${claseAsignacion}">
                <strong>${asignacion.cantidad_total_escaneada || 0}${
    !esDesconocido ? "/" + (asignacion.total_asignado || 0) : ""
  }</strong>
                ${
                  !esDesconocido
                    ? `(${progresoAsignacion.toFixed(1)}%)`
                    : " bob/uni"
                }
              </small>
            </div>
          </div>
        </button>
      </h2>
      <div id="${accordionId}" class="accordion-collapse collapse">
        <div class="accordion-body">
          ${generarDetalleAsignacion(
            asignacion,
            esDesconocido,
            esFueraDeRejilla,
            progresoAsignacion,
            dataAsignacionId,
            cliente
          )}
        </div>
      </div>
    </div>
  `;
}

function generarDetalleAsignacion(
  asignacion,
  esDesconocido,
  esFueraDeRejilla,
  progresoAsignacion,
  dataAsignacionId,
  cliente
) {
  return `
    <div class="row">
      <div class="col-8">
        <div><strong>Producto:</strong> ${escapeHtml(
          asignacion.productos_asignados ||
            asignacion.productos ||
            "Sin especificar"
        )}</div>
        ${
          asignacion.productos &&
          asignacion.productos !== (asignacion.productos_asignados || "")
            ? `<div class="small text-success mt-1"><strong>Escaneados:</strong> ${escapeHtml(
                asignacion.productos
              )}</div>`
            : ""
        }
        
        ${generarInfoEspecialAsignacion(
          asignacion,
          esFueraDeRejilla,
          esDesconocido
        )}
      </div>

      <div class="col-4 text-end">
        ${generarEstadisticasAsignacion(
          asignacion,
          esDesconocido,
          esFueraDeRejilla
        )}
      </div>
    </div>
    
    ${generarProgresoYBotonesAsignacion(
      asignacion,
      esDesconocido,
      esFueraDeRejilla,
      progresoAsignacion,
      dataAsignacionId,
      cliente
    )}
  `;
}

function generarInfoEspecialAsignacion(
  asignacion,
  esFueraDeRejilla,
  esDesconocido
) {
  if (esFueraDeRejilla) {
    return `
      <div class="small text-info mt-2">
        <i class="fas fa-map-marker-alt me-1"></i>
        <strong>Item fuera de rejilla de expedición</strong><br>
        • Agregado como DESCONOCIDO<br>
        • Requiere reasignación manual<br>
        • Sistema automático no pudo asignar
      </div>
    `;
  } else if (
    !esDesconocido &&
    (asignacion.cantidad_ya_despachada > 0 || asignacion.info_despachos)
  ) {
    return `
      <div class="small text-info mt-2">
        <i class="fas fa-history me-1"></i>
        <strong>Historial de despachos:</strong><br>
        ${
          asignacion.cantidad_ya_despachada > 0
            ? `• Ya despachado: ${asignacion.cantidad_ya_despachada} bobinas/unidades<br>`
            : ""
        }
        • Expedición actual: ${asignacion.cantidad_total_escaneada} bobinas/unidades<br>
        • Disponible: ${asignacion.cantidad_disponible || 0} bobinas/unidades
      </div>
    `;
  }
  return "";
}

function generarEstadisticasAsignacion(
  asignacion,
  esDesconocido,
  esFueraDeRejilla
) {
  if (!esDesconocido) {
    return `
      <div class="small">
        <div><strong>${
          asignacion.cantidad_total_escaneada || 0
        }</strong> de <strong>${
      asignacion.total_asignado || 0
    }</strong> bobinas/unidades</div>
        <div><strong>${
          asignacion.peso_total_formateado || "0.00 kg"
        }</strong></div>
        <div class="text-muted">de ${
          asignacion.peso_asignado_formateado || "0.00 kg"
        } asignados</div>
        ${
          asignacion.cantidad_ya_despachada > 0
            ? `<div class="text-success small">✓ ${asignacion.cantidad_ya_despachada} ya despachado</div>`
            : ""
        }
      </div>
    `;
  } else {
    return `
      <div class="small">
        <div><strong>${
          asignacion.cantidad_total_escaneada || 0
        }</strong> bobinas/unidades escaneadas</div>
        <div><strong>${
          asignacion.peso_total_formateado || "0.00 kg"
        }</strong></div>
        <div class="${esFueraDeRejilla ? "text-info" : "text-warning"}">${
      esFueraDeRejilla ? "Fuera de rejilla" : "Sin asignación específica"
    }</div>
      </div>
    `;
  }
}

function generarProgresoYBotonesAsignacion(
  asignacion,
  esDesconocido,
  esFueraDeRejilla,
  progresoAsignacion,
  dataAsignacionId,
  cliente
) {
  let html = "";

  if (!esDesconocido) {
    html += `
      <div class="mt-3">
        <div class="d-flex justify-content-between small mb-1">
          <span>Progreso de esta asignación</span>
          <span><strong>${progresoAsignacion.toFixed(1)}%</strong></span>
        </div>
        <div class="progress" style="height: 6px;">
          <div class="progress-bar ${
            progresoAsignacion > 100 ? "bg-danger" : "bg-primary"
          }" 
               style="width: ${Math.min(progresoAsignacion, 100)}%"></div>
        </div>
        <small class="${
          progresoAsignacion > 100 ? "text-danger" : "text-muted"
        } mt-1">
          ${
            progresoAsignacion > 100
              ? `⚠️ Excede asignación`
              : asignacion.estado === "completado"
              ? "✅ Asignación completada"
              : `${asignacion.cantidad_pendiente || 0} bobinas/unidades pendientes`
          }
        </small>
      </div>
    `;
  }

  // Botones de reasignación
  if (
    esDesconocido &&
    asignacion.cantidad_total_escaneada &&
    asignacion.cantidad_total_escaneada > 0
  ) {
    html += `
      <div class="mt-3">
        <button class="btn ${
          esFueraDeRejilla ? "btn-info" : "btn-warning"
        } btn-reasignar" 
                data-asignacion-id="${dataAsignacionId}"
                data-cliente="${escapeHtml(cliente)}"
                data-items-count="${asignacion.cantidad_total_escaneada || 0}"
                title="Reasignar ${
                  asignacion.cantidad_total_escaneada || 0
                } items DESCONOCIDOS">
          <i class="fas fa-exchange-alt me-1"></i>Reasignar ${
            asignacion.cantidad_total_escaneada || 0
          } Bobinas/Unidad ${esFueraDeRejilla ? "Fuera de Rejilla" : "DESCONOCIDOS"}
        </button>
      </div>
    `;
  } else if (!esDesconocido) {
    html += `
      <div class="mt-3">
        <small class="d-block text-muted mt-1">
          Items asignados automáticamente no se pueden mover
        </small>
      </div>
    `;
  } else {
    html += `
      <div class="mt-3">
        <button class="btn btn-outline-secondary btn-sm" disabled>
          <i class="fas fa-info-circle me-1"></i>Sin items para reasignar
        </button>
      </div>
    `;
  }

  return html;
}

// ===== ACTUALIZAR ESTADÍSTICAS DE VISTA PREVIA =====
function actualizarEstadisticasVistaPrevia(estadisticas) {
  const elementos = [
    {
      id: "totalItemsEscaneados",
      valor: estadisticas.total_items_escaneados || 0,
    },
    {
      id: "pesoTotalEscaneado",
      valor: (estadisticas.total_peso_escaneado || 0).toFixed(1),
    },
    { id: "resumenProductos", valor: estadisticas.total_clientes || 0 },
    { id: "resumenItems", valor: estadisticas.total_items_escaneados || 0 },
    { id: "resumenClientes", valor: estadisticas.total_clientes || 0 },
  ];

  elementos.forEach((elemento) => {
    const el = document.getElementById(elemento.id);
    if (el) {
      const valorAnterior = el.textContent;
      el.textContent = elemento.valor;

      if (valorAnterior !== elemento.valor.toString()) {
        el.closest(".card, .card-body")?.classList.add("resumen-actualizado");
        setTimeout(() => {
          el.closest(".card, .card-body")?.classList.remove(
            "resumen-actualizado"
          );
        }, 300);
      }
    }
  });
}

// ===== CARGAR ITEMS POR CLIENTE (FALLBACK) =====
function cargarItemsPorCliente(numeroExpedicion) {
  const contenedorClientes = document.getElementById("contenedorItemsClientes");

  if (!contenedorClientes) {
    logDebug("Contenedor de items por cliente no encontrado");
    return;
  }

  contenedorClientes.innerHTML =
    '<div class="text-center p-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando items por cliente...</div>';

  const formData = new FormData();
  formData.append("accion", "obtener_items_por_cliente");
  formData.append("numero_expedicion", numeroExpedicion);

  logDebug("Cargando items por cliente para expedición", numeroExpedicion);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarVistaPreviaClientes(data.items_por_cliente || []);
        logDebug("Items por cliente cargados", data.items_por_cliente);
      } else {
        contenedorClientes.innerHTML =
          '<div class="text-center text-danger p-3"><i class="fas fa-exclamation-triangle me-2"></i>Error: ' +
          (data.error || "Error desconocido") +
          "</div>";
        logDebug("Error cargando items por cliente", data);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      contenedorClientes.innerHTML =
        '<div class="text-center text-danger p-3"><i class="fas fa-exclamation-triangle me-2"></i>Error de conexión</div>';
      logDebug("Error de red cargando items por cliente", error);
    });
}

// ===== FUNCIONES DE REASIGNACIÓN =====
function configurarBotonesReasignarIndividuales() {
  const botonesAnteriores = document.querySelectorAll(
    '.btn-reasignar[data-configurado="true"]'
  );
  botonesAnteriores.forEach((boton) => {
    boton.replaceWith(boton.cloneNode(true));
  });

  const botonesReasignar = document.querySelectorAll(".btn-reasignar");

  botonesReasignar.forEach((boton) => {
    boton.setAttribute("data-configurado", "true");

    boton.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const asignacionId = this.dataset.asignacionId;
      const cliente = this.dataset.cliente;

      logDebug("Botón reasignar clickeado", { asignacionId, cliente });

      if (cliente !== "DESCONOCIDO") {
        mostrarToast(
          "❌ Solo items DESCONOCIDOS pueden ser reasignados",
          "warning"
        );
        logDebug("Intento de reasignar item no DESCONOCIDO bloqueado", {
          cliente,
        });
        return;
      }

      const textoOriginal = this.innerHTML;
      this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cargando...';
      this.disabled = true;

      const restaurarBoton = () => {
        this.innerHTML = textoOriginal;
        this.disabled = false;
      };

      if (!verificarModalMover()) {
        crearModalMoverItem();
        setTimeout(() => {
          buscarItemsDesconocidos(cliente);
          setTimeout(restaurarBoton, 2000);
        }, 100);
      } else {
        buscarItemsDesconocidos(cliente);
        setTimeout(restaurarBoton, 2000);
      }
    });
  });

  logDebug(
    "Botones de reasignación configurados (solo DESCONOCIDOS)",
    botonesReasignar.length
  );
}

function buscarItemsDesconocidos(cliente) {
  if (!expedicionActiva) {
    mostrarToast("❌ No hay expedición activa", "warning");
    return;
  }

  const formData = new FormData();
  formData.append("accion", "obtener_items_desconocidos");
  formData.append("numero_expedicion", expedicionActiva);

  logDebug("Buscando items DESCONOCIDOS", { expedicion: expedicionActiva });

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.items_ids && data.items_ids.length > 0) {
        logDebug("Items DESCONOCIDOS encontrados", data.items_ids);
        abrirModalMoverItems(cliente, data.items_ids);
      } else {
        mostrarToast("❌ No se encontraron items DESCONOCIDOS", "warning");
        logDebug("No hay items DESCONOCIDOS", data);
      }
    })
    .catch((error) => {
      console.error("Error buscando items DESCONOCIDOS:", error);
      mostrarToast("❌ Error obteniendo items para reasignar", "danger");
      logDebug("Error de red buscando items DESCONOCIDOS", error);
    });
}

// ===== MODAL DE MOVER ITEMS =====
function crearModalMoverItem() {
  const modalExistente = document.getElementById("modalMoverItem");
  if (modalExistente) {
    modalExistente.remove();
  }

  const modalHtml = `
    <div class="modal fade" id="modalMoverItem" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title">
              <i class="fas fa-exchange-alt me-2"></i>Reasignar Items DESCONOCIDOS
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              Moviendo <strong id="totalItemsMover">0</strong> items de <strong id="clienteOrigenMover">DESCONOCIDO</strong> a un cliente de la misma rejilla.
            </div>
            <div class="mb-3">
              <label class="form-label"><strong>Seleccionar cliente destino:</strong></label>
              <select class="form-select form-select-lg" id="nuevoClienteSelect" required>
                <option value="">Seleccione un cliente...</option>
              </select>
              <div class="form-text">Solo se muestran clientes con asignaciones activas en esta rejilla.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-warning btn-lg" id="btnConfirmarMover">
              <i class="fas fa-exchange-alt me-2"></i>Reasignar Items
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  modalMoverItem = new bootstrap.Modal(
    document.getElementById("modalMoverItem"),
    {
      backdrop: "static",
      keyboard: false,
    }
  );

  const btnConfirmarMover = document.getElementById("btnConfirmarMover");
  if (btnConfirmarMover) {
    btnConfirmarMover.addEventListener("click", ejecutarMoverItems);
  }

  const modalElement = document.getElementById("modalMoverItem");
  if (modalElement) {
    modalElement.addEventListener("shown.bs.modal", function () {
      const selectCliente = document.getElementById("nuevoClienteSelect");
      if (selectCliente) {
        selectCliente.focus();
      }
      logDebug("Modal mover items completamente abierto");
    });

    modalElement.addEventListener("hidden.bs.modal", function () {
      itemsParaMover = [];
      logDebug("Modal mover items cerrado");
    });
  }

  logDebug(
    "Modal mover items creado exitosamente (incluye items fuera de rejilla)"
  );
}

function verificarModalMover() {
  const modal = document.getElementById("modalMoverItem");
  if (!modal) {
    logDebug("Modal de mover items no encontrado");
    return false;
  }

  const elementos = [
    "clienteOrigenMover",
    "totalItemsMover",
    "nuevoClienteSelect",
    "btnConfirmarMover",
  ];

  for (let elementId of elementos) {
    if (!document.getElementById(elementId)) {
      logDebug(`Elemento ${elementId} no encontrado en modal`);
      return false;
    }
  }

  return true;
}

function cargarClientesMismaRejilla(selectCliente) {
  const formData = new FormData();
  formData.append("accion", "obtener_clientes_misma_rejilla");
  formData.append("numero_expedicion", expedicionActiva);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        selectCliente.innerHTML =
          '<option value="">Seleccione cliente...</option>';
        data.clientes.forEach((cliente) => {
          selectCliente.innerHTML += `<option value="${escapeHtml(
            cliente
          )}">${escapeHtml(cliente)}</option>`;
        });
      } else {
        selectCliente.innerHTML =
          '<option value="">Sin clientes disponibles en esta rejilla</option>';
      }
    })
    .catch((error) => {
      console.error("Error cargando clientes de la rejilla:", error);
      selectCliente.innerHTML =
        '<option value="">Error cargando clientes</option>';
    });
}

function abrirModalMoverItems(clienteOrigen, idsItems) {
  logDebug("=== INICIANDO APERTURA MODAL MOVER ===", {
    clienteOrigen,
    totalItems: idsItems.length,
    items: idsItems,
  });

  if (!verificarModalMover()) {
    logDebug("Modal no existe o está incompleto, creándolo...");
    crearModalMoverItem();

    setTimeout(() => {
      abrirModalMoverItemsInterno(clienteOrigen, idsItems);
    }, 200);
    return;
  }

  abrirModalMoverItemsInterno(clienteOrigen, idsItems);
}

function abrirModalMoverItemsInterno(clienteOrigen, idsItems) {
  if (!idsItems || idsItems.length === 0) {
    mostrarToast("❌ No hay items para reasignar", "warning");
    return;
  }

  const clienteOrigenElement = document.getElementById("clienteOrigenMover");
  const totalItemsElement = document.getElementById("totalItemsMover");
  const selectCliente = document.getElementById("nuevoClienteSelect");

  if (!clienteOrigenElement || !totalItemsElement || !selectCliente) {
    mostrarToast("❌ Error: Componentes del modal no encontrados", "danger");
    logDebug("Elementos del modal no encontrados");
    return;
  }

  clienteOrigenElement.textContent = clienteOrigen;
  totalItemsElement.textContent = idsItems.length;

  cargarClientesMismaRejilla(selectCliente);
  itemsParaMover = idsItems;

  if (modalMoverItem) {
    modalMoverItem.show();

    setTimeout(() => {
      if (selectCliente) {
        selectCliente.focus();
      }
    }, 300);

    logDebug("Modal mover items abierto exitosamente", {
      clienteOrigen,
      totalItems: idsItems.length,
    });
  } else {
    mostrarToast("❌ Error: No se pudo abrir el modal", "danger");
  }
}

function ejecutarMoverItems() {
  const selectCliente = document.getElementById("nuevoClienteSelect");
  const btnMover = document.getElementById("btnConfirmarMover");

  if (!selectCliente || !btnMover) {
    mostrarToast("❌ Error: Elementos del modal no encontrados", "danger");
    return;
  }

  const nuevoCliente = selectCliente.value;

  if (!nuevoCliente) {
    mostrarToast("❌ Debe seleccionar un cliente de la lista", "danger");
    selectCliente.focus();
    return;
  }

  if (itemsParaMover.length === 0) {
    mostrarToast("❌ No hay items para mover", "danger");
    return;
  }

  const textoOriginal = btnMover.innerHTML;
  btnMover.innerHTML =
    '<i class="fas fa-spinner fa-spin me-2"></i>Reasignando...';
  btnMover.disabled = true;

  let itemsMovidos = 0;
  let errores = 0;

  const moverSiguienteItem = (index) => {
    if (index >= itemsParaMover.length) {
      modalMoverItem.hide();

      if (errores === 0) {
        mostrarToast(
          `✅ ${itemsMovidos} items reasignados exitosamente a ${nuevoCliente}<br>📍 Incluye items que estaban fuera de rejilla`,
          "success",
          8000
        );
      } else {
        mostrarToast(
          `⚠️ ${itemsMovidos} items reasignados, ${errores} errores`,
          "warning"
        );
      }

      cargarVistaPreviaClientes(expedicionActiva);

      btnMover.innerHTML = textoOriginal;
      btnMover.disabled = false;

      return;
    }

    const idItem = itemsParaMover[index];
    const formData = new FormData();
    formData.append("accion", "mover_item_a_cliente");
    formData.append("id_expedicion_item", idItem);
    formData.append("nuevo_cliente", nuevoCliente);

    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          itemsMovidos++;
        } else {
          errores++;
          logDebug("Error moviendo item", { idItem, error: data.error });
        }
      })
      .catch((error) => {
        errores++;
        logDebug("Error de red moviendo item", { idItem, error });
      })
      .finally(() => {
        moverSiguienteItem(index + 1);
      });
  };

  moverSiguienteItem(0);
  logDebug("Iniciando reasignar items (incluye fuera de rejilla)", {
    nuevoCliente,
    totalItems: itemsParaMover.length,
  });
}

// Hacer funciones disponibles globalmente
window.cargarVistaPreviaClientes = cargarVistaPreviaClientes;
window.mostrarVistaPreviaClientes = mostrarVistaPreviaClientes;
window.configurarBotonesReasignarIndividuales =
  configurarBotonesReasignarIndividuales;
window.abrirModalMoverItems = abrirModalMoverItems;
