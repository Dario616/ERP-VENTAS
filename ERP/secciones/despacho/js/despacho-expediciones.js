// =====================================
// DESPACHO EXPEDICIONES - Gestión de expediciones
// =====================================

// ===== CREAR NUEVA EXPEDICIÓN =====
function crearNuevaExpedicion() {
  const form = document.getElementById("formNuevaExpedicion");
  const btnCrear = document.getElementById("btnCrearExpedicion");
  const rejillaSeleccionada = document.getElementById("selectRejilla").value;

  if (!validarFormularioExpedicion()) {
    mostrarToast(
      "❌ Complete el transportista y seleccione una rejilla",
      "danger"
    );
    return;
  }

  const textoOriginal = btnCrear.innerHTML;
  btnCrear.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando...';
  btnCrear.disabled = true;

  const formData = new FormData(form);
  formData.append("accion", "crear_expedicion");

  logDebug(
    "Enviando formulario de nueva expedición",
    Object.fromEntries(formData)
  );

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const rejillaTexto = document.querySelector(
          `#selectRejilla option[value="${rejillaSeleccionada}"]`
        ).textContent;

        let mensaje = `✅ Expedición creada: ${data.numero_expedicion}<br>📍 Rejilla asignada: ${rejillaTexto}<br>🤖 Modo automático activo<br>📍`;

        mostrarToast(mensaje, "success", 10000);
        modalNuevaExpedicion.hide();

        setTimeout(() => {
          location.reload();
        }, 2000);

        logDebug("Expedición creada exitosamente", data);
      } else {
        mostrarToast(
          "❌ Error: " + (data.error || "Error desconocido"),
          "danger"
        );
        logDebug("Error creando expedición", data);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      mostrarToast("❌ Error de conexión", "danger");
      logDebug("Error de red", error);
    })
    .finally(() => {
      btnCrear.innerHTML = textoOriginal;
      btnCrear.disabled = false;
    });
}

// ===== VALIDAR FORMULARIO DE EXPEDICIÓN =====
function validarFormularioExpedicion() {
  const transportista = document.querySelector('[name="transportista"]').value;
  const rejilla = document.querySelector('[name="id_rejilla"]').value;
  const btnCrear = document.getElementById("btnCrearExpedicion");

  const esValido = transportista.trim() !== "" && rejilla !== "";

  if (btnCrear) {
    btnCrear.disabled = !esValido;
  }

  return esValido;
}

// ===== DESPACHAR EXPEDICIÓN =====
function despacharExpedicion(numeroExpedicion) {
  try {
    const expedicionElement = document
      .querySelector(
        `button[onclick*="despacharExpedicion('${numeroExpedicion}')"]`
      )
      .closest(".expedicion-item");
    const statBadges = expedicionElement.querySelectorAll(".stat-badge");

    let totalItems = "0";
    let pesoTotal = "0";

    statBadges.forEach((badge) => {
      const texto = badge.textContent;
      if (texto.includes("items")) {
        totalItems = texto.match(/\d+/)[0];
      } else if (texto.includes("kg")) {
        pesoTotal = texto.match(/[\d.]+/)[0];
      }
    });

    document.getElementById("expedicionDespacho").textContent =
      numeroExpedicion;

    expedicionParaDespachar = numeroExpedicion;
    modalConfirmarDespacho.show();

    logDebug("Modal de confirmación de despacho preparado", {
      numeroExpedicion,
      totalItems,
      pesoTotal,
    });
  } catch (error) {
    console.error("Error preparando despacho:", error);
    mostrarToast("❌ Error preparando datos de despacho", "danger");
    logDebug("Error en despacharExpedicion", error);
  }
}

// ===== EJECUTAR DESPACHO =====
function ejecutarDespacho(numeroExpedicion) {
  const btnConfirmar = document.getElementById("btnConfirmarDespacho");
  const textoOriginal = btnConfirmar.innerHTML;

  btnConfirmar.innerHTML =
    '<i class="fas fa-spinner fa-spin me-2"></i>Despachando...';
  btnConfirmar.disabled = true;

  const formData = new FormData();
  formData.append("accion", "despachar_expedicion");
  formData.append("numero_expedicion", numeroExpedicion);

  logDebug("Ejecutando despacho", numeroExpedicion);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarToast(
          `✅ ${data.mensaje}<br>📦 Items despachados: ${
            data.total_items
          }<br>📍 Rejilla: #${data.rejilla}<br>🎯 Actualizaciones de cliente: ${
            data.actualizaciones_cliente || 0
          }<br>✅ Asignaciones completadas: ${
            data.asignaciones_completadas || 0
          }<br>📊 <strong>Incluye items que estaban fuera de rejilla</strong><br>📊 <strong>Sistema actualizado con despachos anteriores</strong>`,
          "success",
          10000
        );

        modalConfirmarDespacho.hide();

        if (expedicionActiva === numeroExpedicion && modalScanner) {
          modalScanner.hide();
        }

        setTimeout(() => {
          location.reload();
        }, 2000);

        logDebug("Despacho ejecutado exitosamente", data);
      } else {
        mostrarToast("❌ Error: " + data.error, "danger");
        logDebug("Error en despacho", data);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      mostrarToast("❌ Error de conexión", "danger");
      logDebug("Error de red en despacho", error);
    })
    .finally(() => {
      btnConfirmar.innerHTML = textoOriginal;
      btnConfirmar.disabled = false;
      expedicionParaDespachar = null;
    });
}

// ===== VER ITEMS DE EXPEDICIÓN =====
function verItemsExpedicion(numeroExpedicion) {
  const url = `ver.php?expedicion=${encodeURIComponent(numeroExpedicion)}`;
  window.open(url, "_blank");
  logDebug("Abriendo vista de items", numeroExpedicion);
}

// ===== FUNCIONES DE REJILLA =====
function cargarAsignacionesRejilla(idRejilla) {
  const contenedor = document.getElementById("contenedorItemsRejilla");
  const tablaBody = document.getElementById("tablaItemsRejilla");

  if (!idRejilla) {
    contenedor.style.display = "none";
    return;
  }

  contenedor.style.display = "block";
  tablaBody.innerHTML =
    '<tr><td colspan="4" class="text-center p-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando asignaciones...</td></tr>';

  const formData = new FormData();
  formData.append("accion", "obtener_asignaciones_rejilla");
  formData.append("id_rejilla", idRejilla);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.asignaciones) {
        mostrarAsignacionesRejilla(data.asignaciones);
      } else {
        tablaBody.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted p-3">No se encontraron asignaciones en esta rejilla</td></tr>';
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      tablaBody.innerHTML =
        '<tr><td colspan="4" class="text-center text-danger p-3"><i class="fas fa-exclamation-triangle me-2"></i>Error cargando asignaciones</td></tr>';
    });
}

function mostrarAsignacionesRejilla(asignaciones) {
  const tablaBody = document.getElementById("tablaItemsRejilla");

  if (asignaciones.length === 0) {
    tablaBody.innerHTML =
      '<tr><td colspan="4" class="text-center text-muted p-3">No hay asignaciones en esta rejilla</td></tr>';
    return;
  }

  let html = "";
  let pesoTotal = 0;
  let cantTotal = 0;

  asignaciones.forEach((asignacion) => {
    pesoTotal += parseFloat(asignacion.peso_asignado || 0);
    cantTotal += parseFloat(asignacion.cant_uni || 0);

    html += `
      <tr>
        <td><strong>${escapeHtml(
          asignacion.nombre_producto || "Sin especificar"
        )}</strong><br><small class="text-muted">${escapeHtml(
      asignacion.cliente || "Sin cliente"
    )}</small></td>
        <td class="text-center"><strong>${parseFloat(
          asignacion.peso_asignado
        ).toFixed(1)} kg</strong></td>
        <td class="text-center"><strong>${parseFloat(
          asignacion.cant_uni
        ).toFixed(0)}</strong></td>
      </tr>
    `;
  });

  html += `
    <tr class="table-success fw-bold">
      <td><strong>TOTAL ASIGNADO</strong></td>
      <td class="text-center"><strong>${pesoTotal.toFixed(1)} kg</strong></td>
      <td class="text-center"><strong>${cantTotal.toFixed(0)}</strong></td>
    </tr>
  `;

  tablaBody.innerHTML = html;
}

function limpiarVisualizacionRejilla() {
  const contenedor = document.getElementById("contenedorItemsRejilla");
  if (contenedor) contenedor.style.display = "none";
}

function cargarItemsRejilla(idRejilla = null) {
  const selectRejilla = document.getElementById("selectRejilla");
  const rejillaId = idRejilla || selectRejilla?.value;

  if (rejillaId) {
    cargarAsignacionesRejilla(rejillaId);
  } else {
    limpiarVisualizacionRejilla();
  }
}

function limpiarFormularioExpedicion() {
  const form = document.getElementById("formNuevaExpedicion");
  if (form) {
    form.reset();
  }
  validarFormularioExpedicion();
}

// ===== ELIMINAR EXPEDICIÓN =====
function eliminarExpedicion(numeroExpedicion) {
  // Confirmación mejorada
  const mensaje =
    `¿Está seguro de eliminar la expedición ${numeroExpedicion}?\n\n` +
    `⚠️ Esta acción no se puede deshacer\n` +
    `📍 Solo se pueden eliminar expediciones vacías (sin items)`;

  if (!confirm(mensaje)) {
    return;
  }

  // Mostrar indicador de carga en el botón
  const boton = event.target;
  const textoOriginal = boton.innerHTML;
  boton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Eliminando...';
  boton.disabled = true;

  const formData = new FormData();
  formData.append("accion", "eliminar_expedicion");
  formData.append("numero_expedicion", numeroExpedicion);

  logDebug("Eliminando expedición vacía", numeroExpedicion);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarToast(
          `✅ Expedición ${numeroExpedicion} eliminada exitosamente<br>` +
            `📦 La expedición estaba vacía (sin items)`,
          "success",
          5000
        );

        setTimeout(() => {
          location.reload();
        }, 1500);

        logDebug("Expedición vacía eliminada exitosamente", data);
      } else {
        // Restaurar botón en caso de error
        boton.innerHTML = textoOriginal;
        boton.disabled = false;

        // Mensaje específico si tiene items
        if (data.error.includes("items escaneados")) {
          mostrarToast(
            "❌ No se puede eliminar<br>" +
              "📦 La expedición tiene items escaneados<br>" +
              "💡 Debe estar vacía para poder eliminarla",
            "warning",
            6000
          );
        } else {
          mostrarToast("❌ Error: " + data.error, "danger");
        }

        logDebug("Error eliminando expedición", data);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      // Restaurar botón
      boton.innerHTML = textoOriginal;
      boton.disabled = false;
      mostrarToast("❌ Error al eliminar la expedición", "danger");
      logDebug("Error de red eliminando expedición", error);
    });
}

// Agregar al final con las otras exportaciones globales
window.eliminarExpedicion = eliminarExpedicion;

// Función para abrir PDF de resumen
function abrirResumenPDF(numeroExpedicion) {
  const url = `pdf/resumen.php?expedicion=${encodeURIComponent(
    numeroExpedicion
  )}`;
  window.open(url, "_blank");

  const toast = document.createElement("div");
  toast.className = "alert alert-info position-fixed";
  toast.style.cssText =
    "top: 20px; right: 20px; z-index: 9999; min-width: 300px; opacity: 0.95;";
  toast.innerHTML = `
    <i class="fas fa-file-pdf me-2"></i>
    Abriendo PDF de resumen para expedición automática ${numeroExpedicion}
  `;
  document.body.appendChild(toast);

  setTimeout(() => {
    if (toast.parentNode) {
      toast.remove();
    }
  }, 3000);
}

// Hacer funciones disponibles globalmente
window.crearNuevaExpedicion = crearNuevaExpedicion;
window.validarFormularioExpedicion = validarFormularioExpedicion;
window.despacharExpedicion = despacharExpedicion;
window.ejecutarDespacho = ejecutarDespacho;
window.verItemsExpedicion = verItemsExpedicion;
window.cargarItemsRejilla = cargarItemsRejilla;
window.abrirResumenPDF = abrirResumenPDF;
